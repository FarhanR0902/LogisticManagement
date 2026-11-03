<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use App\Exports\LogistikExportFull;
use App\Models\LogistikPengiriman;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\LogistikImport;
use App\Exports\LogistikExport;
use Carbon\Carbon;

class LogistikController extends Controller
{
    private const PERIODE_SALES = '11-2026';

    private const PULAU_MAP = [
        'JAWA'       => ['JABODEBEK', 'BANTEN', 'JAWA_BARAT', 'JAWA_TENGAH', 'JAWA_TIMUR', 'YOGYAKARTA'],
        'SUMATERA'   => ['ACEH', 'SUMATERA_UTARA', 'SUMATERA_BARAT', 'RIAU', 'KEP._RIAU', 'JAMBI', 'SUMATERA_SELATAN', 'BENGKULU', 'LAMPUNG', 'KEP._BANGKA_BELITUNG'],
        'KALIMANTAN' => ['KALIMANTAN_BARAT', 'KALIMANTAN_TENGAH', 'KALIMANTAN_SELATAN', 'KALIMANTAN_TIMUR', 'KALIMANTAN_UTARA'],
        'SULAWESI'   => ['SULAWESI_UTARA', 'SULAWESI_TENGAH', 'SULAWESI_SELATAN', 'SULAWESI_TENGGARA', 'SULAWESI_BARAT', 'GORONTALO'],
        'BALI_NUSRA' => ['PROV._BALI', 'NUSA_TENGGARA_BARAT', 'NUSA_TENGGARA_TIMUR'],
        'MALUKU'     => ['PROV._MALUKU', 'PROV._MALUKU_UTARA'],
        'PAPUA'      => ['PROV._PAPUA', 'PAPUA_BARAT', 'PAPUA_BARAT_DAYA', 'PAPUA_SELATAN', 'PAPUA_TENGAH'],
    ];
    private const GUDANG = [
    ''   => 'KACS',
    '_2' => 'SENTUL',
    '_3' => 'CCIE',
    '_4' => 'P5',
    '_5' => 'FIFATEX',
];

private function gudangSuffixes(): array
{
    return array_keys(self::GUDANG);
}

    /* =========================================================
     * HELPER FILTER TANGGAL
     * ========================================================= */

    /**
     * Tentukan kolom tanggal dari pilihan "tgl_basis".
     * 'periode_shipment' di DB berisi string bulan-tahun (mis. 11-2026),
     * bukan tanggal -> dialihkan ke kolom tanggal_shipment.
     */
    private function resolveTanggalColumn(?string $basis): string
    {
        return match ($basis) {
            'rencana_kirim'         => 'rencana_kirim',
            'tanggal_naik_logistik' => 'tanggal_naik_logistik',
            'periode_shipment',
            'tanggal_shipment'      => 'tanggal_shipment',
            default                 => 'create_tgl',
        };
    }

    /**
     * Ekspresi SQL tanggal yang tahan format: Y-m-d (DATE/DATETIME) ATAU d-m-Y (string).
     */
    private function dateExpr(string $col): string
    {
        return "COALESCE(DATE(`{$col}`), STR_TO_DATE(`{$col}`, '%d-%m-%Y'))";
    }

    /**
     * Terapkan filter from/to/month/year pada kolom tanggal terpilih.
     */
    private function applyDateRange($query, string $col, $from, $to, $month = null, $year = null)
    {
        $expr = $this->dateExpr($col);

        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }
        if ($from) {
            $query->whereRaw("{$expr} >= ?", [$from]);
        }
        if ($to) {
            $query->whereRaw("{$expr} <= ?", [$to]);
        }
        if ($month) {
            $query->whereRaw("MONTH({$expr}) = ?", [(int) $month]);
        }
        if ($year) {
            $query->whereRaw("YEAR({$expr}) = ?", [(int) $year]);
        }

        return $query;
    }

    private function gudangExtraCells($r): array
{
    $out = [];
    foreach (['_4', '_5'] as $s) {
        $n      = ltrim($s, '_');
        $tiba   = $r->{"tanggal_tiba_gudang{$s}"};
        $keluar = $r->{"tanggal_keluar_gudang{$s}"};

        $out["planning_loading{$s}"]        = $this->fmtDate($r->{"planning_loading{$s}"});
        $out["tanggal_tiba_gudang{$s}"]     = $this->fmtDate($tiba);
        $out["waktu_tiba_gudang{$s}_fmt"]   = $this->fmtWaktu($r->{"waktu_tiba_gudang{$s}"});
        $out["tanggal_keluar_gudang{$s}"]   = $this->fmtDate($keluar);
        $out["waktu_keluar_gudang{$s}_fmt"] = $this->fmtWaktu($r->{"waktu_keluar_gudang{$s}"});
        $out["reason_gudang{$s}"]           = $r->{"reason_gudang{$s}"} ?: '-';
        $out["lama_digudang{$s}"]           = $this->computeDurasiGudang($tiba, $keluar);
        $out["status_gudang{$n}_badge"]     = $this->badgeStatusOnTimeDelayByDate($tiba, $keluar);
        $out["sla_loading{$n}_badge"]       = $this->badgeSlaLoadingClean($tiba, $keluar);
    }
    return $out;
}


    /**
     * Filter dari dashboard manager (dikirim sebagai dash[...] / query string).
     * Logikanya HARUS sama dengan ManagerController::applyFilter().
     */
   private function applyDashboardFilter($query, array $f)
{
    $areas = array_values(array_filter((array) ($f['area'] ?? []), 'strlen'));
    if (!empty($areas)) {
        $query->whereIn('area', $areas);
    }

    if (!empty($f['dist_channel'])) {
        $query->where('dist_channel', $f['dist_channel']);
    }

    if (!empty($f['pulau']) && isset(self::PULAU_MAP[$f['pulau']])) {
        $query->whereIn('area', self::PULAU_MAP[$f['pulau']]);
    }

    $basis = $f['tgl_basis'] ?? 'periode_shipment';   // default sama dengan Manager
    $from  = $f['date_from'] ?? null;
    $to    = $f['date_to'] ?? null;
    if ($from && $to && $from > $to) {
        [$from, $to] = [$to, $from];
    }

    // ===== Basis PERIODE SHIPMENT (sama dengan ManagerController::applyFilter) =====
    if (in_array($basis, ['periode_shipment', 'tanggal_shipment'], true)) {
        if (!empty($f['month']) && preg_match('/^(\d{4})-(\d{2})$/', $f['month'], $m)) {
            $query->where('periode_shipment', $m[2] . '-' . $m[1]);
        }
        if (!empty($f['year']) && ctype_digit((string) $f['year'])) {
            $query->where('periode_shipment', 'like', '%-' . $f['year']);
        }
        if (!empty($f['date']))  $query->whereDate('tanggal_shipment', $f['date']);
        if ($from) $query->whereDate('tanggal_shipment', '>=', $from);
        if ($to)   $query->whereDate('tanggal_shipment', '<=', $to);

        return $query;
    }

    // ===== Basis lain (rencana_kirim / tanggal_naik_logistik) =====
    $col = $this->resolveTanggalColumn($basis);

    $month = $yearFromMonth = null;
    if (!empty($f['month'])) {
        $yearFromMonth = substr($f['month'], 0, 4);
        $month         = substr($f['month'], 5, 2);
    }

    $this->applyDateRange($query, $col, $from, $to, $month, $yearFromMonth);

    if (!empty($f['year'])) {
        $query->whereRaw('YEAR(' . $this->dateExpr($col) . ') = ?', [(int) $f['year']]);
    }

    return $query;
}

    /**
     * Filter tanggal dari dropdown halaman: periode + bulan + tahun.
     */
    private function applyTanggalFilter($query, Request $request)
    {
        $col = $this->resolveTanggalColumn($request->input('tgl_basis'));

        return $this->applyDateRange(
            $query,
            $col,
            $request->input('date_from'),
            $request->input('date_to'),
            $request->filled('month') ? $request->month : null,
            $request->filled('year') ? $request->year : null
        );
    }

    /* =========================================================
     * ROLE SALES
     * ========================================================= */
    private function isSales(): bool
    {
        return auth()->check() && auth()->user()->role === 'sales';
    }

    private function filterPeriodeSales($query)
    {
        if ($this->isSales()) {
            $query->where('periode_shipment', self::PERIODE_SALES);
        }
        return $query;
    }

    /* =========================================================
     * DIST CHANNEL SESSION FILTER
     * ========================================================= */
   private const MODERN_MARKET_CHANNELS = [
    'FSI',
    'MT - Minimarket',
    'MT - Supermarket',
    'MT - Lokal',
    'MT - Hypermarket',
];

private function filterByDistChannel($query)
{
    $u = auth()->user();

    $isMM = $u && (
        strtolower((string) $u->username) === 'modern_market'
        || strtoupper((string) $u->dist_channel) === 'MODERN_MARKET'
    );

    $channel = $isMM ? 'MODERN_MARKET' : session('dist_channel');

    if (is_array($channel)) {
        $channel = array_values(array_filter($channel, 'strlen'));
        if (!empty($channel)) {
            $query->whereIn('dist_channel', $channel);
        }
    } elseif ($channel && strtoupper(trim((string) $channel)) === 'MODERN_MARKET') {
        $query->whereIn('dist_channel', self::MODERN_MARKET_CHANNELS);
    } elseif ($channel) {
        $query->whereRaw('LOWER(TRIM(dist_channel)) = ?', [strtolower(trim((string) $channel))]);
    }

    return $this->filterPeriodeSales($query);
}

    /* =========================================================
     * DASHBOARD
     * ========================================================= */
    public function dashboard(Request $request)
    {
        $bulan = $request->month ? date('m', strtotime($request->month)) : null;
        $tahun = $request->year;
        $area  = $request->area;
        $date  = $request->date;

        $query = LogistikPengiriman::query();

        if ($date) {
            $query->whereDate('tanggal_naik_logistik', $date);
        }

        if ($bulan && $tahun) {
            $query->whereMonth('tanggal_naik_logistik', $bulan)
                ->whereYear('tanggal_naik_logistik', $tahun);
        }

        if ($area) {
            $query->where('area', $area);
        }

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

        $totalNilaiMuatan = (clone $query)->sum('nilai_muatan');
        $totalBiayaKirim  = (clone $query)->sum('biaya_kirim');

        $summary_area = (clone $query)
            ->select(
                'area',
                DB::raw('COUNT(*) as total_shipment'),
                DB::raw('SUM(biaya_kirim) as total_biaya'),
                DB::raw('SUM(nilai_muatan) as total_muatan')
            )
            ->groupBy('area')
            ->orderByDesc('total_shipment')
            ->get();

        $summary_tujuan = (clone $query)
            ->select(
                'tujuan',
                DB::raw('COUNT(*) as total_shipment'),
                DB::raw('SUM(biaya_kirim) as total_biaya'),
                DB::raw('SUM(nilai_muatan) as total_muatan')
            )
            ->groupBy('tujuan')
            ->orderByDesc('total_shipment')
            ->get();

        $list_area = $this->cachedList('area');
        $list_dist_channel = $this->cachedList('dist_channel');

        $gudang_ontime = (clone $query)
            ->where(function ($q) {
                $q->where('sla_loading', 'H+0')
                    ->orWhere('status_gudang', 'On Time')
                    ->orWhere('status_gudang', 'ONTIME');
            })
            ->count();

        $gudang_delay = (clone $query)
            ->where(function ($q) {
                $q->where('sla_loading', 'H+1')
                    ->orWhere('sla_loading', 'H+2')
                    ->orWhere('sla_loading', 'H>2')
                    ->orWhere('status_gudang', 'Delay')
                    ->orWhere('status_gudang', 'DELAY');
            })
            ->count();

        $customer_ontime = (clone $query)
            ->where(function ($q) {
                $q->where('sla_tiba', 'H+0')
                    ->orWhere('sla_tiba', 'On Time')
                    ->orWhere('sla_tiba', 'ONTIME');
            })
            ->count();

        $customer_delay = (clone $query)
            ->where(function ($q) {
                $q->where('sla_tiba', 'H+1')
                    ->orWhere('sla_tiba', 'H+2')
                    ->orWhere('sla_tiba', 'H>2')
                    ->orWhere('sla_tiba', 'Delay')
                    ->orWhere('sla_tiba', 'Critical Delay');
            })
            ->count();

        $bongkar_ontime = (clone $query)
            ->where(function ($q) {
                $q->where('sla_bongkar', 'H+0')
                    ->orWhere('sla_bongkar', 'On Time')
                    ->orWhere('sla_bongkar', 'ONTIME');
            })
            ->count();

        $bongkar_delay = (clone $query)
            ->where(function ($q) {
                $q->where('sla_bongkar', 'H+1')
                    ->orWhere('sla_bongkar', 'H+2')
                    ->orWhere('sla_bongkar', 'H>2')
                    ->orWhere('sla_bongkar', 'Delay')
                    ->orWhere('sla_bongkar', 'Critical Delay');
            })
            ->count();

        $total_tiba = $customer_ontime + $customer_delay;
        $total_bongkar = $bongkar_ontime + $bongkar_delay;

        $summary_monitoring = [
            'tiba_ontime'    => $total_tiba ? ($customer_ontime / $total_tiba) * 100 : 0,
            'tiba_delay'     => $total_tiba ? ($customer_delay / $total_tiba) * 100 : 0,
            'bongkar_ontime' => $total_bongkar ? ($bongkar_ontime / $total_bongkar) * 100 : 0,
            'bongkar_delay'  => $total_bongkar ? ($bongkar_delay / $total_bongkar) * 100 : 0,
        ];

        $planner_ontime = $total_loading_ontime;
        $planner_delay  = $total_loading_delay;

        $total_planner = $planner_ontime + $planner_delay;

        $ontime_rate = $total_planner ? ($planner_ontime / $total_planner) * 100 : 0;
        $delay_rate  = $total_planner ? ($planner_delay / $total_planner) * 100 : 0;

        $planner_armada = $armada;
        $planner_belum_armada = $belum_armada;

        $armada_rate = ($armada + $belum_armada)
            ? ($armada / ($armada + $belum_armada)) * 100
            : 0;

        $label = [];
        $value = [];

        return view('dashboard', compact(
            'total_data',
            'total_loading_ontime',
            'total_loading_delay',
            'armada',
            'belum_armada',
            'process',
            'totalNilaiMuatan',
            'totalBiayaKirim',
            'summary_area',
            'summary_tujuan',
            'list_area',
            'gudang_ontime',
            'list_dist_channel',
            'gudang_delay',
            'customer_ontime',
            'customer_delay',
            'bongkar_ontime',
            'bongkar_delay',
            'summary_monitoring',
            'planner_ontime',
            'planner_delay',
            'ontime_rate',
            'delay_rate',
            'planner_armada',
            'planner_belum_armada',
            'armada_rate',
            'label',
            'value'
        ));
    }

    /* =========================================================
     * IMPORT EXCEL
     * ========================================================= */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        $import = new LogistikImport();

        try {
            Excel::import($import, $request->file('file'));
        } catch (\Throwable $e) {
            logger()->error('LOGISTIK IMPORT GAGAL TOTAL', ['error' => $e->getMessage()]);
            return back()->with('import_error', 'Import gagal total: ' . mb_substr($e->getMessage(), 0, 200));
        }

        return back()->with('import_result', [
            'inserted'        => $import->getInsertedCount(),
            'updated'         => $import->getUpdatedCount(),
            'success'         => $import->getImportedCount(),
            'skipped'         => $import->getSkippedCount(),
            'failed'          => $import->getFailedCount(),
            'failed_list'     => array_slice($import->getFailedList(), 0, 100),
            'shipments'       => count(array_unique($import->getAllNoShipmentInFile())),
            'ignored_headers' => $import->getIgnoredHeaders(),
            'missing_columns' => $import->getMissingColumns(),
            'unknown_planner' => $import->getUnknownPlannerCodes(),
        ]);
    }

    /* =========================================================
     * EXPORT EXCEL
     * ========================================================= */
    public function export(Request $request)
    {
        $query = LogistikPengiriman::query();

        $this->filterByDistChannel($query);
        $this->applyTanggalFilter($query, $request);

        $areas = array_values(array_filter((array) $request->input('area', []), 'strlen'));
        if (!empty($areas)) {
            $query->whereIn('area', $areas);
        }

        return Excel::download(
            new LogistikExportFull($query),
            'data_logistik_full_' . date('Ymd_His') . '.xlsx'
        );
    }

    /* =========================================================
     * DATA LOGISTIK — HALAMAN
     * ========================================================= */
    public function dataLogistik(Request $request)
    {
        $picList  = $this->cachedList('pic_monitoring');
        $areaList = $this->cachedList('area');

        return view('data_logistik', compact('picList', 'areaList'));
    }

    /* =========================================================
     * DATA LOGISTIK — ENDPOINT AJAX (SERVER-SIDE PAGINATION)
     * ========================================================= */
    public function dataLogistikAjax(Request $request)
    {
        $query = LogistikPengiriman::query();

        $this->filterByDistChannel($query);

        // ---------- FILTER DARI DROPDOWN CUSTOM ----------
        $this->applyTanggalFilter($query, $request);

        if ($request->filled('pic_monitoring')) {
            $query->where('pic_monitoring', $request->pic_monitoring);
        }

        $areas = array_values(array_filter((array) $request->input('area', []), 'strlen'));
        if (!empty($areas)) {
            $query->whereIn('area', $areas);
        }

        // ---------- FILTER DARI DASHBOARD MANAGER ----------
        // Prioritas: dash[...] dari JS. Fallback: query string halaman (Referer).
        $dash = $request->input('dash');

        if (!is_array($dash) || empty($dash)) {
            $dash = [];
            $refQuery = parse_url((string) $request->headers->get('referer'), PHP_URL_QUERY);
            if ($refQuery) {
                parse_str($refQuery, $dash);
            }
        }

     $dash = array_intersect_key($dash, array_flip([
    'tgl_basis', 'date', 'date_from', 'date_to', 'month', 'year',
    'area', 'dist_channel', 'pulau',
]));
        // area boleh array (area[]=...), sisanya harus scalar
        $dash = array_filter($dash, function ($v, $k) {
            return $k === 'area' ? (is_scalar($v) || is_array($v)) : is_scalar($v);
        }, ARRAY_FILTER_USE_BOTH);

        // Jangan terapkan filter tanggal dua kali kalau request sudah membawa filter yang sama
        if ($request->filled('date_from') || $request->filled('date_to')
            || $request->filled('month') || $request->filled('year')) {
            unset($dash['tgl_basis'], $dash['date_from'], $dash['date_to'], $dash['month'], $dash['year']);
        }
        if (!empty($areas)) {
            unset($dash['area']);
        }

        if (!empty($dash)) {
            $this->applyDashboardFilter($query, $dash);
        }

        $recordsTotal = $this->countGroups($query);

        // ---------- SEARCH BOX ----------
        $searchValue = trim((string) $request->input('search.value'));
        if ($searchValue !== '') {

            $cols = $this->searchableCols();

            $variants = [$searchValue];
            if (preg_match('/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/', $searchValue, $m)) {
                $variants[] = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
            }

            $f = fn($c) => "NULLIF(TRIM({$c}), '') IS NOT NULL";
            $e = fn($c) => "NULLIF(TRIM({$c}), '') IS NULL";

            $sisa = "DATEDIFF(DATE(estimasi_tiba), CURDATE())";
            $num  = fn($c) => "CAST(COALESCE(NULLIF(TRIM({$c}),''),0) AS DECIMAL(20,4))";
            $estAdmin = "DATE_ADD(rencana_kirim, INTERVAL (CAST(COALESCE(NULLIF(TRIM(transport_lead_time),''),0) AS UNSIGNED)
                 + CASE WHEN LOWER(TRIM(area)) = 'jawa barat' THEN 1 ELSE 0 END) DAY)";
$posisi = "CASE WHEN " . $e('tanggal_dpt_unit') . " THEN 'MENCARI UNIT'"
    . " WHEN " . implode(' AND ', array_map(fn($s) => $e("planning_loading{$s}"), $this->gudangSuffixes()))
    . " AND " . $e('tanggal_tiba') . " THEN 'PERJALANAN KE GUDANG'";

foreach (self::GUDANG as $s => $nama) {
    $posisi .= " WHEN " . $f("planning_loading{$s}") . " AND " . $e("tanggal_tiba_gudang{$s}")
             . " THEN 'PERJALANAN KE {$nama}'";
    $posisi .= " WHEN " . $f("planning_loading{$s}") . " AND " . $f("tanggal_tiba_gudang{$s}")
             . " AND " . $e("tanggal_keluar_gudang{$s}") . " THEN 'DI GUDANG {$nama}'";
}

$posisi .= " WHEN " . $e('tanggal_tiba') . " THEN 'PERJALANAN KE TUJUAN'"
         . " WHEN " . $f('tanggal_bongkar') . " THEN 'SUDAH SELESAI'"
         . " ELSE 'SUDAH TIBA TUJUAN' END";
            $computed = [
                // Ketersediaan Unit
                "CASE WHEN {$e('rencana_kirim')} OR {$e('tanggal_dpt_unit')} THEN 'BELUM DAPAT' ELSE 'SUDAH DAPAT' END",

           // Update Posisi Mobil
$posisi,

                // Alert
                "CASE
            WHEN {$f('tanggal_tiba')} THEN 'TIBA'
            WHEN {$e('estimasi_tiba')} THEN ''
            WHEN {$sisa} < 0 THEN CONCAT('Pending Tiba H+', ABS({$sisa}))
            WHEN {$sisa} <= 7 THEN CONCAT('H-', {$sisa})
            ELSE 'ON TRACK' END",

                // Status Bongkar
                "CASE
            WHEN {$f('tanggal_bongkar')} THEN 'Sudah Bongkar'
            WHEN {$f('tanggal_tiba')} THEN CONCAT('Pending Bongkar H+', GREATEST(0, DATEDIFF(CURDATE(), DATE(tanggal_tiba))))
            ELSE '' END",

                // Status Akhir
                "CASE
            WHEN {$e('tanggal_tiba')} THEN 'Dalam Perjalanan'
            WHEN {$e('tanggal_bongkar')} THEN 'Sudah Tiba Dalam Pembongkaran'
            WHEN UPPER(TRIM(sla_tiba)) = 'ON TIME' AND UPPER(TRIM(sla_bongkar)) = 'ON TIME' THEN 'Pengiriman On Time'
            ELSE 'Pengiriman Delay' END",

                // Status Alert
                "CASE
            WHEN UPPER(TRIM(sla_tiba)) = 'ON TIME' AND UPPER(TRIM(sla_bongkar)) = 'ON TIME' THEN 'Delivered Ontime'
            WHEN UPPER(TRIM(sla_tiba)) = 'DELAY'   AND UPPER(TRIM(sla_bongkar)) = 'ON TIME' THEN 'Delay Perjalanan'
            WHEN UPPER(TRIM(sla_tiba)) = 'ON TIME' AND UPPER(TRIM(sla_bongkar)) = 'DELAY'   THEN 'Delay Pembongkaran'
            WHEN UPPER(TRIM(sla_tiba)) = 'DELAY'   AND UPPER(TRIM(sla_bongkar)) = 'DELAY'   THEN 'Delivered Delay'
            ELSE 'Belum Selesai' END",

                // Pengiriman Optimal
                "CASE
            WHEN {$num('kubikasi')} <= 0 AND {$num('tonase')} <= 0 THEN ''
            WHEN ({$num('kubikasi')} > 0 AND {$num('total_kubik')} / {$num('kubikasi')} * 100 >= 85)
              OR ({$num('tonase')}   > 0 AND {$num('total_tonase')} / {$num('tonase')}   * 100 >= 85) THEN 'Optimal'
            ELSE 'Tidak Optimal' END",

                // Ontime/Delay Admin
                "CASE
            WHEN {$e('rencana_kirim')} THEN ''
            WHEN {$f('tanggal_tiba')} THEN CASE WHEN tanggal_tiba <= {$estAdmin} THEN 'On Time' ELSE 'Delay' END
            WHEN CURDATE() > DATE({$estAdmin}) THEN 'Delay'
            ELSE 'Belum Tiba' END",

                // Estimasi Tiba Di Customer (d-m-Y)
                "CASE WHEN {$e('rencana_kirim')} THEN '' ELSE DATE_FORMAT({$estAdmin}, '%d-%m-%Y') END",
            ];

            $query->where(function ($q) use ($variants, $cols, $computed) {
                foreach ($variants as $v) {
                    $like = "%{$v}%";
                    foreach ($cols as $c) {
                        $q->orWhereRaw("CAST(`{$c}` AS CHAR) LIKE ?", [$like]);
                    }
                    foreach ($computed as $x) {
                        $q->orWhereRaw("({$x}) LIKE ?", [$like]);
                    }
                }
            });
        }

        $recordsFiltered = $this->countGroups($query);
        $query = LogistikPengiriman::query()->whereIn('id', $this->groupIdsQuery($query));

        // ---------- SORTING ----------
    

     $orderColIndex = (int) $request->input('order.0.column', 0);
$orderDir      = strtolower($request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';
$orderColumn   = (string) $request->input("columns.{$orderColIndex}.name", '');
     

        // pastikan kolom benar-benar ada di tabel (hindari SQL error)
        if (!in_array($orderColumn, $this->searchableCols(), true)) {
            $orderColumn = 'tanggal_naik_logistik';
        }

        if ($orderColumn === 'no_shipment') {
            $query->orderByRaw("CAST(no_shipment AS UNSIGNED) {$orderDir}");
        } else {
            $query->orderBy($orderColumn, $orderDir)
                ->orderByRaw('CAST(no_shipment AS UNSIGNED) ASC');
        }

        $query->orderBy('tujuan')->orderBy('id');

        // ---------- PAGINATION ----------
        $start  = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 25);
        $length = $length > 0 ? min($length, 200) : 200;

        $rows = $query->skip($start)->take($length)->get();

        $shipments = $rows->pluck('no_shipment')->filter()->unique()->values();

        $groupAgg = LogistikPengiriman::query()
            ->whereIn('no_shipment', $shipments)
            ->select(
                'no_shipment',
                DB::raw("COALESCE(tujuan,'') as tujuan_key"),
                DB::raw('COUNT(*) as jml_do'),
                DB::raw('SUM(COALESCE(nilai_muatan,0)) as sum_nilai'),
                DB::raw('SUM(COALESCE(total_kubik,0)) as sum_kubik'),
                DB::raw('SUM(COALESCE(total_tonase,0)) as sum_tonase'),
                DB::raw('SUM(COALESCE(total_do_qty_car,0)) as sum_qty'),
                DB::raw('SUM(kirim_kontribusi) as sum_kontribusi'),
                DB::raw('MAX(COALESCE(biaya_kirim,0)) as sum_biaya'),
                DB::raw('SUM(COALESCE(selisih_qty,0)) as sum_selisih'),
                DB::raw('MAX(COALESCE(biaya_kuli,0)) as max_kuli')
            )
            ->groupBy('no_shipment', 'tujuan_key')
            ->get()
            ->keyBy(fn($g) => $g->no_shipment . '|' . $g->tujuan_key);

        // CR per shipment: jumlahkan semua tujuan dalam 1 shipment, lalu hitung CR-nya
        $crShipment = $groupAgg
            ->groupBy(fn($g) => $g->no_shipment)
            ->map(function ($grp) {
                $biaya  = $grp->sum(fn($g) => (float) ($g->sum_kontribusi ?? $g->sum_biaya));
                $muatan = $grp->sum(fn($g) => (float) $g->sum_nilai);

                return $muatan > 0 ? ($biaya / $muatan) * 100 : 0;
            });

        $shipmentAgg = Cache::remember('logistik_shipment_agg', 300, fn() => $this->shipmentAggregates());

        // ---------- BANGUN BARIS OUTPUT ----------
        $data = $rows->map(function ($r) use ($shipmentAgg, $groupAgg, $crShipment) {

            $g = $groupAgg[$r->no_shipment . '|' . ($r->tujuan ?? '')] ?? null;
            if ($g) {
                $r->nilai_muatan     = $g->sum_nilai;
                $r->total_kubik      = $g->sum_kubik;
                $r->total_tonase     = $g->sum_tonase;
                $r->kirim_kontribusi = $g->sum_kontribusi;
                $r->biaya_kirim      = $g->sum_biaya;

                $q = (float) $g->sum_qty;
                $r->total_do_qty_car = floor($q) == $q ? (int) $q : $q;

                $sumSelisih          = (float) $g->sum_selisih;
                $r->selisih_qty      = $sumSelisih != 0 ? $sumSelisih : null;
                $r->qty_monitoring   = $q - $sumSelisih;
                $biayaKuli           = (float) $g->max_kuli;
                $r->biaya_kuli       = $biayaKuli;
                $r->total_biaya_kuli = $r->qty_monitoring * $biayaKuli;
            }

            $estimasi      = $this->computeEstimasiDanAlert($r);
            $estimasiAdmin = $this->computeEstimasiAdmin($r);

            return [
                'tanggal_naik_logistik_fmt'   => $this->fmtDate($r->tanggal_naik_logistik),
                'rencana_kirim_fmt'           => $this->fmtDate($r->rencana_kirim),
                'transport_lead_time'         => $r->transport_lead_time,
                'nama_driver'                 => $r->nama_driver,
                'no_pol'                      => $r->no_pol,
                'planner'                     => $r->planner,
                'no_shipment'                 => $r->no_shipment,
                'status_pengiriman_badge'     => $this->badgeStatusPengiriman($r),
                'dist_channel_badge'          => $this->badgeDistChannel($r->dist_channel),
                'tujuan_html' => $r->no_shipment
                    ? '<a href="#" class="btn-tujuan" data-shipment="' . e($r->no_shipment) . '" data-tujuan="' . e($r->tujuan) . '">▶ '
                    . e($r->tujuan) . ($g && $g->jml_do > 1 ? ' <span class="badge gray">' . $g->jml_do . ' DO</span>' : '') . '</a>'
                    : e($r->tujuan),

                'area'                        => $r->area,
                'ketersediaan_unit_badge'     => $this->badgeKetersediaanUnit($r),
                'mobil'                       => $r->mobil,
                'total_do_qty_car'            => $r->total_do_qty_car,
                'nilai_muatan_fmt'            => 'Rp ' . number_format((float) $r->nilai_muatan, 0, ',', '.'),
                'kirim_kontribusi_fmt'        => $r->kirim_kontribusi !== null
                    ? 'Rp ' . number_format((float) $r->kirim_kontribusi, 0, ',', '.') : '-',
                'biaya_kirim_fmt'             => 'Rp ' . number_format((float) $r->biaya_kirim, 0, ',', '.'),
                'cr_fmt'                      => $this->formatCR($this->computeCR($r, $shipmentAgg)),
                'cr_shipment_fmt'             => $this->formatCR((float) ($crShipment[$r->no_shipment] ?? 0)),
                'kubikasi_fmt'                => $this->formatKubik($r->kubikasi ?? null),
                'tonase_fmt'                  => $this->formatTonase($r->tonase ?? null),
                'total_kubik_fmt'             => $this->formatKubik($r->total_kubik ?? null),
                'total_tonase_fmt'            => $this->formatTonase($r->total_tonase ?? null),
                'hasil_kubik_fmt'             => $this->formatHasilPersen($this->computeHasilKubik($r)),
                'hasil_tonase_fmt'            => $this->formatHasilPersen($this->computeHasilTonase($r)),
                'pengiriman_optimal_badge'    => $this->badgePengirimanOptimal($r, $shipmentAgg),
                'reason_optimal'              => $r->reason_optimal,
                'pengiriman_optimal_customer_badge' => $this->badgePengirimanOptimalCustomer($r),
                'reason_optimal_customer'     => $r->reason_optimal_customer ?: '-',
                'kategori_ekspedisi_badge'    => $this->badgeKategoriEkspedisi($r->kategori_ekspedisi),
                'ekpedisi'                    => $r->ekpedisi,
                'tanggal_dpt_unit_fmt'        => $this->fmtDate($r->tanggal_dpt_unit),
                'lama_waktu_pencarian'        => $r->lama_waktu_pencarian ?? '-',
                'sla_dapat_mobil_badge'       => $this->badgeSlaDapatMobil($r->sla_dapat_mobil),

                // GUDANG 1 (KACS)
                'planning_loading_fmt'        => $this->fmtDate($r->planning_loading),
                'tanggal_tiba_gudang_fmt'     => $this->fmtDate($r->tanggal_tiba_gudang),
                'waktu_tiba_gudang_fmt'       => $this->fmtWaktu($r->waktu_tiba_gudang),
                'tanggal_keluar_gudang_fmt'   => $this->fmtDate($r->tanggal_keluar_gudang),
                'waktu_keluar_gudang_fmt'     => $this->fmtWaktu($r->waktu_keluar_gudang),
                'reason_gudang'               => $r->reason_gudang ?: '-',
                'durasi_gudang1_fmt'          => $this->computeDurasiGudang($r->tanggal_tiba_gudang, $r->tanggal_keluar_gudang),
                'status_gudang1_badge'        => $this->badgeStatusOnTimeDelayByDate($r->tanggal_tiba_gudang, $r->tanggal_keluar_gudang),
                'sla_loading1_badge'          => $this->badgeSlaLoadingClean($r->tanggal_tiba_gudang, $r->tanggal_keluar_gudang),

                // GUDANG 2 (SENTUL)
                'planning_loading_2'          => $this->fmtDate($r->planning_loading_2 ?? null),
                'tanggal_tiba_gudang_2'       => $this->fmtDate($r->tanggal_tiba_gudang_2 ?? null),
                'waktu_tiba_gudang_2_fmt'     => $this->fmtWaktu($r->waktu_tiba_gudang_2),
                'tanggal_keluar_gudang_2'     => $this->fmtDate($r->tanggal_keluar_gudang_2 ?? null),
                'waktu_keluar_gudang_2_fmt'   => $this->fmtWaktu($r->waktu_keluar_gudang_2),
                'reason_gudang_2'             => $r->reason_gudang_2 ?: '-',
                'lama_digudang_2'             => $this->computeDurasiGudang($r->tanggal_tiba_gudang_2, $r->tanggal_keluar_gudang_2),
                'status_gudang2_badge'        => $this->badgeStatusOnTimeDelayByDate($r->tanggal_tiba_gudang_2, $r->tanggal_keluar_gudang_2),
                'sla_loading2_badge'          => $this->badgeSlaLoadingClean($r->tanggal_tiba_gudang_2, $r->tanggal_keluar_gudang_2),

                // GUDANG 3 (CCIE)
                ...$this->gudangExtraCells($r),
                'planning_loading_3'          => $this->fmtDate($r->planning_loading_3 ?? null),
                'tanggal_tiba_gudang_3'       => $this->fmtDate($r->tanggal_tiba_gudang_3 ?? null),
                'waktu_tiba_gudang_3_fmt'     => $this->fmtWaktu($r->waktu_tiba_gudang_3),
                'tanggal_keluar_gudang_3'     => $this->fmtDate($r->tanggal_keluar_gudang_3 ?? null),
                'waktu_keluar_gudang_3_fmt'   => $this->fmtWaktu($r->waktu_keluar_gudang_3),
                'reason_gudang_3'             => $r->reason_gudang_3 ?: '-',
                'lama_digudang_3'             => $this->computeDurasiGudang($r->tanggal_tiba_gudang_3, $r->tanggal_keluar_gudang_3),
                'status_gudang3_badge'        => $this->badgeStatusOnTimeDelayByDate($r->tanggal_tiba_gudang_3, $r->tanggal_keluar_gudang_3),
                'sla_loading3_badge'          => $this->badgeSlaLoadingClean($r->tanggal_tiba_gudang_3, $r->tanggal_keluar_gudang_3),

                'pic_monitoring'              => $r->pic_monitoring,
                'nama_kapal'                  => $r->nama_kapal,
                'etd'                         => $r->etd,
                'eta'                         => $r->eta,
                'status_kendaraan_badge'      => $this->badgeStatusKendaraan($r->status_kendaraan ?? null),
                'alert_badge'                 => $estimasi['alert_html'],

                'act_urutan_bongkar'          => $r->act_urutan_bongkar,
                'qty_monitoring'              => $r->qty_monitoring,
                'biaya_kuli'                  => $r->biaya_kuli ? 'Rp ' . number_format($r->biaya_kuli, 0, ',', '.') : '',
                'total_biaya_kuli'            => $r->total_biaya_kuli ? 'Rp ' . number_format($r->total_biaya_kuli, 0, ',', '.') : '',
                'selisih_qty'                 => $r->selisih_qty,
                'remarks_qty'                 => $r->remarks_qty,
                'act_pgi_date'                => $r->act_pgi_date ? Carbon::parse($r->act_pgi_date)->format('d/m/Y') : '-',

                'atd'                         => $r->atd,
                'ata'                         => $r->ata,
                'estimasi_tiba_fmt'           => $estimasi['estimasi_show'],
                'tanggal_tiba_fmt'            => $this->fmtDate($r->tanggal_tiba),
                'waktu_tiba_fmt'              => $this->fmtWaktu($r->waktu_tiba ?? null),
                'lama_perjalanan'             => $this->computeLamaPerjalananSimple($r),

                'sla_tiba_badge'              => $this->badgeOnTimeDelay($r->sla_tiba),
                'tanggal_bongkar_fmt'         => $this->fmtDate($r->tanggal_bongkar),
                'waktu_bongkar_fmt'           => $this->fmtWaktu($r->waktu_bongkar ?? null),
                'status_bongkar_badge'        => $this->badgeStatusBongkar($r),
                'overstay_days'               => $this->formatHari($r->overstay_days),
                'sla_bongkar_badge'           => $this->badgeOnTimeDelay($r->sla_bongkar),
                'reason_tiba'                 => $r->reason_tiba,
                'reason_bongkar'              => $r->reason_bongkar,
                'status_akhir_badge'          => $this->badgeStatusAkhir($r),
                'status_alert_badge'          => $this->badgeStatusAlert($r),

                'remarks'                     => $r->remarks,
                'route'                       => $r->route,
                'route_awal'                  => $r->route ? explode('-', trim($r->route))[0] : '-',
                'pulau'                       => $r->pulau,
                'via_kirim'                   => $r->via_kirim,
                'estimasi_admin_fmt'          => $estimasiAdmin ? $estimasiAdmin->format('d-m-Y') : '-',
                'estimasi_admin_status_badge' => $this->badgeEstimasiAdmin($r),
                'create_on_fmt'               => $this->fmtDate($r->create_on),
                'no_delivery'                 => e($r->no_delivery) ?: '-',
                'customer_reference'          => e($r->customer_reference) ?: '-',
                'sales_document'              => e($r->sales_document) ?: '-',
                'contract_end_date_fmt'       => $this->fmtDate($r->contract_end_date),
                'no_do'                       => e($r->no_do) ?: '-',
                'tanggal_shipment_fmt'        => $this->fmtDate($r->tanggal_shipment),
                // periode_shipment berisi string "MM-YYYY", tampilkan apa adanya
                'periode_shipment_fmt'        => $r->periode_shipment ?: '-',
            ];
        });

        return response()->json([
            'draw'            => (int) $request->input('draw', 1),
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    public function dataLogistikDetail(Request $request)
    {
        $request->validate([
            'no_shipment' => 'required|string',
            'tujuan'      => 'nullable|string',
        ]);

        $q = LogistikPengiriman::query()->where('no_shipment', $request->no_shipment);
        $this->filterByDistChannel($q);

        $tujuan = (string) $request->tujuan;
        $tujuan !== ''
            ? $q->where('tujuan', $tujuan)
            : $q->where(fn($x) => $x->whereNull('tujuan')->orWhere('tujuan', ''));

        $data = $q->orderBy('sales_document')->orderBy('id')->get()->map(fn($r) => [
            'sales_document'       => $r->sales_document ?: '-',
            'customer_reference'   => $r->customer_reference ?: '-',
            'contract_end_date'    => $this->fmtDate($r->contract_end_date),
            'no_do'                => $r->no_do ?: '-',
            'qty'                  => $r->total_do_qty_car,
            'nilai_muatan'         => 'Rp ' . number_format((float) $r->nilai_muatan, 0, ',', '.'),
            'kirim_kontribusi'     => $r->kirim_kontribusi !== null
                ? 'Rp ' . number_format((float) $r->kirim_kontribusi, 0, ',', '.') : '-',
            'kirim_kontribusi_raw' => (float) $r->kirim_kontribusi,
            'total_kubik'          => $this->formatKubik($r->total_kubik),
            'total_tonase'         => $this->formatTonase($r->total_tonase),
        ]);

        return response()->json(['data' => $data]);
    }

    /** Daftar kolom asli tabel (cache 1 jam) */
    private function searchableCols(): array
    {
        return Cache::remember('logistik_searchable_cols', 3600, function () {
            return array_values(array_diff(
                Schema::getColumnListing('logistik_pengiriman'),
                ['id', 'created_at', 'updated_at']
            ));
        });
    }

    /** Subquery: 1 id wakil (MIN id) per shipment+tujuan dari query yang sudah terfilter */
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

    private function fmtWaktu($value): string
    {
        if (empty($value)) {
            return '-';
        }
        return substr((string) $value, 0, 5);
    }

    private function formatHari($value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }
        return $value . ' Hari';
    }

    /* =========================================================
     * ARCHIVE / DELETE ALL
     * ========================================================= */
    public function archiveAll()
    {
        $cols = array_values(array_diff(
            array_intersect(
                Schema::getColumnListing('logistik_pengiriman'),
                Schema::getColumnListing('logistik_storage')
            ),
            ['id']
        ));
        $list = '`' . implode('`,`', $cols) . '`';

        $moved = 0;

        DB::transaction(function () use ($list, &$moved) {
            DB::insert("INSERT INTO logistik_storage ({$list}) SELECT {$list} FROM logistik_pengiriman");
            $moved = DB::table('logistik_pengiriman')->delete();
        });

        Cache::forget('logistik_shipment_agg');
        foreach (['area', 'dist_channel', 'pic_monitoring'] as $c) {
            Cache::forget("list_{$c}");
        }

        return back()->with('success', "{$moved} baris berhasil dipindahkan ke Storage");
    }

    public function deleteAll()
    {
        DB::table('logistik_pengiriman')->delete();

        return back()->with('success', 'Semua data berhasil dihapus');
    }

    public function monitoring()
    {
        $logistik = LogistikPengiriman::latest()->get();
        $areaList = $this->cachedList('area');

        return view('monitoring.data_monitoring', compact('logistik', 'areaList'));
    }

    /* =========================================================
     * ARMADA
     * ========================================================= */
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
                $tibaGudang = $this->getTibaGudangTerdekat($row);

                if ($row->tanggal_dpt_unit && $tibaGudang) {
                    $awal = new \DateTime(date('Y-m-d H:i:s', strtotime($row->tanggal_dpt_unit)));
                    $akhir = new \DateTime(date('Y-m-d H:i:s', strtotime($tibaGudang)));

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

        return view('planner.armada', compact('logistik'));
    }

    public function edit($id)
    {
        $data['logistik'] = LogistikPengiriman::findOrFail($id);
        return view('edit', $data);
    }

    public function update(Request $request, $id)
    {
        $logistik = LogistikPengiriman::findOrFail($id);
        $logistik->update($request->all());

        return redirect('/logistik')->with('success', 'Data berhasil diupdate');
    }

    public function destroy($id)
    {
        LogistikPengiriman::findOrFail($id)->delete();
        return redirect('/logistik')->with('success', 'Data berhasil dihapus');
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

        return view('planner.belum_armada', compact('logistik'));
    }

    public function store(Request $request)
    {
        LogistikPengiriman::create($request->all());
        return back()->with('success', 'Data berhasil ditambah');
    }

    public function delete($id)
    {
        LogistikPengiriman::findOrFail($id)->delete();
        return back()->with('success', 'Data dihapus');
    }

    public function chartStatus()
    {
        return LogistikPengiriman::select('status_akhir', DB::raw('COUNT(*) as total'))
            ->groupBy('status_akhir')
            ->get();
    }

    public function slaOntime(Request $request)
    {
        $query = LogistikPengiriman::query();

        if ($request->bulan) {
            $query->whereMonth('tanggal_naik_logistik', $request->bulan);
        }

        if ($request->tahun) {
            $query->whereYear('tanggal_naik_logistik', $request->tahun);
        }

        if ($request->area) {
            $query->where('area', $request->area);
        }

        $list = $query->where('status_akhir', 'On Time')->get();
        $list_area = $this->cachedList('area');
        $title = "SLA ONTIME";

        return view('sla_ontime', compact('list', 'list_area', 'title'));
    }

    public function index()
    {
        $total = LogistikPengiriman::count();

        $ontime = LogistikPengiriman::whereIn('status_akhir', ['On Time', 'Ontime'])->count();
        $delay  = LogistikPengiriman::whereIn('status_akhir', ['Delay', 'Critical Delay'])->count();

        $summary_area = LogistikPengiriman::select('area', DB::raw('COUNT(*) as total'))
            ->groupBy('area')
            ->orderByDesc('total')
            ->get();

        return view('dashboard', compact('total', 'ontime', 'delay', 'summary_area'));
    }

    public function slaDelay(Request $request)
    {
        $query = LogistikPengiriman::query();

        if ($request->bulan) {
            $query->whereMonth('tanggal_naik_logistik', $request->bulan);
        }

        if ($request->tahun) {
            $query->whereYear('tanggal_naik_logistik', $request->tahun);
        }

        if ($request->area) {
            $query->where('area', $request->area);
        }

        $list = $query->whereIn('status_akhir', ['Delay', 'Critical Delay'])->get();
        $list_area = $this->cachedList('area');
        $title = "SLA DELAY";

        return view('sla_delay', compact('list', 'list_area', 'title'));
    }

    public function dashboardPlanner()
    {
        $total = DB::table('logistik_pengiriman')->count();

        $ontime = DB::table('logistik_pengiriman')
            ->whereIn('status_akhir', ['On Time', 'Ontime'])
            ->count();

        $delay = DB::table('logistik_pengiriman')
            ->whereIn('status_akhir', ['Delay', 'Critical Delay'])
            ->count();

        return view('planner.dashboard', compact('total', 'ontime', 'delay'));
    }

    public function ontimeCustomer(Request $request)
    {
        $bulan = $request->bulan;
        $tahun = $request->tahun;

        $query = DB::table('logistik_pengiriman_new');

        if ($bulan) {
            $query->whereMonth('tanggal_tiba', $bulan);
        }

        if ($tahun) {
            $query->whereYear('tanggal_tiba', $tahun);
        }

        $logistik = $query->get();

        foreach ($logistik as $row) {
            $row->status_customer = 'DELAY';

            if (!empty($row->rencana_kirim) && !empty($row->tanggal_tiba)) {
                if (strtotime($row->tanggal_tiba) <= strtotime($row->rencana_kirim)) {
                    $row->status_customer = 'ONTIME';
                }
            }
        }

        return view('logistik.ontime_customer', compact('logistik'));
    }

    public static function kpi($query)
    {
        return [
            'total_data' => (clone $query)->count(),

            'gudang_ontime' => (clone $query)->where(function ($q) {
                $q->where('sla_loading', 'H+0')->orWhere('status', 'On Time');
            })->count(),

            'gudang_delay' => (clone $query)->where(function ($q) {
                $q->where('sla_loading', 'H+1')->orWhere('status', 'Delay');
            })->count(),
        ];
    }

    public static function summaryArea($query)
    {
        return (clone $query)
            ->select(
                'area',
                DB::raw('COUNT(*) as total_shipment'),
                DB::raw('SUM(biaya_kirim) as total_biaya'),
                DB::raw('SUM(nilai_muatan) as total_muatan')
            )
            ->groupBy('area')
            ->get();
    }

    public function dashboardSpv()
    {
        $data = LogistikPengiriman::all();
        return view('spv.dashboard', compact('data'));
    }

    public function dashboardManager(Request $request)
    {
        $date  = $request->date;
        $month = $request->month;
        $year  = $request->year;

        $base = DB::table('logistik_pengiriman');

        if ($date) {
            $base->whereDate('created_at', $date);
        }

        if ($month) {
            $base->whereMonth('created_at', substr($month, 5, 2))
                ->whereYear('created_at', substr($month, 0, 4));
        }

        if ($year) {
            $base->whereYear('created_at', $year);
        }

        $total_data = (clone $base)->count();

        $gudang_ontime = (clone $base)->whereRaw("LOWER(status) LIKE '%on%'")->count();
        $gudang_delay  = (clone $base)->whereRaw("LOWER(status) LIKE '%delay%'")->count();

        $customer_ontime = (clone $base)
            ->whereNotNull('tanggal_tiba')
            ->where('tanggal_tiba', '!=', '')
            ->whereIn('status_akhir', ['On Time', 'ONTIME'])
            ->count();

        $customer_delay = (clone $base)
            ->whereNotNull('tanggal_tiba')
            ->where('tanggal_tiba', '!=', '')
            ->whereIn('status_akhir', ['Delay', 'DELAY', 'Critical Delay'])
            ->count();

        $bongkar_ontime = (clone $base)
            ->whereNotNull('tanggal_bongkar')
            ->where('tanggal_bongkar', '!=', '')
            ->where('sla_bongkar', 'H+0')
            ->count();

        $bongkar_delay = (clone $base)
            ->whereNotNull('tanggal_bongkar')
            ->where('tanggal_bongkar', '!=', '')
            ->whereRaw("
                LOWER(sla_bongkar) LIKE '%delay%'
                OR LOWER(sla_bongkar) LIKE '%h+1%'
                OR LOWER(sla_bongkar) LIKE '%h+2%'
                OR LOWER(sla_bongkar) LIKE '%h>2%'
            ")
            ->count();

        $summary_area = (clone $base)
            ->select(
                'area',
                DB::raw('COUNT(*) as total_shipment'),
                DB::raw("SUM(CASE WHEN biaya_kirim IS NULL THEN 0 ELSE biaya_kirim END) as total_biaya"),
                DB::raw("SUM(CASE WHEN nilai_muatan IS NULL THEN 0 ELSE nilai_muatan END) as total_muatan")
            )
            ->groupBy('area')
            ->get();

        $summary_tujuan = (clone $base)
            ->select(
                'tujuan',
                DB::raw('COUNT(*) as total_shipment'),
                DB::raw("SUM(biaya_kirim) as total_biaya"),
                DB::raw("SUM(nilai_muatan) as total_muatan")
            )
            ->groupBy('tujuan')
            ->get();

        $ekspedisi = (clone $base)
            ->select('kategori_ekspedisi', DB::raw('COUNT(*) as total'))
            ->groupBy('kategori_ekspedisi')
            ->get();

        $label = $ekspedisi->pluck('kategori_ekspedisi');
        $value = $ekspedisi->pluck('total');

        $totalNilaiMuatan = (clone $base)->sum('nilai_muatan');
        $totalBiayaKirim  = (clone $base)->sum('biaya_kirim');

        $planner_ontime = $gudang_ontime;
        $planner_delay  = $gudang_delay;
        $planner_armada = (clone $base)->whereNotNull('mobil')->count();
        $planner_belum_armada = (clone $base)->whereNull('mobil')->count();

        $total_tiba = (clone $base)
            ->whereNotNull('tanggal_tiba')
            ->where('tanggal_tiba', '!=', '')
            ->count();

        $total_bongkar = (clone $base)
            ->whereNotNull('tanggal_bongkar')
            ->where('tanggal_bongkar', '!=', '')
            ->count();

        $summary_monitoring = [
            'tiba_ontime'    => $total_tiba ? round(($customer_ontime / $total_tiba) * 100, 2) : 0,
            'tiba_delay'     => $total_tiba ? round(($customer_delay / $total_tiba) * 100, 2) : 0,
            'bongkar_ontime' => $total_bongkar ? round(($bongkar_ontime / $total_bongkar) * 100, 2) : 0,
            'bongkar_delay'  => $total_bongkar ? round(($bongkar_delay / $total_bongkar) * 100, 2) : 0,
        ];

        return view('manager.dashboard', compact(
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
            'label',
            'value',
            'planner_ontime',
            'planner_delay',
            'planner_armada',
            'planner_belum_armada',
            'summary_monitoring'
        ));
    }

    private function dashboardData()
    {
        return [
            'total' => DB::table('logistik_pengiriman')->count(),
            'ontime' => DB::table('logistik_pengiriman')
                ->whereIn('status_akhir', ['On Time', 'Ontime'])->count(),
            'delay' => DB::table('logistik_pengiriman')
                ->whereIn('status_akhir', ['Delay', 'Critical Delay'])->count(),
        ];
    }

    /* =========================================================================
     *  HELPER / PRESENTER METHODS
     * ========================================================================= */

    /** Cache dropdown list 1 jam */
    private function cachedList(string $column)
    {
        return Cache::remember("list_{$column}", 3600, function () use ($column) {
            return DB::table('logistik_pengiriman')
                ->select($column)
                ->whereNotNull($column)
                ->distinct()
                ->orderBy($column)
                ->pluck($column);
        });
    }

    private function fmtDate($value, string $format = 'd-m-Y')
    {
        if (empty($value) || $value === 'mm/dd/yyyy') {
            return '-';
        }
        $ts = strtotime((string) $value);
        return $ts === false ? (string) $value : date($format, $ts);
    }

    /** Agregat per no_shipment (dipakai untuk Cost Ratio & optimal). */
    private function shipmentAggregates(): array
    {
        return DB::table('logistik_pengiriman')
            ->select(
                'no_shipment',
                DB::raw('SUM(nilai_muatan) as total_muatan'),
                DB::raw('MAX(biaya_kirim) as total_biaya'),
                DB::raw('SUM(COALESCE(total_kubik, 0)) as sum_kubik'),
                DB::raw('SUM(COALESCE(total_tonase, 0)) as sum_tonase'),
                DB::raw('MAX(kubikasi) as kubikasi'),
                DB::raw('MAX(tonase) as tonase')
            )
            ->whereNotNull('no_shipment')
            ->where('no_shipment', '!=', '')
            ->groupBy('no_shipment')
            ->get()
            ->keyBy('no_shipment')
            ->map(fn($g) => [
                'total_muatan' => (float) $g->total_muatan,
                'total_biaya'  => (float) $g->total_biaya,
                'sum_kubik'    => (float) $g->sum_kubik,
                'sum_tonase'   => (float) $g->sum_tonase,
                'kubikasi'     => (float) $g->kubikasi,
                'tonase'       => (float) $g->tonase,
            ])
            ->toArray();
    }

    private function computeCR($r, array $shipmentAgg): float
    {
        $muatan = (float) $r->nilai_muatan;
        $biaya  = (float) ($r->kirim_kontribusi ?? $r->biaya_kirim);

        return $muatan > 0 ? ($biaya / $muatan) * 100 : 0;
    }

    private function formatPersen($value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }
        return number_format((float) $value, 2, ',', '.') . '%';
    }

    private function formatKubik($value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }
        return number_format((float) $value, 2, ',', '.');
    }

    private function formatTonase($value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }
        return number_format((float) $value, 2, ',', '.');
    }

    private function computeHasilKubik($r): ?float
    {
        $totalMuatan = (float) ($r->total_kubik ?? 0);
        $kapasitas   = (float) ($r->kubikasi ?? 0);

        if ($kapasitas <= 0) {
            return null;
        }

        return ($totalMuatan / $kapasitas) * 100;
    }

    private function computeHasilTonase($r): ?float
    {
        $totalMuatan = (float) ($r->total_tonase ?? 0);
        $kapasitas   = (float) ($r->tonase ?? 0);

        if ($kapasitas <= 0) {
            return null;
        }

        return ($totalMuatan / $kapasitas) * 100;
    }

    private function formatHasilPersen(?float $value): string
    {
        if ($value === null) {
            return '-';
        }
        return number_format($value, 2, ',', '.') . '%';
    }

    /**
     * PER SHIPMENT: hasil kubik/tonase semua baris di no_shipment yang sama dijumlahkan.
     * Optimal jika salah satunya >= 85%.
     */
    private function badgePengirimanOptimal($r, array $shipmentAgg = []): string
    {
        $no  = trim((string) $r->no_shipment);
        $agg = ($no !== '' && isset($shipmentAgg[$no])) ? $shipmentAgg[$no] : null;

        if ($agg && isset($agg['sum_kubik'])) {
            $kapKubik    = $agg['kubikasi'];
            $kapTonase   = $agg['tonase'];
            $hasilKubik  = $kapKubik  > 0 ? ($agg['sum_kubik']  / $kapKubik)  * 100 : null;
            $hasilTonase = $kapTonase > 0 ? ($agg['sum_tonase'] / $kapTonase) * 100 : null;
        } else {
            $hasilKubik  = $this->computeHasilKubik($r);
            $hasilTonase = $this->computeHasilTonase($r);
        }

        if ($hasilKubik === null && $hasilTonase === null) {
            return '<span class="badge gray">-</span>';
        }

        $isOptimal = ($hasilKubik !== null && $hasilKubik >= 85)
            || ($hasilTonase !== null && $hasilTonase >= 85);

        return $isOptimal
            ? '<span class="badge green">✅ Optimal</span>'
            : '<span class="badge orange">⚠️ Tidak Optimal</span>';
    }

    /** PER CUSTOMER (per baris/tujuan) */
    private function badgePengirimanOptimalCustomer($r): string
    {
        $hasilKubik  = $this->computeHasilKubik($r);
        $hasilTonase = $this->computeHasilTonase($r);

        if ($hasilKubik === null && $hasilTonase === null) {
            return '<span class="badge gray">-</span>';
        }

        $isOptimal = ($hasilKubik !== null && $hasilKubik >= 85)
            || ($hasilTonase !== null && $hasilTonase >= 85);

        return $isOptimal
            ? '<span class="badge green">✅ Optimal</span>'
            : '<span class="badge orange">⚠️ Tidak Optimal</span>';
    }

    private function formatCR(float $cr): string
    {
        return $cr > 0
            ? '<span class="text-primary font-weight-bold" style="color:#0056b3;font-weight:bold;">' . number_format($cr, 4, ',', '.') . '%</span>'
            : '<span class="text-muted" style="color:#9e9e9e;font-size:11px;">0,0000%</span>';
    }

    private function badgeSLA($sla): string
    {
        $sla = trim((string) $sla);

        if ($sla === '' || $sla === '-' || $sla === 'null') {
            return '<span class="badge badge-gray">-</span>';
        }

        $slaLower = strtolower($sla);

        if (in_array($slaLower, ['sesuai sla', 'on time', 'ontime', 'h+0'])) {
            return '<span class="badge badge-green">' . e($sla) . '</span>';
        }

        if (preg_match('/^h\+1$/i', $sla)) {
            return '<span class="badge badge-orange">' . e($sla) . '</span>';
        }

        if (preg_match('/^h\+\d+$/i', $sla)) {
            return '<span class="badge badge-red">' . e($sla) . '</span>';
        }

        return '<span class="badge badge-gray">' . e($sla) . '</span>';
    }

    private function badgeOnTimeDelay($value): string
    {
        $value = trim((string) $value);

        if ($value === '' || $value === '-') {
            return '-';
        }

        if (strtolower($value) === 'on time') {
            return '<span class="badge green">On Time</span>';
        }

        return '<span class="badge red">' . e($value) . '</span>';
    }

    private function badgeSlaDapatMobil($sla): string
    {
        $sla = trim((string) $sla);

        if (empty($sla)) {
            return '<span class="badge gray">-</span>';
        }

        if (strtolower($sla) === 'on time' || strtoupper($sla) === 'H+0') {
            return '<span class="badge green">' . e($sla) . '</span>';
        }

        if (strtolower($sla) === 'delay') {
            return '<span class="badge red">Delay</span>';
        }

        if (preg_match('/h\+1/i', $sla)) {
            return '<span class="badge orange">' . e($sla) . '</span>';
        }

        if (preg_match('/h\+/i', $sla)) {
            return '<span class="badge red">' . e($sla) . '</span>';
        }

        return '<span class="badge gray">' . e($sla) . '</span>';
    }

    private function badgeDistChannel($channel): string
    {
        $channel = trim((string) $channel);

        $classes = [
            'badge-green',
            'badge-blue',
            'badge-orange',
            'badge-red',
            'badge-purple',
            'badge-pink',
            'badge-cyan',
            'badge-yellow',
        ];

        $badgeClass = $channel
            ? $classes[abs(crc32($channel)) % count($classes)]
            : 'badge-default';

        return '<span class="badge ' . $badgeClass . '">' . e($channel ?: '-') . '</span>';
    }

    private function badgeKetersediaanUnit($r): string
    {
        if (empty($r->rencana_kirim) || empty($r->tanggal_dpt_unit)) {
            return '<span class="badge-status status-belum">BELUM DAPAT</span>';
        }

        return '<span class="badge-status status-sudah">SUDAH DAPAT</span>';
    }

    private function badgeKategoriEkspedisi($kategori): string
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
private function gudangList($r): array
{
    $out = [];
    foreach (self::GUDANG as $s => $nama) {
        $out[] = [
            'nama'     => $nama,
            'planning' => $r->{"planning_loading{$s}"},
            'tiba'     => $r->{"tanggal_tiba_gudang{$s}"},
            'keluar'   => $r->{"tanggal_keluar_gudang{$s}"},
        ];
    }
    return $out;
}
    private function badgeStatusPengiriman($r): string
    {
        $dpt = $r->tanggal_dpt_unit;
        $tibaAkhir = $r->tanggal_tiba;
        $bongkarAkhir = $r->tanggal_bongkar;

    $gudang = collect($this->gudangList($r))
    ->filter(fn($g) => !empty($g['planning']))
    ->sortBy(fn($g) => strtotime($g['planning']))
    ->values();

$adaPlanningGudang = $gudang->count() > 0;

        $adaPlanningGudang = $gudang->count() > 0;
        $statusGudang = null;

        foreach ($gudang as $g) {
            if (empty($g['tiba'])) {
                $statusGudang = ['status' => 'PERJALANAN KE ' . $g['nama'], 'badge' => 'yellow'];
                break;
            }

            if (!empty($g['tiba']) && empty($g['keluar'])) {
                $statusGudang = ['status' => 'DI GUDANG ' . $g['nama'], 'badge' => 'blue'];
                break;
            }
        }

        if (empty($dpt)) {
            $status = 'MENCARI UNIT';
            $badge = 'red';
        } elseif (!$adaPlanningGudang && empty($tibaAkhir)) {
            $status = 'PERJALANAN KE GUDANG';
            $badge = 'orange';
        } elseif ($statusGudang) {
            $status = $statusGudang['status'];
            $badge = $statusGudang['badge'];
        } elseif (empty($tibaAkhir)) {
            $status = 'PERJALANAN KE TUJUAN';
            $badge = 'yellow';
        } elseif (!empty($tibaAkhir) && !empty($bongkarAkhir)) {
            $status = 'SUDAH SELESAI';
            $badge = 'green';
        } elseif (!empty($tibaAkhir)) {
            $status = 'SUDAH TIBA TUJUAN';
            $badge = 'pink';
        } else {
            $status = '-';
            $badge = 'gray';
        }

        return '<span class="badge ' . $badge . '">' . e($status) . '</span>';
    }

   private function getStatusGudangAktif($r): ?array
{
    $gudang = collect($this->gudangList($r))
        ->filter(fn($g) => !empty($g['planning']))
        ->sortBy(fn($g) => strtotime($g['planning']))
        ->values();

    foreach ($gudang as $g) {
        if (empty($g['tiba'])) {
            return ['status' => 'PERJALANAN KE ' . $g['nama']];
        }
    }

    return null;
}

    private function computeEstimasiDanAlert($r): array
    {
        $statusGudang = $this->getStatusGudangAktif($r);

        if ($statusGudang) {
            return [
                'estimasi_show' => $statusGudang['status'],
                'alert_html' => '<span class="badge gray">-</span>',
            ];
        }

        $estimasi = !empty($r->estimasi_tiba) ? strtotime($r->estimasi_tiba) : null;
        $estimasiShow = $estimasi ? date('d-m-Y', $estimasi) : '-';

        if ($r->tanggal_tiba) {
            return [
                'estimasi_show' => $estimasiShow,
                'alert_html' => '<span class="badge green">✅ TIBA</span>',
            ];
        }

        if (!$estimasi) {
            return [
                'estimasi_show' => $estimasiShow,
                'alert_html' => '<span class="badge gray">-</span>',
            ];
        }

        $today = strtotime(date('Y-m-d'));
        $hariSisa = floor(($estimasi - $today) / 86400);

        if ($hariSisa < 0) {
            $alertText = 'Pending Tiba H+' . abs($hariSisa);
            $alertClass = 'red';
        } elseif ($hariSisa == 0) {
            $alertText = 'H-0';
            $alertClass = 'red';
        } elseif ($hariSisa == 1) {
            $alertText = 'H-1';
            $alertClass = 'red';
        } elseif ($hariSisa == 2 || $hariSisa == 3) {
            $alertText = 'H-' . $hariSisa;
            $alertClass = 'orange';
        } elseif ($hariSisa <= 7) {
            $alertText = 'H-' . $hariSisa;
            $alertClass = 'blue';
        } else {
            $alertText = 'ON TRACK';
            $alertClass = 'green';
        }

        return [
            'estimasi_show' => $estimasiShow,
            'alert_html' => '<span class="badge ' . $alertClass . '">' . e($alertText) . '</span>',
        ];
    }

    private function badgeStatusBongkar($r): string
    {
        if ($r->tanggal_bongkar) {
            return '<span class="badge green">Sudah Bongkar</span>';
        }

        if ($r->tanggal_tiba) {
            $tanggalTiba = strtotime(date('Y-m-d', strtotime($r->tanggal_tiba)));
            $today = strtotime(date('Y-m-d'));
            $hariBongkar = max(0, floor(($today - $tanggalTiba) / 86400));

            $class = $hariBongkar == 0 ? 'orange' : 'red';

            return '<span class="badge ' . $class . '">Pending Bongkar H+' . $hariBongkar . '</span>';
        }

        return '-';
    }

    private function badgeStatusAkhir($r): string
    {
        $slaTiba = strtoupper(trim($r->sla_tiba ?? ''));
        $slaBongkar = strtoupper(trim($r->sla_bongkar ?? ''));

        if (empty($r->tanggal_tiba)) {
            return '<span class="status-badge status-transit">🚚 Dalam Perjalanan</span>';
        }

        if (!empty($r->tanggal_tiba) && empty($r->tanggal_bongkar)) {
            return '<span class="status-badge status-unloading">📦 Sudah Tiba<br>Dalam Pembongkaran</span>';
        }

        if ($slaTiba === 'ON TIME' && $slaBongkar === 'ON TIME') {
            return '<span class="status-badge status-ontime">✅ Pengiriman On Time</span>';
        }

        return '<span class="status-badge status-delay">🚨 Pengiriman Delay</span>';
    }

    private function badgeStatusAlert($r): string
    {
        $slaTiba = strtoupper(trim($r->sla_tiba ?? ''));
        $slaBongkar = strtoupper(trim($r->sla_bongkar ?? ''));

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

    private function computeEstimasiAdmin($r): ?Carbon
    {
        if (empty($r->rencana_kirim)) {
            return null;
        }

        $estimasiAdmin = Carbon::parse($r->rencana_kirim)
            ->addDays((int) ($r->transport_lead_time ?? 0));

        if (strtolower(trim($r->area ?? '')) === 'jawa barat') {
            $estimasiAdmin->addDay();
        }

        return $estimasiAdmin;
    }

    private function badgeEstimasiAdmin($r): string
    {
        $estimasiAdmin = $this->computeEstimasiAdmin($r);

        if (!$estimasiAdmin) {
            return '<span class="badge gray">-</span>';
        }

        if (!empty($r->tanggal_tiba)) {
            $tanggalTiba = Carbon::parse($r->tanggal_tiba);
            return $tanggalTiba->lte($estimasiAdmin)
                ? '<span class="badge green">On Time</span>'
                : '<span class="badge red">Delay</span>';
        }

        $isLate = now()->startOfDay()->gt($estimasiAdmin->copy()->startOfDay());

        return $isLate
            ? '<span class="badge red">Delay</span>'
            : '<span class="badge orange">Belum Tiba</span>';
    }

    private function formatLamaPerjalananRaw($value): string
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return '-';
        }
        return (string) round((float) $value);
    }

    private function computeLamaPerjalananSimple($r): string
    {
        if (empty($r->tanggal_tiba)) {
            return '-';
        }

       $keluarCandidates = array_filter(array_map(
    fn($s) => $r->{"tanggal_keluar_gudang{$s}"} ?? null,
    $this->gudangSuffixes()
));

        if (empty($keluarCandidates)) {
            return '-';
        }

        $keluar = max(array_map('strtotime', $keluarCandidates));
        $tiba = strtotime($r->tanggal_tiba);

        $hari = max(0, floor(($tiba - $keluar) / 86400));

        return $hari . ' Hari';
    }

    private function computeDurasiGudang($planning, $tiba): string
    {
        if (empty($planning) || empty($tiba)) {
            return '-';
        }

        $start = Carbon::parse($planning);
        $end = Carbon::parse($tiba);

        $totalMenit = $start->diffInMinutes($end);
        $desimalHari = $totalMenit / 1440;

        $hari = floor($desimalHari);
        $jam = round(($desimalHari - $hari) * 24);

        if ($jam == 24) {
            $jam = 0;
            $hari += 1;
        }

        if ($hari > 0 && $jam > 0) return "{$hari} Hari {$jam} Jam";
        if ($hari > 0) return "{$hari} Hari";
        if ($jam > 0) return "{$jam} Jam";
        return "0 Jam";
    }

    private function badgeStatusOnTimeDelayByDate($planning, $tiba): string
    {
        if (empty($planning) || empty($tiba)) {
            return '<span class="badge gray">-</span>';
        }

        $startDay = Carbon::parse($planning)->startOfDay();
        $endDay = Carbon::parse($tiba)->startOfDay();

        return $endDay->gt($startDay)
            ? '<span class="badge red">Delay</span>'
            : '<span class="badge green">On Time</span>';
    }

    private function badgeSlaLoadingClean($planning, $tiba): string
    {
        if (empty($planning) || empty($tiba)) {
            return '<span class="badge bg-secondary">-</span>';
        }

        $start = Carbon::parse($planning)->startOfDay();
        $end = Carbon::parse($tiba)->startOfDay();

        if ($end->gt($start)) {
            $selisihHari = $start->diffInDays($end);
            return '<span class="badge bg-warning text-dark">H+' . $selisihHari . '</span>';
        }

        return '<span class="badge bg-success">Sesuai SLA</span>';
    }

    private function badgeStatusGudangRaw($status): string
    {
        if (empty($status)) {
            return '<span class="badge gray">-</span>';
        }

        $s = strtolower($status);

        if ($s === 'on time') return '<span class="badge green">On Time</span>';
        if ($s === 'delay') return '<span class="badge red">Delay</span>';
        if ($s === 'on site') return '<span class="badge orange">On Site</span>';

        return '<span class="badge gray">' . e($status) . '</span>';
    }

    private function badgeStatusKendaraan($status): string
    {
        $status = trim((string) $status);

        if ($status === 'On Track') return '<span class="badge green">🟢 On Track</span>';
        if ($status === 'Potential Delay') return '<span class="badge red">🔴 Potential Delay</span>';

        return '<span class="badge gray">-</span>';
    }

 private function getTibaGudangTerdekat($row)
{
    foreach ($this->gudangSuffixes() as $s) {
        $t = $row->{"tanggal_tiba_gudang{$s}"} ?? null;
        if (!empty($t)) return $t;
    }
    return null;
}
}