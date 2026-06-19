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
        $bulan = $request->bulan;
        $tahun = $request->tahun;
        $area  = $request->area;

        $query = LogistikPengiriman::query();

        // ================= FILTER =================
        if ($bulan && $tahun) {
            $query->whereMonth('tanggal_naik_logistik', $bulan)
                  ->whereYear('tanggal_naik_logistik', $tahun);
        }

        if ($area) {
            $query->where('area', $area);
        }

        
$list_area = DB::table('logistik_pengiriman')
    ->select('area')
    ->whereNotNull('area')
    ->distinct()
    ->orderBy('area')
    ->get();


        // ================= KPI =================
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

        $total_muatan = (clone $query)->sum('nilai_muatan');

        $total_biaya = (clone $query)->sum('biaya_kirim');

        // ================= SUMMARY AREA =================
        $summary_area = (clone $query)
            ->select('area', DB::raw('COUNT(*) as total'))
            ->groupBy('area')
            ->orderByDesc('total')
            ->get();

        // ================= TOP AREA DELAY =================
        $top_area_delay = (clone $query)
            ->select('area', DB::raw('COUNT(*) as total_delay'))
            ->whereIn('status_akhir', ['Delay', 'Critical Delay'])
            ->groupBy('area')
            ->orderByDesc('total_delay')
            ->limit(10)
            ->get();

        // ================= TOP PLANNER =================
        $top_planner = (clone $query)
            ->select('planner', DB::raw('COUNT(*) as total'))
            ->groupBy('planner')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return view('dashboard', compact(
            'total_data',
            'total_loading_ontime',
            'total_loading_delay',
            'armada',
            'belum_armada',
            'process',
            'total_muatan',
            'total_biaya',
            'summary_area',
            'top_area_delay',
            'list_area',
            'top_planner'
        ));
    }

    public function dashboardPlanner()
{
    $data = $this->getDashboardData();
    return view('planner.dashboard', $data);
}

public function dashboardMonitoring()
{
    $data = $this->getDashboardData();
    return view('monitoring.dashboard', $data);
}

public function dashboardSpv()
{
    $data = $this->getDashboardData();
    return view('spv.dashboard', $data);
}

public function dashboardManager()
{
    $data = $this->getDashboardData();
    return view('manager.dashboard', $data);
}

public function dashboardSales()
{
    $data = $this->getDashboardData();
    return view('sales.dashboard', $data);
}
private function getDashboardData()
{
    return [
        'total' => \DB::table('logistik_pengiriman')->count(),
        'ontime' => \DB::table('logistik_pengiriman')
            ->whereIn('status_akhir', ['On Time', 'Ontime'])
            ->count(),
        'delay' => \DB::table('logistik_pengiriman')
            ->whereIn('status_akhir', ['Delay', 'Critical Delay'])
            ->count(),
    ];
}


 public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);

        Excel::import(new LogistikImport, $request->file('file'));

        return back()->with('success', 'Import berhasil');
    }
}
    /* =========================================================
     * SLA ONTIME / DELAY
     * ========================================================= */
    public function sla($type, Request $request)
    {
        $bulan = $request->bulan;
        $area  = $request->area;

        $query = LogistikPengiriman::query();

        // FILTER BULAN
        if ($bulan) {
            $query->whereMonth('tanggal_naik_logistik', $bulan);
        }

        // FILTER AREA
        if ($area) {
            $query->where('area', $area);
        }

        // SLA ONTIME
        if ($type == 'ontime') {

            $list = (clone $query)
                ->whereIn('status_akhir', ['On Time', 'Ontime'])
                ->get();

            $list_area = LogistikPengiriman::select('area')
                ->distinct()
                ->orderBy('area')
                ->get();

            return view('sla_ontime', [
                'list'       => $list,
                'list_area'  => $list_area,
                'title'      => '🚛 SLA DAPAT MOBIL - ONTIME',
                'bulan'      => $bulan,
                'tahun'      => $request->tahun,
                'area'       => $area,
            ]);
        }

        // SLA DELAY
        if ($type == 'delay') {

            $list = (clone $query)
                ->whereIn('status_akhir', ['Delay', 'Critical Delay'])
                ->get();

            $list_area = LogistikPengiriman::select('area')
                ->distinct()
                ->orderBy('area')
                ->get();

            return view('sla_delay', [
                'list'       => $list,
                'list_area'  => $list_area,
                'title'      => '🚛 SLA DAPAT MOBIL - DELAY',
                'bulan'      => $bulan,
                'tahun'      => $request->tahun,
                'area'       => $area,
            ]);
        }

        abort(404);
    }
    
    

    /* =========================================================
     * DATA LOGISTIK
     * ========================================================= */
    public function dataLogistik()
    {
        $logistik = LogistikPengiriman::orderBy('id', 'DESC')->get();

        return view('data_logistik', compact('logistik'));
    }

    /* =========================================================
     * SLA ONTIME
     * ========================================================= */
    public function slaOntime(Request $request)
    {
        $query = LogistikPengiriman::query();

        if ($request->bulan) {
            $query->whereMonth('rencana_kirim', $request->bulan);
        }

        if ($request->tahun) {
            $query->whereYear('rencana_kirim', $request->tahun);
        }

        if ($request->area) {
            $query->where('area', $request->area);
        }

        $list = $query
            ->where('sla_dapat_mobil', 'Ontime')
            ->get();

        $list_area = LogistikPengiriman::select('area')
            ->distinct()
            ->orderBy('area')
            ->get();

        return view('sla_ontime', compact(
            'list',
            'list_area'
        ));
    }

    /* =========================================================
     * SLA DELAY
     * ========================================================= */
    public function slaDelay(Request $request)
    {
        $query = LogistikPengiriman::query();

        if ($request->bulan) {
            $query->whereMonth('rencana_kirim', $request->bulan);
        }

        if ($request->tahun) {
            $query->whereYear('rencana_kirim', $request->tahun);
        }

        if ($request->area) {
            $query->where('area', $request->area);
        }

        $list = $query
            ->whereIn('sla_dapat_mobil', ['Delay', 'Critical Delay'])
            ->get();

        $list_area = LogistikPengiriman::select('area')
            ->distinct()
            ->orderBy('area')
            ->get();

        $title = '🚛 SLA DAPAT MOBIL - DELAY';

        return view('sla_delay', compact(
            'list',
            'list_area',
            'title'
        ));
    }


    public function edit($id)
{
    $data['logistik'] = LogistikPengiriman::findOrFail($id);

    return view('logistik.edit', $data);
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
     * ARMADA READY
     * ========================================================= */
    public function armada(Request $request)
    {
        $query = LogistikPengiriman::where(
            'ketersediaan_unit',
            'Sudah Dapat'
        );

        // FILTER BULAN
        if ($request->bulan) {
            $query->whereMonth(
                'tanggal_naik_logistik',
                $request->bulan
            );
        }

        // FILTER TAHUN
        if ($request->tahun) {
            $query->whereYear(
                'tanggal_naik_logistik',
                $request->tahun
            );
        }

        $logistik = $query
            ->orderBy('id', 'DESC')
            ->get();

        return view('armada', compact('logistik'));
    }

    /* =========================================================
     * BELUM ARMADA
     * ========================================================= */
public function belumArmada(Request $request)
{
    $query = LogistikPengiriman::where(
        'ketersediaan_unit',
        'Belum Dapat'
    );

    // FILTER BULAN
    if ($request->bulan) {

        $query->whereMonth(
            'tanggal_naik_logistik',
            $request->bulan
        );
    }

    // FILTER TAHUN
    if ($request->tahun) {

        $query->whereYear(
            'tanggal_naik_logistik',
            $request->tahun
        );
    }

    $logistik = $query
        ->orderBy('id', 'DESC')
        ->get();

    return view(
        'belum_armada',
        compact('logistik')
    );
}

    /* =========================================================
     * STORE DATA
     * ========================================================= */
    public function store(Request $request)
    {
        LogistikPengiriman::create($request->all());

        return redirect('/datalogistik')
            ->with('success', 'Data berhasil ditambahkan');
    }

    /* =========================================================
     * UPDATE DATA
     * ========================================================= */
    public function update(Request $request, $id)
    {
        $data = LogistikPengiriman::findOrFail($id);

        $data->update($request->all());

        return back()
            ->with('success', 'Data berhasil diupdate');
    }

    /* =========================================================
     * DELETE DATA
     * ========================================================= */
    public function delete($id)
    {
        $data = LogistikPengiriman::findOrFail($id);

        $data->delete();

        return back()
            ->with('success', 'Data berhasil dihapus');
    }

    /* =========================================================
     * CHART STATUS
     * ========================================================= */
    public function chartStatus()
    {
        $data = LogistikPengiriman::select(
                'status_akhir',
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('status_akhir')
            ->get();

        return response()->json($data);
    }

    /* =========================================================
     * EXPORT CSV
     * ========================================================= */
    public function export()
    {
        $filename = 'logistik.csv';

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=$filename",
        ];

        $callback = function () {

            $handle = fopen('php://output', 'w');

            // HEADER CSV
            fputcsv($handle, [
                'No',
                'Shipment',
                'Tujuan',
                'Area',
                'Status'
            ]);

            // DATA
            $data = LogistikPengiriman::all();

            foreach ($data as $row) {

                fputcsv($handle, [
                    $row->id,
                    $row->no_shipment,
                    $row->tujuan,
                    $row->area,
                    $row->status_akhir
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
