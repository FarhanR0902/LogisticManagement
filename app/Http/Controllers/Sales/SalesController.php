<?php
namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\LogistikPengiriman;
use App\Models\LogistikPengirimanPasuruan;

class SalesController extends Controller
{

    /*
    |--------------------------------------------------------------------------
    | DASHBOARD
    |--------------------------------------------------------------------------
    */
 private const PULAU_MAP = [
        'JAWA'       => ['JABODEBEK','BANTEN','JAWA_BARAT','JAWA_TENGAH','JAWA_TIMUR','YOGYAKARTA'],
        'SUMATERA'   => ['ACEH','SUMATERA_UTARA','SUMATERA_BARAT','RIAU','KEP._RIAU','JAMBI','SUMATERA_SELATAN','BENGKULU','LAMPUNG','KEP._BANGKA_BELITUNG'],
        'KALIMANTAN' => ['KALIMANTAN_BARAT','KALIMANTAN_TENGAH','KALIMANTAN_SELATAN','KALIMANTAN_TIMUR','KALIMANTAN_UTARA'],
        'SULAWESI'   => ['SULAWESI_UTARA','SULAWESI_TENGAH','SULAWESI_SELATAN','SULAWESI_TENGGARA','SULAWESI_BARAT','GORONTALO'],
        'BALI_NUSRA' => ['PROV._BALI','NUSA_TENGGARA_BARAT','NUSA_TENGGARA_TIMUR'],
        'MALUKU'     => ['PROV._MALUKU','PROV._MALUKU_UTARA'],
        'PAPUA'      => ['PROV._PAPUA','PAPUA_BARAT','PAPUA_BARAT_DAYA','PAPUA_SELATAN','PAPUA_TENGAH'],
    ];
 public function dashboard(Request $request)
{
    // ================= BASE QUERY =================

    $base = DB::table('logistik_pengiriman');

    $base = $this->filterByDistChannel($base);
    $base = $this->applyFilter($base, $request);

    // ================= TOTAL =================

    $total_data = (clone $base)->count();

    // ================= GUDANG (FIXED - samain sama Manager) =================

    $gudang_ontime = (clone $base)
        ->where(function ($q) {
            $q->whereNotNull('tanggal_tiba_gudang')
              ->orWhereNotNull('tanggal_tiba_gudang_2')
              ->orWhereNotNull('tanggal_tiba_gudang_3');
        })
        ->count();

    $gudang_delay = (clone $base)
        ->where(function ($q) {
            $q->whereNull('rencana_kirim')
              ->orWhere('rencana_kirim', '')
              ->orWhereNull('tanggal_dpt_unit')
              ->orWhere('tanggal_dpt_unit', '');
        })
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

    // ================= TUJUAN / CUSTOMER (FIXED - samain sama Manager, ini yang 'CR') =================

    $customer_ontime = (clone $base)
        ->whereNotNull('tanggal_tiba')
        ->whereNotNull('estimasi_tiba')
        ->whereRaw("
            DATEDIFF(
                DATE(tanggal_tiba),
                DATE(estimasi_tiba)
            ) <= 0
        ")
        ->count();

    $customer_delay = (clone $base)
        ->whereNotNull('tanggal_tiba')
        ->whereNotNull('estimasi_tiba')
        ->whereRaw("
            DATEDIFF(
                DATE(tanggal_tiba),
                DATE(estimasi_tiba)
            ) > 0
        ")
        ->count();

    // ================= BONGKAR (FIXED - samain sama Manager) =================

    $bongkar_ontime = (clone $base)
        ->whereNotNull('tanggal_bongkar')
        ->where('tanggal_bongkar', '!=', '1899-12-31 00:00:00')
        ->where(function ($q) {
            $q->whereNull('overstay_days')
              ->orWhere('overstay_days', 0);
        })
        ->count();

    $bongkar_delay = (clone $base)
        ->whereNotNull('tanggal_bongkar')
        ->where('tanggal_bongkar', '!=', '1899-12-31 00:00:00')
        ->where('overstay_days', '>', 0)
        ->count();

    // ================= ARMADA =================
    // Dibiarin pakai ketersediaan_unit (spesifik sales), tidak ada pembanding di Manager

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

    // ================= PLANNER (FIXED - samain sama Manager, jangan ambil dari gudang) =================

    $planner_ontime = (clone $base)
        ->whereNotNull('rencana_kirim')
        ->whereNotNull('tanggal_dpt_unit')
        ->whereRaw('DATE(tanggal_dpt_unit) <= DATE(rencana_kirim)')
        ->count();

    $planner_delay = (clone $base)
        ->whereNotNull('rencana_kirim')
        ->whereNotNull('tanggal_dpt_unit')
        ->whereRaw('DATE(tanggal_dpt_unit) > DATE(rencana_kirim)')
        ->count();

    // ================= TOTAL NILAI MUATAN & BIAYA (sudah benar, dibiarin) =================

    $totalNilaiMuatan = (clone $base)->sum('nilai_muatan');

    $totalBiayaKirim = (clone $base)
        ->selectRaw("SUM(biaya_kirim) as total")
        ->value('total');

    // ================= SUMMARY AREA (FIXED - buang REPLACE yang salah) =================

    $summary_area = (clone $base)
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

    // ================= SUMMARY TUJUAN (FIXED - buang REPLACE yang salah) =================

    $summary_tujuan = (clone $base)
        ->select(
            'tujuan',
            DB::raw('COUNT(*) as total_shipment'),
            DB::raw('COALESCE(SUM(biaya_kirim),0) as total_biaya'),
            DB::raw('COALESCE(SUM(nilai_muatan),0) as total_muatan')
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
    // Tetap dibatasi dist_channel session, TIDAK dibatasi filter pulau

    $list_area_query = DB::table('logistik_pengiriman')
        ->select('area')
        ->whereNotNull('area');

    $this->filterByDistChannel($list_area_query);

    $list_area = $list_area_query
        ->distinct()
        ->orderBy('area')
        ->pluck('area');

    // ================= RETURN =================

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
        'list_dist_channel',
        'list_area'
    ));
}   /*
    |--------------------------------------------------------------------------
    | FILTER DIST CHANNEL - PASURUAN
    |--------------------------------------------------------------------------
    */
    public function filterByDistChannelPasuruan($query)
    {
        if (session('dist_channel')) {
            $query->where('dist_channel_pasuruan', session('dist_channel'));
        }

        return $query;
    }

    /*
    |--------------------------------------------------------------------------
    | FILTER - PASURUAN
    |--------------------------------------------------------------------------
    */
    private function applyFilterPasuruan($query, $request)
    {
        if ($request->area) {
            $query->where('area_pasuruan', $request->area);
        }

        if ($request->dist_channel) {
            $query->where('dist_channel_pasuruan', $request->dist_channel);
        }
           if ($request->filled('pulau') && isset(self::PULAU_MAP[$request->pulau])) {
            $query->whereIn('area_pasuruan', self::PULAU_MAP[$request->pulau]);
        }

        if ($request->date) {
            $query->whereDate('tanggal_terima_po_pasuruan', $request->date);
        }

        if ($request->month) {
            $query->whereMonth('tanggal_terima_po_pasuruan', substr($request->month, 5, 2));
            $query->whereYear('tanggal_terima_po_pasuruan', substr($request->month, 0, 4));
        }

        if ($request->year) {
            $query->whereYear('tanggal_terima_po_pasuruan', $request->year);
        }

        return $query;
    }

    /*
    |--------------------------------------------------------------------------
    | DASHBOARD PASURUAN (SALES - filtered by dist_channel)
    |--------------------------------------------------------------------------
    */
   public function dashboardPasuruan(Request $request)
    {
        $base = DB::table('logistik_pengiriman_pasuruan');

        $this->filterByDistChannelPasuruan($base);
        $this->applyFilterPasuruan($base, $request);

        $total_data = (clone $base)->count();

        $gudang_ontime = (clone $base)
            ->whereNotNull('tanggal_tiba_gudang_pasuruan')
            ->count();

        $gudang_delay = (clone $base)
            ->where(function ($q) {
                $q->whereNull('rencana_kirim_pasuruan')
                  ->orWhere('rencana_kirim_pasuruan', '')
                  ->orWhereNull('tanggal_dpt_unit_pasuruan')
                  ->orWhere('tanggal_dpt_unit_pasuruan', '');
            })
            ->count();

        $customer_ontime = (clone $base)
            ->whereNotNull('tanggal_tiba_pasuruan')
            ->whereNotNull('estimasi_tiba_pasuruan')
            ->whereRaw("DATEDIFF(DATE(tanggal_tiba_pasuruan), DATE(estimasi_tiba_pasuruan)) <= 0")
            ->count();

        $customer_delay = (clone $base)
            ->whereNotNull('tanggal_tiba_pasuruan')
            ->whereNotNull('estimasi_tiba_pasuruan')
            ->whereRaw("DATEDIFF(DATE(tanggal_tiba_pasuruan), DATE(estimasi_tiba_pasuruan)) > 0")
            ->count();

        $bongkar_ontime = (clone $base)
            ->whereNotNull('tanggal_bongkar_pasuruan')
            ->where('tanggal_bongkar_pasuruan', '!=', '1899-12-31 00:00:00')
            ->where(function ($q) {
                $q->whereNull('overstay_days_pasuruan')
                  ->orWhere('overstay_days_pasuruan', 0);
            })
            ->count();

        $bongkar_delay = (clone $base)
            ->whereNotNull('tanggal_bongkar_pasuruan')
            ->where('tanggal_bongkar_pasuruan', '!=', '1899-12-31 00:00:00')
            ->where('overstay_days_pasuruan', '>', 0)
            ->count();

        $planner_armada = (clone $base)
            ->whereNotNull('rencana_kirim_pasuruan')
            ->whereRaw("TRIM(rencana_kirim_pasuruan) <> ''")
            ->whereNotNull('tanggal_dpt_unit_pasuruan')
            ->whereRaw("TRIM(tanggal_dpt_unit_pasuruan) <> ''")
            ->count();

        $planner_belum_armada = (clone $base)
            ->where(function ($q) {
                $q->whereNull('rencana_kirim_pasuruan')
                  ->orWhere('rencana_kirim_pasuruan', '')
                  ->orWhereNull('tanggal_dpt_unit_pasuruan')
                  ->orWhere('tanggal_dpt_unit_pasuruan', '');
            })
            ->count();

        $list_dist_channel = (clone $base)
            ->select('dist_channel_pasuruan')
            ->whereNotNull('dist_channel_pasuruan')
            ->distinct()
            ->orderBy('dist_channel_pasuruan')
            ->get();

        $planner_ontime = (clone $base)
            ->whereNotNull('rencana_kirim_pasuruan')
            ->whereNotNull('tanggal_dpt_unit_pasuruan')
            ->whereRaw('DATE(tanggal_dpt_unit_pasuruan) <= DATE(rencana_kirim_pasuruan)')
            ->count();

        $planner_delay = (clone $base)
            ->whereNotNull('rencana_kirim_pasuruan')
            ->whereNotNull('tanggal_dpt_unit_pasuruan')
            ->whereRaw('DATE(tanggal_dpt_unit_pasuruan) > DATE(rencana_kirim_pasuruan)')
            ->count();

        $totalNilaiMuatan = (clone $base)->sum('nilai_muatan_pasuruan');

        $totalBiayaKirim = (clone $base)
            ->selectRaw("SUM(biaya_kirim_pasuruan) as total")
            ->value('total');

        $summary_area = (clone $base)
            ->select(
                'area_pasuruan',
                DB::raw('COUNT(*) as total_shipment'),
                DB::raw('SUM(IFNULL(biaya_kirim_pasuruan, 0)) as total_biaya'),
                DB::raw('SUM(IFNULL(nilai_muatan_pasuruan, 0)) as total_muatan')
            )
            ->whereNotNull('area_pasuruan')
            ->groupBy('area_pasuruan')
            ->orderByDesc('total_shipment')
            ->get();

        $summary_tujuan = (clone $base)
            ->select(
                'tujuan_pasuruan',
                DB::raw('COUNT(*) as total_shipment'),
                DB::raw('SUM(IFNULL(biaya_kirim_pasuruan, 0)) as total_biaya'),
                DB::raw('SUM(IFNULL(nilai_muatan_pasuruan, 0)) as total_muatan')
            )
            ->whereNotNull('tujuan_pasuruan')
            ->groupBy('tujuan_pasuruan')
            ->orderByDesc('total_shipment')
            ->get();

        $ekspedisi = (clone $base)
            ->select('kategori_ekspedisi_pasuruan', DB::raw('COUNT(*) as total'))
            ->whereNotNull('kategori_ekspedisi_pasuruan')
            ->groupBy('kategori_ekspedisi_pasuruan')
            ->get();

        $label = $ekspedisi->pluck('kategori_ekspedisi_pasuruan');
        $value = $ekspedisi->pluck('total');

        $total_status = $planner_ontime + $planner_delay;
        $ontime_rate = $total_status > 0 ? ($planner_ontime / $total_status) * 100 : 0;
        $delay_rate  = $total_status > 0 ? ($planner_delay / $total_status) * 100 : 0;

        $total_armada = $planner_armada + $planner_belum_armada;
        $armada_rate  = $total_armada > 0 ? ($planner_armada / $total_armada) * 100 : 0;
        $pending_rate = $total_armada > 0 ? ($planner_belum_armada / $total_armada) * 100 : 0;

        $summary_monitoring = [
            'tiba_ontime'    => $total_data > 0 ? ($customer_ontime / $total_data) * 100 : 0,
            'tiba_delay'     => $total_data > 0 ? ($customer_delay / $total_data) * 100 : 0,
            'bongkar_ontime' => $total_data > 0 ? ($bongkar_ontime / $total_data) * 100 : 0,
            'bongkar_delay'  => $total_data > 0 ? ($bongkar_delay / $total_data) * 100 : 0,
        ];

        // list_area untuk filter dropdown, tetap dibatasi dist_channel session
        // (sengaja TIDAK dibatasi pulau, supaya kotak pulau lain tetap bisa diklik)
        $list_area = (clone $base)
            ->select('area_pasuruan')
            ->whereNotNull('area_pasuruan')
            ->distinct()
            ->orderBy('area_pasuruan')
            ->pluck('area_pasuruan');

        return view('sales.dashboard_pasuruan', compact(
            'total_data',
            'gudang_ontime', 'gudang_delay',
            'customer_ontime', 'customer_delay',
            'bongkar_ontime', 'bongkar_delay',
            'summary_area', 'summary_tujuan',
            'totalNilaiMuatan', 'totalBiayaKirim',
            'ekspedisi', 'label', 'value',
            'planner_ontime', 'planner_delay',
            'planner_armada', 'planner_belum_armada',
            'ontime_rate', 'delay_rate',
            'armada_rate', 'pending_rate',
            'summary_monitoring', 'list_dist_channel', 'list_area'
        ));
    }
  /*
    |--------------------------------------------------------------------------
    | DATA LOGISTIK PASURUAN (SALES - filtered by dist_channel)
    |--------------------------------------------------------------------------
    */
    public function dataLogistikPasuruan()
    {
        $query = LogistikPengirimanPasuruan::query();
        $this->filterByDistChannelPasuruan($query);

        $logistik = $query->orderByDesc('id')->get();

        $plannerQuery = LogistikPengirimanPasuruan::select('planner_pasuruan')
            ->whereNotNull('planner_pasuruan')
            ->where('planner_pasuruan', '!=', '');
        $this->filterByDistChannelPasuruan($plannerQuery);

        $planners = $plannerQuery
            ->distinct()
            ->orderBy('planner_pasuruan')
            ->pluck('planner_pasuruan');

        $areaQuery = LogistikPengirimanPasuruan::select('area_pasuruan')
            ->whereNotNull('area_pasuruan')
            ->where('area_pasuruan', '!=', '');
        $this->filterByDistChannelPasuruan($areaQuery);

        $areas = $areaQuery
            ->distinct()
            ->orderBy('area_pasuruan')
            ->pluck('area_pasuruan');

        return view('sales.data_logistik_pasuruan', compact(
            'logistik',
            'planners',
            'areas'
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
      if ($request->filled('pulau') && isset(self::PULAU_MAP[$request->pulau])) {
        $query->whereIn('area', self::PULAU_MAP[$request->pulau]);
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

    return view('sales.sla_ontime', compact(
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

    return view('sales.sla_delay', compact(
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

    return view('sales.tujuan_ontime', compact('logistik', 'list_area'));
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

    return view('sales.tujuan_delay', compact(
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
    return view('sales.tujuan_delay', compact(
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

    return view('sales.bongkar_ontime', compact('logistik', 'list_area'));
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

    return view('sales.bongkar_delay', compact('logistik', 'list_area'));
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

    return view('sales.bongkar_delay', compact(
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

        return view('sales.summary_total', compact(
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

        return view('sales.summary_area', compact(
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
