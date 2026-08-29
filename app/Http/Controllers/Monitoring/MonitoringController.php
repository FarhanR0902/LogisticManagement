<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Models\LogistikPengiriman;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Exports\MonitoringExport;
use Maatwebsite\Excel\Facades\Excel;

class MonitoringController extends Controller
{

    // =====================================================
    // DASHBOARD (tidak berubah — sudah pakai COUNT/aggregate,
    // bukan load semua baris jadi objek PHP)
    // =====================================================

    public function dashboard()
    {
        $total_data = LogistikPengiriman::count();

        $total_tiba_ontime = LogistikPengiriman::where('sla_tiba', 'On Time')->count();
        $total_tiba_delay = LogistikPengiriman::where('sla_tiba', 'Delay')->count();

        $total_bongkar_ontime = LogistikPengiriman::where('sla_bongkar', 'On Time')->count();
        $total_bongkar_delay = LogistikPengiriman::where('sla_bongkar', 'Delay')->count();

        $total_ontime_total = LogistikPengiriman::where('status_akhir', 'On Time Total')->count();
        $total_delay_perjalanan = LogistikPengiriman::where('status_akhir', 'Delay Perjalanan')->count();
        $total_delay_pembongkaran = LogistikPengiriman::where('status_akhir', 'Delay Pembongkaran')->count();
        $total_delay_total = LogistikPengiriman::where('status_akhir', 'Delay Total')->count();

        $delivered_ontime = LogistikPengiriman::where('monitoring_alert', 'Delivered On Time')->count();
        $delivered_delay = LogistikPengiriman::where('monitoring_alert', 'Delivered Delay')->count();

        $belum_tiba = LogistikPengiriman::whereNull('tanggal_tiba')->count();
        $belum_bongkar = LogistikPengiriman::whereNotNull('tanggal_tiba')
            ->whereNull('tanggal_bongkar')
            ->count();

        $summary_area = LogistikPengiriman::select('area', DB::raw('COUNT(*) as total'))
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
            new MonitoringExport($request->pic_monitoring, $request->area),
            'Monitoring_Logistik.xlsx'
        );
    }

    // =====================================================
    // HALAMAN DATA MONITORING
    // FIX PERFORMA: tidak lagi query semua baris ke Blade.
    // View sekarang ambil data lewat DataTables server-side
    // (endpoint dataAjax()). Di sini hanya kirim dropdown list
    // (di-cache 1 jam karena jarang berubah) + modal shipment laut.
    // =====================================================
    public function dataLogistik(Request $request)
    {
        $areaList = Cache::remember('monitoring_area_list', 3600, function () {
            return LogistikPengiriman::whereNotNull('area')
                ->distinct()->orderBy('area')->pluck('area');
        });

        $picList = Cache::remember('monitoring_pic_list', 3600, function () {
            return LogistikPengiriman::whereNotNull('pic_monitoring')
                ->distinct()->orderBy('pic_monitoring')->pluck('pic_monitoring');
        });

        $akurasiTiba = Cache::remember('monitoring_akurasi_tiba', 3600, function () {
            return DB::table('akurasi3')->distinct()->pluck('akurasi_waktu_tiba');
        });

        $akurasiBongkar = Cache::remember('monitoring_akurasi_bongkar', 3600, function () {
            return DB::table('akurasi3')->distinct()->pluck('akurasi_waktu_bongkar');
        });

        $akurasiQty = Cache::remember('monitoring_akurasi_qty', 3600, function () {
            return DB::table('akurasi3')->distinct()->pluck('remarks_qty');
        });

        // untuk dropdown "No Shipment" di modal shipment laut —
        // ambil list ringan (id + no_shipment + tujuan saja), bukan full row
        $shipmentList = LogistikPengiriman::select('no_shipment', 'tujuan')
            ->whereNotNull('no_shipment')
            ->distinct()
            ->orderBy('no_shipment')
            ->get();

        return view('monitoring.data_monitoring', compact(
            'areaList',
            'picList',
            'akurasiTiba',
            'akurasiBongkar',
            'akurasiQty',
            'shipmentList'
        ));
    }

    // =====================================================
    // ENDPOINT SERVER-SIDE DATATABLES
    // Hanya ambil & hitung baris yang benar-benar tampil
    // (10-100 baris per request), bukan semua data sekaligus.
    // =====================================================
    public function dataAjax(Request $request)
    {
        
        $draw   = (int) $request->input('draw', 1);
        $start  = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $searchValue = trim((string) $request->input('search.value', ''));

        $baseQuery = LogistikPengiriman::query();

        // ================= FILTER =================
        if ($request->filled('jenis')) {
            $baseQuery->where('transportasi', strtoupper($request->jenis));
        }
        if ($request->filled('area')) {
            $baseQuery->where('area', $request->input('area'));
        }
        if ($request->filled('pic_monitoring')) {
            $baseQuery->where('pic_monitoring', $request->input('pic_monitoring'));
        }
        if ($request->filled('bulan')) {
            $baseQuery->whereRaw("
                MONTH(GREATEST(
                    COALESCE(tanggal_keluar_gudang,'1900-01-01'),
                    COALESCE(tanggal_keluar_gudang_2,'1900-01-01'),
                    COALESCE(tanggal_keluar_gudang_3,'1900-01-01')
                )) = ?
            ", [$request->input('bulan')]);
        }
        if ($request->filled('tahun')) {
            $baseQuery->whereRaw("
                YEAR(GREATEST(
                    COALESCE(tanggal_keluar_gudang,'1900-01-01'),
                    COALESCE(tanggal_keluar_gudang_2,'1900-01-01'),
                    COALESCE(tanggal_keluar_gudang_3,'1900-01-01')
                )) = ?
            ", [$request->input('tahun')]);
        }
        if ($request->filled('keluar_gudang_tgl')) {
            $baseQuery->whereDate('tanggal_keluar_gudang', $request->input('keluar_gudang_tgl'));
        }

        $totalRecords = (clone $baseQuery)->count();

        // ================= GLOBAL SEARCH =================
        if ($searchValue !== '') {
            $baseQuery->where(function ($q) use ($searchValue) {
                $cols = [
                    'no_shipment', 'tujuan', 'area', 'ekpedisi',
                    'dist_channel', 'pic_monitoring', 'remarks',
                ];
                foreach ($cols as $col) {
                    $q->orWhere($col, 'like', "%{$searchValue}%");
                }
            });
        }

        $recordsFiltered = (clone $baseQuery)->count();

        // ================= ORDERING (klik header) =================
        // Map index kolom di tabel (harus sinkron dgn <thead> di blade)
        // ke nama kolom asli di database. Kolom yg isinya badge/HTML
        // hasil kalkulasi (Alert, Status Bongkar, Kelengkapan, Action)
        // sengaja tidak dimasukkan -> disable orderable di JS.
        $orderColumnMap = [
            0  => 'tanggal_keluar_gudang',
            1  => 'create_tgl',
            2  => 'dist_channel',
            3  => 'area',
            4  => 'no_shipment',
            5  => 'tujuan',
            6  => 'ekpedisi',
            7  => 'pic_monitoring',
            8  => 'status_kendaraan',
            10 => 'total_do_qty_car',
            11 => 'selisih_qty',
            12 => 'biaya_kuli',
            13 => 'total_biaya_kuli',
            14 => 'qty_monitoring',
            15 => 'remarks_qty',
            16 => 'act_urutan_bongkar',
            17 => 'estimasi_tiba',
            18 => 'tanggal_tiba',
            19 => 'lama_perjalanan',
            20 => 'sla_tiba',
            21 => 'tanggal_bongkar',
            23 => 'overstay_days',
            24 => 'sla_bongkar',
            25 => 'reason_tiba',
            26 => 'reason_bongkar',
            27 => 'remarks',
            28 => 'nama_kapal',
            29 => 'etd',
            30 => 'eta',
            31 => 'atd',
            32 => 'ata',
        ];

        $orderCol = $request->input('order.0.column');
        $orderDir = strtolower($request->input('order.0.dir', 'asc')) === 'desc' ? 'DESC' : 'ASC';

        if ($orderCol !== null && isset($orderColumnMap[$orderCol])) {
            $baseQuery->orderBy($orderColumnMap[$orderCol], $orderDir);
            // tie-breaker biar urutan stabil antar baris yg nilainya sama
            $baseQuery->orderBy('no_shipment', 'ASC')->orderBy('act_urutan_bongkar', 'ASC');
        } else {
            $baseQuery->orderBy('no_shipment', 'ASC')->orderBy('act_urutan_bongkar', 'ASC');
        }

        $rows = $baseQuery
            ->skip($start)
            ->take($length)
            ->get();

        // ============================================================
        // Hitung estimasi/blocked per grup no_shipment DALAM PAGE INI
        // saja (kalau baris belum punya estimasi_tiba tersimpan).
        // Ini jauh lebih ringan drpd hitung utk SEMUA data tiap request.
        // ============================================================
        $grouped = $rows->groupBy('no_shipment');
    foreach ($grouped as $shipment => $items) {
    $gudangInfo = $this->getKeluarGudangInfo($items->first()); // atau mergeGudangFields kalau udah diterapkan
    $keluar  = $gudangInfo['keluar'];
    $blocked = $gudangInfo['blocked'];
    $blockedStatus = $gudangInfo['blocked_status'];
    $leadtime = (int) ($items->first()->transport_lead_time ?? 0);

    $estimasi = (!$blocked && $keluar)
        ? strtotime("+{$leadtime} days", $keluar)
        : null;

    foreach ($items as $r) {
        $r->_keluar = $keluar;
        $r->_blocked = $blocked;
        $r->_blocked_status = $blockedStatus;
        $r->_tanggal_estimasi = $r->estimasi_tiba
            ? strtotime($r->estimasi_tiba)
            : $estimasi;
    }
}
        $akurasiTiba = Cache::remember('monitoring_akurasi_tiba', 3600, function () {
            return DB::table('akurasi3')->distinct()->pluck('akurasi_waktu_tiba');
        });
        $akurasiBongkar = Cache::remember('monitoring_akurasi_bongkar', 3600, function () {
            return DB::table('akurasi3')->distinct()->pluck('akurasi_waktu_bongkar');
        });
        $akurasiQty = Cache::remember('monitoring_akurasi_qty', 3600, function () {
            return DB::table('akurasi3')->distinct()->pluck('remarks_qty');
        });

        $lists = compact('akurasiTiba', 'akurasiBongkar', 'akurasiQty');

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
     * Bangun 1 baris (array kolom, index HARUS sinkron dengan
     * <thead> di data_monitoring.blade.php) — semua badge/alert/
     * kelengkapan dihitung di PHP di sini (bukan di JS lagi),
     * supaya frontend tinggal render, tidak perlu loop ribuan baris.
     */
    private function renderRowColumns($r, array $lists)
    {
        $id = $r->id;
        $rowAttr = 'data-id="' . $id . '"';

        $textInput = function ($name, $value) {
            return '<input type="text" name="' . $name . '" value="' . e($value) . '">';
        };

        $numberInput = function ($name, $value) {
            return '<input type="number" name="' . $name . '" value="' . e($value) . '">';
        };

        $selectBox = function ($name, $selected, $options, $placeholder) {
            $html = '<select name="' . $name . '" class="reason-select searchable-select">';
            $html .= '<option value="">' . e($placeholder) . '</option>';
            foreach ($options as $opt) {
                $sel = ((string) $selected === (string) $opt) ? ' selected' : '';
                $html .= '<option value="' . e($opt) . '"' . $sel . '>' . e($opt) . '</option>';
            }
            $html .= '</select>';
            return $html;
        };

        $keluar   = $r->_keluar ?? null;
        $blocked  = $r->_blocked ?? false;
        $estimasi = $r->_tanggal_estimasi ?? null;
        $blockedStatus = $r->_blocked_status ?? null;

        $tiba = $r->tanggal_tiba ? strtotime($r->tanggal_tiba) : null;
        $blockedLabel = $blockedStatus === 'sedang'
    ? 'Sedang di Gudang Berikutnya'
    : 'Menuju Gudang Berikutnya';

        $lama_perjalanan = '-';
        if ($tiba && $keluar) {
            $lama_perjalanan = floor(($tiba - $keluar) / 86400);
        }

        $alert = '-';
        $alertClass = '';
        $estimasi_show = '-';
        

if ($blocked) {
    $estimasi_show = $blockedLabel;
    $alertClass = 'gray';
} else {
    $estimasi_show = $estimasi ? date('d-m-Y', $estimasi) : '-';

            if (!$r->tanggal_tiba && $estimasi) {
                $today = strtotime(date('Y-m-d'));
                $hariSisa = floor(($estimasi - $today) / 86400);

                if ($hariSisa < 0) {
                    $alert = 'Pending Tiba  H+' . abs($hariSisa);
                    $alertClass = 'red';
                } elseif ($hariSisa == 0) {
                    $alert = 'H-0'; $alertClass = 'red';
                } elseif ($hariSisa == 1) {
                    $alert = 'H-1'; $alertClass = 'red';
                } elseif ($hariSisa == 2) {
                    $alert = 'H-2'; $alertClass = 'orange';
                } elseif ($hariSisa == 3) {
                    $alert = 'H-3'; $alertClass = 'orange';
                } elseif ($hariSisa <= 7) {
                    $alert = 'H-' . $hariSisa; $alertClass = 'blue';
                } else {
                    $alert = 'ON TRACK'; $alertClass = 'green';
                }
            }
        }

        $alertHtml = $r->tanggal_tiba
            ? '<span class="badge green">✅ TIBA</span>'
            : '<span class="badge ' . $alertClass . '">' . e($alert) . '</span>';

        $sla_tiba = $r->sla_tiba ?? '-';
        $sla_tiba_html = $sla_tiba == '-'
            ? '-'
            : ($sla_tiba == 'On Time'
                ? '<span class="badge green">On Time</span>'
                : '<span class="badge red">' . e($sla_tiba) . '</span>');

        $sla_bongkar = $r->sla_bongkar ?? '-';
        $sla_bongkar_html = $sla_bongkar == '-'
            ? '-'
            : ($sla_bongkar == 'On Time'
                ? '<span class="badge green">On Time</span>'
                : '<span class="badge red">' . e($sla_bongkar) . '</span>');

        $statusBongkar = '-';
        $statusBongkarClass = '';
        if ($r->tanggal_bongkar) {
            $statusBongkar = 'Sudah Bongkar';
            $statusBongkarClass = 'green';
        } elseif ($r->tanggal_tiba) {
            $tanggalTiba = strtotime(date('Y-m-d', strtotime($r->tanggal_tiba)));
            $today = strtotime(date('Y-m-d'));
            $hariBongkar = floor(($today - $tanggalTiba) / 86400);
            $statusBongkar = 'Pending Bongkar H+' . max(0, $hariBongkar);
            $statusBongkarClass = $hariBongkar == 0 ? 'orange' : 'red';
        }
        $statusBongkarHtml = $statusBongkar != '-'
            ? '<span class="badge ' . $statusBongkarClass . '">' . e($statusBongkar) . '</span>'
            : '-';

        // ===== Kelengkapan Data (per baris, sama logic seperti JS lama) =====
        $today = strtotime(date('Y-m-d'));
        $isOverdue = ($estimasi && !$blocked) ? ($estimasi < $today) : false;

        $missing = [];
        if ($isOverdue) {
            if (empty($r->tanggal_tiba)) $missing[] = 'Tgl Tiba';
            if (empty($r->tanggal_bongkar)) $missing[] = 'Tgl Bongkar';
        }

        if (!$isOverdue) {
            $kelengkapanHtml = '<span class="badge completeness-badge gray" title="Belum jatuh tempo estimasi tiba">-</span>';
        } elseif (count($missing) === 0) {
            $kelengkapanHtml = '<span class="badge completeness-badge green" title="Data lengkap">✅ Lengkap</span>';
        } else {
            $cls = count($missing) === 1 ? 'orange' : 'red';
            $text = '❌ ' . implode(', ', $missing);
            $kelengkapanHtml = '<span class="badge completeness-badge ' . $cls . '" title="' . e($text) . '">' . e($text) . '</span>';
        }

       return [
   // 0 Tanggal Keluar Gudang
$blocked
    ? '<span class="badge red">' . e($blockedLabel) . '</span>'
    : ($keluar ? '<span class="badge green">' . date('d-m-Y', $keluar) . '</span>' : '-'),
            // 1 Act PGI Date (editable)
                  e($r->create_tgl),
            // 2 Dist Channel
            e($r->dist_channel),
            // 3 Area
            e($r->area),
            // 4 No Shipment
            e($r->no_shipment),
            // 5 Tujuan
            e($r->tujuan),
            // 6 Ekspedisi
            e($r->ekpedisi),
            // 7 PIC (editable)
            $textInput('pic_monitoring', $r->pic_monitoring),
            // 8 Status (editable select)
            '<select name="status_kendaraan" class="form-select status-select">'
                . '<option value="On Track"' . ($r->status_kendaraan == 'On Track' ? ' selected' : '') . '>🟢 On Track</option>'
                . '<option value="Potential Delay"' . ($r->status_kendaraan == 'Potential Delay' ? ' selected' : '') . '>🔴 Potential Delay</option>'
                . '</select>',
            // 9 Alert
            $alertHtml,
            // 10 Total DO Qty
            e($r->total_do_qty_car),
            // 11 Selisih Qty Do (editable)
            '<input type="number" name="selisih_qty" class="row-selisih-qty" data-total-do="' . e($r->total_do_qty_car) . '" value="' . e($r->selisih_qty) . '">',
            // 12 Biaya Kuli (editable)
            '<input type="number" name="biaya_kuli" class="row-biaya-kuli" value="' . e($r->biaya_kuli) . '">',
            // 13 Total Biaya Kuli
            '<input type="text" name="total_biaya_kuli" class="row-total-biaya-kuli" value="Rp ' . number_format($r->total_biaya_kuli ?? 0, 0, ',', '.') . '" readonly>',
            // 14 Qty Actual Do
            '<input type="number" name="qty_monitoring" class="row-qty-monitoring" value="' . e($r->qty_monitoring) . '" readonly style="background:#f1f5f9;color:#0284c7;font-weight:600;">',
            // 15 Reason Qty (editable select)
            $selectBox('remarks_qty', $r->remarks_qty, $lists['akurasiQty'], 'Pilih Reason Qty'),
            // 16 Urutan Bongkar (editable)
            $numberInput('act_urutan_bongkar', $r->act_urutan_bongkar),
            // 17 Estimasi Tiba
            e($estimasi_show),
            // 18 Tanggal Tiba (editable)
            '<input type="datetime-local" name="tanggal_tiba" data-required="true" data-label="Tgl Tiba" value="' . ($r->tanggal_tiba ? date('Y-m-d\TH:i', strtotime($r->tanggal_tiba)) : '') . '">',
            // 19 Lama Perjalanan
            e($lama_perjalanan),
            // 20 SLA Tiba
            $sla_tiba_html,
            // 21 Tanggal Bongkar (editable)
            '<input type="datetime-local" name="tanggal_bongkar" data-required="true" data-label="Tgl Bongkar" value="' . ($r->tanggal_bongkar ? date('Y-m-d\TH:i', strtotime($r->tanggal_bongkar)) : '') . '">',
            // 22 Status Bongkar
            $statusBongkarHtml,
            // 23 Overstay
            e($r->overstay_days ?? '-'),
            // 24 SLA Bongkar
            $sla_bongkar_html,
            // 25 Reason Tiba (editable select)
            $selectBox('reason_tiba', $r->reason_tiba, $lists['akurasiTiba'], 'Pilih Reason Tiba'),
            // 26 Reason Bongkar (editable select)
            $selectBox('reason_bongkar', $r->reason_bongkar, $lists['akurasiBongkar'], 'Pilih Reason Bongkar'),
            // 27 Remarks (editable)
            $textInput('remarks', $r->remarks),
            // 28 Nama Kapal (editable)
            $textInput('nama_kapal', $r->nama_kapal),
            // 29 ETD
            '<input type="date" name="ETD" value="' . ($r->etd ? date('Y-m-d', strtotime($r->etd)) : '') . '">',
            // 30 ETA
            '<input type="date" name="ETA" value="' . ($r->eta ? date('Y-m-d', strtotime($r->eta)) : '') . '">',
            // 31 ATD
            '<input type="date" name="ATD" value="' . ($r->atd ? date('Y-m-d', strtotime($r->atd)) : '') . '">',
            // 32 ATA
            '<input type="date" name="ATA" value="' . ($r->ata ? date('Y-m-d', strtotime($r->ata)) : '') . '">',
            // 33 Kelengkapan Data
            $kelengkapanHtml,
            // 34 Action
            '<span class="save-status"></span><button type="button" class="save-btn" data-id="' . $id . '" onclick="saveRow(this)">SAVE</button>',
        ];
    }

    // =====================================================
    // ALERT CONTROL — query ringan, HANYA ambil shipment yang
    // sudah lewat estimasi_tiba dan Tgl Tiba/Tgl Bongkar belum
    // diisi. Tidak perlu load semua ribuan baris ke PHP/JS.
    // =====================================================
    public function alerts(Request $request)
    {
        $today = date('Y-m-d');

        $query = DB::table('logistik_pengiriman')
            ->select('id', 'no_shipment', 'estimasi_tiba', 'tanggal_tiba', 'tanggal_bongkar')
            ->whereNotNull('estimasi_tiba')
            ->where('estimasi_tiba', '<', $today)
            ->where(function ($q) {
                $q->whereNull('tanggal_tiba')->orWhereNull('tanggal_bongkar');
            });

        // ================= FILTER — samain persis dgn dataAjax() =================
        if ($request->filled('jenis')) {
            $query->where('transportasi', strtoupper($request->input('jenis')));
        }
        if ($request->filled('pic_monitoring')) {
            $query->where('pic_monitoring', $request->input('pic_monitoring'));
        }
        if ($request->filled('area')) {
            $query->where('area', $request->input('area'));
        }
        if ($request->filled('bulan')) {
            $query->whereRaw("
                MONTH(GREATEST(
                    COALESCE(tanggal_keluar_gudang,'1900-01-01'),
                    COALESCE(tanggal_keluar_gudang_2,'1900-01-01'),
                    COALESCE(tanggal_keluar_gudang_3,'1900-01-01')
                )) = ?
            ", [$request->input('bulan')]);
        }
        if ($request->filled('tahun')) {
            $query->whereRaw("
                YEAR(GREATEST(
                    COALESCE(tanggal_keluar_gudang,'1900-01-01'),
                    COALESCE(tanggal_keluar_gudang_2,'1900-01-01'),
                    COALESCE(tanggal_keluar_gudang_3,'1900-01-01')
                )) = ?
            ", [$request->input('tahun')]);
        }
        if ($request->filled('keluar_gudang_tgl')) {
            $query->whereDate('tanggal_keluar_gudang', $request->input('keluar_gudang_tgl'));
        }

        $rows = $query->orderBy('estimasi_tiba', 'ASC')->limit(200)->get();

        $alertList = [];
        $missingSummary = [];

        foreach ($rows as $r) {
            $missing = [];

            if (empty($r->tanggal_tiba)) {
                $missing[] = 'Tgl Tiba';
                $missingSummary['Tgl Tiba'] = ($missingSummary['Tgl Tiba'] ?? 0) + 1;
            }
            if (empty($r->tanggal_bongkar)) {
                $missing[] = 'Tgl Bongkar';
                $missingSummary['Tgl Bongkar'] = ($missingSummary['Tgl Bongkar'] ?? 0) + 1;
            }

            $alertList[] = [
                'id'         => $r->id,
                'shipment'   => $r->no_shipment,
                'missing'    => $missing,
                'emptyCount' => count($missing),
                'estimasi'   => $r->estimasi_tiba,
            ];
        }

        return response()->json([
            'alerts'         => $alertList,
            'missingSummary' => $missingSummary,
            'totalAlert'     => (clone $query)->count(),
        ]);
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

        $logistik->pic_monitoring   = $request->pic_monitoring;
        $logistik->status_kendaraan = $request->status_kendaraan;
        $logistik->remarks_qty     = $request->remarks_qty;
        $logistik->action_required  = $request->action_required;

        $logistik->act_urutan_bongkar = $request->act_urutan_bongkar;

        $logistik->total_do_qty_car = $request->total_do_qty_car ?? $logistik->total_do_qty_car;
        $logistik->selisih_qty      = $request->selisih_qty;
        $logistik->biaya_kuli       = $request->biaya_kuli;

        $logistik->qty_monitoring = ($logistik->total_do_qty_car ?? 0) - ($logistik->selisih_qty ?? 0);
        $logistik->total_biaya_kuli = ($logistik->qty_monitoring ?? 0) * ($logistik->biaya_kuli ?? 0);

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

        if ($request->filled('nama_kapal')) {
            $logistik->nama_kapal = $request->nama_kapal;
            $logistik->etd = $request->etd;
            $logistik->eta = $request->eta;
            $logistik->atd = $request->atd;
            $logistik->ata = $request->ata;
        }
        $logistik->save();

        $shipment = LogistikPengiriman::where('no_shipment', $logistik->no_shipment)->get();

        $baseEstimasi = (!$blocked && $keluar)
            ? strtotime("+{$leadtime} days", $keluar)
            : null;

        $lastBongkar = $shipment->whereNotNull('tanggal_bongkar')->max('tanggal_bongkar');

        $nextEstimasi = $lastBongkar
            ? date('Y-m-d', strtotime($lastBongkar . ' +1 day'))
            : ($baseEstimasi ? date('Y-m-d', $baseEstimasi) : null);

        foreach ($shipment as $item) {
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
        $request->validate(['no_shipment' => 'required']);

        $data = [
            'nama_kapal' => $request->nama_kapal,
            'etd' => $request->etd,
            'eta' => $request->eta,
            'atd' => $request->atd,
            'ata' => $request->ata,
        ];

        LogistikPengiriman::where('no_shipment', $request->no_shipment)->update($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Data transport laut berhasil diupdate'
        ]);
    }

 private function getKeluarGudangInfo($r)
{
    $cycles = [
        ['planning' => $r->planning_loading,   'tiba' => $r->tanggal_tiba_gudang,   'keluar' => $r->tanggal_keluar_gudang],
        ['planning' => $r->planning_loading_2, 'tiba' => $r->tanggal_tiba_gudang_2, 'keluar' => $r->tanggal_keluar_gudang_2],
        ['planning' => $r->planning_loading_3, 'tiba' => $r->tanggal_tiba_gudang_3, 'keluar' => $r->tanggal_keluar_gudang_3],
    ];

    $blocked = false;
    $blockedStatus = null; // 'menuju' | 'sedang'
    $keluarTimestamps = [];

    foreach ($cycles as $c) {
        $hasPlanning = !empty($c['planning']);
        $hasTiba     = !empty($c['tiba']);
        $started     = $hasPlanning || $hasTiba;
        $selesai     = !empty($c['keluar']);

        if ($started && !$selesai) {
            $blocked = true;
            // kalau udah tiba di gudang itu -> "sedang di gudang"
            // kalau baru planning doang -> "menuju gudang"
            $blockedStatus = $hasTiba ? 'sedang' : 'menuju';
        }
        if ($selesai) {
            $keluarTimestamps[] = strtotime($c['keluar']);
        }
    }

    return [
        'blocked'       => $blocked,
        'blocked_status'=> $blockedStatus,
        'keluar'        => !empty($keluarTimestamps) ? max($keluarTimestamps) : null,
    ];
}

    private function generateStatusAlert($sla_tiba, $sla_bongkar)
    {
        $sla_tiba = strtolower(trim($sla_tiba ?? '-'));
        $sla_bongkar = strtolower(trim($sla_bongkar ?? '-'));

        if ($sla_tiba == '-' || $sla_bongkar == '-') {
            return ['status_akhir' => '-', 'alert' => '-'];
        }

        if ($sla_tiba == 'on time' && $sla_bongkar == 'on time') {
            return ['status_akhir' => 'On Time Total', 'alert' => 'Delivered On Time'];
        }

        if ($sla_tiba == 'delay' && $sla_bongkar == 'on time') {
            return ['status_akhir' => 'Delay Perjalanan', 'alert' => 'Delay Perjalanan'];
        }

        if ($sla_tiba == 'on time' && $sla_bongkar == 'delay') {
            return ['status_akhir' => 'Delay Pembongkaran', 'alert' => 'Delay Pembongkaran'];
        }

        return ['status_akhir' => 'Delay Total', 'alert' => 'Delivered Delay'];
    }

    public function bongkarDelay(Request $request)
    {
        $query = DB::table('logistik_pengiriman')
            ->where(function ($q) {
                $q->whereIn('sla_bongkar', ['Delay', 'Critical Delay'])
                    ->orWhere('overstay_days', '>', 0);
            })
            ->whereNotNull('tanggal_bongkar')
            ->where('tanggal_bongkar', '!=', '1899-12-31 00:00:00');

        if ($request->filled('tanggal_bongkar')) {
            $query->whereDate('tanggal_bongkar', $request->tanggal_bongkar);
        }
        if ($request->filled('area')) {
            $query->where('area', $request->area);
        }

        $list = $query->orderByDesc('tanggal_bongkar')->paginate(50)->withQueryString();

        return view('monitoring.bongkar_delay', compact('list'));
    }

    public function bongkarOntime(Request $request)
    {
        $query = DB::table('logistik_pengiriman')
            ->selectRaw("
                *,
                CASE
                    WHEN DATEDIFF(DATE(tanggal_bongkar), DATE(tanggal_tiba)) <= 0
                    THEN 'On Time' ELSE 'Delay'
                END AS sla_bongkar
            ")
            ->whereNotNull('tanggal_bongkar')
            ->whereNotNull('tanggal_tiba')
            ->where('tanggal_bongkar', '!=', '1899-12-31 00:00:00')
            ->whereRaw("DATEDIFF(DATE(tanggal_bongkar), DATE(tanggal_tiba)) <= 0");

        if ($request->filled('tanggal_bongkar')) {
            $query->whereDate('tanggal_bongkar', $request->tanggal_bongkar);
        }
        if ($request->filled('area')) {
            $query->where('area', $request->area);
        }

        $list = $query->orderByDesc('tanggal_bongkar')->paginate(50)->withQueryString();

        return view('monitoring.bongkar_ontime', compact('list'));
    }

    public function slaOntime(Request $request)
    {
        $query = DB::table('logistik_pengiriman')
            ->selectRaw("
                logistik_pengiriman.*,
                estimasi_tiba AS tanggal_estimasi,
                CASE
                    WHEN DATEDIFF(DATE(tanggal_tiba), DATE(estimasi_tiba)) <= 0
                    THEN 'On Time' ELSE 'Delay'
                END AS sla_tiba
            ")
            ->whereNotNull('tanggal_tiba')
            ->whereNotNull('estimasi_tiba');

        $query->havingRaw("DATEDIFF(DATE(tanggal_tiba), DATE(estimasi_tiba)) <= 0");

        if ($request->filled('bulan')) {
            $query->whereMonth('tanggal_tiba', $request->bulan);
        }
        if ($request->filled('tahun')) {
            $query->whereYear('tanggal_tiba', $request->tahun);
        }

        $logistik = $query->orderByDesc('tanggal_tiba')->paginate(50)->withQueryString();

        return view('monitoring.sla_ontime', compact('logistik'));
    }

    public function slaDelay(Request $request)
    {
        $query = DB::table('logistik_pengiriman')
            ->selectRaw("
                logistik_pengiriman.*,
                estimasi_tiba AS tanggal_estimasi,
                CASE
                    WHEN DATEDIFF(DATE(tanggal_tiba), DATE(estimasi_tiba)) > 0
                    THEN 'Delay' ELSE 'On Time'
                END AS sla_tiba
            ")
            ->whereNotNull('tanggal_tiba')
            ->whereNotNull('estimasi_tiba');

        $query->havingRaw("DATEDIFF(DATE(tanggal_tiba), DATE(estimasi_tiba)) > 0");

        if ($request->filled('bulan')) {
            $query->whereMonth('tanggal_tiba', $request->bulan);
        }
        if ($request->filled('tahun')) {
            $query->whereYear('tanggal_tiba', $request->tahun);
        }

        $logistik = $query->orderByDesc('tanggal_tiba')->paginate(50)->withQueryString();

        return view('monitoring.sla_delay', compact('logistik'));
    }

    public function summaryArea()
    {
        $summary_area = DB::table('logistik_pengiriman')
            ->select('area', DB::raw('COUNT(*) as total'))
            ->groupBy('area')
            ->orderByDesc('total')
            ->get();

        return view('monitoring.summary_area', compact('summary_area'));
    }

    public function summaryAreaDetail(Request $request)
    {
        $area = $request->area;

        $logistik = DB::table('logistik_pengiriman')
            ->where('area', $area)
            ->paginate(50)
            ->withQueryString();

        return view('monitoring.summary_area_detail', compact('logistik', 'area'));
    }
}