<?php

namespace App\Imports;

use Illuminate\Support\Facades\DB;
use App\Models\LogistikPengiriman;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class LogistikImport implements ToModel, WithHeadingRow
{

    public function __construct()
    {
        if (self::$customerMap === null) {
            self::$customerMap = DB::table('tujuanfilterr')
                ->select(
        'name_customer_1',
        'dist_channel',
        'pic_monitoring'
    )
    ->get()
    ->keyBy(fn($row) => strtolower(trim($row->name_customer_1)));
        }
    }
    public function model(array $row)
    {
        // ================= DATE =================
        $rencanaKirim = $this->convertDate($row['rencana_kirim'] ?? null);
        $tanggalKeluarGudang = $this->convertDate($row['tanggal_keluar_gudang'] ?? null);
        $tanggalTibaAktual = $this->convertDate($row['tanggal_tiba'] ?? null);
        $tanggalNaikLogistik = $this->convertDate($row['tanggal_naik_logistik'] ?? null);
        $tanggalDptUnit = $this->convertDate($row['tanggal_dpt_unit'] ?? null);
        $planningLoading = $this->convertDate($row['planning_loading'] ?? null);
        $tanggalTibaGudang = $this->convertDate($row['tanggal_tiba_di_gudang'] ?? null);
        $tanggalBongkar = $this->convertDate($row['tanggal_bongkar'] ?? null);
        $total_do_qty_car = $this->convertDate($row['total_do_qty_car'] ?? null);
        $tujuanKey = strtolower(trim($this->cleanText($row['tujuan'] ?? '')));

        $customerData = self::$customerMap[$tujuanKey] ?? null;

        $distChannel = $customerData->dist_channel ?? null;
        $picMonitoring = $customerData->pic_monitoring ?? null;
        // ================= LEAD TIME =================
        $leadTime = (int) filter_var($row['transport_lead_time'] ?? 0, FILTER_SANITIZE_NUMBER_INT);

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

        $lamaDigudang = null;
        if ($tanggalTibaGudang && $tanggalKeluarGudang) {
            $lamaDigudang = date_diff(
                date_create($tanggalTibaGudang),
                date_create($tanggalKeluarGudang)
            )->format('%a');
        }

        $slaLoading = null;
        if ($tanggalTibaGudang && $tanggalKeluarGudang) {
            $diff = (int) date_diff(
                date_create($tanggalTibaGudang),
                date_create($tanggalKeluarGudang)
            )->format('%a');

            if ($diff == 0) $slaLoading = 'H+0';
            elseif ($diff == 1) $slaLoading = 'H+1';
            elseif ($diff == 2) $slaLoading = 'H+2';
            else $slaLoading = 'H>2';
        }

        // ================= LAMA WAKTU CARI MOBIL =================
        $lamaWaktuPencarian = null;
        if ($tanggalTibaGudang && $tanggalDptUnit) {
            $lamaWaktuPencarian = date_diff(
                date_create($tanggalTibaGudang),
                date_create($tanggalDptUnit)
            )->format('%a');
        }

        // ================= SLA DAPAT MOBIL =================
        $slaDapatMobil = null;
        if ($lamaWaktuPencarian !== null) {
            if ($lamaWaktuPencarian == 0) $slaDapatMobil = 'H+0';
            elseif ($lamaWaktuPencarian == 1) $slaDapatMobil = 'H+1';
            elseif ($lamaWaktuPencarian == 2) $slaDapatMobil = 'H+2';
            else $slaDapatMobil = 'H>2';
        }

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

        $tujuan = $this->cleanText($row['tujuan'] ?? null);


        $distChannel = DB::table('tujuanfilterr')
            ->whereRaw(
                'LOWER(TRIM(name_customer_1)) = ?',
                [strtolower($tujuan)]
            )
            ->value('dist_channel');
        return new LogistikPengiriman([

            // ================= BASIC =================
            'no' => $this->cleanText($row['no'] ?? null),
            'transport_lead_time' => $this->cleanText($row['transport_lead_time'] ?? null),
            'planner' => $this->cleanText($row['planner'] ?? null),
            'no_shipment' => $this->cleanText($row['no_shipment'] ?? null),
            'tujuan' => $tujuan,
            'dist_channel' => $distChannel,
            'area' => $this->cleanText($row['area'] ?? null),
            'ketersediaan_unit' => $ketersediaanUnit,

            'mobil' => $this->cleanText($row['mobil'] ?? null),
            'perubahan_mobil' => $this->cleanText($row['perubahan_mobil'] ?? null),

            'cr' => $this->cleanText($row['cr'] ?? null),
            'kategori_ekspedisi' => $this->cleanText($row['kategori_ekspedisi'] ?? null),
            'ekpedisi' => $this->cleanText($row['ekpedisi'] ?? null),
            'nama_driver' => $this->cleanText($row['nama_driver'] ?? null),
            'no_pol' => $this->cleanText($row['no_pol'] ?? ($row['nopol'] ?? null)),

            'status_pengiriman' => $this->cleanText($row['status'] ?? null),
            'status_akhir' => $this->cleanText($row['status'] ?? null),

            // ================= NUMBER =================
            'nilai_muatan' => $this->cleanNumber($row['nilai_muatan_rp'] ?? null),
            'biaya_kirim' => $this->cleanNumber($row['biaya_kirim_rp'] ?? null),
            'total_do_qty_car' => $this->cleanNumber($row['total_do_qty_car'] ?? null),

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

            // ================= GUDANG =================
            'lama_digudang' => $lamaDigudang,
            'sla_loading' => $slaLoading,
            'lama_waktu_pencarian' => $lamaWaktuPencarian,
            'sla_dapat_mobil' => $slaDapatMobil,

            // ================= OTHER =================
            'status' => $this->cleanText($row['status'] ?? null),
            'keterangan' => $this->cleanText($row['keterangan'] ?? null),

            'pic_monitoring' => $picMonitoring,
            'status_kendaraan' => $this->cleanText($row['status_kendaraan'] ?? null),
            'monitoring_alert' => $this->cleanText($row['monitoring_alert'] ?? null),
            'action_required' => $this->cleanText($row['action_required'] ?? null),

            'act_urutan_bongkar' => $this->cleanText($row['act_urutan_bongkar'] ?? null),
            'overstay_days' => $this->cleanText($row['overstay_days'] ?? null),

            'reason_tiba' => $this->cleanText($row['reason_waktu_tiba'] ?? null),
            'reason_bongkar' => $this->cleanText($row['reason_waktu_bongkar'] ?? null),

            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private static $customerMap = null;

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
