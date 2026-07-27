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

$base = $this->filterByDistChannel($base);
$base = $this->applyFilter($base, $request);

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

   public function applyFilter($query, $request)
{
    if ($request->date) {
        $query->whereDate('create_tgl', $request->date);
    }

    if ($request->month) {
        $query->whereMonth('create_tgl', substr($request->month, 5, 2))
              ->whereYear('create_tgl', substr($request->month, 0, 4));
    }

    if ($request->year) {
        $query->whereYear('create_tgl', $request->year);
    }

    if ($request->area) {
        $query->where('area', $request->area);
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
private function hitungSla($request)
{


    $data = [
        'lama_waktu_pencarian' => null,
        'sla_dapat_mobil'      => null,
        'status_pengiriman'    => null,

        'lama_digudang'        => null,
        'status_gudang'        => null,
        'sla_loading'          => null,

        'lama_digudang_2'      => null,
        'status_gudang_2'      => null,
        'sla_loading_2'        => null,

        'lama_digudang_3'      => null,
        'status_gudang_3'      => null,
        'sla_loading_3'        => null,
    ];

    // =====================================================
    // FUNCTION HITUNG SELISIH (NO CARBON)
    // =====================================================



    $hitungSelisih = function ($start, $end) {

        if (!$start || !$end) return null;

        $awal  = new \DateTime($start);
        $akhir = new \DateTime($end);

        if ($akhir < $awal) {
            return [
                'text' => '0 Menit',
                'days' => 0,
                'hours' => 0,
                'minutes' => 0,
                'seconds' => 0
            ];
        }

        $selisih = $akhir->getTimestamp() - $awal->getTimestamp();

        $days    = floor($selisih / 86400);
        $hours   = floor(($selisih % 86400) / 3600);
        $minutes = floor(($selisih % 3600) / 60);

        $text = '';

        if ($days > 0) {
            $text .= $days . ' Hari ';
        }

        if ($hours > 0) {
            $text .= $hours . ' Jam ';
        }

        $text .= $minutes . ' Menit';

        return [
            'text'    => trim($text),
            'days'    => $days,
            'hours'   => $hours,
            'minutes' => $minutes
        ];
    };

    // =====================================================
    // AMBIL TIBA GUDANG TERCEPAT (GLOBAL)
    // =====================================================
    $tibaGudang = collect([
        $request->tanggal_tiba_gudang,
        $request->tanggal_tiba_gudang_2,
        $request->tanggal_tiba_gudang_3,
    ])->filter()->sort()->first();

    // =====================================================
// 1. SLA DAPAT MOBIL (pakai rencana_kirim)
// =====================================================
// 1. SLA DAPAT MOBIL (FIX: dari rencana kirim ke tanggal dpt unit)
// =====================================================
// INI sla lama pencarian yg sebelum area
// $start = $request->rencana_kirim
//     ? date('Y-m-d H:i:s', strtotime($request->rencana_kirim))
//     : null;

// $end = $request->tanggal_dpt_unit
//     ? date('Y-m-d H:i:s', strtotime($request->tanggal_dpt_unit))
//     : null;

// $diff = $hitungSelisih($start, $end);

// // WAJIB selalu di-set (biar tidak stuck nilai lama)
// $data['lama_waktu_pencarian'] = $diff['text'] ?? null;

// // SLA LOGIC (pakai tanggal saja: sama hari masih On Time)
// if ($start && $end) {

//     $tanggalRencana = date('Y-m-d', strtotime($start));
//     $tanggalDptUnit = date('Y-m-d', strtotime($end));

//     if ($tanggalDptUnit > $tanggalRencana) {
//         $data['sla_dapat_mobil']   = 'Delay';
//         $data['status_pengiriman'] = 'Terlambat';
//     } else {
//         $data['sla_dapat_mobil']   = 'On Time';
//         $data['status_pengiriman'] = 'Sudah Dapat';
//     }

// } else {
//     $data['sla_dapat_mobil']   = null;
//     $data['status_pengiriman'] = null;
// }

// setelah area

// =====================================================
// 1. SLA DAPAT MOBIL
// =====================================================

$start = $request->rencana_kirim
    ? date('Y-m-d H:i:s', strtotime($request->rencana_kirim))
    : null;

$end = $request->tanggal_dpt_unit
    ? date('Y-m-d H:i:s', strtotime($request->tanggal_dpt_unit))
    : null;

$diff = $hitungSelisih($start, $end);

$data['lama_waktu_pencarian'] = $diff['text'] ?? null;

if ($start && $end) {

    $area = strtoupper(trim($request->area ?? ''));

    // Hitung berdasarkan TANGGAL saja (abaikan jam)
    $tanggalRencana = strtotime(date('Y-m-d', strtotime($start)));
    $tanggalDptUnit = strtotime(date('Y-m-d', strtotime($end)));

    $selisihHari = floor(
        ($tanggalDptUnit - $tanggalRencana) / 86400
    );

    // Tentukan batas SLA
    if ($area == 'JABODETABEK' || $area == 'JABODEBEK') {

        // H+0
        $batasHari = 0;

    } elseif ($area == 'JAWA_BARAT') {

        // H+1
        $batasHari = 1;

    } else {

        // Semua area lainnya H+2
        $batasHari = 2;

    }

    if ($selisihHari > $batasHari) {

        $data['sla_dapat_mobil']   = 'Delay';
        $data['status_pengiriman'] = 'Terlambat';

    } else {

        $data['sla_dapat_mobil']   = 'On Time';
        $data['status_pengiriman'] = 'Sudah Dapat';

    }

} else {

    $data['sla_dapat_mobil']   = null;
    $data['status_pengiriman'] = null;

}    // =====================================================
    // 2. GUDANG 1
    // =====================================================
// =====================================================
// 2. GUDANG 1
// =====================================================
if ($request->planning_loading && $request->tanggal_tiba_gudang) {

    $diff = $hitungSelisih(
        $request->planning_loading,
        $request->tanggal_tiba_gudang
    );



    if ($diff) {

        $data['lama_digudang'] = $diff['text'];

        if ($diff['days'] > 0) {
            $data['status_gudang'] = 'Delay';
            $data['sla_loading']   = 'H+' . $diff['days'];
            
        } else {
            $data['status_gudang'] = 'On Time';
            $data['sla_loading']   = 'Sesuai SLA';
        }
    }
}

// =====================================================
// 3. GUDANG 2
// =====================================================
if ($request->planning_loading_2 && $request->tanggal_tiba_gudang_2) {

    $diff = $hitungSelisih(
        $request->planning_loading_2,
        $request->tanggal_tiba_gudang_2
    );

    if ($diff) {

        $data['lama_digudang_2'] = $diff['text'];

        if ($diff['days'] > 0) {
            $data['status_gudang_2'] = 'Delay';
            $data['sla_loading_2']   = 'H+' . $diff['days'];
        } else {
            $data['status_gudang_2'] = 'On Time';
            $data['sla_loading_2']   = 'Sesuai SLA';
        }
    }
}

// =====================================================
// 4. GUDANG 3
// =====================================================
if ($request->planning_loading_3 && $request->tanggal_tiba_gudang_3) {

    $diff = $hitungSelisih(
        $request->planning_loading_3,
        $request->tanggal_tiba_gudang_3
    );

    if ($diff) {

        $data['lama_digudang_3'] = $diff['text'];

        if ($diff['days'] > 0) {
            $data['status_gudang_3'] = 'Delay';
            $data['sla_loading_3']   = 'H+' . $diff['days'];
        } else {
            $data['status_gudang_3'] = 'On Time';
            $data['sla_loading_3']   = 'Sesuai SLA';
        }
    }
}

    return $data;
}

public function gudangOntime(Request $request)
{
    $query = DB::table('logistik_pengiriman');

    // Filter dist channel
    $this->filterByDistChannel($query);

    // Sudah tiba minimal salah satu gudang
    $query->where(function ($q) {
        $q->whereNotNull('tanggal_tiba_gudang')
          ->orWhereNotNull('tanggal_tiba_gudang_2')
          ->orWhereNotNull('tanggal_tiba_gudang_3');
    });

    // Filter bulan, area, tahun
    $this->applyFilter($query, $request);

    $list = $query
        ->orderByDesc(DB::raw("
            COALESCE(
                tanggal_tiba_gudang,
                tanggal_tiba_gudang_2,
                tanggal_tiba_gudang_3
            )
        "))
        ->get();

    $list_area = $this->getArea();

    return view('jess.sla_ontime', compact(
        'list',
        'list_area'
    ));
}
    /*
    |--------------------------------------------------------------------------
    | GUDANG DELAY
    |--------------------------------------------------------------------------
    */

 public function gudangDelay(Request $request)
{
    $query = DB::table('logistik_pengiriman');

    // Filter sesuai Dist Channel user
    $this->filterByDistChannel($query);

    // Sudah ada rencana kirim
    $query->whereNotNull('rencana_kirim')
          ->whereRaw("TRIM(rencana_kirim) <> ''");

    // Sudah dapat unit
    $query->whereNotNull('tanggal_dpt_unit')
          ->whereRaw("TRIM(tanggal_dpt_unit) <> ''");

    // Belum tiba di gudang sama sekali
    $query->where(function ($q) {
        $q->whereNull('tanggal_tiba_gudang')
          ->orWhereRaw("TRIM(tanggal_tiba_gudang) = ''");
    });

    $query->where(function ($q) {
        $q->whereNull('tanggal_tiba_gudang_2')
          ->orWhereRaw("TRIM(tanggal_tiba_gudang_2) = ''");
    });

    $query->where(function ($q) {
        $q->whereNull('tanggal_tiba_gudang_3')
          ->orWhereRaw("TRIM(tanggal_tiba_gudang_3) = ''");
    });

    // Filter area, bulan, tahun, dll
    $this->applyFilter($query, $request);

    $list = $query
        ->orderByDesc('tanggal_dpt_unit')
        ->get();

    $list_area = $this->getArea();

    return view('jess.sla_delay', compact(
        'list',
        'list_area'
    ));
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
        estimasi_tiba AS tanggal_estimasi,
        CASE
            WHEN DATEDIFF(
                DATE(tanggal_tiba),
                DATE(estimasi_tiba)
            ) <= 0
            THEN 'On Time'
            ELSE 'Delay'
        END AS sla_tiba
    ")
    ->whereNotNull('tanggal_tiba')
    ->whereNotNull('estimasi_tiba')
    ->whereRaw("
        DATEDIFF(
            DATE(tanggal_tiba),
            DATE(estimasi_tiba)
        ) <= 0
    ");

    $this->applyFilter($query, $request);

    $logistik = $query
        ->orderByDesc('tanggal_tiba')
        ->get();

    $list_area = $this->getArea();

    return view('jess.tujuan_ontime', compact('logistik', 'list_area'));
}

public function tujuanDelay(Request $request)
{
    $query = DB::table('logistik_pengiriman')
        ->whereRaw("LOWER(TRIM(sla_tiba)) = 'delay'");

    // Filter sesuai dist_channel user yang login
    $this->filterByDistChannel($query);

    // Filter tanggal, area, dll
    $this->applyFilter($query, $request);

    $logistik = $query
        ->orderBy('no_shipment')
        ->orderBy('estimasi_tiba')
        ->get();

    $list_area = $this->getArea();

    return view('jess.tujuan_delay', compact(
        'logistik',
        'list_area'
    ));
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
public function filterByDistChannel($query)
{
    if (session('dist_channel')) {
        $query->where('dist_channel', session('dist_channel'));
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
