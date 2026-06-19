<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | FILTER
    |--------------------------------------------------------------------------
    */
    private function applyFilter($query, $request)
    {
        if ($request->area) {
            $query->where('area', $request->area);
        }

        if ($request->date) {
            $query->whereDate('tanggal_naik_logistik', $request->date);
        }

        if ($request->month) {
            $query->whereMonth('tanggal_naik_logistik', substr($request->month, 5, 2));
            $query->whereYear('tanggal_naik_logistik', substr($request->month, 0, 4));
        }

        if ($request->year) {
            $query->whereYear('tanggal_naik_logistik', $request->year);
        }

        return $query;
    }

    /*
    |--------------------------------------------------------------------------
    | GET AREA
    |--------------------------------------------------------------------------
    */
    private function getArea()
    {
        return DB::table('logistik_pengiriman')
            ->select('area')
            ->whereNotNull('area')
            ->groupBy('area')
            ->orderBy('area')
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | DASHBOARD (FIXED = SAMA PERSIS MANAGER STYLE)
    |--------------------------------------------------------------------------
    */
public function dashboard(Request $request)
{
    $base = DB::table('logistik_pengiriman');
    $this->applyFilter($base, $request);

    $total_data = (clone $base)->count();

    // ================= GUDANG =================
$gudang_ontime = (clone $base)
    ->whereNotNull('sla_loading')
    ->whereRaw("LOWER(TRIM(sla_loading)) IN ('h+0','on time','ontime')")
    ->count();

$gudang_delay = (clone $base)
    ->whereNotNull('sla_loading')
    ->whereRaw("LOWER(TRIM(sla_loading)) IN ('h+1','h+2','h>2','delay','critical delay')")
    ->count();

$gudang_unknown = (clone $base)
    ->where(function ($q) {
        $q->whereNull('sla_loading')
          ->orWhereRaw("TRIM(sla_loading) = ''")
          ->orWhereRaw("LOWER(TRIM(sla_loading)) NOT IN (
              'h+0','h+1','h+2','h>2','on time','ontime','delay','critical delay'
          )");
    })
    ->count();


        // ================= TUJUAN / CUSTOMER =================

$customer_ontime = (clone $base)
    ->whereNotNull('sla_tiba')
    ->whereRaw("LOWER(TRIM(sla_tiba)) IN ('on time','ontime','h+0')")
    ->count();

$customer_delay = (clone $base)
    ->whereNotNull('sla_tiba')
    ->whereRaw("LOWER(TRIM(sla_tiba)) IN ('delay','h+1','h+2','h>2','critical delay')")
    ->count();


        // ================= BONGKAR =================

$bongkar_ontime = (clone $base)
    ->whereNotNull('sla_bongkar')
    ->whereRaw("LOWER(TRIM(sla_bongkar)) IN ('on time','ontime','h+0')")
    ->count();


$bongkar_delay = (clone $base)
    ->whereNotNull('sla_bongkar')
    ->whereRaw("LOWER(TRIM(sla_bongkar)) IN ('delay','h+1','h+2','h>2','critical delay')")
    ->count();
    // ================= ARMADA =================
    $planner_armada = (clone $base)
        ->where('ketersediaan_unit', 'Sudah Dapat')
        ->count();

    $planner_belum_armada = (clone $base)
        ->where('ketersediaan_unit', 'Belum Dapat')
        ->count();

    // ================= PLANNER (FIX BENAR) =================
    $planner_ontime = $gudang_ontime;
    $planner_delay = $gudang_delay;

    // ================= TOTAL NILAI =================
    $totalNilaiMuatan = (clone $base)
        ->selectRaw("
            SUM(
                CASE
                    WHEN nilai_muatan IS NULL THEN 0
                    ELSE CAST(REPLACE(REPLACE(nilai_muatan,'.',''),',','') AS UNSIGNED)
                END
            ) as total
        ")
        ->value('total');

    $totalBiayaKirim = (clone $base)
        ->selectRaw("
            SUM(
                CASE
                    WHEN biaya_kirim IS NULL THEN 0
                    ELSE CAST(REPLACE(REPLACE(biaya_kirim,'.',''),',','') AS UNSIGNED)
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

    // ================= RATIO =================
    $total_status = $planner_ontime + $planner_delay;

    $ontime_rate = $total_status ? ($planner_ontime / $total_status) * 100 : 0;
    $delay_rate = $total_status ? ($planner_delay / $total_status) * 100 : 0;

    $total_armada = $planner_armada + $planner_belum_armada;

    $armada_rate = $total_armada ? ($planner_armada / $total_armada) * 100 : 0;
    $pending_rate = $total_armada ? ($planner_belum_armada / $total_armada) * 100 : 0;

    // ================= MONITORING =================
    $summary_monitoring = [
        'tiba_ontime' => $total_data ? ($customer_ontime / $total_data) * 100 : 0,
        'tiba_delay' => $total_data ? ($customer_delay / $total_data) * 100 : 0,
        'bongkar_ontime' => $total_data ? ($bongkar_ontime / $total_data) * 100 : 0,
        'bongkar_delay' => $total_data ? ($bongkar_delay / $total_data) * 100 : 0,
    ];

    // ================= AREA LIST =================
    $list_area = $this->getArea();

    return view('sales.dashboard', compact(
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

public function summaryArea()
{
    $summary_area = DB::table('logistik_pengiriman')
        ->select(
            'area',
            DB::raw('COUNT(*) as total')
        )
        ->whereNotNull('area')
        ->groupBy('area')
        ->orderBy('area')
        ->get();

    return view(
        'sales.summary_area',
        compact('summary_area')
    );
}
public function summaryAreaDetail($area)
{
    $logistik = DB::table('logistik_pengiriman')
        ->where('area', $area)
        ->orderByDesc('tanggal_tiba')
        ->get();

    return view(
        'sales.summary_area_detail',
        compact('logistik', 'area')
    );
}

public function dataLogistik(Request $request)
{
    $query = DB::table('logistik_pengiriman');

    if ($request->date) {
        $query->whereDate('tanggal_naik_logistik', $request->date);
    }

    if ($request->month) {
        $query->whereMonth('tanggal_naik_logistik', $request->month);
    }

    if ($request->year) {
        $query->whereYear('tanggal_naik_logistik', $request->year);
    }

    if ($request->area) {
        $query->where('area', $request->area);
    }

    $logistik = $query
        ->orderBy('id', 'DESC')
        ->get();

    $picList = DB::table('logistik_pengiriman')
        ->whereNotNull('pic_monitoring')
        ->distinct()
        ->pluck('pic_monitoring');

    $areaList = DB::table('logistik_pengiriman')
        ->whereNotNull('area')
        ->distinct()
        ->pluck('area');

    $akurasiTiba = [
        'Sesuai Leadtime',
        'Transit Mundur',
        'Transit Maju',
        'Belum Tiba'
    ];

    $akurasiBongkar = [
        'Sesuai Leadtime',
        'Bongkar Mundur',
        'Bongkar Maju',
        'Belum Bongkar'
    ];

    return view(
        'sales.data_logistik_full',
        compact(
            'logistik',
            'picList',
            'areaList',
            'akurasiTiba',
            'akurasiBongkar'
        )
    );
}

public function gudangOntime(Request $request)
{
    $query = DB::table('logistik_pengiriman')
        ->where('status', 'On Time');

    $this->applyFilter($query, $request);

    $logistik = $query
        ->orderByDesc('tanggal_naik_logistik')
        ->get();

    return view('sales.gudang_ontime', [
        'logistik' => $logistik,
        'list_area' => $this->getArea(),
        'title' => 'Gudang On Time'
    ]);
}

public function gudangDelay(Request $request)
{
    $query = DB::table('logistik_pengiriman')
        ->where('status', 'Delay');

    $this->applyFilter($query, $request);

    $logistik = $query
        ->orderByDesc('tanggal_naik_logistik')
        ->get();

    return view('sales.gudang_delay', [
        'logistik' => $logistik,
        'list_area' => $this->getArea(),
        'title' => 'Gudang Delay'
    ]);
}
public function bongkarDelay(Request $request)
{
    $query = DB::table('logistik_pengiriman')
        ->whereIn('sla_bongkar', [
            'Delay',
            'DELAY',
            'Critical Delay',
            'H+1',
            'H+2',
            'H>2'
        ]);

    $this->applyFilter($query, $request);

    $logistik = $query
        ->orderByDesc('tanggal_bongkar')
        ->get();

    return view('sales.bongkar_delay', compact('logistik'));
}

    public function bongkarOntime(Request $request)
{
    $query = DB::table('logistik_pengiriman')
        ->whereIn('sla_bongkar', ['H+0','On Time','ONTIME']);

    $this->applyFilter($query, $request);

    $logistik = $query->orderByDesc('tanggal_bongkar')->get();

    return view('sales.bongkar_ontime', compact('logistik'));
}

public function tujuanDelay(Request $request)
{
    $query = DB::table('logistik_pengiriman')
        ->whereIn('sla_tiba', [
            'H+1',
            'H+2',
            'H>2',
            'Delay',
            'Critical Delay'
        ]);

    $this->applyFilter($query, $request);

    $logistik = $query
        ->orderByDesc('tanggal_tiba')
        ->get();

    return view('sales.tujuan_delay', [
        'logistik'  => $logistik,
        'list_area' => $this->getArea(),
        'title'     => 'Tujuan Delay'
    ]);
}
public function tujuanOntime(Request $request)
{
    $query = DB::table('logistik_pengiriman')
        ->whereIn('sla_tiba', [
            'H+0',
            'On Time',
            'ONTIME'
        ]);

    $this->applyFilter($query, $request);

    $logistik = $query
        ->orderByDesc('tanggal_tiba')
        ->get();

    return view('sales.tujuan_ontime', [
        'logistik'  => $logistik,
        'list_area' => $this->getArea(),
        'title'     => 'Tujuan On Time'
    ]);
}
}