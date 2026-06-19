<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManagerController extends Controller
{

// public function dashboard()
// {
//     // ================= TOTAL =================
//     $total_data = DB::table('logistik_pengiriman')->count();

//     // ================= GUDANG =================
//     $gudang_ontime = DB::table('logistik_pengiriman')
//         ->where('status', 'like', '%On%Time%')
//         ->count();

//     $gudang_delay = DB::table('logistik_pengiriman')
//         ->where('status', 'Delay')
//         ->count();

//     // ================= CUSTOMER =================
//     $customer_ontime = DB::table('logistik_pengiriman')
//         ->whereIn('status_akhir', ['On Time','OnTime','ONTIME'])
//         ->count();

//     $customer_delay = DB::table('logistik_pengiriman')
//         ->whereIn('status_akhir', ['Delay','Critical Delay'])
//         ->count();

//     // ================= BONGKAR =================
//     $bongkar_ontime = DB::table('logistik_pengiriman')
//         ->where(function ($q) {
//             $q->where('sla_bongkar', 'H+0')
//               ->orWhere('sla_bongkar', 'On Time')
//               ->orWhere('overstay_days', '<=', 0);
//         })
//         ->count();

//     $bongkar_delay = DB::table('logistik_pengiriman')
//         ->where(function ($q) {
//             $q->where('sla_bongkar', 'Delay')
//               ->orWhere('sla_bongkar', 'Critical Delay')
//               ->orWhere('overstay_days', '>', 0);
//         })
//         ->count();

//     // ================= AREA =================
//     // $summary_area = DB::table('logistik_pengiriman')
//     //     ->select(
//     //         'area',
//     //         DB::raw('COUNT(*) as total_shipment'),
//     //         DB::raw('SUM(COALESCE(biaya_kirim,0)) as total_biaya'),
//     //         DB::raw('SUM(COALESCE(nilai_muatan,0)) as total_muatan')
//     //     )
//     //     ->groupBy('area')
//     //     ->orderByDesc('total_shipment')
//     //     ->get();


//     $summary_area = DB::table('logistik_pengiriman')
//     ->select(
//         'area',
//         DB::raw('COUNT(*) as total_shipment'),
        
//         // FORCE numeric conversion
//         DB::raw('SUM(
//             CASE 
//                 WHEN biaya_kirim IS NULL THEN 0
//                 ELSE CAST(REPLACE(REPLACE(biaya_kirim, ".", ""), ",", "") AS UNSIGNED)
//             END
//         ) as total_biaya'),

//         DB::raw('SUM(
//             CASE 
//                 WHEN nilai_muatan IS NULL THEN 0
//                 ELSE CAST(REPLACE(REPLACE(nilai_muatan, ".", ""), ",", "") AS UNSIGNED)
//             END
//         ) as total_muatan')
//     )
//     ->groupBy('area')
//     ->orderByDesc('total_shipment')
//     ->get();
//     // ================= TUJUAN =================
//     // $summary_tujuan = DB::table('logistik_pengiriman')
//     //     ->select(
//     //         'tujuan',
//     //         DB::raw('COUNT(*) as total_shipment')
//     //     )
//     //     ->groupBy('tujuan')
//     //     ->orderByDesc('total_shipment')
//     //     ->get();

//     $summary_tujuan = DB::table('logistik_pengiriman')
//     ->select(
//         'tujuan',
//         DB::raw('COUNT(*) as total_shipment'),
//         DB::raw('COALESCE(SUM(biaya_kirim),0) as total_biaya'),
//         DB::raw('COALESCE(SUM(nilai_muatan),0) as total_muatan')
//     )
//     ->whereNotNull('tujuan')
//     ->groupBy('tujuan')
//     ->orderByDesc('total_shipment')
//     ->get();

//     // ================= HEATMAP =================
//     $heatmap = DB::table('logistik_pengiriman')
//         ->select('area', DB::raw('COUNT(*) as total'))
//         ->whereNotNull('area')
//         ->groupBy('area')
//         ->get();

//     // ================= TOTAL FINANCE =================
//     // $totalNilaiMuatan = DB::table('logistik_pengiriman')
//     //     ->sum('nilai_muatan');

//     // $totalBiayaKirim = DB::table('logistik_pengiriman')
//     //     ->sum('biaya_kirim');

//     $totalNilaiMuatan = DB::table('logistik_pengiriman')
//     ->sum('nilai_muatan');

// $totalBiayaKirim = DB::table('logistik_pengiriman')
//     ->sum('biaya_kirim');

//     // ================= PLANNER (OPTIONAL FIX) =================
//     $planner_ontime = DB::table('logistik_pengiriman')
//         ->whereRaw("LOWER(status) LIKE '%on%'")
//         ->count();

//     $planner_delay = DB::table('logistik_pengiriman')
//         ->whereRaw("LOWER(status) LIKE '%delay%'")
//         ->count();

//     $planner_armada = DB::table('logistik_pengiriman')
//         ->whereNotNull('mobil')
//         ->count();

//     $planner_belum_armada = DB::table('logistik_pengiriman')
//         ->whereNull('mobil')
//         ->count();

//         $summary_monitoring = [
//     'tiba_ontime' => $total_data > 0 ? ($customer_ontime / $total_data) * 100 : 0,
//     'tiba_delay' => $total_data > 0 ? ($customer_delay / $total_data) * 100 : 0,
//     'bongkar_ontime' => $total_data > 0 ? ($bongkar_ontime / $total_data) * 100 : 0,
//     'bongkar_delay' => $total_data > 0 ? ($bongkar_delay / $total_data) * 100 : 0,
// ];

//     // ================= RATIO =================
//     $total_status = $planner_ontime + $planner_delay;

//     $ontime_rate = $total_status > 0 ? ($planner_ontime / $total_status) * 100 : 0;
//     $delay_rate  = $total_status > 0 ? ($planner_delay / $total_status) * 100 : 0;

//     $total_armada = $planner_armada + $planner_belum_armada;

//     $armada_rate  = $total_armada > 0 ? ($planner_armada / $total_armada) * 100 : 0;
//     $pending_rate = $total_armada > 0 ? ($planner_belum_armada / $total_armada) * 100 : 0;

//     // ================= CHART (OPTIONAL SAFE DEFAULT) =================
//     $bulan = [];
//     $muatan = [];
//     $biaya = [];

//     $label = [];
//     $value = [];

//     // ================= RETURN VIEW CLEAN =================
//  return view('manager.dashboard', compact(
//     'total_data',
//     'gudang_ontime',
//     'gudang_delay',
//     'customer_ontime',
//     'customer_delay',
//     'bongkar_ontime',
//     'bongkar_delay',
//     'summary_area',
//     'summary_tujuan',
//     'heatmap',
//     'totalNilaiMuatan',
//     'totalBiayaKirim',
//     'summary_monitoring',
//     'planner_ontime',
//     'planner_delay',
//     'planner_armada',
//     'planner_belum_armada',
//     'ontime_rate',
//     'delay_rate',
//     'armada_rate',
//     'pending_rate',
//     'bulan',
//     'muatan',
//     'biaya',
//     'label',
//     'value'
// ));


public function dashboard()
{
    // ================= FILTER =================
    $date = request('date');
    $month = request('month');
    $year = request('year');
    $area = request('area');


    $base = DB::table('logistik_pengiriman');

   if ($date) {
    $base->whereDate('tanggal_naik_logistik', $date);
}

if ($month) {
    $base->whereMonth('tanggal_naik_logistik', substr($month, 5, 2))
         ->whereYear('tanggal_naik_logistik', substr($month, 0, 4));
}

if ($year) {
    $base->whereYear('tanggal_naik_logistik', $year);
}
    
    if ($area) {

    $base->where('area', $area);
}

    // ================= TOTAL =================
    $total_data = (clone $base)->count();

    // ================= GUDANG =================
   $gudang_ontime = (clone $base)
    ->where(function($q){
        $q->where('sla_loading', 'H+0')
          ->orWhere('status', 'On Time')
          ->orWhere('status', 'ONTIME');
    })
    ->count();

$gudang_delay = (clone $base)
    ->where(function($q){
        $q->where('sla_loading', 'H+1')
          ->orWhere('sla_loading', 'H+2')
          ->orWhere('sla_loading', 'H>2')
          ->orWhere('status', 'Delay')
          ->orWhere('status', 'DELAY');
    })
    ->count();

    // ================= CUSTOMER =================
  $customer_ontime = (clone $base)
    ->whereNotNull('tanggal_tiba')
    ->whereRaw("
        DATEDIFF(
            tanggal_tiba,
            DATE_ADD(rencana_kirim, INTERVAL transport_lead_time DAY)
        ) <= 0
    ")
    ->count();

$customer_delay = (clone $base)
    ->whereNotNull('tanggal_tiba')
    ->whereRaw("
        DATEDIFF(
            tanggal_tiba,
            DATE_ADD(rencana_kirim, INTERVAL transport_lead_time DAY)
        ) > 0
    ")
    ->count();

    // ================= BONGKAR (FIXED PAKE BASE) =================
    $bongkar_ontime = (clone $base)
    ->where(function($q){
        $q->where('sla_bongkar', 'H+0')
          ->orWhere('sla_bongkar', 'On Time')
          ->orWhere('sla_bongkar', 'ONTIME');
    })
    ->count();

$bongkar_delay = (clone $base)
    ->where(function($q){
        $q->where('sla_bongkar', 'H+1')
          ->orWhere('sla_bongkar', 'H+2')
          ->orWhere('sla_bongkar', 'H>2')
          ->orWhere('sla_bongkar', 'Delay')
          ->orWhere('sla_bongkar', 'Critical Delay');
    })
    ->count();
    // ================= FINANCE (FIXED FILTER) =================
    $totalNilaiMuatan = (clone $base)
        ->selectRaw("
            SUM(
                CASE 
                    WHEN nilai_muatan IS NULL THEN 0
                    ELSE CAST(REPLACE(REPLACE(nilai_muatan, '.', ''), ',', '') AS UNSIGNED)
                END
            ) as total
        ")
        ->value('total');

    $totalBiayaKirim = (clone $base)
        ->selectRaw("
            SUM(
                CASE 
                    WHEN biaya_kirim IS NULL THEN 0
                    ELSE CAST(REPLACE(REPLACE(biaya_kirim, '.', ''), ',', '') AS UNSIGNED)
                END
            ) as total
        ")
        ->value('total');

    // ================= AREA =================
    $summary_area = (clone $base)
        ->select(
            'area',
            DB::raw('COUNT(*) as total_shipment'),
            DB::raw("SUM(CASE WHEN biaya_kirim IS NULL THEN 0 ELSE CAST(REPLACE(REPLACE(biaya_kirim,'.',''),',','') AS UNSIGNED) END) as total_biaya"),
            DB::raw("SUM(CASE WHEN nilai_muatan IS NULL THEN 0 ELSE CAST(REPLACE(REPLACE(nilai_muatan,'.',''),',','') AS UNSIGNED) END) as total_muatan")
        )
        ->whereNotNull('area')
        ->groupBy('area')
        ->orderByDesc('total_shipment')
        ->get();

    // ================= TUJUAN =================
    $summary_tujuan = (clone $base)
        ->select(
            'tujuan',
            DB::raw('COUNT(*) as total_shipment'),
            DB::raw("SUM(CASE WHEN biaya_kirim IS NULL THEN 0 ELSE CAST(REPLACE(REPLACE(biaya_kirim,'.',''),',','') AS UNSIGNED) END) as total_biaya"),
            DB::raw("SUM(CASE WHEN nilai_muatan IS NULL THEN 0 ELSE CAST(REPLACE(REPLACE(nilai_muatan,'.',''),',','') AS UNSIGNED) END) as total_muatan")
        )
        ->whereNotNull('tujuan')
        ->groupBy('tujuan')
        ->orderByDesc('total_shipment')
        ->get();

    // ================= EKSPEDISI =================
    $ekspedisi = (clone $base)
        ->select('kategori_ekspedisi', DB::raw('COUNT(*) as total'))
        ->whereNotNull('kategori_ekspedisi')
        ->groupBy('kategori_ekspedisi')
        ->get();

    $label = $ekspedisi->pluck('kategori_ekspedisi');
    $value = $ekspedisi->pluck('total');

    // ================= PLANNER =================
    $planner_ontime = (clone $base)
        ->whereRaw("LOWER(status) LIKE '%on%'")
        ->count();

    $planner_delay = (clone $base)
        ->whereRaw("LOWER(status) LIKE '%delay%'")
        ->count();

        $armada = (clone $base)
    ->where('ketersediaan_unit', 'Sudah Dapat')
    ->count();

$belum_armada = (clone $base)
    ->where('ketersediaan_unit', 'Belum Dapat')
    ->count();

    $planner_armada = (clone $base)->whereNotNull('mobil')->count();
    $planner_belum_armada = (clone $base)->whereNull('mobil')->count();

    // ================= RATIO =================
    $total_status = $planner_ontime + $planner_delay;

    $ontime_rate = $total_status > 0 ? ($planner_ontime / $total_status) * 100 : 0;
    $delay_rate = $total_status > 0 ? ($planner_delay / $total_status) * 100 : 0;

    $total_armada = $planner_armada + $planner_belum_armada;

    $armada_rate = $total_armada > 0 ? ($planner_armada / $total_armada) * 100 : 0;
    $pending_rate = $total_armada > 0 ? ($planner_belum_armada / $total_armada) * 100 : 0;

    // ================= MONITORING =================
    $summary_monitoring = [
        'tiba_ontime' => $total_data > 0 ? ($customer_ontime / $total_data) * 100 : 0,
        'tiba_delay' => $total_data > 0 ? ($customer_delay / $total_data) * 100 : 0,
        'bongkar_ontime' => $total_data > 0 ? ($bongkar_ontime / $total_data) * 100 : 0,
        'bongkar_delay' => $total_data > 0 ? ($bongkar_delay / $total_data) * 100 : 0,
    ];

    $list_area = DB::table('logistik_pengiriman')
    ->select('area')
    ->whereNotNull('area')
    ->distinct()
    ->orderBy('area')
    ->get();

    

    return view('manager.dashboard', compact(
        'total_data',
        'gudang_ontime',
        'gudang_delay',
        'customer_ontime',
        'customer_delay',
        'bongkar_ontime',
        'bongkar_delay',
        'summary_area',
        'summary_tujuan',
        'totalNilaiMuatan',
        'totalBiayaKirim',
        'ekspedisi',
        'label',
        'value',
        'planner_ontime',
        'planner_delay',
        'planner_armada',
        'planner_belum_armada',
        'ontime_rate',
        'delay_rate',
        'armada_rate',
        'pending_rate',
        'summary_monitoring',
        'list_area'
    ));
}


private function applyFilter($query, $request)
{
    // FILTER AREA
    if ($request->area) {
        $query->where('area', $request->area);
    }

    // FILTER DATE
    if ($request->date) {
        $query->whereDate('tanggal_naik_logistik', $request->date);
    }

    // FILTER MONTH
    if ($request->month) {
        $query->whereMonth('tanggal_naik_logistik', substr($request->month, 5, 2))
              ->whereYear('tanggal_naik_logistik', substr($request->month, 0, 4));
    }

    // FILTER YEAR
    if ($request->year) {
        $query->whereYear('tanggal_naik_logistik', $request->year);
    }

    return $query;
}
//     public function dashboard()
//     {
//         $total_data = DB::table('logistik_pengiriman')->count();

//         // GUDANG
//         $gudang_ontime = DB::table('logistik_pengiriman')
//             ->where('status', 'like', '%On%Time%')
//             ->count();

//         $gudang_delay = DB::table('logistik_pengiriman')
//             ->where('status', 'Delay')
//             ->count();

//         // CUSTOMER
//         $customer_ontime = DB::table('logistik_pengiriman')
//             ->where(function ($q) {
//                 $q->where('status_akhir', 'On Time')
//                     ->orWhere('status_akhir', 'OnTime')
//                     ->orWhere('status_akhir', 'ONTIME');
//             })
//             ->count();

//         $customer_delay = DB::table('logistik_pengiriman')
//             ->whereIn('status_akhir', ['Delay', 'Critical Delay'])
//             ->count();

//         // BONGKAR
//         $bongkar_ontime = DB::table('logistik_pengiriman')
//             ->where(function ($q) {
//                 $q->where('sla_bongkar', 'H+0')
//                     ->orWhere('sla_bongkar', 'On Time')
//                     ->orWhere('sla_bongkar', 'ONTIME')
//                     ->orWhere('overstay_days', '<=', 0);
//             })
//             ->count();

//         $bongkar_delay = DB::table('logistik_pengiriman')
//             ->where(function ($q) {
//                 $q->where('sla_bongkar', '!=', 'H+0')
//                     ->orWhere('sla_bongkar', 'Delay')
//                     ->orWhere('sla_bongkar', 'Critical Delay')
//                     ->orWhere('overstay_days', '>', 0);
//             })
//             ->count();

//         // AREA
//         $summary_area = DB::table('logistik_pengiriman')
//             ->select(
//                 'area',
//                 DB::raw('COUNT(*) as total_shipment'),
//                 DB::raw('SUM(COALESCE(biaya_kirim,0)) as total_biaya'),
//                 DB::raw('SUM(COALESCE(nilai_muatan,0)) as total_muatan')
//             )
//             ->groupBy('area')
//             ->orderByDesc('total_shipment')
//             ->get();

//         $summary_tujuan = DB::table('logistik_pengiriman')
//             ->select(
//                 'tujuan',
//                 DB::raw('COUNT(*) as total_shipment'),
//                 DB::raw('SUM(biaya_kirim) as total_biaya'),
//                 DB::raw('SUM(nilai_muatan) as total_muatan')
//             )
//             ->groupBy('tujuan')
//             ->orderByDesc('total_shipment')
//             ->get();

//             $heatmap = DB::table('logistik_pengiriman')
//     ->select('area', DB::raw('COUNT(*) as total'))
//     ->whereNotNull('area')
//     ->groupBy('area')
//     ->get();

//         // ================= TOTAL NILAI MUATAN =================

//         $totalNilaiMuatan = DB::table('logistik_pengiriman')
//             ->sum('nilai_muatan');


//         // ================= TOTAL BIAYA KIRIM =================

//         $totalBiayaKirim = DB::table('logistik_pengiriman')
//             ->sum('biaya_kirim');


//         $total_data = DB::table('logistik_pengiriman')->count();

//         // ================= TIBA =================
//         $total_tiba_ontime = DB::table('logistik_pengiriman')
//             ->where('status_akhir', 'On Time')
//             ->count();

//         $total_tiba_delay = DB::table('logistik_pengiriman')
//             ->whereIn('status_akhir', ['Delay', 'Critical Delay'])
//             ->count();

//         // ================= BONGKAR =================
//         $total_bongkar_ontime = DB::table('logistik_pengiriman')
//             ->where(function ($q) {
//                 $q->where('sla_bongkar', 'H+0')
//                     ->orWhere('sla_bongkar', 'On Time')
//                     ->orWhere('sla_bongkar', 'ONTIME')
//                     ->orWhere('overstay_days', '<=', 0);
//             })
//             ->count();
//         $summary_monitoring = [
//     'tiba_ontime' => $total_data > 0 ? ($customer_ontime / $total_data) * 100 : 0,
//     'tiba_delay' => $total_data > 0 ? ($customer_delay / $total_data) * 100 : 0,
//     'bongkar_ontime' => $total_data > 0 ? ($bongkar_ontime / $total_data) * 100 : 0,
//     'bongkar_delay' => $total_data > 0 ? ($bongkar_delay / $total_data) * 100 : 0,
// ];

//    $planner_ontime = DB::table('logistik_pengiriman')
//             ->whereRaw("LOWER(status) LIKE '%on%'")
//             ->count();

//         $planner_delay = DB::table('logistik_pengiriman')
//             ->whereRaw("LOWER(status) LIKE '%delay%'")
//             ->count();

//         $planner_armada = DB::table('logistik_pengiriman')
//             ->whereNotNull('mobil')
//             ->count();

//         $planner_belum_armada = DB::table('logistik_pengiriman')
//             ->whereNull('mobil')
//             ->count();

//         // ✔ FIX LOGIC (PISAH DOMAIN)
//         $total_status = $planner_ontime + $planner_delay;
//         $ontime_rate = $total_status > 0 ? ($planner_ontime / $total_status) * 100 : 0;
//         $delay_rate  = $total_status > 0 ? ($planner_delay / $total_status) * 100 : 0;

//         $total_armada = $planner_armada + $planner_belum_armada;
//         $armada_rate  = $total_armada > 0 ? ($planner_armada / $total_armada) * 100 : 0;
//         $pending_rate = $total_armada > 0 ? ($planner_belum_armada / $total_armada) * 100 : 0;

//         $total_bongkar_delay = DB::table('logistik_pengiriman')
//             ->where(function ($q) {
//                 $q->where('sla_bongkar', '!=', 'H+0')
//                     ->orWhere('sla_bongkar', 'Delay')
//                     ->orWhere('sla_bongkar', 'Critical Delay')
//                     ->orWhere('overstay_days', '>', 0);
//             })
//             ->count();
//         // ================= CHART NILAI MUATAN & BIAYA =================

//         $chartData = DB::table('logistik_pengiriman')
//             ->selectRaw('MONTH(tanggal_naik_logistik) as bulan')
//             ->selectRaw('SUM(nilai_muatan) as total_muatan')
//             ->selectRaw('SUM(biaya_kirim) as total_biaya')
//             ->groupBy('bulan')
//             ->orderBy('bulan')
//             ->get();

//         $cost_ratio = DB::table('logistik_pengiriman')
//             ->selectRaw('
//         SUM(COALESCE(biaya_kirim,0)) as total_biaya,
//         SUM(COALESCE(nilai_muatan,0)) as total_muatan
//     ')
//             ->first();


//         $bulan = [];
//         $muatan = [];
//         $biaya = [];

//         foreach ($chartData as $row) {

//             $bulan[] = date('F', mktime(0, 0, 0, $row->bulan, 1));

//             $muatan[] = $row->total_muatan;

//             $biaya[] = $row->total_biaya;
//         }

//         $cost_ration = DB::table('logistik_pengiriman')
//             ->selectRaw('
//         SUM(COALESCE(biaya_kirim,0)) as total_biaya,
//         SUM(COALESCE(nilai_muatan,0)) as total_muatan
//     ')
//             ->first();
//         $data = DB::table('logistik_pengiriman')
//             ->select('kategori_ekspedisi', DB::raw('COUNT(*) as total'))
//             ->whereNotNull('kategori_ekspedisi')
//             ->groupBy('kategori_ekspedisi')
//             ->get();

//         $label = $data->pluck('kategori_ekspedisi');
//         $value = $data->pluck('total');


//         return view('manager.dashboard', compact(
//              'total_data',
//             'gudang_ontime',
//             'gudang_delay',
//             'customer_ontime',
//             'customer_delay',
//             'bongkar_ontime',
//             'bongkar_delay',
//             'summary_area',
//             'summary_tujuan',
//             'totalNilaiMuatan',
//             'totalBiayaKirim',
//             'summary_monitoring',
//             'planner_ontime',
//             'planner_delay',
//             'planner_armada',
//             'planner_belum_armada',
//             'ontime_rate',
//             'delay_rate',
//             'armada_rate',
//             'pending_rate',
//             'bulan',
//             'muatan',
//             'biaya',
//             'label',
//             'value'

            


//         ));
//     }

    // ================= GUDANG =================
   public function gudangOntime(Request $request)
{
    $query = DB::table('logistik_pengiriman')
        ->whereIn('status', [
            'On Time',
            'ONTIME',
            'OnTime',
            'on time'
        ]);

    $this->applyFilter($query, $request);

    $logistik = $query
        ->orderByDesc('tanggal_tiba_gudang')
        ->get();

    $list_area = $this->getArea();

    return view('manager.sla_ontime', [
        'title' => 'Gudang On Time',
        'logistik' => $logistik,
        'list_area' => $list_area
    ]);
}


   public function gudangDelay(Request $request)
{
    $query = DB::table('logistik_pengiriman')
        ->whereIn('status', [
            'Delay',
            'Critical Delay',
            'DELAY',
            'delay'
        ]);

    $this->applyFilter($query, $request);

    $logistik = $query
        ->orderByDesc('tanggal_tiba_gudang')
        ->get();

    $list_area = $this->getArea();

    return view('manager.sla_delay', [
        'title' => 'Gudang Delay',
        'logistik' => $logistik,
        'list_area' => $list_area
    ]);
}

    // private function getArea()
    // {
    //     return DB::table('logistik_pengiriman')
    //         ->select('area')
    //         ->whereNotNull('area')
    //         ->groupBy('area')
    //         ->orderBy('area')
    //         ->get();
    // }

    private function getArea()
    {
        return DB::table('logistik_pengiriman')
            ->select('area')
            ->whereNotNull('area')
            ->groupBy('area')
            ->orderBy('area')
            ->get();
    }

    // public function gudangDelay()
    // {
    //     $logistik = DB::table('logistik_pengiriman')
    //         ->where('status','Delay')
    //         ->get();

    //     return view('manager.sla_delay', compact('logistik'));
    // }

    // ================= CUSTOMER =================
public function tujuanOntime(Request $request)
{
    $query = DB::table('logistik_pengiriman')
        ->whereIn('status_akhir', [
            'On Time',
            'ONTIME',
            'OnTime',
            'on time'
        ]);

    // FILTER DATE
    if ($request->filled('date')) {

        $query->whereDate(
            'tanggal_tiba',
            $request->date
        );
    }

    // FILTER MONTH
    if ($request->filled('month')) {

        $query->whereMonth(
            'tanggal_tiba',
            substr($request->month, 5, 2)
        );

        $query->whereYear(
            'tanggal_tiba',
            substr($request->month, 0, 4)
        );
    }

    // FILTER YEAR
    if ($request->filled('year')) {

        $query->whereYear(
            'tanggal_tiba',
            $request->year
        );
    }

    // FILTER AREA
    if ($request->filled('area')) {

        $query->where(
            'area',
            $request->area
        );
    }

    $list = $query
        ->orderBy('tanggal_tiba', 'DESC')
        ->get();

    $list_area = DB::table('logistik_pengiriman')
        ->select('area')
        ->whereNotNull('area')
        ->distinct()
        ->orderBy('area')
        ->get();

    return view('manager.tujuan_ontime', compact(
        'list',
        'list_area'
    ));
}

public function tujuanDelay(Request $request)
{
    $query = DB::table('logistik_pengiriman')
        ->whereIn('status_akhir', ['Delay', 'Critical Delay']);

    $this->applyFilter($query, $request);

    $logistik = $query->get();

    return view('manager.tujuan_delay', compact('logistik'));
}

    // ================= BONGKAR =================public function bongkarOnTime(Request $request)
public function bongkarOnTime(Request $request)
{
    $query = DB::table('logistik_pengiriman')
        ->where(function ($q) {
            $q->where('sla_bongkar', 'H+0')
              ->orWhere('sla_bongkar', 'On Time')
              ->orWhere('sla_bongkar', 'ONTIME')
              ->orWhere('overstay_days', '<=', 0);
        });

    $this->applyFilter($query, $request);

    $logistik = $query->get();

    return view('manager.bongkar_ontime', compact('logistik'));
}

public function bongkarDelay(Request $request)
{
    $query = DB::table('logistik_pengiriman')
        ->where(function ($q) {
            $q->where('sla_bongkar', '!=', 'H+0')
              ->orWhere('sla_bongkar', 'Delay')
              ->orWhere('sla_bongkar', 'Critical Delay')
              ->orWhere('overstay_days', '>', 0);
        });

    $this->applyFilter($query, $request);

    $logistik = $query->get();

    return view('manager.bongkar_delay', compact('logistik'));
}

    // ================= SUMMARY =================
   public function summaryTotal(Request $request)
{
    $query = DB::table('logistik_pengiriman');

    // ================= FILTER DAY =================
    if ($request->date) {
        $query->whereDate('tanggal_naik_logistik', $request->date);
    }

    // ================= FILTER MONTH =================
    if ($request->month) {
        $query->whereMonth('tanggal_naik_logistik', substr($request->month, 5, 2))
              ->whereYear('tanggal_naik_logistik', substr($request->month, 0, 4));
    }

    // ================= FILTER YEAR =================
    if ($request->year) {
        $query->whereYear('tanggal_naik_logistik', $request->year);
    }

    if($request->area){

    $query->where('area', $request->area);
}

    $logistik = $query->get();

    return view('manager.summary_total', compact('logistik'));
}

    // public function summaryArea()
    // {
    //     $summary_area = DB::table('logistik_pengiriman')
    //         ->select(
    //             'area',
    //             DB::raw('COUNT(*) as total_shipment'),
    //             DB::raw('SUM(COALESCE(biaya_kirim,0)) as total_biaya'),
    //             DB::raw('SUM(COALESCE(nilai_muatan,0)) as total_muatan')
    //         )
    //         ->groupBy('area')
    //         ->orderByDesc('total_shipment')
    //         ->get();

    //     return view('manager.summary_area', compact('summary_area'));
    // }

     public function summaryArea(Request $request)
{
    $query = DB::table('logistik_pengiriman');

    $this->applyFilter($query, $request);

    $summary_area = $query
        ->select(
            'area',
            DB::raw('COUNT(*) as total_shipment'),
            DB::raw('COALESCE(SUM(biaya_kirim),0) as total_biaya'),
            DB::raw('COALESCE(SUM(nilai_muatan),0) as total_muatan')
        )
        ->whereNotNull('area')
        ->groupBy('area')
        ->orderByDesc('total_shipment')
        ->get();

    return view('manager.summary_area', compact('summary_area'));
}

    // fallback view planner & monitoring (biar aman)
    public function planner()
    {
        return redirect()->route('planner.dashboard');
    }

    public function monitoring()
    {
        return redirect()->route('monitoring.dashboard');
    }
}
