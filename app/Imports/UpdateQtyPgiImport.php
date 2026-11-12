<?php

namespace App\Imports;

use App\Models\LogistikPengiriman;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterImport;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;

/**
 * Import update rincian DO.
 *
 * 1 baris Excel = 1 DO = 1 baris di logistik_pengiriman.
 * Kunci: no_shipment + tujuan + no_do.
 *   - Baris lama yang no_do-nya masih kosong dipakai untuk DO pertama di tujuan itu.
 *   - DO berikutnya jadi baris baru (data shipment disalin dari baris saudaranya).
 *   - Tujuan yang belum ada di DB dibuat baris baru, seperti import biasa.
 * Tampilan (grup per shipment + tujuan) dan rincian DO sudah ditangani LogistikController.
 *
 * Nilai kosong di Excel tidak menimpa data lama. Tanggal, gudang, reason tidak disentuh.
 * ekpedisi, route, mobil : Excel menang (diupdate ke semua baris di shipment itu).
 * kubik / tonase         : apa adanya dari Excel, tidak dibulatkan.
 *
 * kirim_kontribusi per DO = biaya 1 mobil x total_kubik DO / SUM total_kubik shipment
 * biaya_kirim per tujuan  = SUM kirim_kontribusi DO di tujuan itu (ditulis ke semua baris DO tujuan tsb)
 *
 * UPDATE KIRIM_KONTRIBUSI SAJA:
 *   Template cukup berisi kolom no_shipment + kirim_kontribusi (kolom tujuan TIDAK diperlukan).
 *   Nilai = total biaya 1 mobil untuk shipment itu, dibagi ke semua DO shipment tsb
 *   (lintas tujuan) sesuai total_kubik yang sudah ada di DB. Satu baris per shipment.
 *   Kolom tujuan hanya dibutuhkan kalau Excel juga membawa data per DO
 *   (no_do, total_kubik, total_tonase, nilai_muatan, dst).
 */
class UpdateQtyPgiImport implements ToCollection, WithHeadingRow, WithEvents, WithCalculatedFormulas
{
    private const TABLE = 'logistik_pengiriman';

    // tabel master tujuan
    private const MASTER_TABLE      = 'tujuanfillterr';
    private const MASTER_TUJUAN_COL = 'tujuan';

    // kolom_di_master => kolom_di_logistik_pengiriman (hanya mengisi yang kosong pada baris BARU)
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

    // Excel menang kalau beda dengan DB (berlaku ke semua baris di shipment itu)
    private const OVERWRITE_SHIPMENT = ['ekpedisi', 'route', 'mobil'];

    // kolom gudang: 1=KACS, 2=SENTUL, 3=CCIE, 4=P5, 5=FIFATEX
    private const GUDANG_SUFFIX = ['', '_2', '_3', '_4', '_5'];
    private const GUDANG_BASE   = ['planning_loading', 'tanggal_tiba_gudang', 'tanggal_keluar_gudang'];

    // hanya diisi kalau di DB masih kosong
    private const FILL_IF_EMPTY = [
        'pulau',
        'area',
        'dist_channel',
        'via_kirim',
        'kategori_ekspedisi',
        'nama_driver',
        'no_pol',
    ];

    // header Excel alternatif (file pivot)
    private const SOURCE_ALIASES = [
        'ekpedisi'  => ['ekpedisi', 'vendor'],
        'route'     => ['route', 'route_name'],
        'mobil'     => ['mobil', 'shipping_type'],
        'via_kirim' => ['via_kirim', 'via'],
        'no_pol'    => ['no_pol', 'nopol'],
    ];

    // header Excel yang membawa data per DO (butuh tujuan untuk menentukan barisnya)
    private const PER_DO_HEADERS = [
        'no_do',
        'sales_document',
        'customer_reference',
        'contract_end_date',
        'nilai_muatan_rp',
        'nilai_muatan',
        'sum_of_nilai_do',
        'total_do_qty_car',
        'sum_of_qty_do',
        'total_kubik',
        'sum_of_kubikasi2',
        'total_tonase',
        'sum_of_tonase3',
    ];

    private static $masterByTujuan = null;   // tujuan ternormalisasi => rows master
    private static $tarifByRoute   = null;   // route => rows tarif

    private array $tujuanNotInMaster = [];
    private array $ekspedisiResolved = [];   // cache "route|ekspedisi" => nama di master

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

    // ===== hasil recalc biaya =====
    // ===== hasil recalc biaya =====
    private int $biayaKirimUpdated = 0;
    private int $kontribusiRowsUpdated = 0;   // <-- TAMBAH
    private array $biayaSkipped = [];
    private array $tarifNotFound = [];
    private array $tarifAmbiguous = [];

    private array $pgiProcessed = [];

    // no_shipment yang tersentuh import ini
    private array $touchedShipments = [];

    // no_shipment => biaya 1 mobil dari Excel
    private array $groupBiaya = [];

    // no_shipment yang total_kubik-nya berubah
    private array $kubikChanged = [];

    // no_shipment => [tujuan (huruf kecil) => true] yang ada di Excel
    // (hanya terisi kalau Excel memang membawa kolom tujuan)
    private array $shipmentTujuan = [];

    // no_shipment => [ekpedisi/route/mobil] dari Excel
    private array $shipmentShared = [];

    // no_shipment yang kapasitasnya perlu diambil ulang dari tarif
    private array $capacityRefresh = [];

    // kolom gudang yang benar-benar ada di tabel
    private array $gudangCols = [];

    // no_shipment => [kolom => datetime] dari Excel
    private array $shipmentGudang = [];

    private int $gudangUpdated = 0;

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

    public function getBiayaKirimUpdated(): int
    {
        return $this->biayaKirimUpdated;
    }
    public function getKontribusiRowsUpdated(): int
    {
        return $this->kontribusiRowsUpdated;
    }   // <-- TAMBAH
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
    public function getGudangUpdated(): int
    {
        return $this->gudangUpdated;
    }

    public function __construct()
    {
        $this->hasOptimalCustomer       = Schema::hasColumn(self::TABLE, 'pengiriman_optimal_customer');
        $this->hasReasonOptimalCustomer = Schema::hasColumn(self::TABLE, 'reason_optimal_customer');
        $this->hasKirimKontribusi       = Schema::hasColumn(self::TABLE, 'kirim_kontribusi');
        $this->hasCreatedAt             = Schema::hasColumn(self::TABLE, 'created_at');
        $this->hasUpdatedAt             = Schema::hasColumn(self::TABLE, 'updated_at');

        foreach (self::GUDANG_BASE as $b) {
            foreach (self::GUDANG_SUFFIX as $s) {
                if (Schema::hasColumn(self::TABLE, $b . $s)) {
                    $this->gudangCols[] = $b . $s;
                }
            }
        }

        if (self::$tarifByRoute === null) {
            self::$tarifByRoute = DB::table(self::TARIF_TABLE)
                ->select('ekpedisi', 'route', 'mobil', 'biaya_kirim', 'kubikasi', 'tonase')
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

    /** huruf kecil, tanda baca dan spasi ganda dibuang, supaya "Ada Swalayan - Bogor" = "ADA SWALAYAN BOGOR" */
    private function normalizeTujuan(?string $v): string
    {
        $v = strtolower((string) $v);
        return trim(preg_replace('/[^a-z0-9]+/', ' ', $v));
    }

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
                $out[$dst] = $v;
            }
        }
        return $out;
    }

    // ================= EVENTS =================

    public function registerEvents(): array
    {
        return [
            AfterImport::class => function () {
                try {
                    $shipments = array_map('strval', array_keys($this->touchedShipments));

                    $this->processShipmentFields();            // ekpedisi/route/mobil: Excel menang
                    $this->processGudang();                    // planning / tiba / keluar gudang
                    $this->recalcTarifCapacity($shipments);
                    $this->recalcBiayaKontribusi();            // kirim_kontribusi per DO + biaya_kirim per tujuan
                    $this->recalcShipments($shipments);        // cr, hasil_kubik, hasil_tonase, optimal
                } catch (\Throwable $e) {
                    logger()->error('UPDATE IMPORT AFTER-IMPORT GAGAL', [
                        'error' => $e->getMessage(),
                        'line'  => $e->getLine(),
                    ]);
                }
            },
        ];
    }

    // ================= MAIN =================

    public function collection(Collection $rows)
    {
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
            $this->touchedShipments[$noShipment] = true;
            $this->collectGudang($row, $noShipment);

            // biaya 1 mobil (kunci: no_shipment, tujuan tidak diperlukan)
            $pool = $this->parseNumber($this->pick($row, ['biaya_kirim', 'biaya_kirim_rp', 'kirim_kontribusi']));
            if ($pool !== null && $pool > 0) {
                // nilai harus sama kalau shipment muncul di beberapa baris; yang terakhir dipakai
                if (isset($this->groupBiaya[$noShipment]) && abs($this->groupBiaya[$noShipment] - $pool) > 0.005) {
                    $this->biayaSkipped[] = "[peringatan] {$noShipment} (nilai kirim_kontribusi berbeda antar baris, dipakai yang terakhir: {$pool})";
                }
                $this->groupBiaya[$noShipment] = $pool;
            }

            $tujuan = $this->cleanText($this->pick($row, ['tujuan', 'sold_to_party_name']));

            if (empty($tujuan)) {
                // Tanpa tujuan: baris ini cukup untuk update kirim_kontribusi (kunci: no_shipment).
                // Baru dihitung dilewati kalau baris juga membawa data per DO yang butuh tujuan.
                if ($this->hasPerDoData($row)) {
                    $this->generalSkipped++;
                    $this->generalAmbiguous[] = "{$noShipment} (tujuan kosong, data DO dilewati)";
                }
            } else {
                $this->shipmentTujuan[$noShipment][strtolower($tujuan)] = true;
                $this->collectShared($row, $noShipment);

                try {
                    $this->processGeneral($row, $noShipment, $tujuan);
                } catch (\Throwable $e) {
                    $this->generalAmbiguous[] = "{$noShipment} - {$tujuan} (ERROR: " . mb_substr($e->getMessage(), 0, 120) . ')';
                    logger()->error('UPDATE IMPORT GAGAL PER BARIS', [
                        'no_shipment' => $noShipment,
                        'tujuan'      => $tujuan,
                        'error'       => $e->getMessage(),
                        'line'        => $e->getLine(),
                    ]);
                }
            }

            // ACT PGI DATE (kunci: no_shipment)
            $pgiDate = $this->convertDate($row['act_pgi_date'] ?? null);

            if ($pgiDate !== null && !isset($this->pgiProcessed[$noShipment])) {
                $affected = LogistikPengiriman::where('no_shipment', $noShipment)
                    ->update(['act_pgi_date' => $pgiDate]);

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
        $v = $this->cleanText($this->pick($row, self::SOURCE_ALIASES[$col] ?? [$col]));

        return $col === 'route' ? $this->applyRouteAlias($v) : $v;
    }

    /** ekpedisi/route/mobil dari Excel per shipment (nilai pertama yang terisi) */
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

    /** apakah baris membawa data per DO yang butuh tujuan untuk menentukan barisnya */
    private function hasPerDoData($row): bool
    {
        return $this->pick($row, self::PER_DO_HEADERS) !== null;
    }

    // ================= EKSPEDISI / ROUTE / MOBIL: Excel menang, per shipment =================

    private function processShipmentFields(): void
    {
        foreach ($this->shipmentShared as $sh => $vals) {
            $vals = array_filter($vals, fn($v) => $v !== null);
            if (!$vals) continue;

            $payload = $vals;
            if ($this->hasUpdatedAt) $payload['updated_at'] = now();

            $affected = DB::table(self::TABLE)
                ->where('no_shipment', (string) $sh)
                ->where(function ($q) use ($vals) {
                    foreach ($vals as $col => $v) {
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
        $noDo = $this->cleanText($row['no_do'] ?? null);
        $qty  = $this->parseNumber($this->pick($row, ['total_do_qty_car', 'sum_of_qty_do']));

        // field per DO (kubik & tonase apa adanya)
        $perDo = [
            'sales_document'     => $this->cleanText($row['sales_document'] ?? null),
            'customer_reference' => $this->cleanText($row['customer_reference'] ?? null),
            'contract_end_date'  => $this->convertDateUs($row['contract_end_date'] ?? null),
            'no_do'              => $noDo,
            'nilai_muatan'       => $this->parseNumber($this->pick($row, ['nilai_muatan_rp', 'nilai_muatan', 'sum_of_nilai_do'])),
            'total_do_qty_car'   => $qty !== null ? (int) round($qty) : null,
            'total_kubik'        => $this->parseNumber($this->pick($row, ['total_kubik', 'sum_of_kubikasi2'])),
            'total_tonase'       => $this->parseNumber($this->pick($row, ['total_tonase', 'sum_of_tonase3'])),
        ];

        // field milik shipment / tujuan
        $shared = [];
        foreach (array_merge(self::OVERWRITE_SHIPMENT, self::FILL_IF_EMPTY) as $col) {
            $shared[$col] = $this->src($row, $col);
        }

        $base = DB::table(self::TABLE)
            ->where('no_shipment', $noShipment)
            ->where('tujuan', $tujuan);

        // cari baris DO ini
        if ($noDo !== null) {
            $existing = (clone $base)->where('no_do', $noDo)->first()
                // baris lama yang no_do-nya masih kosong dipakai untuk DO pertama
                ?? (clone $base)->where(fn($q) => $q->whereNull('no_do')->orWhere('no_do', ''))
                ->orderBy('id')->first();
        } else {
            if ((clone $base)->count() > 1) {
                $this->generalAmbiguous[] = "{$noShipment} - {$tujuan} (no_do kosong, banyak baris)";
                return;
            }
            $existing = (clone $base)->first();
        }

        // ===== UPDATE =====
        if ($existing) {
            $payload = [];

            foreach ($perDo as $col => $new) {
                if ($new === null) continue;
                if (!$this->valuesEqual($existing->{$col} ?? null, $new)) {
                    $payload[$col] = $new;
                }
            }

            // field lain: hanya isi kalau di DB masih kosong (ekpedisi/route/mobil ditangani processShipmentFields)
            foreach (self::FILL_IF_EMPTY as $col) {
                $new = $shared[$col] ?? null;
                if ($new !== null && $this->isBlank($existing->{$col} ?? null)) {
                    $payload[$col] = $new;
                }
            }

            if (isset($payload['total_kubik'])) $this->kubikChanged[$noShipment] = true;
            if (isset($payload['total_do_qty_car'])) $this->qtyUpdated++;
            if (isset($payload['total_kubik']) || isset($payload['total_tonase'])) $this->kubikTonaseUpdated++;

            if (!empty($payload)) {
                if ($this->hasUpdatedAt) $payload['updated_at'] = now();
                DB::table(self::TABLE)->where('id', $existing->id)->update($payload);
                $this->generalUpdated++;
            } else {
                $this->generalUnchanged++;
            }
            return;
        }

        // ===== INSERT DO BARU =====
        $sibling = (clone $base)->orderBy('id')->first();
        $now     = now();

        if ($sibling) {
            // tujuan sudah ada -> salin data shipment (tanggal, gudang, driver, dst) dari baris saudara,
            // supaya grup shipment + tujuan tetap seragam, lalu timpa field per DO
            $new = (array) $sibling;
            foreach (
                [
                    'id',
                    'created_at',
                    'updated_at',
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
                ] as $k
            ) {
                unset($new[$k]);
            }

            $new = array_merge($new, array_filter($shared + $perDo, fn($v) => $v !== null));

            if ($this->hasCreatedAt) $new['created_at'] = $now;
            if ($this->hasUpdatedAt) $new['updated_at'] = $now;

            DB::table(self::TABLE)->insert($new);
        } else {
            $fromExcel = array_filter(
                ['no_shipment' => $noShipment, 'tujuan' => $tujuan, 'create_tgl' => Carbon::now()]
                    + $shared + $perDo,
                fn($v) => $v !== null
            );

            // isi Excel menang; master hanya mengisi yang kosong
            LogistikPengiriman::create($fromExcel + $this->masterAttributes($tujuan));
        }

        $this->capacityRefresh[$noShipment] = true;
        if ($perDo['total_kubik'] !== null) $this->kubikChanged[$noShipment] = true;
        if ($perDo['total_do_qty_car'] !== null) $this->qtyUpdated++;
        if ($perDo['total_kubik'] !== null || $perDo['total_tonase'] !== null) $this->kubikTonaseUpdated++;

        $this->generalCreated++;
    }

    // ================= GUDANG =================

    /** planning / tiba / keluar gudang dari Excel per shipment (nilai terisi pertama) */
    private function collectGudang($row, string $sh): void
    {
        foreach ($this->gudangCols as $col) {
            $v = $this->convertDateTime($row[$col] ?? null);
            if ($v !== null) {
                $this->shipmentGudang[$sh][$col] ??= $v;
            }
        }
    }

    /** Excel menang kalau terisi; sel kosong tidak menimpa data lama. Berlaku ke semua baris shipment. */
    private function processGudang(): void
    {
        foreach ($this->shipmentGudang as $sh => $vals) {
            if (!$vals) continue;

            $payload = $vals;
            if ($this->hasUpdatedAt) $payload['updated_at'] = now();

            $affected = DB::table(self::TABLE)
                ->where('no_shipment', (string) $sh)
                ->where(function ($q) use ($vals) {
                    foreach ($vals as $col => $v) {
                        $q->orWhereNull($col)->orWhere($col, '!=', $v);
                    }
                })
                ->update($payload);

            $this->gudangUpdated += $affected;
        }
    }

    /** tanggal + jam (d/m/Y H:i, Y-m-d H:i:s, serial Excel) -> "Y-m-d H:i:s" */
    private function convertDateTime($value): ?string
    {
        if ($value === null || $value === '' || $value == '-') return null;

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_numeric($value)) {
            if ((float) $value < 1) return null;
            return Date::excelToDateTimeObject($value)->format('Y-m-d H:i:s');
        }

        $v = trim((string) $value);
        if ($v === '' || stripos($v, 'blank') !== false) return null;

        // d/m/Y atau d-m-Y (+ jam) -> Y-m-d
        if (preg_match('#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})(.*)$#', $v, $m)) {
            $v = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]) . $m[4];
        }

        $ts = strtotime($v);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }

    // ================= RECALC SETELAH IMPORT =================

    /** kubikasi & tonase dari tarif (nama ekspedisi/mobil TIDAK diubah ke master) */
    private function recalcTarifCapacity(array $shipments): void
    {
        foreach (array_chunk($shipments, 500) as $chunk) {
            $rows = DB::table(self::TABLE)
                ->select('id', 'no_shipment', 'ekpedisi', 'route', 'mobil', 'kubikasi', 'tonase')
                ->whereIn('no_shipment', $chunk)
                ->get();

            foreach ($rows as $r) {
                $refresh = isset($this->capacityRefresh[(string) $r->no_shipment])
                    || $this->isBlank($r->kubikasi)
                    || $this->isBlank($r->tonase);
                if (!$refresh) continue;

                $tarif = $this->findTarif($this->applyRouteAlias($r->route), $r->ekpedisi, $r->mobil);
                if (!$tarif) continue;

                $payload = array_filter([
                    'kubikasi' => $this->parseNumber($tarif->kubikasi ?? null),
                    'tonase'   => $this->parseNumber($tarif->tonase ?? null),
                ], fn($v) => $v !== null);

                if ($payload) {
                    DB::table(self::TABLE)->where('id', $r->id)->update($payload);
                }
            }
        }
    }

    /**
     * kirim_kontribusi per DO = biaya 1 mobil x total_kubik DO / SUM total_kubik shipment
     * biaya_kirim per tujuan  = SUM kirim_kontribusi DO di tujuan itu
     */
    private function recalcBiayaKontribusi(): void
    {
        // A) Excel membawa biaya 1 mobil (kunci: no_shipment)
        foreach ($this->groupBiaya as $sh => $pool) {
            $this->distributeBiaya((string) $sh, (float) $pool);
        }

        // B) Excel tanpa biaya tapi kubik berubah -> bagi ulang biaya yang sudah ada di DB
        $need = array_diff(
            array_map('strval', array_keys($this->kubikChanged)),
            array_map('strval', array_keys($this->groupBiaya))
        );

        foreach ($need as $sh) {
            // biaya_kirim sama di semua DO satu tujuan, jadi ambil 1 nilai per tujuan
            $pool = (float) DB::table(self::TABLE)
                ->where('no_shipment', $sh)
                ->select('tujuan', DB::raw('MAX(COALESCE(biaya_kirim,0)) AS b'))
                ->groupBy('tujuan')
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
            ->select('id', 'tujuan', 'total_kubik')
            ->where('no_shipment', $sh)
            ->orderBy('id')
            ->get()
            ->values();

        if ($rows->isEmpty()) {
            $this->biayaSkipped[] = $sh;
            return;
        }

        // Pengaman hanya berlaku kalau Excel membawa kolom tujuan (ikut mengupdate data DO shipment ini):
        // semua tujuan di DB harus ada di Excel, kalau tidak total_kubik lama (total shipment yang
        // diulang per baris) ikut terhitung dan pembagiannya salah.
        // Kalau Excel hanya berisi no_shipment + kirim_kontribusi, total_kubik di DB dipakai apa adanya.
        $excel = $this->shipmentTujuan[$sh] ?? null;

        if ($excel !== null) {
            $missing = $rows->pluck('tujuan')->unique()
                ->filter(fn($t) => !isset($excel[strtolower((string) $t)]))
                ->values()->all();

            if ($missing) {
                $this->biayaSkipped[] = $sh . ' (tujuan tidak ada di Excel: ' . implode('; ', array_map('strval', $missing)) . ')';
                return;
            }
        }

        $totalKubik = (float) $rows->sum('total_kubik');
        $n          = $rows->count();

        // sisa pembulatan masuk ke DO terakhir yang punya kubik
        $lastIdx = $n - 1;
        if ($totalKubik > 0) {
            foreach ($rows as $i => $r) {
                if ((float) ($r->total_kubik ?? 0) > 0) $lastIdx = $i;
            }
        }

        $remaining = $pool;
        $perTujuan = [];   // tujuan => ['sum' => ..., 'ids' => [...]]

        foreach ($rows as $i => $r) {
            $share = $totalKubik > 0 ? ((float) ($r->total_kubik ?? 0)) / $totalKubik : 1 / $n;
            $val   = ($i === $lastIdx) ? round($remaining, 2) : round($pool * $share, 2);
            $remaining -= $val;

            if ($this->hasKirimKontribusi) {
                DB::table(self::TABLE)->where('id', $r->id)->update(['kirim_kontribusi' => $val]);
                $this->kontribusiRowsUpdated++;
            }

            $t = (string) $r->tujuan;
            $perTujuan[$t]['sum']   = ($perTujuan[$t]['sum'] ?? 0) + $val;
            $perTujuan[$t]['ids'][] = $r->id;
        }

        // biaya_kirim tujuan = jumlah kontribusi DO-nya, ditulis ke semua DO di tujuan itu
        foreach ($perTujuan as $t) {
            DB::table(self::TABLE)->whereIn('id', $t['ids'])->update(['biaya_kirim' => round($t['sum'], 2)]);
        }

        $this->biayaKirimUpdated++;
    }

    /** ambil nilai pertama yang terisi dari beberapa kemungkinan header */
    private function pick($row, array $keys)
    {
        foreach ($keys as $k) {
            if (isset($row[$k]) && $row[$k] !== '' && $row[$k] !== '-') {
                return $row[$k];
            }
        }
        return null;
    }

    /**
     * CR            : biaya_kirim tujuan / SUM nilai_muatan tujuan x 100.
     * Hasil kubik   : per baris = total_kubik / kubikasi x 100.
     * Hasil tonase  : per baris = total_tonase / tonase x 100.
     * Pengiriman optimal: per shipment (SUM total / kapasitas >= 85%).
     */
    private function recalcShipments(array $shipments): void
    {
        foreach (array_chunk($shipments, 500) as $chunk) {
            $in       = implode(',', array_fill(0, count($chunk), '?'));
            $bindings = array_merge($chunk, $chunk);

            // ---- CR per shipment + tujuan ----
            DB::update("
                UPDATE logistik_pengiriman lp
                JOIN (
                    SELECT no_shipment, COALESCE(tujuan,'') AS tj,
                           SUM(COALESCE(nilai_muatan,0)) AS sum_nilai
                    FROM logistik_pengiriman
                    WHERE no_shipment IN ($in)
                    GROUP BY no_shipment, COALESCE(tujuan,'')
                ) g ON lp.no_shipment = g.no_shipment AND COALESCE(lp.tujuan,'') = g.tj
                SET lp.cr = IF(g.sum_nilai <= 0, 0,
                               ROUND(COALESCE(lp.biaya_kirim,0) / g.sum_nilai * 100, 4))
                WHERE lp.no_shipment IN ($in)
            ", $bindings);

            // ---- HASIL KUBIK / TONASE (per baris) + OPTIMAL (per shipment) ----
            DB::update("
                UPDATE logistik_pengiriman lp
                JOIN (
                    SELECT no_shipment,
                           SUM(COALESCE(total_kubik, 0))  AS sum_kubik,
                           SUM(COALESCE(total_tonase, 0)) AS sum_tonase,
                           MAX(kubikasi) AS kubikasi,
                           MAX(tonase)   AS tonase
                    FROM logistik_pengiriman
                    WHERE no_shipment IN ($in)
                    GROUP BY no_shipment
                ) x ON lp.no_shipment = x.no_shipment
                SET
                    lp.hasil_kubik = CASE
                        WHEN x.kubikasi > 0 THEN ROUND(COALESCE(lp.total_kubik, 0) / x.kubikasi * 100, 2)
                        ELSE NULL
                    END,
                    lp.hasil_tonase = CASE
                        WHEN x.tonase > 0 THEN ROUND(COALESCE(lp.total_tonase, 0) / x.tonase * 100, 2)
                        ELSE NULL
                    END,
                    lp.pengiriman_optimal = CASE
                        WHEN (x.kubikasi > 0 AND (x.sum_kubik  / x.kubikasi * 100) >= 85)
                          OR (x.tonase   > 0 AND (x.sum_tonase / x.tonase   * 100) >= 85)
                            THEN 'OPTIMAL'
                        WHEN x.kubikasi > 0 OR x.tonase > 0
                            THEN 'TIDAK OPTIMAL'
                        ELSE NULL
                    END
                WHERE lp.no_shipment IN ($in)
            ", $bindings);

            // ---- OPTIMAL PER CUSTOMER (per baris) ----
            if ($this->hasOptimalCustomer) {
                DB::update("
                    UPDATE logistik_pengiriman lp
                    SET lp.pengiriman_optimal_customer = CASE
                        WHEN COALESCE(lp.hasil_kubik, 0)  >= 85
                          OR COALESCE(lp.hasil_tonase, 0) >= 85 THEN 'OPTIMAL'
                        WHEN lp.hasil_kubik IS NOT NULL
                          OR lp.hasil_tonase IS NOT NULL THEN 'TIDAK OPTIMAL'
                        ELSE NULL
                    END
                    WHERE lp.no_shipment IN ($in)
                ", $chunk);
            }

            // ---- REASON OPTIMAL CUSTOMER (otomatis hanya kalau kosong) ----
            if ($this->hasReasonOptimalCustomer && $this->hasOptimalCustomer) {
                DB::update("
                    UPDATE logistik_pengiriman lp
                    SET lp.reason_optimal_customer = CONCAT(
                        'Isi kubik ', COALESCE(lp.hasil_kubik, 0),
                        '%, tonase ', COALESCE(lp.hasil_tonase, 0),
                        '% (di bawah 85%)'
                    )
                    WHERE lp.no_shipment IN ($in)
                      AND lp.pengiriman_optimal_customer = 'TIDAK OPTIMAL'
                      AND NULLIF(TRIM(lp.reason_optimal_customer), '') IS NULL
                ", $chunk);
            }
        }
    }

    // ================= TARIF LOOKUP (sama dengan LogistikImport) =================

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

    /** huruf kecil, buang badan usaha di depan (PT/CV/UD/PD), buang spasi, titik, tanda baca */
    private function ekspedisiKey(?string $v): string
    {
        $k = strtolower($this->normalize($v));
        $k = preg_replace('/^(pt|cv|ud|pd)\b[\s\.]*/', '', $k);
        return preg_replace('/[^a-z0-9]+/', '', $k);
    }

    /** cari nama ekspedisi di master (khusus route ini) yang paling mirip dengan nama di data */
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

    private function findTarif(?string $route, ?string $ekpedisi, ?string $mobil)
    {
        $mobilKey   = $this->normalizeMobil($mobil);
        $routeKey   = $this->normalize($route);
        $candidates = self::$tarifByRoute[$routeKey] ?? null;

        if (!$candidates || $mobilKey === '' || $this->ekspedisiKey($ekpedisi) === '') {
            return null;
        }

        $cacheKey = $routeKey . '|' . $ekpedisi;
        if (!array_key_exists($cacheKey, $this->ekspedisiResolved)) {
            $this->ekspedisiResolved[$cacheKey] = $this->resolveEkspedisiName($ekpedisi, $candidates);
        }

        $master = $this->ekspedisiResolved[$cacheKey];
        $found  = null;

        if ($master !== null) {
            $found = $candidates->first(
                fn($t) =>
                $t->ekpedisi === $master
                    && str_starts_with($this->normalizeMobil($t->mobil), $mobilKey)
            );
        }

        if (!$found) {
            $key = "{$ekpedisi} | {$route} | {$mobil}";
            if (!in_array($key, $this->tarifNotFound, true)) {
                $this->tarifNotFound[] = $key;
            }
        }

        return $found;
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
