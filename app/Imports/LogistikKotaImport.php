<?php

namespace App\Imports;

use Illuminate\Support\Facades\DB;
use App\Models\LogistikPengirimanKota;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterImport;

class LogistikKotaImport implements ToModel, WithHeadingRow, WithEvents, WithCalculatedFormulas
{
    private static $customerMap = null;
    private static $tarifByRoute = null;
    private const TARIF_TABLE = 'tarif_pengiriman';

    // ================= FORWARD-FILL STATE =================
    // Sama seperti LogistikImport: kolom Route/Mobil/Ekpedisi/Total
    // Kubik/Total Tonase biasanya hasil merged cell di Excel sumber,
    // hanya terisi di baris pertama tiap No Shipment.
    private $lastNoShipment  = null;
    private $lastRoute       = null;
    private $lastMobil       = null;
    private $lastEkpedisi    = null;
    private $lastTotalKubik  = null;
    private $lastTotalTonase = null;

    private $imported = 0;
    private $skipped  = 0;

    public function getImportedCount(): int { return $this->imported; }
    public function getSkippedCount(): int { return $this->skipped; }

    public function __construct()
    {
        if (self::$customerMap === null) {
            self::$customerMap = DB::table('tujuanfillterr')
                ->select(
                    'tujuan',
                    'dist_channel',
                    'pulau',
                    'area',
                    'Planner',
                    'biaya_kuli',
                    'transport_lead_time',
                    'Monitoring'
                )
                ->where('Div', 'HO Meruya')
                ->get()
                ->keyBy(fn($row) => strtolower(trim($row->tujuan)));
        }

        if (self::$tarifByRoute === null) {
            self::$tarifByRoute = DB::table(self::TARIF_TABLE)
                ->select('ekpedisi', 'route', 'mobil', 'biaya_kirim', 'kubikasi', 'tonase')
                ->get()
                ->groupBy(fn($row) => $this->normalize($row->route));
        }
    }

    public function model(array $row)
    {
        $noShipmentCheck = $this->cleanText($row['no_shipment'] ?? null);
        if (empty($noShipmentCheck)) {
            $this->skipped++;
            return null;
        }

        // =============================
        // GUDANG 2
        // =============================
        $lama_digudang_2 = null;
        $sla_loading_2   = null;

        if (!empty($row['tanggal_tiba_gudang_2']) && !empty($row['tanggal_keluar_gudang_2'])) {
            $in2  = strtotime($row['tanggal_tiba_gudang_2']);
            $out2 = strtotime($row['tanggal_keluar_gudang_2']);
            $jam2 = ($out2 - $in2) / 3600;
            $lama_digudang_2 = round($jam2, 1) . ' Jam';
            $sla_loading_2 = $jam2 <= 24 ? 'H+0' : ($jam2 <= 48 ? 'H+1' : 'H>1');
        }

        // =============================
        // GUDANG 3
        // =============================
        $lama_digudang_3 = null;
        $sla_loading_3   = null;

        if (!empty($row['tanggal_tiba_gudang_3']) && !empty($row['tanggal_keluar_gudang_3'])) {
            $in3  = strtotime($row['tanggal_tiba_gudang_3']);
            $out3 = strtotime($row['tanggal_keluar_gudang_3']);
            $jam3 = ($out3 - $in3) / 3600;
            $lama_digudang_3 = round($jam3, 1) . ' Jam';
            $sla_loading_3 = $jam3 <= 24 ? 'H+0' : ($jam3 <= 48 ? 'H+1' : 'H>1');
        }

        // ================= NILAI TAMBAHAN =================
        $totalDoCar = $this->cleanNumber($row['total_do_qty_car'] ?? null);
        $addtText4  = $this->cleanText($row['addt_text'] ?? null);

        $kubikasi = $this->cleanPersen($this->pick($row, ['kubikasi', 'kubikasi_persen', 'kubikasi_1']));
        $tonase   = $this->cleanPersen($this->pick($row, ['tonase', 'tonase_persen']));

        // ================= DATE =================
        $rencanaKirim        = $this->convertDate($row['rencana_kirim'] ?? null);
        $tanggalKeluarGudang = $this->convertDate($row['tanggal_keluar_gudang'] ?? null);
        $tanggalTibaAktual   = $this->convertDate($row['tanggal_tiba'] ?? null);
        $tanggalNaikLogistik = $this->convertDate($row['tanggal_naik_logistik'] ?? null);
        $tanggalDptUnit      = $this->convertDate($row['tanggal_dpt_unit'] ?? null);
        $planningLoading     = $this->convertDate($row['planning_loading'] ?? null);
        $tanggalTibaGudang   = $this->convertDate($row['tanggal_tiba_di_gudang'] ?? null);
        $tanggalBongkar      = $this->convertDate($row['tanggal_bongkar'] ?? null);

        $tanggalTibaGudang2   = $this->convertDate($row['tanggal_tiba_gudang_2'] ?? null);
        $planningLoading2     = $this->convertDate($row['planning_loading_2'] ?? null);
        $tanggalKeluarGudang2 = $this->convertDate($row['tanggal_keluar_gudang_2'] ?? null);

        $tanggalTibaGudang3   = $this->convertDate($row['tanggal_tiba_gudang_3'] ?? null);
        $planningLoading3     = $this->convertDate($row['planning_loading_3'] ?? null);
        $tanggalKeluarGudang3 = $this->convertDate($row['tanggal_keluar_gudang_3'] ?? null);

        // ================= SLA DAPAT MOBIL =================
        $lamaWaktuPencarian = null;
        $slaDapatMobil      = null;

        if ($tanggalDptUnit && $tanggalTibaGudang) {
            $selisihCariMobil = (int) date_diff(
                date_create($tanggalDptUnit),
                date_create($tanggalTibaGudang)
            )->format('%a');

            $lamaWaktuPencarian = $selisihCariMobil . ' Hari';
            $slaDapatMobil = ($selisihCariMobil == 0) ? 'On Time' : 'Delay';
        }

        // ================= SLA LOADING (GUDANG 1) =================
        $lamaDigudang = null;
        $slaLoading   = null;

        if ($tanggalTibaGudang && $tanggalKeluarGudang) {
            $selisihGudang = (int) date_diff(
                date_create($tanggalTibaGudang),
                date_create($tanggalKeluarGudang)
            )->format('%a');

            $lamaDigudang = $selisihGudang . ' Hari';
            $slaLoading = ($selisihGudang == 0) ? 'On Time' : 'Delay';
        }

        $act_pgi_date = $this->convertDate($row['act_pgi_date'] ?? null);
        $custGrp5     = $this->cleanText($row['cust_grp_5_desc'] ?? null);
        $custGrp3     = $this->cleanText($row['cust_grp_3_desc'] ?? null);
        $shipNo       = $this->cleanText($row['ship_no'] ?? null);
        $viaKirim     = $this->cleanText($row['via_kirim'] ?? $row['via'] ?? null);
        $custDesc     = $this->cleanText($row['cust_desc'] ?? null);
        $serviceAgent = $this->cleanText($row['service_agent'] ?? null);

        // ================= BIAYA KULI (manual dari Excel, fallback ke master) =================
        $biayaKuliFromFile = $this->cleanNumber($row['biaya_kuli'] ?? null);

        // ================= FORWARD-FILL =================
        $noShipment = $this->cleanText($row['no_shipment'] ?? null);

        if ($noShipment !== $this->lastNoShipment) {
            $this->lastRoute       = null;
            $this->lastMobil       = null;
            $this->lastEkpedisi    = null;
            $this->lastTotalKubik  = null;
            $this->lastTotalTonase = null;
        }

        $route    = $this->cleanText($row['route'] ?? null)    ?: $this->lastRoute;
        $mobil    = $this->cleanText($row['mobil'] ?? null)    ?: $this->lastMobil;
        $ekpedisi = $this->cleanText($row['ekpedisi'] ?? null) ?: $this->lastEkpedisi;

        $totalKubik = $this->cleanDecimal($this->pick($row, [
            'total_kubik', 'total_kubik_m3', 'total_kubik_m_3', 'totalkubik'
        ])) ?? $this->lastTotalKubik;

        $totalTonase = $this->cleanDecimal($this->pick($row, [
            'total_tonase', 'total_tonase_ton', 'totaltonase'
        ])) ?? $this->lastTotalTonase;

        if ($route)    $this->lastRoute    = $route;
        if ($mobil)    $this->lastMobil    = $mobil;
        if ($ekpedisi) $this->lastEkpedisi = $ekpedisi;
        if ($totalKubik !== null)  $this->lastTotalKubik  = $totalKubik;
        if ($totalTonase !== null) $this->lastTotalTonase = $totalTonase;

        $this->lastNoShipment = $noShipment;

        // ================= BIAYA KIRIM (lookup master tarif) =================
        $tarifRow   = $this->findTarif($route, $ekpedisi, $mobil);
        $biayaKirim = $tarifRow
            ? $this->cleanNumberTarif($tarifRow->biaya_kirim)
            : $this->cleanNumber($row['biaya_kirim_rp'] ?? null);

        $kubikasi = $tarifRow ? $this->cleanPersen($tarifRow->kubikasi ?? null) : $kubikasi;
        $tonase   = $tarifRow ? $this->cleanPersen($tarifRow->tonase ?? null)   : $tonase;

        // ================= TUJUAN =================
        $tujuan    = $this->cleanText($row['tujuan'] ?? null);
        $tujuanKey = preg_replace('/\s+/', ' ', trim(strtolower($tujuan ?? '')));

        $customerData = self::$customerMap[$tujuanKey] ?? null;

        $distChannel   = $customerData->dist_channel ?? null;
        $pulauMaster   = $customerData->pulau ?? null;
        $area          = $customerData->area ?? null;
        $plannerMaster = $customerData->Planner ?? null;
        $picMonitoring = $customerData->Monitoring ?? null;
        $biayaKuliMaster      = $customerData->biaya_kuli ?? null;
        $transport_lead_time  = $customerData->transport_lead_time ?? null;

        $biayaKuli = $biayaKuliFromFile ?? $biayaKuliMaster;

        $pulauFromFile   = $this->cleanText($row['pulau'] ?? null);
        $plannerFromFile = $this->cleanText($row['planner'] ?? null);

        $pulau   = $pulauMaster ?: $pulauFromFile;
        $planner = $plannerMaster ?: $plannerFromFile;

        // ================= MONITORING / SLA =================
        $keluar = collect([
            $tanggalKeluarGudang,
            $tanggalKeluarGudang2,
            $tanggalKeluarGudang3,
        ])
            ->filter()
            ->map(fn($d) => strtotime($d))
            ->max();

        $tiba    = $tanggalTibaAktual ? strtotime($tanggalTibaAktual) : null;
        $bongkar = $tanggalBongkar ? strtotime($tanggalBongkar) : null;
        $leadtime = (int) ($row['transport_lead_time'] ?? $transport_lead_time ?? 0);

        $estimasi = $keluar ? strtotime("+{$leadtime} days", $keluar) : null;

        $lamaPerjalanan = ($keluar && $tiba)
            ? max(0, floor(($tiba - $keluar) / 86400))
            : null;

        $slaTiba = ($tiba && $estimasi)
            ? (($tiba <= $estimasi) ? 'On Time' : 'Delay')
            : '-';

        $overstay = ($tiba && $bongkar)
            ? max(0, floor(($bongkar - $tiba) / 86400))
            : null;

        $slaBongkar = ($tiba && $bongkar)
            ? (($overstay <= 0) ? 'On Time' : 'Delay')
            : '-';

        $logic = $this->generateStatusAlert($slaTiba, $slaBongkar);
        $statusAkhir     = $logic['status_akhir'];
        $monitoringAlert = $logic['alert'];

        // ================= KETERSEDIAAN UNIT =================
        $ketersediaanUnit = $this->cleanText($row['ketersediaan_unit'] ?? null);

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

        // ================= KAPAL (opsional, dari Excel) =================
        $namaKapal = $this->cleanText($row['nama_kapal'] ?? null);
        $etd = $this->convertDate($row['etd'] ?? null);
        $eta = $this->convertDate($row['eta'] ?? null);
        $atd = $this->convertDate($row['atd'] ?? null);
        $ata = $this->convertDate($row['ata'] ?? null);

        // ================= QTY MONITORING (turunan, kalau selisih_qty ada di Excel) =================
        $selisihQty = $this->cleanNumber($row['selisih_qty'] ?? null);
        $qtyMonitoring = ($totalDoCar !== null && $selisihQty !== null)
            ? ($totalDoCar - $selisihQty)
            : null;
        $totalBiayaKuli = ($qtyMonitoring !== null && $biayaKuli !== null)
            ? ($qtyMonitoring * $biayaKuli)
            : null;

        $create_tgl = date('Y-m-d H:i:s');
        $this->imported++;

        return new LogistikPengirimanKota([

            'no_kota'                  => $this->cleanText($row['no'] ?? null),
            'create_tgl_kota'          => $create_tgl,
            'transport_lead_time_kota' => $transport_lead_time,
            'planner_kota'             => $planner,
            'no_shipment_kota'         => $noShipment,
            'tujuan_kota'              => $tujuan,
            'dist_channel_kota'        => $distChannel,
            'area_kota'                => $area,
            'ketersediaan_unit_kota'   => $ketersediaanUnit,

            'pulau_kota'      => $pulau,
            'route_kota'      => $route,
            'via_kirim_kota'  => $viaKirim,
            'mobil_kota'      => $mobil,

            'perubahan_mobil_kota'    => $this->cleanText($row['perubahan_mobil'] ?? null),
            'cr_kota'                 => $this->cleanText($row['cr'] ?? null),
            'kategori_ekspedisi_kota' => $this->cleanText($row['kategori_ekspedisi'] ?? null),
            'ekpedisi_kota'           => $ekpedisi,
            'kubikasi_kota'           => $kubikasi,

            'nama_driver_kota' => $this->cleanText($row['nama_driver'] ?? null),
            'no_pol_kota'      => $this->cleanText($row['no_pol'] ?? ($row['nopol'] ?? null)),

            'status_pengiriman_kota' => $this->cleanText($row['status'] ?? null),

            'nilai_muatan_kota' => $this->cleanNumber($row['nilai_muatan_rp'] ?? null),
            'biaya_kuli_kota'   => $biayaKuli,
            'total_biaya_kuli_kota' => $totalBiayaKuli,
            'biaya_kirim_kota'  => $biayaKirim,

            'tanggal_naik_logistik_kota' => $tanggalNaikLogistik,
            'rencana_kirim_kota'         => $rencanaKirim,
            'tanggal_dpt_unit_kota'      => $tanggalDptUnit,
            'planning_loading_kota'      => $planningLoading,
            'tanggal_tiba_gudang_kota'   => $tanggalTibaGudang,
            'tanggal_keluar_gudang_kota' => $tanggalKeluarGudang,
            'tanggal_tiba_kota'          => $tanggalTibaAktual,
            'tanggal_bongkar_kota'       => $tanggalBongkar,

            'tanggal_tiba_gudang_2_kota'   => $tanggalTibaGudang2,
            'planning_loading_2_kota'      => $planningLoading2,
            'tanggal_keluar_gudang_2_kota' => $tanggalKeluarGudang2,
            'lama_digudang_2_kota'         => $lama_digudang_2,
            'sla_loading_2_kota'           => $sla_loading_2,

            'tanggal_tiba_gudang_3_kota'   => $tanggalTibaGudang3,
            'planning_loading_3_kota'      => $planningLoading3,
            'tanggal_keluar_gudang_3_kota' => $tanggalKeluarGudang3,
            'lama_digudang_3_kota'         => $lama_digudang_3,
            'sla_loading_3_kota'           => $sla_loading_3,

            'estimasi_tiba_kota' => $estimasi ? date('Y-m-d', $estimasi) : null,

            'lama_perjalanan_kota'  => $lamaPerjalanan,
            'sla_tiba_kota'         => $slaTiba,
            'overstay_days_kota'    => $overstay,
            'sla_bongkar_kota'      => $slaBongkar,
            'status_akhir_kota'     => $statusAkhir,
            'monitoring_alert_kota' => $monitoringAlert,

            'lama_waktu_pencarian_kota' => $lamaWaktuPencarian,
            'sla_dapat_mobil_kota'      => $slaDapatMobil,

            'lama_digudang_kota' => $lamaDigudang,
            'sla_loading_kota'   => $slaLoading,

            'keterangan_kota'  => $this->cleanText($row['keterangan'] ?? null),

            'pic_monitoring_kota'   => $picMonitoring,
            'status_kendaraan_kota' => $this->cleanText($row['status_kendaraan'] ?? null),
            'action_required_kota'  => $this->cleanText($row['action_required'] ?? null),

            'act_urutan_bongkar_kota' => $row['act_urutan_bongkar'] ?? null,
            'urutan_bongkar_kota'     => $this->cleanText($row['urutan_bongkar'] ?? null),

            'reason_tiba_kota'    => $this->cleanText($row['reason_waktu_tiba'] ?? null),
            'reason_bongkar_kota' => $this->cleanText($row['reason_waktu_bongkar'] ?? null),

            'act_pgi_date_kota'    => $act_pgi_date,
            'cust_grp_5_desc_kota' => $custGrp5,
            'created_by_kota'      => $this->cleanText($row['created_by'] ?? null),
            'cust_grp_3_desc_kota' => $custGrp3,
            'ship_no_kota'         => $shipNo,
            'cust_desc_kota'       => $custDesc,
            'addt_text_4_kota'     => $addtText4,
            'service_agent_kota'   => $serviceAgent,
            'total_do_qty_car_kota' => $totalDoCar,

            'selisih_qty_kota'    => $selisihQty,
            'qty_monitoring_kota' => $qtyMonitoring,
            'remarks_qty_kota'    => $this->cleanText($row['remarks_qty'] ?? null),
            'remarks_kota'        => $this->cleanText($row['remarks'] ?? null),

            'nama_kapal_kota' => $namaKapal,
            'etd_kota' => $etd,
            'eta_kota' => $eta,
            'atd_kota' => $atd,
            'ata_kota' => $ata,

            'created_at' => now(),
            'updated_at' => now(),
        ]);
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

    // ================= HELPERS (identik dengan LogistikImport) =================

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
        if ($value === null || $value === '' || $value == '-') return null;
        if (is_numeric($value)) return (float) $value;
        $value = str_replace(['Rp', 'rp', ' '], '', $value);
        $value = str_replace(['.', ','], '', $value);
        return (float) $value;
    }

    private function cleanPersen($value)
    {
        if ($value === null || $value === '' || $value == '-') return null;
        $value = str_replace('%', '', (string) $value);
        $value = str_replace(',', '.', $value);
        $value = preg_replace('/[^0-9.]/', '', $value);
        if (!is_numeric($value)) return null;
        $value = (float) $value;
        if ($value < 0) $value = 0;
        if ($value > 100) $value = 100;
        return round($value, 2);
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
        if (!$value || $value == '-' || $value == '#VALUE!') return null;
        if (is_numeric($value)) {
            return Date::excelToDateTimeObject($value)->format('Y-m-d');
        }
        $timestamp = strtotime(str_replace('/', '-', trim($value)));
        return $timestamp ? date('Y-m-d', $timestamp) : null;
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
        $value = (string) $value;
        $value = str_replace("\xC2\xA0", ' ', $value);
        $value = preg_replace('/\s+/', ' ', trim($value));
        return strtolower($value);
    }

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

            if ($strict) return $strict;
        }

        return $candidates->first(function ($row) use ($mobilExcel) {
            $mobilMaster = $this->normalizeMobil($row->mobil);
            return str_starts_with($mobilMaster, $mobilExcel);
        });
    }

    private function pick(array $row, array $keys)
    {
        foreach ($keys as $k) {
            if (isset($row[$k]) && $row[$k] !== '' && $row[$k] !== '-') {
                return $row[$k];
            }
        }
        return null;
    }

    private function cleanNumberTarif($value): float
    {
        if ($value === null || $value === '' || $value == '-') return 0;
        $value = str_replace(['Rp', 'rp', ' ', '.', ','], '', (string) $value);
        return (float) $value;
    }

    public function registerEvents(): array
    {
        return [
            AfterImport::class => function () {

                foreach (['route_kota', 'mobil_kota', 'ekpedisi_kota'] as $col) {
                    DB::statement("
                        UPDATE logistik_pengiriman_kota lp
                        JOIN (
                            SELECT no_shipment_kota, MIN($col) AS val
                            FROM logistik_pengiriman_kota
                            WHERE $col IS NOT NULL AND $col != ''
                            GROUP BY no_shipment_kota
                        ) x ON lp.no_shipment_kota = x.no_shipment_kota
                        SET lp.$col = x.val
                        WHERE (lp.$col IS NULL OR lp.$col = '')
                          AND lp.no_shipment_kota IS NOT NULL
                          AND lp.no_shipment_kota != ''
                    ");
                }

                DB::statement("
                    UPDATE logistik_pengiriman_kota lp
                    JOIN (
                        SELECT
                            no_shipment_kota,
                            MAX(biaya_kirim_kota) AS biaya,
                            SUM(nilai_muatan_kota) AS muatan
                        FROM logistik_pengiriman_kota
                        GROUP BY no_shipment_kota
                    ) x ON lp.no_shipment_kota = x.no_shipment_kota
                    SET lp.cr_kota = IF(
                        x.muatan = 0 OR lp.nilai_muatan_kota <= 0,
                        0,
                        ROUND((lp.nilai_muatan_kota * x.biaya) / (x.muatan * x.muatan) * 100, 4)
                    )
                ");
            },
        ];
    }
}