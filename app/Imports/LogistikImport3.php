<?php

namespace App\Imports;

use App\Models\LogistikPengiriman;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class LogistikImport implements ToModel, WithHeadingRow
{
    private static $customerMap = null;
    private static $picMap = null;
    private static $hargaMap = null;

    public function __construct()
    {
        // ================= CUSTOMER MAP =================
        if (self::$customerMap === null) {
            self::$customerMap = DB::table('tujuanfilterr')
                ->select('name_customer_1', 'dist_channel', 'area')
                ->get()
                ->mapWithKeys(function ($row) {
                    return [
                        $this->norm($row->name_customer_1) => [
                            'dist_channel' => $row->dist_channel,
                            'area' => $row->area,
                        ]
                    ];
                });
        }

        // ================= PIC MAP =================
        if (self::$picMap === null) {
            self::$picMap = DB::table('pic_monitoring')
                ->select('tujuan', 'pic_monitoring')
                ->get()
                ->mapWithKeys(function ($row) {
                    return [
                        $this->norm($row->tujuan) => $row->pic_monitoring
                    ];
                });
        }

        // ================= HARGA MAP =================
        if (self::$hargaMap === null) {
            self::$hargaMap = DB::table('databasehargacsv')
                ->select('ekpedisi', 'route', 'mobil', 'biaya_kirim')
                ->get()
                ->mapWithKeys(function ($row) {

                    $key = $this->buildHargaKey(
                        $row->ekpedisi,
                        $row->route,
                        $row->mobil
                    );

                    return [
                        $key => [
                            'biaya_kirim' => $row->biaya_kirim,
                            'mobil' => $row->mobil,
                            'route' => $row->route,
                        ]
                    ];
                });
        }
    }

    public function model(array $row)
    {
        // ================= CLEAN INPUT =================
        $ekpedisiRaw = $this->cleanText($row['ekpedisi'] ?? null);
        $routeRaw    = $this->cleanText($row['route'] ?? null);
        $mobilRaw    = $this->cleanText($row['mobil'] ?? null);
        $tujuanRaw   = $this->cleanText($row['tujuan'] ?? null);

        // ================= NORMALIZE =================
        $tujuanKey   = $this->norm($tujuanRaw);
        $ekpedisiKey = $this->norm($ekpedisiRaw);
        $routeKey    = $this->norm($routeRaw);
        $mobilKey    = $this->norm($mobilRaw);

        // ================= CUSTOMER =================
        $customer = self::$customerMap[$tujuanKey] ?? null;
        $pic      = self::$picMap[$tujuanKey] ?? null;

        $distChannel   = $customer['dist_channel'] ?? null;
        $area          = $customer['area'] ?? null;
        $picMonitoring = $pic ?? null;

        // ================= HARGA =================
        $keyHarga = $this->buildHargaKey($ekpedisiRaw, $routeRaw, $mobilRaw);
        $harga = self::$hargaMap[$keyHarga] ?? null;

        $biayaKirim = $harga['biaya_kirim'] ?? 0;
        $finalMobil = $harga['mobil'] ?? $mobilRaw;
        $finalRoute = $harga['route'] ?? $routeRaw;

        // ================= DATE =================
        $tglKeluar = $this->convertDate($row['tanggal_keluar_gudang'] ?? null);
        $tglTiba   = $this->convertDate($row['tanggal_tiba'] ?? null);
        $tglBongkar = $this->convertDate($row['tanggal_bongkar'] ?? null);

        $tglNaik   = $this->convertDate($row['tanggal_naik_logistik'] ?? null);
        $tglDpt    = $this->convertDate($row['tanggal_dpt_unit'] ?? null);
        $planLoad  = $this->convertDate($row['planning_loading'] ?? null);
        $tglGudang = $this->convertDate($row['tanggal_tiba_di_gudang'] ?? null);

        // ================= LEAD TIME =================
        $leadTime = (int) ($row['transport_lead_time'] ?? 0);

        // ================= ESTIMASI =================
        $estimasi = $tglKeluar
            ? date('Y-m-d', strtotime($tglKeluar . " +{$leadTime} days"))
            : null;

        // ================= SLA =================
        $slaTiba = null;
        if ($tglTiba && $estimasi) {
            $slaTiba = ($tglTiba <= $estimasi) ? 'On Time' : 'Delay';
        }

        return new LogistikPengiriman([
            'no' => $this->cleanText($row['no'] ?? null),
            'planner' => $this->cleanText($row['planner'] ?? null),
            'no_shipment' => $this->cleanText($row['no_shipment'] ?? null),

            'tujuan' => $tujuanRaw,
            'dist_channel' => $distChannel,
            'area' => $area,

            'ekpedisi' => $ekpedisiRaw,
            'route' => $finalRoute,
            'mobil' => $finalMobil,

            'biaya_kirim' => $biayaKirim,

            'ketersediaan_unit' => $this->cleanText($row['ketersediaan_unit'] ?? null),

            'tanggal_naik_logistik' => $tglNaik,
            'tanggal_dpt_unit' => $tglDpt,
            'planning_loading' => $planLoad,
            'tanggal_tiba_gudang' => $tglGudang,

            'tanggal_keluar_gudang' => $tglKeluar,
            'tanggal_tiba' => $tglTiba,
            'tanggal_bongkar' => $tglBongkar,

            'transport_lead_time' => $leadTime,
            'tanggal_tiba_estimasi' => $estimasi,
            'sla_tiba' => $slaTiba,

            'pic_monitoring' => $picMonitoring,

            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ================= KEY BUILDER =================
    private function buildHargaKey($ekpedisi, $route, $mobil)
    {
        return $this->norm($ekpedisi) . '|' .
               $this->norm($route) . '|' .
               $this->norm($mobil);
    }

    // ================= NORMALIZER =================
    private function norm($value)
    {
        $value = (string) $value;

        $value = preg_replace('/\x{00A0}/u', ' ', $value);
        $value = str_replace(['–', '—'], '-', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return strtolower(trim($value));
    }

    // ================= CLEAN TEXT =================
    private function cleanText($value)
    {
        if ($value === null) return null;
        if (is_array($value)) return null;

        $value = trim((string) $value);

        if ($value === '' || $value === '-' || $value === '#VALUE!') {
            return null;
        }

        if (str_contains($value, '=')) {
            return null;
        }

        return $value;
    }

    // ================= DATE CONVERTER =================
    private function convertDate($value)
    {
        if (!$value || $value === '-' || $value === '#VALUE!') return null;

        if (is_numeric($value)) {
            return Date::excelToDateTimeObject($value)->format('Y-m-d');
        }

        $timestamp = strtotime(str_replace('/', '-', trim($value)));

        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }
}