<?php

namespace App\Imports;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterImport;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Versi Pasuruan dari LogistikImport.
 * Tabel: logistik_pengiriman_pasuruan (semua kolom bisnis berakhiran _pasuruan).
 * Logika (biaya_kirim, cr, hasil_kubik, hasil_tonase, pengiriman_optimal) sama persis dengan LogistikImport.
 *
 * Di dalam kode semua atribut tetap pakai nama "dasar" (tanpa suffix),
 * lalu dipetakan otomatis ke kolom aktual (nama + _pasuruan) lewat $baseToActual.
 *
 * Template Excel:
 * sales_document, customer_reference, Contract End Date, no_do, planner (berisi kode, mis. MSNLOG-14),
 * no_shipment, tujuan, ekpedisi, route, mobil, nilai_muatan, total_do_qty_car, total_kubik, total_tonase
 */
class LogistikPasuruanImport implements ToCollection, WithHeadingRow, WithEvents, WithCalculatedFormulas
{
    private const TABLE       = 'logistik_pengiriman_pasuruan';
    private const TARIF_TABLE = 'tarif_pengiriman';
    private const SFX         = '_pasuruan';

    // !! SESUAIKAN: nilai kolom Div di tujuanfillterr untuk Pasuruan, dan nilai Div yang ditulis ke baris baru
    private const MASTER_DIV  = 'Pasuruan';
    private const DIV_VALUE   = 'Pasuruan';   // kolom Div varchar(9)

    // kolom yang tidak pernah diisi dari Excel
    private const SKIP_COLUMNS = ['id', 'created_at', 'updated_at'];

    // nilai teks yang dianggap kosong (dibandingkan dengan huruf kecil)
    private const BLANK_TOKENS = ['#value!', '#n/a', '#ref!', '#div/0!', '(blank)'];

    // nama dasar (di kode) -> nama dasar di tabel Pasuruan, hanya untuk yang BEDA
    private const BASE_ALIASES = [
        'tanggal_naik_logistik' => 'tanggal_terima_po',
        'total_do_qty_car'      => 'total_do',
        'sla_loading'           => 'sla_ketepatan_loading',
        'reason_tiba'           => 'reason_waktu_tiba',
        'reason_bongkar'        => 'reason_waktu_bongkar',
    ];

    private const ROUTE_ALIASES = [
        'jabodetabek' => 'Sentul-Jabodetabek',
    ];
    private const EKSPEDISI_ALIASES = [
        // 'namadiexcel' => 'PT Nama Di Master',
    ];
    // kolom hasil lookup master tujuan yang boleh "ikut" baris lain di shipment yang sama
    private const SIBLING_FILL_COLUMNS = [
        'dist_channel', 'area', 'pulau', 'transport_lead_time', 'planner', 'pic_monitoring',
    ];

    // header Excel (sudah di-slug) -> nama dasar, hanya untuk yang namanya BEDA
    private const HEADER_ALIASES = [
        'tgl_terima_dari_admin'  => 'tanggal_naik_logistik',
        'tgl_rencana_kirim'      => 'rencana_kirim',
        'tanggal_tiba_di_gudang' => 'tanggal_tiba_gudang',
        'ac_turutan_bongkar'     => 'act_urutan_bongkar',
        'reason_waktu_tiba'      => 'reason_tiba',
        'reason_waktu_bongkar'   => 'reason_bongkar',
        'nilai_muatan_rp'        => 'nilai_muatan',
        'biaya_kirim_rp'         => 'biaya_kirim',
        'nopol'                  => 'no_pol',
        'via'                    => 'via_kirim',
        'kode_planner'           => 'code_planner',
    ];

    private static $areaMap        = null;   // area => ['planner' => ..., 'pic' => ...]
    private static $customerMap    = null;   // tujuan => row         (cadangan, berdasarkan tujuan)
    private static $customerByCode = null;   // "code|tujuan" => row  (prioritas, code_planner + tujuan)
    private static $tarifByRoute   = null;   // route => rows tarif

    private array $allColumns   = [];      // semua kolom tabel
    private array $dbColumns    = [];      // [kolom aktual => tipe], tanpa SKIP_COLUMNS
    private array $baseToActual = [];      // [nama dasar => kolom aktual]
    private ?array $map = null;            // [header => [kolom aktual, kind]]
    private bool $hasCreatedAt = false;
    private bool $hasUpdatedAt = false;

    // forward-fill (sel merge di Excel)
    private $lastNoShipmentRaw = null;
    private $lastNoShipment    = null;
    private $lastRoute         = null;
    private $lastMobil         = null;
    private $lastEkpedisi      = null;
    private array $shipmentKubik     = [];   // no_shipment => total kubik di file
    private array $ekspedisiResolved = [];   // cache: "route|ekspedisi excel" => nama di master
private array $shipmentKK    = [];   // no_shipment => total kirim_kontribusi di file
private array $shipmentRows  = [];   // no_shipment => jumlah baris di file
private array $kkSeen        = [];   // no_shipment => baris yang sudah diproses
private array $kkAlloc       = [];   // no_shipment => total yang sudah dialokasikan
    // ===== hasil proses =====
    private $inserted = 0;
    private $updated  = 0;
    private $skipped  = 0;
    private $failed   = 0;
    private array $failedList = [];
    private array $allNoShipmentInFile = [];
    private array $ignoredHeaders = [];
    private array $missingColumns = [];
    private array $unknownPlannerCodes = [];
    private array $noLeadTime = [];
    private array $unmatchedTujuan = [];

    public function getInsertedCount(): int { return $this->inserted; }
    public function getUpdatedCount(): int { return $this->updated; }
    public function getImportedCount(): int { return $this->inserted + $this->updated; }
    public function getSkippedCount(): int { return $this->skipped; }
    public function getFailedCount(): int { return $this->failed; }
    public function getFailedList(): array { return $this->failedList; }
    public function getAllNoShipmentInFile(): array { return $this->allNoShipmentInFile; }
    public function getIgnoredHeaders(): array { return array_values(array_unique($this->ignoredHeaders)); }
    public function getMissingColumns(): array { return array_keys($this->missingColumns); }
    public function getNoLeadTime(): array { return array_values(array_unique($this->noLeadTime)); }
    public function getUnmatchedTujuan(): array { return array_values(array_unique($this->unmatchedTujuan)); }
    public function getUnknownPlannerCodes(): array { return array_values(array_unique($this->unknownPlannerCodes)); }

    public function __construct()
    {
        foreach (DB::select('SHOW COLUMNS FROM `' . self::TABLE . '`') as $c) {
            $this->allColumns[] = $c->Field;

            if (in_array($c->Field, self::SKIP_COLUMNS, true)) continue;

            $this->dbColumns[$c->Field] = strtolower($c->Type);

            $base = str_ends_with($c->Field, self::SFX)
                ? substr($c->Field, 0, -strlen(self::SFX))
                : $c->Field;                       // mis. "Div"
            $this->baseToActual[$base] = $c->Field;
        }

        $this->hasCreatedAt = in_array('created_at', $this->allColumns, true);
        $this->hasUpdatedAt = in_array('updated_at', $this->allColumns, true);

        if (self::$customerMap === null) {
            $rows = DB::table('tujuanfillterr')
                ->select('tujuan', 'code_planner', 'Planner', 'dist_channel', 'pulau', 'area',
                         'biaya_kuli', 'transport_lead_time', 'Monitoring')
                ->where('Div', self::MASTER_DIV)
                ->get();

            self::$customerMap    = [];
            self::$customerByCode = [];

            foreach ($rows as $r) {
                $t = $this->normTujuan($r->tujuan);
                if ($t === '') continue;

                self::$customerMap[$t] ??= $r;

                $code = strtolower(trim((string) $r->code_planner));
                if ($code !== '') {
                    self::$customerByCode[$code . '|' . $t] ??= $r;
                }
            }

            // ---- peta area -> planner & PIC (paling sering muncul di master) ----
            $tally = [];
            foreach ($rows as $r) {
                $a = trim((string) $r->area);
                if ($a === '') continue;

                $p = trim((string) $r->Planner);
                $m = trim((string) $r->Monitoring);

                if ($p !== '') $tally[$a]['p'][$p] = ($tally[$a]['p'][$p] ?? 0) + 1;
                if ($m !== '') $tally[$a]['m'][$m] = ($tally[$a]['m'][$m] ?? 0) + 1;
            }

            self::$areaMap = [];
            foreach ($tally as $a => $t) {
                self::$areaMap[(string) $a] = [
                    'planner' => isset($t['p']) ? (string) array_search(max($t['p']), $t['p']) : null,
                    'pic'     => isset($t['m']) ? (string) array_search(max($t['m']), $t['m']) : null,
                ];
            }
        }

        if (self::$tarifByRoute === null) {
            self::$tarifByRoute = DB::table(self::TARIF_TABLE)
                ->select('ekpedisi', 'route', 'mobil', 'biaya_kirim', 'kubikasi', 'tonase')
                ->get()
                ->groupBy(fn($row) => $this->normalize($row->route));
        }
    }

    // =========================================================
    // PEMETAAN NAMA KOLOM (dasar -> aktual _pasuruan)
    // =========================================================
    /** nama dasar (atau alias dasar) -> nama kolom aktual di tabel, null kalau tidak ada */
    private function actualCol(string $base): ?string
    {
        $base = self::BASE_ALIASES[$base] ?? $base;
        return $this->baseToActual[$base] ?? null;
    }

    /** nama kolom aktual untuk dipakai di SQL mentah (wajib ada) */
    private function c(string $base): string
    {
        return $this->actualCol($base) ?? ($base . self::SFX);
    }

    private function hasCol(string $base): bool
    {
        return $this->actualCol($base) !== null;
    }

    /** ekspresi angka dari kolom varchar/decimal untuk SQL */
    private function num(string $expr): string
    {
        return "CAST(NULLIF(TRIM($expr), '') AS DECIMAL(18,4))";
    }

    /**
     * Header Excel Pasuruan bisa berakhiran _pasuruan (mis. no_shipment_pasuruan, tujuan_pasuruan)
     * atau memakai nama dasar. Di sini keduanya disamakan: kalau ada key "xxx_pasuruan"
     * dan "xxx" belum ada, "xxx" ikut dibuat (plus nama alias dasarnya).
     */
    private function normalizeRow(array $row): array
    {
        $flip = array_flip(self::BASE_ALIASES);   // nama tabel -> nama dasar di kode

        foreach ($row as $k => $v) {
            if (!is_string($k)) continue;

            $base = str_ends_with($k, self::SFX) ? substr($k, 0, -strlen(self::SFX)) : $k;

            if ($base !== $k && !array_key_exists($base, $row)) {
                $row[$base] = $v;
            }
            if (isset($flip[$base]) && !array_key_exists($flip[$base], $row)) {
                $row[$flip[$base]] = $v;
            }
        }

        return $row;
    }

    private function normTujuan(?string $value): string
    {
        $value = str_replace("\xC2\xA0", ' ', (string) $value);
        return preg_replace('/\s+/', ' ', strtolower(trim($value)));
    }

    // =========================================================
    // IMPORT PER BARIS
    // =========================================================
    public function collection(Collection $rows)
    {
        // total kubik per shipment (ikut aturan merge no_shipment)
        $this->shipmentKubik = [];
        $lastNo = null;
        foreach ($rows as $rc) {
            $r  = $this->normalizeRow($rc->toArray());
            $no = $this->cleanText($r['no_shipment'] ?? null);
            $tj = $this->cleanText($r['tujuan'] ?? null);

            if ($no === null && $tj !== null) $no = $lastNo;
            if ($no !== null) $lastNo = $no;
            if ($no === null || $tj === null) continue;

            $k = $this->parseNumber($this->pick($r, ['total_kubik', 'total_kubik_m3', 'total_kubik_m_3', 'totalkubik']));
            if ($k !== null && $k > 0) {
                $this->shipmentKubik[$no] = ($this->shipmentKubik[$no] ?? 0) + $k;
            }
            $this->shipmentRows[$no] = ($this->shipmentRows[$no] ?? 0) + 1;

$kk = $this->parseNumber($this->pick($r, ['kirim_kontribusi', 'kirim_kontribusi_pasuruan']));
if ($kk !== null && $kk > 0) {
    $this->shipmentKK[$no] = ($this->shipmentKK[$no] ?? 0) + $kk;
}
        }

        foreach ($rows as $rowCollection) {
            $row = $this->normalizeRow($rowCollection->toArray());

            if ($this->map === null) {
                $this->buildMap(array_keys($row));
                logger()->info('LOGISTIK PASURUAN IMPORT: header terbaca', array_keys($row));
            }

            // 1) baris kosong total -> tidak masuk
            if ($this->isEmptyRow($row)) {
                $this->skipped++;
                continue;
            }

            $noShipment = $this->cleanText($row['no_shipment'] ?? null);
            $tujuan     = $this->cleanText($row['tujuan'] ?? null);

            // sel merge: no_shipment kosong tapi tujuan ada -> ikut baris di atas
            if ($noShipment === null && $tujuan !== null) {
                $noShipment = $this->lastNoShipmentRaw;
            }
            if ($noShipment !== null) {
                $this->lastNoShipmentRaw = $noShipment;
            }

            // 2) no_shipment atau tujuan kosong -> tidak masuk
            if ($noShipment === null || $tujuan === null) {
                $this->skipped++;
                continue;
            }

            $this->allNoShipmentInFile[] = $noShipment;

            try {
                $attrs = $this->buildAttributes($row, $noShipment, $tujuan);
                $this->save($noShipment, $tujuan, $attrs);
            } catch (\Throwable $e) {
                $this->failed++;
                $this->failedList[] = [
                    'no_shipment' => $noShipment,
                    'tujuan'      => $tujuan,
                    'error'       => $e->getMessage(),
                    'line'        => $e->getLine(),
                ];

                logger()->error('LOGISTIK PASURUAN IMPORT GAGAL PER BARIS', [
                    'no_shipment' => $noShipment,
                    'tujuan'      => $tujuan,
                    'error'       => $e->getMessage(),
                    'file'        => $e->getFile(),
                    'line'        => $e->getLine(),
                ]);
            }
        }

        if (!empty($this->ignoredHeaders)) {
            logger()->info('LOGISTIK PASURUAN IMPORT: header Excel tanpa kolom DB (diabaikan)', $this->getIgnoredHeaders());
        }
        if (!empty($this->missingColumns)) {
            logger()->warning('LOGISTIK PASURUAN IMPORT: kolom belum ada di tabel (tidak disimpan)', $this->getMissingColumns());
        }
        if (!empty($this->unknownPlannerCodes)) {
            logger()->warning('LOGISTIK PASURUAN IMPORT: code_planner tidak ada di master', $this->getUnknownPlannerCodes());
        }
    }

    // =========================================================
    // SIMPAN (insert / update berdasarkan no_shipment + tujuan + no_do)
    // =========================================================
    private function save(string $noShipment, string $tujuan, array $attrs): void
    {
        // $attrs sudah berkey kolom aktual (hasil toActual)
        $colNo = $this->c('no_shipment');
        $colTj = $this->c('tujuan');

        if (isset($this->dbColumns[$colNo])) $attrs[$colNo] = $noShipment;
        if (isset($this->dbColumns[$colTj])) $attrs[$colTj] = $tujuan;

        $now = now();

        // satu shipment + tujuan bisa punya banyak DO (mis. CHI / DRY / FRZ) -> no_do ikut jadi kunci
     $colDo = $this->actualCol('no_delivery') ?? $this->actualCol('no_do');
$noDo  = ($colDo !== null && isset($attrs[$colDo])) ? $attrs[$colDo] : null;
        $q = DB::table(self::TABLE)
            ->where($colNo, $noShipment)
            ->where($colTj, $tujuan);

        if ($noDo !== null) {
            // cocokkan no_do; kalau tidak ada, pakai baris lama yang no_do-nya masih kosong
            $existingId = (clone $q)->where($colDo, $noDo)->value('id')
                ?? (clone $q)->where(function ($w) use ($colDo) {
                        $w->whereNull($colDo)->orWhere($colDo, '');
                    })->value('id');
        } else {
            $existingId = $q->value('id');
        }

        if ($existingId) {
            if ($this->hasUpdatedAt) $attrs['updated_at'] = $now;

            DB::table(self::TABLE)->where('id', $existingId)->update($attrs);
            $this->updated++;
            return;
        }

        // default hanya untuk baris BARU
        if ($this->hasCol('create_tgl')) {
            $k = $this->c('create_tgl');
            $attrs[$k] ??= $now->format('Y-m-d H:i:s');
        }
        if ($this->hasCol('ketersediaan_unit')) {
            $k = $this->c('ketersediaan_unit');
            $attrs[$k] ??= 'BELUM DAPAT';
        }
        if ($this->hasCreatedAt) $attrs['created_at'] = $now;
        if ($this->hasUpdatedAt) $attrs['updated_at'] = $now;

        DB::table(self::TABLE)->insert($attrs);
        $this->inserted++;
    }

    /** nama dasar -> kolom aktual; buang null (supaya data lama tidak tertimpa) dan key yang belum ada di tabel */
    private function toActual(array $attrs): array
    {
        $out = [];

        foreach ($attrs as $base => $v) {
            if ($v === null) continue;

            $col = $this->actualCol($base);
            if ($col === null) {
                $this->missingColumns[$base] = true;
                continue;
            }

            $out[$col] = $v;
        }

        return $out;
    }

    // =========================================================
    // BANGUN ATRIBUT
    // =========================================================
    private function buildAttributes(array $row, string $noShipment, string $tujuan): array
    {
        // ================= GUDANG =================
        $planningLoading     = $this->convertDateTime($row['planning_loading'] ?? null);
        $tanggalTibaGudang   = $this->convertDateTime($this->pick($row, ['tanggal_tiba_gudang', 'tanggal_tiba_di_gudang']));
        $tanggalKeluarGudang = $this->convertDateTime($row['tanggal_keluar_gudang'] ?? null);

        $lamaDigudang = null;
        $slaLoading   = null;

        if ($tanggalTibaGudang && $tanggalKeluarGudang) {
            $selisihGudang = (int) date_diff(date_create($tanggalTibaGudang), date_create($tanggalKeluarGudang))->format('%a');
            $lamaDigudang  = $selisihGudang;                       // kolom int (hari)
            $slaLoading    = ($selisihGudang == 0) ? 'On Time' : 'Delay';
        }

        // ================= DATE =================
        $rencanaKirim        = $this->convertDateTime($this->pick($row, ['rencana_kirim', 'tgl_rencana_kirim']));
        $tanggalNaikLogistik = $this->convertDateTime($this->pick($row, ['tanggal_naik_logistik', 'tgl_terima_dari_admin']));
        $tanggalDptUnit      = $this->convertDateTime($row['tanggal_dpt_unit'] ?? null);
        $tanggalTibaAktual   = $this->convertDateTime($row['tanggal_tiba'] ?? null);
        $tanggalBongkar      = $this->convertDateTime($row['tanggal_bongkar'] ?? null);
        $actPgiDate          = $this->convertDate($row['act_pgi_date'] ?? null);
        $contractEndDate     = $this->convertDate($row['contract_end_date'] ?? null);

        // ================= SLA DAPAT MOBIL =================
        $lamaWaktuPencarian = null;
        $slaDapatMobil      = null;

        if ($tanggalDptUnit && $tanggalTibaGudang) {
            $selisihCariMobil   = (int) date_diff(date_create($tanggalDptUnit), date_create($tanggalTibaGudang))->format('%a');
            $lamaWaktuPencarian = $selisihCariMobil;               // kolom int (hari)
            $slaDapatMobil      = ($selisihCariMobil == 0) ? 'On Time' : 'Delay';
        }

        // ================= FORWARD-FILL (route/mobil/ekpedisi) =================
        if ($noShipment !== $this->lastNoShipment) {
            $this->lastRoute    = null;
            $this->lastMobil    = null;
            $this->lastEkpedisi = null;
        }

        $route    = $this->applyRouteAlias($this->cleanText($row['route'] ?? null) ?: $this->lastRoute);
        $mobil    = $this->cleanText($row['mobil'] ?? null)    ?: $this->lastMobil;
        $ekpedisi = $this->cleanText($row['ekpedisi'] ?? null) ?: $this->lastEkpedisi;

        if ($route)    $this->lastRoute    = $route;
        if ($mobil)    $this->lastMobil    = $mobil;
        if ($ekpedisi) $this->lastEkpedisi = $ekpedisi;

        $this->lastNoShipment = $noShipment;

        // ================= ANGKA DARI EXCEL =================
        $totalDoCar  = $this->parseNumber($row['total_do_qty_car'] ?? null);
        $totalKubik  = $this->parseNumber($this->pick($row, ['total_kubik', 'total_kubik_m3', 'total_kubik_m_3', 'totalkubik']));
        $totalTonase = $this->parseNumber($this->pick($row, ['total_tonase', 'total_tonase_ton', 'totaltonase']));
        $nilaiMuatan = $this->parseNumber($this->pick($row, ['nilai_muatan', 'nilai_muatan_rp', 'nilai_muatan_rp_', 'nilai_muatan_pasuruan']));


        // ================= KIRIM KONTRIBUSI (dibagi per kontribusi total_kubik) =================
$kirimKontribusi = null;
$totKK = $this->shipmentKK[$noShipment] ?? 0;

if ($totKK > 0) {
    $sumKubik = $this->shipmentKubik[$noShipment] ?? 0;
    $nRows    = $this->shipmentRows[$noShipment] ?? 1;

    $this->kkSeen[$noShipment] = ($this->kkSeen[$noShipment] ?? 0) + 1;

    if ($this->kkSeen[$noShipment] >= $nRows) {
        // baris terakhir: ambil sisa supaya total tepat (tidak ada selisih pembulatan)
        $kirimKontribusi = round($totKK - ($this->kkAlloc[$noShipment] ?? 0), 2);
    } else {
        $share = $sumKubik > 0
            ? (($totalKubik ?? 0) / $sumKubik)
            : (1 / $nRows);                    // fallback kalau kubik kosong semua
        $kirimKontribusi = round($totKK * $share, 2);
    }

    $this->kkAlloc[$noShipment] = ($this->kkAlloc[$noShipment] ?? 0) + $kirimKontribusi;
}


        // ================= BIAYA KIRIM & KAPASITAS DARI TARIF =================
        // biaya tarif = biaya 1 mobil penuh (sesuai route + ekpedisi + mobil).
        // biaya per baris = total_kubik / kubikasi x biaya tarif (porsi kubik baris ini).
      // ================= KAPASITAS DARI TARIF (biaya tarif TIDAK dipakai) =================
$tarifRow = $this->findTarif($route, $ekpedisi, $mobil);
$kubikasi = $tarifRow ? $this->parseNumber($tarifRow->kubikasi ?? null) : null;
$tonase   = $tarifRow ? $this->parseNumber($tarifRow->tonase ?? null)   : null;

// biaya_kirim = kirim_kontribusi (total template dibagi per total_kubik)
$biayaKirim = $kirimKontribusi;
        // ================= LOOKUP MASTER TUJUAN =================
        $tujuanKey    = $this->normTujuan($tujuan);
        $customerData = self::$customerMap[$tujuanKey] ?? null;   // cadangan (berdasarkan tujuan)

        if ($customerData === null) {
            $this->unmatchedTujuan[] = $tujuan;
        }

        // ================= PLANNER & PIC MONITORING =================
        $plannerRaw = $this->cleanText($row['planner'] ?? null);
        $codeExcel  = $this->cleanText($this->pick($row, ['code_planner', 'kode_planner']));

        // kolom "planner" di template berisi KODE (mis. MSNLOG-14) -> perlakukan sebagai code_planner
        if ($codeExcel === null && $plannerRaw !== null && preg_match('/^[A-Za-z]+-\d+$/', $plannerRaw)) {
            $codeExcel  = $plannerRaw;
            $plannerRaw = null;
        }
        // supaya loop "sisa header" di bawah tidak menulis kode ke kolom planner
        $row['planner'] = $plannerRaw;

        if ($codeExcel !== null) {
            // PRIORITAS: code_planner dari Excel + tujuan
            $plannerRow = self::$customerByCode[strtolower($codeExcel) . '|' . $tujuanKey] ?? null;

            if ($plannerRow === null) {
                $this->unknownPlannerCodes[] = $codeExcel . ' @ ' . $tujuan;
            }

            $planner       = $this->cleanText($plannerRow->Planner ?? null)
                             ?: $this->cleanText($customerData->Planner ?? null)
                             ?: $plannerRaw;
            $picMonitoring = $this->cleanText($plannerRow->Monitoring ?? null)
                             ?: $this->cleanText($customerData->Monitoring ?? null);
        } else {
            // CADANGAN: code_planner kosong -> ambil dari DB berdasarkan tujuan
            $planner       = $this->cleanText($customerData->Planner ?? null)
                             ?: $plannerRaw;
            $picMonitoring = $this->cleanText($customerData->Monitoring ?? null);
        }

        $distChannel         = $customerData->dist_channel ?? null;
        $area                = $customerData->area ?? null;
        $biayaKuli           = $customerData->biaya_kuli ?? null;
        $transport_lead_time = $customerData->transport_lead_time ?? null;
        $pulau               = ($customerData->pulau ?? null) ?: $this->cleanText($row['pulau'] ?? null);

        if ($biayaKuli !== null && is_numeric($biayaKuli)) $biayaKuli = (int) round((float) $biayaKuli);
        if ($transport_lead_time !== null && is_numeric($transport_lead_time)) $transport_lead_time = (int) $transport_lead_time;

        // ================= MONITORING =================
        $keluar = $tanggalKeluarGudang ? strtotime($tanggalKeluarGudang) : null;

        $tiba    = $tanggalTibaAktual ? strtotime($tanggalTibaAktual) : null;
        $bongkar = $tanggalBongkar ? strtotime($tanggalBongkar) : null;

        $leadRaw  = $transport_lead_time ?? ($row['transport_lead_time'] ?? null);
        $leadtime = ($leadRaw !== null && $leadRaw !== '' && is_numeric($leadRaw)) ? (int) $leadRaw : null;
        $estimasi = ($keluar && $leadtime !== null) ? strtotime("+{$leadtime} days", $keluar) : null;

        if ($leadtime === null) {
            $this->noLeadTime[] = $noShipment . ' - ' . $tujuan;
        }

        $lamaPerjalanan = ($keluar && $tiba) ? (int) max(0, floor(($tiba - $keluar) / 86400)) : null;
        $slaTiba        = ($tiba && $estimasi) ? (($tiba <= $estimasi) ? 'On Time' : 'Delay') : null;

        $overstay   = ($tiba && $bongkar) ? (int) max(0, floor(($bongkar - $tiba) / 86400)) : null;
        $slaBongkar = ($tiba && $bongkar) ? (($overstay <= 0) ? 'On Time' : 'Delay') : null;

        $logic           = $this->generateStatusAlert($slaTiba, $slaBongkar);
        $statusAkhir     = $logic['status_akhir'];
        $monitoringAlert = $logic['alert'];

        // ================= KETERSEDIAAN UNIT (hanya kalau ada di Excel) =================
        $ketersediaanUnit = $this->cleanText($row['ketersediaan_unit'] ?? null);

        if ($ketersediaanUnit !== null) {
            $ketersediaanUnit = strtoupper($ketersediaanUnit);

            if (in_array($ketersediaanUnit, ['SUDAH DAPAT MOBIL', 'READY MOBIL', 'READY'])) {
                $ketersediaanUnit = 'SUDAH DAPAT';
            }
            if (in_array($ketersediaanUnit, ['BELUM DAPAT MOBIL', 'PENDING'])) {
                $ketersediaanUnit = 'BELUM DAPAT';
            }
        }

        $actUrutan = $this->parseNumber($this->pick($row, ['act_urutan_bongkar', 'ac_turutan_bongkar']));

        // cr / hasil_kubik / hasil_tonase / pengiriman_optimal dihitung di recalcShipments()
        // (key = nama dasar; otomatis jadi *_pasuruan lewat toActual)
        $attrs = [
            'transport_lead_time' => $transport_lead_time,
            'planner'             => $planner,
            'dist_channel'        => $distChannel,
            'area'                => $area,
            'no_delivery' => $this->cleanText($row['no_delivery'] ?? null),
            'kirim_kontribusi' => $kirimKontribusi,   // -> kirim_kontribusi_pasuruan
            'ketersediaan_unit'   => $ketersediaanUnit,
            'Div'                 => self::DIV_VALUE,

            // ---- kolom template Excel ----
            'sales_document'     => $this->cleanText($row['sales_document'] ?? null),
            'customer_reference' => $this->cleanText($row['customer_reference'] ?? null),
            'contract_end_date'  => $contractEndDate,
            'no_do'              => $this->cleanText($row['no_do'] ?? null),

            'pulau'     => $pulau,
            'route'     => $route,
            'via_kirim' => $this->cleanText($row['via_kirim'] ?? $row['via'] ?? null),
            'mobil'     => $mobil,

            'perubahan_mobil'    => $this->cleanText($row['perubahan_mobil'] ?? null),
            'kategori_ekspedisi' => $this->getKategoriEkspedisi($noShipment)
                                    ?? $this->cleanText($row['kategori_ekspedisi'] ?? null),
            'ekpedisi'           => $ekpedisi,
            'ekspedisi'          => $ekpedisi,   // tabel Pasuruan punya dua kolom (ekpedisi_ & ekspedisi_)
            'kubikasi'           => $kubikasi,
            'tonase'             => $tonase,
            'total_kubik'        => $totalKubik,
            'total_tonase'       => $totalTonase,
            'total_do_qty_car'   => $totalDoCar !== null ? (int) round($totalDoCar) : null,

            'nama_driver' => $this->cleanText($row['nama_driver'] ?? null),
            'no_pol'      => $this->cleanText($row['no_pol'] ?? ($row['nopol'] ?? null)),

            'nilai_muatan' => $nilaiMuatan,
            'biaya_kuli'   => $biayaKuli,
            'biaya_kirim'  => $biayaKirim,

            'tanggal_naik_logistik' => $tanggalNaikLogistik,   // -> tanggal_terima_po_pasuruan
            'rencana_kirim'         => $rencanaKirim,
            'tanggal_dpt_unit'      => $tanggalDptUnit,
            'planning_loading'      => $planningLoading,
            'tanggal_tiba_gudang'   => $tanggalTibaGudang,
            'tanggal_keluar_gudang' => $tanggalKeluarGudang,
            'tanggal_tiba'          => $tanggalTibaAktual,
            'tanggal_bongkar'       => $tanggalBongkar,

            'estimasi_tiba' => $estimasi ? date('Y-m-d', $estimasi) : null,

            'lama_perjalanan'  => $lamaPerjalanan,
            'sla_tiba'         => $slaTiba,
            'overstay_days'    => $overstay,
            'sla_bongkar'      => $slaBongkar,
            'status_akhir'     => $statusAkhir,
            'monitoring_alert' => $monitoringAlert,

            'lama_waktu_pencarian' => $lamaWaktuPencarian,
            'sla_dapat_mobil'      => $slaDapatMobil,
            'lama_digudang'        => $lamaDigudang,
            'sla_loading'          => $slaLoading,    // -> sla_ketepatan_loading_pasuruan

            'pic_monitoring'   => $picMonitoring,
            'status_kendaraan' => $this->cleanText($row['status_kendaraan'] ?? null),
            'action_required'  => $this->cleanText($row['action_required'] ?? null),

            'act_urutan_bongkar' => $actUrutan !== null ? (int) round($actUrutan) : null,

            'reason_tiba'    => $this->cleanText($this->pick($row, ['reason_tiba', 'reason_waktu_tiba'])),     // -> reason_waktu_tiba_pasuruan
            'reason_bongkar' => $this->cleanText($this->pick($row, ['reason_bongkar', 'reason_waktu_bongkar'])), // -> reason_waktu_bongkar_pasuruan

            'act_pgi_date' => $actPgiDate,
        ];

        $out = $this->toActual($attrs);

        // ================= SISA HEADER EXCEL (apa adanya) =================
        // header yang cocok dengan kolom DB tapi belum diisi di atas
        foreach ($this->map as $header => [$col, $kind]) {
            if (($out[$col] ?? null) !== null) continue;

            $v = $this->convert($row[$header] ?? null, $kind, $col);
            if ($v !== null) {
                $out[$col] = $v;
            }
        }

        return $out;
    }

    private function applyRouteAlias(?string $route): ?string
    {
        if ($route === null || $route === '') return $route;

        return self::ROUTE_ALIASES[$this->normalize($route)] ?? $route;
    }

    private function generateStatusAlert($sla_tiba, $sla_bongkar): array
    {
        if ($sla_tiba === null || $sla_bongkar === null) {
            return ['status_akhir' => null, 'alert' => null];
        }

        $sla_tiba    = strtolower(trim($sla_tiba));
        $sla_bongkar = strtolower(trim($sla_bongkar));

        if ($sla_tiba == 'on time' && $sla_bongkar == 'on time') {
            return ['status_akhir' => 'On Time Total', 'alert' => 'Delivered On Time'];
        }
        if ($sla_tiba == 'delay' && $sla_bongkar == 'on time') {
            return ['status_akhir' => 'Delay Perjalanan', 'alert' => 'Delay Perjalanan'];
        }
        if ($sla_tiba == 'on time' && $sla_bongkar == 'delay') {
            return ['status_akhir' => 'Delay Pembongkaran', 'alert' => 'Delay Pembongkaran'];
        }
        return ['status_akhir' => 'Delay Total', 'alert' => 'Delivered Delay'];
    }

    // =========================================================
    // MAPPING HEADER -> KOLOM DB
    // =========================================================
    private function buildMap(array $headers): void
    {
        $this->map = [];

        foreach ($headers as $h) {
            if (!is_string($h) || $h === '') continue;

            // 1) header = nama dasar / alias dasar  2) header = nama kolom aktual (sudah ada _pasuruan)
            // 3) header ada di HEADER_ALIASES
            $col = $this->actualCol($h);
            if ($col === null && isset($this->dbColumns[$h])) $col = $h;
            if ($col === null && isset(self::HEADER_ALIASES[$h])) $col = $this->actualCol(self::HEADER_ALIASES[$h]);

            if ($col === null || !isset($this->dbColumns[$col])) {
                $this->ignoredHeaders[] = $h;
                continue;
            }

            $this->map[$h] = [$col, $this->kind($this->dbColumns[$col])];
        }
    }

    private function kind(string $type): string
    {
        if (str_starts_with($type, 'datetime') || str_starts_with($type, 'timestamp')) return 'datetime';
        if (str_starts_with($type, 'date')) return 'date';
        if (str_starts_with($type, 'time')) return 'time';
        if (preg_match('/^(tinyint|smallint|mediumint|int|bigint)/', $type)) return 'int';
        if (preg_match('/^(decimal|numeric|float|double)/', $type)) return 'decimal';
        return 'string';
    }

    // =========================================================
    // HELPERS
    // =========================================================
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $v) {
            if (!$this->blank($v)) return false;
        }
        return true;
    }

    private function blank($value): bool
    {
        if ($value === null) return true;

        if (is_string($value)) {
            $s = trim($value);
            return $s === '' || in_array(strtolower($s), self::BLANK_TOKENS, true);
        }

        return false;
    }

    private function cleanText($value): ?string
    {
        if ($value === null || is_array($value)) return null;

        if (is_float($value) && floor($value) == $value && abs($value) < 1e15) {
            return (string) (int) $value;           // 4200067817.0 -> "4200067817"
        }

        $value = trim((string) $value);

        if ($value === '' || $value === '-' || in_array(strtolower($value), self::BLANK_TOKENS, true)) {
            return null;
        }
        if (str_starts_with($value, '=')) return null;   // formula yang tidak terhitung

        return $value;
    }

    private function pick(array $row, array $keys)
    {
        foreach ($keys as $k) {
            if (isset($row[$k]) && !$this->blank($row[$k]) && $row[$k] !== '-') {
                return $row[$k];
            }
        }
        return null;
    }

    private function getKategoriEkspedisi(?string $noShipment): ?string
    {
        $no = trim((string) $noShipment);

        if (str_starts_with($no, '45')) return 'Kontrak';
        if (str_starts_with($no, '42')) return 'Oncall';

        return null;
    }

    private function convertDate($value)
    {
        return $this->toDate($value, 'Y-m-d');
    }

    private function convertDateTime($value)
    {
        return $this->toDate($value, 'Y-m-d H:i:s');
    }

    private function toDate($value, string $format): ?string
    {
        if ($this->blank($value) || $value === '-') return null;

        if (is_numeric($value)) {
            if ((float) $value <= 0) return null;
            return Date::excelToDateTimeObject($value)->format($format);
        }

        // 04-09-2026 / 04/09/2026 -> d-m-Y
        $ts = strtotime(str_replace('/', '-', trim((string) $value)));
        return $ts ? date($format, $ts) : null;
    }

    private function toTime($value): ?string
    {
        if ($this->blank($value) || $value === '-') return null;

        if (is_numeric($value)) {
            return gmdate('H:i:s', (int) round(fmod((float) $value, 1) * 86400));
        }

        $ts = strtotime(trim((string) $value));
        return $ts ? date('H:i:s', $ts) : null;
    }

    private function parseNumber($value): ?float
    {
        if ($value === null) return null;
        if (is_int($value) || is_float($value)) return (float) $value;

        $s = trim(str_ireplace(['Rp', ' ', '%'], '', (string) $value));
        if ($s === '' || $s === '-') return null;

        if (preg_match('/^-?\d{1,3}(,\d{3})+(\.\d+)?$/', $s)) {
            $s = str_replace(',', '', $s);                         // 76,559,246 / 911,032
        } elseif (preg_match('/^-?\d{1,3}(\.\d{3})+(,\d+)?$/', $s)) {
            $s = str_replace(['.', ','], ['', '.'], $s);           // 76.559.246,5
        } elseif (preg_match('/^-?\d+,\d+$/', $s)) {
            $s = str_replace(',', '.', $s);                        // 12,5
        } else {
            $s = str_replace(',', '', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    /** konversi nilai Excel sesuai tipe kolom DB (untuk header yang dicocokkan otomatis) */
    private function convert($value, string $kind, string $col)
    {
        if ($this->blank($value)) return null;
        if (is_string($value)) $value = trim($value);

        switch ($kind) {
            case 'date':
                return $this->toDate($value, 'Y-m-d');

            case 'datetime':
                return $this->toDate($value, 'Y-m-d H:i:s');

            case 'time':
                return $this->toTime($value);

            case 'int':
                $n = $this->parseNumber($value);
                return $n === null ? null : (int) round($n);

            case 'decimal':
                return $this->parseNumber($value);

            default: // string
                if (is_numeric($value)) {
                    if (preg_match('/^waktu_/', $col)) {
                        return $this->toTime($value);
                    }
                    if ($value > 20000 && $value < 80000
                        && preg_match('/(^|_)(tanggal|date|etd|eta|atd|ata|estimasi)(_|$)|^planning|^rencana|^create_on/', $col)) {
                        return $this->toDate($value, 'Y-m-d');
                    }
                }
                return $this->cleanText($value);
        }
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

    /** cari nama ekspedisi di master (khusus route ini) yang paling mirip dengan nama di Excel */
    private function resolveEkspedisiName(?string $name, $candidates): ?string
    {
        $key = $this->ekspedisiKey($name);
        if ($key === '') return null;

        if (isset(self::EKSPEDISI_ALIASES[$key])) {
            return self::EKSPEDISI_ALIASES[$key];
        }

        $best = null; $bestScore = 0.0; $second = 0.0;

        foreach ($candidates->pluck('ekpedisi')->filter()->unique() as $master) {
            $mk = $this->ekspedisiKey($master);
            if ($mk === '') continue;

            if ($mk === $key) return $master;

            similar_text($key, $mk, $pct);
            $score = $pct / 100;

            if (min(strlen($key), strlen($mk)) >= 6
                && (str_contains($mk, $key) || str_contains($key, $mk))) {
                $score = max($score, 0.9);
            }

            if ($score > $bestScore)  { $second = $bestScore; $bestScore = $score; $best = $master; }
            elseif ($score > $second) { $second = $score; }
        }

        return ($best !== null && $bestScore >= 0.85 && ($bestScore - $second) >= 0.05) ? $best : null;
    }

    private function findTarif(?string $route, ?string $ekpedisi, ?string $mobil)
    {
        $mobilExcel = $this->normalizeMobil($mobil);
        $routeKey   = $this->normalize($route);
        $candidates = self::$tarifByRoute[$routeKey] ?? null;

        if (!$candidates || $mobilExcel === '' || $this->ekspedisiKey($ekpedisi) === '') {
            return null;
        }

        $cacheKey = $routeKey . '|' . $ekpedisi;
        if (!array_key_exists($cacheKey, $this->ekspedisiResolved)) {
            $master = $this->resolveEkspedisiName($ekpedisi, $candidates);
            $this->ekspedisiResolved[$cacheKey] = $master;

            if ($master !== null && $this->ekspedisiKey($master) !== $this->ekspedisiKey($ekpedisi)) {
                logger()->info('LOGISTIK PASURUAN IMPORT: nama ekspedisi disamakan dengan master', [
                    'route' => $route, 'excel' => $ekpedisi, 'master' => $master,
                ]);
            }
        }

        $master = $this->ekspedisiResolved[$cacheKey];
        $found  = null;

        if ($master !== null) {
            $found = $candidates->first(fn($row) =>
                $row->ekpedisi === $master
                && str_starts_with($this->normalizeMobil($row->mobil), $mobilExcel)
            );
        }

        if (!$found) {
            logger()->warning('LOGISTIK PASURUAN IMPORT: tarif tidak ditemukan', [
                'route' => $route, 'ekpedisi' => $ekpedisi, 'mobil' => $mobil,
            ]);
        }

        return $found;
    }

    // =========================================================
    // SETELAH IMPORT
    // =========================================================
    public function registerEvents(): array
    {
        return [
            AfterImport::class => function () {
                try {
                    $shipments = array_map('strval', array_values(array_unique($this->allNoShipmentInFile)));

                    $this->fillShipmentGaps($shipments);
                    $this->fillAreaAndPlannerGaps($shipments);
                    $this->fillMasterGapsFromSiblings($shipments);
                    $this->recalcShipments($shipments);
                } catch (\Throwable $e) {
                    logger()->error('LOGISTIK PASURUAN IMPORT AFTER-IMPORT GAGAL', [
                        'error' => $e->getMessage(),
                        'line'  => $e->getLine(),
                    ]);
                }
            },
        ];
    }

    /**
     * kolom master yang kosong -> ikut baris lain di no_shipment yang sama.
     * Hanya diisi kalau nilai di shipment itu seragam (1 nilai unik); kalau campur, dilewati.
     */
    private function fillMasterGapsFromSiblings(array $shipments): void
    {
        $ns = $this->c('no_shipment');

        foreach (array_chunk($shipments, 500) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));

            foreach (self::SIBLING_FILL_COLUMNS as $base) {
                if (!$this->hasCol($base)) continue;
                $col = $this->c($base);

                DB::update("
                    UPDATE " . self::TABLE . " lp
                    JOIN (
                        SELECT $ns AS ns, MIN(TRIM($col)) AS val
                        FROM " . self::TABLE . "
                        WHERE NULLIF(TRIM($col), '') IS NOT NULL
                          AND $ns IN ($in)
                        GROUP BY $ns
                        HAVING COUNT(DISTINCT TRIM($col)) = 1
                    ) x ON lp.$ns = x.ns
                    SET lp.$col = x.val
                    WHERE NULLIF(TRIM(lp.$col), '') IS NULL
                      AND lp.$ns IN ($in)
                ", array_merge($chunk, $chunk));
            }
        }
    }

    /**
     * 1) area kosong -> ikut area baris lain di no_shipment yang sama
     *    (hanya kalau shipment itu cuma punya 1 area; kalau areanya campur, dilewati)
     * 2) planner / pic_monitoring kosong -> isi dari master berdasarkan area
     */
    private function fillAreaAndPlannerGaps(array $shipments): void
    {
        $ns      = $this->c('no_shipment');
        $area    = $this->c('area');
        $planner = $this->c('planner');
        $pic     = $this->c('pic_monitoring');

        foreach (array_chunk($shipments, 500) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));

            // ---- 1) area dari saudara satu shipment ----
            DB::update("
                UPDATE " . self::TABLE . " lp
                JOIN (
                    SELECT $ns AS ns, MIN(TRIM($area)) AS val
                    FROM " . self::TABLE . "
                    WHERE NULLIF(TRIM($area), '') IS NOT NULL
                      AND $ns IN ($in)
                    GROUP BY $ns
                    HAVING COUNT(DISTINCT TRIM($area)) = 1
                ) x ON lp.$ns = x.ns
                SET lp.$area = x.val
                WHERE NULLIF(TRIM(lp.$area), '') IS NULL
                  AND lp.$ns IN ($in)
            ", array_merge($chunk, $chunk));

            // ---- 2) planner & PIC dari master per area ----
            foreach ((self::$areaMap ?? []) as $a => $info) {
                if (!empty($info['planner']) && $this->hasCol('planner')) {
                    DB::table(self::TABLE)
                        ->whereIn($ns, $chunk)
                        ->where($area, (string) $a)
                        ->whereRaw("NULLIF(TRIM($planner), '') IS NULL")
                        ->update([$planner => $info['planner']]);
                }

                if (!empty($info['pic']) && $this->hasCol('pic_monitoring')) {
                    DB::table(self::TABLE)
                        ->whereIn($ns, $chunk)
                        ->where($area, (string) $a)
                        ->whereRaw("NULLIF(TRIM($pic), '') IS NULL")
                        ->update([$pic => $info['pic']]);
                }
            }
        }
    }

    /** isi route/mobil/ekpedisi yang kosong dari baris lain dalam shipment yang sama */
    private function fillShipmentGaps(array $shipments): void
    {
        $ns = $this->c('no_shipment');

        foreach (array_chunk($shipments, 500) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));

            foreach (['route', 'mobil', 'ekpedisi', 'ekspedisi'] as $base) {
                if (!$this->hasCol($base)) continue;
                $col = $this->c($base);

                DB::update("
                    UPDATE " . self::TABLE . " lp
                    JOIN (
                        SELECT $ns AS ns, MIN($col) AS val
                        FROM " . self::TABLE . "
                        WHERE $col IS NOT NULL AND $col != ''
                          AND $ns IN ($in)
                        GROUP BY $ns
                    ) x ON lp.$ns = x.ns
                    SET lp.$col = x.val
                    WHERE (lp.$col IS NULL OR lp.$col = '')
                      AND lp.$ns IN ($in)
                ", array_merge($chunk, $chunk));
            }
        }
    }

    /**
     * CR: per baris sesuai kontribusi nilai_muatan, total biaya = SUM biaya_kirim per shipment.
     * Hasil kubik/tonase: per baris (kontribusi baris / kapasitas mobil).
     * Pengiriman optimal: per shipment (SUM total / kapasitas).
     *
     * total_kubik, total_tonase, kubikasi, tonase, hasil_* bertipe varchar di tabel Pasuruan,
     * jadi semua di-CAST ke DECIMAL dulu (MAX() varchar tanpa CAST membandingkan sebagai teks).
     */
    private function recalcShipments(array $shipments): void
    {
        $ns   = $this->c('no_shipment');
        $bk   = $this->c('biaya_kirim');
        $nm   = $this->c('nilai_muatan');
        $cr   = $this->c('cr');
        $tk   = $this->c('total_kubik');
        $tt   = $this->c('total_tonase');
        $kub  = $this->c('kubikasi');
        $ton  = $this->c('tonase');
        $hk   = $this->c('hasil_kubik');
        $ht   = $this->c('hasil_tonase');
                $opt  = $this->c('pengiriman_optimal');
        $tj   = $this->c('tujuan');

        foreach (array_chunk($shipments, 500) as $chunk) {
            $in       = implode(',', array_fill(0, count($chunk), '?'));
            $bindings = array_merge($chunk, $chunk);

            // ---- CR ----
            if ($this->hasCol('cr')) {
                DB::update("
                    UPDATE " . self::TABLE . " lp
                    JOIN (
                        SELECT $ns AS ns,
                               SUM(COALESCE($bk, 0)) AS biaya,
                               SUM(COALESCE($nm, 0)) AS muatan
                        FROM " . self::TABLE . "
                        WHERE $ns IN ($in)
                        GROUP BY $ns
                    ) x ON lp.$ns = x.ns
                    SET lp.$cr = IF(
                        x.muatan = 0 OR COALESCE(lp.$nm, 0) <= 0,
                        0,
                        ROUND((lp.$nm * x.biaya) / (x.muatan * x.muatan) * 100, 4)
                    )
                    WHERE lp.$ns IN ($in)
                ", $bindings);
            }

            // ---- HASIL KUBIK / TONASE (per baris) + OPTIMAL (per shipment) ----
           // ---- HASIL KUBIK / TONASE (per TUJUAN) + OPTIMAL (gabungan SELURUH shipment) ----
DB::update("
    UPDATE " . self::TABLE . " lp
    JOIN (
        -- total seluruh shipment: untuk status optimal + kapasitas mobil
        SELECT $ns AS ns,
               SUM(COALESCE(" . $this->num($tk) . ", 0)) AS sum_kubik,
               SUM(COALESCE(" . $this->num($tt) . ", 0)) AS sum_tonase,
               MAX(" . $this->num($kub) . ") AS kubikasi,
               MAX(" . $this->num($ton) . ") AS tonase
        FROM " . self::TABLE . "
        WHERE $ns IN ($in)
        GROUP BY $ns
    ) s ON lp.$ns = s.ns
    JOIN (
        -- total per tujuan: untuk hasil kubik/tonase
        SELECT $ns AS ns, COALESCE($tj, '') AS tj,
               SUM(COALESCE(" . $this->num($tk) . ", 0)) AS t_kubik,
               SUM(COALESCE(" . $this->num($tt) . ", 0)) AS t_tonase
        FROM " . self::TABLE . "
        WHERE $ns IN ($in)
        GROUP BY $ns, COALESCE($tj, '')
    ) t ON lp.$ns = t.ns AND COALESCE(lp.$tj, '') = t.tj
    SET
        lp.$hk = CASE
            WHEN s.kubikasi > 0 THEN ROUND(t.t_kubik / s.kubikasi * 100, 2)
            ELSE NULL
        END,
        lp.$ht = CASE
            WHEN s.tonase > 0 THEN ROUND(t.t_tonase / s.tonase * 100, 2)
            ELSE NULL
        END,
        lp.$opt = CASE
            WHEN (s.kubikasi > 0 AND (s.sum_kubik  / s.kubikasi * 100) >= 85)
              OR (s.tonase   > 0 AND (s.sum_tonase / s.tonase   * 100) >= 85)
                THEN 'OPTIMAL'
            WHEN s.kubikasi > 0 OR s.tonase > 0
                THEN 'TIDAK OPTIMAL'
            ELSE NULL
        END
    WHERE lp.$ns IN ($in)
", array_merge($chunk, $chunk, $chunk));

                        // ---- OPTIMAL PER CUSTOMER (shipment + tujuan) ----
            if ($this->hasCol('pengiriman_optimal_customer')) {
                $oc = $this->c('pengiriman_optimal_customer');

                DB::update("
                    UPDATE " . self::TABLE . " lp
                    JOIN (
                        SELECT $ns AS ns, COALESCE($tj, '') AS tj,
                               SUM(COALESCE(" . $this->num($tk) . ", 0)) AS sum_kubik,
                               SUM(COALESCE(" . $this->num($tt) . ", 0)) AS sum_tonase,
                               MAX(" . $this->num($kub) . ") AS kubikasi,
                               MAX(" . $this->num($ton) . ") AS tonase
                        FROM " . self::TABLE . "
                        WHERE $ns IN ($in)
                        GROUP BY $ns, COALESCE($tj, '')
                    ) x ON lp.$ns = x.ns AND COALESCE(lp.$tj, '') = x.tj
                    SET lp.$oc = CASE
                        WHEN COALESCE(x.kubikasi, 0) <= 0 AND COALESCE(x.tonase, 0) <= 0 THEN NULL
                        WHEN (x.kubikasi > 0 AND x.sum_kubik  / x.kubikasi * 100 >= 85)
                          OR (x.tonase   > 0 AND x.sum_tonase / x.tonase   * 100 >= 85) THEN 'OPTIMAL'
                        ELSE 'TIDAK OPTIMAL'
                    END
                    WHERE lp.$ns IN ($in)
                ", $bindings);
            }
        }
    }
}