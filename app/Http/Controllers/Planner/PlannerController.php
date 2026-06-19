<?php

namespace App\Http\Controllers\Planner;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\LogistikPengiriman;
class PlannerController extends Controller
{


public function dashboard()
{
    // ================= TOTAL =================
    $total_data = DB::table('logistik_pengiriman')->count();

    // ================= ARMADA READY (sudah dapat unit) =================
  $armada = DB::table('logistik_pengiriman')
    ->whereNotNull('tanggal_dpt_unit')
    ->whereNotNull('tanggal_tiba_gudang')
    ->count();
    // ================= BELUM ARMADA (belum dapat unit) =================
  $belum_armada = DB::table('logistik_pengiriman')
    ->where(function ($q) {
        $q->whereNull('tanggal_dpt_unit')
          ->orWhereNull('tanggal_tiba_gudang');
    })
    ->count();

    // ================= SLA ONTIME (dapat unit same day rencana kirim) =================
    $ontime = DB::table('logistik_pengiriman')
        ->whereNotNull('tanggal_dpt_unit')
        ->whereNotNull('tanggal_tiba_gudang')
        ->whereRaw('DATE(tanggal_dpt_unit) = DATE(tanggal_tiba_gudang)')
        ->count();

    // ================= SLA DELAY (dapat unit beda hari) =================
    $delay = DB::table('logistik_pengiriman')
        ->whereNotNull('tanggal_dpt_unit')
        ->whereNotNull('tanggal_tiba_gudang')
        ->whereRaw('DATE(tanggal_tiba_gudang) > DATE(tanggal_dpt_unit)')
        ->count();

    // ================= SUMMARY AREA =================
    $summary_area = DB::table('logistik_pengiriman')
        ->select('area', DB::raw('COUNT(*) as total'))
        ->whereNotNull('area')
        ->groupBy('area')
        ->orderByDesc('total')
        ->limit(10)
        ->get();

    return view('planner.dashboard', compact(
        'total_data',
        'ontime',
        'delay',
        'armada',
        'belum_armada',
        'summary_area'
    ));
}
// public function dashboard()
// {
//     // ================= TOTAL =================
//     $total_data = DB::table('logistik_pengiriman')->count();

//     // ================= ONTIME / DELAY (PAKAI STATUS ASLI) =================
// $ontime = DB::table('logistik_pengiriman')
//     ->where('status_akhir', 'On Time')
//     ->count();

// $delay = DB::table('logistik_pengiriman')
//     ->where('status_akhir', 'Delay')
//     ->count();
//     // ================= ARMADA READY =================
//     $armada = DB::table('logistik_pengiriman')
//         ->where(function ($q) {
//             $q->whereIn('ketersediaan_unit', ['Sudah Dapat', 'READY', 'Ready'])
//               ->orWhereIn('status_kendaraan', ['Sudah Dapat', 'READY', 'Ready']);
//         })
//         ->count();

//     // ================= BELUM ARMADA =================
//     $belum_armada = DB::table('logistik_pengiriman')
//         ->where(function ($q) {
//             $q->whereIn('ketersediaan_unit', ['Belum Dapat', 'Pending', 'PENDING'])
//               ->orWhereIn('status_kendaraan', ['Belum Dapat', 'Pending', 'PENDING']);
//         })
//         ->count();

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

public function store(Request $request)
{

  
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
// public function store(Request $request)
// {


//      $rumus = $this->hitungSla($request);

//     LogistikPengiriman::create(array_merge(
//         $request->all(),
//         $rumus
//     ));

//     return redirect()->back()
//         ->with('success','Data berhasil disimpan');
// }
//  public function update(Request $request, $id)
// {
//     // ===================================
// // LAMA WAKTU PENCARIAN
// // ===================================

// // $lama_waktu_pencarian = null;
// // $sla_dapat_mobil = null;
// // $status_pengiriman = null;

// // if ($request->tanggal_naik_logistik && $request->tanggal_dpt_unit) {

// //     $jam = floor(
// //         (strtotime($request->tanggal_dpt_unit) -
// //          strtotime($request->tanggal_naik_logistik))
// //         / 3600
// //     );

// //    $selisih = strtotime($request->tanggal_dpt_unit) - strtotime($request->tanggal_naik_logistik);

// // $hari  = floor($selisih / 86400);
// // $jam   = floor(($selisih % 86400) / 3600);
// // $menit = floor(($selisih % 3600) / 60);

// // if($hari > 0){
// //     $lama_waktu_pencarian = $hari.' Hari '.$jam.' Jam';
// // }else{
// //     $lama_waktu_pencarian = $jam.' Jam '.$menit.' Menit';
// // }

// //     $sla_dapat_mobil = ($jam <= 24) ? 'On Time' : 'Delay';
// //     $status_pengiriman = ($jam <= 24) ? 'Sudah Dapat' : 'Terlambat';
// // }

// // // ===================================
// // // KACS
// // // ===================================

// // $lama_digudang = null;
// // $status = null;
// // $sla_loading = null;

// // if ($request->tanggal_tiba_gudang && $request->tanggal_keluar_gudang) {

// //     $selisih = strtotime($request->tanggal_keluar_gudang)
// //              - strtotime($request->tanggal_tiba_gudang);

// //     $totalJam = floor($selisih / 3600);

// //     $hari = floor($totalJam / 24);
// //     $jam  = $totalJam % 24;

// //     if ($hari > 0) {
// //         $lama_digudang = $hari . ' Hari ' . $jam . ' Jam';
// //     } else {
// //         $lama_digudang = $jam . ' Jam';
// //     }

// //     $status = ($totalJam <= 24) ? 'On Time' : 'Delay';
// //     $sla_loading = ($totalJam <= 24) ? 'Sesuai SLA' : 'Melebihi SLA';
// // }

// // // ===================================
// // // SENTUL
// // // ===================================

// // $lama_digudang_2 = null;
// // $status_gudang_2 = null;
// // $sla_loading_2 = null;

// // if ($request->tanggal_tiba_gudang_2 && $request->tanggal_keluar_gudang_2) {

// //     $jam = floor(
// //         (strtotime($request->tanggal_keluar_gudang_2) -
// //          strtotime($request->tanggal_tiba_gudang_2))
// //         / 3600
// //     );

// // $selisih = strtotime($request->tanggal_keluar_gudang_2) - strtotime($request->tanggal_tiba_gudang_2);

// // $hari  = floor($selisih / 86400);
// // $jam   = floor(($selisih % 86400) / 3600);

// // if($hari > 0){
// //     $lama_digudang_2 = $hari.' Hari '.$jam.' Jam';
// // }else{
// //     $lama_digudang_2 = $jam.' Jam';
// // }

// //     $status_gudang_2 = ($jam <= 24) ? 'On Time' : 'Delay';
// //     $sla_loading_2 = ($jam <= 24) ? 'Sesuai SLA' : 'Melebihi SLA';
// // }

// // // ===================================
// // // CCIE
// // // ===================================

// // $lama_digudang_3 = null;
// // $status_gudang_3 = null;
// // $sla_loading_3 = null;

// // if ($request->tanggal_tiba_gudang_3 && $request->tanggal_keluar_gudang_3) {

// //     $jam = floor(
// //         (strtotime($request->tanggal_keluar_gudang_3) -
// //          strtotime($request->tanggal_tiba_gudang_3))
// //         / 3600
// //     );

// // $selisih = strtotime($request->tanggal_keluar_gudang_3) - strtotime($request->tanggal_tiba_gudang_3);

// // $hari  = floor($selisih / 86400);
// // $jam   = floor(($selisih % 86400) / 3600);

// // if($hari > 0){
// //     $lama_digudang_3 = $hari.' Hari '.$jam.' Jam';
// // }else{
// //     $lama_digudang_3 = $jam.' Jam';
// // }

// //     $status_gudang_3 = ($jam <= 24) ? 'On Time' : 'Delay';
// //     $sla_loading_3 = ($jam <= 24) ? 'Sesuai SLA' : 'Melebihi SLA';
// // }

// $rumus = $this->hitungSla($request);
//     DB::table('logistik_pengiriman')
//         ->where('id', $id)
//         ->update([

//             'tanggal_naik_logistik' => $request->tanggal_naik_logistik,
//             'rencana_kirim' => $request->rencana_kirim,
//             'transport_lead_time' => $request->transport_lead_time,
//             'planner' => $request->planner,
//             'no_shipment' => $request->no_shipment,
//             'tujuan' => $request->tujuan,
//             'area' => $request->area,
//             'ketersediaan_unit' => $request->ketersediaan_unit,
//             'mobil' => $request->mobil,
//             'perubahan_mobil' => $request->perubahan_mobil,
//             'nilai_muatan' => $request->nilai_muatan,
//             'biaya_kirim' => $request->biaya_kirim,
//             'cr' => $request->cr,
//             'kategori_ekspedisi' => $request->kategori_ekspedisi,
//             'ekpedisi' => $request->ekpedisi,
//             'nama_driver' => $request->nama_driver,
//             'no_pol' => $request->no_pol,
//             'status_pengiriman' => $rumus['status_pengiriman'],

//             // 'tanggal_dpt_unit' => $request->tanggal_dpt_unit,
//             // 'planning_loading' => $request->planning_loading,
//             // 'tanggal_tiba_gudang' => $request->tanggal_tiba_gudang,
//             // 'tanggal_keluar_gudang' => $request->tanggal_keluar_gudang,
//             // 'lama_digudang' => $request->lama_digudang,
//             // 'status' => $request->status,
//             // 'sla_loading' => $request->sla_loading,
//             // 'keterangan' => $request->keterangan,
//             // 'lama_waktu_pencarian' => $request->lama_waktu_pencarian,
//             // 'sla_dapat_mobil' => $request->sla_dapat_mobil,

//             // 'tanggal_tiba_gudang_2' => $request->tanggal_tiba_gudang_2,
//             // 'tanggal_keluar_gudang_2' => $request->tanggal_keluar_gudang_2,
//             // 'lama_digudang_2' => $request->lama_digudang_2,
//             // 'status_gudang_2' => $request->status_gudang_2,
//             // 'sla_loading_2' => $request->sla_loading_2,

//             // 'tanggal_tiba_gudang_3' => $request->tanggal_tiba_gudang_3,
//             // 'tanggal_keluar_gudang_3' => $request->tanggal_keluar_gudang_3,
//             // 'lama_digudang_3' => $request->lama_digudang_3,
//             // 'status_gudang_3' => $request->status_gudang_3,
//             // 'sla_loading_3' => $request->sla_loading_3,

//             'tanggal_dpt_unit' => $request->tanggal_dpt_unit,
// 'planning_loading' => $request->planning_loading,
// 'tanggal_tiba_gudang' => $request->tanggal_tiba_gudang,
// 'tanggal_keluar_gudang' => $request->tanggal_keluar_gudang,

// 'lama_waktu_pencarian' => $rumus['lama_waktu_pencarian'],
// 'sla_dapat_mobil'      => $rumus['sla_dapat_mobil'],
// 'status_pengiriman'    => $rumus['status_pengiriman'],

// 'lama_digudang'        => $rumus['lama_digudang'],
// 'status'               => $rumus['status'],
// 'sla_loading'          => $rumus['sla_loading'],

// 'lama_digudang_2'      => $rumus['lama_digudang_2'],
// 'status_gudang_2'      => $rumus['status_gudang_2'],
// 'sla_loading_2'        => $rumus['sla_loading_2'],

// 'lama_digudang_3'      => $rumus['lama_digudang_3'],
// 'status_gudang_3'      => $rumus['status_gudang_3'],
// 'sla_loading_3'        => $rumus['sla_loading_3'],
// // 'lama_digudang' => $lama_digudang,
// // 'status' => $status,
// // 'sla_loading' => $sla_loading,

// // 'keterangan' => $request->keterangan,

// // 'lama_waktu_pencarian' => $lama_waktu_pencarian,
// // 'sla_dapat_mobil' => $sla_dapat_mobil,

// // 'tanggal_tiba_gudang_2' => $request->tanggal_tiba_gudang_2,
// // 'tanggal_keluar_gudang_2' => $request->tanggal_keluar_gudang_2,

// // 'lama_digudang_2' => $lama_digudang_2,
// // 'status_gudang_2' => $status_gudang_2,
// // 'sla_loading_2' => $sla_loading_2,

// // 'tanggal_tiba_gudang_3' => $request->tanggal_tiba_gudang_3,
// // 'tanggal_keluar_gudang_3' => $request->tanggal_keluar_gudang_3,

// // 'lama_digudang_3' => $lama_digudang_3,
// // 'status_gudang_3' => $status_gudang_3,
// // 'sla_loading_3' => $sla_loading_3,
//             'route' => $request->route,
// // 'route_first' => $request->route_first,
// 'pulau' => $request->pulau,
// 'via_kirim' => $request->via_kirim,

//             'updated_at' => now()
//         ]);
//         if ($request->filled('tujuan'))    $updateData['tujuan'] = $request->tujuan;
//     if ($request->filled('ekpedisi'))  $updateData['ekpedisi'] = $request->ekpedisi;
//     if ($request->filled('route'))     $updateData['route'] = $request->route;
//     if ($request->filled('mobil'))     $updateData['mobil'] = $request->mobil;
//     if ($request->filled('pulau'))     $updateData['pulau'] = $request->pulau;
//     if ($request->filled('area'))      $updateData['area'] = $request->area;
//     if ($request->filled('via_kirim')) $updateData['via_kirim'] = $request->via_kirim;

//     DB::table('logistik_pengiriman')
//         ->where('id', $id)
//         ->update($updateData);

//     return back()->with('success', 'Data berhasil disimpan');
// }

public function update(Request $request, $id)
{
    
    

    
    $rumus = $this->hitungSla($request);
    

    // 1. Tampung data yang murni berasal dari form web (bukan data master Excel)
    $updateData = [
        
        'tanggal_naik_logistik'   => $request->tanggal_naik_logistik,
        'rencana_kirim'           => $request->rencana_kirim,
        'transport_lead_time'     => $request->transport_lead_time,
        'planner'                 => $request->planner,
        'no_shipment'             => $request->no_shipment,
        'ketersediaan_unit'       => $request->ketersediaan_unit,
        'perubahan_mobil'         => $request->perubahan_mobil,
        'nilai_muatan'            => $request->nilai_muatan,
        'biaya_kirim'             => $request->biaya_kirim,
        'cr' => $this->cleanCr($request->cr),
        'kategori_ekspedisi'      => $request->kategori_ekspedisi,

        'keterangan'              => $request->keterangan,

        // Input tanggal & Hasil Rumus SLA otomatis
        'tanggal_dpt_unit'        => $request->tanggal_dpt_unit,
        'planning_loading'        => $request->planning_loading,
         'planning_loading_2'        => $request->planning_loading_2,
          'planning_loading_3'        => $request->planning_loading_3,
      'tanggal_tiba_gudang'     => $request->tanggal_tiba_gudang,
'tanggal_keluar_gudang'   => $request->tanggal_keluar_gudang,

'tanggal_tiba_gudang_2'   => $request->tanggal_tiba_gudang_2,
'tanggal_keluar_gudang_2' => $request->tanggal_keluar_gudang_2,

'tanggal_tiba_gudang_3'   => $request->tanggal_tiba_gudang_3,
'tanggal_keluar_gudang_3' => $request->tanggal_keluar_gudang_3,

        'lama_waktu_pencarian'    => $rumus['lama_waktu_pencarian'],
        'sla_dapat_mobil'         => $rumus['sla_dapat_mobil'],
        'status_pengiriman'       => $rumus['status_pengiriman'],

        'lama_digudang'           => $rumus['lama_digudang'],
        'status_gudang'                  => $rumus['status_gudang'],
        'sla_loading'             => $rumus['sla_loading'],
         'lama_digudang_2'           => $rumus['lama_digudang_2'],
        'status_gudang_2'                  => $rumus['status_gudang_2'],
        'sla_loading_2'             => $rumus['sla_loading_2'],
         'lama_digudang_3'           => $rumus['lama_digudang_3'],
        'status_gudang_3'                  => $rumus['status_gudang_3'],
        'sla_loading_3'             => $rumus['sla_loading_3'],

        
        'updated_at'              => now()
    ];

    // 2. PROTEKSI KRITIS: Data Excel hanya masuk ke antrean update JIKA form di web diisi
    if ($request->filled('tujuan'))    $updateData['tujuan'] = $request->tujuan;
    if ($request->filled('ekpedisi'))  $updateData['ekpedisi'] = $request->ekpedisi;
    if ($request->filled('route'))     $updateData['route'] = $request->route;
    if ($request->filled('mobil'))     $updateData['mobil'] = $request->mobil;
    if ($request->filled('pulau'))     $updateData['pulau'] = $request->pulau;
    if ($request->filled('area'))      $updateData['area'] = $request->area;
    if ($request->filled('via_kirim')) $updateData['via_kirim'] = $request->via_kirim;
    
    $updateData['nilai_muatan'] = $this->cleanMoney($request->nilai_muatan);
$updateData['biaya_kirim'] = $this->cleanMoney($request->biaya_kirim);

    // 3. Eksekusi SATU KALI SAJA ke database menggunakan array yang aman
    DB::table('logistik_pengiriman')
        ->where('id', $id)
        ->update($updateData);
        
    return back()->with('success', 'Data berhasil disimpan');
}

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
    $logistik = LogistikPengiriman::orderByRaw('CAST(no_shipment AS UNSIGNED) ASC')->get();

    return view('planner.data_planner', compact('logistik'));
} 

// PAke jam
// private function hitungSla($request)
// {
//     $data = [];

//     // =====================
//     // SLA DAPAT MOBIL
//     // =====================

//     $data['lama_waktu_pencarian'] = null;
//     $data['sla_dapat_mobil'] = null;
//     $data['status_pengiriman'] = null;

//     if ($request->tanggal_naik_logistik && $request->tanggal_dpt_unit) {

//         $selisih = strtotime($request->tanggal_dpt_unit)
//                  - strtotime($request->tanggal_naik_logistik);

//         $totalMenit = floor($selisih / 60);
// $totalJam = intdiv($totalMenit, 60);
// $menitSisa = $totalMenit % 60;

//         $hari = floor($totalJam / 24);
//         $jam  = $totalJam % 24;

//        $data['lama_waktu_pencarian'] =
//     ($totalJam > 0)
//         ? $totalJam.' Jam '.$menitSisa.' Menit'
//         : $menitSisa.' Menit';

//         $data['sla_dapat_mobil'] =
// ($selisih <= 86400) ? 'On Time' : 'Delay';

//         $data['status_pengiriman'] =
//            ($selisih <= 86400) ? 'Sudah Dapat' : 'Terlambat';
//     }

//     // =====================
//     // KACS
//     // =====================

//     $data['lama_digudang'] = null;
//     $data['status'] = null;
//     $data['sla_loading'] = null;

//     if ($request->tanggal_tiba_gudang && $request->tanggal_keluar_gudang) {

//         $selisih = strtotime($request->tanggal_keluar_gudang)
//                  - strtotime($request->tanggal_tiba_gudang);

//         $totalJam = floor($selisih / 3600);

//         $hari = floor($totalJam / 24);
//         $jam  = $totalJam % 24;

//         $data['lama_digudang'] =
//             ($hari > 0)
//             ? $hari.' Hari '.$jam.' Jam'
//             : $jam.' Jam';

//         $data['status'] =
//             ($totalJam <= 24) ? 'On Time' : 'Delay';

//         $data['sla_loading'] =
//             ($totalJam <= 24) ? 'Sesuai SLA' : 'Melebihi SLA';
//     }

//     // =====================
//     // SENTUL
//     // =====================

//     $data['lama_digudang_2'] = null;
//     $data['status_gudang_2'] = null;
//     $data['sla_loading_2'] = null;

//     if ($request->tanggal_tiba_gudang_2 && $request->tanggal_keluar_gudang_2) {

//         $selisih = strtotime($request->tanggal_keluar_gudang_2)
//                  - strtotime($request->tanggal_tiba_gudang_2);

//         $totalJam = floor($selisih / 3600);

//         $hari = floor($totalJam / 24);
//         $jam  = $totalJam % 24;

//         $data['lama_digudang_2'] =
//             ($hari > 0)
//             ? $hari.' Hari '.$jam.' Jam'
//             : $jam.' Jam';

//         $data['status_gudang_2'] =
//             ($totalJam <= 24) ? 'On Time' : 'Delay';

//         $data['sla_loading_2'] =
//             ($totalJam <= 24) ? 'Sesuai SLA' : 'Melebihi SLA';
//     }

//     // =====================
//     // CCIE
//     // =====================

//     $data['lama_digudang_3'] = null;
//     $data['status_gudang_3'] = null;
//     $data['sla_loading_3'] = null;

//     if ($request->tanggal_tiba_gudang_3 && $request->tanggal_keluar_gudang_3) {

//         $selisih = strtotime($request->tanggal_keluar_gudang_3)
//                  - strtotime($request->tanggal_tiba_gudang_3);

//         $totalJam = floor($selisih / 3600);

//         $hari = floor($totalJam / 24);
//         $jam  = $totalJam % 24;

//         $data['lama_digudang_3'] =
//             ($hari > 0)
//             ? $hari.' Hari '.$jam.' Jam'
//             : $jam.' Jam';

//         $data['status_gudang_3'] =
//             ($totalJam <= 24) ? 'On Time' : 'Delay';

//         $data['sla_loading_3'] =
//             ($totalJam <= 24) ? 'Sesuai SLA' : 'Melebihi SLA';
//     }

//     return $data;
// }

// pake hari

// private function hitungSla($request)
// {
//     $data = [
//         'lama_waktu_pencarian' => null,
//         'sla_dapat_mobil'      => null,
//         'status_pengiriman'    => null,
//         'lama_digudang'        => null,
//         'status'               => null,
//         'sla_loading'          => null,
//         'lama_digudang_2'      => null,
//         'status_gudang_2'      => null,
//         'sla_loading_2'        => null,
//         'lama_digudang_3'      => null,
//         'status_gudang_3'      => null,
//         'sla_loading_3'        => null,
//     ];

//     // 1. SLA DAPAT MOBIL (Tanggal Naik Logistik ke Tanggal Dapat Unit)
//     if ($request->tanggal_naik_logistik && $request->tanggal_dpt_unit) {
//         $awal  = new \DateTime($request->tanggal_naik_logistik);
//         $akhir = new \DateTime($request->tanggal_dpt_unit);
        
//         if ($akhir >= $awal) {
//             $diff = $awal->diff($akhir);
            
//             // Simpan format text durasi asli yang presisi
//             if ($diff->days > 0) {
//                 $data['lama_waktu_pencarian'] = "{$diff->days} Hari {$diff->h} Jam {$diff->i} Menit";
//             } else {
//                 $data['lama_waktu_pencarian'] = "{$diff->h} Jam {$diff->i} Menit";
//             }

//             // LOGIKA BEDA HARI: Bandingkan hanya tanggalnya saja (Y-m-d)
//             if ($akhir->format('Y-m-d') > $awal->format('Y-m-d')) {
//                 // Jika hari sudah berganti (besoknya atau lebih)
//                 $data['sla_dapat_mobil']   = 'Delay';
//                 $data['status_pengiriman'] = 'Terlambat';
//             } else {
//                 // Jika masih di hari yang sama
//                 $data['sla_dapat_mobil']   = 'On Time';
//                 $data['status_pengiriman'] = 'Sudah Dapat';
//             }
//         } else {
//             $data['lama_waktu_pencarian'] = "0 Jam";
//             $data['sla_dapat_mobil']   = 'On Time';
//             $data['status_pengiriman'] = 'Sudah Dapat';
//         }
//     }

//     // 2. KACS (GUDANG 1)
//     if ($request->tanggal_tiba_gudang && $request->tanggal_keluar_gudang) {
//         $awal  = new \DateTime($request->tanggal_tiba_gudang);
//         $akhir = new \DateTime($request->tanggal_keluar_gudang);
        
//         if ($akhir >= $awal) {
//             $diff = $awal->diff($akhir);
//             $data['lama_digudang'] = ($diff->days > 0) ? "{$diff->days} Hari {$diff->h} Jam" : "{$diff->h} Jam";

//             // Cek beda hari
//             if ($akhir->format('Y-m-d') > $awal->format('Y-m-d')) {
//                 $data['status']      = 'Delay';
//                 $data['sla_loading'] = 'Melebihi SLA';
//             } else {
//                 $data['status']      = 'On Time';
//                 $data['sla_loading'] = 'Sesuai SLA';
//             }
//         }
//     }

//     // 3. SENTUL (GUDANG 2)
//     if ($request->tanggal_tiba_gudang_2 && $request->tanggal_keluar_gudang_2) {
//         $awal  = new \DateTime($request->tanggal_tiba_gudang_2);
//         $akhir = new \DateTime($request->tanggal_keluar_gudang_2);
        
//         if ($akhir >= $awal) {
//             $diff = $awal->diff($akhir);
//             $data['lama_digudang_2'] = ($diff->days > 0) ? "{$diff->days} Hari {$diff->h} Jam" : "{$diff->h} Jam";

//             // Cek beda hari
//             if ($akhir->format('Y-m-d') > $awal->format('Y-m-d')) {
//                 $data['status_gudang_2'] = 'Delay';
//                 $data['sla_loading_2']   = 'Melebihi SLA';
//             } else {
//                 $data['status_gudang_2'] = 'On Time';
//                 $data['sla_loading_2']   = 'Sesuai SLA';
//             }
//         }
//     }

//     // 4. CCIE (GUDANG 3)
//     if ($request->tanggal_tiba_gudang_3 && $request->tanggal_keluar_gudang_3) {
//         $awal  = new \DateTime($request->tanggal_tiba_gudang_3);
//         $akhir = new \DateTime($request->tanggal_keluar_gudang_3);
        
//         if ($akhir >= $awal) {
//             $diff = $awal->diff($akhir);
//             $data['lama_digudang_3'] = ($diff->days > 0) ? "{$diff->days} Hari {$diff->h} Jam" : "{$diff->h} Jam";

//             // Cek beda hari
//             if ($akhir->format('Y-m-d') > $awal->format('Y-m-d')) {
//                 $data['status_gudang_3'] = 'Delay';
//                 $data['sla_loading_3']   = 'Melebihi SLA';
//             } else {
//                 $data['status_gudang_3'] = 'On Time';
//                 $data['sla_loading_3']   = 'Sesuai SLA';
//             }
//         }
//     }

//     return $data;
// }

// baru Hitung

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

    // 1. SLA DAPAT MOBIL (Tanggal Naik Logistik ke Tanggal Dapat Unit)
    if ($request->tanggal_dpt_unit && $request->tanggal_tiba_gudang) {
        // PERBAIKAN: Bungkus dengan date() dan strtotime() agar format tanggal dari browser terbaca dengan benar oleh PHP
        $awal  = new \DateTime(date('Y-m-d H:i:s', strtotime($request->tanggal_dpt_unit)));
        $akhir = new \DateTime(date('Y-m-d H:i:s', strtotime($request->tanggal_tiba_gudang)));
        
        // Buat clone untuk cek perbandingan tanggal murni (tanpa terganggu jam/menit)
        $awalCek  = (clone $awal)->setTime(0, 0, 0);
        $akhirCek = (clone $akhir)->setTime(0, 0, 0);

        if ($akhir >= $awal) {
            $diff = $awal->diff($akhir);
            
            if ($diff->days > 0) {
                $data['lama_waktu_pencarian'] = "{$diff->days} Hari {$diff->h} Jam {$diff->i} Menit";
            } else {
                $data['lama_waktu_pencarian'] = "{$diff->h} Jam {$diff->i} Menit";
            }

            // LOGIKA BEDA HARI (SLA)
            if ($akhirCek > $awalCek) {
                $data['sla_dapat_mobil']   = 'Delay';
                $data['status_pengiriman'] = 'Terlambat';
            } else {
                $data['sla_dapat_mobil']   = 'On Time';
                $data['status_pengiriman'] = 'Sudah Dapat';
            }
        } else {
            $data['lama_waktu_pencarian'] = "0 Jam 0 Menit";
            $data['sla_dapat_mobil']   = 'On Time';
            $data['status_pengiriman'] = 'Sudah Dapat';
        }
    }

    // 2. KACS (GUDANG 1)
if ($request->tanggal_tiba_gudang && $request->tanggal_keluar_gudang) {

    $awal  = new \DateTime($request->tanggal_tiba_gudang);
    $akhir = new \DateTime($request->tanggal_keluar_gudang);

    if ($akhir >= $awal) {

        $diff = $awal->diff($akhir);

        // lama gudang
        $data['lama_digudang'] =
            ($diff->days > 0)
                ? "{$diff->days} Hari {$diff->h} Jam"
                : "{$diff->h} Jam";

        // STATUS (INI YANG DIPERBAIKI)
        if ($diff->days > 0) {
            $data['status_gudang'] = 'Delay';
            $data['sla_loading'] = 'H+' . $diff->days;
        } else {
            $data['status_gudang'] = 'On Time';
            $data['sla_loading'] = 'Sesuai SLA';
        }

    } else {
        $data['status_gudang'] = 'On Time';
        $data['sla_loading'] = 'Sesuai SLA';
        $data['lama_digudang'] = '0 Jam';
    }
}

    // 3. SENTUL (GUDANG 2)
// 3. SENTUL (GUDANG 2)
// =====================================
// GUDANG 2 (SENTUL)
// =====================================
if ($request->tanggal_tiba_gudang_2 && $request->tanggal_keluar_gudang_2) {

    $awal  = new \DateTime($request->tanggal_tiba_gudang_2);
    $akhir = new \DateTime($request->tanggal_keluar_gudang_2);

    if ($akhir >= $awal) {

        $diff = $awal->diff($akhir);

        $data['lama_digudang_2'] =
            ($diff->days > 0)
                ? "{$diff->days} Hari {$diff->h} Jam"
                : "{$diff->h} Jam";

        $selisihHari = $diff->days;

        if ($selisihHari > 0) {

            $data['status_gudang_2'] = 'Delay';

            $data['sla_loading_2'] = 'H+' . $selisihHari;

        } else {

            $data['status_gudang_2'] = 'On Time';
            $data['sla_loading_2'] = 'Sesuai SLA';
        }
    }
}
    // 4. CCIE (GUDANG 3)
// =====================================
// GUDANG 3 (CCIE)
// =====================================
if ($request->tanggal_tiba_gudang_3 && $request->tanggal_keluar_gudang_3) {

    $awal  = new \DateTime($request->tanggal_tiba_gudang_3);
    $akhir = new \DateTime($request->tanggal_keluar_gudang_3);

    if ($akhir >= $awal) {

        $diff = $awal->diff($akhir);

        $data['lama_digudang_3'] =
            ($diff->days > 0)
                ? "{$diff->days} Hari {$diff->h} Jam"
                : "{$diff->h} Jam";

        $selisihHari = $diff->days;

        if ($selisihHari > 0) {

            $data['status_gudang_3'] = 'Delay';

            $data['sla_loading_3'] = 'H+' . $selisihHari;

        } else {

            $data['status_gudang_3'] = 'On Time';
            $data['sla_loading_3'] = 'Sesuai SLA';
        }
    }
}

return $data;
}

public function autosaveRow(Request $request, $id)
{
    DB::table('logistik_pengiriman')
        ->where('id', $id)
        ->update([
            'planner' => $request->planner,
            'no_shipment' => $request->no_shipment,
            'tanggal_naik_logistik' => $request->tanggal_naik_logistik,
            'rencana_kirim' => $request->rencana_kirim,
            'tanggal_dpt_unit' => $request->tanggal_dpt_unit,
            'biaya_kirim' => $request->biaya_kirim,
            'nilai_muatan' => $request->nilai_muatan,
            'cr' => $request->cr,
            'updated_at' => now(),
        ]);

    return response()->json(['success' => true]);
}
public function slaOntime(Request $request)
{
    $query = DB::table('logistik_pengiriman')
        ->whereNotNull('tanggal_dpt_unit')
        ->whereNotNull('tanggal_tiba_gudang')
        ->whereRaw('DATE(tanggal_dpt_unit) = DATE(tanggal_tiba_gudang)'); // same day = On Time

    if ($request->filled('bulan')) {
        $query->whereMonth('tanggal_dpt_unit', $request->bulan);
    }

    if ($request->filled('tahun')) {
        $query->whereYear('tanggal_dpt_unit', $request->tahun);
    }

    if ($request->filled('area')) {
        $query->where('area', $request->area);
    }

    $list = $query->orderBy('tanggal_dpt_unit', 'DESC')->get();

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
        ->whereNotNull('tanggal_dpt_unit')
        ->whereNotNull('tanggal_tiba_gudang')
        ->whereRaw('DATE(tanggal_tiba_gudang) > DATE(tanggal_dpt_unit)'); // beda hari = Delay

    if ($request->filled('bulan')) {
        $query->whereMonth('tanggal_dpt_unit', $request->bulan);
    }

    if ($request->filled('tahun')) {
        $query->whereYear('tanggal_dpt_unit', $request->tahun);
    }

    if ($request->filled('area')) {
        $query->where('area', $request->area);
    }

    $list = $query->orderBy('tanggal_dpt_unit', 'DESC')->get();

    $list_area = DB::table('logistik_pengiriman')
        ->select('area')
        ->whereNotNull('area')
        ->groupBy('area')
        ->orderBy('area')
        ->get();

    return view('planner.sla_delay', [
        'title' => 'SLA DELAY',
        'list'  => $list,
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

public function summaryArea()
{
    $summary_area = DB::table('logistik_pengiriman')
        ->select('area', DB::raw('COUNT(*) as total'))
        ->groupBy('area')
        ->orderByDesc('total')
        ->get();

    return view('planner.summary_area', compact('summary_area'));
}
public function armada(Request $request)
{
    $query = DB::table('logistik_pengiriman')
        ->whereNotNull('tanggal_dpt_unit')
        ->whereNotNull('tanggal_tiba_gudang');

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
            if ($row->tanggal_dpt_unit && $row->tanggal_tiba_gudang) {
                $awal  = new \DateTime(date('Y-m-d H:i:s', strtotime($row->tanggal_dpt_unit)));
                $akhir = new \DateTime(date('Y-m-d H:i:s', strtotime($row->tanggal_tiba_gudang)));

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

public function armadaDelay(Request $request)
{
    $query = DB::table('logistik_pengiriman')
        ->whereNotNull('tanggal_dpt_unit')
        ->whereNotNull('tanggal_tiba_gudang');

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
            if ($row->tanggal_dpt_unit && $row->tanggal_tiba_gudang) {
                $awal  = new \DateTime(date('Y-m-d H:i:s', strtotime($row->tanggal_dpt_unit)));
                $akhir = new \DateTime(date('Y-m-d H:i:s', strtotime($row->tanggal_tiba_gudang)));

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

// public function belumArmada(Request $request)
// {
//     $query = DB::table('logistik_pengiriman')
//         ->whereNull('tanggal_dpt_unit')
//         ->whereNull('tanggal_tiba_gudang');

//     if ($request->filled('bulan')) {
//         $query->whereMonth('tanggal_naik_logistik', $request->bulan);
//     }

//     if ($request->filled('tahun')) {
//         $query->whereYear('tanggal_naik_logistik', $request->tahun);
//     }

//     $logistik = $query
//         ->orderBy('tanggal_naik_logistik', 'DESC')
//         ->get();

//     return view('planner.armada', compact('logistik'));
// }


public function belumArmada(Request $request)
{
    $query = DB::table('logistik_pengiriman')
        ->whereNull('tanggal_tiba_gudang');

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