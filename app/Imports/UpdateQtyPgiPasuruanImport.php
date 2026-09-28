<?php

namespace App\Imports;

use App\Models\LogistikPengirimanPasuruan;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\AfterImport;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Update data Pasuruan dari Excel (logika disamakan dengan PasuruanImport):
 *   0) General upsert  (kunci: no_shipment + tujuan)
 *        ekspedisi / route / mobil / pulau / area / planner / pic /
 *        dist_channel / biaya_kuli / via_kirim / kategori_ekspedisi / nilai_muatan
 *        + biaya_kirim / kubikasi / tonase dari tarif_pengiriman
 *          (HANYA kalau ekspedisi / mobil / route berubah)
 *   1) Total DO Qty         (kunci: no_shipment + tujuan)
 *   2) Act PGI Date         (kunci: no_shipment)
 *   3) Total Kubik/Tonase   (kunci: no_shipment + tujuan)
 *
 * Setelah import (AfterImport), untuk shipment yang ada di file ini saja:
 *   - safety net route/mobil/ekspedisi kosong
 *   - kolom turunan per baris (SLA, estimasi, overstay, status, selisih qty, lead time)
 *   - cr, hasil_kubik, hasil_tonase, pengiriman_optimal
 *
 * Kolom yang kosong di Excel TIDAK menimpa data lama.
 */
class UpdateQtyPgiPasuruanImport implements ToCollection, WithHeadingRow, WithCalculatedFormulas, WithEvents
{
    private const TABLE       = 'logistik_pengiriman_pasuruan';
    private const TARIF_TABLE = 'tarif_pengiriman';

    // set false kalau Pasuruan TIDAK boleh membuat baris baru dari Excel
    private const ALLOW_CREATE = true;

    private const ROUTE_ALIASES = [
        'jabodetabek' => 'Sentul-Jabodetabek',
    ];

    private static $customerMap  = null;
    private static $tarifByRoute = null;

    // forward-fill state (merged cell di Excel)
    private ?string $lastNoShipment = null;
    private ?string $lastRoute      = null;
    private ?string $lastMobil      = null;
    private ?string $lastEkspedisi  = null;

    // ===== total_do (kunci: no_shipment + tujuan) =====
    private int $qtyUpdated = 0;
    private array $qtyNotFound = [];
    private array $qtyAmbiguous = [];
    private int $qtySkipped = 0;

    // ===== act_pgi_date (kunci: no_shipment) =====
    private int $pgiUpdated = 0;
    private array $pgiNotFound = [];
    private int $pgiSkipped = 0;

    // ===== total_kubik/total_tonase (kunci: no_shipment + tujuan) =====
    private int $kubikTonaseUpdated = 0;
    private array $kubikTonaseNotFound = [];
    private int $kubikTonaseSkipped = 0;

    // ===== GENERAL (kunci: no_shipment + tujuan) =====
    private int $generalCreated = 0;
    private int $generalUpdated = 0;
    private int $generalUnchanged = 0;
    private array $generalAmbiguous = [];
    private array $generalNotFound = [];
    private int $generalSkipped = 0;

    // ===== tarif =====
    private int $biayaKirimUpdated = 0;
    private array $tarifNotFound = [];
    private array $tarifAmbiguous = []; // dipertahankan supaya controller tidak error (selalu kosong)

    // ===== kolom turunan (SLA dll) =====
    private int $derivedUpdated = 0;

    private array $pgiProcessed = [];

    // no_shipment yang tersentuh import ini -> dipakai buat recalc di AfterImport
    private array $touchedShipments = [];

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
    public function getGeneralNotFound(): array { return $this->generalNotFound; }
    public function getGeneralSkipped(): int { return $this->generalSkipped; }

    public function getBiayaKirimUpdated(): int { return $this->biayaKirimUpdated; }
    public function getTarifNotFound(): array { return $this->tarifNotFound; }
    public function getTarifAmbiguous(): array { return $this->tarifAmbiguous; }

    public function getDerivedUpdated(): int { return $this->derivedUpdated; }

    // ================= CONSTRUCTOR =================

    public function __construct()
    {
        // Master tarif: route -> kandidat (ekpedisi, mobil, biaya, kapasitas)
        if (self::$tarifByRoute === null) {
            self::$tarifByRoute = DB::table(self::TARIF_TABLE)
                ->select('ekpedisi', 'route', 'mobil', 'biaya_kirim', 'kubikasi', 'tonase')
                ->get()
                ->groupBy(fn($row) => $this->normalize($row->route));
        }

        // Master tujuan: WAJIB difilter Div = 'Pasuruan'
        if (self::$customerMap === null) {
            self::$customerMap = DB::table('tujuanfillterr')
                ->select(
                    'tujuan',
                    'dist_channel',
                    'transport_lead_time',
                    'pulau',
                    'area',
                    'Planner',
                    'biaya_kuli',
                    'Monitoring'
                )
                ->where('Div', 'Pasuruan')
                ->get()
                ->keyBy(fn($row) => preg_replace('/\s+/', ' ', trim(strtolower($row->tujuan))));
        }
    }

    // ================= EVENTS =================

    public function registerEvents(): array
    {
        return [
            AfterImport::class => function () {
                $this->recalcShipments();
            },
        ];
    }

    // ================= MAIN =================

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $row = $row->toArray();

            $noShipment = $this->cleanText($this->pick($row, [
                'no_shipment_pasuruan', 'no_shipment',
            ]));

            if (empty($noShipment)) {
                $this->qtySkipped++;
                $this->pgiSkipped++;
                $this->kubikTonaseSkipped++;
                $this->generalSkipped++;
                continue;
            }

            $this->touchedShipments[(string) $noShipment] = true;

            $tujuan = $this->cleanText($this->pick($row, [
                'tujuan_pasuruan', 'tujuan',
            ]));

            // ===== FORWARD-FILL route/mobil/ekspedisi (merged cell) =====
            if ($noShipment !== $this->lastNoShipment) {
                $this->lastRoute = $this->lastMobil = $this->lastEkspedisi = null;
            }

            $route = $this->applyRouteAlias(
                $this->cleanText($this->pick($row, ['route_pasuruan', 'route'])) ?: $this->lastRoute
            );
            $mobil = $this->cleanText($this->pick($row, ['mobil_pasuruan', 'mobil'])) ?: $this->lastMobil;
            $ekspedisi = $this->cleanText($this->pick($row, [
                'ekspedisi_pasuruan', 'ekspedisi', 'ekpedisi_pasuruan', 'ekpedisi',
            ])) ?: $this->lastEkspedisi;

            if ($route)     $this->lastRoute     = $route;
            if ($mobil)     $this->lastMobil     = $mobil;
            if ($ekspedisi) $this->lastEkspedisi = $ekspedisi;
            $this->lastNoShipment = $noShipment;

            // timpa row supaya processGeneral otomatis pakai nilai yang sudah di-fill
            $row['route_pasuruan']     = $route;
            $row['mobil_pasuruan']     = $mobil;
            $row['ekspedisi_pasuruan'] = $ekspedisi;

            // =====================================================
            // 0) GENERAL UPSERT (kunci: no_shipment + tujuan)
            // =====================================================
            if (empty($tujuan)) {
                $this->generalSkipped++;
            } else {
                $this->processGeneral($row, $noShipment, $tujuan);
            }

            // =====================================================
            // 1) TOTAL DO QTY (kunci: no_shipment + tujuan)
            // =====================================================
            $qty = $this->cleanNumber($this->pick($row, [
                'total_do_qty_car_pasuruan',
                'total_do_qty_car',
                'total_do_pasuruan',
                'total_do',
            ]));

            if (!empty($tujuan) && $qty !== null) {

                $matches = LogistikPengirimanPasuruan::where('no_shipment_pasuruan', $noShipment)
                    ->where('tujuan_pasuruan', $tujuan)
                    ->get();

                if ($matches->isEmpty()) {
                    $this->qtyNotFound[] = "{$noShipment} - {$tujuan}";
                } elseif ($matches->count() > 1) {
                    $this->qtyAmbiguous[] = "{$noShipment} - {$tujuan} ({$matches->count()} baris)";
                } else {
                    $matches->first()->update(['total_do_pasuruan' => (int) round($qty)]);
                    $this->qtyUpdated++;
                }
            } else {
                $this->qtySkipped++;
            }

            // =====================================================
            // 2) ACT PGI DATE (kunci: no_shipment saja)
            // =====================================================
            $pgiDate = $this->convertDate($this->pick($row, [
                'act_pgi_date_pasuruan', 'act_pgi_date',
            ]));

            if ($pgiDate !== null && !isset($this->pgiProcessed[$noShipment])) {

                $affected = LogistikPengirimanPasuruan::where('no_shipment_pasuruan', $noShipment)
                    ->update(['act_pgi_date_pasuruan' => $pgiDate]);

                if ($affected === 0) {
                    $this->pgiNotFound[] = $noShipment;
                } else {
                    $this->pgiUpdated += $affected;
                }

                $this->pgiProcessed[$noShipment] = true;

            } elseif ($pgiDate === null) {
                $this->pgiSkipped++;
            }

            // =====================================================
            // 3) TOTAL KUBIK / TOTAL TONASE (kunci: no_shipment + tujuan)
            //    Nilai beda per tujuan, jadi ditulis PER BARIS.
            //    Kapasitas (kubikasi/tonase) TIDAK dibaca dari Excel,
            //    selalu dari tarif_pengiriman.
            //    hasil_* & pengiriman_optimal dihitung di AfterImport.
            // =====================================================
            $totalKubikNew = $this->cleanDecimal($this->pick($row, [
                'total_kubik_pasuruan', 'total_kubikasi_pasuruan',
                'total_kubik', 'total_kubikasi',
            ]));
            $totalTonaseNew = $this->cleanDecimal($this->pick($row, [
                'total_tonase_pasuruan', 'total_tonase',
            ]));

            if ($totalKubikNew === null && $totalTonaseNew === null) {
                $this->kubikTonaseSkipped++;
            } elseif (empty($tujuan)) {
                $this->kubikTonaseNotFound[] = "{$noShipment} (tujuan kosong, total kubik/tonase dilewati)";
            } else {
                $payload = array_filter([
                    'total_kubik_pasuruan'  => $totalKubikNew,
                    'total_tonase_pasuruan' => $totalTonaseNew,
                ], fn($v) => $v !== null);

                $affected = LogistikPengirimanPasuruan::where('no_shipment_pasuruan', $noShipment)
                    ->where('tujuan_pasuruan', $tujuan)
                    ->update($payload);

                if ($affected === 0) {
                    $this->kubikTonaseNotFound[] = "{$noShipment} - {$tujuan}";
                } else {
                    $this->kubikTonaseUpdated += $affected;
                }
            }
        }
    }

    // ================= GENERAL UPSERT =================

    private function processGeneral(array $row, string $noShipment, string $tujuan): void
    {
        $matches = LogistikPengirimanPasuruan::where('no_shipment_pasuruan', $noShipment)
            ->where('tujuan_pasuruan', $tujuan)
            ->get();

        $master = $this->lookupTujuan($tujuan);

        // header Excel boleh "ekspedisi" atau "ekpedisi"; kolom DB = ekspedisi_pasuruan
        $ekpedisiNew = $this->cleanText($this->pick($row, [
            'ekspedisi_pasuruan', 'ekspedisi', 'ekpedisi_pasuruan', 'ekpedisi',
        ]));
        $routeNew = $this->cleanText($this->pick($row, ['route_pasuruan', 'route']));
        $mobilNew = $this->cleanText($this->pick($row, ['mobil_pasuruan', 'mobil']));

        // master tujuan menang, Excel jadi fallback (sama seperti import)
        $pulauNew   = ($master->pulau ?? null) ?: $this->cleanText($this->pick($row, ['pulau_pasuruan', 'pulau']));
        $areaNew    = ($master->area ?? null) ?: $this->cleanText($this->pick($row, ['area_pasuruan', 'area']));
        $plannerNew = ($master->Planner ?? null) ?: $this->cleanText($this->pick($row, ['planner_pasuruan', 'planner']));
        $picNew     = ($master->Monitoring ?? null) ?: $this->cleanText($this->pick($row, ['pic_monitoring_pasuruan', 'pic_monitoring']));
        $distChannelNew = ($master->dist_channel ?? null) ?: null;
        $biayaKuliNew   = ($master->biaya_kuli ?? null) ?: null;

        $viaKirimNew = $this->cleanText($this->pick($row, ['via_kirim_pasuruan', 'via_kirim', 'via_pasuruan']));

        // prefix no_shipment menang, Excel fallback (sama seperti import)
        $kategoriEkspedisiNew = $this->getKategoriEkspedisi($noShipment)
            ?? $this->cleanText($this->pick($row, ['kategori_ekspedisi_pasuruan', 'kategori_ekspedisi']));

        $nilaiMuatanNew = $this->cleanNumber($this->pick($row, [
            'nilai_muatan_pasuruan', 'nilai_muatan_rp_pasuruan',
            'nilai_muatan_rp', 'nilai_muatan',
        ]));

        if ($matches->count() > 1) {
            $this->generalAmbiguous[] = "{$noShipment} - {$tujuan} ({$matches->count()} baris)";
            return;
        }

        // ===== INSERT BARU =====
        if ($matches->isEmpty()) {
            if (!self::ALLOW_CREATE) {
                $this->generalNotFound[] = "{$noShipment} - {$tujuan}";
                return;
            }

            $tarifRow = $this->findTarif($routeNew, $ekpedisiNew, $mobilNew);

            LogistikPengirimanPasuruan::create(array_filter([
                'no_shipment_pasuruan'        => $noShipment,
                'tujuan_pasuruan'             => $tujuan,
                'ekspedisi_pasuruan'          => $tarifRow->ekpedisi ?? $ekpedisiNew,
                'route_pasuruan'              => $routeNew,
                'mobil_pasuruan'              => $tarifRow->mobil ?? $mobilNew,
                'pulau_pasuruan'              => $pulauNew,
                'area_pasuruan'               => $areaNew,
                'planner_pasuruan'            => $plannerNew,
                'pic_monitoring_pasuruan'     => $picNew,
                'dist_channel_pasuruan'       => $distChannelNew,
                'biaya_kuli_pasuruan'         => $biayaKuliNew,
                'via_kirim_pasuruan'          => $viaKirimNew,
                'kategori_ekspedisi_pasuruan' => $kategoriEkspedisiNew,
                'nilai_muatan_pasuruan'       => $nilaiMuatanNew,
                'ketersediaan_unit_pasuruan'  => 'BELUM DAPAT', // default sama seperti import
                'biaya_kirim_pasuruan'        => $tarifRow ? $this->cleanNumberTarif($tarifRow->biaya_kirim) : null,
                'kubikasi_pasuruan'           => $tarifRow ? $this->cleanDecimal($tarifRow->kubikasi) : null,
                'tonase_pasuruan'             => $tarifRow ? $this->cleanDecimal($tarifRow->tonase) : null,
                'create_tgl_pasuruan'         => Carbon::now(),
            ], fn($v) => $v !== null));

            $this->generalCreated++;

            if ($tarifRow) {
                $this->syncShipmentTarif($noShipment, $tarifRow);
            }
            return;
        }

        // ===== SUDAH ADA -> UPDATE HANYA YANG BEDA =====
        $existing = $matches->first();

        $candidates = [
            'ekspedisi_pasuruan'          => $ekpedisiNew,
            'route_pasuruan'              => $routeNew,
            'mobil_pasuruan'              => $mobilNew,
            'pulau_pasuruan'              => $pulauNew,
            'area_pasuruan'               => $areaNew,
            'planner_pasuruan'            => $plannerNew,
            'pic_monitoring_pasuruan'     => $picNew,
            'dist_channel_pasuruan'       => $distChannelNew,
            'biaya_kuli_pasuruan'         => $biayaKuliNew,
            'via_kirim_pasuruan'          => $viaKirimNew,
            'kategori_ekspedisi_pasuruan' => $kategoriEkspedisiNew,
            'nilai_muatan_pasuruan'       => $nilaiMuatanNew,
        ];

        $payload = [];
        foreach ($candidates as $column => $newValue) {
            if ($newValue === null) continue;
            if (!$this->valuesEqual($existing->{$column}, $newValue)) {
                $payload[$column] = $newValue;
            }
        }

        $ekpedisiBerubah = $ekpedisiNew !== null && !$this->valuesEqual($existing->ekspedisi_pasuruan, $ekpedisiNew);
        $mobilBerubah    = $mobilNew    !== null && !$this->valuesEqual($existing->mobil_pasuruan, $mobilNew);
        $routeBerubah    = $routeNew    !== null && !$this->valuesEqual($existing->route_pasuruan, $routeNew);

        $tarifUntukSync = null;

        // RECALC biaya_kirim + kubikasi + tonase HANYA kalau ekspedisi/mobil/route berubah
        if ($ekpedisiBerubah || $mobilBerubah || $routeBerubah) {
            $ekpedisiFinal = $ekpedisiNew ?? $existing->ekspedisi_pasuruan;
            $routeFinal    = $routeNew    ?? $existing->route_pasuruan;
            $mobilFinal    = $mobilNew    ?? $existing->mobil_pasuruan;

            $tarifRow = $this->findTarif($routeFinal, $ekpedisiFinal, $mobilFinal);

            if ($tarifRow) {
                $tarifUntukSync = $tarifRow;

                $biayaKirimBaru = $this->cleanNumberTarif($tarifRow->biaya_kirim);
                if ($biayaKirimBaru !== null && !$this->valuesEqual($existing->biaya_kirim_pasuruan, $biayaKirimBaru)) {
                    $payload['biaya_kirim_pasuruan'] = $biayaKirimBaru;
                    $this->biayaKirimUpdated++;
                }

                // pakai nama LENGKAP dari tarif; kalau sudah sama dgn DB, buang dari payload
                if ($this->valuesEqual($existing->ekspedisi_pasuruan, $tarifRow->ekpedisi)) {
                    unset($payload['ekspedisi_pasuruan']);
                } else {
                    $payload['ekspedisi_pasuruan'] = $tarifRow->ekpedisi;
                }
                if ($this->valuesEqual($existing->mobil_pasuruan, $tarifRow->mobil)) {
                    unset($payload['mobil_pasuruan']);
                } else {
                    $payload['mobil_pasuruan'] = $tarifRow->mobil;
                }

                $kubikasiTarif = $this->cleanDecimal($tarifRow->kubikasi);
                $tonaseTarif   = $this->cleanDecimal($tarifRow->tonase);

                if ($kubikasiTarif !== null && !$this->valuesEqual($existing->kubikasi_pasuruan, $kubikasiTarif)) {
                    $payload['kubikasi_pasuruan'] = $kubikasiTarif;
                }
                if ($tonaseTarif !== null && !$this->valuesEqual($existing->tonase_pasuruan, $tonaseTarif)) {
                    $payload['tonase_pasuruan'] = $tonaseTarif;
                }
            }
            // kalau $tarifRow null: ekspedisi/mobil tetap versi Excel,
            // biaya_kirim/kubikasi/tonase tidak disentuh.
        }

        if (!empty($payload)) {
            $existing->update($payload);
            $this->generalUpdated++;
        } else {
            $this->generalUnchanged++;
        }

        if ($tarifUntukSync) {
            $this->syncShipmentTarif($noShipment, $tarifUntukSync);
        }
    }

    /**
     * Sebarkan ekspedisi, mobil, biaya_kirim, kubikasi & tonase dari tarif
     * ke semua baris dalam shipment.
     */
    private function syncShipmentTarif(string $noShipment, object $tarifRow): void
    {
        $data = array_filter([
            'ekspedisi_pasuruan'   => $tarifRow->ekpedisi,
            'mobil_pasuruan'       => $tarifRow->mobil,
            'biaya_kirim_pasuruan' => $this->cleanNumberTarif($tarifRow->biaya_kirim),
            'kubikasi_pasuruan'    => $this->cleanDecimal($tarifRow->kubikasi),
            'tonase_pasuruan'      => $this->cleanDecimal($tarifRow->tonase),
        ], fn($v) => $v !== null);

        if (!empty($data)) {
            LogistikPengirimanPasuruan::where('no_shipment_pasuruan', $noShipment)->update($data);
        }
    }

    // ================= RECALC SETELAH IMPORT =================

    private function recalcShipments(): void
    {
        $table = self::TABLE;
        $shipments = array_map('strval', array_keys($this->touchedShipments));

        foreach (array_chunk($shipments, 500) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));
            $bindings = array_merge($chunk, $chunk);

            // ---- safety net: isi route/mobil/ekspedisi kosong dari baris lain ----
            foreach (['route_pasuruan', 'mobil_pasuruan', 'ekspedisi_pasuruan'] as $col) {
                DB::update("
                    UPDATE {$table} lp
                    JOIN (
                        SELECT no_shipment_pasuruan, MIN($col) AS val
                        FROM {$table}
                        WHERE $col IS NOT NULL AND $col != ''
                          AND no_shipment_pasuruan IN ($in)
                        GROUP BY no_shipment_pasuruan
                    ) x ON lp.no_shipment_pasuruan = x.no_shipment_pasuruan
                    SET lp.$col = x.val
                    WHERE (lp.$col IS NULL OR lp.$col = '')
                      AND lp.no_shipment_pasuruan IN ($in)
                ", $bindings);
            }

            // ---- kolom turunan per baris (SLA, estimasi, overstay, status, selisih qty) ----
            $this->recalcDerivedRows($chunk);

            // ---- CR: per baris sesuai kontribusi nilai_muatan (dengan CAST) ----
            DB::update("
                UPDATE {$table} lp
                JOIN (
                    SELECT no_shipment_pasuruan,
                           MAX(CAST(NULLIF(TRIM(biaya_kirim_pasuruan), '') AS DECIMAL(18,4))) AS biaya,
                           SUM(COALESCE(CAST(NULLIF(TRIM(nilai_muatan_pasuruan), '') AS DECIMAL(18,4)), 0)) AS muatan
                    FROM {$table}
                    WHERE no_shipment_pasuruan IN ($in)
                    GROUP BY no_shipment_pasuruan
                ) x ON lp.no_shipment_pasuruan = x.no_shipment_pasuruan
                SET lp.cr_pasuruan = IF(
                    x.muatan = 0
                      OR COALESCE(CAST(NULLIF(TRIM(lp.nilai_muatan_pasuruan), '') AS DECIMAL(18,4)), 0) <= 0,
                    0,
                    ROUND(
                        (CAST(NULLIF(TRIM(lp.nilai_muatan_pasuruan), '') AS DECIMAL(18,4)) * COALESCE(x.biaya, 0))
                        / (x.muatan * x.muatan) * 100,
                        4
                    )
                )
                WHERE lp.no_shipment_pasuruan IN ($in)
            ", $bindings);

            // ---- HASIL KUBIK / HASIL TONASE / PENGIRIMAN OPTIMAL ----
            DB::update("
                UPDATE {$table} lp
                JOIN (
                    SELECT no_shipment_pasuruan,
                           SUM(COALESCE(CAST(NULLIF(TRIM(total_kubik_pasuruan), '')  AS DECIMAL(18,4)), 0)) AS sum_kubik,
                           SUM(COALESCE(CAST(NULLIF(TRIM(total_tonase_pasuruan), '') AS DECIMAL(18,4)), 0)) AS sum_tonase,
                           MAX(CAST(NULLIF(TRIM(kubikasi_pasuruan), '') AS DECIMAL(18,4))) AS kubikasi,
                           MAX(CAST(NULLIF(TRIM(tonase_pasuruan), '')   AS DECIMAL(18,4))) AS tonase
                    FROM {$table}
                    WHERE no_shipment_pasuruan IN ($in)
                    GROUP BY no_shipment_pasuruan
                ) x ON lp.no_shipment_pasuruan = x.no_shipment_pasuruan
                SET
                    lp.hasil_kubik_pasuruan = CASE
                        WHEN x.kubikasi > 0 THEN ROUND(x.sum_kubik / x.kubikasi * 100, 2)
                        ELSE NULL
                    END,
                    lp.hasil_tonase_pasuruan = CASE
                        WHEN x.tonase > 0 THEN ROUND(x.sum_tonase / x.tonase * 100, 2)
                        ELSE NULL
                    END,
                    lp.pengiriman_optimal_pasuruan = CASE
                        WHEN (x.kubikasi > 0 AND (x.sum_kubik  / x.kubikasi * 100) >= 85)
                          OR (x.tonase   > 0 AND (x.sum_tonase / x.tonase   * 100) >= 85)
                            THEN 'OPTIMAL'
                        WHEN x.kubikasi > 0 OR x.tonase > 0
                            THEN 'TIDAK OPTIMAL'
                        ELSE NULL
                    END
                WHERE lp.no_shipment_pasuruan IN ($in)
            ", $bindings);
        }
    }

    /**
     * Hitung ulang kolom turunan (sama dengan PasuruanImport::buildAttributes)
     * dari nilai yang SUDAH ada di DB. Hanya menulis kalau nilainya beda.
     */
    private function recalcDerivedRows(array $shipmentChunk): void
    {
        $rows = LogistikPengirimanPasuruan::whereIn('no_shipment_pasuruan', $shipmentChunk)->get();

        foreach ($rows as $r) {
            $new = $this->computeDerived($r);

            $payload = [];
            foreach ($new as $column => $value) {
                if (!$this->valuesEqual($this->scalar($r->{$column}), $value)) {
                    $payload[$column] = $value;
                }
            }

            if (!empty($payload)) {
                $r->update($payload);
                $this->derivedUpdated++;
            }
        }
    }

    private function computeDerived($r): array
    {
        $rencanaKirim        = $this->dateStr($r->rencana_kirim_pasuruan);
        $tanggalDptUnit      = $this->dateStr($r->tanggal_dpt_unit_pasuruan);
        $tanggalTibaGudang   = $this->dateStr($r->tanggal_tiba_gudang_pasuruan);
        $tanggalKeluarGudang = $this->dateStr($r->tanggal_keluar_gudang_pasuruan);
        $tanggalTiba         = $this->dateStr($r->tanggal_tiba_pasuruan);
        $tanggalBongkar      = $this->dateStr($r->tanggal_bongkar_pasuruan);

        // lead time: master tujuan menang, kalau tidak ada pakai yang sudah di DB
        $master     = $this->lookupTujuan((string) $r->tujuan_pasuruan);
        $leadMaster = $master->transport_lead_time ?? null;
        $leadTime   = ($leadMaster !== null && $leadMaster !== '')
            ? (int) $leadMaster
            : (int) ($r->transport_lead_time_pasuruan ?? 0);

        // ---- SLA dapat mobil ----
        $lamaWaktuPencarian = null;
        $slaDapatMobil      = null;
        if ($rencanaKirim && $tanggalDptUnit) {
            $selisih = (int) date_diff(date_create($rencanaKirim), date_create($tanggalDptUnit))->format('%a');
            $lamaWaktuPencarian = $selisih;
            $slaDapatMobil      = ($selisih <= 0) ? 'On Time' : 'Delay';
        }

        // ---- SLA loading / lama di gudang ----
        $lamaDigudang = null;
        $slaLoading   = null;
        if ($tanggalTibaGudang && $tanggalKeluarGudang) {
            $selisih = (int) date_diff(date_create($tanggalTibaGudang), date_create($tanggalKeluarGudang))->format('%a');
            $lamaDigudang = $selisih;
            $slaLoading   = $selisih > 0 ? 'H+' . $selisih : 'Sesuai SLA';
        }

        // ---- estimasi, perjalanan, SLA tiba, overstay, SLA bongkar ----
        $keluar  = $tanggalKeluarGudang ? strtotime($tanggalKeluarGudang) : null;
        $tiba    = $tanggalTiba ? strtotime($tanggalTiba) : null;
        $bongkar = $tanggalBongkar ? strtotime($tanggalBongkar) : null;

        $estimasi = ($keluar && $leadTime > 0)
            ? strtotime("+{$leadTime} days", $keluar)
            : null;

        $lamaPerjalanan = ($keluar && $tiba)
            ? (int) max(0, ceil(($tiba - $keluar) / 86400))
            : null;

        $slaTiba = ($tiba && $estimasi)
            ? (($tiba <= $estimasi) ? 'On Time' : 'Delay')
            : null;

        $overstay = ($tiba && $bongkar)
            ? (int) max(0, ceil(($bongkar - $tiba) / 86400))
            : null;

        $slaBongkar = ($tiba && $bongkar)
            ? (($overstay <= 0) ? 'On Time' : 'Delay')
            : null;

        // ---- status akhir / monitoring alert / action ----
        $logic           = $this->generateStatusAlert($slaTiba, $slaBongkar);
        $monitoringAlert = $logic['alert'];

        switch ($monitoringAlert) {
            case 'TERLAMBAT':
                $actionRequired = 'Follow Up Driver';
                break;
            case 'WARNING H-2':
                $actionRequired = 'Monitoring';
                break;
            case 'TIBA DI TUJUAN':
                $actionRequired = 'Menunggu Bongkar';
                break;
            case 'SELESAI':
            case 'On Time Total':
            case 'Delay Total':
                $actionRequired = 'Closed';
                break;
            default:
                $actionRequired = '-';
                break;
        }

        // ---- selisih qty DO ----
        $selisihQty = (float) ($r->total_do_pasuruan ?? 0) - (float) ($r->actual_delivery_quantity_pasuruan ?? 0);

        return [
            'transport_lead_time_pasuruan'   => $leadTime,
            'lama_waktu_pencarian_pasuruan'  => $lamaWaktuPencarian,
            'sla_dapat_mobil_pasuruan'       => $slaDapatMobil,
            'lama_digudang_pasuruan'         => $lamaDigudang,
            'sla_ketepatan_loading_pasuruan' => $slaLoading,
            'estimasi_tiba_pasuruan'         => $estimasi ? date('Y-m-d', $estimasi) : null,
            'tanggal_estimasi_pasuruan'      => $estimasi ? date('Y-m-d', $estimasi) : null,
            'lama_perjalanan_pasuruan'       => $lamaPerjalanan,
            'sla_tiba_pasuruan'              => $slaTiba,
            'overstay_days_pasuruan'         => $overstay,
            'sla_bongkar_pasuruan'           => $slaBongkar,
            'status_akhir_pasuruan'          => $logic['status_akhir'],
            'monitoring_alert_pasuruan'      => $monitoringAlert,
            'action_required_pasuruan'       => $actionRequired,
            'selisih_quantity_pasuruan'      => $selisihQty,
            'selisih_qty_pasuruan'           => $selisihQty,
        ];
    }

    private function generateStatusAlert($sla_tiba, $sla_bongkar): array
    {
        $sla_tiba    = strtolower(trim($sla_tiba ?? '-'));
        $sla_bongkar = strtolower(trim($sla_bongkar ?? '-'));

        if ($sla_tiba == '-' || $sla_bongkar == '-') {
            return ['status_akhir' => '-', 'alert' => '-'];
        }
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

    // ================= TARIF & MASTER LOOKUP =================

    private function lookupTujuan(string $tujuan): ?object
    {
        $key = preg_replace('/\s+/', ' ', trim(strtolower($tujuan)));
        return self::$customerMap[$key] ?? null;
    }

    private function applyRouteAlias(?string $route): ?string
    {
        if ($route === null || $route === '') return $route;
        return self::ROUTE_ALIASES[$this->normalize($route)] ?? $route;
    }

    private function getKategoriEkspedisi(?string $noShipment): ?string
    {
        $no = trim((string) $noShipment);
        if (str_starts_with($no, '45')) return 'Kontrak';
        if (str_starts_with($no, '42')) return 'Oncall';
        return null;
    }

    /**
     * Sama dengan PasuruanImport::findTarif:
     * route exact + ekspedisi exact (kalau ada) + mobil prefix,
     * fallback: abaikan ekspedisi.
     */
    private function findTarif(?string $route, ?string $ekpedisi, ?string $mobil): ?object
    {
        $routeKey    = $this->normalize($route);
        $mobilExcel  = $this->normalizeMobil($mobil);
        $ekpedisiKey = $ekpedisi !== null ? $this->normalize($ekpedisi) : '';

        $candidates = self::$tarifByRoute[$routeKey] ?? null;

        if (!$candidates || $mobilExcel === '') {
            $this->logTarifNotFound($route, $ekpedisi, $mobil);
            return null;
        }

        if ($ekpedisiKey !== '') {
            $strict = $candidates->first(function ($row) use ($ekpedisiKey, $mobilExcel) {
                return $this->normalize($row->ekpedisi) === $ekpedisiKey
                    && str_starts_with($this->normalizeMobil($row->mobil), $mobilExcel);
            });
            if ($strict) return $strict;
        }

        $found = $candidates->first(function ($row) use ($mobilExcel) {
            return str_starts_with($this->normalizeMobil($row->mobil), $mobilExcel);
        });

        if (!$found) {
            $this->logTarifNotFound($route, $ekpedisi, $mobil);
        }
        return $found;
    }

    private function logTarifNotFound(?string $route, ?string $ekpedisi, ?string $mobil): void
    {
        if (empty($route) || empty($mobil)) return;
        $key = "{$ekpedisi} | {$route} | {$mobil}";
        if (!in_array($key, $this->tarifNotFound, true)) {
            $this->tarifNotFound[] = $key;
        }
    }

    /**
     * Normalisasi Route: hapus NBSP, rapikan spasi di sekitar "-",
     * collapse spasi ganda, lowercase.
     */
    private function normalize(?string $value): string
    {
        $value = str_replace("\xC2\xA0", ' ', (string) $value);
        $value = preg_replace('/\s*-\s*/', '-', $value);
        return strtolower(preg_replace('/\s+/', ' ', trim($value)));
    }

    /**
     * Normalisasi Mobil: sama tapi tanpa menyentuh "-".
     */
    private function normalizeMobil(?string $value): string
    {
        $value = str_replace("\xC2\xA0", ' ', (string) $value);
        return strtolower(preg_replace('/\s+/', ' ', trim($value)));
    }

    // ================= COMPARE / DATE HELPERS =================

    /**
     * Bandingkan nilai lama vs baru, toleran terhadap tipe data
     * (mis. varchar "8000" vs float 8000.0).
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

    /** Objek tanggal (Carbon/DateTime) -> string Y-m-d; lainnya dibiarkan. */
    private function scalar($value)
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        return $value;
    }

    private function dateStr($value): ?string
    {
        if ($value === null || $value === '' || $value === '-') return null;
        if ($value instanceof \DateTimeInterface) return $value->format('Y-m-d');

        $ts = strtotime((string) $value);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    // ================= HELPERS =================

    /**
     * Ambil nilai pertama yang terisi dari beberapa kemungkinan nama header.
     */
    private function pick(array $row, array $keys)
    {
        foreach ($keys as $k) {
            if (isset($row[$k]) && $row[$k] !== '' && $row[$k] !== '-') {
                return $row[$k];
            }
        }
        return null;
    }

    private function cleanText($value)
    {
        if ($value === null || $value === '' || $value == '-') return null;
        $value = (string) $value;
        $value = str_replace("\xC2\xA0", ' ', $value);
        return preg_replace('/\s+/', ' ', trim($value));
    }

    /** Angka bulat/float; kosong = null (supaya tidak menimpa data lama). */
    private function cleanNumber($value): ?float
    {
        if ($value === null || $value === '' || $value == '-') return null;
        if (is_numeric($value)) return (float) $value;

        $value = str_replace(['Rp', 'rp', ' '], '', (string) $value);
        $value = str_replace(['.', ','], '', $value);

        return is_numeric($value) ? (float) $value : null;
    }

    /** biaya_kirim master tarif, format "8,500,000". Kosong = null. */
    private function cleanNumberTarif($value): ?float
    {
        if ($value === null || $value === '' || $value == '-') return null;

        $value = str_replace(['Rp', 'rp', ' '], '', (string) $value);

        if (strpos($value, ',') !== false && strpos($value, '.') !== false) {
            // ada titik DAN koma -> titik = ribuan, koma = desimal
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } else {
            // cuma titik ATAU cuma koma -> keduanya pemisah ribuan
            $value = str_replace(['.', ','], '', $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }

    /** Angka desimal polos (total kubik/tonase, kapasitas). */
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