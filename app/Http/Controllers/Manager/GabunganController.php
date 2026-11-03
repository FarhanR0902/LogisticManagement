<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GabunganController extends Controller
{
    /** View dashboard & nama route list: bisa ditimpa subclass (mis. Sales). */
    protected string $dashboardView = 'gabungan.dashboard';
    protected string $listRoute     = 'manager.gabungan.list'; // sesuaikan dengan nama route list milikmu

    private const PULAU_MAP = [
        'JAWA'       => ['JABODEBEK','BANTEN','JAWA_BARAT','JAWA_TENGAH','JAWA_TIMUR','YOGYAKARTA'],
        'SUMATERA'   => ['ACEH','SUMATERA_UTARA','SUMATERA_BARAT','RIAU','KEP._RIAU','JAMBI','SUMATERA_SELATAN','BENGKULU','LAMPUNG','KEP._BANGKA_BELITUNG'],
        'KALIMANTAN' => ['KALIMANTAN_BARAT','KALIMANTAN_TENGAH','KALIMANTAN_SELATAN','KALIMANTAN_TIMUR','KALIMANTAN_UTARA'],
        'SULAWESI'   => ['SULAWESI_UTARA','SULAWESI_TENGAH','SULAWESI_SELATAN','SULAWESI_TENGGARA','SULAWESI_BARAT','GORONTALO'],
        'BALI_NUSRA' => ['PROV._BALI','NUSA_TENGGARA_BARAT','NUSA_TENGGARA_TIMUR'],
        'MALUKU'     => ['PROV._MALUKU','PROV._MALUKU_UTARA'],
        'PAPUA'      => ['PROV._PAPUA','PAPUA_BARAT','PAPUA_BARAT_DAYA','PAPUA_SELATAN','PAPUA_TENGAH'],
    ];

    /**
     * Mapping kolom: nama generik (= nama kolom Jakarta) => kolom Pasuruan.
     * null = Pasuruan tidak punya kolom itu (diisi NULL).
     * Kalau ada view yang error "undefined property", tambahkan kolomnya di sini.
     */
    private const COLS = [
        'no_shipment'             => 'no_shipment_pasuruan',
        'tujuan'                  => 'tujuan_pasuruan',
        'area'                    => 'area_pasuruan',
        'planning_loading_2'      => 'planning_loading_puntir_pasuruan',
'tanggal_tiba_gudang_2'   => 'tanggal_tiba_gudang_puntir_pasuruan',
'tanggal_keluar_gudang_2' => 'tanggal_keluar_gudang_puntir_pasuruan',
        'pulau'                   => 'pulau_pasuruan',
        'dist_channel'            => 'dist_channel_pasuruan',
        'periode_shipment'        => 'periode_shipment_pasuruan',
        'tanggal_shipment'        => 'tanggal_shipment_pasuruan',
        'planner'                 => 'planner_pasuruan',
        'pic_monitoring'          => 'pic_monitoring_pasuruan',
        'kategori_ekspedisi'      => 'kategori_ekspedisi_pasuruan',
        'ekpedisi'                => 'ekspedisi_pasuruan',
        'mobil'                   => 'mobil_pasuruan',
        'nama_driver'             => 'nama_driver_pasuruan',
        'no_pol'                  => 'no_pol_pasuruan',
        'route'                   => 'route_pasuruan',
        'via_kirim'               => 'via_kirim_pasuruan',
        'transport_lead_time'     => 'transport_lead_time_pasuruan',
        'act_urutan_bongkar'      => 'act_urutan_bongkar_pasuruan',
        'rencana_kirim'           => 'rencana_kirim_pasuruan',
        'tanggal_naik_logistik'   => 'tanggal_terima_po_pasuruan',
        'tanggal_dpt_unit'        => 'tanggal_dpt_unit_pasuruan',
        'planning_loading'        => 'planning_loading_pasuruan',
        'tanggal_tiba_gudang'     => 'tanggal_tiba_gudang_pasuruan',
        'tanggal_keluar_gudang'   => 'tanggal_keluar_gudang_pasuruan',
        'planning_loading_2'      => null,
        'tanggal_tiba_gudang_2'   => null,
        'tanggal_keluar_gudang_2' => null,
        'planning_loading_3'      => null,
        'tanggal_tiba_gudang_3'   => null,
        'tanggal_keluar_gudang_3' => null,
        'sla_loading'             => null,
        'estimasi_tiba'           => 'estimasi_tiba_pasuruan',
        'tanggal_tiba'            => 'tanggal_tiba_pasuruan',
        'sla_tiba'                => 'sla_tiba_pasuruan',
        'tanggal_bongkar'         => 'tanggal_bongkar_pasuruan',
        'overstay_days'           => 'overstay_days_pasuruan',
        'sla_bongkar'             => 'sla_bongkar_pasuruan',
        'nilai_muatan'            => 'nilai_muatan_pasuruan',
        'biaya_kirim'             => 'biaya_kirim_pasuruan',
        'remarks'                 => 'remarks_pasuruan',
    ];

    private const NUM_COALESCE = ['nilai_muatan', 'biaya_kirim']; // angka, NULL jadi 0
    private const NUM_NULLABLE = ['overstay_days'];               // angka, NULL tetap NULL

    /* =====================================================
     * HOOK SCOPE (kosong untuk manager; diisi subclass, mis. Sales)
     * Dipasang di masing-masing tabel SEBELUM UNION.
     * ===================================================== */
    protected function scopeJakarta($q) {}
    protected function scopePasuruan($q) {}

    /* =====================================================
     * SUMBER DATA GABUNGAN (UNION di kode, bukan di DB)
     * ===================================================== */
    private function src()
    {
        $selJ = ['id', DB::raw("'JAKARTA' AS sumber")];
        $selP = [DB::raw('id + 1000000000 AS id'), DB::raw("'PASURUAN' AS sumber")];

        foreach (self::COLS as $g => $p) {
            $selJ[] = DB::raw($this->colExpr($g, $g) . " AS `{$g}`");
            $selP[] = DB::raw($this->colExpr($g, $p) . " AS `{$g}`");
        }

        $jakarta  = DB::table('logistik_pengiriman')->select($selJ);
        $pasuruan = DB::table('logistik_pengiriman_pasuruan')->select($selP);

        $this->scopeJakarta($jakarta);
        $this->scopePasuruan($pasuruan);

        return DB::query()->fromSub($jakarta->unionAll($pasuruan), 'v');
    }

    private function colExpr(string $generic, ?string $col): string
    {
        if ($col === null) return 'NULL';

        if (in_array($generic, self::NUM_COALESCE, true)) {
            return "COALESCE(CAST(NULLIF(TRIM(`{$col}`),'') AS DECIMAL(18,2)),0)";
        }
        if (in_array($generic, self::NUM_NULLABLE, true)) {
            return "CAST(NULLIF(TRIM(`{$col}`),'') AS DECIMAL(10,2))";
        }
        return "`{$col}`";
    }

    /* =====================================================
     * FILTER (sama dengan Jakarta + filter sumber)
     * ===================================================== */
    private function applyFilter($query, $request)
    {
        if (in_array($request->sumber, ['JAKARTA', 'PASURUAN'], true)) {
            $query->where('sumber', $request->sumber);
        }

        $areas = array_values(array_filter((array) $request->input('area', []), 'strlen'));
        if (!empty($areas)) $query->whereIn('area', $areas);

        if ($request->dist_channel) $query->where('dist_channel', $request->dist_channel);

        if ($request->filled('pulau') && isset(self::PULAU_MAP[$request->pulau])) {
            $query->whereIn('area', self::PULAU_MAP[$request->pulau]);
        }

        $col = match ($request->input('tgl_basis')) {
            'rencana_kirim'         => 'rencana_kirim',
            'tanggal_naik_logistik' => 'tanggal_naik_logistik',
            default                 => 'periode_shipment',
        };

        $from = $request->input('date_from');
        $to   = $request->input('date_to');
        if ($from && $to && $from > $to) [$from, $to] = [$to, $from];

        if ($col === 'periode_shipment') {
            if ($request->month) $query->where('periode_shipment', substr($request->month, 5, 2) . '-' . substr($request->month, 0, 4));
            if ($request->year)  $query->where('periode_shipment', 'like', '%-' . $request->year);
            if ($request->date)  $query->whereDate('tanggal_shipment', $request->date);
            if ($from) $query->whereDate('tanggal_shipment', '>=', $from);
            if ($to)   $query->whereDate('tanggal_shipment', '<=', $to);
            return $query;
        }

        if ($request->date) $query->whereDate($col, $request->date);
        if ($from) $query->whereDate($col, '>=', $from);
        if ($to)   $query->whereDate($col, '<=', $to);
        if ($request->month) {
            $query->whereMonth($col, substr($request->month, 5, 2))
                  ->whereYear($col, substr($request->month, 0, 4));
        }
        if ($request->year) $query->whereYear($col, $request->year);

        return $query;
    }

    /* =====================================================
     * GROUPING (shipment + tujuan, per sumber supaya no_shipment
     * yang kebetulan sama di 2 tabel tidak tercampur)
     * ===================================================== */
    private function groupIdsQuery($query)
    {
        return (clone $query)
            ->select(DB::raw('MIN(id) as id'))
            ->groupByRaw("sumber, COALESCE(NULLIF(TRIM(no_shipment),''), CONCAT('id', id)), COALESCE(tujuan,'')");
    }

    private function countGroups($query): int
    {
        return DB::query()->fromSub($this->groupIdsQuery($query), 'g')->count();
    }

    private function grupBiayaQuery($query)
    {
        return (clone $query)->selectRaw("
                COALESCE(NULLIF(TRIM(no_shipment),''), CONCAT('id', id)) AS sh,
                COALESCE(tujuan,'')           AS tj,
                MAX(area)                     AS area,
                MAX(pulau)                    AS pulau,
                MAX(COALESCE(biaya_kirim,0))  AS biaya,
                SUM(COALESCE(nilai_muatan,0)) AS nilai,
                COUNT(*)                      AS jml_do
            ")->groupByRaw('sumber, sh, tj');
    }

    /* =====================================================
     * DEFINISI KPI (satu tempat: dipakai kartu DAN halaman list)
     * ===================================================== */
    private function kpiQuery(string $kpi)
    {
        $filled = fn($c) => "NULLIF(TRIM({$c}), '') IS NOT NULL";
        $empty  = fn($c) => "NULLIF(TRIM({$c}), '') IS NULL";
        $q = $this->src();

        switch ($kpi) {
            case 'gudang_ontime': // sudah tiba gudang
                $q->where(fn($w) => $w->whereNotNull('tanggal_tiba_gudang')
                    ->orWhereNotNull('tanggal_tiba_gudang_2')
                    ->orWhereNotNull('tanggal_tiba_gudang_3'));
                break;

            case 'belum_armada':
                $q->where(fn($w) => $w->whereNull('rencana_kirim')->orWhere('rencana_kirim', '')
                    ->orWhereNull('tanggal_dpt_unit')->orWhere('tanggal_dpt_unit', ''));
                break;

            case 'sla_delay': // sudah dapat armada, belum tiba gudang
                $q->whereRaw($filled('rencana_kirim'))
                  ->whereRaw($filled('tanggal_dpt_unit'))
                  ->whereRaw($empty('tanggal_tiba_gudang'))
                  ->whereRaw($empty('tanggal_tiba_gudang_2'))
                  ->whereRaw($empty('tanggal_tiba_gudang_3'));
                break;

            case 'tujuan_ontime':
                $q->whereNotNull('tanggal_tiba')->whereNotNull('estimasi_tiba')
                  ->whereRaw('DATEDIFF(DATE(tanggal_tiba), DATE(estimasi_tiba)) <= 0');
                break;

            case 'tujuan_delay':
                $q->whereNotNull('tanggal_tiba')->whereNotNull('estimasi_tiba')
                  ->whereRaw('DATEDIFF(DATE(tanggal_tiba), DATE(estimasi_tiba)) > 0');
                break;

            case 'bongkar_ontime':
                $q->whereNotNull('tanggal_bongkar')
                  ->where('tanggal_bongkar', '!=', '1899-12-31 00:00:00')
                  ->where(fn($w) => $w->whereNull('overstay_days')->orWhere('overstay_days', 0));
                break;

            case 'bongkar_delay':
                $q->whereNotNull('tanggal_bongkar')
                  ->where('tanggal_bongkar', '!=', '1899-12-31 00:00:00')
                  ->where('overstay_days', '>', 0);
                break;

            case 'in_transit':
                $q->whereRaw($empty('tanggal_tiba'))
                  ->whereRaw('(' . implode(' OR ', [
                      $filled('tanggal_keluar_gudang'),
                      $filled('tanggal_keluar_gudang_2'),
                      $filled('tanggal_keluar_gudang_3'),
                  ]) . ')');
                foreach ([
                    ['planning_loading',   'tanggal_tiba_gudang',   'tanggal_keluar_gudang'],
                    ['planning_loading_2', 'tanggal_tiba_gudang_2', 'tanggal_keluar_gudang_2'],
                    ['planning_loading_3', 'tanggal_tiba_gudang_3', 'tanggal_keluar_gudang_3'],
                ] as [$pl, $tb, $kl]) {
                    $q->whereRaw('NOT ((' . $filled($pl) . ' OR ' . $filled($tb) . ') AND ' . $empty($kl) . ')');
                }
                break;
        }

        return $q; // 'total' = tanpa kondisi
    }

    private function kpiCount(string $kpi, Request $request): int
    {
        return $this->countGroups($this->applyFilter($this->kpiQuery($kpi), $request));
    }

    /* =====================================================
     * DASHBOARD GABUNGAN
     * ===================================================== */
    public function dashboard(Request $request)
    {
        $base = $this->applyFilter($this->src(), $request);
        $grouped = $this->src()->whereIn('id', $this->groupIdsQuery($base));

        $total_data       = $this->countGroups($base);
        $gudang_ontime    = $this->kpiCount('gudang_ontime', $request);
        $gudang_delay     = $this->kpiCount('belum_armada', $request);
        $total_sla_delay  = $this->kpiCount('sla_delay', $request);
        $customer_ontime  = $this->kpiCount('tujuan_ontime', $request);
        $customer_delay   = $this->kpiCount('tujuan_delay', $request);
        $bongkar_ontime   = $this->kpiCount('bongkar_ontime', $request);
        $bongkar_delay    = $this->kpiCount('bongkar_delay', $request);
        $total_in_transit = $this->kpiCount('in_transit', $request);

        // armada & planner
        $planner_armada = $this->countGroups((clone $base)
            ->whereNotNull('rencana_kirim')->whereRaw("TRIM(rencana_kirim) <> ''")
            ->whereNotNull('tanggal_dpt_unit')->whereRaw("TRIM(tanggal_dpt_unit) <> ''"));
        $planner_belum_armada = $gudang_delay; // definisinya sama dengan KPI belum_armada

        $planner_ontime = $this->countGroups((clone $base)
            ->whereNotNull('rencana_kirim')->whereNotNull('tanggal_dpt_unit')
            ->whereRaw('DATE(tanggal_dpt_unit) <= DATE(rencana_kirim)'));
        $planner_delay = $this->countGroups((clone $base)
            ->whereNotNull('rencana_kirim')->whereNotNull('tanggal_dpt_unit')
            ->whereRaw('DATE(tanggal_dpt_unit) > DATE(rencana_kirim)'));

        // dropdown
        $list_dist_channel = $this->src()->select('dist_channel')
            ->whereNotNull('dist_channel')->distinct()->orderBy('dist_channel')->get();
        $list_area = $this->listArea();

        // nilai & biaya
        $grp = $this->grupBiayaQuery($base);
        $tot = DB::query()->fromSub($grp, 'g')
            ->selectRaw('COALESCE(SUM(biaya),0) AS total_biaya, COALESCE(SUM(nilai),0) AS total_nilai')->first();
        $totalBiayaKirim  = (float) $tot->total_biaya;
        $totalNilaiMuatan = (float) $tot->total_nilai;

        $summary_area = DB::query()->fromSub($grp, 'g')
            ->selectRaw('area, COUNT(*) AS total_shipment, SUM(biaya) AS total_biaya, SUM(nilai) AS total_muatan')
            ->whereNotNull('area')->groupBy('area')->orderByDesc('total_shipment')->get();

        $summary_tujuan = DB::query()->fromSub($grp, 'g')
            ->selectRaw('tj AS tujuan, COUNT(*) AS total_shipment, SUM(biaya) AS total_biaya, SUM(nilai) AS total_muatan')
            ->where('tj', '<>', '')->groupBy('tj')->orderByDesc('total_shipment')->get();

        $ekspedisi = (clone $grouped)
            ->select('kategori_ekspedisi', DB::raw('COUNT(*) as total'))
            ->whereNotNull('kategori_ekspedisi')->groupBy('kategori_ekspedisi')->get();
        $label = $ekspedisi->pluck('kategori_ekspedisi');
        $value = $ekspedisi->pluck('total');

        // rasio
        $total_status = $planner_ontime + $planner_delay;
        $ontime_rate  = $total_status > 0 ? ($planner_ontime / $total_status) * 100 : 0;
        $delay_rate   = $total_status > 0 ? ($planner_delay / $total_status) * 100 : 0;
        $total_armada = $planner_armada + $planner_belum_armada;
        $armada_rate  = $total_armada > 0 ? ($planner_armada / $total_armada) * 100 : 0;
        $pending_rate = $total_armada > 0 ? ($planner_belum_armada / $total_armada) * 100 : 0;

        $pct = fn($n) => $total_data > 0 ? ($n / $total_data) * 100 : 0;
        $summary_monitoring = [
            'tiba_ontime'    => $pct($customer_ontime),
            'tiba_delay'     => $pct($customer_delay),
            'bongkar_ontime' => $pct($bongkar_ontime),
            'bongkar_delay'  => $pct($bongkar_delay),
        ];

        // summary planner (gudang terakhir)
        $greatestTiba = "GREATEST(COALESCE(tanggal_tiba_gudang,'1900-01-01'),COALESCE(tanggal_tiba_gudang_2,'1900-01-01'),COALESCE(tanggal_tiba_gudang_3,'1900-01-01'))";
        $lastTiba = "CASE
            WHEN tanggal_tiba_gudang_3 IS NOT NULL AND tanggal_tiba_gudang_3 = {$greatestTiba} THEN tanggal_tiba_gudang_3
            WHEN tanggal_tiba_gudang_2 IS NOT NULL AND tanggal_tiba_gudang_2 = {$greatestTiba} THEN tanggal_tiba_gudang_2
            WHEN tanggal_tiba_gudang IS NOT NULL THEN tanggal_tiba_gudang ELSE NULL END";
        $lastPlanning = "CASE
            WHEN tanggal_tiba_gudang_3 IS NOT NULL AND tanggal_tiba_gudang_3 = {$greatestTiba} THEN planning_loading_3
            WHEN tanggal_tiba_gudang_2 IS NOT NULL AND tanggal_tiba_gudang_2 = {$greatestTiba} THEN planning_loading_2
            WHEN tanggal_tiba_gudang IS NOT NULL THEN planning_loading ELSE NULL END";
        $belumIsi = "NULLIF(TRIM(rencana_kirim), '') IS NULL";

        $summary_planner = (clone $grouped)->select(
                'planner',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN {$belumIsi} THEN 1 ELSE 0 END) as total_belum_rencana"),
                DB::raw("SUM(CASE WHEN ({$lastPlanning}) IS NOT NULL AND ({$lastTiba}) IS NOT NULL AND DATE({$lastTiba}) <= DATE({$lastPlanning}) THEN 1 ELSE 0 END) as total_ontime"),
                DB::raw("SUM(CASE WHEN ({$lastPlanning}) IS NOT NULL AND ({$lastTiba}) IS NOT NULL AND DATE({$lastTiba}) > DATE({$lastPlanning}) THEN 1 ELSE 0 END) as total_delay")
            )->whereNotNull('planner')->where('planner', '!=', '')
            ->groupBy('planner')->orderByDesc('total')->get();

        $summary_pic_monitoring = (clone $grouped)->select(
                'pic_monitoring',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN tanggal_tiba IS NOT NULL AND estimasi_tiba IS NOT NULL AND DATEDIFF(DATE(tanggal_tiba), DATE(estimasi_tiba)) <= 0 THEN 1 ELSE 0 END) as total_ontime"),
                DB::raw("SUM(CASE WHEN tanggal_tiba IS NOT NULL AND estimasi_tiba IS NOT NULL AND DATEDIFF(DATE(tanggal_tiba), DATE(estimasi_tiba)) > 0 THEN 1 ELSE 0 END) as total_delay"),
                DB::raw("SUM(CASE WHEN NULLIF(TRIM(tanggal_tiba), '') IS NULL THEN 1 ELSE 0 END) as total_belum_tiba"),
                DB::raw("SUM(CASE WHEN NULLIF(TRIM(tanggal_tiba), '') IS NULL AND estimasi_tiba IS NOT NULL AND DATE(estimasi_tiba) < CURDATE() THEN 1 ELSE 0 END) as total_lewat_estimasi")
            )->whereNotNull('pic_monitoring')->where('pic_monitoring', '!=', '')
            ->groupBy('pic_monitoring')->orderByDesc('total')->get();

        return view($this->dashboardView, compact(
            'total_data', 'summary_planner', 'summary_pic_monitoring',
            'gudang_ontime', 'gudang_delay', 'total_in_transit', 'total_sla_delay',
            'customer_ontime', 'customer_delay', 'bongkar_ontime', 'bongkar_delay',
            'summary_area', 'summary_tujuan', 'totalNilaiMuatan', 'totalBiayaKirim',
            'ekspedisi', 'label', 'value',
            'planner_ontime', 'planner_delay', 'planner_armada', 'planner_belum_armada',
            'ontime_rate', 'delay_rate', 'armada_rate', 'pending_rate',
            'summary_monitoring', 'list_dist_channel', 'list_area'
        ))->with('listRoute', $this->listRoute);
    }

    /* =====================================================
     * HALAMAN LIST (satu method untuk semua kartu)
     * protected + static:: supaya subclass bisa menimpa daftar view-nya
     * ===================================================== */
    protected const LIST_CONFIG = [
        'total'          => ['view' => 'manager.summary_total',  'var' => 'logistik', 'order' => null],
        'gudang_ontime'  => ['view' => 'manager.sla_ontime',     'var' => 'list',     'order' => null,              'sla' => true],
        'belum_armada'   => ['view' => 'planner.belum_armada',   'var' => 'logistik', 'order' => 'tanggal_naik_logistik'],
        'sla_delay'      => ['view' => 'manager.sla_delay',      'var' => 'list',     'order' => 'tanggal_naik_logistik', 'paginate' => 10],
        'tujuan_ontime'  => ['view' => 'manager.tujuan_ontime',  'var' => 'logistik', 'order' => 'tanggal_tiba',    'extra' => "estimasi_tiba AS tanggal_estimasi, 'On Time' AS sla_tiba"],
        'tujuan_delay'   => ['view' => 'manager.tujuan_delay',   'var' => 'logistik', 'order' => null,              'extra' => "estimasi_tiba AS tanggal_estimasi, 'Delay' AS sla_tiba"],
        'bongkar_ontime' => ['view' => 'manager.bongkar_ontime', 'var' => 'list',     'order' => 'tanggal_bongkar', 'extra' => "'On Time' AS sla_bongkar"],
        'bongkar_delay'  => ['view' => 'manager.bongkar_delay',  'var' => 'list',     'order' => 'tanggal_bongkar'],
    ];

    public function list(Request $request, string $kpi)
    {
        $cfg = static::LIST_CONFIG[$kpi] ?? abort(404);

        $filter = $this->applyFilter($this->kpiQuery($kpi), $request);

        $q = $this->src()->whereIn('id', $this->groupIdsQuery($filter));
        if (!empty($cfg['extra'])) $q->selectRaw('*, ' . $cfg['extra']);
        if (!empty($cfg['order'])) $q->orderByDesc($cfg['order']);
        $q->orderBy('sumber')->orderBy('no_shipment')->orderBy('tujuan');

        if (!empty($cfg['paginate'])) {
            $rows = $q->paginate($cfg['paginate'])->withQueryString();
        } else {
            $rows = $q->get();

            if (!empty($cfg['sla'])) {
                $rows = $rows->map(function ($row) {
                    // TODO: paste method hitungSla() dari ManagerController ke controller ini,
                    // lalu isi ulang field SLA seperti di ManagerController::gudangOntime()
                    return $row;
                })->values();
            }
        }

        return view($cfg['view'], [
            $cfg['var']         => $rows,
            'title'             => strtoupper(str_replace('_', ' ', $kpi)),
            'list_area'         => $this->listArea(),
            'list_dist_channel' => $this->src()->select('dist_channel')
                ->whereNotNull('dist_channel')->distinct()->orderBy('dist_channel')->get(),
        ]);
    }

    private function listArea()
    {
        return $this->src()->select('area')->whereNotNull('area')
            ->groupBy('area')->orderBy('area')->get();
    }
}