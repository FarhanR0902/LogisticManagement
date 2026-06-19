<?php


namespace App\Http\Controllers\spv;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\LogistikPengiriman;

class SpvMonitoringController extends Controller
{

    // =====================================================
    // DASHBOARD
    // =====================================================

  public function dashboard()
{
    // Total semua data
    $total_data = DB::table('logistik_pengiriman')->count();

    // ─── SLA TIBA ────────────────────────────────────────────
    // updateMonitoring() menyimpan 'On Time' atau 'Delay'
    // Hitung realtime dari tanggal (tidak bergantung kolom sla_tiba)

    $total_tiba_ontime = DB::table('logistik_pengiriman')
        ->whereNotNull('tanggal_tiba')
        ->whereNotNull('tanggal_keluar_gudang')
        ->whereRaw("
            DATE(tanggal_tiba) <=
            DATE_ADD(DATE(tanggal_keluar_gudang),
                INTERVAL transport_lead_time DAY)
        ")
        ->count();

    $total_tiba_delay = DB::table('logistik_pengiriman')
        ->whereNotNull('tanggal_tiba')
        ->whereNotNull('tanggal_keluar_gudang')
        ->whereRaw("
            DATE(tanggal_tiba) >
            DATE_ADD(DATE(tanggal_keluar_gudang),
                INTERVAL transport_lead_time DAY)
        ")
        ->count();

    $total_final_delay = $total_tiba_delay;

    // ─── SLA BONGKAR ─────────────────────────────────────────

    $total_bongkar_ontime = DB::table('logistik_pengiriman')
        ->whereNotNull('tanggal_tiba')
        ->whereNotNull('tanggal_bongkar')
        ->where('tanggal_bongkar', '!=', '1899-12-31 00:00:00')
        ->whereRaw("
            DATEDIFF(DATE(tanggal_bongkar), DATE(tanggal_tiba)) <= 0
        ")
        ->count();

    $total_bongkar_delay = DB::table('logistik_pengiriman')
        ->whereNotNull('tanggal_tiba')
        ->whereNotNull('tanggal_bongkar')
        ->where('tanggal_bongkar', '!=', '1899-12-31 00:00:00')
        ->whereRaw("
            DATEDIFF(DATE(tanggal_bongkar), DATE(tanggal_tiba)) > 0
        ")
        ->count();

    // ─── SUMMARY AREA ────────────────────────────────────────

    $summary_area = DB::table('logistik_pengiriman')
        ->select('area', DB::raw('COUNT(*) as total'))
        ->whereNotNull('area')
        ->groupBy('area')
        ->orderByDesc('total')
        ->get();

    return view('spvmonitoring.dashboard', compact(
        'total_data',
        'total_tiba_ontime',
        'total_tiba_delay',
        'total_final_delay',
        'total_bongkar_ontime',
        'total_bongkar_delay',
        'summary_area'
    ));
}


    // =====================================================
    // DATA spvmonitoring
    // =====================================================

// public function dataLogistik(Request $request)
// {
//     $query = LogistikPengiriman::query();

//     if ($request->pic_spvmonitoring) {
//         $query->where('pic_spvmonitoring', $request->pic_spvmonitoring);
//     }

//     if ($request->bulan) {
//         $query->whereMonth('tanggal_keluar_gudang', $request->bulan);
//     }

//     if ($request->tahun) {
//         $query->whereYear('tanggal_keluar_gudang', $request->tahun);
//     }

//     $logistik = $query->orderBy('id', 'DESC')->get();

//     $picList = LogistikPengiriman::whereNotNull('pic_spvmonitoring')
//         ->distinct()
//         ->pluck('pic_spvmonitoring');

//     return view('spvmonitoring.data_spvmonitoring', compact('logistik', 'picList'));
// }

public function dataLogistik(Request $request)
{
     
    $query = LogistikPengiriman::query();

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
        $query->whereMonth('tanggal_keluar_gudang', $request->bulan);
    }

    // ================= FILTER TAHUN =================
    if ($request->filled('tahun')) {
        $query->whereYear('tanggal_keluar_gudang', $request->tahun);
    }

    // ================= DATA =================
   $logistik = $query
    ->orderBy('no_shipment', 'ASC')
    ->get();

    // ================= AREA LIST =================
    $areaList = LogistikPengiriman::whereNotNull('area')
        ->distinct()
        ->pluck('area');

    // ================= AKURASI TIBA =================
    $akurasiTiba = DB::table('akurasi3')
        ->distinct()
        ->pluck('akurasi_waktu_tiba');

    // ================= AKURASI BONGKAR =================
    $akurasiBongkar = DB::table('akurasi3')
        ->distinct()
        ->pluck('akurasi_waktu_bongkar');
        $picList = LogistikPengiriman::whereNotNull('pic_monitoring')
    ->distinct()
    ->pluck('pic_monitoring');

    return view('spvmonitoring.data_monitoring', compact(
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

    $keluar = $logistik->tanggal_keluar_gudang
        ? strtotime(date('Y-m-d', strtotime($logistik->tanggal_keluar_gudang)))
        : null;

    $tiba = $request->tanggal_tiba
        ? strtotime(date('Y-m-d', strtotime($request->tanggal_tiba)))
        : null;

    $bongkar = $request->tanggal_bongkar
        ? strtotime(date('Y-m-d', strtotime($request->tanggal_bongkar)))
        : null;

    $leadtime = (int)($logistik->transport_lead_time ?? 0);

    $estimasi = $keluar
        ? strtotime("+{$leadtime} days", $keluar)
        : null;

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

$logistik->estimasi_tiba = $estimasi
    ? date('Y-m-d', $estimasi)
    : null;

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
$logistik->transport_laut = $request->transport_laut ?? 0;

if ($logistik->transport_laut == 1) {

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
        'transport_laut' => $request->transport_laut,
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
//  public function updateMonitoring(Request $request, $id)
//     {
        

//         $logistik = LogistikPengiriman::findOrFail($id);

//         $keluar = $logistik->tanggal_keluar_gudang
//             ? strtotime(date('Y-m-d', strtotime($logistik->tanggal_keluar_gudang)))
//             : null;

//         $tiba = $request->tanggal_tiba
//             ? strtotime(date('Y-m-d', strtotime($request->tanggal_tiba)))
//             : null;

//         $bongkar = $request->tanggal_bongkar
//             ? strtotime(date('Y-m-d', strtotime($request->tanggal_bongkar)))
//             : null;

//         $leadtime = (int)($logistik->transport_lead_time ?? 0);

//         $estimasi = $keluar
//             ? strtotime("+{$leadtime} days", $keluar)
//             : null;

//         $lama_perjalanan = ($keluar && $tiba)
//             ? max(0, floor(($tiba - $keluar) / 86400))
//             : null;

//         $sla_tiba = ($tiba && $estimasi)
//             ? (($tiba <= $estimasi) ? 'On Time' : 'Delay')
//             : '-';

//         $overstay = ($tiba && $bongkar)
//             ? max(0, floor(($bongkar - $tiba) / 86400))
//             : null;

//         $sla_bongkar = ($tiba && $bongkar)
//             ? (($overstay <= 0) ? 'On Time' : 'Delay')
//             : '-';

//         $logistik->pic_monitoring   = $request->pic_monitoring;
//         $logistik->status_kendaraan = $request->status_kendaraan;
//         $logistik->action_required  = $request->action_required;
//         $logistik->monitoring_alert = $request->monitoring_alert;

//         $logistik->act_urutan_bongkar = $request->act_urutan_bongkar;

//         $logistik->tanggal_tiba    = $request->tanggal_tiba;
//         $logistik->tanggal_bongkar  = $request->tanggal_bongkar;
// $logic = $this->generateStatusAlert($sla_tiba, $sla_bongkar);

//         $logistik->overstay_days   = $overstay;
//         $logistik->lama_perjalanan = $lama_perjalanan;

//         $logistik->reason_tiba     = $request->reason_tiba;
//         $logistik->reason_bongkar  = $request->reason_bongkar;

//         $logistik->remarks         = $request->remarks;

//         $logistik->act_pgi_date = $request->input('act_pgi_date');
// $logistik->created_by   = $request->input('created_by');
// $logistik->total_do_qty_car = $request->input('total_do_qty_car');


//         $logistik->save();

//         return response()->json([
//             'success' => true,
//             'message' => 'Monitoring berhasil diupdate'
//         ]);
//     }

private function generateStatusAlert($sla_tiba, $sla_bongkar)
{
    // normalisasi status
    $tibaDelay = ($sla_tiba !== 'On Time' && $sla_tiba !== '-' && $sla_tiba !== null);
    $bongkarDelay = ($sla_bongkar !== 'On Time' && $sla_bongkar !== '-' && $sla_bongkar !== null);

    // default
    $status_akhir = 'In Transit';
    $alert = 'Menunggu update';

    // 1. ON TIME + ON TIME
    if (!$tibaDelay && !$bongkarDelay) {
        $status_akhir = 'Delivered On Time';
        $alert = 'Delivered On Time';
    }

    // 2. ON TIME + DELAY BONGKAR
    elseif (!$tibaDelay && $bongkarDelay) {
        $status_akhir = 'Delivered Delay';
        $alert = 'Delay di Pembongkaran';
    }

    // 3. DELAY TIBA + ON TIME BONGKAR
    elseif ($tibaDelay && !$bongkarDelay) {
        $status_akhir = 'Delivered Delay';
        $alert = 'Delay di Perjalanan';
    }

    // 4. DELAY KEDUANYA
    elseif ($tibaDelay && $bongkarDelay) {
        $status_akhir = 'Delivered Delay';
        $alert = 'Delay Total (Perjalanan + Pembongkaran)';
    }

    return [
        'status_akhir' => $status_akhir,
        'alert' => $alert
    ];
}
// public function updateMonitoring(Request $request, $id)
// {
//     $logistik = LogistikPengiriman::findOrFail($id);

//     // =========================
//     // DATA DASAR
//     // =========================
//     $keluar = $logistik->tanggal_keluar_gudang
//         ? strtotime(date('Y-m-d', strtotime($logistik->tanggal_keluar_gudang)))
//         : null;

//     $tiba = $request->tanggal_tiba
//         ? strtotime(date('Y-m-d', strtotime($request->tanggal_tiba)))
//         : null;

//     $bongkar = $request->tanggal_bongkar
//         ? strtotime(date('Y-m-d', strtotime($request->tanggal_bongkar)))
//         : null;

//     $leadtime = (int)($logistik->transport_lead_time ?? 0);

//     // =========================
//     // ESTIMASI
//     // =========================
//     $estimasi = $keluar
//         ? strtotime("+{$leadtime} days", $keluar)
//         : null;

//     // =========================
//     // LAMA PERJALANAN (INI YANG KAMU MINTA)
//     // =========================
//     $lama_perjalanan = ($keluar && $tiba)
//         ? floor(($tiba - $keluar) / 86400)
//         : null;

//     // =========================
//     // SLA TIBA
//     // =========================
//     $sla_tiba = '-';

//     if ($tiba && $estimasi) {
//         $sla_tiba = ($tiba <= $estimasi) ? 'On Time' : 'Delay';
//     }

//     // =========================
//     // OVERSTAY + SLA BONGKAR
//     // =========================
//     $overstay = null;
//     $sla_bongkar = '-';

//     if ($tiba && $bongkar) {
//         $overstay = max(0, floor(($bongkar - $tiba) / 86400));
//         $sla_bongkar = ($overstay <= 0) ? 'On Time' : 'Delay';
//     }

//     // =========================
//     // SAVE DATABASE (FIXED FULL)
//     // =========================
//     $logistik->pic_monitoring   = $request->pic_monitoring; // ✅ FIX
//     $logistik->status_kendaraan = $request->status_kendaraan;
//     $logistik->action_required  = $request->action_required;
//     $logistik->monitoring_alert = $request->monitoring_alert;

//     $logistik->act_urutan_bongkar = $request->act_urutan_bongkar;

//     $logistik->tanggal_tiba    = $request->tanggal_tiba;
//     $logistik->tanggal_bongkar = $request->tanggal_bongkar;

//     $logistik->sla_tiba       = $sla_tiba;
//     $logistik->sla_bongkar    = $sla_bongkar;

//     $logistik->overstay_days  = $overstay;
//     $logistik->lama_perjalanan = $lama_perjalanan; // ✅ FIX INI

//     $logistik->reason_tiba    = $request->reason_tiba;
//     $logistik->reason_bongkar = $request->reason_bongkar;

//     $logistik->remarks        = $request->remarks;

//     $logistik->save();

//     return response()->json([
//         'success' => true,
//         'message' => 'Monitoring berhasil diupdate'
//     ]);
// }
    // UPDATE MONITORING
    // =====================================================
// public function updateMonitoring(Request $request, $id)
// {
//     $logistik = LogistikPengiriman::findOrFail($id);

//     // =========================
//     // HITUNG OVERSTAY
//     // =========================
//     $overstay = 0;

//     if (!empty($request->tanggal_tiba) && !empty($request->tanggal_bongkar)) {

//         $tiba = strtotime($request->tanggal_tiba);
//         $bongkar = strtotime($request->tanggal_bongkar);

//         $overstay = floor(($bongkar - $tiba) / 86400);

//         if ($overstay < 0) $overstay = 0;
//     }

//     // =========================
//     // HITUNG SLA TIBA (AUTO)
//     // =========================
// $keluar = $logistik->tanggal_keluar_gudang
//     ? strtotime(date('Y-m-d', strtotime($logistik->tanggal_keluar_gudang)))
//     : null;

// $tiba = $request->tanggal_tiba
//     ? strtotime(date('Y-m-d', strtotime($request->tanggal_tiba)))
//     : null;
//     $estimasi = $estimasi
//     ? strtotime(date('Y-m-d 00:00:00', $estimasi))
//     : null;

// $bongkar = $request->tanggal_bongkar
//     ? strtotime(date('Y-m-d', strtotime($request->tanggal_bongkar)))
//     : null;

// $leadtime = (int)($logistik->transport_lead_time ?? 0);

// // ================= ESTIMASI =================
// $estimasi = $keluar
//     ? strtotime("+$leadtime days", $keluar)
//     : null;

// // ================= SLA TIBA =================
// $sla_tiba = '-';

// if ($tiba && $estimasi) {
//     $sla_tiba = ($tiba <= $estimasi) ? 'On Time' : 'Delay';
// }

// // ================= OVERSTAY =================
// $overstay = null;

// if ($tiba && $bongkar) {
//     $overstay = floor(($bongkar - $tiba) / 86400);
//     $overstay = max(0, $overstay);
// }

// // ================= SLA BONGKAR =================
// $sla_bongkar = '-';

// if ($tiba && $bongkar) {
//     $sla_bongkar = ($overstay <= 0) ? 'On Time' : 'Delay';
// }

//     // =========================
//     // SAVE KE DATABASE (LOGISTIK)
//     // =========================
//     $logistik->pic_monitoring     = $request->pic_monitoring;
//     $logistik->status_kendaraan   = $request->status_kendaraan;
//     $logistik->monitoring_alert   = $request->monitoring_alert;
//     $logistik->action_required    = $request->action_required;

//     $logistik->act_urutan_bongkar = $request->act_urutan_bongkar;

//     $logistik->tanggal_tiba       = $request->tanggal_tiba;
//     $logistik->tanggal_bongkar    = $request->tanggal_bongkar;

//     $logistik->sla_tiba           = $sla_tiba;
//     $logistik->sla_bongkar        = $sla_bongkar;

//     $logistik->overstay_days      = $overstay;

//     $logistik->reason_tiba        = $request->reason_tiba;
//     $logistik->reason_bongkar     = $request->reason_bongkar;

//     $logistik->status_akhir       = $request->status_akhir;
//     $logistik->remarks            = $request->remarks;

//     $logistik->save();

//     return back()->with('success', 'Monitoring & SLA berhasil diupdate');
// }


    // =====================================================
    // BONGKAR DELAY
    // =====================================================

//     public function bongkarDelay()
// {
//     $logistik = DB::table('logistik_pengiriman')
//         ->where(function ($q) {

//             $q->whereRaw("LOWER(sla_bongkar) = 'delay'")
//               ->orWhereRaw("LOWER(sla_bongkar) = 'critical delay'")
//               ->orWhere('sla_bongkar', 'H+1')
//               ->orWhere('sla_bongkar', 'H+2')
//               ->orWhere('sla_bongkar', 'H>2');

//         })
//         ->get();

//     return view(
//         'monitoring.bongkar_delay',
//         compact('logistik')
//     );
// }

public function bongkarDelay(Request $request)
{
    $query = DB::table('logistik_pengiriman')
        ->whereNotNull('tanggal_bongkar')
        ->whereNotNull('tanggal_tiba')
        ->where('tanggal_bongkar', '!=', '1899-12-31 00:00:00')
        ->whereRaw('DATEDIFF(DATE(tanggal_bongkar), DATE(tanggal_tiba)) > 0');

    if ($request->filled('area')) {
        $query->where('area', $request->area);
    }

    if ($request->filled('tanggal_bongkar')) {
        $query->whereDate('tanggal_bongkar', $request->tanggal_bongkar);
    }

    $list = $query->orderByDesc('tanggal_bongkar')->get();

    $areaList = DB::table('logistik_pengiriman')
        ->select('area')
        ->whereNotNull('area')
        ->groupBy('area')
        ->orderBy('area')
        ->pluck('area');

    $title = 'Bongkar Delay';

    return view('spvmonitoring.bongkar_delay', compact('list', 'title', 'areaList'));
}

    // =====================================================
    // BONGKAR ONTIME
    // =====================================================

//     public function bongkarOntime()
// {
//     $logistik = DB::table('logistik_pengiriman')
//         ->where(function ($q) {

//             $q->whereRaw("LOWER(sla_bongkar) = 'on time'")
//               ->orWhere('sla_bongkar', 'ONTIME')
//               ->orWhere('sla_bongkar', 'H+0');

//         })
//         ->get();

//     return view(
//         'spvmonitoring.bongkar_ontime',
//         compact('logistik')
//     );
// }
public function bongkarOntime(Request $request)
{
    $query = DB::table('logistik_pengiriman')
        ->selectRaw("
            *,
            CASE
                WHEN overstay_days IS NULL OR overstay_days = 0 THEN 'H+0'
                WHEN overstay_days = 1 THEN 'H+1'
                WHEN overstay_days = 2 THEN 'H+2'
                ELSE 'Critical Delay'
            END AS sla_bongkar
        ")
        ->whereNotNull('tanggal_bongkar')
        ->where('tanggal_bongkar', '!=', '1899-12-31 00:00:00')
        ->where(function ($q) {
            $q->whereNull('overstay_days')
              ->orWhere('overstay_days', 0);
        });

    if ($request->filled('tanggal_bongkar')) {
        $query->whereDate('tanggal_bongkar', $request->tanggal_bongkar);
    }

    if ($request->filled('area')) {
        $query->where('area', $request->area);
    }

    $list = $query->orderByDesc('tanggal_bongkar')->get();

    return view('spvmonitoring.bongkar_ontime', compact('list'));
}

    // =====================================================
    // SLA ONTIME
    // =====================================================


public function slaOntime(Request $request)
{
    $query = DB::table('logistik_pengiriman')
        ->selectRaw("
            *,
            CASE
                WHEN DATEDIFF(
                    DATE(tanggal_tiba),
                    DATE_ADD(
                        DATE(tanggal_keluar_gudang),
                        INTERVAL transport_lead_time DAY
                    )
                ) = 1 THEN 'H+1'

                WHEN DATEDIFF(
                    DATE(tanggal_tiba),
                    DATE_ADD(
                        DATE(tanggal_keluar_gudang),
                        INTERVAL transport_lead_time DAY
                    )
                ) = 2 THEN 'H+2'

                WHEN DATEDIFF(
                    DATE(tanggal_tiba),
                    DATE_ADD(
                        DATE(tanggal_keluar_gudang),
                        INTERVAL transport_lead_time DAY
                    )
                ) > 2 THEN 'Critical Delay'

                ELSE 'On Time'
            END AS sla_tiba
        ")
        ->whereNotNull('tanggal_tiba')
        ->whereNotNull('tanggal_keluar_gudang')
        ->whereRaw("
            DATE(tanggal_tiba) <=
            DATE_ADD(
                DATE(tanggal_keluar_gudang),
                INTERVAL transport_lead_time DAY
            )
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

    $list_area = DB::table('logistik_pengiriman')
        ->select('area')
        ->whereNotNull('area')
        ->groupBy('area')
        ->orderBy('area')
        ->get();

    return view('spvmonitoring.sla_ontime', compact('logistik', 'list_area'));
}
    // =====================================================
    // SLA DELAY
    // =====================================================

public function slaDelay(Request $request)
{
    $query = DB::table('logistik_pengiriman')
        // ... query yang sudah ada, jangan diubah ...
        ;

    $logistik = $query->orderByDesc('tanggal_tiba')->get();

    $list_area = DB::table('logistik_pengiriman')
        ->select('area')
        ->whereNotNull('area')
        ->groupBy('area')
        ->orderBy('area')
        ->get();

    $title = 'SLA Delay'; // ← TAMBAHKAN INI

    return view('spvmonitoring.sla_delay', compact('logistik', 'list_area', 'title')); // ← tambah 'title'
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


    public function FullDashboard(Request $request)
    {

        // ================= BASE QUERY =================

        $base = DB::table('logistik_pengiriman');

        $this->applyFilter($base, $request);

        // ================= TOTAL =================

        $total_data = (clone $base)->count();

        // ================= GUDANG =================

    $gudang_ontime = DB::table('logistik_pengiriman')
        ->whereNotNull('tanggal_dpt_unit')
        ->whereNotNull('tanggal_tiba_gudang')
        ->whereRaw('DATE(tanggal_dpt_unit) = DATE(tanggal_tiba_gudang)')
        ->count();

    // ================= SLA DELAY (dapat unit beda hari) =================
    $gudang_delay = DB::table('logistik_pengiriman')
        ->whereNotNull('tanggal_dpt_unit')
        ->whereNotNull('tanggal_tiba_gudang')
        ->whereRaw('DATE(tanggal_tiba_gudang) > DATE(tanggal_dpt_unit)')
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

    $customer_ontime = DB::table('logistik_pengiriman')
        ->whereNotNull('tanggal_tiba')
        ->whereNotNull('tanggal_keluar_gudang')
        ->whereRaw("
            DATE(tanggal_tiba) <=
            DATE_ADD(DATE(tanggal_keluar_gudang),
                INTERVAL transport_lead_time DAY)
        ")
        ->count();

    $customer_delay = DB::table('logistik_pengiriman')
        ->whereNotNull('tanggal_tiba')
        ->whereNotNull('tanggal_keluar_gudang')
        ->whereRaw("
            DATE(tanggal_tiba) >
            DATE_ADD(DATE(tanggal_keluar_gudang),
                INTERVAL transport_lead_time DAY)
        ")
        ->count();

  

    // ─── SLA BONGKAR ─────────────────────────────────────────

    $bongkar_ontime = DB::table('logistik_pengiriman')
        ->whereNotNull('tanggal_tiba')
        ->whereNotNull('tanggal_bongkar')
        ->where('tanggal_bongkar', '!=', '1899-12-31 00:00:00')
        ->whereRaw("
            DATEDIFF(DATE(tanggal_bongkar), DATE(tanggal_tiba)) <= 0
        ")
        ->count();

    $bongkar_delay = DB::table('logistik_pengiriman')
        ->whereNotNull('tanggal_tiba')
        ->whereNotNull('tanggal_bongkar')
        ->where('tanggal_bongkar', '!=', '1899-12-31 00:00:00')
        ->whereRaw("
            DATEDIFF(DATE(tanggal_bongkar), DATE(tanggal_tiba)) > 0
        ")
        ->count();


        // ================= ARMADA =================

        $planner_armada = (clone $base)

            ->where('ketersediaan_unit', 'Sudah Dapat')

            ->count();


        $planner_belum_armada = (clone $base)

            ->where('ketersediaan_unit', 'Belum Dapat')

            ->count();

         $list_dist_channel = (clone $base)
    ->select('dist_channel')
    ->whereNotNull('dist_channel')
    ->distinct()
    ->orderBy('dist_channel')
    ->get();


        // ================= PLANNER =================

        $planner_ontime = $gudang_ontime;

        $planner_delay = $gudang_delay;


        // ================= TOTAL NILAI MUATAN =================

        $totalNilaiMuatan = (clone $base)

            ->selectRaw("
                SUM(
                    CASE
                        WHEN nilai_muatan IS NULL THEN 0
                        ELSE CAST(
                            REPLACE(
                                REPLACE(nilai_muatan,'.',''),
                            ',','') AS UNSIGNED
                        )
                    END
                ) as total
            ")

            ->value('total');


        // ================= TOTAL BIAYA =================

        $totalBiayaKirim = (clone $base)

            ->selectRaw("
                SUM(
                    CASE
                        WHEN biaya_kirim IS NULL THEN 0
                        ELSE CAST(
                            REPLACE(
                                REPLACE(biaya_kirim,'.',''),
                            ',','') AS UNSIGNED
                        )
                    END
                ) as total
            ")

            ->value('total');


        // ================= SUMMARY AREA =================

        $summary_area = (clone $base)

            ->select(
                'area',

                DB::raw('COUNT(*) as total_shipment'),

                DB::raw("
                    SUM(
                        CASE
                            WHEN biaya_kirim IS NULL THEN 0
                            ELSE CAST(
                                REPLACE(
                                    REPLACE(biaya_kirim,'.',''),
                                ',','') AS UNSIGNED
                            )
                        END
                    ) as total_biaya
                "),

                DB::raw("
                    SUM(
                        CASE
                            WHEN nilai_muatan IS NULL THEN 0
                            ELSE CAST(
                                REPLACE(
                                    REPLACE(nilai_muatan,'.',''),
                                ',','') AS UNSIGNED
                            )
                        END
                    ) as total_muatan
                ")
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

                DB::raw("
                    SUM(
                        CASE
                            WHEN biaya_kirim IS NULL THEN 0
                            ELSE CAST(
                                REPLACE(
                                    REPLACE(biaya_kirim,'.',''),
                                ',','') AS UNSIGNED
                            )
                        END
                    ) as total_biaya
                "),

                DB::raw("
                    SUM(
                        CASE
                            WHEN nilai_muatan IS NULL THEN 0
                            ELSE CAST(
                                REPLACE(
                                    REPLACE(nilai_muatan,'.',''),
                                ',','') AS UNSIGNED
                            )
                        END
                    ) as total_muatan
                ")
            )

            ->whereNotNull('tujuan')

            ->groupBy('tujuan')

            ->orderByDesc('total_shipment')

            ->get();


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
'list_area'
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
 public function summaryTotal(Request $request)
    {

        $query = DB::table('logistik_pengiriman');

        $this->applyFilter($query, $request);

        $logistik = $query->get();

        return view('spvmonitoring.summary_total', compact(
            'logistik'
        ));
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