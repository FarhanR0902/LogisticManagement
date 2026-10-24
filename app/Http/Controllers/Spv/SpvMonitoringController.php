<?php

namespace App\Http\Controllers\Spv;

use App\Http\Controllers\Controller;
use App\Models\LogistikPengiriman;
use App\Models\LogistikPengirimanPasuruan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Exports\MonitoringExport;
use Maatwebsite\Excel\Facades\Excel;

class SpvMonitoringController extends Controller
{

    // =====================================================
    // DASHBOARD
    // =====================================================

    public function export(Request $request)
    {
        return Excel::download(
            new MonitoringExport(
                $request->pic_monitoring,
                $request->area
            ),
            'Monitoring_Logistik.xlsx'
        );
    }

  public function dashboard()
{
    $total_data = $this->countGroups(LogistikPengiriman::query());

    $total_in_transit = $this->countGroups($this->inTransitQuery());

    $total_tiba_ontime = $this->countGroups(LogistikPengiriman::where('sla_tiba', 'On Time'));
    $total_tiba_delay  = $this->countGroups(LogistikPengiriman::where('sla_tiba', 'Delay'));

    $total_bongkar_ontime = $this->countGroups(LogistikPengiriman::where('sla_bongkar', 'On Time'));
    $total_bongkar_delay  = $this->countGroups(LogistikPengiriman::where('sla_bongkar', 'Delay'));

    $total_ontime_total       = $this->countGroups(LogistikPengiriman::where('status_akhir', 'On Time Total'));
    $total_delay_perjalanan   = $this->countGroups(LogistikPengiriman::where('status_akhir', 'Delay Perjalanan'));
    $total_delay_pembongkaran = $this->countGroups(LogistikPengiriman::where('status_akhir', 'Delay Pembongkaran'));
    $total_delay_total        = $this->countGroups(LogistikPengiriman::where('status_akhir', 'Delay Total'));

    $delivered_ontime = $this->countGroups(LogistikPengiriman::where('monitoring_alert', 'Delivered On Time'));
    $delivered_delay  = $this->countGroups(LogistikPengiriman::where('monitoring_alert', 'Delivered Delay'));

    $belum_tiba    = $this->countGroups(LogistikPengiriman::whereNull('tanggal_tiba'));
    $belum_bongkar = $this->countGroups(
        LogistikPengiriman::whereNotNull('tanggal_tiba')->whereNull('tanggal_bongkar')
    );

    $summary_area = DB::table('logistik_pengiriman')
        ->whereIn('id', $this->groupIdsQuery(DB::table('logistik_pengiriman')))
        ->select('area', DB::raw('COUNT(*) as total'))
        ->groupBy('area')
        ->orderByDesc('total')
        ->get();

    return view('spvmonitoring.dashboard', compact(
        'total_data',
        'total_tiba_ontime', 'total_tiba_delay',
        'total_bongkar_ontime', 'total_bongkar_delay',
        'total_ontime_total', 'total_delay_perjalanan',
        'total_delay_pembongkaran', 'total_delay_total',
        'delivered_ontime', 'delivered_delay',
        'belum_tiba', 'belum_bongkar', 'total_in_transit',
        'summary_area'
    ));
}

    private function getKeluarGudangInfo($r)
    {
        $cycles = [
            ['planning' => $r->planning_loading,   'tiba' => $r->tanggal_tiba_gudang,   'keluar' => $r->tanggal_keluar_gudang],
            ['planning' => $r->planning_loading_2, 'tiba' => $r->tanggal_tiba_gudang_2, 'keluar' => $r->tanggal_keluar_gudang_2],
            ['planning' => $r->planning_loading_3, 'tiba' => $r->tanggal_tiba_gudang_3, 'keluar' => $r->tanggal_keluar_gudang_3],
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

    public function dataLogistik(Request $request)
    {
        $query = LogistikPengiriman::query();

        // ================= FILTER JENIS =================
        if ($request->filled('jenis')) {
            $query->where('transportasi', strtoupper($request->jenis));
        }

        // ================= FILTER AREA =================
        if ($request->filled('area')) {
            $query->where('area', $request->area);
        }

        // ================= FILTER PIC =================
        if ($request->filled('pic_monitoring')) {
            $query->where('pic_monitoring', $request->pic_monitoring);
        }

        // ================= FILTER BULAN =================
        if ($request->filled('bulan')) {
            $query->whereRaw("
                MONTH(
                    GREATEST(
                        COALESCE(tanggal_keluar_gudang,'1900-01-01'),
                        COALESCE(tanggal_keluar_gudang_2,'1900-01-01'),
                        COALESCE(tanggal_keluar_gudang_3,'1900-01-01')
                    )
                ) = ?
            ", [$request->bulan]);
        }

        // ================= FILTER TAHUN =================
        if ($request->filled('tahun')) {
            $query->whereRaw("
                YEAR(
                    GREATEST(
                        COALESCE(tanggal_keluar_gudang,'1900-01-01'),
                        COALESCE(tanggal_keluar_gudang_2,'1900-01-01'),
                        COALESCE(tanggal_keluar_gudang_3,'1900-01-01')
                    )
                ) = ?
            ", [$request->tahun]);
        }

        // ================= AMBIL DATA =================
        $logistik = $query
            ->orderBy('no_shipment', 'ASC')
            ->orderBy('act_urutan_bongkar', 'ASC')
            ->get();

        // =====================================================
        // HITUNG ESTIMASI BERDASARKAN URUTAN DUPLIKAT SHIPMENT
        // =====================================================

        $grouped = $logistik->groupBy('no_shipment');

        foreach ($grouped as $shipment => $items) {

            $gudangInfo = $this->getKeluarGudangInfo($items->first());
            $keluar  = $gudangInfo['keluar'];
            $blocked = $gudangInfo['blocked'];

            $leadtime = (int) ($items->first()->transport_lead_time ?? 0);

            $estimasi = (!$blocked && $keluar)
                ? strtotime("+{$leadtime} days", $keluar)
                : null;

            // assign ke semua row dalam shipment
            foreach ($items as $r) {
                $r->tanggal_estimasi = $r->estimasi_tiba
                    ? strtotime($r->estimasi_tiba)
                    : $estimasi;

                // flag buat blade — dipakai untuk sembunyikan estimasi/alert
                $r->gudang_blocked = $blocked;
            }
        }

        // ================= LIST AREA =================
        $areaList = LogistikPengiriman::whereNotNull('area')
            ->distinct()
            ->orderBy('area')
            ->pluck('area');

        // ================= LIST PIC =================
        $picList = LogistikPengiriman::whereNotNull('pic_monitoring')
            ->distinct()
            ->orderBy('pic_monitoring')
            ->pluck('pic_monitoring');
            // ================= LIST DIST CHANNEL =================
$distChannelList = LogistikPengiriman::whereNotNull('dist_channel')
    ->where('dist_channel', '!=', '')
    ->distinct()
    ->orderBy('dist_channel')
    ->pluck('dist_channel');

        // ================= LIST SHIPMENT (dropdown "No Shipment") =================
    $shipmentList = LogistikPengiriman::selectRaw('
        no_shipment,
        MAX(tujuan) as tujuan,
        MAX(nama_kapal) as nama_kapal,
        MAX(etd) as etd,
        MAX(eta) as eta,
        MAX(atd) as atd,
        MAX(ata) as ata
    ')
    ->whereNotNull('no_shipment')
    ->where('no_shipment', '!=', '')
    ->groupBy('no_shipment')
    ->orderBy('no_shipment')
    ->get();

        // ================= AKURASI =================
      $akurasiTiba = DB::table('akurasi3')
    ->distinct()
    ->pluck('akurasi_waktu_tiba');

$akurasiBongkar = DB::table('akurasi3')
    ->distinct()
    ->pluck('akurasi_waktu_bongkar');

$akurasiQty = DB::table('akurasi3')
    ->distinct()
    ->pluck('remarks_qty');

return view('monitoring.data_monitoring', compact(
    'logistik',
    'areaList',
    'akurasiTiba',
    'akurasiBongkar',
    'akurasiQty',
        'distChannelList',   
    'picList',
    'shipmentList'
));
    }

    private const PULAU_MAP = [
        'JAWA'       => ['JABODEBEK','BANTEN','JAWA_BARAT','JAWA_TENGAH','JAWA_TIMUR','YOGYAKARTA'],
        'SUMATERA'   => ['ACEH','SUMATERA_UTARA','SUMATERA_BARAT','RIAU','KEP._RIAU','JAMBI','SUMATERA_SELATAN','BENGKULU','LAMPUNG','KEP._BANGKA_BELITUNG'],
        'KALIMANTAN' => ['KALIMANTAN_BARAT','KALIMANTAN_TENGAH','KALIMANTAN_SELATAN','KALIMANTAN_TIMUR','KALIMANTAN_UTARA'],
        'SULAWESI'   => ['SULAWESI_UTARA','SULAWESI_TENGAH','SULAWESI_SELATAN','SULAWESI_TENGGARA','SULAWESI_BARAT','GORONTALO'],
        'BALI_NUSRA' => ['PROV._BALI','NUSA_TENGGARA_BARAT','NUSA_TENGGARA_TIMUR'],
        'MALUKU'     => ['PROV._MALUKU','PROV._MALUKU_UTARA'],
        'PAPUA'      => ['PROV._PAPUA','PAPUA_BARAT','PAPUA_BARAT_DAYA','PAPUA_SELATAN','PAPUA_TENGAH'],
    ];

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

    // ManagerController, SpvPlannerController, SpvMonitoringController (applyFilterPasuruan)
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

 public function dashboardPasuruan(Request $request)
{
    $base = DB::table('logistik_pengiriman_pasuruan');
    $this->applyFilterPasuruan($base, $request);

    $grouped = DB::table('logistik_pengiriman_pasuruan')
        ->whereIn('id', $this->groupIdsQueryPasuruan($base));

    $total_data = $this->countGroupsPasuruan($base);

    // ================= KPI (query sama dengan halaman list) =================
    $kpi = fn (string $k) => $this->countGroupsPasuruan(
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

    return view('spvmonitoring.dashboard_pasuruan', compact(
        'total_data', 'kpiLinks',
        'gudang_ontime', 'gudang_delay',
        'customer_ontime', 'customer_delay',
        'bongkar_ontime', 'bongkar_delay',
        'summary_area', 'summary_tujuan',
        'totalNilaiMuatan', 'totalBiayaKirim',
        'ekspedisi', 'label', 'value',
        'planner_ontime', 'planner_delay',
        'planner_armada', 'planner_belum_armada',
        'total_in_transit',
        'ontime_rate', 'delay_rate',
        'armada_rate', 'pending_rate',
        'summary_monitoring', 'list_dist_channel', 'list_area'
    ));
}
    private function kpiLinksPasuruan(Request $request): array
{
    // hanya filter yang benar-benar terisi
    $filter = array_filter(
        $request->only(['tgl_basis', 'date', 'month', 'year', 'area', 'dist_channel', 'pulau']),
        fn ($v) => $v !== null && $v !== ''
    );

    // halaman list KPI dilayani PasuruanController (route pasuruan.*), filter-nya
    // tidak mengenal tgl_basis, jadi dibuang dari link ke sana
    $kpiFilter = array_diff_key($filter, ['tgl_basis' => true]);

    return [
        'total'           => route('spvmonitoring.data.pasuruan', $filter),
        'in_transit'      => route('spvmonitoring.intransit.pasuruan', $filter),
        'gudang_ontime'   => route('pasuruan.gudang.ontime', $kpiFilter),
        'gudang_delay'    => route('pasuruan.gudang.delay', $kpiFilter),
        'customer_ontime' => route('pasuruan.tujuan.ontime', $kpiFilter),
        'customer_delay'  => route('pasuruan.tujuan.delay', $kpiFilter),
        'bongkar_ontime'  => route('pasuruan.bongkar.ontime', $kpiFilter),
        'bongkar_delay'   => route('pasuruan.bongkar.delay', $kpiFilter),
    ];
}

    public function dataLogistikPasuruan()
    {
        $planners = $this->cachedListPasuruan('planner_pasuruan');
        $areas    = $this->cachedListPasuruan('area_pasuruan');

        // 'logistik' sengaja tidak di-query lagi: tabel diisi via AJAX
        return view('spvmonitoring.data_logistik_pasuruan', compact('planners', 'areas'));
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

    private function cachedListPasuruan(string $column)
    {
        return Cache::remember("spv_pasuruan_list_{$column}", 3600, function () use ($column) {
            return DB::table('logistik_pengiriman_pasuruan')
                ->select($column)
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->distinct()
                ->orderBy($column)
                ->pluck($column);
        });
    }

    /** Filter dashboard -> halaman Data Logistik (AJAX). Ambil dari dash[...] atau query string halaman (Referer). */
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


    /** Query dasar tiap KPI. Dipakai kartu dashboard DAN halaman list, supaya angkanya selalu sama. */
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

private function dateFilterColumn($request): string
{
    return match ($request->input('tgl_basis')) {
        'create_tgl'    => 'create_tgl',
        'rencana_kirim' => 'rencana_kirim',
        default         => 'tanggal_naik_logistik',
    };
}
   private function belumTibaGudangQuery()
{
    return DB::table('logistik_pengiriman')
        // sudah terinput
        ->whereNotNull('tanggal_naik_logistik')
        ->whereNotNull('rencana_kirim')
        ->whereRaw("TRIM(rencana_kirim) <> ''")
        ->whereNotNull('tanggal_dpt_unit')
        ->whereRaw("TRIM(tanggal_dpt_unit) <> ''")
        // ketiga tanggal tiba gudang harus kosong semua
        ->where(fn ($q) => $q->whereNull('tanggal_tiba_gudang')->orWhereRaw("TRIM(tanggal_tiba_gudang) = ''"))
        ->where(fn ($q) => $q->whereNull('tanggal_tiba_gudang_2')->orWhereRaw("TRIM(tanggal_tiba_gudang_2) = ''"))
        ->where(fn ($q) => $q->whereNull('tanggal_tiba_gudang_3')->orWhereRaw("TRIM(tanggal_tiba_gudang_3) = ''"));
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

    return view('spvmonitoring.belum_tiba_gudang', [
        'title'     => 'BELUM TIBA DI GUDANG',
        'list'      => $list,
        'list_area' => $this->getArea(),
    ]);
}

public function bongkarDelay(Request $request)
{
    $filter = DB::table('logistik_pengiriman')
        ->where(function ($q) {
            $q->whereIn('sla_bongkar', ['Delay', 'Critical Delay'])
              ->orWhere('overstay_days', '>', 0);
        })
        ->whereNotNull('tanggal_bongkar')
        ->where('tanggal_bongkar', '!=', '1899-12-31 00:00:00');

    if ($request->filled('tanggal_bongkar')) $filter->whereDate('tanggal_bongkar', $request->tanggal_bongkar);
    if ($request->filled('area'))            $filter->where('area', $request->area);

    $list = DB::table('logistik_pengiriman')
        ->whereIn('id', $this->groupIdsQuery($filter))
        ->orderByDesc('tanggal_bongkar')
        ->orderBy('no_shipment')
        ->orderBy('tujuan')
        ->get();

    return view('spvmonitoring.bongkar_delay', compact('list'));
}

public function bongkarOntime(Request $request)
{
    $filter = DB::table('logistik_pengiriman')
        ->whereNotNull('tanggal_bongkar')
        ->whereNotNull('tanggal_tiba')
        ->where('tanggal_bongkar', '!=', '1899-12-31 00:00:00')
        ->whereRaw("DATEDIFF(DATE(tanggal_bongkar), DATE(tanggal_tiba)) <= 0");

    if ($request->filled('tanggal_bongkar')) $filter->whereDate('tanggal_bongkar', $request->tanggal_bongkar);
    if ($request->filled('area'))            $filter->where('area', $request->area);

    $list = DB::table('logistik_pengiriman')
        ->selectRaw("logistik_pengiriman.*, 'On Time' AS sla_bongkar")
        ->whereIn('id', $this->groupIdsQuery($filter))
        ->orderByDesc('tanggal_bongkar')
        ->orderBy('no_shipment')
        ->orderBy('tujuan')
        ->get();

    return view('spvmonitoring.bongkar_ontime', compact('list'));
}

public function slaOntime(Request $request)
{
    $filter = DB::table('logistik_pengiriman')
        ->whereNotNull('tanggal_tiba')
        ->whereNotNull('estimasi_tiba')
        ->whereRaw("DATEDIFF(DATE(tanggal_tiba), DATE(estimasi_tiba)) <= 0");

    if ($request->filled('bulan')) $filter->whereMonth('tanggal_tiba', $request->bulan);
    if ($request->filled('tahun')) $filter->whereYear('tanggal_tiba', $request->tahun);

    $logistik = DB::table('logistik_pengiriman')
        ->selectRaw("logistik_pengiriman.*, estimasi_tiba AS tanggal_estimasi, 'On Time' AS sla_tiba")
        ->whereIn('id', $this->groupIdsQuery($filter))
        ->orderByDesc('tanggal_tiba')
        ->orderBy('no_shipment')
        ->orderBy('tujuan')
        ->get();

    return view('spvmonitoring.sla_ontime', compact('logistik'));
}

public function slaDelay(Request $request)
{
    $filter = DB::table('logistik_pengiriman')
        ->whereNotNull('tanggal_tiba')
        ->whereNotNull('estimasi_tiba')
        ->whereRaw("DATEDIFF(DATE(tanggal_tiba), DATE(estimasi_tiba)) > 0");

    if ($request->filled('bulan')) $filter->whereMonth('tanggal_tiba', $request->bulan);
    if ($request->filled('tahun')) $filter->whereYear('tanggal_tiba', $request->tahun);

    $logistik = DB::table('logistik_pengiriman')
        ->selectRaw("logistik_pengiriman.*, estimasi_tiba AS tanggal_estimasi, 'Delay' AS sla_tiba")
        ->whereIn('id', $this->groupIdsQuery($filter))
        ->orderByDesc('tanggal_tiba')
        ->orderBy('no_shipment')
        ->orderBy('tujuan')
        ->get();

    return view('spvmonitoring.sla_delay', compact('logistik'));
}

public function summaryArea()
{
    $summary_area = DB::table('logistik_pengiriman')
        ->whereIn('id', $this->groupIdsQuery(DB::table('logistik_pengiriman')))
        ->select('area', DB::raw('COUNT(*) as total'))
        ->groupBy('area')
        ->orderByDesc('total')
        ->get();

    return view('spvmonitoring.summary_area', compact('summary_area'));
}

public function summaryAreaDetail(Request $request)
{
    $area   = $request->area;
    $filter = DB::table('logistik_pengiriman')->where('area', $area);

    $logistik = DB::table('logistik_pengiriman')
        ->whereIn('id', $this->groupIdsQuery($filter))
        ->orderBy('no_shipment')
        ->orderBy('tujuan')
        ->get();

    return view('spvmonitoring.summary_area_detail', compact('logistik', 'area'));
}
    public function updateMonitoring(Request $request, $id)
    {
        $logistik = LogistikPengiriman::findOrFail($id);
        $oldTanggalTiba = $logistik->tanggal_tiba;

        $gudangInfo = $this->getKeluarGudangInfo($logistik);
        $keluar  = $gudangInfo['keluar'];
        $blocked = $gudangInfo['blocked'];

        $tiba = $request->tanggal_tiba
            ? strtotime(date('Y-m-d', strtotime($request->tanggal_tiba)))
            : null;

        $bongkar = $request->tanggal_bongkar
            ? strtotime(date('Y-m-d', strtotime($request->tanggal_bongkar)))
            : null;

        $leadtime = (int)($logistik->transport_lead_time ?? 0);

        $estimasi = $logistik->estimasi_tiba
            ? strtotime($logistik->estimasi_tiba)
            : (
                (!$blocked && $keluar)
                    ? strtotime("+{$leadtime} days", $keluar)
                    : null
            );

        $lama_perjalanan = ($keluar && $tiba)
            ? max(0, floor(($tiba - $keluar) / 86400))
            : null;

        $sla_tiba = ($tiba && $estimasi)
            ? (($tiba <= $estimasi) ? 'On Time' : 'Delay')
            : '-';

      $tibaDT = ($request->filled('tanggal_tiba') && $request->filled('waktu_tiba'))
    ? strtotime(date('Y-m-d', strtotime($request->tanggal_tiba)) . ' ' . $request->waktu_tiba)
    : null;

$bongkarDT = ($request->filled('tanggal_bongkar') && $request->filled('waktu_bongkar'))
    ? strtotime(date('Y-m-d', strtotime($request->tanggal_bongkar)) . ' ' . $request->waktu_bongkar)
    : null;

$hasil       = $this->hitungOverstay($tiba, $bongkar, $tibaDT, $bongkarDT);
$overstay    = $hasil['overstay'];
$sla_bongkar = $hasil['sla'];
        // =========================
        // AUTO STATUS + ALERT
        // =========================
        $logic = $this->generateStatusAlert($sla_tiba, $sla_bongkar);

        $logistik->status_akhir = $logic['status_akhir'];
        $logistik->monitoring_alert = $logic['alert'];

        $logistik->sla_tiba = $sla_tiba;
        $logistik->sla_bongkar = $sla_bongkar;

        if (empty($logistik->estimasi_tiba)) {

            if (!$logistik->tanggal_bongkar && empty($logistik->estimasi_tiba)) {

                $logistik->estimasi_tiba = (!$blocked && $estimasi)
                    ? date('Y-m-d', $estimasi)
                    : null;
            }
        }

        $logistik->reason_tiba    = $request->reason_tiba;
        $logistik->reason_bongkar = $request->reason_bongkar;

        // =========================
        // FIELD UPDATE
        // =========================
        $logistik->pic_monitoring   = $request->pic_monitoring;
        $logistik->status_kendaraan = $request->status_kendaraan;
        $logistik->action_required  = $request->action_required;

        $logistik->act_urutan_bongkar = $request->act_urutan_bongkar;

      $logistik->tanggal_tiba    = $request->tanggal_tiba;
$logistik->waktu_tiba      = ($request->filled('tanggal_tiba') && $request->filled('waktu_tiba'))
    ? $request->waktu_tiba
    : null;

$logistik->tanggal_bongkar = $request->tanggal_bongkar;
$logistik->waktu_bongkar   = ($request->filled('tanggal_bongkar') && $request->filled('waktu_bongkar'))
    ? $request->waktu_bongkar
    : null;
        $logistik->overstay_days   = $overstay;
        $logistik->lama_perjalanan = $lama_perjalanan;

        $logistik->reason_tiba    = $request->reason_tiba;
        $logistik->reason_bongkar = $request->reason_bongkar;

        $logistik->remarks        = $request->remarks;

        $logistik->act_pgi_date      = $request->input('act_pgi_date');
        $logistik->created_by        = $request->input('created_by');
        $logistik->total_do_qty_car  = $request->input('total_do_qty_car');

        // =========================
        // TRANSPORT LAUT (NEW)
        // =========================
        // $logistik->nama_kapal = $request->nama_kapal ?? 0;

        // if ($logistik->nama_kapal == 1) {

        //     $logistik->nama_kapal = $request->nama_kapal;

        //     $logistik->etd = $request->etd;
        //     $logistik->eta = $request->eta;
        //     $logistik->atd = $request->atd;
        //     $logistik->ata = $request->ata;

        // } else {

        //     $logistik->nama_kapal = null;
        //     $logistik->etd = null;
        //     $logistik->eta = null;
        //     $logistik->atd = null;
        //     $logistik->ata = null;
        // }
        // =========================
// TRANSPORT LAUT — field independen, tidak saling menghapus
// =========================
if ($request->filled('nama_kapal')) {
    $logistik->nama_kapal = $request->nama_kapal;
}
if ($request->filled('etd')) {
    $logistik->etd = $request->etd;
}
if ($request->filled('eta')) {
    $logistik->eta = $request->eta;
}
if ($request->filled('atd')) {
    $logistik->atd = $request->atd;
}
if ($request->filled('ata')) {
    $logistik->ata = $request->ata;
}
        $logistik->save();

        $shipment = LogistikPengiriman::where(
            'no_shipment',
            $logistik->no_shipment
        )->get();

        $baseEstimasi = (!$blocked && $keluar)
            ? strtotime("+{$leadtime} days", $keluar)
            : null;

        // cari tanggal bongkar terakhir
        $lastBongkar = $shipment
            ->whereNotNull('tanggal_bongkar')
            ->max('tanggal_bongkar');

        $nextEstimasi = $lastBongkar
            ? date('Y-m-d', strtotime($lastBongkar . ' +1 day'))
            : ($baseEstimasi ? date('Y-m-d', $baseEstimasi) : null);

        // update hanya yang BELUM TIBA
        foreach ($shipment as $item) {

            // sudah pernah tiba = estimasi dikunci
            if (!empty($item->tanggal_tiba)) {
                continue;
            }

            $item->estimasi_tiba = $nextEstimasi;
            $item->save();
        }
$fresh = LogistikPengiriman::find($id);

return response()->json([
    'status'          => 'success',
    'message'         => 'Data transport laut berhasil diupdate',
    'lama_perjalanan' => $fresh->lama_perjalanan,
    'sla_tiba'        => $fresh->sla_tiba,
    'overstay_days'   => $fresh->overstay_days,
    'sla_bongkar'     => $fresh->sla_bongkar,
]);
    }

     public function fullDashboard(Request $request)
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
            ->where(fn ($q) => $q->whereNull('overstay_days')->orWhere('overstay_days', 0))
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

    // ================= NILAI MUATAN & BIAYA (pakai $base: semua DO) =================
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

    $top_ekspedisi_pemakaian = $ekspedisi->take(5)->map(fn ($e) => (object) [
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
        ->when($request->filled('bulan'), fn ($q) => $q->whereMonth('tanggal_naik_logistik', $request->bulan))
        ->when($request->filled('tahun'), fn ($q) => $q->whereYear('tanggal_naik_logistik', $request->tahun))
        ->when($request->filled('area'), fn ($q) => $q->where('area', $request->area))
        ->when($request->filled('dist_channel'), fn ($q) => $q->where('dist_channel', $request->dist_channel));

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

    return view('spvmonitoring.dashboard_full', compact(
        'total_data', 'gudang_ontime', 'gudang_delay',
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
    private function applyFilter($query, $request)
    {
        // AREA

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

/** per grup: biaya = MAX, nilai = SUM (dipakai total nilai muatan, biaya, summary) */
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

// ---- versi Pasuruan ----
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
    private function getArea()
    {
        return DB::table('logistik_pengiriman')
            ->select('area')
            ->whereNotNull('area')
            ->groupBy('area')
            ->orderBy('area')
            ->get();
    }

    // public function updateTransportLaut(Request $request)
    // {
    //     $request->validate([
    //         'no_shipment' => 'required'
    //     ]);

    //     $data = [
    //         'nama_kapal' => $request->nama_kapal,
    //         'etd' => $request->etd,
    //         'eta' => $request->eta,
    //         'atd' => $request->atd,
    //         'ata' => $request->ata,
    //     ];

    //     \App\Models\LogistikPengiriman::where('no_shipment', $request->no_shipment)
    //         ->update($data);

    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'Data transport laut berhasil diupdate'
    //     ]);
    // }

    public function updateTransportLaut(Request $request)
{
    $request->validate([
        'no_shipment' => 'required'
    ]);

    $data = array_filter([
        'nama_kapal' => $request->nama_kapal,
        'etd' => $request->etd,
        'eta' => $request->eta,
        'atd' => $request->atd,
        'ata' => $request->ata,
    ], fn($v) => $v !== null && $v !== '');

    if (empty($data)) {
        return response()->json([
            'status' => 'error',
            'message' => 'Tidak ada data yang diisi',
        ], 422);
    }

    \App\Models\LogistikPengiriman::where('no_shipment', $request->no_shipment)
        ->update($data);

    return response()->json([
        'status' => 'success',
        'message' => 'Data transport laut berhasil diupdate'
    ]);
}

private function hitungOverstay(?int $tiba, ?int $bongkar, ?int $tibaDT, ?int $bongkarDT): array
{
    if (!$tiba || !$bongkar) {
        return ['overstay' => null, 'sla' => '-'];
    }

    if ($tibaDT && $bongkarDT) {
        // jam lengkap: <= 24 jam On Time, lebih dari itu Delay
        $selisihJam = ($bongkarDT - $tibaDT) / 3600;
        $overstay   = $selisihJam <= 24 ? 0 : (int) floor($selisihJam / 24);
    } else {
        // jam belum lengkap: beda 1 tanggal belum bisa dipastikan Delay
        $selisihHari = (int) floor(($bongkar - $tiba) / 86400);
        $overstay    = $selisihHari <= 1 ? 0 : $selisihHari;
    }

    return [
        'overstay' => $overstay,
        'sla'      => $overstay <= 0 ? 'On Time' : 'Delay',
    ];
}


    private function generateStatusAlert($sla_tiba, $sla_bongkar)
    {
        $sla_tiba = strtolower(trim($sla_tiba ?? '-'));
        $sla_bongkar = strtolower(trim($sla_bongkar ?? '-'));

        // Belum lengkap
        if ($sla_tiba == '-' || $sla_bongkar == '-') {
            return [
                'status_akhir' => '-',
                'alert' => '-'
            ];
        }

        // ON TIME + ON TIME
        if ($sla_tiba == 'on time' && $sla_bongkar == 'on time') {
            return [
                'status_akhir' => 'On Time Total',
                'alert' => 'Delivered On Time'
            ];
        }

        // DELAY PERJALANAN
        if ($sla_tiba == 'delay' && $sla_bongkar == 'on time') {
            return [
                'status_akhir' => 'Delay Perjalanan',
                'alert' => 'Delay Perjalanan'
            ];
        }

        // DELAY PEMBONGKARAN
        if ($sla_tiba == 'on time' && $sla_bongkar == 'delay') {
            return [
                'status_akhir' => 'Delay Pembongkaran',
                'alert' => 'Delay Pembongkaran'
            ];
        }

        // DELAY TOTAL
        return [
            'status_akhir' => 'Delay Total',
            'alert' => 'Delivered Delay'
        ];
    }

    // =====================================================
// IN TRANSIT: sudah keluar gudang (max), belum ada tanggal_tiba
// =====================================================
private function inTransitQuery()
{
    $filled = fn($c) => "NULLIF(TRIM({$c}), '') IS NOT NULL";
    $empty  = fn($c) => "NULLIF(TRIM({$c}), '') IS NULL";

    $q = DB::table('logistik_pengiriman')
        ->whereRaw($empty('tanggal_tiba'));

    // minimal 1 gudang sudah keluar
    $q->whereRaw('(' . implode(' OR ', [
        $filled('tanggal_keluar_gudang'),
        $filled('tanggal_keluar_gudang_2'),
        $filled('tanggal_keluar_gudang_3'),
    ]) . ')');

    // tidak boleh ada siklus gudang yang masih menggantung
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

    if ($request->filled('area'))           $base->where('area', $request->input('area'));
    if ($request->filled('pic_monitoring')) $base->where('pic_monitoring', $request->input('pic_monitoring'));
    if ($request->filled('gudang_asal')) {
        $base->whereRaw('(' . $this->inTransitAsalSql() . ') = ?', [$request->input('gudang_asal')]);
    }
    if ($request->filled('q')) {
        $this->applyInTransitSearch($base, $request->input('q'));
    }

    // 1 baris wakil per shipment + tujuan
    $grouped = DB::table('logistik_pengiriman')
        ->whereIn('id', $this->groupIdsQuery($base));

    // ===== ringkasan (per grup) =====
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

    // ===== data tabel (per grup) =====
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

    $areaList = LogistikPengiriman::whereNotNull('area')->distinct()->orderBy('area')->pluck('area');
    $picList  = LogistikPengiriman::whereNotNull('pic_monitoring')->distinct()->orderBy('pic_monitoring')->pluck('pic_monitoring');

    $formRoute = route('spvmonitoring.intransit');

    return view('monitoring.in_transit', compact('list', 'summary', 'areaList', 'picList', 'formRoute'));
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

// =====================================================
// IN TRANSIT (PASURUAN): sudah keluar gudang, belum ada tanggal_tiba
// =====================================================
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

public function inTransitPasuruan(Request $request)
{
    $today   = date('Y-m-d');
    $todayTs = strtotime($today);
    $soon    = date('Y-m-d', strtotime('+3 days'));
    $est     = $this->inTransitEstimasiSqlPasuruan();

    $base = $this->inTransitQueryPasuruan();

    // pakai filter terpusat yang sama dengan dashboardPasuruan()
    // (area, pulau, dist_channel, date/month/year)
    $this->applyFilterPasuruan($base, $request);

    if ($request->filled('pic_monitoring')) {
        $base->where('pic_monitoring_pasuruan', $request->input('pic_monitoring'));
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

    

    // ===== ringkasan =====
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

    // ===== data tabel =====
  $list = (clone $grouped)
    ->orderByRaw("DATE({$est}) ASC")
    ->orderBy('no_shipment_pasuruan')
    ->orderBy('tujuan_pasuruan')
    ->paginate(50)
    ->withQueryString();

    // mapping nama kolom Pasuruan -> nama properti generik yang dipakai
    // view monitoring/in_transit.blade.php (biar view-nya bisa dipakai ulang)
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

    $formRoute = route('spvmonitoring.intransit.pasuruan');

    return view('monitoring.in_transit', compact('list', 'summary', 'areaList', 'picList', 'formRoute'));
}
 
  
    // =====================================================
    // SUMMARY AREA
    // =====================================================

   
}