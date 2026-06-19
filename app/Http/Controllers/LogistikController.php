<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LogistikPengiriman;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\LogistikImport;

class LogistikController extends Controller
{
    /* =========================================================
     * DASHBOARD
     * ========================================================= */
    public function dashboard(Request $request)
    {
        $bulan = $request->month ? date('m', strtotime($request->month)) : null;
        $tahun = $request->year;
        $area  = $request->area;
        $date  = $request->date;

        $query = LogistikPengiriman::query();

        // FILTER DATE
        if ($date) {
            $query->whereDate('tanggal_naik_logistik', $date);
        }

        if ($bulan && $tahun) {
            $query->whereMonth('tanggal_naik_logistik', $bulan)
                ->whereYear('tanggal_naik_logistik', $tahun);
        }

        if ($area) {
            $query->where('area', $area);
        }

        /* ================= KPI ================= */
        $total_data = (clone $query)->count();

        $total_loading_ontime = (clone $query)
            ->whereIn('status_akhir', ['On Time', 'Ontime'])
            ->count();

        $total_loading_delay = (clone $query)
            ->whereIn('status_akhir', ['Delay', 'Critical Delay'])
            ->count();

        $armada = (clone $query)
    ->where('ketersediaan_unit', 'Sudah Dapat')
    ->count();

$belum_armada = (clone $query)
    ->where('ketersediaan_unit', 'Belum Dapat')
    ->count();

        $process = (clone $query)
            ->where('status_pengiriman', 'like', '%process%')
            ->count();

        /* ================= FINANCE ================= */
        $totalNilaiMuatan = (clone $query)->sum('nilai_muatan');
        $totalBiayaKirim  = (clone $query)->sum('biaya_kirim');

        /* ================= SUMMARY AREA ================= */
        $summary_area = (clone $query)
            ->select(
                'area',
                DB::raw('COUNT(*) as total_shipment'),
                DB::raw('SUM(biaya_kirim) as total_biaya'),
                DB::raw('SUM(nilai_muatan) as total_muatan')
            )
            ->groupBy('area')
            ->orderByDesc('total_shipment')
            ->get();

        /* ================= SUMMARY TUJUAN ================= */
        $summary_tujuan = (clone $query)
            ->select(
                'tujuan',
                DB::raw('COUNT(*) as total_shipment'),
                DB::raw('SUM(biaya_kirim) as total_biaya'),
                DB::raw('SUM(nilai_muatan) as total_muatan')
            )
            ->groupBy('tujuan')
            ->orderByDesc('total_shipment')
            ->get();

        /* ================= LIST AREA ================= */
        $list_area = DB::table('logistik_pengiriman')
            ->select('area')
            ->whereNotNull('area')
            ->distinct()
            ->orderBy('area')
            ->get();
            $list_dist_channel = DB::table('logistik_pengiriman')
    ->select('dist_channel')
    ->whereNotNull('dist_channel')
    ->distinct()
    ->orderBy('dist_channel')
    ->get();

        /* ================= GUDANG ================= */
       $gudang_ontime = (clone $query)
    ->where(function($q){
        $q->where('sla_loading', 'H+0')
          ->orWhere('status_gudang', 'On Time')
          ->orWhere('status_gudang', 'ONTIME');
    })
    ->count();

$gudang_delay = (clone $query)
    ->where(function($q){
        $q->where('sla_loading', 'H+1')
          ->orWhere('sla_loading', 'H+2')
          ->orWhere('sla_loading', 'H>2')
          ->orWhere('status_gudang', 'Delay')
          ->orWhere('status_gudang', 'DELAY');
    })
    ->count();

        /* ================= CUSTOMER ================= */
      $customer_ontime = (clone $query)
    ->where(function($q){
        $q->where('sla_tiba', 'H+0')
          ->orWhere('sla_tiba', 'On Time')
          ->orWhere('sla_tiba', 'ONTIME');
    })
    ->count();

$customer_delay = (clone $query)
    ->where(function($q){
        $q->where('sla_tiba', 'H+1')
          ->orWhere('sla_tiba', 'H+2')
          ->orWhere('sla_tiba', 'H>2')
          ->orWhere('sla_tiba', 'Delay')
          ->orWhere('sla_tiba', 'Critical Delay');
    })
    ->count();

        /* ================= BONGKAR ================= */
       $bongkar_ontime = (clone $query)
    ->where(function($q){
        $q->where('sla_bongkar', 'H+0')
          ->orWhere('sla_bongkar', 'On Time')
          ->orWhere('sla_bongkar', 'ONTIME');
    })
    ->count();

$bongkar_delay = (clone $query)
    ->where(function($q){
        $q->where('sla_bongkar', 'H+1')
          ->orWhere('sla_bongkar', 'H+2')
          ->orWhere('sla_bongkar', 'H>2')
          ->orWhere('sla_bongkar', 'Delay')
          ->orWhere('sla_bongkar', 'Critical Delay');
    })
    ->count();

        /* ================= SUMMARY MONITORING (FIX ERROR VIEW) ================= */
        $total_tiba = $customer_ontime + $customer_delay;
        $total_bongkar = $bongkar_ontime + $bongkar_delay;

        $summary_monitoring = [
            'tiba_ontime'   => $total_tiba ? ($customer_ontime / $total_tiba) * 100 : 0,
            'tiba_delay'    => $total_tiba ? ($customer_delay / $total_tiba) * 100 : 0,
            'bongkar_ontime' => $total_bongkar ? ($bongkar_ontime / $total_bongkar) * 100 : 0,
            'bongkar_delay' => $total_bongkar ? ($bongkar_delay / $total_bongkar) * 100 : 0,
        ];

        /* ================= PLANNER (FIX VIEW ERROR) ================= */
        $planner_ontime = $total_loading_ontime;
        $planner_delay  = $total_loading_delay;

        $total_planner = $planner_ontime + $planner_delay;

        $ontime_rate = $total_planner ? ($planner_ontime / $total_planner) * 100 : 0;
        $delay_rate  = $total_planner ? ($planner_delay / $total_planner) * 100 : 0;

        $planner_armada = $armada;
        $planner_belum_armada = $belum_armada;

        $armada_rate = ($armada + $belum_armada)
            ? ($armada / ($armada + $belum_armada)) * 100
            : 0;

        /* ================= CHART DATA (OPTIONAL SAFE) ================= */
        $label = [];
        $value = [];

        return view('dashboard', compact(
            'total_data',
            'total_loading_ontime',
            'total_loading_delay',
            'armada',
            'belum_armada',
            'process',

            'totalNilaiMuatan',
            'totalBiayaKirim',

            'summary_area',
            'summary_tujuan',
            'list_area',

            'gudang_ontime',
            'list_dist_channel',
            'gudang_delay',

            'customer_ontime',
            'customer_delay',

            'bongkar_ontime',
            'bongkar_delay',

            'summary_monitoring',

            'planner_ontime',
            'planner_delay',
            'ontime_rate',
            'delay_rate',
            'planner_armada',
            'planner_belum_armada',
            'armada_rate',

            'label',
            'value'
        ));
    }
    /* =========================================================
     * IMPORT EXCEL
     * ========================================================= */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);

        Excel::import(new LogistikImport, $request->file('file'));

        return back()->with('success', 'Import berhasil');
    }

    /* =========================================================
     * DATA LOGISTIK
     * ========================================================= */
    // public function dataLogistik()
    // {
    //     $logistik = LogistikPengiriman::orderBy('id', 'DESC')->get();
    //     return view('data_logistik', compact('logistik'));
    // }

//     public function dataLogistik(Request $request)
//     {
//         $query = LogistikPengiriman::query();

//         // ================= FILTER HARI =================
//         if ($request->date) {

//             $query->whereDate(
//                 'tanggal_naik_logistik',
//                 $request->date
//             );
//         }

//         // ================= FILTER BULAN =================
//         if ($request->month) {
//             $query->whereMonth('tanggal_naik_logistik', $request->month);
//         }

//         // ================= FILTER TAHUN =================
//         if ($request->year) {
//             $query->whereYear('tanggal_naik_logistik', $request->year);
//         }

//         // ================= FILTER PIC =================
//         if ($request->pic_monitoring) {

//             $query->where(
//                 'pic_monitoring',
//                 $request->pic_monitoring
//             );
//         }

// if ($request->search) {

//     $query->where(function($q) use ($request){

//         $q->where('no_shipment', 'like', '%'.$request->search.'%')
//           ->orWhere('tujuan', 'like', '%'.$request->search.'%')
//           ->orWhere('ekpedisi', 'like', '%'.$request->search.'%')
//           ->orWhere('area', 'like', '%'.$request->search.'%');

//     });
// }
//         // ================= FILTER AREA =================
//         if ($request->area) {

//             $query->where(
//                 'area',
//                 $request->area
//             );
//         }

//         // ================= DATA =================
//         $logistik = $query
//             ->orderBy('id', 'DESC')
//             ->get();

//         // ================= LIST PIC =================
//         $picList = DB::table('logistik_pengiriman')

//             ->whereNotNull('pic_monitoring')

//             ->distinct()

//             ->pluck('pic_monitoring');

//         // ================= LIST AREA =================
//         $areaList = DB::table('logistik_pengiriman')

//             ->select('area')

//             ->whereNotNull('area')

//             ->distinct()

//             ->pluck('area');


//         // ================= ESTIMASI =================
//         $estimasiData = $logistik->map(function ($r) {

//             $keluar = (
//                 !empty($r->tanggal_keluar_gudang)
//                 &&
//                 $r->tanggal_keluar_gudang != 'mm/dd/yyyy'
//             )

//                 ? strtotime($r->tanggal_keluar_gudang)

//                 : null;

//             $leadtime = is_numeric($r->transport_lead_time)

//                 ? (int)$r->transport_lead_time

//                 : 0;

//             $estimasi = $keluar

//                 ? strtotime("+$leadtime days", $keluar)

//                 : null;

//             return [

//                 'no_shipment' => $r->no_shipment,

//                 'estimasi' => $estimasi

//                     ? date('Y-m-d', $estimasi)

//                     : null

//             ];
//         });

//         return view(
//             'data_logistik',
//             compact(
//                 'logistik',
//                 'estimasiData',
//                 'picList',
//                 'areaList'
//             )
//         );
//     }


private function filterByDistChannel($query)
{
    $channel = session('dist_channel');

    if ($channel) {
        $query->whereRaw('LOWER(TRIM(dist_channel)) = ?', [$channel]);
    }

    return $query;
}

public function dataLogistik(Request $request)
{
    $query = LogistikPengiriman::query();

      $this->filterByDistChannel($query);

    /* ================= FILTER ================= */
    if ($request->date) {
        $query->whereDate('tanggal_naik_logistik', $request->date);
    }

    if ($request->month) {
        $query->whereMonth('tanggal_naik_logistik', $request->month);
    }

    if ($request->year) {
        $query->whereYear('tanggal_naik_logistik', $request->year);
    }

    if ($request->pic_monitoring) {
        $query->where('pic_monitoring', $request->pic_monitoring);
    }

    if ($request->area) {
        $query->where('area', $request->area);
    }

    if ($request->search) {
        $search = $request->search;

        $query->where(function ($q) use ($search) {
            $q->where('no_shipment', 'like', "%$search%")
              ->orWhere('tujuan', 'like', "%$search%")
              ->orWhere('ekspedisi', 'like', "%$search%")
              ->orWhere('area', 'like', "%$search%");
        });
    }

    /* ================= DATA ================= */
    $logistik = $query->orderBy('id', 'DESC')->get();

    /* ================= LIST DROPDOWN ================= */
    $picList = LogistikPengiriman::whereNotNull('pic_monitoring')
        ->distinct()
        ->pluck('pic_monitoring');

    $areaList = LogistikPengiriman::whereNotNull('area')
        ->distinct()
        ->pluck('area');

    /* ================= ESTIMASI + FORMAT TANGGAL ================= */
    $estimasiData = $logistik->map(function ($r) {

        /* ---------------- TANGGAL KELUAR ---------------- */
        $keluar = (!empty($r->tanggal_keluar_gudang) && $r->tanggal_keluar_gudang != 'mm/dd/yyyy')
            ? strtotime($r->tanggal_keluar_gudang)
            : null;

        $leadtime = is_numeric($r->transport_lead_time)
            ? (int) $r->transport_lead_time
            : 0;

        $estimasi = $keluar ? strtotime("+$leadtime days", $keluar) : null;

        /* ---------------- FORMAT TANGGAL ---------------- */
        $tglKeluar = !empty($r->tanggal_keluar_gudang)
            ? date('d-m-Y', strtotime($r->tanggal_keluar_gudang))
            : null;

        $tglNaik = !empty($r->tanggal_naik_logistik)
            ? date('d-m-Y', strtotime($r->tanggal_naik_logistik))
            : null;

        return [
            'no_shipment' => $r->no_shipment,

            // kalau kosong = NULL (bukan ONTIME/DELAY)
            'estimasi' => $estimasi ? date('d-m-Y', $estimasi) : null,

            'tanggal_keluar_gudang' => $tglKeluar,
            'tanggal_naik_logistik' => $tglNaik,
        ];
    });

    return view('data_logistik', compact(
        'logistik',
        'estimasiData',
        'picList',
        'areaList'
    ));
}

    public function archiveAll()
    {
        $data = DB::table('logistik_pengiriman')->get();

        foreach ($data as $row) {

            DB::table('logistik_storage')->insert([

                'no_shipment' => $row->no_shipment ?? null,
                'tanggal_naik_logistik' => $row->tanggal_naik_logistik ?? null,
                'rencana_kirim' => $row->rencana_kirim ?? null,
                'dist_channel' => $row->dist_channel ?? null,
                'tujuan' => $row->tujuan ?? null,
                'area' => $row->area ?? null,
                'nilai_muatan' => $row->nilai_muatan ?? 0,
                'biaya_kirim' => $row->biaya_kirim ?? 0,
                'kategori_ekspedisi' => $row->kategori_ekspedisi ?? null,
                'ekspedisi' => $row->ekspedisi ?? null,
               
                'status_pengiriman' => $row->status_pengiriman ?? null,
                'status_gudang' => $row->status_gudang ?? null,
                'status_akhir' => $row->status_akhir ?? null,
                'sla_tiba' => $row->sla_tiba ?? null,
                'sla_bongkar' => $row->sla_bongkar ?? null,
               'total_do_qty_car' => $row->total_do_qty_car ?? 0,
                'overstay_days' => $row->overstay_days ?? 0,
                'tanggal_tiba_gudang' => $row->tanggal_tiba_gudang ?? null,
                'tanggal_keluar_gudang' => $row->tanggal_keluar_gudang ?? null,
                'tanggal_tiba' => $row->tanggal_tiba ?? null,
                'tanggal_bongkar' => $row->tanggal_bongkar ?? null,
                'remarks' => $row->remarks ?? null,
                'reason_tiba' => $row->reason_tiba ?? null,
                'reason_bongkar' => $row->reason_bongkar ?? null,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
            ]);
        }

        DB::table('logistik_pengiriman')->delete();

        return back()->with('success', 'Data berhasil dipindahkan ke Storage');
    }

    public function deleteAll()
    {
        DB::table('logistik_pengiriman')->delete();

        return back()->with(
            'success',
            'Semua data berhasil dihapus'
        );
    }
    public function monitoring()
    {
        $logistik = LogistikPengiriman::latest()->get();

        $areaList = DB::table('logistik_pengiriman')
            ->whereNotNull('area')
            ->distinct()
            ->pluck('area');

        return view(
            'monitoring.data_monitoring',
            compact('logistik', 'areaList')
        );
    }

    /* =========================================================
     * SLA GENERIC
     * ========================================================= */
    // public function sla($type, Request $request)
    // {
    //     $query = LogistikPengiriman::query();

    //     if ($request->bulan) {
    //         $query->whereMonth('tanggal_naik_logistik', $request->bulan);
    //     }

    //     if ($request->area) {
    //         $query->where('area', $request->area);
    //     }

    //     if ($type == 'ontime') {

    //         $list = (clone $query)
    //             ->whereIn('status_akhir', ['On Time', 'Ontime'])
    //             ->get();

    //         return view('sla_ontime', [
    //             'list' => $list,
    //             'title' => 'ONTIME'
    //         ]);
    //     }

    //     if ($type == 'delay') {

    //         $list = (clone $query)
    //             ->whereIn('status_akhir', ['Delay', 'Critical Delay'])
    //             ->get();

    //         return view('sla_delay', [
    //             'list' => $list,
    //             'title' => 'DELAY'
    //         ]);
    //     }

    //     abort(404);
    // }

    /* =========================================================
     * ARMADA READY
     * ========================================================= */
    public function armada(Request $request)
    {
        $query = LogistikPengiriman::where('ketersediaan_unit', 'Sudah Dapat');

        if ($request->bulan) {
            $query->whereMonth('tanggal_naik_logistik', $request->bulan);
        }

        if ($request->tahun) {
            $query->whereYear('tanggal_naik_logistik', $request->tahun);
        }

        $logistik = $query->orderBy('id', 'DESC')->get();

        return view('armada', compact('logistik'));
    }


    public function edit($id)
    {
        $data['logistik'] = LogistikPengiriman::findOrFail($id);

        return view('edit', $data);
    }

    public function update(Request $request, $id)
    {
        $logistik = LogistikPengiriman::findOrFail($id);

        $logistik->update($request->all());

        return redirect('/logistik')->with('success', 'Data berhasil diupdate');
    }

    public function destroy($id)
    {
        $logistik = LogistikPengiriman::findOrFail($id);

        $logistik->delete();

        return redirect('/logistik')->with('success', 'Data berhasil dihapus');
    }

    /* =========================================================
     * BELUM ARMADA
     * ========================================================= */
    public function belumArmada(Request $request)
    {
        $query = LogistikPengiriman::where('ketersediaan_unit', 'Belum Dapat');

        if ($request->bulan) {
            $query->whereMonth('tanggal_naik_logistik', $request->bulan);
        }

        if ($request->tahun) {
            $query->whereYear('tanggal_naik_logistik', $request->tahun);
        }

        $logistik = $query->orderBy('id', 'DESC')->get();

        return view('belum_armada', compact('logistik'));
    }

    /* =========================================================
     * STORE
     * ========================================================= */
    public function store(Request $request)
    {
        LogistikPengiriman::create($request->all());

        return back()->with('success', 'Data berhasil ditambah');
    }

    /* =========================================================
     * UPDATE
     * ========================================================= */
    // public function update(Request $request, $id)
    // {
    //     $data = LogistikPengiriman::findOrFail($id);
    //     $data->update($request->all());

    //     return back()->with('success', 'Data berhasil diupdate');
    // }

    /* =========================================================
     * DELETE
     * ========================================================= */
    public function delete($id)
    {
        LogistikPengiriman::findOrFail($id)->delete();

        return back()->with('success', 'Data dihapus');
    }

    /* =========================================================
     * CHART
     * ========================================================= */
    public function chartStatus()
    {
        return LogistikPengiriman::select('status_akhir', DB::raw('COUNT(*) as total'))
            ->groupBy('status_akhir')
            ->get();
    }

    /* =========================================================
     * EXPORT CSV
     * ========================================================= */
    public function export()
    {
        $filename = 'logistik.csv';

        return response()->stream(function () {

            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'No',
                'Shipment',
                'Tujuan',
                'Area',
                'Status'
            ]);

            foreach (LogistikPengiriman::all() as $row) {
                fputcsv($handle, [
                    $row->id,
                    $row->no_shipment,
                    $row->tujuan,
                    $row->area,
                    $row->status_akhir
                ]);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$filename"
        ]);
    }

    /* =========================================================
 * SLA GLOBAL (ONTIME & DELAY)
 * ========================================================= */
    public function slaOntime(Request $request)
    {
        $query = LogistikPengiriman::query();

        if ($request->bulan) {
            $query->whereMonth('tanggal_naik_logistik', $request->bulan);
        }

        if ($request->tahun) {
            $query->whereYear('tanggal_naik_logistik', $request->tahun);
        }

        if ($request->area) {
            $query->where('area', $request->area);
        }

        $list = $query->where('status_akhir', 'On Time')->get();

        $list_area = LogistikPengiriman::select('area')->distinct()->get();

        $title = "SLA ONTIME";

        return view('sla_ontime', compact('list', 'list_area', 'title'));
    }

    public function index()
    {
        $total = LogistikPengiriman::count();

        $ontime = LogistikPengiriman::whereIn('status_akhir', ['On Time', 'Ontime'])
            ->count();

        $delay = LogistikPengiriman::whereIn('status_akhir', ['Delay', 'Critical Delay'])
            ->count();

        // OPTIONAL: kalau nanti kamu pakai summary area (biar tidak error lagi)
        $summary_area = LogistikPengiriman::select('area', DB::raw('COUNT(*) as total'))
            ->groupBy('area')
            ->orderByDesc('total')
            ->get();

        return view('dashboard', compact('total', 'ontime', 'delay', 'summary_area'));
    }

    public function slaDelay(Request $request)
    {
        $query = LogistikPengiriman::query();

        if ($request->bulan) {
            $query->whereMonth('tanggal_naik_logistik', $request->bulan);
        }

        if ($request->tahun) {
            $query->whereYear('tanggal_naik_logistik', $request->tahun);
        }

        if ($request->area) {
            $query->where('area', $request->area);
        }

        $list = $query->whereIn('status_akhir', ['Delay', 'Critical Delay'])->get();

        $list_area = LogistikPengiriman::select('area')->distinct()->get();

        $title = "SLA DELAY";

        return view('sla_delay', compact('list', 'list_area', 'title'));
    }

    public function dashboardPlanner()
    {
        $total = \DB::table('logistik_pengiriman')->count();

        $ontime = \DB::table('logistik_pengiriman')
            ->whereIn('status_akhir', ['On Time', 'Ontime'])
            ->count();

        $delay = \DB::table('logistik_pengiriman')
            ->whereIn('status_akhir', ['Delay', 'Critical Delay'])
            ->count();

        return view('planner.dashboard', compact('total', 'ontime', 'delay'));
    }

    public function ontimeCustomer(Request $request)
    {
        $bulan = $request->bulan;
        $tahun = $request->tahun;

        $query = DB::table('logistik_pengiriman_new');

        if ($bulan) {
            $query->whereMonth('tanggal_tiba', $bulan);
        }

        if ($tahun) {
            $query->whereYear('tanggal_tiba', $tahun);
        }

        $logistik = $query->get();

        foreach ($logistik as $row) {

            // ================= ONTIME CUSTOMER LOGIC =================
            $row->status_customer = 'DELAY';

            if (!empty($row->rencana_kirim) && !empty($row->tanggal_tiba)) {

                if (strtotime($row->tanggal_tiba) <= strtotime($row->rencana_kirim)) {
                    $row->status_customer = 'ONTIME';
                }
            }
        }

        return view('logistik.ontime_customer', compact('logistik'));
    }
// Rumus Dari lama gudang dan sla loading//
public static function kpi($query)
{
    return [
        'total_data' => (clone $query)->count(),

        'gudang_ontime' => (clone $query)->where(function($q){
            $q->where('sla_loading','H+0')
              ->orWhere('status','On Time');
        })->count(),

        'gudang_delay' => (clone $query)->where(function($q){
            $q->where('sla_loading','H+1')
              ->orWhere('status','Delay');
        })->count(),
    ];
}

public static function summaryArea($query)
{
    return (clone $query)
        ->select('area',
            DB::raw('COUNT(*) as total_shipment'),
            DB::raw('SUM(biaya_kirim) as total_biaya'),
            DB::raw('SUM(nilai_muatan) as total_muatan')
        )
        ->groupBy('area')
        ->get();
}
    public function dashboardSpv()
    {
        $data = LogistikPengiriman::all();
        return view('spv.dashboard', compact('data'));
    }

    public function dashboardManager(Request $request)
    {
        $date  = $request->date;
        $month = $request->month;
        $year  = $request->year;

        $base = DB::table('logistik_pengiriman');

        if ($date) {
            $base->whereDate('created_at', $date);
        }

        if ($month) {
            $base->whereMonth('created_at', substr($month, 5, 2))
                ->whereYear('created_at', substr($month, 0, 4));
        }

        if ($year) {
            $base->whereYear('created_at', $year);
        }

        // ================= TOTAL =================
        $total_data = (clone $base)->count();

        // ================= GUDANG =================
        $gudang_ontime = (clone $base)->whereRaw("LOWER(status) LIKE '%on%'")->count();
        $gudang_delay  = (clone $base)->whereRaw("LOWER(status) LIKE '%delay%'")->count();

        // ================= CUSTOMER =================
        // ================= CUSTOMER =================
        $customer_ontime = (clone $base)
            ->whereNotNull('tanggal_tiba')
            ->where('tanggal_tiba', '!=', '')
            ->whereIn('status_akhir', ['On Time', 'ONTIME'])
            ->count();

        $customer_delay = (clone $base)
            ->whereNotNull('tanggal_tiba')
            ->where('tanggal_tiba', '!=', '')
            ->whereIn('status_akhir', ['Delay', 'DELAY', 'Critical Delay'])
            ->count();


        // ================= BONGKAR =================
        $bongkar_ontime = (clone $base)
            ->whereNotNull('tanggal_bongkar')
            ->where('tanggal_bongkar', '!=', '')
            ->where('sla_bongkar', 'H+0')
            ->count();

        $bongkar_delay = (clone $base)
            ->whereNotNull('tanggal_bongkar')
            ->where('tanggal_bongkar', '!=', '')
            ->whereRaw("
        LOWER(sla_bongkar) LIKE '%delay%'
        OR LOWER(sla_bongkar) LIKE '%h+1%'
        OR LOWER(sla_bongkar) LIKE '%h+2%'
        OR LOWER(sla_bongkar) LIKE '%h>2%'
    ")
            ->count();

        // ================= AREA =================
        $summary_area = (clone $base)
            ->select(
                'area',
                DB::raw('COUNT(*) as total_shipment'),
                DB::raw("SUM(CASE WHEN biaya_kirim IS NULL THEN 0 ELSE biaya_kirim END) as total_biaya"),
                DB::raw("SUM(CASE WHEN nilai_muatan IS NULL THEN 0 ELSE nilai_muatan END) as total_muatan")
            )
            ->groupBy('area')
            ->get();

        // ================= TUJUAN =================
        $summary_tujuan = (clone $base)
            ->select(
                'tujuan',
                DB::raw('COUNT(*) as total_shipment'),
                DB::raw("SUM(biaya_kirim) as total_biaya"),
                DB::raw("SUM(nilai_muatan) as total_muatan")
            )
            ->groupBy('tujuan')
            ->get();

        // ================= EKSPEDISI =================
        $ekspedisi = (clone $base)
            ->select('kategori_ekspedisi', DB::raw('COUNT(*) as total'))
            ->groupBy('kategori_ekspedisi')
            ->get();

        $label = $ekspedisi->pluck('kategori_ekspedisi');
        $value = $ekspedisi->pluck('total');

        // ================= FINANCE =================
        $totalNilaiMuatan = (clone $base)->sum('nilai_muatan');
        $totalBiayaKirim  = (clone $base)->sum('biaya_kirim');

        // ================= PLANNER =================
        $planner_ontime = $gudang_ontime;
        $planner_delay  = $gudang_delay;
        $planner_armada = (clone $base)->whereNotNull('mobil')->count();
        $planner_belum_armada = (clone $base)->whereNull('mobil')->count();

        $total_tiba = (clone $base)
            ->whereNotNull('tanggal_tiba')
            ->where('tanggal_tiba', '!=', '')
            ->count();

        $total_bongkar = (clone $base)
            ->whereNotNull('tanggal_bongkar')
            ->where('tanggal_bongkar', '!=', '')
            ->count();

        $summary_monitoring = [

            'tiba_ontime' =>
            $total_tiba
                ? round(($customer_ontime / $total_tiba) * 100, 2)
                : 0,

            'tiba_delay' =>
            $total_tiba
                ? round(($customer_delay / $total_tiba) * 100, 2)
                : 0,

            'bongkar_ontime' =>
            $total_bongkar
                ? round(($bongkar_ontime / $total_bongkar) * 100, 2)
                : 0,

            'bongkar_delay' =>
            $total_bongkar
                ? round(($bongkar_delay / $total_bongkar) * 100, 2)
                : 0,

        ];


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
            'label',
            'value',
            'planner_ontime',
            'planner_delay',
            'planner_armada',
            'planner_belum_armada',
            'summary_monitoring'
        ));
    }

    private function dashboardData()
    {
        return [
            'total' => \DB::table('logistik_pengiriman')->count(),
            'ontime' => \DB::table('logistik_pengiriman')
                ->whereIn('status_akhir', ['On Time', 'Ontime'])->count(),
            'delay' => \DB::table('logistik_pengiriman')
                ->whereIn('status_akhir', ['Delay', 'Critical Delay'])->count(),
        ];
    }
}
