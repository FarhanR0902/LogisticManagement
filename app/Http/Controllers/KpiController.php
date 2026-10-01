<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * KPI Planner (per planner) & KPI Monitoring (per PIC monitoring).
 *
 * PRINSIP: SEMUA data ikut dihitung, tidak ada baris yang tertinggal.
 *
 * - Planner   : per SHIPMENT (no_shipment duplikat dihitung 1). Baris yang no_shipment-nya
 *               kosong tetap dihitung, masing-masing sebagai 1 shipment sendiri.
 *               Kelengkapan Data per baris.
 * - Monitoring: per BARIS DATA / TUJUAN (COUNT(*)), no_shipment duplikat tidak digabung.
 * - Baris yang planner / PIC-nya kosong ikut TOTAL TIM, tapi tidak dijadikan "karyawan";
 *   jumlahnya ditulis di catatan halaman. Angka per karyawan murni milik karyawan itu.
 * - "Belum Kelar" dihitung per planner / per PIC:
 *     Planner    : tanggal wajib kosong, ATAU ada gudang yang sudah dimulai tapi belum keluar
 *                  (mis. pakai 2 gudang, 1 sudah keluar 1 belum), ATAU belum ada keluar gudang sama sekali.
 *     Monitoring : tanggal_tiba ATAU tanggal_bongkar kosong.
 * - Periode (dipakai hanya kalau filter tahun/bulan dipilih; default = SEMUA data):
 *     Planner    : tanggal_naik_logistik -> rencana_kirim -> create_tgl
 *     Monitoring : tanggal_tiba -> estimasi_tiba -> tanggal_naik_logistik -> rencana_kirim -> create_tgl
 * - Target diatur di konstanta PLANNER_COLUMNS / MONITORING_COLUMNS di bawah.
 *   better = 'high' (makin tinggi makin bagus) | 'low' (makin rendah makin bagus)
 *   target = null -> tidak diberi warna.
 */
class KpiController extends Controller
{
    private const T = 'logistik_pengiriman';

    /** label untuk baris yang planner / PIC-nya belum diisi */
    private const KOSONG = '(Belum Diisi)';

    /** kunci 1 shipment: no_shipment, atau kalau kosong pakai id baris (dihitung sendiri) */
    private const SHIP_KEY = "COALESCE(NULLIF(TRIM(no_shipment), ''), CONCAT('#', id))";

    /** field non-tanggal yang wajib diisi planner */
    private const PLANNER_WAJIB = [
        'no_shipment', 'mobil', 'ekpedisi', 'route', 'nama_driver', 'no_pol',
    ];

    /** tanggal yang wajib diisi planner (selain siklus gudang) */
    private const PLANNER_TANGGAL = [
        'tanggal_naik_logistik', 'rencana_kirim', 'tanggal_dpt_unit',
    ];

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
     
        ['key' => 'belum_gudang', 'label' => 'Belum Sampai Keluar Gudang',  'unit' => '', 'target' => 0, 'better' => 'low'],
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
        ['key' => 'belum_update', 'label' => 'Belum Kelar (Tiba/Bongkar Kosong)', 'unit' => '', 'target' => 0, 'better' => 'low'],
        ['key' => 'belum_reason', 'label' => 'Reason Delay Kosong',       'unit' => '', 'target' => 0, 'better' => 'low'],
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

    /** tanggal dianggap KOSONG: NULL, '', mm/dd/yyyy, 0000-00-00 atau placeholder 1899-12-31 */
    private function dateBlank(string $c): string
    {
        return "(NULLIF(TRIM({$c}), '') IS NULL OR TRIM({$c}) = 'mm/dd/yyyy' OR TRIM({$c}) LIKE '0000-00-00%' OR TRIM({$c}) LIKE '1899-12-31%')";
    }

    private function dateFilled(string $c): string
    {
        return 'NOT ' . $this->dateBlank($c);
    }

    /**
     * Siklus gudang BELUM KELAR (per baris/shipment):
     * - ada gudang yang sudah dimulai (planning loading / tiba gudang terisi) tapi tanggal keluarnya kosong
     *   (contoh: pakai 2 gudang, gudang 1 sudah keluar tapi gudang 2 belum -> belum kelar), ATAU
     * - belum ada satu pun tanggal keluar gudang.
     */
    private function gudangBelumSql(): string
    {
        $cycles = [
            ['planning_loading',   'tanggal_tiba_gudang',   'tanggal_keluar_gudang'],
            ['planning_loading_2', 'tanggal_tiba_gudang_2', 'tanggal_keluar_gudang_2'],
            ['planning_loading_3', 'tanggal_tiba_gudang_3', 'tanggal_keluar_gudang_3'],
        ];

        $parts = [];
        foreach ($cycles as [$plan, $tiba, $keluar]) {
            $parts[] = '((' . $this->dateFilled($plan) . ' OR ' . $this->dateFilled($tiba) . ') AND ' . $this->dateBlank($keluar) . ')';
        }
        $parts[] = '(' . implode(' AND ', array_map(
            fn($c) => $this->dateBlank($c),
            ['tanggal_keluar_gudang', 'tanggal_keluar_gudang_2', 'tanggal_keluar_gudang_3']
        )) . ')';

        return '(' . implode(' OR ', $parts) . ')';
    }

    /** pisahkan baris "(Belum Diisi)" (tanpa nama) dari daftar karyawan; return [$named, $jumlahTanpaNama] */
    private function splitKosong($people): array
    {
        $kosong = $people->first(fn($p) => $p->person === self::KOSONG);

        return [
            $people->reject(fn($p) => $p->person === self::KOSONG)->values(),
            $kosong ? (int) $kosong->total : 0,
        ];
    }

    /** COALESCE beberapa kolom tanggal, kolom kosong ('') dianggap NULL */
    private function coalesceDate(array $cols): string
    {
        return 'COALESCE(' . implode(', ', array_map(
            fn($c) => "NULLIF(TRIM({$c}), '')",
            $cols
        )) . ')';
    }

    /** nama orang; kalau kosong jadi "(Belum Diisi)" */
    private function personExpr(string $col): string
    {
        return "COALESCE(NULLIF(TRIM({$col}), ''), '" . self::KOSONG . "')";
    }

    /** hitung shipment unik yang memenuhi kondisi (dipakai Planner) */
    private function ship(string $cond): string
    {
        return "COUNT(DISTINCT CASE WHEN {$cond} THEN " . self::SHIP_KEY . " END)";
    }

    /** hitung baris yang memenuhi kondisi (dipakai Monitoring; 1 baris = 1 tujuan) */
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

    /** default = SEMUA data. Filter tahun/bulan hanya aktif kalau dipilih. */
    private function scope($q, Request $r, string $period)
    {
        $year = $r->filled('year') ? $r->input('year') : 'all';

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

    /** return [$teamRow, $personRows]  (baris tanpa nama ikut TOTAL TIM; dipisah dari daftar karyawan lewat splitKosong) */
    private function aggregate(Request $r, string $period, string $groupCol, string $selects): array
    {
        $team = $this->scope(DB::table(self::T), $r, $period)
            ->selectRaw($selects)
            ->first();

        $person = $this->personExpr($groupCol);

        $people = $this->scope(DB::table(self::T), $r, $period)
            ->selectRaw("{$person} AS person, {$selects}")
            ->groupByRaw($person)
            ->orderByRaw($person)
            ->get();

        return [$team, $people];
    }

    private function areaList()
    {
        return DB::table(self::T)->whereNotNull('area')->distinct()->orderBy('area')->pluck('area');
    }

    /* =====================================================
     * KPI PLANNER (per no_shipment, duplikat dihitung 1)
     * ===================================================== */
    public function planner(Request $r)
    {
        $period = $this->coalesceDate(['tanggal_naik_logistik', 'rencana_kirim', 'create_tgl']);

        $gudangBelum = $this->gudangBelumSql();
        $belumSemua  = '(' . implode(' OR ', array_merge(
            array_map(fn($c) => $this->blankSql($c), self::PLANNER_WAJIB),
            array_map(fn($c) => $this->dateBlank($c), self::PLANNER_TANGGAL),
            [$gudangBelum]
        )) . ')';

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

            // belum kelar (per shipment): field/tanggal wajib kosong, ATAU siklus gudang belum sampai keluar gudang terakhir
            $this->ship($belumSemua) . ' AS belum',
            $this->ship($gudangBelum) . ' AS belum_gudang',
        ]);

        [$team, $people] = $this->aggregate($r, $period, 'planner', $sel);
        [$people, $tanpaPlanner] = $this->splitKosong($people);

        // Cost ratio = total biaya (1x per shipment) / total nilai muatan
        // semua baris ikut (no_shipment kosong dianggap 1 shipment sendiri)
        $pk = $this->personExpr('planner');
        $crSub = fn() => $this->scope(DB::table(self::T), $r, $period)
            ->selectRaw("{$pk} AS planner_key, " . self::SHIP_KEY . " AS sk,
                MAX(CAST(biaya_kirim AS DECIMAL(20,2))) AS b,
                SUM(CAST(nilai_muatan AS DECIMAL(20,2))) AS m")
            ->groupByRaw("{$pk}, " . self::SHIP_KEY);

        $crTeam = DB::query()->fromSub($crSub(), 's')
            ->selectRaw('SUM(b) / NULLIF(SUM(m),0) * 100 AS cr')->value('cr');

        $crPeople = DB::query()->fromSub($crSub(), 's')
            ->selectRaw('planner_key, SUM(b) / NULLIF(SUM(m),0) * 100 AS cr')
            ->groupBy('planner_key')->pluck('cr', 'planner_key');

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
            'belum'   => (int) $x->belum,
            'belum_gudang' => (int) $x->belum_gudang,
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
            'notes'    => 'Semua data ikut dihitung (default: semua periode). Per No Shipment, duplikat dihitung 1; baris tanpa No Shipment dihitung sendiri-sendiri. Kelengkapan Data per baris. Belum Kelar = tanggal wajib belum lengkap, atau ada gudang (KACS/Sentul/CCIE) yang sudah dimulai tapi belum keluar, atau belum ada tanggal keluar gudang sama sekali. Filter tahun/bulan memakai Tanggal Naik Logistik (kalau kosong: Rencana Kirim, lalu tanggal import).'
                . ($tanpaPlanner > 0 ? " Ada {$tanpaPlanner} shipment tanpa nama planner: ikut TOTAL TIM, tidak dimasukkan ke daftar planner." : ''),
        ]);
    }

    /* =====================================================
     * KPI MONITORING (per baris data / tujuan)
     * ===================================================== */
    public function monitoring(Request $r)
    {
        $period = $this->coalesceDate([
            'tanggal_tiba', 'estimasi_tiba', 'tanggal_naik_logistik', 'rencana_kirim', 'create_tgl',
        ]);

        $akhirValid = "status_akhir IN ('On Time Total','Delay Perjalanan','Delay Pembongkaran','Delay Total')";
        $overdueRow = "NULLIF(TRIM(estimasi_tiba),'') IS NOT NULL AND DATE(estimasi_tiba) < CURDATE()";

        $sel = implode(",\n", [
            'COUNT(*) AS total',

            $this->rows("sla_tiba IN ('On Time','Delay')") . ' AS tiba_den',
            $this->rows("sla_tiba = 'On Time'") . ' AS tiba_num',

            $this->rows("sla_bongkar IN ('On Time','Delay')") . ' AS bkr_den',
            $this->rows("sla_bongkar = 'On Time'") . ' AS bkr_num',

            $this->rows($akhirValid) . ' AS fin_den',
            $this->rows("status_akhir = 'On Time Total'") . ' AS fin_num',

            'AVG(overstay_days) AS overstay',

            $this->rows($this->blankSql('tanggal_tiba') . " AND {$overdueRow}") . ' AS overdue',

            // akurasi qty
            $this->rows($this->filledSql('selisih_qty')) . ' AS qty_den',
            $this->rows($this->filledSql('selisih_qty') . ' AND CAST(selisih_qty AS DECIMAL(20,2)) = 0') . ' AS qty_num',

            // reason coverage (tiba delay + bongkar delay)
            $this->rows("sla_tiba = 'Delay'") . ' AS rt_den',
            $this->rows("sla_tiba = 'Delay' AND " . $this->filledSql('reason_tiba')) . ' AS rt_num',
            $this->rows("sla_bongkar = 'Delay'") . ' AS rb_den',
            $this->rows("sla_bongkar = 'Delay' AND " . $this->filledSql('reason_bongkar')) . ' AS rb_num',

            // belum kelar: tanggal_tiba ATAU tanggal_bongkar masih kosong
            $this->rows($this->dateBlank('tanggal_tiba') . ' OR ' . $this->dateBlank('tanggal_bongkar')) . ' AS belum_upd',

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
        [$people, $tanpaPic] = $this->splitKosong($people);

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
            // belum diisi monitoring
            'belum_update' => (int) $x->belum_upd,
            'belum_reason' => (int) (($x->rt_den - $x->rt_num) + ($x->rb_den - $x->rb_num)),
        ];

        return view('kpi.index', [
            'title'    => 'KPI Monitoring',
            'personLabel' => 'PIC Monitoring',
            'columns'  => self::MONITORING_COLUMNS,
            'team'     => $build($team),
            'people'   => $people->map(fn($p) => ['name' => $p->person] + $build($p)),
            'areaList' => $this->areaList(),
            'notes'    => 'Semua data ikut dihitung (default: semua periode). Per baris data / tujuan, No Shipment duplikat tidak digabung. Belum Kelar = Tanggal Tiba atau Tanggal Bongkar masih kosong. Filter tahun/bulan memakai Tanggal Tiba (kalau belum tiba: Estimasi Tiba, lalu Tanggal Naik Logistik).'
                . ($tanpaPic > 0 ? " Ada {$tanpaPic} baris tanpa PIC monitoring: ikut TOTAL TIM, tidak dimasukkan ke daftar PIC." : ''),
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