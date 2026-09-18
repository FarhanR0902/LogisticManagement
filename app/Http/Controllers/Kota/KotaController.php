<?php

namespace App\Http\Controllers\Kota;

use App\Http\Controllers\Controller;
use App\Imports\LogistikKotaImport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\LogistikPengirimanKota;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class KotaController extends Controller
{
    // =====================================================
    // DASHBOARD
    // =====================================================
    public function dashboard()
    {
        $total_data = LogistikPengirimanKota::count();

        $total_tiba_ontime = LogistikPengirimanKota::where('sla_tiba_kota', 'On Time')->count();
        $total_tiba_delay  = LogistikPengirimanKota::where('sla_tiba_kota', 'Delay')->count();

        $total_bongkar_ontime = LogistikPengirimanKota::where('sla_bongkar_kota', 'On Time')->count();
        $total_bongkar_delay  = LogistikPengirimanKota::where('sla_bongkar_kota', 'Delay')->count();

        $total_ontime_total        = LogistikPengirimanKota::where('status_akhir_kota', 'On Time Total')->count();
        $total_delay_perjalanan    = LogistikPengirimanKota::where('status_akhir_kota', 'Delay Perjalanan')->count();
        $total_delay_pembongkaran  = LogistikPengirimanKota::where('status_akhir_kota', 'Delay Pembongkaran')->count();
        $total_delay_total         = LogistikPengirimanKota::where('status_akhir_kota', 'Delay Total')->count();

        $delivered_ontime = LogistikPengirimanKota::where('monitoring_alert_kota', 'Delivered On Time')->count();
        $delivered_delay  = LogistikPengirimanKota::where('monitoring_alert_kota', 'Delivered Delay')->count();

        $belum_tiba = LogistikPengirimanKota::whereNull('tanggal_tiba_kota')->count();
        $belum_bongkar = LogistikPengirimanKota::whereNotNull('tanggal_tiba_kota')
            ->whereNull('tanggal_bongkar_kota')
            ->count();

        $summary_area = LogistikPengirimanKota::select('area_kota', DB::raw('COUNT(*) as total'))
            ->groupBy('area_kota')
            ->orderByDesc('total')
            ->get();

        return view('kota.dashboard_kota', compact(
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

    public function import(Request $request)
{
    $request->validate([
        'file' => 'required|mimes:xlsx,xls,csv',
    ]);

    $import = new LogistikKotaImport;
    Excel::import($import, $request->file('file'));

    return redirect()
        ->route('kota.datalogistik')
        ->with('success', "Import selesai. Masuk: {$import->getImportedCount()}, Skip: {$import->getSkippedCount()}");
}

    // =====================================================
    // HALAMAN DATA MONITORING KOTA
    // =====================================================
    public function dataLogistik(Request $request)
    {
        $areaList = Cache::remember('kota_area_list', 3600, function () {
            return LogistikPengirimanKota::whereNotNull('area_kota')
                ->distinct()->orderBy('area_kota')->pluck('area_kota');
        });

        $picList = Cache::remember('kota_pic_list', 3600, function () {
            return LogistikPengirimanKota::whereNotNull('pic_monitoring_kota')
                ->distinct()->orderBy('pic_monitoring_kota')->pluck('pic_monitoring_kota');
        });

        $tujuanList = Cache::remember('kota_tujuan_list', 3600, function () {
            return DB::table('tujuanfillterr')
                ->whereNotNull('tujuan')->where('tujuan', '!=', '')
                ->distinct()->orderBy('tujuan')->pluck('tujuan');
        });

        $akurasiTiba = Cache::remember('kota_akurasi_tiba', 3600, function () {
            return DB::table('akurasi3')->distinct()->pluck('akurasi_waktu_tiba');
        });

        $akurasiBongkar = Cache::remember('kota_akurasi_bongkar', 3600, function () {
            return DB::table('akurasi3')->distinct()->pluck('akurasi_waktu_bongkar');
        });

        $akurasiQty = Cache::remember('kota_akurasi_qty', 3600, function () {
            return DB::table('akurasi3')->distinct()->pluck('remarks_qty');
        });

        $shipmentList = LogistikPengirimanKota::select('no_shipment_kota', 'tujuan_kota')
            ->whereNotNull('no_shipment_kota')
            ->distinct()
            ->orderBy('no_shipment_kota')
            ->get();

        return view('kota.data_logistik_kota', compact(
            'areaList',
            'picList',
            'tujuanList',
            'akurasiTiba',
            'akurasiBongkar',
            'akurasiQty',
            'shipmentList'
        ));
    }

    // =====================================================
    // ENDPOINT SERVER-SIDE DATATABLES
    // =====================================================
    public function dataAjax(Request $request)
    {
        $draw   = (int) $request->input('draw', 1);
        $start  = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $searchValue = trim((string) $request->input('search.value', ''));

        $baseQuery = LogistikPengirimanKota::query();

        // ================= FILTER =================
        if ($request->filled('area')) {
            $baseQuery->where('area_kota', $request->input('area'));
        }
        if ($request->filled('pic_monitoring')) {
            $baseQuery->where('pic_monitoring_kota', $request->input('pic_monitoring'));
        }
        if ($request->filled('bulan')) {
            $baseQuery->whereRaw("
                MONTH(GREATEST(
                    COALESCE(tanggal_keluar_gudang_kota,'1900-01-01'),
                    COALESCE(tanggal_keluar_gudang_2_kota,'1900-01-01'),
                    COALESCE(tanggal_keluar_gudang_3_kota,'1900-01-01')
                )) = ?
            ", [$request->input('bulan')]);
        }
        if ($request->filled('tahun')) {
            $baseQuery->whereRaw("
                YEAR(GREATEST(
                    COALESCE(tanggal_keluar_gudang_kota,'1900-01-01'),
                    COALESCE(tanggal_keluar_gudang_2_kota,'1900-01-01'),
                    COALESCE(tanggal_keluar_gudang_3_kota,'1900-01-01')
                )) = ?
            ", [$request->input('tahun')]);
        }
        if ($request->filled('keluar_gudang_tgl')) {
            $baseQuery->whereDate('tanggal_keluar_gudang_kota', $request->input('keluar_gudang_tgl'));
        }

        $totalRecords = (clone $baseQuery)->count();

        // ================= GLOBAL SEARCH =================
        if ($searchValue !== '') {
            $baseQuery->where(function ($q) use ($searchValue) {
                $cols = [
                    'no_shipment_kota', 'tujuan_kota', 'area_kota', 'ekpedisi_kota',
                    'dist_channel_kota', 'pic_monitoring_kota', 'remarks_kota',
                    'no_kota', 'nama_driver_kota', 'no_pol_kota', 'nama_kapal_kota',
                ];
                foreach ($cols as $col) {
                    $q->orWhere($col, 'like', "%{$searchValue}%");
                }
            });
        }

        $recordsFiltered = (clone $baseQuery)->count();

        // ================= ORDERING =================
        // Hanya kolom mentah (bukan HTML/badge/computed) yang boleh di-sort.
        // Index di sini HARUS sinkron dengan urutan <thead> di data_logistik_kota.blade.php
        $orderColumnMap = [
            0  => 'id',
            1  => 'no_kota',
            2  => 'tanggal_naik_logistik_kota',
            3  => 'rencana_kirim_kota',
            4  => 'transport_lead_time_kota',
            5  => 'estimasi_admin_kota',
            6  => 'planner_kota',
            7  => 'no_shipment_kota',
            9  => 'area_kota',
            10 => 'ketersediaan_unit_kota',
            11 => 'mobil_kota',
            12 => 'perubahan_mobil_kota',
            13 => 'nilai_muatan_kota',
            14 => 'biaya_kirim_kota',
            17 => 'cr_kota',
            18 => 'kategori_ekspedisi_kota',
            19 => 'ekpedisi_kota',
            25 => 'nama_driver_kota',
            26 => 'no_pol_kota',
            27 => 'status_pengiriman_kota',
            28 => 'tanggal_dpt_unit_kota',
            29 => 'planning_loading_kota',
            30 => 'tanggal_tiba_gudang_kota',
            31 => 'tanggal_keluar_gudang_kota',
            32 => 'lama_digudang_kota',
            33 => 'status_gudang_kota',
            34 => 'sla_loading_kota',
            35 => 'keterangan_kota',
            36 => 'lama_waktu_pencarian_kota',
            37 => 'sla_dapat_mobil_kota',
            40 => 'monitoring_alert_kota',
            41 => 'action_required_kota',
            44 => 'lama_perjalanan_kota',
            45 => 'sla_tiba_kota',
            47 => 'overstay_days_kota',
            48 => 'sla_bongkar_kota',
            51 => 'status_akhir_kota',
            52 => 'created_at',
            53 => 'updated_at',
            54 => 'tanggal_tiba_estimasi_kota',
            56 => 'dist_channel_kota',
            57 => 'transportasi_kota',
            58 => 'transport_laut_kota',
            59 => 'tanggal_tiba_gudang_2_kota',
            60 => 'planning_loading_2_kota',
            61 => 'tanggal_keluar_gudang_2_kota',
            62 => 'lama_digudang_2_kota',
            63 => 'status_gudang_2_kota',
            64 => 'sla_loading_2_kota',
            65 => 'tanggal_tiba_gudang_3_kota',
            66 => 'planning_loading_3_kota',
            67 => 'tanggal_keluar_gudang_3_kota',
            68 => 'lama_digudang_3_kota',
            69 => 'status_gudang_3_kota',
            70 => 'sla_loading_3_kota',
            71 => 'cust_grp_5_desc_kota',
            72 => 'cust_grp_3_desc_kota',
            73 => 'ship_no_kota',
            74 => 'cust_desc_kota',
            75 => 'addt_text_4_kota',
            76 => 'service_agent_kota',
            77 => 'urutan_bongkar_kota',
            79 => 'created_by_kota',
            81 => 'route_kota',
            82 => 'shipping_point_kota',
            83 => 'kubikasi_kota',
            84 => 'pulau_kota',
            85 => 'via_kirim_kota',
            86 => 'estimasi_tiba_kota',
            87 => 'create_tgl_kota',
        ];

        $orderCol = $request->input('order.0.column');
        $orderDir = strtolower($request->input('order.0.dir', 'asc')) === 'desc' ? 'DESC' : 'ASC';

        if ($orderCol !== null && isset($orderColumnMap[$orderCol])) {
            $baseQuery->orderBy($orderColumnMap[$orderCol], $orderDir);
            $baseQuery->orderBy('no_shipment_kota', 'ASC')->orderBy('act_urutan_bongkar_kota', 'ASC');
        } else {
            $baseQuery->orderBy('no_shipment_kota', 'ASC')->orderBy('act_urutan_bongkar_kota', 'ASC');
        }

        $rows = $baseQuery
            ->skip($start)
            ->take($length)
            ->get();

        // ================= ESTIMASI/BLOCKED PER GRUP (page ini saja) =================
        $grouped = $rows->groupBy('no_shipment_kota');
        foreach ($grouped as $shipment => $items) {
            $gudangInfo = $this->getKeluarGudangInfo($items->first());
            $keluar  = $gudangInfo['keluar'];
            $blocked = $gudangInfo['blocked'];
            $blockedStatus = $gudangInfo['blocked_status'];
            $leadtime = (int) ($items->first()->transport_lead_time_kota ?? 0);

            $estimasi = (!$blocked && $keluar)
                ? strtotime("+{$leadtime} days", $keluar)
                : null;

            foreach ($items as $r) {
                $r->_keluar = $keluar;
                $r->_blocked = $blocked;
                $r->_blocked_status = $blockedStatus;
                $r->_tanggal_estimasi = $r->estimasi_tiba_kota
                    ? strtotime($r->estimasi_tiba_kota)
                    : $estimasi;
            }
        }

        $akurasiTiba = Cache::remember('kota_akurasi_tiba', 3600, function () {
            return DB::table('akurasi3')->distinct()->pluck('akurasi_waktu_tiba');
        });
        $akurasiBongkar = Cache::remember('kota_akurasi_bongkar', 3600, function () {
            return DB::table('akurasi3')->distinct()->pluck('akurasi_waktu_bongkar');
        });
        $akurasiQty = Cache::remember('kota_akurasi_qty', 3600, function () {
            return DB::table('akurasi3')->distinct()->pluck('remarks_qty');
        });
        $tujuanList = Cache::remember('kota_tujuan_list', 3600, function () {
            return DB::table('tujuanfillterr')
                ->whereNotNull('tujuan')->where('tujuan', '!=', '')
                ->distinct()->orderBy('tujuan')->pluck('tujuan');
        });

        $lists = compact('akurasiTiba', 'akurasiBongkar', 'akurasiQty', 'tujuanList');

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
     * Bangun 1 baris (96 kolom, index 0-95).
     * HARUS sinkron dengan <thead> di kota/data_logistik_kota.blade.php:
     *   0-90  : kolom mentah persis urutan tabel database
     *   91-95 : kolom hasil kalkulasi (Estimasi Live, Alert, Status Bongkar,
     *           Kelengkapan Data, Aksi)
     */
    private function renderRowColumns($r, array $lists)
    {
        $id = $r->id;

        // ---- helper builder ----
        $textInput = fn($name, $value) =>
            '<input type="text" name="' . $name . '" value="' . e($value) . '">';

        $numberInput = fn($name, $value) =>
            '<input type="number" name="' . $name . '" value="' . e($value) . '">';

        $dateInput = fn($name, $value) =>
            '<input type="date" name="' . $name . '" value="' . ($value ? date('Y-m-d', strtotime($value)) : '') . '">';

        $datetimeInput = fn($name, $value) =>
            '<input type="datetime-local" name="' . $name . '" value="' . ($value ? date('Y-m-d\TH:i', strtotime($value)) : '') . '">';

        $selectBox = function ($name, $selected, $options, $placeholder) {
            $html = '<select name="' . $name . '" class="reason-select searchable-select" data-placeholder="' . e($placeholder) . '">';
            $html .= '<option value="">' . e($placeholder) . '</option>';
            foreach ($options as $opt) {
                $sel = ((string) $selected === (string) $opt) ? ' selected' : '';
                $html .= '<option value="' . e($opt) . '"' . $sel . '>' . e($opt) . '</option>';
            }
            $html .= '</select>';
            return $html;
        };

        // ---- helper format (kolom read-only) ----
        $plain     = fn($v) => e($v ?? '-');
        $fmtDate   = fn($v) => $v ? date('d-m-Y', strtotime($v)) : '-';
        $fmtTime   = fn($v) => $v ? date('d-m-Y H:i', strtotime($v)) : '-';
        $fmtMoney  = fn($v) => $v !== null ? 'Rp ' . number_format((float) $v, 0, ',', '.') : '-';

        // ================= LOGIC ESTIMASI / BLOCKED (per grup no_shipment) =================
        $keluar   = $r->_keluar ?? null;
        $blocked  = $r->_blocked ?? false;
        $estimasi = $r->_tanggal_estimasi ?? null;
        $blockedStatus = $r->_blocked_status ?? null;

        $tiba = $r->tanggal_tiba_kota ? strtotime($r->tanggal_tiba_kota) : null;
        $blockedLabel = $blockedStatus === 'sedang'
            ? 'Sedang di Gudang Berikutnya'
            : 'Menuju Gudang Berikutnya';

        $alert = '-';
        $alertClass = '';
        $estimasi_show = '-';

        if ($blocked) {
            $estimasi_show = $blockedLabel;
            $alertClass = 'gray';
        } else {
            $estimasi_show = $estimasi ? date('d-m-Y', $estimasi) : '-';

            if (!$r->tanggal_tiba_kota && $estimasi) {
                $today = strtotime(date('Y-m-d'));
                $hariSisa = floor(($estimasi - $today) / 86400);

                if ($hariSisa < 0) {
                    $alert = 'Pending Tiba H+' . abs($hariSisa);
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

        $alertHtml = $r->tanggal_tiba_kota
            ? '<span class="badge green">✅ TIBA</span>'
            : '<span class="badge ' . $alertClass . '">' . e($alert) . '</span>';

        $statusBongkar = '-';
        $statusBongkarClass = '';
        if ($r->tanggal_bongkar_kota) {
            $statusBongkar = 'Sudah Bongkar';
            $statusBongkarClass = 'green';
        } elseif ($r->tanggal_tiba_kota) {
            $tanggalTiba = strtotime(date('Y-m-d', strtotime($r->tanggal_tiba_kota)));
            $today = strtotime(date('Y-m-d'));
            $hariBongkar = floor(($today - $tanggalTiba) / 86400);
            $statusBongkar = 'Pending Bongkar H+' . max(0, $hariBongkar);
            $statusBongkarClass = $hariBongkar == 0 ? 'orange' : 'red';
        }
        $statusBongkarHtml = $statusBongkar != '-'
            ? '<span class="badge ' . $statusBongkarClass . '">' . e($statusBongkar) . '</span>'
            : '-';

        // ===== Kelengkapan Data =====
        $today = strtotime(date('Y-m-d'));
        $isOverdue = ($estimasi && !$blocked) ? ($estimasi < $today) : false;

        $missing = [];
        if ($isOverdue) {
            if (empty($r->tanggal_tiba_kota)) $missing[] = 'Tgl Tiba';
            if (empty($r->tanggal_bongkar_kota)) $missing[] = 'Tgl Bongkar';
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

        // Badge sederhana untuk sla_tiba_kota / sla_bongkar_kota (kolom mentah DB)
        $badgeSla = function ($val) {
            if ($val === null || $val === '' || $val === '-') return '-';
            $cls = strtolower(trim($val)) === 'on time' ? 'green' : 'red';
            return '<span class="badge ' . $cls . '">' . e($val) . '</span>';
        };

        return [
            // ===== 0-90: RAW sesuai urutan kolom database =====
            $plain($r->id),                                                        // 0  id
            $plain($r->no_kota),                                                   // 1  no_kota
            $fmtTime($r->tanggal_naik_logistik_kota),                              // 2
            $fmtTime($r->rencana_kirim_kota),                                      // 3
            $plain($r->transport_lead_time_kota),                                  // 4
            $plain($r->estimasi_admin_kota),                                       // 5
            $plain($r->planner_kota),                                              // 6
            $plain($r->no_shipment_kota),                                          // 7
            $selectBox('tujuan', $r->tujuan_kota, $lists['tujuanList'], 'Pilih Tujuan'), // 8 (editable)
            $plain($r->area_kota),                                                 // 9
            $plain($r->ketersediaan_unit_kota),                                    // 10
            $plain($r->mobil_kota),                                                // 11
            $plain($r->perubahan_mobil_kota),                                      // 12
            $fmtMoney($r->nilai_muatan_kota),                                      // 13
            $fmtMoney($r->biaya_kirim_kota),                                       // 14
            $numberInput('biaya_kuli', $r->biaya_kuli_kota),                       // 15 (editable)
            '<input type="text" name="total_biaya_kuli" class="row-total-biaya-kuli" value="' . $fmtMoney($r->total_biaya_kuli_kota ?? 0) . '" readonly>', // 16
            $plain($r->cr_kota),                                                   // 17
            $plain($r->kategori_ekspedisi_kota),                                   // 18
            $plain($r->ekpedisi_kota),                                             // 19
            $textInput('nama_kapal', $r->nama_kapal_kota),                         // 20 (editable)
            $dateInput('ETD', $r->etd_kota),                                       // 21 (editable)
            $dateInput('ETA', $r->eta_kota),                                       // 22 (editable)
            $dateInput('ATD', $r->atd_kota),                                       // 23 (editable)
            $dateInput('ATA', $r->ata_kota),                                       // 24 (editable)
            $plain($r->nama_driver_kota),                                          // 25
            $plain($r->no_pol_kota),                                               // 26
            $plain($r->status_pengiriman_kota),                                    // 27
            $fmtTime($r->tanggal_dpt_unit_kota),                                   // 28
            $fmtTime($r->planning_loading_kota),                                   // 29
            $fmtTime($r->tanggal_tiba_gudang_kota),                                // 30
            $fmtTime($r->tanggal_keluar_gudang_kota),                              // 31
            $plain($r->lama_digudang_kota),                                        // 32
            $plain($r->status_gudang_kota),                                        // 33
            $plain($r->sla_loading_kota),                                          // 34
            $plain($r->keterangan_kota),                                           // 35
            $plain($r->lama_waktu_pencarian_kota),                                 // 36
            $plain($r->sla_dapat_mobil_kota),                                      // 37
            $textInput('pic_monitoring', $r->pic_monitoring_kota),                 // 38 (editable)
            '<select name="status_kendaraan" class="form-select status-select">'
                . '<option value="On Track"' . ($r->status_kendaraan_kota == 'On Track' ? ' selected' : '') . '>🟢 On Track</option>'
                . '<option value="Potential Delay"' . ($r->status_kendaraan_kota == 'Potential Delay' ? ' selected' : '') . '>🔴 Potential Delay</option>'
                . '</select>',                                                     // 39 (editable)
            $plain($r->monitoring_alert_kota),                                     // 40
            $plain($r->action_required_kota),                                      // 41
            $numberInput('act_urutan_bongkar', $r->act_urutan_bongkar_kota),       // 42 (editable)
            $datetimeInput('tanggal_tiba', $r->tanggal_tiba_kota),                 // 43 (editable)
            $plain($r->lama_perjalanan_kota),                                      // 44
            $badgeSla($r->sla_tiba_kota),                                          // 45
            $datetimeInput('tanggal_bongkar', $r->tanggal_bongkar_kota),           // 46 (editable)
            $plain($r->overstay_days_kota),                                        // 47
            $badgeSla($r->sla_bongkar_kota),                                       // 48
            $selectBox('reason_tiba', $r->reason_tiba_kota, $lists['akurasiTiba'], 'Pilih Reason Tiba'), // 49 (editable)
            $selectBox('reason_bongkar', $r->reason_bongkar_kota, $lists['akurasiBongkar'], 'Pilih Reason Bongkar'), // 50 (editable)
            $plain($r->status_akhir_kota),                                         // 51
            $fmtTime($r->created_at),                                              // 52
            $fmtTime($r->updated_at),                                              // 53
            $fmtDate($r->tanggal_tiba_estimasi_kota),                              // 54
            $textInput('remarks', $r->remarks_kota),                               // 55 (editable)
            $plain($r->dist_channel_kota),                                         // 56
            $plain($r->transportasi_kota),                                         // 57
            $plain($r->transport_laut_kota),                                       // 58
            $fmtTime($r->tanggal_tiba_gudang_2_kota),                              // 59
            $fmtTime($r->planning_loading_2_kota),                                 // 60
            $fmtTime($r->tanggal_keluar_gudang_2_kota),                            // 61
            $plain($r->lama_digudang_2_kota),                                      // 62
            $plain($r->status_gudang_2_kota),                                      // 63
            $plain($r->sla_loading_2_kota),                                        // 64
            $fmtTime($r->tanggal_tiba_gudang_3_kota),                              // 65
            $fmtTime($r->planning_loading_3_kota),                                 // 66
            $fmtTime($r->tanggal_keluar_gudang_3_kota),                            // 67
            $plain($r->lama_digudang_3_kota),                                      // 68
            $plain($r->status_gudang_3_kota),                                      // 69
            $plain($r->sla_loading_3_kota),                                        // 70
            $plain($r->cust_grp_5_desc_kota),                                      // 71
            $plain($r->cust_grp_3_desc_kota),                                      // 72
            $plain($r->ship_no_kota),                                              // 73
            $plain($r->cust_desc_kota),                                            // 74
            $plain($r->addt_text_4_kota),                                          // 75
            $plain($r->service_agent_kota),                                        // 76
            $plain($r->urutan_bongkar_kota),                                       // 77
            $dateInput('act_pgi_date', $r->act_pgi_date_kota),                     // 78 (editable)
            $plain($r->created_by_kota),                                           // 79
            '<span class="row-total-do-display" data-total-do="' . e($r->total_do_qty_car_kota) . '">' . $plain($r->total_do_qty_car_kota) . '</span>', // 80
            $plain($r->route_kota),                                                // 81
            $plain($r->shipping_point_kota),                                       // 82
            $plain($r->kubikasi_kota),                                             // 83
            $plain($r->pulau_kota),                                                // 84
            $plain($r->via_kirim_kota),                                            // 85
            $fmtTime($r->estimasi_tiba_kota),                                      // 86
            $fmtTime($r->create_tgl_kota),                                         // 87
            '<input type="number" name="qty_monitoring" class="row-qty-monitoring" value="' . e($r->qty_monitoring_kota) . '" readonly style="background:#f1f5f9;color:#0284c7;font-weight:600;">', // 88 (readonly, dihitung)
            $selectBox('remarks_qty', $r->remarks_qty_kota, $lists['akurasiQty'], 'Pilih Reason Qty'), // 89 (editable)
            '<input type="number" name="selisih_qty" class="row-selisih-qty" data-total-do="' . e($r->total_do_qty_car_kota) . '" value="' . e($r->selisih_qty_kota) . '">', // 90 (editable)

            // ===== 91-95: kolom hasil kalkulasi =====
            e($estimasi_show),                                                     // 91 Estimasi Tiba (Live)
            $alertHtml,                                                            // 92 Alert
            $statusBongkarHtml,                                                    // 93 Status Bongkar
            $kelengkapanHtml,                                                      // 94 Kelengkapan Data
            '<span class="save-status"></span><button type="button" class="save-btn" data-id="' . $id . '" onclick="saveRow(this)">SAVE</button>', // 95 Aksi
        ];
    }

    // =====================================================
    // ALERT CONTROL
    // =====================================================
    public function alerts(Request $request)
    {
        $today = date('Y-m-d');

        $query = DB::table('logistik_pengiriman_kota')
            ->select('id', 'no_shipment_kota', 'estimasi_tiba_kota', 'tanggal_tiba_kota', 'tanggal_bongkar_kota')
            ->whereNotNull('estimasi_tiba_kota')
            ->where('estimasi_tiba_kota', '<', $today)
            ->where(function ($q) {
                $q->whereNull('tanggal_tiba_kota')->orWhereNull('tanggal_bongkar_kota');
            });

        if ($request->filled('pic_monitoring')) {
            $query->where('pic_monitoring_kota', $request->input('pic_monitoring'));
        }
        if ($request->filled('area')) {
            $query->where('area_kota', $request->input('area'));
        }
        if ($request->filled('bulan')) {
            $query->whereRaw("
                MONTH(GREATEST(
                    COALESCE(tanggal_keluar_gudang_kota,'1900-01-01'),
                    COALESCE(tanggal_keluar_gudang_2_kota,'1900-01-01'),
                    COALESCE(tanggal_keluar_gudang_3_kota,'1900-01-01')
                )) = ?
            ", [$request->input('bulan')]);
        }
        if ($request->filled('tahun')) {
            $query->whereRaw("
                YEAR(GREATEST(
                    COALESCE(tanggal_keluar_gudang_kota,'1900-01-01'),
                    COALESCE(tanggal_keluar_gudang_2_kota,'1900-01-01'),
                    COALESCE(tanggal_keluar_gudang_3_kota,'1900-01-01')
                )) = ?
            ", [$request->input('tahun')]);
        }
        if ($request->filled('keluar_gudang_tgl')) {
            $query->whereDate('tanggal_keluar_gudang_kota', $request->input('keluar_gudang_tgl'));
        }

        $rows = $query->orderBy('estimasi_tiba_kota', 'ASC')->limit(200)->get();

        $alertList = [];
        $missingSummary = [];

        foreach ($rows as $r) {
            $missing = [];

            if (empty($r->tanggal_tiba_kota)) {
                $missing[] = 'Tgl Tiba';
                $missingSummary['Tgl Tiba'] = ($missingSummary['Tgl Tiba'] ?? 0) + 1;
            }
            if (empty($r->tanggal_bongkar_kota)) {
                $missing[] = 'Tgl Bongkar';
                $missingSummary['Tgl Bongkar'] = ($missingSummary['Tgl Bongkar'] ?? 0) + 1;
            }

            $alertList[] = [
                'id'         => $r->id,
                'shipment'   => $r->no_shipment_kota,
                'missing'    => $missing,
                'emptyCount' => count($missing),
                'estimasi'   => $r->estimasi_tiba_kota,
            ];
        }

        return response()->json([
            'alerts'         => $alertList,
            'missingSummary' => $missingSummary,
            'totalAlert'     => (clone $query)->count(),
        ]);
    }

    // =====================================================
    // UPDATE ROW (auto-save)
    // =====================================================
    public function updateMonitoring(Request $request, $id)
    {
        $logistik = LogistikPengirimanKota::findOrFail($id);

        $gudangInfo = $this->getKeluarGudangInfo($logistik);
        $keluar  = $gudangInfo['keluar'];
        $blocked = $gudangInfo['blocked'];

        $tiba = $request->tanggal_tiba
            ? strtotime(date('Y-m-d', strtotime($request->tanggal_tiba)))
            : null;

        $bongkar = $request->tanggal_bongkar
            ? strtotime(date('Y-m-d', strtotime($request->tanggal_bongkar)))
            : null;

        $leadtime = (int) ($logistik->transport_lead_time_kota ?? 0);

        $estimasi = $logistik->estimasi_tiba_kota
            ? strtotime($logistik->estimasi_tiba_kota)
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

        $logistik->status_akhir_kota    = $logic['status_akhir'];
        $logistik->monitoring_alert_kota = $logic['alert'];

        $logistik->sla_tiba_kota    = $sla_tiba;
        $logistik->sla_bongkar_kota = $sla_bongkar;

        if (empty($logistik->estimasi_tiba_kota)) {
            if (!$logistik->tanggal_bongkar_kota && empty($logistik->estimasi_tiba_kota)) {
                $logistik->estimasi_tiba_kota = (!$blocked && $estimasi)
                    ? date('Y-m-d', $estimasi)
                    : null;
            }
        }

        $logistik->tujuan_kota           = $request->input('tujuan', $logistik->tujuan_kota);
        $logistik->pic_monitoring_kota   = $request->pic_monitoring;
        $logistik->status_kendaraan_kota = $request->status_kendaraan;
        $logistik->remarks_qty_kota      = $request->remarks_qty;
        $logistik->action_required_kota  = $request->action_required;

        $logistik->act_urutan_bongkar_kota = $request->act_urutan_bongkar;

        $logistik->total_do_qty_car_kota = $request->total_do_qty_car ?? $logistik->total_do_qty_car_kota;
        $logistik->selisih_qty_kota      = $request->selisih_qty;
        $logistik->biaya_kuli_kota       = $request->biaya_kuli;

        $logistik->qty_monitoring_kota   = ($logistik->total_do_qty_car_kota ?? 0) - ($logistik->selisih_qty_kota ?? 0);
        $logistik->total_biaya_kuli_kota = ($logistik->qty_monitoring_kota ?? 0) * ($logistik->biaya_kuli_kota ?? 0);

        $logistik->tanggal_tiba_kota    = $request->tanggal_tiba;
        $logistik->tanggal_bongkar_kota = $request->tanggal_bongkar;

        $logistik->overstay_days_kota   = $overstay;
        $logistik->lama_perjalanan_kota = $lama_perjalanan;

        $logistik->reason_tiba_kota    = $request->reason_tiba;
        $logistik->reason_bongkar_kota = $request->reason_bongkar;

        $logistik->remarks_kota      = $request->remarks;
        $logistik->act_pgi_date_kota = $request->input('act_pgi_date');
        $logistik->created_by_kota   = $request->input('created_by');

        if ($request->filled('nama_kapal')) {
            $logistik->nama_kapal_kota = $request->nama_kapal;
            $logistik->etd_kota = $request->ETD ?? $request->etd;
            $logistik->eta_kota = $request->ETA ?? $request->eta;
            $logistik->atd_kota = $request->ATD ?? $request->atd;
            $logistik->ata_kota = $request->ATA ?? $request->ata;
        }

        $logistik->save();

        // ===== propagate estimasi ke shipment lain dgn no_shipment_kota sama =====
        $shipment = LogistikPengirimanKota::where('no_shipment_kota', $logistik->no_shipment_kota)->get();

        $baseEstimasi = (!$blocked && $keluar)
            ? strtotime("+{$leadtime} days", $keluar)
            : null;

        $lastBongkar = $shipment->whereNotNull('tanggal_bongkar_kota')->max('tanggal_bongkar_kota');

        $nextEstimasi = $lastBongkar
            ? date('Y-m-d', strtotime($lastBongkar . ' +1 day'))
            : ($baseEstimasi ? date('Y-m-d', $baseEstimasi) : null);

        foreach ($shipment as $item) {
            if (!empty($item->tanggal_tiba_kota)) {
                continue;
            }
            $item->estimasi_tiba_kota = $nextEstimasi;
            $item->save();
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Data kota berhasil diupdate',
        ]);
    }

    public function updateTransportLaut(Request $request)
    {
        $request->validate(['no_shipment' => 'required']);

        $data = [
            'nama_kapal_kota' => $request->nama_kapal,
            'etd_kota' => $request->etd,
            'eta_kota' => $request->eta,
            'atd_kota' => $request->atd,
            'ata_kota' => $request->ata,
        ];

        LogistikPengirimanKota::where('no_shipment_kota', $request->no_shipment)->update($data);

        return response()->json([
            'status'  => 'success',
            'message' => 'Data transport laut kota berhasil diupdate',
        ]);
    }

    // =====================================================
    // HELPERS
    // =====================================================
    private function getKeluarGudangInfo($r)
    {
        $cycles = [
            ['planning' => $r->planning_loading_kota,   'tiba' => $r->tanggal_tiba_gudang_kota,   'keluar' => $r->tanggal_keluar_gudang_kota],
            ['planning' => $r->planning_loading_2_kota, 'tiba' => $r->tanggal_tiba_gudang_2_kota, 'keluar' => $r->tanggal_keluar_gudang_2_kota],
            ['planning' => $r->planning_loading_3_kota, 'tiba' => $r->tanggal_tiba_gudang_3_kota, 'keluar' => $r->tanggal_keluar_gudang_3_kota],
        ];

        $blocked = false;
        $blockedStatus = null;
        $keluarTimestamps = [];

        foreach ($cycles as $c) {
            $hasPlanning = !empty($c['planning']);
            $hasTiba     = !empty($c['tiba']);
            $started     = $hasPlanning || $hasTiba;
            $selesai     = !empty($c['keluar']);

            if ($started && !$selesai) {
                $blocked = true;
                $blockedStatus = $hasTiba ? 'sedang' : 'menuju';
            }
            if ($selesai) {
                $keluarTimestamps[] = strtotime($c['keluar']);
            }
        }

        return [
            'blocked'        => $blocked,
            'blocked_status' => $blockedStatus,
            'keluar'         => !empty($keluarTimestamps) ? max($keluarTimestamps) : null,
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
}