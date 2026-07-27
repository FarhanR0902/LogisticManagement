<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LogistikPengirimanPasuruan;

use App\Models\LogistikPengiriman;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\PasuruanImport;
use App\Exports\PasuruanExport;
class PasuruanController extends Controller
{


    /**
     * Kolom yang boleh diisi lewat form Add / Update.
     * Dipakai bareng buat store() & update() biar konsisten.
     */
   private array $fillableFields = [
    'planner_pasuruan',
    'no_shipment_pasuruan',
    'tanggal_terima_po_pasuruan',
    'rencana_kirim_pasuruan',
    'transport_lead_time_pasuruan',

    'tujuan_pasuruan',
    'area_pasuruan',
    'pulau_pasuruan',

    'ketersediaan_unit_pasuruan',
    'mobil_pasuruan',
    'perubahan_mobil_pasuruan',

    'kategori_pengiriman_pasuruan',

    'nilai_muatan_pasuruan',
    'biaya_kirim_pasuruan',
    // 'cr_pasuruan',

    'kategori_ekspedisi_pasuruan',
    'ekspedisi_pasuruan',

    'no_pol_pasuruan',
    'nama_driver_pasuruan',

    'tanggal_dpt_unit_pasuruan',
    'planning_loading_pasuruan',
    'tanggal_tiba_gudang_pasuruan',
    'tanggal_keluar_gudang_pasuruan',

    'lama_digudang_pasuruan',
    'sla_ketepatan_loading_pasuruan',
    'keterangan_loading_pasuruan',
    'keterangan_loading2_pasuruan',

    'lama_waktu_pencarian_pasuruan',
    'sla_dapat_mobil_pasuruan',

    'sla_ketibaan_gudang_muat_pasuruan',
    'keterangan_ketibaan_gudang_pasuruan',

    'pic_monitoring_pasuruan',
    'status_kendaraan_pasuruan',
    'monitoring_alert_pasuruan',
    'action_required_pasuruan',

    'tanggal_tiba_pasuruan',
    'lama_perjalanan_pasuruan',
    'sla_tiba_pasuruan',
    'keterangan_waktu_tiba_pasuruan',

    'tanggal_bongkar_pasuruan',
    'overstay_days_pasuruan',
    'sla_bongkar_pasuruan',

    'keterangan_waktu_bongkar_pasuruan',
    'reason_waktu_tiba_pasuruan',
    'reason_waktu_bongkar_pasuruan',
    'remarks_pasuruan',

    'keterangan_monitoring_pasuruan',
    'route_pasuruan',
    'via_kirim_pasuruan',
    'ekpedisi_pasuruan',
    'total_do_pasuruan',
    'dist_channel_pasuruan',
     'pic_monitoring_pasuruan',
        'status_kendaraan_pasuruan',
        'monitoring_alert_pasuruan',
        'action_required_pasuruan',
        'act_urutan_bongkar_pasuruan',
        'tanggal_tiba_estimasi_pasuruan',
        'tanggal_tiba_pasuruan',
        'lama_perjalanan_pasuruan',
        'sla_tiba_pasuruan',
        'tanggal_bongkar_pasuruan',
        'overstay_days_pasuruan',
        'sla_bongkar_pasuruan',
        'reason_waktu_tiba_pasuruan',
        'reason_waktu_bongkar_pasuruan',
        'status_akhir_pasuruan',
        'remarks_pasuruan',
        'act_pgi_date_pasuruan',
        'total_do_pasuruan',
    'nama_kapal_pasuruan',
    'etd_pasuruan',
    'eta_pasuruan',
    'atd_pasuruan',
    'ata_pasuruan',
    'actual_delivery_quantity_pasuruan',
    'selisih_quantity_pasuruan',
   'reason_selisih_quantity_pasuruan',
    'act_urutan_bongkar_pasuruan',
    'transport_laut_pasuruan',
    'route_pasuruan',
    'pulau_pasuruan',
    'via_kirim_pasuruan',
    'estimasi_tiba_pasuruan',
    'shipping_point_pasuruan',
        'created_by_pasuruan',
        'qty_monitoring_pasuruan',
        'remarks_qty_pasuruan',
        'selisih_qty_pasuruan',
        'create_tgl_pasuruan',
         'act_pgi_date_pasuruan'

    
];

private function generateMonitoringPasuruan(array &$data)
{
    $keluar = !empty($data['tanggal_keluar_gudang_pasuruan'])
        ? strtotime($data['tanggal_keluar_gudang_pasuruan'])
        : null;

    $tiba = !empty($data['tanggal_tiba_pasuruan'])
        ? strtotime($data['tanggal_tiba_pasuruan'])
        : null;

    $bongkar = !empty($data['tanggal_bongkar_pasuruan'])
        ? strtotime($data['tanggal_bongkar_pasuruan'])
        : null;

    $leadtime = isset($data['transport_lead_time_pasuruan'])
        ? (int)$data['transport_lead_time_pasuruan']
        : 0;

    /*
    |--------------------------------------------------------------------------
    | Estimasi Tiba
    |--------------------------------------------------------------------------
    */

  $estimasi = !empty($data['estimasi_tiba_pasuruan'])
    ? strtotime($data['estimasi_tiba_pasuruan'])
    : null;

if (!$estimasi && $keluar && $leadtime > 0) {
    $estimasi = strtotime("+{$leadtime} days", $keluar);
}

    /*
    |--------------------------------------------------------------------------
    | Lama Perjalanan
    |--------------------------------------------------------------------------
    */

    if ($keluar && $tiba) {
        $lama = ceil(($tiba - $keluar) / 86400);

        $data['lama_perjalanan_pasuruan'] = max(0, $lama);
    }

    /*
    |--------------------------------------------------------------------------
    | SLA Tiba
    |--------------------------------------------------------------------------
    */

    if ($tiba && $estimasi) {

        if ($tiba <= $estimasi) {
            $data['sla_tiba_pasuruan'] = 'On Time';
        } else {
            $data['sla_tiba_pasuruan'] = 'Delay';
        }

    }

    /*
    |--------------------------------------------------------------------------
    | Overstay
    |--------------------------------------------------------------------------
    */

    if ($tiba && $bongkar) {

        $overstay = ceil(($bongkar - $tiba) / 86400);

        $data['overstay_days_pasuruan'] = max(0, $overstay);

        $data['sla_bongkar_pasuruan'] =
            $overstay <= 0 ? 'On Time' : 'Delay';

    }

    /*
    |--------------------------------------------------------------------------
    | Alert Monitoring
    |--------------------------------------------------------------------------
    */

    if ($bongkar) {

        $data['monitoring_alert_pasuruan'] = 'SELESAI';

    } elseif ($tiba) {

        $data['monitoring_alert_pasuruan'] = 'TIBA DI TUJUAN';

    } elseif ($estimasi) {

        $today = strtotime(date('Y-m-d'));

        $selisih = ceil(($estimasi - $today) / 86400);

        if ($selisih < 0) {

            $data['monitoring_alert_pasuruan'] = 'TERLAMBAT';

        } elseif ($selisih <= 2) {

            $data['monitoring_alert_pasuruan'] = 'WARNING H-2';

        } else {

            $data['monitoring_alert_pasuruan'] = 'AMAN';

        }

    }

    /*
    |--------------------------------------------------------------------------
    | Action Required
    |--------------------------------------------------------------------------
    */

    switch ($data['monitoring_alert_pasuruan'] ?? '') {

        case 'TERLAMBAT':
            $data['action_required_pasuruan'] = 'Follow Up Driver';
            break;

        case 'WARNING H-2':
            $data['action_required_pasuruan'] = 'Monitoring';
            break;

        case 'TIBA DI TUJUAN':
            $data['action_required_pasuruan'] = 'Menunggu Bongkar';
            break;

        case 'SELESAI':
            $data['action_required_pasuruan'] = 'Closed';
            break;

        default:
            $data['action_required_pasuruan'] = '-';
            break;
    }
}

public function updateTransportLaut(Request $request)
{
    LogistikPengirimanPasuruan::where(
        'no_shipment_pasuruan',
        $request->no_shipment_pasuruan
    )->update([

        'nama_kapal_pasuruan' => $request->nama_kapal_pasuruan,
        'etd_pasuruan'         => $request->etd_pasuruan,
        'eta_pasuruan'         => $request->eta_pasuruan,
        'atd_pasuruan'         => $request->atd_pasuruan,
        'ata_pasuruan'         => $request->ata_pasuruan,

    ]);

    return back()->with('success','Transport Laut berhasil diupdate.');
}

public function export(Request $request)
{
   return Excel::download(
    new PasuruanExport(),
    'Data_Logistik_Pasuruan.xlsx'
);
}


public function dashboard(Request $request)
{

    // ================= BASE QUERY =================

    $base = DB::table('logistik_pengiriman_pasuruan');

    $this->applyFilter($base, $request);

    // ================= TOTAL =================

    $total_data = (clone $base)->count();

    // ================= GUDANG =================

    $gudang_ontime = (clone $base)
        ->where(function ($q) {
            $q->whereNotNull('tanggal_tiba_gudang_pasuruan');
        })
        ->count();

    $gudang_delay = (clone $base)
        ->where(function ($q) {
            $q->whereNull('rencana_kirim_pasuruan')
              ->orWhere('rencana_kirim_pasuruan', '')
              ->orWhereNull('tanggal_dpt_unit_pasuruan')
              ->orWhere('tanggal_dpt_unit_pasuruan', '');
        })
        ->count();

    // FIXED: 'sla_loading_pasuruan' tidak ada di skema DB.
    // Diganti ke kolom terdekat 'sla_ketepatan_loading_pasuruan'.
    // Silakan koreksi jika maksudnya kolom lain (mis. sla_dapat_mobil_pasuruan).
    $gudang_unknown = (clone $base)
        ->where(function ($q) {
            $q->whereNull('sla_ketepatan_loading_pasuruan')
              ->orWhereRaw("TRIM(sla_ketepatan_loading_pasuruan) = ''")
              ->orWhereRaw("LOWER(TRIM(sla_ketepatan_loading_pasuruan)) NOT IN (
                  'h+0','h+1','h+2','h>2','on time','ontime','delay','critical delay'
              )");
        })
        ->count();


    // ================= TUJUAN / CUSTOMER =================

    $customer_ontime = (clone $base)
        ->whereNotNull('tanggal_tiba_pasuruan')
        ->whereNotNull('estimasi_tiba_pasuruan')
        ->whereRaw("
            DATEDIFF(
                DATE(tanggal_tiba_pasuruan),
                DATE(estimasi_tiba_pasuruan)
            ) <= 0
        ")
        ->count();

    $customer_delay = (clone $base)
        ->whereNotNull('tanggal_tiba_pasuruan')
        ->whereNotNull('estimasi_tiba_pasuruan')
        ->whereRaw("
            DATEDIFF(
                DATE(tanggal_tiba_pasuruan),
                DATE(estimasi_tiba_pasuruan)
            ) > 0
        ")
        ->count();


    // ================= BONGKAR =================

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


    // ================= ARMADA =================
    // FIXED: rencana_kirim -> rencana_kirim_pasuruan, tanggal_dpt_unit -> tanggal_dpt_unit_pasuruan

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

    // FIXED: dist_channel -> dist_channel_pasuruan
    $list_dist_channel = (clone $base)
        ->select('dist_channel_pasuruan')
        ->whereNotNull('dist_channel_pasuruan')
        ->distinct()
        ->orderBy('dist_channel_pasuruan')
        ->get();


    // ================= PLANNER =================
    // FIXED: rencana_kirim -> rencana_kirim_pasuruan, tanggal_dpt_unit -> tanggal_dpt_unit_pasuruan

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

    // ================= TOTAL NILAI MUATAN =================
    // FIXED: nilai_muatan -> nilai_muatan_pasuruan, biaya_kirim -> biaya_kirim_pasuruan

    $totalNilaiMuatan = (clone $base)->sum('nilai_muatan_pasuruan');

    $totalBiayaKirim = (clone $base)
        ->selectRaw("SUM(biaya_kirim_pasuruan) as total")
        ->value('total');


    // ================= SUMMARY AREA =================
    // FIXED: area -> area_pasuruan, biaya_kirim -> biaya_kirim_pasuruan, nilai_muatan -> nilai_muatan_pasuruan
    // Catatan: kolom biaya_kirim_pasuruan & nilai_muatan_pasuruan sudah bertipe decimal(18,2),
    // jadi REPLACE(...,',','') tidak lagi diperlukan kecuali datanya memang disimpan sebagai string berformat ribuan.

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


    // ================= SUMMARY TUJUAN =================
    // FIXED: tujuan -> tujuan_pasuruan, biaya_kirim -> biaya_kirim_pasuruan, nilai_muatan -> nilai_muatan_pasuruan

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


    // ================= EKSPEDISI =================
    // FIXED: kategori_ekspedisi -> kategori_ekspedisi_pasuruan

    $ekspedisi = (clone $base)
        ->select(
            'kategori_ekspedisi_pasuruan',
            DB::raw('COUNT(*) as total')
        )
        ->whereNotNull('kategori_ekspedisi_pasuruan')
        ->groupBy('kategori_ekspedisi_pasuruan')
        ->get();

    $label = $ekspedisi->pluck('kategori_ekspedisi_pasuruan');
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

    return view('pasuruan.dashboard', compact(
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
        'list_area'
    ));
}


private function applyFilter($query, $request)
{

    // AREA
    // FIXED: area -> area_pasuruan
    if ($request->area) {
        $query->where('area_pasuruan', $request->area);
    }

    // DIST CHANNEL
    // FIXED: dist_channel -> dist_channel_pasuruan
    if ($request->dist_channel) {
        $query->where('dist_channel_pasuruan', $request->dist_channel);
    }

    // DATE
    // ⚠️ PERHATIAN: kolom 'tanggal_naik_logistik' TIDAK ADA di skema tabel
    // logistik_pengiriman_pasuruan yang Anda berikan. Sementara diganti ke
    // 'tanggal_terima_po_pasuruan'. Ganti sesuai kolom tanggal yang benar
    // (misalnya planning_loading_pasuruan atau tanggal_dpt_unit_pasuruan)
    // jika asumsi ini salah.
    if ($request->date) {
        $query->whereDate(
            'tanggal_terima_po_pasuruan',
            $request->date
        );
    }

    // MONTH
    if ($request->month) {
        $query->whereMonth(
            'tanggal_terima_po_pasuruan',
            substr($request->month, 5, 2)
        );

        $query->whereYear(
            'tanggal_terima_po_pasuruan',
            substr($request->month, 0, 4)
        );
    }

    // YEAR
    if ($request->year) {
        $query->whereYear(
            'tanggal_terima_po_pasuruan',
            $request->year
        );
    }

    return $query;
}

// Catatan: getArea() sengaja TIDAK diubah karena mengambil dari tabel
// 'logistik_pengiriman' (tanpa suffix _pasuruan) yang tampaknya memang
// tabel terpisah/master area, bukan tabel logistik_pengiriman_pasuruan.
private function getArea()
{
    return DB::table('logistik_pengiriman')
        ->select('area')
        ->whereNotNull('area')
        ->groupBy('area')
        ->orderBy('area')
        ->get();
}
public function import(Request $request)
{
    $request->validate([
        'file' => 'required|mimes:xlsx,xls,csv',
    ]);

    Excel::import(new PasuruanImport, $request->file('file'));

    return redirect()
        ->route('pasuruan.dataLogistik')
        ->with('success', 'Data Pasuruan berhasil diimport.');
}
public function archiveAll()
{
    LogistikPengirimanPasuruan::truncate();

    return redirect()
        ->route('pasuruan.dataLogistik')
        ->with('success', 'Semua data Pasuruan berhasil dihapus.');
}

public function dataLogistik()
{
    $logistik = LogistikPengirimanPasuruan::orderByDesc('id')->get();

    $planners = LogistikPengirimanPasuruan::whereNotNull('planner_pasuruan')
        ->distinct()
        ->pluck('planner_pasuruan');

    $areas = LogistikPengirimanPasuruan::whereNotNull('area_pasuruan')
        ->distinct()
        ->pluck('area_pasuruan');

    return view('pasuruan.data_logistik', compact(
        'logistik',
        'planners',
        'areas'
    ));
}
    public function index()
    {
        $logistik = LogistikPengirimanPasuruan::orderBy('id', 'desc')->get();

        return view('pasuruan.index', compact('logistik'));
    }

    public function admin(Request $request)
    {
        $logistik = LogistikPengirimanPasuruan::orderBy('id', 'desc')->get();

 $reasonTiba = DB::table('akurasi3')
    ->whereNotNull('akurasi_waktu_tiba')
    ->where('akurasi_waktu_tiba', '<>', '')
    ->distinct()
    ->orderBy('akurasi_waktu_tiba')
    ->pluck('akurasi_waktu_tiba');

$reasonBongkar = DB::table('akurasi3')
    ->whereNotNull('akurasi_waktu_bongkar')
    ->where('akurasi_waktu_bongkar', '<>', '')
    ->distinct()
    ->orderBy('akurasi_waktu_bongkar')
    ->pluck('akurasi_waktu_bongkar');

        $planners = LogistikPengirimanPasuruan::whereNotNull('planner_pasuruan')
            ->distinct()
            ->orderBy('planner_pasuruan')
            ->pluck('planner_pasuruan');

        $areas = LogistikPengirimanPasuruan::whereNotNull('area_pasuruan')
            ->distinct()
            ->orderBy('area_pasuruan')
            ->pluck('area_pasuruan');

        $tujuans = LogistikPengirimanPasuruan::whereNotNull('tujuan_pasuruan')
            ->distinct()
            ->orderBy('tujuan_pasuruan')
            ->pluck('tujuan_pasuruan');

        return view('pasuruan.data_admin', compact(
            'logistik',
            'reasonTiba',
            'reasonBongkar',
            'planners',
            'areas',
            'tujuans'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'planner_pasuruan'              => 'nullable|string|max:100',
            'no_shipment_pasuruan'          => 'nullable|string|max:50',
            'dist_channel_pasuruan'         => 'nullable|string|max:100',
            'transport_lead_time_pasuruan'  => 'nullable|numeric',
            'tujuan_pasuruan'               => 'nullable|string|max:150',
            'area_pasuruan'                 => 'nullable|string|max:100',
            'mobil_pasuruan'                => 'nullable|string|max:100',
            'nilai_muatan_pasuruan'         => 'nullable|string',
            'biaya_kirim_pasuruan'          => 'nullable|string',
            'kategori_ekspedisi_pasuruan'   => 'nullable|string|max:100',
            'ekspedisi_pasuruan'             => 'nullable|string|max:100',
            'tanggal_terima_po_pasuruan' => 'nullable|date',
            'rencana_kirim_pasuruan'        => 'nullable|date',
            'tanggal_dpt_unit_pasuruan'     => 'nullable|date',
            
            'tanggal_tiba_gudang_pasuruan'  => 'nullable|date',
            'planning_loading_pasuruan'     => 'nullable|date',
            'tanggal_keluar_gudang_pasuruan' => 'nullable|date',
            'keterangan_pasuruan'           => 'nullable|string',
            'create_tgl'                    => 'nullable|date',
        ]);

        // Bersihkan format "Rp 1.000.000" jadi angka murni sebelum disimpan
        $nilaiMuatan = $this->parseRupiah($validated['nilai_muatan_pasuruan'] ?? null);
        $biayaKirim  = $this->parseRupiah($validated['biaya_kirim_pasuruan'] ?? null);

        $validated['nilai_muatan_pasuruan'] = $nilaiMuatan;
        $validated['biaya_kirim_pasuruan']  = $biayaKirim;
        $validated['cr_pasuruan']           = $this->hitungCr($nilaiMuatan, $biayaKirim);

        LogistikPengirimanPasuruan::create($validated);

        return redirect()
            ->route('pasuruan.admin')
            ->with('success', 'Data berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $logistik = LogistikPengirimanPasuruan::findOrFail($id);

        return view('pasuruan.edit', compact('logistik'));
    }

    public function update(Request $request, $id)
    {
        $logistik = LogistikPengirimanPasuruan::findOrFail($id);

        // Ambil cuma field yang memang ada di request & termasuk kolom yang diizinkan
        $data = $request->only($this->fillableFields);
        // Hitung selisih quantity
$totalDo = $request->filled('total_do_pasuruan')
    ? (float) $request->total_do_pasuruan
    : (float) $logistik->total_do_pasuruan;

$actualQty = $request->filled('actual_delivery_quantity_pasuruan')
    ? (float) $request->actual_delivery_quantity_pasuruan
    : (float) $logistik->actual_delivery_quantity_pasuruan;

// Kalau actual belum diisi jangan hitung
if ($request->filled('actual_delivery_quantity_pasuruan')) {

    $data['selisih_quantity_pasuruan'] = $totalDo - $actualQty;

} else {

    $data['selisih_quantity_pasuruan'] = null;

}

        if (array_key_exists('nilai_muatan_pasuruan', $data)) {
            $data['nilai_muatan_pasuruan'] = $this->parseRupiah($data['nilai_muatan_pasuruan']);
        }

        if (array_key_exists('biaya_kirim_pasuruan', $data)) {
            $data['biaya_kirim_pasuruan'] = $this->parseRupiah($data['biaya_kirim_pasuruan']);
        }

        // Hitung ulang CR di server pakai nilai final (baru atau lama)
       $shipmentNo = $logistik->no_shipment_pasuruan;

// total semua nilai muatan untuk shipment ini
$totalMuatan = LogistikPengirimanPasuruan::where(
    'no_shipment_pasuruan',
    $shipmentNo
)->sum('nilai_muatan_pasuruan');

// ambil biaya kirim yang terisi
$biayaKirim = LogistikPengirimanPasuruan::where(
    'no_shipment_pasuruan',
    $shipmentNo
)->whereNotNull('biaya_kirim_pasuruan')
 ->where('biaya_kirim_pasuruan', '>', 0)
 ->value('biaya_kirim_pasuruan');

// kalau user sedang mengubah biaya kirim
if (isset($data['biaya_kirim_pasuruan'])) {
    $biayaKirim = $data['biaya_kirim_pasuruan'];
}

// kalau user sedang mengubah nilai muatan
if (isset($data['nilai_muatan_pasuruan'])) {

    $lama = (float)$logistik->nilai_muatan_pasuruan;

    $baru = (float)$data['nilai_muatan_pasuruan'];

    $totalMuatan = $totalMuatan - $lama + $baru;
}

$cr = $this->hitungCr($totalMuatan, $biayaKirim);
$data['cr_pasuruan'] = $cr;
        $totalDo = $data['total_do_pasuruan']
    ?? $logistik->total_do_pasuruan;

$actualQty = $data['actual_delivery_quantity_pasuruan']
    ?? $logistik->actual_delivery_quantity_pasuruan;

if ($actualQty !== null && $actualQty !== '') {

    $data['selisih_quantity_pasuruan'] =
        (float)$totalDo - (float)$actualQty;

} else {

    $data['selisih_quantity_pasuruan'] = null;

}

        // ============================================================
// HITUNG SELISIH QTY
// ============================================================

$totalDo = $data['total_do_pasuruan']
    ?? $logistik->total_do_pasuruan;

$actualQty = $data['actual_delivery_quantity_pasuruan']
    ?? $logistik->actual_delivery_quantity_pasuruan;

if (
    $totalDo !== null && $totalDo !== '' &&
    $actualQty !== null && $actualQty !== ''
) {

    $data['selisih_quantity_pasuruan'] =
        (float)$totalDo - (float)$actualQty;

} else {

    $data['selisih_quantity_pasuruan'] = null;

}

$this->generateMonitoringPasuruan($data);
/* ============================================================
| FIELD YANG HARUS SAMA UNTUK SEMUA NO SHIPMENT
============================================================ */

$shipmentFields = [
    'planner_pasuruan',
    'tanggal_terima_po_pasuruan',
    'rencana_kirim_pasuruan',
    'transport_lead_time_pasuruan',
    'route_pasuruan',
    'pulau_pasuruan',
    'area_pasuruan',
    'via_kirim_pasuruan',
    'pic_monitoring_pasuruan',

    'ketersediaan_unit_pasuruan',
    'mobil_pasuruan',
    'perubahan_mobil_pasuruan',

    'kategori_pengiriman_pasuruan',
    'kategori_ekspedisi_pasuruan',
    'ekspedisi_pasuruan',

    'no_pol_pasuruan',
    'nama_driver_pasuruan',

    'tanggal_dpt_unit_pasuruan',
    'planning_loading_pasuruan',
    'tanggal_tiba_gudang_pasuruan',
    'tanggal_keluar_gudang_pasuruan',

    'nama_kapal_pasuruan',
    'etd_pasuruan',
    'eta_pasuruan',
    'atd_pasuruan',
    'ata_pasuruan',

];

/*
| Ambil hanya field yang ada di atas
*/

$shipmentData = array_intersect_key(
    $data,
    array_flip($shipmentFields)
);

/*
| Kalau ada perubahan, update semua row dengan shipment yang sama
*/

if (!empty($shipmentData)) {

    LogistikPengirimanPasuruan::where(
        'no_shipment_pasuruan',
        $logistik->no_shipment_pasuruan
    )->update($shipmentData);

}


        $logistik->update($data);
        LogistikPengirimanPasuruan::where(
    'no_shipment_pasuruan',
    $shipmentNo
)->update([
    'cr_pasuruan' => $cr
]);

     $shipment = LogistikPengirimanPasuruan::where(
    'no_shipment_pasuruan',
    $logistik->no_shipment_pasuruan
)->get();

// estimasi awal
$keluar = optional($shipment->first())->tanggal_keluar_gudang_pasuruan;
$leadtime = (int) optional($shipment->first())->transport_lead_time_pasuruan;

$baseEstimasi = $keluar
    ? date('Y-m-d', strtotime($keluar." +{$leadtime} days"))
    : null;

// cari tanggal bongkar TERAKHIR yang sudah ada
$lastBongkar = $shipment
    ->whereNotNull('tanggal_bongkar_pasuruan')
    ->max('tanggal_bongkar_pasuruan');

$nextEstimasi = $lastBongkar
    ? date('Y-m-d', strtotime($lastBongkar.' +1 day'))
    : $baseEstimasi;

// update semua yang BELUM bongkar
foreach ($shipment as $row) {

    if (!empty($row->tanggal_bongkar_pasuruan)) {
        continue; // yang sudah bongkar dikunci
    }

    $row->estimasi_tiba_pasuruan = $nextEstimasi;
    $row->save();
}
        // Kalau dipanggil lewat AJAX (autosave form-update-{id}), balikin JSON
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'message' => 'Data berhasil diupdate.',
                'cr'      => $data['cr_pasuruan'],
            ]);
        }

        return redirect()->route('pasuruan.admin')
                         ->with('success', 'Data berhasil diupdate.');
    }

    /**
     * Endpoint untuk auto-save per baris (dipanggil dari JS saveRow()
     * ke URL /planner/autosave-row/{id}). Route ini sebelumnya belum
     * ada di controller, jadi request dari JS selalu gagal / 404.
     */
    public function autosaveRow(Request $request, $id)
    {
        $logistik = LogistikPengirimanPasuruan::findOrFail($id);

        $data = $request->only($this->fillableFields);
        $totalDo = $request->filled('total_do_pasuruan')
    ? (float) $request->total_do_pasuruan
    : (float) $logistik->total_do_pasuruan;

$actualQty = $request->filled('actual_delivery_quantity_pasuruan')
    ? (float) $request->actual_delivery_quantity_pasuruan
    : (float) $logistik->actual_delivery_quantity_pasuruan;

if ($request->filled('actual_delivery_quantity_pasuruan')) {

    $data['selisih_quantity_pasuruan'] = $totalDo - $actualQty;

} else {

    $data['selisih_quantity_pasuruan'] = null;

}

        if (array_key_exists('nilai_muatan_pasuruan', $data)) {
            $data['nilai_muatan_pasuruan'] = $this->parseRupiah($data['nilai_muatan_pasuruan']);
        }

        if (array_key_exists('biaya_kirim_pasuruan', $data)) {
            $data['biaya_kirim_pasuruan'] = $this->parseRupiah($data['biaya_kirim_pasuruan']);
        }

        $nilaiMuatan = $data['nilai_muatan_pasuruan'] ?? $logistik->nilai_muatan_pasuruan;
        $biayaKirim  = $data['biaya_kirim_pasuruan'] ?? $logistik->biaya_kirim_pasuruan;
        $data['cr_pasuruan'] = $this->hitungCr($nilaiMuatan, $biayaKirim);
        $totalDo = $data['total_do_pasuruan']
    ?? $logistik->total_do_pasuruan;

$actualQty = $data['actual_delivery_quantity_pasuruan']
    ?? $logistik->actual_delivery_quantity_pasuruan;

if ($actualQty !== null && $actualQty !== '') {

    $data['selisih_quantity_pasuruan'] =
        (float)$totalDo - (float)$actualQty;

} else {

    $data['selisih_quantity_pasuruan'] = null;

}

        /* ============================================================
| MONITORING PASURUAN
============================================================ */

// Ambil nilai terbaru (request kalau ada, kalau tidak pakai database)
$keluar = !empty($data['tanggal_keluar_gudang_pasuruan'])
    ? strtotime($data['tanggal_keluar_gudang_pasuruan'])
    : (!empty($logistik->tanggal_keluar_gudang_pasuruan)
        ? strtotime($logistik->tanggal_keluar_gudang_pasuruan)
        : null);

$tiba = !empty($data['tanggal_tiba_pasuruan'])
    ? strtotime($data['tanggal_tiba_pasuruan'])
    : (!empty($logistik->tanggal_tiba_pasuruan)
        ? strtotime($logistik->tanggal_tiba_pasuruan)
        : null);

$bongkar = !empty($data['tanggal_bongkar_pasuruan'])
    ? strtotime($data['tanggal_bongkar_pasuruan'])
    : (!empty($logistik->tanggal_bongkar_pasuruan)
        ? strtotime($logistik->tanggal_bongkar_pasuruan)
        : null);

$leadtime = $data['transport_lead_time_pasuruan']
    ?? $logistik->transport_lead_time_pasuruan;

/* ===========================
   Lama Perjalanan
=========================== */
if ($keluar && $tiba) {

    $lama = max(0, ceil(($tiba - $keluar) / 86400));

    $data['lama_perjalanan_pasuruan'] = $lama;
}

/* ===========================
   SLA Tiba
=========================== */
if ($keluar && $tiba && $leadtime !== null) {

    $estimasi = strtotime("+{$leadtime} days", $keluar);

    $data['sla_tiba_pasuruan'] =
        ($tiba <= $estimasi)
            ? 'On Time'
            : 'Delay';
}

/* ===========================
   Overstay Bongkar
=========================== */
if ($tiba && $bongkar) {

    $overstay = max(0, ceil(($bongkar - $tiba) / 86400));

    $data['overstay_days_pasuruan'] = $overstay;

    $data['sla_bongkar_pasuruan'] =
        ($overstay <= 0)
            ? 'On Time'
            : 'Delay';
}

/* ===========================
   Monitoring Alert
=========================== */

if ($bongkar) {

    $data['monitoring_alert_pasuruan'] = 'SELESAI';

} elseif ($tiba) {

    $data['monitoring_alert_pasuruan'] = 'TIBA DI TUJUAN';

} elseif ($keluar) {

    $today = strtotime(date('Y-m-d'));

    $estimasi = strtotime("+{$leadtime} days", $keluar);

    $selisih = ceil(($estimasi - $today) / 86400);

    if ($selisih < 0) {

        $data['monitoring_alert_pasuruan'] = 'TERLAMBAT';

    } elseif ($selisih <= 2) {

        $data['monitoring_alert_pasuruan'] = 'WARNING H-2';

    } else {

        $data['monitoring_alert_pasuruan'] = 'AMAN';

    }
}

/* ===========================
   Action Required
=========================== */

switch ($data['monitoring_alert_pasuruan'] ?? '') {

    case 'TERLAMBAT':
        $data['action_required_pasuruan'] = 'Follow Up Driver';
        break;

    case 'WARNING H-2':
        $data['action_required_pasuruan'] = 'Monitoring';
        break;

    case 'TIBA DI TUJUAN':
        $data['action_required_pasuruan'] = 'Menunggu Bongkar';
        break;

    case 'SELESAI':
        $data['action_required_pasuruan'] = 'Closed';
        break;

    default:
        $data['action_required_pasuruan'] = '-';
        break;
}
$this->generateMonitoringPasuruan($data);
        $logistik->update($data);

        return response()->json([
            'message' => 'Auto-save berhasil.',
            'id'      => $logistik->id,
            'cr'      => $data['cr_pasuruan'],
        ]);
    }

    public function destroy($id)
    {
        $logistik = LogistikPengirimanPasuruan::findOrFail($id);

        $logistik->delete();

        return redirect()->route('pasuruan.admin')
                         ->with('success', 'Data berhasil dihapus.');
    }

    /**
     * Ubah "Rp 1.000.000" / "1.000.000" jadi angka murni (float).
     * Kalau kosong, balikin null biar kolom numeric di DB nggak error.
     */
    private function parseRupiah($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $clean = preg_replace('/[^0-9]/', '', (string) $value);

        return $clean === '' ? null : (float) $clean;
    }

    /**
     * CR (%) = biaya_kirim / nilai_muatan * 100
     */
    private function hitungCr($nilaiMuatan, $biayaKirim): float
    {
        $nilaiMuatan = (float) $nilaiMuatan;
        $biayaKirim  = (float) $biayaKirim;

        if ($nilaiMuatan <= 0) {
            return 0;
        }

        return round(($biayaKirim / $nilaiMuatan) * 100, 4);
    }
}