<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class GabunganController extends Controller
{
    /** View & nama route: bisa ditimpa subclass (mis. Sales\SalesGabunganController). */
    protected string $dashboardView  = 'gabungan.dashboard';
    protected string $dataView       = 'manager.data_gabungan';
    protected string $listRoute      = 'manager.gabungan.list';
    protected string $inTransitRoute = 'manager.gabungan.intransit';
    protected string $dataRoute      = 'manager.gabungan.data';
    protected string $plannerRoute    = 'planner.datalogistik';
protected string $monitoringRoute = 'monitoring.datalogistik';

    /**
     * Dipakai subclass: false = scope periode dimatikan (In Transit).
     * Di manager tidak berpengaruh apa-apa.
     */
    protected bool $periodeAktif = true;

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
     * Tiap key HARUS unik (key ganda di PHP saling menimpa).
     */
    private const COLS = [
        'no_shipment'             => 'no_shipment_pasuruan',
        'tujuan'                  => 'tujuan_pasuruan',
        'area'                    => 'area_pasuruan',
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
        'create_tgl'              => 'tanggal_terima_po_pasuruan',
        'tanggal_dpt_unit'        => 'tanggal_dpt_unit_pasuruan',

        // gudang 1 (Pasuruan = gudang utama)
        'planning_loading'        => 'planning_loading_pasuruan',
        'tanggal_tiba_gudang'     => 'tanggal_tiba_gudang_pasuruan',
        'tanggal_keluar_gudang'   => 'tanggal_keluar_gudang_pasuruan',
        // gudang 2 (Pasuruan = puntir)
        'planning_loading_2'      => 'planning_loading_puntir_pasuruan',
        'tanggal_tiba_gudang_2'   => 'tanggal_tiba_gudang_puntir_pasuruan',
        'tanggal_keluar_gudang_2' => 'tanggal_keluar_gudang_puntir_pasuruan',
        // gudang 3-5 (Pasuruan tidak punya)
        'planning_loading_3'      => null,
        'tanggal_tiba_gudang_3'   => null,
        'tanggal_keluar_gudang_3' => null,
        'sla_loading'             => null,
        'planning_loading_4'      => null,
        'tanggal_tiba_gudang_4'   => null,
        'tanggal_keluar_gudang_4' => null,
        'planning_loading_5'      => null,
        'tanggal_tiba_gudang_5'   => null,
        'tanggal_keluar_gudang_5' => null,
        'waktu_tiba'              => null,
        'waktu_bongkar'           => null,

        'estimasi_tiba'           => 'estimasi_tiba_pasuruan',
        'tanggal_tiba'            => 'tanggal_tiba_pasuruan',
        'sla_tiba'                => 'sla_tiba_pasuruan',
        'tanggal_bongkar'         => 'tanggal_bongkar_pasuruan',
        'overstay_days'           => 'overstay_days_pasuruan',
        'sla_bongkar'             => 'sla_bongkar_pasuruan',
        'nilai_muatan'            => 'nilai_muatan_pasuruan',
        'biaya_kirim'             => 'biaya_kirim_pasuruan',
        'remarks'                 => 'remarks_pasuruan',
        'kubikasi'                => 'kubikasi_pasuruan',
        'tonase'                  => 'tonase_pasuruan',
        'total_kubik'             => 'total_kubik_pasuruan',
        'total_tonase'            => 'total_tonase_pasuruan',
        'hasil_kubik'             => 'hasil_kubik_pasuruan',
        'hasil_tonase'            => 'hasil_tonase_pasuruan',
        'pengiriman_optimal'      => 'pengiriman_optimal_pasuruan',
        'cr'                      => 'cr_pasuruan',

        // dipakai view manager.* (nama kolom Jakarta = key di kiri)
        'total_do_qty_car'        => 'total_do_pasuruan',
        'qty_monitoring'          => 'actual_delivery_quantity_pasuruan',
        'selisih_qty'             => 'selisih_quantity_pasuruan',
        'remarks_qty'             => 'reason_selisih_quantity_pasuruan',
        'reason_tiba'             => 'reason_waktu_tiba_pasuruan',
        'reason_bongkar'          => 'reason_waktu_bongkar_pasuruan',
    ];

    private const NUM_COALESCE = ['nilai_muatan', 'biaya_kirim']; // angka, NULL jadi 0
    private const NUM_NULLABLE = ['overstay_days'];               // angka, NULL tetap NULL

    /** Akhiran kolom gudang: 1=KACS, 2=SENTUL, 3=CCIE, 4=P5, 5=FIFATEX */
    private const GUDANG = ['', '_2', '_3', '_4', '_5'];

    private function gcols(string $base): array
    {
        return array_map(fn($s) => $base . $s, self::GUDANG);
    }

    /* =====================================================
     * HOOK SCOPE (kosong untuk manager; diisi subclass)
     * ===================================================== */
    protected function scopeJakarta($q) {}
    protected function scopePasuruan($q) {}

    /* =====================================================
     * SUMBER DATA GABUNGAN (UNION Jakarta + Pasuruan)
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
     * FILTER
     * ===================================================== */
   private function applyFilter($query, $request)
{
    if (in_array($request->sumber, ['JAKARTA', 'PASURUAN'], true)) {
        $query->where('sumber', $request->sumber);
    }

    $areas = array_values(array_filter((array) $request->input('area', []), 'strlen'));
    if (!empty($areas)) $query->whereIn('area', $areas);

    $dists = array_values(array_filter((array) $request->input('dist_channel', []), 'strlen'));
    if (!empty($dists)) $query->whereIn('dist_channel', $dists);

    $pulauSel  = array_values(array_filter((array) $request->input('pulau', []), 'strlen'));
    $areaPulau = [];
    foreach ($pulauSel as $p) {
        if (isset(self::PULAU_MAP[$p])) $areaPulau = array_merge($areaPulau, self::PULAU_MAP[$p]);
    }
    if (!empty($areaPulau)) $query->whereIn('area', $areaPulau);

    $col = match ($request->input('tgl_basis')) {
        'rencana_kirim'         => 'rencana_kirim',
        'tanggal_naik_logistik' => 'tanggal_naik_logistik',
        default                 => 'periode_shipment',
    };

    $from = $request->input('date_from');
    $to   = $request->input('date_to');
    if ($from && $to && $from > $to) [$from, $to] = [$to, $from];

    $months = array_values(array_filter(
        (array) $request->input('month', []),
        fn($m) => preg_match('/^\d{4}-\d{2}$/', (string) $m)
    ));

    // basis periode shipment: kolom berformat "MM-YYYY"
    if ($col === 'periode_shipment') {
        if ($months) {
            $query->whereIn('periode_shipment', array_map(
                fn($m) => substr($m, 5, 2) . '-' . substr($m, 0, 4), $months
            ));
        }
        if ($request->year && !$months) $query->where('periode_shipment', 'like', '%-' . $request->year);
        if ($request->date) $query->whereDate('tanggal_shipment', $request->date);
        if ($from)          $query->whereDate('tanggal_shipment', '>=', $from);
        if ($to)            $query->whereDate('tanggal_shipment', '<=', $to);
        return $query;
    }

    // basis tanggal lain (rencana_kirim / tanggal_naik_logistik)
    if ($request->date) $query->whereDate($col, $request->date);
    if ($from)          $query->whereDate($col, '>=', $from);
    if ($to)            $query->whereDate($col, '<=', $to);

    if ($months) {
        $query->where(function ($w) use ($months, $col) {
            foreach ($months as $m) {
                $w->orWhere(fn($x) => $x->whereYear($col, substr($m, 0, 4))
                                        ->whereMonth($col, substr($m, 5, 2)));
            }
        });
    }
    if ($request->year && !$months) $query->whereYear($col, $request->year);

    return $query;
}

    /* =====================================================
     * GROUPING (sumber + shipment + tujuan)
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
     * DEFINISI KPI (dipakai kartu DAN halaman list)
     * ===================================================== */
    private function kpiQuery(string $kpi)
    {
        $filled = fn($c) => "NULLIF(TRIM({$c}), '') IS NOT NULL";
        $empty  = fn($c) => "NULLIF(TRIM({$c}), '') IS NULL";
        $q = $this->src();

        switch ($kpi) {
            case 'gudang_ontime': // sudah tiba gudang (gudang mana pun)
                $q->whereRaw('(' . implode(' OR ', array_map($filled, $this->gcols('tanggal_tiba_gudang'))) . ')');
                break;

            case 'belum_armada':
                $q->whereRaw('(' . $empty('rencana_kirim') . ' OR ' . $empty('tanggal_dpt_unit') . ')');
                break;

            case 'sla_delay': // sudah dapat armada, belum tiba di gudang mana pun
                $q->whereRaw($filled('rencana_kirim'))
                  ->whereRaw($filled('tanggal_dpt_unit'));
                foreach ($this->gcols('tanggal_tiba_gudang') as $col) {
                    $q->whereRaw($empty($col));
                }
                break;

          case 'tujuan_ontime':
    $q->whereRaw($filled('tanggal_tiba'))->whereRaw($filled('estimasi_tiba'))
      ->whereRaw('TIMESTAMPDIFF(SECOND, TIMESTAMP(DATE(estimasi_tiba)), ' . $this->tibaDtSql() . ') < 86400');
    break;

case 'tujuan_delay':
    $q->whereRaw($filled('tanggal_tiba'))->whereRaw($filled('estimasi_tiba'))
      ->whereRaw('TIMESTAMPDIFF(SECOND, TIMESTAMP(DATE(estimasi_tiba)), ' . $this->tibaDtSql() . ') >= 86400');
    break;

    case 'bongkar_ontime':
    $q->whereRaw($filled('tanggal_bongkar'))->whereRaw($filled('tanggal_tiba'))
      ->whereRaw("CAST(tanggal_bongkar AS CHAR) NOT LIKE '1899-12-31%'")
      ->whereRaw('TIMESTAMPDIFF(SECOND, ' . $this->tibaDtSql() . ', ' . $this->bongkarDtSql() . ') < 86400');
    break;

case 'bongkar_delay':
    $q->whereRaw($filled('tanggal_bongkar'))->whereRaw($filled('tanggal_tiba'))
      ->whereRaw("CAST(tanggal_bongkar AS CHAR) NOT LIKE '1899-12-31%'")
      ->whereRaw('TIMESTAMPDIFF(SECOND, ' . $this->tibaDtSql() . ', ' . $this->bongkarDtSql() . ') >= 86400');
    break;
            case 'in_transit':
                $q->whereRaw($empty('tanggal_tiba'))
                  ->whereRaw('(' . implode(' OR ', array_map($filled, $this->gcols('tanggal_keluar_gudang'))) . ')');

                foreach (self::GUDANG as $s) {
                    $q->whereRaw('NOT ((' . $filled('planning_loading' . $s) . ' OR ' . $filled('tanggal_tiba_gudang' . $s)
                        . ') AND ' . $empty('tanggal_keluar_gudang' . $s) . ')');
                }
                break;
        }

        return $q; // 'total' = tanpa kondisi
    }

    private function kpiCount(string $kpi, Request $request): int
    {
        // In Transit tidak dibatasi periode (dipakai subclass Sales)
        $this->periodeAktif = $kpi !== 'in_transit';
        $n = $this->countGroups($this->applyFilter($this->kpiQuery($kpi), $request));
        $this->periodeAktif = true;

        return $n;
    }

    /**
     * tanggal_tiba + waktu_tiba jadi satu DATETIME (jam kosong = jam bawaan tanggal / 00:00)
     */
 private function tibaDtSql(): string
{
    return "IF(waktu_tiba IS NULL, tanggal_tiba,
        DATE(tanggal_tiba) + INTERVAL TIME_TO_SEC(waktu_tiba) SECOND)";
}

private function bongkarDtSql(): string
{
    return "IF(waktu_bongkar IS NULL, tanggal_bongkar,
        DATE(tanggal_bongkar) + INTERVAL TIME_TO_SEC(waktu_bongkar) SECOND)";
}

    /* =====================================================
     * HALAMAN DATA GABUNGAN
     * ===================================================== */
  public function dataGabungan(Request $request)
{
    return view($this->dataView, [
        'areaList'      => $this->listArea()->pluck('area'),
        'dashFilters'   => $request->query(),   // <-- filter dari dashboard
    ]);
}
/** Samakan format filter untuk semua halaman (dashboard, list, in-transit, ajax) */

public function dataGabunganAjax(Request $request)
{
    $this->mergeDashboardFilters($request);
    $this->normalizeRequest($request);

    $filtered = $this->applyFilter($this->src(), $request);
    $base     = clone $filtered;

    $s = trim((string) $request->input('search.value'));
    if ($s !== '') {
        $base->where(function ($q) use ($s) {
            foreach (['no_shipment', 'tujuan', 'planner', 'area', 'mobil', 'ekpedisi'] as $c) {
                $q->orWhere($c, 'like', "%{$s}%");
            }
        });
    }

    $recordsTotal    = $this->countGroups($filtered);   // = angka di dashboard (200)
    $recordsFiltered = $this->countGroups($base);

    $start  = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 10);
        $length = $length > 0 ? min($length, 200) : 10;

        $rows = $this->src()->whereIn('id', $this->groupIdsQuery($base))
            ->orderByDesc('tanggal_shipment')->orderBy('sumber')
            ->orderBy('no_shipment')->orderBy('tujuan')
            ->skip($start)->take($length)->get();

        // jumlahan per sumber + shipment + tujuan
        $n   = fn($c) => "COALESCE(CAST(NULLIF(TRIM({$c}), '') AS DECIMAL(18,4)), 0)";
        $agg = [];
        $ships = $rows->pluck('no_shipment')->filter(fn($v) => trim((string) $v) !== '')->unique()->values();
        if ($ships->isNotEmpty()) {
            
          $agg = $this->src()->whereIn('no_shipment', $ships)
    ->selectRaw("sumber, no_shipment, COALESCE(tujuan,'') AS tj,
        COUNT(*)                      AS jml_do,
        SUM(COALESCE(nilai_muatan,0)) AS nilai,
        SUM({$n('total_do_qty_car')}) AS qty,
        MAX(COALESCE(biaya_kirim,0))  AS biaya,
        SUM({$n('total_kubik')})      AS kubik,
        SUM({$n('total_tonase')})     AS tonase")
    ->groupBy('sumber', 'no_shipment', 'tj')->get()
    ->keyBy(fn($g) => $g->sumber . '|' . $g->no_shipment . '|' . $g->tj)->all();
        }

        $d   = fn($v) => $v ? date('d-m-Y', strtotime($v)) : '-';
        $num = fn($v, $dec = 2) => is_numeric($v) ? number_format((float) $v, $dec, ',', '.') : '-';
        $pct = fn($v) => is_numeric($v) ? number_format((float) $v, 2, ',', '.') . '%' : '-';
        $sla = function ($v) {
            $v = trim((string) $v);
            if ($v === '') return '<span class="badge gray">-</span>';
            $c = strtolower($v) === 'on time' ? 'green' : (strtolower($v) === 'delay' ? 'red' : 'gray');
            return '<span class="badge ' . $c . '">' . e($v) . '</span>';
        };

        $data = $rows->map(function ($r) use ($agg, $d, $num, $pct, $sla) {
            $g = $agg[$r->sumber . '|' . $r->no_shipment . '|' . ($r->tujuan ?? '')] ?? null;

            $nilai = $g ? (float) $g->nilai : (float) $r->nilai_muatan;
            $biaya = $g ? (float) $g->biaya : (float) $r->biaya_kirim;
            $qty   = $g ? (float) $g->qty   : $r->total_do_qty_car;
            $kubik = $g ? (float) $g->kubik : $r->total_kubik;
            $ton   = $g ? (float) $g->tonase : $r->total_tonase;

            // tanggal keluar gudang terakhir
            $keluar = null;
            foreach ($this->gcols('tanggal_keluar_gudang') as $c) {
                if (!empty($r->$c)) {
                    $ts = strtotime($r->$c);
                    if ($keluar === null || $ts > $keluar) $keluar = $ts;
                }
            }

            if     (!empty($r->tanggal_bongkar))  { $st = ['SELESAI', 'green']; }
            elseif (!empty($r->tanggal_tiba))     { $st = ['TIBA DI TUJUAN', 'blue']; }
            elseif ($keluar)                      { $st = ['IN TRANSIT', 'orange']; }
            elseif (!empty($r->tanggal_dpt_unit)) { $st = ['SUDAH DAPAT UNIT', 'yellow']; }
            else                                  { $st = ['BELUM DAPAT UNIT', 'red']; }

            $opt = strtoupper(trim((string) $r->pengiriman_optimal));

            return [
                'sumber_badge'       => '<span class="badge ' . ($r->sumber === 'JAKARTA' ? 'blue' : 'pink') . '">' . $r->sumber . '</span>',
                'tgl_shipment'       => $d($r->tanggal_shipment),
                'rencana_kirim'      => $d($r->rencana_kirim),
                'lead_time'          => $r->transport_lead_time ?? '-',
                'planner'            => $r->planner,
                'no_shipment'        => $r->no_shipment,
                'status_badge'       => '<span class="badge ' . $st[1] . '">' . $st[0] . '</span>',
                'dist_channel'       => $r->dist_channel,
              'tujuan_html' => trim((string) $r->no_shipment) !== ''
    ? '<a href="#" class="btn-tujuan" data-sumber="' . e($r->sumber)
      . '" data-shipment="' . e($r->no_shipment)
      . '" data-tujuan="' . e($r->tujuan) . '">▶ ' . e($r->tujuan)
      . ($g && $g->jml_do > 1 ? ' <span class="badge gray">' . $g->jml_do . ' DO</span>' : '')
      . '</a>'
    : e($r->tujuan),
                'area'               => $r->area,
                'pulau'              => $r->pulau,
                'mobil'              => $r->mobil,
                'qty'                => $num($qty, 0),
                'kubikasi'           => $pct($r->kubikasi),
                'tonase'             => $pct($r->tonase),
                'total_kubik'        => $num($kubik),
                'total_tonase'       => $num($ton),
                'hasil_kubik'        => $pct($r->hasil_kubik),
                'hasil_tonase'       => $pct($r->hasil_tonase),
                'nilai_fmt'          => 'Rp ' . number_format($nilai, 0, ',', '.'),
                'biaya_fmt'          => 'Rp ' . number_format($biaya, 0, ',', '.'),
                'cr_fmt'             => $nilai > 0 ? number_format($biaya / $nilai * 100, 4, ',', '.') . '%' : '0,0000%',
                'pengiriman_optimal' => $opt === '' ? '<span class="badge gray">-</span>'
                    : ($opt === 'OPTIMAL' ? '<span class="badge green">Optimal</span>' : '<span class="badge orange">Tidak Optimal</span>'),
                'kategori'           => $r->kategori_ekspedisi,
                'ekspedisi'          => $r->ekpedisi,
                'tgl_dapat_unit'     => $d($r->tanggal_dpt_unit),
                'tgl_keluar_gudang'  => $keluar ? date('d-m-Y', $keluar) : '-',
                'tgl_estimasi'       => $d($r->estimasi_tiba),
                'tgl_tiba'           => $d($r->tanggal_tiba),
                'sla_tiba'           => $sla($r->sla_tiba),
                'tgl_bongkar'        => $d($r->tanggal_bongkar),
                'sla_bongkar'        => $sla($r->sla_bongkar),
            ];
        });

        return response()->json([
            'draw'            => (int) $request->input('draw', 1),
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    /* =====================================================
     * DASHBOARD GABUNGAN
     * ===================================================== */
    public function dashboard(Request $request)
    {
         $this->normalizeRequest($request);
        $base    = $this->applyFilter($this->src(), $request);
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

        $planner_armada = $this->countGroups((clone $base)
            ->whereNotNull('rencana_kirim')->whereRaw("TRIM(rencana_kirim) <> ''")
            ->whereNotNull('tanggal_dpt_unit')->whereRaw("TRIM(tanggal_dpt_unit) <> ''"));
        $planner_belum_armada = $gudang_delay;

        $planner_ontime = $this->countGroups((clone $base)
            ->whereNotNull('rencana_kirim')->whereNotNull('tanggal_dpt_unit')
            ->whereRaw('DATE(tanggal_dpt_unit) <= DATE(rencana_kirim)'));
        $planner_delay = $this->countGroups((clone $base)
            ->whereNotNull('rencana_kirim')->whereNotNull('tanggal_dpt_unit')
            ->whereRaw('DATE(tanggal_dpt_unit) > DATE(rencana_kirim)'));

        $list_dist_channel = $this->src()->select('dist_channel')
            ->whereNotNull('dist_channel')->distinct()->orderBy('dist_channel')->get();
        $list_area = $this->listArea();

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

        $tibaCols = $this->gcols('tanggal_tiba_gudang');
        $plCols   = $this->gcols('planning_loading');

        $greatestTiba = 'GREATEST(' . implode(',', array_map(fn($c) => "COALESCE({$c},'1900-01-01')", $tibaCols)) . ')';

        $whenT = $whenP = '';
        foreach (array_reverse(array_keys($tibaCols)) as $i) {
            if ($i === 0) {
                $whenT .= "WHEN {$tibaCols[0]} IS NOT NULL THEN {$tibaCols[0]} ";
                $whenP .= "WHEN {$tibaCols[0]} IS NOT NULL THEN {$plCols[0]} ";
            } else {
                $whenT .= "WHEN {$tibaCols[$i]} IS NOT NULL AND {$tibaCols[$i]} = {$greatestTiba} THEN {$tibaCols[$i]} ";
                $whenP .= "WHEN {$tibaCols[$i]} IS NOT NULL AND {$tibaCols[$i]} = {$greatestTiba} THEN {$plCols[$i]} ";
            }
        }
        $lastTiba     = "CASE {$whenT} ELSE NULL END";
        $lastPlanning = "CASE {$whenP} ELSE NULL END";
        $belumIsi     = "NULLIF(TRIM(rencana_kirim), '') IS NULL";

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
                DB::raw("SUM(CASE WHEN NULLIF(TRIM(tanggal_tiba),'') IS NOT NULL AND NULLIF(TRIM(estimasi_tiba),'') IS NOT NULL AND TIMESTAMPDIFF(SECOND, TIMESTAMP(DATE(estimasi_tiba)), {$this->tibaDtSql()}) <= 86400 THEN 1 ELSE 0 END) as total_ontime"),
                DB::raw("SUM(CASE WHEN NULLIF(TRIM(tanggal_tiba),'') IS NOT NULL AND NULLIF(TRIM(estimasi_tiba),'') IS NOT NULL AND TIMESTAMPDIFF(SECOND, TIMESTAMP(DATE(estimasi_tiba)), {$this->tibaDtSql()}) > 86400 THEN 1 ELSE 0 END) as total_delay"),
                DB::raw("SUM(CASE WHEN NULLIF(TRIM(tanggal_tiba), '') IS NULL THEN 1 ELSE 0 END) as total_belum_tiba"),
                DB::raw("SUM(CASE WHEN NULLIF(TRIM(tanggal_tiba), '') IS NULL AND estimasi_tiba IS NOT NULL AND DATE(estimasi_tiba) < CURDATE() THEN 1 ELSE 0 END) as total_lewat_estimasi")
            )->whereNotNull('pic_monitoring')->where('pic_monitoring', '!=', '')
            ->groupBy('pic_monitoring')->orderByDesc('total')->get();
         // ---- ALERT per periode_shipment ----
$filled = fn($c) => "NULLIF(TRIM({$c}), '') IS NOT NULL";
$empty  = fn($c) => "NULLIF(TRIM({$c}), '') IS NULL";

// Gudang TERAKHIR = urutan tertinggi (_5 -> utama) yang sudah ada planning_loading / tanggal_tiba_gudang.
// Hasil = 1 kalau tanggal_keluar_gudang di gudang itu masih kosong.
// Kalau belum masuk gudang mana pun -> 0 (belum jadi tanggung jawab input keluar gudang).
// Gudang TERAKHIR = urutan tertinggi (_5 -> utama) yang sudah ada isinya
// (planning_loading / tanggal_tiba_gudang / tanggal_keluar_gudang).
// Hasil = 1 kalau tanggal_keluar_gudang di gudang itu masih kosong.
// Belum ada gudang terisi sama sekali = keluar gudang pasti belum diinput -> 1.
$lastKeluarKosong = $this->lastKeluarKosongSql();
$alertPerPeriode = function (string $kolom, callable $kondisi) use ($grouped) {
    $q = (clone $grouped)
        ->selectRaw("{$kolom} AS nama, periode_shipment, COUNT(*) AS total")
        ->whereNotNull($kolom)->where($kolom, '!=', '');

    return $kondisi($q)
        ->groupBy($kolom, 'periode_shipment')
        ->orderByRaw('RIGHT(periode_shipment, 4), LEFT(periode_shipment, 2)')
        ->get()
        ->groupBy('nama');
};

// PLANNER: keluar gudang terakhir belum diinput
$alert_planner = $alertPerPeriode('planner', fn($q) => $q->whereRaw("({$lastKeluarKosong}) = 1"));

// PIC MONITORING: tanggal_tiba belum diinput
// PIC MONITORING (1): belum input sama sekali (belum keluar gudang & belum ada tgl tiba)
$alert_pic_belum = $alertPerPeriode('pic_monitoring', fn($q) => $q
    ->whereRaw($empty('tanggal_tiba'))
    ->whereRaw("({$lastKeluarKosong}) = 1"));

// PIC MONITORING (2): sudah ada tanggal_keluar_gudang terakhir, tapi tgl tiba belum diinput
$alert_pic_sudah = $alertPerPeriode('pic_monitoring', fn($q) => $q
    ->whereRaw($empty('tanggal_tiba'))
    ->whereRaw("({$lastKeluarKosong}) = 0"));
// kalau monitoring mau beda definisi, ganti argumen kedua, contoh:
// $alert_pic = $alertPerPeriode('pic_monitoring', fn($q) => $q->whereRaw($empty('tanggal_tiba')));
// $alert_pic = $alertPerPeriode('pic_monitoring', fn($q) => $q->whereRaw($empty('tanggal_tiba')));
        return view($this->dashboardView, compact(
            'total_data', 'summary_planner', 'summary_pic_monitoring',
            'gudang_ontime', 'gudang_delay', 'total_in_transit', 'total_sla_delay',
            'customer_ontime', 'customer_delay', 'bongkar_ontime', 'bongkar_delay',
            'summary_area', 'summary_tujuan', 'totalNilaiMuatan', 'totalBiayaKirim',
            'ekspedisi', 'label', 'value',
            'planner_ontime', 'planner_delay', 'planner_armada', 'planner_belum_armada',
            'ontime_rate', 'delay_rate', 'armada_rate', 'pending_rate',
           'summary_monitoring', 'list_dist_channel', 'list_area', 'alert_planner', 'alert_pic_belum', 'alert_pic_sudah'
        ))->with([
            'listRoute'      => $this->listRoute,
            'inTransitRoute' => $this->inTransitRoute,
            'plannerRoute'    => $this->plannerRoute,
'monitoringRoute' => $this->monitoringRoute,
            'dataRoute'      => $this->dataRoute,
        ]);
    }

    private function enrichArmada($row)
    {
        $row = $this->enrichRow($row);

        $today = strtotime(date('Y-m-d'));
        $jawa  = ['JAWA_BARAT','JAWA_TENGAH','JAWA_TIMUR','YOGYAKARTA','BANTEN','JABODEBEK'];

        $row->status_armada = !empty($row->tanggal_dpt_unit) ? 'Sudah Dapat' : 'Belum Dapat';

        // tanggal tiba gudang paling awal (gudang mana pun)
        $tiba = [];
        foreach (self::GUDANG as $s) {
            $v = $row->{'tanggal_tiba_gudang' . $s} ?? null;
            if (!empty($v)) $tiba[] = strtotime($v);
        }
        $row->tiba_gudang_label = $tiba ? date('d-m-Y', min($tiba)) : '-';

        if (empty($row->rencana_kirim)) {
            $row->lama_pencarian_now  = '-';
            $row->status_armada_alert = 'Belum Ada Rencana';
            $row->status_armada_cls   = 'gray';
            return $row;
        }

        $rk      = strtotime(date('Y-m-d', strtotime($row->rencana_kirim)));
        $selisih = (int) floor(($today - $rk) / 86400);
        $late    = in_array(strtoupper(trim($row->area ?? '')), $jawa, true) ? $selisih > 0 : $selisih > 2;

        $row->lama_pencarian_now  = $selisih > 0 ? $selisih . ' Hari' : '0 Hari';
        $row->status_armada_alert = $late ? 'Terlambat H+' . $selisih : 'On Track';
        $row->status_armada_cls   = $late ? 'red' : 'green';

        return $row;
    }

    /* =====================================================
     * HALAMAN LIST (satu method untuk semua kartu)
     * ===================================================== */
    protected const LIST_CONFIG = [
        'total'          => ['view' => 'manager.summary_total', 'var' => 'logistik', 'order' => null],
        'gudang_ontime'  => ['view' => 'gabungan.sla_ontime',   'var' => 'list', 'order' => null, 'sla' => true],
        'belum_armada'   => ['view' => 'gabungan.belum_armada', 'var' => 'list', 'order' => 'tanggal_naik_logistik', 'paginate' => 10, 'armada' => true],
        'sla_delay'      => ['view' => 'gabungan.sla_delay',    'var' => 'list', 'order' => 'tanggal_naik_logistik', 'paginate' => 10],
        'tujuan_ontime'  => [
            'view'     => 'gabungan.tujuan_ontime',
            'var'      => 'list',
            'order'    => 'tanggal_tiba',
            'paginate' => 10,
            'extra'    => "estimasi_tiba AS tanggal_estimasi, 'On Time' AS sla_tiba",
        ],
        'tujuan_delay'   => [
            'view'     => 'gabungan.tujuan_delay',
            'var'      => 'list',
            'order'    => 'tanggal_tiba',
            'paginate' => 10,
            'extra'    => "estimasi_tiba AS tanggal_estimasi, 'Delay' AS sla_tiba",
        ],
        'bongkar_ontime' => [
            'view'     => 'gabungan.bongkar_ontime',
            'var'      => 'list',
            'order'    => 'tanggal_bongkar',
            'paginate' => 10,
            'extra'    => "'On Time' AS sla_bongkar",
        ],
        'bongkar_delay'  => [
            'view'     => 'gabungan.bongkar_delay',
            'var'      => 'list',
            'order'    => 'tanggal_bongkar',
            'paginate' => 10,
            'extra'    => "'Delay' AS sla_bongkar",
        ],
    ];

    private function enrichRow($row)
    {
        foreach ($this->hitungSla($row) as $k => $v) $row->$k = $v;

        $tiba = []; $keluar = [];
        foreach (self::GUDANG as $i => $s) {
            if (!empty($row->{'tanggal_tiba_gudang' . $s}))   $tiba[$i + 1]   = strtotime($row->{'tanggal_tiba_gudang' . $s});
            if (!empty($row->{'tanggal_keluar_gudang' . $s})) $keluar[$i + 1] = strtotime($row->{'tanggal_keluar_gudang' . $s});
        }
        $row->gudang_sla             = $tiba ? array_search(min($tiba), $tiba) : null;
        $row->gudang_keluar_terakhir = $keluar ? array_search(max($keluar), $keluar) : null;
// overstay dihitung dari selisih tiba → bongkar (sama dengan rumus filter)
if (!empty($row->tanggal_tiba) && !empty($row->tanggal_bongkar)) {
    $tiba = strtotime(date('Y-m-d', strtotime($row->tanggal_tiba)) . ' ' .
            (!empty($row->waktu_tiba) ? $row->waktu_tiba : date('H:i:s', strtotime($row->tanggal_tiba))));
    $bongkar = strtotime(date('Y-m-d', strtotime($row->tanggal_bongkar)) . ' ' .
            (!empty($row->waktu_bongkar) ? $row->waktu_bongkar : date('H:i:s', strtotime($row->tanggal_bongkar))));

    $detik = max(0, $bongkar - $tiba);
    $row->overstay_days = intdiv($detik, 86400);   // 23j59m = 0 hari, 24j00m = 1 hari
}
        return $row;
    }

    public function list(Request $request, string $kpi)
    {
        $this->normalizeRequest($request);
    $cfg = static::LIST_CONFIG[$kpi] ?? abort(404);

        $filter = $this->applyFilter($this->kpiQuery($kpi), $request);

        $q = $this->src()->whereIn('id', $this->groupIdsQuery($filter));
        if (!empty($cfg['extra'])) $q->selectRaw('*, ' . $cfg['extra']);
        if (!empty($cfg['order'])) $q->orderByDesc($cfg['order']);
        $q->orderBy('sumber')->orderBy('no_shipment')->orderBy('tujuan');

        $enrich = !empty($cfg['armada'])
            ? fn($r) => $this->enrichArmada($r)
            : fn($r) => $this->enrichRow($r);

        if (!empty($cfg['paginate'])) {
            $rows = $q->paginate($cfg['paginate'])->withQueryString();
            $rows->setCollection($rows->getCollection()->map($enrich));
        } else {
            $rows = $q->get()->map($enrich)->values();
        }

        return view($cfg['view'], [
            $cfg['var']         => $rows,
            'title'             => strtoupper(str_replace('_', ' ', $kpi)),
            'list_area'         => $this->listArea(),
            'list_dist_channel' => $this->src()->select('dist_channel')
                ->whereNotNull('dist_channel')->distinct()->orderBy('dist_channel')->get(),
                   'dashFilters'       => $request->query(),
        ]);
    }

    /* =====================================================
     * IN TRANSIT (Jakarta + Pasuruan)
     * ===================================================== */
    public function inTransit(Request $request)
    {
         $this->normalizeRequest($request);
        // In Transit tidak dibatasi periode (dipakai subclass Sales)
        $this->periodeAktif = false;

        $todayTs = strtotime(date('Y-m-d'));

        $base = $this->applyFilter($this->kpiQuery('in_transit'), $request);

        $pics = array_values(array_filter((array) $request->input('pic_monitoring', []), 'strlen'));
        if ($pics) $base->whereIn('pic_monitoring', $pics);

        if ($request->filled('q')) {
            $s = trim($request->input('q'));
            $base->where(function ($q) use ($s) {
                foreach (['no_shipment', 'tujuan', 'ekpedisi', 'nama_driver', 'no_pol', 'mobil'] as $col) {
                    $q->orWhere($col, 'like', "%{$s}%");
                }
            });
        }

        $rows = $this->src()->whereIn('id', $this->groupIdsQuery($base))->get()
            ->map(fn($r) => $this->mapTransit($r, $todayTs))
            ->sortBy(fn($r) => $r->estimasi_ts ?? PHP_INT_MAX)
            ->values();

        $sisa = fn($r) => $r->estimasi_ts ? floor(($r->estimasi_ts - $todayTs) / 86400) : null;
        $summary = [
            'total'   => $rows->count(),
            'overdue' => $rows->filter(fn($r) => $sisa($r) !== null && $sisa($r) < 0)->count(),
            'soon'    => $rows->filter(fn($r) => $sisa($r) !== null && $sisa($r) >= 0 && $sisa($r) <= 3)->count(),
            'ontrack' => $rows->filter(fn($r) => $sisa($r) !== null && $sisa($r) > 3)->count(),
        ];

        $page = max(1, (int) $request->input('page', 1));
        $list = (new LengthAwarePaginator(
            $rows->forPage($page, 50)->values(), $rows->count(), 50, $page, ['path' => $request->url()]
        ))->withQueryString();

        return view('monitoring.in_transit', [
            'list'      => $list,
            'summary'   => $summary,
             'dashFilters' => $request->query(),  
            'areaList'  => $this->listArea()->pluck('area'),
            'picList'   => $this->src()->whereNotNull('pic_monitoring')->where('pic_monitoring', '!=', '')
                                ->distinct()->orderBy('pic_monitoring')->pluck('pic_monitoring'),
            'formRoute' => route($this->inTransitRoute),
        ]);
    }
/**
 * Ambil filter dari URL halaman Total Shipment (Referer), karena link dashboard
 * membawa array berindeks (pulau[0]=JAWA) yang tidak dikirim ulang oleh JS di view.
 * Nilai yang dikirim AJAX (dropdown halaman) tetap menang.
 */
protected function mergeDashboardFilters(Request $request): void
{
    $ref = $request->headers->get('referer');
    if (!$ref) return;

    $p = parse_url($ref);
    if (($p['host'] ?? null) !== $request->getHost() || empty($p['query'])) return;

    parse_str($p['query'], $q);   // pulau[0]=JAWA, pulau[]=JAWA, dst. semua jadi array

    $merge = [];
    foreach (['area', 'pulau', 'dist_channel', 'month'] as $k) {
        $dariUrl = array_values(array_filter((array) ($q[$k] ?? []), 'strlen'));
        if (!$request->filled($k) && $dariUrl) $merge[$k] = $dariUrl;
    }
    foreach (['date', 'year', 'tgl_basis', 'date_from', 'date_to'] as $k) {
        if (!$request->filled($k) && !empty($q[$k]) && is_string($q[$k])) $merge[$k] = $q[$k];
    }

    if ($merge) $request->merge($merge);
}

/** Samakan format filter untuk semua halaman (dashboard, list, in-transit, ajax) */
private function normalizeRequest(Request $request): void
{
    $request->merge(['sumber' => strtoupper((string) $request->input('sumber'))]);
}
    private function mapTransit($r, int $todayTs): object
    {
        $jkt  = $r->sumber === 'JAKARTA';
        $nama = $jkt
            ? [1 => 'KACS', 2 => 'SENTUL', 3 => 'CCIE', 4 => 'P5', 5 => 'FIFATEX']
            : [1 => 'PASURUAN', 2 => 'PUNTIR'];

        $cols = [];
        foreach (self::GUDANG as $i => $s) {
            if (isset($nama[$i + 1])) {
                $cols[$i + 1] = 'tanggal_keluar_gudang' . $s;
            }
        }

        $keluar = null; $asal = '-';
        foreach ($cols as $i => $col) {
            if (!empty($r->$col)) {
                $ts = strtotime($r->$col);
                if ($keluar === null || $ts >= $keluar) { $keluar = $ts; $asal = $nama[$i]; }
            }
        }

        $keluarD = $keluar ? strtotime(date('Y-m-d', $keluar)) : null;
        $lead    = (int) ($r->transport_lead_time ?? 0);
        $est     = !empty($r->estimasi_tiba)
            ? strtotime($r->estimasi_tiba)
            : ($keluarD ? strtotime("+{$lead} days", $keluarD) : null);

        $alert = '-'; $cls = 'gray';
        if ($est) {
            $s = floor(($est - $todayTs) / 86400);
            if     ($s < 0)  { $alert = 'Pending Tiba H+' . abs($s); $cls = 'red'; }
            elseif ($s <= 1) { $alert = 'H-' . $s;                   $cls = 'red'; }
            elseif ($s <= 3) { $alert = 'H-' . $s;                   $cls = 'orange'; }
            elseif ($s <= 7) { $alert = 'H-' . $s;                   $cls = 'blue'; }
            else             { $alert = 'ON TRACK';                  $cls = 'green'; }
        }

        return (object) [
            'sumber'         => $r->sumber,
            'no_shipment'    => $r->no_shipment,
            'tujuan'         => $r->tujuan,
            'area'           => $r->area,
            'dist_channel'   => $r->dist_channel,
            'ekpedisi'       => $r->ekpedisi,
            'mobil'          => $r->mobil,
            'pic_monitoring' => $r->pic_monitoring,
            'nama_driver'    => $r->nama_driver,
            'no_pol'         => $r->no_pol,
            'remarks'        => $r->remarks,

            'gudang_asal'    => $asal,
            'keluar_label'   => $keluar ? date('d-m-Y', $keluar) : '-',
            'hari_transit'   => $keluarD ? max(0, floor(($todayTs - $keluarD) / 86400)) : null,
            'estimasi_ts'    => $est,
            'estimasi_label' => $est ? date('d-m-Y', $est) : '-',
            'alert_label'    => $alert,
            'alert_class'    => $cls,
        ];
    }

    /* =====================================================
     * HITUNG SLA (untuk halaman Sudah Tiba Di Gudang)
     * ===================================================== */
    private function hitungSla($r): array
    {
        $d = array_fill_keys([
            'lama_waktu_pencarian', 'sla_dapat_mobil', 'status_pengiriman',
            'lama_digudang', 'status_gudang', 'sla_loading',
            'lama_digudang_2', 'status_gudang_2', 'sla_loading_2',
            'lama_digudang_3', 'status_gudang_3', 'sla_loading_3',
            'lama_digudang_4', 'status_gudang_4', 'sla_loading_4',
            'lama_digudang_5', 'status_gudang_5', 'sla_loading_5',
        ], null);

        $diff = function ($a, $b) {
            if (!$a || !$b) return null;
            $s    = max(0, strtotime($b) - strtotime($a));
            $days = intdiv($s, 86400);
            $h    = intdiv($s % 86400, 3600);
            $m    = intdiv($s % 3600, 60);
            return [
                'text' => trim(($days ? "{$days} Hari " : '') . ($h ? "{$h} Jam " : '') . "{$m} Menit"),
                'days' => $days,
            ];
        };

        if (!empty($r->rencana_kirim) && !empty($r->tanggal_dpt_unit)) {
            $d['lama_waktu_pencarian'] = $diff($r->rencana_kirim, $r->tanggal_dpt_unit)['text'];

            $selisih = (int) floor((strtotime(date('Y-m-d', strtotime($r->tanggal_dpt_unit)))
                                  - strtotime(date('Y-m-d', strtotime($r->rencana_kirim)))) / 86400);

            $jawa = ['JAWA_BARAT','JAWA_TENGAH','JAWA_TIMUR','YOGYAKARTA','BANTEN','JABODEBEK'];
            $late = in_array(strtoupper(trim($r->area ?? '')), $jawa, true) ? $selisih > 0 : $selisih > 2;

            $d['sla_dapat_mobil']   = $late ? 'Delay' : 'On Time';
            $d['status_pengiriman'] = $late ? 'Terlambat' : 'Sudah Dapat';
        }

        foreach (self::GUDANG as $s) {
            $x = $diff($r->{'tanggal_tiba_gudang' . $s} ?? null, $r->{'tanggal_keluar_gudang' . $s} ?? null);
            if ($x) {
                $d['lama_digudang' . $s] = $x['text'];
                $d['status_gudang' . $s] = $x['days'] > 0 ? 'Delay' : 'On Time';
                $d['sla_loading' . $s]   = $x['days'] > 0 ? 'H+' . $x['days'] : 'Sesuai SLA';
            }
        }

        return $d;
    }
/** Rincian DO per shipment + tujuan (klik tujuan di Data Gabungan) */
public function detail(Request $request)
{
    $this->mergeDashboardFilters($request);   // <-- tambahkan
    $request->validate([
        'sumber'      => 'nullable|string',
        'no_shipment' => 'required|string',
        'tujuan'      => 'nullable|string',
    ]);
    $pasuruan = strtoupper((string) $request->input('sumber')) === 'PASURUAN';
    $sfx      = $pasuruan ? '_pasuruan' : '';
    $tujuan   = (string) $request->input('tujuan', '');

    $q = DB::table($pasuruan ? 'logistik_pengiriman_pasuruan' : 'logistik_pengiriman')
        ->where('no_shipment' . $sfx, $request->input('no_shipment'));

    // manager: kosong; sales: batasi periode + dist_channel
    $pasuruan ? $this->scopePasuruan($q) : $this->scopeJakarta($q);

    $tujuan !== ''
        ? $q->where('tujuan' . $sfx, $tujuan)
        : $q->where(fn($x) => $x->whereNull('tujuan' . $sfx)->orWhere('tujuan' . $sfx, ''));

    $fmtDate = fn($v) => $v ? date('d-m-Y', strtotime($v)) : '-';
    $fmtDes  = fn($v) => ($v === null || $v === '' || !is_numeric($v))
        ? '-' : number_format((float) $v, 2, ',', '.');

    $data = $q->orderBy('sales_document' . $sfx)->orderBy('id')->get()
        ->map(function ($row) use ($sfx, $pasuruan, $fmtDate, $fmtDes) {
            $r   = (array) $row;
            $get = fn($c) => $r[$c . $sfx] ?? null;
            $qty = $pasuruan ? $get('total_do') : ($r['total_do_qty_car'] ?? null);
            $kk  = $get('kirim_kontribusi');

            return [
                'sales_document'       => $get('sales_document') ?: '-',
                'customer_reference'   => $get('customer_reference') ?: '-',
                'contract_end_date'    => $fmtDate($get('contract_end_date')),
                'no_do'                => $get('no_do') ?: '-',
                'no_delivery'          => $get('no_delivery') ?: '-',
                'qty'                  => $qty,
                'nilai_muatan'         => 'Rp ' . number_format((float) $get('nilai_muatan'), 0, ',', '.'),
                'kirim_kontribusi'     => $kk !== null && $kk !== ''
                    ? 'Rp ' . number_format((float) $kk, 0, ',', '.') : '-',
                'kirim_kontribusi_raw' => (float) $kk,
                'total_kubik'          => $fmtDes($get('total_kubik')),
                'total_tonase'         => $fmtDes($get('total_tonase')),
            ];
        });

    return response()->json(['data' => $data]);
}
    private function listArea()
    {
        return $this->src()->select('area')->whereNotNull('area')
            ->groupBy('area')->orderBy('area')->get();
    }
    /** Rumus "keluar gudang di gudang terakhir belum diinput" (dipakai dashboard & halaman alert) */
private function lastKeluarKosongSql(): string
{
    $filled = fn($c) => "NULLIF(TRIM({$c}), '') IS NOT NULL";
    $empty  = fn($c) => "NULLIF(TRIM({$c}), '') IS NULL";

    $sql = 'CASE ';
    foreach (array_reverse(self::GUDANG) as $s) {
        $sql .= 'WHEN (' . $filled('planning_loading' . $s)
            . ' OR ' . $filled('tanggal_tiba_gudang' . $s)
            . ' OR ' . $filled('tanggal_keluar_gudang' . $s)
            . ') THEN (' . $empty('tanggal_keluar_gudang' . $s) . ') ';
    }
    return $sql . 'ELSE 1 END';
}

/** Daftar baris di balik badge alert (angka sama persis dengan dashboard) */
public function alertList(Request $request, string $tipe)
{
  abort_unless(in_array($tipe, ['planner', 'monitoring_belum', 'monitoring_sudah'], true), 404);
    $this->normalizeRequest($request);

    $base    = $this->applyFilter($this->src(), $request);
    $grouped = $this->src()->whereIn('id', $this->groupIdsQuery($base));

  $kolom = $tipe === 'planner' ? 'planner' : 'pic_monitoring';
    $nama  = (string) $request->input('nama');
    $q     = $grouped->where($kolom, $nama);

    $periode = (string) $request->input('periode_shipment');
    if ($periode === 'KOSONG') {
        $q->where(fn($x) => $x->whereNull('periode_shipment')->orWhere('periode_shipment', ''));
    } elseif ($periode !== '') {
        $q->where('periode_shipment', $periode);
    }

  $lk = $this->lastKeluarKosongSql();
match ($tipe) {
    'planner'          => $q->whereRaw("({$lk}) = 1"),
    'monitoring_belum' => $q->whereRaw("NULLIF(TRIM(tanggal_tiba), '') IS NULL")->whereRaw("({$lk}) = 1"),
    'monitoring_sudah' => $q->whereRaw("NULLIF(TRIM(tanggal_tiba), '') IS NULL")->whereRaw("({$lk}) = 0"),
};

    $rows = $q->orderBy('sumber')->orderBy('no_shipment')->orderBy('tujuan')
        ->paginate(50)->withQueryString();

    return view('gabungan.alert_list', [
        'rows'    => $rows,
        'tipe'    => $tipe,
        'nama'    => $nama,
        'periode' => $periode,
        'gudang'  => self::GUDANG,
    ]);
}
}