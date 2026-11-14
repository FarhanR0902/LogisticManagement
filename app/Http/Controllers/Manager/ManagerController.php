<?php
namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\LogistikPengiriman;
use App\Models\LogistikPengirimanPasuruan;

class ManagerController extends Controller
{
    private const PULAU_MAP = [
        'JAWA'       => ['JABODEBEK','BANTEN','JAWA_BARAT','JAWA_TENGAH','JAWA_TIMUR','YOGYAKARTA'],
        'SUMATERA'   => ['ACEH','SUMATERA_UTARA','SUMATERA_BARAT','RIAU','KEP._RIAU','JAMBI','SUMATERA_SELATAN','BENGKULU','LAMPUNG','KEP._BANGKA_BELITUNG'],
        'KALIMANTAN' => ['KALIMANTAN_BARAT','KALIMANTAN_TENGAH','KALIMANTAN_SELATAN','KALIMANTAN_TIMUR','KALIMANTAN_UTARA'],
        'SULAWESI'   => ['SULAWESI_UTARA','SULAWESI_TENGAH','SULAWESI_SELATAN','SULAWESI_TENGGARA','SULAWESI_BARAT','GORONTALO'],
        'BALI_NUSRA' => ['PROV._BALI','NUSA_TENGGARA_BARAT','NUSA_TENGGARA_TIMUR'],
        'MALUKU'     => ['PROV._MALUKU','PROV._MALUKU_UTARA'],
        'PAPUA'      => ['PROV._PAPUA','PAPUA_BARAT','PAPUA_BARAT_DAYA','PAPUA_SELATAN','PAPUA_TENGAH'],
    ];

    /** kunci grup Jakarta = no_shipment + tujuan (baris tanpa no_shipment dianggap grup sendiri) */
    private const GROUP_KEY = "COALESCE(NULLIF(TRIM(no_shipment),''), CONCAT('id', id)), COALESCE(tujuan,'')";

    /** kunci grup Pasuruan */
    private const GROUP_KEY_PASURUAN = "COALESCE(NULLIF(TRIM(no_shipment_pasuruan),''), CONCAT('id', id)), COALESCE(tujuan_pasuruan,'')";

    /** lama cache dashboard (detik) */
    private const CACHE_DASHBOARD = 120;
    /** lama cache dropdown (detik) */
    private const CACHE_LIST = 3600;

    /**
     * SATU-SATUNYA daftar gudang Pasuruan.
     * URUTAN = urutan alur mobil. 'key' = suffix key output AJAX.
     * Dipakai juga oleh PasuruanController. Gudang baru: tambah 1 entri di sini.
     */
    public const GUDANG_PASURUAN = [
        'PASURUAN' => [
            'key'      => '',
            'planning' => 'planning_loading_pasuruan',
            'tiba'     => 'tanggal_tiba_gudang_pasuruan',
            'keluar'   => 'tanggal_keluar_gudang_pasuruan',
        ],
        'PUNTIR' => [
            'key'      => '_puntir',
            'planning' => 'planning_loading_puntir_pasuruan',
            'tiba'     => 'tanggal_tiba_gudang_puntir_pasuruan',
            'keluar'   => 'tanggal_keluar_gudang_puntir_pasuruan',
        ],
    ];

    /*
    |--------------------------------------------------------------------------
    | DASHBOARD (JAKARTA)
    |--------------------------------------------------------------------------
    | Di-cache 2 menit per kombinasi filter. Hitungan KPI digabung jadi 1 query.
    */
    public function dashboard(Request $request)
    {
        $key  = 'mgr.dashboard.' . md5(json_encode($request->query()));
        $data = Cache::remember($key, self::CACHE_DASHBOARD, fn() => $this->dashboardData($request));

        return view('dashboard', $data);
    }

    /** kondisi KPI dashboard (Jakarta) sebagai potongan SQL. Sama persis dengan query halaman list. */
    private function kpiFlagsSql(): array
    {
        $f = fn($c) => "NULLIF(TRIM({$c}), '') IS NOT NULL";
        $e = fn($c) => "NULLIF(TRIM({$c}), '') IS NULL";

        $tiba3   = ['tanggal_tiba_gudang', 'tanggal_tiba_gudang_2', 'tanggal_tiba_gudang_3'];
        $keluar3 = ['tanggal_keluar_gudang', 'tanggal_keluar_gudang_2', 'tanggal_keluar_gudang_3'];
        $plan3   = ['planning_loading', 'planning_loading_2', 'planning_loading_3'];

        $belumArmada = '(' . $e('rencana_kirim') . ' OR ' . $e('tanggal_dpt_unit') . ')';

        $menggantung = [];
        foreach ($plan3 as $i => $p) {
            $menggantung[] = 'NOT ((' . $f($p) . ' OR ' . $f($tiba3[$i]) . ') AND ' . $e($keluar3[$i]) . ')';
        }
        $inTransit = $e('tanggal_tiba')
            . ' AND (' . implode(' OR ', array_map($f, $keluar3)) . ')'
            . ' AND ' . implode(' AND ', $menggantung);

        $tibaCust = 'tanggal_tiba IS NOT NULL AND estimasi_tiba IS NOT NULL';
        $bongkar  = "tanggal_bongkar IS NOT NULL AND tanggal_bongkar != '1899-12-31 00:00:00'";

        return [
            'gudang_ontime'        => '(' . implode(' OR ', array_map(fn($c) => "{$c} IS NOT NULL", $tiba3)) . ')',
            'planner_belum_armada' => $belumArmada,
            'planner_armada'       => 'NOT ' . $belumArmada,
            'sla_delay'            => $f('rencana_kirim') . ' AND ' . $f('tanggal_dpt_unit') . ' AND '
                                      . implode(' AND ', array_map($e, $tiba3)),
            'customer_ontime'      => "{$tibaCust} AND DATEDIFF(DATE(tanggal_tiba), DATE(estimasi_tiba)) <= 0",
            'customer_delay'       => "{$tibaCust} AND DATEDIFF(DATE(tanggal_tiba), DATE(estimasi_tiba)) > 0",
            'bongkar_ontime'       => "{$bongkar} AND (overstay_days IS NULL OR overstay_days = 0)",
            'bongkar_delay'        => "{$bongkar} AND overstay_days > 0",
            'planner_ontime'       => 'rencana_kirim IS NOT NULL AND tanggal_dpt_unit IS NOT NULL AND DATE(tanggal_dpt_unit) <= DATE(rencana_kirim)',
            'planner_delay'        => 'rencana_kirim IS NOT NULL AND tanggal_dpt_unit IS NOT NULL AND DATE(tanggal_dpt_unit) > DATE(rencana_kirim)',
            'in_transit'           => $inTransit,
        ];
    }

    private function dashboardData(Request $request): array
    {
        $base = DB::table('logistik_pengiriman');
        $this->applyFilter($base, $request);

        // 1 baris wakil per shipment + tujuan (JOIN, bukan IN-subquery)
        $grouped = DB::table('logistik_pengiriman as l')
            ->joinSub($this->groupIdsQuery($base), 'r', 'r.id', '=', 'l.id');

        // ===== semua hitungan KPI dalam 1 query =====
        $flags = $this->kpiFlagsSql();

        $groupFlags = (clone $base)->selectRaw(
            'MIN(id) AS id, ' . implode(', ', array_map(
                fn($k, $c) => "MAX(CASE WHEN {$c} THEN 1 ELSE 0 END) AS f_{$k}",
                array_keys($flags), $flags
            ))
        )->groupByRaw(self::GROUP_KEY);

        $c = DB::query()->fromSub($groupFlags, 'g')->selectRaw(
            'COUNT(*) AS total_data, ' . implode(', ', array_map(
                fn($k) => "COALESCE(SUM(f_{$k}),0) AS {$k}", array_keys($flags)
            ))
        )->first();

        $total_data           = (int) $c->total_data;
        $gudang_ontime        = (int) $c->gudang_ontime;
        $gudang_delay         = (int) $c->planner_belum_armada;   // kondisinya sama
        $total_sla_delay      = (int) $c->sla_delay;
        $customer_ontime      = (int) $c->customer_ontime;
        $customer_delay       = (int) $c->customer_delay;
        $bongkar_ontime       = (int) $c->bongkar_ontime;
        $bongkar_delay        = (int) $c->bongkar_delay;
        $planner_armada       = (int) $c->planner_armada;
        $planner_belum_armada = (int) $c->planner_belum_armada;
        $planner_ontime       = (int) $c->planner_ontime;
        $planner_delay        = (int) $c->planner_delay;
        $total_in_transit     = (int) $c->in_transit;

        // ===== dropdown (cache) =====
        $list_dist_channel = Cache::remember('mgr.dist_channel', self::CACHE_LIST, fn() =>
            DB::table('logistik_pengiriman')
                ->select('dist_channel')
                ->whereNotNull('dist_channel')
                ->distinct()->orderBy('dist_channel')->get());

        $list_area = Cache::remember('mgr.area', self::CACHE_LIST, fn() => $this->getArea());

        // ===== biaya & muatan: 1 query, agregasi di PHP =====
        $grpRows = $this->grupBiayaQuery($base)->get();

        $totalBiayaKirim  = (float) $grpRows->sum('biaya');
        $totalNilaiMuatan = (float) $grpRows->sum('nilai');

        $summary_area = $grpRows->filter(fn($g) => $g->area !== null)
            ->groupBy('area')
            ->map(fn($g, $area) => (object) [
                'area'           => $area,
                'total_shipment' => $g->count(),
                'total_biaya'    => $g->sum('biaya'),
                'total_muatan'   => $g->sum('nilai'),
            ])->sortByDesc('total_shipment')->values();

        $summary_tujuan = $grpRows->filter(fn($g) => $g->tj !== '')
            ->groupBy('tj')
            ->map(fn($g, $tj) => (object) [
                'tujuan'         => $tj,
                'total_shipment' => $g->count(),
                'total_biaya'    => $g->sum('biaya'),
                'total_muatan'   => $g->sum('nilai'),
            ])->sortByDesc('total_shipment')->values();

        // pulau: basenya tanpa filter pulau, jadi query sendiri
        $reqTanpaPulau = $request->duplicate($request->except('pulau'));
        $basePulau     = $this->applyFilter(DB::table('logistik_pengiriman'), $reqTanpaPulau);
        $grpPulau      = $this->grupBiayaQuery($basePulau);

        $summary_pulau = DB::query()->fromSub($grpPulau, 'g')
            ->selectRaw('pulau,
                         COUNT(*)   AS total_shipment,
                         SUM(nilai) AS total_muatan,
                         SUM(biaya) AS total_biaya')
            ->whereNotNull('pulau')
            ->whereRaw("TRIM(pulau) <> ''")
            ->groupBy('pulau')
            ->orderByDesc('total_muatan')
            ->get();

        $label_pulau        = $summary_pulau->pluck('pulau');
        $value_muatan_pulau = $summary_pulau->pluck('total_muatan');
        $value_biaya_pulau  = $summary_pulau->pluck('total_biaya');

        $ekspedisi = (clone $grouped)
            ->select('kategori_ekspedisi', DB::raw('COUNT(*) as total'))
            ->whereNotNull('kategori_ekspedisi')
            ->groupBy('kategori_ekspedisi')
            ->get();

        $label = $ekspedisi->pluck('kategori_ekspedisi');
        $value = $ekspedisi->pluck('total');

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

        $belumIsi = "NULLIF(TRIM(rencana_kirim), '') IS NULL";

        $summary_planner = (clone $grouped)
            ->select(
                'planner',
                DB::raw("COUNT(*) as total"),
                DB::raw("SUM(CASE WHEN {$belumIsi} THEN 1 ELSE 0 END) as total_belum_rencana"),
                DB::raw("SUM(CASE WHEN ({$lastPlanning}) IS NOT NULL AND ({$lastTiba}) IS NOT NULL AND DATE({$lastTiba}) <= DATE({$lastPlanning}) THEN 1 ELSE 0 END) as total_ontime"),
                DB::raw("SUM(CASE WHEN ({$lastPlanning}) IS NOT NULL AND ({$lastTiba}) IS NOT NULL AND DATE({$lastTiba}) > DATE({$lastPlanning}) THEN 1 ELSE 0 END) as total_delay")
            )
            ->whereNotNull('planner')
            ->where('planner', '!=', '')
            ->groupBy('planner')
            ->orderByDesc('total')
            ->get();

        $summary_pic_monitoring = (clone $grouped)
            ->select(
                'pic_monitoring',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN tanggal_tiba IS NOT NULL AND estimasi_tiba IS NOT NULL AND DATEDIFF(DATE(tanggal_tiba), DATE(estimasi_tiba)) <= 0 THEN 1 ELSE 0 END) as total_ontime"),
                DB::raw("SUM(CASE WHEN tanggal_tiba IS NOT NULL AND estimasi_tiba IS NOT NULL AND DATEDIFF(DATE(tanggal_tiba), DATE(estimasi_tiba)) > 0 THEN 1 ELSE 0 END) as total_delay"),
                DB::raw("SUM(CASE WHEN NULLIF(TRIM(tanggal_tiba), '') IS NULL THEN 1 ELSE 0 END) as total_belum_tiba"),
                DB::raw("SUM(CASE WHEN NULLIF(TRIM(tanggal_tiba), '') IS NULL AND estimasi_tiba IS NOT NULL AND DATE(estimasi_tiba) < CURDATE() THEN 1 ELSE 0 END) as total_lewat_estimasi")
            )
            ->whereNotNull('pic_monitoring')
            ->where('pic_monitoring', '!=', '')
            ->groupBy('pic_monitoring')
            ->orderByDesc('total')
            ->get();

        return compact(
            'total_data',
            'summary_planner',
            'summary_pic_monitoring',
            'gudang_ontime',
            'gudang_delay',
            'total_in_transit',
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
            'total_sla_delay',
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
        );
    }

    public function slaDelay(Request $request)
    {
        $filter = $this->slaDelayQuery();
        $this->applyFilter($filter, $request);

        $list = DB::table('logistik_pengiriman')
            ->whereIn('id', $this->groupIdsQuery($filter))
            ->orderBy('tanggal_naik_logistik', 'DESC')
            ->orderBy('no_shipment')
            ->orderBy('tujuan')
            ->paginate(10)
            ->withQueryString();

        $list_area = Cache::remember('mgr.area', self::CACHE_LIST, fn() => $this->getArea());

        $list_dist_channel = Cache::remember('mgr.dist_channel', self::CACHE_LIST, fn() =>
            DB::table('logistik_pengiriman')
                ->select('dist_channel')->whereNotNull('dist_channel')
                ->distinct()->orderBy('dist_channel')->get());

        return view('manager.sla_delay', [
            'title'             => 'SLA DELAY',
            'list'              => $list,
            'list_area'         => $list_area,
            'list_dist_channel' => $list_dist_channel,
        ]);
    }

    private function slaDelayQuery()
    {
        return DB::table('logistik_pengiriman')
            ->whereNotNull('rencana_kirim')
            ->whereRaw("TRIM(rencana_kirim) <> ''")
            ->whereNotNull('tanggal_dpt_unit')
            ->whereRaw("TRIM(tanggal_dpt_unit) <> ''")
            ->where(fn ($q) => $q->whereNull('tanggal_tiba_gudang')->orWhere('tanggal_tiba_gudang', ''))
            ->where(fn ($q) => $q->whereNull('tanggal_tiba_gudang_2')->orWhere('tanggal_tiba_gudang_2', ''))
            ->where(fn ($q) => $q->whereNull('tanggal_tiba_gudang_3')->orWhere('tanggal_tiba_gudang_3', ''));
    }

    public function PasuruandataLogistik()
    {
        $logistik = LogistikPengirimanPasuruan::orderByDesc('id')->get();

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

        return view('manager.data_logistik', compact('logistik', 'planners', 'areas'));
    }

    // =====================================================
    // IN TRANSIT (JAKARTA)
    // =====================================================
    private function inTransitQuery()
    {
        $filled = fn($c) => "NULLIF(TRIM({$c}), '') IS NOT NULL";
        $empty  = fn($c) => "NULLIF(TRIM({$c}), '') IS NULL";

        $q = DB::table('logistik_pengiriman')
            ->whereRaw($empty('tanggal_tiba'));

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

    public function inTransit(Request $request)
    {
        $today   = date('Y-m-d');
        $todayTs = strtotime($today);
        $soon    = date('Y-m-d', strtotime('+3 days'));
        $est     = $this->inTransitEstimasiSql();

        $base = $this->inTransitQuery();
        $this->applyFilter($base, $request);

        $pics = array_values(array_filter((array) $request->input('pic_monitoring', []), 'strlen'));
        if (!empty($pics)) {
            $base->whereIn('pic_monitoring', $pics);
        }

        $asal = array_values(array_filter((array) $request->input('gudang_asal', []), 'strlen'));
        if (!empty($asal)) {
            $placeholders = implode(',', array_fill(0, count($asal), '?'));
            $base->whereRaw('(' . $this->inTransitAsalSql() . ") IN ({$placeholders})", $asal);
        }

        if ($request->filled('q')) {
            $s = trim($request->input('q'));
            $base->where(function ($q) use ($s) {
                foreach (['no_shipment', 'tujuan', 'ekpedisi', 'nama_driver', 'no_pol', 'mobil'] as $col) {
                    $q->orWhere($col, 'like', "%{$s}%");
                }
            });
        }

        $grouped = DB::table('logistik_pengiriman')->whereIn('id', $this->groupIdsQuery($base));

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

        $areaList = Cache::remember('mgr.area_list', self::CACHE_LIST, fn() =>
            DB::table('logistik_pengiriman')
                ->whereNotNull('area')->distinct()->orderBy('area')->pluck('area'));

        $picList = Cache::remember('mgr.pic_list', self::CACHE_LIST, fn() =>
            DB::table('logistik_pengiriman')
                ->whereNotNull('pic_monitoring')->distinct()->orderBy('pic_monitoring')->pluck('pic_monitoring'));

        $formRoute = route('manager.intransit');

        return view('monitoring.in_transit', compact('list', 'summary', 'areaList', 'picList', 'formRoute'));
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

    /*
    |--------------------------------------------------------------------------
    | FILTER (JAKARTA)
    |--------------------------------------------------------------------------
    */
    private function dateFilterColumn($request): string
    {
        return match ($request->input('tgl_basis')) {
            'rencana_kirim'         => 'rencana_kirim',
            'tanggal_naik_logistik' => 'tanggal_naik_logistik',
            default                 => 'periode_shipment',
        };
    }

    private function applyFilter($query, $request)
    {
        $areas = array_values(array_filter((array) $request->input('area', []), 'strlen'));
        if (!empty($areas)) {
            $query->whereIn('area', $areas);
        }

        if ($request->dist_channel) {
            $query->where('dist_channel', $request->dist_channel);
        }

        if ($request->filled('pulau') && isset(self::PULAU_MAP[$request->pulau])) {
            $query->whereIn('area', self::PULAU_MAP[$request->pulau]);
        }

        $col  = $this->dateFilterColumn($request);
        $from = $request->input('date_from');
        $to   = $request->input('date_to');

        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        if ($col === 'periode_shipment') {
            if ($request->month) {
                $query->where('periode_shipment', substr($request->month, 5, 2) . '-' . substr($request->month, 0, 4));
            }
            if ($request->year) {
                $query->where('periode_shipment', 'like', '%-' . $request->year);
            }
            if ($request->date) {
                $query->whereDate('tanggal_shipment', $request->date);
            }
            if ($from) $query->whereDate('tanggal_shipment', '>=', $from);
            if ($to)   $query->whereDate('tanggal_shipment', '<=', $to);

            return $query;
        }

        if ($request->date) {
            $query->whereDate($col, $request->date);
        }
        if ($from) $query->whereDate($col, '>=', $from);
        if ($to)   $query->whereDate($col, '<=', $to);

        if ($request->month) {
            $query->whereMonth($col, substr($request->month, 5, 2));
            $query->whereYear($col, substr($request->month, 0, 4));
        }
        if ($request->year) {
            $query->whereYear($col, $request->year);
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

    /** 1 id wakil (MIN id) per shipment + tujuan */
    private function groupIdsQuery($query)
    {
        return (clone $query)
            ->select(DB::raw('MIN(id) as id'))
            ->groupByRaw(self::GROUP_KEY);
    }

    private function countGroups($query): int
    {
        return DB::query()->fromSub($this->groupIdsQuery($query), 'g')->count();
    }

    private function grupBiayaQuery($query)
    {
        return (clone $query)
            ->selectRaw("
                COALESCE(NULLIF(TRIM(no_shipment),''), CONCAT('id', id)) AS sh,
                COALESCE(tujuan,'')            AS tj,
                MAX(area)                      AS area,
                MAX(pulau)                     AS pulau,
                MAX(COALESCE(biaya_kirim,0))   AS biaya,
                SUM(COALESCE(nilai_muatan,0))  AS nilai,
                COUNT(*)                       AS jml_do
            ")
            ->groupByRaw('sh, tj');
    }

    /*
    |--------------------------------------------------------------------------
    | HITUNG SLA (dipakai gudangOntime Jakarta & Pasuruan). 3 slot gudang.
    |--------------------------------------------------------------------------
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
                return ['text' => '0 Menit', 'days' => 0, 'hours' => 0, 'minutes' => 0, 'seconds' => 0];
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

        // 1. SLA DAPAT MOBIL
        $start = $request->rencana_kirim
            ? date('Y-m-d H:i:s', strtotime($request->rencana_kirim))
            : null;

        $end = $request->tanggal_dpt_unit
            ? date('Y-m-d H:i:s', strtotime($request->tanggal_dpt_unit))
            : null;

        $diff = $hitungSelisih($start, $end);

        $data['lama_waktu_pencarian'] = $diff['text'] ?? null;

        if ($start && $end) {
            $areaJawa = ['JAWA_BARAT', 'JAWA_TENGAH', 'JAWA_TIMUR', 'YOGYAKARTA', 'BANTEN', 'JABODEBEK'];

            $area = strtoupper(trim($request->area ?? ''));

            $tanggalRencana = date('Y-m-d', strtotime($start));
            $tanggalDptUnit = date('Y-m-d', strtotime($end));

            if (in_array($area, $areaJawa)) {
                if ($tanggalDptUnit > $tanggalRencana) {
                    $data['sla_dapat_mobil']   = 'Delay';
                    $data['status_pengiriman'] = 'Terlambat';
                } else {
                    $data['sla_dapat_mobil']   = 'On Time';
                    $data['status_pengiriman'] = 'Sudah Dapat';
                }
            } else {
                $selisihHari = floor((strtotime($tanggalDptUnit) - strtotime($tanggalRencana)) / 86400);

                if ($selisihHari > 2) {
                    $data['sla_dapat_mobil']   = 'Delay';
                    $data['status_pengiriman'] = 'Terlambat';
                } else {
                    $data['sla_dapat_mobil']   = 'On Time';
                    $data['status_pengiriman'] = 'Sudah Dapat';
                }
            }
        }

        // 2-4. GUDANG 1..3
        foreach (['', '_2', '_3'] as $s) {
            $tiba   = $request->{"tanggal_tiba_gudang{$s}"};
            $keluar = $request->{"tanggal_keluar_gudang{$s}"};

            if ($tiba && $keluar) {
                $diff = $hitungSelisih($tiba, $keluar);

                if ($diff) {
                    $data["lama_digudang{$s}"] = $diff['text'];

                    if ($diff['days'] > 0) {
                        $data["status_gudang{$s}"] = 'Delay';
                        $data["sla_loading{$s}"]   = 'H+' . $diff['days'];
                    } else {
                        $data["status_gudang{$s}"] = 'On Time';
                        $data["sla_loading{$s}"]   = 'Sesuai SLA';
                    }
                }
            }
        }

        return $data;
    }

    /*
    |--------------------------------------------------------------------------
    | LIST KPI JAKARTA
    |--------------------------------------------------------------------------
    */
    public function gudangDelay(Request $request)
    {
        $filter = DB::table('logistik_pengiriman')
            ->where(function ($q) {
                $q->whereNull('rencana_kirim')
                  ->orWhere('rencana_kirim', '')
                  ->orWhereNull('tanggal_dpt_unit')
                  ->orWhere('tanggal_dpt_unit', '');
            });

        $this->applyFilter($filter, $request);

        $logistik = DB::table('logistik_pengiriman')
            ->whereIn('id', $this->groupIdsQuery($filter))
            ->orderBy('tanggal_naik_logistik', 'DESC')
            ->orderBy('no_shipment')
            ->orderBy('tujuan')
            ->get();

        return view('planner.belum_armada', compact('logistik'));
    }

    public function gudangOntime(Request $request)
    {
        $filter = DB::table('logistik_pengiriman')
            ->where(function ($q) {
                $q->whereNotNull('tanggal_tiba_gudang')
                  ->orWhereNotNull('tanggal_tiba_gudang_2')
                  ->orWhereNotNull('tanggal_tiba_gudang_3');
            });

        $this->applyFilter($filter, $request);

        $list = DB::table('logistik_pengiriman')
            ->whereIn('id', $this->groupIdsQuery($filter))
            ->orderBy('no_shipment')
            ->orderBy('tujuan')
            ->get()
            ->map(function ($row) {
                $sla = $this->hitungSla($row);
                foreach ($sla as $k => $v) {
                    $row->$k = $v;
                }
                $this->tandaiGudangSla($row);
                return $row;
            })
            ->values();

        $list_area = Cache::remember('mgr.area', self::CACHE_LIST, fn() => $this->getArea());

        return view('manager.sla_ontime', compact('list', 'list_area'));
    }

    /** gudang tiba paling awal & keluar paling akhir (nomor slot 1..3) */
    private function tandaiGudangSla($row): void
    {
        $tiba = []; $keluar = [];
        foreach (['' => 1, '_2' => 2, '_3' => 3] as $s => $no) {
            if (!empty($row->{"tanggal_tiba_gudang{$s}"}))   $tiba[$no]   = strtotime($row->{"tanggal_tiba_gudang{$s}"});
            if (!empty($row->{"tanggal_keluar_gudang{$s}"})) $keluar[$no] = strtotime($row->{"tanggal_keluar_gudang{$s}"});
        }
        $row->gudang_sla             = $tiba   ? array_search(min($tiba), $tiba)     : null;
        $row->gudang_keluar_terakhir = $keluar ? array_search(max($keluar), $keluar) : null;
    }

    public function tujuanOntime(Request $request)
    {
        $filter = DB::table('logistik_pengiriman')
            ->whereNotNull('tanggal_tiba')
            ->whereNotNull('estimasi_tiba')
            ->whereRaw("DATEDIFF(DATE(tanggal_tiba), DATE(estimasi_tiba)) <= 0");

        $this->applyFilter($filter, $request);

        $logistik = DB::table('logistik_pengiriman')
            ->selectRaw("logistik_pengiriman.*, estimasi_tiba AS tanggal_estimasi, 'On Time' AS sla_tiba")
            ->whereIn('id', $this->groupIdsQuery($filter))
            ->orderByDesc('tanggal_tiba')
            ->orderBy('no_shipment')
            ->orderBy('tujuan')
            ->get();

        return view('manager.tujuan_ontime', compact('logistik'));
    }

    public function tujuanDelay(Request $request)
    {
        $filter = DB::table('logistik_pengiriman')
            ->whereNotNull('tanggal_tiba')
            ->whereNotNull('estimasi_tiba')
            ->whereRaw("DATEDIFF(DATE(tanggal_tiba), DATE(estimasi_tiba)) > 0");

        $this->applyFilter($filter, $request);

        $logistik = DB::table('logistik_pengiriman')
            ->selectRaw("logistik_pengiriman.*, estimasi_tiba AS tanggal_estimasi, 'Delay' AS sla_tiba")
            ->whereIn('id', $this->groupIdsQuery($filter))
            ->orderBy('no_shipment')
            ->orderBy('tujuan')
            ->orderBy('estimasi_tiba')
            ->get();

        return view('manager.tujuan_delay', compact('logistik'));
    }

    public function bongkarOntime(Request $request)
    {
        $filter = DB::table('logistik_pengiriman')
            ->whereNotNull('tanggal_bongkar')
            ->where('tanggal_bongkar', '!=', '1899-12-31 00:00:00')
            ->where(function ($q) {
                $q->whereNull('overstay_days')->orWhere('overstay_days', 0);
            });

        $this->applyFilter($filter, $request);

        $list = DB::table('logistik_pengiriman')
            ->selectRaw("logistik_pengiriman.*, 'On Time' AS sla_bongkar")
            ->whereIn('id', $this->groupIdsQuery($filter))
            ->orderByDesc('tanggal_bongkar')
            ->orderBy('no_shipment')
            ->orderBy('tujuan')
            ->get();

        return view('manager.bongkar_ontime', compact('list'));
    }

    public function bongkarDelay(Request $request)
    {
        $filter = DB::table('logistik_pengiriman')
            ->whereNotNull('tanggal_bongkar')
            ->where('tanggal_bongkar', '!=', '1899-12-31 00:00:00')
            ->where('overstay_days', '>', 0);

        $this->applyFilter($filter, $request);

        $list = DB::table('logistik_pengiriman')
            ->whereIn('id', $this->groupIdsQuery($filter))
            ->orderByDesc('tanggal_bongkar')
            ->orderBy('no_shipment')
            ->orderBy('tujuan')
            ->get();

        return view('manager.bongkar_delay', compact('list'));
    }

    public function summaryTotal(Request $request)
    {
        $filter = DB::table('logistik_pengiriman');
        $this->applyFilter($filter, $request);

        $logistik = DB::table('logistik_pengiriman')
            ->whereIn('id', $this->groupIdsQuery($filter))
            ->orderBy('no_shipment')
            ->orderBy('tujuan')
            ->get();

        return view('manager.summary_total', compact('logistik'));
    }

    public function summaryArea(Request $request)
    {
        $query = DB::table('logistik_pengiriman');
        $this->applyFilter($query, $request);

        $grp = $this->grupBiayaQuery($query);

        $summary_area = DB::query()->fromSub($grp, 'g')
            ->selectRaw('area, COUNT(*) AS total_shipment, SUM(biaya) AS total_biaya, SUM(nilai) AS total_muatan')
            ->whereNotNull('area')
            ->groupBy('area')
            ->orderByDesc('total_shipment')
            ->get();

        return view('manager.summary_area', compact('summary_area'));
    }

    public function planner()
    {
        return redirect()->route('planner.dashboard');
    }

    public function monitoring()
    {
        return redirect()->route('monitoring.dashboard');
    }

    /* =========================================================================
     *  PASURUAN — HELPER GUDANG (semua loop dari self::GUDANG_PASURUAN)
     * ========================================================================= */

    /** $field: planning | tiba | keluar */
    private function gudangCols(string $field): array
    {
        return array_column(self::GUDANG_PASURUAN, $field);
    }

    private function namaGudangPasuruan(): array
    {
        return array_keys(self::GUDANG_PASURUAN);
    }

    private function sqlFilled(string $c): string { return "NULLIF(TRIM({$c}), '') IS NOT NULL"; }
    private function sqlEmpty(string $c): string  { return "NULLIF(TRIM({$c}), '') IS NULL"; }

    private function sqlAnyFilled(string $field): string
    {
        return '(' . implode(' OR ', array_map(fn($c) => $this->sqlFilled($c), $this->gudangCols($field))) . ')';
    }

    private function sqlAllEmpty(string $field): string
    {
        return '(' . implode(' AND ', array_map(fn($c) => $this->sqlEmpty($c), $this->gudangCols($field))) . ')';
    }

    /** tanggal terbaru dari semua gudang ($field: tiba | keluar) */
    private function sqlTerbaru(string $field): string
    {
        $p = array_map(
            fn($c) => "COALESCE(NULLIF(TRIM({$c}),''),'1900-01-01')",
            $this->gudangCols($field)
        );
        return count($p) > 1 ? 'GREATEST(' . implode(',', $p) . ')' : $p[0];
    }

    /** nama gudang asal = keluar terbaru. Seri -> gudang yang lebih akhir di urutan. */
    private function inTransitAsalSqlPasuruan(): string
    {
        $g    = self::GUDANG_PASURUAN;
        $k    = fn($c) => "COALESCE(NULLIF(TRIM({$c}),''),'1900-01-01')";
        $nama = array_keys($g);
        $sql  = 'CASE';

        foreach (array_reverse($nama) as $n) {
            $mine  = $k($g[$n]['keluar']);
            $conds = ["{$mine} > '1900-01-01'"];
            foreach ($nama as $other) {
                if ($other !== $n) $conds[] = "{$mine} >= " . $k($g[$other]['keluar']);
            }
            $sql .= ' WHEN ' . implode(' AND ', $conds) . " THEN '{$n}'";
        }

        return $sql . " ELSE '{$nama[0]}' END";
    }

    /** potongan SQL "in transit" Pasuruan: belum tiba tujuan, ada gudang yg sudah keluar, tidak ada yg menggantung */
    private function inTransitConditionSqlPasuruan(): string
    {
        $parts = [
            $this->sqlEmpty('tanggal_tiba_pasuruan'),
            $this->sqlAnyFilled('keluar'),
        ];
        foreach (self::GUDANG_PASURUAN as $g) {
            $parts[] = 'NOT ((' . $this->sqlFilled($g['planning']) . ' OR ' . $this->sqlFilled($g['tiba'])
                . ') AND ' . $this->sqlEmpty($g['keluar']) . ')';
        }
        return implode(' AND ', $parts);
    }

    /** in transit: belum tiba tujuan, ada gudang yg sudah keluar, tidak ada gudang yg menggantung */
    private function whereInTransitPasuruan($q)
    {
        return $q->whereRaw($this->inTransitConditionSqlPasuruan());
    }

    private function inTransitQueryPasuruan()
    {
        return $this->whereInTransitPasuruan(DB::table('logistik_pengiriman_pasuruan'));
    }

    private function inTransitEstimasiSqlPasuruan(): string
    {
        return "COALESCE(estimasi_tiba_pasuruan, DATE_ADD(
            " . $this->sqlTerbaru('keluar') . ",
            INTERVAL CAST(COALESCE(NULLIF(TRIM(transport_lead_time_pasuruan),''),0) AS UNSIGNED) DAY
        ))";
    }

    /** [nama gudang asal, timestamp keluar] 1 baris. Seri -> gudang terakhir. */
    private function gudangAsalRow($r): array
    {
        $asal = '-'; $ts = null;
        foreach (self::GUDANG_PASURUAN as $nama => $g) {
            $v = $r->{$g['keluar']} ?? null;
            if (!empty($v)) {
                $t = strtotime($v);
                if ($ts === null || $t >= $ts) { $ts = $t; $asal = $nama; }
            }
        }
        return [$asal, $ts];
    }

    /** Posisi mobil: loop semua gudang sesuai urutan */
    private function badgeStatusPosisiMobilPasuruanManager($r): string
    {
        $ada = fn($v) => trim((string) $v) !== '';

        if (!$ada($r->tanggal_dpt_unit_pasuruan)) {
            return '<span class="badge red">MENCARI UNIT</span>';
        }

        $dipakai = false;
        foreach (self::GUDANG_PASURUAN as $nama => $g) {
            $p = $ada($r->{$g['planning']});
            $t = $ada($r->{$g['tiba']});
            $k = $ada($r->{$g['keluar']});
            if (!$p && !$t && !$k) continue;
            $dipakai = true;

            if (!$t) return '<span class="badge orange">' . e("PERJALANAN KE {$nama}") . '</span>';
            if (!$k) return '<span class="badge blue">'   . e("DI GUDANG {$nama}")      . '</span>';
        }

        if (!$dipakai && !$ada($r->tanggal_tiba_pasuruan)) return '<span class="badge orange">PERJALANAN KE GUDANG</span>';
        if (!$ada($r->tanggal_tiba_pasuruan))              return '<span class="badge yellow">PERJALANAN KE TUJUAN</span>';
        if (!$ada($r->tanggal_bongkar_pasuruan))           return '<span class="badge blue">TIBA DI TUJUAN</span>';
        return '<span class="badge green">SUDAH SELESAI</span>';
    }

    /**
     * Key output AJAX per gudang:
     *   PASURUAN -> planning_loading_fmt, tanggal_tiba_gudang_fmt, tanggal_keluar_gudang_fmt
     *   PUNTIR   -> planning_loading_puntir_fmt, tanggal_tiba_gudang_puntir_fmt, tanggal_keluar_gudang_puntir_fmt
     */
    private function gudangCellsManager($r): array
    {
        $out = [];
        foreach (self::GUDANG_PASURUAN as $g) {
            $k = $g['key'];
            $out["planning_loading{$k}_fmt"]      = $this->fmtDatePasuruanManager($r->{$g['planning']});
            $out["tanggal_tiba_gudang{$k}_fmt"]   = $this->fmtDatePasuruanManager($r->{$g['tiba']});
            $out["tanggal_keluar_gudang{$k}_fmt"] = $this->fmtDatePasuruanManager($r->{$g['keluar']});
        }
        return $out;
    }

    /* =========================================================================
     *  PASURUAN — QUERY DASAR
     * ========================================================================= */

    private function groupIdsQueryPasuruan($query)
    {
        return (clone $query)
            ->select(DB::raw('MIN(id) as id'))
            ->groupByRaw(self::GROUP_KEY_PASURUAN);
    }

    private function countGroupsPasuruan($query): int
    {
        return DB::query()->fromSub($this->groupIdsQueryPasuruan($query), 'g')->count();
    }

    /** Agregat per grup (shipment + tujuan). Rumus biaya SAMA dengan tabel data logistik. */
    private function grupBiayaQueryPasuruan($query)
    {
        $n = fn($c) => "COALESCE(CAST(NULLIF(TRIM({$c}), '') AS DECIMAL(18,4)), 0)";

        return (clone $query)
            ->selectRaw("
                COALESCE(NULLIF(TRIM(no_shipment_pasuruan),''), CONCAT('id', id)) AS sh,
                COALESCE(tujuan_pasuruan,'') AS tj,
                MAX(area_pasuruan) AS area_pasuruan,
                IF(SUM(" . $n('kirim_kontribusi_pasuruan') . ") > 0,
                   SUM(" . $n('biaya_kirim_pasuruan') . "),
                   MAX(" . $n('biaya_kirim_pasuruan') . ")) AS biaya,
                SUM(" . $n('nilai_muatan_pasuruan') . ") AS nilai,
                COUNT(*) AS jml_do
            ")
            ->groupByRaw('sh, tj');
    }

    private function listAreaPasuruan()
    {
        return Cache::remember('mgr.pas.area_list', self::CACHE_LIST, fn() =>
            DB::table('logistik_pengiriman_pasuruan')
                ->select('area_pasuruan as area')
                ->whereNotNull('area_pasuruan')
                ->where('area_pasuruan', '!=', '')
                ->distinct()->orderBy('area_pasuruan')->get());
    }

    private function applyFilterPasuruan($query, $request)
    {
        $areas = array_values(array_filter((array) $request->input('area', []), 'strlen'));
        if (!empty($areas)) {
            $query->whereIn('area_pasuruan', $areas);
        }
        if ($request->filled('pulau') && isset(self::PULAU_MAP[$request->pulau])) {
            $query->whereIn('area_pasuruan', self::PULAU_MAP[$request->pulau]);
        }
        if ($request->filled('dist_channel')) {
            $query->where('dist_channel_pasuruan', $request->dist_channel);
        }

        $col = match ($request->input('tgl_basis')) {
            'tanggal_terima_po_pasuruan' => 'tanggal_terima_po_pasuruan',
            'rencana_kirim_pasuruan'     => 'rencana_kirim_pasuruan',
            'create_tgl_pasuruan'        => 'create_tgl_pasuruan',
            default                      => 'periode_shipment_pasuruan',
        };

        $from = $request->input('date_from');
        $to   = $request->input('date_to');
        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        $month = (string) $request->input('month', '');
        $year  = (string) $request->input('year', '');

        if ($col === 'periode_shipment_pasuruan') {
            if (preg_match('/^(\d{4})-(\d{2})$/', $month, $m)) {
                $query->where('periode_shipment_pasuruan', $m[2] . '-' . $m[1]);
            } elseif (ctype_digit($month) && (int) $month >= 1 && (int) $month <= 12) {
                $query->where('periode_shipment_pasuruan', 'like', sprintf('%02d-', (int) $month) . '%');
            }
            if (ctype_digit($year)) {
                $query->where('periode_shipment_pasuruan', 'like', '%-' . $year);
            }
            if ($request->filled('date')) $query->whereDate('tanggal_shipment_pasuruan', $request->date);
            if ($from) $query->whereDate('tanggal_shipment_pasuruan', '>=', $from);
            if ($to)   $query->whereDate('tanggal_shipment_pasuruan', '<=', $to);

            return $query;
        }

        if ($request->filled('date')) $query->whereDate($col, $request->date);
        if ($from) $query->whereDate($col, '>=', $from);
        if ($to)   $query->whereDate($col, '<=', $to);

        if (preg_match('/^(\d{4})-(\d{2})$/', $month, $m)) {
            $query->whereYear($col, $m[1])->whereMonth($col, $m[2]);
        }
        if (ctype_digit($year)) {
            $query->whereYear($col, $year);
        }

        return $query;
    }

    /** Filter dashboard -> Data Logistik Pasuruan (AJAX). Dari dash[...] atau query string Referer. */
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

        return new Request(array_filter($params, fn($v) => is_scalar($v) || is_array($v)));
    }

    /**
     * Kondisi tiap KPI Pasuruan dalam bentuk potongan SQL.
     * SATU sumber untuk kartu dashboard (flag) dan halaman list (kpiQueryPasuruan),
     * jadi angkanya selalu sama.
     */
    private function kpiConditionsPasuruan(): array
    {
        $tibaCust = 'tanggal_tiba_pasuruan IS NOT NULL AND estimasi_tiba_pasuruan IS NOT NULL';
        $bongkar  = "tanggal_bongkar_pasuruan IS NOT NULL AND tanggal_bongkar_pasuruan != '1899-12-31 00:00:00'";

        return [
            // sudah tiba di minimal 1 gudang
            'gudang_ontime'  => $this->sqlAnyFilled('tiba'),
            // sudah dapat unit, belum tiba di gudang manapun
            'gudang_delay'   => $this->sqlFilled('rencana_kirim_pasuruan') . ' AND '
                              . $this->sqlFilled('tanggal_dpt_unit_pasuruan') . ' AND '
                              . $this->sqlAllEmpty('tiba'),
            'tujuan_ontime'  => "{$tibaCust} AND DATEDIFF(DATE(tanggal_tiba_pasuruan), DATE(estimasi_tiba_pasuruan)) <= 0",
            'tujuan_delay'   => "{$tibaCust} AND DATEDIFF(DATE(tanggal_tiba_pasuruan), DATE(estimasi_tiba_pasuruan)) > 0",
            'bongkar_ontime' => "{$bongkar} AND (overstay_days_pasuruan IS NULL OR overstay_days_pasuruan = 0)",
            'bongkar_delay'  => "{$bongkar} AND overstay_days_pasuruan > 0",
        ];
    }

    /** Query dasar tiap KPI. Dipakai halaman list. */
    private function kpiQueryPasuruan(string $kpi)
    {
        $q = DB::table('logistik_pengiriman_pasuruan');
        $c = $this->kpiConditionsPasuruan();

        if (isset($c[$kpi])) {
            $q->whereRaw($c[$kpi]);
        }

        return $q;
    }

    /** Baris wakil (1 per shipment + tujuan) untuk 1 KPI, sudah kena filter & urutan. */
    private function kpiGroupedPasuruan(Request $request, string $kpi)
    {
        $filter = $this->applyFilterPasuruan($this->kpiQueryPasuruan($kpi), $request);

        $q = DB::table('logistik_pengiriman_pasuruan')
            ->whereIn('id', $this->groupIdsQueryPasuruan($filter));

        if ($kpi === 'gudang_ontime') {
            $q->orderByRaw($this->sqlTerbaru('tiba') . ' DESC');
        } else {
            $q->orderByDesc([
                'gudang_delay'   => 'tanggal_dpt_unit_pasuruan',
                'tujuan_ontime'  => 'tanggal_tiba_pasuruan',
                'tujuan_delay'   => 'tanggal_tiba_pasuruan',
                'bongkar_ontime' => 'tanggal_bongkar_pasuruan',
                'bongkar_delay'  => 'tanggal_bongkar_pasuruan',
            ][$kpi]);
        }

        return $q->orderBy('no_shipment_pasuruan')->orderBy('tujuan_pasuruan');
    }

    /**
     * Baris *_pasuruan -> objek kolom biasa (tanpa suffix) supaya view manager.* lama bisa dipakai ulang.
     * Gudang Pasuruan dipetakan ke slot view lama: '' (gudang 1), '_2', '_3'.
     */
    private function pasuruanToGeneric($r, array $extra = []): object
    {
        $o = [];
        foreach ((array) $r as $k => $v) {
            $o[preg_replace('/_pasuruan$/', '', $k)] = $v;
        }

        $o['ekpedisi']              = $o['ekspedisi'] ?? null;
        $o['tanggal_naik_logistik'] = $o['tanggal_terima_po'] ?? null;
        $o['create_tgl']            = $o['tanggal_terima_po'] ?? null;
        $o['total_do_qty_car']      = $o['total_do'] ?? null;
        $o['qty_monitoring']        = $o['actual_delivery_quantity'] ?? null;
        $o['selisih_qty']           = $o['selisih_quantity'] ?? null;
        $o['remarks_qty']           = $o['reason_selisih_quantity'] ?? null;
        $o['reason_tiba']           = $o['reason_waktu_tiba'] ?? null;
        $o['reason_bongkar']        = $o['reason_waktu_bongkar'] ?? null;

        // gudang -> slot generik (view lama cuma punya 3 slot); slot kosong = null
        $slots  = ['', '_2', '_3'];
        $gudang = array_values(self::GUDANG_PASURUAN);
        foreach ($slots as $i => $s) {
            $g = $gudang[$i] ?? null;
            $o["planning_loading{$s}"]      = $g ? ($r->{$g['planning']} ?? null) : null;
            $o["tanggal_tiba_gudang{$s}"]   = $g ? ($r->{$g['tiba']}     ?? null) : null;
            $o["tanggal_keluar_gudang{$s}"] = $g ? ($r->{$g['keluar']}   ?? null) : null;
        }

        $o['tanggal_estimasi'] = $o['estimasi_tiba'] ?? null;
        $o['sla_tiba'] = (!empty($o['tanggal_tiba']) && !empty($o['estimasi_tiba']))
            ? (date('Y-m-d', strtotime($o['tanggal_tiba'])) <= date('Y-m-d', strtotime($o['estimasi_tiba'])) ? 'On Time' : 'Delay')
            : null;

        $ov = (float) ($o['overstay_days'] ?? 0);
        $o['sla_bongkar'] = $ov <= 0 ? 'H+0' : ($ov <= 1 ? 'H+1' : ($ov <= 2 ? 'H+2' : 'Critical Delay'));

        return new class(array_merge($o, $extra)) extends \stdClass {
            public function __construct(array $data)
            {
                foreach ($data as $k => $v) {
                    $this->$k = $v;
                }
            }

            // properti yang tidak ada di tabel Pasuruan -> null, bukan error
            public function __get($name)
            {
                return null;
            }
        };
    }

    private function renderKpiPasuruan(Request $request, string $kpi, string $view, string $var, array $extra = [], bool $withSla = false)
    {
        $list = $this->kpiGroupedPasuruan($request, $kpi)->get()->map(function ($r) use ($extra, $withSla) {
            $row = $this->pasuruanToGeneric($r, $extra);

            if ($withSla) {
                foreach ($this->hitungSla($row) as $k => $v) {
                    $row->$k = $v;
                }
                $this->tandaiGudangSla($row);
            }

            return $row;
        })->values();

        return view($view, [$var => $list, 'list_area' => $this->listAreaPasuruan()]);
    }

    /* =========================================================================
     *  PASURUAN — DASHBOARD (di-cache 2 menit, KPI digabung jadi 1 query)
     * ========================================================================= */
    public function dashboardPasuruan(Request $request)
    {
        $key  = 'mgr.pas.dashboard.' . md5(json_encode($request->query()));
        $data = Cache::remember($key, self::CACHE_DASHBOARD, fn() => $this->dashboardPasuruanData($request));

        return view('manager.dashboard_pasuruan', $data);
    }

    private function dashboardPasuruanData(Request $request): array
    {
        $base = DB::table('logistik_pengiriman_pasuruan');
        $this->applyFilterPasuruan($base, $request);

        $grouped = DB::table('logistik_pengiriman_pasuruan as l')
            ->joinSub($this->groupIdsQueryPasuruan($base), 'r', 'r.id', '=', 'l.id');

        // ===== semua hitungan KPI dalam 1 query =====
        $flags = $this->kpiConditionsPasuruan();
        $flags['in_transit']           = $this->inTransitConditionSqlPasuruan();
        $flags['planner_belum_armada'] = '(' . $this->sqlEmpty('rencana_kirim_pasuruan') . ' OR '
                                       . $this->sqlEmpty('tanggal_dpt_unit_pasuruan') . ')';
        $flags['planner_armada']       = 'NOT ' . $flags['planner_belum_armada'];
        $flags['planner_ontime']       = 'rencana_kirim_pasuruan IS NOT NULL AND tanggal_dpt_unit_pasuruan IS NOT NULL '
                                       . 'AND DATE(tanggal_dpt_unit_pasuruan) <= DATE(rencana_kirim_pasuruan)';
        $flags['planner_delay']        = 'rencana_kirim_pasuruan IS NOT NULL AND tanggal_dpt_unit_pasuruan IS NOT NULL '
                                       . 'AND DATE(tanggal_dpt_unit_pasuruan) > DATE(rencana_kirim_pasuruan)';

        $groupFlags = (clone $base)->selectRaw(
            'MIN(id) AS id, ' . implode(', ', array_map(
                fn($k, $c) => "MAX(CASE WHEN {$c} THEN 1 ELSE 0 END) AS f_{$k}",
                array_keys($flags), $flags
            ))
        )->groupByRaw(self::GROUP_KEY_PASURUAN);

        $c = DB::query()->fromSub($groupFlags, 'g')->selectRaw(
            'COUNT(*) AS total_data, ' . implode(', ', array_map(
                fn($k) => "COALESCE(SUM(f_{$k}),0) AS {$k}", array_keys($flags)
            ))
        )->first();

        $total_data           = (int) $c->total_data;
        $gudang_ontime        = (int) $c->gudang_ontime;
        $gudang_delay         = (int) $c->gudang_delay;
        $customer_ontime      = (int) $c->tujuan_ontime;
        $customer_delay       = (int) $c->tujuan_delay;
        $bongkar_ontime       = (int) $c->bongkar_ontime;
        $bongkar_delay        = (int) $c->bongkar_delay;
        $total_in_transit     = (int) $c->in_transit;
        $planner_armada       = (int) $c->planner_armada;
        $planner_belum_armada = (int) $c->planner_belum_armada;
        $planner_ontime       = (int) $c->planner_ontime;
        $planner_delay        = (int) $c->planner_delay;

        // ===== dropdown (cache) =====
        $list_dist_channel = Cache::remember('mgr.pas.dist_channel', self::CACHE_LIST, fn() =>
            DB::table('logistik_pengiriman_pasuruan')
                ->select('dist_channel_pasuruan')
                ->whereNotNull('dist_channel_pasuruan')
                ->distinct()->orderBy('dist_channel_pasuruan')->get());

        $list_area = $this->listAreaPasuruan();

        // ===== biaya & muatan: 1 query, agregasi di PHP =====
        $grpRows = $this->grupBiayaQueryPasuruan($base)->get();

        $totalBiayaKirim  = (float) $grpRows->sum('biaya');
        $totalNilaiMuatan = (float) $grpRows->sum('nilai');

        $summary_area = $grpRows->filter(fn($g) => $g->area_pasuruan !== null)
            ->groupBy('area_pasuruan')
            ->map(fn($g, $area) => (object) [
                'area_pasuruan'  => $area,
                'total_shipment' => $g->count(),
                'total_biaya'    => $g->sum('biaya'),
                'total_muatan'   => $g->sum('nilai'),
            ])->sortByDesc('total_shipment')->values();

        $summary_tujuan = $grpRows->filter(fn($g) => $g->tj !== '')
            ->groupBy('tj')
            ->map(fn($g, $tj) => (object) [
                'tujuan_pasuruan' => $tj,
                'total_shipment'  => $g->count(),
                'total_biaya'     => $g->sum('biaya'),
                'total_muatan'    => $g->sum('nilai'),
            ])->sortByDesc('total_shipment')->values();

        $ekspedisi = (clone $grouped)
            ->select('kategori_ekspedisi_pasuruan', DB::raw('COUNT(*) as total'))
            ->whereNotNull('kategori_ekspedisi_pasuruan')
            ->groupBy('kategori_ekspedisi_pasuruan')->get();

        $label = $ekspedisi->pluck('kategori_ekspedisi_pasuruan');
        $value = $ekspedisi->pluck('total');

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

        return compact(
            'total_data',
            'gudang_ontime', 'gudang_delay',
            'customer_ontime', 'customer_delay',
            'bongkar_ontime', 'bongkar_delay',
            'summary_area', 'summary_tujuan',
            'totalNilaiMuatan', 'totalBiayaKirim',
            'ekspedisi', 'label', 'value',
            'planner_ontime', 'planner_delay',
            'planner_armada', 'planner_belum_armada',
            'ontime_rate', 'delay_rate',
            'total_in_transit',
            'armada_rate', 'pending_rate',
            'summary_monitoring', 'list_dist_channel', 'list_area'
        );
    }

    /* =========================================================================
     *  PASURUAN — IN TRANSIT
     * ========================================================================= */
    public function inTransitPasuruan(Request $request)
    {
        $today   = date('Y-m-d');
        $todayTs = strtotime($today);
        $soon    = date('Y-m-d', strtotime('+3 days'));
        $est     = $this->inTransitEstimasiSqlPasuruan();

        $base = $this->inTransitQueryPasuruan();
        $this->applyFilterPasuruan($base, $request);

        $pics = array_values(array_filter((array) $request->input('pic_monitoring', []), 'strlen'));
        if ($pics) {
            $base->whereIn('pic_monitoring_pasuruan', $pics);
        }

        $asal = array_values(array_filter((array) $request->input('gudang_asal', []), 'strlen'));
        if ($asal) {
            $base->whereRaw('(' . $this->inTransitAsalSqlPasuruan() . ') IN ('
                . implode(',', array_fill(0, count($asal), '?')) . ')', $asal);
        }

        if ($request->filled('q')) {
            $s = trim($request->input('q'));
            $base->where(function ($q) use ($s) {
                foreach ([
                    'no_shipment_pasuruan', 'tujuan_pasuruan', 'ekspedisi_pasuruan',
                    'nama_driver_pasuruan', 'no_pol_pasuruan', 'mobil_pasuruan',
                ] as $col) {
                    $q->orWhere($col, 'like', "%{$s}%");
                }
            });
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

        $list->getCollection()->transform(function ($r) use ($todayTs) {
            [$asalNama, $keluar] = $this->gudangAsalRow($r);

            $lead     = (int) ($r->transport_lead_time_pasuruan ?? 0);
            $keluarD  = $keluar ? strtotime(date('Y-m-d', $keluar)) : null;
            $estimasi = !empty($r->estimasi_tiba_pasuruan)
                ? strtotime($r->estimasi_tiba_pasuruan)
                : ($keluarD ? strtotime("+{$lead} days", $keluarD) : null);

            $alert = '-'; $cls = 'gray';
            if ($estimasi) {
                $sisa = floor(($estimasi - $todayTs) / 86400);
                if     ($sisa < 0)  { $alert = 'Pending Tiba H+' . abs($sisa); $cls = 'red'; }
                elseif ($sisa <= 1) { $alert = 'H-' . $sisa; $cls = 'red'; }
                elseif ($sisa <= 3) { $alert = 'H-' . $sisa; $cls = 'orange'; }
                elseif ($sisa <= 7) { $alert = 'H-' . $sisa; $cls = 'blue'; }
                else                { $alert = 'ON TRACK';   $cls = 'green'; }
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
                'gudang_asal'    => $asalNama,
                'keluar_label'   => $keluar ? date('d-m-Y', $keluar) : '-',
                'hari_transit'   => $keluarD ? max(0, floor(($todayTs - $keluarD) / 86400)) : null,
                'estimasi_label' => $estimasi ? date('d-m-Y', $estimasi) : '-',
                'alert_label'    => $alert,
                'alert_class'    => $cls,
            ];
        });

        $areaList = Cache::remember('mgr.pas.area_plain', self::CACHE_LIST, fn() =>
            DB::table('logistik_pengiriman_pasuruan')
                ->whereNotNull('area_pasuruan')->distinct()->orderBy('area_pasuruan')->pluck('area_pasuruan'));

        $picList = Cache::remember('mgr.pas.pic_list', self::CACHE_LIST, fn() =>
            DB::table('logistik_pengiriman_pasuruan')
                ->whereNotNull('pic_monitoring_pasuruan')->distinct()->orderBy('pic_monitoring_pasuruan')->pluck('pic_monitoring_pasuruan'));

        return view('monitoring.in_transit', [
            'list'           => $list,
            'summary'        => $summary,
            'areaList'       => $areaList,
            'picList'        => $picList,
            'formRoute'      => route('manager.pasuruan.intransit'),
            'gudangAsalList' => $this->namaGudangPasuruan(),
        ]);
    }

    /* =========================================================================
     *  PASURUAN — HALAMAN LIST KPI
     * ========================================================================= */
    public function gudangOntimePasuruan(Request $request)
    {
        return $this->renderKpiPasuruan($request, 'gudang_ontime', 'manager.sla_ontime', 'list', [], true);
    }

    public function gudangDelayPasuruan(Request $request)
    {
        $list = $this->kpiGroupedPasuruan($request, 'gudang_delay')->paginate(10)->withQueryString();
        $list->getCollection()->transform(fn($r) => $this->pasuruanToGeneric($r));

        return view('manager.sla_delay', [
            'title'             => 'SLA DELAY',
            'list'              => $list,
            'list_area'         => $this->listAreaPasuruan(),
            'list_dist_channel' => Cache::remember('mgr.pas.dist_channel_generic', self::CACHE_LIST, fn() =>
                DB::table('logistik_pengiriman_pasuruan')
                    ->select('dist_channel_pasuruan as dist_channel')
                    ->whereNotNull('dist_channel_pasuruan')
                    ->distinct()->orderBy('dist_channel_pasuruan')->get()),
        ]);
    }

    public function tujuanOntimePasuruan(Request $request)
    {
        return $this->renderKpiPasuruan($request, 'tujuan_ontime', 'manager.tujuan_ontime', 'logistik');
    }

    public function tujuanDelayPasuruan(Request $request)
    {
        return $this->renderKpiPasuruan($request, 'tujuan_delay', 'manager.tujuan_delay', 'logistik');
    }

    public function bongkarOntimePasuruan(Request $request)
    {
        return $this->renderKpiPasuruan($request, 'bongkar_ontime', 'manager.bongkar_ontime', 'list', ['sla_bongkar' => 'On Time']);
    }

    public function bongkarDelayPasuruan(Request $request)
    {
        return $this->renderKpiPasuruan($request, 'bongkar_delay', 'manager.bongkar_delay', 'list');
    }

    /* =========================================================================
     *  PASURUAN — DATA LOGISTIK (halaman + AJAX read-only)
     * ========================================================================= */
    public function dataLogistikPasuruan()
    {
        $planners = $this->cachedListPasuruanManager('planner_pasuruan');
        $areas    = $this->cachedListPasuruanManager('area_pasuruan');

        return view('manager.data_logistik_pasuruan', compact('planners', 'areas'));
    }

    public function dataLogistikPasuruanAjax(Request $request)
    {
        $query = LogistikPengirimanPasuruan::query();
        $this->applyFilterPasuruan($query, $this->dashRequestPasuruan($request));

        if ($request->filled('date')) {
            $query->whereDate('tanggal_shipment_pasuruan', $request->date);
        }
        $mDrop = (string) $request->input('month', '');
        if (ctype_digit($mDrop) && (int) $mDrop >= 1 && (int) $mDrop <= 12) {
            $query->where('periode_shipment_pasuruan', 'like', sprintf('%02d-', (int) $mDrop) . '%');
        }
        $yDrop = (string) $request->input('year', '');
        if (ctype_digit($yDrop)) {
            $query->where('periode_shipment_pasuruan', 'like', '%-' . $yDrop);
        }
        if ($request->filled('planner')) {
            $query->where('planner_pasuruan', $request->planner);
        }
        $areaDrop = array_values(array_filter((array) $request->input('area', []), 'strlen'));
        if ($areaDrop) {
            $query->whereIn('area_pasuruan', $areaDrop);
        }

        $recordsTotal = $this->countGroupsPasuruan($query);

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
            // hanya hitung ulang kalau memang ada pencarian
            $recordsFiltered = $this->countGroupsPasuruan($query);
        } else {
            // tanpa pencarian, hasilnya identik dengan recordsTotal -> hemat 1 query berat
            $recordsFiltered = $recordsTotal;
        }

        $query = LogistikPengirimanPasuruan::query()
            ->whereIn('id', $this->groupIdsQueryPasuruan($query));

        // sorting: pakai columns[i][name] (whitelist), fallback ke index
        $indexMap = [
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
            16 => 'kubikasi_pasuruan',
            17 => 'tonase_pasuruan',
            20 => 'hasil_kubik_pasuruan',
            21 => 'hasil_tonase_pasuruan',
            22 => 'pengiriman_optimal_pasuruan',
            25 => 'ekspedisi_pasuruan',
            26 => 'tanggal_dpt_unit_pasuruan',
            35 => 'pic_monitoring_pasuruan',
            36 => 'nama_kapal_pasuruan',
        ];
        $whitelist = array_merge(
            array_values($indexMap),
            $this->gudangCols('planning'),
            $this->gudangCols('tiba'),
            $this->gudangCols('keluar')
        );

        $idx  = (int) $request->input('order.0.column', 0);
        $name = (string) $request->input("columns.{$idx}.name", '');
        $orderColumn = in_array($name, $whitelist, true) ? $name : ($indexMap[$idx] ?? 'tanggal_terima_po_pasuruan');
        $orderDir    = strtolower($request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query->orderBy($orderColumn, $orderDir)
              ->orderByRaw('CAST(no_shipment_pasuruan AS UNSIGNED) ASC')
              ->orderBy('no_shipment_pasuruan')
              ->orderBy('tujuan_pasuruan')
              ->orderBy('id');

        $start  = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 25);
        $length = $length > 0 ? min($length, 200) : max(1, min($recordsFiltered, 200));

        $rows     = $query->skip($start)->take($length)->get();
        $groupAgg = $this->groupAggPasuruanManager($rows);

        // CR per shipment: jumlahkan semua tujuan dalam 1 shipment, lalu hitung CR-nya
        $crShipment = [];
        foreach ($groupAgg as $g) {
            $ns    = $g->no_shipment_pasuruan;
            $biaya = (float) $g->sum_kontribusi > 0 ? (float) $g->sum_kontribusi : (float) $g->sum_biaya;

            $crShipment[$ns]['biaya']  = ($crShipment[$ns]['biaya']  ?? 0) + $biaya;
            $crShipment[$ns]['muatan'] = ($crShipment[$ns]['muatan'] ?? 0) + (float) $g->sum_nilai;
        }

        $data = $rows->map(function ($r) use ($groupAgg, $crShipment) {
            $g = $groupAgg[$r->no_shipment_pasuruan . '|' . ($r->tujuan_pasuruan ?? '')] ?? null;

            if ($g && trim((string) $r->no_shipment_pasuruan) !== '') {
                $r->nilai_muatan_pasuruan     = $g->sum_nilai;
                $r->total_kubik_pasuruan      = $g->sum_kubik;
                $r->total_tonase_pasuruan     = $g->sum_tonase;
                $r->kirim_kontribusi_pasuruan = $g->sum_kontribusi;
                $r->biaya_kirim_pasuruan      = (float) $g->sum_kontribusi > 0 ? $g->sum_kontribusi : $g->sum_biaya;

                $q = (float) $g->sum_qty;
                $r->total_do_pasuruan = floor($q) == $q ? (int) $q : $q;

                // actual = total semua DO - total selisih (per shipment + tujuan)
                $kuli = (float) $g->max_kuli;
                $r->biaya_kuli_pasuruan = $kuli;

                if ((int) $g->jml_selisih > 0) {
                    $sel    = (float) $g->sum_selisih;
                    $actual = $q - $sel;

                    $r->selisih_quantity_pasuruan         = $sel != 0 ? $sel : null;
                    $r->actual_delivery_quantity_pasuruan = floor($actual) == $actual ? (int) $actual : $actual;
                    $r->total_biaya_kuli_pasuruan         = $actual * $kuli;
                } else {
                    $r->selisih_quantity_pasuruan         = null;
                    $r->actual_delivery_quantity_pasuruan = null;
                    $r->total_biaya_kuli_pasuruan         = 0;
                }
            }

            $nilai = (float) $r->nilai_muatan_pasuruan;
            $biaya = (float) $r->biaya_kirim_pasuruan;
            $cr    = $nilai > 0 ? ($biaya / $nilai) * 100 : 0;
            $fmt   = fn($v) => $this->fmtDatePasuruanManager($v);

            $cs = $crShipment[$r->no_shipment_pasuruan] ?? null;
            $crShipmentVal = ($cs && $cs['muatan'] > 0)
                ? ($cs['biaya'] / $cs['muatan']) * 100
                : $cr;

            return array_merge([
                'tanggal_terima_po_fmt'    => $fmt($r->tanggal_terima_po_pasuruan),
                'rencana_kirim_fmt'        => $fmt($r->rencana_kirim_pasuruan),
                'transport_lead_time'      => $r->transport_lead_time_pasuruan,
                'planner'                  => $r->planner_pasuruan,
                'no_shipment'              => $r->no_shipment_pasuruan,
                'status_pengiriman_badge'  => $this->badgeStatusPosisiMobilPasuruanManager($r),
                'dist_channel_badge'       => $this->badgeDistChannelPasuruanManager($r->dist_channel_pasuruan),
                'tujuan_html'              => $r->no_shipment_pasuruan
                    ? '<a href="#" class="btn-tujuan" data-shipment="' . e($r->no_shipment_pasuruan)
                        . '" data-tujuan="' . e($r->tujuan_pasuruan) . '">▶ ' . e($r->tujuan_pasuruan)
                        . ($g && $g->jml_do > 1 ? ' <span class="badge gray">' . $g->jml_do . ' DO</span>' : '')
                        . '</a>'
                    : e($r->tujuan_pasuruan),
                'area'                     => $r->area_pasuruan,
                'ketersediaan_unit_badge'  => $this->badgeKetersediaanUnitPasuruanManager($r),
                'mobil'                    => $r->mobil_pasuruan,
                'total_do'                 => $r->total_do_pasuruan,
                'nilai_muatan_fmt'         => 'Rp ' . number_format($nilai, 0, ',', '.'),
                'biaya_kirim_fmt'          => 'Rp ' . number_format($biaya, 0, ',', '.'),
                'cr_fmt'                   => $this->formatCRPasuruanManager($cr),
                'cr_shipment_fmt'          => $this->formatCRPasuruanManager((float) $crShipmentVal),

                // kubikasi / tonase / hasil / optimal
                'kubikasi_fmt'             => $r->kubikasi_pasuruan !== null && $r->kubikasi_pasuruan !== ''
                    ? number_format((float) $r->kubikasi_pasuruan, 2, ',', '.') . '%' : '-',
                'tonase_fmt'               => $r->tonase_pasuruan !== null && $r->tonase_pasuruan !== ''
                    ? number_format((float) $r->tonase_pasuruan, 2, ',', '.') . '%' : '-',
                'total_kubik_fmt'          => $this->formatDesimalPasuruanManager($r->total_kubik_pasuruan),
                'total_tonase_fmt'         => $this->formatDesimalPasuruanManager($r->total_tonase_pasuruan),
                'hasil_kubik_fmt'          => $this->formatHasilPersenPasuruanManager($r->hasil_kubik_pasuruan),
                'hasil_tonase_fmt'         => $this->formatHasilPersenPasuruanManager($r->hasil_tonase_pasuruan),
                'pengiriman_optimal_badge' => $this->badgePengirimanOptimalPasuruanManager($r),
                'reason_optimal'           => $r->reason_pengiriman_optimal_pasuruan,

                'kategori_ekspedisi_badge' => $this->badgeKategoriEkspedisiPasuruanManager($r->kategori_ekspedisi_pasuruan),
                'ekspedisi'                => $r->ekspedisi_pasuruan,
                'tanggal_dpt_unit_fmt'     => $fmt($r->tanggal_dpt_unit_pasuruan),
                'lama_waktu_pencarian'     => $this->computeLamaPencarianPasuruanManager($r),
                'sla_dapat_mobil_badge'    => $this->badgeSlaDapatMobilPasuruanManager($r),
            ], $this->gudangCellsManager($r), [
                'pic_monitoring'           => $r->pic_monitoring_pasuruan,
                'nama_kapal'               => $r->nama_kapal_pasuruan,
                'etd'                      => $r->etd_pasuruan,
                'eta'                      => $r->eta_pasuruan,
                'alert_badge'              => $this->badgeAlertPasuruanManager($r),
                'act_urutan_bongkar'       => $r->act_urutan_bongkar_pasuruan,
                'actual_delivery_quantity' => $r->actual_delivery_quantity_pasuruan,
                'selisih_qty_badge'        => $this->badgeSelisihQtyPasuruanManager($r),
                'reason_selisih_quantity'  => $r->reason_selisih_quantity_pasuruan,
                'act_pgi_date_fmt'         => $fmt($r->act_pgi_date_pasuruan),
                'atd_fmt'                  => $fmt($r->atd_pasuruan),
                'ata_fmt'                  => $fmt($r->ata_pasuruan),
                'estimasi_tiba_fmt'        => $fmt($r->estimasi_tiba_pasuruan),
                'tanggal_tiba_fmt'         => $r->tanggal_tiba_pasuruan ? date('d-m-Y h:i A', strtotime($r->tanggal_tiba_pasuruan)) : '-',
                'lama_perjalanan'          => $r->lama_perjalanan_pasuruan ?? '-',
                'sla_tiba_badge'           => $this->badgeSlaTibaPasuruanManager($r),
                'tanggal_bongkar_fmt'      => $r->tanggal_bongkar_pasuruan ? date('d-m-Y h:i A', strtotime($r->tanggal_bongkar_pasuruan)) : '-',
                'status_bongkar_badge'     => $this->badgeStatusBongkarPasuruanManager($r),
                'overstay_badge'           => $this->badgeOverstayPasuruanManager($r),
                'sla_bongkar_badge'        => $this->badgeSlaBongkarPasuruanManager($r),
                'reason_tiba'              => $r->reason_waktu_tiba_pasuruan,
                'reason_bongkar'           => $r->reason_waktu_bongkar_pasuruan,
                'status_akhir_badge'       => $this->badgeStatusAkhirPasuruanManager($r),
                'status_alert_badge'       => $this->badgeStatusAlertPasuruanManager($r),
                'remarks'                  => $r->remarks_pasuruan,
                'route'                    => $r->route_pasuruan,
                'shipping_point'           => $r->route_pasuruan ? explode('-', trim($r->route_pasuruan))[0] : '-',
                'pulau'                    => $r->pulau_pasuruan,
                'via_kirim'                => $r->via_kirim_pasuruan,

                'kirim_kontribusi_fmt'     => $r->kirim_kontribusi_pasuruan !== null
                    ? 'Rp ' . number_format((float) $r->kirim_kontribusi_pasuruan, 0, ',', '.') : '-',
                'pengiriman_optimal_customer_badge' => $this->badgePengirimanOptimalCustomerPasuruanManager($r),
                'reason_optimal_customer'  => $r->reason_pengiriman_optimal_customer_pasuruan ?: '-',

                'biaya_kuli'               => $r->biaya_kuli_pasuruan,
                'total_biaya_kuli'         => $r->total_biaya_kuli_pasuruan,
            ]);
        });

        return response()->json([
            'draw'            => (int) $request->input('draw', 1),
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    private function formatDesimalPasuruanManager($value): string
    {
        if ($value === null || $value === '' || !is_numeric($value)) return '-';
        return number_format((float) $value, 2, ',', '.');
    }

    private function formatHasilPersenPasuruanManager($value): string
    {
        if ($value === null || $value === '' || !is_numeric($value)) return '-';
        return number_format((float) $value, 2, ',', '.') . '%';
    }

    private function badgePengirimanOptimalPasuruanManager($r): string
    {
        $v = strtoupper(trim((string) ($r->pengiriman_optimal_pasuruan ?? '')));
        if ($v === '') return '<span class="badge gray">-</span>';

        return $v === 'OPTIMAL'
            ? '<span class="badge green">✅ Optimal</span>'
            : '<span class="badge orange">⚠️ Belum Optimal</span>';
    }

    private function badgePengirimanOptimalCustomerPasuruanManager($r): string
    {
        $v = strtoupper(trim((string) ($r->pengiriman_optimal_customer_pasuruan ?? '')));
        if ($v === '') return '<span class="badge gray">-</span>';

        return $v === 'OPTIMAL'
            ? '<span class="badge green">✅ Optimal</span>'
            : '<span class="badge orange">⚠️ Tidak Optimal</span>';
    }

    /** jumlah per shipment + tujuan untuk baris yang tampil di halaman ini */
    private function groupAggPasuruanManager($rows): array
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
                DB::raw('SUM(' . $n('total_kubik_pasuruan') . ') as sum_kubik'),
                DB::raw('SUM(' . $n('total_tonase_pasuruan') . ') as sum_tonase'),
                DB::raw('SUM(' . $n('total_do_pasuruan') . ') as sum_qty'),
                DB::raw('SUM(' . $n('kirim_kontribusi_pasuruan') . ') as sum_kontribusi'),
                DB::raw('SUM(' . $n('selisih_quantity_pasuruan') . ') as sum_selisih'),
                DB::raw("SUM(CASE WHEN NULLIF(TRIM(selisih_quantity_pasuruan),'') IS NOT NULL THEN 1 ELSE 0 END) as jml_selisih"),
                DB::raw('MAX(' . $n('biaya_kuli_pasuruan') . ') as max_kuli'),
                DB::raw('IF(SUM(' . $n('kirim_kontribusi_pasuruan') . ') > 0,
                            SUM(' . $n('biaya_kirim_pasuruan') . '),
                            MAX(' . $n('biaya_kirim_pasuruan') . ')) as sum_biaya')
            )
            ->groupBy('no_shipment_pasuruan', 'tujuan_key')
            ->get()
            ->keyBy(fn($g) => $g->no_shipment_pasuruan . '|' . $g->tujuan_key)
            ->all();
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

    /* =========================================================================
     *  PASURUAN — PRESENTER / BADGE (suffix "Manager")
     *  Aturan bongkar SAMA dengan PasuruanController: wajar sampai 24 jam.
     * ========================================================================= */
    private function cachedListPasuruanManager(string $column)
    {
        return Cache::remember("manager_pasuruan_list_{$column}", 3600, function () use ($column) {
            return DB::table('logistik_pengiriman_pasuruan')
                ->select($column)->whereNotNull($column)->where($column, '!=', '')
                ->distinct()->orderBy($column)->pluck($column);
        });
    }

    private function fmtDatePasuruanManager($value, string $format = 'd-m-Y')
    {
        if (empty($value) || $value === 'mm/dd/yyyy') return '-';
        $ts = strtotime((string) $value);
        return $ts === false ? (string) $value : date($format, $ts);
    }

    private function jamTibaKeBongkarPasuruanManager($tiba, $bongkar): ?float
    {
        if (empty($tiba) || empty($bongkar)) return null;
        $t = strtotime($tiba); $b = strtotime($bongkar);
        if (!$t || !$b) return null;
        return max(0, ($b - $t) / 3600);
    }

    private function formatCRPasuruanManager(float $cr): string
    {
        return $cr > 0
            ? '<span class="cr-value">' . number_format($cr, 4, ',', '.') . '%</span>'
            : '<span class="text-muted">0,0000%</span>';
    }

    private function badgeDistChannelPasuruanManager($channel): string
    {
        $channel = trim((string) $channel);
        $classes = ['badge-green', 'badge-blue', 'badge-orange', 'badge-red', 'badge-purple', 'badge-pink', 'badge-cyan', 'badge-yellow'];
        $cls     = $channel ? $classes[abs(crc32($channel)) % count($classes)] : 'badge-default';

        return '<span class="badge ' . $cls . '">' . e($channel ?: '-') . '</span>';
    }

    private function badgeKetersediaanUnitPasuruanManager($r): string
    {
        return !empty($r->tanggal_dpt_unit_pasuruan)
            ? '<span class="badge-status status-sudah">Sudah Dapat Unit</span>'
            : '<span class="badge-status status-belum">Belum Dapat Unit</span>';
    }

    private function badgeKategoriEkspedisiPasuruanManager($kategori): string
    {
        $kategori = trim((string) $kategori);

        if ($kategori === '' || $kategori === '-') return '<span class="badge gray">-</span>';
        if (strtolower($kategori) === 'kontrak')   return '<span class="badge yellow">Kontrak</span>';
        if (strtolower($kategori) === 'oncall')    return '<span class="badge blue">Oncall</span>';

        return '<span class="badge orange">' . e($kategori) . '</span>';
    }

    private function selisihHariRencanaDptPasuruanManager($r): ?int
    {
        if (empty($r->rencana_kirim_pasuruan) || empty($r->tanggal_dpt_unit_pasuruan)) return null;

        $rencana = strtotime(date('Y-m-d', strtotime($r->rencana_kirim_pasuruan)));
        $dapat   = strtotime(date('Y-m-d', strtotime($r->tanggal_dpt_unit_pasuruan)));

        return (int) floor(($dapat - $rencana) / 86400);
    }

    private function computeLamaPencarianPasuruanManager($r): string
    {
        $s = $this->selisihHariRencanaDptPasuruanManager($r);
        if ($s === null) return '-';
        return $s <= 0 ? 'H+0' : 'H+' . $s;
    }

    private function badgeSlaDapatMobilPasuruanManager($r): string
    {
        $s = $this->selisihHariRencanaDptPasuruanManager($r);
        if ($s === null) return '<span class="badge gray">-</span>';

        return $s <= 0
            ? '<span class="badge green">On Time</span>'
            : '<span class="badge red">Delay</span>';
    }

    private function badgeAlertPasuruanManager($r): string
    {
        if (!empty($r->tanggal_tiba_pasuruan)) return '<span class="badge badge-success">✓ Tiba</span>';
        if (empty($r->estimasi_tiba_pasuruan)) return '<span class="badge badge-secondary">-</span>';

        $estimasi = strtotime(date('Y-m-d', strtotime($r->estimasi_tiba_pasuruan)));
        $sisa     = floor(($estimasi - strtotime(date('Y-m-d'))) / 86400);

        if ($sisa < 0)  return '<span class="badge badge-danger">OVERDUE</span>';
        if ($sisa <= 1) return '<span class="badge badge-danger">H-' . $sisa . '</span>';
        if ($sisa <= 3) return '<span class="badge badge-warning">H-' . $sisa . '</span>';
        if ($sisa <= 7) return '<span class="badge badge-info">H-' . $sisa . '</span>';

        return '<span class="badge badge-success">ON TRACK</span>';
    }

    private function badgeSelisihQtyPasuruanManager($r): string
    {
        $totalDo   = is_numeric($r->total_do_pasuruan) ? (float) $r->total_do_pasuruan : 0;
        $actualRaw = $r->actual_delivery_quantity_pasuruan;

        if ($actualRaw === null || $actualRaw === '' || (float) $actualRaw == 0) {
            return '<span class="badge badge-secondary">-</span>';
        }

        $selisih = $totalDo - (float) $actualRaw;

        if ($selisih == 0) return '<span class="badge badge-success">Sesuai (0)</span>';
        if ($selisih > 0)  return '<span class="badge badge-danger">Berkurang ' . number_format($selisih, 0, ',', '.') . '</span>';

        return '<span class="badge badge-warning">Lebih ' . number_format(abs($selisih), 0, ',', '.') . '</span>';
    }

    private function badgeSlaTibaPasuruanManager($r): string
    {
        if (empty($r->tanggal_tiba_pasuruan) || empty($r->estimasi_tiba_pasuruan)) {
            return '<span class="badge gray">-</span>';
        }

        $tiba     = strtotime(date('Y-m-d', strtotime($r->tanggal_tiba_pasuruan)));
        $estimasi = strtotime(date('Y-m-d', strtotime($r->estimasi_tiba_pasuruan)));

        return $tiba <= $estimasi
            ? '<span class="badge green">On Time</span>'
            : '<span class="badge red">Delay</span>';
    }

    private function badgeStatusBongkarPasuruanManager($r): string
    {
        if (!empty($r->tanggal_bongkar_pasuruan)) {
            return '<span class="badge status-bongkar green">Telah Bongkar</span>';
        }

        if (!empty($r->tanggal_tiba_pasuruan)) {
            $jam   = max(0, (time() - strtotime($r->tanggal_tiba_pasuruan)) / 3600);
            $hari  = $jam <= 24 ? 0 : (int) ceil(($jam - 24) / 24);
            $class = $hari == 0 ? 'orange' : 'red';

            return '<span class="badge status-bongkar ' . $class . '">H+' . $hari . '</span>';
        }

        return '<span class="badge status-bongkar gray">-</span>';
    }

    private function badgeOverstayPasuruanManager($r): string
    {
        $jam = $this->jamTibaKeBongkarPasuruanManager($r->tanggal_tiba_pasuruan, $r->tanggal_bongkar_pasuruan);
        if ($jam === null) return '<span class="badge gray">-</span>';

        $overstay = $jam <= 24 ? 0 : (int) ceil(($jam - 24) / 24);

        return $overstay == 0
            ? '<span class="badge green">0 Hari</span>'
            : '<span class="badge red">H+' . $overstay . ' Hari</span>';
    }

    private function badgeSlaBongkarPasuruanManager($r): string
    {
        $jam = $this->jamTibaKeBongkarPasuruanManager($r->tanggal_tiba_pasuruan, $r->tanggal_bongkar_pasuruan);
        if ($jam === null) return '<span class="badge gray">-</span>';

        return $jam <= 24
            ? '<span class="badge green">On Time</span>'
            : '<span class="badge red">Delay</span>';
    }

    private function badgeStatusAkhirPasuruanManager($r): string
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

    private function badgeStatusAlertPasuruanManager($r): string
    {
        $slaTiba    = strtoupper(trim($r->sla_tiba_pasuruan ?? ''));
        $slaBongkar = strtoupper(trim($r->sla_bongkar_pasuruan ?? ''));

        if ($slaTiba === 'ON TIME' && $slaBongkar === 'ON TIME') return '<span class="badge badge-success">🟢 Delivered Ontime</span>';
        if ($slaTiba === 'DELAY'   && $slaBongkar === 'ON TIME') return '<span class="badge badge-warning">🚚 Delay Perjalanan</span>';
        if ($slaTiba === 'ON TIME' && $slaBongkar === 'DELAY')   return '<span class="badge badge-info">📦 Delay Pembongkaran</span>';
        if ($slaTiba === 'DELAY'   && $slaBongkar === 'DELAY')   return '<span class="badge badge-danger">🔥 Delivered Delay</span>';

        return '<span class="badge badge-secondary">⏳ Belum Selesai</span>';
    }
}