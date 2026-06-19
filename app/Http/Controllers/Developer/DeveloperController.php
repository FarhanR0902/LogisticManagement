<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class DeveloperController extends Controller
{
    public function dashboard()
    {
        // ================= FILTER (WAJIB PALING ATAS) =================
        $month = request('month');
        $year  = request('year');
        $area  = request('area');

        // ================= BASE QUERY =================
        $base = DB::table('logistik_pengiriman');

        // ================= APPLY FILTER =================
        if ($month) {
            $base->whereMonth('tanggal_naik_logistik', substr($month, 5, 2))
                ->whereYear('tanggal_naik_logistik', substr($month, 0, 4));
        }

        if ($year) {
            $base->whereYear('tanggal_naik_logistik', $year);
        }

        if ($area) {
            $base->where('area', $area);
        }

        // ================= TOTAL =================
        $total_data = (clone $base)->count();

        // ================= GUDANG =================
      $gudang_ontime = (clone $base)
    ->whereRaw("LOWER(TRIM(sla_loading)) IN ('on time','ontime','h+0')")
    ->count();

$gudang_delay = (clone $base)
    ->whereRaw("LOWER(TRIM(sla_loading)) IN ('delay','h+1','h+2','h>2','critical delay')")
    ->count();

        // ================= CUSTOMER =================
        $customer_ontime = (clone $base)
            ->whereIn('status_akhir', ['On Time', 'OnTime', 'ONTIME'])
            ->count();

        $customer_delay = (clone $base)
            ->whereIn('status_akhir', ['Delay', 'Critical Delay'])
            ->count();

        // ================= BONGKAR =================
        $bongkar_ontime = (clone $base)
            ->where(function ($q) {
                $q->whereIn('sla_bongkar', ['H+0', 'On Time', 'ONTIME'])
                    ->orWhere('overstay_days', '<=', 0);
            })
            ->count();

        $bongkar_delay = (clone $base)
            ->where(function ($q) {
                $q->whereIn('sla_bongkar', ['Delay', 'Critical Delay'])
                    ->orWhere('overstay_days', '>', 0);
            })
            ->count();

        // ================= SUMMARY AREA =================
        $summary_area = (clone $base)
            ->select(
                'area',
                DB::raw('COUNT(*) as total_shipment'),
                DB::raw('SUM(COALESCE(biaya_kirim,0)) as total_biaya'),
                DB::raw('SUM(COALESCE(nilai_muatan,0)) as total_muatan')
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
                DB::raw('SUM(COALESCE(biaya_kirim,0)) as total_biaya'),
                DB::raw('SUM(COALESCE(nilai_muatan,0)) as total_muatan')
            )
            ->whereNotNull('tujuan')
            ->where('tujuan', '!=', '')
            ->groupBy('tujuan')
            ->orderByDesc('total_shipment')
            ->get();

        // ================= TOTAL FINANCE =================
        $totalNilaiMuatan = (clone $base)->sum('nilai_muatan');
        $totalBiayaKirim  = (clone $base)->sum('biaya_kirim');

        // ================= PLANNER =================
     $planner_ontime = (clone $base)
    ->whereRaw("LOWER(TRIM(status_akhir)) IN ('on time','ontime')")
    ->count();

$planner_delay = (clone $base)
    ->whereRaw("LOWER(TRIM(status_akhir)) LIKE '%delay%'")
    ->count();

        $planner_armada = (clone $base)
            ->whereNotNull('mobil')
            ->count();

        $planner_belum_armada = (clone $base)
            ->whereNull('mobil')
            ->count();

        // ================= EKSPEDISI =================
        $exp = (clone $base)
            ->select('kategori_ekspedisi', DB::raw('COUNT(*) as total'))
            ->whereIn('kategori_ekspedisi', ['Oncall', 'Kontrak'])
            ->groupBy('kategori_ekspedisi')
            ->get();

        $list_area = DB::table('logistik_pengiriman')
            ->select('area')
            ->whereNotNull('area')
            ->distinct()
            ->orderBy('area')
            ->get();

        $label = $exp->pluck('kategori_ekspedisi')->toArray();
        $value = $exp->pluck('total')->toArray();

        // ================= MONITORING =================
        $summary_monitoring = [
            'tiba_ontime'    => $total_data ? ($customer_ontime / $total_data) * 100 : 0,
            'tiba_delay'     => $total_data ? ($customer_delay / $total_data) * 100 : 0,
            'bongkar_ontime' => $total_data ? ($bongkar_ontime / $total_data) * 100 : 0,
            'bongkar_delay'  => $total_data ? ($bongkar_delay / $total_data) * 100 : 0,
        ];

        // ================= RATIO =================
        $total_status = $planner_ontime + $planner_delay;

        $ontime_rate = $total_status ? ($planner_ontime / $total_status) * 100 : 0;
        $delay_rate  = $total_status ? ($planner_delay / $total_status) * 100 : 0;

        $total_armada = $planner_armada + $planner_belum_armada;

        $armada_rate  = $total_armada ? ($planner_armada / $total_armada) * 100 : 0;
        $pending_rate = $total_armada ? ($planner_belum_armada / $total_armada) * 100 : 0;

        // ================= RETURN VIEW =================
        return view('developer.dashboard', [
            'total_data' => $total_data,

            'gudang_ontime' => $gudang_ontime,
            'gudang_delay' => $gudang_delay,

            'customer_ontime' => $customer_ontime,
            'customer_delay' => $customer_delay,

            'bongkar_ontime' => $bongkar_ontime,
            'bongkar_delay' => $bongkar_delay,

            'summary_area' => $summary_area,
            'summary_tujuan' => $summary_tujuan,

            'totalNilaiMuatan' => $totalNilaiMuatan,
            'totalBiayaKirim' => $totalBiayaKirim,

            'planner_ontime' => $planner_ontime,
            'planner_delay' => $planner_delay,
            'planner_armada' => $planner_armada,
            'planner_belum_armada' => $planner_belum_armada,

            'ontime_rate' => $ontime_rate,
            'delay_rate' => $delay_rate,
            'armada_rate' => $armada_rate,
            'pending_rate' => $pending_rate,

            'label' => $label,
            'value' => $value,
            'list_area'=>$list_area,

            'summary_monitoring' => $summary_monitoring,
        ]);
    }
    public function dataLogistik()
    {
        $logistik = DB::table('logistik_pengiriman')
            ->orderByDesc('id')
            ->get();

        return view('developer.datalogistik', compact('logistik'));
    }
}
