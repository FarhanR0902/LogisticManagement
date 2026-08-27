<?php

namespace App\Http\Controllers\Spv;

use App\Http\Controllers\Controller;
use App\Models\LogistikPengiriman;
use App\Models\LogistikPengirimanPasuruan;
use Illuminate\Http\Request;
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
        $total_data = LogistikPengiriman::count();

        // =============================
        // SLA TIBA
        // =============================
        $total_tiba_ontime = LogistikPengiriman::where('sla_tiba', 'On Time')->count();

        $total_tiba_delay = LogistikPengiriman::where('sla_tiba', 'Delay')->count();

        // =============================
        // SLA BONGKAR
        // =============================
        $total_bongkar_ontime = LogistikPengiriman::where('sla_bongkar', 'On Time')->count();

        $total_bongkar_delay = LogistikPengiriman::where('sla_bongkar', 'Delay')->count();

        // =============================
        // STATUS AKHIR
        // =============================
        $total_ontime_total = LogistikPengiriman::where('status_akhir', 'On Time Total')->count();

        $total_delay_perjalanan = LogistikPengiriman::where('status_akhir', 'Delay Perjalanan')->count();

        $total_delay_pembongkaran = LogistikPengiriman::where('status_akhir', 'Delay Pembongkaran')->count();

        $total_delay_total = LogistikPengiriman::where('status_akhir', 'Delay Total')->count();

        // =============================
        // ALERT
        // =============================
        $delivered_ontime = LogistikPengiriman::where('monitoring_alert', 'Delivered On Time')->count();

        $delivered_delay = LogistikPengiriman::where('monitoring_alert', 'Delivered Delay')->count();

        // =============================
        // MASIH BELUM SELESAI
        // =============================
        $belum_tiba = LogistikPengiriman::whereNull('tanggal_tiba')->count();

        $belum_bongkar = LogistikPengiriman::whereNotNull('tanggal_tiba')
            ->whereNull('tanggal_bongkar')
            ->count();

        // =============================
        // SUMMARY AREA
        // =============================
        $summary_area = LogistikPengiriman::select(
                'area',
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('area')
            ->orderByDesc('total')
            ->get();

        return view('spvmonitoring.dashboard', compact(
            'total_data',

            'total_tiba_ontime',
            'total_tiba_delay',

            'total_bongkar_ontime',
            'total_bongkar_delay',

            'total_ontime_total',
            'total_delay_perjalanan',
            'total_delay_pembongkaran',
            'total_delay_total',

            'delivered_ontime',
            'delivered_delay',

            'belum_tiba',
            'belum_bongkar',

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

        // ================= LIST SHIPMENT (dropdown "No Shipment") =================
        $shipmentList = LogistikPengiriman::select('no_shipment', 'tujuan')
            ->whereNotNull('no_shipment')
            ->where('no_shipment', '!=', '')
            ->groupBy('no_shipment', 'tujuan')
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

        if ($request->date) {
            $query->whereDate('tanggal_terima_po_pasuruan', $request->date);
        }

        if ($request->month) {
            $query->whereMonth('tanggal_terima_po_pasuruan', substr($request->month, 5, 2));
            $query->whereYear('tanggal_terima_po_pasuruan', substr($request->month, 0, 4));
        }

        if ($request->year) {
            $query->whereYear('tanggal_terima_po_pasuruan', $request->year);
        }

        return $query;
    }

    public function dashboardPasuruan(Request $request)
    {
        $base = DB::table('logistik_pengiriman_pasuruan');

        $this->applyFilterPasuruan($base, $request);

        $total_data = (clone $base)->count();

        $gudang_ontime = (clone $base)
            ->whereNotNull('tanggal_tiba_gudang_pasuruan')
            ->count();

        $gudang_delay = (clone $base)
            ->where(function ($q) {
                $q->whereNull('rencana_kirim_pasuruan')
                  ->orWhere('rencana_kirim_pasuruan', '')
                  ->orWhereNull('tanggal_dpt_unit_pasuruan')
                  ->orWhere('tanggal_dpt_unit_pasuruan', '');
            })
            ->count();

        $customer_ontime = (clone $base)
            ->whereNotNull('tanggal_tiba_pasuruan')
            ->whereNotNull('estimasi_tiba_pasuruan')
            ->whereRaw("DATEDIFF(DATE(tanggal_tiba_pasuruan), DATE(estimasi_tiba_pasuruan)) <= 0")
            ->count();

        $customer_delay = (clone $base)
            ->whereNotNull('tanggal_tiba_pasuruan')
            ->whereNotNull('estimasi_tiba_pasuruan')
            ->whereRaw("DATEDIFF(DATE(tanggal_tiba_pasuruan), DATE(estimasi_tiba_pasuruan)) > 0")
            ->count();

        $bongkar_ontime = (clone $base)
            ->whereNotNull('tanggal_bongkar_pasuruan')
            ->where('tanggal_bongkar_pasuruan', '!=', '1899-12-31 00:00:00')
            ->where(function ($q) {
                $q->whereNull('overstay_days_pasuruan')
                  ->orWhere('overstay_days_pasuruan', 0);
            })
            ->count();

        $bongkar_delay = (clone $base)
            ->whereNotNull('tanggal_bongkar_pasuruan')
            ->where('tanggal_bongkar_pasuruan', '!=', '1899-12-31 00:00:00')
            ->where('overstay_days_pasuruan', '>', 0)
            ->count();

        $planner_armada = (clone $base)
            ->whereNotNull('rencana_kirim_pasuruan')
            ->whereRaw("TRIM(rencana_kirim_pasuruan) <> ''")
            ->whereNotNull('tanggal_dpt_unit_pasuruan')
            ->whereRaw("TRIM(tanggal_dpt_unit_pasuruan) <> ''")
            ->count();

        $planner_belum_armada = (clone $base)
            ->where(function ($q) {
                $q->whereNull('rencana_kirim_pasuruan')
                  ->orWhere('rencana_kirim_pasuruan', '')
                  ->orWhereNull('tanggal_dpt_unit_pasuruan')
                  ->orWhere('tanggal_dpt_unit_pasuruan', '');
            })
            ->count();

        $list_dist_channel = (clone $base)
            ->select('dist_channel_pasuruan')
            ->whereNotNull('dist_channel_pasuruan')
            ->distinct()
            ->orderBy('dist_channel_pasuruan')
            ->get();

        $planner_ontime = (clone $base)
            ->whereNotNull('rencana_kirim_pasuruan')
            ->whereNotNull('tanggal_dpt_unit_pasuruan')
            ->whereRaw('DATE(tanggal_dpt_unit_pasuruan) <= DATE(rencana_kirim_pasuruan)')
            ->count();

        $planner_delay = (clone $base)
            ->whereNotNull('rencana_kirim_pasuruan')
            ->whereNotNull('tanggal_dpt_unit_pasuruan')
            ->whereRaw('DATE(tanggal_dpt_unit_pasuruan) > DATE(rencana_kirim_pasuruan)')
            ->count();

        $totalNilaiMuatan = (clone $base)->sum('nilai_muatan_pasuruan');

        $totalBiayaKirim = (clone $base)
            ->selectRaw("SUM(biaya_kirim_pasuruan) as total")
            ->value('total');

        $summary_area = (clone $base)
            ->select(
                'area_pasuruan',
                DB::raw('COUNT(*) as total_shipment'),
                DB::raw('SUM(IFNULL(biaya_kirim_pasuruan, 0)) as total_biaya'),
                DB::raw('SUM(IFNULL(nilai_muatan_pasuruan, 0)) as total_muatan')
            )
            ->whereNotNull('area_pasuruan')
            ->groupBy('area_pasuruan')
            ->orderByDesc('total_shipment')
            ->get();

        $summary_tujuan = (clone $base)
            ->select(
                'tujuan_pasuruan',
                DB::raw('COUNT(*) as total_shipment'),
                DB::raw('SUM(IFNULL(biaya_kirim_pasuruan, 0)) as total_biaya'),
                DB::raw('SUM(IFNULL(nilai_muatan_pasuruan, 0)) as total_muatan')
            )
            ->whereNotNull('tujuan_pasuruan')
            ->groupBy('tujuan_pasuruan')
            ->orderByDesc('total_shipment')
            ->get();

        $ekspedisi = (clone $base)
            ->select('kategori_ekspedisi_pasuruan', DB::raw('COUNT(*) as total'))
            ->whereNotNull('kategori_ekspedisi_pasuruan')
            ->groupBy('kategori_ekspedisi_pasuruan')
            ->get();

        $label = $ekspedisi->pluck('kategori_ekspedisi_pasuruan');
        $value = $ekspedisi->pluck('total');

        $total_status = $planner_ontime + $planner_delay;
        $ontime_rate = $total_status > 0 ? ($planner_ontime / $total_status) * 100 : 0;
        $delay_rate  = $total_status > 0 ? ($planner_delay / $total_status) * 100 : 0;

        $total_armada = $planner_armada + $planner_belum_armada;
        $armada_rate  = $total_armada > 0 ? ($planner_armada / $total_armada) * 100 : 0;
        $pending_rate = $total_armada > 0 ? ($planner_belum_armada / $total_armada) * 100 : 0;

        $summary_monitoring = [
            'tiba_ontime'    => $total_data > 0 ? ($customer_ontime / $total_data) * 100 : 0,
            'tiba_delay'     => $total_data > 0 ? ($customer_delay / $total_data) * 100 : 0,
            'bongkar_ontime' => $total_data > 0 ? ($bongkar_ontime / $total_data) * 100 : 0,
            'bongkar_delay'  => $total_data > 0 ? ($bongkar_delay / $total_data) * 100 : 0,
        ];

        // pakai getArea() yang sudah ada (tabel logistik_pengiriman, dipakai sbg master area)
        $list_area = $this->getArea();

        return view('spvmonitoring.dashboard_pasuruan', compact(
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
            'armada_rate', 'pending_rate',
            'summary_monitoring', 'list_dist_channel', 'list_area'
        ));
    }

    public function dataLogistikPasuruan()
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

        return view('spvmonitoring.data_logistik_pasuruan', compact(
            'logistik',
            'planners',
            'areas'
        ));
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

        $overstay = ($tiba && $bongkar)
            ? max(0, floor(($bongkar - $tiba) / 86400))
            : null;

        $sla_bongkar = ($tiba && $bongkar)
            ? (($overstay <= 0) ? 'On Time' : 'Delay')
            : '-';

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
        $logistik->tanggal_bongkar = $request->tanggal_bongkar;

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
        $logistik->nama_kapal = $request->nama_kapal ?? 0;

        if ($logistik->nama_kapal == 1) {

            $logistik->nama_kapal = $request->nama_kapal;

            $logistik->etd = $request->etd;
            $logistik->eta = $request->eta;
            $logistik->atd = $request->atd;
            $logistik->ata = $request->ata;

        } else {

            $logistik->nama_kapal = null;
            $logistik->etd = null;
            $logistik->eta = null;
            $logistik->atd = null;
            $logistik->ata = null;
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
        return response()->json([
            'status' => 'success',
            'message' => 'Data transport laut berhasil diupdate'
        ]);
    }

    public function fullDashboard(Request $request)
    {

        // ================= BASE QUERY =================

        $base = DB::table('logistik_pengiriman');

        $this->applyFilter($base, $request);

        // ================= TOTAL =================

        $total_data = (clone $base)->count();

        // ================= GUDANG =================

        $gudang_ontime = (clone $base)
            ->where(function ($q) {
                $q->whereNotNull('tanggal_tiba_gudang')
                  ->orWhereNotNull('tanggal_tiba_gudang_2')
                  ->orWhereNotNull('tanggal_tiba_gudang_3');
            })
            ->count();

        $gudang_delay = (clone $base)
            ->where(function ($q) {
                $q->whereNull('rencana_kirim')
                  ->orWhere('rencana_kirim', '')
                  ->orWhereNull('tanggal_dpt_unit')
                  ->orWhere('tanggal_dpt_unit', '');
            })
            ->count();

        $gudang_unknown = (clone $base)
            ->where(function ($q) {
                $q->whereNull('sla_loading')
                  ->orWhereRaw("TRIM(sla_loading) = ''")
                  ->orWhereRaw("LOWER(TRIM(sla_loading)) NOT IN (
                      'h+0','h+1','h+2','h>2','on time','ontime','delay','critical delay'
                  )");
            })
            ->count();

        // ================= TUJUAN / CUSTOMER =================

        $customer_ontime = (clone $base)
            ->whereNotNull('tanggal_tiba')
            ->whereNotNull('estimasi_tiba')
            ->whereRaw("
                DATEDIFF(
                    DATE(tanggal_tiba),
                    DATE(estimasi_tiba)
                ) <= 0
            ")
            ->count();

        $customer_delay = (clone $base)
            ->whereNotNull('tanggal_tiba')
            ->whereNotNull('estimasi_tiba')
            ->whereRaw("
                DATEDIFF(
                    DATE(tanggal_tiba),
                    DATE(estimasi_tiba)
                ) > 0
            ")
            ->count();

        // ================= BONGKAR =================

        $bongkar_ontime = (clone $base)
            ->whereNotNull('tanggal_bongkar')
            ->where('tanggal_bongkar', '!=', '1899-12-31 00:00:00')
            ->where(function ($q) {
                $q->whereNull('overstay_days')
                  ->orWhere('overstay_days', 0);
            })
            ->count();

        $bongkar_delay = (clone $base)
            ->whereNotNull('tanggal_bongkar')
            ->where('tanggal_bongkar', '!=', '1899-12-31 00:00:00')
            ->where('overstay_days', '>', 0)
            ->count();

        // ================= ARMADA =================

        $planner_armada = (clone $base)
            ->whereNotNull('rencana_kirim')
            ->whereRaw("TRIM(rencana_kirim) <> ''")
            ->whereNotNull('tanggal_dpt_unit')
            ->whereRaw("TRIM(tanggal_dpt_unit) <> ''")
            ->count();

        $planner_belum_armada = (clone $base)
            ->where(function ($q) {
                $q->whereNull('rencana_kirim')
                  ->orWhere('rencana_kirim', '')
                  ->orWhereNull('tanggal_dpt_unit')
                  ->orWhere('tanggal_dpt_unit', '');
            })
            ->count();

        $list_dist_channel = (clone $base)
            ->select('dist_channel')
            ->whereNotNull('dist_channel')
            ->distinct()
            ->orderBy('dist_channel')
            ->get();

        // ================= PLANNER =================

        $planner_ontime = (clone $base)
            ->whereNotNull('rencana_kirim')
            ->whereNotNull('tanggal_dpt_unit')
            ->whereRaw('DATE(tanggal_dpt_unit) <= DATE(rencana_kirim)')
            ->count();

        $planner_delay = (clone $base)
            ->whereNotNull('rencana_kirim')
            ->whereNotNull('tanggal_dpt_unit')
            ->whereRaw('DATE(tanggal_dpt_unit) > DATE(rencana_kirim)')
            ->count();

        // ================= TOTAL NILAI MUATAN =================

        $totalNilaiMuatan = (clone $base)->sum('nilai_muatan');

        $totalBiayaKirim = (clone $base)
            ->selectRaw("SUM(biaya_kirim) as total")
            ->value('total');

        // ================= SUMMARY AREA =================

        $summary_area = (clone $base)
            ->select(
                'area',
                DB::raw('COUNT(*) as total_shipment'),
                DB::raw('COALESCE(SUM(biaya_kirim),0) as total_biaya'),
                DB::raw('COALESCE(SUM(nilai_muatan),0) as total_muatan')
            )
            ->whereNotNull('area')
            ->groupBy('area')
            ->orderByDesc('total_shipment')
            ->get();

        // ================= SUMMARY TUJUAN =================

        $summary_tujuan = (clone $base)
            ->select(
                'tujuan',
                DB::raw('COUNT(*) as total_shipment'),
                DB::raw('COALESCE(SUM(biaya_kirim),0) as total_biaya'),
                DB::raw('COALESCE(SUM(nilai_muatan),0) as total_muatan')
            )
            ->whereNotNull('tujuan')
            ->groupBy('tujuan')
            ->orderByDesc('total_shipment')
            ->get();

        // ================= SUMMARY PULAU =================

        $summary_pulau = DB::table('logistik_pengiriman')
            ->when($request->filled('bulan'), function ($q) use ($request) {
                $q->whereMonth('tanggal_naik_logistik', $request->bulan);
            })
            ->when($request->filled('tahun'), function ($q) use ($request) {
                $q->whereYear('tanggal_naik_logistik', $request->tahun);
            })
            ->when($request->filled('area'), function ($q) use ($request) {
                $q->where('area', $request->area);
            })
            ->when($request->filled('dist_channel'), function ($q) use ($request) {
                $q->where('dist_channel', $request->dist_channel);
            })
            ->select(
                'pulau',
                DB::raw('COUNT(DISTINCT no_shipment) AS total_shipment'),
                DB::raw('SUM(nilai_muatan) AS total_muatan'),
                DB::raw('SUM(biaya_kirim) AS total_biaya')
            )
            ->whereNotNull('pulau')
            ->whereRaw("TRIM(pulau) <> ''")
            ->groupBy('pulau')
            ->orderByDesc('total_muatan')
            ->get();

        $label_pulau        = $summary_pulau->pluck('pulau');
        $value_muatan_pulau = $summary_pulau->pluck('total_muatan');
        $value_biaya_pulau  = $summary_pulau->pluck('total_biaya');

        // ================= EKSPEDISI =================

        $ekspedisi = (clone $base)
            ->select(
                'kategori_ekspedisi',
                DB::raw('COUNT(*) as total')
            )
            ->whereNotNull('kategori_ekspedisi')
            ->groupBy('kategori_ekspedisi')
            ->get();

        $label = $ekspedisi->pluck('kategori_ekspedisi');
        $value = $ekspedisi->pluck('total');

        // ================= RATIO =================

        $total_status = $planner_ontime + $planner_delay;

        $ontime_rate = $total_status > 0
            ? ($planner_ontime / $total_status) * 100
            : 0;

        $delay_rate = $total_status > 0
            ? ($planner_delay / $total_status) * 100
            : 0;

        $total_armada = $planner_armada + $planner_belum_armada;

        $armada_rate = $total_armada > 0
            ? ($planner_armada / $total_armada) * 100
            : 0;

        $pending_rate = $total_armada > 0
            ? ($planner_belum_armada / $total_armada) * 100
            : 0;

        // ================= MONITORING =================

        $summary_monitoring = [
            'tiba_ontime' => $total_data > 0
                ? ($customer_ontime / $total_data) * 100
                : 0,

            'tiba_delay' => $total_data > 0
                ? ($customer_delay / $total_data) * 100
                : 0,

            'bongkar_ontime' => $total_data > 0
                ? ($bongkar_ontime / $total_data) * 100
                : 0,

            'bongkar_delay' => $total_data > 0
                ? ($bongkar_delay / $total_data) * 100
                : 0,
        ];

        // ================= LIST AREA =================

        $list_area = $this->getArea();

        // ================= RETURN =================

        return view('spvmonitoring.dashboard_full', compact(
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

            'ekspedisi',

            'label',
            'value',

            'planner_ontime',
            'planner_delay',

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

        // DATE

        if ($request->date) {
            $query->whereDate(
                'tanggal_naik_logistik',
                $request->date
            );
        }

        // MONTH

        if ($request->month) {
            $query->whereMonth(
                'tanggal_naik_logistik',
                substr($request->month, 5, 2)
            );

            $query->whereYear(
                'tanggal_naik_logistik',
                substr($request->month, 0, 4)
            );
        }

        // YEAR

        if ($request->year) {
            $query->whereYear(
                'tanggal_naik_logistik',
                $request->year
            );
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

    public function updateTransportLaut(Request $request)
    {
        $request->validate([
            'no_shipment' => 'required'
        ]);

        $data = [
            'nama_kapal' => $request->nama_kapal,
            'etd' => $request->etd,
            'eta' => $request->eta,
            'atd' => $request->atd,
            'ata' => $request->ata,
        ];

        \App\Models\LogistikPengiriman::where('no_shipment', $request->no_shipment)
            ->update($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Data transport laut berhasil diupdate'
        ]);
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

    public function bongkarDelay(Request $request)
    {
        $query = DB::table('logistik_pengiriman')
            ->where(function ($q) {
                $q->whereIn('sla_bongkar', ['Delay', 'Critical Delay'])
                  ->orWhere('overstay_days', '>', 0);
            })
            // 🚨 BUANG DATA RUSAK
            ->whereNotNull('tanggal_bongkar')
            ->where('tanggal_bongkar', '!=', '1899-12-31 00:00:00');

        if ($request->filled('tanggal_bongkar')) {
            $query->whereDate('tanggal_bongkar', $request->tanggal_bongkar);
        }

        if ($request->filled('area')) {
            $query->where('area', $request->area);
        }

        $list = $query->orderByDesc('tanggal_bongkar')->get();

        return view('spvmonitoring.bongkar_delay', compact('list'));
    }

    public function bongkarOntime(Request $request)
    {
        $query = DB::table('logistik_pengiriman')
            ->selectRaw("
                *,
                CASE
                    WHEN DATEDIFF(DATE(tanggal_bongkar), DATE(tanggal_tiba)) <= 0
                    THEN 'On Time'
                    ELSE 'Delay'
                END AS sla_bongkar
            ")
            ->whereNotNull('tanggal_bongkar')
            ->whereNotNull('tanggal_tiba')
            ->where('tanggal_bongkar', '!=', '1899-12-31 00:00:00')
            ->whereRaw("
                DATEDIFF(
                    DATE(tanggal_bongkar),
                    DATE(tanggal_tiba)
                ) <= 0
            ");

        if ($request->filled('tanggal_bongkar')) {
            $query->whereDate('tanggal_bongkar', $request->tanggal_bongkar);
        }

        if ($request->filled('area')) {
            $query->where('area', $request->area);
        }

        $list = $query
            ->orderByDesc('tanggal_bongkar')
            ->get();

        return view('spvmonitoring.bongkar_ontime', compact('list'));
    }

    // =====================================================
    // SLA ONTIME
    // =====================================================

    public function slaOntime(Request $request)
    {
        $query = DB::table('logistik_pengiriman')
            ->selectRaw("
                logistik_pengiriman.*,

                estimasi_tiba AS tanggal_estimasi,

                CASE
                    WHEN DATEDIFF(
                        DATE(tanggal_tiba),
                        DATE(estimasi_tiba)
                    ) <= 0
                    THEN 'On Time'
                    ELSE 'Delay'
                END AS sla_tiba
            ")
            ->whereNotNull('tanggal_tiba')
            ->whereNotNull('estimasi_tiba');

        $query->havingRaw("
            DATEDIFF(
                DATE(tanggal_tiba),
                DATE(estimasi_tiba)
            ) <= 0
        ");

        if ($request->filled('bulan')) {
            $query->whereMonth('tanggal_tiba', $request->bulan);
        }

        if ($request->filled('tahun')) {
            $query->whereYear('tanggal_tiba', $request->tahun);
        }

        $logistik = $query
            ->orderByDesc('tanggal_tiba')
            ->get();

        return view('spvmonitoring.sla_ontime', compact('logistik'));
    }

    // =====================================================
    // SLA DELAY
    // =====================================================

    public function slaDelay(Request $request)
    {
        $query = DB::table('logistik_pengiriman')
            ->selectRaw("
                logistik_pengiriman.*,

                estimasi_tiba AS tanggal_estimasi,

                CASE
                    WHEN DATEDIFF(
                        DATE(tanggal_tiba),
                        DATE(estimasi_tiba)
                    ) > 0
                    THEN 'Delay'
                    ELSE 'On Time'
                END AS sla_tiba
            ")
            ->whereNotNull('tanggal_tiba')
            ->whereNotNull('estimasi_tiba');

        // Hanya tampilkan yang Delay
        $query->havingRaw("
            DATEDIFF(
                DATE(tanggal_tiba),
                DATE(estimasi_tiba)
            ) > 0
        ");

        if ($request->filled('bulan')) {
            $query->whereMonth('tanggal_tiba', $request->bulan);
        }

        if ($request->filled('tahun')) {
            $query->whereYear('tanggal_tiba', $request->tahun);
        }

        $logistik = $query
            ->orderByDesc('tanggal_tiba')
            ->get();

        return view('spvmonitoring.sla_delay', compact('logistik'));
    }

    // =====================================================
    // SUMMARY AREA
    // =====================================================

    public function summaryArea()
    {
        $summary_area = DB::table('logistik_pengiriman')
            ->select(
                'area',
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('area')
            ->orderByDesc('total')
            ->get();

        return view(
            'spvmonitoring.summary_area',
            compact('summary_area')
        );
    }

    // =====================================================
    // SUMMARY AREA DETAIL
    // =====================================================

    public function summaryAreaDetail(Request $request)
    {
        $area = $request->area;

        $logistik = DB::table('logistik_pengiriman')
            ->where('area', $area)
            ->get();

        return view(
            'spvmonitoring.summary_area_detail',
            compact('logistik', 'area')
        );
    }
}