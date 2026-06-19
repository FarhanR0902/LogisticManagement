<?php

namespace App\Imports;

use Illuminate\Support\Facades\DB;
use App\Models\LogistikPengiriman;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class LogistikImport implements ToModel, WithHeadingRow
{


    private static $customerMap = null;
    private static $picMap = null;

    public function __construct()
    {
        // Mapping tujuan -> dist_channel
        if (self::$customerMap === null) {
            self::$customerMap = DB::table('tujuanfilterr')
                ->select(
                    'name_customer_1',
                    'dist_channel',
                    'area'
                )
                ->get()
                ->keyBy(fn($row) => strtolower(trim($row->name_customer_1)));
        }

        // Mapping tujuan -> pic_monitoring
        if (self::$picMap === null) {
            self::$picMap = DB::table('pic_monitoring')
                ->select('tujuan', 'pic_monitoring')
                ->get()
                ->keyBy(fn($row) => strtolower(trim($row->tujuan)));
        }
    }
    public function model(array $row)
    {

        // PLANNER

       

        // =============================
        // KACS
        // =============================

      

        // =============================
        // SENTUL
        // =============================

        $lama_digudang_2 = null;
        $sla_loading_2 = null;

        if (
            !empty($row['tanggal_tiba_gudang_2']) &&
            !empty($row['tanggal_keluar_gudang_2'])
        ) {

            $in2  = strtotime($row['tanggal_tiba_gudang_2']);
            $out2 = strtotime($row['tanggal_keluar_gudang_2']);

            $jam2 = ($out2 - $in2) / 3600;

            $lama_digudang_2 = round($jam2, 1) . ' Jam';

            if ($jam2 <= 24) {
                $sla_loading_2 = 'H+0';
            } elseif ($jam2 <= 48) {
                $sla_loading_2 = 'H+1';
            } else {
                $sla_loading_2 = 'H>1';
            }
        }

        // =============================
        // CCIE
        // =============================

        $lama_digudang_3 = null;
        $sla_loading_3 = null;

        if (
            !empty($row['tanggal_tiba_gudang_3']) &&
            !empty($row['tanggal_keluar_gudang_3'])
        ) {

            $in3  = strtotime($row['tanggal_tiba_gudang_3']);
            $out3 = strtotime($row['tanggal_keluar_gudang_3']);

            $jam3 = ($out3 - $in3) / 3600;

            $lama_digudang_3 = round($jam3, 1) . ' Jam';

            if ($jam3 <= 24) {
                $sla_loading_3 = 'H+0';
            } elseif ($jam3 <= 48) {
                $sla_loading_3 = 'H+1';
            } else {
                $sla_loading_3 = 'H>1';
            }
        }
        // MONITORING
        $totalDoCar = $this->cleanNumber($row['total_do_qty_car'] ?? $row['total_do_qty_car'] ?? null);
        $addtText4 = $this->cleanText($row['addt_text'] ?? null);

        // ================= DATE =================
        $rencanaKirim = $this->convertDate($row['rencana_kirim'] ?? null);
        $tanggalKeluarGudang = $this->convertDate($row['tanggal_keluar_gudang'] ?? null);
        $tanggalTibaAktual = $this->convertDate($row['tanggal_tiba'] ?? null);
        $tanggalNaikLogistik = $this->convertDate($row['tanggal_naik_logistik'] ?? null);
        $tanggalDptUnit = $this->convertDate($row['tanggal_dpt_unit'] ?? null);
        $planningLoading = $this->convertDate($row['planning_loading'] ?? null);
        $tanggalTibaGudang = $this->convertDate($row['tanggal_tiba_di_gudang'] ?? null);
        $tanggalBongkar = $this->convertDate($row['tanggal_bongkar'] ?? null);
        $total_do_qty_car = $this->cleanNumber($row['total_do_qty_car'] ?? null);

        // =====================================================
// SLA DAPAT MOBIL
// tanggal_dpt_unit -> tanggal_tiba_gudang
// =====================================================

$lamaWaktuPencarian = null;
$slaDapatMobil = null;

if ($tanggalDptUnit && $tanggalTibaGudang) {

    $selisihCariMobil = (int) date_diff(
        date_create($tanggalDptUnit),
        date_create($tanggalTibaGudang)
    )->format('%a');

    $lamaWaktuPencarian = $selisihCariMobil . ' Hari';

    $slaDapatMobil = ($selisihCariMobil == 0)
        ? 'On Time'
        : 'Delay';
}

// =====================================================
// SLA LOADING
// tanggal_tiba_gudang -> tanggal_keluar_gudang
// =====================================================

$lamaDigudang = null;
$slaLoading = null;

if ($tanggalTibaGudang && $tanggalKeluarGudang) {

    $selisihGudang = (int) date_diff(
        date_create($tanggalTibaGudang),
        date_create($tanggalKeluarGudang)
    )->format('%a');

    $lamaDigudang = $selisihGudang . ' Hari';

    $slaLoading = ($selisihGudang == 0)
        ? 'On Time'
        : 'Delay';
}

        $act_pgi_date = isset($row['act_pgi_date']) && is_numeric($row['act_pgi_date'])
            ? Date::excelToDateTimeObject($row['act_pgi_date'])->format('Y-m-d')
            : null;
        $custGrp5 = $this->cleanText($row['cust_grp_5_desc'] ?? null);
        $createdBy = $this->cleanText($row['created_by'] ?? null);
        $custGrp3 = $this->cleanText($row['cust_grp_3_desc'] ?? null);
        $shipNo = $this->cleanText($row['ship_no'] ?? null);
        $route = $this->cleanText($row['route'] ?? null);
        $mobil = $this->cleanText($row['mobil'] ?? null);
        $ekpedisi = $this->cleanText($row['ekpedisi'] ?? null);
  
        $biayaKirim = $harga->biaya_kirim ?? 0;
        $pulau = $this->cleanText($row['pulau'] ?? null);
        $viaKirim = $this->cleanText($row['via_kirim'] ?? $row['via'] ?? null); // fleksibel jika nama header di excel hanya 'via'
        $custDesc = $this->cleanText($row['cust_desc'] ?? null);
        // $addtText4 = $this->cleanText($row['addt_text_4'] ?? null);
        $serviceAgent = $this->cleanText($row['service_agent'] ?? null);
        // $totalDoCar = $this->cleanNumber($row['total_do_qty_car'] ?? null);
        $biayaKirim = $harga->biaya_kirim ?? 0;

        $route      = $harga->route ?? null;

        $mobil      = $harga->mobil ?? null;
        $tujuan = $this->cleanText($row['tujuan'] ?? null);
        // $tujuanKey = strtolower(trim($tujuan));
        $tujuanKey = preg_replace('/\s+/', ' ', trim(strtolower($tujuan)));

        $customerData = self::$customerMap[$tujuanKey] ?? null;
        $picData = self::$picMap[$tujuanKey] ?? null;

        $distChannel = $customerData->dist_channel ?? null;
        $area = $customerData->area ?? null;
        $picMonitoring = $picData->pic_monitoring ?? null;
        // ================= LEAD TIME =================
        // $leadTime = (int) filter_var($row['transport_lead_time'] ?? 0, FILTER_SANITIZE_NUMBER_INT);
        $leadTime = is_numeric($row['transport_lead_time'] ?? null)
            ? (int) $row['transport_lead_time']
            : 0;

      

        $biayaKirim = $harga->biaya_kirim ?? 0;

        // ================= ESTIMASI TIBA =================
        $tanggalTibaEstimasi = null;
        if ($tanggalKeluarGudang && $leadTime >= 0) {
            $tanggalTibaEstimasi = date('Y-m-d', strtotime($tanggalKeluarGudang . " +{$leadTime} days"));
        }


        // ================= LAMA PERJALANAN =================
        $lamaPerjalanan = null;
        if ($tanggalKeluarGudang && $tanggalTibaAktual) {
            $lamaPerjalanan = date_diff(
                date_create($tanggalKeluarGudang),
                date_create($tanggalTibaAktual)
            )->format('%a');
        }

        // ================= SLA TIBA =================
        $slaTiba = null;
        if ($tanggalTibaAktual && $tanggalTibaEstimasi) {
            $slaTiba = ($tanggalTibaAktual <= $tanggalTibaEstimasi) ? 'On Time' : 'Delay';
        }

        // =====================================================
        // 🔥 SLA GUDANG
        // =====================================================

      

        // ================= NORMALISASI KETERSEDIAAN UNIT =================
        $ketersediaanUnit = $this->cleanText($row['ketersediaan_unit'] ?? null);

        if ($ketersediaanUnit === null || $ketersediaanUnit === '' || $ketersediaanUnit === '-') {
            $ketersediaanUnit = 'BELUM DAPAT';
        } else {
            $ketersediaanUnit = strtoupper(trim($ketersediaanUnit));
        }

        // variasi data
        if (in_array($ketersediaanUnit, [
            'SUDAH DAPAT MOBIL',
            'READY MOBIL',
            'READY'
        ])) {
            $ketersediaanUnit = 'SUDAH DAPAT';
        }

        if (in_array($ketersediaanUnit, [
            'BELUM DAPAT MOBIL',
            'PENDING'
        ])) {
            $ketersediaanUnit = 'BELUM DAPAT';
        }
        $route = $this->cleanText($row['route'] ?? null);
$mobil = $this->cleanText($row['mobil'] ?? null);

        // $tujuan = $this->cleanText($row['tujuan'] ?? null);

// CREATE DATE
$create_tgl = date('Y-m-d H:i:s');
        // dd($row);
        return new LogistikPengiriman([

            // ================= BASIC =================
            'no' => $this->cleanText($row['no'] ?? null),
            'create_tgl' => $create_tgl,
            // 'transport_lead_time' => $this->cleanText($row['transport_lead_time'] ?? null),
            'transport_lead_time' => $this->cleanNumber($row['transport_lead_time'] ?? null),
            'planner' => $this->cleanText($row['planner'] ?? null),
            'no_shipment' => $this->cleanText($row['no_shipment'] ?? null),
            'tujuan' => $tujuan,
            'dist_channel' => $distChannel,
            'area' => $area,
            'ketersediaan_unit' => $ketersediaanUnit,
           
'mobil' => $mobil,
            
            // 'route' => $route, INI yg akan diambil dari database route,biaya_kirim, dan mobil
            'pulau' => $pulau,
            'route' => $this->cleanText($row['route'] ?? null),
            'via_kirim' => $viaKirim,

            'mobil' => $mobil,
            'perubahan_mobil' => $this->cleanText($row['perubahan_mobil'] ?? null),

            'cr' => $this->cleanText($row['cr'] ?? null),
            'kategori_ekspedisi' => $this->cleanText($row['kategori_ekspedisi'] ?? null),
            'ekpedisi' => $ekpedisi,
            'nama_driver' => $this->cleanText($row['nama_driver'] ?? null),
            'no_pol' => $this->cleanText($row['no_pol'] ?? ($row['nopol'] ?? null)),

            'status_pengiriman' => $this->cleanText($row['status'] ?? null),
            // 'status_akhir' => $this->cleanText($row['status'] ?? null),

            // ================= NUMBER =================
            'nilai_muatan' => $this->cleanNumber($row['nilai_muatan_rp'] ?? null),
            'biaya_kirim' => $this->cleanNumber($row['biaya_kirim_rp'] ?? null),
            // 'total_do_qty_car' => $totalDoCar,

            // ================= DATE =================
            'tanggal_naik_logistik' => $tanggalNaikLogistik,
            'rencana_kirim' => $rencanaKirim,
            'tanggal_dpt_unit' => $tanggalDptUnit,
            'planning_loading' => $planningLoading,
            'tanggal_tiba_gudang' => $tanggalTibaGudang,
            'tanggal_keluar_gudang' => $tanggalKeluarGudang,
            'tanggal_tiba' => $tanggalTibaAktual,
            'tanggal_bongkar' => $tanggalBongkar,

            // ================= AUTO =================
            'tanggal_tiba_estimasi' => $tanggalTibaEstimasi,
            'lama_perjalanan' => $lamaPerjalanan,
            'sla_tiba' => $slaTiba,

            // ================= GUDANG pERTAMA Berhasil =================
        'lama_waktu_pencarian' => $lamaWaktuPencarian,
'sla_dapat_mobil'      => $slaDapatMobil,

'lama_digudang'        => $lamaDigudang,
'sla_loading'          => $slaLoading,

            // GudangBaru

            // 'lama_waktu_pencarian' => $lama_waktu_pencarian,
            // 'sla_dapat_mobil'      => $sla_dapat_mobil,

            // 'lama_digudang'        => $lama_digudang,
            // 'sla_loading'          => $sla_loading,

            // 'lama_digudang_2'      => $lama_digudang_2,
            // 'sla_loading_2'        => $sla_loading_2,

            // 'lama_digudang_3'      => $lama_digudang_3,
            // 'sla_loading_3'        => $sla_loading_3,

            // ================= OTHER =================
            'status' => $this->cleanText($row['status'] ?? null),
            'keterangan' => $this->cleanText($row['keterangan'] ?? null),

            'pic_monitoring' => $picMonitoring,
            'status_kendaraan' => $this->cleanText($row['status_kendaraan'] ?? null),
            'monitoring_alert' => $this->cleanText($row['monitoring_alert'] ?? null),
            'action_required' => $this->cleanText($row['action_required'] ?? null),

            'act_urutan_bongkar' => $row['ac_turutan_bongkar'] ?? null,
            'overstay_days' => $this->cleanText($row['overstay_days'] ?? null),

            'reason_tiba' => $this->cleanText($row['reason_waktu_tiba'] ?? null),
            'reason_bongkar' => $this->cleanText($row['reason_waktu_bongkar'] ?? null),

            'act_pgi_date' => $act_pgi_date,
            'cust_grp_5_desc' => $custGrp5,
            'created_by'   => $row['created_by'] ?? null,
            'cust_grp_3_desc' => $custGrp3,
            'ship_no' => $shipNo,
            'cust_desc' => $custDesc,
            'addt_text_4' => $addtText4,
            'service_agent' => $serviceAgent,
            'total_do_qty_car' => $totalDoCar,

            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }



    // ================= HELPERS =================

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

    private function convertDate($value)
    {
        if (!$value || $value == '-' || $value == '#VALUE!') return null;

        if (is_numeric($value)) {
            return Date::excelToDateTimeObject($value)->format('Y-m-d');
        }

        $timestamp = strtotime(str_replace('/', '-', trim($value)));

        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }
}
