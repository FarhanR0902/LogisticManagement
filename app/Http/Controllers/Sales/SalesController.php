<?php
namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache; 
use App\Models\LogistikPengirimanPasuruan;

/**
 * SalesController
 * Semua halaman list & kartu dashboard dihitung per SHIPMENT + TUJUAN
 * (1 baris wakil = MIN(id)), bukan per DO. Rincian DO dibuka lewat klik tujuan.
 */
class SalesController extends Controller
{
    // hanya tampilkan data periode shipment ini (format di DB: "MM-YYYY")
private const PERIODE_SHIPMENT = '11-2026';
    private const PULAU_MAP = [
        'JAWA'       => ['JABODEBEK','BANTEN','JAWA_BARAT','JAWA_TENGAH','JAWA_TIMUR','YOGYAKARTA'],
        'SUMATERA'   => ['ACEH','SUMATERA_UTARA','SUMATERA_BARAT','RIAU','KEP._RIAU','JAMBI','SUMATERA_SELATAN','BENGKULU','LAMPUNG','KEP._BANGKA_BELITUNG'],
        'KALIMANTAN' => ['KALIMANTAN_BARAT','KALIMANTAN_TENGAH','KALIMANTAN_SELATAN','KALIMANTAN_TIMUR','KALIMANTAN_UTARA'],
        'SULAWESI'   => ['SULAWESI_UTARA','SULAWESI_TENGAH','SULAWESI_SELATAN','SULAWESI_TENGGARA','SULAWESI_BARAT','GORONTALO'],
        'BALI_NUSRA' => ['PROV._BALI','NUSA_TENGGARA_BARAT','NUSA_TENGGARA_TIMUR'],
        'MALUKU'     => ['PROV._MALUKU','PROV._MALUKU_UTARA'],
        'PAPUA'      => ['PROV._PAPUA','PAPUA_BARAT','PAPUA_BARAT_DAYA','PAPUA_SELATAN','PAPUA_TENGAH'],
    ];

    /* =====================================================================
     |  FILTER DIST CHANNEL
     * ===================================================================== */
public function filterByDistChannel($query)
{
    $query->where('periode_shipment', self::PERIODE_SHIPMENT);
    return $this->scopeDistChannel($query, 'dist_channel');
}

public function filterByDistChannelPasuruan($query)
{
    $query->where('periode_shipment_pasuruan', self::PERIODE_SHIPMENT);
    return $this->scopeDistChannel($query, 'dist_channel_pasuruan');
}

private const MODERN_MARKET_CHANNELS = [
    'MT - Minimarket', 'MT - Supermarket', 'MT - Lokal', 'MT - Hypermarket', 'MT - Ecommerce',
];

/** Nilai dist_channel efektif: user modern_market dikunci, lainnya ikut session. */
private function resolveDistChannel()
{
    $u = auth()->user();

    if ($u && (
        strtolower((string) $u->username) === 'modern_market'
        || strtoupper((string) $u->dist_channel) === 'MODERN_MARKET'
    )) {
        return 'MODERN_MARKET';
    }

    return session('dist_channel');
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
     |  HELPER GROUPING (shipment + tujuan)
     * ===================================================================== */
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

    private function grupBiayaQuery($query)
    {
        return (clone $query)
            ->selectRaw("
                COALESCE(NULLIF(TRIM(no_shipment),''), CONCAT('id', id)) AS sh,
                COALESCE(tujuan,'')            AS tj,
                MAX(area)                      AS area,
                MAX(COALESCE(biaya_kirim,0))   AS biaya,
                SUM(COALESCE(nilai_muatan,0))  AS nilai,
                COUNT(*)                       AS jml_do
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
                SUM(COALESCE(nilai_muatan_pasuruan,0))  AS nilai,
                COUNT(*)                                AS jml_do
            ")
            ->groupByRaw('sh, tj');
    }

    /* =====================================================================
     |  FILTER
     * ===================================================================== */
private function dateFilterColumn($request): string
{
    return match ($request->input('tgl_basis')) {
        'rencana_kirim'         => 'rencana_kirim',
        'tanggal_naik_logistik' => 'tanggal_naik_logistik',
        default                 => 'periode_shipment',   // sebelumnya create_tgl
    };
}
  public function applyFilter($query, $request)
{
    $areas = array_values(array_filter((array) $request->input('area', []), 'strlen'));
    if (!empty($areas)) {
        $query->whereIn('area', $areas);
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

    // periode_shipment = teks "MM-YYYY", jadi tanggal pakai tanggal_shipment
    if ($col === 'periode_shipment') {
        if ($request->month) {
            $query->where('periode_shipment', substr($request->month, 5, 2) . '-' . substr($request->month, 0, 4));
        }
        if ($request->year) {
            $query->where('periode_shipment', 'like', '%-' . $request->year);
        }
        if ($from) $query->whereDate('tanggal_shipment', '>=', $from);
        if ($to)   $query->whereDate('tanggal_shipment', '<=', $to);

        return $query;
    }

    // basis lain (rencana_kirim / tanggal_naik_logistik): kolom tanggal biasa
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
public function dataGabungan()
{
    $a = DB::table('logistik_pengiriman')->whereNotNull('area')->where('area', '!=', '')->select('area');
    $this->filterByDistChannel($a);

    $b = DB::table('logistik_pengiriman_pasuruan')->whereNotNull('area_pasuruan')->where('area_pasuruan', '!=', '')
        ->select('area_pasuruan as area');
    $this->filterByDistChannelPasuruan($b);

    $areaList = $a->union($b)->pluck('area')->unique()->sort()->values();

    return view('sales.data_gabungan', compact('areaList'));
}

/** 1 baris = shipment + tujuan, dari Jakarta */
private function gabunganJakarta(Request $request)
{
    $n = fn($c) => "COALESCE(CAST(NULLIF(TRIM({$c}), '') AS DECIMAL(18,4)), 0)";

    $q = DB::table('logistik_pengiriman');
    $this->filterByDistChannel($q);
    $this->applyFilter($q, $request);

    // filter dari hyperlink dashboard yang tidak ditangani applyFilter()
    if ($request->filled('dist_channel')) {
        $q->where('dist_channel', $request->dist_channel);
    }
    if ($request->filled('date')) {
        $col = $this->dateFilterColumn($request);
        $q->whereDate($col === 'periode_shipment' ? 'tanggal_shipment' : $col, $request->date);
    }

    return $q->selectRaw("
            'Jakarta'                          AS sumber,
            MAX(no_shipment)                   AS no_shipment,
            COALESCE(tujuan,'')                AS tujuan,
            MAX(planner)                       AS planner,
            MAX(area)                          AS area,
            MAX(pulau)                         AS pulau,
            MAX(dist_channel)                  AS dist_channel,
            MAX(mobil)                         AS mobil,
            MAX(ekpedisi)                      AS ekspedisi,
            MAX(kategori_ekspedisi)            AS kategori,
            MAX(transport_lead_time)           AS lead_time,
            MAX(DATE(tanggal_shipment))        AS tgl_shipment,
            MAX(DATE(rencana_kirim))           AS rencana_kirim,
            MAX(DATE(tanggal_dpt_unit))        AS tgl_dapat_unit,
            MAX(DATE(estimasi_tiba))           AS tgl_estimasi,
            MAX(DATE(tanggal_tiba))            AS tgl_tiba,
            MAX(DATE(tanggal_bongkar))         AS tgl_bongkar,
            SUM(COALESCE(total_do_qty_car,0))  AS qty,
            SUM(COALESCE(nilai_muatan,0))      AS nilai,
            MAX(COALESCE(biaya_kirim,0))       AS biaya,
            MAX({$n('kubikasi')})              AS kubikasi,
            MAX({$n('tonase')})                AS tonase,
            SUM({$n('total_kubik')})           AS total_kubik,
            SUM({$n('total_tonase')})          AS total_tonase,
            COALESCE(
                MAX(CASE WHEN NULLIF(TRIM(pengiriman_optimal),'') IS NOT NULL AND UPPER(TRIM(pengiriman_optimal)) <> 'OPTIMAL' THEN TRIM(pengiriman_optimal) END),
                MAX(NULLIF(TRIM(pengiriman_optimal),''))
            )                                  AS pengiriman_optimal,
            MAX({$n('overstay_days')})         AS overstay,

            MAX(NULLIF(TRIM(planning_loading),''))          AS pl1,
            MAX(NULLIF(TRIM(tanggal_tiba_gudang),''))       AS tg1,
            MAX(NULLIF(TRIM(tanggal_keluar_gudang),''))     AS kg1,
            MAX(NULLIF(TRIM(planning_loading_2),''))        AS pl2,
            MAX(NULLIF(TRIM(tanggal_tiba_gudang_2),''))     AS tg2,
            MAX(NULLIF(TRIM(tanggal_keluar_gudang_2),''))   AS kg2,
            MAX(NULLIF(TRIM(planning_loading_3),''))        AS pl3,
            MAX(NULLIF(TRIM(tanggal_tiba_gudang_3),''))     AS tg3,
            MAX(NULLIF(TRIM(tanggal_keluar_gudang_3),''))   AS kg3,
            MAX(NULLIF(TRIM(planning_loading_4),''))        AS pl4,
            MAX(NULLIF(TRIM(tanggal_tiba_gudang_4),''))     AS tg4,
            MAX(NULLIF(TRIM(tanggal_keluar_gudang_4),''))   AS kg4,
            MAX(NULLIF(TRIM(planning_loading_5),''))        AS pl5,
            MAX(NULLIF(TRIM(tanggal_tiba_gudang_5),''))     AS tg5,
            MAX(NULLIF(TRIM(tanggal_keluar_gudang_5),''))   AS kg5,

            COUNT(*)                           AS jml_do
        ")
        ->groupByRaw("COALESCE(NULLIF(TRIM(no_shipment),''), CONCAT('id', id)), COALESCE(tujuan,'')");
}

/** 1 baris = shipment + tujuan, dari Pasuruan (urutan kolom HARUS sama dengan Jakarta) */
private function gabunganPasuruan(Request $request)
{
    $n = fn($c) => "COALESCE(CAST(NULLIF(TRIM({$c}), '') AS DECIMAL(18,4)), 0)";

    $q = DB::table('logistik_pengiriman_pasuruan');
    $this->filterByDistChannelPasuruan($q);
    $this->applyFilterPasuruan($q, $request);

    return $q->selectRaw("
            'Pasuruan'                                   AS sumber,
            MAX(no_shipment_pasuruan)                    AS no_shipment,
            COALESCE(tujuan_pasuruan,'')                 AS tujuan,
            MAX(planner_pasuruan)                        AS planner,
            MAX(area_pasuruan)                           AS area,
            MAX(pulau_pasuruan)                          AS pulau,
            MAX(dist_channel_pasuruan)                   AS dist_channel,
            MAX(mobil_pasuruan)                          AS mobil,
            MAX(ekspedisi_pasuruan)                      AS ekspedisi,
            MAX(kategori_ekspedisi_pasuruan)             AS kategori,
            MAX(transport_lead_time_pasuruan)            AS lead_time,
            MAX(DATE(tanggal_terima_po_pasuruan))        AS tgl_shipment,
            MAX(DATE(rencana_kirim_pasuruan))            AS rencana_kirim,
            MAX(DATE(tanggal_dpt_unit_pasuruan))         AS tgl_dapat_unit,
            MAX(DATE(estimasi_tiba_pasuruan))            AS tgl_estimasi,
            MAX(DATE(tanggal_tiba_pasuruan))             AS tgl_tiba,
            MAX(DATE(tanggal_bongkar_pasuruan))          AS tgl_bongkar,
            SUM({$n('total_do_pasuruan')})               AS qty,
            SUM({$n('nilai_muatan_pasuruan')})           AS nilai,
            IF(SUM({$n('kirim_kontribusi_pasuruan')}) > 0,
               SUM({$n('kirim_kontribusi_pasuruan')}),
               MAX({$n('biaya_kirim_pasuruan')}))        AS biaya,
            MAX({$n('kubikasi_pasuruan')})               AS kubikasi,
            MAX({$n('tonase_pasuruan')})                 AS tonase,
            SUM({$n('total_kubik_pasuruan')})            AS total_kubik,
            SUM({$n('total_tonase_pasuruan')})           AS total_tonase,
            COALESCE(
                MAX(CASE WHEN NULLIF(TRIM(pengiriman_optimal_pasuruan),'') IS NOT NULL AND UPPER(TRIM(pengiriman_optimal_pasuruan)) <> 'OPTIMAL' THEN TRIM(pengiriman_optimal_pasuruan) END),
                MAX(NULLIF(TRIM(pengiriman_optimal_pasuruan),''))
            )                                            AS pengiriman_optimal,
            MAX({$n('overstay_days_pasuruan')})          AS overstay,

            MAX(NULLIF(TRIM(planning_loading_pasuruan),''))        AS pl1,
            MAX(NULLIF(TRIM(tanggal_tiba_gudang_pasuruan),''))     AS tg1,
            MAX(NULLIF(TRIM(tanggal_keluar_gudang_pasuruan),''))   AS kg1,
            NULL AS pl2, NULL AS tg2, NULL AS kg2,
            NULL AS pl3, NULL AS tg3, NULL AS kg3,
            NULL AS pl4, NULL AS tg4, NULL AS kg4,
            NULL AS pl5, NULL AS tg5, NULL AS kg5,

            COUNT(*)                                     AS jml_do
        ")
        ->groupByRaw("COALESCE(NULLIF(TRIM(no_shipment_pasuruan),''), CONCAT('id', id)), COALESCE(tujuan_pasuruan,'')");
}

public function dataGabunganAjax(Request $request)
{
    $request = $this->withDashFilters($request);
  
    $sumber  = strtolower((string) $request->input('sumber', 'semua')); // semua | jakarta | pasuruan

    if ($sumber === 'jakarta') {
        $union = $this->gabunganJakarta($request);
    } elseif ($sumber === 'pasuruan') {
        $union = $this->gabunganPasuruan($request);
    } else {
        $union = $this->gabunganJakarta($request)->unionAll($this->gabunganPasuruan($request));
    }

    $base = DB::query()->fromSub($union, 'u');

    $recordsTotal = (clone $base)->count();

    $s = trim((string) $request->input('search.value', ''));
    if ($s !== '') {
        $base->where(function ($q) use ($s) {
            foreach (['no_shipment', 'tujuan', 'planner', 'area', 'ekspedisi', 'mobil', 'dist_channel'] as $c) {
                $q->orWhere($c, 'like', "%{$s}%");
            }
        });
    }
    $recordsFiltered = (clone $base)->count();

    $start  = max(0, (int) $request->input('start', 0));
    $length = (int) $request->input('length', 10);
    $length = $length > 0 ? min($length, 200) : 10;

    $rows = $base
        ->orderByDesc('tgl_shipment')
        ->orderByRaw('CAST(no_shipment AS UNSIGNED) ASC')
        ->orderBy('tujuan')
        ->skip($start)->take($length)
        ->get();

    $fmtD   = fn($v) => $v ? date('d-m-Y', strtotime($v)) : '-';
    $rp     = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $fmtDes = fn($v) => ($v === null || $v === '' || !is_numeric($v) || (float) $v == 0)
        ? '-' : number_format((float) $v, 2, ',', '.');
    $fmtHasil = fn($v) => $v === null ? '-' : number_format($v, 2, ',', '.') . '%';

    $data = $rows->map(function ($r) use ($fmtD, $rp, $fmtDes, $fmtHasil) {

        // ===== status = Update Posisi Mobil (sama dengan LogistikController) =====
        $namaGudang = $r->sumber === 'Jakarta'
            ? [1 => 'KACS', 2 => 'SENTUL', 3 => 'CCIE', 4 => 'P5', 5 => 'FIFATEX']
            : [1 => 'GUDANG'];

        $gudang = collect($namaGudang)
            ->map(fn($nama, $i) => [
                'nama'     => $nama,
                'planning' => $r->{"pl{$i}"},
                'tiba'     => $r->{"tg{$i}"},
                'keluar'   => $r->{"kg{$i}"},
            ])
            ->filter(fn($g) => !empty($g['planning']))
            ->sortBy(fn($g) => strtotime($g['planning']))
            ->values();

        $statusGudang = null;
        foreach ($gudang as $g) {
            if (empty($g['tiba'])) {
                $statusGudang = ['PERJALANAN KE ' . $g['nama'], 'yellow'];
                break;
            }
            if (empty($g['keluar'])) {
                $statusGudang = ['DI GUDANG ' . $g['nama'], 'blue'];
                break;
            }
        }

        if (empty($r->tgl_dapat_unit)) {
            [$st, $c] = ['MENCARI UNIT', 'red'];
        } elseif ($gudang->isEmpty() && empty($r->tgl_tiba)) {
            [$st, $c] = ['PERJALANAN KE GUDANG', 'orange'];
        } elseif ($statusGudang) {
            [$st, $c] = $statusGudang;
        } elseif (empty($r->tgl_tiba)) {
            [$st, $c] = ['PERJALANAN KE TUJUAN', 'yellow'];
        } elseif (!empty($r->tgl_bongkar)) {
            [$st, $c] = ['SUDAH SELESAI', 'green'];
        } else {
            [$st, $c] = ['SUDAH TIBA TUJUAN', 'pink'];
        }

        // ===== tgl keluar gudang terakhir (5 gudang) =====
        $keluarList = array_filter([$r->kg1, $r->kg2, $r->kg3, $r->kg4, $r->kg5]);
        $tglKeluarTerakhir = $keluarList ? max(array_map('strtotime', $keluarList)) : null;

        // ===== hasil kubik / tonase =====
        $hasilKubik  = (float) $r->kubikasi > 0 ? ((float) $r->total_kubik  / (float) $r->kubikasi) * 100 : null;
        $hasilTonase = (float) $r->tonase   > 0 ? ((float) $r->total_tonase / (float) $r->tonase)   * 100 : null;

        // ===== SLA tiba =====
        if ($r->tgl_tiba && $r->tgl_estimasi) {
            $sla = $r->tgl_tiba <= $r->tgl_estimasi
                ? '<span class="badge green">On Time</span>'
                : '<span class="badge red">Delay</span>';
        } else {
            $sla = '<span class="badge gray">-</span>';
        }

        // ===== SLA bongkar =====
        if ($r->tgl_bongkar) {
            $slaB = ((float) $r->overstay) <= 0
                ? '<span class="badge green">On Time</span>'
                : '<span class="badge red">Delay H+' . (int) $r->overstay . '</span>';
        } else {
            $slaB = '<span class="badge gray">-</span>';
        }

        // ===== pengiriman optimal =====
        $opt = strtoupper(trim((string) $r->pengiriman_optimal));
        $optHtml = $opt === ''
            ? '<span class="badge gray">-</span>'
            : ($opt === 'OPTIMAL'
                ? '<span class="badge green">✅ Optimal</span>'
                : '<span class="badge orange">⚠️ ' . e($r->pengiriman_optimal) . '</span>');

        $nilai = (float) $r->nilai;
        $cr    = $nilai > 0 ? ((float) $r->biaya / $nilai) * 100 : 0;
        $qty   = (float) $r->qty;

        $tujuan = trim((string) $r->no_shipment) !== ''
            ? '<a href="#" class="btn-tujuan" data-sumber="' . e($r->sumber) . '" data-shipment="' . e($r->no_shipment)
              . '" data-tujuan="' . e($r->tujuan) . '">▶ ' . e($r->tujuan)
              . ($r->jml_do > 1 ? ' <span class="badge gray">' . $r->jml_do . ' DO</span>' : '') . '</a>'
            : e($r->tujuan);

        return [
            'sumber_badge'       => '<span class="badge ' . ($r->sumber === 'Jakarta' ? 'blue' : 'orange') . '">' . e($r->sumber) . '</span>',
            'tgl_shipment'       => $fmtD($r->tgl_shipment),
            'rencana_kirim'      => $fmtD($r->rencana_kirim),
            'lead_time'          => $r->lead_time,
            'planner'            => $r->planner,
            'no_shipment'        => $r->no_shipment,
            'status_badge'       => '<span class="badge ' . $c . '">' . e($st) . '</span>',
            'dist_channel'       => $r->dist_channel,
            'tujuan_html'        => $tujuan,
            'area'               => $r->area,
            'pulau'              => $r->pulau,
            'mobil'              => $r->mobil,
            'qty'                => floor($qty) == $qty ? (int) $qty : $qty,
            'kubikasi'           => $fmtDes($r->kubikasi),
            'tonase'             => $fmtDes($r->tonase),
            'total_kubik'        => $fmtDes($r->total_kubik),
            'total_tonase'       => $fmtDes($r->total_tonase),
            'hasil_kubik'        => $fmtHasil($hasilKubik),
            'hasil_tonase'       => $fmtHasil($hasilTonase),
            'nilai_fmt'          => $rp($nilai),
            'biaya_fmt'          => $rp($r->biaya),
            'cr_fmt'             => $cr > 0 ? number_format($cr, 4, ',', '.') . '%' : '-',
            'pengiriman_optimal' => $optHtml,
            'kategori'           => $r->kategori,
            'ekspedisi'          => $r->ekspedisi,
            'tgl_dapat_unit'     => $fmtD($r->tgl_dapat_unit),
            'tgl_keluar_gudang'  => $tglKeluarTerakhir ? date('d-m-Y', $tglKeluarTerakhir) : '-',
            'tgl_estimasi'       => $fmtD($r->tgl_estimasi),
            'tgl_tiba'           => $fmtD($r->tgl_tiba),
            'sla_tiba'           => $sla,
            'tgl_bongkar'        => $fmtD($r->tgl_bongkar),
            'sla_bongkar'        => $slaB,
        ];
    });

    return response()->json([
        'draw'            => (int) $request->input('draw', 1),
        'recordsTotal'    => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data'            => $data,
    ]);
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

    $basis = $request->input('tgl_basis');

    // rencana_kirim -> kolom rencana; lainnya -> tanggal_terima_po
    $col = in_array($basis, ['rencana_kirim', 'rencana_kirim_pasuruan'], true)
        ? 'rencana_kirim_pasuruan'
        : 'tanggal_terima_po_pasuruan';

    // basis periode_shipment (default): bulan/tahun memakai teks "MM-YYYY"
    $byPeriode = !in_array($basis, ['rencana_kirim', 'rencana_kirim_pasuruan', 'tanggal_naik_logistik'], true);

    $from = $request->input('date_from');
    $to   = $request->input('date_to');
    if ($from && $to && $from > $to) {
        [$from, $to] = [$to, $from];
    }
    if ($from) $query->whereDate($col, '>=', $from);
    if ($to)   $query->whereDate($col, '<=', $to);

    if ($request->filled('date')) {
        $query->whereDate($col, $request->date);
    }

    if ($request->filled('month') && preg_match('/^(\d{4})-(\d{2})$/', $request->month, $m)) {
        $byPeriode
            ? $query->where('periode_shipment_pasuruan', $m[2] . '-' . $m[1])
            : $query->whereYear($col, $m[1])->whereMonth($col, $m[2]);
    }
    if ($request->filled('year')) {
        $byPeriode
            ? $query->where('periode_shipment_pasuruan', 'like', '%-' . $request->year)
            : $query->whereYear($col, $request->year);
    }

    return $query;
}

private function withDashFilters(Request $request): Request
{
    $ref = [];
    parse_str((string) parse_url((string) $request->headers->get('referer'), PHP_URL_QUERY), $ref);

    $kosong = fn($v) => is_array($v)
        ? count(array_filter($v, 'strlen')) === 0
        : in_array(strtolower(trim((string) $v)), ['', 'semua', 'all'], true);

    $merge = [];
    foreach (['sumber', 'tgl_basis', 'date_from', 'date_to', 'date', 'month', 'year', 'area', 'dist_channel', 'pulau'] as $k) {
        if ($kosong($request->input($k)) && isset($ref[$k]) && !$kosong($ref[$k])) {
            $merge[$k] = $ref[$k];
        }
    }

    return $request->merge($merge);
}
    /** Filter dashboard -> halaman Data Logistik Pasuruan (AJAX). */
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

    /* =====================================================================
     |  QUERY DASAR (dipakai kartu dashboard DAN halaman list)
     * ===================================================================== */
    private function kpiQuery(string $kpi)
    {
        $q = DB::table('logistik_pengiriman');

        switch ($kpi) {
            case 'gudang_ontime':
                $q->where(function ($w) {
                    $w->whereNotNull('tanggal_tiba_gudang')
                      ->orWhereNotNull('tanggal_tiba_gudang_2')
                      ->orWhereNotNull('tanggal_tiba_gudang_3');
                });
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
        }

        return $this->filterByDistChannel($q);
    }

    private function belumArmadaQuery()
    {
        $q = DB::table('logistik_pengiriman')
            ->where(function ($q) {
                $q->whereNull('rencana_kirim')
                  ->orWhere('rencana_kirim', '')
                  ->orWhereNull('tanggal_dpt_unit')
                  ->orWhere('tanggal_dpt_unit', '');
            });

        return $this->filterByDistChannel($q);
    }

    private function slaDelayQuery()
    {
        $q = DB::table('logistik_pengiriman')
            ->whereNotNull('rencana_kirim')
            ->whereRaw("TRIM(rencana_kirim) <> ''")
            ->whereNotNull('tanggal_dpt_unit')
            ->whereRaw("TRIM(tanggal_dpt_unit) <> ''")
            ->where(fn ($q) => $q->whereNull('tanggal_tiba_gudang')->orWhereRaw("TRIM(tanggal_tiba_gudang) = ''"))
            ->where(fn ($q) => $q->whereNull('tanggal_tiba_gudang_2')->orWhereRaw("TRIM(tanggal_tiba_gudang_2) = ''"))
            ->where(fn ($q) => $q->whereNull('tanggal_tiba_gudang_3')->orWhereRaw("TRIM(tanggal_tiba_gudang_3) = ''"));

        return $this->filterByDistChannel($q);
    }

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

        return $this->filterByDistChannel($q);
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

    private function kpiQueryPasuruan(string $kpi)
    {
        $filled = fn($c) => "NULLIF(TRIM({$c}), '') IS NOT NULL";
        $empty  = fn($c) => "NULLIF(TRIM({$c}), '') IS NULL";

        $q = DB::table('logistik_pengiriman_pasuruan');

        switch ($kpi) {
            case 'gudang_ontime':
                $q->whereRaw($filled('tanggal_tiba_gudang_pasuruan'));
                break;
            case 'gudang_delay':
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

        return $this->filterByDistChannelPasuruan($q);
    }

    private function inTransitQueryPasuruan()
    {
        $filled = fn($c) => "NULLIF(TRIM({$c}), '') IS NOT NULL";
        $empty  = fn($c) => "NULLIF(TRIM({$c}), '') IS NULL";

        $q = DB::table('logistik_pengiriman_pasuruan')
            ->whereRaw($empty('tanggal_tiba_pasuruan'))
            ->whereRaw($filled('tanggal_keluar_gudang_pasuruan'));

        return $this->filterByDistChannelPasuruan($q);
    }

    private function inTransitEstimasiSqlPasuruan(): string
    {
        return "COALESCE(estimasi_tiba_pasuruan, DATE_ADD(
            COALESCE(tanggal_keluar_gudang_pasuruan, '1900-01-01'),
            INTERVAL CAST(COALESCE(NULLIF(TRIM(transport_lead_time_pasuruan),''),0) AS UNSIGNED) DAY
        ))";
    }

    /** Baris wakil (1 per shipment + tujuan) untuk satu KPI Pasuruan. */
    private function kpiGroupedPasuruan(Request $request, string $kpi)
    {
        $order = [
            'gudang_ontime'  => 'tanggal_tiba_gudang_pasuruan',
            'gudang_delay'   => 'tanggal_dpt_unit_pasuruan',
            'tujuan_ontime'  => 'tanggal_tiba_pasuruan',
            'tujuan_delay'   => 'tanggal_tiba_pasuruan',
            'bongkar_ontime' => 'tanggal_bongkar_pasuruan',
            'bongkar_delay'  => 'tanggal_bongkar_pasuruan',
        ][$kpi];

        $filter = $this->applyFilterPasuruan($this->kpiQueryPasuruan($kpi), $request);

        return DB::table('logistik_pengiriman_pasuruan')
            ->whereIn('id', $this->groupIdsQueryPasuruan($filter))
            ->orderByDesc($order)
            ->orderBy('no_shipment_pasuruan')
            ->orderBy('tujuan_pasuruan');
    }

    /* =====================================================================
     |  GET AREA
     * ===================================================================== */
    private function getArea()
    {
        $query = DB::table('logistik_pengiriman')->select('area')->whereNotNull('area');
        $this->filterByDistChannel($query);

        return $query->groupBy('area')->orderBy('area')->get();
    }

    private function listAreaPasuruan()
    {
        $q = DB::table('logistik_pengiriman_pasuruan')
            ->select('area_pasuruan as area')
            ->whereNotNull('area_pasuruan');

        $this->filterByDistChannelPasuruan($q);

        return $q->distinct()->orderBy('area_pasuruan')->get();
    }

    /* =====================================================================
     |  DASHBOARD (logistik_pengiriman)
     * ===================================================================== */
    public function dashboard(Request $request)
    {
        $base = $this->applyFilter($this->filterByDistChannel(DB::table('logistik_pengiriman')), $request);

        $grouped = DB::table('logistik_pengiriman')->whereIn('id', $this->groupIdsQuery($base));

        $total_data = $this->countGroups($base);

        $kpi = fn(string $k) => $this->countGroups($this->applyFilter($this->kpiQuery($k), $request));

        $gudang_ontime   = $kpi('gudang_ontime');
        $customer_ontime = $kpi('tujuan_ontime');
        $customer_delay  = $kpi('tujuan_delay');
        $bongkar_ontime  = $kpi('bongkar_ontime');
        $bongkar_delay   = $kpi('bongkar_delay');

        // sama dengan halaman belumArmada
        $gudang_delay = $this->countGroups($this->applyFilter($this->belumArmadaQuery(), $request));

        $total_in_transit = $this->countGroups($this->applyFilter($this->inTransitQuery(), $request));
        $total_sla_delay  = $this->countGroups($this->applyFilter($this->slaDelayQuery(), $request));

        $planner_belum_armada = $gudang_delay;
        $planner_armada = $this->countGroups(
            (clone $base)
                ->whereNotNull('rencana_kirim')->whereRaw("TRIM(rencana_kirim) <> ''")
                ->whereNotNull('tanggal_dpt_unit')->whereRaw("TRIM(tanggal_dpt_unit) <> ''")
        );

        $planner_ontime = $this->countGroups(
            (clone $base)->whereNotNull('rencana_kirim')->whereNotNull('tanggal_dpt_unit')
                ->whereRaw('DATE(tanggal_dpt_unit) <= DATE(rencana_kirim)')
        );
        $planner_delay = $this->countGroups(
            (clone $base)->whereNotNull('rencana_kirim')->whereNotNull('tanggal_dpt_unit')
                ->whereRaw('DATE(tanggal_dpt_unit) > DATE(rencana_kirim)')
        );

        $list_dist_channel = (clone $base)->select('dist_channel')
            ->whereNotNull('dist_channel')->distinct()->orderBy('dist_channel')->get();

        // nilai = SUM per grup, biaya = MAX per grup
        $grp = $this->grupBiayaQuery($base);

        $tot = DB::query()->fromSub($grp, 'g')
            ->selectRaw('COALESCE(SUM(biaya),0) AS total_biaya, COALESCE(SUM(nilai),0) AS total_nilai')
            ->first();
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

        $summary_monitoring = [
            'tiba_ontime'    => $total_data > 0 ? ($customer_ontime / $total_data) * 100 : 0,
            'tiba_delay'     => $total_data > 0 ? ($customer_delay / $total_data) * 100 : 0,
            'bongkar_ontime' => $total_data > 0 ? ($bongkar_ontime / $total_data) * 100 : 0,
            'bongkar_delay'  => $total_data > 0 ? ($bongkar_delay / $total_data) * 100 : 0,
        ];

        // dropdown area: dibatasi dist_channel, TIDAK dibatasi filter pulau
        $list_area = $this->getArea()->pluck('area');

        return view('sales.dashboard', compact(
            'total_data', 'gudang_ontime', 'gudang_delay',
            'customer_ontime', 'customer_delay',
            'bongkar_ontime', 'bongkar_delay',
            'total_in_transit', 'total_sla_delay',
            'summary_area', 'summary_tujuan',
            'totalNilaiMuatan', 'totalBiayaKirim',
            'ekspedisi', 'label', 'value',
            'planner_ontime', 'planner_delay',
            'planner_armada', 'planner_belum_armada',
            'ontime_rate', 'delay_rate', 'armada_rate', 'pending_rate',
            'summary_monitoring', 'list_dist_channel', 'list_area'
        ));
    }

    /* =====================================================================
     |  HALAMAN LIST (logistik_pengiriman) - per shipment + tujuan
     * ===================================================================== */
    public function belumArmada(Request $request)
    {
        $filter = $this->applyFilter($this->belumArmadaQuery(), $request);

        $logistik = DB::table('logistik_pengiriman')
            ->whereIn('id', $this->groupIdsQuery($filter))
            ->orderByDesc('create_tgl')->orderBy('no_shipment')->orderBy('tujuan')
            ->get();

        return view('sales.belum_armada', ['logistik' => $logistik, 'list_area' => $this->getArea()]);
    }

    public function slaDelay(Request $request)
    {
        $filter = $this->applyFilter($this->slaDelayQuery(), $request);

        $list = DB::table('logistik_pengiriman')
            ->whereIn('id', $this->groupIdsQuery($filter))
            ->orderByDesc('tanggal_dpt_unit')->orderBy('no_shipment')->orderBy('tujuan')
            ->get();

        return view('sales.sla_delay', ['list' => $list, 'list_area' => $this->getArea()]);
    }

    // query-nya identik dengan slaDelay()
    public function gudangDelay(Request $request)
    {
        return $this->slaDelay($request);
    }

    public function gudangOntime(Request $request)
    {
        $filter = $this->applyFilter($this->kpiQuery('gudang_ontime'), $request);

        $list = DB::table('logistik_pengiriman')
            ->whereIn('id', $this->groupIdsQuery($filter))
            ->orderByDesc(DB::raw('COALESCE(tanggal_tiba_gudang, tanggal_tiba_gudang_2, tanggal_tiba_gudang_3)'))
            ->orderBy('no_shipment')->orderBy('tujuan')
            ->get();

        return view('sales.sla_ontime', ['list' => $list, 'list_area' => $this->getArea()]);
    }

    public function tujuanOntime(Request $request)
    {
        $filter = $this->applyFilter($this->kpiQuery('tujuan_ontime'), $request);

        $logistik = DB::table('logistik_pengiriman')
            ->selectRaw("logistik_pengiriman.*, estimasi_tiba AS tanggal_estimasi, 'On Time' AS sla_tiba")
            ->whereIn('id', $this->groupIdsQuery($filter))
            ->orderByDesc('tanggal_tiba')->orderBy('no_shipment')->orderBy('tujuan')
            ->get();

        return view('sales.tujuan_ontime', ['logistik' => $logistik, 'list_area' => $this->getArea()]);
    }

    public function tujuanDelay(Request $request)
    {
        $filter = $this->applyFilter($this->kpiQuery('tujuan_delay'), $request);

        $logistik = DB::table('logistik_pengiriman')
            ->selectRaw("logistik_pengiriman.*, estimasi_tiba AS tanggal_estimasi, 'Delay' AS sla_tiba")
            ->whereIn('id', $this->groupIdsQuery($filter))
            ->orderBy('no_shipment')->orderBy('tujuan')->orderBy('estimasi_tiba')
            ->get();

        return view('sales.tujuan_delay', ['logistik' => $logistik, 'list_area' => $this->getArea()]);
    }

    public function bongkarOntime(Request $request)
    {
        $filter = $this->applyFilter($this->kpiQuery('bongkar_ontime'), $request);

        $logistik = DB::table('logistik_pengiriman')
            ->selectRaw("logistik_pengiriman.*, 'H+0' AS sla_bongkar")
            ->whereIn('id', $this->groupIdsQuery($filter))
            ->orderByDesc('tanggal_bongkar')->orderBy('no_shipment')->orderBy('tujuan')
            ->get();

        return view('sales.bongkar_ontime', ['logistik' => $logistik, 'list_area' => $this->getArea()]);
    }

    public function bongkarDelay(Request $request)
    {
        $filter = $this->applyFilter($this->kpiQuery('bongkar_delay'), $request);

        $logistik = DB::table('logistik_pengiriman')
            ->selectRaw("logistik_pengiriman.*, CASE
                WHEN overstay_days = 1 THEN 'H+1'
                WHEN overstay_days = 2 THEN 'H+2'
                ELSE 'Critical Delay' END AS sla_bongkar")
            ->whereIn('id', $this->groupIdsQuery($filter))
            ->orderByDesc('tanggal_bongkar')->orderBy('no_shipment')->orderBy('tujuan')
            ->get();

        return view('sales.bongkar_delay', ['logistik' => $logistik, 'list_area' => $this->getArea()]);
    }

    public function summaryTotal(Request $request)
    {
        $filter = $this->applyFilter($this->filterByDistChannel(DB::table('logistik_pengiriman')), $request);

        $logistik = DB::table('logistik_pengiriman')
            ->whereIn('id', $this->groupIdsQuery($filter))
            ->orderBy('no_shipment')->orderBy('tujuan')
            ->get();

        return view('sales.summary_total', compact('logistik'));
    }

    public function summaryArea(Request $request)
    {
        $query = $this->applyFilter($this->filterByDistChannel(DB::table('logistik_pengiriman')), $request);

        $summary_area = DB::query()->fromSub($this->grupBiayaQuery($query), 'g')
            ->selectRaw('area, COUNT(*) AS total_shipment, SUM(biaya) AS total_biaya, SUM(nilai) AS total_muatan')
            ->whereNotNull('area')->groupBy('area')->orderByDesc('total_shipment')->get();

        return view('sales.summary_area', compact('summary_area'));
    }

    /* =====================================================================
     |  IN TRANSIT (logistik_pengiriman) - per shipment + tujuan
     * ===================================================================== */
    public function inTransit(Request $request)
    {
        $today   = date('Y-m-d');
        $todayTs = strtotime($today);
        $soon    = date('Y-m-d', strtotime('+3 days'));
        $est     = $this->inTransitEstimasiSql();

        $base = $this->applyFilter($this->inTransitQuery(), $request);

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
            ->paginate(25)
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

            [$alert, $cls] = $this->alertTransit($estimasi, $todayTs);

            $r->gudang_asal    = $asal;
            $r->keluar_label   = $keluar ? date('d-m-Y', $keluar) : '-';
            $r->hari_transit   = $keluarD ? max(0, floor(($todayTs - $keluarD) / 86400)) : null;
            $r->estimasi_label = $estimasi ? date('d-m-Y', $estimasi) : '-';
            $r->alert_label    = $alert;
            $r->alert_class    = $cls;

            return $r;
        });

        $areaList = $this->getArea()->pluck('area');
        $picList  = collect();
        $formRoute = route('sales.intransit');

        return view('monitoring.in_transit', compact('list', 'summary', 'areaList', 'picList', 'formRoute'));
    }

    public function inTransitPasuruan(Request $request)
    {
        $today   = date('Y-m-d');
        $todayTs = strtotime($today);
        $soon    = date('Y-m-d', strtotime('+3 days'));
        $est     = $this->inTransitEstimasiSqlPasuruan();

        $base = $this->applyFilterPasuruan($this->inTransitQueryPasuruan(), $request);

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

        $grouped = DB::table('logistik_pengiriman_pasuruan')->whereIn('id', $this->groupIdsQueryPasuruan($base));

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
            $keluar = !empty($r->tanggal_keluar_gudang_pasuruan)
                ? strtotime($r->tanggal_keluar_gudang_pasuruan)
                : null;

            $lead     = (int) ($r->transport_lead_time_pasuruan ?? 0);
            $keluarD  = $keluar ? strtotime(date('Y-m-d', $keluar)) : null;
            $estimasi = !empty($r->estimasi_tiba_pasuruan)
                ? strtotime($r->estimasi_tiba_pasuruan)
                : ($keluarD ? strtotime("+{$lead} days", $keluarD) : null);

            [$alert, $cls] = $this->alertTransit($estimasi, $todayTs);

            return (object) [
                'no_shipment'    => $r->no_shipment_pasuruan,
                'tujuan'         => $r->tujuan_pasuruan,
                'area'           => $r->area_pasuruan,
                'dist_channel'   => $r->dist_channel_pasuruan,
                'ekpedisi'       => $r->ekspedisi_pasuruan,
                'mobil'          => $r->mobil_pasuruan,
                'pic_monitoring' => $r->pic_monitoring_pasuruan,
                'nama_driver'    => $r->nama_driver_pasuruan,
                'no_pol'         => $r->no_pol_pasuruan,
                'remarks'        => $r->remarks_pasuruan,
                'gudang_asal'    => 'GUDANG',
                'keluar_label'   => $keluar ? date('d-m-Y', $keluar) : '-',
                'hari_transit'   => $keluarD ? max(0, floor(($todayTs - $keluarD) / 86400)) : null,
                'estimasi_label' => $estimasi ? date('d-m-Y', $estimasi) : '-',
                'alert_label'    => $alert,
                'alert_class'    => $cls,
            ];
        });

        $areaList  = $this->listAreaPasuruan()->pluck('area');
        $picList   = collect();
        $formRoute = route('sales.intransit.pasuruan');

        return view('monitoring.in_transit', compact('list', 'summary', 'areaList', 'picList', 'formRoute'));
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

    /* =====================================================================
     |  PASURUAN - DASHBOARD
     * ===================================================================== */
    public function dashboardPasuruan(Request $request)
    {
        $base = DB::table('logistik_pengiriman_pasuruan');
        $this->filterByDistChannelPasuruan($base);
        $this->applyFilterPasuruan($base, $request);

        $grouped = DB::table('logistik_pengiriman_pasuruan')
            ->whereIn('id', $this->groupIdsQueryPasuruan($base));

        $total_data = $this->countGroupsPasuruan($base);

        $kpi = fn(string $k) => $this->countGroupsPasuruan(
            $this->applyFilterPasuruan($this->kpiQueryPasuruan($k), $request)
        );

        $gudang_ontime   = $kpi('gudang_ontime');
        $gudang_delay    = $kpi('gudang_delay');
        $customer_ontime = $kpi('tujuan_ontime');
        $customer_delay  = $kpi('tujuan_delay');
        $bongkar_ontime  = $kpi('bongkar_ontime');
        $bongkar_delay   = $kpi('bongkar_delay');

        $total_in_transit = $this->countGroupsPasuruan(
            $this->applyFilterPasuruan($this->inTransitQueryPasuruan(), $request)
        );

        $list_area = $this->listAreaPasuruan()->pluck('area');

        $planner_armada = $this->countGroupsPasuruan(
            (clone $base)
                ->whereNotNull('rencana_kirim_pasuruan')
                ->whereRaw("TRIM(rencana_kirim_pasuruan) <> ''")
                ->whereNotNull('tanggal_dpt_unit_pasuruan')
                ->whereRaw("TRIM(tanggal_dpt_unit_pasuruan) <> ''")
        );

        $planner_belum_armada = $this->countGroupsPasuruan(
            (clone $base)->where(function ($q) {
                $q->whereNull('rencana_kirim_pasuruan')
                  ->orWhere('rencana_kirim_pasuruan', '')
                  ->orWhereNull('tanggal_dpt_unit_pasuruan')
                  ->orWhere('tanggal_dpt_unit_pasuruan', '');
            })
        );

        $list_dist_channel = (clone $base)
            ->select('dist_channel_pasuruan')
            ->whereNotNull('dist_channel_pasuruan')
            ->distinct()->orderBy('dist_channel_pasuruan')->get();

        $planner_ontime = $this->countGroupsPasuruan(
            (clone $base)
                ->whereNotNull('rencana_kirim_pasuruan')
                ->whereNotNull('tanggal_dpt_unit_pasuruan')
                ->whereRaw('DATE(tanggal_dpt_unit_pasuruan) <= DATE(rencana_kirim_pasuruan)')
        );

        $planner_delay = $this->countGroupsPasuruan(
            (clone $base)
                ->whereNotNull('rencana_kirim_pasuruan')
                ->whereNotNull('tanggal_dpt_unit_pasuruan')
                ->whereRaw('DATE(tanggal_dpt_unit_pasuruan) > DATE(rencana_kirim_pasuruan)')
        );

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

        return view('sales.dashboard_pasuruan', compact(
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
        ));
    }

    /* =====================================================================
     |  PASURUAN - HALAMAN LIST KPI (per shipment + tujuan)
     * ===================================================================== */
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

        foreach (['planning_loading_2', 'tanggal_tiba_gudang_2', 'tanggal_keluar_gudang_2',
                  'planning_loading_3', 'tanggal_tiba_gudang_3', 'tanggal_keluar_gudang_3'] as $k) {
            $o[$k] = null;
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
            }
            return $row;
        })->values();

        return view($view, [$var => $list, 'list_area' => $this->listAreaPasuruan()]);
    }

    public function gudangOntimePasuruan(Request $request)
    {
        return $this->renderKpiPasuruan($request, 'gudang_ontime', 'sales.sla_ontime', 'list', [], true);
    }

    public function gudangDelayPasuruan(Request $request)
    {
        return $this->renderKpiPasuruan($request, 'gudang_delay', 'sales.sla_delay', 'list');
    }

    public function tujuanOntimePasuruan(Request $request)
    {
        return $this->renderKpiPasuruan($request, 'tujuan_ontime', 'sales.tujuan_ontime', 'logistik');
    }

    public function tujuanDelayPasuruan(Request $request)
    {
        return $this->renderKpiPasuruan($request, 'tujuan_delay', 'sales.tujuan_delay', 'logistik');
    }

    public function bongkarOntimePasuruan(Request $request)
    {
        return $this->renderKpiPasuruan($request, 'bongkar_ontime', 'sales.bongkar_ontime', 'logistik');
    }

    public function bongkarDelayPasuruan(Request $request)
    {
        return $this->renderKpiPasuruan($request, 'bongkar_delay', 'sales.bongkar_delay', 'logistik');
    }

    public function summaryAreaPasuruan(Request $request)
    {
        $query = DB::table('logistik_pengiriman_pasuruan');
        $this->filterByDistChannelPasuruan($query);
        $this->applyFilterPasuruan($query, $request);

        $summary_area = DB::query()->fromSub($this->grupBiayaQueryPasuruan($query), 'g')
            ->selectRaw('area_pasuruan, COUNT(*) AS total_shipment, SUM(biaya) AS total_biaya, SUM(nilai) AS total_muatan')
            ->whereNotNull('area_pasuruan')
            ->groupBy('area_pasuruan')
            ->orderByDesc('total_shipment')
            ->get();

        return view('sales.summary_area', compact('summary_area'));
    }

    /* =====================================================================
     |  PASURUAN - DATA LOGISTIK (AJAX, per shipment + tujuan)
     * ===================================================================== */
    public function dataLogistikPasuruan()
    {
        $plannerQuery = LogistikPengirimanPasuruan::select('planner_pasuruan')
            ->whereNotNull('planner_pasuruan')
            ->where('planner_pasuruan', '!=', '');
        $this->filterByDistChannelPasuruan($plannerQuery);

        $planners = $plannerQuery->distinct()->orderBy('planner_pasuruan')->pluck('planner_pasuruan');

        $areaQuery = LogistikPengirimanPasuruan::select('area_pasuruan')
            ->whereNotNull('area_pasuruan')
            ->where('area_pasuruan', '!=', '');
        $this->filterByDistChannelPasuruan($areaQuery);

        $areas = $areaQuery->distinct()->orderBy('area_pasuruan')->pluck('area_pasuruan');

        return view('sales.data_logistik_pasuruan', compact('planners', 'areas'));
    }

    /** jumlah per shipment + tujuan untuk baris yang tampil di halaman ini */
  private function groupAggPasuruan($rows): array
{
    $n = fn($c) => "COALESCE(CAST(NULLIF(TRIM({$c}), '') AS DECIMAL(18,4)), 0)";
 
    $shipments = $rows->pluck('no_shipment_pasuruan')
        ->filter(fn($v) => trim((string) $v) !== '')->unique()->values();
 
    if ($shipments->isEmpty()) return [];
 
    $q = LogistikPengirimanPasuruan::query()->whereIn('no_shipment_pasuruan', $shipments);
    $this->filterByDistChannelPasuruan($q);
 
    return $q->select(
            'no_shipment_pasuruan',
            DB::raw("COALESCE(tujuan_pasuruan,'') as tujuan_key"),
            DB::raw('COUNT(*) as jml_do'),
            DB::raw('SUM(' . $n('total_kubik_pasuruan') . ') as sum_kubik'),
            DB::raw('SUM(' . $n('total_tonase_pasuruan') . ') as sum_tonase'),
            DB::raw('SUM(' . $n('nilai_muatan_pasuruan') . ') as sum_nilai'),
            DB::raw('SUM(' . $n('total_do_pasuruan') . ') as sum_qty'),
            DB::raw('SUM(' . $n('kirim_kontribusi_pasuruan') . ') as sum_kontribusi'),
            DB::raw('SUM(' . $n('selisih_quantity_pasuruan') . ') as sum_selisih'),
            DB::raw("SUM(CASE WHEN NULLIF(TRIM(selisih_quantity_pasuruan),'') IS NOT NULL THEN 1 ELSE 0 END) as jml_selisih"),
            DB::raw('IF(SUM(' . $n('kirim_kontribusi_pasuruan') . ') > 0,
                        SUM(' . $n('biaya_kirim_pasuruan') . '),
                        MAX(' . $n('biaya_kirim_pasuruan') . ')) as sum_biaya')
        )
        ->groupBy('no_shipment_pasuruan', 'tujuan_key')
        ->get()
        ->keyBy(fn($g) => $g->no_shipment_pasuruan . '|' . $g->tujuan_key)
        ->all();
}
/** Rincian DO per shipment + tujuan (klik tujuan di halaman Data Pasuruan Sales) */
public function dataLogistikDetailPasuruan(Request $request)
{
    $request->validate([
        'no_shipment' => 'required|string',
        'tujuan'      => 'nullable|string',
    ]);

    $q = LogistikPengirimanPasuruan::query()
        ->where('no_shipment_pasuruan', $request->no_shipment);
    $this->filterByDistChannelPasuruan($q);   // modern market hanya lihat channel miliknya

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
 public function dataLogistikPasuruanAjax(Request $request)
{
    $draw   = (int) $request->input('draw', 1);
    $start  = max(0, (int) $request->input('start', 0));
    $length = (int) $request->input('length', 10);
    $searchValue = trim((string) $request->input('search.value', ''));
 
    $query = LogistikPengirimanPasuruan::query();
 
    $this->filterByDistChannelPasuruan($query);
    $this->applyFilterPasuruan($query, $this->dashRequestPasuruan($request));
 
    if ($request->filled('planner')) $query->where('planner_pasuruan', $request->planner);
    if ($request->filled('area'))    $query->where('area_pasuruan', $request->area);
    if ($request->filled('date'))    $query->whereDate('tanggal_terima_po_pasuruan', $request->date);
    if ($request->filled('month'))   $query->whereMonth('tanggal_terima_po_pasuruan', $request->month);
    if ($request->filled('year'))    $query->whereYear('tanggal_terima_po_pasuruan', $request->year);
 
    // jumlah GRUP (shipment + tujuan), bukan jumlah DO
    $recordsTotal = $this->countGroupsPasuruan($query);
 
    if ($searchValue !== '') {
        $query->where(function ($q) use ($searchValue) {
            foreach (['no_shipment_pasuruan', 'tujuan_pasuruan', 'ekspedisi_pasuruan',
                      'area_pasuruan', 'planner_pasuruan', 'no_do_pasuruan',
                      'sales_document_pasuruan', 'customer_reference_pasuruan'] as $col) {
                $q->orWhere($col, 'like', "%{$searchValue}%");
            }
        });
        $recordsFiltered = $this->countGroupsPasuruan($query);
    } else {
        $recordsFiltered = $recordsTotal;
    }
 
    $rows = LogistikPengirimanPasuruan::query()
        ->whereIn('id', $this->groupIdsQueryPasuruan($query))
        // baris tanpa no_shipment taruh paling bawah
        ->orderByRaw("(no_shipment_pasuruan IS NULL OR TRIM(no_shipment_pasuruan) = '') ASC")
        ->orderByRaw('CAST(no_shipment_pasuruan AS UNSIGNED) ASC')
        ->orderBy('no_shipment_pasuruan')
        ->orderBy('tujuan_pasuruan')
        ->orderBy('id')
        ->skip($start)
        ->take($length > 0 ? min($length, 200) : max(1, $recordsFiltered))
        ->get();
 
    $groupAgg = $this->groupAggPasuruan($rows);
    // CR per shipment: jumlahkan semua tujuan dalam 1 shipment
$crShipment = [];
foreach ($groupAgg as $g) {
    $ns    = $g->no_shipment_pasuruan;
    $biaya = (float) $g->sum_kontribusi > 0 ? (float) $g->sum_kontribusi : (float) $g->sum_biaya;

    $crShipment[$ns]['biaya']  = ($crShipment[$ns]['biaya']  ?? 0) + $biaya;
    $crShipment[$ns]['muatan'] = ($crShipment[$ns]['muatan'] ?? 0) + (float) $g->sum_nilai;
}
 
    $data = [];
    foreach ($rows as $r) {
        $g = $groupAgg[$r->no_shipment_pasuruan . '|' . ($r->tujuan_pasuruan ?? '')] ?? null;
 
        if ($g && trim((string) $r->no_shipment_pasuruan) !== '') {
            $r->nilai_muatan_pasuruan = $g->sum_nilai;
            $r->biaya_kirim_pasuruan  = (float) $g->sum_kontribusi > 0 ? $g->sum_kontribusi : $g->sum_biaya;
 
            $q = (float) $g->sum_qty;
            $r->total_kubik_pasuruan      = $g->sum_kubik;
            $r->total_tonase_pasuruan     = $g->sum_tonase;
            $r->kirim_kontribusi_pasuruan = $g->sum_kontribusi;
            $r->total_do_pasuruan         = floor($q) == $q ? (int) $q : $q;
 
            if ((int) $g->jml_selisih > 0) {
                $actual = $q - (float) $g->sum_selisih;
                $r->actual_delivery_quantity_pasuruan = floor($actual) == $actual ? (int) $actual : $actual;
            } else {
                $r->actual_delivery_quantity_pasuruan = null;
            }
        }
        $cs = $crShipment[$r->no_shipment_pasuruan] ?? null;
$r->cr_shipment_val = ($cs && $cs['muatan'] > 0)
    ? ($cs['biaya'] / $cs['muatan']) * 100
    : ((float) $r->nilai_muatan_pasuruan > 0
        ? ((float) $r->biaya_kirim_pasuruan / (float) $r->nilai_muatan_pasuruan) * 100
        : 0);
 
        $r->jml_do_grup = $g->jml_do ?? 1;
        $data[] = $this->renderSalesPasuruanRow($r);
    }
 
    return response()->json([
        'draw'            => $draw,
        'recordsTotal'    => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data'            => $data,
    ]);
}
    /**
     * 1 baris untuk halaman Data Pasuruan (Sales).
     * Urutan array HARUS sinkron dengan <th> di sales/data_logistik_pasuruan.blade.php (52 kolom).
     */
    private function renderSalesPasuruanRow($r)
    {
        $fmtDate   = fn ($v, $fmt = 'd-m-Y') => $v ? date($fmt, strtotime($v)) : '-';
        $fmtRupiah = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');

        // ===== posisi mobil =====
        $dpt           = $r->tanggal_dpt_unit_pasuruan;
        $tibaGudang    = $r->tanggal_tiba_gudang_pasuruan;
        $keluarGudang  = $r->tanggal_keluar_gudang_pasuruan;
        $tibaTujuan    = $r->tanggal_tiba_pasuruan;
        $bongkarTujuan = $r->tanggal_bongkar_pasuruan;

        if (empty($dpt)) {
            $status = 'MENCARI UNIT'; $badge = 'red';
        } elseif (empty($tibaGudang)) {
            $status = 'PERJALANAN KE GUDANG'; $badge = 'orange';
        } elseif (empty($keluarGudang)) {
            $status = 'DI GUDANG'; $badge = 'blue';
        } elseif (empty($tibaTujuan)) {
            $status = 'PERJALANAN KE TUJUAN'; $badge = 'yellow';
        } elseif (empty($bongkarTujuan)) {
            $status = 'TIBA DI TUJUAN'; $badge = 'success';
        } else {
            $status = 'SUDAH SELESAI'; $badge = 'green';
        }
        $posisiHtml = '<span class="badge ' . $badge . '">' . e($status) . '</span>';

        // ===== dist channel =====
        $channel = trim($r->dist_channel_pasuruan ?? '');
        $channelClasses = ['badge-green','badge-blue','badge-orange','badge-red','badge-purple','badge-pink','badge-cyan','badge-yellow'];
        $channelClass = $channel ? $channelClasses[abs(crc32($channel)) % count($channelClasses)] : 'badge-default';
        $channelHtml = '<span class="badge ' . $channelClass . '">' . e($channel ?: '-') . '</span>';

        // ===== ketersediaan unit =====
        $ketersediaanHtml = !empty($r->tanggal_dpt_unit_pasuruan)
            ? '<span class="badge-status status-sudah">Sudah Dapat Unit</span>'
            : '<span class="badge-status status-belum">Belum Dapat Unit</span>';

        // ===== CR (dari nilai & biaya yang sudah dijumlah per grup) =====
        $nilai = (float) $r->nilai_muatan_pasuruan;
        $biaya = (float) $r->biaya_kirim_pasuruan;
        $cr    = $nilai > 0 ? ($biaya / $nilai) * 100 : 0;

        $crHtml = $cr > 0
            ? '<span class="cr-value">' . number_format($cr, 4, ',', '.') . '%</span>'
            : '<span class="text-muted">-</span>';
            $crShipHtml = (float) ($r->cr_shipment_val ?? 0) > 0
    ? '<span class="cr-value">' . number_format((float) $r->cr_shipment_val, 4, ',', '.') . '%</span>'
    : '<span class="text-muted">-</span>';
            $fmtDes    = fn ($v) => ($v === null || $v === '' || !is_numeric($v)) ? '-' : number_format((float) $v, 2, ',', '.');
$fmtPersen = fn ($v) => ($v === null || $v === '') ? '-' : number_format((float) $v, 2, ',', '.') . '%';

$badgeOptimal = function ($v, $labelTidak) {
    $v = strtoupper(trim((string) $v));
    if ($v === '') return '<span class="badge gray">-</span>';
    return $v === 'OPTIMAL'
        ? '<span class="badge green">✅ Optimal</span>'
        : '<span class="badge orange">⚠️ ' . $labelTidak . '</span>';
};
$optimalHtml     = $badgeOptimal($r->pengiriman_optimal_pasuruan, 'Belum Optimal');
$optimalCustHtml = $badgeOptimal($r->pengiriman_optimal_customer_pasuruan, 'Tidak Optimal');

        // ===== tujuan (klik untuk lihat rincian DO) =====
        $tujuanHtml = trim((string) $r->no_shipment_pasuruan) !== ''
            ? '<a href="#" class="btn-tujuan" data-shipment="' . e($r->no_shipment_pasuruan)
                . '" data-tujuan="' . e($r->tujuan_pasuruan) . '">▶ ' . e($r->tujuan_pasuruan)
                . (($r->jml_do_grup ?? 1) > 1 ? ' <span class="badge gray">' . $r->jml_do_grup . ' DO</span>' : '')
                . '</a>'
            : e($r->tujuan_pasuruan);

        // ===== kategori ekspedisi =====
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

        // ===== lama pencarian & SLA dapat mobil =====
        $lamaPencarian = '-';
        $slaMobilHtml  = '<span class="badge gray">-</span>';

        if (!empty($r->rencana_kirim_pasuruan) && !empty($r->tanggal_dpt_unit_pasuruan)) {
            $rencana   = strtotime(date('Y-m-d', strtotime($r->rencana_kirim_pasuruan)));
            $dapatUnit = strtotime(date('Y-m-d', strtotime($r->tanggal_dpt_unit_pasuruan)));
            $selisih   = floor(($dapatUnit - $rencana) / 86400);

            $lamaPencarian = $selisih <= 0 ? 'H+0' : 'H+' . $selisih;
            $slaMobilHtml  = $selisih <= 0
                ? '<span class="badge green">On Time</span>'
                : '<span class="badge red">Delay</span>';
        }

        // ===== alert =====
        $alert = '-'; $alertClass = 'badge-secondary';
        if (!empty($r->tanggal_tiba_pasuruan)) {
            $alert = '✓ Tiba'; $alertClass = 'badge-success';
        } elseif (!empty($r->estimasi_tiba_pasuruan)) {
            $estimasi = strtotime(date('Y-m-d', strtotime($r->estimasi_tiba_pasuruan)));
            $sisaHari = floor(($estimasi - strtotime(date('Y-m-d'))) / 86400);

            if ($sisaHari < 0)      { $alert = 'OVERDUE'; $alertClass = 'badge-danger'; }
            elseif ($sisaHari <= 1) { $alert = 'H-' . $sisaHari; $alertClass = 'badge-danger'; }
            elseif ($sisaHari <= 3) { $alert = 'H-' . $sisaHari; $alertClass = 'badge-warning'; }
            elseif ($sisaHari <= 7) { $alert = 'H-' . $sisaHari; $alertClass = 'badge-info'; }
            else                    { $alert = 'ON TRACK'; $alertClass = 'badge-success'; }
        }
        $alertHtml = '<span class="badge ' . $alertClass . '">' . e($alert) . '</span>';

        // ===== selisih quantity =====
        $totalDo   = is_numeric($r->total_do_pasuruan) ? (float) $r->total_do_pasuruan : 0;
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

        // ===== SLA tiba (per tanggal) =====
        $slaTibaVal = '-';
        $slaTibaClass = 'gray';
        if (!empty($r->tanggal_tiba_pasuruan) && !empty($r->estimasi_tiba_pasuruan)) {
            $tibaDate     = strtotime(date('Y-m-d', strtotime($r->tanggal_tiba_pasuruan)));
            $estimasiDate = strtotime(date('Y-m-d', strtotime($r->estimasi_tiba_pasuruan)));

            $slaTibaVal   = ($tibaDate <= $estimasiDate) ? 'On Time' : 'Delay';
            $slaTibaClass = ($slaTibaVal == 'On Time') ? 'green' : 'red';
        }
        $slaTibaHtml = '<span class="badge ' . $slaTibaClass . '">' . e($slaTibaVal) . '</span>';

        // ===== status bongkar =====
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

        // ===== overstay & SLA bongkar =====
        $overstayHtml = '<span class="badge gray">-</span>';
        $slaBongkarComputedHtml = '<span class="badge gray">-</span>';
        if (!empty($r->tanggal_tiba_pasuruan) && !empty($r->tanggal_bongkar_pasuruan)) {
            $tiba    = strtotime(date('Y-m-d', strtotime($r->tanggal_tiba_pasuruan)));
            $bongkar = strtotime(date('Y-m-d', strtotime($r->tanggal_bongkar_pasuruan)));
            $selisih = floor(($bongkar - $tiba) / 86400);
            $overstay = max(0, $selisih);

            $overstayText = $overstay == 0 ? '0 Hari' : "H+{$overstay} Hari";
            $overstayHtml = '<span class="badge ' . ($overstay == 0 ? 'green' : 'red') . '">' . e($overstayText) . '</span>';

            $slaBongkarComputedHtml = $selisih <= 0
                ? '<span class="badge green">On Time</span>'
                : '<span class="badge red">Delay</span>';
        }

        // ===== status akhir & status alert =====
        $slaBongkarUp = strtoupper(trim($r->sla_bongkar_pasuruan ?? ''));
        $slaTibaUp    = strtoupper($slaTibaVal);

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
            $tujuanHtml,
            $r->area_pasuruan,
            $ketersediaanHtml,
            $r->mobil_pasuruan,
            $r->total_do_pasuruan,
            $fmtRupiah($r->nilai_muatan_pasuruan),
            $fmtRupiah($r->biaya_kirim_pasuruan),
               $crHtml,
        $crShipHtml,                 // BARU: kolom ke-16 (CR Shipment)
        $fmtDes($r->kubikasi_pasuruan),
$fmtDes($r->tonase_pasuruan),
$fmtDes($r->total_kubik_pasuruan),
$fmtDes($r->total_tonase_pasuruan),
$fmtPersen($r->hasil_kubik_pasuruan),
$fmtPersen($r->hasil_tonase_pasuruan),
$optimalHtml,
$r->reason_pengiriman_optimal_pasuruan ?: '-',
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
            $r->kirim_kontribusi_pasuruan !== null ? $fmtRupiah($r->kirim_kontribusi_pasuruan) : '-',
$optimalCustHtml,
$r->reason_pengiriman_optimal_customer_pasuruan ?: '-',
        ];
    }

    /* =====================================================================
     |  HITUNG SLA (dipakai gudangOntimePasuruan)
     * ===================================================================== */
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

        // 1. SLA dapat mobil
        $start = $request->rencana_kirim
            ? date('Y-m-d H:i:s', strtotime($request->rencana_kirim)) : null;
        $end = $request->tanggal_dpt_unit
            ? date('Y-m-d H:i:s', strtotime($request->tanggal_dpt_unit)) : null;

        $diff = $hitungSelisih($start, $end);
        $data['lama_waktu_pencarian'] = $diff['text'] ?? null;

        if ($start && $end) {
            $area = strtoupper(trim($request->area ?? ''));

            $tanggalRencana = strtotime(date('Y-m-d', strtotime($start)));
            $tanggalDptUnit = strtotime(date('Y-m-d', strtotime($end)));
            $selisihHari    = floor(($tanggalDptUnit - $tanggalRencana) / 86400);

            if ($area == 'JABODETABEK' || $area == 'JABODEBEK') {
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
        }

        // 2-4. Gudang 1..3
        foreach ([
            ['planning_loading',   'tanggal_tiba_gudang',   'lama_digudang',   'status_gudang',   'sla_loading'],
            ['planning_loading_2', 'tanggal_tiba_gudang_2', 'lama_digudang_2', 'status_gudang_2', 'sla_loading_2'],
            ['planning_loading_3', 'tanggal_tiba_gudang_3', 'lama_digudang_3', 'status_gudang_3', 'sla_loading_3'],
        ] as [$plan, $tiba, $kLama, $kStatus, $kSla]) {
            if ($request->$plan && $request->$tiba) {
                $d = $hitungSelisih($request->$plan, $request->$tiba);
                if ($d) {
                    $data[$kLama] = $d['text'];
                    if ($d['days'] > 0) {
                        $data[$kStatus] = 'Delay';
                        $data[$kSla]    = 'H+' . $d['days'];
                    } else {
                        $data[$kStatus] = 'On Time';
                        $data[$kSla]    = 'Sesuai SLA';
                    }
                }
            }
        }

        return $data;
    }

    /* =====================================================================
     |  REDIRECT
     * ===================================================================== */
    public function planner()
    {
        return redirect()->route('planner.dashboard');
    }

    public function monitoring()
    {
        return redirect()->route('monitoring.dashboard');
    }
}