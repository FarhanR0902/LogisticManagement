<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Models\LogistikPengiriman;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MonitoringController extends Controller
{

    // =====================================================
    // DASHBOARD
    // =====================================================

    public function dashboard()
    {
        $total_data = DB::table('logistik_pengiriman')->count();

        // ================= TIBA ONTIME =================

        $total_tiba_ontime = DB::table('logistik_pengiriman')
            ->where(function ($q) {

                $q->whereRaw("LOWER(status_akhir) LIKE '%on%'")
                  ->orWhereRaw("LOWER(status_akhir) LIKE '%time%'");

            })
            ->count();

        // ================= TIBA DELAY =================

        $total_tiba_delay = DB::table('logistik_pengiriman')
            ->whereRaw("LOWER(status_akhir) LIKE '%delay%'")
            ->count();

        $total_final_delay = $total_tiba_delay;

        // ================= BONGKAR ONTIME =================

        $total_bongkar_ontime = DB::table('logistik_pengiriman')
            ->where(function ($q) {

                $q->whereIn('sla_bongkar', [
                    'H+0',
                    'On Time',
                    'ONTIME'
                ])
                ->orWhere('overstay_days', '<=', 0);

            })
            ->count();

        // ================= BONGKAR DELAY =================

        $total_bongkar_delay = DB::table('logistik_pengiriman')
            ->where(function ($q) {

                $q->where('sla_bongkar', 'Delay')
                  ->orWhere('sla_bongkar', 'Critical Delay')
                  ->orWhere('overstay_days', '>', 0);

            })
            ->count();

        // ================= SUMMARY AREA =================

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


    // =====================================================
    // DATA MONITORING
    // =====================================================

    public function dataLogistik(Request $request)
    {

        $query = LogistikPengiriman::query();

        // FILTER PIC
        if ($request->pic_monitoring) {

            $query->where(
                'pic_monitoring',
                $request->pic_monitoring
            );

        }

        // FILTER BULAN
        if ($request->bulan) {

            $query->whereMonth(
                'tanggal_keluar_gudang',
                $request->bulan
            );

        }

        // FILTER TAHUN
        if ($request->tahun) {

            $query->whereYear(
                'tanggal_keluar_gudang',
                $request->tahun
            );

        }

        $logistik = $query
            ->latest()
            ->get();

        return view(
            'monitoring.data_monitoring',
            compact('logistik')
        );
    }


    // =====================================================
    // UPDATE MONITORING
    // =====================================================

    public function updateMonitoring(Request $request, $id)
    {

        $logistik = LogistikPengiriman::findOrFail($id);

        $request->validate([

            'act_urutan_bongkar'    => 'nullable',
            'tanggal_tiba_estimasi' => 'nullable|date',
            'tanggal_tiba'          => 'nullable|date',
            'sla_tiba'              => 'nullable',
            'tanggal_bongkar'       => 'nullable|date',
            'sla_bongkar'           => 'nullable',
            'reason_tiba'           => 'nullable',
            'reason_bongkar'        => 'nullable',
            'status_akhir'          => 'nullable',
            'remarks'               => 'nullable',

        ]);

        $logistik->update([

            'act_urutan_bongkar'    => $request->act_urutan_bongkar,

            'tanggal_tiba_estimasi' => $request->tanggal_tiba_estimasi,

            'tanggal_tiba'          => $request->tanggal_tiba,

            'sla_tiba'              => $request->sla_tiba,

            'tanggal_bongkar'       => $request->tanggal_bongkar,

            'sla_bongkar'           => $request->sla_bongkar,

            'reason_tiba'           => $request->reason_tiba,

            'reason_bongkar'        => $request->reason_bongkar,

            'status_akhir'          => $request->status_akhir,

            'remarks'               => $request->remarks,

        ]);

        return redirect()
            ->back()
            ->with(
                'success',
                'Data monitoring berhasil diupdate'
            );
    }


    // =====================================================
    // BONGKAR DELAY
    // =====================================================

    public function bongkarDelay()
    {

        $logistik = DB::table('logistik_pengiriman')
            ->where(function ($q) {

                $q->where('sla_bongkar', 'Delay')
                  ->orWhere('sla_bongkar', 'Critical Delay')
                  ->orWhere('overstay_days', '>', 0);

            })
            ->get();

        return view(
            'monitoring.bongkar_delay',
            compact('logistik')
        );
    }


    // =====================================================
    // BONGKAR ONTIME
    // =====================================================

    public function bongkarOntime()
    {

        $logistik = DB::table('logistik_pengiriman')
            ->where(function ($q) {

                $q->whereIn('sla_bongkar', [
                    'H+0',
                    'On Time',
                    'ONTIME'
                ])
                ->orWhere('overstay_days', '<=', 0);

            })
            ->get();

        return view(
            'monitoring.bongkar_ontime',
            compact('logistik')
        );
    }


    // =====================================================
    // SLA ONTIME
    // =====================================================

    public function slaOntime(Request $request)
    {

        $query = DB::table('logistik_pengiriman')
            ->where(function ($q) {

                $q->whereRaw("LOWER(status_akhir) LIKE '%on%'")
                  ->orWhereRaw("LOWER(status_akhir) LIKE '%time%'");

            });

        // FILTER BULAN
        if ($request->bulan) {

            $query->whereMonth(
                'tanggal_tiba',
                $request->bulan
            );
        }

        // FILTER TAHUN
        if ($request->tahun) {

            $query->whereYear(
                'tanggal_tiba',
                $request->tahun
            );
        }

        $logistik = $query
            ->orderBy('tanggal_tiba', 'DESC')
            ->get();

        return view(
            'monitoring.sla_ontime',
            compact('logistik')
        );
    }


    // =====================================================
    // SLA DELAY
    // =====================================================

    public function slaDelay(Request $request)
    {

        $query = DB::table('logistik_pengiriman')
            ->whereRaw("LOWER(status_akhir) LIKE '%delay%'");

        // FILTER BULAN
        if ($request->bulan) {

            $query->whereMonth(
                'tanggal_tiba',
                $request->bulan
            );
        }

        // FILTER TAHUN
        if ($request->tahun) {

            $query->whereYear(
                'tanggal_tiba',
                $request->tahun
            );
        }

        // FILTER TIPE
        if ($request->tipe == 'mobil') {

            $query->where(
                'sla_dapat_mobil',
                'Delay'
            );
        }

        $logistik = $query
            ->orderBy('tanggal_tiba', 'DESC')
            ->get();

        return view(
            'monitoring.sla_delay',
            compact('logistik')
        );
    }


    // =====================================================
    // SUMMARY AREA
    // =====================================================

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

        return view(
            'monitoring.summary_area',
            compact('summary_area')
        );
    }


    // =====================================================
    // SUMMARY AREA DETAIL
    // =====================================================

    public function summaryAreaDetail(Request $request)
    {

        $area = $request->area;

        $logistik = DB::table('logistik_pengiriman')
            ->where('area', $area)
            ->get();

        return view(
            'monitoring.summary_area_detail',
            compact('logistik', 'area')
        );
    }
}