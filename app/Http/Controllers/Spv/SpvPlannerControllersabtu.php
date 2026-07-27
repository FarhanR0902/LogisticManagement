<?php


namespace App\Http\Controllers\Spv;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\LogistikPengiriman;
use App\Models\LogistikPengirimanPasuruan;

class SzpvPlannerController extends Controller
{

// public function dashboard(Request $request)
// {
//     // ================= FILTER =================
//     $date  = $request->date;
//     $month = $request->month;
//     $year  = $request->year;
//     $area  = $request->area;

//     // ================= BASE QUERY =================
//     $base = DB::table('logistik_pengiriman');

//     // FILTER TANGGAL
//     if ($date) {
//         $base->whereDate('tanggal_naik_logistik', $date);
//     }

//     // FILTER BULAN
//     if ($month) {

//         $base->whereMonth(
//             'tanggal_naik_logistik',
//             substr($month, 5, 2)
//         );

//         $base->whereYear(
//             'tanggal_naik_logistik',
//             substr($month, 0, 4)
//         );
//     }

//     // FILTER TAHUN
//     if ($year) {
//         $base->whereYear('tanggal_naik_logistik', $year);
//     }

//     // FILTER AREA
//     if ($area) {
//         $base->where('area', $area);
//     }

//     // ================= TOTAL =================
//     $total_data = (clone $base)->count();

//     // ================= ONTIME =================
//     $ontime = (clone $base)
//         ->whereIn('status_akhir', [
//             'On Time',
//             'ONTIME',
//             'OnTime'
//         ])
//         ->count();

//     // ================= DELAY =================
//     $delay = (clone $base)
//         ->whereIn('status_akhir', [
//             'Delay',
//             'DELAY',
//             'Critical Delay'
//         ])
//         ->count();

//     // ================= ARMADA READY =================
//     $armada = (clone $base)
//         ->where(function ($q) {

//             $q->whereIn('ketersediaan_unit', [
//                 'Sudah Dapat',
//                 'READY',
//                 'Ready'
//             ])

//             ->orWhereIn('status_kendaraan', [
//                 'Sudah Dapat',
//                 'READY',
//                 'Ready'
//             ]);

//         })
//         ->count();

//     // ================= BELUM ARMADA =================
//     $belum_armada = (clone $base)
//         ->where(function ($q) {

//             $q->whereIn('ketersediaan_unit', [
//                 'Belum Dapat',
//                 'Pending',
//                 'PENDING'
//             ])

//             ->orWhereIn('status_kendaraan', [
//                 'Belum Dapat',
//                 'Pending',
//                 'PENDING'
//             ]);

//         })
//         ->count();

//     // ================= SUMMARY AREA =================
//     $summary_area = (clone $base)
//         ->select(
//             'area',
//             DB::raw('COUNT(*) as total')
//         )
//         ->whereNotNull('area')
//         ->groupBy('area')
//         ->orderByDesc('total')
//         ->limit(10)
//         ->get();

//     // ================= DROPDOWN AREA =================
//     $list_area = DB::table('logistik_pengiriman')
//         ->select('area')
//         ->whereNotNull('area')
//         ->distinct()
//         ->orderBy('area')
//         ->get();

//     return view('spvplanner.dashboard', compact(
//         'total_data',
//         'ontime',
//         'delay',
//         'armada',
//         'belum_armada',
//         'summary_area',
//         'list_area'
//     ));
// }

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


private function applyFilterPasuruan($query, $request)
{
    if ($request->area) {
        $query->where('area_pasuruan', $request->area);
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

    return view('spvplanner.dashboard_pasuruan', compact(
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

    return view('spvplanner.data_logistik_pasuruan', compact(
        'logistik',
        'planners',
        'areas'
    ));
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


public function bongkarOnTime(Request $request)
{
    $query = DB::table('logistik_pengiriman')
        ->where(function ($q) {
            $q->where('sla_bongkar', 'H+0')
              ->orWhere('sla_bongkar', 'On Time')
              ->orWhere('sla_bongkar', 'ONTIME')
              ->orWhere('overstay_days', '<=', 0);
        });

    $this->applyFilter($query, $request);

    $logistik = $query->get();

    return view('manager.bongkar_ontime', compact('logistik'));
}

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
//         'nama_driver'             => $request->nama_driver,
//         'no_pol'                  => $request->no_pol,
//         'keterangan'              => $request->keterangan,

//         // Input tanggal & Hasil Rumus SLA otomatis
//         'tanggal_dpt_unit'        => $request->tanggal_dpt_unit,
//         'planning_loading'        => $request->planning_loading,
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
//         'status'                  => $rumus['status'],
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
    

//     // 3. Eksekusi SATU KALI SAJA ke database menggunakan array yang aman
//     DB::table('logistik_pengiriman')
//         ->where('id', $id)
//         ->update($updateData);
        
//     return back()->with('success', 'Data berhasil disimpan');
// }
private function hitungSla($request)
{
    $data = [
        'lama_waktu_pencarian' => null,
        'sla_dapat_mobil'      => null,
        'status_pengiriman'    => null,
        'lama_digudang'        => null,
        'status_gudang'               => null,
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
if ($request->tanggal_tiba_gudang_&& $request->tanggal_keluar_gudang) {

    $awal  = new \DateTime($request->tanggal_tiba_gudang);
    $akhir = new \DateTime($request->tanggal_keluar_gudang);

    if ($akhir >= $awal) {

        $diff = $awal->diff($akhir);

        $data['lama_digudang'] =
            ($diff->days > 0)
                ? "{$diff->days} Hari {$diff->h} Jam"
                : "{$diff->h} Jam";

        $selisihHari = $diff->days;

        if ($selisihHari > 0) {

            $data['status_gudang'] = 'Delay';

            $data['sla_loading'] = 'H+' . $selisihHari;

        } else {

            $data['status_gudang'] = 'On Time';
            $data['sla_loading'] = 'Sesuai SLA';
        }
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

    return view('spvplanner.data_planner', compact('logistik'));
} 
    // public function dashboard()
    // {

    //     $total_data = DB::table('logistik_pengiriman')
    //         ->count();

    //     $ontime = DB::table('logistik_pengiriman')
    //         ->whereIn('status_akhir', [
    //             'On Time',
    //             'ONTIME',
    //             'OnTime'
    //         ])
    //         ->count();

    //     $delay = DB::table('logistik_pengiriman')
    //         ->whereIn('status_akhir', [
    //             'Delay',
    //             'DELAY',
    //             'Critical Delay'
    //         ])
    //         ->count();

    //     $armada = DB::table('logistik_pengiriman')
    //         ->where(function ($q) {

    //             $q->whereIn('ketersediaan_unit', [
    //                 'Sudah Dapat',
    //                 'READY',
    //                 'Ready'
    //             ])

    //             ->orWhereIn('status_kendaraan', [
    //                 'Sudah Dapat',
    //                 'READY',
    //                 'Ready'
    //             ]);

    //         })
    //         ->count();

    //     $belum_armada = DB::table('logistik_pengiriman')
    //         ->where(function ($q) {

    //             $q->whereIn('ketersediaan_unit', [
    //                 'Belum Dapat',
    //                 'Pending',
    //                 'PENDING'
    //             ])

    //             ->orWhereIn('status_kendaraan', [
    //                 'Belum Dapat',
    //                 'Pending',
    //                 'PENDING'
    //             ]);

    //         })
    //         ->count();

    //     $summary_area = DB::table('logistik_pengiriman')
    //         ->select(
    //             'area',
    //             DB::raw('COUNT(*) as total')
    //         )
    //         ->whereNotNull('area')
    //         ->groupBy('area')
    //         ->orderByDesc('total')
    //         ->limit(10)
    //         ->get();

    //     return view('spvplanner.dashboard', compact(
    //         'total_data',
    //         'ontime',
    //         'delay',
    //         'armada',
    //         'belum_armada',
    //         'summary_area'
    //     ));
    // }


    public function slaOntimae(Request $request)
{
    $query = DB::table('logistik_pengiriman')
        ->whereIn('status_akhir', [
            'On Time',
            'ONTIME',
            'OnTime',
            'on time'
        ]);

    // 🔥 AUTO FILTER
    $this->applyFilter($query, $request);

    $list = $query
        ->orderBy('tanggal_tiba', 'DESC')
        ->get();

    $list_area = DB::table('logistik_pengiriman')
        ->select('area')
        ->distinct()
        ->orderBy('area')
        ->get();

    return view('spvplanner.sla_ontime', [
        'title' => 'SLA ONTIME',
        'list' => $list,
        'list_area' => $list_area
    ]);
}

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

    public function fulldashboard   (Request $request)
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

        return view('spvplanner.dashboard_full', compact(

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

    return view('spvplanner.sla_ontime', compact('list', 'list_area'));
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

    return view('spvplanner.sla_delay', [
        'title' => 'SLA DELAY',
        'list'  => $list,
        'list_area' => $list_area
    ]);
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

    return view('spvplanner.armada', compact('logistik'));
}

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

    return view('spvplanner.belum_armada', compact('logistik'));
}
public function delete($id)
{
    LogistikPengiriman::findOrFail($id)->delete();

    return redirect()
        ->back()
        ->with('success', 'Data berhasil dihapus');
}
public function fullDataLogistik(Request $request)
{
    $query = DB::table('logistik_pengiriman');

    // FILTER DATE
    if ($request->date) {

        $query->whereDate(
            'tanggal_naik_logistik',
            $request->date
        );
    }

    // FILTER MONTH
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

    // FILTER YEAR
    if ($request->year) {

        $query->whereYear(
            'tanggal_naik_logistik',
            $request->year
        );
    }

    // FILTER AREA
    if ($request->area) {

        $query->where(
            'area',
            $request->area
        );
    }

    $logistik = $query
        ->orderBy('tanggal_naik_logistik', 'DESC')
        ->get();

    $list_area = DB::table('logistik_pengiriman')
        ->select('area')
        ->whereNotNull('area')
        ->distinct()
        ->orderBy('area')
        ->get();

    return view(
        'data_logistik',
        compact(
            'logistik',
            'list_area'
        )
    );
}

    // public function dataLogistik()
    // {

    //     $logistik = LogistikPengiriman::latest()->get();

    //     return view('spvplanner.data_planner', compact(
    //         'logistik'
    //     ));
    // }

    


    // ================= GLOBAL FILTER =================


    public function slaDelaya(Request $request)
{
    $query = DB::table('logistik_pengiriman')
        ->whereIn('status_akhir', [
            'Delay',
            'Critical Delay',
            'delay',
            'critical delay'
        ]);

    // 🔥 AUTO FILTER
    $this->applyFilter($query, $request);

    $list = $query
        ->orderBy('tanggal_tiba', 'DESC')
        ->get();

    $list_area = DB::table('logistik_pengiriman')
        ->select('area')
        ->distinct()
        ->orderBy('area')
        ->get();

    return view('spvplanner.sla_delay', [
        'title' => 'SLA DELAY',
        'list' => $list,
        'list_area' => $list_area
    ]);
}
    public function summaryArea(Request $request)
{
    $query = DB::table('logistik_pengiriman');

    // 🔥 AUTO FILTER
    $this->applyFilter($query, $request);

    $summary_area = $query
        ->select(
            'area',
            DB::raw('COUNT(*) as total_shipment'),
            DB::raw('SUM(COALESCE(biaya_kirim,0)) as total_biaya'),
            DB::raw('SUM(COALESCE(nilai_muatan,0)) as total_muatan')
        )
        ->whereNotNull('area')
        ->groupBy('area')
        ->orderByDesc('total_shipment')
        ->get();

    return view('spvplanner.summary_area', compact(
        'summary_area'
    ));
}

  public function armadaa(Request $request)
{
    $query = DB::table('logistik_pengiriman')
        ->where(function ($q) {

            $q->whereIn('ketersediaan_unit', [
                'Sudah Dapat',
                'READY',
                'Ready'
            ])

            ->orWhereIn('status_kendaraan', [
                'Sudah Dapat',
                'READY',
                'Ready'
            ]);

        });

    // FILTER DATE
// 🔥 AUTO FILTER
$this->applyFilter($query, $request);
    $logistik = $query
        ->orderByDesc('tanggal_naik_logistik')
        ->get();

    return view('spvplanner.armada', compact(
        'logistik'
    ));
}

public function belumArmadaa(Request $request)
{
    $query = DB::table('logistik_pengiriman')
        ->where(function ($q) {

            $q->whereIn('ketersediaan_unit', [
                'Belum Dapat',
                'Pending',
                'PENDING'
            ])

            ->orWhereIn('status_kendaraan', [
                'Belum Dapat',
                'Pending',
                'PENDING'
            ]);

        });

    // 🔥 AUTO FILTER (sama seperti armada)
    $this->applyFilter($query, $request);

    $logistik = $query
        ->orderByDesc('tanggal_naik_logistik')
        ->get();

    return view('spvplanner.belum_armada', compact(
        'logistik'
    ));
}


    // FILTER DATE
// 🔥 AUTO FILTER
private function applyFilter2($query, $request)
    {

        // AREA

        if ($request->area) {

            $query->where('area', $request->area);
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

    public function fulldashsboard(Request $request)
    {

        // ================= BASE QUERY =================

        $base = DB::table('logistik_pengiriman');

        $this->applyFilter($base, $request);

        // ================= TOTAL =================

        $total_data = (clone $base)->count();

        // ================= GUDANG =================

        $gudang_ontime = (clone $base)

            ->where(function ($q) {

                $q->where('sla_loading', 'H+0')
                  ->orWhere('sla_loading', 'On Time')
                  ->orWhere('sla_loading', 'ONTIME');

            })

            ->count();


        $gudang_delay = (clone $base)

            ->where(function ($q) {

                $q->where('sla_loading', 'H+1')
                  ->orWhere('sla_loading', 'H+2')
                  ->orWhere('sla_loading', 'H>2')
                  ->orWhere('sla_loading', 'Delay')
                  ->orWhere('sla_loading', 'Critical Delay');

            })

            ->count();


        // ================= TUJUAN / CUSTOMER =================

        $customer_ontime = (clone $base)

            ->whereNotNull('tanggal_tiba')

            ->whereRaw("
                DATEDIFF(
                    tanggal_tiba,
                    DATE_ADD(rencana_kirim, INTERVAL transport_lead_time DAY)
                ) <= 0
            ")

            ->count();


        $customer_delay = (clone $base)

            ->whereNotNull('tanggal_tiba')

            ->whereRaw("
                DATEDIFF(
                    tanggal_tiba,
                    DATE_ADD(rencana_kirim, INTERVAL transport_lead_time DAY)
                ) > 0
            ")

            ->count();


        // ================= BONGKAR =================

$bongkar_ontime = (clone $base)

    ->whereNotNull('sla_bongkar')

    ->where(function ($q) {

        $q->whereRaw("LOWER(sla_bongkar) = 'on time'")
          ->orWhere('sla_bongkar', 'ONTIME')
          ->orWhere('sla_bongkar', 'H+0');

    })

    ->count();


$bongkar_delay = (clone $base)

    ->whereNotNull('sla_bongkar')

    ->where(function ($q) {

        $q->whereRaw("LOWER(sla_bongkar) = 'delay'")
          ->orWhereRaw("LOWER(sla_bongkar) = 'critical delay'")
          ->orWhere('sla_bongkar', 'H+1')
          ->orWhere('sla_bongkar', 'H+2')
          ->orWhere('sla_bongkar', 'H>2');

    })

    ->count();


        // ================= ARMADA =================

        $planner_armada = (clone $base)

            ->where('ketersediaan_unit', 'Sudah Dapat')

            ->count();


        $planner_belum_armada = (clone $base)

            ->where('ketersediaan_unit', 'Belum Dapat')

            ->count();


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

        return view('dashboard', compact(

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

            'list_area'
        ));
    }

    
    /*
    |--------------------------------------------------------------------------
    | FULL ACCESS VIEW
    |--------------------------------------------------------------------------
    */

    // public function fullDashboard()
    // {

    //     $total_data = DB::table('logistik_pengiriman')->count();

    //     $ontime = DB::table('logistik_pengiriman')
    //         ->whereIn('status_akhir', [
    //             'On Time',
    //             'ONTIME',
    //             'OnTime'
    //         ])
    //         ->count();

    //     $delay = DB::table('logistik_pengiriman')
    //         ->whereIn('status_akhir', [
    //             'Delay',
    //             'DELAY',
    //             'Critical Delay'
    //         ])
    //         ->count();

    //     $bongkar_ontime = DB::table('logistik_pengiriman')
    //         ->where(function ($q) {

    //             $q->where('sla_bongkar', 'H+0')
    //               ->orWhere('sla_bongkar', 'On Time')
    //               ->orWhere('sla_bongkar', 'ONTIME')
    //               ->orWhere('overstay_days', '<=', 0);

    //         })
    //         ->count();

    //     $bongkar_delay = DB::table('logistik_pengiriman')
    //         ->where(function ($q) {

    //             $q->where('sla_bongkar', '!=', 'H+0')
    //               ->orWhere('sla_bongkar', 'Delay')
    //               ->orWhere('sla_bongkar', 'Critical Delay')
    //               ->orWhere('overstay_days', '>', 0);

    //         })
    //         ->count();

    //     $summary_area = DB::table('logistik_pengiriman')
    //         ->select(
    //             'area',
    //             DB::raw('COUNT(*) as total_shipment'),
    //             DB::raw('SUM(COALESCE(biaya_kirim,0)) as total_biaya'),
    //             DB::raw('SUM(COALESCE(nilai_muatan,0)) as total_muatan')
    //         )
    //         ->groupBy('area')
    //         ->orderByDesc('total_shipment')
    //         ->get();

    //     return view('spvplanner.full_dashboard', compact(
    //         'total_data',
    //         'ontime',
    //         'delay',
    //         'bongkar_ontime',
    //         'bongkar_delay',
    //         'summary_area'
    //     ));
    // }

}