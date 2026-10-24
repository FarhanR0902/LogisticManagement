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

class UpdateQtyPgiImport implements ToCollection, WithHeadingRow, WithEvents, WithCalculatedFormulas
{
    private const TABLE       = 'logistik_pengiriman';
    // GANTI sesuai nama tabel master tujuan kamu
private const MASTER_TABLE       = 'tujuanfillterr';
private const MASTER_TUJUAN_COL  = 'tujuan';

// kolom_di_master => kolom_di_logistik_pengiriman
private const MASTER_MAP = [
   
    'pulau'                => 'pulau',
    'area'                 => 'area',
    'planner'              => 'planner',
    'monitoring'           => 'pic_monitoring',
    'transport_lead_time'  => 'transport_lead_time',
];

private static $masterByTujuan = null;   // tujuan ternormalisasi => rows master
private array $tujuanNotInMaster = [];
    private const TARIF_TABLE = 'tarif_pengiriman';

    // sama seperti LogistikImport
    private const ROUTE_ALIASES = [
        'jabodetabek' => 'Sentul-Jabodetabek',
    ];
    private const EKSPEDISI_ALIASES = [
        // 'namadiexcel' => 'PT Nama Di Master',
    ];

    private static $tarifByRoute = null;   // route => rows tarif

    private bool $hasOptimalCustomer = false;
    private bool $hasReasonOptimalCustomer = false;

    private array $ekspedisiResolved = []; // cache: "route|ekspedisi" => nama di master

    // ===== hasil proses total_do_qty_car (kunci: no_shipment + tujuan) =====
    private int $qtyUpdated = 0;
    private array $qtyNotFound = [];
    private array $qtyAmbiguous = [];
    private int $qtySkipped = 0;

    // ===== hasil proses act_pgi_date (kunci: no_shipment) =====
    private int $pgiUpdated = 0;
    private array $pgiNotFound = [];
    private int $pgiSkipped = 0;

    // ===== hasil proses total_kubik/total_tonase (kunci: no_shipment + tujuan) =====
    private int $kubikTonaseUpdated = 0;
    private array $kubikTonaseNotFound = [];
    private int $kubikTonaseSkipped = 0;

    // ===== hasil proses GENERAL (kunci: no_shipment + tujuan) =====
    private int $generalCreated = 0;
    private int $generalUpdated = 0;
    private int $generalUnchanged = 0;
    private array $generalAmbiguous = [];
    private int $generalSkipped = 0;

    // ===== hasil recalc biaya_kirim dari tarif_pengiriman =====
    private int $biayaKirimUpdated = 0;
    private array $tarifNotFound = [];
    private array $tarifAmbiguous = [];   // dipertahankan supaya controller lama tidak error

    // dedup supaya field level-shipment tidak diproses berkali-kali
    private array $pgiProcessed = [];

    // no_shipment yang tersentuh import ini -> dipakai buat recalc di AfterImport
    private array $touchedShipments = [];

    // baris yang biaya_kirim-nya diisi manual di Excel ("no_shipment|tujuan") -> tidak ditimpa tarif
    // private array $manualBiaya = [];

    // no_shipment => biaya 1 mobil dari Excel
private array $groupBiaya = [];

// no_shipment yang total_kubik-nya berubah di import ini
private array $kubikChanged = [];

private bool $hasKirimKontribusi = false;

    // ================= GETTERS =================
    public function getQtyUpdated(): int { return $this->qtyUpdated; }
    public function getQtyNotFound(): array { return $this->qtyNotFound; }
    public function getQtyAmbiguous(): array { return $this->qtyAmbiguous; }
    public function getQtySkipped(): int { return $this->qtySkipped; }

    public function getPgiUpdated(): int { return $this->pgiUpdated; }
    public function getPgiNotFound(): array { return $this->pgiNotFound; }
    public function getPgiSkipped(): int { return $this->pgiSkipped; }

    public function getKubikTonaseUpdated(): int { return $this->kubikTonaseUpdated; }
    public function getKubikTonaseNotFound(): array { return $this->kubikTonaseNotFound; }
    public function getKubikTonaseSkipped(): int { return $this->kubikTonaseSkipped; }

    public function getGeneralCreated(): int { return $this->generalCreated; }
    public function getGeneralUpdated(): int { return $this->generalUpdated; }
    public function getGeneralUnchanged(): int { return $this->generalUnchanged; }
    public function getGeneralAmbiguous(): array { return $this->generalAmbiguous; }
    public function getGeneralSkipped(): int { return $this->generalSkipped; }

    public function getBiayaKirimUpdated(): int { return $this->biayaKirimUpdated; }
    public function getTarifNotFound(): array { return $this->tarifNotFound; }
    public function getTarifAmbiguous(): array { return $this->tarifAmbiguous; }

    public function __construct()
    {
        $this->hasOptimalCustomer       = Schema::hasColumn(self::TABLE, 'pengiriman_optimal_customer');
        $this->hasReasonOptimalCustomer = Schema::hasColumn(self::TABLE, 'reason_optimal_customer');
$this->hasKirimKontribusi = Schema::hasColumn(self::TABLE, 'kirim_kontribusi');
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

    public function getTujuanNotInMaster(): array { return $this->tujuanNotInMaster; }

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

$this->recalcTarifCapacity($shipments);    // ekpedisi, mobil, kubikasi, tonase dari tarif
$this->recalcBiayaKontribusi($shipments);  // kirim_kontribusi + biaya_kirim
$this->recalcShipments($shipments);        // cr, hasil_kubik, hasil_tonase, optimal

                 // cr, hasil_kubik, hasil_tonase, optimal
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

            $noShipment = $this->cleanText($row['no_shipment'] ?? null);

            if (empty($noShipment)) {
                $this->qtySkipped++;
                $this->pgiSkipped++;
                $this->kubikTonaseSkipped++;
                $this->generalSkipped++;
                continue;
            }

            $this->touchedShipments[(string) $noShipment] = true;
            // biaya 1 mobil per no_shipment (sama dengan LogistikImport)
$biayaPool = $this->parseNumber($this->pick($row, ['biaya_kirim', 'biaya_kirim_rp', 'kirim_kontribusi']));
if ($biayaPool !== null && $biayaPool > 0) {
    $this->groupBiaya[(string) $noShipment] = $biayaPool;
}

            $tujuan = $this->cleanText($row['tujuan'] ?? null);

            // 0) GENERAL UPSERT (kunci: no_shipment + tujuan)
            if (empty($tujuan)) {
                $this->generalSkipped++;
            } else {
                $this->processGeneral($row, $noShipment, $tujuan);
            }

            // 1) TOTAL DO QTY CAR (kunci: no_shipment + tujuan)
            $qty = $this->cleanNumber($row['total_do_qty_car'] ?? null);

            if (!empty($tujuan) && $qty !== null) {

                $matches = LogistikPengiriman::where('no_shipment', $noShipment)
                    ->where('tujuan', $tujuan)
                    ->get();

                if ($matches->isEmpty()) {
                    $this->qtyNotFound[] = "{$noShipment} - {$tujuan}";
                } elseif ($matches->count() > 1) {
                    $this->qtyAmbiguous[] = "{$noShipment} - {$tujuan} ({$matches->count()} baris)";
                } else {
                    $matches->first()->update(['total_do_qty_car' => $qty]);
                    $this->qtyUpdated++;
                }
            } else {
                $this->qtySkipped++;
            }

            // 2) ACT PGI DATE (kunci: no_shipment saja)
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

            // 3) TOTAL KUBIK / TOTAL TONASE (kunci: no_shipment + tujuan), per baris.
            //    Kapasitas (kubikasi/tonase) selalu dari tarif_pengiriman.
            $totalKubikNew  = $this->cleanDecimal($row['total_kubik'] ?? null);
            $totalTonaseNew = $this->cleanDecimal($row['total_tonase'] ?? null);

            if ($totalKubikNew === null && $totalTonaseNew === null) {
                $this->kubikTonaseSkipped++;
            } elseif (empty($tujuan)) {
                $this->kubikTonaseNotFound[] = "{$noShipment} (tujuan kosong, total kubik/tonase dilewati)";
            } else {
                $payload = array_filter([
                    'total_kubik'  => $totalKubikNew,
                    'total_tonase' => $totalTonaseNew,
                ], fn($v) => $v !== null);

                $affected = LogistikPengiriman::where('no_shipment', $noShipment)
                    ->where('tujuan', $tujuan)
                    ->update($payload);

                if ($affected === 0) {
                    $this->kubikTonaseNotFound[] = "{$noShipment} - {$tujuan}";
              } else {
    $this->kubikTonaseUpdated += $affected;
    if ($totalKubikNew !== null) {
        $this->kubikChanged[(string) $noShipment] = true;
    }
}
            }
        }
    }

    // ================= GENERAL UPSERT =================

    private function processGeneral($row, string $noShipment, string $tujuan): void
    {
        $matches = LogistikPengiriman::where('no_shipment', $noShipment)
            ->where('tujuan', $tujuan)
            ->get();

        $ekpedisiNew          = $this->cleanText($row['ekpedisi'] ?? null);
        $routeNew             = $this->applyRouteAlias($this->cleanText($row['route'] ?? null));
        $mobilNew             = $this->cleanText($row['mobil'] ?? null);
        $pulauNew             = $this->cleanText($row['pulau'] ?? null);
        $areaNew              = $this->cleanText($row['area'] ?? null);
        $viaKirimNew          = $this->cleanText($row['via_kirim'] ?? $row['via'] ?? null);
        $kategoriEkspedisiNew = $this->cleanText($row['kategori_ekspedisi'] ?? null);
        $namaDriverNew        = $this->cleanText($row['nama_driver'] ?? null);
        $noPolNew             = $this->cleanText($row['no_pol'] ?? $row['nopol'] ?? null);
        $nilaiMuatanNew       = $this->cleanDecimal(
            $row['nilai_muatan_rp'] ?? $row['nilai_muatan'] ?? null
        );

        // biaya_kirim diisi manual di Excel -> dipakai apa adanya, tidak dihitung ulang dari tarif
       

        if ($matches->count() > 1) {
            $this->generalAmbiguous[] = "{$noShipment} - {$tujuan} ({$matches->count()} baris)";
            return;
        }

        // if ($biayaExcel !== null) {
        //     $this->manualBiaya[$noShipment . '|' . $tujuan] = true;
        // }

        if ($matches->isEmpty()) {
            // ===== INSERT BARU =====
            // biaya_kirim / kubikasi / tonase diisi di AfterImport (recalcBiayaKirim)
           $fromExcel = array_filter([
    'no_shipment'        => $noShipment,
    'tujuan'             => $tujuan,
    'ekpedisi'           => $ekpedisiNew,
    'route'              => $routeNew,
    'mobil'              => $mobilNew,
    'pulau'              => $pulauNew,
    'area'               => $areaNew,
    'via_kirim'          => $viaKirimNew,
    'kategori_ekspedisi' => $kategoriEkspedisiNew,
    'nama_driver'        => $namaDriverNew,
    'no_pol'             => $noPolNew,
    'nilai_muatan'       => $nilaiMuatanNew,
    'create_tgl'         => Carbon::now(),
], fn($v) => $v !== null);

// isi Excel menang; master hanya mengisi kolom yang kosong
LogistikPengiriman::create($fromExcel + $this->masterAttributes($tujuan));

            $this->generalCreated++;
            return;
        }

        // ===== SUDAH ADA -> UPDATE HANYA YANG BEDA =====
        $existing = $matches->first();

        $candidates = [
            'ekpedisi'           => $ekpedisiNew,
            'route'              => $routeNew,
            'mobil'              => $mobilNew,
            'pulau'              => $pulauNew,
            'area'               => $areaNew,
            'via_kirim'          => $viaKirimNew,
            'kategori_ekspedisi' => $kategoriEkspedisiNew,
            'nama_driver'        => $namaDriverNew,
            'no_pol'             => $noPolNew,
            'nilai_muatan'       => $nilaiMuatanNew,
        
        ];

        $payload = [];
        foreach ($candidates as $column => $newValue) {
            if ($newValue === null) {
                continue;
            }
            if (!$this->valuesEqual($existing->{$column}, $newValue)) {
                $payload[$column] = $newValue;
            }
        }

        if (!empty($payload)) {
            $existing->update($payload);
            $this->generalUpdated++;
        } else {
            $this->generalUnchanged++;
        }
    }

    // ================= RECALC SETELAH IMPORT =================

    /**
     * Biaya kirim per BARIS (sama dengan LogistikImport):
     *   total_kubik / kubikasi x biaya tarif (biaya 1 mobil penuh),
     *   kalau total_kubik / kubikasi belum ada -> biaya tarif penuh.
     * Kubikasi & tonase juga diisi dari tarif. Ekpedisi/mobil yang kepotong
     * disamakan dengan nama di master tarif.
     * Dijalankan untuk SEMUA baris di shipment yang ada di file ini, jadi
     * biaya lama yang sama semua per shipment ikut terkoreksi.
     */
   /** Kapasitas dari tarif saja (biaya tidak lagi dari tarif). */
private function recalcTarifCapacity(array $shipments): void
{
    foreach (array_chunk($shipments, 500) as $chunk) {
        $rows = DB::table(self::TABLE)
            ->select('id', 'ekpedisi', 'route', 'mobil')
            ->whereIn('no_shipment', $chunk)
            ->get();

        foreach ($rows as $r) {
            $tarif = $this->findTarif($this->applyRouteAlias($r->route), $r->ekpedisi, $r->mobil);
            if (!$tarif) continue;

            $payload = array_filter([
                'ekpedisi' => $tarif->ekpedisi,
                'mobil'    => $tarif->mobil,
                'kubikasi' => $this->parseNumber($tarif->kubikasi ?? null),
                'tonase'   => $this->parseNumber($tarif->tonase ?? null),
            ], fn($v) => $v !== null);

            DB::table(self::TABLE)->where('id', $r->id)->update($payload);
        }
    }
}

/**
 * kirim_kontribusi per DO = biaya 1 mobil x total_kubik DO / SUM total_kubik shipment
 * biaya_kirim per tujuan  = SUM kirim_kontribusi DO di tujuan itu
 * (sama dengan LogistikImport)
 */
private function recalcBiayaKontribusi(array $shipments): void
{
    if (!$this->hasKirimKontribusi) {
        logger()->warning('UPDATE IMPORT: kolom kirim_kontribusi tidak ada, biaya_kirim tidak dihitung');
        return;
    }

    // biaya 1 mobil dari Excel
    $pools = [];
    foreach ($this->groupBiaya as $sh => $pool) {
        $pools[(string) $sh] = $pool;
    }

    // total_kubik berubah tapi Excel tidak bawa biaya -> bagi ulang biaya yang sudah ada
    $need = array_diff(
        array_map('strval', array_keys($this->kubikChanged)),
        array_keys($pools)
    );
    foreach (array_chunk($need, 500) as $c) {
        $rows = DB::table(self::TABLE)
            ->select('no_shipment', DB::raw('SUM(COALESCE(kirim_kontribusi,0)) AS pool'))
            ->whereIn('no_shipment', $c)
            ->groupBy('no_shipment')
            ->get();

        foreach ($rows as $r) {
            if ((float) $r->pool > 0) {
                $pools[(string) $r->no_shipment] = (float) $r->pool;
            }
        }
    }

    // 1) kirim_kontribusi per DO
    foreach ($pools as $sh => $pool) {
    $sh = (string) $sh;
    DB::update("
            UPDATE logistik_pengiriman lp
            JOIN (
                SELECT SUM(COALESCE(total_kubik,0)) AS sum_kubik, COUNT(*) AS cnt
                FROM logistik_pengiriman
                WHERE no_shipment = ?
            ) g
            SET lp.kirim_kontribusi = CASE
                WHEN g.sum_kubik > 0 THEN ROUND(? * COALESCE(lp.total_kubik,0) / g.sum_kubik, 2)
                ELSE ROUND(? / g.cnt, 2)
            END
            WHERE lp.no_shipment = ?
        ", [$sh, $pool, $pool, $sh]);
    }

    // 2) biaya_kirim per tujuan = SUM kirim_kontribusi
    foreach (array_chunk(array_map('strval', array_keys($pools)), 500) as $chunk) {
        $in = implode(',', array_fill(0, count($chunk), '?'));

        DB::update("
            UPDATE logistik_pengiriman lp
            JOIN (
                SELECT no_shipment, COALESCE(tujuan,'') AS tj,
                       SUM(COALESCE(kirim_kontribusi,0)) AS total
                FROM logistik_pengiriman
                WHERE no_shipment IN ($in)
                GROUP BY no_shipment, COALESCE(tujuan,'')
            ) g ON lp.no_shipment = g.no_shipment AND COALESCE(lp.tujuan,'') = g.tj
            SET lp.biaya_kirim = g.total
            WHERE lp.no_shipment IN ($in)
              AND g.total > 0
        ", array_merge($chunk, $chunk));
    }

    $this->biayaKirimUpdated = count($pools);
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
     * CR            : per baris = biaya_kirim baris / nilai_muatan baris x 100.
     * Hasil kubik   : per baris = total_kubik / kubikasi x 100.
     * Hasil tonase  : per baris = total_tonase / tonase x 100.
     * Pengiriman optimal: per shipment (SUM total / kapasitas >= 85%).
     * (sama persis dengan LogistikImport)
     */
    private function recalcShipments(array $shipments): void
    {
        foreach (array_chunk($shipments, 500) as $chunk) {
            $in       = implode(',', array_fill(0, count($chunk), '?'));
            $bindings = array_merge($chunk, $chunk);

         // ---- CR per shipment + tujuan = biaya_kirim / SUM nilai_muatan tujuan x 100 ----
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
            $found = $candidates->first(fn($t) =>
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

    /**
     * Bandingkan nilai lama vs baru, toleran terhadap tipe data.
     */
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

    /** parser angka yang sama dengan LogistikImport (Rp, 76,559,246 / 76.559.246,5 / 12,5) */
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
        $value = (string) $value;
        $value = str_replace("\xC2\xA0", ' ', $value);
        return preg_replace('/\s+/', ' ', trim($value));
    }

    private function cleanNumber($value)
    {
        if ($value === null || $value === '' || $value == '-') return null;
        $value = preg_replace('/[^0-9]/', '', (string) $value);
        return $value === '' ? null : (int) $value;
    }

    private function cleanDecimal($value): ?float
    {
        if ($value === null || $value === '' || $value === '-') return null;
        if (is_numeric($value)) return (float) $value;

        $value = trim((string) $value);

        if (preg_match('/^\d+,\d+$/', $value)) {
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function convertDate($value)
    {
        if (!$value || $value == '-') return null;

        if (is_numeric($value)) {
            return Date::excelToDateTimeObject($value)->format('Y-m-d');
        }

        $timestamp = strtotime(str_replace('/', '-', trim($value)));
        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }
}