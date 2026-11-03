<?php

namespace App\Http\Controllers\Sales;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesGabunganController extends Controller
{
    private const PERIODE_SHIPMENT = '11-2026';   // samakan dengan SalesController

    private const MODERN_MARKET_CHANNELS = [
        'FSI', 'MT - Minimarket', 'MT - Supermarket', 'MT - Lokal', 'MT - Hypermarket',
    ];

    private const PULAU_MAP = [
        'JAWA'       => ['JABODEBEK','BANTEN','JAWA_BARAT','JAWA_TENGAH','JAWA_TIMUR','YOGYAKARTA'],
        'SUMATERA'   => ['ACEH','SUMATERA_UTARA','SUMATERA_BARAT','RIAU','KEP._RIAU','JAMBI','SUMATERA_SELATAN','BENGKULU','LAMPUNG','KEP._BANGKA_BELITUNG'],
        'KALIMANTAN' => ['KALIMANTAN_BARAT','KALIMANTAN_TENGAH','KALIMANTAN_SELATAN','KALIMANTAN_TIMUR','KALIMANTAN_UTARA'],
        'SULAWESI'   => ['SULAWESI_UTARA','SULAWESI_TENGAH','SULAWESI_SELATAN','SULAWESI_TENGGARA','SULAWESI_BARAT','GORONTALO'],
        'BALI_NUSRA' => ['PROV._BALI','NUSA_TENGGARA_BARAT','NUSA_TENGGARA_TIMUR'],
        'MALUKU'     => ['PROV._MALUKU','PROV._MALUKU_UTARA'],
        'PAPUA'      => ['PROV._PAPUA','PAPUA_BARAT','PAPUA_BARAT_DAYA','PAPUA_SELATAN','PAPUA_TENGAH'],
    ];

    private const LIST_CONFIG = [
        'total'          => ['view' => 'sales.summary_total',  'var' => 'logistik', 'order' => null],
        'gudang_ontime'  => ['view' => 'sales.sla_ontime',     'var' => 'list',     'order' => null],
        'belum_armada'   => ['view' => 'sales.belum_armada',   'var' => 'logistik', 'order' => 'tanggal_naik_logistik'],
        'sla_delay'      => ['view' => 'sales.sla_delay',      'var' => 'list',     'order' => 'tanggal_dpt_unit'],
        'tujuan_ontime'  => ['view' => 'sales.tujuan_ontime',  'var' => 'logistik', 'order' => 'tanggal_tiba'],
        'tujuan_delay'   => ['view' => 'sales.tujuan_delay',   'var' => 'logistik', 'order' => null],
        'bongkar_ontime' => ['view' => 'sales.bongkar_ontime', 'var' => 'logistik', 'order' => 'tanggal_bongkar'],
        'bongkar_delay'  => ['view' => 'sales.bongkar_delay',  'var' => 'logistik', 'order' => 'tanggal_bongkar'],
    ];

    /* =====================================================================
     |  KONFIGURASI KOLOM PER SUMBER (Jakarta tanpa suffix, Pasuruan _pasuruan)
     * ===================================================================== */
    private function cfg(string $src): array
    {
        $pas = $src === 'pasuruan';
        $p   = $pas ? '_pasuruan' : '';

        return [
            'src'     => $src,
            'table'   => $pas ? 'logistik_pengiriman_pasuruan' : 'logistik_pengiriman',
            'ship'    => 'no_shipment' . $p,
            'tuj'     => 'tujuan' . $p,
            'area'    => 'area' . $p,
            'dist'    => 'dist_channel' . $p,
            'periode' => 'periode_shipment' . $p,
            'tglShip' => $pas ? 'tanggal_terima_po_pasuruan' : 'tanggal_shipment',
            'tglNaik' => $pas ? 'tanggal_terima_po_pasuruan' : 'tanggal_naik_logistik',
            'rencana' => 'rencana_kirim' . $p,
            'dpt'     => 'tanggal_dpt_unit' . $p,
            'tiba'    => 'tanggal_tiba' . $p,
            'est'     => 'estimasi_tiba' . $p,
            'bongkar' => 'tanggal_bongkar' . $p,
            'over'    => 'overstay_days' . $p,
            'nilai'   => 'nilai_muatan' . $p,
            'biaya'   => 'biaya_kirim' . $p,
            'kat'     => 'kategori_ekspedisi' . $p,
            'planner' => 'planner' . $p,
            'pic'     => 'pic_monitoring' . $p,
            'tibaGudang' => $pas
                ? ['tanggal_tiba_gudang_pasuruan']
                : ['tanggal_tiba_gudang', 'tanggal_tiba_gudang_2', 'tanggal_tiba_gudang_3'],
            // [planning, tiba, keluar]
            'cycles' => $pas
                ? [['planning_loading_pasuruan', 'tanggal_tiba_gudang_pasuruan', 'tanggal_keluar_gudang_pasuruan']]
                : [
                    ['planning_loading',   'tanggal_tiba_gudang',   'tanggal_keluar_gudang'],
                    ['planning_loading_2', 'tanggal_tiba_gudang_2', 'tanggal_keluar_gudang_2'],
                    ['planning_loading_3', 'tanggal_tiba_gudang_3', 'tanggal_keluar_gudang_3'],
                ],
        ];
    }

        /* =====================================================================
     |  IN TRANSIT (gabungan Jakarta + Pasuruan)
     * ===================================================================== */
    public function inTransit(Request $request)
    {
        $todayTs = strtotime(date('Y-m-d'));
        $rows = collect();

        foreach ($this->sources($request) as $s) {
            $c = $this->cfg($s);
         $q = $this->baseQuery($s, $request, false);
            $this->applyKpi($q, $c, 'in_transit');

            foreach (DB::table($c['table'])->whereIn('id', $this->groupIds($q, $c))->get() as $r) {
                $rows->push($this->mapTransit($r, $s, $todayTs));
            }
        }

        if ($request->filled('q')) {
            $needle = strtolower(trim($request->input('q')));
            $rows = $rows->filter(fn($r) => str_contains(strtolower(
                $r->no_shipment . ' ' . $r->tujuan . ' ' . $r->ekpedisi . ' ' . $r->nama_driver . ' ' . $r->no_pol . ' ' . $r->mobil
            ), $needle));
        }

        $rows = $rows->sortBy(fn($r) => $r->estimasi_ts ?? PHP_INT_MAX)->values();

        $sisa = fn($r) => $r->estimasi_ts ? floor(($r->estimasi_ts - $todayTs) / 86400) : null;
        $summary = [
            'total'   => $rows->count(),
            'overdue' => $rows->filter(fn($r) => $sisa($r) !== null && $sisa($r) < 0)->count(),
            'soon'    => $rows->filter(fn($r) => $sisa($r) !== null && $sisa($r) >= 0 && $sisa($r) <= 3)->count(),
            'ontrack' => $rows->filter(fn($r) => $sisa($r) !== null && $sisa($r) > 3)->count(),
        ];

        $perPage = 50;
        $page = max(1, (int) $request->input('page', 1));
        $list = (new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page,
            ['path' => $request->url()]
        ))->withQueryString();

        return view('monitoring.in_transit', [
            'list'      => $list,
            'summary'   => $summary,
            'areaList'  => $this->listAreas()->pluck('area'),
            'picList'   => collect(),
            'formRoute' => route('sales.gabungan.intransit'),
        ]);
    }

    private function mapTransit($r, string $src, int $todayTs): object
    {
        $pas = $src === 'pasuruan';
        $p   = $pas ? '_pasuruan' : '';

        $keluarCols = $pas
            ? ['tanggal_keluar_gudang_pasuruan' => 'GUDANG']
            : ['tanggal_keluar_gudang' => 'KACS', 'tanggal_keluar_gudang_2' => 'SENTUL', 'tanggal_keluar_gudang_3' => 'CCIE'];

        $keluar = null;
        $asal   = '-';
        foreach ($keluarCols as $col => $nama) {
            if (!empty($r->$col)) {
                $ts = strtotime($r->$col);
                if ($keluar === null || $ts >= $keluar) {
                    $keluar = $ts;
                    $asal   = $nama;
                }
            }
        }

        $keluarD = $keluar ? strtotime(date('Y-m-d', $keluar)) : null;
        $lead    = (int) ($r->{'transport_lead_time' . $p} ?? 0);
        $estCol  = 'estimasi_tiba' . $p;
        $est     = !empty($r->$estCol)
            ? strtotime($r->$estCol)
            : ($keluarD ? strtotime("+{$lead} days", $keluarD) : null);

        [$alert, $cls] = $this->alertTransit($est, $todayTs);

        return (object) [
            'sumber'         => strtoupper($src),
            'no_shipment'    => $r->{'no_shipment' . $p},
            'tujuan'         => $r->{'tujuan' . $p},
            'area'           => $r->{'area' . $p},
            'dist_channel'   => $r->{'dist_channel' . $p},
            'ekpedisi'       => $pas ? $r->ekspedisi_pasuruan : $r->ekpedisi,
            'mobil'          => $r->{'mobil' . $p},
            'pic_monitoring' => $r->{'pic_monitoring' . $p},
            'nama_driver'    => $r->{'nama_driver' . $p},
            'no_pol'         => $r->{'no_pol' . $p},
            'remarks'        => $r->{'remarks' . $p},
            'gudang_asal'    => $asal,
            'keluar_label'   => $keluar ? date('d-m-Y', $keluar) : '-',
            'hari_transit'   => $keluarD ? max(0, floor(($todayTs - $keluarD) / 86400)) : null,
            'estimasi_ts'    => $est,
            'estimasi_label' => $est ? date('d-m-Y', $est) : '-',
            'alert_label'    => $alert,
            'alert_class'    => $cls,
        ];
    }

    private function alertTransit($estimasi, int $todayTs): array
    {
        if (!$estimasi) return ['-', 'gray'];

        $sisa = floor(($estimasi - $todayTs) / 86400);
        if     ($sisa < 0)  return ['Pending Tiba H+' . abs($sisa), 'red'];
        elseif ($sisa <= 1) return ['H-' . $sisa, 'red'];
        elseif ($sisa <= 3) return ['H-' . $sisa, 'orange'];
        elseif ($sisa <= 7) return ['H-' . $sisa, 'blue'];
        return ['ON TRACK', 'green'];
    }


    /** jakarta | pasuruan | keduanya, dari param ?sumber= (huruf besar/kecil sama saja) */
    private function sources(Request $r): array
    {
        $s = strtolower(trim((string) $r->input('sumber')));
        return in_array($s, ['jakarta', 'pasuruan'], true) ? [$s] : ['jakarta', 'pasuruan'];
    }

    /* =====================================================================
     |  SCOPE (periode + dist channel user)
     * ===================================================================== */
private function resolveDistChannel()
{
    return 'MODERN_MARKET';
}

    private function scopeDistChannel($query, string $col)
    {
        $v = $this->resolveDistChannel();

        if (is_array($v)) {
            return empty($v) ? $query : $query->whereIn($col, $v);
        }
        if (empty($v) || strtoupper((string) $v) === 'ALL') {
            return $query;
        }
        if (strtoupper($v) === 'MODERN_MARKET') {
            return $query->whereIn($col, self::MODERN_MARKET_CHANNELS);
        }
        if (strtoupper($v) === 'MT') {
            return $query->where($col, 'like', 'MT -%');
        }
        return $query->whereIn($col, (array) $v);
    }

    /* =====================================================================
     |  FILTER (SATU-SATUNYA, dipakai dashboard DAN list)
     * ===================================================================== */
   private function baseQuery(string $src, Request $r, bool $filterPeriode = true)
{
    $c = $this->cfg($src);

    $q = DB::table($c['table']);
    if ($filterPeriode) {
        $q->where($c['periode'], self::PERIODE_SHIPMENT);
    }
    $this->scopeDistChannel($q, $c['dist']);
    $this->applyFilters($q, $c, $r);

    return $q;
}
    private function applyFilters($q, array $c, Request $r): void
    {
        $areas = array_values(array_filter((array) $r->input('area', []), 'strlen'));
        if ($areas) {
            $q->whereIn($c['area'], $areas);
        }

        $pulau = (string) $r->input('pulau');
        if (isset(self::PULAU_MAP[$pulau])) {
            $q->whereIn($c['area'], self::PULAU_MAP[$pulau]);
        }

        if ($r->filled('dist_channel')) {
            $q->where($c['dist'], $r->input('dist_channel'));
        }

        $basis = (string) $r->input('tgl_basis', 'periode_shipment');
        $from  = $r->input('date_from');
        $to    = $r->input('date_to');
        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }
        $date     = $r->input('date');
        $year     = $r->input('year');
        $hasMonth = preg_match('/^(\d{4})-(\d{2})$/', (string) $r->input('month'), $m);

        // basis tanggal biasa
        if ($basis === 'rencana_kirim' || $basis === 'tanggal_naik_logistik') {
            $col = $basis === 'rencana_kirim' ? $c['rencana'] : $c['tglNaik'];

            if ($from)     $q->whereDate($col, '>=', $from);
            if ($to)       $q->whereDate($col, '<=', $to);
            if ($date)     $q->whereDate($col, $date);
            if ($hasMonth) $q->whereYear($col, $m[1])->whereMonth($col, $m[2]);
            if ($year)     $q->whereYear($col, $year);
            return;
        }

        // basis periode shipment: bulan/tahun memakai teks "MM-YYYY"
        if ($hasMonth) $q->where($c['periode'], $m[2] . '-' . $m[1]);
        if ($year)     $q->where($c['periode'], 'like', '%-' . $year);

        $col = $c['tglShip'];
        if ($from) $q->whereDate($col, '>=', $from);
        if ($to)   $q->whereDate($col, '<=', $to);
        if ($date) $q->whereDate($col, $date);
    }

    /* =====================================================================
     |  KPI
     * ===================================================================== */
    private function applyKpi($q, array $c, string $kpi): void
    {
        $f = fn($col) => "NULLIF(TRIM({$col}),'') IS NOT NULL";
        $e = fn($col) => "NULLIF(TRIM({$col}),'') IS NULL";
        $over   = "COALESCE(CAST(NULLIF(TRIM({$c['over']}),'') AS DECIMAL(18,4)),0)";
        $bgOk   = "CAST({$c['bongkar']} AS CHAR) NOT LIKE '1899-12-31%'";

        switch ($kpi) {
            case 'gudang_ontime':
                $q->whereRaw('(' . implode(' OR ', array_map($f, $c['tibaGudang'])) . ')');
                break;

            case 'sla_delay':   // belum tiba di gudang
                $q->whereRaw($f($c['rencana']))->whereRaw($f($c['dpt']));
                foreach ($c['tibaGudang'] as $col) {
                    $q->whereRaw($e($col));
                }
                break;

            case 'belum_armada':
                $q->whereRaw('(' . $e($c['rencana']) . ' OR ' . $e($c['dpt']) . ')');
                break;

            case 'armada':
                $q->whereRaw($f($c['rencana']))->whereRaw($f($c['dpt']));
                break;

            case 'planner_ontime':
                $q->whereRaw($f($c['rencana']))->whereRaw($f($c['dpt']))
                  ->whereRaw("DATE({$c['dpt']}) <= DATE({$c['rencana']})");
                break;

            case 'planner_delay':
                $q->whereRaw($f($c['rencana']))->whereRaw($f($c['dpt']))
                  ->whereRaw("DATE({$c['dpt']}) > DATE({$c['rencana']})");
                break;

            case 'tujuan_ontime':
                $q->whereRaw($f($c['tiba']))->whereRaw($f($c['est']))
                  ->whereRaw("DATEDIFF(DATE({$c['tiba']}), DATE({$c['est']})) <= 0");
                break;

            case 'tujuan_delay':
                $q->whereRaw($f($c['tiba']))->whereRaw($f($c['est']))
                  ->whereRaw("DATEDIFF(DATE({$c['tiba']}), DATE({$c['est']})) > 0");
                break;

            case 'bongkar_ontime':
                $q->whereRaw($f($c['bongkar']))->whereRaw($bgOk)->whereRaw("{$over} = 0");
                break;

            case 'bongkar_delay':
                $q->whereRaw($f($c['bongkar']))->whereRaw($bgOk)->whereRaw("{$over} > 0");
                break;

            case 'in_transit':
                $q->whereRaw($e($c['tiba']));
                $q->whereRaw('(' . implode(' OR ', array_map(fn($cy) => $f($cy[2]), $c['cycles'])) . ')');
                foreach ($c['cycles'] as [$pl, $tg, $kg]) {
                    $q->whereRaw('NOT ((' . $f($pl) . ' OR ' . $f($tg) . ') AND ' . $e($kg) . ')');
                }
                break;
        }
    }

    private function groupIds($q, array $c)
    {
        return (clone $q)
            ->select(DB::raw('MIN(id) as id'))
            ->groupByRaw("COALESCE(NULLIF(TRIM({$c['ship']}),''), CONCAT('id', id)), COALESCE({$c['tuj']},'')");
    }

    private function countKpi(Request $r, ?string $kpi): int
    {
        $total = 0;
        foreach ($this->sources($r) as $s) {
            $c = $this->cfg($s);
         $q = $this->baseQuery($s, $r, $kpi !== 'in_transit');
            if ($kpi) {
                $this->applyKpi($q, $c, $kpi);
            }
            $total += DB::query()->fromSub($this->groupIds($q, $c), 'g')->count();
        }
        return $total;
    }

    /** 1 baris per shipment + tujuan, dipakai untuk semua ringkasan di dashboard */
    private function groupRows(string $src, Request $r)
    {
        $c  = $this->cfg($src);
        $n  = fn($col) => "COALESCE(CAST(NULLIF(TRIM({$col}),'') AS DECIMAL(18,4)),0)";
        $mx = fn($col) => "MAX(NULLIF(TRIM({$col}),''))";

        return $this->baseQuery($src, $r)->selectRaw("
                COALESCE(NULLIF(TRIM({$c['ship']}),''), CONCAT('id', id)) AS sh,
                COALESCE({$c['tuj']},'')        AS tj,
                MAX({$c['area']})               AS area,
                MAX({$n($c['biaya'])})          AS biaya,
                SUM({$n($c['nilai'])})          AS nilai,
                MAX({$c['kat']})                AS kat,
                MAX({$c['planner']})            AS planner,
                MAX({$c['pic']})                AS pic,
                {$mx($c['rencana'])}            AS rencana,
                {$mx($c['dpt'])}                AS dpt,
                {$mx($c['tiba'])}               AS tiba,
                {$mx($c['est'])}                AS est
            ")
            ->groupByRaw('sh, tj')
            ->get();
    }

    /* =====================================================================
     |  DROPDOWN
     * ===================================================================== */
    private function listAreas()
    {
        $out = collect();
        foreach (['jakarta', 'pasuruan'] as $s) {
            $c = $this->cfg($s);
            $q = DB::table($c['table'])
                ->where($c['periode'], self::PERIODE_SHIPMENT)
                ->whereNotNull($c['area'])->where($c['area'], '!=', '');
            $this->scopeDistChannel($q, $c['dist']);
            $out = $out->merge($q->distinct()->pluck($c['area']));
        }

        return $out->unique()->sort()->values()->map(fn($a) => (object) ['area' => $a]);
    }

    private function listDistChannels()
    {
        $out = collect();
        foreach (['jakarta', 'pasuruan'] as $s) {
            $c = $this->cfg($s);
            $q = DB::table($c['table'])
                ->where($c['periode'], self::PERIODE_SHIPMENT)
                ->whereNotNull($c['dist'])->where($c['dist'], '!=', '');
            $this->scopeDistChannel($q, $c['dist']);
            $out = $out->merge($q->distinct()->pluck($c['dist']));
        }

        return $out->unique()->sort()->values()->map(fn($d) => (object) ['dist_channel' => $d]);
    }

    /* =====================================================================
     |  DASHBOARD
     * ===================================================================== */
    public function dashboard(Request $request)
    {
        $total_data       = $this->countKpi($request, null);
        $total_in_transit = $this->countKpi($request, 'in_transit');
        $gudang_ontime    = $this->countKpi($request, 'gudang_ontime');
        $total_sla_delay  = $this->countKpi($request, 'sla_delay');
        $gudang_delay     = $this->countKpi($request, 'belum_armada');
        $customer_ontime  = $this->countKpi($request, 'tujuan_ontime');
        $customer_delay   = $this->countKpi($request, 'tujuan_delay');
        $bongkar_ontime   = $this->countKpi($request, 'bongkar_ontime');
        $bongkar_delay    = $this->countKpi($request, 'bongkar_delay');

        $planner_armada       = $this->countKpi($request, 'armada');
        $planner_belum_armada = $gudang_delay;
        $planner_ontime       = $this->countKpi($request, 'planner_ontime');
        $planner_delay        = $this->countKpi($request, 'planner_delay');

        $total_status = $planner_ontime + $planner_delay;
        $ontime_rate  = $total_status > 0 ? ($planner_ontime / $total_status) * 100 : 0;
        $delay_rate   = $total_status > 0 ? ($planner_delay / $total_status) * 100 : 0;

        $total_armada = $planner_armada + $planner_belum_armada;
        $armada_rate  = $total_armada > 0 ? ($planner_armada / $total_armada) * 100 : 0;

        $summary_monitoring = [
            'tiba_ontime'    => $total_data > 0 ? ($customer_ontime / $total_data) * 100 : 0,
            'tiba_delay'     => $total_data > 0 ? ($customer_delay / $total_data) * 100 : 0,
            'bongkar_ontime' => $total_data > 0 ? ($bongkar_ontime / $total_data) * 100 : 0,
            'bongkar_delay'  => $total_data > 0 ? ($bongkar_delay / $total_data) * 100 : 0,
        ];

        // ringkasan per grup shipment + tujuan
        $rows = collect();
        foreach ($this->sources($request) as $s) {
            $rows = $rows->merge($this->groupRows($s, $request));
        }

        $totalBiayaKirim  = (float) $rows->sum('biaya');
        $totalNilaiMuatan = (float) $rows->sum('nilai');

        $summary_area = $rows->filter(fn($r) => $r->area)
            ->groupBy('area')
            ->map(fn($g, $a) => (object) [
                'area'           => $a,
                'total_shipment' => $g->count(),
                'total_biaya'    => (float) $g->sum('biaya'),
                'total_muatan'   => (float) $g->sum('nilai'),
            ])->sortByDesc('total_shipment')->values();

        $summary_tujuan = $rows->filter(fn($r) => $r->tj !== '')
            ->groupBy('tj')
            ->map(fn($g, $t) => (object) [
                'tujuan'         => $t,
                'total_shipment' => $g->count(),
                'total_biaya'    => (float) $g->sum('biaya'),
                'total_muatan'   => (float) $g->sum('nilai'),
            ])->sortByDesc('total_shipment')->values();

        $kategori = $rows->filter(fn($r) => trim((string) $r->kat) !== '')->groupBy('kat')->map->count();
        $label = $kategori->keys()->values()->all();
        $value = $kategori->values()->all();

        $d     = fn($v) => $v ? substr((string) $v, 0, 10) : null;
        $today = date('Y-m-d');

        $summary_planner = $rows->filter(fn($r) => trim((string) $r->planner) !== '')
            ->groupBy('planner')
            ->map(fn($g, $name) => (object) [
                'planner'             => $name,
                'total'               => $g->count(),
                'total_belum_rencana' => $g->filter(fn($r) => !$r->rencana)->count(),
                'total_ontime'        => $g->filter(fn($r) => $r->rencana && $r->dpt && $d($r->dpt) <= $d($r->rencana))->count(),
                'total_delay'         => $g->filter(fn($r) => $r->rencana && $r->dpt && $d($r->dpt) > $d($r->rencana))->count(),
            ])->sortByDesc('total')->values();

        $summary_pic_monitoring = $rows->filter(fn($r) => trim((string) $r->pic) !== '')
            ->groupBy('pic')
            ->map(fn($g, $name) => (object) [
                'pic_monitoring'       => $name,
                'total'                => $g->count(),
                'total_ontime'         => $g->filter(fn($r) => $r->tiba && $r->est && $d($r->tiba) <= $d($r->est))->count(),
                'total_delay'          => $g->filter(fn($r) => $r->tiba && $r->est && $d($r->tiba) > $d($r->est))->count(),
                'total_belum_tiba'     => $g->filter(fn($r) => !$r->tiba)->count(),
                'total_lewat_estimasi' => $g->filter(fn($r) => !$r->tiba && $r->est && $d($r->est) < $today)->count(),
            ])->sortByDesc('total')->values();

        $list_area         = $this->listAreas();
        $list_dist_channel = $this->listDistChannels();

        return view('sales.gabungan_dashboard', compact(
            'total_data', 'total_in_transit', 'gudang_ontime', 'total_sla_delay', 'gudang_delay',
            'customer_ontime', 'customer_delay', 'bongkar_ontime', 'bongkar_delay',
            'planner_armada', 'planner_belum_armada', 'planner_ontime', 'planner_delay',
            'ontime_rate', 'delay_rate', 'armada_rate', 'summary_monitoring',
            'totalBiayaKirim', 'totalNilaiMuatan',
            'summary_area', 'summary_tujuan', 'summary_planner', 'summary_pic_monitoring',
            'label', 'value', 'list_area', 'list_dist_channel'
        ));
    }

    /* =====================================================================
     |  HALAMAN LIST (hyperlink dari kartu dashboard)
     * ===================================================================== */
    public function listKpi(Request $request, string $kpi)
    {
        $cfg = self::LIST_CONFIG[$kpi] ?? abort(404);

        $rows = collect();
        foreach ($this->sources($request) as $s) {
            $c = $this->cfg($s);
            $q = $this->baseQuery($s, $request);
            $this->applyKpi($q, $c, $kpi);

            $got = DB::table($c['table'])->whereIn('id', $this->groupIds($q, $c))->get();
            foreach ($got as $row) {
                $rows->push($this->toGeneric($row, $s, $kpi));
            }
        }

        $order = $cfg['order'];
        $rows = $rows->sort(function ($a, $b) use ($order) {
            if ($order) {
                $cmp = strcmp((string) $b->$order, (string) $a->$order);
                if ($cmp !== 0) return $cmp;
            }
            return [(string) $a->no_shipment, (string) $a->tujuan] <=> [(string) $b->no_shipment, (string) $b->tujuan];
        })->values();

        return view($cfg['view'], [
            $cfg['var']  => $rows,
            'list_area'  => $this->listAreas(),
        ]);
    }

    /** Samakan bentuk baris Jakarta & Pasuruan supaya view sales.* bisa dipakai keduanya */
    private function toGeneric($r, string $src, string $kpi): object
    {
        $o = (array) $r;

        if ($src === 'pasuruan') {
            $x = [];
            foreach ($o as $k => $v) {
                $x[preg_replace('/_pasuruan$/', '', $k)] = $v;
            }
            $o = $x;

            $o['ekpedisi']              = $o['ekspedisi'] ?? null;
            $o['tanggal_naik_logistik'] = $o['tanggal_terima_po'] ?? null;
            $o['create_tgl']            = $o['tanggal_terima_po'] ?? null;
            $o['total_do_qty_car']      = $o['total_do'] ?? null;
            $o['qty_monitoring']        = $o['actual_delivery_quantity'] ?? null;
            $o['selisih_qty']           = $o['selisih_quantity'] ?? null;
            $o['remarks_qty']           = $o['reason_selisih_quantity'] ?? null;
            $o['reason_tiba']           = $o['reason_waktu_tiba'] ?? null;
            $o['reason_bongkar']        = $o['reason_waktu_bongkar'] ?? null;

            foreach (['planning_loading_2', 'tanggal_tiba_gudang_2', 'tanggal_keluar_gudang_2',
                      'planning_loading_3', 'tanggal_tiba_gudang_3', 'tanggal_keluar_gudang_3'] as $k) {
                $o[$k] = null;
            }
        }

        $o['sumber']           = strtoupper($src);
        $o['tanggal_estimasi'] = $o['estimasi_tiba'] ?? null;

        $ov = (float) ($o['overstay_days'] ?? 0);

        if ($kpi === 'tujuan_ontime')  $o['sla_tiba'] = 'On Time';
        if ($kpi === 'tujuan_delay')   $o['sla_tiba'] = 'Delay';
        if ($kpi === 'bongkar_ontime') $o['sla_bongkar'] = 'H+0';
        if ($kpi === 'bongkar_delay')  $o['sla_bongkar'] = $ov == 1 ? 'H+1' : ($ov == 2 ? 'H+2' : 'Critical Delay');

        return new class($o) extends \stdClass {
            public function __construct(array $data)
            {
                foreach ($data as $k => $v) {
                    $this->$k = $v;
                }
            }

            public function __get($name)
            {
                return null;
            }
        };
    }
}