<?php

namespace App\Imports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\AfterImport;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Import update rincian DO (versi PASURUAN) — logikanya sama dengan UpdateQtyPgiImport.
 *
 * 1 baris Excel = 1 DO = 1 baris di logistik_pengiriman_pasuruan.
 * Kunci: no_shipment + tujuan + no_do (kolom DB: no_delivery_pasuruan).
 *   - Baris lama yang no_do-nya masih kosong dipakai untuk DO pertama di tujuan itu.
 *   - DO berikutnya jadi baris baru (data shipment disalin dari baris saudaranya).
 *   - no_shipment / tujuan yang belum ada di DB dibuat baris baru, seperti import biasa.
 *
 * Nilai kosong di Excel tidak menimpa data lama. Tanggal, gudang, reason tidak disentuh.
 * ekpedisi, route, mobil : Excel menang (diupdate ke semua baris di shipment itu).
 * Nama ekspedisi dari Excel disamakan dengan nama di master tarif kalau maksudnya sama.
 *
 * kirim_kontribusi per DO = biaya 1 mobil x total_kubik DO / SUM total_kubik shipment
 * biaya_kirim per tujuan  = SUM kirim_kontribusi DO di tujuan itu
 *
 * PERBAIKAN (tarif Pasuruan):
 *   - Tarif hanya dari tabel tarif_pengiriman (kubikasi & tonase saja, biaya_kirim tidak dipakai).
 *   - findTarif(): route fuzzy, mobil dua arah, fallback kapasitas, dan SETIAP kegagalan dicatat
 *     di $tarifNotFound dengan alasannya (sebelumnya gagal tanpa jejak).
 *   - AfterImport: satu langkah gagal tidak mematikan langkah berikutnya.
 */
class UpdateQtyPgiPasuruanImport implements ToCollection, WithHeadingRow, WithEvents, WithCalculatedFormulas
{
    private const TABLE = 'logistik_pengiriman_pasuruan';
    private const SUF   = '_pasuruan';

    // nama logis => kolom DB, untuk yang namanya tidak sekadar "<nama>_pasuruan"
    private const COLMAP = [
        'no_do'                 => 'no_delivery_pasuruan',
        'total_do_qty_car'      => 'total_do_pasuruan',
        'ekpedisi'              => 'ekspedisi_pasuruan',        // di Pasuruan ejaannya "ekspedisi"
        'tgl_rencana_kirim'     => 'rencana_kirim_pasuruan',
        'tgl_terima_dari_admin' => 'tanggal_terima_po_pasuruan',
    ];

    // tabel master tujuan (kolom master TIDAK pakai suffix)
    private const MASTER_TABLE      = 'tujuanfillterr';
    private const MASTER_TUJUAN_COL = 'tujuan';

    // kolom_di_master => nama logis di logistik_pengiriman_pasuruan (hanya mengisi yang kosong pada baris BARU)
    private const MASTER_MAP = [
        'pulau'               => 'pulau',
        'area'                => 'area',
        'planner'             => 'planner',
        'monitoring'          => 'pic_monitoring',
        'transport_lead_time' => 'transport_lead_time',
        'dist_channel'        => 'dist_channel',
    ];

    private const TARIF_TABLE = 'tarif_pengiriman';

    private const ROUTE_ALIASES = [
        'jabodetabek' => 'Sentul-Jabodetabek',
    ];
    private const EKSPEDISI_ALIASES = [
        // 'namadiexcel' => 'PT Nama Di Master',
    ];
    private const MONTHS = [
    'januari' => 1, 'jan' => 1, 'january' => 1,
    'februari' => 2, 'feb' => 2, 'february' => 2,
    'maret' => 3, 'mar' => 3, 'march' => 3,
    'april' => 4, 'apr' => 4,
    'mei' => 5, 'may' => 5,
    'juni' => 6, 'jun' => 6, 'june' => 6,
    'juli' => 7, 'jul' => 7, 'july' => 7,
    'agustus' => 8, 'agu' => 8, 'agt' => 8, 'aug' => 8, 'august' => 8,
    'september' => 9, 'sep' => 9, 'sept' => 9,
    'oktober' => 10, 'okt' => 10, 'oct' => 10, 'october' => 10,
    'november' => 11, 'nov' => 11, 'nopember' => 11,
    'desember' => 12, 'des' => 12, 'dec' => 12, 'december' => 12,
];

    // Excel menang kalau beda dengan DB (berlaku ke semua baris di shipment itu)
    private const OVERWRITE_SHIPMENT = ['ekpedisi', 'route', 'mobil'];

    // hanya diisi kalau di DB masih kosong
    private const FILL_IF_EMPTY = [
        'pulau',
        'area',
        'dist_channel',
        'via_kirim',
        'kategori_ekspedisi',
        'nama_driver',
        'no_pol',
        'planner',
        'pic_monitoring',
        'transport_lead_time',
        'shipping_point',
        'tgl_rencana_kirim',
        'tgl_terima_dari_admin',
        'tanggal_dpt_unit',
        'planning_loading',
        'tanggal_tiba_di_gudang',
        'tanggal_keluar_gudang',
    ];

    // field di atas yang berisi tanggal
    private const DATE_FIELDS = [
        'tgl_rencana_kirim',
        'tgl_terima_dari_admin',
        'tanggal_dpt_unit',
        'planning_loading',
        'tanggal_tiba_di_gudang',
        'tanggal_keluar_gudang',
    ];

    // header Excel alternatif (file pivot / template Pasuruan)
    private const SOURCE_ALIASES = [
        'ekpedisi'  => ['ekpedisi', 'ekspedisi', 'vendor'],
        'route'     => ['route', 'route_name'],
        'mobil'     => ['mobil', 'shipping_type'],
        'via_kirim' => ['via_kirim', 'via'],
        'no_pol'    => ['no_pol', 'nopol'],
        'pic_monitoring'      => ['pic_monitoring', 'monitoring'],
        'transport_lead_time' => ['transport_lead_time', 'lead_time'],
        'tgl_rencana_kirim'      => ['tgl_rencana_kirim', 'rencana_kirim'],
        'tgl_terima_dari_admin'  => ['tgl_terima_dari_admin', 'tanggal_terima_po', 'tanggal_naik_logistik'],
        'tanggal_dpt_unit'       => ['tanggal_dpt_unit', 'tanggal_dapat_unit'],
        'planning_loading'       => ['planning_loading', 'planning_loading_pasuruan'],
        'tanggal_tiba_di_gudang' => ['tanggal_tiba_di_gudang', 'tanggal_tiba_pasuruan'],
        'tanggal_keluar_gudang'  => ['tanggal_keluar_gudang', 'tanggal_keluar_pasuruan'],
    ];

    private static $masterByTujuan = null;
    private static $tarifByRoute   = null;
    private static $ekspedisiMaster = null;   // daftar nama ekspedisi di master tarif

    private array $tujuanNotInMaster = [];
    private array $ekspedisiResolved = [];
    private array $ekspedisiCanon    = [];   // nama Excel -> nama master (cache)

    private ?array $tableCols = null;
    private array $droppedCols = [];

    private bool $hasOptimalCustomer       = false;
    private bool $hasReasonOptimalCustomer = false;
    private bool $hasKirimKontribusi       = false;
    private bool $hasCreatedAt             = false;
    private bool $hasUpdatedAt             = false;

    // ===== hasil proses qty / kubik-tonase (per DO) =====
    private int $qtyUpdated = 0;
    private array $qtyNotFound = [];
    private array $qtyAmbiguous = [];
    private int $qtySkipped = 0;

    // ===== hasil proses act_pgi_date (kunci: no_shipment) =====
    private int $pgiUpdated = 0;
    private array $pgiNotFound = [];
    private int $pgiSkipped = 0;

    private int $kubikTonaseUpdated = 0;
    private array $kubikTonaseNotFound = [];
    private int $kubikTonaseSkipped = 0;

    // ===== hasil proses GENERAL (per DO) =====
    private int $generalCreated = 0;
    private int $generalUpdated = 0;
    private int $generalUnchanged = 0;
    private array $generalAmbiguous = [];
    private int $generalSkipped = 0;
    private array $createdList = [];

    // ===== hasil recalc biaya =====
    private int $biayaKirimUpdated = 0;
    private array $biayaSkipped = [];
    private array $tarifNotFound = [];
    private array $tarifAmbiguous = [];

    private array $usedIds = [];   // id baris DB yang sudah dipakai oleh baris Excel sebelumnya di import ini

    private array $pgiProcessed = [];
    private array $touchedShipments = [];
    private array $groupBiaya = [];
    private array $kubikChanged = [];
    private array $shipmentTujuan = [];
    private array $shipmentShared = [];
    private array $capacityRefresh = [];
    private array $shipmentDate = [];     // no_shipment => ['tanggal' => ..., 'periode' => ...]
    private int $dateUpdated = 0;
    private array $dateNotFound = [];

    public function getDateUpdated(): int
    {
        return $this->dateUpdated;
    }
    public function getDateNotFound(): array
    {
        return $this->dateNotFound;
    }

    // ================= GETTERS =================
    public function getQtyUpdated(): int
    {
        return $this->qtyUpdated;
    }
    public function getQtyNotFound(): array
    {
        return $this->qtyNotFound;
    }
    public function getQtyAmbiguous(): array
    {
        return $this->qtyAmbiguous;
    }
    public function getQtySkipped(): int
    {
        return $this->qtySkipped;
    }

    public function getPgiUpdated(): int
    {
        return $this->pgiUpdated;
    }
    public function getPgiNotFound(): array
    {
        return $this->pgiNotFound;
    }
    public function getPgiSkipped(): int
    {
        return $this->pgiSkipped;
    }

    public function getKubikTonaseUpdated(): int
    {
        return $this->kubikTonaseUpdated;
    }
    public function getKubikTonaseNotFound(): array
    {
        return $this->kubikTonaseNotFound;
    }
    public function getKubikTonaseSkipped(): int
    {
        return $this->kubikTonaseSkipped;
    }

    public function getGeneralCreated(): int
    {
        return $this->generalCreated;
    }
    public function getGeneralUpdated(): int
    {
        return $this->generalUpdated;
    }
    public function getGeneralUnchanged(): int
    {
        return $this->generalUnchanged;
    }
    public function getGeneralAmbiguous(): array
    {
        return $this->generalAmbiguous;
    }
    public function getGeneralSkipped(): int
    {
        return $this->generalSkipped;
    }

    // alias dari versi sebelumnya
    public function getInserted(): int
    {
        return $this->generalCreated;
    }
    public function getInsertedList(): array
    {
        return $this->createdList;
    }

    public function getBiayaKirimUpdated(): int
    {
        return $this->biayaKirimUpdated;
    }
    public function getBiayaSkipped(): array
    {
        return $this->biayaSkipped;
    }
    public function getTarifNotFound(): array
    {
        return $this->tarifNotFound;
    }
    public function getTarifAmbiguous(): array
    {
        return $this->tarifAmbiguous;
    }
    public function getTujuanNotInMaster(): array
    {
        return $this->tujuanNotInMaster;
    }

    public function __construct()
    {
        $this->hasOptimalCustomer       = Schema::hasColumn(self::TABLE, 'pengiriman_optimal_customer_pasuruan');
        $this->hasReasonOptimalCustomer = Schema::hasColumn(self::TABLE, 'reason_optimal_customer_pasuruan');
        $this->hasKirimKontribusi       = Schema::hasColumn(self::TABLE, 'kirim_kontribusi_pasuruan');
        $this->hasCreatedAt             = Schema::hasColumn(self::TABLE, 'created_at');
        $this->hasUpdatedAt             = Schema::hasColumn(self::TABLE, 'updated_at');

        // tarif_pengiriman dipakai hanya untuk kubikasi & tonase (biaya_kirim tidak perlu)
        if (self::$tarifByRoute === null) {
            self::$tarifByRoute = DB::table(self::TARIF_TABLE)
                ->select('ekpedisi', 'route', 'mobil', 'kubikasi', 'tonase')
                ->get()
                ->groupBy(fn($row) => $this->normalize($row->route));
        }

        if (self::$masterByTujuan === null) {
            self::$masterByTujuan = Schema::hasTable(self::MASTER_TABLE)
                ? DB::table(self::MASTER_TABLE)->get()
                ->groupBy(fn($r) => $this->normalizeTujuan($r->{self::MASTER_TUJUAN_COL}))
                : collect();
        }
    }

    // ================= KOLOM DB =================

    /** nama logis -> nama kolom di tabel pasuruan */
    private function c(string $k): string
    {
        return self::COLMAP[$k] ?? ($k . self::SUF);
    }

    private function tableCols(): array
    {
        return $this->tableCols ??= Schema::getColumnListing(self::TABLE);
    }

    private function hasCol(string $col): bool
    {
        return in_array($col, $this->tableCols(), true);
    }

    /** buang key yang bukan kolom tabel (supaya tidak error SQL) */
    private function onlyCols(array $a): array
    {
        foreach (array_diff_key($a, array_flip($this->tableCols())) as $k => $_) {
            $this->droppedCols[$k] = true;
        }

        return array_intersect_key($a, array_flip($this->tableCols()));
    }

    /** kolom yang dicoba ditulis tapi TIDAK ada di tabel (nama kolom salah tebak) */
    public function getDroppedCols(): array
    {
        return array_keys($this->droppedCols);
    }

    /** huruf kecil, tanda baca dan spasi ganda dibuang */
    private function normalizeTujuan(?string $v): string
    {
        $v = strtolower((string) $v);
        return trim(preg_replace('/[^a-z0-9]+/', ' ', $v));
    }

    /** atribut master (sudah dalam nama kolom pasuruan) */
    private function masterAttributes(string $tujuan): array
    {
        $rows = self::$masterByTujuan[$this->normalizeTujuan($tujuan)] ?? null;

        if (!$rows) {
            if (!in_array($tujuan, $this->tujuanNotInMaster, true)) {
                $this->tujuanNotInMaster[] = $tujuan;
            }
            return [];
        }

        $master = $rows->first();
        $out = [];
        foreach (self::MASTER_MAP as $src => $dst) {
            $v = $this->cleanText($master->{$src} ?? null);
            if ($v !== null) {
                $out[$this->c($dst)] = $v;
            }
        }
        return $out;
    }

    // ================= EVENTS =================

    public function registerEvents(): array
    {
        return [
            AfterImport::class => function () {
                $shipments = array_map('strval', array_keys($this->touchedShipments));

                // tiap langkah dibungkus sendiri: satu gagal tidak mematikan yang lain
                $steps = [
                    'processShipmentDate'   => fn() => $this->processShipmentDate(),
                    'processShipmentFields' => fn() => $this->processShipmentFields(),
                    'recalcTarifCapacity'   => fn() => $this->recalcTarifCapacity($shipments),
                    'recalcBiayaKontribusi' => fn() => $this->recalcBiayaKontribusi(),
                    'recalcShipments'       => fn() => $this->recalcShipments($shipments),
                ];

                foreach ($steps as $name => $step) {
                    try {
                        $step();
                    } catch (\Throwable $e) {
                        logger()->error("UPDATE IMPORT PASURUAN {$name} GAGAL", [
                            'error' => $e->getMessage(),
                            'line'  => $e->getLine(),
                        ]);
                    }
                }

                if ($this->tarifNotFound) {
                    logger()->warning('UPDATE IMPORT PASURUAN TARIF TIDAK KETEMU', [
                        'jumlah' => count($this->tarifNotFound),
                        'daftar' => array_slice($this->tarifNotFound, 0, 50),
                    ]);
                }
            },
        ];
    }
    /** ambil tanggal & periode shipment dari baris Excel (yang pertama terisi per shipment) */
   private function collectShipmentDate($row, string $sh): void
{
    $tgl  = $this->convertDateUs($this->pick($row, ['tanggal_shipment']));
    $year = $tgl !== null ? date('Y', strtotime($tgl)) : null;
    $per  = $this->convertPeriode($this->pick($row, ['periode_shipment']), $year);

    // periode kosong tapi tanggal ada -> periode diambil dari tanggal
    if ($per === null && $tgl !== null) {
        $per = date('m-Y', strtotime($tgl));
    }

    if ($tgl !== null) $this->shipmentDate[$sh]['tanggal'] ??= $tgl;
    if ($per !== null) $this->shipmentDate[$sh]['periode'] ??= $per;
}
    /** tulis tanggal & periode ke semua baris dengan no_shipment itu */
    private function processShipmentDate(): void
    {
        $cShip = $this->c('no_shipment');
        $cTgl  = $this->c('tanggal_shipment');
        $cPer  = $this->c('periode_shipment');

        foreach ($this->shipmentDate as $sh => $v) {
            $payload = $this->onlyCols(array_filter([
                $cTgl => $v['tanggal'] ?? null,
                $cPer => $v['periode'] ?? null,
            ], fn($x) => $x !== null));

            if (!$payload) continue;
            if ($this->hasUpdatedAt) $payload['updated_at'] = now();

            $affected = DB::table(self::TABLE)->where($cShip, (string) $sh)->update($payload);

            if ($affected > 0) {
                $this->dateUpdated += $affected;
            } elseif (!DB::table(self::TABLE)->where($cShip, (string) $sh)->exists()) {
                $this->dateNotFound[] = (string) $sh;   // shipment tidak ada di DB
            }
        }
    }

    /** periode shipment = teks "MM-YYYY" */
  /** periode shipment = teks "MM-YYYY"; $fallbackYear dipakai kalau cuma ada nama bulan */
private function convertPeriode($value, ?string $fallbackYear = null): ?string
{
    if ($value === null || $value === '' || $value === '-') return null;

    if ($value instanceof \DateTimeInterface) return $value->format('m-Y');

    if (is_numeric($value)) {
        return (float) $value > 0 ? Date::excelToDateTimeObject($value)->format('m-Y') : null;
    }

    $v = trim((string) $value);

    if (preg_match('#^(\d{1,2})[-/](\d{4})$#', $v, $m) && (int) $m[1] >= 1 && (int) $m[1] <= 12) {
        return sprintf('%02d-%04d', (int) $m[1], (int) $m[2]);
    }
    if (preg_match('#^(\d{4})[-/](\d{1,2})$#', $v, $m) && (int) $m[2] >= 1 && (int) $m[2] <= 12) {
        return sprintf('%02d-%04d', (int) $m[2], (int) $m[1]);
    }

    // nama bulan: "November" atau "November 2026"
    if (preg_match('/^([a-z]+)\.?\s*(\d{4})?$/i', $v, $m)) {
        $mon = self::MONTHS[strtolower($m[1])] ?? null;
        if ($mon !== null) {
            $year = $m[2] ?? $fallbackYear;
            return $year ? sprintf('%02d-%04d', $mon, (int) $year) : null;
        }
    }

    $ts = strtotime($v);
    return $ts ? date('m-Y', $ts) : null;
}
    // ================= MAIN =================

    public function collection(Collection $rows)
    {
        logger()->info('DEBUG HEADER', [
            'keys'  => $rows->first() ? $rows->first()->keys()->all() : 'KOSONG',
            'first' => $rows->first() ? $rows->first()->toArray() : null,
            'count' => $rows->count(),
        ]);
        foreach ($rows as $row) {

            $noShipment = $this->cleanText($this->pick($row, ['no_shipment', 'nomer_shipment']));

            if (empty($noShipment)) {
                $this->generalSkipped++;
                $this->pgiSkipped++;
                $this->qtySkipped++;
                $this->kubikTonaseSkipped++;
                continue;
            }

            $noShipment = (string) $noShipment;
            $this->collectShipmentDate($row, $noShipment);
            $this->touchedShipments[$noShipment] = true;

            // biaya 1 mobil (diisi satu baris saja per shipment)
            $pool = $this->parseNumber($this->pick($row, ['biaya_kirim', 'biaya_kirim_rp', 'kirim_kontribusi']));
            if ($pool !== null && $pool > 0) {
                $this->groupBiaya[$noShipment] = $pool;
            }

            $tujuan = $this->cleanText($this->pick($row, ['tujuan', 'sold_to_party_name']));

            if (empty($tujuan)) {
                $this->generalSkipped++;
            } else {
                $this->shipmentTujuan[$noShipment][strtolower($tujuan)] = true;
                $this->collectShared($row, $noShipment);

                try {
                    $this->processGeneral($row, $noShipment, $tujuan);
                } catch (\Throwable $e) {
                    $this->generalAmbiguous[] = "{$noShipment} - {$tujuan} (ERROR: " . mb_substr($e->getMessage(), 0, 120) . ')';
                    logger()->error('UPDATE IMPORT PASURUAN GAGAL PER BARIS', [
                        'no_shipment' => $noShipment,
                        'tujuan'      => $tujuan,
                        'error'       => $e->getMessage(),
                        'line'        => $e->getLine(),
                    ]);
                }
            }

            // ACT PGI DATE (kunci: no_shipment)
            $pgiDate = $this->convertDate($this->pick($row, ['act_pgi_date']));

            if ($pgiDate !== null && !isset($this->pgiProcessed[$noShipment]) && $this->hasCol($this->c('act_pgi_date'))) {
                $affected = DB::table(self::TABLE)
                    ->where($this->c('no_shipment'), $noShipment)
                    ->update([$this->c('act_pgi_date') => $pgiDate]);

                if ($affected === 0) {
                    $this->pgiNotFound[] = $noShipment;
                } else {
                    $this->pgiUpdated += $affected;
                }
                $this->pgiProcessed[$noShipment] = true;
            } elseif ($pgiDate === null) {
                $this->pgiSkipped++;
            }
        }
    }

    /** sumber nilai Excel untuk sebuah kolom (dengan alias header) */
    private function src($row, string $col)
    {
        if (in_array($col, self::DATE_FIELDS, true)) {
            return $this->convertDate($this->pick($row, self::SOURCE_ALIASES[$col] ?? [$col]));
        }

        $v = $this->cleanText($this->pick($row, self::SOURCE_ALIASES[$col] ?? [$col]));

        if ($col === 'ekpedisi') return $this->canonEkspedisi($v);

        return $col === 'route' ? $this->applyRouteAlias($v) : $v;
    }

    /**
     * Nama ekspedisi dari Excel disamakan dengan nama di master (tarif_pengiriman)
     * kalau maksudnya sama (beda awalan PT/CV, titik, spasi, huruf besar-kecil,
     * atau salah ketik kecil). Kalau tidak yakin / tidak ada yang mirip,
     * nama dari Excel dipakai apa adanya.
     */
    private function canonEkspedisi(?string $v): ?string
    {
        if ($v === null || $v === '') return $v;

        if (array_key_exists($v, $this->ekspedisiCanon)) {
            return $this->ekspedisiCanon[$v];
        }

        if (self::$ekspedisiMaster === null) {
            self::$ekspedisiMaster = self::$tarifByRoute->flatten(1)
                ->pluck('ekpedisi')->filter()->unique()->values()
                ->map(fn($n) => (object) ['ekpedisi' => $n]);
        }

        $match = $this->resolveEkspedisiName($v, self::$ekspedisiMaster);

        return $this->ekspedisiCanon[$v] = ($match ?? $v);
    }

    private function collectShared($row, string $sh): void
    {
        foreach (self::OVERWRITE_SHIPMENT as $col) {
            $v = $this->src($row, $col);
            if ($v !== null) {
                $this->shipmentShared[$sh][$col] ??= $v;
            }
        }
    }

    private function isBlank($v): bool
    {
        return $v === null || trim((string) $v) === '';
    }

    // ================= EKSPEDISI / ROUTE / MOBIL: Excel menang, per shipment =================

    private function processShipmentFields(): void
    {
        foreach ($this->shipmentShared as $sh => $vals) {
            $vals = array_filter($vals, fn($v) => $v !== null);
            if (!$vals) continue;

            $where = [];
            foreach ($vals as $col => $v) {
                $where[$this->c($col)] = $v;
            }
            $where = $this->onlyCols($where);
            if (!$where) continue;

            $payload = $where;
            if ($this->hasUpdatedAt) $payload['updated_at'] = now();

            $affected = DB::table(self::TABLE)
                ->where($this->c('no_shipment'), (string) $sh)
                ->where(function ($q) use ($where) {
                    foreach ($where as $col => $v) {
                        $q->orWhereNull($col)->orWhere($col, '!=', $v);
                    }
                })
                ->update($payload);

            if ($affected > 0) {
                $this->capacityRefresh[(string) $sh] = true;
            }
        }
    }

    // ================= UPSERT PER DO =================

    private function processGeneral($row, string $noShipment, string $tujuan): void
    {
        $noDo = $this->cleanText($this->pick($row, ['no_do', 'no_delivery']));
        $qty  = $this->parseNumber($this->pick($row, ['total_do_qty_car', 'total_do', 'delivery_quantity', 'sum_of_qty_do']));

        // field per DO (kunci logis; kolom DB lewat $this->c())
        $perDo = [
            'sales_document'     => $this->cleanText($this->pick($row, ['sales_document'])),
            'customer_reference' => $this->cleanText($this->pick($row, ['customer_reference'])),
            'contract_end_date'  => $this->convertDateUs($this->pick($row, ['contract_end_date'])),
            'no_do'              => $noDo,
            'nilai_muatan'       => $this->parseNumber($this->pick($row, ['nilai_muatan_rp', 'nilai_muatan', 'sum_of_nilai_do'])),
            'total_do_qty_car'   => $qty !== null ? (int) round($qty) : null,
            'total_kubik'        => $this->parseNumber($this->pick($row, ['total_kubik', 'total_kubikasi', 'sum_of_kubikasi2'])),
            'total_tonase'       => $this->parseNumber($this->pick($row, ['total_tonase', 'sum_of_tonase3'])),
        ];
        // tanpa total_kubik = bukan baris DO sungguhan (mis. baris pembawa biaya).
        // Jangan insert dan jangan update apa pun, biarkan data yang sudah ada.
        if ($perDo['total_kubik'] === null || (float) $perDo['total_kubik'] <= 0) {
            $this->generalSkipped++;
            return;
        }
        // field milik shipment / tujuan
        $shared = [];
        foreach (array_merge(self::OVERWRITE_SHIPMENT, self::FILL_IF_EMPTY) as $col) {
            $shared[$col] = $this->src($row, $col);
        }

        $cShip = $this->c('no_shipment');
        $cTuj  = $this->c('tujuan');
        $cNoDo = $this->c('no_do');

        $base = DB::table(self::TABLE)
            ->where($cShip, $noShipment)
            ->where($cTuj, $tujuan);

        // cari baris DO ini
        if ($noDo !== null) {
            $existing = (clone $base)->where($cNoDo, $noDo)->first()
                // baris lama yang no_do-nya masih kosong dipakai untuk DO pertama
                ?? (clone $base)->where(fn($q) => $q->whereNull($cNoDo)->orWhere($cNoDo, ''))
                ->orderBy('id')->first();
        } else {
            // template tanpa no_do: 1 baris Excel = 1 DO, dicocokkan lewat sidik jari
            // (qty + nilai muatan + kubik + tonase). Tidak cocok -> jadi baris baru.
            $existing = $this->matchWithoutDo($base, $perDo);
        }

        // ===== UPDATE =====
        if ($existing) {
            $this->usedIds[$existing->id] = true;
            $payload = [];

            foreach ($perDo as $col => $new) {
                if ($new === null) continue;
                $dbc = $this->c($col);
                if (!$this->valuesEqual($existing->{$dbc} ?? null, $new)) {
                    $payload[$dbc] = $new;
                }
            }

            // field lain: hanya isi kalau di DB masih kosong
            foreach (self::FILL_IF_EMPTY as $col) {
                $new = $shared[$col] ?? null;
                $dbc = $this->c($col);
                if ($new !== null && $this->isBlank($existing->{$dbc} ?? null)) {
                    $payload[$dbc] = $new;
                }
            }

            if (isset($payload[$this->c('total_kubik')])) $this->kubikChanged[$noShipment] = true;
            if (isset($payload[$this->c('total_do_qty_car')])) $this->qtyUpdated++;
            if (isset($payload[$this->c('total_kubik')]) || isset($payload[$this->c('total_tonase')])) $this->kubikTonaseUpdated++;

            $payload = $this->onlyCols($payload);

            if (!empty($payload)) {
                if ($this->hasUpdatedAt) $payload['updated_at'] = now();
                DB::table(self::TABLE)->where('id', $existing->id)->update($payload);
                $this->generalUpdated++;
            } else {
                $this->generalUnchanged++;
            }
            return;
        }

        // ===== INSERT: DO baru / tujuan baru / shipment baru =====
        $perDoDb  = [];
        foreach ($perDo as $col => $v) $perDoDb[$this->c($col)] = $v;
        $sharedDb = [];
        foreach ($shared as $col => $v) $sharedDb[$this->c($col)] = $v;

        $sibling = (clone $base)->orderBy('id')->first();
        $now     = now();

        if ($sibling) {
            // tujuan sudah ada -> salin data shipment dari baris saudara, lalu timpa field per DO
            $new = (array) $sibling;
            foreach (
                array_merge(
                    ['id', 'created_at', 'updated_at'],
                    array_map(fn($k) => $this->c($k), [
                        'sales_document',
                        'customer_reference',
                        'contract_end_date',
                        'no_do',
                        'nilai_muatan',
                        'total_do_qty_car',
                        'total_kubik',
                        'total_tonase',
                        'kirim_kontribusi',
                        'hasil_kubik',
                        'hasil_tonase',
                        'cr',
                    ])
                ) as $k
            ) {
                unset($new[$k]);
            }

            $new = array_merge($new, array_filter($sharedDb + $perDoDb, fn($v) => $v !== null));

            if ($this->hasCreatedAt) $new['created_at'] = $now;
            if ($this->hasUpdatedAt) $new['updated_at'] = $now;

            $newId = DB::table(self::TABLE)->insertGetId($this->onlyCols($new));
            $this->usedIds[$newId] = true;
        } else {
            // shipment / tujuan belum ada sama sekali -> baris baru dari Excel + master
            $fromExcel = array_filter(
                [
                    $cShip                    => $noShipment,
                    $cTuj                     => $tujuan,
                    $this->c('create_tgl')    => Carbon::now(),
                ] + $sharedDb + $perDoDb,
                fn($v) => $v !== null
            );

            // isi Excel menang; master hanya mengisi yang kosong
            $new = $fromExcel + $this->masterAttributes($tujuan);

            if ($this->hasCreatedAt) $new['created_at'] = $now;
            if ($this->hasUpdatedAt) $new['updated_at'] = $now;

            $newId = DB::table(self::TABLE)->insertGetId($this->onlyCols($new));
            $this->usedIds[$newId] = true;
        }

        $this->capacityRefresh[$noShipment] = true;
        if ($perDo['total_kubik'] !== null) $this->kubikChanged[$noShipment] = true;
        if ($perDo['total_do_qty_car'] !== null) $this->qtyUpdated++;
        if ($perDo['total_kubik'] !== null || $perDo['total_tonase'] !== null) $this->kubikTonaseUpdated++;

        $this->generalCreated++;
        $this->createdList[] = $noShipment . ' - ' . $tujuan . ($noDo !== null ? ' - ' . $noDo : '');
    }

    /**
     * Cari baris DB untuk baris Excel TANPA no_do, di antara baris shipment+tujuan
     * yang belum dipakai baris Excel lain pada import ini.
     *   1) qty + nilai muatan + total kubik + total tonase sama
     *   2) qty + nilai muatan sama
     *   3) baris kosong (qty & nilai belum terisi)
     * Tidak ada yang cocok -> null (dibuat baris baru).
     */
    private function matchWithoutDo($base, array $perDo)
    {
        $cands = (clone $base)->orderBy('id')->get()
            ->reject(fn($r) => isset($this->usedIds[$r->id]))
            ->values();

        if ($cands->isEmpty()) return null;

        $cQty = $this->c('total_do_qty_car');
        $cNil = $this->c('nilai_muatan');
        $cKub = $this->c('total_kubik');
        $cTon = $this->c('total_tonase');

        $qty = $perDo['total_do_qty_car'];
        $nil = $perDo['nilai_muatan'];
        $kub = $perDo['total_kubik'];
        $ton = $perDo['total_tonase'];

        $eq = fn($a, $b, $tol) => $a !== null && $b !== null && is_numeric($a) && is_numeric($b)
            && abs((float) $a - (float) $b) <= $tol;

        if ($qty !== null && $nil !== null) {
            $hit = $cands->first(
                fn($r) =>
                $eq($r->{$cQty} ?? null, $qty, 0.5)
                    && $eq($r->{$cNil} ?? null, $nil, 1.0)
                    && ($kub === null || $eq($r->{$cKub} ?? null, $kub, 0.0005))
                    && ($ton === null || $eq($r->{$cTon} ?? null, $ton, 0.0005))
            );
            if ($hit) return $hit;

            $hit = $cands->first(
                fn($r) =>
                $eq($r->{$cQty} ?? null, $qty, 0.5)
                    && $eq($r->{$cNil} ?? null, $nil, 1.0)
            );
            if ($hit) return $hit;
        }

        // baris lama yang qty & nilainya masih kosong dipakai dulu
        return $cands->first(
            fn($r) =>
            (float) ($r->{$cQty} ?? 0) == 0.0 && (float) ($r->{$cNil} ?? 0) == 0.0
        );
    }

    // ================= RECALC SETELAH IMPORT =================

    /** kubikasi & tonase dari tarif (nama ekspedisi/mobil TIDAK diubah ke master) */
    private function recalcTarifCapacity(array $shipments): void
    {
        $cShip = $this->c('no_shipment');
        $cEks  = $this->c('ekpedisi');
        $cRt   = $this->c('route');
        $cMb   = $this->c('mobil');
        $cKub  = $this->c('kubikasi');
        $cTon  = $this->c('tonase');

        $select = array_values(array_filter(
            ['id', $cShip, $cEks, $cRt, $cMb, $cKub, $cTon],
            fn($x) => $x === 'id' || $this->hasCol($x)
        ));

        foreach (array_chunk($shipments, 500) as $chunk) {
            $rows = DB::table(self::TABLE)
                ->select($select)
                ->whereIn($cShip, $chunk)
                ->get();

            foreach ($rows as $r) {
                $refresh = isset($this->capacityRefresh[(string) ($r->{$cShip} ?? '')])
                    || $this->isBlank($r->{$cKub} ?? null)
                    || $this->isBlank($r->{$cTon} ?? null);
                if (!$refresh) continue;

                $tarif = $this->findTarif(
                    $this->applyRouteAlias($r->{$cRt} ?? null),
                    $r->{$cEks} ?? null,
                    $r->{$cMb} ?? null
                );
                if (!$tarif) continue;

                $payload = array_filter([
                    $cKub => $this->parseNumber($tarif->kubikasi ?? null),
                    $cTon => $this->parseNumber($tarif->tonase ?? null),
                ], fn($v) => $v !== null);

                $payload = $this->onlyCols($payload);

                if ($payload) {
                    DB::table(self::TABLE)->where('id', $r->id)->update($payload);
                }
            }
        }
    }

    private function recalcBiayaKontribusi(): void
    {
        // A) Excel membawa biaya 1 mobil
        foreach ($this->groupBiaya as $sh => $pool) {
            $this->distributeBiaya((string) $sh, (float) $pool);
        }

        // B) Excel tanpa biaya tapi kubik berubah -> bagi ulang biaya yang sudah ada di DB
        $need = array_diff(
            array_map('strval', array_keys($this->kubikChanged)),
            array_map('strval', array_keys($this->groupBiaya))
        );

        foreach ($need as $sh) {
            $pool = (float) DB::table(self::TABLE)
                ->where($this->c('no_shipment'), $sh)
                ->select($this->c('tujuan') . ' as tujuan', DB::raw('MAX(COALESCE(' . $this->c('biaya_kirim') . ',0)) AS b'))
                ->groupBy($this->c('tujuan'))
                ->get()
                ->sum('b');

            if ($pool > 0) {
                $this->distributeBiaya($sh, $pool);
            }
        }
    }

    private function distributeBiaya(string $sh, float $pool): void
    {
        $rows = DB::table(self::TABLE)
            ->select('id', $this->c('tujuan') . ' as tujuan', $this->c('total_kubik') . ' as total_kubik')
            ->where($this->c('no_shipment'), $sh)
            ->orderBy('id')
            ->get()
            ->values();

        if ($rows->isEmpty()) {
            $this->biayaSkipped[] = $sh;
            return;
        }

        // pengaman: semua tujuan di DB harus ada di Excel, kalau tidak pembagian jadi salah
        $excel   = $this->shipmentTujuan[$sh] ?? [];
        $missing = $rows->pluck('tujuan')->unique()
            ->filter(fn($t) => !isset($excel[strtolower((string) $t)]))
            ->values()->all();

        if ($missing) {
            $this->biayaSkipped[] = $sh . ' (tujuan tidak ada di Excel: ' . implode('; ', array_map('strval', $missing)) . ')';
            return;
        }

        $totalKubik = (float) $rows->sum(fn($r) => (float) ($r->total_kubik ?? 0));
        $n          = $rows->count();

        // sisa pembulatan masuk ke DO terakhir yang punya kubik
        $lastIdx = $n - 1;
        if ($totalKubik > 0) {
            foreach ($rows as $i => $r) {
                if ((float) ($r->total_kubik ?? 0) > 0) $lastIdx = $i;
            }
        }

        $remaining = $pool;
        $perTujuan = [];

        foreach ($rows as $i => $r) {
            $share = $totalKubik > 0 ? ((float) ($r->total_kubik ?? 0)) / $totalKubik : 1 / $n;
            $val   = ($i === $lastIdx) ? round($remaining, 2) : round($pool * $share, 2);
            $remaining -= $val;

            if ($this->hasKirimKontribusi) {
                DB::table(self::TABLE)->where('id', $r->id)->update([$this->c('kirim_kontribusi') => $val]);
            }

            $t = (string) $r->tujuan;
            $perTujuan[$t]['sum']   = ($perTujuan[$t]['sum'] ?? 0) + $val;
            $perTujuan[$t]['ids'][] = $r->id;
        }

        // biaya_kirim tujuan = jumlah kontribusi DO-nya, ditulis ke semua DO di tujuan itu
        foreach ($perTujuan as $t) {
            DB::table(self::TABLE)->whereIn('id', $t['ids'])->update([$this->c('biaya_kirim') => round($t['sum'], 2)]);
        }

        $this->biayaKirimUpdated++;
    }

    /** ambil nilai pertama yang terisi; cek nama polos dan versi *_pasuruan */
    private function pick($row, array $keys)
    {
        foreach ($keys as $k) {
            foreach ([$k, $k . self::SUF] as $kk) {
                if (isset($row[$kk]) && $row[$kk] !== '' && $row[$kk] !== '-') {
                    return $row[$kk];
                }
            }
        }
        return null;
    }

    /**
     * CR            : biaya_kirim tujuan / SUM nilai_muatan tujuan x 100.
     * Hasil kubik   : per tujuan = SUM total_kubik tujuan / kubikasi x 100.
     * Hasil tonase  : per tujuan = SUM total_tonase tujuan / tonase x 100.
     * Pengiriman optimal: per shipment (SUM total / kapasitas >= 85%).
     */
    private function recalcShipments(array $shipments): void
    {
        $t = self::TABLE;

        $sh  = $this->c('no_shipment');
        $tj  = $this->c('tujuan');
        $nm  = $this->c('nilai_muatan');
        $bk  = $this->c('biaya_kirim');
        $tk  = $this->c('total_kubik');
        $tt  = $this->c('total_tonase');
        $kb  = $this->c('kubikasi');
        $tn  = $this->c('tonase');
        $cr  = $this->c('cr');
        $hk  = $this->c('hasil_kubik');
        $ht  = $this->c('hasil_tonase');
        $po  = $this->c('pengiriman_optimal');
        $poc = $this->c('pengiriman_optimal_customer');
        $rpc = $this->c('reason_optimal_customer');

        // kolom bisa bertipe varchar -> cast aman
        $n = fn($c) => "COALESCE(CAST(NULLIF(TRIM({$c}), '') AS DECIMAL(18,4)), 0)";

        foreach (array_chunk($shipments, 500) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));
            $b2 = array_merge($chunk, $chunk);

            // ---- CR per shipment + tujuan ----
            DB::update("
                UPDATE {$t} lp
                JOIN (
                    SELECT {$sh} AS ns, COALESCE({$tj},'') AS tj,
                           SUM({$n($nm)}) AS sum_nilai
                    FROM {$t}
                    WHERE {$sh} IN ($in)
                    GROUP BY {$sh}, COALESCE({$tj},'')
                ) g ON lp.{$sh} = g.ns AND COALESCE(lp.{$tj},'') = g.tj
                SET lp.{$cr} = IF(g.sum_nilai <= 0, 0,
                               ROUND({$n('lp.' .$bk)} / g.sum_nilai * 100, 4))
                WHERE lp.{$sh} IN ($in)
            ", $b2);

            // ---- HASIL KUBIK / TONASE (per shipment + tujuan) + OPTIMAL (per shipment) ----
            // hasil_kubik  = SUM total_kubik tujuan  / kubikasi x 100
            // hasil_tonase = SUM total_tonase tujuan / tonase   x 100
            // (semua DO di tujuan yang sama memegang nilai yang sama)
            DB::update("
                UPDATE {$t} lp
                JOIN (
                    SELECT {$sh} AS ns,
                           SUM({$n($tk)}) AS sum_kubik,
                           SUM({$n($tt)}) AS sum_tonase,
                           MAX(CAST(NULLIF(TRIM({$kb}), '') AS DECIMAL(18,4))) AS kubikasi,
                           MAX(CAST(NULLIF(TRIM({$tn}), '') AS DECIMAL(18,4))) AS tonase
                    FROM {$t}
                    WHERE {$sh} IN ($in)
                    GROUP BY {$sh}
                ) x ON lp.{$sh} = x.ns
                JOIN (
                    SELECT {$sh} AS ns, COALESCE({$tj},'') AS tj,
                           SUM({$n($tk)}) AS t_kubik,
                           SUM({$n($tt)}) AS t_tonase
                    FROM {$t}
                    WHERE {$sh} IN ($in)
                    GROUP BY {$sh}, COALESCE({$tj},'')
                ) g ON lp.{$sh} = g.ns AND COALESCE(lp.{$tj},'') = g.tj
                SET
                    lp.{$hk} = CASE
                        WHEN x.kubikasi > 0 THEN ROUND(g.t_kubik / x.kubikasi * 100, 2)
                        ELSE NULL
                    END,
                    lp.{$ht} = CASE
                        WHEN x.tonase > 0 THEN ROUND(g.t_tonase / x.tonase * 100, 2)
                        ELSE NULL
                    END,
                    lp.{$po} = CASE
                        WHEN (x.kubikasi > 0 AND (x.sum_kubik  / x.kubikasi * 100) >= 85)
                          OR (x.tonase   > 0 AND (x.sum_tonase / x.tonase   * 100) >= 85)
                            THEN 'OPTIMAL'
                        WHEN x.kubikasi > 0 OR x.tonase > 0
                            THEN 'TIDAK OPTIMAL'
                        ELSE NULL
                    END
                WHERE lp.{$sh} IN ($in)
            ", array_merge($chunk, $chunk, $chunk));

            // ---- OPTIMAL PER CUSTOMER (per baris) ----
            if ($this->hasOptimalCustomer) {
                DB::update("
                    UPDATE {$t} lp
                    SET lp.{$poc} = CASE
                        WHEN COALESCE(lp.{$hk}, 0) >= 85
                          OR COALESCE(lp.{$ht}, 0) >= 85 THEN 'OPTIMAL'
                        WHEN lp.{$hk} IS NOT NULL
                          OR lp.{$ht} IS NOT NULL THEN 'TIDAK OPTIMAL'
                        ELSE NULL
                    END
                    WHERE lp.{$sh} IN ($in)
                ", $chunk);
            }

            // ---- REASON OPTIMAL CUSTOMER (otomatis hanya kalau kosong) ----
            if ($this->hasReasonOptimalCustomer && $this->hasOptimalCustomer) {
                DB::update("
                    UPDATE {$t} lp
                    SET lp.{$rpc} = CONCAT(
                        'Isi kubik ', COALESCE(lp.{$hk}, 0),
                        '%, tonase ', COALESCE(lp.{$ht}, 0),
                        '% (di bawah 85%)'
                    )
                    WHERE lp.{$sh} IN ($in)
                      AND lp.{$poc} = 'TIDAK OPTIMAL'
                      AND NULLIF(TRIM(lp.{$rpc}), '') IS NULL
                ", $chunk);
            }
        }
    }

    // ================= TARIF LOOKUP =================

    private function applyRouteAlias(?string $route): ?string
    {
        if ($route === null || $route === '') return $route;

        return self::ROUTE_ALIASES[$this->normalize($route)] ?? $route;
    }

    private function normalize(?string $value): string
    {
        $value = (string) $value;
        $value = str_replace("\xC2\xA0", ' ', $value);
        $value = preg_replace('/\s*-\s*/', '-', $value);
        $value = preg_replace('/\s+/', ' ', trim($value));
        return strtolower($value);
    }

    private function normalizeMobil(?string $value): string
    {
        $value = str_replace("\xC2\xA0", ' ', (string) $value);
        return strtolower(preg_replace('/\s+/', ' ', trim($value)));
    }

    private function ekspedisiKey(?string $v): string
    {
        $k = strtolower($this->normalize($v));
        $k = preg_replace('/^(pt|cv|ud|pd)\b[\s\.]*/', '', $k);
        return preg_replace('/[^a-z0-9]+/', '', $k);
    }

    private function resolveEkspedisiName(?string $name, $candidates): ?string
    {
        $key = $this->ekspedisiKey($name);
        if ($key === '') return null;

        if (isset(self::EKSPEDISI_ALIASES[$key])) {
            return self::EKSPEDISI_ALIASES[$key];
        }

        $best = null;
        $bestScore = 0.0;
        $second = 0.0;

        foreach ($candidates->pluck('ekpedisi')->filter()->unique() as $master) {
            $mk = $this->ekspedisiKey($master);
            if ($mk === '') continue;

            if ($mk === $key) return $master;

            similar_text($key, $mk, $pct);
            $score = $pct / 100;

            if (
                min(strlen($key), strlen($mk)) >= 6
                && (str_contains($mk, $key) || str_contains($key, $mk))
            ) {
                $score = max($score, 0.9);
            }

            if ($score > $bestScore) {
                $second = $bestScore;
                $bestScore = $score;
                $best = $master;
            } elseif ($score > $second) {
                $second = $score;
            }
        }

        return ($best !== null && $bestScore >= 0.85 && ($bestScore - $second) >= 0.05) ? $best : null;
    }

    /** catat kegagalan tarif beserta alasannya, lalu return null */
    private function tarifMiss(string $label)
    {
        if (!in_array($label, $this->tarifNotFound, true)) {
            $this->tarifNotFound[] = $label;
        }
        return null;
    }

    private function findTarif(?string $route, ?string $ekpedisi, ?string $mobil)
    {
        $mobilKey = $this->normalizeMobil($mobil);
        $routeKey = $this->normalize($route);
        $label    = "{$ekpedisi} | {$route} | {$mobil}";

        // 1) route: persis, kalau tidak ada coba "mengandung"
        if ($routeKey === '') {
            // route kosong di DB/Excel -> kapasitas adalah sifat jenis mobil,
            // jadi cari di SEMUA route; hasilnya dipakai hanya jika kapasitasnya seragam (langkah 4)
            $candidates = self::$tarifByRoute->flatten(1);
            if ($candidates->isEmpty()) $candidates = null;
        } else {
            $candidates = self::$tarifByRoute[$routeKey] ?? null;

            if (!$candidates) {
                $fuzzy = self::$tarifByRoute
                    ->filter(fn($v, $k) => $k !== '' && (str_contains($k, $routeKey) || str_contains($routeKey, $k)))
                    ->flatten(1);
                $candidates = $fuzzy->isEmpty() ? null : $fuzzy;
            }
        }

        if (!$candidates)     return $this->tarifMiss($label . ($routeKey === '' ? ' [tabel tarif kosong]' : ' [route tidak ada di tabel tarif]'));
        if ($mobilKey === '') return $this->tarifMiss($label . ' [mobil kosong]');

        // 2) mobil: cocok dua arah ("CDE" vs "CDE Box")
        $byMobil = $candidates->filter(function ($t) use ($mobilKey) {
            $m = $this->normalizeMobil($t->mobil);
            return $m !== '' && (str_starts_with($m, $mobilKey) || str_starts_with($mobilKey, $m));
        })->values();

        if ($byMobil->isEmpty()) return $this->tarifMiss($label . ' [mobil tidak cocok]');

        // 3) ekspedisi (kalau ada): nama hasil resolve, atau sama persis setelah normalisasi
        $found = null;
        $ekKey = $this->normalize($ekpedisi);

        if ($ekKey !== '') {
            $cacheKey = $routeKey . '|' . $ekpedisi;
            if (!array_key_exists($cacheKey, $this->ekspedisiResolved)) {
                $this->ekspedisiResolved[$cacheKey] = $this->resolveEkspedisiName($ekpedisi, $candidates);
            }
            $master = $this->ekspedisiResolved[$cacheKey];

            $found = $byMobil->first(
                fn($t) => ($master !== null && $t->ekpedisi === $master)
                    || $this->normalize($t->ekpedisi) === $ekKey
            );
        }

        // 4) fallback: abaikan ekspedisi, cukup route + mobil
        //    - route terisi : ambil yang pertama cocok
        //    - route kosong : hanya jika kapasitas semua kandidat seragam
        if (!$found) {
            if ($routeKey !== '') {
                $found = $byMobil->first();
            } else {
                $caps = $byMobil
                    ->map(fn($t) => (float) $this->parseNumber($t->kubikasi) . '|' . (float) $this->parseNumber($t->tonase))
                    ->unique();
                if ($caps->count() === 1) {
                    $found = $byMobil->first();
                }
            }
        }

        return $found ?: $this->tarifMiss($label . ($routeKey === ''
            ? ' [route kosong & kapasitas mobil ini berbeda antar route]'
            : ' [ekspedisi tidak cocok]'));
    }

    /** Bandingkan nilai lama vs baru, toleran terhadap tipe data. */
    private function valuesEqual($old, $new): bool
    {
        if ($old === null && $new === null) {
            return true;
        }
        if ($old === null || $new === null) {
            return false;
        }
        if (is_numeric($old) && is_numeric($new)) {
            return abs((float) $old - (float) $new) < 0.0001;
        }
        return trim((string) $old) === trim((string) $new);
    }

    // ================= HELPERS =================

    /** parser angka: Rp, 76,559,246 / 76.559.246,5 / 12,5 */
    private function parseNumber($value): ?float
    {
        if ($value === null) return null;
        if (is_int($value) || is_float($value)) return (float) $value;

        $s = trim(str_ireplace(['Rp', ' ', '%'], '', (string) $value));
        if ($s === '' || $s === '-') return null;

        if (preg_match('/^-?\d{1,3}(,\d{3})+(\.\d+)?$/', $s)) {
            $s = str_replace(',', '', $s);
        } elseif (preg_match('/^-?\d{1,3}(\.\d{3})+(,\d+)?$/', $s)) {
            $s = str_replace(['.', ','], ['', '.'], $s);
        } elseif (preg_match('/^-?\d+,\d+$/', $s)) {
            $s = str_replace(',', '.', $s);
        } else {
            $s = str_replace(',', '', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    private function cleanText($value)
    {
        if ($value === null || $value === '' || $value == '-') return null;

        if (is_float($value) && floor($value) == $value && abs($value) < 1e15) {
            return (string) (int) $value;   // 4200067817.0 -> "4200067817"
        }

        $value = (string) $value;
        $value = str_replace("\xC2\xA0", ' ', $value);
        $value = preg_replace('/\s+/', ' ', trim($value));

        if ($value === '' || stripos($value, '(blank)') !== false) return null;

        return $value;
    }

    /** tanggal format d-m-Y / Y-m-d / serial Excel (untuk act_pgi_date) */
    private function convertDate($value)
    {
        if (!$value || $value == '-') return null;

        if ($value instanceof \DateTimeInterface) return $value->format('Y-m-d');

        if (is_numeric($value)) {
            return Date::excelToDateTimeObject($value)->format('Y-m-d');
        }

        $timestamp = strtotime(str_replace('/', '-', trim($value)));
        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    /** tanggal format US m/d/Y (Contract End Date), "(blank)" -> null */
    private function convertDateUs($value)
    {
        if (!$value || $value == '-' || stripos((string) $value, 'blank') !== false) return null;

        if ($value instanceof \DateTimeInterface) return $value->format('Y-m-d');

        if (is_numeric($value)) {
            return Date::excelToDateTimeObject($value)->format('Y-m-d');
        }

        $v = trim((string) $value);
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $v, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[1], $m[2]);   // m/d/Y
        }

        $ts = strtotime($v);
        return $ts ? date('Y-m-d', $ts) : null;
    }
}
