<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Models\LogistikPengiriman;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Exports\MonitoringExport;
use Maatwebsite\Excel\Facades\Excel;

class MonitoringController extends Controller
{

    // =====================================================
    // DASHBOARD
    // =====================================================

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

    return view('monitoring.dashboard', compact(
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

// public function dataLogistik(Request $request)
// {
     
//     $query = LogistikPengiriman::query();

// if ($request->filled('jenis')) {
//     $query->where('transportasi', strtoupper($request->jenis));
// }
//     // ================= FILTER AREA =================
//     if ($request->filled('area')) {
//         $query->where('area', $request->area);
//     }

//     // ================= FILTER PIC =================
// if ($request->filled('pic_monitoring')) {
//     $query->where('pic_monitoring', $request->pic_monitoring);
// }

//     // ================= FILTER BULAN =================
//    if ($request->filled('bulan')) {
//     $query->whereRaw("
//         MONTH(
//             GREATEST(
//                 COALESCE(tanggal_keluar_gudang,'1900-01-01'),
//                 COALESCE(tanggal_keluar_gudang_2,'1900-01-01'),
//                 COALESCE(tanggal_keluar_gudang_3,'1900-01-01')
//             )
//         ) = ?
//     ", [$request->bulan]);
// }

// if ($request->filled('tahun')) {
//     $query->whereRaw("
//         YEAR(
//             GREATEST(
//                 COALESCE(tanggal_keluar_gudang,'1900-01-01'),
//                 COALESCE(tanggal_keluar_gudang_2,'1900-01-01'),
//                 COALESCE(tanggal_keluar_gudang_3,'1900-01-01')
//             )
//         ) = ?
//     ", [$request->tahun]);
// }
//     // ================= DATA =================
//    $logistik = $query
//     ->orderBy('no_shipment', 'ASC')
//     ->get();
//     $logistik = $query
//     ->orderBy('no_shipment', 'ASC')
//     ->orderBy('act_urutan_bongkar', 'ASC')
//     ->get();

// // =====================================================
// // 🔥 FIX ESTIMASI (ANTI BERUBAH + ANTI SHIFT BUG)
// // =====================================================

// $lastEstimasiPerShipment = [];

// foreach ($logistik as $r) {

//     $keluar = collect([
//         $r->tanggal_keluar_gudang,
//         $r->tanggal_keluar_gudang_2 ?? null,
//         $r->tanggal_keluar_gudang_3 ?? null,
//     ])
//     ->filter()
//     ->map(fn($d) => strtotime($d))
//     ->max();

//     $leadtime = (int) $r->transport_lead_time;

//     $key = $r->no_shipment;

//     // =========================
//     // SHIFT (ANTI QUERY DB)
//     // =========================
//     if (!isset($lastEstimasiPerShipment[$key])) {
//         $shift = 0;
//     } else {
//         $shift = 1; // cukup +1 per step (stabil, tidak query DB)
//     }

//     $leadtimeFinal = $leadtime + $shift;

//     // =========================
//     // ESTIMASI FINAL
//     // =========================
//     if ($keluar) {

//         if (!isset($lastEstimasiPerShipment[$key])) {
//             $estimasi = strtotime("+{$leadtimeFinal} days", $keluar);
//         } else {
//             $estimasi = strtotime("+{$leadtimeFinal} days", $lastEstimasiPerShipment[$key]);
//         }

//     } else {
//         $estimasi = null;
//     }

//     $r->tanggal_estimasi = $estimasi;

//     // simpan anchor (INI YANG BIKIN GA BERUBAH)
//     $lastEstimasiPerShipment[$key] = $estimasi;
// }

//     // ================= AREA LIST =================
//     $areaList = LogistikPengiriman::whereNotNull('area')
//         ->distinct()
//         ->pluck('area');

//     // ================= AKURASI TIBA =================
//     $akurasiTiba = DB::table('akurasi3')
//         ->distinct()
//         ->pluck('akurasi_waktu_tiba');

//     // ================= AKURASI BONGKAR =================
//     $akurasiBongkar = DB::table('akurasi3')
//         ->distinct()
//         ->pluck('akurasi_waktu_bongkar');
//         $picList = LogistikPengiriman::whereNotNull('pic_monitoring')
//     ->distinct()
//     ->pluck('pic_monitoring');

//     return view('monitoring.data_monitoring', compact(
//         'logistik',
//         'areaList',
//         'akurasiTiba',
//         'akurasiBongkar',
//         'picList'
//     ));
// }


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
$query->whereNotNull('transport_lead_time');

$query->where(function ($q) {
    $q->whereNotNull('tanggal_keluar_gudang')
      ->orWhereNotNull('tanggal_keluar_gudang_2')
      ->orWhereNotNull('tanggal_keluar_gudang_3');
});
    // ================= AMBIL DATA =================
    $logistik = $query
        ->orderBy('no_shipment', 'ASC')
        ->orderBy('act_urutan_bongkar', 'ASC')
        ->get();

    // =====================================================
    // HITUNG ESTIMASI BERDASARKAN URUTAN DUPLIKAT SHIPMENT
    // =====================================================

    // $shipmentCounter = [];

    // foreach ($logistik as $r) {

    //     $shipment = trim($r->no_shipment);

    //     if (!isset($shipmentCounter[$shipment])) {
    //         $shipmentCounter[$shipment] = 0;
    //     } else {
    //         $shipmentCounter[$shipment]++;
    //     }

    //     $shift = $shipmentCounter[$shipment];

    //     $keluar = collect([
    //         $r->tanggal_keluar_gudang,
    //         $r->tanggal_keluar_gudang_2 ?? null,
    //         $r->tanggal_keluar_gudang_3 ?? null,
    //     ])
    //     ->filter()
    //     ->map(fn($d) => strtotime($d))
    //     ->max();

    //     $leadtime = (int) ($r->transport_lead_time ?? 0);

    //     $leadtimeFinal = $leadtime + $shift;

    //     if ($keluar) {
    //         $r->tanggal_estimasi = strtotime(
    //             "+{$leadtimeFinal} days",
    //             $keluar
    //         );
    //     } else {
    //         $r->tanggal_estimasi = null;
    //     }
    // }

    $grouped = $logistik->groupBy('no_shipment');

foreach ($grouped as $shipment => $items) {

    // ambil estimasi dasar SEKALI per shipment
    $keluar = $items->flatMap(function ($r) {
        return [
            $r->tanggal_keluar_gudang,
            $r->tanggal_keluar_gudang_2,
            $r->tanggal_keluar_gudang_3,
        ];
    })
    ->filter()
    ->map(fn($d) => strtotime($d))
    ->max();

    $leadtime = (int) ($items->first()->transport_lead_time ?? 0);

    $estimasi = $keluar
        ? strtotime("+{$leadtime} days", $keluar)
        : null;

    // assign ke semua row dalam shipment
 foreach ($items as $r) {
    $r->tanggal_estimasi = $r->estimasi_tiba
        ? strtotime($r->estimasi_tiba)
        : $estimasi;
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

    // ================= AKURASI =================
    $akurasiTiba = DB::table('akurasi3')
        ->distinct()
        ->pluck('akurasi_waktu_tiba');

    $akurasiBongkar = DB::table('akurasi3')
        ->distinct()
        ->pluck('akurasi_waktu_bongkar');

    return view('monitoring.data_monitoring', compact(
        'logistik',
        'areaList',
        'akurasiTiba',
        'akurasiBongkar',
        'picList'
    ));
}

public function updateMonitoring(Request $request, $id)
{


    $logistik = LogistikPengiriman::findOrFail($id);
    $oldTanggalTiba = $logistik->tanggal_tiba;

   $keluar = collect([
    $logistik->tanggal_keluar_gudang,
    $logistik->tanggal_keluar_gudang_2 ?? null,
    $logistik->tanggal_keluar_gudang_3 ?? null,
])
->filter()
->map(fn($d) => strtotime($d))
->max();

    $tiba = $request->tanggal_tiba
        ? strtotime(date('Y-m-d', strtotime($request->tanggal_tiba)))
        : null;

    $bongkar = $request->tanggal_bongkar
        ? strtotime(date('Y-m-d', strtotime($request->tanggal_bongkar)))
        : null;

    $leadtime = (int)($logistik->transport_lead_time ?? 0);

    // $estimasi = $keluar
    //     ? strtotime("+{$leadtime} days", $keluar)
    //     : null;

    $estimasi = $logistik->estimasi_tiba
    ? strtotime($logistik->estimasi_tiba)
    : (
        $keluar
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

    $logistik->estimasi_tiba = $estimasi
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
        $logistik->qty_monitoring = $request->qty_monitoring;
$logistik->selisih_qty = $logistik->total_do_qty_car - $logistik->qty_monitoring;
                $logistik->remarks_qty = $request->remarks_qty;

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


if ($request->filled('nama_kapal')) {

    $logistik->nama_kapal = $request->nama_kapal;
    $logistik->etd = $request->etd;
    $logistik->eta = $request->eta;
    $logistik->atd = $request->atd;
    $logistik->ata = $request->ata;

}
    $logistik->save();

    
$shipment = LogistikPengiriman::where(
    'no_shipment',
    $logistik->no_shipment
)->get();

$baseEstimasi = $keluar
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

// private function generateStatusAlert($sla_tiba, $sla_bongkar)
// {
//     // normalisasi status
//     $tibaDelay = ($sla_tiba !== 'On Time' && $sla_tiba !== '-' && $sla_tiba !== null);
//     $bongkarDelay = ($sla_bongkar !== 'On Time' && $sla_bongkar !== '-' && $sla_bongkar !== null);

//     // default
//     $status_akhir = 'In Transit';
//     $alert = 'Menunggu update';

//     // 1. ON TIME + ON TIME
//     if (!$tibaDelay && !$bongkarDelay) {
//         $status_akhir = 'Delivered On Time';
//         $alert = 'Delivered On Time';
//     }

//     // 2. ON TIME + DELAY BONGKAR
//     elseif (!$tibaDelay && $bongkarDelay) {
//         $status_akhir = 'Delivered Delay';
//         $alert = 'Delay di Pembongkaran';
//     }

//     // 3. DELAY TIBA + ON TIME BONGKAR
//     elseif ($tibaDelay && !$bongkarDelay) {
//         $status_akhir = 'Delivered Delay';
//         $alert = 'Delay di Perjalanan';
//     }

//     // 4. DELAY KEDUANYA
//     elseif ($tibaDelay && $bongkarDelay) {
//         $status_akhir = 'Delivered Delay';
//         $alert = 'Delay Total (Perjalanan + Pembongkaran)';
//     }

//     return [
//         'status_akhir' => $status_akhir,
//         'alert' => $alert
//     ];
// }

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

    return view('monitoring.bongkar_delay', compact('list'));
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

    return view('monitoring.bongkar_ontime', compact('list'));
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

    return view('monitoring.sla_ontime', compact('logistik'));
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

    return view('monitoring.sla_delay', compact('logistik'));
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
            'monitoring.summary_area',
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
            'monitoring.summary_area_detail',
            compact('logistik', 'area')
        );
    }
}


// $grouped = $logistik->groupBy('no_shipment');

