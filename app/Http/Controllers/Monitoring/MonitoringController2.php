<?php

namespace App\Http\Controllers\Monitoring;
use App\Models\LogistikPengiriman;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class MonitoringController extends Controller
{
    public function dashboard()
    {
        $total_data = DB::table('logistik_pengiriman')->count();

        // ================= NORMALIZED FUNCTION (BIAR TIDAK 0) =================

        // TIBA ONTIME
        $total_tiba_ontime = DB::table('logistik_pengiriman')
            ->where(function($q){
                $q->whereRaw("LOWER(status_akhir) LIKE '%on%'")
                  ->orWhereRaw("LOWER(status_akhir) LIKE '%time%'");
            })
            ->count();

        // TIBA DELAY
        $total_tiba_delay = DB::table('logistik_pengiriman')
            ->whereRaw("LOWER(status_akhir) LIKE '%delay%'")
            ->count();

        $total_final_delay = $total_tiba_delay;

        // ================= BONGKAR ONTIME =================
        $total_bongkar_ontime = DB::table('logistik_pengiriman')
            ->where(function($q){
                $q->whereIn('sla_bongkar', ['H+0','On Time','ONTIME'])
                  ->orWhere('overstay_days','<=',0);
            })
            ->count();

        // ================= BONGKAR DELAY =================
        $total_bongkar_delay = DB::table('logistik_pengiriman')
            ->where(function($q){
                $q->where('sla_bongkar','Delay')
                  ->orWhere('sla_bongkar','Critical Delay')
                  ->orWhere('overstay_days','>',0);
            })
            ->count();

        // ================= AREA =================
        $summary_area = DB::table('logistik_pengiriman')
            ->select(
                'area',
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('area')
            ->orderByDesc('total')
            ->get();

        return view('monitoring.dashboard', compact(
            'total_data',
            'total_tiba_ontime',
            'total_tiba_delay',
            'total_final_delay',
            'total_bongkar_ontime',
            'total_bongkar_delay',
            'summary_area'
        ));
    }
public function dataLogistik()
{
    $logistik = LogistikPengiriman::latest()->get();

    return view('monitoring.data_monitoring', compact('logistik'));
}
    // ================= BONGKAR DELAY =================
    public function bongkarDelay()
    {
        $logistik = DB::table('logistik_pengiriman')
            ->where(function($q){
                $q->where('sla_bongkar','Delay')
                  ->orWhere('sla_bongkar','Critical Delay')
                  ->orWhere('overstay_days','>',0);
            })
            ->get();

        return view('monitoring.bongkar_delay', compact('logistik'));
    }

    // ================= BONGKAR ONTIME =================
    public function bongkarOntime()
    {
        $logistik = DB::table('logistik_pengiriman')
            ->where(function($q){
                $q->whereIn('sla_bongkar',['H+0','On Time','ONTIME'])
                  ->orWhere('overstay_days','<=',0);
            })
            ->get();

        return view('monitoring.bongkar_ontime', compact('logistik'));
    }

    // ================= SLA ONTIME =================
    public function slaOntime(Request $request)
    {
        $query = DB::table('logistik_pengiriman')
            ->where(function($q){
                $q->whereRaw("LOWER(status_akhir) LIKE '%on%'")
                  ->orWhereRaw("LOWER(status_akhir) LIKE '%time%'");
            });

        if ($request->bulan) {
            $query->whereMonth('tanggal_tiba', $request->bulan);
        }

        if ($request->tahun) {
            $query->whereYear('tanggal_tiba', $request->tahun);
        }

        $logistik = $query->orderBy('tanggal_tiba','DESC')->get();

        return view('monitoring.sla_ontime', compact('logistik'));
    }

    // ================= SLA DELAY =================
    public function slaDelay(Request $request)
    {
        $query = DB::table('logistik_pengiriman')
            ->whereRaw("LOWER(status_akhir) LIKE '%delay%'");

        if ($request->bulan) {
            $query->whereMonth('tanggal_tiba', $request->bulan);
        }

        if ($request->tahun) {
            $query->whereYear('tanggal_tiba', $request->tahun);
        }

        if ($request->tipe == 'mobil') {
            $query->where('sla_dapat_mobil', 'Delay');
        }

        $logistik = $query->orderBy('tanggal_tiba','DESC')->get();

        return view('monitoring.sla_delay', compact('logistik'));
    }

    public function summaryArea()
    {
        $summary_area = DB::table('logistik_pengiriman')
            ->select(
                'area',
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('area')
            ->orderByDesc('total')
            ->get();

        return view('monitoring.summary_area', compact('summary_area'));
    }

    public function summaryAreaDetail(Request $request)
    {
        $area = $request->area;

        $logistik = DB::table('logistik_pengiriman')
            ->where('area', $area)
            ->get();

        return view('monitoring.summary_area_detail', compact('logistik', 'area'));
    }
}