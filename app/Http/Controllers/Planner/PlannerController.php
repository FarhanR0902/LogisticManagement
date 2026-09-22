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

private const PULAU_MAP = [
        'JAWA'       => ['JABODEBEK', 'BANTEN', 'JAWA_BARAT', 'JAWA_TENGAH', 'JAWA_TIMUR', 'YOGYAKARTA'],
        'SUMATERA'   => ['ACEH', 'SUMATERA_UTARA', 'SUMATERA_BARAT', 'RIAU', 'KEP._RIAU', 'JAMBI', 'SUMATERA_SELATAN', 'BENGKULU', 'LAMPUNG', 'KEP._BANGKA_BELITUNG'],
        'KALIMANTAN' => ['KALIMANTAN_BARAT', 'KALIMANTAN_TENGAH', 'KALIMANTAN_SELATAN', 'KALIMANTAN_TIMUR', 'KALIMANTAN_UTARA'],
        'SULAWESI'   => ['SULAWESI_UTARA', 'SULAWESI_TENGAH', 'SULAWESI_SELATAN', 'SULAWESI_TENGGARA', 'SULAWESI_BARAT', 'GORONTALO'],
        'BALI_NUSRA' => ['PROV._BALI', 'NUSA_TENGGARA_BARAT', 'NUSA_TENGGARA_TIMUR'],
        'MALUKU'     => ['PROV._MALUKU', 'PROV._MALUKU_UTARA'],
        'PAPUA'      => ['PROV._PAPUA', 'PAPUA_BARAT', 'PAPUA_BARAT_DAYA', 'PAPUA_SELATAN', 'PAPUA_TENGAH'],
    ];
    public function store(Request $request)
    {
        $data['total_kubik']  = $this->cleanDecimalPlanner($request->total_kubik);
$data['total_tonase'] = $this->cleanDecimalPlanner($request->total_tonase);
        $rumus = $this->hitungSla($request);

        $data = $request->only([
            'create_tgl',
            'no_shipment',
            'kubikasi',
            'planner',
            'dist_channel',
            'transport_lead_time',
            'tujuan',
            'reason_optimal',
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
        $data['kubikasi'] = $this->cleanPersen($request->kubikasi);
        $kapasitas = $this->cariKapasitasTarif($request->route, $request->mobil, $request->ekpedisi);

$data['kubikasi'] = $kapasitas['kubikasi'] ?? $this->cleanPersen($request->kubikasi);
$data['tonase']   = $kapasitas['tonase'];

$data['total_kubik']  = $this->cleanDecimalPlanner($request->total_kubik);
$data['total_tonase'] = $this->cleanDecimalPlanner($request->total_tonase);

$hasil = $this->hitungHasilOptimal(
    $data['total_kubik'],
    $data['kubikasi'],
    $data['total_tonase'],
    $data['tonase']
);

$data['hasil_kubik']         = $hasil['hasil_kubik'];
$data['hasil_tonase']        = $hasil['hasil_tonase'];
$data['pengiriman_optimal']  = $hasil['pengiriman_optimal'];

        LogistikPengiriman::create(array_merge($data, $rumus));

        return back()->with('success', 'Data berhasil disimpan');
    }

  public function dashboard(Request $request)
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

    $total_data = $shipments->count();

    $armada = $shipments->filter(function ($row) {
        return !empty($row->rencana_kirim)
            && !empty($row->tanggal_dpt_unit);
    })->count();

    $belum_armada = $shipments->filter(function ($row) {
        return empty($row->rencana_kirim)
            || empty($row->tanggal_dpt_unit);
    })->count();

    $ontime = $shipments->filter(function ($row) {
        return !empty($row->tanggal_tiba_gudang)
            || !empty($row->tanggal_tiba_gudang_2)
            || !empty($row->tanggal_tiba_gudang_3);
    })->count();

    $delay = $shipments->filter(function ($row) {
        return !empty($row->tanggal_dpt_unit)
            && empty($row->tanggal_tiba_gudang)
            && empty($row->tanggal_tiba_gudang_2)
            && empty($row->tanggal_tiba_gudang_3);
    })->count();

    $summary_area = $shipments
        ->groupBy('area')
        ->map(function ($group) {
            return count($group);
        })
        ->sortDesc()
        ->take(10);

    $total_in_transit = $this->applyFilter($this->inTransitQuery(), $request)->count();

    return view('planner.dashboard', compact(
        'total_data',
        'ontime',
        'total_in_transit',
        'delay',
        'armada',
        'belum_armada',
        'summary_area'
    ));
}


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

    // filter dari dashboard (tanggal/bulan/tahun/area/channel/pulau) ikut terbawa
    $this->applyFilter($base, $request);

    if ($request->filled('pic_monitoring')) {
        $base->where('pic_monitoring', $request->input('pic_monitoring'));
    }
    if ($request->filled('q')) {
        $s = trim($request->input('q'));
        $base->where(function ($q) use ($s) {
            foreach (['no_shipment', 'tujuan', 'ekpedisi', 'nama_driver', 'no_pol', 'mobil'] as $col) {
                $q->orWhere($col, 'like', "%{$s}%");
            }
        });
    }

    // ===== ringkasan =====
    $sum = (clone $base)->selectRaw("
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
    $list = (clone $base)
        ->orderByRaw("DATE({$est}) ASC")
        ->orderBy('no_shipment')
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

    $areaList = DB::table('logistik_pengiriman')
        ->whereNotNull('area')->distinct()->orderBy('area')->pluck('area');

    $picList = DB::table('logistik_pengiriman')
        ->whereNotNull('pic_monitoring')->distinct()->orderBy('pic_monitoring')->pluck('pic_monitoring');

    $formRoute = route('planner.intransit');

    return view('monitoring.in_transit', compact('list', 'summary', 'areaList', 'picList', 'formRoute'));
}


 private function applyFilter($query, $request)
    {
        if ($request->area) {
            $query->where('area', $request->area);
        }

        if ($request->dist_channel) {
            $query->where('dist_channel', $request->dist_channel);
        }

        if ($request->filled('pulau') && isset(self::PULAU_MAP[$request->pulau])) {
            $query->whereIn('area', self::PULAU_MAP[$request->pulau]);
        }

        if ($request->date) {
            $query->whereDate('tanggal_naik_logistik', $request->date);
        }

        if ($request->month) {
            $query->whereMonth('tanggal_naik_logistik', substr($request->month, 5, 2));
            $query->whereYear('tanggal_naik_logistik', substr($request->month, 0, 4));
        }

        if ($request->year) {
            $query->whereYear('tanggal_naik_logistik', $request->year);
        }

        return $query;
    }


    /**
     * =====================================================
     * UPDATE (form penuh dari halaman planner)
     * =====================================================
     */
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
        // Hitung Estimasi Tiba (FIX: pakai blocked-check,
        // supaya konsisten dengan logic di MonitoringController)
        // ==========================
        $gudangInfo = $this->getKeluarGudangInfoRequest($request);
        $keluar  = $gudangInfo['keluar'];
        $blocked = $gudangInfo['blocked'];

        if (!$blocked && $keluar && $request->transport_lead_time) {

            $request->merge([
                'estimasi_tiba' => date(
                    'Y-m-d',
                    strtotime(
                        '+' . (int) $request->transport_lead_time . ' days',
                        $keluar
                    )
                )
            ]);
        } else {
            // masih ada siklus gudang yang "ngegantung" (belum keluar)
            // -> jangan hitung estimasi dulu
            $request->merge(['estimasi_tiba' => null]);
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
            'route'                 => $request->route,

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
        // 2. AUTO HITUNG BIAYA KIRIM
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
      $kapasitas = $this->cariKapasitasTarif($request->route, $request->mobil, $request->ekpedisi);

$totalKubik  = $this->cleanDecimalPlanner($request->total_kubik);
$totalTonase = $this->cleanDecimalPlanner($request->total_tonase);

$hasil = $this->hitungHasilOptimal(
    $totalKubik,
    $kapasitas['kubikasi'],
    $totalTonase,
    $kapasitas['tonase']
);

$updateRow = [
    'tujuan'             => $request->tujuan,
    'pulau'              => $request->pulau,
    'total_do_qty_car'   => $request->total_do_qty_car,
    'kubikasi'           => $kapasitas['kubikasi'] ?? $this->cleanPersen($request->kubikasi),
    'tonase'             => $kapasitas['tonase'],
    'total_kubik'        => $totalKubik,
    'total_tonase'       => $totalTonase,
    'hasil_kubik'        => $hasil['hasil_kubik'],
    'hasil_tonase'       => $hasil['hasil_tonase'],
    'pengiriman_optimal' => $hasil['pengiriman_optimal'],
    'reason_optimal'     => $request->reason_optimal ?: null,  
    'nilai_muatan'       => $this->cleanMoney($request->nilai_muatan),
    'updated_at'         => now(),
];

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

    /**
     * =====================================================
     * AUTOSAVE ROW (inline edit per-cell)
     * =====================================================
     */
    public function autosaveRow(Request $request, $id)
    {
        $old = DB::table('logistik_pengiriman')
            ->where('id', $id)
            ->first();

        if (!$old) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $oldNoShipment = $old->no_shipment;
        $newNoShipment = $request->no_shipment;
        $shipment      = $newNoShipment ?: $oldNoShipment;

        // ==========================
        // HITUNG ESTIMASI TIBA (blocked-check)
        // ==========================
        $gudangInfo = $this->getKeluarGudangInfoRequest($request);
        $keluar  = $gudangInfo['keluar'];
        $blocked = $gudangInfo['blocked'];

        if (!$blocked && $keluar && $request->transport_lead_time) {
            $request->merge([
                'estimasi_tiba' => date(
                    'Y-m-d',
                    strtotime('+' . (int) $request->transport_lead_time . ' days', $keluar)
                )
            ]);
        } else {
            $request->merge(['estimasi_tiba' => null]);
        }

        // ==========================
        // HITUNG SLA
        // ==========================
        $rumus = $this->hitungSla($request);

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
        // ====================================================
        $updateShipment = [
            'planner'       => $request->planner,
            'no_shipment'   => $newNoShipment,
            'estimasi_tiba' => $request->estimasi_tiba,

            'tanggal_naik_logistik' => $request->tanggal_naik_logistik,
            'rencana_kirim'         => $request->rencana_kirim,
            'tanggal_dpt_unit'      => $request->tanggal_dpt_unit,

            'planning_loading'      => $request->planning_loading,
            'tanggal_tiba_gudang'   => $request->tanggal_tiba_gudang,
            'tanggal_keluar_gudang' => $request->tanggal_keluar_gudang,

            'planning_loading_2'      => $request->planning_loading_2,
            'tanggal_tiba_gudang_2'   => $request->tanggal_tiba_gudang_2,
            'tanggal_keluar_gudang_2' => $request->tanggal_keluar_gudang_2,

            'planning_loading_3'      => $request->planning_loading_3,
            'tanggal_tiba_gudang_3'   => $request->tanggal_tiba_gudang_3,
            'tanggal_keluar_gudang_3' => $request->tanggal_keluar_gudang_3,

          
            'route'     => $request->route,
            'pulau'     => $request->pulau,
            'area'      => $request->area,
            'via_kirim' => $request->via_kirim,

            'dist_channel'        => $request->dist_channel,
            'kategori_ekspedisi'  => $request->kategori_ekspedisi,
            'ekpedisi'            => $request->ekpedisi,
            'transport_lead_time' => $request->transport_lead_time,

            'nama_driver' => $request->nama_driver,
            'no_pol'      => $request->no_pol,
            'mobil'       => $request->mobil,

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
        // 2. SYNC BIAYA KIRIM AUTO KE SEMUA ROW SESHIPMENT
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
       // ====================================================
// 3. UPDATE FIELD SPESIFIK PER ROW (per unit/per tujuan)
// ====================================================
$kapasitas = $this->cariKapasitasTarif($request->route, $request->mobil, $request->ekpedisi);

$totalKubik  = $this->cleanDecimalPlanner($request->total_kubik);
$totalTonase = $this->cleanDecimalPlanner($request->total_tonase);

$hasil = $this->hitungHasilOptimal(
    $totalKubik,
    $kapasitas['kubikasi'],
    $totalTonase,
    $kapasitas['tonase']
);

$updateRow = [
    'tujuan'             => $request->tujuan,
    'pulau'              => $request->pulau,
    'total_do_qty_car'   => $request->total_do_qty_car,
    'kubikasi'           => $kapasitas['kubikasi'] ?? $this->cleanPersen($request->kubikasi),
    'tonase'             => $kapasitas['tonase'],
    'total_kubik'        => $totalKubik,
    'total_tonase'       => $totalTonase,
    'hasil_kubik'        => $hasil['hasil_kubik'],
    'hasil_tonase'       => $hasil['hasil_tonase'],
    'pengiriman_optimal' => $hasil['pengiriman_optimal'],
    'reason_optimal'     => $request->reason_optimal ?: null,  
    'nilai_muatan'       => $this->cleanMoney($request->nilai_muatan),
    'updated_at'         => now(),
];

if ($autoBiaya === null) {
    $updateRow['biaya_kirim'] = $biayaKirim;
}

DB::table('logistik_pengiriman')
    ->where('id', $id)
    ->update($updateRow);

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
            'success'       => true,
            'biaya_kirim'   => $biayaKirim,
            'estimasi_tiba' => $request->estimasi_tiba,
            'sla'           => $rumus,
        ]);
    }


    public function searchReasonOptimal(Request $request)
{
    $q = trim((string) $request->get('q', ''));

    $items = DB::table('akurasi3')
        ->whereNotNull('reason_optimal')
        ->where('reason_optimal', '!=', '')
        ->when($q !== '', fn($query) => $query->where('reason_optimal', 'like', "%{$q}%"))
        ->distinct()
        ->orderBy('reason_optimal')
        ->limit(50)
        ->pluck('reason_optimal');

    return response()->json(
        $items->map(fn($v) => ['id' => $v, 'text' => $v])->values()
    );
}
    private function cleanCr($value)
    {
        if (!$value) return null;
        $value = preg_replace('/[^0-9.]/', '', $value);
        return is_numeric($value) ? (float) $value : null;
    }

    private function cleanPersen($value)
{
    if ($value === null || $value === '') return null;

    // buang simbol % dan karakter selain angka & titik
    $value = str_replace(',', '.', $value);
    $value = preg_replace('/[^0-9.]/', '', $value);

    if (!is_numeric($value)) return null;

    $value = (float) $value;

    // clamp 0 - 100
    if ($value < 0) $value = 0;
    if ($value > 100) $value = 100;

    return round($value, 2);
}

    private function cleanMoney($value)
    {
        if (!$value) return null;
        return (int) preg_replace('/[^0-9]/', '', $value);
    }

    /**
     * =====================================================
     * HALAMAN DATA PLANNER
     * FIX PERFORMA: TIDAK LAGI query semua baris ($logistik)
     * ke Blade. View sekarang mengambil data lewat DataTables
     * server-side (endpoint dataAjax()). Di sini hanya kirim
     * list dropdown (planner/area/tujuan/dll) yang memang
     * dibutuhkan untuk filter & modal "Add New Shipment".
     * =====================================================
     */
    public function dataLogistik()
    {
        $planners = LogistikPengiriman::whereNotNull('planner')
            ->where('planner', '!=', '')
            ->distinct()
            ->orderBy('planner')
            ->pluck('planner');

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
            $reasonOptimalList = DB::table('akurasi3')
    ->whereNotNull('reason_optimal')->where('reason_optimal', '!=', '')
    ->distinct()->orderBy('reason_optimal')->pluck('reason_optimal');

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
                'ekpedisiList',
                'tujuanList',
                'mobilList',
                'pulauList',
                'routeList',
                'distChannelList',
                'planners',
                'areas',
                'tarifPengiriman',
                'reasonOptimalList'
            )
        );
    }

    /**
     * =====================================================
     * ENDPOINT SERVER-SIDE UNTUK DATATABLES
     * Hanya ambil & render baris yang benar-benar tampil
     * (biasanya 10-25 baris), bukan semua data.
     * =====================================================
     */
    public function dataAjax(Request $request)
    {
        $draw   = (int) $request->input('draw', 1);
        $start  = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $searchValue = trim((string) $request->input('search.value', ''));

        $baseQuery = LogistikPengiriman::query();
        $totalRecords = (clone $baseQuery)->count();

        // ===== FILTER: planner / area / tanggal import =====
        if ($request->filled('planner_filter')) {
            $baseQuery->where('planner', $request->input('planner_filter'));
        }
        if ($request->filled('area_filter')) {
            $baseQuery->where('area', $request->input('area_filter'));
        }
        if ($request->filled('create_tgl_filter')) {
            $baseQuery->whereDate('create_tgl', $request->input('create_tgl_filter'));
        }

        // ===== GLOBAL SEARCH (kolom-kolom penting saja) =====
        if ($searchValue !== '') {
            $baseQuery->where(function ($q) use ($searchValue) {
                $cols = [
                    'planner', 'no_shipment', 'tujuan', 'route', 'pulau', 'area',
                    'via_kirim', 'dist_channel', 'kategori_ekspedisi', 'ekpedisi',
                    'nama_driver', 'no_pol', 'mobil', 'transport_lead_time',
                ];
                foreach ($cols as $col) {
                    $q->orWhere($col, 'like', "%{$searchValue}%");
                }
            });
        }


        $recordsFiltered = (clone $baseQuery)->count();

        $rows = $baseQuery
            ->orderByRaw('CAST(no_shipment AS UNSIGNED) ASC')
            ->skip($start)
            ->take($length)
            ->get();

        // list dropdown untuk render select di tiap baris
        $tujuanList = DB::table('tujuanfillterr')->whereNotNull('tujuan')->where('tujuan', '!=', '')->distinct()->orderBy('tujuan')->pluck('tujuan');
        $pulauList = DB::table('tujuanfillterr')->whereNotNull('pulau')->where('pulau', '!=', '')->distinct()->orderBy('pulau')->pluck('pulau');
        $areas = DB::table('tujuanfillterr')->whereNotNull('area')->where('area', '!=', '')->distinct()->orderBy('area')->pluck('area');
        $distChannelList = DB::table('tujuanfillterr')->whereNotNull('dist_channel')->where('dist_channel', '!=', '')->distinct()->orderBy('dist_channel')->pluck('dist_channel');
        $ekpedisiList = DB::table('tarif_pengiriman')->whereNotNull('ekpedisi')->where('ekpedisi', '!=', '')->distinct()->orderBy('ekpedisi')->pluck('ekpedisi');
        $mobilList = DB::table('tarif_pengiriman')->whereNotNull('mobil')->where('mobil', '!=', '')->distinct()->orderBy('mobil')->pluck('mobil');
      $routeList = DB::table('tarif_pengiriman')->whereNotNull('route')->where('route', '!=', '')->distinct()->orderBy('route')->pluck('route');   // <-- INI YANG HILANG
        $reasonOptimalList = DB::table('akurasi3')
    ->whereNotNull('reason_optimal')->where('reason_optimal', '!=', '')
    ->distinct()->orderBy('reason_optimal')->pluck('reason_optimal');

$lists = compact(
    'tujuanList', 'pulauList', 'areas', 'distChannelList',
    'ekpedisiList', 'mobilList', 'routeList', 'reasonOptimalList'
);

        $data = [];
        foreach ($rows as $r) {
            $data[] = $this->renderRowColumns($r, $lists);
        }

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    /**
     * Bangun 1 baris (array kolom, index harus sinkron dengan
     * thead di data_planner.blade.php) dalam bentuk HTML string,
     * supaya input/select/badge yang sudah ada tetap identik.
     */
    private function renderRowColumns($r, array $lists)
    {
        $id = $r->id;
        $formAttr = 'form="form-update-' . $id . '"';

        $dateInput = function ($name, $value) use ($formAttr) {
            $val = $value ? date('Y-m-d', strtotime($value)) : '';
            return '<input type="date" ' . $formAttr . ' name="' . $name . '" value="' . e($val) . '">';
        };
        $formattedPersen = function ($angka) {
    if ($angka === null || $angka === '') return '';
    return number_format((float) $angka, 2, ',', '.') . '%';
};

        $textInput = function ($name, $value, $extraClass = '') use ($formAttr) {
            return '<input type="text" ' . $formAttr . ' name="' . $name . '" class="' . $extraClass . '" value="' . e($value) . '">';
        };

        $buildSelect = function ($name, $selected, $options, $extraClass = '', $required = false) use ($formAttr) {
            $html = '<select ' . $formAttr . ' name="' . $name . '" class="' . $extraClass . ' select2-row">';
            $html .= '<option value="">-- Pilih --</option>';
            $found = false;
            foreach ($options as $opt) {
                $isSelected = ((string) $selected === (string) $opt);
                if ($isSelected) $found = true;
                $html .= '<option value="' . e($opt) . '"' . ($isSelected ? ' selected' : '') . '>' . e($opt) . '</option>';
            }
            if ($selected && !$found) {
                $html .= '<option value="' . e($selected) . '" selected>' . e($selected) . ' (lama)</option>';
            }
            $html .= '</select>';
            return $html;
        };

        // ===== helper durasi & status gudang (identik dgn logic asli) =====
        $durasiStatus = function ($planning, $tiba) {
            $durasiText = '-';
            if (!empty($planning) && !empty($tiba)) {
                $start = \Carbon\Carbon::parse($planning);
                $end   = \Carbon\Carbon::parse($tiba);
                $totalMenit  = $start->diffInMinutes($end);
                $desimalHari = $totalMenit / 1440;
                $hari = floor($desimalHari);
                $jam  = round(($desimalHari - $hari) * 24);
                if ($jam == 24) { $jam = 0; $hari += 1; }
                if ($hari > 0 && $jam > 0) $durasiText = "{$hari} Hari {$jam} Jam";
                elseif ($hari > 0) $durasiText = "{$hari} Hari";
                elseif ($jam > 0) $durasiText = "{$jam} Jam";
                else $durasiText = "0 Jam";
            }
            return $durasiText;
        };

        $statusBadge = function ($planning, $tiba) {
            if (empty($planning) || empty($tiba)) {
                return '<span class="badge gray">-</span>';
            }
            $startDay = \Carbon\Carbon::parse($planning)->startOfDay();
            $endDay   = \Carbon\Carbon::parse($tiba)->startOfDay();
            return $endDay->gt($startDay)
                ? '<span class="badge red">Delay</span>'
                : '<span class="badge green">On Time</span>';
        };

        $slaBadge = function ($planning, $tiba) {
            if (empty($planning) || empty($tiba)) {
                return '<span class="badge bg-secondary">-</span>';
            }
            $start = \Carbon\Carbon::parse($planning)->startOfDay();
            $end   = \Carbon\Carbon::parse($tiba)->startOfDay();
            if ($end->gt($start)) {
                $selisih = $start->diffInDays($end);
                return '<span class="badge red">H+' . $selisih . '</span>';
            }
            return '<span class="badge bg-success">Sesuai SLA</span>';
        };

        // ===== Status Mobil =====
        if (!empty($r->tanggal_dpt_unit)) {
            $statusMobilHtml = '<span class="badge-status bg-success text-white">SUDAH DAPAT</span>';
        } else {
            $statusMobilHtml = '<span class="badge-status bg-danger text-white">BELUM DAPAT</span>';
        }

        // ===== SLA Dapat Mobil =====
        if ($r->rencana_kirim && $r->tanggal_dpt_unit) {
            $area = strtoupper(trim($r->area ?? ''));
            $rencana = strtotime(date('Y-m-d', strtotime($r->rencana_kirim)));
            $dptUnit = strtotime(date('Y-m-d', strtotime($r->tanggal_dpt_unit)));
            $selisihHari = floor(($dptUnit - $rencana) / 86400);

            if (in_array($area, ['JABODEBEK', 'JABODETABEK', 'BANTEN'])) {
                $batasHari = 0;
            } elseif (in_array($area, ['JAWA_BARAT', 'JAWA BARAT'])) {
                $batasHari = 1;
            } else {
                $batasHari = 2;
            }

            $text = $selisihHari > $batasHari ? 'H+' . ($selisihHari - $batasHari) : 'Sesuai SLA';
            $slaMobilHtml = '<span class="badge-status ' . (str_contains($text, 'H+') ? 'bg-danger text-white' : 'bg-success text-white') . '">' . $text . '</span>';
        } else {
            $slaMobilHtml = '<span class="badge-status bg-secondary text-white">-</span>';
        }

        // ===== Kelengkapan Data (5 field wajib) =====
        $requiredFields = [
            'mobil'       => 'Mobil',
            'ekpedisi'    => 'Ekspedisi',
            'route'       => 'Route',
            'nama_driver' => 'Nama Driver',
            'no_pol'      => 'No Pol',
        ];
        $missing = [];
        foreach ($requiredFields as $col => $label) {
            if (trim((string) ($r->$col ?? '')) === '') {
                $missing[] = $label;
            }
        }
        if (count($missing) === 0) {
            $kelengkapanHtml = '<span class="badge completeness-badge green" title="Data lengkap">✅ Lengkap</span>';
        } else {
            $emptyCount = count($missing);
            $cls = $emptyCount === 1 ? 'yellow' : ($emptyCount <= 3 ? 'orange' : 'red');
            $text = '❌ ' . implode(', ', $missing);
            $kelengkapanHtml = '<span class="badge completeness-badge ' . $cls . '" title="' . e($text) . '">' . e($text) . '</span>';
        }

        $formattedRupiah = function ($angka) {
            if (!$angka) return '';
            $stringMurni = explode('.', (string) $angka)[0];
            $angkaMurni = preg_replace('/[^0-9]/', '', $stringMurni);
            return $angkaMurni ? 'Rp ' . number_format((float) $angkaMurni, 0, ',', '.') : '';
        };

        $tanggalImportText = $r->create_tgl ? \Carbon\Carbon::parse($r->create_tgl)->format('d/m/Y H:i') : '-';

        $hiddenForm = '<form class="d-none" id="form-update-' . $id . '" action="' . route('planner.update', $id) . '" method="POST">'
            . csrf_field() . method_field('PUT') . '</form>';

        return [
            // 0
            $hiddenForm . $tanggalImportText,
            // 1
            $textInput('planner', $r->planner),
            // 2
// 2
'<span class="fw-medium">' . e($r->no_shipment) . '</span>'
. '<input type="hidden" ' . $formAttr . ' name="no_shipment" value="' . e($r->no_shipment) . '">',
            // 3-14 tanggal
            $dateInput('tanggal_naik_logistik', $r->tanggal_naik_logistik),
            $dateInput('rencana_kirim', $r->rencana_kirim),
            $dateInput('tanggal_dpt_unit', $r->tanggal_dpt_unit),
            $dateInput('planning_loading', $r->planning_loading),
            $dateInput('tanggal_tiba_gudang', $r->tanggal_tiba_gudang),
            $dateInput('tanggal_keluar_gudang', $r->tanggal_keluar_gudang),
            $dateInput('planning_loading_2', $r->planning_loading_2),
            $dateInput('tanggal_tiba_gudang_2', $r->tanggal_tiba_gudang_2),
            $dateInput('tanggal_keluar_gudang_2', $r->tanggal_keluar_gudang_2),
            $dateInput('planning_loading_3', $r->planning_loading_3),
            $dateInput('tanggal_tiba_gudang_3', $r->tanggal_tiba_gudang_3),
            $dateInput('tanggal_keluar_gudang_3', $r->tanggal_keluar_gudang_3),
            // 15 tujuan
           // 15 tujuan
'<span class="fw-medium">' . e($r->tujuan) . '</span>'
. '<input type="hidden" ' . $formAttr . ' name="tujuan" value="' . e($r->tujuan) . '">',
            // 16 route (required)
            $buildSelect('route', $r->route, $lists['routeList'], 'row-route'),
            // 17 pulau
            $buildSelect('pulau', $r->pulau, $lists['pulauList'], 'row-pulau'),
            // 18 area
            $buildSelect('area', $r->area, $lists['areas'], 'row-area'),
            // 19 via kirim
            $textInput('via_kirim', $r->via_kirim),
            // 20 dist channel
            $buildSelect('dist_channel', $r->dist_channel, $lists['distChannelList'], 'row-dist-channel'),
            // 21 kategori ekspedisi
            $textInput('kategori_ekspedisi', $r->kategori_ekspedisi),
            // 22 ekpedisi (required)
            $buildSelect('ekpedisi', $r->ekpedisi, $lists['ekpedisiList'], 'row-ekpedisi'),
            // 23 lead time
            $textInput('transport_lead_time', $r->transport_lead_time),
            // 24 nama driver (required)
            $textInput('nama_driver', $r->nama_driver),
            // 25 no pol (required)
            $textInput('no_pol', $r->no_pol),
            // 26 mobil (required)
            $buildSelect('mobil', $r->mobil, $lists['mobilList'], 'row-mobil'),
            // 27 total qty
            '<input type="number" ' . $formAttr . ' name="total_do_qty_car" value="' . e($r->total_do_qty_car) . '">',
            // 28 nilai muatan
            $textInput('nilai_muatan', $formattedRupiah($r->nilai_muatan), 'row-nilai-muatan input-rupiah'),
            // 29 biaya kirim
            $textInput('biaya_kirim', $formattedRupiah($r->biaya_kirim), 'row-biaya-kirim input-rupiah'),
          
           
            // 30 cr
            '<input type="text" ' . $formAttr . ' name="cr" class="row-cr" readonly style="background:#f1f5f9;color:#0284c7;font-weight:600;" value="' . e(is_numeric($r->cr) ? number_format((float) $r->cr, 4) : $r->cr) . '">',
// SESUDAH — text input, diformat pakai helper yang sudah ada
$textInput('kubikasi', $formattedPersen($r->kubikasi), 'row-kubikasi'),
// tonase (readonly, hasil lookup tarif)
'<input type="text" readonly style="background:#f1f5f9;" value="' . e($r->tonase !== null ? number_format((float)$r->tonase, 2, ',', '.') . '%' : '-') . '">',

// total kubik (manual)
$textInput('total_kubik', $r->total_kubik, 'row-total-kubikasi'),
$textInput('total_tonase', $r->total_tonase, 'row-total-tonase'),

// hasil kubik
'<span>' . e($r->hasil_kubik !== null ? number_format((float)$r->hasil_kubik, 2, ',', '.') . '%' : '-') . '</span>',

// hasil tonase
'<span>' . e($r->hasil_tonase !== null ? number_format((float)$r->hasil_tonase, 2, ',', '.') . '%' : '-') . '</span>',

// pengiriman optimal badge
$r->pengiriman_optimal === 'OPTIMAL'
    ? '<span class="badge green">✅ Optimal</span>'
    : ($r->pengiriman_optimal ? '<span class="badge orange">⚠️ Tidak Optimal</span>' : '<span class="badge gray">-</span>'),
    $buildSelect('reason_optimal', $r->reason_optimal, $lists['reasonOptimalList'], 'row-reason-optimal'),
            // 31 status mobil
            $statusMobilHtml,
            // 32 lama waktu pencarian
            '<span class="text-primary fw-medium">' . e($r->lama_waktu_pencarian) . '</span>',
            // 33 sla dapat mobil
            $slaMobilHtml,
            // 34-36 KACS
            $durasiStatus($r->planning_loading, $r->tanggal_tiba_gudang),
            $statusBadge($r->planning_loading, $r->tanggal_tiba_gudang),
            $slaBadge($r->planning_loading, $r->tanggal_tiba_gudang),
            // 37-39 Sentul
            $durasiStatus($r->planning_loading_2, $r->tanggal_tiba_gudang_2),
            $statusBadge($r->planning_loading_2, $r->tanggal_tiba_gudang_2),
            $slaBadge($r->planning_loading_2, $r->tanggal_tiba_gudang_2),
            // 40-42 CCIE
            $durasiStatus($r->planning_loading_3, $r->tanggal_tiba_gudang_3),
            $statusBadge($r->planning_loading_3, $r->tanggal_tiba_gudang_3),
            $slaBadge($r->planning_loading_3, $r->tanggal_tiba_gudang_3),
            // 43 shipping point
            $r->route ? explode('-', trim($r->route))[0] : '-',
            // 44 kelengkapan data
            $kelengkapanHtml,
            // 45 hapus
            '<div class="btn-action"><a href="' . route('planner.delete', $id) . '" class="btn btn-danger btn-sm px-2 d-flex align-items-center gap-1" onclick="return confirm(\'Hapus data ini?\')"><i class="fa-solid fa-trash"></i> Del</a></div>',
        ];
    }


    private function cariKapasitasTarif($route, $mobil, $ekpedisi = null)
{
    if (!$route || !$mobil) {
        return ['kubikasi' => null, 'tonase' => null];
    }

    $normalize = function ($v) {
        if (!$v) return '';
        $v = str_replace("\xc2\xa0", ' ', $v);
        $v = preg_replace('/\s*-\s*/', '-', $v);
        $v = preg_replace('/\s+/', ' ', trim($v));
        return mb_strtolower($v);
    };

    $routeKey    = $normalize($route);
    $mobilKey    = $normalize($mobil);
    $ekpedisiKey = $ekpedisi ? $normalize($ekpedisi) : '';

    $candidates = DB::table('tarif_pengiriman')
        ->whereNotNull('route')
        ->whereNotNull('mobil')
        ->get()
        ->filter(fn($t) => $normalize($t->route) === $routeKey);

    if ($candidates->isEmpty()) {
        return ['kubikasi' => null, 'tonase' => null];
    }

    $match = null;

    if ($ekpedisiKey !== '') {
        $match = $candidates->first(function ($t) use ($normalize, $ekpedisiKey, $mobilKey) {
            return $normalize($t->ekpedisi) === $ekpedisiKey
                && str_starts_with($normalize($t->mobil), $mobilKey);
        });
    }

    if (!$match) {
        $match = $candidates->first(fn($t) => str_starts_with($normalize($t->mobil), $mobilKey));
    }

    return [
        'kubikasi' => $match->kubikasi ?? null,
        'tonase'   => $match->tonase ?? null,
    ];
}

private function hitungHasilOptimal($totalKubik, $kubikasi, $totalTonase, $tonase)
{
    $hasilKubik  = null;
    $hasilTonase = null;

    if ($kubikasi !== null && (float) $kubikasi > 0 && $totalKubik !== null) {
        $hasilKubik = round(((float) $totalKubik / (float) $kubikasi) * 100, 2);
    }

    if ($tonase !== null && (float) $tonase > 0 && $totalTonase !== null) {
        $hasilTonase = round(((float) $totalTonase / (float) $tonase) * 100, 2);
    }

    $pengirimanOptimal = null;
    if ($hasilKubik !== null || $hasilTonase !== null) {
        $pengirimanOptimal = (($hasilKubik >= 85) || ($hasilTonase >= 85))
            ? 'OPTIMAL'
            : 'TIDAK OPTIMAL';
    }

    return [
        'hasil_kubik'         => $hasilKubik,
        'hasil_tonase'        => $hasilTonase,
        'pengiriman_optimal'  => $pengirimanOptimal,
    ];
}

private function cleanDecimalPlanner($value): ?float
{
    if ($value === null || $value === '' || $value === '-') return null;

    if (is_numeric($value)) return (float) $value;

    $value = trim((string) $value);

    if (preg_match('/^\d+,\d+$/', $value)) {
        $value = str_replace(',', '.', $value);
    } else {
        $value = str_replace(',', '', $value);
    }

    return is_numeric($value) ? (float) $value : null;
}
    /**
     * =====================================================
     * ALERT CONTROL (ringkasan field kosong) — query ringan,
     * tidak perlu load semua 3000 baris ke PHP/JS.
     * =====================================================
     */
public function alerts(Request $request)
{
    $fieldsMap = [
        'mobil'       => 'Mobil',
        'ekpedisi'    => 'Ekspedisi',
        'route'       => 'Route',
        'kubikasi'       => 'Kubikasi',
        'nama_driver' => 'Nama Driver',
        'no_pol'      => 'No Pol',
    ];

    $query = DB::table('logistik_pengiriman')
        ->select('id', 'no_shipment', 'mobil', 'ekpedisi', 'route', 'kubikasi', 'nama_driver', 'no_pol');

    // ===== FILTER ikut sama seperti dataAjax() =====
    if ($request->filled('planner_filter')) {
        $query->where('planner', $request->input('planner_filter'));
    }
    if ($request->filled('area_filter')) {
        $query->where('area', $request->input('area_filter'));
    }

    $rows = $query->where(function ($q) {
            $q->whereNull('mobil')->orWhere('mobil', '')
              ->orWhereNull('ekpedisi')->orWhere('ekpedisi', '')
              ->orWhereNull('route')->orWhere('route', '')
              ->orWhereNull('kubikasi')->orWhere('kubikasi', '')
              ->orWhereNull('nama_driver')->orWhere('nama_driver', '')
              ->orWhereNull('no_pol')->orWhere('no_pol', '');
        })
        ->get();

    $alertList = [];
    $missingSummary = [];

    foreach ($rows as $r) {
        $missing = [];
        foreach ($fieldsMap as $col => $label) {
            if (trim((string) $r->$col) === '') {
                $missing[] = $label;
                $missingSummary[$label] = ($missingSummary[$label] ?? 0) + 1;
            }
        }
        $alertList[] = [
            'id'         => $r->id,
            'shipment'   => $r->no_shipment ?: '(tanpa no shipment)',
            'missing'    => $missing,
            'emptyCount' => count($missing),
        ];
    }

    usort($alertList, fn ($a, $b) => $b['emptyCount'] <=> $a['emptyCount']);

    return response()->json([
        'alerts'         => $alertList,
        'missingSummary' => $missingSummary,
    ]);
}
    private function cariBiayaKirimOtomatis($route, $mobil, $ekpedisi = null)
    {
        if (!$route || !$mobil) {
            return null;
        }

        $normalize = function ($v) {
            if (!$v) return '';
            $v = str_replace("\xc2\xa0", ' ', $v);
            $v = preg_replace('/\s*-\s*/', '-', $v);
            $v = preg_replace('/\s+/', ' ', trim($v));
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

        if ($ekpedisiKey !== '') {
            $strict = $candidates->first(function ($t) use ($normalize, $ekpedisiKey, $mobilKey) {
                return $normalize($t->ekpedisi) === $ekpedisiKey
                    && str_starts_with($normalize($t->mobil), $mobilKey);
            });

            if ($strict) {
                return $strict->biaya_kirim;
            }
        }

        $fallback = $candidates->first(fn ($t) => str_starts_with($normalize($t->mobil), $mobilKey));

        return $fallback->biaya_kirim ?? null;
    }

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

    private function getKeluarGudangInfoRequest($request)
    {
        $cycles = [
            ['planning' => $request->planning_loading,   'tiba' => $request->tanggal_tiba_gudang,   'keluar' => $request->tanggal_keluar_gudang],
            ['planning' => $request->planning_loading_2, 'tiba' => $request->tanggal_tiba_gudang_2, 'keluar' => $request->tanggal_keluar_gudang_2],
            ['planning' => $request->planning_loading_3, 'tiba' => $request->tanggal_tiba_gudang_3, 'keluar' => $request->tanggal_keluar_gudang_3],
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
                return [
                    'text' => '0 Menit',
                    'days' => 0,
                    'hours' => 0,
                    'minutes' => 0,
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

            $tanggalRencana = strtotime(date('Y-m-d', strtotime($start)));
            $tanggalDptUnit = strtotime(date('Y-m-d', strtotime($end)));

            $selisihHari = floor(
                ($tanggalDptUnit - $tanggalRencana) / 86400
            );

            if (
                $area == 'JABODETABEK' ||
                $area == 'JABODEBEK' ||
                $area == 'BANTEN'
            ) {
                $batasHari = 0;
            } elseif ($area == 'JAWA_BARAT' || $area == 'JAWA BARAT') {
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

        } else {
            $data['sla_dapat_mobil']   = null;
            $data['status_pengiriman'] = null;
        }

        if ($request->tanggal_tiba_gudang && $request->tanggal_keluar_gudang) {

            $diff = $hitungSelisih(
                $request->tanggal_tiba_gudang,
                $request->tanggal_keluar_gudang
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

        if ($request->tanggal_tiba_gudang_2 && $request->tanggal_keluar_gudang_2) {

            $diff = $hitungSelisih(
                $request->tanggal_tiba_gudang_2,
                $request->tanggal_keluar_gudang_2
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

        if ($request->tanggal_tiba_gudang_3 && $request->tanggal_keluar_gudang_3) {

            $diff = $hitungSelisih(
                $request->tanggal_tiba_gudang_3,
                $request->tanggal_keluar_gudang_3
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

        $sla = $this->hitungSla($request);

        $data = [

            'tanggal_tiba_gudang_2'   => $request->tanggal_tiba_gudang_2,
            'tanggal_keluar_gudang_2' => $request->tanggal_keluar_gudang_2,

            'tanggal_tiba_gudang_3'   => $request->tanggal_tiba_gudang_3,
            'tanggal_keluar_gudang_3' => $request->tanggal_keluar_gudang_3,

            'lama_digudang_2' => $sla['lama_digudang_2'],
            'status_gudang_2' => $sla['status_gudang_2'],
            'sla_loading_2'   => $sla['sla_loading_2'],

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
            ->filter(fn($row) => $row->sla_dapat_mobil === 'Delay');

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