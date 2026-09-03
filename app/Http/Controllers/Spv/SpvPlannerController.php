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
    private const PULAU_MAP = [
        'JAWA'       => ['JABODEBEK', 'BANTEN', 'JAWA_BARAT', 'JAWA_TENGAH', 'JAWA_TIMUR', 'YOGYAKARTA'],
        'SUMATERA'   => ['ACEH', 'SUMATERA_UTARA', 'SUMATERA_BARAT', 'RIAU', 'KEP._RIAU', 'JAMBI', 'SUMATERA_SELATAN', 'BENGKULU', 'LAMPUNG', 'KEP._BANGKA_BELITUNG'],
        'KALIMANTAN' => ['KALIMANTAN_BARAT', 'KALIMANTAN_TENGAH', 'KALIMANTAN_SELATAN', 'KALIMANTAN_TIMUR', 'KALIMANTAN_UTARA'],
        'SULAWESI'   => ['SULAWESI_UTARA', 'SULAWESI_TENGAH', 'SULAWESI_SELATAN', 'SULAWESI_TENGGARA', 'SULAWESI_BARAT', 'GORONTALO'],
        'BALI_NUSRA' => ['PROV._BALI', 'NUSA_TENGGARA_BARAT', 'NUSA_TENGGARA_TIMUR'],
        'MALUKU'     => ['PROV._MALUKU', 'PROV._MALUKU_UTARA'],
        'PAPUA'      => ['PROV._PAPUA', 'PAPUA_BARAT', 'PAPUA_BARAT_DAYA', 'PAPUA_SELATAN', 'PAPUA_TENGAH'],
    ];

    /**
     * =====================================================
     * IMPORT / EXPORT PASURUAN
     * =====================================================
     */
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

    public function exportPasuruan()
    {
        return Excel::download(
            new PasuruanExport(),
            'Data_Logistik_Pasuruan.xlsx'
        );
    }

    /**
     * =====================================================
     * TARIF PENGIRIMAN (CRUD)
     * =====================================================
     */
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

    /**
     * =====================================================
     * FILTER HELPERS
     * =====================================================
     */
    private function applyFilter($query, $request)
    {
        if ($request->area) {
            $query->where('area', $request->area);
        }

        if ($request->dist_channel) {
            $query->where('dist_channel', $request->dist_channel);
        }

        if ($request->filled('pulau') && isset(self::PULAU_MAP[$request->pulau])) {
            $query->whereIn('area', self::PULAU_MAP[$request->pulau]);
        }

        if ($request->date) {
            $query->whereDate('tanggal_naik_logistik', $request->date);
        }

        if ($request->month) {
            $query->whereMonth('tanggal_naik_logistik', substr($request->month, 5, 2));
            $query->whereYear('tanggal_naik_logistik', substr($request->month, 0, 4));
        }

        if ($request->year) {
            $query->whereYear('tanggal_naik_logistik', $request->year);
        }

        return $query;
    }

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

    private function getArea()
    {
        return DB::table('logistik_pengiriman')
            ->select('area')
            ->whereNotNull('area')
            ->groupBy('area')
            ->orderBy('area')
            ->get();
    }

    /**
     * =====================================================
     * DASHBOARD PASURUAN
     * =====================================================
     */
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

        $list_area = $this->getArea();

        return view('spvplanner.dashboard_pasuruan', compact(
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
            'list_area'
        ));
    }

    // public function dataLogistikPasuruan()
    // {
    //     $logistik = LogistikPengirimanPasuruan::orderByDesc('id')->get();

    //     $planners = LogistikPengirimanPasuruan::select('planner_pasuruan')
    //         ->whereNotNull('planner_pasuruan')
    //         ->where('planner_pasuruan', '!=', '')
    //         ->distinct()
    //         ->orderBy('planner_pasuruan')
    //         ->pluck('planner_pasuruan');

    //     $areas = LogistikPengirimanPasuruan::select('area_pasuruan')
    //         ->whereNotNull('area_pasuruan')
    //         ->where('area_pasuruan', '!=', '')
    //         ->distinct()
    //         ->orderBy('area_pasuruan')
    //         ->pluck('area_pasuruan');

    //     return view('spvplanner.data_logistik_pasuruan', compact(
    //         'logistik',
    //         'planners',
    //         'areas'
    //     ));
    // }

    /**
     * =====================================================
     * HALAMAN DATA PASURUAN (server-side DataTables)
     * =====================================================
     */
    public function dataLogistikPasuruan()
    {
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

        // TIDAK query $logistik di sini lagi — data diambil via AJAX
        return view('spvplanner.data_logistik_pasuruan', compact('planners', 'areas'));
    }

    public function dataLogistikPasuruanAjax(Request $request)
    {
        $draw   = (int) $request->input('draw', 1);
        $start  = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $searchValue = trim((string) $request->input('search.value', ''));

        $baseQuery = LogistikPengirimanPasuruan::query();
        $recordsTotal = (clone $baseQuery)->count();

        if ($request->filled('planner')) {
            $baseQuery->where('planner_pasuruan', $request->planner);
        }
        if ($request->filled('area')) {
            $baseQuery->where('area_pasuruan', $request->area);
        }
        if ($request->filled('date')) {
            $baseQuery->whereDate('tanggal_terima_po_pasuruan', $request->date);
        }
        if ($request->filled('month')) {
            $baseQuery->whereMonth('tanggal_terima_po_pasuruan', $request->month);
        }
        if ($request->filled('year')) {
            $baseQuery->whereYear('tanggal_terima_po_pasuruan', $request->year);
        }

      $this->applyGlobalSearch($baseQuery, $searchValue, 'logistik_pengiriman_pasuruan');

        $recordsFiltered = (clone $baseQuery)->count();

        $rows = $baseQuery
            ->orderByDesc('id')
            ->skip($start)
            ->take($length)
            ->get();

        // ================= HITUNG CR HANYA UNTUK SHIPMENT DI HALAMAN INI =================
        // Bukan hitung dari SEMUA data (itu penyebab lag-nya), cukup shipment
        // yang tampil di halaman/page ini saja -> 1 query agregat ringan.
        $shipmentNumbers = $rows->pluck('no_shipment_pasuruan')
            ->filter()
            ->unique()
            ->values();

        $crMap = [];
        if ($shipmentNumbers->isNotEmpty()) {
            $crMap = DB::table('logistik_pengiriman_pasuruan')
                ->select(
                    'no_shipment_pasuruan',
                    DB::raw('SUM(nilai_muatan_pasuruan) as total_muatan'),
                    DB::raw('MAX(biaya_kirim_pasuruan) as total_biaya')
                )
                ->whereIn('no_shipment_pasuruan', $shipmentNumbers)
                ->groupBy('no_shipment_pasuruan')
                ->get()
                ->keyBy('no_shipment_pasuruan')
                ->toArray();
        }

        $data = [];
        foreach ($rows as $r) {
            $data[] = $this->renderPasuruanRow($r, $crMap);
        }

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    public function deleteFiltered(Request $request)
{
    $baseQuery = LogistikPengiriman::query();

    // filter dari halaman data_planner
    if ($request->filled('planner_filter')) {
        $baseQuery->where('planner', $request->input('planner_filter'));
    }
    if ($request->filled('create_tgl_filter')) {
        $baseQuery->whereDate('create_tgl', $request->input('create_tgl_filter'));
    }

    // filter dari halaman full_data_logistik
    if ($request->filled('date')) {
        $baseQuery->whereDate('tanggal_naik_logistik', $request->date);
    }
    if ($request->filled('month')) {
        $baseQuery->whereMonth('tanggal_naik_logistik', $request->month);
    }
    if ($request->filled('year')) {
        $baseQuery->whereYear('tanggal_naik_logistik', $request->year);
    }
    if ($request->filled('pic_monitoring')) {
        $baseQuery->where('pic_monitoring', $request->pic_monitoring);
    }

    // filter umum (dipakai kedua halaman)
    if ($request->filled('area_filter')) {
        $baseQuery->where('area', $request->input('area_filter'));
    } elseif ($request->filled('area')) {
        $baseQuery->where('area', $request->area);
    }

    $this->applyGlobalSearch(
        $baseQuery,
        (string) $request->input('search_value', ''),
        'logistik_pengiriman'
    );

    // GUARD PENTING: kalau tidak ada filter & search sama sekali, TOLAK
    // supaya orang tidak sengaja hapus SEMUA data karena lupa isi filter.
    $hasCondition = $request->filled('planner_filter')
        || $request->filled('create_tgl_filter')
        || $request->filled('date')
        || $request->filled('month')
        || $request->filled('year')
        || $request->filled('pic_monitoring')
        || $request->filled('area_filter')
        || $request->filled('area')
        || trim((string) $request->input('search_value', '')) !== '';

    if (!$hasCondition) {
        return response()->json([
            'success' => false,
            'message' => 'Aktifkan minimal 1 filter atau kata kunci pencarian sebelum menghapus.',
        ], 422);
    }

    $count = (clone $baseQuery)->count();

    if ($count === 0) {
        return response()->json([
            'success' => false,
            'message' => 'Tidak ada data yang cocok untuk dihapus.',
        ]);
    }

    $baseQuery->delete();

    return response()->json([
        'success' => true,
        'deleted' => $count,
        'message' => "{$count} data berhasil dihapus.",
    ]);
}

/**
 * Sama seperti di atas tapi untuk tabel Pasuruan.
 */
public function deleteFilteredPasuruan(Request $request)
{
    $baseQuery = LogistikPengirimanPasuruan::query();

    if ($request->filled('planner')) {
        $baseQuery->where('planner_pasuruan', $request->planner);
    }
    if ($request->filled('area')) {
        $baseQuery->where('area_pasuruan', $request->area);
    }
    if ($request->filled('date')) {
        $baseQuery->whereDate('tanggal_terima_po_pasuruan', $request->date);
    }
    if ($request->filled('month')) {
        $baseQuery->whereMonth('tanggal_terima_po_pasuruan', $request->month);
    }
    if ($request->filled('year')) {
        $baseQuery->whereYear('tanggal_terima_po_pasuruan', $request->year);
    }

    $this->applyGlobalSearch(
        $baseQuery,
        (string) $request->input('search_value', ''),
        'logistik_pengiriman_pasuruan'
    );

    $hasCondition = $request->filled('planner')
        || $request->filled('area')
        || $request->filled('date')
        || $request->filled('month')
        || $request->filled('year')
        || trim((string) $request->input('search_value', '')) !== '';

    if (!$hasCondition) {
        return response()->json([
            'success' => false,
            'message' => 'Aktifkan minimal 1 filter atau kata kunci pencarian sebelum menghapus.',
        ], 422);
    }

    $count = (clone $baseQuery)->count();

    if ($count === 0) {
        return response()->json([
            'success' => false,
            'message' => 'Tidak ada data yang cocok untuk dihapus.',
        ]);
    }

    $baseQuery->delete();

    return response()->json([
        'success' => true,
        'deleted' => $count,
        'message' => "{$count} data berhasil dihapus.",
    ]);
}
    /**
     * Bangun 1 baris untuk halaman Data Pasuruan.
     * Urutan array HARUS sinkron dengan urutan <th> di
     * spvplanner/data_logistik_pasuruan.blade.php (52 kolom).
     */
    private function renderPasuruanRow($r, array $crMap)
    {
        $fmtDate = fn($v, $fmt = 'd-m-Y') => $v ? date($fmt, strtotime($v)) : '-';
        $fmtRupiah = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');

        // ================= UPDATE POSISI MOBIL =================
        $dpt           = $r->tanggal_dpt_unit_pasuruan;
        $tibaGudang    = $r->tanggal_tiba_gudang_pasuruan;
        $keluarGudang  = $r->tanggal_keluar_gudang_pasuruan;
        $tibaTujuan    = $r->tanggal_tiba_pasuruan;
        $bongkarTujuan = $r->tanggal_bongkar_pasuruan;

        if (empty($dpt)) {
            $status = 'MENCARI UNIT';
            $badge = 'red';
        } elseif (empty($tibaGudang)) {
            $status = 'PERJALANAN KE GUDANG';
            $badge = 'orange';
        } elseif (!empty($tibaGudang) && empty($keluarGudang)) {
            $status = 'DI GUDANG';
            $badge = 'blue';
        } elseif (!empty($keluarGudang) && empty($tibaTujuan)) {
            $status = 'PERJALANAN KE TUJUAN';
            $badge = 'yellow';
        } elseif (!empty($tibaTujuan) && empty($bongkarTujuan)) {
            $status = 'TIBA DI TUJUAN';
            $badge = 'success';
        } elseif (!empty($tibaTujuan) && !empty($bongkarTujuan)) {
            $status = 'SUDAH SELESAI';
            $badge = 'green';
        } else {
            $status = '-';
            $badge = 'gray';
        }
        $posisiHtml = '<span class="badge ' . $badge . '">' . e($status) . '</span>';

        // ================= DIST CHANNEL (badge warna hash) =================
        $channel = trim($r->dist_channel_pasuruan ?? '');
        $channelClasses = ['badge-green', 'badge-blue', 'badge-orange', 'badge-red', 'badge-purple', 'badge-pink', 'badge-cyan', 'badge-yellow'];
        $channelClass = $channel ? $channelClasses[abs(crc32($channel)) % count($channelClasses)] : 'badge-default';
        $channelHtml = '<span class="badge ' . $channelClass . '">' . e($channel ?: '-') . '</span>';

        // ================= KETERSEDIAAN UNIT =================
        if (!empty($r->tanggal_dpt_unit_pasuruan)) {
            $ketersediaanHtml = '<span class="badge-status status-sudah">Sudah Dapat Unit</span>';
        } else {
            $ketersediaanHtml = '<span class="badge-status status-belum">Belum Dapat Unit</span>';
        }

        // ================= CR (dari agregat per shipment) =================
        $shipment = trim($r->no_shipment_pasuruan ?? '');
        $nilaiMuatanRow = (float) $r->nilai_muatan_pasuruan;
        $biayaRow = (float) $r->biaya_kirim_pasuruan;

        $costRatio = 0;
        if ($shipment !== '' && isset($crMap[$shipment])) {
            $totalMuatan = (float) $crMap[$shipment]->total_muatan;
            $totalBiaya  = (float) $crMap[$shipment]->total_biaya;

            if ($totalMuatan > 0 && $nilaiMuatanRow > 0) {
                $totalCR    = ($totalBiaya / $totalMuatan) * 100;
                $kontribusi = $nilaiMuatanRow / $totalMuatan;
                $costRatio  = $kontribusi * $totalCR;
            }
            $crHtml = $costRatio > 0
                ? '<span class="cr-value">' . number_format($costRatio, 4, ',', '.') . '%</span>'
                : '<span class="text-muted">0,0000%</span>';
        } else {
            if ($nilaiMuatanRow > 0) {
                $costRatio = ($biayaRow / $nilaiMuatanRow) * 100;
            }
            $crHtml = $costRatio > 0
                ? '<span class="cr-value">' . number_format($costRatio, 4, ',', '.') . '%</span>'
                : '<span class="text-muted">-</span>';
        }

        // ================= KATEGORI EKSPEDISI =================
        $kategori = $r->kategori_ekspedisi_pasuruan ?? '-';
        if (empty($kategori) || $kategori == '-') {
            $kategoriHtml = '<span class="badge gray">-</span>';
        } elseif (strtolower($kategori) == 'kontrak') {
            $kategoriHtml = '<span class="badge yellow">Kontrak</span>';
        } elseif (strtolower($kategori) == 'oncall') {
            $kategoriHtml = '<span class="badge blue">Oncall</span>';
        } else {
            $kategoriHtml = '<span class="badge orange">' . e($kategori) . '</span>';
        }

        // ================= LAMA WAKTU PENCARIAN & SLA DAPAT MOBIL =================
        $lamaPencarian = '-';
        $slaMobilHtml = '<span class="badge gray">-</span>';

        if (!empty($r->rencana_kirim_pasuruan) && !empty($r->tanggal_dpt_unit_pasuruan)) {
            $rencana   = strtotime(date('Y-m-d', strtotime($r->rencana_kirim_pasuruan)));
            $dapatUnit = strtotime(date('Y-m-d', strtotime($r->tanggal_dpt_unit_pasuruan)));
            $selisih   = floor(($dapatUnit - $rencana) / 86400);

            $lamaPencarian = $selisih <= 0 ? 'H+0' : 'H+' . $selisih;
            $slaMobilHtml = $selisih <= 0
                ? '<span class="badge green">On Time</span>'
                : '<span class="badge red">Delay</span>';
        }

        // ================= ALERT =================
        $alert = '-';
        $alertClass = 'badge-secondary';
        if (!empty($r->tanggal_tiba_pasuruan)) {
            $alert = '✓ Tiba';
            $alertClass = 'badge-success';
        } elseif (!empty($r->estimasi_tiba_pasuruan)) {
            $estimasi = strtotime(date('Y-m-d', strtotime($r->estimasi_tiba_pasuruan)));
            $sisaHari = floor(($estimasi - strtotime(date('Y-m-d'))) / 86400);

            if ($sisaHari < 0) {
                $alert = 'OVERDUE';
                $alertClass = 'badge-danger';
            } elseif ($sisaHari == 0) {
                $alert = 'H-0';
                $alertClass = 'badge-danger';
            } elseif ($sisaHari == 1) {
                $alert = 'H-1';
                $alertClass = 'badge-danger';
            } elseif ($sisaHari == 2) {
                $alert = 'H-2';
                $alertClass = 'badge-warning';
            } elseif ($sisaHari == 3) {
                $alert = 'H-3';
                $alertClass = 'badge-warning';
            } elseif ($sisaHari <= 7) {
                $alert = 'H-' . $sisaHari;
                $alertClass = 'badge-info';
            } else {
                $alert = 'ON TRACK';
                $alertClass = 'badge-success';
            }
        }
        $alertHtml = '<span class="badge ' . $alertClass . '">' . e($alert) . '</span>';

        // ================= SELISIH QUANTITY =================
        $totalDo = is_numeric($r->total_do_pasuruan) ? (float) $r->total_do_pasuruan : 0;
        $actualRaw = $r->actual_delivery_quantity_pasuruan;
        $actualBelumDiisi = ($actualRaw === null || $actualRaw === '' || (float) $actualRaw == 0);

        if ($actualBelumDiisi) {
            $selisihHtml = '<span class="badge badge-secondary">-</span>';
        } else {
            $actualQty = (float) $actualRaw;
            $selisihQty = $totalDo - $actualQty;

            if ($selisihQty == 0) {
                $selisihHtml = '<span class="badge badge-success">Sesuai (0)</span>';
            } elseif ($selisihQty > 0) {
                $selisihHtml = '<span class="badge badge-danger">Berkurang ' . number_format($selisihQty, 0, ',', '.') . '</span>';
            } else {
                $selisihHtml = '<span class="badge badge-warning">Lebih ' . number_format(abs($selisihQty), 0, ',', '.') . '</span>';
            }
        }

        // ================= SLA TIBA (stored) =================
        $slaTibaVal = trim($r->sla_tiba_pasuruan ?? '');
        if ($slaTibaVal == '') {
            $slaTibaHtml = '<span class="badge gray">-</span>';
        } elseif (strtolower($slaTibaVal) == 'on time') {
            $slaTibaHtml = '<span class="badge green">' . e($slaTibaVal) . '</span>';
        } elseif (strtolower($slaTibaVal) == 'delay') {
            $slaTibaHtml = '<span class="badge red">' . e($slaTibaVal) . '</span>';
        } else {
            $slaTibaHtml = '<span class="badge gray">' . e($slaTibaVal) . '</span>';
        }

        // ================= STATUS BONGKAR =================
        if (!empty($r->tanggal_bongkar_pasuruan)) {
            $statusBongkarHtml = '<span class="badge status-bongkar green">Telah Bongkar</span>';
        } elseif (!empty($r->tanggal_tiba_pasuruan)) {
            $tglTiba = strtotime(date('Y-m-d', strtotime($r->tanggal_tiba_pasuruan)));
            $selisihHari = max(0, floor((strtotime(date('Y-m-d')) - $tglTiba) / 86400));
            $cls = $selisihHari == 0 ? 'orange' : 'red';
            $statusBongkarHtml = '<span class="badge status-bongkar ' . $cls . '">H+' . $selisihHari . '</span>';
        } else {
            $statusBongkarHtml = '<span class="badge status-bongkar gray">-</span>';
        }

        // ================= OVERSTAY =================
        $overstayHtml = '<span class="badge gray">-</span>';
        if (!empty($r->tanggal_tiba_pasuruan) && !empty($r->tanggal_bongkar_pasuruan)) {
            $tiba = strtotime(date('Y-m-d', strtotime($r->tanggal_tiba_pasuruan)));
            $bongkar = strtotime(date('Y-m-d', strtotime($r->tanggal_bongkar_pasuruan)));
            $overstay = max(0, floor(($bongkar - $tiba) / 86400));
            $overstayText = $overstay == 0 ? '0 Hari' : "H+{$overstay} Hari";
            $overstayHtml = '<span class="badge ' . ($overstay == 0 ? 'green' : 'red') . '">' . e($overstayText) . '</span>';
        }

        // ================= SLA BONGKAR (dihitung ulang dari tanggal) =================
        $slaBongkarComputedHtml = '<span class="badge gray">-</span>';
        if (!empty($r->tanggal_tiba_pasuruan) && !empty($r->tanggal_bongkar_pasuruan)) {
            $tiba = strtotime(date('Y-m-d', strtotime($r->tanggal_tiba_pasuruan)));
            $bongkar = strtotime(date('Y-m-d', strtotime($r->tanggal_bongkar_pasuruan)));
            $selisih = floor(($bongkar - $tiba) / 86400);
            $slaBongkarComputedHtml = $selisih <= 0
                ? '<span class="badge green">On Time</span>'
                : '<span class="badge red">Delay</span>';
        }

        // ================= STATUS AKHIR & STATUS ALERT (pakai kolom tersimpan) =================
        $slaTibaUp = strtoupper(trim($r->sla_tiba_pasuruan ?? ''));
        $slaBongkarUp = strtoupper(trim($r->sla_bongkar_pasuruan ?? ''));

        if (empty($r->tanggal_tiba_pasuruan)) {
            $statusAkhirHtml = '<span class="status-badge status-transit">🚚 Dalam Perjalanan</span>';
        } elseif (empty($r->tanggal_bongkar_pasuruan)) {
            $statusAkhirHtml = '<span class="status-badge status-unloading">📦 Sudah Tiba <br> Dalam Pembongkaran</span>';
        } elseif ($slaTibaUp == 'ON TIME' && $slaBongkarUp == 'ON TIME') {
            $statusAkhirHtml = '<span class="status-badge status-ontime">✅ Pengiriman On Time</span>';
        } else {
            $statusAkhirHtml = '<span class="status-badge status-delay">🚨 Pengiriman Delay</span>';
        }

        if ($slaTibaUp == 'ON TIME' && $slaBongkarUp == 'ON TIME') {
            $statusAlertHtml = '<span class="badge badge-success">🟢 Delivered Ontime</span>';
        } elseif ($slaTibaUp == 'DELAY' && $slaBongkarUp == 'ON TIME') {
            $statusAlertHtml = '<span class="badge badge-warning">🚚 Delay Perjalanan</span>';
        } elseif ($slaTibaUp == 'ON TIME' && $slaBongkarUp == 'DELAY') {
            $statusAlertHtml = '<span class="badge badge-info">📦 Delay Pembongkaran</span>';
        } elseif ($slaTibaUp == 'DELAY' && $slaBongkarUp == 'DELAY') {
            $statusAlertHtml = '<span class="badge badge-danger">🔥 Delivered Delay</span>';
        } else {
            $statusAlertHtml = '<span class="badge badge-secondary">⏳ Belum Selesai</span>';
        }

        return [
            $fmtDate($r->tanggal_terima_po_pasuruan),
            $fmtDate($r->rencana_kirim_pasuruan),
            $r->transport_lead_time_pasuruan,
            $r->planner_pasuruan,
            $r->no_shipment_pasuruan,
            $posisiHtml,
            $channelHtml,
            $r->tujuan_pasuruan,
            $r->area_pasuruan,
            $ketersediaanHtml,
            $r->mobil_pasuruan,
            $r->total_do_pasuruan,
            $fmtRupiah($r->nilai_muatan_pasuruan),
            $fmtRupiah($r->biaya_kirim_pasuruan),
            $crHtml,
            $kategoriHtml,
            $r->ekspedisi_pasuruan,
            $fmtDate($r->tanggal_dpt_unit_pasuruan),
            $lamaPencarian,
            $slaMobilHtml,
            $fmtDate($r->planning_loading_pasuruan),
            $fmtDate($r->tanggal_tiba_gudang_pasuruan),
            $fmtDate($r->tanggal_keluar_gudang_pasuruan),
            $r->pic_monitoring_pasuruan,
            $r->nama_kapal_pasuruan,
            $r->etd_pasuruan,
            $r->eta_pasuruan,
            $alertHtml,
            $r->act_urutan_bongkar_pasuruan,
            $r->actual_delivery_quantity_pasuruan,
            $selisihHtml,
            $r->reason_selisih_quantity_pasuruan,
            $fmtDate($r->act_pgi_date_pasuruan),
            $fmtDate($r->atd_pasuruan),
            $fmtDate($r->ata_pasuruan),
            $fmtDate($r->estimasi_tiba_pasuruan),
            $r->tanggal_tiba_pasuruan ? date('d-m-Y h:i A', strtotime($r->tanggal_tiba_pasuruan)) : '-',
            $r->lama_perjalanan_pasuruan ?? '-',
            $slaTibaHtml,
            $r->tanggal_bongkar_pasuruan ? date('d-m-Y h:i A', strtotime($r->tanggal_bongkar_pasuruan)) : '-',
            $statusBongkarHtml,
            $overstayHtml,
            $slaBongkarComputedHtml,
            $r->reason_waktu_tiba_pasuruan,
            $r->reason_waktu_bongkar_pasuruan,
            $statusAkhirHtml,
            $statusAlertHtml,
            $r->remarks_pasuruan,
            $r->route_pasuruan,
            $r->route_pasuruan ? explode('-', trim($r->route_pasuruan))[0] : '-',
            $r->pulau_pasuruan,
            $r->via_kirim_pasuruan,
        ];
    }

    /**
     * =====================================================
     * FULL DASHBOARD
     * =====================================================
     */
    public function Fulldashboard(Request $request)
    {
        $base = DB::table('logistik_pengiriman');

        $this->applyFilter($base, $request);

        $total_data = (clone $base)->count();

        $gudang_ontime = (clone $base)
            ->where(function ($q) {
                $q->whereNotNull('tanggal_tiba_gudang')
                    ->orWhereNotNull('tanggal_tiba_gudang_2')
                    ->orWhereNotNull('tanggal_tiba_gudang_3');
            })
            ->count();

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

            $top_ekspedisi_pemakaian = (clone $base)
    ->select(
        'ekpedisi',
        DB::raw('COUNT(*) as total')
    )
    ->whereNotNull('ekpedisi')
    ->where('ekpedisi', '!=', '')
    ->groupBy('ekpedisi')
    ->orderByDesc('total')
    ->limit(5)
    ->get();

    $trend_pengiriman_bulanan = (clone $base)
    ->select(
        DB::raw("DATE_FORMAT(tanggal_naik_logistik, '%Y-%m') as bulan"),
        DB::raw('COUNT(*) as total')
    )
    ->whereNotNull('tanggal_naik_logistik')
    ->groupBy('bulan')
    ->orderBy('bulan')
    ->get();


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

        $totalNilaiMuatan = (clone $base)->sum('nilai_muatan');

        $totalBiayaKirim = (clone $base)
            ->selectRaw("SUM(biaya_kirim) as total")
            ->value('total');

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
        $summary_area_ontime = (clone $base)
            ->select(
                'area',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN tanggal_tiba IS NOT NULL AND estimasi_tiba IS NOT NULL AND DATEDIFF(DATE(tanggal_tiba), DATE(estimasi_tiba)) <= 0 THEN 1 ELSE 0 END) as total_ontime"),
                DB::raw("SUM(CASE WHEN tanggal_tiba IS NOT NULL AND estimasi_tiba IS NOT NULL AND DATEDIFF(DATE(tanggal_tiba), DATE(estimasi_tiba)) > 0 THEN 1 ELSE 0 END) as total_delay")
            )
            ->whereNotNull('area')
            ->where('area', '!=', '')
            ->groupBy('area')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

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

        // ================= SUMMARY EKSPEDISI (jumlah dipakai + total biaya_kirim) =================
        // ================= SUMMARY EKSPEDISI (Top 10 jumlah dipakai + total biaya_kirim) =================
        // $ekspedisi = (clone $base)
        //     ->select(
        //         'ekpedisi',
        //         DB::raw('COUNT(*) as total'),
        //         DB::raw('COALESCE(SUM(biaya_kirim),0) as total_biaya')
        //     )
        //     ->whereNotNull('ekpedisi')
        //     ->where('ekpedisi', '!=', '')
        //     ->groupBy('ekpedisi')
        //     ->orderByDesc('total')
        //     ->limit(10)
        //     ->get();

        // $label = $ekspedisi->pluck('ekpedisi');
        // $value = $ekspedisi->pluck('total');

        $ekspedisi = (clone $base)
            ->select(
                'ekpedisi',
                DB::raw('COUNT(*) as total'),
                DB::raw('COALESCE(SUM(biaya_kirim), 0) as total_biaya')
            )
            ->whereNotNull('ekpedisi')
            ->where('ekpedisi', '!=', '')
            ->groupBy('ekpedisi')
            ->orderByDesc('total_biaya') // berdasarkan biaya kirim terbesar
            ->limit(10)
            ->get();

        $label = $ekspedisi->pluck('ekpedisi');
        $value = $ekspedisi->pluck('total_biaya');
        // ================= SUMMARY PLANNER (jumlah shipment per planner) =================
        // ================= SUMMARY PLANNER (jumlah shipment + ontime/delay armada) =================
        // On time = tanggal_dpt_unit <= rencana_kirim (sama logic kayak $planner_ontime di atas)
        $greatestTiba = "GREATEST(
    COALESCE(tanggal_tiba_gudang, '1900-01-01'),
    COALESCE(tanggal_tiba_gudang_2, '1900-01-01'),
    COALESCE(tanggal_tiba_gudang_3, '1900-01-01')
)";

        $lastTiba = "
    CASE
        WHEN tanggal_tiba_gudang_3 IS NOT NULL AND tanggal_tiba_gudang_3 = {$greatestTiba} THEN tanggal_tiba_gudang_3
        WHEN tanggal_tiba_gudang_2 IS NOT NULL AND tanggal_tiba_gudang_2 = {$greatestTiba} THEN tanggal_tiba_gudang_2
        WHEN tanggal_tiba_gudang IS NOT NULL THEN tanggal_tiba_gudang
        ELSE NULL
    END
";

        $lastPlanning = "
    CASE
        WHEN tanggal_tiba_gudang_3 IS NOT NULL AND tanggal_tiba_gudang_3 = {$greatestTiba} THEN planning_loading_3
        WHEN tanggal_tiba_gudang_2 IS NOT NULL AND tanggal_tiba_gudang_2 = {$greatestTiba} THEN planning_loading_2
        WHEN tanggal_tiba_gudang IS NOT NULL THEN planning_loading
        ELSE NULL
    END
";

        $summary_planner = (clone $base)
            ->select(
                'planner',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN ({$lastPlanning}) IS NOT NULL AND ({$lastTiba}) IS NOT NULL AND DATE({$lastTiba}) <= DATE({$lastPlanning}) THEN 1 ELSE 0 END) as total_ontime"),
                DB::raw("SUM(CASE WHEN ({$lastPlanning}) IS NOT NULL AND ({$lastTiba}) IS NOT NULL AND DATE({$lastTiba}) > DATE({$lastPlanning}) THEN 1 ELSE 0 END) as total_delay")
            )
            ->whereNotNull('planner')
            ->where('planner', '!=', '')
            ->groupBy('planner')
            ->orderByDesc('total')
            ->get();
        // ================= SUMMARY PIC MONITORING (jumlah shipment + ontime/delay kedatangan) =================
        // On time = tanggal_tiba <= estimasi_tiba (sama logic kayak $customer_ontime di atas)
        $summary_pic_monitoring = (clone $base)
            ->select(
                'pic_monitoring',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN tanggal_tiba IS NOT NULL AND estimasi_tiba IS NOT NULL AND DATEDIFF(DATE(tanggal_tiba), DATE(estimasi_tiba)) <= 0 THEN 1 ELSE 0 END) as total_ontime"),
                DB::raw("SUM(CASE WHEN tanggal_tiba IS NOT NULL AND estimasi_tiba IS NOT NULL AND DATEDIFF(DATE(tanggal_tiba), DATE(estimasi_tiba)) > 0 THEN 1 ELSE 0 END) as total_delay")
            )
            ->whereNotNull('pic_monitoring')
            ->where('pic_monitoring', '!=', '')
            ->groupBy('pic_monitoring')
            ->orderByDesc('total')
            ->get();

            $summary_kategori_ekspedisi = (clone $base)
    ->select(
        'kategori_ekspedisi',
        DB::raw('COUNT(*) as total')
    )
    ->whereNotNull('kategori_ekspedisi')
    ->where('kategori_ekspedisi', '!=', '')
    ->groupBy('kategori_ekspedisi')
    ->get();

        $label = $ekspedisi->pluck('ekpedisi');
        $value = $ekspedisi->pluck('total');

        $label = $ekspedisi->pluck('kategori_ekspedisi');
        $value = $ekspedisi->pluck('total');

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

        $list_area = $this->getArea();

        return view('spvplanner.dashboard_full', compact(
            'total_data',
            'gudang_ontime',
            'gudang_delay',
            'customer_ontime',
            'customer_delay',
            'bongkar_ontime',
            'summary_kategori_ekspedisi',
            'bongkar_delay',
            'summary_area',
            'summary_tujuan',
            'totalNilaiMuatan',
            'totalBiayaKirim',
            'trend_pengiriman_bulanan',
            'ekspedisi',
            'top_ekspedisi_pemakaian',
            'summary_area_ontime',
            'summary_planner',
            'summary_pic_monitoring',
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
            'summary_pulau',
            'label_pulau',
            'value_muatan_pulau',
            'value_biaya_pulau'
        ));
    }

    private function applyGlobalSearch($query, string $searchValue, string $table)
{
    $searchValue = trim($searchValue);
    if ($searchValue === '') {
        return $query;
    }

    $columns = DB::getSchemaBuilder()->getColumnListing($table);

    return $query->where(function ($q) use ($columns, $searchValue) {
        foreach ($columns as $col) {
            $q->orWhere($col, 'like', "%{$searchValue}%");
        }
    });
}
    /**
     * =====================================================
     * STORE (Add New Shipment)
     * =====================================================
     */
    public function store(Request $request)
    {
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

    /**
     * =====================================================
     * DASHBOARD (ringkas)
     * =====================================================
     */
    public function dashboard()
    {
        $shipments = DB::table('logistik_pengiriman')
            ->orderBy('no_shipment')
            ->get()
            ->groupBy('no_shipment')
            ->map(function ($group) {
                return $group->first();
            });

        $total_data = $shipments->count();

        $armada = $shipments->filter(function ($row) {
            return !empty($row->rencana_kirim)
                && !empty($row->tanggal_dpt_unit);
        })->count();

        $belum_armada = $shipments->filter(function ($row) {
            return empty($row->rencana_kirim)
                || empty($row->tanggal_dpt_unit);
        })->count();

        $ontime = $shipments->filter(function ($row) {
            return !empty($row->tanggal_tiba_gudang)
                || !empty($row->tanggal_tiba_gudang_2)
                || !empty($row->tanggal_tiba_gudang_3);
        })->count();

        $delay = $shipments->filter(function ($row) {
            return !empty($row->tanggal_dpt_unit)
                && empty($row->tanggal_tiba_gudang)
                && empty($row->tanggal_tiba_gudang_2)
                && empty($row->tanggal_tiba_gudang_3);
        })->count();

        $summary_area = $shipments
            ->groupBy('area')
            ->map(function ($group) {
                return count($group);
            })
            ->sortDesc()
            ->take(10);

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

            if ($data->isEmpty()) {
                return;
            }

            // insert per 500 baris supaya jumlah placeholder (kolom x baris)
            // tidak melebihi batas MySQL (1390: too many placeholders)
            $data->map(fn($row) => (array) $row)
                ->chunk(500)
                ->each(function ($chunk) {
                    DB::table('logistik_pengiriman_pasuruan_storage')
                        ->insert($chunk->toArray());
                });

            DB::table('logistik_pengiriman_pasuruan')->delete();
        });

        return redirect()
            ->route('spvplanner.data.pasuruan')
            ->with('success', 'Semua data berhasil dipindahkan ke Storage.');
    }
    /**
     * =====================================================
     * UPDATE (form penuh dari halaman planner)
     * =====================================================
     */
    public function update(Request $request, $id)
    {
        $old = DB::table('logistik_pengiriman')
            ->where('id', $id)
            ->first();

        if (!$old) {
            return back()->with('error', 'Data tidak ditemukan');
        }

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
            $request->merge(['estimasi_tiba' => null]);
        }

        $rumus = $this->hitungSla($request);

        $oldNoShipment = $old->no_shipment;
        $newNoShipment = $request->no_shipment;
        $shipment      = $newNoShipment ?: $oldNoShipment;

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

    private function cleanCr($value)
    {
        if (!$value) return null;
        $value = preg_replace('/[^0-9.]/', '', $value);
        return is_numeric($value) ? (float) $value : null;
    }

    private function cleanMoney($value)
    {
        if (!$value) return null;
        return (int) preg_replace('/[^0-9]/', '', $value);
    }

    /**
     * =====================================================
     * HALAMAN DATA PLANNER (SPV)
     * FIX PERFORMA: TIDAK load semua row ke Blade lagi.
     * View mengambil data lewat DataTables server-side (dataAjax()).
     * =====================================================
     */
    public function dataLogistik()
    {
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

    /**
     * =====================================================
     * ENDPOINT SERVER-SIDE UNTUK DATATABLES (SPV)
     * =====================================================
     */
    public function dataAjax(Request $request)
    {
        $draw   = (int) $request->input('draw', 1);
        $start  = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $searchValue = trim((string) $request->input('search.value', ''));

        $baseQuery = LogistikPengiriman::query();
        $totalRecords = (clone $baseQuery)->count();

        // ===== FILTER: planner / area / tanggal import =====
        if ($request->filled('planner_filter')) {
            $baseQuery->where('planner', $request->input('planner_filter'));
        }
        if ($request->filled('area_filter')) {
            $baseQuery->where('area', $request->input('area_filter'));
        }
        if ($request->filled('create_tgl_filter')) {
            $baseQuery->whereDate('create_tgl', $request->input('create_tgl_filter'));
        }

        // ===== GLOBAL SEARCH (kolom-kolom penting saja) =====
      $this->applyGlobalSearch($baseQuery, $searchValue, 'logistik_pengiriman');

        $recordsFiltered = (clone $baseQuery)->count();

        $rows = $baseQuery
            ->orderByRaw('CAST(no_shipment AS UNSIGNED) ASC')
            ->skip($start)
            ->take($length)
            ->get();

        $tujuanList = DB::table('tujuanfillterr')->whereNotNull('tujuan')->where('tujuan', '!=', '')->distinct()->orderBy('tujuan')->pluck('tujuan');
        $pulauList = DB::table('tujuanfillterr')->whereNotNull('pulau')->where('pulau', '!=', '')->distinct()->orderBy('pulau')->pluck('pulau');
        $areas = DB::table('tujuanfillterr')->whereNotNull('area')->where('area', '!=', '')->distinct()->orderBy('area')->pluck('area');
        $distChannelList = DB::table('tujuanfillterr')->whereNotNull('dist_channel')->where('dist_channel', '!=', '')->distinct()->orderBy('dist_channel')->pluck('dist_channel');
        $ekpedisiList = DB::table('tarif_pengiriman')->whereNotNull('ekpedisi')->where('ekpedisi', '!=', '')->distinct()->orderBy('ekpedisi')->pluck('ekpedisi');
        $mobilList = DB::table('tarif_pengiriman')->whereNotNull('mobil')->where('mobil', '!=', '')->distinct()->orderBy('mobil')->pluck('mobil');
        $routeList = DB::table('tarif_pengiriman')->whereNotNull('route')->where('route', '!=', '')->distinct()->orderBy('route')->pluck('route');

        $lists = compact('tujuanList', 'pulauList', 'areas', 'distChannelList', 'ekpedisiList', 'mobilList', 'routeList');

        $data = [];
        foreach ($rows as $r) {
            $data[] = $this->renderRowColumns($r, $lists);
        }

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    /**
     * Bangun 1 baris (array kolom, index harus sinkron dengan
     * thead di spvplanner/data_planner.blade.php).
     */
    private function renderRowColumns($r, array $lists)
    {
        $id = $r->id;
        $formAttr = 'form="form-update-' . $id . '"';

        $dateInput = function ($name, $value) use ($formAttr) {
            $val = $value ? date('Y-m-d', strtotime($value)) : '';
            return '<input type="date" ' . $formAttr . ' name="' . $name . '" value="' . e($val) . '">';
        };

        $textInput = function ($name, $value, $extraClass = '') use ($formAttr) {
            return '<input type="text" ' . $formAttr . ' name="' . $name . '" class="' . $extraClass . '" value="' . e($value) . '">';
        };

        $buildSelect = function ($name, $selected, $options, $extraClass = '', $required = false) use ($formAttr) {
            $html = '<select ' . $formAttr . ' name="' . $name . '" class="' . $extraClass . ' select2-row">';
            $html .= '<option value="">-- Pilih --</option>';
            $found = false;
            foreach ($options as $opt) {
                $isSelected = ((string) $selected === (string) $opt);
                if ($isSelected) $found = true;
                $html .= '<option value="' . e($opt) . '"' . ($isSelected ? ' selected' : '') . '>' . e($opt) . '</option>';
            }
            if ($selected && !$found) {
                $html .= '<option value="' . e($selected) . '" selected>' . e($selected) . ' (lama)</option>';
            }
            $html .= '</select>';
            return $html;
        };

        $durasiStatus = function ($planning, $tiba) {
            $durasiText = '-';
            if (!empty($planning) && !empty($tiba)) {
                $start = \Carbon\Carbon::parse($planning);
                $end   = \Carbon\Carbon::parse($tiba);
                $totalMenit  = $start->diffInMinutes($end);
                $desimalHari = $totalMenit / 1440;
                $hari = floor($desimalHari);
                $jam  = round(($desimalHari - $hari) * 24);
                if ($jam == 24) {
                    $jam = 0;
                    $hari += 1;
                }
                if ($hari > 0 && $jam > 0) $durasiText = "{$hari} Hari {$jam} Jam";
                elseif ($hari > 0) $durasiText = "{$hari} Hari";
                elseif ($jam > 0) $durasiText = "{$jam} Jam";
                else $durasiText = "0 Jam";
            }
            return $durasiText;
        };

        $statusBadge = function ($planning, $tiba) {
            if (empty($planning) || empty($tiba)) {
                return '<span class="badge gray">-</span>';
            }
            $startDay = \Carbon\Carbon::parse($planning)->startOfDay();
            $endDay   = \Carbon\Carbon::parse($tiba)->startOfDay();
            return $endDay->gt($startDay)
                ? '<span class="badge red">Delay</span>'
                : '<span class="badge green">On Time</span>';
        };

        $slaBadge = function ($planning, $tiba) {
            if (empty($planning) || empty($tiba)) {
                return '<span class="badge bg-secondary">-</span>';
            }
            $start = \Carbon\Carbon::parse($planning)->startOfDay();
            $end   = \Carbon\Carbon::parse($tiba)->startOfDay();
            if ($end->gt($start)) {
                $selisih = $start->diffInDays($end);
                return '<span class="badge red">H+' . $selisih . '</span>';
            }
            return '<span class="badge bg-success">Sesuai SLA</span>';
        };

        if (!empty($r->tanggal_dpt_unit)) {
            $statusMobilHtml = '<span class="badge-status bg-success text-white">SUDAH DAPAT</span>';
        } else {
            $statusMobilHtml = '<span class="badge-status bg-danger text-white">BELUM DAPAT</span>';
        }

        if ($r->rencana_kirim && $r->tanggal_dpt_unit) {
            $area = strtoupper(trim($r->area ?? ''));
            $rencana = strtotime(date('Y-m-d', strtotime($r->rencana_kirim)));
            $dptUnit = strtotime(date('Y-m-d', strtotime($r->tanggal_dpt_unit)));
            $selisihHari = floor(($dptUnit - $rencana) / 86400);

            if (in_array($area, ['JABODEBEK', 'JABODETABEK', 'BANTEN'])) {
                $batasHari = 0;
            } elseif (in_array($area, ['JAWA_BARAT', 'JAWA BARAT'])) {
                $batasHari = 1;
            } else {
                $batasHari = 2;
            }

            $text = $selisihHari > $batasHari ? 'H+' . ($selisihHari - $batasHari) : 'Sesuai SLA';
            $slaMobilHtml = '<span class="badge-status ' . (str_contains($text, 'H+') ? 'bg-danger text-white' : 'bg-success text-white') . '">' . $text . '</span>';
        } else {
            $slaMobilHtml = '<span class="badge-status bg-secondary text-white">-</span>';
        }

        $requiredFields = [
            'mobil'       => 'Mobil',
            'ekpedisi'    => 'Ekspedisi',
            'route'       => 'Route',
            'nama_driver' => 'Nama Driver',
            'no_pol'      => 'No Pol',
        ];
        $missing = [];
        foreach ($requiredFields as $col => $label) {
            if (trim((string) ($r->$col ?? '')) === '') {
                $missing[] = $label;
            }
        }
        if (count($missing) === 0) {
            $kelengkapanHtml = '<span class="badge completeness-badge green" title="Data lengkap">✅ Lengkap</span>';
        } else {
            $emptyCount = count($missing);
            $cls = $emptyCount === 1 ? 'yellow' : ($emptyCount <= 3 ? 'orange' : 'red');
            $text = '❌ ' . implode(', ', $missing);
            $kelengkapanHtml = '<span class="badge completeness-badge ' . $cls . '" title="' . e($text) . '">' . e($text) . '</span>';
        }

        $formattedRupiah = function ($angka) {
            if (!$angka) return '';
            $stringMurni = explode('.', (string) $angka)[0];
            $angkaMurni = preg_replace('/[^0-9]/', '', $stringMurni);
            return $angkaMurni ? 'Rp ' . number_format((float) $angkaMurni, 0, ',', '.') : '';
        };

        $tanggalImportText = $r->create_tgl ? \Carbon\Carbon::parse($r->create_tgl)->format('d/m/Y H:i') : '-';

        $hiddenForm = '<form class="d-none" id="form-update-' . $id . '" action="' . route('spvplanner.update', $id) . '" method="POST">'
            . csrf_field() . method_field('PUT') . '</form>';

        return [
            // 0
            $hiddenForm . $tanggalImportText,
            // 1
            $textInput('planner', $r->planner),
            // 2
            $textInput('no_shipment', $r->no_shipment, 'row-no-shipment'),
            // 3-14 tanggal
            $dateInput('tanggal_naik_logistik', $r->tanggal_naik_logistik),
            $dateInput('rencana_kirim', $r->rencana_kirim),
            $dateInput('tanggal_dpt_unit', $r->tanggal_dpt_unit),
            $dateInput('planning_loading', $r->planning_loading),
            $dateInput('tanggal_tiba_gudang', $r->tanggal_tiba_gudang),
            $dateInput('tanggal_keluar_gudang', $r->tanggal_keluar_gudang),
            $dateInput('planning_loading_2', $r->planning_loading_2),
            $dateInput('tanggal_tiba_gudang_2', $r->tanggal_tiba_gudang_2),
            $dateInput('tanggal_keluar_gudang_2', $r->tanggal_keluar_gudang_2),
            $dateInput('planning_loading_3', $r->planning_loading_3),
            $dateInput('tanggal_tiba_gudang_3', $r->tanggal_tiba_gudang_3),
            $dateInput('tanggal_keluar_gudang_3', $r->tanggal_keluar_gudang_3),
            // 15 tujuan
            $buildSelect('tujuan', $r->tujuan, $lists['tujuanList'], 'row-tujuan'),
            // 16 route (required)
            $buildSelect('route', $r->route, $lists['routeList'], 'row-route'),
            // 17 pulau
            $buildSelect('pulau', $r->pulau, $lists['pulauList'], 'row-pulau'),
            // 18 area
            $buildSelect('area', $r->area, $lists['areas'], 'row-area'),
            // 19 via kirim
            $textInput('via_kirim', $r->via_kirim),
            // 20 dist channel
            $buildSelect('dist_channel', $r->dist_channel, $lists['distChannelList'], 'row-dist-channel'),
            // 21 kategori ekspedisi
            $textInput('kategori_ekspedisi', $r->kategori_ekspedisi),
            // 22 ekpedisi (required)
            $buildSelect('ekpedisi', $r->ekpedisi, $lists['ekpedisiList'], 'row-ekpedisi'),
            // 23 lead time
            $textInput('transport_lead_time', $r->transport_lead_time),
            // 24 nama driver (required)
            $textInput('nama_driver', $r->nama_driver),
            // 25 no pol (required)
            $textInput('no_pol', $r->no_pol),
            // 26 mobil (required)
            $buildSelect('mobil', $r->mobil, $lists['mobilList'], 'row-mobil'),
            // 27 total qty
            '<input type="number" ' . $formAttr . ' name="total_do_qty_car" value="' . e($r->total_do_qty_car) . '">',
            // 28 nilai muatan
            $textInput('nilai_muatan', $formattedRupiah($r->nilai_muatan), 'row-nilai-muatan input-rupiah'),
            // 29 biaya kirim
            $textInput('biaya_kirim', $formattedRupiah($r->biaya_kirim), 'row-biaya-kirim input-rupiah'),
            // 30 cr
            '<input type="text" ' . $formAttr . ' name="cr" class="row-cr" readonly style="background:#f1f5f9;color:#0284c7;font-weight:600;" value="' . e(is_numeric($r->cr) ? number_format((float) $r->cr, 4) : $r->cr) . '">',
            // 31 status mobil
            $statusMobilHtml,
            // 32 lama waktu pencarian
            '<span class="text-primary fw-medium">' . e($r->lama_waktu_pencarian) . '</span>',
            // 33 sla dapat mobil
            $slaMobilHtml,
            // 34-36 KACS
            $durasiStatus($r->planning_loading, $r->tanggal_tiba_gudang),
            $statusBadge($r->planning_loading, $r->tanggal_tiba_gudang),
            $slaBadge($r->planning_loading, $r->tanggal_tiba_gudang),
            // 37-39 Sentul
            $durasiStatus($r->planning_loading_2, $r->tanggal_tiba_gudang_2),
            $statusBadge($r->planning_loading_2, $r->tanggal_tiba_gudang_2),
            $slaBadge($r->planning_loading_2, $r->tanggal_tiba_gudang_2),
            // 40-42 CCIE
            $durasiStatus($r->planning_loading_3, $r->tanggal_tiba_gudang_3),
            $statusBadge($r->planning_loading_3, $r->tanggal_tiba_gudang_3),
            $slaBadge($r->planning_loading_3, $r->tanggal_tiba_gudang_3),
            // 43 shipping point
            $r->route ? explode('-', trim($r->route))[0] : '-',
            // 44 kelengkapan data
            $kelengkapanHtml,
            // 45 hapus
            '<div class="btn-action"><a href="' . route('spvplanner.delete', $id) . '" class="btn btn-danger btn-sm px-2 d-flex align-items-center gap-1" onclick="return confirm(\'Hapus data ini?\')"><i class="fa-solid fa-trash"></i> Del</a></div>',
        ];
    }

    /**
     * =====================================================
     * ALERT CONTROL (ringkasan field kosong) — sekarang ikut
     * filter planner / area yang aktif di halaman
     * =====================================================
     */
    public function alerts(Request $request)
    {
        $fieldsMap = [
            'mobil'       => 'Mobil',
            'ekpedisi'    => 'Ekspedisi',
            'route'       => 'Route',
            'nama_driver' => 'Nama Driver',
            'no_pol'      => 'No Pol',
        ];

        $query = DB::table('logistik_pengiriman')
            ->select('id', 'no_shipment', 'mobil', 'ekpedisi', 'route', 'nama_driver', 'no_pol');

        if ($request->filled('planner_filter')) {
            $query->where('planner', $request->input('planner_filter'));
        }
        if ($request->filled('area_filter')) {
            $query->where('area', $request->input('area_filter'));
        }

        $rows = $query->where(function ($q) {
            $q->whereNull('mobil')->orWhere('mobil', '')
                ->orWhereNull('ekpedisi')->orWhere('ekpedisi', '')
                ->orWhereNull('route')->orWhere('route', '')
                ->orWhereNull('nama_driver')->orWhere('nama_driver', '')
                ->orWhereNull('no_pol')->orWhere('no_pol', '');
        })
            ->get();

        $alertList = [];
        $missingSummary = [];

        foreach ($rows as $r) {
            $missing = [];
            foreach ($fieldsMap as $col => $label) {
                if (trim((string) $r->$col) === '') {
                    $missing[] = $label;
                    $missingSummary[$label] = ($missingSummary[$label] ?? 0) + 1;
                }
            }
            $alertList[] = [
                'id'         => $r->id,
                'shipment'   => $r->no_shipment ?: '(tanpa no shipment)',
                'missing'    => $missing,
                'emptyCount' => count($missing),
            ];
        }

        usort($alertList, fn($a, $b) => $b['emptyCount'] <=> $a['emptyCount']);

        return response()->json([
            'alerts'         => $alertList,
            'missingSummary' => $missingSummary,
        ]);
    }

    /**
     * =====================================================
     * FULL DATA LOGISTIK (versi lama, client-side, dipakai
     * di halaman lain — dibiarkan seperti aslinya)
     * =====================================================
     */
    // public function fullDataLogistik(Request $request)
    // {
    //     $query = LogistikPengiriman::query();

    //     if ($request->date) {
    //         $query->whereDate('tanggal_naik_logistik', $request->date);
    //     }

    //     if ($request->month) {
    //         $query->whereMonth('tanggal_naik_logistik', $request->month);
    //     }

    //     if ($request->year) {
    //         $query->whereYear('tanggal_naik_logistik', $request->year);
    //     }

    //     if ($request->pic_monitoring) {
    //         $query->where('pic_monitoring', $request->pic_monitoring);
    //     }

    //     if ($request->area) {
    //         $query->where('area', $request->area);
    //     }

    //     if ($request->search) {
    //         $search = $request->search;

    //         $query->where(function ($q) use ($search) {
    //             $q->where('no_shipment', 'like', "%$search%")
    //               ->orWhere('tujuan', 'like', "%$search%")
    //               ->orWhere('ekspedisi', 'like', "%$search%")
    //               ->orWhere('area', 'like', "%$search%");
    //         });
    //     }

    //     $logistik = $query
    //         ->orderBy('id', 'DESC')
    //         ->get();

    //     $picList = LogistikPengiriman::whereNotNull('pic_monitoring')
    //         ->distinct()
    //         ->pluck('pic_monitoring');

    //     $areaList = LogistikPengiriman::whereNotNull('area')
    //         ->distinct()
    //         ->pluck('area');

    //     $grouped = $logistik->groupBy('no_shipment');

    //     foreach ($grouped as $shipment => $items) {

    //         $keluar = $items->flatMap(function ($r) {
    //             return [
    //                 $r->tanggal_keluar_gudang,
    //                 $r->tanggal_keluar_gudang_2,
    //                 $r->tanggal_keluar_gudang_3,
    //             ];
    //         })
    //         ->filter(function ($t) {
    //             return !empty($t) && $t != 'mm/dd/yyyy';
    //         })
    //         ->map(fn($t) => strtotime($t))
    //         ->max();

    //         $leadtime = (int) ($items->first()->transport_lead_time ?? 0);

    //         $baseEstimasi = $keluar
    //             ? strtotime("+{$leadtime} days", $keluar)
    //             : null;

    //         $jumlahSudahTiba = $items->whereNotNull('tanggal_tiba')->count();

    //         foreach ($items as $r) {

    //             if (!$baseEstimasi) {
    //                 $r->tanggal_estimasi = null;
    //                 continue;
    //             }

    //             if ($r->tanggal_tiba) {
    //                 $r->tanggal_estimasi = $r->estimasi_tiba
    //                     ? strtotime($r->estimasi_tiba)
    //                     : $baseEstimasi;
    //             } else {
    //                 $r->tanggal_estimasi = strtotime(
    //                     "+{$jumlahSudahTiba} days",
    //                     $baseEstimasi
    //                 );
    //             }
    //         }
    //     }

    //     return view('spvplanner.full_data_logistik', compact(
    //         'logistik',
    //         'picList',
    //         'areaList'
    //     ));
    // }

    public function fullDataLogistik(Request $request)
    {
        $picList = LogistikPengiriman::whereNotNull('pic_monitoring')
            ->where('pic_monitoring', '!=', '')
            ->distinct()
            ->orderBy('pic_monitoring')
            ->pluck('pic_monitoring');

        $areaList = LogistikPengiriman::whereNotNull('area')
            ->where('area', '!=', '')
            ->distinct()
            ->orderBy('area')
            ->pluck('area');

        // TIDAK query data di sini lagi — data diambil via AJAX (dataAjaxFull)
        return view('spvplanner.full_data_logistik', compact('picList', 'areaList'));
    }

    public function fullDataLogistikAjax(Request $request)
    {
        $draw   = (int) $request->input('draw', 1);
        $start  = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $searchValue = trim((string) $request->input('search.value', ''));

        $baseQuery = LogistikPengiriman::query();
        $recordsTotal = (clone $baseQuery)->count();

        if ($request->filled('date')) {
            $baseQuery->whereDate('tanggal_naik_logistik', $request->date);
        }
        if ($request->filled('month')) {
            $baseQuery->whereMonth('tanggal_naik_logistik', $request->month);
        }
        if ($request->filled('year')) {
            $baseQuery->whereYear('tanggal_naik_logistik', $request->year);
        }
        if ($request->filled('pic_monitoring')) {
            $baseQuery->where('pic_monitoring', $request->pic_monitoring);
        }
        if ($request->filled('area')) {
            $baseQuery->where('area', $request->area);
        }

$this->applyGlobalSearch($baseQuery, $searchValue, 'logistik_pengiriman');

        $recordsFiltered = (clone $baseQuery)->count();

        $rows = $baseQuery
            ->orderByDesc('id')
            ->skip($start)
            ->take($length)
            ->get();

        $data = [];
        foreach ($rows as $r) {
            $data[] = $this->renderFullDataRow($r);
        }

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    /**
     * Bangun 1 baris untuk full_data_logistik.
     * Urutan array WAJIB sinkron dengan urutan <th> di
     * spvplanner/full_data_logistik.blade.php (74 kolom).
     */
    private function renderFullDataRow($r)
    {
        $badgeSla = function ($sla) {
            $sla = trim((string) $sla);
            if ($sla === '' || $sla === '-' || $sla === 'null') {
                return '<span class="badge badge-gray">-</span>';
            }
            $lower = strtolower($sla);
            if (in_array($lower, ['sesuai sla', 'on time', 'ontime', 'h+0'])) {
                return '<span class="badge badge-green">' . e($sla) . '</span>';
            }
            if (preg_match('/^h\+1$/i', $sla)) {
                return '<span class="badge badge-orange">' . e($sla) . '</span>';
            }
            if (preg_match('/^h\+\d+$/i', $sla)) {
                return '<span class="badge badge-red">' . e($sla) . '</span>';
            }
            return '<span class="badge badge-gray">' . e($sla) . '</span>';
        };

        $fmtDate = fn($v, $fmt = 'd-m-Y') => $v ? date($fmt, strtotime($v)) : '-';
        $fmtRupiah = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
        $fmtRupiahOrBlank = fn($v) => $v ? 'Rp ' . number_format($v, 0, ',', '.') : '';

        // ================= KELUAR GUDANG TERAKHIR =================
        $keluar = collect([
            $r->tanggal_keluar_gudang,
            $r->tanggal_keluar_gudang_2,
            $r->tanggal_keluar_gudang_3,
        ])->filter(fn($t) => !empty($t) && $t != 'mm/dd/yyyy')
            ->map(fn($t) => strtotime($t))
            ->max();

        $tiba = (!empty($r->tanggal_tiba) && $r->tanggal_tiba != 'mm/dd/yyyy')
            ? strtotime($r->tanggal_tiba)
            : null;

        $bongkar = (!empty($r->tanggal_bongkar) && $r->tanggal_bongkar != 'mm/dd/yyyy')
            ? strtotime(date('Y-m-d', strtotime($r->tanggal_bongkar)))
            : null;

        $estimasi = $r->estimasi_tiba ? strtotime($r->estimasi_tiba) : null;

        $lama_perjalanan = ($keluar && $tiba) ? max(0, floor(($tiba - $keluar) / 86400)) : null;

        // SLA Tiba
        $sla_tiba = '-';
        if ($tiba && $estimasi) {
            $sla_tiba = (strtotime(date('Y-m-d', $tiba)) <= strtotime(date('Y-m-d', $estimasi)))
                ? 'On Time' : 'Delay';
        }

        // Overstay bongkar
        $over_bongkar = null;
        if ($tiba && $bongkar) {
            $over_bongkar = max(0, ceil(($bongkar - strtotime(date('Y-m-d', $tiba))) / 86400));
        }

        // SLA Bongkar
        $sla_bongkar = '-';
        if ($tiba && $bongkar) {
            $sla_bongkar = ($over_bongkar <= 0) ? 'On Time' : 'Delay';
        }

        // Estimasi tiba admin
        $estimasiAdmin = null;
        if (!empty($r->rencana_kirim) && !empty($r->transport_lead_time)) {
            $estimasiAdmin = \Carbon\Carbon::parse($r->rencana_kirim)
                ->addDays((int) $r->transport_lead_time);
            if (strtolower(trim($r->area ?? '')) == 'jawa barat') {
                $estimasiAdmin->addDay();
            }
        }

        $statusEstimasiAdmin = '-';
        if ($estimasiAdmin && !empty($r->tanggal_tiba)) {
            $tglTiba = \Carbon\Carbon::parse($r->tanggal_tiba);
            $statusEstimasiAdmin = $tglTiba->lte($estimasiAdmin) ? 'On Time' : 'Delay';
        } elseif ($estimasiAdmin) {
            $statusEstimasiAdmin = now()->startOfDay()->gt($estimasiAdmin->copy()->startOfDay())
                ? 'Delay' : 'Belum Tiba';
        }

        // ================= STATUS POSISI MOBIL (gudang aktif) =================
        $gudangList = collect([
            ['nama' => 'KACS',   'planning' => $r->planning_loading,   'tiba' => $r->tanggal_tiba_gudang,   'keluar' => $r->tanggal_keluar_gudang],
            ['nama' => 'SENTUL', 'planning' => $r->planning_loading_2, 'tiba' => $r->tanggal_tiba_gudang_2, 'keluar' => $r->tanggal_keluar_gudang_2],
            ['nama' => 'CCIE',   'planning' => $r->planning_loading_3, 'tiba' => $r->tanggal_tiba_gudang_3, 'keluar' => $r->tanggal_keluar_gudang_3],
        ])->filter(fn($g) => !empty($g['planning']))
            ->sortBy(fn($g) => strtotime($g['planning']))
            ->values();

        $statusGudang = null;
        foreach ($gudangList as $g) {
            if (empty($g['tiba'])) {
                $statusGudang = ['status' => 'PERJALANAN KE ' . $g['nama'], 'badge' => 'yellow'];
                break;
            }
            if (!empty($g['tiba']) && empty($g['keluar'])) {
                $statusGudang = ['status' => 'DI GUDANG ' . $g['nama'], 'badge' => 'blue'];
                break;
            }
        }

        $adaPlanningGudang = $gudangList->count() > 0;
        $tibaAkhir = $r->tanggal_tiba;
        $bongkarAkhir = $r->tanggal_bongkar;

        if (empty($r->tanggal_dpt_unit)) {
            $posisiStatus = 'MENCARI UNIT';
            $posisiBadge = 'red';
        } elseif (!$adaPlanningGudang && empty($tibaAkhir)) {
            $posisiStatus = 'PERJALANAN KE GUDANG';
            $posisiBadge = 'orange';
        } elseif ($statusGudang) {
            $posisiStatus = $statusGudang['status'];
            $posisiBadge = $statusGudang['badge'];
        } elseif (empty($tibaAkhir)) {
            $posisiStatus = 'PERJALANAN KE TUJUAN';
            $posisiBadge = 'yellow';
        } elseif (!empty($tibaAkhir) && !empty($bongkarAkhir)) {
            $posisiStatus = 'SUDAH SELESAI';
            $posisiBadge = 'green';
        } elseif (!empty($tibaAkhir)) {
            $posisiStatus = 'SUDAH TIBA TUJUAN';
            $posisiBadge = 'success';
        } else {
            $posisiStatus = '-';
            $posisiBadge = 'gray';
        }

        // ================= ALERT ESTIMASI =================
        $alert = '-';
        $alertClass = 'gray';
        if ($statusGudang) {
            $estimasiShow = $statusGudang['status'];
        } else {
            $estimasiShow = $estimasi ? date('d-m-Y', $estimasi) : '-';

            if (!$r->tanggal_tiba && $estimasi) {
                $hariSisa = floor(($estimasi - strtotime(date('Y-m-d'))) / 86400);
                if ($hariSisa < 0) {
                    $alert = 'OVERDUE';
                    $alertClass = 'red';
                } elseif ($hariSisa == 0) {
                    $alert = 'H-0';
                    $alertClass = 'red';
                } elseif ($hariSisa == 1) {
                    $alert = 'H-1';
                    $alertClass = 'red';
                } elseif ($hariSisa == 2) {
                    $alert = 'H-2';
                    $alertClass = 'orange';
                } elseif ($hariSisa == 3) {
                    $alert = 'H-3';
                    $alertClass = 'orange';
                } elseif ($hariSisa <= 7) {
                    $alert = 'H-' . $hariSisa;
                    $alertClass = 'blue';
                } else {
                    $alert = 'ON TRACK';
                    $alertClass = 'green';
                }
            }
        }

        // ================= STATUS BONGKAR =================
        $statusBongkar = '-';
        $statusBongkarClass = '';
        if ($r->tanggal_bongkar) {
            $statusBongkar = 'Sudah Bongkar';
            $statusBongkarClass = 'green';
        } elseif ($r->tanggal_tiba) {
            $hariBongkar = floor((strtotime(date('Y-m-d')) - strtotime(date('Y-m-d', strtotime($r->tanggal_tiba)))) / 86400);
            $statusBongkar = 'Pending Bongkar H+' . max(0, $hariBongkar);
            $statusBongkarClass = $hariBongkar == 0 ? 'orange' : 'red';
        }

        // ================= DIST CHANNEL BADGE (warna hash) =================
        $channel = trim($r->dist_channel ?? '');
        $channelClasses = ['badge-green', 'badge-blue', 'badge-orange', 'badge-red', 'badge-purple', 'badge-pink', 'badge-cyan', 'badge-yellow'];
        $channelClass = $channel ? $channelClasses[abs(crc32($channel)) % count($channelClasses)] : 'badge-default';

        // ================= KATEGORI EKSPEDISI =================
        $kategori = $r->kategori_ekspedisi ?? '-';
        if (empty($kategori) || $kategori == '-') {
            $kategoriHtml = '<span class="badge gray">-</span>';
        } elseif (strtolower($kategori) == 'kontrak') {
            $kategoriHtml = '<span class="badge yellow">Kontrak</span>';
        } elseif (strtolower($kategori) == 'oncall') {
            $kategoriHtml = '<span class="badge blue">Oncall</span>';
        } else {
            $kategoriHtml = '<span class="badge orange">' . e($kategori) . '</span>';
        }

        // ================= SLA DAPAT MOBIL =================
        $slaMobil = trim($r->sla_dapat_mobil ?? '');
        if (empty($slaMobil)) {
            $slaMobilHtml = '<span class="badge gray">-</span>';
        } elseif (strtolower($slaMobil) == 'on time' || strtoupper($slaMobil) == 'H+0') {
            $slaMobilHtml = '<span class="badge green">' . e($slaMobil) . '</span>';
        } elseif (strtolower($slaMobil) == 'delay') {
            $slaMobilHtml = '<span class="badge red">Delay</span>';
        } elseif (preg_match('/h\+1/i', $slaMobil)) {
            $slaMobilHtml = '<span class="badge orange">' . e($slaMobil) . '</span>';
        } elseif (preg_match('/h\+/i', $slaMobil)) {
            $slaMobilHtml = '<span class="badge red">' . e($slaMobil) . '</span>';
        } else {
            $slaMobilHtml = '<span class="badge gray">' . e($slaMobil) . '</span>';
        }

        // ================= GUDANG 1 (KACS) durasi/status/sla =================
        $durasiGudang = function ($planning, $tibaG) {
            if (empty($planning) || empty($tibaG)) return '-';
            $start = \Carbon\Carbon::parse($planning);
            $end   = \Carbon\Carbon::parse($tibaG);
            $desimalHari = $start->diffInMinutes($end) / 1440;
            $hari = floor($desimalHari);
            $jam  = round(($desimalHari - $hari) * 24);
            if ($jam == 24) {
                $jam = 0;
                $hari += 1;
            }
            if ($hari > 0 && $jam > 0) return "{$hari} Hari {$jam} Jam";
            if ($hari > 0) return "{$hari} Hari";
            if ($jam > 0) return "{$jam} Jam";
            return "0 Jam";
        };

        $statusGudangBadge = function ($planning, $tibaG) {
            if (empty($planning) || empty($tibaG)) return '<span class="badge gray">-</span>';
            $startDay = \Carbon\Carbon::parse($planning)->startOfDay();
            $endDay   = \Carbon\Carbon::parse($tibaG)->startOfDay();
            return $endDay->gt($startDay)
                ? '<span class="badge red">Delay</span>'
                : '<span class="badge green">On Time</span>';
        };

        $slaLoadingBadge = function ($planning, $tibaG) {
            if (empty($planning) || empty($tibaG)) return '<span class="badge bg-secondary">-</span>';
            $start = \Carbon\Carbon::parse($planning)->startOfDay();
            $end   = \Carbon\Carbon::parse($tibaG)->startOfDay();
            if ($end->gt($start)) {
                return '<span class="badge bg-warning text-dark">H+' . $start->diffInDays($end) . '</span>';
            }
            return '<span class="badge bg-success">Sesuai SLA</span>';
        };

        $statusGudangBadgeGeneric = function ($status) {
            if (empty($status)) return '<span class="badge gray">-</span>';
            $l = strtolower($status);
            if ($l == 'on time') return '<span class="badge green">On Time</span>';
            if ($l == 'delay')   return '<span class="badge red">Delay</span>';
            if ($l == 'on site') return '<span class="badge orange">On Site</span>';
            return '<span class="badge gray">' . e($status) . '</span>';
        };

        // ================= MONITORING (status_kendaraan) =================
        $statusKendaraan = trim($r->status_kendaraan ?? '');
        if ($statusKendaraan == 'On Track') {
            $monitoringHtml = '<span class="badge green">🟢 On Track</span>';
        } elseif ($statusKendaraan == 'Potential Delay') {
            $monitoringHtml = '<span class="badge red">🔴 Potential Delay</span>';
        } else {
            $monitoringHtml = '<span class="badge gray">-</span>';
        }

        $alertHtml = $r->tanggal_tiba
            ? '<span class="badge green">✅ TIBA</span>'
            : '<span class="badge ' . $alertClass . '">' . e($alert) . '</span>';

        // ================= SLA TIBA / SLA BONGKAR BADGE =================
        $slaTibaHtml = $sla_tiba == '-' ? '-' : ($sla_tiba == 'On Time'
            ? '<span class="badge green">On Time</span>'
            : '<span class="badge red">' . e($sla_tiba) . '</span>');

        $slaBongkarHtml = $sla_bongkar == '-' ? '-' : ($sla_bongkar == 'On Time'
            ? '<span class="badge green">On Time</span>'
            : '<span class="badge red">' . e($sla_bongkar) . '</span>');

        $statusBongkarHtml = $statusBongkar != '-'
            ? '<span class="badge ' . $statusBongkarClass . '">' . e($statusBongkar) . '</span>'
            : '-';

        // ================= STATUS AKHIR =================
        $slaTibaUp = strtoupper(trim($sla_tiba));
        $slaBongkarUp = strtoupper(trim($sla_bongkar));

        if (empty($r->tanggal_tiba)) {
            $statusAkhirHtml = '<span class="status-badge status-transit">🚚 Dalam Perjalanan</span>';
        } elseif (empty($r->tanggal_bongkar)) {
            $statusAkhirHtml = '<span class="status-badge status-unloading">📦 Sudah Tiba <br> Dalam Pembongkaran</span>';
        } elseif ($slaTibaUp == 'ON TIME' && $slaBongkarUp == 'ON TIME') {
            $statusAkhirHtml = '<span class="status-badge status-ontime">✅ Pengiriman On Time</span>';
        } else {
            $statusAkhirHtml = '<span class="status-badge status-delay">🚨 Pengiriman Delay</span>';
        }

        // ================= STATUS ALERT =================
        if ($slaTibaUp == 'ON TIME' && $slaBongkarUp == 'ON TIME') {
            $statusAlertHtml = '<span class="badge badge-success">🟢 Delivered Ontime</span>';
        } elseif ($slaTibaUp == 'DELAY' && $slaBongkarUp == 'ON TIME') {
            $statusAlertHtml = '<span class="badge badge-warning">🚚 Delay Perjalanan</span>';
        } elseif ($slaTibaUp == 'ON TIME' && $slaBongkarUp == 'DELAY') {
            $statusAlertHtml = '<span class="badge badge-info">📦 Delay Pembongkaran</span>';
        } elseif ($slaTibaUp == 'DELAY' && $slaBongkarUp == 'DELAY') {
            $statusAlertHtml = '<span class="badge badge-danger">🔥 Delivered Delay</span>';
        } else {
            $statusAlertHtml = '<span class="badge badge-secondary">⏳ Belum Selesai</span>';
        }

        $statusEstAdminHtml = match ($statusEstimasiAdmin) {
            'On Time'   => '<span class="badge green">On Time</span>',
            'Delay'     => '<span class="badge red">Delay</span>',
            'Belum Tiba' => '<span class="badge orange">Belum Tiba</span>',
            default     => '<span class="badge gray">-</span>',
        };

        // ================= KETERSEDIAAN UNIT =================
        if (empty($r->rencana_kirim) || empty($r->tanggal_dpt_unit)) {
            $ketersediaanHtml = '<span class="badge-status status-belum">BELUM DAPAT</span>';
        } else {
            $ketersediaanHtml = '<span class="badge-status status-sudah">SUDAH DAPAT</span>';
        }

        return [
            $fmtDate($r->tanggal_naik_logistik),
            $fmtDate($r->rencana_kirim),
            $r->transport_lead_time,
            $r->nama_driver,
            $r->no_pol,
            $r->planner,
            $r->no_shipment,
            '<span class="badge ' . $posisiBadge . '">' . e($posisiStatus) . '</span>',
            '<span class="badge ' . $channelClass . '">' . e($channel ?: '-') . '</span>',
            $r->tujuan,
            $r->area,
            $ketersediaanHtml,
            $r->mobil,
            $r->total_do_qty_car,
            $fmtRupiah($r->nilai_muatan),
            $fmtRupiah($r->biaya_kirim),
            is_numeric($r->cr) ? number_format((float) $r->cr, 4, ',', '.') . '%' : ($r->cr ?? '-'),
            $kategoriHtml,
            $r->ekpedisi,
            $r->tanggal_dpt_unit ? date('d-m-Y', strtotime($r->tanggal_dpt_unit)) : '-',
            $r->lama_waktu_pencarian ?? '-',
            $slaMobilHtml,
            $fmtDate($r->planning_loading),
            $fmtDate($r->tanggal_tiba_gudang),
            $fmtDate($r->tanggal_keluar_gudang),
            $durasiGudang($r->planning_loading, $r->tanggal_tiba_gudang),
            $statusGudangBadge($r->planning_loading, $r->tanggal_tiba_gudang),
            $slaLoadingBadge($r->planning_loading, $r->tanggal_tiba_gudang),
            $fmtDate($r->planning_loading_2),
            $fmtDate($r->tanggal_tiba_gudang_2),
            $fmtDate($r->tanggal_keluar_gudang_2),
            $r->lama_digudang_2 ?? '-',
            $badgeSla($r->sla_loading_2),
            $statusGudangBadgeGeneric($r->status_gudang_2),
            $fmtDate($r->planning_loading_3),
            $fmtDate($r->tanggal_tiba_gudang_3),
            $fmtDate($r->tanggal_keluar_gudang_3),
            $r->lama_digudang_3 ?? '-',
            $badgeSla($r->sla_loading_3),
            $statusGudangBadgeGeneric($r->status_gudang_3),
            $r->pic_monitoring,
            $r->nama_kapal,
            $r->etd,
            $r->eta,
            $monitoringHtml,
            $alertHtml,
            $r->act_urutan_bongkar,
            $r->qty_monitoring,
            $fmtRupiahOrBlank($r->biaya_kuli),
            $fmtRupiahOrBlank($r->total_biaya_kuli),
            $r->selisih_qty,
            $r->remarks_qty,
            $r->create_tgl ? \Carbon\Carbon::parse($r->create_tgl)->format('d/m/Y H:i') : '-',
            $r->atd,
            $r->ata,
            $statusGudang ? $statusGudang['status'] : $estimasiShow,
            $r->tanggal_tiba ? date('d-m-Y h:i A', strtotime($r->tanggal_tiba)) : '-',
            $lama_perjalanan !== null ? $lama_perjalanan . ' Hari' : '-',
            $slaTibaHtml,
            $r->tanggal_bongkar ? date('d-m-Y h:i A', strtotime($r->tanggal_bongkar)) : '-',
            $statusBongkarHtml,
            $over_bongkar !== null ? $over_bongkar . ' Hari' : '-',
            $slaBongkarHtml,
            $r->reason_tiba,
            $r->reason_bongkar,
            $statusAkhirHtml,
            $statusAlertHtml,
            $r->remarks,
            $r->route,
            $r->route ? explode('-', trim($r->route))[0] : '-',
            $r->pulau,
            $r->via_kirim,
            $estimasiAdmin ? $estimasiAdmin->format('d-m-Y') : '-',
            $statusEstAdminHtml,
        ];
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

    /**
     * =====================================================
     * HITUNG SLA (dipakai oleh store/update/autosaveRow)
     * =====================================================
     */
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

        $tibaGudang = collect([
            $request->tanggal_tiba_gudang,
            $request->tanggal_tiba_gudang_2,
            $request->tanggal_tiba_gudang_3,
        ])->filter()->sort()->first();

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

            $tanggalRencana = strtotime(date('Y-m-d', strtotime($start)));
            $tanggalDptUnit = strtotime(date('Y-m-d', strtotime($end)));

            $selisihHari = floor(
                ($tanggalDptUnit - $tanggalRencana) / 86400
            );

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
        }

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

    /**
     * =====================================================
     * AUTOSAVE ROW (inline edit per-cell)
     * =====================================================
     */
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

        $rumus = $this->hitungSla($request);

        $autoBiaya = $this->cariBiayaKirimOtomatis(
            $request->route,
            $request->mobil,
            $request->ekpedisi
        );

        $biayaKirim = $autoBiaya !== null
            ? $this->cleanMoney($autoBiaya)
            : $this->cleanMoney($request->biaya_kirim);

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

        if ($autoBiaya !== null) {
            DB::table('logistik_pengiriman')
                ->where('no_shipment', $shipment)
                ->update([
                    'biaya_kirim' => $biayaKirim,
                    'updated_at'  => now(),
                ]);
        }

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
            ->filter(fn($t) => $normalize($t->route) === $routeKey);

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

        $fallback = $candidates->first(fn($t) => str_starts_with($normalize($t->mobil), $mobilKey));

        return $fallback->biaya_kirim ?? null;
    }

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

        $sla = $this->hitungSla($request);

        $data = [

            'tanggal_tiba_gudang_2'   => $request->tanggal_tiba_gudang_2,
            'tanggal_keluar_gudang_2' => $request->tanggal_keluar_gudang_2,

            'tanggal_tiba_gudang_3'   => $request->tanggal_tiba_gudang_3,
            'tanggal_keluar_gudang_3' => $request->tanggal_keluar_gudang_3,

            'lama_digudang_2' => $sla['lama_digudang_2'],
            'status_gudang_2' => $sla['status_gudang_2'],
            'sla_loading_2'   => $sla['sla_loading_2'],

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
            ->filter(fn($row) => $row->sla_dapat_mobil === 'Delay');

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
