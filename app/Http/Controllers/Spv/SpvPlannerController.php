<?php

namespace App\Http\Controllers\Spv;

use App\Imports\UpdateQtyPgiImport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\LogistikPengiriman;
use App\Models\LogistikPengirimanPasuruan;
use App\Models\TarifPengiriman;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PlannerExport;
use App\Imports\PasuruanImport;
use App\Exports\PasuruanExport;

/**
 * CATATAN:
 * - Dashboard / KPI / In Transit / Belum Tiba Gudang  -> logika sama dengan SpvMonitoringController
 *   (dihitung per grup no_shipment + tujuan, bukan per DO).
 * - Halaman Data Logistik (full_data_logistik) -> ajax, detail & export dilayani LogistikController
 *   (route logistik.ajax / logistik.detail / logistik.export). fullDataLogistikAjax() &
 *   renderFullDataRow() DIHAPUS dari controller ini.
 * - Halaman Data Logistik Pasuruan (data_logistik_pasuruan) -> SAMA dengan SpvMonitoringController:
 *   1 baris = shipment + tujuan, rincian DO via dataLogistikDetailPasuruan().
 */
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

    /* =====================================================
     * GROUP HELPERS (sama dengan SpvMonitoringController)
     * ===================================================== */

    /** 1 id wakil (MIN id) per shipment + tujuan */
    private function groupIdsQuery($query)
    {
        return (clone $query)
            ->select(DB::raw('MIN(id) as id'))
            ->groupByRaw("COALESCE(NULLIF(TRIM(no_shipment),''), CONCAT('id', id)), COALESCE(tujuan,'')");
    }

    private function countGroups($query): int
    {
        return DB::query()->fromSub($this->groupIdsQuery($query), 'g')->count();
    }

    /** per grup: biaya = MAX, nilai = SUM */
    private function grupBiayaQuery($query)
    {
        return (clone $query)
            ->selectRaw("
                COALESCE(NULLIF(TRIM(no_shipment),''), CONCAT('id', id)) AS sh,
                COALESCE(tujuan,'')            AS tj,
                MAX(area)                      AS area,
                MAX(pulau)                     AS pulau,
                MAX(ekpedisi)                  AS ekpedisi,
                MAX(COALESCE(biaya_kirim,0))   AS biaya,
                SUM(COALESCE(nilai_muatan,0))  AS nilai
            ")
            ->groupByRaw('sh, tj');
    }

    private function groupIdsQueryPasuruan($query)
    {
        return (clone $query)
            ->select(DB::raw('MIN(id) as id'))
            ->groupByRaw("COALESCE(NULLIF(TRIM(no_shipment_pasuruan),''), CONCAT('id', id)), COALESCE(tujuan_pasuruan,'')");
    }

    private function countGroupsPasuruan($query): int
    {
        return DB::query()->fromSub($this->groupIdsQueryPasuruan($query), 'g')->count();
    }

    private function grupBiayaQueryPasuruan($query)
    {
        return (clone $query)
            ->selectRaw("
                COALESCE(NULLIF(TRIM(no_shipment_pasuruan),''), CONCAT('id', id)) AS sh,
                COALESCE(tujuan_pasuruan,'')            AS tj,
                MAX(area_pasuruan)                      AS area_pasuruan,
                MAX(COALESCE(biaya_kirim_pasuruan,0))   AS biaya,
                SUM(COALESCE(nilai_muatan_pasuruan,0))  AS nilai
            ")
            ->groupByRaw('sh, tj');
    }

    /* =====================================================
     * IMPORT / EXPORT PASURUAN
     * ===================================================== */
    public function importPasuruan(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:10240',
        ]);

        Excel::import(new PasuruanImport, $request->file('file'));

        return redirect()
            ->route('spvplanner.data.pasuruan')
            ->with('success', 'Data logistik Pasuruan berhasil diimport.');
    }

    public function exportPasuruan()
    {
        return Excel::download(new PasuruanExport(), 'Data_Logistik_Pasuruan.xlsx');
    }

    public function importQtyPgi(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        $import = new UpdateQtyPgiImport;
        Excel::import($import, $request->file('file'));

        $message = "Qty DO: {$import->getQtyUpdated()} baris berhasil diupdate.";

        if ($import->getQtySkipped() > 0) {
            $message .= " {$import->getQtySkipped()} baris dilewati.";
        }
        if (!empty($import->getQtyNotFound())) {
            $message .= " Qty tidak ketemu: " . implode(' | ', array_slice($import->getQtyNotFound(), 0, 5));
            if (count($import->getQtyNotFound()) > 5) {
                $message .= " (dan " . (count($import->getQtyNotFound()) - 5) . " lainnya)";
            }
        }
        if (!empty($import->getQtyAmbiguous())) {
            $message .= " Qty ambigu: " . implode(' | ', array_slice($import->getQtyAmbiguous(), 0, 5));
        }

        $message .= " || PGI Date: {$import->getPgiUpdated()} baris berhasil diupdate.";

        if (!empty($import->getPgiNotFound())) {
            $message .= " PGI tidak ketemu: " . implode(', ', array_slice($import->getPgiNotFound(), 0, 5));
        }

        return redirect()->back()->with('success', $message);
    }

    /* =====================================================
     * REASON LISTS
     * ===================================================== */
    private function getReasonOptimalList()
    {
        return DB::table('akurasi3')
            ->whereNotNull('reason_optimal')
            ->where('reason_optimal', '!=', '')
            ->distinct()
            ->orderBy('reason_optimal')
            ->pluck('reason_optimal');
    }

    private function getReasonGudangList()
    {
        return DB::table('akurasi3')
            ->whereNotNull('reason_gudang')
            ->where('reason_gudang', '!=', '')
            ->distinct()
            ->orderBy('reason_gudang')
            ->pluck('reason_gudang');
    }

    public function searchReasonOptimal(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $items = DB::table('akurasi3')
            ->whereNotNull('reason_optimal')
            ->where('reason_optimal', '!=', '')
            ->when($q !== '', fn($query) => $query->where('reason_optimal', 'like', "%{$q}%"))
            ->distinct()
            ->orderBy('reason_optimal')
            ->limit(50)
            ->pluck('reason_optimal');

        return response()->json(
            $items->map(fn($v) => ['id' => $v, 'text' => $v])->values()
        );
    }


    private function cleanMoney($value)
    {
        if (!$value) return null;
        return (int) preg_replace('/[^0-9]/', '', $value);
    }

    /* =====================================================
     * TARIF PENGIRIMAN (CRUD)
     * ===================================================== */
    private function guardSpvPlanner(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'spvplanner') {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }
    }

    private function tarifRules(): array
    {
        return [
            'servc_agent' => 'nullable|string|max:10',
            'ekpedisi'    => 'nullable|string|max:100',
            'sh'          => 'nullable|string|max:10',
            'mobil'       => 'nullable|string|max:50',
            'routew'      => 'nullable|string|max:20',
            'route'       => 'nullable|string|max:100',
            'biaya_kirim' => 'nullable|string|max:30',
            'unit'        => 'nullable|string|max:10',
            'per'         => 'nullable|string|max:10',
            'uom'         => 'nullable|string|max:10',
            'd'           => 'nullable|string|max:10',
            'tx'          => 'nullable|string|max:10',
            'e'           => 'nullable|string|max:10',
            's_1'         => 'nullable|string|max:10',
            's_2'         => 'nullable|string|max:10',
            'valid_from'  => 'nullable|string|max:20',
            'valid_to'    => 'nullable|string|max:20',
        ];
    }

    public function tarifIndex(Request $request)
    {
        $this->guardSpvPlanner();

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

        $data = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return view('spvplanner.tarif_pengiriman.index', compact('data'));
    }

    public function tarifCreate()
    {
        $this->guardSpvPlanner();
        return view('spvplanner.tarif_pengiriman.create');
    }

    public function tarifStore(Request $request)
    {
        $this->guardSpvPlanner();

        TarifPengiriman::create($request->validate($this->tarifRules()));

        return redirect()->route('spvplanner.tarif.index')->with('success', 'Data tarif berhasil ditambahkan.');
    }

    public function tarifEdit($id)
    {
        $this->guardSpvPlanner();

        $data = TarifPengiriman::findOrFail($id);

        return view('spvplanner.tarif_pengiriman.edit', compact('data'));
    }

    public function tarifUpdate(Request $request, $id)
    {
        $this->guardSpvPlanner();

        $data = TarifPengiriman::findOrFail($id);
        $data->update($request->validate($this->tarifRules()));

        return redirect()->route('spvplanner.tarif.index')->with('success', 'Data tarif berhasil diperbarui.');
    }

    public function tarifDestroy($id)
    {
        $this->guardSpvPlanner();

        TarifPengiriman::findOrFail($id)->delete();

        return redirect()->route('spvplanner.tarif.index')->with('success', 'Data tarif berhasil dihapus.');
    }

    /* =====================================================
     * FILTER HELPERS
     * ===================================================== */

    /** SAMA dengan SpvMonitoringController: default tanggal_naik_logistik */
    private function dateFilterColumn($request): string
    {
        return match ($request->input('tgl_basis')) {
            'create_tgl'    => 'create_tgl',
            'rencana_kirim' => 'rencana_kirim',
            default         => 'tanggal_naik_logistik',
        };
    }

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

        $col = $this->dateFilterColumn($request);

        if ($request->date) {
            $query->whereDate($col, $request->date);
        }
        if ($request->month) {
            $query->whereMonth($col, substr($request->month, 5, 2));
            $query->whereYear($col, substr($request->month, 0, 4));
        }
        if ($request->year) {
            $query->whereYear($col, $request->year);
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

        $col = match ($request->input('tgl_basis')) {
            'rencana_kirim_pasuruan' => 'rencana_kirim_pasuruan',
            'create_tgl_pasuruan'    => 'create_tgl_pasuruan',
            default                  => 'tanggal_terima_po_pasuruan',
        };

        if ($request->date) {
            $query->whereDate($col, $request->date);
        }
        if ($request->month) {
            $query->whereMonth($col, substr($request->month, 5, 2));
            $query->whereYear($col, substr($request->month, 0, 4));
        }
        if ($request->year) {
            $query->whereYear($col, $request->year);
        }

        return $query;
    }

    private function dashRequestPasuruan(Request $request): Request
    {
        $params = $request->input('dash');
        if (!is_array($params) || empty($params)) {
            $params = [];
            parse_str((string) parse_url((string) $request->headers->get('referer'), PHP_URL_QUERY), $params);
        }

        $params = array_intersect_key($params, array_flip([
            'tgl_basis', 'date_from', 'date_to', 'date', 'month', 'year',
            'area', 'dist_channel', 'pulau',
        ]));

        return new Request(array_filter($params, 'is_scalar'));
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

    /* =====================================================
     * IN TRANSIT (NON-PASURUAN) — per grup shipment+tujuan
     * ===================================================== */
    private function inTransitQuery()
    {
        $filled = fn($c) => "NULLIF(TRIM({$c}), '') IS NOT NULL";
        $empty  = fn($c) => "NULLIF(TRIM({$c}), '') IS NULL";

        $q = DB::table('logistik_pengiriman')->whereRaw($empty('tanggal_tiba'));

        $q->whereRaw('(' . implode(' OR ', [
            $filled('tanggal_keluar_gudang'),
            $filled('tanggal_keluar_gudang_2'),
            $filled('tanggal_keluar_gudang_3'),
        ]) . ')');

        $cycles = [
            ['planning_loading',   'tanggal_tiba_gudang',   'tanggal_keluar_gudang'],
            ['planning_loading_2', 'tanggal_tiba_gudang_2', 'tanggal_keluar_gudang_2'],
            ['planning_loading_3', 'tanggal_tiba_gudang_3', 'tanggal_keluar_gudang_3'],
        ];
        foreach ($cycles as [$planning, $tiba, $keluar]) {
            $q->whereRaw('NOT ((' . $filled($planning) . ' OR ' . $filled($tiba) . ') AND ' . $empty($keluar) . ')');
        }

        return $q;
    }

    private function inTransitEstimasiSql(): string
    {
        return "COALESCE(estimasi_tiba, DATE_ADD(
            GREATEST(
                COALESCE(tanggal_keluar_gudang,'1900-01-01'),
                COALESCE(tanggal_keluar_gudang_2,'1900-01-01'),
                COALESCE(tanggal_keluar_gudang_3,'1900-01-01')
            ),
            INTERVAL CAST(COALESCE(NULLIF(TRIM(transport_lead_time),''),0) AS UNSIGNED) DAY
        ))";
    }

    private function inTransitAsalSql(): string
    {
        $k1 = "COALESCE(NULLIF(TRIM(tanggal_keluar_gudang),''),'1900-01-01')";
        $k2 = "COALESCE(NULLIF(TRIM(tanggal_keluar_gudang_2),''),'1900-01-01')";
        $k3 = "COALESCE(NULLIF(TRIM(tanggal_keluar_gudang_3),''),'1900-01-01')";

        return "CASE
            WHEN {$k3} > '1900-01-01' AND {$k3} >= {$k1} AND {$k3} >= {$k2} THEN 'CCIE'
            WHEN {$k2} > '1900-01-01' AND {$k2} >= {$k1} AND {$k2} >= {$k3} THEN 'SENTUL'
            ELSE 'KACS' END";
    }

    private function inTransitSortExpr(string $key): ?string
    {
        $k1 = "COALESCE(NULLIF(TRIM(tanggal_keluar_gudang),''),'1900-01-01')";
        $k2 = "COALESCE(NULLIF(TRIM(tanggal_keluar_gudang_2),''),'1900-01-01')";
        $k3 = "COALESCE(NULLIF(TRIM(tanggal_keluar_gudang_3),''),'1900-01-01')";
        $keluar = "GREATEST({$k1},{$k2},{$k3})";
        $est    = $this->inTransitEstimasiSql();

        return match ($key) {
            'no_shipment', 'tujuan', 'area', 'dist_channel', 'ekpedisi', 'mobil',
            'nama_driver', 'no_pol', 'pic_monitoring', 'remarks' => $key,
            'gudang_asal'    => '(' . $this->inTransitAsalSql() . ')',
            'tanggal_keluar' => $keluar,
            'lama_jalan'     => "DATEDIFF(CURDATE(), DATE({$keluar}))",
            'estimasi', 'alert' => "DATE({$est})",
            default          => null,
        };
    }

    private function applyInTransitSearch($base, string $s)
    {
        $s = trim($s);
        if ($s === '') {
            return $base;
        }
        $like = "%{$s}%";

        $est = $this->inTransitEstimasiSql();
        $k1  = "COALESCE(NULLIF(TRIM(tanggal_keluar_gudang),''),'1900-01-01')";
        $k2  = "COALESCE(NULLIF(TRIM(tanggal_keluar_gudang_2),''),'1900-01-01')";
        $k3  = "COALESCE(NULLIF(TRIM(tanggal_keluar_gudang_3),''),'1900-01-01')";
        $keluar = "GREATEST({$k1},{$k2},{$k3})";
        $sisa   = "DATEDIFF(DATE({$est}), CURDATE())";

        $exprs = [
            $this->inTransitAsalSql(),
            "DATE_FORMAT({$keluar}, '%d-%m-%Y')",
            "CONCAT(DATEDIFF(CURDATE(), DATE({$keluar})), ' Hari')",
            "DATE_FORMAT({$est}, '%d-%m-%Y')",
            "CASE
                WHEN {$sisa} < 0 THEN CONCAT('Pending Tiba H+', ABS({$sisa}))
                WHEN {$sisa} <= 7 THEN CONCAT('H-', {$sisa})
                ELSE 'ON TRACK' END",
        ];

        $cols = [
            'no_shipment', 'tujuan', 'area', 'dist_channel', 'ekpedisi',
            'mobil', 'nama_driver', 'no_pol', 'pic_monitoring', 'remarks',
        ];

        return $base->where(function ($q) use ($cols, $exprs, $like) {
            foreach ($cols as $col) {
                $q->orWhere($col, 'like', $like);
            }
            foreach ($exprs as $expr) {
                $q->orWhereRaw("({$expr}) LIKE ?", [$like]);
            }
        });
    }

    public function inTransit(Request $request)
    {
        $today   = date('Y-m-d');
        $todayTs = strtotime($today);
        $soon    = date('Y-m-d', strtotime('+3 days'));
        $est     = $this->inTransitEstimasiSql();

        $base = $this->inTransitQuery();

        // filter dari dashboard (tanggal/bulan/tahun/area/channel/pulau)
        $this->applyFilter($base, $request);

        if ($request->filled('pic_monitoring')) {
            $base->where('pic_monitoring', $request->input('pic_monitoring'));
        }
        if ($request->filled('gudang_asal')) {
            $base->whereRaw('(' . $this->inTransitAsalSql() . ') = ?', [$request->input('gudang_asal')]);
        }
        if ($request->filled('q')) {
            $this->applyInTransitSearch($base, $request->input('q'));
        }

        // 1 baris wakil per shipment + tujuan
        $grouped = DB::table('logistik_pengiriman')
            ->whereIn('id', $this->groupIdsQuery($base));

        $sum = (clone $grouped)->selectRaw("
            COUNT(*) AS total,
            SUM(CASE WHEN DATE({$est}) < ? THEN 1 ELSE 0 END) AS overdue,
            SUM(CASE WHEN DATE({$est}) BETWEEN ? AND ? THEN 1 ELSE 0 END) AS soon,
            SUM(CASE WHEN DATE({$est}) > ? THEN 1 ELSE 0 END) AS ontrack
        ", [$today, $today, $soon, $soon])->first();

        $summary = [
            'total'   => (int) ($sum->total ?? 0),
            'overdue' => (int) ($sum->overdue ?? 0),
            'soon'    => (int) ($sum->soon ?? 0),
            'ontrack' => (int) ($sum->ontrack ?? 0),
        ];

        $listQuery = clone $grouped;

        $sortExpr = $this->inTransitSortExpr((string) $request->input('sort'));
        $dir      = strtolower($request->input('dir')) === 'desc' ? 'DESC' : 'ASC';
        if ($sortExpr) {
            $listQuery->orderByRaw("{$sortExpr} {$dir}");
        }

        $list = $listQuery
            ->orderBy('no_shipment', 'ASC')
            ->orderBy('tujuan', 'ASC')
            ->paginate(50)
            ->withQueryString();

        $list->getCollection()->transform(function ($r) use ($todayTs) {
            $keluar = null;
            $asal   = '-';
            foreach ([
                ['KACS',   $r->tanggal_keluar_gudang],
                ['SENTUL', $r->tanggal_keluar_gudang_2],
                ['CCIE',   $r->tanggal_keluar_gudang_3],
            ] as [$nama, $tgl]) {
                if (!empty($tgl)) {
                    $ts = strtotime($tgl);
                    if ($keluar === null || $ts >= $keluar) {
                        $keluar = $ts;
                        $asal   = $nama;
                    }
                }
            }

            $lead     = (int) ($r->transport_lead_time ?? 0);
            $keluarD  = $keluar ? strtotime(date('Y-m-d', $keluar)) : null;
            $estimasi = !empty($r->estimasi_tiba)
                ? strtotime($r->estimasi_tiba)
                : ($keluarD ? strtotime("+{$lead} days", $keluarD) : null);

            $alert = '-';
            $cls   = 'gray';
            if ($estimasi) {
                $sisa = floor(($estimasi - $todayTs) / 86400);
                if     ($sisa < 0)  { $alert = 'Pending Tiba H+' . abs($sisa); $cls = 'red'; }
                elseif ($sisa <= 1) { $alert = 'H-' . $sisa;                    $cls = 'red'; }
                elseif ($sisa <= 3) { $alert = 'H-' . $sisa;                    $cls = 'orange'; }
                elseif ($sisa <= 7) { $alert = 'H-' . $sisa;                    $cls = 'blue'; }
                else                { $alert = 'ON TRACK';                      $cls = 'green'; }
            }

            $r->gudang_asal    = $asal;
            $r->keluar_label   = $keluar ? date('d-m-Y', $keluar) : '-';
            $r->hari_transit   = $keluarD ? max(0, floor(($todayTs - $keluarD) / 86400)) : null;
            $r->estimasi_label = $estimasi ? date('d-m-Y', $estimasi) : '-';
            $r->alert_label    = $alert;
            $r->alert_class    = $cls;

            return $r;
        });

        $areaList = DB::table('logistik_pengiriman')
            ->whereNotNull('area')->distinct()->orderBy('area')->pluck('area');

        $picList = DB::table('logistik_pengiriman')
            ->whereNotNull('pic_monitoring')->distinct()->orderBy('pic_monitoring')->pluck('pic_monitoring');

        $formRoute = route('spvplanner.intransit');

        return view('monitoring.in_transit', compact('list', 'summary', 'areaList', 'picList', 'formRoute'));
    }

    /* =====================================================
     * IN TRANSIT (PASURUAN) — per grup shipment+tujuan
     * ===================================================== */
    private function inTransitQueryPasuruan()
    {
        $filled = fn($c) => "NULLIF(TRIM({$c}), '') IS NOT NULL";
        $empty  = fn($c) => "NULLIF(TRIM({$c}), '') IS NULL";

        return DB::table('logistik_pengiriman_pasuruan')
            ->whereRaw($empty('tanggal_tiba_pasuruan'))
            ->whereRaw($filled('tanggal_keluar_gudang_pasuruan'));
    }

    private function inTransitEstimasiSqlPasuruan(): string
    {
        return "COALESCE(estimasi_tiba_pasuruan, DATE_ADD(
            COALESCE(tanggal_keluar_gudang_pasuruan, '1900-01-01'),
            INTERVAL CAST(COALESCE(NULLIF(TRIM(transport_lead_time_pasuruan),''),0) AS UNSIGNED) DAY
        ))";
    }

    private function applyInTransitSearchPasuruan($base, string $s)
    {
        $s = trim($s);
        if ($s === '') {
            return $base;
        }
        $like = "%{$s}%";

        $est    = $this->inTransitEstimasiSqlPasuruan();
        $keluar = 'tanggal_keluar_gudang_pasuruan';
        $sisa   = "DATEDIFF(DATE({$est}), CURDATE())";

        $exprs = [
            "'GUDANG'",
            "DATE_FORMAT({$keluar}, '%d-%m-%Y')",
            "CONCAT(DATEDIFF(CURDATE(), DATE({$keluar})), ' Hari')",
            "DATE_FORMAT({$est}, '%d-%m-%Y')",
            "CASE
                WHEN {$sisa} < 0 THEN CONCAT('Pending Tiba H+', ABS({$sisa}))
                WHEN {$sisa} <= 7 THEN CONCAT('H-', {$sisa})
                ELSE 'ON TRACK' END",
        ];

        $cols = [
            'no_shipment_pasuruan', 'tujuan_pasuruan', 'area_pasuruan', 'dist_channel_pasuruan',
            'ekspedisi_pasuruan', 'mobil_pasuruan', 'nama_driver_pasuruan', 'no_pol_pasuruan',
            'pic_monitoring_pasuruan', 'remarks_pasuruan',
        ];

        return $base->where(function ($q) use ($cols, $exprs, $like) {
            foreach ($cols as $col) {
                $q->orWhere($col, 'like', $like);
            }
            foreach ($exprs as $expr) {
                $q->orWhereRaw("({$expr}) LIKE ?", [$like]);
            }
        });
    }

    public function inTransitPasuruan(Request $request)
    {
        $today   = date('Y-m-d');
        $todayTs = strtotime($today);
        $soon    = date('Y-m-d', strtotime('+3 days'));
        $est     = $this->inTransitEstimasiSqlPasuruan();

        $base = $this->inTransitQueryPasuruan();

        // filter dari dashboard pasuruan (area/pulau/dist_channel/date/month/year)
        $this->applyFilterPasuruan($base, $request);

        if ($request->filled('pic_monitoring')) {
            $base->where('pic_monitoring_pasuruan', $request->input('pic_monitoring'));
        }
        if ($request->filled('q')) {
            $this->applyInTransitSearchPasuruan($base, $request->input('q'));
        }

        $grouped = DB::table('logistik_pengiriman_pasuruan')
            ->whereIn('id', $this->groupIdsQueryPasuruan($base));

        $sum = (clone $grouped)->selectRaw("
            COUNT(*) AS total,
            SUM(CASE WHEN DATE({$est}) < ? THEN 1 ELSE 0 END) AS overdue,
            SUM(CASE WHEN DATE({$est}) BETWEEN ? AND ? THEN 1 ELSE 0 END) AS soon,
            SUM(CASE WHEN DATE({$est}) > ? THEN 1 ELSE 0 END) AS ontrack
        ", [$today, $today, $soon, $soon])->first();

        $summary = [
            'total'   => (int) ($sum->total ?? 0),
            'overdue' => (int) ($sum->overdue ?? 0),
            'soon'    => (int) ($sum->soon ?? 0),
            'ontrack' => (int) ($sum->ontrack ?? 0),
        ];

        $list = (clone $grouped)
            ->orderByRaw("DATE({$est}) ASC")
            ->orderBy('no_shipment_pasuruan')
            ->orderBy('tujuan_pasuruan')
            ->paginate(50)
            ->withQueryString();

        // mapping kolom *_pasuruan -> properti generik view monitoring/in_transit.blade.php
        $list->getCollection()->transform(function ($r) use ($todayTs) {
            $keluar = !empty($r->tanggal_keluar_gudang_pasuruan)
                ? strtotime($r->tanggal_keluar_gudang_pasuruan)
                : null;

            $lead     = (int) ($r->transport_lead_time_pasuruan ?? 0);
            $keluarD  = $keluar ? strtotime(date('Y-m-d', $keluar)) : null;
            $estimasi = !empty($r->estimasi_tiba_pasuruan)
                ? strtotime($r->estimasi_tiba_pasuruan)
                : ($keluarD ? strtotime("+{$lead} days", $keluarD) : null);

            $alert = '-';
            $cls   = 'gray';
            if ($estimasi) {
                $sisa = floor(($estimasi - $todayTs) / 86400);
                if     ($sisa < 0)  { $alert = 'Pending Tiba H+' . abs($sisa); $cls = 'red'; }
                elseif ($sisa <= 1) { $alert = 'H-' . $sisa;                    $cls = 'red'; }
                elseif ($sisa <= 3) { $alert = 'H-' . $sisa;                    $cls = 'orange'; }
                elseif ($sisa <= 7) { $alert = 'H-' . $sisa;                    $cls = 'blue'; }
                else                { $alert = 'ON TRACK';                      $cls = 'green'; }
            }

            return (object) [
                'no_shipment'    => $r->no_shipment_pasuruan,
                'tujuan'         => $r->tujuan_pasuruan,
                'area'           => $r->area_pasuruan,
                'dist_channel'   => $r->dist_channel_pasuruan,
                'ekpedisi'       => $r->ekspedisi_pasuruan,
                'mobil'          => $r->mobil_pasuruan,
                'nama_driver'    => $r->nama_driver_pasuruan,
                'no_pol'         => $r->no_pol_pasuruan,
                'pic_monitoring' => $r->pic_monitoring_pasuruan,
                'remarks'        => $r->remarks_pasuruan,
                'gudang_asal'    => 'GUDANG',
                'keluar_label'   => $keluar ? date('d-m-Y', $keluar) : '-',
                'hari_transit'   => $keluarD ? max(0, floor(($todayTs - $keluarD) / 86400)) : null,
                'estimasi_label' => $estimasi ? date('d-m-Y', $estimasi) : '-',
                'alert_label'    => $alert,
                'alert_class'    => $cls,
            ];
        });

        $areaList = DB::table('logistik_pengiriman_pasuruan')
            ->whereNotNull('area_pasuruan')->distinct()->orderBy('area_pasuruan')->pluck('area_pasuruan');

        $picList = DB::table('logistik_pengiriman_pasuruan')
            ->whereNotNull('pic_monitoring_pasuruan')->distinct()->orderBy('pic_monitoring_pasuruan')->pluck('pic_monitoring_pasuruan');

        $formRoute = route('spvplanner.pasuruan.intransit');

        return view('monitoring.in_transit', compact('list', 'summary', 'areaList', 'picList', 'formRoute'));
    }

    /* =====================================================
     * BELUM TIBA GUDANG — per grup
     * ===================================================== */
    private function belumTibaGudangQuery()
    {
        return DB::table('logistik_pengiriman')
            ->whereNotNull('tanggal_naik_logistik')
            ->whereNotNull('rencana_kirim')
            ->whereRaw("TRIM(rencana_kirim) <> ''")
            ->whereNotNull('tanggal_dpt_unit')
            ->whereRaw("TRIM(tanggal_dpt_unit) <> ''")
            ->where(fn($q) => $q->whereNull('tanggal_tiba_gudang')->orWhereRaw("TRIM(tanggal_tiba_gudang) = ''"))
            ->where(fn($q) => $q->whereNull('tanggal_tiba_gudang_2')->orWhereRaw("TRIM(tanggal_tiba_gudang_2) = ''"))
            ->where(fn($q) => $q->whereNull('tanggal_tiba_gudang_3')->orWhereRaw("TRIM(tanggal_tiba_gudang_3) = ''"));
    }

    public function belumTibaGudang(Request $request)
    {
        $filter = $this->belumTibaGudangQuery();
        $this->applyFilter($filter, $request);

        $list = DB::table('logistik_pengiriman')
            ->whereIn('id', $this->groupIdsQuery($filter))
            ->orderBy('tanggal_naik_logistik', 'DESC')
            ->orderBy('no_shipment')
            ->orderBy('tujuan')
            ->paginate(10)
            ->withQueryString();

        return view('spvplanner.sla_delay', [
            'title'     => 'BELUM TIBA DI GUDANG',
            'list'      => $list,
            'list_area' => $this->getArea(),
        ]);
    }

    /* =====================================================
     * KPI PASURUAN (query sama dengan Monitoring)
     * ===================================================== */
    private function kpiQueryPasuruan(string $kpi)
    {
        $filled = fn($c) => "NULLIF(TRIM({$c}), '') IS NOT NULL";
        $empty  = fn($c) => "NULLIF(TRIM({$c}), '') IS NULL";

        $q = DB::table('logistik_pengiriman_pasuruan');

        switch ($kpi) {
            case 'gudang_ontime':
                $q->whereRaw($filled('tanggal_tiba_gudang_pasuruan'));
                break;

            case 'gudang_delay': // sudah dapat unit, belum tiba gudang
                $q->whereRaw($filled('rencana_kirim_pasuruan'))
                  ->whereRaw($filled('tanggal_dpt_unit_pasuruan'))
                  ->whereRaw($empty('tanggal_tiba_gudang_pasuruan'));
                break;

            case 'tujuan_ontime':
                $q->whereNotNull('tanggal_tiba_pasuruan')
                  ->whereNotNull('estimasi_tiba_pasuruan')
                  ->whereRaw('DATEDIFF(DATE(tanggal_tiba_pasuruan), DATE(estimasi_tiba_pasuruan)) <= 0');
                break;

            case 'tujuan_delay':
                $q->whereNotNull('tanggal_tiba_pasuruan')
                  ->whereNotNull('estimasi_tiba_pasuruan')
                  ->whereRaw('DATEDIFF(DATE(tanggal_tiba_pasuruan), DATE(estimasi_tiba_pasuruan)) > 0');
                break;

            case 'bongkar_ontime':
                $q->whereNotNull('tanggal_bongkar_pasuruan')
                  ->where('tanggal_bongkar_pasuruan', '!=', '1899-12-31 00:00:00')
                  ->where(fn($w) => $w->whereNull('overstay_days_pasuruan')->orWhere('overstay_days_pasuruan', 0));
                break;

            case 'bongkar_delay':
                $q->whereNotNull('tanggal_bongkar_pasuruan')
                  ->where('tanggal_bongkar_pasuruan', '!=', '1899-12-31 00:00:00')
                  ->where('overstay_days_pasuruan', '>', 0);
                break;
        }

        return $q;
    }
    /** Pakai route pertama yang terdaftar dari daftar kandidat. */
private function firstRoute(array $names, array $params = []): string
{
    foreach ($names as $n) {
        if (\Illuminate\Support\Facades\Route::has($n)) {
            return route($n, $params);
        }
    }
    return '#'; // belum ada route -> cek php artisan route:list
}

private function kpiLinks(Request $request): array
{
    $filter = array_filter(
        $request->only(['tgl_basis', 'date', 'month', 'year', 'area', 'dist_channel', 'pulau']),
        fn($v) => $v !== null && $v !== ''
    );

    // halaman list KPI (ManagerController) tidak mengenal tgl_basis 'create_tgl'
    $kpiFilter = array_diff_key($filter, ['tgl_basis' => true]);

    return [
     'total' => url('/spvplanner/full-data-logistik')
    . (!empty($filter) ? '?' . http_build_query($filter) : ''),
        'in_transit'      => $this->firstRoute(['spvplanner.intransit'], $filter),
        'gudang_ontime'   => $this->firstRoute(['manager.gudang.ontime'], $kpiFilter),
        'gudang_delay'    => $this->firstRoute(['manager.gudang.delay'], $kpiFilter),
        'customer_ontime' => $this->firstRoute(['manager.tujuan.ontime', 'manager.customer.ontime'], $kpiFilter),
        'customer_delay'  => $this->firstRoute(['manager.tujuan.delay', 'manager.customer.delay'], $kpiFilter),
        'bongkar_ontime'  => $this->firstRoute(['manager.bongkar.ontime'], $kpiFilter),
        'bongkar_delay'   => $this->firstRoute(['manager.bongkar.delay'], $kpiFilter),
    ];
}

    private function kpiLinksPasuruan(Request $request): array
    {
        $filter = array_filter(
            $request->only(['tgl_basis', 'date', 'month', 'year', 'area', 'dist_channel', 'pulau']),
            fn($v) => $v !== null && $v !== ''
        );

        // halaman list KPI dilayani PasuruanController, filter-nya tidak mengenal tgl_basis
        $kpiFilter = array_diff_key($filter, ['tgl_basis' => true]);

        return [
            'total'           => route('spvplanner.data.pasuruan', $filter),
            'in_transit'      => route('spvplanner.pasuruan.intransit', $filter),
            'gudang_ontime'   => route('pasuruan.gudang.ontime', $kpiFilter),
            'gudang_delay'    => route('pasuruan.gudang.delay', $kpiFilter),
            'customer_ontime' => route('pasuruan.tujuan.ontime', $kpiFilter),
            'customer_delay'  => route('pasuruan.tujuan.delay', $kpiFilter),
            'bongkar_ontime'  => route('pasuruan.bongkar.ontime', $kpiFilter),
            'bongkar_delay'   => route('pasuruan.bongkar.delay', $kpiFilter),
        ];
    }

    /* =====================================================
     * DASHBOARD PASURUAN (per grup, versi Monitoring)
     * ===================================================== */
    public function dashboardPasuruan(Request $request)
    {
        $base = DB::table('logistik_pengiriman_pasuruan');
        $this->applyFilterPasuruan($base, $request);

        $grouped = DB::table('logistik_pengiriman_pasuruan')
            ->whereIn('id', $this->groupIdsQueryPasuruan($base));

        $total_data = $this->countGroupsPasuruan($base);

        // ================= KPI =================
        $kpi = fn(string $k) => $this->countGroupsPasuruan(
            $this->applyFilterPasuruan($this->kpiQueryPasuruan($k), $request)
        );

        $gudang_ontime   = $kpi('gudang_ontime');
        $gudang_delay    = $kpi('gudang_delay');
        $customer_ontime = $kpi('tujuan_ontime');
        $customer_delay  = $kpi('tujuan_delay');
        $bongkar_ontime  = $kpi('bongkar_ontime');
        $bongkar_delay   = $kpi('bongkar_delay');

        // ================= ARMADA / PLANNER =================
        $planner_armada = $this->countGroupsPasuruan(
            (clone $base)->whereNotNull('rencana_kirim_pasuruan')->whereRaw("TRIM(rencana_kirim_pasuruan) <> ''")
                ->whereNotNull('tanggal_dpt_unit_pasuruan')->whereRaw("TRIM(tanggal_dpt_unit_pasuruan) <> ''")
        );

        $planner_belum_armada = $this->countGroupsPasuruan(
            (clone $base)->where(function ($q) {
                $q->whereNull('rencana_kirim_pasuruan')->orWhere('rencana_kirim_pasuruan', '')
                  ->orWhereNull('tanggal_dpt_unit_pasuruan')->orWhere('tanggal_dpt_unit_pasuruan', '');
            })
        );

        $planner_ontime = $this->countGroupsPasuruan(
            (clone $base)->whereNotNull('rencana_kirim_pasuruan')->whereNotNull('tanggal_dpt_unit_pasuruan')
                ->whereRaw('DATE(tanggal_dpt_unit_pasuruan) <= DATE(rencana_kirim_pasuruan)')
        );

        $planner_delay = $this->countGroupsPasuruan(
            (clone $base)->whereNotNull('rencana_kirim_pasuruan')->whereNotNull('tanggal_dpt_unit_pasuruan')
                ->whereRaw('DATE(tanggal_dpt_unit_pasuruan) > DATE(rencana_kirim_pasuruan)')
        );

        $total_in_transit = $this->countGroupsPasuruan(
            $this->applyFilterPasuruan($this->inTransitQueryPasuruan(), $request)
        );

        // ================= DROPDOWN =================
        $list_dist_channel = DB::table('logistik_pengiriman_pasuruan')
            ->select('dist_channel_pasuruan')
            ->whereNotNull('dist_channel_pasuruan')->where('dist_channel_pasuruan', '!=', '')
            ->distinct()->orderBy('dist_channel_pasuruan')->get();

        $list_area = DB::table('logistik_pengiriman_pasuruan')
            ->select('area_pasuruan as area')
            ->whereNotNull('area_pasuruan')->where('area_pasuruan', '!=', '')
            ->groupBy('area_pasuruan')->orderBy('area_pasuruan')->get();

        $kpiLinks = $this->kpiLinksPasuruan($request);

        // ================= NILAI MUATAN & BIAYA =================
        $grp = $this->grupBiayaQueryPasuruan($base);

        $tot = DB::query()->fromSub($grp, 'g')
            ->selectRaw('COALESCE(SUM(biaya),0) AS total_biaya, COALESCE(SUM(nilai),0) AS total_nilai')
            ->first();

        $totalBiayaKirim  = (float) $tot->total_biaya;
        $totalNilaiMuatan = (float) $tot->total_nilai;

        $summary_area = DB::query()->fromSub($grp, 'g')
            ->selectRaw('area_pasuruan, COUNT(*) AS total_shipment, SUM(biaya) AS total_biaya, SUM(nilai) AS total_muatan')
            ->whereNotNull('area_pasuruan')
            ->groupBy('area_pasuruan')
            ->orderByDesc('total_shipment')
            ->get();

        $summary_tujuan = DB::query()->fromSub($grp, 'g')
            ->selectRaw('tj AS tujuan_pasuruan, COUNT(*) AS total_shipment, SUM(biaya) AS total_biaya, SUM(nilai) AS total_muatan')
            ->where('tj', '<>', '')
            ->groupBy('tj')
            ->orderByDesc('total_shipment')
            ->get();

        $ekspedisi = (clone $grouped)
            ->select('kategori_ekspedisi_pasuruan', DB::raw('COUNT(*) as total'))
            ->whereNotNull('kategori_ekspedisi_pasuruan')
            ->groupBy('kategori_ekspedisi_pasuruan')
            ->get();

        $label = $ekspedisi->pluck('kategori_ekspedisi_pasuruan');
        $value = $ekspedisi->pluck('total');

        // ================= TABEL RINGKASAN (dipakai view planner) =================
        $top_ekspedisi_pemakaian = (clone $grouped)
            ->select('ekspedisi_pasuruan', DB::raw('COUNT(*) as total'))
            ->whereNotNull('ekspedisi_pasuruan')
            ->where('ekspedisi_pasuruan', '!=', '')
            ->groupBy('ekspedisi_pasuruan')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $trend_pengiriman_bulanan = (clone $grouped)
            ->select(
                DB::raw("DATE_FORMAT(tanggal_terima_po_pasuruan, '%Y-%m') as bulan"),
                DB::raw('COUNT(*) as total')
            )
            ->whereNotNull('tanggal_terima_po_pasuruan')
            ->groupBy('bulan')
            ->orderBy('bulan')
            ->get();

        $summary_area_ontime = (clone $grouped)
            ->select(
                'area_pasuruan',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN tanggal_tiba_pasuruan IS NOT NULL AND estimasi_tiba_pasuruan IS NOT NULL AND DATEDIFF(DATE(tanggal_tiba_pasuruan), DATE(estimasi_tiba_pasuruan)) <= 0 THEN 1 ELSE 0 END) as total_ontime"),
                DB::raw("SUM(CASE WHEN tanggal_tiba_pasuruan IS NOT NULL AND estimasi_tiba_pasuruan IS NOT NULL AND DATEDIFF(DATE(tanggal_tiba_pasuruan), DATE(estimasi_tiba_pasuruan)) > 0 THEN 1 ELSE 0 END) as total_delay")
            )
            ->whereNotNull('area_pasuruan')
            ->where('area_pasuruan', '!=', '')
            ->groupBy('area_pasuruan')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $summary_planner = (clone $grouped)
            ->select(
                'planner_pasuruan',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN rencana_kirim_pasuruan IS NOT NULL AND tanggal_dpt_unit_pasuruan IS NOT NULL AND DATE(tanggal_dpt_unit_pasuruan) <= DATE(rencana_kirim_pasuruan) THEN 1 ELSE 0 END) as total_ontime"),
                DB::raw("SUM(CASE WHEN rencana_kirim_pasuruan IS NOT NULL AND tanggal_dpt_unit_pasuruan IS NOT NULL AND DATE(tanggal_dpt_unit_pasuruan) > DATE(rencana_kirim_pasuruan) THEN 1 ELSE 0 END) as total_delay")
            )
            ->whereNotNull('planner_pasuruan')
            ->where('planner_pasuruan', '!=', '')
            ->groupBy('planner_pasuruan')
            ->orderByDesc('total')
            ->get();

        $summary_pic_monitoring = (clone $grouped)
            ->select(
                'pic_monitoring_pasuruan',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN tanggal_tiba_pasuruan IS NOT NULL AND estimasi_tiba_pasuruan IS NOT NULL AND DATEDIFF(DATE(tanggal_tiba_pasuruan), DATE(estimasi_tiba_pasuruan)) <= 0 THEN 1 ELSE 0 END) as total_ontime"),
                DB::raw("SUM(CASE WHEN tanggal_tiba_pasuruan IS NOT NULL AND estimasi_tiba_pasuruan IS NOT NULL AND DATEDIFF(DATE(tanggal_tiba_pasuruan), DATE(estimasi_tiba_pasuruan)) > 0 THEN 1 ELSE 0 END) as total_delay")
            )
            ->whereNotNull('pic_monitoring_pasuruan')
            ->where('pic_monitoring_pasuruan', '!=', '')
            ->groupBy('pic_monitoring_pasuruan')
            ->orderByDesc('total')
            ->get();

        // ================= RATIO =================
        $total_status = $planner_ontime + $planner_delay;
        $ontime_rate  = $total_status > 0 ? ($planner_ontime / $total_status) * 100 : 0;
        $delay_rate   = $total_status > 0 ? ($planner_delay / $total_status) * 100 : 0;

        $total_armada = $planner_armada + $planner_belum_armada;
        $armada_rate  = $total_armada > 0 ? ($planner_armada / $total_armada) * 100 : 0;
        $pending_rate = $total_armada > 0 ? ($planner_belum_armada / $total_armada) * 100 : 0;

        $summary_monitoring = [
            'tiba_ontime'    => $total_data > 0 ? ($customer_ontime / $total_data) * 100 : 0,
            'tiba_delay'     => $total_data > 0 ? ($customer_delay / $total_data) * 100 : 0,
            'bongkar_ontime' => $total_data > 0 ? ($bongkar_ontime / $total_data) * 100 : 0,
            'bongkar_delay'  => $total_data > 0 ? ($bongkar_delay / $total_data) * 100 : 0,
        ];

        return view('spvplanner.dashboard_pasuruan', compact(
            'total_data', 'kpiLinks',
            'gudang_ontime', 'gudang_delay',
            'customer_ontime', 'customer_delay',
            'bongkar_ontime', 'bongkar_delay',
            'summary_area', 'summary_tujuan',
            'totalNilaiMuatan', 'totalBiayaKirim',
            'ekspedisi', 'label', 'value',
            'top_ekspedisi_pemakaian', 'trend_pengiriman_bulanan',
            'summary_area_ontime', 'summary_planner', 'summary_pic_monitoring',
            'planner_ontime', 'planner_delay',
            'planner_armada', 'planner_belum_armada',
            'total_in_transit',
            'ontime_rate', 'delay_rate',
            'armada_rate', 'pending_rate',
            'summary_monitoring', 'list_dist_channel', 'list_area'
        ));
    }

    /* =====================================================
     * HALAMAN DATA PASURUAN (server-side DataTables)
     * ===================================================== */
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

        return view('spvplanner.data_logistik_pasuruan', compact('planners', 'areas'));
    }

    public function dataLogistikPasuruanAjax(Request $request)
    {
        $query = LogistikPengirimanPasuruan::query();
        $this->applyFilterPasuruan($query, $this->dashRequestPasuruan($request));

        // ---------- FILTER DARI DROPDOWN CUSTOM ----------
        if ($request->filled('date'))    $query->whereDate('tanggal_terima_po_pasuruan', $request->date);
        if ($request->filled('month'))   $query->whereMonth('tanggal_terima_po_pasuruan', $request->month);
        if ($request->filled('year'))    $query->whereYear('tanggal_terima_po_pasuruan', $request->year);
        if ($request->filled('planner')) $query->where('planner_pasuruan', $request->planner);
        if ($request->filled('area'))    $query->where('area_pasuruan', $request->area);

        // jumlah GRUP (shipment + tujuan), bukan jumlah DO
        $recordsTotal = $this->countGroupsPasuruan($query);

        // ---------- SEARCH ----------
        $searchValue = trim((string) $request->input('search.value'));
        if ($searchValue !== '') {
            $query->where(function ($q) use ($searchValue) {
                foreach ([
                    'no_shipment_pasuruan', 'tujuan_pasuruan', 'area_pasuruan', 'nama_driver_pasuruan',
                    'no_pol_pasuruan', 'planner_pasuruan', 'mobil_pasuruan', 'ekspedisi_pasuruan',
                    'pic_monitoring_pasuruan', 'customer_reference_pasuruan', 'sales_document_pasuruan',
                    'no_do_pasuruan', 'no_delivery_pasuruan',
                ] as $col) {
                    $q->orWhere($col, 'like', "%{$searchValue}%");
                }
            });
        }

        $recordsFiltered = $this->countGroupsPasuruan($query);

        // 1 baris wakil per shipment + tujuan
        $query = LogistikPengirimanPasuruan::query()
            ->whereIn('id', $this->groupIdsQueryPasuruan($query));

        // ---------- SORTING ----------
        $orderableColumns = [
            0  => 'tanggal_terima_po_pasuruan',
            1  => 'rencana_kirim_pasuruan',
            2  => 'transport_lead_time_pasuruan',
            3  => 'planner_pasuruan',
            4  => 'no_shipment_pasuruan',
            6  => 'dist_channel_pasuruan',
            7  => 'tujuan_pasuruan',
            8  => 'area_pasuruan',
            10 => 'mobil_pasuruan',
            11 => 'total_do_pasuruan',
            12 => 'nilai_muatan_pasuruan',
            13 => 'biaya_kirim_pasuruan',
            16 => 'ekspedisi_pasuruan',
            17 => 'tanggal_dpt_unit_pasuruan',
            23 => 'pic_monitoring_pasuruan',
            24 => 'nama_kapal_pasuruan',
        ];

        $orderColIndex = (int) $request->input('order.0.column', 0);
        $orderDir      = strtolower($request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $orderColumn   = $orderableColumns[$orderColIndex] ?? 'tanggal_terima_po_pasuruan';

        $query->orderBy($orderColumn, $orderDir)
              ->orderByRaw('CAST(no_shipment_pasuruan AS UNSIGNED) ASC')
              ->orderBy('no_shipment_pasuruan')
              ->orderBy('tujuan_pasuruan')
              ->orderBy('id');

        // ---------- PAGINATION ----------
        $start  = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 25);
        $length = $length > 0 ? min($length, 200) : $recordsFiltered;

        $rows = $query->skip($start)->take($length)->get();

        // penjumlahan per shipment + tujuan
        $groupAgg = $this->groupAggPasuruan($rows);

        $data = $rows->map(function ($r) use ($groupAgg) {
            $g = $groupAgg[$r->no_shipment_pasuruan . '|' . ($r->tujuan_pasuruan ?? '')] ?? null;

            if ($g && trim((string) $r->no_shipment_pasuruan) !== '') {
                $r->nilai_muatan_pasuruan = $g->sum_nilai;
                $r->biaya_kirim_pasuruan  = (float) $g->sum_kontribusi > 0 ? $g->sum_kontribusi : $g->sum_biaya;

                $q = (float) $g->sum_qty;
                $r->total_do_pasuruan = floor($q) == $q ? (int) $q : $q;
            }

            $nilai = (float) $r->nilai_muatan_pasuruan;
            $biaya = (float) $r->biaya_kirim_pasuruan;
            $cr    = $nilai > 0 ? ($biaya / $nilai) * 100 : 0;

            return [
                'tanggal_terima_po_fmt'      => $this->fmtDatePasuruan($r->tanggal_terima_po_pasuruan),
                'rencana_kirim_fmt'          => $this->fmtDatePasuruan($r->rencana_kirim_pasuruan),
                'transport_lead_time'        => $r->transport_lead_time_pasuruan,
                'planner'                    => $r->planner_pasuruan,
                'no_shipment'                => $r->no_shipment_pasuruan,
                'status_pengiriman_badge'    => $this->badgeStatusPengirimanPasuruan($r),
                'dist_channel_badge'         => $this->badgeDistChannelPasuruan($r->dist_channel_pasuruan),

                'tujuan_html'                => $r->no_shipment_pasuruan
                    ? '<a href="#" class="btn-tujuan" data-shipment="' . e($r->no_shipment_pasuruan)
                        . '" data-tujuan="' . e($r->tujuan_pasuruan) . '">▶ '
                        . e($r->tujuan_pasuruan)
                        . ($g && $g->jml_do > 1 ? ' <span class="badge gray">' . $g->jml_do . ' DO</span>' : '')
                        . '</a>'
                    : e($r->tujuan_pasuruan),

                'area'                       => $r->area_pasuruan,
                'ketersediaan_unit_badge'    => $this->badgeKetersediaanUnitPasuruan($r),
                'mobil'                      => $r->mobil_pasuruan,
                'total_do'                   => $r->total_do_pasuruan,
                'nilai_muatan_fmt'           => 'Rp ' . number_format($nilai, 0, ',', '.'),
                'biaya_kirim_fmt'            => 'Rp ' . number_format($biaya, 0, ',', '.'),
                'cr_fmt'                     => $this->formatCRPasuruan($cr),
                'kategori_ekspedisi_badge'   => $this->badgeKategoriEkspedisiPasuruan($r->kategori_ekspedisi_pasuruan),
                'ekspedisi'                  => $r->ekspedisi_pasuruan,
                'tanggal_dpt_unit_fmt'       => $this->fmtDatePasuruan($r->tanggal_dpt_unit_pasuruan),
                'lama_waktu_pencarian'       => $this->computeLamaPencarianPasuruan($r),
                'sla_dapat_mobil_badge'      => $this->badgeSlaDapatMobilPasuruan($r),
                'planning_loading_fmt'       => $this->fmtDatePasuruan($r->planning_loading_pasuruan),
                'tanggal_tiba_gudang_fmt'    => $this->fmtDatePasuruan($r->tanggal_tiba_gudang_pasuruan),
                'tanggal_keluar_gudang_fmt'  => $this->fmtDatePasuruan($r->tanggal_keluar_gudang_pasuruan),
                'pic_monitoring'             => $r->pic_monitoring_pasuruan,
                'nama_kapal'                 => $r->nama_kapal_pasuruan,
                'etd'                        => $r->etd_pasuruan,
                'eta'                        => $r->eta_pasuruan,
                'alert_badge'                => $this->badgeAlertPasuruan($r),
                'act_urutan_bongkar'         => $r->act_urutan_bongkar_pasuruan,
                'actual_delivery_quantity'   => $r->actual_delivery_quantity_pasuruan,
                'selisih_qty_badge'          => $this->badgeSelisihQtyPasuruan($r),
                'reason_selisih_quantity'    => $r->reason_selisih_quantity_pasuruan,
                'act_pgi_date_fmt'           => $this->fmtDatePasuruan($r->act_pgi_date_pasuruan),
                'atd_fmt'                    => $this->fmtDatePasuruan($r->atd_pasuruan),
                'ata_fmt'                    => $this->fmtDatePasuruan($r->ata_pasuruan),
                'estimasi_tiba_fmt'          => $this->fmtDatePasuruan($r->estimasi_tiba_pasuruan),
                'tanggal_tiba_fmt'           => $r->tanggal_tiba_pasuruan ? date('d-m-Y h:i A', strtotime($r->tanggal_tiba_pasuruan)) : '-',
                'lama_perjalanan'            => $r->lama_perjalanan_pasuruan ?? '-',
                'sla_tiba_badge'             => $this->badgeSlaTibaPasuruan($r),
                'tanggal_bongkar_fmt'        => $r->tanggal_bongkar_pasuruan ? date('d-m-Y h:i A', strtotime($r->tanggal_bongkar_pasuruan)) : '-',
                'status_bongkar_badge'       => $this->badgeStatusBongkarPasuruan($r),
                'overstay_badge'             => $this->badgeOverstayPasuruan($r),
                'sla_bongkar_badge'          => $this->badgeSlaBongkarPasuruan($r),
                'reason_tiba'                => $r->reason_waktu_tiba_pasuruan,
                'reason_bongkar'             => $r->reason_waktu_bongkar_pasuruan,
                'status_akhir_badge'         => $this->badgeStatusAkhirPasuruan($r),
                'status_alert_badge'         => $this->badgeStatusAlertPasuruan($r),
                'remarks'                    => $r->remarks_pasuruan,
                'route'                      => $r->route_pasuruan,
                'shipping_point'             => $r->route_pasuruan ? explode('-', trim($r->route_pasuruan))[0] : '-',
                'pulau'                      => $r->pulau_pasuruan,
                'via_kirim'                  => $r->via_kirim_pasuruan,
                'biaya_kuli'                 => $r->biaya_kuli_pasuruan,
                'total_biaya_kuli'           => $r->total_biaya_kuli_pasuruan,
            ];
        });

        return response()->json([
            'draw'            => (int) $request->input('draw', 1),
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    /** Rincian DO per shipment + tujuan (dipanggil saat klik tujuan) */
    public function dataLogistikDetailPasuruan(Request $request)
    {
        $request->validate([
            'no_shipment' => 'required|string',
            'tujuan'      => 'nullable|string',
        ]);

        $q = LogistikPengirimanPasuruan::query()
            ->where('no_shipment_pasuruan', $request->no_shipment);

        $tujuan = (string) $request->tujuan;
        $tujuan !== ''
            ? $q->where('tujuan_pasuruan', $tujuan)
            : $q->where(fn($x) => $x->whereNull('tujuan_pasuruan')->orWhere('tujuan_pasuruan', ''));

        $fmtDate = fn($v) => $v ? date('d-m-Y', strtotime($v)) : '-';
        $fmtDes  = fn($v) => ($v === null || $v === '' || !is_numeric($v))
            ? '-' : number_format((float) $v, 2, ',', '.');

        $data = $q->orderBy('sales_document_pasuruan')->orderBy('id')->get()->map(fn($r) => [
            'sales_document'       => $r->sales_document_pasuruan ?: '-',
            'customer_reference'   => $r->customer_reference_pasuruan ?: '-',
            'contract_end_date'    => $fmtDate($r->contract_end_date_pasuruan),
            'no_do'                => $r->no_do_pasuruan ?: '-',
            'no_delivery'          => $r->no_delivery_pasuruan ?: '-',
            'qty'                  => $r->total_do_pasuruan,
            'nilai_muatan'         => 'Rp ' . number_format((float) $r->nilai_muatan_pasuruan, 0, ',', '.'),
            'kirim_kontribusi'     => $r->kirim_kontribusi_pasuruan !== null
                ? 'Rp ' . number_format((float) $r->kirim_kontribusi_pasuruan, 0, ',', '.') : '-',
            'kirim_kontribusi_raw' => (float) $r->kirim_kontribusi_pasuruan,
            'total_kubik'          => $fmtDes($r->total_kubik_pasuruan),
            'total_tonase'         => $fmtDes($r->total_tonase_pasuruan),
        ]);

        return response()->json(['data' => $data]);
    }

    /* ===================== HELPER ===================== */

    /** jumlah per shipment + tujuan untuk baris yang tampil di halaman ini */
    private function groupAggPasuruan($rows): array
    {
        $n = fn($c) => "COALESCE(CAST(NULLIF(TRIM({$c}), '') AS DECIMAL(18,4)), 0)";

        $shipments = $rows->pluck('no_shipment_pasuruan')
            ->filter(fn($v) => trim((string) $v) !== '')->unique()->values();

        if ($shipments->isEmpty()) return [];

        return LogistikPengirimanPasuruan::query()
            ->whereIn('no_shipment_pasuruan', $shipments)
            ->select(
                'no_shipment_pasuruan',
                DB::raw("COALESCE(tujuan_pasuruan,'') as tujuan_key"),
                DB::raw('COUNT(*) as jml_do'),
                DB::raw('SUM(' . $n('nilai_muatan_pasuruan') . ') as sum_nilai'),
                DB::raw('SUM(' . $n('total_do_pasuruan') . ') as sum_qty'),
                DB::raw('SUM(' . $n('kirim_kontribusi_pasuruan') . ') as sum_kontribusi'),
                DB::raw('IF(SUM(' . $n('kirim_kontribusi_pasuruan') . ') > 0,
                            SUM(' . $n('biaya_kirim_pasuruan') . '),
                            MAX(' . $n('biaya_kirim_pasuruan') . ')) as sum_biaya')
            )
            ->groupBy('no_shipment_pasuruan', 'tujuan_key')
            ->get()
            ->keyBy(fn($g) => $g->no_shipment_pasuruan . '|' . $g->tujuan_key)
            ->all();
    }

    private function fmtDatePasuruan($value, string $format = 'd-m-Y')
    {
        if (empty($value) || $value === 'mm/dd/yyyy') {
            return '-';
        }
        return date($format, strtotime($value));
    }

    private function formatCRPasuruan(float $cr): string
    {
        return $cr > 0
            ? '<span class="cr-value">' . number_format($cr, 4, ',', '.') . '%</span>'
            : '<span class="text-muted">0,0000%</span>';
    }

    private function badgeStatusPengirimanPasuruan($r): string
    {
        $dpt           = $r->tanggal_dpt_unit_pasuruan;
        $tibaGudang    = $r->tanggal_tiba_gudang_pasuruan;
        $keluarGudang  = $r->tanggal_keluar_gudang_pasuruan;
        $tibaTujuan    = $r->tanggal_tiba_pasuruan;
        $bongkarTujuan = $r->tanggal_bongkar_pasuruan;

        if (empty($dpt)) {
            $status = 'MENCARI UNIT'; $badge = 'red';
        } elseif (empty($tibaGudang)) {
            $status = 'PERJALANAN KE GUDANG'; $badge = 'orange';
        } elseif (!empty($tibaGudang) && empty($keluarGudang)) {
            $status = 'DI GUDANG'; $badge = 'cream';
        } elseif (!empty($keluarGudang) && empty($tibaTujuan)) {
            $status = 'PERJALANAN KE TUJUAN'; $badge = 'yellow';
        } elseif (!empty($tibaTujuan) && empty($bongkarTujuan)) {
            $status = 'TIBA DI TUJUAN'; $badge = 'blue';
        } elseif (!empty($tibaTujuan) && !empty($bongkarTujuan)) {
            $status = 'SUDAH SELESAI'; $badge = 'green';
        } else {
            $status = '-'; $badge = 'gray';
        }

        return '<span class="badge ' . $badge . '">' . e($status) . '</span>';
    }

    private function badgeDistChannelPasuruan($channel): string
    {
        $channel = trim((string) $channel);

        $classes = [
            'badge-green', 'badge-blue', 'badge-orange', 'badge-red',
            'badge-purple', 'badge-pink', 'badge-cyan', 'badge-yellow',
        ];

        $badgeClass = $channel
            ? $classes[abs(crc32($channel)) % count($classes)]
            : 'badge-default';

        return '<span class="badge ' . $badgeClass . '">' . e($channel ?: '-') . '</span>';
    }

    private function badgeKetersediaanUnitPasuruan($r): string
    {
        if (!empty($r->tanggal_dpt_unit_pasuruan)) {
            return '<span class="badge-status status-sudah">Sudah Dapat Unit</span>';
        }
        return '<span class="badge-status status-belum">Belum Dapat Unit</span>';
    }

    private function badgeKategoriEkspedisiPasuruan($kategori): string
    {
        $kategori = $kategori ?? '-';

        if (empty($kategori) || $kategori === '-') {
            return '<span class="badge gray">-</span>';
        }
        if (strtolower($kategori) === 'kontrak') {
            return '<span class="badge yellow">Kontrak</span>';
        }
        if (strtolower($kategori) === 'oncall') {
            return '<span class="badge blue">Oncall</span>';
        }
        return '<span class="badge orange">' . e($kategori) . '</span>';
    }

    private function computeLamaPencarianPasuruan($r): string
    {
        if (empty($r->rencana_kirim_pasuruan) || empty($r->tanggal_dpt_unit_pasuruan)) {
            return '-';
        }

        $rencana   = strtotime(date('Y-m-d', strtotime($r->rencana_kirim_pasuruan)));
        $dapatUnit = strtotime(date('Y-m-d', strtotime($r->tanggal_dpt_unit_pasuruan)));
        $selisih   = floor(($dapatUnit - $rencana) / 86400);

        return ($selisih <= 0) ? 'H+0' : 'H+' . $selisih;
    }

    private function badgeSlaDapatMobilPasuruan($r): string
    {
        if (empty($r->rencana_kirim_pasuruan) || empty($r->tanggal_dpt_unit_pasuruan)) {
            return '<span class="badge gray">-</span>';
        }

        $rencana   = strtotime(date('Y-m-d', strtotime($r->rencana_kirim_pasuruan)));
        $dapatUnit = strtotime(date('Y-m-d', strtotime($r->tanggal_dpt_unit_pasuruan)));
        $selisih   = floor(($dapatUnit - $rencana) / 86400);

        return ($selisih <= 0)
            ? '<span class="badge green">On Time</span>'
            : '<span class="badge red">Delay</span>';
    }

    private function badgeAlertPasuruan($r): string
    {
        if (!empty($r->tanggal_tiba_pasuruan)) {
            return '<span class="badge badge-success">✓ Tiba</span>';
        }
        if (empty($r->estimasi_tiba_pasuruan)) {
            return '<span class="badge badge-secondary">-</span>';
        }

        $estimasi = strtotime(date('Y-m-d', strtotime($r->estimasi_tiba_pasuruan)));
        $today    = strtotime(date('Y-m-d'));
        $sisaHari = floor(($estimasi - $today) / 86400);

        if ($sisaHari < 0) return '<span class="badge badge-danger">OVERDUE</span>';
        if ($sisaHari == 0) return '<span class="badge badge-danger">H-0</span>';
        if ($sisaHari == 1) return '<span class="badge badge-danger">H-1</span>';
        if ($sisaHari == 2 || $sisaHari == 3) return '<span class="badge badge-warning">H-' . $sisaHari . '</span>';
        if ($sisaHari <= 7) return '<span class="badge badge-info">H-' . $sisaHari . '</span>';

        return '<span class="badge badge-success">ON TRACK</span>';
    }

    private function badgeSelisihQtyPasuruan($r): string
    {
        $totalDo    = is_numeric($r->total_do_pasuruan) ? (float) $r->total_do_pasuruan : 0;
        $actualRaw  = $r->actual_delivery_quantity_pasuruan;
        $belumDiisi = ($actualRaw === null || $actualRaw === '' || (float) $actualRaw == 0);

        if ($belumDiisi) {
            return '<span class="badge badge-secondary">-</span>';
        }

        $selisih = $totalDo - (float) $actualRaw;

        if ($selisih == 0) {
            return '<span class="badge badge-success">Sesuai (0)</span>';
        }
        if ($selisih > 0) {
            return '<span class="badge badge-danger">Berkurang ' . number_format($selisih, 0, ',', '.') . '</span>';
        }
        return '<span class="badge badge-warning">Lebih ' . number_format(abs($selisih), 0, ',', '.') . '</span>';
    }

    private function badgeSlaTibaPasuruan($r): string
    {
        if (empty($r->tanggal_tiba_pasuruan) || empty($r->estimasi_tiba_pasuruan)) {
            return '<span class="badge gray">-</span>';
        }

        $tiba     = strtotime(date('Y-m-d', strtotime($r->tanggal_tiba_pasuruan)));
        $estimasi = strtotime(date('Y-m-d', strtotime($r->estimasi_tiba_pasuruan)));

        return ($tiba <= $estimasi)
            ? '<span class="badge green">On Time</span>'
            : '<span class="badge red">Delay</span>';
    }

    private function badgeStatusBongkarPasuruan($r): string
    {
        if (!empty($r->tanggal_bongkar_pasuruan)) {
            return '<span class="badge status-bongkar green">Telah Bongkar</span>';
        }

        if (!empty($r->tanggal_tiba_pasuruan)) {
            $tanggalTiba = strtotime(date('Y-m-d', strtotime($r->tanggal_tiba_pasuruan)));
            $hariIni     = strtotime(date('Y-m-d'));
            $selisihHari = max(0, floor(($hariIni - $tanggalTiba) / 86400));
            $class       = ($selisihHari == 0) ? 'orange' : 'red';

            return '<span class="badge status-bongkar ' . $class . '">H+' . $selisihHari . '</span>';
        }

        return '<span class="badge status-bongkar gray">-</span>';
    }

    private function badgeOverstayPasuruan($r): string
    {
        if (empty($r->tanggal_tiba_pasuruan) || empty($r->tanggal_bongkar_pasuruan)) {
            return '<span class="badge gray">-</span>';
        }

        $tiba     = strtotime(date('Y-m-d', strtotime($r->tanggal_tiba_pasuruan)));
        $bongkar  = strtotime(date('Y-m-d', strtotime($r->tanggal_bongkar_pasuruan)));
        $overstay = max(0, floor(($bongkar - $tiba) / 86400));

        if ($overstay == 0) {
            return '<span class="badge green">0 Hari</span>';
        }
        return '<span class="badge red">H+' . $overstay . ' Hari</span>';
    }

    private function badgeSlaBongkarPasuruan($r): string
    {
        if (empty($r->tanggal_tiba_pasuruan) || empty($r->tanggal_bongkar_pasuruan)) {
            return '<span class="badge gray">-</span>';
        }

        $tiba    = strtotime(date('Y-m-d', strtotime($r->tanggal_tiba_pasuruan)));
        $bongkar = strtotime(date('Y-m-d', strtotime($r->tanggal_bongkar_pasuruan)));
        $selisih = floor(($bongkar - $tiba) / 86400);

        return ($selisih <= 0)
            ? '<span class="badge green">On Time</span>'
            : '<span class="badge red">Delay</span>';
    }

    private function badgeStatusAkhirPasuruan($r): string
    {
        $slaTiba    = strtoupper(trim($r->sla_tiba_pasuruan ?? ''));
        $slaBongkar = strtoupper(trim($r->sla_bongkar_pasuruan ?? ''));

        if (empty($r->tanggal_tiba_pasuruan)) {
            return '<span class="status-badge status-transit">🚚 Dalam Perjalanan</span>';
        }
        if (empty($r->tanggal_bongkar_pasuruan)) {
            return '<span class="status-badge status-unloading">📦 Sudah Tiba <br> Dalam Pembongkaran</span>';
        }
        if ($slaTiba === 'ON TIME' && $slaBongkar === 'ON TIME') {
            return '<span class="status-badge status-ontime">✅ Pengiriman On Time</span>';
        }
        return '<span class="status-badge status-delay">🚨 Pengiriman Delay</span>';
    }

    private function badgeStatusAlertPasuruan($r): string
    {
        $slaTiba    = strtoupper(trim($r->sla_tiba_pasuruan ?? ''));
        $slaBongkar = strtoupper(trim($r->sla_bongkar_pasuruan ?? ''));

        if ($slaTiba === 'ON TIME' && $slaBongkar === 'ON TIME') {
            return '<span class="badge badge-success">🟢 Delivered Ontime</span>';
        }
        if ($slaTiba === 'DELAY' && $slaBongkar === 'ON TIME') {
            return '<span class="badge badge-warning">🚚 Delay Perjalanan</span>';
        }
        if ($slaTiba === 'ON TIME' && $slaBongkar === 'DELAY') {
            return '<span class="badge badge-info">📦 Delay Pembongkaran</span>';
        }
        if ($slaTiba === 'DELAY' && $slaBongkar === 'DELAY') {
            return '<span class="badge badge-danger">🔥 Delivered Delay</span>';
        }
        return '<span class="badge badge-secondary">⏳ Belum Selesai</span>';
    }

    /**
     * VERSI LAMA (per DO, tanpa grup). Tidak dipakai route manapun sejak halaman
     * disamakan dengan SpvMonitoringController; disimpan supaya bisa dibalikin.
     * Untuk memakainya lagi: ganti nama jadi dataLogistikPasuruanAjax dan hapus yang baru.
     */
    public function dataLogistikPasuruanAjaxLama(Request $request)
    {
        $draw   = (int) $request->input('draw', 1);
        $start  = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $searchValue = trim((string) $request->input('search.value', ''));

        $baseQuery = LogistikPengirimanPasuruan::query();
        $this->applyFilterPasuruan($baseQuery, $this->dashRequestPasuruan($request));
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

        // CR hanya untuk shipment yang tampil di halaman ini
        $shipmentNumbers = $rows->pluck('no_shipment_pasuruan')->filter()->unique()->values();

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

    /* =====================================================
     * HAPUS SESUAI FILTER
     * ===================================================== */

    /**
     * Halaman Data Logistik (full_data_logistik) — filter SAMA dengan
     * LogistikController: area[] multi, tgl_basis, date_from/date_to, month, year, planner.
     * Halaman data_planner lama (planner_filter / area_filter / create_tgl_filter) tetap didukung.
     */
    public function deleteFiltered(Request $request)
    {
        $baseQuery = LogistikPengiriman::query();

        // ---- filter halaman data_planner (lama) ----
        if ($request->filled('planner_filter')) {
            $baseQuery->where('planner', $request->input('planner_filter'));
        }
        if ($request->filled('create_tgl_filter')) {
            $baseQuery->whereDate('create_tgl', $request->input('create_tgl_filter'));
        }
        if ($request->filled('area_filter')) {
            $baseQuery->where('area', $request->input('area_filter'));
        }

        // ---- filter halaman full_data_logistik (baru, ikut LogistikController) ----
        $col = match ($request->input('tgl_basis')) {
            'rencana_kirim' => 'rencana_kirim',
            'create_tgl'    => 'create_tgl',
            default         => 'tanggal_naik_logistik',
        };

        $from = $request->input('date_from');
        $to   = $request->input('date_to');
        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }
        if ($from) $baseQuery->whereDate($col, '>=', $from);
        if ($to)   $baseQuery->whereDate($col, '<=', $to);
        if ($request->filled('date'))  $baseQuery->whereDate($col, $request->date);
        if ($request->filled('month')) $baseQuery->whereMonth($col, $request->month);
        if ($request->filled('year'))  $baseQuery->whereYear($col, $request->year);

        if ($request->filled('planner')) {
            $baseQuery->where('planner', $request->planner);
        }

        $areas = array_values(array_filter((array) $request->input('area', []), 'strlen'));
        if (!empty($areas)) {
            $baseQuery->whereIn('area', $areas);
        }

        $searchValue = trim((string) $request->input('search_value', ''));
        $this->applyGlobalSearch($baseQuery, $searchValue, 'logistik_pengiriman');

        // GUARD: tanpa filter/search sama sekali -> tolak (cegah hapus SEMUA data)
        $hasCondition = $request->filled('planner_filter')
            || $request->filled('create_tgl_filter')
            || $request->filled('area_filter')
            || $request->filled('planner')
            || $request->filled('date')
            || $request->filled('month')
            || $request->filled('year')
            || $from || $to
            || !empty($areas)
            || $searchValue !== '';

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
     * Bangun 1 baris untuk halaman Data Pasuruan (versi lama, dipakai dataLogistikPasuruanAjaxLama).
     * Urutan array HARUS sinkron dengan <th> di spvplanner/data_logistik_pasuruan.blade.php versi lama (52 kolom).
     */
    private function renderPasuruanRow($r, array $crMap)
    {
        $fmtDate   = fn($v, $fmt = 'd-m-Y') => $v ? date($fmt, strtotime($v)) : '-';
        $fmtRupiah = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');

        // ================= UPDATE POSISI MOBIL =================
        $dpt           = $r->tanggal_dpt_unit_pasuruan;
        $tibaGudang    = $r->tanggal_tiba_gudang_pasuruan;
        $keluarGudang  = $r->tanggal_keluar_gudang_pasuruan;
        $tibaTujuan    = $r->tanggal_tiba_pasuruan;
        $bongkarTujuan = $r->tanggal_bongkar_pasuruan;

        if (empty($dpt)) {
            $status = 'MENCARI UNIT';          $badge = 'red';
        } elseif (empty($tibaGudang)) {
            $status = 'PERJALANAN KE GUDANG';  $badge = 'orange';
        } elseif (!empty($tibaGudang) && empty($keluarGudang)) {
            $status = 'DI GUDANG';             $badge = 'blue';
        } elseif (!empty($keluarGudang) && empty($tibaTujuan)) {
            $status = 'PERJALANAN KE TUJUAN';  $badge = 'yellow';
        } elseif (!empty($tibaTujuan) && empty($bongkarTujuan)) {
            $status = 'TIBA DI TUJUAN';        $badge = 'success';
        } elseif (!empty($tibaTujuan) && !empty($bongkarTujuan)) {
            $status = 'SUDAH SELESAI';         $badge = 'green';
        } else {
            $status = '-';                     $badge = 'gray';
        }
        $posisiHtml = '<span class="badge ' . $badge . '">' . e($status) . '</span>';

        // ================= DIST CHANNEL =================
        $channel = trim($r->dist_channel_pasuruan ?? '');
        $channelClasses = ['badge-green', 'badge-blue', 'badge-orange', 'badge-red', 'badge-purple', 'badge-pink', 'badge-cyan', 'badge-yellow'];
        $channelClass = $channel ? $channelClasses[abs(crc32($channel)) % count($channelClasses)] : 'badge-default';
        $channelHtml = '<span class="badge ' . $channelClass . '">' . e($channel ?: '-') . '</span>';

        // ================= KETERSEDIAAN UNIT =================
        $ketersediaanHtml = !empty($r->tanggal_dpt_unit_pasuruan)
            ? '<span class="badge-status status-sudah">Sudah Dapat Unit</span>'
            : '<span class="badge-status status-belum">Belum Dapat Unit</span>';

        // ================= CR =================
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

        // ================= LAMA PENCARIAN & SLA DAPAT MOBIL =================
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
                $alert = 'OVERDUE';        $alertClass = 'badge-danger';
            } elseif ($sisaHari <= 1) {
                $alert = 'H-' . $sisaHari; $alertClass = 'badge-danger';
            } elseif ($sisaHari <= 3) {
                $alert = 'H-' . $sisaHari; $alertClass = 'badge-warning';
            } elseif ($sisaHari <= 7) {
                $alert = 'H-' . $sisaHari; $alertClass = 'badge-info';
            } else {
                $alert = 'ON TRACK';       $alertClass = 'badge-success';
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
            $selisihQty = $totalDo - (float) $actualRaw;

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

        // ================= OVERSTAY & SLA BONGKAR =================
        $overstayHtml = '<span class="badge gray">-</span>';
        $slaBongkarComputedHtml = '<span class="badge gray">-</span>';
        if (!empty($r->tanggal_tiba_pasuruan) && !empty($r->tanggal_bongkar_pasuruan)) {
            $tiba = strtotime(date('Y-m-d', strtotime($r->tanggal_tiba_pasuruan)));
            $bongkar = strtotime(date('Y-m-d', strtotime($r->tanggal_bongkar_pasuruan)));

            $overstay = max(0, floor(($bongkar - $tiba) / 86400));
            $overstayText = $overstay == 0 ? '0 Hari' : "H+{$overstay} Hari";
            $overstayHtml = '<span class="badge ' . ($overstay == 0 ? 'green' : 'red') . '">' . e($overstayText) . '</span>';

            $selisih = floor(($bongkar - $tiba) / 86400);
            $slaBongkarComputedHtml = $selisih <= 0
                ? '<span class="badge green">On Time</span>'
                : '<span class="badge red">Delay</span>';
        }

        // ================= STATUS AKHIR & STATUS ALERT =================
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

    /* =====================================================
     * FULL DASHBOARD (per grup, SAMA dengan SpvMonitoringController::fullDashboard)
     * ===================================================== */
    public function Fulldashboard(Request $request)
    {
        // semua DO, sudah terfilter
        $base = DB::table('logistik_pengiriman');
        $this->applyFilter($base, $request);

        // 1 baris wakil per shipment + tujuan (untuk tabel agregat)
        $grouped = DB::table('logistik_pengiriman')
            ->whereIn('id', $this->groupIdsQuery($base));

        $total_data = $this->countGroups($base);

        // ================= GUDANG =================
        $gudang_ontime = $this->countGroups(
            (clone $base)->where(function ($q) {
                $q->whereNotNull('tanggal_tiba_gudang')
                  ->orWhereNotNull('tanggal_tiba_gudang_2')
                  ->orWhereNotNull('tanggal_tiba_gudang_3');
            })
        );

        $gudang_delay = $this->countGroups(
            (clone $base)->where(function ($q) {
                $q->whereNull('rencana_kirim')->orWhere('rencana_kirim', '')
                  ->orWhereNull('tanggal_dpt_unit')->orWhere('tanggal_dpt_unit', '');
            })
        );

        $total_belum_tiba_gudang = $this->countGroups(
            $this->applyFilter($this->belumTibaGudangQuery(), $request)
        );

        // ================= CUSTOMER =================
        $customer_ontime = $this->countGroups(
            (clone $base)->whereNotNull('tanggal_tiba')->whereNotNull('estimasi_tiba')
                ->whereRaw("DATEDIFF(DATE(tanggal_tiba), DATE(estimasi_tiba)) <= 0")
        );

        $customer_delay = $this->countGroups(
            (clone $base)->whereNotNull('tanggal_tiba')->whereNotNull('estimasi_tiba')
                ->whereRaw("DATEDIFF(DATE(tanggal_tiba), DATE(estimasi_tiba)) > 0")
        );

        $tiba_belum_diisi_delay = $this->countGroups(
            (clone $base)->whereRaw("NULLIF(TRIM(tanggal_tiba), '') IS NULL")
                ->whereNotNull('estimasi_tiba')
                ->whereRaw("DATE(estimasi_tiba) < CURDATE()")
        );

        // ================= BONGKAR =================
        $bongkar_ontime = $this->countGroups(
            (clone $base)->whereNotNull('tanggal_bongkar')
                ->where('tanggal_bongkar', '!=', '1899-12-31 00:00:00')
                ->where(fn($q) => $q->whereNull('overstay_days')->orWhere('overstay_days', 0))
        );

        $bongkar_delay = $this->countGroups(
            (clone $base)->whereNotNull('tanggal_bongkar')
                ->where('tanggal_bongkar', '!=', '1899-12-31 00:00:00')
                ->where('overstay_days', '>', 0)
        );

        // ================= ARMADA / PLANNER =================
        $planner_armada = $this->countGroups(
            (clone $base)->whereNotNull('rencana_kirim')->whereRaw("TRIM(rencana_kirim) <> ''")
                ->whereNotNull('tanggal_dpt_unit')->whereRaw("TRIM(tanggal_dpt_unit) <> ''")
        );

        $planner_belum_armada = $this->countGroups(
            (clone $base)->where(function ($q) {
                $q->whereNull('rencana_kirim')->orWhere('rencana_kirim', '')
                  ->orWhereNull('tanggal_dpt_unit')->orWhere('tanggal_dpt_unit', '');
            })
        );

        $planner_ontime = $this->countGroups(
            (clone $base)->whereNotNull('rencana_kirim')->whereNotNull('tanggal_dpt_unit')
                ->whereRaw('DATE(tanggal_dpt_unit) <= DATE(rencana_kirim)')
        );

        $planner_delay = $this->countGroups(
            (clone $base)->whereNotNull('rencana_kirim')->whereNotNull('tanggal_dpt_unit')
                ->whereRaw('DATE(tanggal_dpt_unit) > DATE(rencana_kirim)')
        );

        // ================= IN TRANSIT =================
        $total_in_transit = $this->countGroups(
            $this->applyFilter($this->inTransitQuery(), $request)
        );

        // ================= DROPDOWN =================
        $list_dist_channel = DB::table('logistik_pengiriman')
            ->select('dist_channel')->whereNotNull('dist_channel')
            ->distinct()->orderBy('dist_channel')->get();

        $list_area = $this->getArea();

        // ================= NILAI MUATAN & BIAYA =================
        $grp = $this->grupBiayaQuery($base);

        $tot = DB::query()->fromSub($grp, 'g')
            ->selectRaw('COALESCE(SUM(biaya),0) AS total_biaya, COALESCE(SUM(nilai),0) AS total_nilai')
            ->first();

        $totalBiayaKirim  = (float) $tot->total_biaya;
        $totalNilaiMuatan = (float) $tot->total_nilai;

        $summary_area = DB::query()->fromSub($grp, 'g')
            ->selectRaw('area, COUNT(*) AS total_shipment, COALESCE(SUM(biaya),0) AS total_biaya, COALESCE(SUM(nilai),0) AS total_muatan')
            ->whereNotNull('area')
            ->groupBy('area')
            ->orderByDesc('total_shipment')
            ->get();

        $summary_tujuan = DB::query()->fromSub($grp, 'g')
            ->selectRaw('tj AS tujuan, COUNT(*) AS total_shipment, COALESCE(SUM(biaya),0) AS total_biaya, COALESCE(SUM(nilai),0) AS total_muatan')
            ->where('tj', '<>', '')
            ->groupBy('tj')
            ->orderByDesc('total_shipment')
            ->get();

        // ================= EKSPEDISI (top 10, per grup) =================
        $ekspedisi = DB::query()->fromSub($grp, 'g')
            ->selectRaw('ekpedisi, COUNT(*) AS total, COALESCE(SUM(biaya),0) AS total_biaya')
            ->whereNotNull('ekpedisi')
            ->where('ekpedisi', '!=', '')
            ->groupBy('ekpedisi')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $top_ekspedisi_pemakaian = $ekspedisi->take(5)->map(fn($e) => (object) [
            'ekpedisi' => $e->ekpedisi,
            'total'    => $e->total,
        ])->values();

        $label = $ekspedisi->pluck('ekpedisi');
        $value = $ekspedisi->pluck('total_biaya');

        // ================= TREND BULANAN =================
        $trend_pengiriman_bulanan = (clone $grouped)
            ->select(DB::raw("DATE_FORMAT(tanggal_naik_logistik, '%Y-%m') as bulan"), DB::raw('COUNT(*) as total'))
            ->whereNotNull('tanggal_naik_logistik')
            ->groupBy('bulan')
            ->orderBy('bulan')
            ->get();

        // ================= SUMMARY AREA ONTIME =================
        $summary_area_ontime = (clone $grouped)
            ->select(
                'area',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN tanggal_tiba IS NOT NULL AND estimasi_tiba IS NOT NULL AND DATEDIFF(DATE(tanggal_tiba), DATE(estimasi_tiba)) <= 0 THEN 1 ELSE 0 END) as total_ontime"),
                DB::raw("SUM(CASE WHEN tanggal_tiba IS NOT NULL AND estimasi_tiba IS NOT NULL AND DATEDIFF(DATE(tanggal_tiba), DATE(estimasi_tiba)) > 0 THEN 1 ELSE 0 END) as total_delay")
            )
            ->whereNotNull('area')->where('area', '!=', '')
            ->groupBy('area')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // ================= SUMMARY PULAU (tanpa filter pulau) =================
        $basePulau = DB::table('logistik_pengiriman')
            ->when($request->filled('bulan'), fn($q) => $q->whereMonth('tanggal_naik_logistik', $request->bulan))
            ->when($request->filled('tahun'), fn($q) => $q->whereYear('tanggal_naik_logistik', $request->tahun))
            ->when($request->filled('area'), fn($q) => $q->where('area', $request->area))
            ->when($request->filled('dist_channel'), fn($q) => $q->where('dist_channel', $request->dist_channel));

        $summary_pulau = DB::query()->fromSub($this->grupBiayaQuery($basePulau), 'g')
            ->selectRaw('pulau, COUNT(*) AS total_shipment, SUM(nilai) AS total_muatan, SUM(biaya) AS total_biaya')
            ->whereNotNull('pulau')
            ->whereRaw("TRIM(pulau) <> ''")
            ->groupBy('pulau')
            ->orderByDesc('total_muatan')
            ->get();

        $label_pulau        = $summary_pulau->pluck('pulau');
        $value_muatan_pulau = $summary_pulau->pluck('total_muatan');
        $value_biaya_pulau  = $summary_pulau->pluck('total_biaya');

        // ================= SUMMARY PLANNER =================
        $greatestTiba = "GREATEST(
            COALESCE(tanggal_tiba_gudang, '1900-01-01'),
            COALESCE(tanggal_tiba_gudang_2, '1900-01-01'),
            COALESCE(tanggal_tiba_gudang_3, '1900-01-01')
        )";
        $lastTiba = "CASE
            WHEN tanggal_tiba_gudang_3 IS NOT NULL AND tanggal_tiba_gudang_3 = {$greatestTiba} THEN tanggal_tiba_gudang_3
            WHEN tanggal_tiba_gudang_2 IS NOT NULL AND tanggal_tiba_gudang_2 = {$greatestTiba} THEN tanggal_tiba_gudang_2
            WHEN tanggal_tiba_gudang IS NOT NULL THEN tanggal_tiba_gudang
            ELSE NULL END";
        $lastPlanning = "CASE
            WHEN tanggal_tiba_gudang_3 IS NOT NULL AND tanggal_tiba_gudang_3 = {$greatestTiba} THEN planning_loading_3
            WHEN tanggal_tiba_gudang_2 IS NOT NULL AND tanggal_tiba_gudang_2 = {$greatestTiba} THEN planning_loading_2
            WHEN tanggal_tiba_gudang IS NOT NULL THEN planning_loading
            ELSE NULL END";
        $belumIsi = "NULLIF(TRIM(tanggal_naik_logistik), '') IS NULL";

        $summary_planner = (clone $grouped)
            ->select(
                'planner',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN {$belumIsi} THEN 1 ELSE 0 END) as total_belum_shipment"),
                DB::raw("SUM(CASE WHEN ({$lastPlanning}) IS NOT NULL AND ({$lastTiba}) IS NOT NULL AND DATE({$lastTiba}) <= DATE({$lastPlanning}) THEN 1 ELSE 0 END) as total_ontime"),
                DB::raw("SUM(CASE WHEN ({$lastPlanning}) IS NOT NULL AND ({$lastTiba}) IS NOT NULL AND DATE({$lastTiba}) > DATE({$lastPlanning}) THEN 1 ELSE 0 END) as total_delay")
            )
            ->whereNotNull('planner')->where('planner', '!=', '')
            ->groupBy('planner')
            ->orderByDesc('total')
            ->get();

        // ================= SUMMARY PIC MONITORING =================
        $summary_pic_monitoring = (clone $grouped)
            ->select(
                'pic_monitoring',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN tanggal_tiba IS NOT NULL AND estimasi_tiba IS NOT NULL AND DATEDIFF(DATE(tanggal_tiba), DATE(estimasi_tiba)) <= 0 THEN 1 ELSE 0 END) as total_ontime"),
                DB::raw("SUM(CASE WHEN tanggal_tiba IS NOT NULL AND estimasi_tiba IS NOT NULL AND DATEDIFF(DATE(tanggal_tiba), DATE(estimasi_tiba)) > 0 THEN 1 ELSE 0 END) as total_delay"),
                DB::raw("SUM(CASE WHEN NULLIF(TRIM(tanggal_tiba), '') IS NULL THEN 1 ELSE 0 END) as total_belum_tiba"),
                DB::raw("SUM(CASE WHEN NULLIF(TRIM(tanggal_tiba), '') IS NULL AND estimasi_tiba IS NOT NULL AND DATE(estimasi_tiba) < CURDATE() THEN 1 ELSE 0 END) as total_lewat_estimasi")
            )
            ->whereNotNull('pic_monitoring')->where('pic_monitoring', '!=', '')
            ->groupBy('pic_monitoring')
            ->orderByDesc('total')
            ->get();

        // ================= KATEGORI EKSPEDISI & PENGIRIMAN OPTIMAL =================
        $summary_kategori_ekspedisi = (clone $grouped)
            ->select('kategori_ekspedisi', DB::raw('COUNT(*) as total'))
            ->whereNotNull('kategori_ekspedisi')->where('kategori_ekspedisi', '!=', '')
            ->groupBy('kategori_ekspedisi')
            ->get();

        $summary_pengiriman_optimal = (clone $grouped)
            ->select('pengiriman_optimal', DB::raw('COUNT(*) as total'))
            ->whereNotNull('pengiriman_optimal')->where('pengiriman_optimal', '!=', '')
            ->groupBy('pengiriman_optimal')
            ->get();

        // ================= RATIO =================
        $total_status = $planner_ontime + $planner_delay;
        $ontime_rate  = $total_status > 0 ? ($planner_ontime / $total_status) * 100 : 0;
        $delay_rate   = $total_status > 0 ? ($planner_delay / $total_status) * 100 : 0;

        $total_armada = $planner_armada + $planner_belum_armada;
        $armada_rate  = $total_armada > 0 ? ($planner_armada / $total_armada) * 100 : 0;
        $pending_rate = $total_armada > 0 ? ($planner_belum_armada / $total_armada) * 100 : 0;

        $summary_monitoring = [
            'tiba_ontime'            => $total_data > 0 ? ($customer_ontime / $total_data) * 100 : 0,
            'tiba_belum_diisi_delay' => $total_data > 0 ? ($tiba_belum_diisi_delay / $total_data) * 100 : 0,
            'tiba_delay'             => $total_data > 0 ? ($customer_delay / $total_data) * 100 : 0,
            'bongkar_ontime'         => $total_data > 0 ? ($bongkar_ontime / $total_data) * 100 : 0,
            'bongkar_delay'          => $total_data > 0 ? ($bongkar_delay / $total_data) * 100 : 0,
        ];

           $kpiLinks = $this->kpiLinks($request);

return view('spvplanner.dashboard_full', compact(
    'total_data', 'kpiLinks', 'gudang_ontime', 'gudang_delay',
    'customer_ontime', 'customer_delay',
    'summary_kategori_ekspedisi', 'summary_pengiriman_optimal',
    'bongkar_ontime', 'bongkar_delay', 'tiba_belum_diisi_delay',
    'summary_area', 'summary_tujuan',
    'totalNilaiMuatan', 'totalBiayaKirim',
    'trend_pengiriman_bulanan', 'ekspedisi',
    'total_belum_tiba_gudang', 'top_ekspedisi_pemakaian',
    'total_in_transit', 'summary_area_ontime',
    'summary_planner', 'summary_pic_monitoring',
    'label', 'value',
    'planner_ontime', 'planner_delay',
    'planner_armada', 'planner_belum_armada',
    'ontime_rate', 'delay_rate', 'armada_rate', 'pending_rate',
    'summary_monitoring', 'list_dist_channel', 'list_area',
    'summary_pulau', 'label_pulau', 'value_muatan_pulau', 'value_biaya_pulau'
));
    }

    /* =====================================================
     * STORE (Add New Shipment)
     * ===================================================== */

    /* =====================================================
     * DASHBOARD (ringkas)
     * ===================================================== */
    public function dashboard()
    {
        $shipments = DB::table('logistik_pengiriman')
            ->orderBy('no_shipment')
            ->get()
            ->groupBy('no_shipment')
            ->map(fn($group) => $group->first());

        $total_data = $shipments->count();

        $armada = $shipments->filter(fn($row) => !empty($row->rencana_kirim) && !empty($row->tanggal_dpt_unit))->count();

        $belum_armada = $shipments->filter(fn($row) => empty($row->rencana_kirim) || empty($row->tanggal_dpt_unit))->count();

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
            ->map(fn($group) => count($group))
            ->sortDesc()
            ->take(10);

        return view('spvplanner.dashboard', compact(
            'total_data', 'ontime', 'delay', 'armada', 'belum_armada', 'summary_area'
        ));
    }

    public function archive()
    {
        DB::transaction(function () {
            $data = DB::table('logistik_pengiriman_pasuruan')->get();

            if ($data->isEmpty()) {
                return;
            }

            // insert per 500 baris (batas placeholder MySQL)
            $data->map(fn($row) => (array) $row)
                ->chunk(500)
                ->each(function ($chunk) {
                    DB::table('logistik_pengiriman_pasuruan_storage')->insert($chunk->toArray());
                });

            DB::table('logistik_pengiriman_pasuruan')->delete();
        });

        return redirect()
            ->route('spvplanner.data.pasuruan')
            ->with('success', 'Semua data berhasil dipindahkan ke Storage.');
    }

    /* =====================================================
     * UPDATE (form penuh dari halaman planner)
     * ===================================================== */

    /* =====================================================
     * HALAMAN DATA PLANNER (SPV) — server-side DataTables
     * ===================================================== */



    private function selesaiGudangSql(): string
    {
        $f = fn($c) => "NULLIF(TRIM({$c}), '') IS NOT NULL";
        $e = fn($c) => "NULLIF(TRIM({$c}), '') IS NULL";

        $cycles = [
            ['planning_loading',   'tanggal_tiba_gudang',   'tanggal_keluar_gudang'],
            ['planning_loading_2', 'tanggal_tiba_gudang_2', 'tanggal_keluar_gudang_2'],
            ['planning_loading_3', 'tanggal_tiba_gudang_3', 'tanggal_keluar_gudang_3'],
        ];

        $menggantung = [];
        foreach ($cycles as [$p, $t, $k]) {
            $menggantung[] = '((' . $f($p) . ' OR ' . $f($t) . ') AND ' . $e($k) . ')';
        }

        $adaKeluar = '(' . implode(' OR ', [
            $f('tanggal_keluar_gudang'),
            $f('tanggal_keluar_gudang_2'),
            $f('tanggal_keluar_gudang_3'),
        ]) . ')';

        return "CASE WHEN {$adaKeluar} AND NOT (" . implode(' OR ', $menggantung) . ") THEN 1 ELSE 0 END";
    }

    /**
     * Bangun 1 baris (index harus sinkron dengan thead spvplanner/data_planner.blade.php).
     */

    /* =====================================================
     * ALERT CONTROL (ringkasan field kosong)
     * ===================================================== */

    /* =====================================================
     * FULL DATA LOGISTIK — hanya halaman.
     * Data (ajax), rincian DO & export dilayani LogistikController:
     *   route('logistik.ajax'), route('logistik.detail'), route('logistik.export')
     * ===================================================== */
    public function fullDataLogistik(Request $request)
    {
        $picList = LogistikPengiriman::whereNotNull('planner')
            ->where('planner', '!=', '')
            ->distinct()
            ->orderBy('planner')
            ->pluck('planner');

        $areaList = LogistikPengiriman::whereNotNull('area')
            ->where('area', '!=', '')
            ->distinct()
            ->orderBy('area')
            ->pluck('area');

        return view('spvplanner.full_data_logistik', compact('picList', 'areaList'));
    }

    /* =====================================================
     * GUDANG / SLA HELPERS
     * ===================================================== */
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

    private function getTibaGudangTerdekat($row)
    {
        return collect([
            $row->tanggal_tiba_gudang,
            $row->tanggal_tiba_gudang_2,
            $row->tanggal_tiba_gudang_3,
        ])->filter()->sort()->values()->first();
    }

    /**
     * HITUNG SLA (dipakai store/update/autosaveRow/slaOntime)
     */

    /* =====================================================
     * AUTOSAVE ROW (inline edit per-cell)
     * ===================================================== */

    /* =====================================================
     * SLA / ARMADA / SUMMARY (halaman lama)
     * ===================================================== */
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

                foreach ($sla as $k => $v) {
                    $row->$k = $v;
                }

                $gudang = [];
                if (!empty($row->tanggal_tiba_gudang))   $gudang[1] = strtotime($row->tanggal_tiba_gudang);
                if (!empty($row->tanggal_tiba_gudang_2)) $gudang[2] = strtotime($row->tanggal_tiba_gudang_2);
                if (!empty($row->tanggal_tiba_gudang_3)) $gudang[3] = strtotime($row->tanggal_tiba_gudang_3);

                $row->gudang_sla = count($gudang) ? array_search(min($gudang), $gudang) : null;

                $keluar = [];
                if (!empty($row->tanggal_keluar_gudang))   $keluar[1] = strtotime($row->tanggal_keluar_gudang);
                if (!empty($row->tanggal_keluar_gudang_2)) $keluar[2] = strtotime($row->tanggal_keluar_gudang_2);
                if (!empty($row->tanggal_keluar_gudang_3)) $keluar[3] = strtotime($row->tanggal_keluar_gudang_3);

                $row->gudang_keluar_terakhir = count($keluar) ? array_search(max($keluar), $keluar) : null;

                return $row;
            })
            ->values();

        $list_area = $this->getArea();

        return view('spvplanner.sla_ontime', compact('list', 'list_area'));
    }

    public function slaDelay(Request $request)
    {
        $query = DB::table('logistik_pengiriman')
            ->whereNotNull('rencana_kirim')
            ->whereRaw("TRIM(rencana_kirim) <> ''")
            ->whereNotNull('tanggal_dpt_unit')
            ->whereRaw("TRIM(tanggal_dpt_unit) <> ''")
            ->where(fn($q) => $q->whereNull('tanggal_tiba_gudang')->orWhere('tanggal_tiba_gudang', ''))
            ->where(fn($q) => $q->whereNull('tanggal_tiba_gudang_2')->orWhere('tanggal_tiba_gudang_2', ''))
            ->where(fn($q) => $q->whereNull('tanggal_tiba_gudang_3')->orWhere('tanggal_tiba_gudang_3', ''));

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

        return view('spvplanner.sla_delay', [
            'title'     => 'SLA DELAY',
            'list'      => $list,
            'list_area' => $this->getArea(),
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

            'updated_at' => now(),
        ];

        LogistikPengiriman::where('no_shipment', $request->no_shipment)->update($data);

        return response()->json([
            'status'  => 'success',
            'message' => 'Data Gudang 2 & Gudang 3 berhasil diupdate'
        ]);
    }

    public function summaryArea()
    {
        // per grup shipment+tujuan (sama dengan Monitoring)
        $summary_area = DB::table('logistik_pengiriman')
            ->whereIn('id', $this->groupIdsQuery(DB::table('logistik_pengiriman')))
            ->select('area', DB::raw('COUNT(*) as total'))
            ->groupBy('area')
            ->orderByDesc('total')
            ->get();

        return view('spvplanner.summary_area', compact('summary_area'));
    }

    private function mapArmadaRow($row, $dari, $sampai)
    {
        if ($dari && $sampai) {
            $awal = new \DateTime(date('Y-m-d H:i:s', strtotime($dari)));
            $akhir = new \DateTime(date('Y-m-d H:i:s', strtotime($sampai)));

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
            ->map(fn($row) => $this->mapArmadaRow($row, $row->rencana_kirim, $row->tanggal_dpt_unit));

        return view('spvplanner.armada', compact('logistik'));
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
            ->map(fn($row) => $this->mapArmadaRow($row, $row->tanggal_dpt_unit, $this->getTibaGudangTerdekat($row)))
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

        $logistik = $query->orderBy('tanggal_naik_logistik', 'DESC')->get();

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

        return redirect()->back()->with('success', 'Data berhasil dihapus');
    }

    /* =====================================================================
     * DATA PLANNER / PENGINPUTAN (disamakan dengan PlannerController)
     * Satu baris = satu shipment + tujuan; nilai/qty/kubik/tonase = jumlah semua DO.
     * ===================================================================== */

    private function cleanTime($value): ?string
    {
        $value = trim((string) $value);
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $value) ? $value : null;
    }

    private function multiInput(Request $request, string ...$keys): array
    {
        foreach ($keys as $key) {
            $vals = array_values(array_filter(
                (array) $request->input($key, []),
                fn($v) => $v !== null && $v !== ''
            ));
            if (!empty($vals)) return $vals;
        }
        return [];
    }

    private function cleanDecimalPlanner($value): ?float
    {
        if ($value === null || $value === '' || $value === '-') return null;

        if (is_numeric($value)) return (float) $value;

        $value = trim((string) $value);

        if (preg_match('/^\d+,\d+$/', $value)) {
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function plannerLists(): array
    {
        $tuj = fn($col) => DB::table('tujuanfillterr')
            ->whereNotNull($col)->where($col, '!=', '')
            ->distinct()->orderBy($col)->pluck($col);

        $tarif = fn($col) => DB::table('tarif_pengiriman')
            ->whereNotNull($col)->where($col, '!=', '')
            ->distinct()->orderBy($col)->pluck($col);

        return [
            'tujuanList'        => $tuj('tujuan'),
            'pulauList'         => $tuj('pulau'),
            'areas'             => $tuj('area'),
            'distChannelList'   => $tuj('dist_channel'),
            'ekpedisiList'      => $tarif('ekpedisi'),
            'mobilList'         => $tarif('mobil'),
            'routeList'         => $tarif('route'),
            'reasonOptimalList' => $this->getReasonOptimalList(),
            'reasonGudangList'  => $this->getReasonGudangList(),
        ];
    }

    private function plannerGroupAgg($rows): array
    {
        $shipments = $rows->pluck('no_shipment')
            ->filter(fn($v) => trim((string) $v) !== '')
            ->unique()->values();

        if ($shipments->isEmpty()) return [];

        return LogistikPengiriman::query()
            ->whereIn('no_shipment', $shipments)
            ->selectRaw("no_shipment,
                COALESCE(tujuan,'') AS tujuan_key,
                COUNT(*) AS jml_do,
                SUM(COALESCE(nilai_muatan,0))     AS sum_nilai,
                SUM(COALESCE(total_kubik,0))      AS sum_kubik,
                MAX(COALESCE(biaya_kirim,0))      AS sum_biaya,
                SUM(COALESCE(total_tonase,0))     AS sum_tonase,
                SUM(COALESCE(total_do_qty_car,0)) AS sum_qty,
                MAX(kubikasi)                     AS kap_kubik,
                MAX(tonase)                       AS kap_tonase")
            ->groupBy('no_shipment', 'tujuan_key')
            ->get()
            ->keyBy(fn($g) => $g->no_shipment . '|' . $g->tujuan_key)
            ->all();
    }

    /** Scope semua DO dalam 1 grup (no_shipment + tujuan) */
    private function groupScope($row, ?string $shipment = null)
    {
        $shipment = $shipment ?: $row->no_shipment;
        $q = DB::table('logistik_pengiriman');

        if (trim((string) $shipment) === '') {
            return $q->where('id', $row->id);
        }
        return $q->where('no_shipment', $shipment)
                 ->whereRaw("COALESCE(tujuan,'') = ?", [(string) $row->tujuan]);
    }

    private function statusOptimal($hasilKubik, $hasilTonase): ?string
    {
        if ($hasilKubik === null && $hasilTonase === null) return null;

        return (($hasilKubik ?? 0) >= 85 || ($hasilTonase ?? 0) >= 85)
            ? 'OPTIMAL'
            : 'TIDAK OPTIMAL';
    }

    private function hitungHasilOptimal($totalKubik, $kubikasi, $totalTonase, $tonase): array
    {
        $hasilKubik  = null;
        $hasilTonase = null;

        if ($kubikasi !== null && (float) $kubikasi > 0 && $totalKubik !== null) {
            $hasilKubik = round(((float) $totalKubik / (float) $kubikasi) * 100, 2);
        }
        if ($tonase !== null && (float) $tonase > 0 && $totalTonase !== null) {
            $hasilTonase = round(((float) $totalTonase / (float) $tonase) * 100, 2);
        }

        return [
            'hasil_kubik'        => $hasilKubik,
            'hasil_tonase'       => $hasilTonase,
            'pengiriman_optimal' => $this->statusOptimal($hasilKubik, $hasilTonase),
        ];
    }

    /** Status optimal per no_shipment: hasil semua grup (tujuan) dijumlah dulu */
    private function optimalPerShipment(array $groupAgg): array
    {
        $k = [];
        $t = [];

        foreach ($groupAgg as $g) {
            $h  = $this->hitungHasilOptimal($g->sum_kubik, $g->kap_kubik, $g->sum_tonase, $g->kap_tonase);
            $no = $g->no_shipment;

            if ($h['hasil_kubik']  !== null) $k[$no] = ($k[$no] ?? 0) + $h['hasil_kubik'];
            if ($h['hasil_tonase'] !== null) $t[$no] = ($t[$no] ?? 0) + $h['hasil_tonase'];
        }

        $out = [];
        foreach (array_unique(array_merge(array_keys($k), array_keys($t))) as $no) {
            $out[$no] = $this->statusOptimal($k[$no] ?? null, $t[$no] ?? null);
        }
        return $out;
    }

    /** Durasi di gudang (tanggal + jam). Delay jika > 24 jam. Jam kosong = 00:00 */
    private function hitungGudang($tglTiba, $wTiba, $tglKeluar, $wKeluar): ?array
    {
        if (empty($tglTiba) || empty($tglKeluar)) return null;

        $awal  = strtotime(date('Y-m-d', strtotime($tglTiba))   . ' ' . ($this->cleanTime($wTiba)   ?: '00:00:00'));
        $akhir = strtotime(date('Y-m-d', strtotime($tglKeluar)) . ' ' . ($this->cleanTime($wKeluar) ?: '00:00:00'));

        $menit = max(0, intdiv($akhir - $awal, 60));
        $hari  = intdiv($menit, 1440);
        $jam   = intdiv($menit % 1440, 60);
        $mnt   = $menit % 60;

        $text = trim(($hari ? "{$hari} Hari " : '') . ($jam ? "{$jam} Jam " : '') . "{$mnt} Menit");

        return ['text' => $text, 'delay' => $menit > 1440, 'hari' => $hari];
    }

    /* ---------------- STORE ---------------- */
    public function store(Request $request)
    {
        $rumus = $this->hitungSla($request);

        $data = $request->only([
            'create_tgl', 'no_shipment', 'kubikasi', 'planner',
            'dist_channel', 'transport_lead_time', 'tujuan', 'reason_optimal',
            'area', 'ketersediaan_unit', 'mobil', 'perubahan_mobil',
            'nilai_muatan', 'biaya_kirim', 'cr', 'kategori_ekspedisi', 'ekpedisi',
            'tanggal_naik_logistik', 'rencana_kirim', 'tanggal_dpt_unit',
            'planning_loading', 'tanggal_tiba_gudang', 'tanggal_keluar_gudang', 'reason_gudang',
            'planning_loading_2', 'tanggal_tiba_gudang_2', 'tanggal_keluar_gudang_2', 'reason_gudang_2',
            'planning_loading_3', 'tanggal_tiba_gudang_3', 'tanggal_keluar_gudang_3', 'reason_gudang_3',
            'keterangan', 'route', 'pulau', 'via_kirim',
        ]);

        foreach (['waktu_tiba_gudang', 'waktu_keluar_gudang', 'waktu_tiba_gudang_2', 'waktu_keluar_gudang_2',
                  'waktu_tiba_gudang_3', 'waktu_keluar_gudang_3'] as $f) {
            $data[$f] = $this->cleanTime($request->$f);
        }

        $data['total_kubik']  = $this->cleanDecimalPlanner($request->total_kubik);
        $data['total_tonase'] = $this->cleanDecimalPlanner($request->total_tonase);

        LogistikPengiriman::create(array_merge($data, $rumus));

        return back()->with('success', 'Data berhasil disimpan');
    }

    /* ---------------- UPDATE / AUTOSAVE (satu logika) ---------------- */

    /**
     * Simpan 1 baris + sebarkan field shared ke semua DO se-shipment.
     * @return array|null [$rumus, $estimasi_tiba] atau null jika data tidak ada
     */
    private function saveRow(Request $request, $id, bool $full): ?array
    {
        $old = DB::table('logistik_pengiriman')->where('id', $id)->first();
        if (!$old) return null;

        $oldNoShipment = $old->no_shipment;
        $newNoShipment = $request->no_shipment;
        $shipment      = $newNoShipment ?: $oldNoShipment;

        $gudangInfo = $this->getKeluarGudangInfoRequest($request);
        $keluar  = $gudangInfo['keluar'];
        $blocked = $gudangInfo['blocked'];
        $leadTime = $request->transport_lead_time;

        if (!$blocked && $keluar && $leadTime !== null && $leadTime !== '') {
            $request->merge([
                'estimasi_tiba' => date('Y-m-d', strtotime('+' . (int) $leadTime . ' days', $keluar)),
            ]);
        } elseif ($blocked) {
            $request->merge(['estimasi_tiba' => null]);
        } else {
            $request->merge(['estimasi_tiba' => $old->estimasi_tiba]);
        }

        $rumus = $this->hitungSla($request);

        $reason = fn($s) => ($rumus['status_gudang' . $s] ?? null) === 'Delay'
            ? ($request->{'reason_gudang' . $s} ?: null)
            : null;

        $shared = [
            'estimasi_tiba'         => $request->estimasi_tiba,
            'tanggal_naik_logistik' => $request->tanggal_naik_logistik,
            'rencana_kirim'         => $request->rencana_kirim,
            'tanggal_dpt_unit'      => $request->tanggal_dpt_unit,
            'transport_lead_time'   => $request->transport_lead_time,
            'planner'               => $request->planner,
            'no_shipment'           => $newNoShipment,

            'kategori_ekspedisi' => $request->kategori_ekspedisi,
            'ekpedisi'           => $request->ekpedisi,
            'mobil'              => $request->mobil,
            'route'              => $request->route,
            'area'               => $request->area,
            'pulau'              => $request->pulau,
            'via_kirim'          => $request->via_kirim,
            'dist_channel'       => $request->dist_channel,
            'nama_driver'        => $request->nama_driver,
            'no_pol'             => $request->no_pol,

            'lama_waktu_pencarian' => $rumus['lama_waktu_pencarian'] ?? null,
            'sla_dapat_mobil'      => $rumus['sla_dapat_mobil'] ?? null,
            'status_pengiriman'    => $rumus['status_pengiriman'] ?? null,

            'updated_at' => now(),
        ];

        if ($full) {
            $shared['perubahan_mobil'] = $request->perubahan_mobil;
            $shared['keterangan']      = $request->keterangan;
        }

        foreach (['', '_2', '_3'] as $s) {
            $shared['planning_loading' . $s]       = $request->{'planning_loading' . $s};
            $shared['tanggal_tiba_gudang' . $s]    = $request->{'tanggal_tiba_gudang' . $s};
            $shared['tanggal_keluar_gudang' . $s]  = $request->{'tanggal_keluar_gudang' . $s};
            $shared['waktu_tiba_gudang' . $s]      = $this->cleanTime($request->{'waktu_tiba_gudang' . $s});
            $shared['waktu_keluar_gudang' . $s]    = $this->cleanTime($request->{'waktu_keluar_gudang' . $s});
            $shared['reason_gudang' . $s]          = $reason($s);
            $shared['lama_digudang' . $s]          = $rumus['lama_digudang' . $s] ?? null;
            $shared['status_gudang' . $s]          = $rumus['status_gudang' . $s] ?? null;
            $shared['sla_loading' . $s]            = $rumus['sla_loading' . $s] ?? null;
        }

        DB::table('logistik_pengiriman')
            ->where(function ($q) use ($oldNoShipment, $newNoShipment) {
                $q->where('no_shipment', $oldNoShipment)
                  ->orWhere('no_shipment', $newNoShipment);
            })
            ->update($shared);

        // FIELD PER GRUP (shipment + tujuan). biaya_kirim, cr, kubikasi, tonase, hasil_*,
        // pengiriman_optimal, total_*, nilai_muatan, qty TIDAK disentuh (hasil import / penjumlahan DO)
        $this->groupScope($old, $shipment)->update([
            'pulau'                   => $request->pulau,
            'reason_optimal'          => $request->reason_optimal ?: null,
            'reason_optimal_customer' => $request->reason_optimal_customer ?: null,
            'updated_at'              => now(),
        ]);

        return [$rumus, $request->estimasi_tiba];
    }

    public function update(Request $request, $id)
    {
        if ($this->saveRow($request, $id, true) === null) {
            return back()->with('error', 'Data tidak ditemukan');
        }

        return back()->with('success', 'Data berhasil diperbarui');
    }

    public function autosaveRow(Request $request, $id)
    {
        $res = $this->saveRow($request, $id, false);

        if ($res === null) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        [$rumus, $estimasi] = $res;
        $row = DB::table('logistik_pengiriman')->where('id', $id)->first(['biaya_kirim', 'cr']);

        return response()->json([
            'success'       => true,
            'biaya_kirim'   => $row->biaya_kirim ?? null,
            'cr'            => $row->cr ?? null,
            'estimasi_tiba' => $estimasi,
            'sla'           => $rumus,
        ]);
    }

    /* ---------------- HALAMAN + AJAX ---------------- */
    public function dataLogistik()
    {
        $planners = LogistikPengiriman::whereNotNull('planner')
            ->where('planner', '!=', '')
            ->distinct()
            ->orderBy('planner')
            ->pluck('planner');

        $tarifPengiriman = DB::table('tarif_pengiriman')
            ->select('route', 'mobil', 'ekpedisi', 'biaya_kirim')
            ->whereNotNull('route')
            ->whereNotNull('mobil')
            ->get();

        return view('spvplanner.data_planner', array_merge(
            $this->plannerLists(),
            compact('planners', 'tarifPengiriman')
        ));
    }

    public function dataAjax(Request $request)
    {
        $draw   = (int) $request->input('draw', 1);
        $start  = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $searchValue = trim((string) $request->input('search.value', ''));

        $baseQuery    = LogistikPengiriman::query();
        $totalRecords = $this->countGroups(LogistikPengiriman::query());

        if ($vals = $this->multiInput($request, 'planner_filter')) {
            $baseQuery->whereIn('planner', $vals);
        }
        if ($vals = $this->multiInput($request, 'area_filter')) {
            $baseQuery->whereIn('area', $vals);
        }
        if ($vals = $this->multiInput($request, 'dist_channel_filter')) {
            $baseQuery->whereIn('dist_channel', $vals);
        }
        if ($request->filled('create_tgl_filter')) {
            $baseQuery->whereDate('create_tgl', $request->input('create_tgl_filter'));
        }

        if ($searchValue !== '') {
            $baseQuery->where(function ($q) use ($searchValue) {
                foreach ([
                    'planner', 'no_shipment', 'tujuan', 'route', 'pulau', 'area',
                    'via_kirim', 'dist_channel', 'kategori_ekspedisi', 'ekpedisi',
                    'nama_driver', 'no_pol', 'mobil', 'transport_lead_time',
                ] as $col) {
                    $q->orWhere($col, 'like', "%{$searchValue}%");
                }
            });
        }

        $recordsFiltered = $this->countGroups($baseQuery);

        $rows = LogistikPengiriman::query()
            ->whereIn('id', $this->groupIdsQuery($baseQuery))
            ->orderByRaw($this->selesaiGudangSql() . ' ASC')
            ->orderByRaw('CAST(no_shipment AS UNSIGNED) ASC')
            ->orderBy('id', 'ASC')
            ->skip($start)
            ->take($length)
            ->get();

        $groupAgg = $this->plannerGroupAgg($rows);
        $shipOpt  = $this->optimalPerShipment($groupAgg);
        $lists    = $this->plannerLists();

        $data = [];
        foreach ($rows as $r) {
            $r->jml_do = 1;
            $g = $groupAgg[$r->no_shipment . '|' . ($r->tujuan ?? '')] ?? null;

            if ($g && trim((string) $r->no_shipment) !== '') {
                $r->jml_do       = (int) $g->jml_do;
                $r->nilai_muatan = $g->sum_nilai;
                $r->biaya_kirim  = $g->sum_biaya;
                $r->total_kubik  = $g->sum_kubik;
                $r->total_tonase = $g->sum_tonase;

                $q = (float) $g->sum_qty;
                $r->total_do_qty_car = floor($q) == $q ? (int) $q : $q;

                $r->cr = (float) $g->sum_nilai > 0
                    ? ((float) $g->sum_biaya / (float) $g->sum_nilai) * 100
                    : 0;

                $h = $this->hitungHasilOptimal($r->total_kubik, $r->kubikasi, $r->total_tonase, $r->tonase);
                $r->hasil_kubik  = $h['hasil_kubik'];
                $r->hasil_tonase = $h['hasil_tonase'];

                // customer: hasil grup ini saja; internal: gabungan seluruh shipment
                $r->pengiriman_optimal_customer = $h['pengiriman_optimal'];
                $r->pengiriman_optimal = $shipOpt[$r->no_shipment] ?? $h['pengiriman_optimal'];
            }

            $data[] = $this->renderRowColumns($r, $lists);
        }

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    private function gudangCells($r, string $s): array
    {
        $n    = $s === '' ? 1 : (int) ltrim($s, '_');
        $info = $this->hitungGudang(
            $r->{'tanggal_tiba_gudang' . $s},   $r->{'waktu_tiba_gudang' . $s},
            $r->{'tanggal_keluar_gudang' . $s}, $r->{'waktu_keluar_gudang' . $s}
        );

        if (!$info) {
            $durasi = '-';
            $status = '<span class="badge gray">-</span>';
            $sla    = '<span class="badge bg-secondary">-</span>';
        } else {
            $durasi = e($info['text']);
            $status = $info['delay']
                ? '<span class="badge red">Delay</span>'
                : '<span class="badge green">On Time</span>';
            $sla = $info['delay']
                ? '<span class="badge red">H+' . $info['hari'] . '</span>'
                : '<span class="badge bg-success">Sesuai SLA</span>';
        }

        return [
            '<span class="g' . $n . '-durasi">' . $durasi . '</span>',
            '<span class="g' . $n . '-status">' . $status . '</span>',
            '<span class="g' . $n . '-sla">'    . $sla    . '</span>',
        ];
    }

    /**
     * Index kolom HARUS sinkron dengan thead spvplanner/data_planner.blade.php
     * (blade = salinan planner/data_planner.blade.php, route planner.* -> spvplanner.*).
     */
    private function renderRowColumns($r, array $lists)
    {
        $id = $r->id;
        $formAttr = 'form="form-update-' . $id . '"';

        $dateInput = function ($name, $value) use ($formAttr) {
            $val = $value ? date('Y-m-d', strtotime($value)) : '';
            return '<input type="date" ' . $formAttr . ' name="' . $name . '" value="' . e($val) . '">';
        };

        $timeInput = function ($name, $value) use ($formAttr) {
            $val = $value ? substr($value, 0, 5) : '';
            return '<input type="text" ' . $formAttr . ' name="' . $name . '" class="input-jam" '
                . 'placeholder="HH:MM" maxlength="5" inputmode="numeric" autocomplete="off" value="' . e($val) . '">';
        };

        $dateTimeInput = fn($dn, $dv, $tn, $tv) => $dateInput($dn, $dv) . '<br>' . $timeInput($tn, $tv);

        $formattedPersen = function ($angka) {
            if ($angka === null || $angka === '') return '';
            return number_format((float) $angka, 2, ',', '.') . '%';
        };

        $textInput = function ($name, $value, $extraClass = '') use ($formAttr) {
            return '<input type="text" ' . $formAttr . ' name="' . $name . '" class="' . $extraClass . '" value="' . e($value) . '">';
        };

        $fmtDec = fn($v) => ($v === null || $v === '')
            ? '' : rtrim(rtrim(number_format((float) $v, 4, ',', ''), '0'), ',');

        $roInput = fn($name, $value, $cls = '') =>
            '<input type="text" ' . $formAttr . ' name="' . $name . '" class="' . $cls . '" readonly '
            . 'style="background:#f1f5f9;" value="' . e($value) . '">';

        $buildSelect = function ($name, $selected, $options, $extraClass = '') use ($formAttr) {
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
            return $html . '</select>';
        };

        // Status Mobil
        $statusMobilHtml = !empty($r->tanggal_dpt_unit)
            ? '<span class="badge-status bg-success text-white">SUDAH DAPAT</span>'
            : '<span class="badge-status bg-danger text-white">BELUM DAPAT</span>';

        // SLA Dapat Mobil
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

        // Kelengkapan data
        $missing = [];
        foreach (['mobil' => 'Mobil', 'ekpedisi' => 'Ekspedisi', 'route' => 'Route', 'nama_driver' => 'Nama Driver', 'no_pol' => 'No Pol'] as $col => $label) {
            if (trim((string) ($r->$col ?? '')) === '') $missing[] = $label;
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

        $badgeOptimal = function ($val) {
            $val = strtoupper(trim((string) $val));
            if ($val === 'OPTIMAL') return '<span class="badge green">✅ Optimal</span>';
            if ($val !== '')        return '<span class="badge orange">⚠️ Tidak Optimal</span>';
            return '<span class="badge gray">-</span>';
        };

        $tanggalImportText = $r->create_tgl ? \Carbon\Carbon::parse($r->create_tgl)->format('d/m/Y H:i') : '-';

        $hiddenForm = '<form class="d-none" id="form-update-' . $id . '" action="' . route('spvplanner.update', $id) . '" method="POST">'
            . csrf_field() . method_field('PUT') . '</form>';

        return [
            $hiddenForm . $tanggalImportText,
            $textInput('planner', $r->planner),
            '<span class="fw-medium">' . e($r->no_shipment) . '</span>'
                . '<input type="hidden" ' . $formAttr . ' name="no_shipment" value="' . e($r->no_shipment) . '">',

            $dateInput('tanggal_naik_logistik', $r->tanggal_naik_logistik),
            $dateInput('rencana_kirim', $r->rencana_kirim),
            $dateInput('tanggal_dpt_unit', $r->tanggal_dpt_unit),

            // KACS
            $dateInput('planning_loading', $r->planning_loading),
            $dateTimeInput('tanggal_tiba_gudang',   $r->tanggal_tiba_gudang,   'waktu_tiba_gudang',   $r->waktu_tiba_gudang),
            $dateTimeInput('tanggal_keluar_gudang', $r->tanggal_keluar_gudang, 'waktu_keluar_gudang', $r->waktu_keluar_gudang),
            $buildSelect('reason_gudang', $r->reason_gudang, $lists['reasonGudangList'], 'row-reason-gudang'),

            // SENTUL
            $dateInput('planning_loading_2', $r->planning_loading_2),
            $dateTimeInput('tanggal_tiba_gudang_2',   $r->tanggal_tiba_gudang_2,   'waktu_tiba_gudang_2',   $r->waktu_tiba_gudang_2),
            $dateTimeInput('tanggal_keluar_gudang_2', $r->tanggal_keluar_gudang_2, 'waktu_keluar_gudang_2', $r->waktu_keluar_gudang_2),
            $buildSelect('reason_gudang_2', $r->reason_gudang_2, $lists['reasonGudangList'], 'row-reason-gudang'),

            // CCIE
            $dateInput('planning_loading_3', $r->planning_loading_3),
            $dateTimeInput('tanggal_tiba_gudang_3',   $r->tanggal_tiba_gudang_3,   'waktu_tiba_gudang_3',   $r->waktu_tiba_gudang_3),
            $dateTimeInput('tanggal_keluar_gudang_3', $r->tanggal_keluar_gudang_3, 'waktu_keluar_gudang_3', $r->waktu_keluar_gudang_3),
            $buildSelect('reason_gudang_3', $r->reason_gudang_3, $lists['reasonGudangList'], 'row-reason-gudang'),

            // tujuan (read only)
            '<span class="fw-medium">' . e($r->tujuan) . '</span>'
                . '<input type="hidden" ' . $formAttr . ' name="tujuan" value="' . e($r->tujuan) . '">',
            $buildSelect('route', $r->route, $lists['routeList'], 'row-route'),
            $buildSelect('pulau', $r->pulau, $lists['pulauList'], 'row-pulau'),
            $buildSelect('area', $r->area, $lists['areas'], 'row-area'),
            $textInput('via_kirim', $r->via_kirim),
            $buildSelect('dist_channel', $r->dist_channel, $lists['distChannelList'], 'row-dist-channel'),
            $textInput('kategori_ekspedisi', $r->kategori_ekspedisi),
            $buildSelect('ekpedisi', $r->ekpedisi, $lists['ekpedisiList'], 'row-ekpedisi'),
            $textInput('transport_lead_time', $r->transport_lead_time),
            $textInput('nama_driver', $r->nama_driver),
            $textInput('no_pol', $r->no_pol),
            $buildSelect('mobil', $r->mobil, $lists['mobilList'], 'row-mobil'),

            // jumlah semua DO (read only)
            $roInput('total_do_qty_car', $r->total_do_qty_car),
            $roInput('nilai_muatan', $formattedRupiah($r->nilai_muatan), 'row-nilai-muatan input-rupiah'),
            $roInput('biaya_kirim',  $formattedRupiah($r->biaya_kirim),  'row-biaya-kirim input-rupiah'),

            '<input type="text" ' . $formAttr . ' name="cr" class="row-cr" readonly style="background:#f1f5f9;color:#0284c7;font-weight:600;" value="' . e(is_numeric($r->cr) ? number_format((float) $r->cr, 4) : $r->cr) . '">',
            $textInput('kubikasi', $formattedPersen($r->kubikasi), 'row-kubikasi'),
            '<input type="text" readonly style="background:#f1f5f9;" value="' . e($r->tonase !== null ? number_format((float) $r->tonase, 2, ',', '.') . '%' : '-') . '">',
            $roInput('total_kubik',  $fmtDec($r->total_kubik),  'row-total-kubikasi'),
            $roInput('total_tonase', $fmtDec($r->total_tonase), 'row-total-tonase'),
            '<span>' . e($r->hasil_kubik !== null ? number_format((float) $r->hasil_kubik, 2, ',', '.') . '%' : '-') . '</span>',
            '<span>' . e($r->hasil_tonase !== null ? number_format((float) $r->hasil_tonase, 2, ',', '.') . '%' : '-') . '</span>',

            // optimal shipment + reason, optimal customer + reason
            $badgeOptimal($r->pengiriman_optimal),
            $buildSelect('reason_optimal', $r->reason_optimal, $lists['reasonOptimalList'], 'row-reason-optimal'),
            $badgeOptimal($r->pengiriman_optimal_customer),
            $buildSelect('reason_optimal_customer', $r->reason_optimal_customer, $lists['reasonOptimalList'], 'row-reason-optimal-customer'),

            $statusMobilHtml,
            '<span class="text-primary fw-medium">' . e($r->lama_waktu_pencarian) . '</span>',
            $slaMobilHtml,

            ...$this->gudangCells($r, ''),
            ...$this->gudangCells($r, '_2'),
            ...$this->gudangCells($r, '_3'),

            $r->route ? explode('-', trim($r->route))[0] : '-',
            $kelengkapanHtml,
            '<div class="btn-action"><a href="' . route('spvplanner.delete', $id) . '" class="btn btn-danger btn-sm px-2 d-flex align-items-center gap-1" onclick="return confirm(\'Hapus data ini?\')"><i class="fa-solid fa-trash"></i> Del</a></div>',
        ];
    }

    /* ---------------- ALERT CONTROL ---------------- */
    public function alerts(Request $request)
    {
        $fieldsMap = [
            'mobil'       => 'Mobil',
            'ekpedisi'    => 'Ekspedisi',
            'route'       => 'Route',
            'kubikasi'    => 'Kubikasi',
            'nama_driver' => 'Nama Driver',
            'no_pol'      => 'No Pol',
        ];

        $query = DB::table('logistik_pengiriman')
            ->select('id', 'no_shipment', 'mobil', 'ekpedisi', 'route', 'kubikasi', 'nama_driver', 'no_pol');

        if ($vals = $this->multiInput($request, 'planner_filter')) {
            $query->whereIn('planner', $vals);
        }
        if ($vals = $this->multiInput($request, 'area_filter')) {
            $query->whereIn('area', $vals);
        }
        if ($vals = $this->multiInput($request, 'dist_channel_filter')) {
            $query->whereIn('dist_channel', $vals);
        }

        $rows = $query->where(function ($q) {
            $q->whereNull('mobil')->orWhere('mobil', '')
                ->orWhereNull('ekpedisi')->orWhere('ekpedisi', '')
                ->orWhereNull('route')->orWhere('route', '')
                ->orWhereNull('kubikasi')->orWhere('kubikasi', '')
                ->orWhereNull('nama_driver')->orWhere('nama_driver', '')
                ->orWhereNull('no_pol')->orWhere('no_pol', '');
        })->get();

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

        return response()->json(['alerts' => $alertList, 'missingSummary' => $missingSummary]);
    }

    /* ---------------- HITUNG SLA (gudang pakai jam) ---------------- */
    private function hitungSla($request)
    {
        $data = [
            'lama_waktu_pencarian' => null,
            'sla_dapat_mobil'      => null,
            'status_pengiriman'    => null,
            'lama_digudang'   => null, 'status_gudang'   => null, 'sla_loading'   => null,
            'lama_digudang_2' => null, 'status_gudang_2' => null, 'sla_loading_2' => null,
            'lama_digudang_3' => null, 'status_gudang_3' => null, 'sla_loading_3' => null,
        ];

        $hitungSelisih = function ($start, $end) {
            if (!$start || !$end) return null;

            $awal  = new \DateTime($start);
            $akhir = new \DateTime($end);

            if ($akhir < $awal) {
                return ['text' => '0 Menit', 'days' => 0, 'hours' => 0, 'minutes' => 0];
            }

            $selisih = $akhir->getTimestamp() - $awal->getTimestamp();
            $days    = floor($selisih / 86400);
            $hours   = floor(($selisih % 86400) / 3600);
            $minutes = floor(($selisih % 3600) / 60);

            $text = '';
            if ($days > 0)  $text .= $days . ' Hari ';
            if ($hours > 0) $text .= $hours . ' Jam ';
            $text .= $minutes . ' Menit';

            return ['text' => trim($text), 'days' => $days, 'hours' => $hours, 'minutes' => $minutes];
        };

        // SLA dapat mobil
        $start = $request->rencana_kirim ? date('Y-m-d H:i:s', strtotime($request->rencana_kirim)) : null;
        $end   = $request->tanggal_dpt_unit ? date('Y-m-d H:i:s', strtotime($request->tanggal_dpt_unit)) : null;

        $diff = $hitungSelisih($start, $end);
        $data['lama_waktu_pencarian'] = $diff['text'] ?? null;

        if ($start && $end) {
            $area = strtoupper(trim($request->area ?? ''));

            $selisihHari = floor(
                (strtotime(date('Y-m-d', strtotime($end))) - strtotime(date('Y-m-d', strtotime($start)))) / 86400
            );

            if ($area == 'JABODETABEK' || $area == 'JABODEBEK' || $area == 'BANTEN') {
                $batasHari = 0;
            } elseif ($area == 'JAWA_BARAT' || $area == 'JAWA BARAT') {
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
        }

        // Gudang 1-3: tiba -> keluar (tanggal + jam), Delay jika > 24 jam
        foreach (['', '_2', '_3'] as $s) {
            $info = $this->hitungGudang(
                $request->{'tanggal_tiba_gudang' . $s},
                $request->{'waktu_tiba_gudang' . $s},
                $request->{'tanggal_keluar_gudang' . $s},
                $request->{'waktu_keluar_gudang' . $s}
            );

            if ($info) {
                $data['lama_digudang' . $s] = $info['text'];
                $data['status_gudang' . $s] = $info['delay'] ? 'Delay' : 'On Time';
                $data['sla_loading' . $s]   = $info['delay'] ? 'H+' . $info['hari'] : 'Sesuai SLA';
            }
        }

        return $data;
    }

    /* ---------------- EXPORT ---------------- */
    public function exportPlanner(Request $request)
    {
        $filters = [
            'planner'      => $this->multiInput($request, 'planner', 'planner_filter'),
            'area'         => $this->multiInput($request, 'area', 'area_filter'),
            'dist_channel' => $this->multiInput($request, 'dist_channel', 'dist_channel_filter'),
            'create_tgl'   => $request->input('create_tgl', $request->input('create_tgl_filter')),
            'bulan'        => $request->input('bulan'),
            'tahun'        => $request->input('tahun'),
        ];

        return Excel::download(
            new PlannerExport(array_filter($filters)),
            'Planner_' . date('Ymd_His') . '.xlsx'
        );
    }

    /* ---------------- IN GUDANG ---------------- */
    private function inGudangQuery()
    {
        return DB::table('logistik_pengiriman')
            ->whereRaw($this->selesaiGudangSql() . ' = 0')
            ->whereIn('id', function ($sub) {
                $sub->from('logistik_pengiriman')->selectRaw('MIN(id)')->groupBy('no_shipment');
            });
    }

    public function inGudang(Request $request)
    {
        $base = $this->inGudangQuery();
        $this->applyFilter($base, $request);

        if ($request->filled('q')) {
            $s = trim($request->input('q'));
            $base->where(function ($q) use ($s) {
                foreach (['no_shipment', 'tujuan', 'ekpedisi', 'nama_driver', 'no_pol', 'mobil', 'planner'] as $col) {
                    $q->orWhere($col, 'like', "%{$s}%");
                }
            });
        }
        if ($request->filled('planner')) {
            $base->where('planner', $request->input('planner'));
        }

        $kolomGudang = [
            'planning_loading', 'tanggal_tiba_gudang', 'tanggal_keluar_gudang',
            'planning_loading_2', 'tanggal_tiba_gudang_2', 'tanggal_keluar_gudang_2',
            'planning_loading_3', 'tanggal_tiba_gudang_3', 'tanggal_keluar_gudang_3',
        ];

        $total  = (clone $base)->count();
        $qBelum = clone $base;
        foreach ($kolomGudang as $c) {
            $qBelum->whereRaw("NULLIF(TRIM({$c}), '') IS NULL");
        }
        $belumInput = $qBelum->count();

        $summary = [
            'total'       => $total,
            'belum_input' => $belumInput,
            'sedang'      => $total - $belumInput,
        ];

        $belumSql   = implode(' AND ', array_map(fn($c) => "NULLIF(TRIM({$c}), '') IS NULL", $kolomGudang));
        $selesaiSql = implode(' + ', array_map(
            fn($c) => "(NULLIF(TRIM({$c}), '') IS NOT NULL)",
            ['tanggal_keluar_gudang', 'tanggal_keluar_gudang_2', 'tanggal_keluar_gudang_3']
        ));

        $sortMap = [
            'no_shipment'    => 'CAST(no_shipment AS UNSIGNED)',
            'planner'        => 'planner',
            'tujuan'         => 'tujuan',
            'area'           => 'area',
            'dist_channel'   => 'dist_channel',
            'rencana_kirim'  => 'rencana_kirim',
            'ekpedisi'       => 'ekpedisi',
            'driver'         => 'nama_driver',
            'gudang_selesai' => "({$selesaiSql})",
            'posisi'         => "(CASE WHEN {$belumSql} THEN 1 ELSE 0 END)",
        ];

        $sort = $request->input('sort');
        $dir  = strtolower($request->input('dir', 'asc')) === 'desc' ? 'DESC' : 'ASC';

        $q = clone $base;
        if ($sort && isset($sortMap[$sort])) {
            $q->orderByRaw("{$sortMap[$sort]} {$dir}");
        }

        $list = $q->orderBy('tanggal_naik_logistik', 'ASC')
            ->orderByRaw('CAST(no_shipment AS UNSIGNED) ASC')
            ->orderBy('id', 'ASC')
            ->paginate(50)
            ->withQueryString();

        $cycles = [
            ['KACS',   'planning_loading',   'tanggal_tiba_gudang',   'tanggal_keluar_gudang'],
            ['SENTUL', 'planning_loading_2', 'tanggal_tiba_gudang_2', 'tanggal_keluar_gudang_2'],
            ['CCIE',   'planning_loading_3', 'tanggal_tiba_gudang_3', 'tanggal_keluar_gudang_3'],
        ];

        $list->getCollection()->transform(function ($r) use ($cycles) {
            $selesai = [];
            $menggantung = [];

            foreach ($cycles as [$nama, $plan, $tiba, $keluar]) {
                $started = !empty($r->$plan) || !empty($r->$tiba);
                $done    = !empty($r->$keluar);

                if ($done) $selesai[] = $nama;

                if ($started && !$done) {
                    $menggantung[] = empty($r->$tiba)
                        ? "Planning loading {$nama}, belum tiba"
                        : "Di gudang {$nama}, belum keluar";
                }
            }

            if ($menggantung) {
                $r->posisi_label = implode(' | ', $menggantung);
                $r->posisi_class = 'orange';
            } else {
                $r->posisi_label = 'Belum diinput';
                $r->posisi_class = 'red';
            }

            $r->gudang_selesai = $selesai ? implode(', ', $selesai) : '-';
            return $r;
        });

        $areaList    = DB::table('logistik_pengiriman')->whereNotNull('area')->distinct()->orderBy('area')->pluck('area');
        $plannerList = DB::table('logistik_pengiriman')->whereNotNull('planner')->where('planner', '!=', '')->distinct()->orderBy('planner')->pluck('planner');
        $channelList = DB::table('logistik_pengiriman')->whereNotNull('dist_channel')->where('dist_channel', '!=', '')->distinct()->orderBy('dist_channel')->pluck('dist_channel');

        return view('spvplanner.in_gudang', compact('list', 'summary', 'areaList', 'plannerList', 'channelList'));
    }

    /** Tanggal import (create_tgl) + jumlah baris, untuk filter di halaman data planner */
    public function importDates()
    {
        $rows = DB::table('logistik_pengiriman')
            ->whereNotNull('create_tgl')
            ->selectRaw('DATE(create_tgl) AS tgl, COUNT(*) AS total')
            ->groupByRaw('DATE(create_tgl)')
            ->orderByRaw('DATE(create_tgl) DESC')
            ->get();

        return response()->json($rows->pluck('total', 'tgl'));
    }

}