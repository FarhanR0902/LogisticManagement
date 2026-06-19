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

    public function __construct()
    {
        if (self::$customerMap === null) {
            self::$customerMap = DB::table('tujuanfilterr')
                ->select('name_customer_1', 'dist_channel', 'area')
                ->get()
                ->keyBy(fn($row) => strtolower(trim($row->name_customer_1)));
        }
    }

    public function model(array $row)
    {
        // ================= NORMAL TEXT =================
        $clean = fn($v) => (!$v || $v === '-' || $v === '#VALUE!') ? null : trim($v);

        // ================= NUMBER =================
        $number = function ($v) {
            if ($v === null || $v === '' || $v === '-') return 0;
            return is_numeric($v) ? (float) $v : (float) str_replace([',', 'Rp', ' '], '', $v);
        };

        // ================= DATE EXCEL =================
        $date = function ($v) {
            if (!$v || $v === '-') return null;

            if (is_numeric($v)) {
                return Date::excelToDateTimeObject($v)->format('Y-m-d');
            }

            $t = strtotime(str_replace('/', '-', $v));
            return $t ? date('Y-m-d', $t) : null;
        };

        // ================= INPUT EXCEL =================
        $act_pgi_date        = $date($row['act_pgi_date'] ?? null);
        $cust_grp_5_desc     = $clean($row['cust_grp_5_desc'] ?? null);
        $created_by          = $clean($row['created_by'] ?? null);
        $cust_grp_3_desc     = $clean($row['cust_grp_3_desc'] ?? null);
        $ship_no             = $clean($row['ship_no'] ?? null);
        $cust_desc           = $clean($row['cust_desc'] ?? null);
        $addt_text_4         = $clean($row['addt_text_4'] ?? null);
        $service_agent       = $clean($row['service_agent'] ?? null);
        $remarks             = $clean($row['remarks'] ?? null);

        $urutan_bongkar      = $number($row['urutan_bongkar'] ?? null);
        $total_do_qty_car    = $number($row['total_do_qty_car'] ?? null);

        $tanggal_tiba        = $date($row['tanggal_tiba'] ?? null);
        $tanggal_bongkar     = $date($row['tanggal_bongkar'] ?? null);

        $akurasi_tiba        = $clean($row['akurasi_waktu_tiba'] ?? null);
        $akurasi_bongkar     = $clean($row['akurasi_waktu_bongkar'] ?? null);

        $status              = $clean($row['status'] ?? null);

        // ================= TUJUAN MAP (OPTIONAL) =================
        $tujuan = $clean($row['cust_desc'] ?? null);

        $key = strtolower(trim($tujuan));
        $customer = self::$customerMap[$key] ?? null;

        $dist_channel = $customer->dist_channel ?? null;
        $area         = $customer->area ?? null;

        return new LogistikPengiriman([
            // ================= CORE =================
            'act_pgi_date'        => $act_pgi_date,
            'cust_grp_5_desc'     => $cust_grp_5_desc,
            'created_by'          => $created_by,
            'cust_grp_3_desc'     => $cust_grp_3_desc,
            'ship_no'             => $ship_no,
            'cust_desc'           => $cust_desc,
            'addt_text_4'         => $addt_text_4,
            'service_agent'       => $service_agent,
            'total_do_qty_car'    => $total_do_qty_car,

            // ================= LOGISTIC =================
            'urutan_bongkar'      => $urutan_bongkar,
            'tanggal_tiba'        => $tanggal_tiba,
            'tanggal_bongkar'     => $tanggal_bongkar,

            'akurasi_waktu_tiba'  => $akurasi_tiba,
            'akurasi_waktu_bongkar'=> $akurasi_bongkar,

            'status'              => $status,
            'remarks'             => $remarks,

            // ================= OPTIONAL MAP =================
            'dist_channel'        => $dist_channel,
            'area'                => $area,

            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
    }
}