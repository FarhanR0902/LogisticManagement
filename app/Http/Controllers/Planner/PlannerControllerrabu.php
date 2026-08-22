<?php

namespace App\Http\Controllers\Planner;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\LogistikPengiriman;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PlannerExport;
class PlannerController extends Controller
{


// public function dashboard()
// {
//     // ================= TOTAL =================
//     $total_data = DB::table('logistik_pengiriman')->count();

//     // ================= ARMADA READY (sudah dapat unit) =================
//   $armada = DB::table('logistik_pengiriman')
//     ->whereNotNull('tanggal_dpt_unit')
//     ->where(function ($q) {
//         $q->whereNotNull('tanggal_tiba_gudang')
//           ->orWhereNotNull('tanggal_tiba_gudang_2')
//           ->orWhereNotNull('tanggal_tiba_gudang_3');
//     })
//     ->count();
//     // ================= BELUM ARMADA (belum dapat unit) =================
//  $belum_armada = DB::table('logistik_pengiriman')
//     ->where(function ($q) {
//         $q->whereNull('tanggal_tiba_gudang')
//           ->whereNull('tanggal_tiba_gudang_2')
//           ->whereNull('tanggal_tiba_gudang_3');
//     })
//     ->count();

//     // ================= SLA ONTIME (dapat unit same day rencana kirim) =================
//     // $ontime = DB::table('logistik_pengiriman')
//     //     ->whereNotNull('tanggal_dpt_unit')
//     //     ->whereNotNull('tanggal_tiba_gudang')
//     //     ->whereRaw('DATE(tanggal_dpt_unit) = DATE(tanggal_tiba_gudang)')
//     //     ->count();

//    $ontime = LogistikPengiriman::all()
// ->filter(function ($row) {

//     $tiba = collect([
//         $row->tanggal_tiba_gudang,
//         $row->tanggal_tiba_gudang_2,
//         $row->tanggal_tiba_gudang_3,
//     ])->filter()->sort()->first();

//     if (!$row->tanggal_dpt_unit || !$tiba) {
//         return false;
//     }

//     return strtotime(date('Y-m-d', strtotime($tiba)))
//         <=
//         strtotime(date('Y-m-d', strtotime($row->tanggal_dpt_unit)));
// })
// ->count();

//     // ================= SLA DELAY (dapat unit beda hari) =================
//     // $delay = DB::table('logistik_pengiriman')
//     //     ->whereNotNull('tanggal_dpt_unit')
//     //     ->whereNotNull('tanggal_tiba_gudang')
//     //     ->whereRaw('DATE(tanggal_tiba_gudang) > DATE(tanggal_dpt_unit)')
//     //     ->count();

//    $delay = LogistikPengiriman::all()
// ->filter(function ($row) {

//     $tiba = collect([
//         $row->tanggal_tiba_gudang,
//         $row->tanggal_tiba_gudang_2,
//         $row->tanggal_tiba_gudang_3,
//     ])->filter()->sort()->first();

//     if (!$row->tanggal_dpt_unit || !$tiba) {
//         return false;
//     }

//     return strtotime(date('Y-m-d', strtotime($tiba)))
//         >
//         strtotime(date('Y-m-d', strtotime($row->tanggal_dpt_unit)));
// })
// ->count();
//     // ================= SUMMARY AREA =================
//     $summary_area = DB::table('logistik_pengiriman')
//         ->select('area', DB::raw('COUNT(*) as total'))
//         ->whereNotNull('area')
//         ->groupBy('area')
//         ->orderByDesc('total')
//         ->limit(10)
//         ->get();

//     return view('planner.dashboard', compact(
//         'total_data',
//         'ontime',
//         'delay',
//         'armada',
//         'belum_armada',
//         'summary_area'
//     ));
// }
// // public function dashboard()
// // {
// //     // ================= TOTAL =================
// //     $total_data = DB::table('logistik_pengiriman')->count();

// //     // ================= ONTIME / DELAY (PAKAI STATUS ASLI) =================
// // $ontime = DB::table('logistik_pengiriman')
// //     ->where('status_akhir', 'On Time')
// //     ->count();

// // $delay = DB::table('logistik_pengiriman')
// //     ->where('status_akhir', 'Delay')
// //     ->count();
// //     // ================= ARMADA READY =================
// //     $armada = DB::table('logistik_pengiriman')
// //         ->where(function ($q) {
// //             $q->whereIn('ketersediaan_unit', ['Sudah Dapat', 'READY', 'Ready'])
// //               ->orWhereIn('status_kendaraan', ['Sudah Dapat', 'READY', 'Ready']);
// //         })
// //         ->count();

// //     // ================= BELUM ARMADA =================
// //     $belum_armada = DB::table('logistik_pengiriman')
// //         ->where(function ($q) {
// //             $q->whereIn('ketersediaan_unit', ['Belum Dapat', 'Pending', 'PENDING'])
// //               ->orWhereIn('status_kendaraan', ['Belum Dapat', 'Pending', 'PENDING']);
// //         })
// //         ->count();

// //     // ================= SUMMARY AREA =================
// //     $summary_area = DB::table('logistik_pengiriman')
// //         ->select('area', DB::raw('COUNT(*) as total'))
// //         ->whereNotNull('area')
// //         ->groupBy('area')
// //         ->orderByDesc('total')
// //         ->limit(10)
// //         ->get();

// //     return view('planner.dashboard', compact(
// //         'total_data',
// //         'ontime',
// //         'delay',
// //         'armada',
// //         'belum_armada',
// //         'summary_area'
// //     ));
// // }

public function store(Request $request)
{
        $request->merge([
   
    ]);

  
    $rumus = $this->hitungSla($request);

    $data = $request->only([
        'create_tgl',
        'no_shipment',
        'planner',
        'dist_channel',
        'transport_lead_time',
        'tujuan',
        'area',
        'ketersediaan_unit',
        'mobil',
        'perubahan_mobil',
        'nilai_muatan',
        'biaya_kirim',
        'cr',
        'kategori_ekspedisi',
        'ekpedisi',
       
        'tanggal_naik_logistik',
        'rencana_kirim',
        'tanggal_dpt_unit',
        'planning_loading',
        'tanggal_tiba_gudang',
        'tanggal_keluar_gudang',
        'planning_loading_2',
        'tanggal_tiba_gudang_2',
        'tanggal_keluar_gudang_2',
        'tanggal_tiba_gudang_3',
        'planning_loading_3',
        'tanggal_keluar_gudang_3',
        'keterangan',
        'route',
        'pulau',
        'via_kirim'
    ]);

    LogistikPengiriman::create(array_merge($data, $rumus));

    return back()->with('success', 'Data berhasil disimpan');
}


public function dashboard()
{
    // =====================================================
    // AMBIL DATA UNIQUE PER NO_SHIPMENT (ANTI DUPLICATE)
    // =====================================================
    $shipments = DB::table('logistik_pengiriman')
        ->orderBy('no_shipment')
        ->get()
        ->groupBy('no_shipment')
        ->map(function ($group) {
            return $group->first(); // 1 shipment saja
        });

    // =====================================================
    // TOTAL DATA (UNIQUE SHIPMENT)
    // =====================================================
    $total_data = $shipments->count();

    // =====================================================
    // ARMADA READY (SUDAH DAPAT UNIT + ADA TIBA GUDANG)
    // =====================================================
   $armada = $shipments->filter(function ($row) {

    return !empty($row->rencana_kirim)
        && !empty($row->tanggal_dpt_unit);

})->count();

    // =====================================================
    // BELUM ARMADA
    // =====================================================
$belum_armada = $shipments->filter(function ($row) {

    return empty($row->rencana_kirim)
        || empty($row->tanggal_dpt_unit);

})->count();

    // =====================================================
    // SLA ONTIME (ADA TANGGAL TIBA GUDANG)
    // =====================================================
    $ontime = $shipments->filter(function ($row) {
        return !empty($row->tanggal_tiba_gudang)
            || !empty($row->tanggal_tiba_gudang_2)
            || !empty($row->tanggal_tiba_gudang_3);
    })->count();

    // =====================================================
    // SLA DELAY (SUDAH ADA TANGGAL DPT UNIT, TAPI BELUM ADA TIBA GUDANG)
    // =====================================================
    $delay = $shipments->filter(function ($row) {
        return !empty($row->tanggal_dpt_unit)
            && empty($row->tanggal_tiba_gudang)
            && empty($row->tanggal_tiba_gudang_2)
            && empty($row->tanggal_tiba_gudang_3);
    })->count();

    // =====================================================
    // SUMMARY AREA (UNIQUE SHIPMENT PER AREA)
    // =====================================================
    $summary_area = $shipments
        ->groupBy('area')
        ->map(function ($group) {
            return count($group);
        })
        ->sortDesc()
        ->take(10);

    // =====================================================
    // RETURN VIEW
    // =====================================================
    return view('planner.dashboard', compact(
        'total_data',
        'ontime',
        'delay',
        'armada',
        'belum_armada',
        'summary_area'
    ));
}

// public function update(Request $request, $id)
// {
    
    

    
//     $rumus = $this->hitungSla($request);
    

//     // 1. Tampung data yang murni berasal dari form web (bukan data master Excel)
//     $updateData = [
        
//         'tanggal_naik_logistik'   => $request->tanggal_naik_logistik,
//         'rencana_kirim'           => $request->rencana_kirim,
//         'transport_lead_time'     => $request->transport_lead_time,
//         'planner'                 => $request->planner,
//         'no_shipment'             => $request->no_shipment,
//         'ketersediaan_unit'       => $request->ketersediaan_unit,
//         'perubahan_mobil'         => $request->perubahan_mobil,
//         'nilai_muatan'            => $request->nilai_muatan,
//         'biaya_kirim'             => $request->biaya_kirim,
//         'cr' => $this->cleanCr($request->cr),
//         'kategori_ekspedisi'      => $request->kategori_ekspedisi,

//         'keterangan'              => $request->keterangan,

//         // Input tanggal & Hasil Rumus SLA otomatis
//         'tanggal_dpt_unit'        => $request->tanggal_dpt_unit,
//         'planning_loading'        => $request->planning_loading,
//          'planning_loading_2'        => $request->planning_loading_2,
//           'planning_loading_3'        => $request->planning_loading_3,
//       'tanggal_tiba_gudang'     => $request->tanggal_tiba_gudang,
// 'tanggal_keluar_gudang'   => $request->tanggal_keluar_gudang,

// 'tanggal_tiba_gudang_2'   => $request->tanggal_tiba_gudang_2,
// 'tanggal_keluar_gudang_2' => $request->tanggal_keluar_gudang_2,

// 'tanggal_tiba_gudang_3'   => $request->tanggal_tiba_gudang_3,
// 'tanggal_keluar_gudang_3' => $request->tanggal_keluar_gudang_3,

//         'lama_waktu_pencarian'    => $rumus['lama_waktu_pencarian'],
//         'sla_dapat_mobil'         => $rumus['sla_dapat_mobil'],
//         'status_pengiriman'       => $rumus['status_pengiriman'],

//         'lama_digudang'           => $rumus['lama_digudang'],
//         'status_gudang'                  => $rumus['status_gudang'],
//         'sla_loading'             => $rumus['sla_loading'],
//          'lama_digudang_2'           => $rumus['lama_digudang_2'],
//         'status_gudang_2'                  => $rumus['status_gudang_2'],
//         'sla_loading_2'             => $rumus['sla_loading_2'],
//          'lama_digudang_3'           => $rumus['lama_digudang_3'],
//         'status_gudang_3'                  => $rumus['status_gudang_3'],
//         'sla_loading_3'             => $rumus['sla_loading_3'],

        
//         'updated_at'              => now()
//     ];

//     // 2. PROTEKSI KRITIS: Data Excel hanya masuk ke antrean update JIKA form di web diisi
//     if ($request->filled('tujuan'))    $updateData['tujuan'] = $request->tujuan;
//     if ($request->filled('ekpedisi'))  $updateData['ekpedisi'] = $request->ekpedisi;
//     if ($request->filled('route'))     $updateData['route'] = $request->route;
//     if ($request->filled('mobil'))     $updateData['mobil'] = $request->mobil;
//     if ($request->filled('pulau'))     $updateData['pulau'] = $request->pulau;
//     if ($request->filled('area'))      $updateData['area'] = $request->area;
//     if ($request->filled('via_kirim')) $updateData['via_kirim'] = $request->via_kirim;
    
//     $updateData['nilai_muatan'] = $this->cleanMoney($request->nilai_muatan);
// $updateData['biaya_kirim'] = $this->cleanMoney($request->biaya_kirim);

//     // 3. Eksekusi SATU KALI SAJA ke database menggunakan array yang aman
//     DB::table('logistik_pengiriman')
//         ->where('id', $id)
//         ->update($updateData);
        
//     return back()->with('success', 'Data berhasil disimpan');
// }


// public function update(Request $request, $id)
// {
//     // =========================
//     // 1. HITUNG SLA
//     // =========================
//     $rumus = $this->hitungSla($request);

//     // =========================
//     // 2. BUILD DATA UPDATE
//     // =========================
//     $updateData = [
//         'tanggal_naik_logistik' => $request->tanggal_naik_logistik,
//         'rencana_kirim'         => $request->rencana_kirim,
//         'transport_lead_time'   => $request->transport_lead_time,
//         'planner'               => $request->planner,
//         'no_shipment'           => $request->no_shipment,

//         'ketersediaan_unit'     => $request->ketersediaan_unit,
//         'perubahan_mobil'       => $request->perubahan_mobil,

//         'kategori_ekspedisi'    => $request->kategori_ekspedisi,
//         'keterangan'            => $request->keterangan,

//         // TANGGAL
//         'tanggal_dpt_unit'      => $request->tanggal_dpt_unit,
//         'planning_loading'      => $request->planning_loading,
//         'planning_loading_2'    => $request->planning_loading_2,
//         'planning_loading_3'    => $request->planning_loading_3,

//         'tanggal_tiba_gudang'   => $request->tanggal_tiba_gudang,
//         'tanggal_keluar_gudang' => $request->tanggal_keluar_gudang,

//         'tanggal_tiba_gudang_2'   => $request->tanggal_tiba_gudang_2,
//         'tanggal_keluar_gudang_2' => $request->tanggal_keluar_gudang_2,

//         'tanggal_tiba_gudang_3'   => $request->tanggal_tiba_gudang_3,
//         'tanggal_keluar_gudang_3' => $request->tanggal_keluar_gudang_3,

//         // =========================
//         // SLA RESULT (AMAN STRING)
//         // =========================
//         'lama_waktu_pencarian' => (string) ($rumus['lama_waktu_pencarian'] ?? ''),
//         'sla_dapat_mobil'      => $rumus['sla_dapat_mobil'] ?? null,
//         'status_pengiriman'    => $rumus['status_pengiriman'] ?? null,

//         'lama_digudang'        => substr((string) ($rumus['lama_digudang'] ?? ''), 0, 50),
//         'status_gudang'        => $rumus['status_gudang'] ?? null,
//         'sla_loading'          => $rumus['sla_loading'] ?? null,

//         'lama_digudang_2'      => substr((string) ($rumus['lama_digudang_2'] ?? ''), 0, 50),
//         'status_gudang_2'      => $rumus['status_gudang_2'] ?? null,
//         'sla_loading_2'        => $rumus['sla_loading_2'] ?? null,

//         'lama_digudang_3'      => substr((string) ($rumus['lama_digudang_3'] ?? ''), 0, 50),
//         'status_gudang_3'      => $rumus['status_gudang_3'] ?? null,
//         'sla_loading_3'        => $rumus['sla_loading_3'] ?? null,

//         'updated_at'           => now(),
//     ];

//     // =========================
//     // 3. FIELD OPTIONAL (SAFE UPDATE)
//     // =========================
//     $optionalFields = [
//         'tujuan',
//         'ekpedisi',
//         'route',
//         'mobil',
//         'pulau',
//         'area',
//         'via_kirim',
//         'dist_channel',
//         'ekpedisi',
//         'transport_lead_time'
//     ];

//     foreach ($optionalFields as $field) {
//         if ($request->filled($field)) {
//             $updateData[$field] = $request->$field;
//         }
//     }

//     // =========================
//     // 4. CLEAN MONEY (WAJIB FINAL OVERRIDE)
//     // =========================
//     $updateData['nilai_muatan'] = $this->cleanMoney($request->nilai_muatan);
//     $updateData['biaya_kirim']  = $this->cleanMoney($request->biaya_kirim);

//     // =========================
//     // 5. CR CLEAN (ANTI SCIENTIFIC NOTATION)
//     // =========================
//     $updateData['cr'] = $this->cleanCr($request->cr);

//     // =========================
//     // 6. EXECUTE UPDATE
//     // =========================
//     DB::table('logistik_pengiriman')
//         ->where('id', $id)
//         ->update($updateData);

//     return back()->with('success', 'Data berhasil disimpan');
// }


// public function update(Request $request, $id)
// {

// $old = DB::table('logistik_pengiriman')
//     ->where('id', $id)
//     ->first();

// $request->merge([
//     'area' => $old->area
// ]);

// // ambil tanggal keluar gudang paling besar
// $keluar = collect([
//     $request->tanggal_keluar_gudang,
//     $request->tanggal_keluar_gudang_2,
//     $request->tanggal_keluar_gudang_3,
// ])
// ->filter()
// ->map(fn($v) => strtotime($v))
// ->max();

// if ($keluar && $request->transport_lead_time) {

//     $estimasi = date(
//         'Y-m-d',
//         strtotime(
//             '+' . (int)$request->transport_lead_time . ' days',
//             $keluar
//         )
//     );

//     $request->merge([
//         'estimasi_tiba' => $estimasi
//     ]);
// }

// // $rumus = $this->hitungSla($request);

//     if (!$old) {
//         return back()->with('error', 'Data tidak ditemukan');
//     }

//     // pakai area dari database
//     $request->merge([
//         'area' => $old->area
//     ]);


//     $rumus = $this->hitungSla($request);

//     // ambil data lama dulu
//     $old = DB::table('logistik_pengiriman')->where('id', $id)->first();

//     if (!$old) {
//         return back()->with('error', 'Data tidak ditemukan');
//     }

//     $oldNoShipment = $old->no_shipment;
//     $newNoShipment = $request->no_shipment;
// $updateData = [

//     'estimasi_tiba'        => $request->estimasi_tiba,
//     'tanggal_naik_logistik'=> $request->tanggal_naik_logistik,
//     'rencana_kirim'        => $request->rencana_kirim,
//     'transport_lead_time'  => $request->transport_lead_time,
//     'planner'              => $request->planner,
//     'no_shipment'          => $newNoShipment,

//     'perubahan_mobil'      => $request->perubahan_mobil,
//     'kategori_ekspedisi'   => $request->kategori_ekspedisi,
//     'keterangan'           => $request->keterangan,

//     'tanggal_dpt_unit'     => $request->tanggal_dpt_unit,
//     'planning_loading'     => $request->planning_loading,
//     'planning_loading_2'   => $request->planning_loading_2,
//     'planning_loading_3'   => $request->planning_loading_3,

//     'tujuan'               => $request->tujuan,
//     'area'                 => $request->area,
//     'via_kirim'            => $request->via_kirim,
//     'route'                => $request->route,
//     'pulau'                => $request->pulau,
//     'mobil'                => $request->mobil,
//     'ekpedisi'             => $request->ekpedisi,

//     'tanggal_tiba_gudang'    => $request->tanggal_tiba_gudang,
//     'tanggal_keluar_gudang'  => $request->tanggal_keluar_gudang,

//     'tanggal_tiba_gudang_2'  => $request->tanggal_tiba_gudang_2,
//     'tanggal_keluar_gudang_2'=> $request->tanggal_keluar_gudang_2,

//     'tanggal_tiba_gudang_3'  => $request->tanggal_tiba_gudang_3,
//     'tanggal_keluar_gudang_3'=> $request->tanggal_keluar_gudang_3,

//     'dist_channel'         => $request->dist_channel,

//     'lama_waktu_pencarian' => (string)($rumus['lama_waktu_pencarian'] ?? ''),
//     'sla_dapat_mobil'      => $rumus['sla_dapat_mobil'] ?? null,
//     'status_pengiriman'    => $rumus['status_pengiriman'] ?? null,

//     'lama_digudang'        => $rumus['lama_digudang'] ?? null,
//     'status_gudang'        => $rumus['status_gudang'] ?? null,
//     'sla_loading'          => $rumus['sla_loading'] ?? null,

//     'lama_digudang_2'      => $rumus['lama_digudang_2'] ?? null,
//     'status_gudang_2'      => $rumus['status_gudang_2'] ?? null,
//     'sla_loading_2'        => $rumus['sla_loading_2'] ?? null,

//     'lama_digudang_3'      => $rumus['lama_digudang_3'] ?? null,
//     'status_gudang_3'      => $rumus['status_gudang_3'] ?? null,
//     'sla_loading_3'        => $rumus['sla_loading_3'] ?? null,

//     'updated_at'           => now(),
// ];


// // ======================================
// // UPDATE SEMUA ROW SHIPMENT
// // ======================================

// DB::table('logistik_pengiriman')
//     ->where('no_shipment', $oldNoShipment)
//     ->orWhere('no_shipment', $newNoShipment)
//     ->update($updateData);


// // ======================================
// // UPDATE KHUSUS ROW YANG DIEDIT
// // ======================================

// DB::table('logistik_pengiriman')
//     ->where('id', $id)
//     ->update([

//         'nilai_muatan' => $this->cleanMoney($request->nilai_muatan),
//         'biaya_kirim'  => $this->cleanMoney($request->biaya_kirim),
//         'cr'           => $this->cleanCr($request->cr),

//     ]);

// return back()->with('success', 'Semua data shipment berhasil disinkronkan');
// }



// public function update(Request $request, $id)
// {
//     // ==========================
//     // Ambil data lama
//     // ==========================
//     $old = DB::table('logistik_pengiriman')
//         ->where('id', $id)
//         ->first();

//     if (!$old) {
//         return back()->with('error', 'Data tidak ditemukan');
//     }

//     // Area tetap dari database
//     // $request->merge([
//     //     'area' => $old->area
//     // ]);

//     // ==========================
//     // Hitung Estimasi Tiba
//     // ==========================
//     $keluar = collect([
//         $request->tanggal_keluar_gudang,
//         $request->tanggal_keluar_gudang_2,
//         $request->tanggal_keluar_gudang_3,
//     ])
//     ->filter()
//     ->map(fn($v) => strtotime($v))
//     ->max();

//     if ($keluar && $request->transport_lead_time) {

//         $request->merge([
//             'estimasi_tiba' => date(
//                 'Y-m-d',
//                 strtotime(
//                     '+' . (int)$request->transport_lead_time . ' days',
//                     $keluar
//                 )
//             )
//         ]);
//     }

//     // ==========================
//     // Hitung SLA
//     // ==========================

//         $request->merge([
    
//     ]);
//     $rumus = $this->hitungSla($request);

//     $oldNoShipment = $old->no_shipment;
//     $newNoShipment = $request->no_shipment;

//     // ====================================================
//     // UPDATE SEMUA ROW YANG NO SHIPMENT SAMA
//     // ====================================================

//     $updateShipment = [

//         'estimasi_tiba'         => $request->estimasi_tiba,
//         'tanggal_naik_logistik' => $request->tanggal_naik_logistik,
//         'rencana_kirim'         => $request->rencana_kirim,
//         'transport_lead_time'   => $request->transport_lead_time,
//         'planner'               => $request->planner,
//         'no_shipment'           => $newNoShipment,
        
//         'perubahan_mobil'       => $request->perubahan_mobil,
//         'kategori_ekspedisi'    => $request->kategori_ekspedisi,
//         'keterangan'            => $request->keterangan,
//          'ekpedisi'    => $request->ekpedisi,
//           'mobil'    => $request->mobil,
//         'tanggal_dpt_unit'      => $request->tanggal_dpt_unit,

//         'planning_loading'      => $request->planning_loading,
//         'tanggal_tiba_gudang'   => $request->tanggal_tiba_gudang,
//         'tanggal_keluar_gudang' => $request->tanggal_keluar_gudang,
//         'area' => $request->area,
//         'via_kirim' => $request->via_kirim,

//         'planning_loading_2'      => $request->planning_loading_2,
//         'tanggal_tiba_gudang_2'   => $request->tanggal_tiba_gudang_2,
//         'tanggal_keluar_gudang_2' => $request->tanggal_keluar_gudang_2,

//         'planning_loading_3'      => $request->planning_loading_3,
//         'tanggal_tiba_gudang_3'   => $request->tanggal_tiba_gudang_3,
//         'tanggal_keluar_gudang_3' => $request->tanggal_keluar_gudang_3,
//           'nama_driver' => $request->nama_driver,
//             'no_pol' => $request->no_pol,
//             // 'total_do_qty_car' => $request->total_do_qty_car,

//         'dist_channel' => $request->dist_channel,

//         'lama_waktu_pencarian' => $rumus['lama_waktu_pencarian'] ?? null,
//         'sla_dapat_mobil'      => $rumus['sla_dapat_mobil'] ?? null,
//         'status_pengiriman'    => $rumus['status_pengiriman'] ?? null,

//         'lama_digudang' => $rumus['lama_digudang'] ?? null,
//         'status_gudang' => $rumus['status_gudang'] ?? null,
//         'sla_loading'   => $rumus['sla_loading'] ?? null,

//         'lama_digudang_2' => $rumus['lama_digudang_2'] ?? null,
//         'status_gudang_2' => $rumus['status_gudang_2'] ?? null,
//         'sla_loading_2'   => $rumus['sla_loading_2'] ?? null,

//         'lama_digudang_3' => $rumus['lama_digudang_3'] ?? null,
//         'status_gudang_3' => $rumus['status_gudang_3'] ?? null,
//         'sla_loading_3'   => $rumus['sla_loading_3'] ?? null,
        
//         'updated_at' => now(),
//     ];
    

//     DB::table('logistik_pengiriman')
//         ->where(function ($q) use ($oldNoShipment, $newNoShipment) {
//             $q->where('no_shipment', $oldNoShipment)
//               ->orWhere('no_shipment', $newNoShipment);
//         })
//         ->update($updateShipment);

//     // ====================================================
//     // UPDATE HANYA ROW YANG DIKLIK
//     // ====================================================

//    $autoBiaya = $this->cariBiayaKirimOtomatis(
//     $request->route,
//     $request->mobil,
//     $request->ekpedisi
// );

// $updateRow = [
//     'tujuan' => $request->tujuan,
//     'route'  => $request->route,
//     'pulau'  => $request->pulau,
//     'total_do_qty_car' => $request->total_do_qty_car,

//     'mobil'      => $request->mobil,
//     'ekpedisi'   => $request->ekpedisi,
//     'via_kirim'  => $request->via_kirim,
//     'area'       => $request->area,

//     'nilai_muatan' => $this->cleanMoney($request->nilai_muatan),
//     'biaya_kirim'  => $autoBiaya !== null
//         ? $this->cleanMoney($autoBiaya)
//         : $this->cleanMoney($request->biaya_kirim),

//     'updated_at' => now(),
// ];

//     DB::table('logistik_pengiriman')
//         ->where('id', $id)
//         ->update($updateRow);
// $shipment = $newNoShipment ?: $oldNoShipment;

// $rows = DB::table('logistik_pengiriman')
//     ->where('no_shipment', $shipment)
//     ->get();

// $totalMuatan = $rows->sum(function ($r) {
//     return (float) $r->nilai_muatan;
// });

// $totalBiaya = $rows->max(function ($r) {
//     return (float) $r->biaya_kirim;
// });

// $cr = 0;

// if ($totalMuatan > 0) {
//     $cr = round(($totalBiaya / $totalMuatan) * 100, 4);
// }

// DB::table('logistik_pengiriman')
//     ->where('no_shipment', $shipment)
//     ->update([
//         'cr' => $cr
//     ]);

//     return back()->with('success', 'Data berhasil diperbarui');
// }


public function update(Request $request, $id)
{
    // ==========================
    // Ambil data lama
    // ==========================
    $old = DB::table('logistik_pengiriman')
        ->where('id', $id)
        ->first();

    if (!$old) {
        return back()->with('error', 'Data tidak ditemukan');
    }

    // ==========================
    // Hitung Estimasi Tiba
    // ==========================
    $keluar = collect([
        $request->tanggal_keluar_gudang,
        $request->tanggal_keluar_gudang_2,
        $request->tanggal_keluar_gudang_3,
    ])
    ->filter()
    ->map(fn($v) => strtotime($v))
    ->max();

    if ($keluar && $request->transport_lead_time) {

        $request->merge([
            'estimasi_tiba' => date(
                'Y-m-d',
                strtotime(
                    '+' . (int)$request->transport_lead_time . ' days',
                    $keluar
                )
            )
        ]);
    }

    // ==========================
    // Hitung SLA
    // ==========================
    $rumus = $this->hitungSla($request);

    $oldNoShipment = $old->no_shipment;
    $newNoShipment = $request->no_shipment;
    $shipment      = $newNoShipment ?: $oldNoShipment;

    // ====================================================
    // 1. UPDATE FIELD YANG SHARED UNTUK SEMUA ROW SESHIPMENT
    //    (route, mobil, ekpedisi ikut disamakan disini)
    // ====================================================
    $updateShipment = [

        'estimasi_tiba'         => $request->estimasi_tiba,
        'tanggal_naik_logistik' => $request->tanggal_naik_logistik,
        'rencana_kirim'         => $request->rencana_kirim,
        'transport_lead_time'   => $request->transport_lead_time,
        'planner'               => $request->planner,
        'no_shipment'           => $newNoShipment,

        'perubahan_mobil'       => $request->perubahan_mobil,
        'kategori_ekspedisi'    => $request->kategori_ekspedisi,
        'keterangan'            => $request->keterangan,

        'ekpedisi'              => $request->ekpedisi,
        'mobil'                 => $request->mobil,
        'route'                 => $request->route, // <-- BARU: route ikut disync

        'tanggal_dpt_unit'      => $request->tanggal_dpt_unit,

        'planning_loading'      => $request->planning_loading,
        'tanggal_tiba_gudang'   => $request->tanggal_tiba_gudang,
        'tanggal_keluar_gudang' => $request->tanggal_keluar_gudang,
        'area'                  => $request->area,
        'via_kirim'             => $request->via_kirim,

        'planning_loading_2'      => $request->planning_loading_2,
        'tanggal_tiba_gudang_2'   => $request->tanggal_tiba_gudang_2,
        'tanggal_keluar_gudang_2' => $request->tanggal_keluar_gudang_2,

        'planning_loading_3'      => $request->planning_loading_3,
        'tanggal_tiba_gudang_3'   => $request->tanggal_tiba_gudang_3,
        'tanggal_keluar_gudang_3' => $request->tanggal_keluar_gudang_3,

        'nama_driver' => $request->nama_driver,
        'no_pol'      => $request->no_pol,

        'dist_channel' => $request->dist_channel,

        'lama_waktu_pencarian' => $rumus['lama_waktu_pencarian'] ?? null,
        'sla_dapat_mobil'      => $rumus['sla_dapat_mobil'] ?? null,
        'status_pengiriman'    => $rumus['status_pengiriman'] ?? null,

        'lama_digudang' => $rumus['lama_digudang'] ?? null,
        'status_gudang' => $rumus['status_gudang'] ?? null,
        'sla_loading'   => $rumus['sla_loading'] ?? null,

        'lama_digudang_2' => $rumus['lama_digudang_2'] ?? null,
        'status_gudang_2' => $rumus['status_gudang_2'] ?? null,
        'sla_loading_2'   => $rumus['sla_loading_2'] ?? null,

        'lama_digudang_3' => $rumus['lama_digudang_3'] ?? null,
        'status_gudang_3' => $rumus['status_gudang_3'] ?? null,
        'sla_loading_3'   => $rumus['sla_loading_3'] ?? null,

        'updated_at' => now(),
    ];

    DB::table('logistik_pengiriman')
        ->where(function ($q) use ($oldNoShipment, $newNoShipment) {
            $q->where('no_shipment', $oldNoShipment)
              ->orWhere('no_shipment', $newNoShipment);
        })
        ->update($updateShipment);

    // ====================================================
    // 2. AUTO HITUNG BIAYA KIRIM DARI ROUTE + MOBIL + EKPEDISI
    //    YANG SUDAH DISYNC, LALU TERAPKAN KE SEMUA ROW SESHIPMENT
    // ====================================================
    $autoBiaya = $this->cariBiayaKirimOtomatis(
        $request->route,
        $request->mobil,
        $request->ekpedisi
    );

    if ($autoBiaya !== null) {
        DB::table('logistik_pengiriman')
            ->where('no_shipment', $shipment)
            ->update([
                'biaya_kirim' => $this->cleanMoney($autoBiaya),
                'updated_at'  => now(),
            ]);
    }

    // ====================================================
    // 3. UPDATE FIELD YANG SPESIFIK PER ROW / PER TUJUAN
    // ====================================================
    $updateRow = [
        'tujuan'           => $request->tujuan,
        'pulau'            => $request->pulau,
        'total_do_qty_car' => $request->total_do_qty_car,
        'nilai_muatan'     => $this->cleanMoney($request->nilai_muatan),
        'updated_at'       => now(),
    ];

    // kalau tarif route/mobil/ekpedisi row ini gak ketemu di master,
    // biarin biaya_kirim manual dari input tetap kepakai buat row ini
    if ($autoBiaya === null) {
        $updateRow['biaya_kirim'] = $this->cleanMoney($request->biaya_kirim);
    }

    DB::table('logistik_pengiriman')
        ->where('id', $id)
        ->update($updateRow);

    // ====================================================
    // 4. HITUNG ULANG CR PER ROW (PROPORSIONAL PER TUJUAN)
    // ====================================================
    $rows = DB::table('logistik_pengiriman')
        ->where('no_shipment', $shipment)
        ->get();

    $totalMuatan = $rows->sum(function ($r) {
        return (float) $r->nilai_muatan;
    });

    $totalBiaya = $rows->max(function ($r) {
        return (float) $r->biaya_kirim;
    });

    foreach ($rows as $r) {

        $crRow = 0;
        $nilaiMuatanRow = (float) $r->nilai_muatan;

        if ($totalMuatan > 0 && $nilaiMuatanRow > 0) {
            $kontribusi = $nilaiMuatanRow / $totalMuatan;
            $totalCR    = ($totalBiaya / $totalMuatan) * 100;
            $crRow      = $kontribusi * $totalCR;
        }

        DB::table('logistik_pengiriman')
            ->where('id', $r->id)
            ->update([
                'cr' => round($crRow, 4)
            ]);
    }

    return back()->with('success', 'Data berhasil diperbarui');
}
//     $updateData = [

//      'estimasi_tiba' => $request->estimasi_tiba,
//         'tanggal_naik_logistik' => $request->tanggal_naik_logistik,
//         'rencana_kirim'         => $request->rencana_kirim,
//         'transport_lead_time'   => $request->transport_lead_time,
//         'planner'               => $request->planner,
//         'no_shipment'           => $newNoShipment,

//         'perubahan_mobil'       => $request->perubahan_mobil,
//         'kategori_ekspedisi'    => $request->kategori_ekspedisi,
//         'keterangan'            => $request->keterangan,

//         'tanggal_dpt_unit'      => $request->tanggal_dpt_unit,
//         'planning_loading'      => $request->planning_loading,
//         'planning_loading_2'    => $request->planning_loading_2,
//         'planning_loading_3'    => $request->planning_loading_3,
//                 'tujuan'            => $request->tujuan,
//             'area'            => $request->area,
//               'via_kirim'            => $request->via_kirim,
//                 'route'            => $request->route,
//                   'pulau'            => $request->pulau,
//                     'mobil'            => $request->mobil,
//                       'ekpedisi'            => $request->ekpedisi,


//         'tanggal_tiba_gudang'   => $request->tanggal_tiba_gudang,
//         'tanggal_keluar_gudang' => $request->tanggal_keluar_gudang,

//         'tanggal_tiba_gudang_2'   => $request->tanggal_tiba_gudang_2,
//         'tanggal_keluar_gudang_2' => $request->tanggal_keluar_gudang_2,

//         'tanggal_tiba_gudang_3'   => $request->tanggal_tiba_gudang_3,
//         'tanggal_keluar_gudang_3' => $request->tanggal_keluar_gudang_3,
//              'dist_channel'   => $request->dist_channel,
//             //    'nilai_muatan'   => $request->Nilai_muatan,
//             //      'biaya_kirim'   => $request->biaya_kirim,

//         'lama_waktu_pencarian' => (string) ($rumus['lama_waktu_pencarian'] ?? ''),
//         'sla_dapat_mobil'      => $rumus['sla_dapat_mobil'] ?? null,
//         'status_pengiriman'    => $rumus['status_pengiriman'] ?? null,

//         'lama_digudang'        => $rumus['lama_digudang'] ?? null,
//         'status_gudang'        => $rumus['status_gudang'] ?? null,
//         'sla_loading'          => $rumus['sla_loading'] ?? null,

//         'lama_digudang_2'      => $rumus['lama_digudang_2'] ?? null,
//         'status_gudang_2'      => $rumus['status_gudang_2'] ?? null,
//         'sla_loading_2'        => $rumus['sla_loading_2'] ?? null,

//         'lama_digudang_3'      => $rumus['lama_digudang_3'] ?? null,
//         'status_gudang_3'      => $rumus['status_gudang_3'] ?? null,
//         'sla_loading_3'        => $rumus['sla_loading_3'] ?? null,

//         'updated_at'           => now(),
//     ];

//     // clean money
//     $updateData['nilai_muatan'] = $this->cleanMoney($request->nilai_muatan);
//     $updateData['biaya_kirim']  = $this->cleanMoney($request->biaya_kirim);
//     $updateData['cr']           = $this->cleanCr($request->cr);

//     /**
//      * 🔥 INI KUNCI UTAMA:
//      * update semua row dengan no_shipment lama ATAU baru
//      */
//     DB::table('logistik_pengiriman')
//         ->where('no_shipment', $oldNoShipment)
//         ->orWhere('no_shipment', $newNoShipment)
//         ->update($updateData);

//     return back()->with('success', 'Semua data shipment berhasil disinkronkan');
// }
private function cleanCr($value)
{
    if (!$value) return null;

    // ambil angka saja (buang %, teks, dll)
    $value = preg_replace('/[^0-9.]/', '', $value);

    return is_numeric($value) ? (float) $value : null;
}

private function cleanMoney($value)
{
    if (!$value) return null;

    // buang semua selain angka
    return (int) preg_replace('/[^0-9]/', '', $value);
}
public function dataLogistik()
{
    $logistik = LogistikPengiriman::orderByRaw('CAST(no_shipment AS UNSIGNED) ASC')
        ->get();

    // ==== FILTER ATAS (dari data transaksi, biarin seperti semula) ====
    $planners = LogistikPengiriman::whereNotNull('planner')
        ->where('planner', '!=', '')
        ->distinct()
        ->orderBy('planner')
        ->pluck('planner');

    // ==== MASTER DATA DARI tujuanfillterr ====
    $tujuanList = DB::table('tujuanfillterr')
        ->whereNotNull('tujuan')->where('tujuan', '!=', '')
        ->distinct()->orderBy('tujuan')->pluck('tujuan');

    $pulauList = DB::table('tujuanfillterr')
        ->whereNotNull('pulau')->where('pulau', '!=', '')
        ->distinct()->orderBy('pulau')->pluck('pulau');

    $areas = DB::table('tujuanfillterr')
        ->whereNotNull('area')->where('area', '!=', '')
        ->distinct()->orderBy('area')->pluck('area');

    $distChannelList = DB::table('tujuanfillterr')
        ->whereNotNull('dist_channel')->where('dist_channel', '!=', '')
        ->distinct()->orderBy('dist_channel')->pluck('dist_channel');

    // Opsional: kalau mau planner & monitoring juga ambil dari tujuanfillterr
    // $plannerListMaster = DB::table('tujuanfillterr')
    //     ->whereNotNull('Planner')->where('Planner', '!=', '')
    //     ->distinct()->orderBy('Planner')->pluck('Planner');

    // ==== MASTER DATA DARI tarif_pengiriman ====
    $ekpedisiList = DB::table('tarif_pengiriman')
        ->whereNotNull('ekpedisi')->where('ekpedisi', '!=', '')
        ->distinct()->orderBy('ekpedisi')->pluck('ekpedisi');

    $mobilList = DB::table('tarif_pengiriman')
        ->whereNotNull('mobil')->where('mobil', '!=', '')
        ->distinct()->orderBy('mobil')->pluck('mobil');

    $routeList = DB::table('tarif_pengiriman')
        ->whereNotNull('route')->where('route', '!=', '')
        ->distinct()->orderBy('route')->pluck('route');

        $tarifPengiriman = DB::table('tarif_pengiriman')
    ->select('route', 'mobil', 'ekpedisi', 'biaya_kirim')
    ->whereNotNull('route')
    ->whereNotNull('mobil')
    ->get();

    return view(
        'planner.data_planner',
        compact(
            'logistik',
            'ekpedisiList',
            'tujuanList',
            'mobilList',
            'pulauList',
            'routeList',
            'distChannelList',
            'planners',
            'areas',
             'tarifPengiriman'
        )
    );
}

private function cariBiayaKirimOtomatis($route, $mobil, $ekpedisi = null)
{
    if (!$route || !$mobil) {
        return null;
    }

    $normalize = function ($v) {
        if (!$v) return '';
        $v = str_replace("\xc2\xa0", ' ', $v);      // non-breaking space
        $v = preg_replace('/\s*-\s*/', '-', $v);     // rapikan spasi di sekitar strip
        $v = preg_replace('/\s+/', ' ', trim($v));   // rapikan spasi ganda
        return mb_strtolower($v);
    };

    $routeKey    = $normalize($route);
    $mobilKey    = $normalize($mobil);
    $ekpedisiKey = $ekpedisi ? $normalize($ekpedisi) : '';

    $candidates = DB::table('tarif_pengiriman')
        ->whereNotNull('route')
        ->whereNotNull('mobil')
        ->get()
        ->filter(fn ($t) => $normalize($t->route) === $routeKey);

    if ($candidates->isEmpty()) {
        return null;
    }

    // 1. Coba match ketat: route + mobil + ekpedisi
    if ($ekpedisiKey !== '') {
        $strict = $candidates->first(function ($t) use ($normalize, $ekpedisiKey, $mobilKey) {
            return $normalize($t->ekpedisi) === $ekpedisiKey
                && str_starts_with($normalize($t->mobil), $mobilKey);
        });

        if ($strict) {
            return $strict->biaya_kirim;
        }
    }

    // 2. Fallback: route + mobil saja
    $fallback = $candidates->first(fn ($t) => str_starts_with($normalize($t->mobil), $mobilKey));

    return $fallback->biaya_kirim ?? null;
}


// public function dataLogistik()
// {
//     $logistik = LogistikPengiriman::orderByRaw('CAST(no_shipment AS UNSIGNED) ASC')
//         ->get();

//    $planners = LogistikPengiriman::whereNotNull('planner')
//     ->where('planner','!=','')
//     ->distinct()
//     ->orderBy('planner')
//     ->pluck('planner');

// $areas = LogistikPengiriman::whereNotNull('area')
//     ->where('area','!=','')
//     ->distinct()
//     ->orderBy('area')
//     ->pluck('area');

//   $ekpedisiList = DB::table('tarif_pengiriman')
//     ->whereNotNull('ekpedisi')->where('ekpedisi','!=')
//     ->distinct()->orderBy('ekpedisi')->pluck('ekpedisi');

// $tujuanList = DB::table('tujuanfillterr')
//     ->whereNotNull('tujuan')->where('tujuan','!=')
//     ->distinct()->orderBy('tujuan')->pluck('tujuan');

// $mobilList = DB::table('tarif_pengiriman')
//     ->whereNotNull('mobil')->where('mobil','!=')
//     ->distinct()->orderBy('mobil')->pluck('mobil');

// $routeList = DB::table('tarif_pengiriman')
//     ->whereNotNull('route')->where('route','!=')
//     ->distinct()->orderBy('route')->pluck('route');

// $pulauList = DB::table('tujuanfillterr')
//     ->whereNotNull('pulau')->where('pulau','!=')
//     ->distinct()->orderBy('pulau')->pluck('pulau');

//     $distChannelList = LogistikPengiriman::whereNotNull('dist_channel')
//     ->where('dist_channel', '!=', '')
//     ->distinct()
//     ->orderBy('dist_channel')
//     ->pluck('dist_channel');

// return view(
//     'planner.data_planner',
//     compact(
//         'logistik',
//         'ekpedisiList',
//          'tujuanList',
//           'mobilList',
//           'pulauList',
//           'routeList',
//         'planners',
//         'areas'
//     )
// );
// }

private function getTibaGudangTerdekatRequest($request)
{
    return collect([
        $request->tanggal_tiba_gudang,
        $request->tanggal_tiba_gudang_2,
        $request->tanggal_tiba_gudang_3,
    ])
    ->filter()
    ->sort()
    ->first();
}


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

    // =====================================================
    // FUNCTION HITUNG SELISIH (NO CARBON)
    // =====================================================



    $hitungSelisih = function ($start, $end) {

        if (!$start || !$end) return null;

        $awal  = new \DateTime($start);
        $akhir = new \DateTime($end);

        if ($akhir < $awal) {
            return [
                'text' => '0 Menit',
                'days' => 0,
                'hours' => 0,
                'minutes' => 0,
                'seconds' => 0
            ];
        }

        $selisih = $akhir->getTimestamp() - $awal->getTimestamp();

        $days    = floor($selisih / 86400);
        $hours   = floor(($selisih % 86400) / 3600);
        $minutes = floor(($selisih % 3600) / 60);

        $text = '';

        if ($days > 0) {
            $text .= $days . ' Hari ';
        }

        if ($hours > 0) {
            $text .= $hours . ' Jam ';
        }

        $text .= $minutes . ' Menit';

        return [
            'text'    => trim($text),
            'days'    => $days,
            'hours'   => $hours,
            'minutes' => $minutes
        ];
    };

    // =====================================================
    // AMBIL TIBA GUDANG TERCEPAT (GLOBAL)
    // =====================================================
    $tibaGudang = collect([
        $request->tanggal_tiba_gudang,
        $request->tanggal_tiba_gudang_2,
        $request->tanggal_tiba_gudang_3,
    ])->filter()->sort()->first();

    // =====================================================
// 1. SLA DAPAT MOBIL (pakai rencana_kirim)
// =====================================================
// 1. SLA DAPAT MOBIL (FIX: dari rencana kirim ke tanggal dpt unit)
// =====================================================
// INI sla lama pencarian yg sebelum area
// $start = $request->rencana_kirim
//     ? date('Y-m-d H:i:s', strtotime($request->rencana_kirim))
//     : null;

// $end = $request->tanggal_dpt_unit
//     ? date('Y-m-d H:i:s', strtotime($request->tanggal_dpt_unit))
//     : null;

// $diff = $hitungSelisih($start, $end);

// // WAJIB selalu di-set (biar tidak stuck nilai lama)
// $data['lama_waktu_pencarian'] = $diff['text'] ?? null;

// // SLA LOGIC (pakai tanggal saja: sama hari masih On Time)
// if ($start && $end) {

//     $tanggalRencana = date('Y-m-d', strtotime($start));
//     $tanggalDptUnit = date('Y-m-d', strtotime($end));

//     if ($tanggalDptUnit > $tanggalRencana) {
//         $data['sla_dapat_mobil']   = 'Delay';
//         $data['status_pengiriman'] = 'Terlambat';
//     } else {
//         $data['sla_dapat_mobil']   = 'On Time';
//         $data['status_pengiriman'] = 'Sudah Dapat';
//     }

// } else {
//     $data['sla_dapat_mobil']   = null;
//     $data['status_pengiriman'] = null;
// }

// setelah area

// =====================================================
// 1. SLA DAPAT MOBIL
// =====================================================

$start = $request->rencana_kirim
    ? date('Y-m-d H:i:s', strtotime($request->rencana_kirim))
    : null;

$end = $request->tanggal_dpt_unit
    ? date('Y-m-d H:i:s', strtotime($request->tanggal_dpt_unit))
    : null;

$diff = $hitungSelisih($start, $end);

$data['lama_waktu_pencarian'] = $diff['text'] ?? null;

if ($start && $end) {

    $area = strtoupper(trim($request->area ?? ''));

    // Hitung berdasarkan TANGGAL saja (abaikan jam)
    $tanggalRencana = strtotime(date('Y-m-d', strtotime($start)));
    $tanggalDptUnit = strtotime(date('Y-m-d', strtotime($end)));

    $selisihHari = floor(
        ($tanggalDptUnit - $tanggalRencana) / 86400
    );

    // Tentukan batas SLA
   if (
    $area == 'JABODETABEK' ||
    $area == 'JABODEBEK' ||
    $area == 'BANTEN'
) {
    // H+0
    $batasHari = 0;


    } elseif ($area == 'JAWA_BARAT') {

        // H+1
        $batasHari = 1;

    } else {

        // Semua area lainnya H+2
        $batasHari = 2;

    }

    if ($selisihHari > $batasHari) {

        $data['sla_dapat_mobil']   = 'Delay';
        $data['status_pengiriman'] = 'Terlambat';

    } else {

        $data['sla_dapat_mobil']   = 'On Time';
        $data['status_pengiriman'] = 'Sudah Dapat';

    }

} else {

    $data['sla_dapat_mobil']   = null;
    $data['status_pengiriman'] = null;

}    // =====================================================
    // 2. GUDANG 1
    // =====================================================
// =====================================================
// 2. GUDANG 1
// =====================================================
if ($request->planning_loading && $request->tanggal_tiba_gudang) {

    $diff = $hitungSelisih(
        $request->planning_loading,
        $request->tanggal_tiba_gudang
    );



    if ($diff) {

        $data['lama_digudang'] = $diff['text'];

        if ($diff['days'] > 0) {
            $data['status_gudang'] = 'Delay';
            $data['sla_loading']   = 'H+' . $diff['days'];
            
        } else {
            $data['status_gudang'] = 'On Time';
            $data['sla_loading']   = 'Sesuai SLA';
        }
    }
}

// =====================================================
// 3. GUDANG 2
// =====================================================
if ($request->planning_loading_2 && $request->tanggal_tiba_gudang_2) {

    $diff = $hitungSelisih(
        $request->planning_loading_2,
        $request->tanggal_tiba_gudang_2
    );

    if ($diff) {

        $data['lama_digudang_2'] = $diff['text'];

        if ($diff['days'] > 0) {
            $data['status_gudang_2'] = 'Delay';
            $data['sla_loading_2']   = 'H+' . $diff['days'];
        } else {
            $data['status_gudang_2'] = 'On Time';
            $data['sla_loading_2']   = 'Sesuai SLA';
        }
    }
}

// =====================================================
// 4. GUDANG 3
// =====================================================
if ($request->planning_loading_3 && $request->tanggal_tiba_gudang_3) {

    $diff = $hitungSelisih(
        $request->planning_loading_3,
        $request->tanggal_tiba_gudang_3
    );

    if ($diff) {

        $data['lama_digudang_3'] = $diff['text'];

        if ($diff['days'] > 0) {
            $data['status_gudang_3'] = 'Delay';
            $data['sla_loading_3']   = 'H+' . $diff['days'];
        } else {
            $data['status_gudang_3'] = 'On Time';
            $data['sla_loading_3']   = 'Sesuai SLA';
        }
    }
}

    return $data;
}
// private function hitungSla($request)
// {
//     $data = [
//         'lama_waktu_pencarian' => null,
//         'sla_dapat_mobil'      => null,
//         'status_pengiriman'    => null,

//         'lama_digudang'        => null,
//         'status_gudang'        => null,
//         'sla_loading'          => null,

//         'lama_digudang_2'      => null,
//         'status_gudang_2'      => null,
//         'sla_loading_2'        => null,

//         'lama_digudang_3'      => null,
//         'status_gudang_3'      => null,
//         'sla_loading_3'        => null,
//     ];

//     // =====================================================
//     // AMBIL TIBA GUDANG TERCEPAT (GLOBAL)
//     // =====================================================
//     $tibaGudang = collect([
//         $request->tanggal_tiba_gudang,
//         $request->tanggal_tiba_gudang_2,
//         $request->tanggal_tiba_gudang_3,
//     ])->filter()->sort()->first();

//     // =====================================================
//     // 1. SLA DAPAT MOBIL
//     // =====================================================
//     if ($request->tanggal_dpt_unit && $tibaGudang) {

//         $awal  = new \DateTime($request->tanggal_dpt_unit);
//         $akhir = new \DateTime($tibaGudang);

//         $awalCek  = (clone $awal)->setTime(0,0,0);
//         $akhirCek = (clone $akhir)->setTime(0,0,0);

//         if ($akhir >= $awal) {

//             $selisihDetik = $akhir->getTimestamp() - $awal->getTimestamp();

//             $jam   = floor($selisihDetik / 3600);
//             $menit = floor(($selisihDetik % 3600) / 60);

//             $data['lama_waktu_pencarian'] = "{$jam} Jam {$menit} Menit";

//             if ($awalCek < $akhirCek) {
//                 $data['sla_dapat_mobil']   = 'Delay';
//                 $data['status_pengiriman'] = 'Terlambat';
//             } else {
//                 $data['sla_dapat_mobil']   = 'On Time';
//                 $data['status_pengiriman'] = 'Sudah Dapat';
//             }

//         } else {
//             $data['lama_waktu_pencarian'] = "0 Jam 0 Menit";
//             $data['sla_dapat_mobil']      = 'On Time';
//             $data['status_pengiriman']    = 'Sudah Dapat';
//         }
//     }

//     // =====================================================
//     // 2. GUDANG 1 (FIX UTAMA DI SINI)
//     // =====================================================
//     // if ($request->tanggal_tiba_gudang && $request->tanggal_keluar_gudang) {

//     //     $awal  = new \DateTime($request->tanggal_tiba_gudang);
//     //     $akhir = new \DateTime($request->tanggal_keluar_gudang);

//     //     if ($akhir >= $awal) {

//     //         $selisihDetik = $akhir->getTimestamp() - $awal->getTimestamp();

//     //         $jam   = floor($selisihDetik / 3600);
//     //         $menit = floor(($selisihDetik % 3600) / 60);

//     //         $data['lama_digudang'] = "{$jam} Jam {$menit} Menit";

//     //         // SLA: lebih dari 1 hari = delay
//     //         if ($selisihDetik > 86400) {

//     //             $hari = floor($selisihDetik / 86400);

//     //             $data['status_gudang'] = 'Delay';
//     //             $data['sla_loading']   = 'H+' . $hari;

//     //         } else {

//     //             $data['status_gudang'] = 'On Time';
//     //             $data['sla_loading']   = 'Sesuai SLA';
//     //         }

//     //     } else {
//     //         $data['lama_digudang']  = "0 Jam 0 Menit";
//     //         $data['status_gudang']  = 'On Time';
//     //         $data['sla_loading']    = 'Sesuai SLA';
//     //     }
//     // }

//    if ($request->tanggal_tiba_gudang && $request->tanggal_keluar_gudang) {

//         $awal  = new \DateTime($request->tanggal_tiba_gudang);
//         $akhir = new \DateTime($request->tanggal_keluar_gudang);

//         if ($akhir >= $awal) {

//             $diff = $awal->diff($akhir);

//             $data['lama_digudang'] = $diff->days > 0
//                 ? "{$diff->days} Hari {$diff->h} Jam"
//                 : "{$diff->h} Jam";

//             $data['status_gudang'] = $diff->days > 0 ? 'Delay' : 'On Time';
//             $data['sla_loading']    = $diff->days > 0 ? 'H+' . $diff->days : 'Sesuai SLA';
//         }
//     }


//     // =====================================================
//     // 3. GUDANG 2 (SUDAH BENAR, SEDIKIT DIRAPIKAN)
//     // =====================================================
//     if ($request->tanggal_tiba_gudang_2 && $request->tanggal_keluar_gudang_2) {

//         $awal  = new \DateTime($request->tanggal_tiba_gudang_2);
//         $akhir = new \DateTime($request->tanggal_keluar_gudang_2);

//         if ($akhir >= $awal) {

//             $diff = $awal->diff($akhir);

//             $data['lama_digudang_2'] = $diff->days > 0
//                 ? "{$diff->days} Hari {$diff->h} Jam"
//                 : "{$diff->h} Jam";

//             $data['status_gudang_2'] = $diff->days > 0 ? 'Delay' : 'On Time';
//             $data['sla_loading_2']    = $diff->days > 0 ? 'H+' . $diff->days : 'Sesuai SLA';
//         }
//     }

//     // =====================================================
//     // 4. GUDANG 3 (SUDAH BENAR)
//     // =====================================================
//     if ($request->tanggal_tiba_gudang_3 && $request->tanggal_keluar_gudang_3) {

//         $awal  = new \DateTime($request->tanggal_tiba_gudang_3);
//         $akhir = new \DateTime($request->tanggal_keluar_gudang_3);

//         if ($akhir >= $awal) {

//             $diff = $awal->diff($akhir);

//             $data['lama_digudang_3'] = $diff->days > 0
//                 ? "{$diff->days} Hari {$diff->h} Jam"
//                 : "{$diff->h} Jam";

//             $data['status_gudang_3'] = $diff->days > 0 ? 'Delay' : 'On Time';
//             $data['sla_loading_3']    = $diff->days > 0 ? 'H+' . $diff->days : 'Sesuai SLA';
//         }
//     }

//     return $data;
// }

public function autosaveRow(Request $request, $id)
{
    // ==========================
    // Ambil data lama
    // ==========================
    $old = DB::table('logistik_pengiriman')
        ->where('id', $id)
        ->first();

    if (!$old) {
        return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
    }

    $oldNoShipment = $old->no_shipment;
    $newNoShipment = $request->no_shipment;
    $shipment      = $newNoShipment ?: $oldNoShipment;

    $autoBiaya = $this->cariBiayaKirimOtomatis(
        $request->route,
        $request->mobil,
        $request->ekpedisi
    );

    $biayaKirim = $autoBiaya !== null
        ? $this->cleanMoney($autoBiaya)
        : $this->cleanMoney($request->biaya_kirim);

    // ====================================================
    // 1. UPDATE FIELD SHARED UNTUK SEMUA ROW SESHIPMENT
    //    (field detail shipment: tanggal, rute, ekspedisi, driver, dll)
    // ====================================================
    $updateShipment = [
        'planner' => $request->planner,
        'no_shipment' => $newNoShipment,

        'tanggal_naik_logistik' => $request->tanggal_naik_logistik,
        'rencana_kirim' => $request->rencana_kirim,
        'tanggal_dpt_unit' => $request->tanggal_dpt_unit,

        'planning_loading' => $request->planning_loading,
        'tanggal_tiba_gudang' => $request->tanggal_tiba_gudang,
        'tanggal_keluar_gudang' => $request->tanggal_keluar_gudang,

        'planning_loading_2' => $request->planning_loading_2,
        'tanggal_tiba_gudang_2' => $request->tanggal_tiba_gudang_2,
        'tanggal_keluar_gudang_2' => $request->tanggal_keluar_gudang_2,

        'planning_loading_3' => $request->planning_loading_3,
        'tanggal_tiba_gudang_3' => $request->tanggal_tiba_gudang_3,
        'tanggal_keluar_gudang_3' => $request->tanggal_keluar_gudang_3,

        'tujuan' => $request->tujuan,
        'route' => $request->route,
        'pulau' => $request->pulau,
        'area' => $request->area,
        'via_kirim' => $request->via_kirim,

        'dist_channel' => $request->dist_channel,
        'kategori_ekspedisi' => $request->kategori_ekspedisi,
        'ekpedisi' => $request->ekpedisi,
        'transport_lead_time' => $request->transport_lead_time,

        'nama_driver' => $request->nama_driver,
        'no_pol' => $request->no_pol,
        'mobil' => $request->mobil,

        'updated_at' => now(),
    ];

    DB::table('logistik_pengiriman')
        ->where(function ($q) use ($oldNoShipment, $newNoShipment) {
            $q->where('no_shipment', $oldNoShipment)
              ->orWhere('no_shipment', $newNoShipment);
        })
        ->update($updateShipment);

    // ====================================================
    // 2. SYNC BIAYA KIRIM AUTO KE SEMUA ROW SESHIPMENT
    //    (kalau ketemu tarifnya di master)
    // ====================================================
    if ($autoBiaya !== null) {
        DB::table('logistik_pengiriman')
            ->where('no_shipment', $shipment)
            ->update([
                'biaya_kirim' => $biayaKirim,
                'updated_at'  => now(),
            ]);
    }

    // ====================================================
    // 3. UPDATE FIELD SPESIFIK PER ROW (per unit/per tujuan)
    // ====================================================
    $updateRow = [
        'total_do_qty_car' => $request->total_do_qty_car,
        'nilai_muatan' => $this->cleanMoney($request->nilai_muatan),
        'updated_at' => now(),
    ];

    // kalau tarif otomatis gak ketemu, biaya_kirim manual tetap dipakai khusus row ini
    if ($autoBiaya === null) {
        $updateRow['biaya_kirim'] = $biayaKirim;
    }

    DB::table('logistik_pengiriman')
        ->where('id', $id)
        ->update($updateRow);

    // ====================================================
    // 4. HITUNG ULANG CR PROPORSIONAL SESHIPMENT
    // ====================================================
    $rows = DB::table('logistik_pengiriman')
        ->where('no_shipment', $shipment)
        ->get();

    $totalMuatan = $rows->sum(fn($r) => (float) $r->nilai_muatan);
    $totalBiaya  = $rows->max(fn($r) => (float) $r->biaya_kirim);

    foreach ($rows as $r) {
        $crRow = 0;
        $nilaiMuatanRow = (float) $r->nilai_muatan;

        if ($totalMuatan > 0 && $nilaiMuatanRow > 0) {
            $kontribusi = $nilaiMuatanRow / $totalMuatan;
            $totalCR    = ($totalBiaya / $totalMuatan) * 100;
            $crRow      = $kontribusi * $totalCR;
        }

        DB::table('logistik_pengiriman')
            ->where('id', $r->id)
            ->update(['cr' => round($crRow, 4)]);
    }

    return response()->json([
        'success' => true,
        'biaya_kirim' => $biayaKirim,
    ]);
}

public function slaOntime(Request $request)
{
    $query = DB::table('logistik_pengiriman')
        ->where(function ($q) {
            $q->whereNotNull('tanggal_tiba_gudang')
              ->orWhereNotNull('tanggal_tiba_gudang_2')
              ->orWhereNotNull('tanggal_tiba_gudang_3');
        });

    if ($request->filled('bulan')) {
        $query->where(function ($q) use ($request) {
            $q->whereMonth('tanggal_tiba_gudang', $request->bulan)
              ->orWhereMonth('tanggal_tiba_gudang_2', $request->bulan)
              ->orWhereMonth('tanggal_tiba_gudang_3', $request->bulan);
        });
    }

    if ($request->filled('tahun')) {
        $query->where(function ($q) use ($request) {
            $q->whereYear('tanggal_tiba_gudang', $request->tahun)
              ->orWhereYear('tanggal_tiba_gudang_2', $request->tahun)
              ->orWhereYear('tanggal_tiba_gudang_3', $request->tahun);
        });
    }

    if ($request->filled('area')) {
        $query->where('area', $request->area);
    }

    $list = $query->get()
      ->map(function ($row) {

    $sla = $this->hitungSla($row);

    $row->lama_waktu_pencarian = $sla['lama_waktu_pencarian'];
    $row->sla_dapat_mobil      = $sla['sla_dapat_mobil'];
    $row->status_pengiriman    = $sla['status_pengiriman'];

    $row->lama_digudang        = $sla['lama_digudang'];
    $row->status_gudang        = $sla['status_gudang'];
    $row->sla_loading          = $sla['sla_loading'];

    $row->lama_digudang_2      = $sla['lama_digudang_2'];
    $row->status_gudang_2      = $sla['status_gudang_2'];
    $row->sla_loading_2        = $sla['sla_loading_2'];

    $row->lama_digudang_3      = $sla['lama_digudang_3'];
    $row->status_gudang_3      = $sla['status_gudang_3'];
    $row->sla_loading_3        = $sla['sla_loading_3'];

    // =========================
    // GUDANG TIBA
    // =========================
    $gudang = [];

    if (!empty($row->tanggal_tiba_gudang)) {
        $gudang[1] = strtotime($row->tanggal_tiba_gudang);
    }

    if (!empty($row->tanggal_tiba_gudang_2)) {
        $gudang[2] = strtotime($row->tanggal_tiba_gudang_2);
    }

    if (!empty($row->tanggal_tiba_gudang_3)) {
        $gudang[3] = strtotime($row->tanggal_tiba_gudang_3);
    }

    $row->gudang_sla = count($gudang)
        ? array_search(min($gudang), $gudang)
        : null;

    // =========================
    // GUDANG KELUAR TERAKHIR
    // =========================
    $keluar = [];

    if (!empty($row->tanggal_keluar_gudang)) {
        $keluar[1] = strtotime($row->tanggal_keluar_gudang);
    }

    if (!empty($row->tanggal_keluar_gudang_2)) {
        $keluar[2] = strtotime($row->tanggal_keluar_gudang_2);
    }

    if (!empty($row->tanggal_keluar_gudang_3)) {
        $keluar[3] = strtotime($row->tanggal_keluar_gudang_3);
    }

    $row->gudang_keluar_terakhir = count($keluar)
        ? array_search(max($keluar), $keluar)
        : null;

    return $row;
})
        ->values();

    $list_area = DB::table('logistik_pengiriman')
        ->select('area')
        ->whereNotNull('area')
        ->groupBy('area')
        ->orderBy('area')
        ->get();

    return view('planner.sla_ontime', compact('list', 'list_area'));
}
public function slaDelay(Request $request)
{
    $query = DB::table('logistik_pengiriman')
        ->whereNotNull('rencana_kirim')
        ->whereRaw("TRIM(rencana_kirim) <> ''")
        ->whereNotNull('tanggal_dpt_unit')
        ->whereRaw("TRIM(tanggal_dpt_unit) <> ''")
        ->where(function ($q) {
            $q->whereNull('tanggal_tiba_gudang')
              ->orWhere('tanggal_tiba_gudang', '');
        })
        ->where(function ($q) {
            $q->whereNull('tanggal_tiba_gudang_2')
              ->orWhere('tanggal_tiba_gudang_2', '');
        })
        ->where(function ($q) {
            $q->whereNull('tanggal_tiba_gudang_3')
              ->orWhere('tanggal_tiba_gudang_3', '');
        });

    if ($request->filled('bulan')) {
        $query->whereMonth('tanggal_naik_logistik', $request->bulan);
    }

    if ($request->filled('tahun')) {
        $query->whereYear('tanggal_naik_logistik', $request->tahun);
    }

    if ($request->filled('area')) {
        $query->where('area', $request->area);
    }

   $list = $query
    ->orderBy('tanggal_naik_logistik', 'DESC')
    ->paginate(10)
    ->withQueryString();

    $list_area = DB::table('logistik_pengiriman')
        ->select('area')
        ->whereNotNull('area')
        ->groupBy('area')
        ->orderBy('area')
        ->get();

    return view('planner.sla_delay', [
        'title' => 'SLA DELAY',
        'list' => $list,
        'list_area' => $list_area
    ]);
}
public function updateGudang23(Request $request)
{
    $request->validate([
        'no_shipment' => 'required'
    ]);

    // hitung SLA ulang
    $sla = $this->hitungSla($request);

    $data = [

        'tanggal_tiba_gudang_2'   => $request->tanggal_tiba_gudang_2,
        'tanggal_keluar_gudang_2' => $request->tanggal_keluar_gudang_2,

        'tanggal_tiba_gudang_3'   => $request->tanggal_tiba_gudang_3,
        'tanggal_keluar_gudang_3' => $request->tanggal_keluar_gudang_3,

        // SENTUL
        'lama_digudang_2' => $sla['lama_digudang_2'],
        'status_gudang_2' => $sla['status_gudang_2'],
        'sla_loading_2'   => $sla['sla_loading_2'],

        // CCIE
        'lama_digudang_3' => $sla['lama_digudang_3'],
        'status_gudang_3' => $sla['status_gudang_3'],
        'sla_loading_3'   => $sla['sla_loading_3'],

        'updated_at' => now()
    ];

    LogistikPengiriman::where(
        'no_shipment',
        $request->no_shipment
    )->update($data);

    return response()->json([
        'status' => 'success',
        'message' => 'Data Gudang 2 & Gudang 3 berhasil diupdate'
    ]);
}

// public function summaryArea()
// {
//     $summary_area = DB::table('logistik_pengiriman')
//         ->select('area', DB::raw('COUNT(*) as total'))
//         ->groupBy('area')
//         ->orderByDesc('total')
//         ->get();

//     return view('planner.summary_area', compact('summary_area'));
// }

public function summaryArea()
{
    
    $shipments = DB::table('logistik_pengiriman')
        ->orderBy('no_shipment')
        ->get()
        ->groupBy('no_shipment')
        ->map(fn($group) => $group->first());

    $summary_area = $shipments
        ->groupBy('area')
        ->map(function ($group, $area) {
            return (object)[
                'area'  => $area,
                'total' => count($group)
            ];
        })
        ->sortByDesc('total');

    return view('planner.summary_area', compact('summary_area'));
}

private function getTibaGudangTerdekat($row)
{
    $tanggal = collect([
        $row->tanggal_tiba_gudang,
        $row->tanggal_tiba_gudang_2,
        $row->tanggal_tiba_gudang_3,
    ])
    ->filter()
    ->sort()
    ->values();

    return $tanggal->first();
}
public function armada(Request $request)
{
    $query = DB::table('logistik_pengiriman')
        ->whereNotNull('rencana_kirim')
        ->whereRaw("TRIM(rencana_kirim) <> ''")
        ->whereNotNull('tanggal_dpt_unit')
        ->whereRaw("TRIM(tanggal_dpt_unit) <> ''");

    if ($request->filled('bulan')) {
        $query->whereMonth('tanggal_naik_logistik', $request->bulan);
    }

    if ($request->filled('tahun')) {
        $query->whereYear('tanggal_naik_logistik', $request->tahun);
    }

    $logistik = $query
        ->orderBy('tanggal_naik_logistik', 'DESC')
        ->get()
        ->map(function ($row) {

if ($row->rencana_kirim && $row->tanggal_dpt_unit) {

    $awal = new \DateTime(
        date('Y-m-d H:i:s', strtotime($row->rencana_kirim))
    );

    $akhir = new \DateTime(
        date('Y-m-d H:i:s', strtotime($row->tanggal_dpt_unit))
    );
                $awalCek  = (clone $awal)->setTime(0, 0, 0);
                $akhirCek = (clone $akhir)->setTime(0, 0, 0);

                if ($akhir >= $awal) {
                    $diff = $awal->diff($akhir);

                    $row->lama_waktu_pencarian = $diff->days > 0
                        ? "{$diff->days} Hari {$diff->h} Jam {$diff->i} Menit"
                        : "{$diff->h} Jam {$diff->i} Menit";

                    $row->sla_dapat_mobil   = $akhirCek > $awalCek ? 'Delay' : 'On Time';
                    $row->status_pengiriman = $akhirCek > $awalCek ? 'Terlambat' : 'Sudah Dapat';
                } else {
                    $row->lama_waktu_pencarian = "0 Jam 0 Menit";
                    $row->sla_dapat_mobil      = 'On Time';
                    $row->status_pengiriman    = 'Sudah Dapat';
                }
            } else {
                $row->lama_waktu_pencarian = '-';
                $row->sla_dapat_mobil      = '-';
                $row->status_pengiriman    = '-';
            }

            return $row;
        });

    return view('planner.armada', compact('logistik'));
}

public function exportPlanner(Request $request)
{
  return Excel::download(
    new PlannerExport(
        $request->planner,
        $request->area,
        $request->bulan,
        $request->tahun
    ),
    'Planner.xlsx'
);
}

public function armadaDelay(Request $request)
{
   $query = DB::table('logistik_pengiriman')
    ->whereNotNull('tanggal_dpt_unit')
    ->where(function ($q) {
        $q->whereNotNull('tanggal_tiba_gudang')
          ->orWhereNotNull('tanggal_tiba_gudang_2')
          ->orWhereNotNull('tanggal_tiba_gudang_3');
    });

    if ($request->filled('bulan')) {
        $query->whereMonth('tanggal_naik_logistik', $request->bulan);
    }

    if ($request->filled('tahun')) {
        $query->whereYear('tanggal_naik_logistik', $request->tahun);
    }

    $logistik = $query
        ->orderBy('tanggal_naik_logistik', 'DESC')
        ->get()
       ->map(function ($row) {

    $tibaGudang = $this->getTibaGudangTerdekat($row);

    if ($row->tanggal_dpt_unit && $tibaGudang) {

        $awal = new \DateTime(
            date('Y-m-d H:i:s', strtotime($row->tanggal_dpt_unit))
        );

        $akhir = new \DateTime(
            date('Y-m-d H:i:s', strtotime($tibaGudang))
        );

                $awalCek  = (clone $awal)->setTime(0, 0, 0);
                $akhirCek = (clone $akhir)->setTime(0, 0, 0);

                if ($akhir >= $awal) {
                    $diff = $awal->diff($akhir);

                    $row->lama_waktu_pencarian = $diff->days > 0
                        ? "{$diff->days} Hari {$diff->h} Jam {$diff->i} Menit"
                        : "{$diff->h} Jam {$diff->i} Menit";

                    $row->sla_dapat_mobil   = $akhirCek > $awalCek ? 'Delay' : 'On Time';
                    $row->status_pengiriman = $akhirCek > $awalCek ? 'Terlambat' : 'Sudah Dapat';
                } else {
                    $row->lama_waktu_pencarian = "0 Jam 0 Menit";
                    $row->sla_dapat_mobil      = 'On Time';
                    $row->status_pengiriman    = 'Sudah Dapat';
                }
            } else {
                $row->lama_waktu_pencarian = '-';
                $row->sla_dapat_mobil      = '-';
                $row->status_pengiriman    = '-';
            }

            return $row;
        })
        ->filter(fn($row) => $row->sla_dapat_mobil === 'Delay'); // Filter hanya Delay

    return view('planner.armada_delay', compact('logistik'));
}



public function belumArmada(Request $request)
{
    $query = DB::table('logistik_pengiriman')
        ->where(function ($q) {
            $q->whereNull('rencana_kirim')
              ->orWhere('rencana_kirim', '')
              ->orWhereNull('tanggal_dpt_unit')
              ->orWhere('tanggal_dpt_unit', '');
        });

    if ($request->filled('bulan')) {
        $query->whereMonth('tanggal_naik_logistik', $request->bulan);
    }

    if ($request->filled('tahun')) {
        $query->whereYear('tanggal_naik_logistik', $request->tahun);
    }

    $logistik = $query
        ->orderBy('tanggal_naik_logistik', 'DESC')
        ->get();

    return view('planner.belum_armada', compact('logistik'));
}

    public function baelumArmada(Request $request)
    {
        $query = DB::table('logistik_pengiriman')
            ->where(function ($q) {
                $q->whereIn('status_pengiriman', ['Belum Dapat', 'Pending', 'PENDING'])
                  ->orWhereIn('status_kendaraan', ['Belum Dapat', 'Pending', 'PENDING']);
            });

        if ($request->filled('bulan')) {
            $query->whereMonth('tanggal_naik_logistik', $request->bulan);
        }

        if ($request->filled('tahun')) {
            $query->whereYear('tanggal_naik_logistik', $request->tahun);
        }

        $logistik = $query->orderBy('tanggal_naik_logistik', 'DESC')->get();

        return view('planner.belum_armada', compact('logistik'));
    }
public function delete($id)
{
    LogistikPengiriman::findOrFail($id)->delete();

    return redirect()
        ->back()
        ->with('success', 'Data berhasil dihapus');
}
}