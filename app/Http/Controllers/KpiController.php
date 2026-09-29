<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * KPI Planner (per planner) & KPI Monitoring (per PIC monitoring).
 *
 * - Semua hitungan per SHIPMENT (COUNT DISTINCT no_shipment), kecuali yang
 *   memang per baris (kelengkapan data, reason, kepatuhan update).
 * - Periode Planner   : tanggal_naik_logistik
 * - Periode Monitoring: COALESCE(tanggal_tiba, estimasi_tiba)
 * - Target diatur di konstanta PLANNER_COLUMNS / MONITORING_COLUMNS di bawah.
 *   better = 'high' (makin tinggi makin bagus) | 'low' (makin rendah makin bagus)
 *   target = null -> tidak diberi warna.
 */
class KpiController extends Controller
{
    private const T = 'logistik_pengiriman';

    private const PLANNER_COLUMNS = [
        ['key' => 'armada',  'label' => 'Armada On Time',        'unit' => '%',    'target' => 90,   'better' => 'high'],
        ['key' => 'backlog', 'label' => 'Backlog Belum Dapat Unit', 'unit' => '',  'target' => 0,    'better' => 'low'],
        ['key' => 'cari',    'label' => 'Rata2 Cari Unit',       'unit' => ' hari', 'target' => 1,   'better' => 'low'],
        ['key' => 'kacs',    'label' => 'SLA Loading KACS',      'unit' => '%',    'target' => 90,   'better' => 'high'],
        ['key' => 'sentul',  'label' => 'SLA Loading Sentul',    'unit' => '%',    'target' => 90,   'better' => 'high'],
        ['key' => 'ccie',    'label' => 'SLA Loading CCIE',      'unit' => '%',    'target' => 90,   'better' => 'high'],
        ['key' => 'optimal', 'label' => 'Pengiriman Optimal',    'unit' => '%',    'target' => 80,   'better' => 'high'],
        ['key' => 'cr',      'label' => 'Cost Ratio',            'unit' => '%',    'target' => null, 'better' => 'low'],
        ['key' => 'kel',     'label' => 'Kelengkapan Data',      'unit' => '%',    'target' => 95,   'better' => 'high'],
    ];

    private const MONITORING_COLUMNS = [
        ['key' => 'tiba',     'label' => 'On-time Arrival',        'unit' => '%',    'target' => 90,   'better' => 'high'],
        ['key' => 'bongkar',  'label' => 'On-time Unloading',      'unit' => '%',    'target' => 90,   'better' => 'high'],
        ['key' => 'final',    'label' => 'Delivered On Time Total', 'unit' => '%',   'target' => 85,   'better' => 'high'],
        ['key' => 'overstay', 'label' => 'Rata2 Overstay',         'unit' => ' hari', 'target' => 0.5, 'better' => 'low'],
        ['key' => 'overdue',  'label' => 'In Transit Overdue',     'unit' => '',     'target' => 0,    'better' => 'low'],
        ['key' => 'qty',      'label' => 'Akurasi Qty',            'unit' => '%',    'target' => 95,   'better' => 'high'],
        ['key' => 'reason',   'label' => 'Reason Coverage',        'unit' => '%',    'target' => 90,   'better' => 'high'],
        ['key' => 'update',   'label' => 'Kepatuhan Update',       'unit' => '%',    'target' => 95,   'better' => 'high'],
        ['key' => 'gap',      'label' => 'Lama Jalan vs Lead Time', 'unit' => ' hari', 'target' => 0, 'better' => 'low'],
        ['key' => 'warning',  'label' => 'Akurasi Early Warning',  'unit' => '%',    'target' => null, 'better' => 'high'],
    ];

    /* ================= HELPER SQL ================= */

    private function filledSql(string $c): string
    {
        return "NULLIF(TRIM({$c}), '') IS NOT NULL";
    }

    private function blankSql(string $c): string
    {
        return "NULLIF(TRIM({$c}), '') IS NULL";
    }

    /** hitung shipment unik yang memenuhi kondisi */
    private function ship(string $cond): string
    {
        return "COUNT(DISTINCT CASE WHEN {$cond} THEN NULLIF(TRIM(no_shipment), '') END)";
    }

    /** hitung baris yang memenuhi kondisi */
    private function rows(string $cond): string
    {
        return "SUM(CASE WHEN {$cond} THEN 1 ELSE 0 END)";
    }

    private function pct($num, $den): ?float
    {
        return ((float) $den) > 0 ? round(((float) $num / (float) $den) * 100, 1) : null;
    }

    private function dec($v, int $d = 1): ?float
    {
        return $v === null ? null : round((float) $v, $d);
    }

    private function scope($q, Request $r, string $period)
    {
        $year = $r->filled('year') ? $r->input('year') : date('Y');

        if ($year !== 'all') {
            $q->whereRaw("YEAR({$period}) = ?", [(int) $year]);
        }
        if ($r->filled('month')) {
            $q->whereRaw("MONTH({$period}) = ?", [(int) $r->input('month')]);
        }
        if ($r->filled('area')) {
            $q->where('area', $r->input('area'));
        }

        return $q;
    }

    /** return [$teamRow, $personRows] */
    private function aggregate(Request $r, string $period, string $groupCol, string $selects): array
    {
        $team = $this->scope(DB::table(self::T), $r, $period)
            ->selectRaw($selects)
            ->first();

        $people = $this->scope(DB::table(self::T), $r, $period)
            ->whereNotNull($groupCol)
            ->whereRaw("TRIM({$groupCol}) <> ''")
            ->selectRaw("{$groupCol} AS person, {$selects}")
            ->groupBy($groupCol)
            ->orderBy($groupCol)
            ->get();

        return [$team, $people];
    }

    private function areaList()
    {
        return DB::table(self::T)->whereNotNull('area')->distinct()->orderBy('area')->pluck('area');
    }

    /* =====================================================
     * KPI PLANNER
     * ===================================================== */
    public function planner(Request $r)
    {
        $period = 'tanggal_naik_logistik';

        $sel = implode(",\n", [
            $this->ship('1=1') . ' AS total',

            // armada
            $this->ship("NULLIF(TRIM(sla_dapat_mobil),'') IN ('On Time','Delay')") . ' AS arm_den',
            $this->ship("sla_dapat_mobil = 'On Time'") . ' AS arm_num',

            // backlog: rencana kirim sudah lewat, unit belum dapat
            $this->ship("NULLIF(TRIM(rencana_kirim),'') IS NOT NULL AND DATE(rencana_kirim) < CURDATE() AND " . $this->blankSql('tanggal_dpt_unit')) . ' AS backlog',

            // rata2 lama cari unit (hari)
            "AVG(CASE WHEN NULLIF(TRIM(rencana_kirim),'') IS NOT NULL AND NULLIF(TRIM(tanggal_dpt_unit),'') IS NOT NULL
                 THEN GREATEST(DATEDIFF(DATE(tanggal_dpt_unit), DATE(rencana_kirim)), 0) END) AS cari",

            // SLA loading per gudang
            $this->ship("status_gudang IN ('On Time','Delay')") . ' AS g1_den',
            $this->ship("status_gudang = 'On Time'") . ' AS g1_num',
            $this->ship("status_gudang_2 IN ('On Time','Delay')") . ' AS g2_den',
            $this->ship("status_gudang_2 = 'On Time'") . ' AS g2_num',
            $this->ship("status_gudang_3 IN ('On Time','Delay')") . ' AS g3_den',
            $this->ship("status_gudang_3 = 'On Time'") . ' AS g3_num',

            // optimal
            $this->ship($this->filledSql('pengiriman_optimal')) . ' AS opt_den',
            $this->ship("pengiriman_optimal = 'OPTIMAL'") . ' AS opt_num',

            // kelengkapan data (per baris)
            'COUNT(*) AS kel_den',
            $this->rows(implode(' AND ', array_map(
                fn($c) => $this->filledSql($c),
                ['mobil', 'ekpedisi', 'route', 'nama_driver', 'no_pol']
            ))) . ' AS kel_num',
        ]);

        [$team, $people] = $this->aggregate($r, $period, 'planner', $sel);

        // Cost ratio = total biaya (1x per shipment) / total nilai muatan
        $crSub = fn() => $this->scope(DB::table(self::T), $r, $period)
            ->whereNotNull('no_shipment')
            ->selectRaw("planner, no_shipment,
                MAX(CAST(biaya_kirim AS DECIMAL(20,2))) AS b,
                SUM(CAST(nilai_muatan AS DECIMAL(20,2))) AS m")
            ->groupBy('planner', 'no_shipment');

        $crTeam = DB::query()->fromSub($crSub(), 's')
            ->selectRaw('SUM(b) / NULLIF(SUM(m),0) * 100 AS cr')->value('cr');

        $crPeople = DB::query()->fromSub($crSub(), 's')
            ->selectRaw('planner, SUM(b) / NULLIF(SUM(m),0) * 100 AS cr')
            ->groupBy('planner')->pluck('cr', 'planner');

        $build = fn($x, $cr) => [
            'total'   => (int) ($x->total ?? 0),
            'armada'  => $this->pct($x->arm_num, $x->arm_den),
            'backlog' => (int) $x->backlog,
            'cari'    => $this->dec($x->cari),
            'kacs'    => $this->pct($x->g1_num, $x->g1_den),
            'sentul'  => $this->pct($x->g2_num, $x->g2_den),
            'ccie'    => $this->pct($x->g3_num, $x->g3_den),
            'optimal' => $this->pct($x->opt_num, $x->opt_den),
            'cr'      => $this->dec($cr, 2),
            'kel'     => $this->pct($x->kel_num, $x->kel_den),
        ];

        $teamRow   = $build($team, $crTeam);
        $peopleRow = $people->map(fn($p) => ['name' => $p->person] + $build($p, $crPeople[$p->person] ?? null));

        return view('kpi.index', [
            'title'    => 'KPI Planner',
            'personLabel' => 'Planner',
            'columns'  => self::PLANNER_COLUMNS,
            'team'     => $teamRow,
            'people'   => $peopleRow,
            'areaList' => $this->areaList(),
            'notes'    => 'Periode: Tanggal Naik Logistik. Dihitung per No Shipment (kecuali Kelengkapan Data yang per baris).',
        ]);
    }

    /* =====================================================
     * KPI MONITORING
     * ===================================================== */
    public function monitoring(Request $r)
    {
        $period = "COALESCE(NULLIF(TRIM(tanggal_tiba),''), estimasi_tiba)";

        $akhirValid = "status_akhir IN ('On Time Total','Delay Perjalanan','Delay Pembongkaran','Delay Total')";
        $overdueRow = "NULLIF(TRIM(estimasi_tiba),'') IS NOT NULL AND DATE(estimasi_tiba) < CURDATE()";

        $sel = implode(",\n", [
            $this->ship('1=1') . ' AS total',

            $this->ship("sla_tiba IN ('On Time','Delay')") . ' AS tiba_den',
            $this->ship("sla_tiba = 'On Time'") . ' AS tiba_num',

            $this->ship("sla_bongkar IN ('On Time','Delay')") . ' AS bkr_den',
            $this->ship("sla_bongkar = 'On Time'") . ' AS bkr_num',

            $this->ship($akhirValid) . ' AS fin_den',
            $this->ship("status_akhir = 'On Time Total'") . ' AS fin_num',

            'AVG(overstay_days) AS overstay',

            $this->ship($this->blankSql('tanggal_tiba') . " AND {$overdueRow}") . ' AS overdue',

            // akurasi qty
            $this->ship($this->filledSql('selisih_qty')) . ' AS qty_den',
            $this->ship($this->filledSql('selisih_qty') . ' AND CAST(selisih_qty AS DECIMAL(20,2)) = 0') . ' AS qty_num',

            // reason coverage (per baris: tiba delay + bongkar delay)
            $this->rows("sla_tiba = 'Delay'") . ' AS rt_den',
            $this->rows("sla_tiba = 'Delay' AND " . $this->filledSql('reason_tiba')) . ' AS rt_num',
            $this->rows("sla_bongkar = 'Delay'") . ' AS rb_den',
            $this->rows("sla_bongkar = 'Delay' AND " . $this->filledSql('reason_bongkar')) . ' AS rb_num',

            // kepatuhan update: sudah lewat estimasi -> tiba & bongkar harus terisi
            $this->rows($overdueRow) . ' AS upd_den',
            $this->rows($overdueRow . ' AND ' . $this->filledSql('tanggal_tiba') . ' AND ' . $this->filledSql('tanggal_bongkar')) . ' AS upd_num',

            // selisih lama perjalanan vs lead time (hari)
            "AVG(CASE WHEN lama_perjalanan IS NOT NULL AND " . $this->filledSql('transport_lead_time') . "
                 THEN lama_perjalanan - CAST(transport_lead_time AS DECIMAL(10,2)) END) AS gap",

            // akurasi early warning
            $this->rows("status_kendaraan = 'Potential Delay' AND sla_tiba IN ('On Time','Delay')") . ' AS ew_den',
            $this->rows("status_kendaraan = 'Potential Delay' AND sla_tiba = 'Delay'") . ' AS ew_num',
        ]);

        [$team, $people] = $this->aggregate($r, $period, 'pic_monitoring', $sel);

        $build = fn($x) => [
            'total'    => (int) ($x->total ?? 0),
            'tiba'     => $this->pct($x->tiba_num, $x->tiba_den),
            'bongkar'  => $this->pct($x->bkr_num, $x->bkr_den),
            'final'    => $this->pct($x->fin_num, $x->fin_den),
            'overstay' => $this->dec($x->overstay),
            'overdue'  => (int) $x->overdue,
            'qty'      => $this->pct($x->qty_num, $x->qty_den),
            'reason'   => $this->pct($x->rt_num + $x->rb_num, $x->rt_den + $x->rb_den),
            'update'   => $this->pct($x->upd_num, $x->upd_den),
            'gap'      => $this->dec($x->gap),
            'warning'  => $this->pct($x->ew_num, $x->ew_den),
        ];

        return view('kpi.index', [
            'title'    => 'KPI Monitoring',
            'personLabel' => 'PIC Monitoring',
            'columns'  => self::MONITORING_COLUMNS,
            'team'     => $build($team),
            'people'   => $people->map(fn($p) => ['name' => $p->person] + $build($p)),
            'areaList' => $this->areaList(),
            'notes'    => 'Periode: Tanggal Tiba (kalau belum tiba pakai Estimasi Tiba). Dihitung per No Shipment (Reason Coverage, Kepatuhan Update, Early Warning per baris).',
        ]);
    }
}

/*
 * ROUTE (routes/web.php) — taruh di grup middleware SPV / manager:
 *
 * Route::get('/kpi/planner',    [\App\Http\Controllers\KpiController::class, 'planner'])->name('kpi.planner');
 * Route::get('/kpi/monitoring', [\App\Http\Controllers\KpiController::class, 'monitoring'])->name('kpi.monitoring');
 *
 * INDEX yang disarankan biar cepat:
 *   planner, pic_monitoring, tanggal_naik_logistik, estimasi_tiba, tanggal_tiba
 */