<?php

namespace App\Http\Controllers;
use App\Exports\StorageExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StorageController extends Controller
{
   public function index(Request $request)
{
    // ================= BASE QUERY =================
    $baseQuery = DB::table('logistik_storage');

    // ================= APPLY FILTER =================
    if ($request->filled('year')) {
        $baseQuery->whereYear('tanggal_naik_logistik', $request->year);
    }

    if ($request->filled('month')) {
        $baseQuery->whereMonth('tanggal_naik_logistik', $request->month);
    }

    if ($request->filled('area')) {
        $baseQuery->where('area', $request->area);
    }

    // ================= DATA TABLE =================
    $data = (clone $baseQuery)
        ->orderBy('tanggal_naik_logistik', 'desc')
        ->get();

    // ================= LIST AREA (GLOBAL) =================
    $list_area = DB::table('logistik_storage')
        ->select('area')
        ->whereNotNull('area')
        ->distinct()
        ->orderBy('area')
        ->get();

    // ================= KPI (PAKAI FILTER YANG SAMA) =================
    $total_data = (clone $baseQuery)->count();
    $total_biaya = (clone $baseQuery)->sum('biaya_kirim');
    $total_muatan = (clone $baseQuery)->sum('nilai_muatan');

    $cost_ratio = $total_muatan > 0
        ? ($total_biaya / $total_muatan) * 100
        : 0;

    return view('storage.index', compact(
        'data',
        'list_area',
        'total_data',
        'total_biaya',
        'total_muatan',
        'cost_ratio'
    ));

}

public function export(Request $request)
{
    return Excel::download(
        new StorageExport($request),
        'storage-logistik.xlsx'
    );
}

public function deleteAll()
{
    DB::table('logistik_storage')->delete();

    return back()->with(
        'success',
        'Semua data archive berhasil dihapus'
    );
}
}