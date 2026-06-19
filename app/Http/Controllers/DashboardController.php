<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function dashboard(Request $request)
    {
        // ================= BASE QUERY =================
        $base = DB::table('logistik_pengiriman');
        $this->applyFilter($base, $request);

        // clone biar aman (WAJIB pattern ini)
        $query = clone $base;

        // ================= TOTAL =================
        $total_data = (clone $query)->count();

        // ================= GUDANG =================
        $gudang_ontime = (clone $query)->where(function ($q) {
            $q->whereIn('sla_loading', ['H+0', 'On Time', 'ONTIME']);
        })->count();

        $gudang_delay = (clone $query)->where(function ($q) {
            $q->whereIn('sla_loading', ['H+1', 'H+2', 'H>2', 'Delay', 'Critical Delay']);
        })->count();

        // ================= CUSTOMER =================
        $customer_ontime = (clone $query)
            ->whereNotNull('tanggal_tiba')
            ->whereRaw("
                DATEDIFF(
                    tanggal_tiba,
                    DATE_ADD(rencana_kirim, INTERVAL transport_lead_time DAY)
                ) <= 0
            ")->count();

        $customer_delay = (clone $query)
            ->whereNotNull('tanggal_tiba')
            ->whereRaw("
                DATEDIFF(
                    tanggal_tiba,
                    DATE_ADD(rencana_kirim, INTERVAL transport_lead_time DAY)
                ) > 0
            ")->count();

        // ================= BONGKAR =================
        $bongkar_ontime = (clone $query)
            ->whereNotNull('sla_bongkar')
            ->where(function ($q) {
                $q->whereRaw("LOWER(sla_bongkar) = 'on time'")
                  ->orWhere('sla_bongkar', 'ONTIME')
                  ->orWhere('sla_bongkar', 'H+0');
            })->count();

        $bongkar_delay = (clone $query)
            ->whereNotNull('sla_bongkar')
            ->where(function ($q) {
                $q->whereRaw("LOWER(sla_bongkar) = 'delay'")
                  ->orWhereRaw("LOWER(sla_bongkar) = 'critical delay'")
                  ->orWhereIn('sla_bongkar', ['H+1','H+2','H>2']);
            })->count();

        // ================= ARMADA =================
        $planner_armada = (clone $query)
            ->where('ketersediaan_unit', 'Sudah Dapat')
            ->count();

        $planner_belum_armada = (clone $query)
            ->where('ketersediaan_unit', 'Belum Dapat')
            ->count();

        // ================= PLANNER KPI =================
        $planner_ontime = $gudang_ontime;
        $planner_delay  = $gudang_delay;

        // ================= TOTAL NILAI =================
        $totalNilaiMuatan = (clone $query)->selectRaw("
            SUM(
                CASE
                    WHEN nilai_muatan IS NULL THEN 0
                    ELSE CAST(REPLACE(REPLACE(nilai_muatan,'.',''),',','') AS UNSIGNED)
                END
            ) as total
        ")->value('total');

        // ================= TOTAL BIAYA =================
        $totalBiayaKirim = (clone $query)->selectRaw("
            SUM(
                CASE
                    WHEN biaya_kirim IS NULL THEN 0
                    ELSE CAST(REPLACE(REPLACE(biaya_kirim,'.',''),',','') AS UNSIGNED)
                END
            ) as total
        ")->value('total');

        // ================= SUMMARY AREA =================
        $summary_area = (clone $query)
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

        // ================= SUMMARY TUJUAN =================
        $summary_tujuan = (clone $query)
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
        $ekspedisi = (clone $query)
            ->select('kategori_ekspedisi', DB::raw('COUNT(*) as total'))
            ->whereNotNull('kategori_ekspedisi')
            ->groupBy('kategori_ekspedisi')
            ->get();

        $label = $ekspedisi->pluck('kategori_ekspedisi');
        $value = $ekspedisi->pluck('total');

        // ================= RATIO =================
        $total_status = $planner_ontime + $planner_delay;

        $ontime_rate = $total_status ? ($planner_ontime / $total_status) * 100 : 0;
        $delay_rate  = $total_status ? ($planner_delay / $total_status) * 100 : 0;

        $total_armada = $planner_armada + $planner_belum_armada;

        $armada_rate  = $total_armada ? ($planner_armada / $total_armada) * 100 : 0;
        $pending_rate = $total_armada ? ($planner_belum_armada / $total_armada) * 100 : 0;

        // ================= MONITORING =================
        $summary_monitoring = [
            'tiba_ontime' => $total_data ? ($customer_ontime / $total_data) * 100 : 0,
            'tiba_delay'  => $total_data ? ($customer_delay / $total_data) * 100 : 0,
            'bongkar_ontime' => $total_data ? ($bongkar_ontime / $total_data) * 100 : 0,
            'bongkar_delay'  => $total_data ? ($bongkar_delay / $total_data) * 100 : 0,
        ];

        // ================= AREA LIST =================
        $list_area = $this->getArea();

        // ================= ROLE VIEW SWITCH =================
        $role = auth()->user()->role;

        return view("dashboard.$role", compact(
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

    // ================= FILTER =================
    private function applyFilter($query, $request)
    {
        if ($request->area) {
            $query->where('area', $request->area);
        }

        if ($request->date) {
            $query->whereDate('tanggal_naik_logistik', $request->date);
        }

        if ($request->month) {
            $query->whereMonth('tanggal_naik_logistik', substr($request->month, 5, 2))
                  ->whereYear('tanggal_naik_logistik', substr($request->month, 0, 4));
        }

        if ($request->year) {
            $query->whereYear('tanggal_naik_logistik', $request->year);
        }

        return $query;
    }

    // ================= AREA =================
    private function getArea()
    {
        return DB::table('logistik_pengiriman')
            ->select('area')
            ->whereNotNull('area')
            ->groupBy('area')
            ->orderBy('area')
            ->get();
    }
    private function getDashboardView()
{
    $role = auth()->user()->role ?? 'guest';

    return match($role) {
        'manager' => 'manager.dashboard',
        'sales' => 'sales.dashboard',
        'spvplanner' => 'spvplanner.dashboard',
        'spvmonitoring' => 'spvmonitoring.dashboard',
        default => 'dashboard',
    };
}
}