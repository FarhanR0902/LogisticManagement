<?php

namespace App\Http\Controllers;

use App\Exports\StorageExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;

class StorageController extends Controller
{
    private const TABLE = 'logistik_storage';

    /* ---------- halaman: KPI + filter saja, tanpa data tabel ---------- */
    public function index(Request $request)
    {
        $base = $this->filtered($request);

        $total_data   = (clone $base)->count();
        $total_muatan = (clone $base)->sum('nilai_muatan');

        // biaya_kirim terulang di tiap row satu shipment, jadi ambil MAX per shipment
        $total_biaya = DB::query()
            ->fromSub(
                (clone $base)->selectRaw('MAX(biaya_kirim) AS b')->groupBy('no_shipment'),
                't'
            )->sum('b');

        $cost_ratio = $total_muatan > 0 ? ($total_biaya / $total_muatan) * 100 : 0;

        $list_area = DB::table(self::TABLE)
            ->whereNotNull('area')->where('area', '!=', '')
            ->distinct()->orderBy('area')->pluck('area');

        $columns = $this->columns();

        return view('storage.index', compact(
            'total_data', 'total_biaya', 'total_muatan', 'cost_ratio', 'list_area', 'columns'
        ));
    }

    /* ---------- endpoint data (server-side) ---------- */
    public function data(Request $request)
    {
        $columns = $this->columns();

        $query = $this->filtered($request);
        $recordsTotal = (clone $query)->count();

        $search = trim((string) $request->input('search'));
        if ($search !== '') {
            // tanggal tampil d-m-Y, di DB Y-m-d: cari dua-duanya
            $variants = [$search];
            if (preg_match('/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/', $search, $m)) {
                $variants[] = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
            }

            $query->where(function ($q) use ($variants, $columns) {
                foreach ($variants as $v) {
                    foreach ($columns as $c) {
                        $q->orWhereRaw("CAST(`{$c}` AS CHAR) LIKE ?", ["%{$v}%"]);
                    }
                }
            });
        }

        $recordsFiltered = (clone $query)->count();

        // nama kolom divalidasi terhadap kolom asli tabel (aman dari SQL injection)
        $orderCol = $request->input('order_col');
        $orderCol = in_array($orderCol, $columns, true) ? $orderCol : 'tanggal_naik_logistik';
        $orderDir = strtolower($request->input('order_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $start  = max(0, (int) $request->input('start', 0));
        $length = min(max(1, (int) $request->input('length', 25)), 200);

        $rows = $query->orderBy($orderCol, $orderDir)
            ->orderBy('id', 'desc')
            ->skip($start)->take($length)
            ->get();

        return response()->json([
            'draw'            => (int) $request->input('draw', 1),
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $rows,
        ]);
    }

    public function export(Request $request)
    {
        return Excel::download(new StorageExport($request), 'storage-logistik.xlsx');
    }

    public function deleteAll()
    {
        DB::table(self::TABLE)->truncate();

        return back()->with('success', 'Semua data archive berhasil dihapus');
    }

    /* ---------- helper ---------- */
    private function filtered(Request $request)
    {
        $q = DB::table(self::TABLE);

        if ($request->filled('year'))  $q->whereYear('tanggal_naik_logistik', $request->year);
        if ($request->filled('month')) $q->whereMonth('tanggal_naik_logistik', $request->month);
        if ($request->filled('area'))  $q->where('area', $request->area);

        return $q;
    }

    /** Semua kolom asli tabel, urutan sama seperti di database */
    private function columns(): array
    {
        return Cache::remember('storage_cols', 600, fn() =>
            Schema::getColumnListing(self::TABLE)
        );
    }
}