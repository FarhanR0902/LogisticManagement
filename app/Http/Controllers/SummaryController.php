<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SummaryController extends Controller
{
    public function summaryArea()
    {
        $summary_area = DB::table('logistik_pengiriman')
            ->select(
                'area',
                DB::raw('COUNT(*) as total')
            )
            ->whereNotNull('area')
            ->groupBy('area')
            ->orderBy('area')
            ->get();

        return view(
            'summary.summary_area',
            compact('summary_area')
        );
    }

    public function summaryAreaDetail(Request $request)
    {
        $area = $request->area;

        $logistik = DB::table('logistik_pengiriman')
            ->where('area', $area)
            ->orderByDesc('tanggal_tiba')
            ->get();

        return view(
            'summary.summary_area_detail',
            compact('logistik', 'area')
        );
    }
}