<?php

namespace App\Http\Controllers\Spv;

use App\Http\Controllers\Controller;
use App\Models\LogistikPengiriman;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    // ================= AMBIL DATA =================
    $logistik = $query
        ->orderBy('no_shipment', 'ASC')
        ->orderBy('act_urutan_bongkar', 'ASC')
        ->get();

    // =====================================================
    // HITUNG ESTIMASI BERDASARKAN URUTAN DUPLIKAT SHIPMENT
    // =====================================================

    $shipmentCounter = [];

    foreach ($logistik as $r) {

        $shipment = trim($r->no_shipment);

        if (!isset($shipmentCounter[$shipment])) {
            $shipmentCounter[$shipment] = 0;
        } else {
            $shipmentCounter[$shipment]++;
        }

        $shift = $shipmentCounter[$shipment];

        $keluar = collect([
            $r->tanggal_keluar_gudang,
            $r->tanggal_keluar_gudang_2 ?? null,
            $r->tanggal_keluar_gudang_3 ?? null,
        ])
        ->filter()
        ->map(fn($d) => strtotime($d))
        ->max();

        $leadtime = (int) ($r->transport_lead_time ?? 0);

        $leadtimeFinal = $leadtime + $shift;

        if ($keluar) {
            $r->tanggal_estimasi = strtotime(
                "+{$leadtimeFinal} days",
                $keluar
            );
        } else {
            $r->tanggal_estimasi = null;
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
            logistik_pengiriman.*,

            GREATEST(
                COALESCE(tanggal_keluar_gudang, '1970-01-01'),
                COALESCE(tanggal_keluar_gudang_2, '1970-01-01'),
                COALESCE(tanggal_keluar_gudang_3, '1970-01-01')
            ) AS tanggal_keluar_terakhir,

            DATE_ADD(
                DATE(GREATEST(
                    COALESCE(tanggal_keluar_gudang, '1970-01-01'),
                    COALESCE(tanggal_keluar_gudang_2, '1970-01-01'),
                    COALESCE(tanggal_keluar_gudang_3, '1970-01-01')
                )),
                INTERVAL transport_lead_time DAY
            ) AS tanggal_estimasi,

            CASE
                WHEN DATE(tanggal_tiba) <= DATE(
                    DATE_ADD(
                        DATE(GREATEST(
                            COALESCE(tanggal_keluar_gudang, '1970-01-01'),
                            COALESCE(tanggal_keluar_gudang_2, '1970-01-01'),
                            COALESCE(tanggal_keluar_gudang_3, '1970-01-01')
                        )),
                        INTERVAL transport_lead_time DAY
                    )
                )
                THEN 'On Time'
                ELSE 'Delay'
            END AS sla_tiba
        ")
        ->whereNotNull('tanggal_tiba')
        ->whereNotNull('tanggal_keluar_gudang');

    // 🔥 INI KUNCI UTAMA: FILTER HANYA ON TIME
    $query->havingRaw("
        CASE
            WHEN DATE(tanggal_tiba) <= DATE(
                DATE_ADD(
                    DATE(GREATEST(
                        COALESCE(tanggal_keluar_gudang, '1970-01-01'),
                        COALESCE(tanggal_keluar_gudang_2, '1970-01-01'),
                        COALESCE(tanggal_keluar_gudang_3, '1970-01-01')
                    )),
                    INTERVAL transport_lead_time DAY
                )
            )
            THEN 'On Time'
            ELSE 'Delay'
        END = 'On Time'
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

            GREATEST(
                COALESCE(tanggal_keluar_gudang, '1970-01-01'),
                COALESCE(tanggal_keluar_gudang_2, '1970-01-01'),
                COALESCE(tanggal_keluar_gudang_3, '1970-01-01')
            ) AS tanggal_keluar_terakhir,

            DATE_ADD(
                DATE(GREATEST(
                    COALESCE(tanggal_keluar_gudang, '1970-01-01'),
                    COALESCE(tanggal_keluar_gudang_2, '1970-01-01'),
                    COALESCE(tanggal_keluar_gudang_3, '1970-01-01')
                )),
                INTERVAL transport_lead_time DAY
            ) AS tanggal_estimasi,

            CASE
                WHEN DATE(tanggal_tiba) <= DATE(
                    DATE_ADD(
                        DATE(GREATEST(
                            COALESCE(tanggal_keluar_gudang, '1970-01-01'),
                            COALESCE(tanggal_keluar_gudang_2, '1970-01-01'),
                            COALESCE(tanggal_keluar_gudang_3, '1970-01-01')
                        )),
                        INTERVAL transport_lead_time DAY
                    )
                )
                THEN 'On Time'
                ELSE 'Delay'
            END AS sla_tiba
        ")
        ->whereNotNull('tanggal_tiba')
        ->whereNotNull('tanggal_keluar_gudang');

    // 🔥 FILTER HANYA DELAY
    $query->havingRaw("
        CASE
            WHEN DATE(tanggal_tiba) <= DATE(
                DATE_ADD(
                    DATE(GREATEST(
                        COALESCE(tanggal_keluar_gudang, '1970-01-01'),
                        COALESCE(tanggal_keluar_gudang_2, '1970-01-01'),
                        COALESCE(tanggal_keluar_gudang_3, '1970-01-01')
                    )),
                    INTERVAL transport_lead_time DAY
                )
            )
            THEN 'On Time'
            ELSE 'Delay'
        END = 'Delay'
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