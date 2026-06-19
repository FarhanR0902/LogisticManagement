<?php
namespace App\Http\Controllers\Jess;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\LogistikPengiriman;

class JessController extends Controller
{

    /*
    |--------------------------------------------------------------------------
    | DASHBOARD
    |--------------------------------------------------------------------------
    */

    public function dashboard(Request $request)
    {

        // ================= BASE QUERY =================

       $base = DB::table('logistik_pengiriman');

$this->filterByDistChannel($base);
$this->applyFilter($base, $request);

        // ================= TOTAL =================

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

         $list_dist_channel = (clone $base)
    ->select('dist_channel')
    ->whereNotNull('dist_channel')
    ->distinct()
    ->orderBy('dist_channel')
    ->get();


        // ================= PLANNER =================

        $planner_ontime = $gudang_ontime;

        $planner_delay = $gudang_delay;


        // ================= TOTAL NILAI MUATAN =================

        $totalNilaiMuatan = (clone $base)

            ->selectRaw("
                SUM(
                    CASE
                        WHEN nilai_muatan IS NULL THEN 0
                        ELSE CAST(
                            REPLACE(
                                REPLACE(nilai_muatan,'.',''),
                            ',','') AS UNSIGNED
                        )
                    END
                ) as total
            ")

            ->value('total');


        // ================= TOTAL BIAYA =================

        $totalBiayaKirim = (clone $base)

            ->selectRaw("
                SUM(
                    CASE
                        WHEN biaya_kirim IS NULL THEN 0
                        ELSE CAST(
                            REPLACE(
                                REPLACE(biaya_kirim,'.',''),
                            ',','') AS UNSIGNED
                        )
                    END
                ) as total
            ")

            ->value('total');


        // ================= SUMMARY AREA =================

        $summary_area = (clone $base)

            ->select(
                'area',

                DB::raw('COUNT(*) as total_shipment'),

                DB::raw("
                    SUM(
                        CASE
                            WHEN biaya_kirim IS NULL THEN 0
                            ELSE CAST(
                                REPLACE(
                                    REPLACE(biaya_kirim,'.',''),
                                ',','') AS UNSIGNED
                            )
                        END
                    ) as total_biaya
                "),

                DB::raw("
                    SUM(
                        CASE
                            WHEN nilai_muatan IS NULL THEN 0
                            ELSE CAST(
                                REPLACE(
                                    REPLACE(nilai_muatan,'.',''),
                                ',','') AS UNSIGNED
                            )
                        END
                    ) as total_muatan
                ")
            )

            ->whereNotNull('area')

            ->groupBy('area')

            ->orderByDesc('total_shipment')

            ->get();


        // ================= SUMMARY TUJUAN =================

        $summary_tujuan = (clone $base)

            ->select(
                'tujuan',

                DB::raw('COUNT(*) as total_shipment'),

                DB::raw("
                    SUM(
                        CASE
                            WHEN biaya_kirim IS NULL THEN 0
                            ELSE CAST(
                                REPLACE(
                                    REPLACE(biaya_kirim,'.',''),
                                ',','') AS UNSIGNED
                            )
                        END
                    ) as total_biaya
                "),

                DB::raw("
                    SUM(
                        CASE
                            WHEN nilai_muatan IS NULL THEN 0
                            ELSE CAST(
                                REPLACE(
                                    REPLACE(nilai_muatan,'.',''),
                                ',','') AS UNSIGNED
                            )
                        END
                    ) as total_muatan
                ")
            )

            ->whereNotNull('tujuan')

            ->groupBy('tujuan')

            ->orderByDesc('total_shipment')

            ->get();


        // ================= EKSPEDISI =================

        $ekspedisi = (clone $base)

            ->select(
                'kategori_ekspedisi',
                DB::raw('COUNT(*) as total')
            )

            ->whereNotNull('kategori_ekspedisi')

            ->groupBy('kategori_ekspedisi')

            ->get();


        $label = $ekspedisi->pluck('kategori_ekspedisi');

        $value = $ekspedisi->pluck('total');


        // ================= RATIO =================

        $total_status = $planner_ontime + $planner_delay;

        $ontime_rate = $total_status > 0
            ? ($planner_ontime / $total_status) * 100
            : 0;

        $delay_rate = $total_status > 0
            ? ($planner_delay / $total_status) * 100
            : 0;


        $total_armada = $planner_armada + $planner_belum_armada;

        $armada_rate = $total_armada > 0
            ? ($planner_armada / $total_armada) * 100
            : 0;

        $pending_rate = $total_armada > 0
            ? ($planner_belum_armada / $total_armada) * 100
            : 0;


        // ================= MONITORING =================

        $summary_monitoring = [

            'tiba_ontime' => $total_data > 0
                ? ($customer_ontime / $total_data) * 100
                : 0,

            'tiba_delay' => $total_data > 0
                ? ($customer_delay / $total_data) * 100
                : 0,

            'bongkar_ontime' => $total_data > 0
                ? ($bongkar_ontime / $total_data) * 100
                : 0,

            'bongkar_delay' => $total_data > 0
                ? ($bongkar_delay / $total_data) * 100
                : 0,

        ];


        // ================= LIST AREA =================

       $list_area_query = DB::table('logistik_pengiriman')
    ->select('area')
    ->whereNotNull('area');

$this->filterByDistChannel($list_area_query);

$list_area = $list_area_query
    ->distinct()
    ->orderBy('area')
    ->pluck('area');


        // ================= RETURN =================

        return view('jess.dashboard', compact(

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
'list_dist_channel',
'list_area'
));
    }

    /*
    |--------------------------------------------------------------------------
    | FILTER
    |--------------------------------------------------------------------------
    */

    private function applyFilter($query, $request)
    {

        // AREA

        if ($request->area) {

            $query->where('area', $request->area);
        }

        if ($request->dist_channel) {
        $query->where('dist_channel', $request->dist_channel);
    }


        // DATE

        if ($request->date) {

            $query->whereDate(
                'tanggal_naik_logistik',
                $request->date
            );
        }

        // MONTH

        if ($request->month) {

            $query->whereMonth(
                'tanggal_naik_logistik',
                substr($request->month, 5, 2)
            );

            $query->whereYear(
                'tanggal_naik_logistik',
                substr($request->month, 0, 4)
            );
        }

        // YEAR

        if ($request->year) {

            $query->whereYear(
                'tanggal_naik_logistik',
                $request->year
            );
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
    | GUDANG ONTIME
    |--------------------------------------------------------------------------
    */


public function gudangOntime(Request $request)
{
    $query = DB::table('logistik_pengiriman');

    $this->filterByDistChannel($query);

    $query->where(function ($q) {
        $q->where('sla_loading', 'H+0')
          ->orWhere('sla_loading', 'On Time')
          ->orWhere('sla_loading', 'ONTIME');
    });

    $this->applyFilter($query, $request);

    $logistik = $query
        ->orderByDesc('tanggal_tiba_gudang')
        ->get();

    $list_area = $this->getArea();

    return view('jess.sla_ontime', compact('logistik', 'list_area'));
}


    /*
    |--------------------------------------------------------------------------
    | GUDANG DELAY
    |--------------------------------------------------------------------------
    */

  public function gudangDelay(Request $request)
{
    $query = DB::table('logistik_pengiriman');

    $this->filterByDistChannel($query);

    $query->where(function ($q) {
        $q->where('sla_loading', 'H+1')
          ->orWhere('sla_loading', 'H+2')
          ->orWhere('sla_loading', 'H>2')
          ->orWhere('sla_loading', 'Delay')
          ->orWhere('sla_loading', 'Critical Delay');
    });

    $this->applyFilter($query, $request);

    $logistik = $query
        ->orderByDesc('tanggal_tiba_gudang')
        ->get();

    $list_area = $this->getArea();

    return view('jess.sla_delay', compact('logistik', 'list_area'));
}

    /*
    |--------------------------------------------------------------------------
    | TUJUAN ONTIME
    |--------------------------------------------------------------------------
    */

public function tujuanOntime(Request $request)
{
    $query = DB::table('logistik_pengiriman');

    $this->filterByDistChannel($query);

    $query->selectRaw("
        *,
        CASE
            WHEN DATEDIFF(
                DATE(tanggal_tiba),
                DATE_ADD(DATE(tanggal_keluar_gudang), INTERVAL transport_lead_time DAY)
            ) <= 0 THEN 'On Time'
            ELSE 'Delay'
        END AS sla_tiba
    ")
    ->whereNotNull('tanggal_tiba')
    ->whereNotNull('tanggal_keluar_gudang')
    ->whereNotNull('transport_lead_time')
    ->whereRaw("
        DATEDIFF(
            DATE(tanggal_tiba),
            DATE_ADD(DATE(tanggal_keluar_gudang), INTERVAL transport_lead_time DAY)
        ) <= 0
    ");

    $this->applyFilter($query, $request);

    $logistik = $query->orderByDesc('tanggal_tiba')->get();

    $list_area = $this->getArea();

    return view('jess.tujuan_ontime', compact('logistik', 'list_area'));
}

public function tujuanDelay(Request $request)
{
    $query = DB::table('logistik_pengiriman');

    $this->filterByDistChannel($query);

    $query->selectRaw("
        *,
        CASE
            WHEN DATEDIFF(
                DATE(tanggal_tiba),
                DATE_ADD(DATE(tanggal_keluar_gudang), INTERVAL transport_lead_time DAY)
            ) = 1 THEN 'H+1'

            WHEN DATEDIFF(
                DATE(tanggal_tiba),
                DATE_ADD(DATE(tanggal_keluar_gudang), INTERVAL transport_lead_time DAY)
            ) = 2 THEN 'H+2'

            WHEN DATEDIFF(
                DATE(tanggal_tiba),
                DATE_ADD(DATE(tanggal_keluar_gudang), INTERVAL transport_lead_time DAY)
            ) > 2 THEN 'Critical Delay'

            ELSE 'On Time'
        END AS sla_tiba
    ")
    ->whereNotNull('tanggal_tiba')
    ->whereNotNull('tanggal_keluar_gudang')
    ->whereNotNull('transport_lead_time')
    ->whereRaw("
        DATEDIFF(
            DATE(tanggal_tiba),
            DATE_ADD(DATE(tanggal_keluar_gudang), INTERVAL transport_lead_time DAY)
        ) > 0
    ");

    $this->applyFilter($query, $request);

    $logistik = $query->orderByDesc('tanggal_tiba')->get();

    $list_area = $this->getArea();

    return view('jess.tujuan_delay', compact('logistik', 'list_area'));
}
    /*
    |--------------------------------------------------------------------------
    | TUJUAN DELAY
    |--------------------------------------------------------------------------
    */

   public function tujuanDelaya(Request $request)
{
    $query = DB::table('logistik_pengiriman')
        ->whereNotNull('sla_tiba')
        ->whereRaw("LOWER(TRIM(sla_tiba)) IN ('delay','h+1','h+2','h>2','critical delay')");

    $this->applyFilter($query, $request);

    $logistik = $query
        ->orderByDesc('tanggal_tiba')
        ->get();

    $list_area = $this->getArea();
// dd($query->toRawSql());    
    return view('jess.tujuan_delay', compact(
        'logistik',
        'list_area'
    ));
}

    /*
    |--------------------------------------------------------------------------
    | BONGKAR ONTIME
    |--------------------------------------------------------------------------
    */

public function bongkarOntime(Request $request)
{
    $query = DB::table('logistik_pengiriman');

    $this->filterByDistChannel($query);

    $query->selectRaw("
        *,
        CASE
            WHEN overstay_days IS NULL OR overstay_days = 0 THEN 'H+0'
            WHEN overstay_days = 1 THEN 'H+1'
            WHEN overstay_days = 2 THEN 'H+2'
            ELSE 'Critical Delay'
        END AS sla_bongkar
    ")
    ->whereNotNull('tanggal_bongkar')
    ->where('tanggal_bongkar', '!=', '1899-12-31 00:00:00')
    ->where(function ($q) {
        $q->whereNull('overstay_days')
          ->orWhere('overstay_days', 0);
    });

    $this->applyFilter($query, $request);

    $logistik = $query->orderByDesc('tanggal_bongkar')->get();

    $list_area = $this->getArea();

    return view('jess.bongkar_ontime', compact('logistik', 'list_area'));
}
public function bongkarDelay(Request $request)
{
    $query = DB::table('logistik_pengiriman');

    $this->filterByDistChannel($query);

    $query->selectRaw("
        *,
        CASE
            WHEN overstay_days = 1 THEN 'H+1'
            WHEN overstay_days = 2 THEN 'H+2'
            ELSE 'Critical Delay'
        END AS sla_bongkar
    ")
    ->whereNotNull('tanggal_bongkar')
    ->where('tanggal_bongkar', '!=', '1899-12-31 00:00:00')
    ->where('overstay_days', '>', 0);

    $this->applyFilter($query, $request);

    $logistik = $query->orderByDesc('tanggal_bongkar')->get();

    $list_area = $this->getArea();

    return view('jess.bongkar_delay', compact('logistik', 'list_area'));
}


    /*
    |--------------------------------------------------------------------------
    | BONGKAR DELAY
    |--------------------------------------------------------------------------
    */

public function bongkarDelaya(Request $request)
{
    $query = DB::table('logistik_pengiriman')
        ->whereNotNull('sla_bongkar')
        ->whereRaw("LOWER(TRIM(sla_bongkar)) IN ('delay','critical delay','h+1','h+2','h>2')");

    $this->applyFilter($query, $request);

    $logistik = $query
        ->orderByDesc('tanggal_bongkar')
        ->get();

    return view('jess.bongkar_delay', compact(
        'logistik'
    ));
}

    /*
    |--------------------------------------------------------------------------
    | SUMMARY TOTAL
    |--------------------------------------------------------------------------
    */

    public function summaryTotal(Request $request)
    {

        $query = DB::table('logistik_pengiriman');

        $this->applyFilter($query, $request);

        $logistik = $query->get();

        return view('jess.summary_total', compact(
            'logistik'
        ));
    }


    /*
    |--------------------------------------------------------------------------
    | SUMMARY AREA
    |--------------------------------------------------------------------------
    */

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

        return view('jess.summary_area', compact(
            'summary_area'
        ));
    }


    /*
    |--------------------------------------------------------------------------
    | REDIRECT
    |--------------------------------------------------------------------------
    */
private function filterByDistChannel($query)
{
    $channel = session('dist_channel');

    if ($channel) {
        $query->whereRaw('LOWER(TRIM(dist_channel)) = ?', [$channel]);
    }

    return $query;
}
    public function planner()
    {
        return redirect()->route('planner.dashboard');
    }

    public function monitoring()
    {
        return redirect()->route('monitoring.dashboard');
    }

}
