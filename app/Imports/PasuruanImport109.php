<?php

namespace App\Imports;

use App\Models\LogistikPengirimanPasuruan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\AfterImport;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Import data pengiriman Pasuruan.
 *
 * Aturan hitung (SAMA dengan LogistikImport & UpdateQtyPgiPasuruanImport):
 *  - CR                : per baris sesuai kontribusi nilai_muatan dalam satu no_shipment
 *  - hasil_kubik       : SUM(total_kubik per no_shipment) / kubikasi * 100
 *  - hasil_tonase      : SUM(total_tonase per no_shipment) / tonase * 100
 *  - pengiriman_optimal: OPTIMAL jika salah satu hasil >= 85
 * Semuanya dihitung di AfterImport (recalcShipments), BUKAN per baris.
 *
 * Kunci upsert: no_shipment_pasuruan + tujuan_pasuruan
 * (import ulang file yang sama tidak membuat data dobel).
 */
class PasuruanImport implements ToCollection, WithHeadingRow, WithCalculatedFormulas, WithEvents
{
    private const TABLE       = 'logistik_pengiriman_pasuruan';
    private const TARIF_TABLE = 'tarif_pengiriman';

    private const ROUTE_ALIASES = [
        'jabodetabek' => 'Sentul-Jabodetabek',
    ];

    private static $customerMap  = null;
    private static $tarifByRoute = null;

    // forward-fill state (merged cell di Excel): hanya route/mobil/ekspedisi
    private $lastNoShipment = null;
    private $lastRoute      = null;
    private $lastMobil      = null;
    private $lastEkpedisi   = null;

    // ===== hasil proses =====
    private int $inserted = 0;
    private int $updated  = 0;
    private int $skipped  = 0;
    private int $failed   = 0;
    private array $failedList = [];

    // no_shipment yang ada di file ini -> dipakai buat recalc di AfterImport
    private array $touchedShipments = [];

    public function getInsertedCount(): int { return $this->inserted; }
    public function getUpdatedCount(): int { return $this->updated; }
    public function getImportedCount(): int { return $this->inserted + $this->updated; }
    public function getSkippedCount(): int { return $this->skipped; }
    public function getFailedCount(): int { return $this->failed; }
    public function getFailedList(): array { return $this->failedList; }

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
                ->keyBy(fn($row) => strtolower(trim($row->tujuan)));
        }
    }

    // ================= MAIN =================

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $row = $row->toArray();

            $noShipment = $this->cleanText($row['no_shipment_pasuruan'] ?? null);

            if (empty($noShipment)) {
                $this->skipped++;
                continue;
            }

            $tujuan = $this->cleanText($row['tujuan_pasuruan'] ?? null);

            $this->touchedShipments[(string) $noShipment] = true;

            try {
                $attributes = $this->buildAttributes($row, $noShipment, $tujuan);

                $model = LogistikPengirimanPasuruan::updateOrCreate(
                    [
                        'no_shipment_pasuruan' => $noShipment,
                        'tujuan_pasuruan'      => $tujuan,
                    ],
                    $attributes
                );

                if ($model->wasRecentlyCreated) {
                    $this->inserted++;
                } else {
                    $this->updated++;
                }
            } catch (\Throwable $e) {
                $this->failed++;
                $this->failedList[] = [
                    'no_shipment' => $noShipment,
                    'tujuan'      => $tujuan,
                    'error'       => $e->getMessage(),
                    'line'        => $e->getLine(),
                ];

                logger()->error('PASURUAN IMPORT GAGAL PER BARIS', [
                    'no_shipment' => $noShipment,
                    'tujuan'      => $tujuan,
                    'error'       => $e->getMessage(),
                    'file'        => $e->getFile(),
                    'line'        => $e->getLine(),
                ]);
            }
        }
    }

    private function buildAttributes(array $row, string $noShipment, ?string $tujuan): array
    {
        // ================= DATE =================
        $tanggalTerimaPo     = $this->convertDate($row['tanggal_terima_po_pasuruan'] ?? null);
        $rencanaKirim        = $this->convertDate($row['rencana_kirim_pasuruan'] ?? null);
        $tanggalDptUnit      = $this->convertDate($row['tanggal_dpt_unit_pasuruan'] ?? null);
        $planningLoading     = $this->convertDate($row['planning_loading_pasuruan'] ?? null);
        $tanggalTibaGudang   = $this->convertDate($row['tanggal_tiba_gudang_pasuruan'] ?? null);
        $tanggalKeluarGudang = $this->convertDate($row['tanggal_keluar_gudang_pasuruan'] ?? null);
        $tanggalTiba         = $this->convertDate($row['tanggal_tiba_pasuruan'] ?? null);
        $tanggalBongkar      = $this->convertDate($row['tanggal_bongkar_pasuruan'] ?? null);

        $etd = $this->convertDate($row['etd_pasuruan'] ?? null);
        $eta = $this->convertDate($row['eta_pasuruan'] ?? null);
        $atd = $this->convertDate($row['atd_pasuruan'] ?? null);
        $ata = $this->convertDate($row['ata_pasuruan'] ?? null);

        $actPgiDate = $this->convertDate($row['act_pgi_date_pasuruan'] ?? null);

        // ================= TEXT =================
        $viaKirim          = $this->cleanText($row['via_kirim_pasuruan'] ?? $row['via_pasuruan'] ?? null);
        $shippingPoint     = $this->cleanText($row['shipping_point_pasuruan'] ?? null);
        $ketersediaanUnit  = $this->cleanText($row['ketersediaan_unit_pasuruan'] ?? null);
        $perubahanMobil    = $this->cleanText($row['perubahan_mobil_pasuruan'] ?? null);
        $kategoriEkspedisi = $this->getKategoriEkspedisi($noShipment)
                             ?? $this->cleanText($row['kategori_ekspedisi_pasuruan'] ?? null);
        $statusKendaraan    = $this->cleanText($row['status_kendaraan_pasuruan'] ?? null);
        $namaKapal          = $this->cleanText($row['nama_kapal_pasuruan'] ?? null);
        $transportLaut      = $this->cleanText($row['transport_laut_pasuruan'] ?? null);
        $reasonSelisihQty   = $this->cleanText($row['reason_selisih_quantity_pasuruan'] ?? null);
        $reasonWaktuTiba    = $this->cleanText($row['reason_waktu_tiba_pasuruan'] ?? null);
        $reasonWaktuBongkar = $this->cleanText($row['reason_waktu_bongkar_pasuruan'] ?? null);
        $remarks            = $this->cleanText($row['remarks_pasuruan'] ?? null);
        $remarksQty         = $this->cleanText($row['remarks_qty_pasuruan'] ?? null);
        $createdBy          = $this->cleanText($row['created_by_pasuruan'] ?? null);
        $noPol              = $this->cleanText($row['no_pol_pasuruan'] ?? null);
        $namaDriver         = $this->cleanText($row['nama_driver_pasuruan'] ?? null);

        // fallback dari Excel kalau tujuan tidak ketemu di master
        $pulauFromFile      = $this->cleanText($row['pulau_pasuruan'] ?? null);
        $areaFromFile       = $this->cleanText($row['area_pasuruan'] ?? null);
        $plannerFromFile    = $this->cleanText($row['planner_pasuruan'] ?? null);
        $picMonitoringExcel = $this->cleanText($row['pic_monitoring_pasuruan'] ?? null);

        // ================= NUMBER =================
        $leadTimeFromFile  = (int) $this->cleanNumber($row['transport_lead_time_pasuruan'] ?? 0);
        $nilaiMuatan       = $this->cleanNumber($row['nilai_muatan_pasuruan'] ?? null);
        $totalDo           = $this->cleanNumber($row['total_do_pasuruan'] ?? null);
        $actualDeliveryQty = $this->cleanNumber($row['actual_delivery_quantity_pasuruan'] ?? null);
        $actUrutanBongkar  = $this->cleanNumber($row['act_urutan_bongkar_pasuruan'] ?? null);
        $qtyMonitoring     = $this->cleanNumber($row['qty_monitoring_pasuruan'] ?? null);

        // total kubik/tonase: nilai per tujuan apa adanya (TANPA forward-fill),
        // karena akan dijumlahkan per no_shipment di recalcShipments()
        $totalKubik = $this->cleanDecimal(
            $row['total_kubikasi_pasuruan'] ?? $row['total_kubik_pasuruan'] ?? null
        );
        $totalTonase = $this->cleanDecimal($row['total_tonase_pasuruan'] ?? null);

        // ================= FORWARD-FILL (route/mobil/ekspedisi) =================
        if ($noShipment !== $this->lastNoShipment) {
            $this->lastRoute    = null;
            $this->lastMobil    = null;
            $this->lastEkpedisi = null;
        }

        $route = $this->applyRouteAlias(
            $this->cleanText($row['route_pasuruan'] ?? null) ?: $this->lastRoute
        );
        $mobil     = $this->cleanText($row['mobil_pasuruan'] ?? null)     ?: $this->lastMobil;
        $ekspedisi = $this->cleanText($row['ekspedisi_pasuruan'] ?? null) ?: $this->lastEkpedisi;

        if ($route)     $this->lastRoute    = $route;
        if ($mobil)     $this->lastMobil    = $mobil;
        if ($ekspedisi) $this->lastEkpedisi = $ekspedisi;

        $this->lastNoShipment = $noShipment;

        // ================= BIAYA KIRIM & KAPASITAS DARI TARIF =================
        $tarifRow = $this->findTarif($route, $ekspedisi, $mobil);

        $biayaKirim = $tarifRow
            ? $this->cleanNumberTarif($tarifRow->biaya_kirim)
            : $this->cleanNumber($row['biaya_kirim_pasuruan'] ?? null);

        // kapasitas BUKAN persen -> cleanDecimal (tidak dipotong 100)
        $kubikasi = $tarifRow ? $this->cleanDecimal($tarifRow->kubikasi ?? null) : null;
        $tonase   = $tarifRow ? $this->cleanDecimal($tarifRow->tonase ?? null)   : null;

        // ================= LOOKUP MASTER (Div = Pasuruan) =================
        $tujuanKey    = preg_replace('/\s+/', ' ', trim(strtolower((string) $tujuan)));
        $customerData = self::$customerMap[$tujuanKey] ?? null;

        $distChannel    = $customerData->dist_channel ?? null;
        $areaMaster     = $customerData->area ?? null;
        $pulauMaster    = $customerData->pulau ?? null;
        $plannerMaster  = $customerData->Planner ?? null;
        $picMaster      = $customerData->Monitoring ?? null;
        $biayaKuli      = $customerData->biaya_kuli ?? null;
        $leadTimeMaster = $customerData->transport_lead_time ?? null;

        $area          = $areaMaster ?: $areaFromFile;
        $pulau         = $pulauMaster ?: $pulauFromFile;
        $planner       = $plannerMaster ?: $plannerFromFile;
        $picMonitoring = $picMaster ?: $picMonitoringExcel;
        $leadTime      = ($leadTimeMaster !== null && $leadTimeMaster !== '')
            ? (int) $leadTimeMaster
            : $leadTimeFromFile;

        // ================= NORMALISASI KETERSEDIAAN UNIT =================
        if ($ketersediaanUnit === null || $ketersediaanUnit === '' || $ketersediaanUnit === '-') {
            $ketersediaanUnit = 'BELUM DAPAT';
        } else {
            $ketersediaanUnit = strtoupper(trim($ketersediaanUnit));
        }

        if (in_array($ketersediaanUnit, ['SUDAH DAPAT MOBIL', 'READY MOBIL', 'READY'])) {
            $ketersediaanUnit = 'SUDAH DAPAT';
        }
        if (in_array($ketersediaanUnit, ['BELUM DAPAT MOBIL', 'PENDING'])) {
            $ketersediaanUnit = 'BELUM DAPAT';
        }

        // ================= SLA DAPAT MOBIL (rencana_kirim -> tanggal_dpt_unit) =================
        $lamaWaktuPencarian = null;
        $slaDapatMobil      = null;

        if ($rencanaKirim && $tanggalDptUnit) {
            $selisihCariMobil = (int) date_diff(
                date_create($rencanaKirim),
                date_create($tanggalDptUnit)
            )->format('%a');

            $lamaWaktuPencarian = $selisihCariMobil;
            $slaDapatMobil      = ($selisihCariMobil <= 0) ? 'On Time' : 'Delay';
        }

        // ================= SLA LOADING / LAMA DI GUDANG =================
        $lamaDigudang = null;
        $slaLoading   = null;

        if ($tanggalTibaGudang && $tanggalKeluarGudang) {
            $selisihGudang = (int) date_diff(
                date_create($tanggalTibaGudang),
                date_create($tanggalKeluarGudang)
            )->format('%a');

            $lamaDigudang = $selisihGudang;
            $slaLoading   = $selisihGudang > 0 ? 'H+' . $selisihGudang : 'Sesuai SLA';
        }

        // ================= ESTIMASI, PERJALANAN, SLA TIBA, OVERSTAY, SLA BONGKAR =================
        $keluar  = $tanggalKeluarGudang ? strtotime($tanggalKeluarGudang) : null;
        $tiba    = $tanggalTiba ? strtotime($tanggalTiba) : null;
        $bongkar = $tanggalBongkar ? strtotime($tanggalBongkar) : null;

        $estimasi = ($keluar && $leadTime > 0)
            ? strtotime("+{$leadTime} days", $keluar)
            : null;

        $lamaPerjalanan = ($keluar && $tiba)
            ? max(0, ceil(($tiba - $keluar) / 86400))
            : null;

        $slaTiba = ($tiba && $estimasi)
            ? (($tiba <= $estimasi) ? 'On Time' : 'Delay')
            : null;

        $overstay = ($tiba && $bongkar)
            ? max(0, ceil(($bongkar - $tiba) / 86400))
            : null;

        $slaBongkar = ($tiba && $bongkar)
            ? (($overstay <= 0) ? 'On Time' : 'Delay')
            : null;

        // ================= STATUS AKHIR / MONITORING ALERT =================
        $logic           = $this->generateStatusAlert($slaTiba, $slaBongkar);
        $statusAkhir     = $logic['status_akhir'];
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

        // ================= SELISIH QTY DO =================
        $selisihQty = $totalDo - $actualDeliveryQty;

        // cr / hasil_kubik / hasil_tonase / pengiriman_optimal
        // TIDAK dihitung di sini, dihitung per shipment di recalcShipments()
        return [
            // ================= BASIC =================
            'transport_lead_time_pasuruan' => $leadTime,
            'planner_pasuruan'             => $planner,
            'dist_channel_pasuruan'        => $distChannel,
            'area_pasuruan'                => $area,
            'pulau_pasuruan'               => $pulau,

            'route_pasuruan'     => $route,
            'via_kirim_pasuruan' => $viaKirim,
            'total_do_pasuruan'  => $totalDo,

            'ketersediaan_unit_pasuruan' => $ketersediaanUnit,
            'mobil_pasuruan'             => $mobil,
            'perubahan_mobil_pasuruan'   => $perubahanMobil,

            'nilai_muatan_pasuruan' => $nilaiMuatan,
            'kubikasi_pasuruan'     => $kubikasi,
            'tonase_pasuruan'       => $tonase,
            'total_kubik_pasuruan'  => $totalKubik,
            'total_tonase_pasuruan' => $totalTonase,
            'biaya_kirim_pasuruan'  => $biayaKirim,
            'biaya_kuli_pasuruan'   => $biayaKuli,

            'kategori_ekspedisi_pasuruan' => $kategoriEkspedisi,
            'ekspedisi_pasuruan'          => $ekspedisi,

            'no_pol_pasuruan'      => $noPol,
            'nama_driver_pasuruan' => $namaDriver,

            // ================= DATE =================
            'tanggal_terima_po_pasuruan'     => $tanggalTerimaPo,
            'rencana_kirim_pasuruan'         => $rencanaKirim,
            'tanggal_dpt_unit_pasuruan'      => $tanggalDptUnit,
            'planning_loading_pasuruan'      => $planningLoading,
            'tanggal_tiba_gudang_pasuruan'   => $tanggalTibaGudang,
            'tanggal_keluar_gudang_pasuruan' => $tanggalKeluarGudang,
            'tanggal_tiba_pasuruan'          => $tanggalTiba,
            'tanggal_bongkar_pasuruan'       => $tanggalBongkar,

            // ================= GUDANG =================
            'lama_digudang_pasuruan'         => $lamaDigudang,
            'sla_ketepatan_loading_pasuruan' => $slaLoading,
            'lama_waktu_pencarian_pasuruan'  => $lamaWaktuPencarian,
            'sla_dapat_mobil_pasuruan'       => $slaDapatMobil,

            // ================= MONITORING =================
            'pic_monitoring_pasuruan'   => $picMonitoring,
            'status_kendaraan_pasuruan' => $statusKendaraan,
            'monitoring_alert_pasuruan' => $monitoringAlert,
            'action_required_pasuruan'  => $actionRequired,

            'estimasi_tiba_pasuruan'    => $estimasi ? date('Y-m-d', $estimasi) : null,
            'tanggal_estimasi_pasuruan' => $estimasi ? date('Y-m-d', $estimasi) : null,

            'lama_perjalanan_pasuruan' => $lamaPerjalanan,
            'sla_tiba_pasuruan'        => $slaTiba,

            'overstay_days_pasuruan' => $overstay,
            'sla_bongkar_pasuruan'   => $slaBongkar,

            'status_akhir_pasuruan' => $statusAkhir,

            // ================= KAPAL =================
            'nama_kapal_pasuruan'     => $namaKapal,
            'etd_pasuruan'            => $etd,
            'eta_pasuruan'            => $eta,
            'atd_pasuruan'            => $atd,
            'ata_pasuruan'            => $ata,
            'transport_laut_pasuruan' => $transportLaut,

            // ================= DELIVERY =================
            'actual_delivery_quantity_pasuruan' => $actualDeliveryQty,
            'selisih_quantity_pasuruan'         => $selisihQty,
            'reason_selisih_quantity_pasuruan'  => $reasonSelisihQty,

            // ================= REASON =================
            'reason_waktu_tiba_pasuruan'    => $reasonWaktuTiba,
            'reason_waktu_bongkar_pasuruan' => $reasonWaktuBongkar,
            'remarks_pasuruan'              => $remarks,
            'remarks_qty_pasuruan'          => $remarksQty,
            'selisih_qty_pasuruan'          => $selisihQty,

            // ================= OTHER =================
            'act_pgi_date_pasuruan'       => $actPgiDate,
            'act_urutan_bongkar_pasuruan' => $actUrutanBongkar,
            'shipping_point_pasuruan'     => $shippingPoint,
            'qty_monitoring_pasuruan'     => $qtyMonitoring,
            'created_by_pasuruan'         => $createdBy,
            'create_tgl_pasuruan'         => date('Y-m-d H:i:s'),
        ];
    }

    // ================= AFTER IMPORT =================

    public function registerEvents(): array
    {
        return [
            AfterImport::class => function () {
                $shipments = array_map('strval', array_keys($this->touchedShipments));
                $table     = self::TABLE;

                foreach (array_chunk($shipments, 500) as $chunk) {
                    $in       = implode(',', array_fill(0, count($chunk), '?'));
                    $bindings = array_merge($chunk, $chunk);

                    // ---- safety net: isi route/mobil/ekspedisi yang kosong
                    //      dari baris lain di shipment yang sama ----
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

                    // ---- CR: per baris sesuai kontribusi nilai_muatan ----
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

                    // ---- hasil kubik / tonase / optimal:
                    //      SUM per shipment / kapasitas ----
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
            },
        ];
    }

    // ================= BUSINESS HELPERS =================

    private function applyRouteAlias(?string $route): ?string
    {
        if ($route === null || $route === '') return $route;

        $key = $this->normalize($route);
        return self::ROUTE_ALIASES[$key] ?? $route;
    }

    private function getKategoriEkspedisi(?string $noShipment): ?string
    {
        $no = trim((string) $noShipment);

        if (str_starts_with($no, '45')) return 'Kontrak';
        if (str_starts_with($no, '42')) return 'Oncall';

        return null;
    }

    private function generateStatusAlert($sla_tiba, $sla_bongkar)
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

    /**
     * Cari baris tarif: Route (exact setelah normalisasi) + Ekpedisi (exact,
     * kalau terisi) + Mobil (PREFIX match karena kolom Excel sering kepotong).
     * Fallback: abaikan Ekpedisi, cukup Route + Mobil prefix.
     */
    private function findTarif(?string $route, ?string $ekpedisi, ?string $mobil)
    {
        $routeKey    = $this->normalize($route);
        $mobilExcel  = $this->normalizeMobil($mobil);
        $ekpedisiKey = $ekpedisi !== null ? $this->normalize($ekpedisi) : '';

        $candidates = self::$tarifByRoute[$routeKey] ?? null;

        if (!$candidates || $mobilExcel === '') {
            return null;
        }

        if ($ekpedisiKey !== '') {
            $strict = $candidates->first(function ($row) use ($ekpedisiKey, $mobilExcel) {
                $mobilMaster = $this->normalizeMobil($row->mobil);
                return $this->normalize($row->ekpedisi) === $ekpedisiKey
                    && str_starts_with($mobilMaster, $mobilExcel);
            });

            if ($strict) {
                return $strict;
            }
        }

        return $candidates->first(function ($row) use ($mobilExcel) {
            $mobilMaster = $this->normalizeMobil($row->mobil);
            return str_starts_with($mobilMaster, $mobilExcel);
        });
    }

    // ================= GENERIC HELPERS =================

    private function cleanText($value)
    {
        if (!$value || $value == '-' || $value == '#VALUE!') return null;
        if (is_array($value)) return null;

        $value = trim((string) $value);

        if (str_contains($value, '=')) return null;

        return $value;
    }

    private function cleanNumber($value)
    {
        if ($value === null || $value === '' || $value == '-') return 0;

        if (is_numeric($value)) return (float) $value;

        $value = str_replace(['Rp', 'rp', ' '], '', $value);
        $value = str_replace(['.', ','], '', $value);

        return (float) $value;
    }

    /**
     * Angka desimal polos (bukan persen, bukan Rupiah).
     * Dipakai untuk total kubik/tonase dan kapasitas kubikasi/tonase.
     */
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

    /**
     * biaya_kirim di master tarif formatnya "8,500,000" (koma = ribuan).
     */
    private function cleanNumberTarif($value): float
    {
        if ($value === null || $value === '' || $value == '-') return 0;

        $value = (string) $value;
        $value = str_replace(['Rp', 'rp', ' '], '', $value);

        if (strpos($value, ',') !== false && strpos($value, '.') !== false) {
            // ada titik DAN koma -> titik = ribuan, koma = desimal
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } else {
            // cuma titik ATAU cuma koma -> keduanya pemisah ribuan
            $value = str_replace(['.', ','], '', $value);
        }

        return is_numeric($value) ? (float) $value : 0;
    }

    private function convertDate($value)
    {
        if (!$value || $value == '-' || $value == '#VALUE!') return null;

        if (is_numeric($value)) {
            return Date::excelToDateTimeObject($value)->format('Y-m-d');
        }

        $timestamp = strtotime(str_replace('/', '-', trim($value)));

        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    /**
     * Normalisasi Route: hapus NBSP, rapikan spasi di sekitar "-",
     * collapse spasi ganda, lowercase.
     */
    private function normalize(?string $value): string
    {
        $value = (string) $value;
        $value = str_replace("\xC2\xA0", ' ', $value);
        $value = preg_replace('/\s*-\s*/', '-', $value);
        $value = preg_replace('/\s+/', ' ', trim($value));

        return strtolower($value);
    }

    /**
     * Normalisasi Mobil: sama seperti normalize() tapi tanpa menyentuh "-".
     */
    private function normalizeMobil(?string $value): string
    {
        $value = (string) $value;
        $value = str_replace("\xC2\xA0", ' ', $value);
        $value = preg_replace('/\s+/', ' ', trim($value));

        return strtolower($value);
    }
}