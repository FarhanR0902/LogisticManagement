<?php

namespace App\Http\Controllers\Spv;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\LogistikPengiriman;
use App\Models\LogistikPengirimanPasuruan;
use App\Models\TarifPengiriman;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PlannerExport;
use App\Models\TujuanFilter;
use App\Imports\PasuruanImport;
use App\Exports\PasuruanExport;
class SpvPlannerController extends Controller
{


// public function dashboard()
// {
//     // ================= TOTAL =================
//     $total_data = DB::table('logistik_pengiriman')->count();

//     // ================= ARMADA READY (sudah dapat unit) =================
//   $armada = DB::table('logistik_pengiriman')
//     ->whereNotNull('tanggal_dpt_unit')
//     ->where(function ($q) {
//         $q->whereNotNull('tanggal_tiba_gudang')
//           ->orWhereNotNull('tanggal_tiba_gudang_2')
//           ->orWhereNotNull('tanggal_tiba_gudang_3');
//     })
//     ->count();
//     // ================= BELUM ARMADA (belum dapat unit) =================
//  $belum_armada = DB::table('logistik_pengiriman')
//     ->where(function ($q) {
//         $q->whereNull('tanggal_tiba_gudang')
//           ->whereNull('tanggal_tiba_gudang_2')
//           ->whereNull('tanggal_tiba_gudang_3');
//     })
//     ->count();

//     // ================= SLA ONTIME (dapat unit same day rencana kirim) =================
//     // $ontime = DB::table('logistik_pengiriman')
//     //     ->whereNotNull('tanggal_dpt_unit')
//     //     ->whereNotNull('tanggal_tiba_gudang')
//     //     ->whereRaw('DATE(tanggal_dpt_unit) = DATE(tanggal_tiba_gudang)')
//     //     ->count();

//    $ontime = LogistikPengiriman::all()
// ->filter(function ($row) {

//     $tiba = collect([
//         $row->tanggal_tiba_gudang,
//         $row->tanggal_tiba_gudang_2,
//         $row->tanggal_tiba_gudang_3,
//     ])->filter()->sort()->first();

//     if (!$row->tanggal_dpt_unit || !$tiba) {
//         return false;
//     }

//     return strtotime(date('Y-m-d', strtotime($tiba)))
//         <=
//         strtotime(date('Y-m-d', strtotime($row->tanggal_dpt_unit)));
// })
// ->count();

//     // ================= SLA DELAY (dapat unit beda hari) =================
//     // $delay = DB::table('logistik_pengiriman')
//     //     ->whereNotNull('tanggal_dpt_unit')
//     //     ->whereNotNull('tanggal_tiba_gudang')
//     //     ->whereRaw('DATE(tanggal_tiba_gudang) > DATE(tanggal_dpt_unit)')
//     //     ->count();

//    $delay = LogistikPengiriman::all()
// ->filter(function ($row) {

//     $tiba = collect([
//         $row->tanggal_tiba_gudang,
//         $row->tanggal_tiba_gudang_2,
//         $row->tanggal_tiba_gudang_3,
//     ])->filter()->sort()->first();

//     if (!$row->tanggal_dpt_unit || !$tiba) {
//         return false;
//     }

//     return strtotime(date('Y-m-d', strtotime($tiba)))
//         >
//         strtotime(date('Y-m-d', strtotime($row->tanggal_dpt_unit)));
// })
// ->count();
//     // ================= SUMMARY AREA =================
//     $summary_area = DB::table('logistik_pengiriman')
//         ->select('area', DB::raw('COUNT(*) as total'))
//         ->whereNotNull('area')
//         ->groupBy('area')
//         ->orderByDesc('total')
//         ->limit(10)
//         ->get();

//     return view('spvplanner.dashboard', compact(
//         'total_data',
//         'ontime',
//         'delay',
//         'armada',
//         'belum_armada',
//         'summary_area'
//     ));
// }
// // public function dashboard()
// // {
// //     // ================= TOTAL =================
// //     $total_data = DB::table('logistik_pengiriman')->count();

// //     // ================= ONTIME / DELAY (PAKAI STATUS ASLI) =================
// // $ontime = DB::table('logistik_pengiriman')
// //     ->where('status_akhir', 'On Time')
// //     ->count();

// // $delay = DB::table('logistik_pengiriman')
// //     ->where('status_akhir', 'Delay')
// //     ->count();
// //     // ================= ARMADA READY =================
// //     $armada = DB::table('logistik_pengiriman')
// //         ->where(function ($q) {
// //             $q->whereIn('ketersediaan_unit', ['Sudah Dapat', 'READY', 'Ready'])
// //               ->orWhereIn('status_kendaraan', ['Sudah Dapat', 'READY', 'Ready']);
// //         })
// //         ->count();

// //     // ================= BELUM ARMADA =================
// //     $belum_armada = DB::table('logistik_pengiriman')
// //         ->where(function ($q) {
// //             $q->whereIn('ketersediaan_unit', ['Belum Dapat', 'Pending', 'PENDING'])
// //               ->orWhereIn('status_kendaraan', ['Belum Dapat', 'Pending', 'PENDING']);
// //         })
// //         ->count();

// //     // ================= SUMMARY AREA =================
// //     $summary_area = DB::table('logistik_pengiriman')
// //         ->select('area', DB::raw('COUNT(*) as total'))
// //         ->whereNotNull('area')
// //         ->groupBy('area')
// //         ->orderByDesc('total')
// //         ->limit(10)
// //         ->get();

// //     return view('spvplanner.dashboard', compact(
// //         'total_data',
// //         'ontime',
// //         'delay',
// //         'armada',
// //         'belum_armada',
// //         'summary_area'
// //     ));
// // }
public function importPasuruan(Request $request)
{
    $request->validate([
        'file' => 'required|mimes:xlsx,xls,csv|max:10240',
    ]);

    Excel::import(
        new PasuruanImport,
        $request->file('file')
    );

    return redirect()
    ->route('spvplanner.data.pasuruan')
    ->with('success', 'Data logistik Pasuruan berhasil diimport.');
}

public function tarifIndex(Request $request)
{
    if (!auth()->check() || auth()->user()->role !== 'spvplanner') {
        abort(403, 'Anda tidak memiliki akses ke halaman ini.');
    }

    $query = TarifPengiriman::query();

 if ($request->filled('search')) {

    $search = $request->search;

    $query->where(function ($q) use ($search) {

        $q->where('Div', 'like', "%{$search}%")
            ->orWhere('customer_id', 'like', "%{$search}%")
            ->orWhere('tujuan', 'like', "%{$search}%")
            ->orWhere('dist_channel', 'like', "%{$search}%")
            ->orWhere('pulau', 'like', "%{$search}%")
            ->orWhere('area', 'like', "%{$search}%")
            ->orWhere('Planner', 'like', "%{$search}%")
            ->orWhere('Monitoring', 'like', "%{$search}%")
            ->orWhere('biaya_kuli', 'like', "%{$search}%")
            ->orWhere('transport_lead_time', 'like', "%{$search}%");

    });
}

    $data = $query
        ->orderByDesc('id')
        ->paginate(20)
        ->withQueryString();

    return view('spvplanner.tarif_pengiriman.index', compact('data'));
}

public function tarifCreate()
{
    if (!auth()->check() || auth()->user()->role !== 'spvplanner') {
        abort(403, 'Anda tidak memiliki akses ke halaman ini.');
    }

    return view('spvplanner.tarif_pengiriman.create');
}

public function tarifStore(Request $request)
{
    if (!auth()->check() || auth()->user()->role !== 'spvplanner') {
        abort(403, 'Anda tidak memiliki akses ke halaman ini.');
    }

    $validated = $request->validate([
        'servc_agent' => 'nullable|string|max:10',
        'ekpedisi' => 'nullable|string|max:100',
        'sh' => 'nullable|string|max:10',
        'mobil' => 'nullable|string|max:50',
        'routew' => 'nullable|string|max:20',
        'route' => 'nullable|string|max:100',
        'biaya_kirim' => 'nullable|string|max:30',
        'unit' => 'nullable|string|max:10',
        'per' => 'nullable|string|max:10',
        'uom' => 'nullable|string|max:10',
        'd' => 'nullable|string|max:10',
        'tx' => 'nullable|string|max:10',
        'e' => 'nullable|string|max:10',
        's_1' => 'nullable|string|max:10',
        's_2' => 'nullable|string|max:10',
        'valid_from' => 'nullable|string|max:20',
        'valid_to' => 'nullable|string|max:20',
    ]);

    TarifPengiriman::create($validated);

    return redirect()
        ->route('spvplanner.tarif.index')
        ->with('success', 'Data tarif berhasil ditambahkan.');
}

public function tarifEdit($id)
{
    if (!auth()->check() || auth()->user()->role !== 'spvplanner') {
        abort(403, 'Anda tidak memiliki akses ke halaman ini.');
    }

    $data = TarifPengiriman::findOrFail($id);

    return view('spvplanner.tarif_pengiriman.edit', compact('data'));
}

public function tarifUpdate(Request $request, $id)
{
    if (!auth()->check() || auth()->user()->role !== 'spvplanner') {
        abort(403, 'Anda tidak memiliki akses ke halaman ini.');
    }

    $data = TarifPengiriman::findOrFail($id);

    $validated = $request->validate([
        'servc_agent' => 'nullable|string|max:10',
        'ekpedisi' => 'nullable|string|max:100',
        'sh' => 'nullable|string|max:10',
        'mobil' => 'nullable|string|max:50',
        'routew' => 'nullable|string|max:20',
        'route' => 'nullable|string|max:100',
        'biaya_kirim' => 'nullable|string|max:30',
        'unit' => 'nullable|string|max:10',
        'per' => 'nullable|string|max:10',
        'uom' => 'nullable|string|max:10',
        'd' => 'nullable|string|max:10',
        'tx' => 'nullable|string|max:10',
        'e' => 'nullable|string|max:10',
        's_1' => 'nullable|string|max:10',
        's_2' => 'nullable|string|max:10',
        'valid_from' => 'nullable|string|max:20',
        'valid_to' => 'nullable|string|max:20',
    ]);

    $data->update($validated);

    return redirect()
        ->route('spvplanner.tarif.index')
        ->with('success', 'Data tarif berhasil diperbarui.');
}

public function tarifDestroy($id)
{
    if (!auth()->check() || auth()->user()->role !== 'spvplanner') {
        abort(403, 'Anda tidak memiliki akses ke halaman ini.');
    }

    $data = TarifPengiriman::findOrFail($id);

    $data->delete();

    return redirect()
        ->route('spvplanner.tarif.index')
        ->with('success', 'Data tarif berhasil dihapus.');
}

public function exportPasuruan()
{
    return Excel::download(
        new PasuruanExport(),
        'Data_Logistik_Pasuruan.xlsx'
    );
}

    private function applyFilter($query, $request)
    {

        // AREA

        if ($request->area) {

            $query->where('area', $request->area);
        }

        if ($request->dist_channel) {
        $query->where('dist_channel', $request->dist_channel);
    }
      if ($request->filled('pulau') && isset(self::PULAU_MAP[$request->pulau])) {
        $query->whereIn('area', self::PULAU_MAP[$request->pulau]);
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
   private const PULAU_MAP = [
        'JAWA'       => ['JABODEBEK','BANTEN','JAWA_BARAT','JAWA_TENGAH','JAWA_TIMUR','YOGYAKARTA'],
        'SUMATERA'   => ['ACEH','SUMATERA_UTARA','SUMATERA_BARAT','RIAU','KEP._RIAU','JAMBI','SUMATERA_SELATAN','BENGKULU','LAMPUNG','KEP._BANGKA_BELITUNG'],
        'KALIMANTAN' => ['KALIMANTAN_BARAT','KALIMANTAN_TENGAH','KALIMANTAN_SELATAN','KALIMANTAN_TIMUR','KALIMANTAN_UTARA'],
        'SULAWESI'   => ['SULAWESI_UTARA','SULAWESI_TENGAH','SULAWESI_SELATAN','SULAWESI_TENGGARA','SULAWESI_BARAT','GORONTALO'],
        'BALI_NUSRA' => ['PROV._BALI','NUSA_TENGGARA_BARAT','NUSA_TENGGARA_TIMUR'],
        'MALUKU'     => ['PROV._MALUKU','PROV._MALUKU_UTARA'],
        'PAPUA'      => ['PROV._PAPUA','PAPUA_BARAT','PAPUA_BARAT_DAYA','PAPUA_SELATAN','PAPUA_TENGAH'],
    ];

private function applyFilterPasuruan($query, $request)
{
    if ($request->area) {
        $query->where('area_pasuruan', $request->area);
    }
      if ($request->filled('pulau') && isset(self::PULAU_MAP[$request->pulau])) {
    $query->whereIn('area_pasuruan', self::PULAU_MAP[$request->pulau]);
}

    if ($request->dist_channel) {
        $query->where('dist_channel_pasuruan', $request->dist_channel);
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

     public function dashboardPasuruan(Request $request)
{
    $base = DB::table('logistik_pengiriman_pasuruan');

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

    // pakai getArea() yang sudah ada (tabel logistik_pengiriman, dipakai sbg master area)
    $list_area = $this->getArea();

    return view('spvplanner.dashboard_pasuruan', compact(
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

public function dataLogistikPasuruan()
{
    $logistik = LogistikPengirimanPasuruan::orderByDesc('id')->get();

    $planners = LogistikPengirimanPasuruan::select('planner_pasuruan')
        ->whereNotNull('planner_pasuruan')
        ->where('planner_pasuruan', '!=', '')
        ->distinct()
        ->orderBy('planner_pasuruan')
        ->pluck('planner_pasuruan');

    $areas = LogistikPengirimanPasuruan::select('area_pasuruan')
        ->whereNotNull('area_pasuruan')
        ->where('area_pasuruan', '!=', '')
        ->distinct()
        ->orderBy('area_pasuruan')
        ->pluck('area_pasuruan');

    return view('spvplanner.data_logistik_pasuruan', compact(
        'logistik',
        'planners',
        'areas'
    ));
}




 public function Fulldashboard(Request $request)
{

    // ================= BASE QUERY =================

    $base = DB::table('logistik_pengiriman');

    $this->applyFilter($base, $request);

    // ================= TOTAL =================

    $total_data = (clone $base)->count();

    // ================= GUDANG =================

    // Sama seperti gudangOntime() — FIXED: hapus extra whereNotNull('tanggal_dpt_unit')
    // biar angka di dashboard match sama halaman detail
    $gudang_ontime = (clone $base)
        ->where(function ($q) {
            $q->whereNotNull('tanggal_tiba_gudang')
              ->orWhereNotNull('tanggal_tiba_gudang_2')
              ->orWhereNotNull('tanggal_tiba_gudang_3');
        })
        ->count();

    // Sama seperti gudangDelay() (versi yang bener, sudah digabung)
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


    // ================= TUJUAN / CUSTOMER =================
    // Sama seperti tujuanOntime() / tujuanDelay() — cocok, gak diubah

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


    // ================= BONGKAR =================
    // Sama seperti bongkarOntime() / bongkarDelay() — cocok, gak diubah

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
    // Sama seperti armada() / belumArmada() — cocok, gak diubah

    $planner_armada = (clone $base)
        ->whereNotNull('rencana_kirim')
        ->whereRaw("TRIM(rencana_kirim) <> ''")
        ->whereNotNull('tanggal_dpt_unit')
        ->whereRaw("TRIM(tanggal_dpt_unit) <> ''")
        ->count();

    $planner_belum_armada = (clone $base)
        ->where(function ($q) {
            $q->whereNull('rencana_kirim')
              ->orWhere('rencana_kirim', '')
              ->orWhereNull('tanggal_dpt_unit')
              ->orWhere('tanggal_dpt_unit', '');
        })
        ->count();

    $list_dist_channel = (clone $base)
        ->select('dist_channel')
        ->whereNotNull('dist_channel')
        ->distinct()
        ->orderBy('dist_channel')
        ->get();


    // ================= PLANNER =================
    // Gak ada halaman detail pembanding, dibiarin apa adanya

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

    // ================= TOTAL NILAI MUATAN =================
    // Sudah benar dari awal: SUM() langsung ke kolom DECIMAL, tanpa REPLACE.

    $totalNilaiMuatan = (clone $base)->sum('nilai_muatan');

    $totalBiayaKirim = (clone $base)
        ->selectRaw("SUM(biaya_kirim) as total")
        ->value('total');


    // ================= SUMMARY AREA (FIXED) =================
    // SEBELUM: pakai CAST(REPLACE(nilai_muatan,'.','')...) -> salah, karena
    // nilai_muatan & biaya_kirim itu DECIMAL asli, bukan string "15.493.325.750".
    // REPLACE tadi ikut menghapus titik desimal -> angka jadi 100x lipat
    // (mis. 15.493.325.750 jadi kebaca 1.549.332.575.000).
    // SESUDAH: SUM() langsung, sama seperti summaryArea() & dashboardPasuruan()
    // yang memang sudah benar dari awal.

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


    // ================= SUMMARY TUJUAN (FIXED) =================
    // Fix yang sama persis seperti $summary_area di atas.

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


    // ================= SUMMARY PULAU (BARU - Nilai Muatan & CR per Pulau) =================
    // Dipakai untuk section "Nilai Muatan & CR per Pulau" di dashboard.blade.php
    // (chart bar Nilai Muatan + line CR%, plus tabel detail per pulau).
    //
    // ASUMSI: nama kolom pulau di tabel ini adalah 'pulau'. Kalau ternyata
    // beda, ganti semua 'pulau' di bawah ke nama kolom yang benar.
    //
    // Kolom 'pulau' sengaja di-alias jadi 'pulau_pasuruan' di hasil query
    // supaya blade (yang sudah nulis $p->pulau_pasuruan) tidak perlu diubah.
    $summary_pulau = DB::table('logistik_pengiriman')
        ->when($request->filled('bulan'), function ($q) use ($request) {
            $q->whereMonth('tanggal_naik_logistik', $request->bulan);
        })
        ->when($request->filled('tahun'), function ($q) use ($request) {
            $q->whereYear('tanggal_naik_logistik', $request->tahun);
        })
        ->when($request->filled('area'), function ($q) use ($request) {
            $q->where('area', $request->area);
        })
        ->when($request->filled('dist_channel'), function ($q) use ($request) {
            $q->where('dist_channel', $request->dist_channel);
        })
        ->select(
            'pulau',
            DB::raw('COUNT(DISTINCT no_shipment) AS total_shipment'),
            DB::raw('SUM(nilai_muatan) AS total_muatan'),
            DB::raw('SUM(biaya_kirim) AS total_biaya')
        )
        ->whereNotNull('pulau')
        ->whereRaw("TRIM(pulau) <> ''")
        ->groupBy('pulau')
        ->orderByDesc('total_muatan')
        ->get();

    $label_pulau        = $summary_pulau->pluck('pulau');
    $value_muatan_pulau = $summary_pulau->pluck('total_muatan');
    $value_biaya_pulau  = $summary_pulau->pluck('total_biaya');

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

    $list_area = $this->getArea();


    // ================= RETURN =================

    return view('spvplanner.dashboard_full', compact(
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
        'list_area',

        // BARU: untuk section Nilai Muatan & CR per Pulau
        'summary_pulau',
        'label_pulau',
        'value_muatan_pulau',
        'value_biaya_pulau'

    ));
}

public function store(Request $request)
{

$request->merge([
        // 'rencana_kirim' => $this->hitungRencanaKirim($request)
    ]);

  
    $rumus = $this->hitungSla($request);

    $data = $request->only([
        'create_tgl',
        'no_shipment',
        'planner',
        'dist_channel',
        'transport_lead_time',
        'tujuan',
        'area',
        'ketersediaan_unit',
        'mobil',
        'perubahan_mobil',
        'nilai_muatan',
        'biaya_kirim',
        'cr',
        'kategori_ekspedisi',
        'ekpedisi',
       
        'tanggal_naik_logistik',
        'rencana_kirim',
        'tanggal_dpt_unit',
        'planning_loading',
        'tanggal_tiba_gudang',
        'tanggal_keluar_gudang',
        'planning_loading_2',
        'tanggal_tiba_gudang_2',
        'tanggal_keluar_gudang_2',
        'tanggal_tiba_gudang_3',
        'planning_loading_3',
        'tanggal_keluar_gudang_3',
        'keterangan',
        'route',
        'pulau',
        'via_kirim'
    ]);

    LogistikPengiriman::create(array_merge($data, $rumus));

    return back()->with('success', 'Data berhasil disimpan');
}


public function dashboard()
{
    // =====================================================
    // AMBIL DATA UNIQUE PER NO_SHIPMENT (ANTI DUPLICATE)
    // =====================================================
    $shipments = DB::table('logistik_pengiriman')
        ->orderBy('no_shipment')
        ->get()
        ->groupBy('no_shipment')
        ->map(function ($group) {
            return $group->first(); // 1 shipment saja
        });

    // =====================================================
    // TOTAL DATA (UNIQUE SHIPMENT)
    // =====================================================
    $total_data = $shipments->count();

    // =====================================================
    // ARMADA READY (SUDAH DAPAT UNIT + ADA TIBA GUDANG)
    // =====================================================
   $armada = $shipments->filter(function ($row) {

    return !empty($row->rencana_kirim)
        && !empty($row->tanggal_dpt_unit);

})->count();

    // =====================================================
    // BELUM ARMADA
    // =====================================================
$belum_armada = $shipments->filter(function ($row) {

    return empty($row->rencana_kirim)
        || empty($row->tanggal_dpt_unit);

})->count();

    // =====================================================
    // SLA ONTIME (ADA TANGGAL TIBA GUDANG)
    // =====================================================
    $ontime = $shipments->filter(function ($row) {
        return !empty($row->tanggal_tiba_gudang)
            || !empty($row->tanggal_tiba_gudang_2)
            || !empty($row->tanggal_tiba_gudang_3);
    })->count();

    // =====================================================
    // SLA DELAY (SUDAH ADA TANGGAL DPT UNIT, TAPI BELUM ADA TIBA GUDANG)
    // =====================================================
    $delay = $shipments->filter(function ($row) {
        return !empty($row->tanggal_dpt_unit)
            && empty($row->tanggal_tiba_gudang)
            && empty($row->tanggal_tiba_gudang_2)
            && empty($row->tanggal_tiba_gudang_3);
    })->count();

    // =====================================================
    // SUMMARY AREA (UNIQUE SHIPMENT PER AREA)
    // =====================================================
    $summary_area = $shipments
        ->groupBy('area')
        ->map(function ($group) {
            return count($group);
        })
        ->sortDesc()
        ->take(10);

    // =====================================================
    // RETURN VIEW
    // =====================================================
    return view('spvplanner.dashboard', compact(
        'total_data',
        'ontime',
        'delay',
        'armada',
        'belum_armada',
        'summary_area'
    ));
}



public function archive()
{
    DB::transaction(function () {

        $data = DB::table('logistik_pengiriman_pasuruan')->get();

        // Kalau tidak ada data, tidak melakukan apa-apa
        if ($data->isEmpty()) {
            return;
        }

        // Pindahkan data ke storage
        DB::table('logistik_pengiriman_pasuruan_storage')
            ->insert(
                $data->map(fn ($row) => (array) $row)->toArray()
            );

        // Hapus data dari tabel utama setelah berhasil masuk storage
        DB::table('logistik_pengiriman_pasuruan')->delete();
    });

    return redirect()
        ->route('spvplanner.data.pasuruan')
        ->with('success', 'Semua data berhasil dipindahkan ke Storage.');
}


// public function update(Request $request, $id)
// {
    
    

    
//     $rumus = $this->hitungSla($request);
    

//     // 1. Tampung data yang murni berasal dari form web (bukan data master Excel)
//     $updateData = [
        
//         'tanggal_naik_logistik'   => $request->tanggal_naik_logistik,
//         'rencana_kirim'           => $request->rencana_kirim,
//         'transport_lead_time'     => $request->transport_lead_time,
//         'spvplanner'                 => $request->spvplanner,
//         'no_shipment'             => $request->no_shipment,
//         'ketersediaan_unit'       => $request->ketersediaan_unit,
//         'perubahan_mobil'         => $request->perubahan_mobil,
//         'nilai_muatan'            => $request->nilai_muatan,
//         'biaya_kirim'             => $request->biaya_kirim,
//         'cr' => $this->cleanCr($request->cr),
//         'kategori_ekspedisi'      => $request->kategori_ekspedisi,

//         'keterangan'              => $request->keterangan,

//         // Input tanggal & Hasil Rumus SLA otomatis
//         'tanggal_dpt_unit'        => $request->tanggal_dpt_unit,
//         'planning_loading'        => $request->planning_loading,
//          'planning_loading_2'        => $request->planning_loading_2,
//           'planning_loading_3'        => $request->planning_loading_3,
//       'tanggal_tiba_gudang'     => $request->tanggal_tiba_gudang,
// 'tanggal_keluar_gudang'   => $request->tanggal_keluar_gudang,

// 'tanggal_tiba_gudang_2'   => $request->tanggal_tiba_gudang_2,
// 'tanggal_keluar_gudang_2' => $request->tanggal_keluar_gudang_2,

// 'tanggal_tiba_gudang_3'   => $request->tanggal_tiba_gudang_3,
// 'tanggal_keluar_gudang_3' => $request->tanggal_keluar_gudang_3,

//         'lama_waktu_pencarian'    => $rumus['lama_waktu_pencarian'],
//         'sla_dapat_mobil'         => $rumus['sla_dapat_mobil'],
//         'status_pengiriman'       => $rumus['status_pengiriman'],

//         'lama_digudang'           => $rumus['lama_digudang'],
//         'status_gudang'                  => $rumus['status_gudang'],
//         'sla_loading'             => $rumus['sla_loading'],
//          'lama_digudang_2'           => $rumus['lama_digudang_2'],
//         'status_gudang_2'                  => $rumus['status_gudang_2'],
//         'sla_loading_2'             => $rumus['sla_loading_2'],
//          'lama_digudang_3'           => $rumus['lama_digudang_3'],
//         'status_gudang_3'                  => $rumus['status_gudang_3'],
//         'sla_loading_3'             => $rumus['sla_loading_3'],

        
//         'updated_at'              => now()
//     ];

//     // 2. PROTEKSI KRITIS: Data Excel hanya masuk ke antrean update JIKA form di web diisi
//     if ($request->filled('tujuan'))    $updateData['tujuan'] = $request->tujuan;
//     if ($request->filled('ekpedisi'))  $updateData['ekpedisi'] = $request->ekpedisi;
//     if ($request->filled('route'))     $updateData['route'] = $request->route;
//     if ($request->filled('mobil'))     $updateData['mobil'] = $request->mobil;
//     if ($request->filled('pulau'))     $updateData['pulau'] = $request->pulau;
//     if ($request->filled('area'))      $updateData['area'] = $request->area;
//     if ($request->filled('via_kirim')) $updateData['via_kirim'] = $request->via_kirim;
    
//     $updateData['nilai_muatan'] = $this->cleanMoney($request->nilai_muatan);
// $updateData['biaya_kirim'] = $this->cleanMoney($request->biaya_kirim);

//     // 3. Eksekusi SATU KALI SAJA ke database menggunakan array yang aman
//     DB::table('logistik_pengiriman')
//         ->where('id', $id)
//         ->update($updateData);
        
//     return back()->with('success', 'Data berhasil disimpan');
// }


// public function update(Request $request, $id)
// {
//     // =========================
//     // 1. HITUNG SLA
//     // =========================
//     $rumus = $this->hitungSla($request);

//     // =========================
//     // 2. BUILD DATA UPDATE
//     // =========================
//     $updateData = [
//         'tanggal_naik_logistik' => $request->tanggal_naik_logistik,
//         'rencana_kirim'         => $request->rencana_kirim,
//         'transport_lead_time'   => $request->transport_lead_time,
//         'spvplanner'               => $request->spvplanner,
//         'no_shipment'           => $request->no_shipment,

//         'ketersediaan_unit'     => $request->ketersediaan_unit,
//         'perubahan_mobil'       => $request->perubahan_mobil,

//         'kategori_ekspedisi'    => $request->kategori_ekspedisi,
//         'keterangan'            => $request->keterangan,

//         // TANGGAL
//         'tanggal_dpt_unit'      => $request->tanggal_dpt_unit,
//         'planning_loading'      => $request->planning_loading,
//         'planning_loading_2'    => $request->planning_loading_2,
//         'planning_loading_3'    => $request->planning_loading_3,

//         'tanggal_tiba_gudang'   => $request->tanggal_tiba_gudang,
//         'tanggal_keluar_gudang' => $request->tanggal_keluar_gudang,

//         'tanggal_tiba_gudang_2'   => $request->tanggal_tiba_gudang_2,
//         'tanggal_keluar_gudang_2' => $request->tanggal_keluar_gudang_2,

//         'tanggal_tiba_gudang_3'   => $request->tanggal_tiba_gudang_3,
//         'tanggal_keluar_gudang_3' => $request->tanggal_keluar_gudang_3,

//         // =========================
//         // SLA RESULT (AMAN STRING)
//         // =========================
//         'lama_waktu_pencarian' => (string) ($rumus['lama_waktu_pencarian'] ?? ''),
//         'sla_dapat_mobil'      => $rumus['sla_dapat_mobil'] ?? null,
//         'status_pengiriman'    => $rumus['status_pengiriman'] ?? null,

//         'lama_digudang'        => substr((string) ($rumus['lama_digudang'] ?? ''), 0, 50),
//         'status_gudang'        => $rumus['status_gudang'] ?? null,
//         'sla_loading'          => $rumus['sla_loading'] ?? null,

//         'lama_digudang_2'      => substr((string) ($rumus['lama_digudang_2'] ?? ''), 0, 50),
//         'status_gudang_2'      => $rumus['status_gudang_2'] ?? null,
//         'sla_loading_2'        => $rumus['sla_loading_2'] ?? null,

//         'lama_digudang_3'      => substr((string) ($rumus['lama_digudang_3'] ?? ''), 0, 50),
//         'status_gudang_3'      => $rumus['status_gudang_3'] ?? null,
//         'sla_loading_3'        => $rumus['sla_loading_3'] ?? null,

//         'updated_at'           => now(),
//     ];

//     // =========================
//     // 3. FIELD OPTIONAL (SAFE UPDATE)
//     // =========================
//     $optionalFields = [
//         'tujuan',
//         'ekpedisi',
//         'route',
//         'mobil',
//         'pulau',
//         'area',
//         'via_kirim',
//         'dist_channel',
//         'ekpedisi',
//         'transport_lead_time'
//     ];

//     foreach ($optionalFields as $field) {
//         if ($request->filled($field)) {
//             $updateData[$field] = $request->$field;
//         }
//     }

//     // =========================
//     // 4. CLEAN MONEY (WAJIB FINAL OVERRIDE)
//     // =========================
//     $updateData['nilai_muatan'] = $this->cleanMoney($request->nilai_muatan);
//     $updateData['biaya_kirim']  = $this->cleanMoney($request->biaya_kirim);

//     // =========================
//     // 5. CR CLEAN (ANTI SCIENTIFIC NOTATION)
//     // =========================
//     $updateData['cr'] = $this->cleanCr($request->cr);

//     // =========================
//     // 6. EXECUTE UPDATE
//     // =========================
//     DB::table('logistik_pengiriman')
//         ->where('id', $id)
//         ->update($updateData);

//     return back()->with('success', 'Data berhasil disimpan');
// }


// public function update(Request $request, $id)
// {

// $old = DB::table('logistik_pengiriman')
//     ->where('id', $id)
//     ->first();

// $request->merge([
//     'area' => $old->area
// ]);

// // ambil tanggal keluar gudang paling besar
// $keluar = collect([
//     $request->tanggal_keluar_gudang,
//     $request->tanggal_keluar_gudang_2,
//     $request->tanggal_keluar_gudang_3,
// ])
// ->filter()
// ->map(fn($v) => strtotime($v))
// ->max();

// if ($keluar && $request->transport_lead_time) {

//     $estimasi = date(
//         'Y-m-d',
//         strtotime(
//             '+' . (int)$request->transport_lead_time . ' days',
//             $keluar
//         )
//     );

//     $request->merge([
//         'estimasi_tiba' => $estimasi
//     ]);
// }

// // $rumus = $this->hitungSla($request);

//     if (!$old) {
//         return back()->with('error', 'Data tidak ditemukan');
//     }

//     // pakai area dari database
//     $request->merge([
//         'area' => $old->area
//     ]);


//     $rumus = $this->hitungSla($request);

//     // ambil data lama dulu
//     $old = DB::table('logistik_pengiriman')->where('id', $id)->first();

//     if (!$old) {
//         return back()->with('error', 'Data tidak ditemukan');
//     }

//     $oldNoShipment = $old->no_shipment;
//     $newNoShipment = $request->no_shipment;
// $updateData = [

//     'estimasi_tiba'        => $request->estimasi_tiba,
//     'tanggal_naik_logistik'=> $request->tanggal_naik_logistik,
//     'rencana_kirim'        => $request->rencana_kirim,
//     'transport_lead_time'  => $request->transport_lead_time,
//     'spvplanner'              => $request->spvplanner,
//     'no_shipment'          => $newNoShipment,

//     'perubahan_mobil'      => $request->perubahan_mobil,
//     'kategori_ekspedisi'   => $request->kategori_ekspedisi,
//     'keterangan'           => $request->keterangan,

//     'tanggal_dpt_unit'     => $request->tanggal_dpt_unit,
//     'planning_loading'     => $request->planning_loading,
//     'planning_loading_2'   => $request->planning_loading_2,
//     'planning_loading_3'   => $request->planning_loading_3,

//     'tujuan'               => $request->tujuan,
//     'area'                 => $request->area,
//     'via_kirim'            => $request->via_kirim,
//     'route'                => $request->route,
//     'pulau'                => $request->pulau,
//     'mobil'                => $request->mobil,
//     'ekpedisi'             => $request->ekpedisi,

//     'tanggal_tiba_gudang'    => $request->tanggal_tiba_gudang,
//     'tanggal_keluar_gudang'  => $request->tanggal_keluar_gudang,

//     'tanggal_tiba_gudang_2'  => $request->tanggal_tiba_gudang_2,
//     'tanggal_keluar_gudang_2'=> $request->tanggal_keluar_gudang_2,

//     'tanggal_tiba_gudang_3'  => $request->tanggal_tiba_gudang_3,
//     'tanggal_keluar_gudang_3'=> $request->tanggal_keluar_gudang_3,

//     'dist_channel'         => $request->dist_channel,

//     'lama_waktu_pencarian' => (string)($rumus['lama_waktu_pencarian'] ?? ''),
//     'sla_dapat_mobil'      => $rumus['sla_dapat_mobil'] ?? null,
//     'status_pengiriman'    => $rumus['status_pengiriman'] ?? null,

//     'lama_digudang'        => $rumus['lama_digudang'] ?? null,
//     'status_gudang'        => $rumus['status_gudang'] ?? null,
//     'sla_loading'          => $rumus['sla_loading'] ?? null,

//     'lama_digudang_2'      => $rumus['lama_digudang_2'] ?? null,
//     'status_gudang_2'      => $rumus['status_gudang_2'] ?? null,
//     'sla_loading_2'        => $rumus['sla_loading_2'] ?? null,

//     'lama_digudang_3'      => $rumus['lama_digudang_3'] ?? null,
//     'status_gudang_3'      => $rumus['status_gudang_3'] ?? null,
//     'sla_loading_3'        => $rumus['sla_loading_3'] ?? null,

//     'updated_at'           => now(),
// ];


// // ======================================
// // UPDATE SEMUA ROW SHIPMENT
// // ======================================

// DB::table('logistik_pengiriman')
//     ->where('no_shipment', $oldNoShipment)
//     ->orWhere('no_shipment', $newNoShipment)
//     ->update($updateData);


// // ======================================
// // UPDATE KHUSUS ROW YANG DIEDIT
// // ======================================

// DB::table('logistik_pengiriman')
//     ->where('id', $id)
//     ->update([

//         'nilai_muatan' => $this->cleanMoney($request->nilai_muatan),
//         'biaya_kirim'  => $this->cleanMoney($request->biaya_kirim),
//         'cr'           => $this->cleanCr($request->cr),

//     ]);

// return back()->with('success', 'Semua data shipment berhasil disinkronkan');
// }



// public function update(Request $request, $id)
// {

// $request->merge([
//         // 'rencana_kirim' => $this->hitungRencanaKirim($request)
//     ]);
//     // ==========================
//     // Ambil data lama
//     // ==========================
//     $old = DB::table('logistik_pengiriman')
//         ->where('id', $id)
//         ->first();

//     if (!$old) {
//         return back()->with('error', 'Data tidak ditemukan');
//     }

//     // Area tetap dari database
//     // $request->merge([
//     //     'area' => $old->area
//     // ]);

//     // ==========================
//     // Hitung Estimasi Tiba
//     // ==========================
//     $keluar = collect([
//         $request->tanggal_keluar_gudang,
//         $request->tanggal_keluar_gudang_2,
//         $request->tanggal_keluar_gudang_3,
//     ])
//     ->filter()
//     ->map(fn($v) => strtotime($v))
//     ->max();

//     if ($keluar && $request->transport_lead_time) {

//         $request->merge([
//             'estimasi_tiba' => date(
//                 'Y-m-d',
//                 strtotime(
//                     '+' . (int)$request->transport_lead_time . ' days',
//                     $keluar
//                 )
//             )
//         ]);
//     }

//     // ==========================
//     // Hitung SLA
//     // ==========================
//     $rumus = $this->hitungSla($request);

//     $oldNoShipment = $old->no_shipment;
//     $newNoShipment = $request->no_shipment;

//     // ====================================================
//     // UPDATE SEMUA ROW YANG NO SHIPMENT SAMA
//     // ====================================================

//     $updateShipment = [

//         'estimasi_tiba'         => $request->estimasi_tiba,
//         'tanggal_naik_logistik' => $request->tanggal_naik_logistik,
//         'rencana_kirim'         => $request->rencana_kirim,
//         'transport_lead_time'   => $request->transport_lead_time,
//         'planner'               => $request->planner,
//         'no_shipment'           => $newNoShipment,
        
//         'perubahan_mobil'       => $request->perubahan_mobil,
//         'kategori_ekspedisi'    => $request->kategori_ekspedisi,
//         'keterangan'            => $request->keterangan,
//          'ekpedisi'    => $request->ekpedisi,
//           'mobil'    => $request->mobil,
//         'tanggal_dpt_unit'      => $request->tanggal_dpt_unit,

//         'planning_loading'      => $request->planning_loading,
//         'tanggal_tiba_gudang'   => $request->tanggal_tiba_gudang,
//         'tanggal_keluar_gudang' => $request->tanggal_keluar_gudang,
//         'area' => $request->area,
//         'via_kirim' => $request->via_kirim,

//         'planning_loading_2'      => $request->planning_loading_2,
//         'tanggal_tiba_gudang_2'   => $request->tanggal_tiba_gudang_2,
//         'tanggal_keluar_gudang_2' => $request->tanggal_keluar_gudang_2,

//         'planning_loading_3'      => $request->planning_loading_3,
//         'tanggal_tiba_gudang_3'   => $request->tanggal_tiba_gudang_3,
//         'tanggal_keluar_gudang_3' => $request->tanggal_keluar_gudang_3,
//           'nama_driver' => $request->nama_driver,
//             'no_pol' => $request->no_pol,
//             // 'total_do_qty_car' => $request->total_do_qty_car,

//         'dist_channel' => $request->dist_channel,

//         'lama_waktu_pencarian' => $rumus['lama_waktu_pencarian'] ?? null,
//         'sla_dapat_mobil'      => $rumus['sla_dapat_mobil'] ?? null,
//         'status_pengiriman'    => $rumus['status_pengiriman'] ?? null,

//         'lama_digudang' => $rumus['lama_digudang'] ?? null,
//         'status_gudang' => $rumus['status_gudang'] ?? null,
//         'sla_loading'   => $rumus['sla_loading'] ?? null,

//         'lama_digudang_2' => $rumus['lama_digudang_2'] ?? null,
//         'status_gudang_2' => $rumus['status_gudang_2'] ?? null,
//         'sla_loading_2'   => $rumus['sla_loading_2'] ?? null,

//         'lama_digudang_3' => $rumus['lama_digudang_3'] ?? null,
//         'status_gudang_3' => $rumus['status_gudang_3'] ?? null,
//         'sla_loading_3'   => $rumus['sla_loading_3'] ?? null,
        
//         'updated_at' => now(),
//     ];
    

//     DB::table('logistik_pengiriman')
//         ->where(function ($q) use ($oldNoShipment, $newNoShipment) {
//             $q->where('no_shipment', $oldNoShipment)
//               ->orWhere('no_shipment', $newNoShipment);
//         })
//         ->update($updateShipment);

//     // ====================================================
//     // UPDATE HANYA ROW YANG DIKLIK
//     // ====================================================

//     $updateRow = [

//         'tujuan' => $request->tujuan,
//         'route'  => $request->route,
//         'pulau'  => $request->pulau,
//         'total_do_qty_car' => $request->total_do_qty_car,

//         'mobil'      => $request->mobil,
//         'ekpedisi'   => $request->ekpedisi,
//         'via_kirim'  => $request->via_kirim,

//         // kalau memang area mau ikut bisa pakai ini
//         // kalau tidak, hapus baris di bawah
//         'area' => $request->area,

//       'nilai_muatan' => $this->cleanMoney($request->nilai_muatan),
// 'biaya_kirim'  => $this->cleanMoney($request->biaya_kirim),

//         'updated_at' => now(),
//     ];

//     DB::table('logistik_pengiriman')
//         ->where('id', $id)
//         ->update($updateRow);
// $shipment = $newNoShipment ?: $oldNoShipment;

// $rows = DB::table('logistik_pengiriman')
//     ->where('no_shipment', $shipment)
//     ->get();

// $totalMuatan = $rows->sum(function ($r) {
//     return (float) $r->nilai_muatan;
// });

// $totalBiaya = $rows->max(function ($r) {
//     return (float) $r->biaya_kirim;
// });

// $cr = 0;

// if ($totalMuatan > 0) {
//     $cr = round(($totalBiaya / $totalMuatan) * 100, 4);
// }

// DB::table('logistik_pengiriman')
//     ->where('no_shipment', $shipment)
//     ->update([
//         'cr' => $cr
//     ]);

//     return back()->with('success', 'Data berhasil diperbarui');
// }


 public function update(Request $request, $id)
    {
        // ==========================
        // Ambil data lama
        // ==========================
        $old = DB::table('logistik_pengiriman')
            ->where('id', $id)
            ->first();

        if (!$old) {
            return back()->with('error', 'Data tidak ditemukan');
        }

        // ==========================
        // Hitung Estimasi Tiba (FIX: pakai blocked-check,
        // supaya konsisten dengan logic di MonitoringController)
        // ==========================
        $gudangInfo = $this->getKeluarGudangInfoRequest($request);
        $keluar  = $gudangInfo['keluar'];
        $blocked = $gudangInfo['blocked'];

        if (!$blocked && $keluar && $request->transport_lead_time) {

            $request->merge([
                'estimasi_tiba' => date(
                    'Y-m-d',
                    strtotime(
                        '+' . (int) $request->transport_lead_time . ' days',
                        $keluar
                    )
                )
            ]);
        } else {
            // masih ada siklus gudang yang "ngegantung" (belum keluar)
            // -> jangan hitung estimasi dulu
            $request->merge(['estimasi_tiba' => null]);
        }

        // ==========================
        // Hitung SLA
        // ==========================
        $rumus = $this->hitungSla($request);

        $oldNoShipment = $old->no_shipment;
        $newNoShipment = $request->no_shipment;
        $shipment      = $newNoShipment ?: $oldNoShipment;

        // ====================================================
        // 1. UPDATE FIELD YANG SHARED UNTUK SEMUA ROW SESHIPMENT
        // ====================================================
        $updateShipment = [

            'estimasi_tiba'         => $request->estimasi_tiba,
            'tanggal_naik_logistik' => $request->tanggal_naik_logistik,
            'rencana_kirim'         => $request->rencana_kirim,
            'transport_lead_time'   => $request->transport_lead_time,
            'planner'               => $request->planner,
            'no_shipment'           => $newNoShipment,

            'perubahan_mobil'       => $request->perubahan_mobil,
            'kategori_ekspedisi'    => $request->kategori_ekspedisi,
            'keterangan'            => $request->keterangan,

            'ekpedisi'              => $request->ekpedisi,
            'mobil'                 => $request->mobil,
            'route'                 => $request->route,

            'tanggal_dpt_unit'      => $request->tanggal_dpt_unit,

            'planning_loading'      => $request->planning_loading,
            'tanggal_tiba_gudang'   => $request->tanggal_tiba_gudang,
            'tanggal_keluar_gudang' => $request->tanggal_keluar_gudang,
            'area'                  => $request->area,
            'via_kirim'             => $request->via_kirim,

            'planning_loading_2'      => $request->planning_loading_2,
            'tanggal_tiba_gudang_2'   => $request->tanggal_tiba_gudang_2,
            'tanggal_keluar_gudang_2' => $request->tanggal_keluar_gudang_2,

            'planning_loading_3'      => $request->planning_loading_3,
            'tanggal_tiba_gudang_3'   => $request->tanggal_tiba_gudang_3,
            'tanggal_keluar_gudang_3' => $request->tanggal_keluar_gudang_3,

            'nama_driver' => $request->nama_driver,
            'no_pol'      => $request->no_pol,

            'dist_channel' => $request->dist_channel,

            'lama_waktu_pencarian' => $rumus['lama_waktu_pencarian'] ?? null,
            'sla_dapat_mobil'      => $rumus['sla_dapat_mobil'] ?? null,
            'status_pengiriman'    => $rumus['status_pengiriman'] ?? null,

            'lama_digudang' => $rumus['lama_digudang'] ?? null,
            'status_gudang' => $rumus['status_gudang'] ?? null,
            'sla_loading'   => $rumus['sla_loading'] ?? null,

            'lama_digudang_2' => $rumus['lama_digudang_2'] ?? null,
            'status_gudang_2' => $rumus['status_gudang_2'] ?? null,
            'sla_loading_2'   => $rumus['sla_loading_2'] ?? null,

            'lama_digudang_3' => $rumus['lama_digudang_3'] ?? null,
            'status_gudang_3' => $rumus['status_gudang_3'] ?? null,
            'sla_loading_3'   => $rumus['sla_loading_3'] ?? null,

            'updated_at' => now(),
        ];

        DB::table('logistik_pengiriman')
            ->where(function ($q) use ($oldNoShipment, $newNoShipment) {
                $q->where('no_shipment', $oldNoShipment)
                  ->orWhere('no_shipment', $newNoShipment);
            })
            ->update($updateShipment);

        // ====================================================
        // 2. AUTO HITUNG BIAYA KIRIM
        // ====================================================
        $autoBiaya = $this->cariBiayaKirimOtomatis(
            $request->route,
            $request->mobil,
            $request->ekpedisi
        );

        if ($autoBiaya !== null) {
            DB::table('logistik_pengiriman')
                ->where('no_shipment', $shipment)
                ->update([
                    'biaya_kirim' => $this->cleanMoney($autoBiaya),
                    'updated_at'  => now(),
                ]);
        }

        // ====================================================
        // 3. UPDATE FIELD YANG SPESIFIK PER ROW / PER TUJUAN
        // ====================================================
        $updateRow = [
            'tujuan'           => $request->tujuan,
            'pulau'            => $request->pulau,
            'total_do_qty_car' => $request->total_do_qty_car,
            'nilai_muatan'     => $this->cleanMoney($request->nilai_muatan),
            'updated_at'       => now(),
        ];

        if ($autoBiaya === null) {
            $updateRow['biaya_kirim'] = $this->cleanMoney($request->biaya_kirim);
        }

        DB::table('logistik_pengiriman')
            ->where('id', $id)
            ->update($updateRow);

        // ====================================================
        // 4. HITUNG ULANG CR PER ROW (PROPORSIONAL PER TUJUAN)
        // ====================================================
        $rows = DB::table('logistik_pengiriman')
            ->where('no_shipment', $shipment)
            ->get();

        $totalMuatan = $rows->sum(function ($r) {
            return (float) $r->nilai_muatan;
        });

        $totalBiaya = $rows->max(function ($r) {
            return (float) $r->biaya_kirim;
        });

        foreach ($rows as $r) {

            $crRow = 0;
            $nilaiMuatanRow = (float) $r->nilai_muatan;

            if ($totalMuatan > 0 && $nilaiMuatanRow > 0) {
                $kontribusi = $nilaiMuatanRow / $totalMuatan;
                $totalCR    = ($totalBiaya / $totalMuatan) * 100;
                $crRow      = $kontribusi * $totalCR;
            }

            DB::table('logistik_pengiriman')
                ->where('id', $r->id)
                ->update([
                    'cr' => round($crRow, 4)
                ]);
        }

        return back()->with('success', 'Data berhasil diperbarui');
    }
//     $updateData = [

//      'estimasi_tiba' => $request->estimasi_tiba,
//         'tanggal_naik_logistik' => $request->tanggal_naik_logistik,
//         'rencana_kirim'         => $request->rencana_kirim,
//         'transport_lead_time'   => $request->transport_lead_time,
//         'planner'               => $request->planner,
//         'no_shipment'           => $newNoShipment,

//         'perubahan_mobil'       => $request->perubahan_mobil,
//         'kategori_ekspedisi'    => $request->kategori_ekspedisi,
//         'keterangan'            => $request->keterangan,

//         'tanggal_dpt_unit'      => $request->tanggal_dpt_unit,
//         'planning_loading'      => $request->planning_loading,
//         'planning_loading_2'    => $request->planning_loading_2,
//         'planning_loading_3'    => $request->planning_loading_3,
//                 'tujuan'            => $request->tujuan,
//             'area'            => $request->area,
//               'via_kirim'            => $request->via_kirim,
//                 'route'            => $request->route,
//                   'pulau'            => $request->pulau,
//                     'mobil'            => $request->mobil,
//                       'ekpedisi'            => $request->ekpedisi,


//         'tanggal_tiba_gudang'   => $request->tanggal_tiba_gudang,
//         'tanggal_keluar_gudang' => $request->tanggal_keluar_gudang,

//         'tanggal_tiba_gudang_2'   => $request->tanggal_tiba_gudang_2,
//         'tanggal_keluar_gudang_2' => $request->tanggal_keluar_gudang_2,

//         'tanggal_tiba_gudang_3'   => $request->tanggal_tiba_gudang_3,
//         'tanggal_keluar_gudang_3' => $request->tanggal_keluar_gudang_3,
//              'dist_channel'   => $request->dist_channel,
//             //    'nilai_muatan'   => $request->Nilai_muatan,
//             //      'biaya_kirim'   => $request->biaya_kirim,

//         'lama_waktu_pencarian' => (string) ($rumus['lama_waktu_pencarian'] ?? ''),
//         'sla_dapat_mobil'      => $rumus['sla_dapat_mobil'] ?? null,
//         'status_pengiriman'    => $rumus['status_pengiriman'] ?? null,

//         'lama_digudang'        => $rumus['lama_digudang'] ?? null,
//         'status_gudang'        => $rumus['status_gudang'] ?? null,
//         'sla_loading'          => $rumus['sla_loading'] ?? null,

//         'lama_digudang_2'      => $rumus['lama_digudang_2'] ?? null,
//         'status_gudang_2'      => $rumus['status_gudang_2'] ?? null,
//         'sla_loading_2'        => $rumus['sla_loading_2'] ?? null,

//         'lama_digudang_3'      => $rumus['lama_digudang_3'] ?? null,
//         'status_gudang_3'      => $rumus['status_gudang_3'] ?? null,
//         'sla_loading_3'        => $rumus['sla_loading_3'] ?? null,

//         'updated_at'           => now(),
//     ];

//     // clean money
//     $updateData['nilai_muatan'] = $this->cleanMoney($request->nilai_muatan);
//     $updateData['biaya_kirim']  = $this->cleanMoney($request->biaya_kirim);
//     $updateData['cr']           = $this->cleanCr($request->cr);

//     /**
//      * 🔥 INI KUNCI UTAMA:
//      * update semua row dengan no_shipment lama ATAU baru
//      */
//     DB::table('logistik_pengiriman')
//         ->where('no_shipment', $oldNoShipment)
//         ->orWhere('no_shipment', $newNoShipment)
//         ->update($updateData);

//     return back()->with('success', 'Semua data shipment berhasil disinkronkan');
// }
private function cleanCr($value)
{
    if (!$value) return null;

    // ambil angka saja (buang %, teks, dll)
    $value = preg_replace('/[^0-9.]/', '', $value);

    return is_numeric($value) ? (float) $value : null;
}

private function cleanMoney($value)
{
    if (!$value) return null;

    // buang semua selain angka
    return (int) preg_replace('/[^0-9]/', '', $value);
}

  public function dataLogistik()
    {
        $logistik = LogistikPengiriman::orderByRaw('CAST(no_shipment AS UNSIGNED) ASC')
            ->get();

        $planners = LogistikPengiriman::whereNotNull('planner')
            ->where('planner', '!=', '')
            ->distinct()
            ->orderBy('planner')
            ->pluck('planner');

        $tujuanList = DB::table('tujuanfillterr')
            ->whereNotNull('tujuan')->where('tujuan', '!=', '')
            ->distinct()->orderBy('tujuan')->pluck('tujuan');

        $pulauList = DB::table('tujuanfillterr')
            ->whereNotNull('pulau')->where('pulau', '!=', '')
            ->distinct()->orderBy('pulau')->pluck('pulau');

        $areas = DB::table('tujuanfillterr')
            ->whereNotNull('area')->where('area', '!=', '')
            ->distinct()->orderBy('area')->pluck('area');

        $distChannelList = DB::table('tujuanfillterr')
            ->whereNotNull('dist_channel')->where('dist_channel', '!=', '')
            ->distinct()->orderBy('dist_channel')->pluck('dist_channel');

        $ekpedisiList = DB::table('tarif_pengiriman')
            ->whereNotNull('ekpedisi')->where('ekpedisi', '!=', '')
            ->distinct()->orderBy('ekpedisi')->pluck('ekpedisi');

        $mobilList = DB::table('tarif_pengiriman')
            ->whereNotNull('mobil')->where('mobil', '!=', '')
            ->distinct()->orderBy('mobil')->pluck('mobil');

        $routeList = DB::table('tarif_pengiriman')
            ->whereNotNull('route')->where('route', '!=', '')
            ->distinct()->orderBy('route')->pluck('route');

        $tarifPengiriman = DB::table('tarif_pengiriman')
            ->select('route', 'mobil', 'ekpedisi', 'biaya_kirim')
            ->whereNotNull('route')
            ->whereNotNull('mobil')
            ->get();

        return view(
            'spvplanner.data_planner',
            compact(
                'logistik',
                'ekpedisiList',
                'tujuanList',
                'mobilList',
                'pulauList',
                'routeList',
                'distChannelList',
                'planners',
                'areas',
                'tarifPengiriman'
            )
        );
    }



public function fullDataLogistik(Request $request)
{
    $query = LogistikPengiriman::query();

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
    $logistik = $query
        ->orderBy('id', 'DESC')
        ->get();

    /* ================= DROPDOWN ================= */
    $picList = LogistikPengiriman::whereNotNull('pic_monitoring')
        ->distinct()
        ->pluck('pic_monitoring');

    $areaList = LogistikPengiriman::whereNotNull('area')
        ->distinct()
        ->pluck('area');

    /* ================= ESTIMASI LOGIC ================= */
    $grouped = $logistik->groupBy('no_shipment');

    foreach ($grouped as $shipment => $items) {

        $keluar = $items->flatMap(function ($r) {
            return [
                $r->tanggal_keluar_gudang,
                $r->tanggal_keluar_gudang_2,
                $r->tanggal_keluar_gudang_3,
            ];
        })
        ->filter(function ($t) {
            return !empty($t) && $t != 'mm/dd/yyyy';
        })
        ->map(fn($t) => strtotime($t))
        ->max();

        $leadtime = (int) ($items->first()->transport_lead_time ?? 0);

        $baseEstimasi = $keluar
            ? strtotime("+{$leadtime} days", $keluar)
            : null;

        $jumlahSudahTiba = $items->whereNotNull('tanggal_tiba')->count();

        foreach ($items as $r) {

            if (!$baseEstimasi) {
                $r->tanggal_estimasi = null;
                continue;
            }

            if ($r->tanggal_tiba) {
                $r->tanggal_estimasi = $r->estimasi_tiba
                    ? strtotime($r->estimasi_tiba)
                    : $baseEstimasi;
            } else {
                $r->tanggal_estimasi = strtotime(
                    "+{$jumlahSudahTiba} days",
                    $baseEstimasi
                );
            }
        }
    }

    return view('spvplanner.full_data_logistik', compact(
        'logistik',
        'picList',
        'areaList'
    ));
}

private function getTibaGudangTerdekatRequest($request)
{
    return collect([
        $request->tanggal_tiba_gudang,
        $request->tanggal_tiba_gudang_2,
        $request->tanggal_tiba_gudang_3,
    ])
    ->filter()
    ->sort()
    ->first();
}


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
  if ($area == 'JABODETABEK' || $area == 'JABODEBEK' || $area == 'BANTEN') {
    $batasHari = 0;
} elseif ($area == 'JAWA_BARAT') {
    $batasHari = 1;
} else {
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

// private function hitungRencanaKirim($request)
// {
//     $tglNaik  = $request->tanggal_naik_logistik;
//     $leadTime = (int) ($request->transport_lead_time ?? 0);

//     if ($tglNaik && $leadTime > 0) {
//         return date('Y-m-d', strtotime($tglNaik . " +{$leadTime} days"));
//     }

//     // tidak ada tanggal_naik_logistik atau lead time belum diisi -> pakai input manual apa adanya
//     return $request->rencana_kirim;
// }
// private function hitungSla($request)
// {
//     $data = [
//         'lama_waktu_pencarian' => null,
//         'sla_dapat_mobil'      => null,
//         'status_pengiriman'    => null,

//         'lama_digudang'        => null,
//         'status_gudang'        => null,
//         'sla_loading'          => null,

//         'lama_digudang_2'      => null,
//         'status_gudang_2'      => null,
//         'sla_loading_2'        => null,

//         'lama_digudang_3'      => null,
//         'status_gudang_3'      => null,
//         'sla_loading_3'        => null,
//     ];

//     // =====================================================
//     // AMBIL TIBA GUDANG TERCEPAT (GLOBAL)
//     // =====================================================
//     $tibaGudang = collect([
//         $request->tanggal_tiba_gudang,
//         $request->tanggal_tiba_gudang_2,
//         $request->tanggal_tiba_gudang_3,
//     ])->filter()->sort()->first();

//     // =====================================================
//     // 1. SLA DAPAT MOBIL
//     // =====================================================
//     if ($request->tanggal_dpt_unit && $tibaGudang) {

//         $awal  = new \DateTime($request->tanggal_dpt_unit);
//         $akhir = new \DateTime($tibaGudang);

//         $awalCek  = (clone $awal)->setTime(0,0,0);
//         $akhirCek = (clone $akhir)->setTime(0,0,0);

//         if ($akhir >= $awal) {

//             $selisihDetik = $akhir->getTimestamp() - $awal->getTimestamp();

//             $jam   = floor($selisihDetik / 3600);
//             $menit = floor(($selisihDetik % 3600) / 60);

//             $data['lama_waktu_pencarian'] = "{$jam} Jam {$menit} Menit";

//             if ($awalCek < $akhirCek) {
//                 $data['sla_dapat_mobil']   = 'Delay';
//                 $data['status_pengiriman'] = 'Terlambat';
//             } else {
//                 $data['sla_dapat_mobil']   = 'On Time';
//                 $data['status_pengiriman'] = 'Sudah Dapat';
//             }

//         } else {
//             $data['lama_waktu_pencarian'] = "0 Jam 0 Menit";
//             $data['sla_dapat_mobil']      = 'On Time';
//             $data['status_pengiriman']    = 'Sudah Dapat';
//         }
//     }

//     // =====================================================
//     // 2. GUDANG 1 (FIX UTAMA DI SINI)
//     // =====================================================
//     // if ($request->tanggal_tiba_gudang && $request->tanggal_keluar_gudang) {

//     //     $awal  = new \DateTime($request->tanggal_tiba_gudang);
//     //     $akhir = new \DateTime($request->tanggal_keluar_gudang);

//     //     if ($akhir >= $awal) {

//     //         $selisihDetik = $akhir->getTimestamp() - $awal->getTimestamp();

//     //         $jam   = floor($selisihDetik / 3600);
//     //         $menit = floor(($selisihDetik % 3600) / 60);

//     //         $data['lama_digudang'] = "{$jam} Jam {$menit} Menit";

//     //         // SLA: lebih dari 1 hari = delay
//     //         if ($selisihDetik > 86400) {

//     //             $hari = floor($selisihDetik / 86400);

//     //             $data['status_gudang'] = 'Delay';
//     //             $data['sla_loading']   = 'H+' . $hari;

//     //         } else {

//     //             $data['status_gudang'] = 'On Time';
//     //             $data['sla_loading']   = 'Sesuai SLA';
//     //         }

//     //     } else {
//     //         $data['lama_digudang']  = "0 Jam 0 Menit";
//     //         $data['status_gudang']  = 'On Time';
//     //         $data['sla_loading']    = 'Sesuai SLA';
//     //     }
//     // }

//    if ($request->tanggal_tiba_gudang && $request->tanggal_keluar_gudang) {

//         $awal  = new \DateTime($request->tanggal_tiba_gudang);
//         $akhir = new \DateTime($request->tanggal_keluar_gudang);

//         if ($akhir >= $awal) {

//             $diff = $awal->diff($akhir);

//             $data['lama_digudang'] = $diff->days > 0
//                 ? "{$diff->days} Hari {$diff->h} Jam"
//                 : "{$diff->h} Jam";

//             $data['status_gudang'] = $diff->days > 0 ? 'Delay' : 'On Time';
//             $data['sla_loading']    = $diff->days > 0 ? 'H+' . $diff->days : 'Sesuai SLA';
//         }
//     }


//     // =====================================================
//     // 3. GUDANG 2 (SUDAH BENAR, SEDIKIT DIRAPIKAN)
//     // =====================================================
//     if ($request->tanggal_tiba_gudang_2 && $request->tanggal_keluar_gudang_2) {

//         $awal  = new \DateTime($request->tanggal_tiba_gudang_2);
//         $akhir = new \DateTime($request->tanggal_keluar_gudang_2);

//         if ($akhir >= $awal) {

//             $diff = $awal->diff($akhir);

//             $data['lama_digudang_2'] = $diff->days > 0
//                 ? "{$diff->days} Hari {$diff->h} Jam"
//                 : "{$diff->h} Jam";

//             $data['status_gudang_2'] = $diff->days > 0 ? 'Delay' : 'On Time';
//             $data['sla_loading_2']    = $diff->days > 0 ? 'H+' . $diff->days : 'Sesuai SLA';
//         }
//     }

//     // =====================================================
//     // 4. GUDANG 3 (SUDAH BENAR)
//     // =====================================================
//     if ($request->tanggal_tiba_gudang_3 && $request->tanggal_keluar_gudang_3) {

//         $awal  = new \DateTime($request->tanggal_tiba_gudang_3);
//         $akhir = new \DateTime($request->tanggal_keluar_gudang_3);

//         if ($akhir >= $awal) {

//             $diff = $awal->diff($akhir);

//             $data['lama_digudang_3'] = $diff->days > 0
//                 ? "{$diff->days} Hari {$diff->h} Jam"
//                 : "{$diff->h} Jam";

//             $data['status_gudang_3'] = $diff->days > 0 ? 'Delay' : 'On Time';
//             $data['sla_loading_3']    = $diff->days > 0 ? 'H+' . $diff->days : 'Sesuai SLA';
//         }
//     }

//     return $data;
// }


 public function autosaveRow(Request $request, $id)
    {
        $old = DB::table('logistik_pengiriman')
            ->where('id', $id)
            ->first();

        if (!$old) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $oldNoShipment = $old->no_shipment;
        $newNoShipment = $request->no_shipment;
        $shipment      = $newNoShipment ?: $oldNoShipment;

        // ==========================
        // HITUNG ESTIMASI TIBA (blocked-check)
        // ==========================
        $gudangInfo = $this->getKeluarGudangInfoRequest($request);
        $keluar  = $gudangInfo['keluar'];
        $blocked = $gudangInfo['blocked'];

        if (!$blocked && $keluar && $request->transport_lead_time) {
            $request->merge([
                'estimasi_tiba' => date(
                    'Y-m-d',
                    strtotime('+' . (int) $request->transport_lead_time . ' days', $keluar)
                )
            ]);
        } else {
            $request->merge(['estimasi_tiba' => null]);
        }

        // ==========================
        // HITUNG SLA (INI YANG SEBELUMNYA HILANG TOTAL)
        // ==========================
        $rumus = $this->hitungSla($request);

        $autoBiaya = $this->cariBiayaKirimOtomatis(
            $request->route,
            $request->mobil,
            $request->ekpedisi
        );

        $biayaKirim = $autoBiaya !== null
            ? $this->cleanMoney($autoBiaya)
            : $this->cleanMoney($request->biaya_kirim);

        // ====================================================
        // 1. UPDATE FIELD SHARED UNTUK SEMUA ROW SESHIPMENT
        // ====================================================
        $updateShipment = [
            'planner'       => $request->planner,
            'no_shipment'   => $newNoShipment,
            'estimasi_tiba' => $request->estimasi_tiba,

            'tanggal_naik_logistik' => $request->tanggal_naik_logistik,
            'rencana_kirim'         => $request->rencana_kirim,
            'tanggal_dpt_unit'      => $request->tanggal_dpt_unit,

            'planning_loading'      => $request->planning_loading,
            'tanggal_tiba_gudang'   => $request->tanggal_tiba_gudang,
            'tanggal_keluar_gudang' => $request->tanggal_keluar_gudang,

            'planning_loading_2'      => $request->planning_loading_2,
            'tanggal_tiba_gudang_2'   => $request->tanggal_tiba_gudang_2,
            'tanggal_keluar_gudang_2' => $request->tanggal_keluar_gudang_2,

            'planning_loading_3'      => $request->planning_loading_3,
            'tanggal_tiba_gudang_3'   => $request->tanggal_tiba_gudang_3,
            'tanggal_keluar_gudang_3' => $request->tanggal_keluar_gudang_3,

            'tujuan'    => $request->tujuan,
            'route'     => $request->route,
            'pulau'     => $request->pulau,
            'area'      => $request->area,
            'via_kirim' => $request->via_kirim,

            'dist_channel'        => $request->dist_channel,
            'kategori_ekspedisi'  => $request->kategori_ekspedisi,
            'ekpedisi'            => $request->ekpedisi,
            'transport_lead_time' => $request->transport_lead_time,

            'nama_driver' => $request->nama_driver,
            'no_pol'      => $request->no_pol,
            'mobil'       => $request->mobil,

            // ===================== FIX: SLA IKUT DISIMPAN =====================
            'lama_waktu_pencarian' => $rumus['lama_waktu_pencarian'] ?? null,
            'sla_dapat_mobil'      => $rumus['sla_dapat_mobil'] ?? null,
            'status_pengiriman'    => $rumus['status_pengiriman'] ?? null,

            'lama_digudang' => $rumus['lama_digudang'] ?? null,
            'status_gudang' => $rumus['status_gudang'] ?? null,
            'sla_loading'   => $rumus['sla_loading'] ?? null,

            'lama_digudang_2' => $rumus['lama_digudang_2'] ?? null,
            'status_gudang_2' => $rumus['status_gudang_2'] ?? null,
            'sla_loading_2'   => $rumus['sla_loading_2'] ?? null,

            'lama_digudang_3' => $rumus['lama_digudang_3'] ?? null,
            'status_gudang_3' => $rumus['status_gudang_3'] ?? null,
            'sla_loading_3'   => $rumus['sla_loading_3'] ?? null,
            // ====================================================================

            'updated_at' => now(),
        ];

        DB::table('logistik_pengiriman')
            ->where(function ($q) use ($oldNoShipment, $newNoShipment) {
                $q->where('no_shipment', $oldNoShipment)
                  ->orWhere('no_shipment', $newNoShipment);
            })
            ->update($updateShipment);

        // ====================================================
        // 2. SYNC BIAYA KIRIM AUTO KE SEMUA ROW SESHIPMENT
        // ====================================================
        if ($autoBiaya !== null) {
            DB::table('logistik_pengiriman')
                ->where('no_shipment', $shipment)
                ->update([
                    'biaya_kirim' => $biayaKirim,
                    'updated_at'  => now(),
                ]);
        }

        // ====================================================
        // 3. UPDATE FIELD SPESIFIK PER ROW (per unit/per tujuan)
        // ====================================================
        $updateRow = [
            'total_do_qty_car' => $request->total_do_qty_car,
            'nilai_muatan'     => $this->cleanMoney($request->nilai_muatan),
            'updated_at'       => now(),
        ];

        if ($autoBiaya === null) {
            $updateRow['biaya_kirim'] = $biayaKirim;
        }

        DB::table('logistik_pengiriman')
            ->where('id', $id)
            ->update($updateRow);

        // ====================================================
        // 4. HITUNG ULANG CR PROPORSIONAL SESHIPMENT
        // ====================================================
        $rows = DB::table('logistik_pengiriman')
            ->where('no_shipment', $shipment)
            ->get();

        $totalMuatan = $rows->sum(fn($r) => (float) $r->nilai_muatan);
        $totalBiaya  = $rows->max(fn($r) => (float) $r->biaya_kirim);

        foreach ($rows as $r) {
            $crRow = 0;
            $nilaiMuatanRow = (float) $r->nilai_muatan;

            if ($totalMuatan > 0 && $nilaiMuatanRow > 0) {
                $kontribusi = $nilaiMuatanRow / $totalMuatan;
                $totalCR    = ($totalBiaya / $totalMuatan) * 100;
                $crRow      = $kontribusi * $totalCR;
            }

            DB::table('logistik_pengiriman')
                ->where('id', $r->id)
                ->update(['cr' => round($crRow, 4)]);
        }

        return response()->json([
            'success'       => true,
            'biaya_kirim'   => $biayaKirim,
            'estimasi_tiba' => $request->estimasi_tiba,
            'sla'           => $rumus,
        ]);
    }


    private function getKeluarGudangInfoRequest($request)
{
    $cycles = [
        ['planning' => $request->planning_loading,   'tiba' => $request->tanggal_tiba_gudang,   'keluar' => $request->tanggal_keluar_gudang],
        ['planning' => $request->planning_loading_2, 'tiba' => $request->tanggal_tiba_gudang_2, 'keluar' => $request->tanggal_keluar_gudang_2],
        ['planning' => $request->planning_loading_3, 'tiba' => $request->tanggal_tiba_gudang_3, 'keluar' => $request->tanggal_keluar_gudang_3],
    ];

    $blocked = false;
    $keluarTimestamps = [];

    foreach ($cycles as $c) {
        $started = !empty($c['planning']) || !empty($c['tiba']);
        $selesai = !empty($c['keluar']);

        if ($started && !$selesai) {
            $blocked = true;
        }

        if ($selesai) {
            $keluarTimestamps[] = strtotime($c['keluar']);
        }
    }

    return [
        'blocked' => $blocked,
        'keluar'  => !empty($keluarTimestamps) ? max($keluarTimestamps) : null,
    ];
}

private function cariBiayaKirimOtomatis($route, $mobil, $ekpedisi = null)
{
    if (!$route || !$mobil) {
        return null;
    }

    $normalize = function ($v) {
        if (!$v) return '';
        $v = str_replace("\xc2\xa0", ' ', $v);
        $v = preg_replace('/\s*-\s*/', '-', $v);
        $v = preg_replace('/\s+/', ' ', trim($v));
        return mb_strtolower($v);
    };

    $routeKey    = $normalize($route);
    $mobilKey    = $normalize($mobil);
    $ekpedisiKey = $ekpedisi ? $normalize($ekpedisi) : '';

    $candidates = DB::table('tarif_pengiriman')
        ->whereNotNull('route')
        ->whereNotNull('mobil')
        ->get()
        ->filter(fn ($t) => $normalize($t->route) === $routeKey);

    if ($candidates->isEmpty()) {
        return null;
    }

    if ($ekpedisiKey !== '') {
        $strict = $candidates->first(function ($t) use ($normalize, $ekpedisiKey, $mobilKey) {
            return $normalize($t->ekpedisi) === $ekpedisiKey
                && str_starts_with($normalize($t->mobil), $mobilKey);
        });

        if ($strict) {
            return $strict->biaya_kirim;
        }
    }

    $fallback = $candidates->first(fn ($t) => str_starts_with($normalize($t->mobil), $mobilKey));

    return $fallback->biaya_kirim ?? null;
}

// public function autosaveRow(Request $request, $id)
// {

// $request->merge([
//         // 'rencana_kirim' => $this->hitungRencanaKirim($request)
//     ]);
//     DB::table('logistik_pengiriman')
//         ->where('id', $id)
//         ->update([
//             'planner' => $request->planner,
//             'no_shipment' => $request->no_shipment,
//             'tanggal_naik_logistik' => $request->tanggal_naik_logistik,
//             'rencana_kirim' => $request->rencana_kirim,
//             'planning_loading' => $request->planning_loading,
//             'tanggal_tiba_gudang' => $request->tanggal_tiba_gudang,
//             'tanggal_keluar_gudang' => $request->tanggal_keluar_gudang,
//             'planning_loading_2' => $request->planning_loading_2,
//             'tanggal_tiba_gudang_2' => $request->tanggal_tiba_gudang_2,
//             'tanggal_keluar_gudang_2' => $request->tanggal_keluar_gudang_2,
//             'planning_loading_3' => $request->planning_loading_3,
//             'tanggal_tiba_gudang_3' => $request->tanggal_tiba_gudang_3,
//             'tanggal_keluar_gudang_3' => $request->tanggal_keluar_gudang_3,
//             'tujuan' => $request->tujuan,
//             'route' => $request->route,
//             'pulau' => $request->pulau,
//             'area' => $request->area,
//             'via_kirim' => $request->via_kirim,
//             'dist_channel' => $request->dist_channel,
//             'kategori_ekspedisi' => $request->kategori_ekspedisi,
//             'ekpedisi' => $request->ekpedisi,
//             'transport_lead_time' => $request->transport_lead_time,
//             'mobil' => $request->mobil,
//             'nilai_muatan' => $request->nilai_muatan,
//             'biaya_kirim' => $request->biaya_kirim,
//             'cr' => $request->cr,
//             'tanggal_dpt_unit' => $request->tanggal_dpt_unit,
//             'biaya_kirim' => $request->biaya_kirim,
//             'nilai_muatan' => $request->nilai_muatan,
//             'cr' => $request->cr,
//             'updated_at' => now(),
//         ]);

//     return response()->json(['success' => true]);
// }
public function slaOntime(Request $request)
{
    $query = DB::table('logistik_pengiriman')
        ->where(function ($q) {
            $q->whereNotNull('tanggal_tiba_gudang')
              ->orWhereNotNull('tanggal_tiba_gudang_2')
              ->orWhereNotNull('tanggal_tiba_gudang_3');
        });

    if ($request->filled('bulan')) {
        $query->where(function ($q) use ($request) {
            $q->whereMonth('tanggal_tiba_gudang', $request->bulan)
              ->orWhereMonth('tanggal_tiba_gudang_2', $request->bulan)
              ->orWhereMonth('tanggal_tiba_gudang_3', $request->bulan);
        });
    }

    if ($request->filled('tahun')) {
        $query->where(function ($q) use ($request) {
            $q->whereYear('tanggal_tiba_gudang', $request->tahun)
              ->orWhereYear('tanggal_tiba_gudang_2', $request->tahun)
              ->orWhereYear('tanggal_tiba_gudang_3', $request->tahun);
        });
    }

    if ($request->filled('area')) {
        $query->where('area', $request->area);
    }

    $list = $query->get()
      ->map(function ($row) {

    $sla = $this->hitungSla($row);

    $row->lama_waktu_pencarian = $sla['lama_waktu_pencarian'];
    $row->sla_dapat_mobil      = $sla['sla_dapat_mobil'];
    $row->status_pengiriman    = $sla['status_pengiriman'];

    $row->lama_digudang        = $sla['lama_digudang'];
    $row->status_gudang        = $sla['status_gudang'];
    $row->sla_loading          = $sla['sla_loading'];

    $row->lama_digudang_2      = $sla['lama_digudang_2'];
    $row->status_gudang_2      = $sla['status_gudang_2'];
    $row->sla_loading_2        = $sla['sla_loading_2'];

    $row->lama_digudang_3      = $sla['lama_digudang_3'];
    $row->status_gudang_3      = $sla['status_gudang_3'];
    $row->sla_loading_3        = $sla['sla_loading_3'];

    // =========================
    // GUDANG TIBA
    // =========================
    $gudang = [];

    if (!empty($row->tanggal_tiba_gudang)) {
        $gudang[1] = strtotime($row->tanggal_tiba_gudang);
    }

    if (!empty($row->tanggal_tiba_gudang_2)) {
        $gudang[2] = strtotime($row->tanggal_tiba_gudang_2);
    }

    if (!empty($row->tanggal_tiba_gudang_3)) {
        $gudang[3] = strtotime($row->tanggal_tiba_gudang_3);
    }

    $row->gudang_sla = count($gudang)
        ? array_search(min($gudang), $gudang)
        : null;

    // =========================
    // GUDANG KELUAR TERAKHIR
    // =========================
    $keluar = [];

    if (!empty($row->tanggal_keluar_gudang)) {
        $keluar[1] = strtotime($row->tanggal_keluar_gudang);
    }

    if (!empty($row->tanggal_keluar_gudang_2)) {
        $keluar[2] = strtotime($row->tanggal_keluar_gudang_2);
    }

    if (!empty($row->tanggal_keluar_gudang_3)) {
        $keluar[3] = strtotime($row->tanggal_keluar_gudang_3);
    }

    $row->gudang_keluar_terakhir = count($keluar)
        ? array_search(max($keluar), $keluar)
        : null;

    return $row;
})
        ->values();

    $list_area = DB::table('logistik_pengiriman')
        ->select('area')
        ->whereNotNull('area')
        ->groupBy('area')
        ->orderBy('area')
        ->get();

    return view('spvplanner.sla_ontime', compact('list', 'list_area'));
}
public function slaDelay(Request $request)
{
    $query = DB::table('logistik_pengiriman')
        ->whereNotNull('rencana_kirim')
        ->whereRaw("TRIM(rencana_kirim) <> ''")
        ->whereNotNull('tanggal_dpt_unit')
        ->whereRaw("TRIM(tanggal_dpt_unit) <> ''")
        ->where(function ($q) {
            $q->whereNull('tanggal_tiba_gudang')
              ->orWhere('tanggal_tiba_gudang', '');
        })
        ->where(function ($q) {
            $q->whereNull('tanggal_tiba_gudang_2')
              ->orWhere('tanggal_tiba_gudang_2', '');
        })
        ->where(function ($q) {
            $q->whereNull('tanggal_tiba_gudang_3')
              ->orWhere('tanggal_tiba_gudang_3', '');
        });

    if ($request->filled('bulan')) {
        $query->whereMonth('tanggal_naik_logistik', $request->bulan);
    }

    if ($request->filled('tahun')) {
        $query->whereYear('tanggal_naik_logistik', $request->tahun);
    }

    if ($request->filled('area')) {
        $query->where('area', $request->area);
    }

   $list = $query
    ->orderBy('tanggal_naik_logistik', 'DESC')
    ->paginate(10)
    ->withQueryString();

    $list_area = DB::table('logistik_pengiriman')
        ->select('area')
        ->whereNotNull('area')
        ->groupBy('area')
        ->orderBy('area')
        ->get();

    return view('spvplanner.sla_delay', [
        'title' => 'SLA DELAY',
        'list' => $list,
        'list_area' => $list_area
    ]);
}
public function updateGudang23(Request $request)
{
    $request->validate([
        'no_shipment' => 'required'
    ]);

    // hitung SLA ulang
    $sla = $this->hitungSla($request);

    $data = [

        'tanggal_tiba_gudang_2'   => $request->tanggal_tiba_gudang_2,
        'tanggal_keluar_gudang_2' => $request->tanggal_keluar_gudang_2,

        'tanggal_tiba_gudang_3'   => $request->tanggal_tiba_gudang_3,
        'tanggal_keluar_gudang_3' => $request->tanggal_keluar_gudang_3,

        // SENTUL
        'lama_digudang_2' => $sla['lama_digudang_2'],
        'status_gudang_2' => $sla['status_gudang_2'],
        'sla_loading_2'   => $sla['sla_loading_2'],

        // CCIE
        'lama_digudang_3' => $sla['lama_digudang_3'],
        'status_gudang_3' => $sla['status_gudang_3'],
        'sla_loading_3'   => $sla['sla_loading_3'],

        'updated_at' => now()
    ];

    LogistikPengiriman::where(
        'no_shipment',
        $request->no_shipment
    )->update($data);

    return response()->json([
        'status' => 'success',
        'message' => 'Data Gudang 2 & Gudang 3 berhasil diupdate'
    ]);
}

// public function summaryArea()
// {
//     $summary_area = DB::table('logistik_pengiriman')
//         ->select('area', DB::raw('COUNT(*) as total'))
//         ->groupBy('area')
//         ->orderByDesc('total')
//         ->get();

//     return view('spvplanner.summary_area', compact('summary_area'));
// }

public function summaryArea()
{
    
    $shipments = DB::table('logistik_pengiriman')
        ->orderBy('no_shipment')
        ->get()
        ->groupBy('no_shipment')
        ->map(fn($group) => $group->first());

    $summary_area = $shipments
        ->groupBy('area')
        ->map(function ($group, $area) {
            return (object)[
                'area'  => $area,
                'total' => count($group)
            ];
        })
        ->sortByDesc('total');

    return view('spvplanner.summary_area', compact('summary_area'));
}

private function getTibaGudangTerdekat($row)
{
    $tanggal = collect([
        $row->tanggal_tiba_gudang,
        $row->tanggal_tiba_gudang_2,
        $row->tanggal_tiba_gudang_3,
    ])
    ->filter()
    ->sort()
    ->values();

    return $tanggal->first();
}
public function armada(Request $request)
{
    $query = DB::table('logistik_pengiriman')
        ->whereNotNull('rencana_kirim')
        ->whereRaw("TRIM(rencana_kirim) <> ''")
        ->whereNotNull('tanggal_dpt_unit')
        ->whereRaw("TRIM(tanggal_dpt_unit) <> ''");

    if ($request->filled('bulan')) {
        $query->whereMonth('tanggal_naik_logistik', $request->bulan);
    }

    if ($request->filled('tahun')) {
        $query->whereYear('tanggal_naik_logistik', $request->tahun);
    }

    $logistik = $query
        ->orderBy('tanggal_naik_logistik', 'DESC')
        ->get()
        ->map(function ($row) {

if ($row->rencana_kirim && $row->tanggal_dpt_unit) {

    $awal = new \DateTime(
        date('Y-m-d H:i:s', strtotime($row->rencana_kirim))
    );

    $akhir = new \DateTime(
        date('Y-m-d H:i:s', strtotime($row->tanggal_dpt_unit))
    );
                $awalCek  = (clone $awal)->setTime(0, 0, 0);
                $akhirCek = (clone $akhir)->setTime(0, 0, 0);

                if ($akhir >= $awal) {
                    $diff = $awal->diff($akhir);

                    $row->lama_waktu_pencarian = $diff->days > 0
                        ? "{$diff->days} Hari {$diff->h} Jam {$diff->i} Menit"
                        : "{$diff->h} Jam {$diff->i} Menit";

                    $row->sla_dapat_mobil   = $akhirCek > $awalCek ? 'Delay' : 'On Time';
                    $row->status_pengiriman = $akhirCek > $awalCek ? 'Terlambat' : 'Sudah Dapat';
                } else {
                    $row->lama_waktu_pencarian = "0 Jam 0 Menit";
                    $row->sla_dapat_mobil      = 'On Time';
                    $row->status_pengiriman    = 'Sudah Dapat';
                }
            } else {
                $row->lama_waktu_pencarian = '-';
                $row->sla_dapat_mobil      = '-';
                $row->status_pengiriman    = '-';
            }

            return $row;
        });

    return view('spvplanner.armada', compact('logistik'));
}

public function exportPlanner(Request $request)
{
    return Excel::download(
        new PlannerExport($request->planner, $request->area, $request->bulan, $request->tahun),
        'Planner.xlsx'
    );
}

public function armadaDelay(Request $request)
{
   $query = DB::table('logistik_pengiriman')
    ->whereNotNull('tanggal_dpt_unit')
    ->where(function ($q) {
        $q->whereNotNull('tanggal_tiba_gudang')
          ->orWhereNotNull('tanggal_tiba_gudang_2')
          ->orWhereNotNull('tanggal_tiba_gudang_3');
    });

    if ($request->filled('bulan')) {
        $query->whereMonth('tanggal_naik_logistik', $request->bulan);
    }

    if ($request->filled('tahun')) {
        $query->whereYear('tanggal_naik_logistik', $request->tahun);
    }

    $logistik = $query
        ->orderBy('tanggal_naik_logistik', 'DESC')
        ->get()
       ->map(function ($row) {

    $tibaGudang = $this->getTibaGudangTerdekat($row);

    if ($row->tanggal_dpt_unit && $tibaGudang) {

        $awal = new \DateTime(
            date('Y-m-d H:i:s', strtotime($row->tanggal_dpt_unit))
        );

        $akhir = new \DateTime(
            date('Y-m-d H:i:s', strtotime($tibaGudang))
        );

                $awalCek  = (clone $awal)->setTime(0, 0, 0);
                $akhirCek = (clone $akhir)->setTime(0, 0, 0);

                if ($akhir >= $awal) {
                    $diff = $awal->diff($akhir);

                    $row->lama_waktu_pencarian = $diff->days > 0
                        ? "{$diff->days} Hari {$diff->h} Jam {$diff->i} Menit"
                        : "{$diff->h} Jam {$diff->i} Menit";

                    $row->sla_dapat_mobil   = $akhirCek > $awalCek ? 'Delay' : 'On Time';
                    $row->status_pengiriman = $akhirCek > $awalCek ? 'Terlambat' : 'Sudah Dapat';
                } else {
                    $row->lama_waktu_pencarian = "0 Jam 0 Menit";
                    $row->sla_dapat_mobil      = 'On Time';
                    $row->status_pengiriman    = 'Sudah Dapat';
                }
            } else {
                $row->lama_waktu_pencarian = '-';
                $row->sla_dapat_mobil      = '-';
                $row->status_pengiriman    = '-';
            }

            return $row;
        })
        ->filter(fn($row) => $row->sla_dapat_mobil === 'Delay'); // Filter hanya Delay

    return view('spvplanner.armada_delay', compact('logistik'));
}



public function belumArmada(Request $request)
{
    $query = DB::table('logistik_pengiriman')
        ->where(function ($q) {
            $q->whereNull('rencana_kirim')
              ->orWhere('rencana_kirim', '')
              ->orWhereNull('tanggal_dpt_unit')
              ->orWhere('tanggal_dpt_unit', '');
        });

    if ($request->filled('bulan')) {
        $query->whereMonth('tanggal_naik_logistik', $request->bulan);
    }

    if ($request->filled('tahun')) {
        $query->whereYear('tanggal_naik_logistik', $request->tahun);
    }

    $logistik = $query
        ->orderBy('tanggal_naik_logistik', 'DESC')
        ->get();

    return view('spvplanner.belum_armada', compact('logistik'));
}

    public function baelumArmada(Request $request)
    {
        $query = DB::table('logistik_pengiriman')
            ->where(function ($q) {
                $q->whereIn('status_pengiriman', ['Belum Dapat', 'Pending', 'PENDING'])
                  ->orWhereIn('status_kendaraan', ['Belum Dapat', 'Pending', 'PENDING']);
            });

        if ($request->filled('bulan')) {
            $query->whereMonth('tanggal_naik_logistik', $request->bulan);
        }

        if ($request->filled('tahun')) {
            $query->whereYear('tanggal_naik_logistik', $request->tahun);
        }

        $logistik = $query->orderBy('tanggal_naik_logistik', 'DESC')->get();

        return view('spvplanner.belum_armada', compact('logistik'));
    }
public function delete($id)
{
    LogistikPengiriman::findOrFail($id)->delete();

    return redirect()
        ->back()
        ->with('success', 'Data berhasil dihapus');
}
}