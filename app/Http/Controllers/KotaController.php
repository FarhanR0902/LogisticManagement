<?php

namespace App\Http\Controllers\Kota;

use App\Http\Controllers\Controller;
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
                ];
                foreach ($cols as $col) {
                    $q->orWhere($col, 'like', "%{$searchValue}%");
                }
            });
        }

        $recordsFiltered = (clone $baseQuery)->count();

        // ================= ORDERING =================
        $orderColumnMap = [
            0  => 'tanggal_keluar_gudang_kota',
            1  => 'act_pgi_date_kota',
            2  => 'dist_channel_kota',
            3  => 'area_kota',
            4  => 'no_shipment_kota',
            5  => 'tujuan_kota',
            6  => 'ekpedisi_kota',
            7  => 'pic_monitoring_kota',
            8  => 'status_kendaraan_kota',
            10 => 'total_do_qty_car_kota',
            11 => 'selisih_qty_kota',
            12 => 'biaya_kuli_kota',
            13 => 'total_biaya_kuli_kota',
            14 => 'qty_monitoring_kota',
            15 => 'remarks_qty_kota',
            16 => 'act_urutan_bongkar_kota',
            17 => 'estimasi_tiba_kota',
            18 => 'tanggal_tiba_kota',
            19 => 'lama_perjalanan_kota',
            20 => 'sla_tiba_kota',
            21 => 'tanggal_bongkar_kota',
            23 => 'overstay_days_kota',
            24 => 'sla_bongkar_kota',
            25 => 'reason_tiba_kota',
            26 => 'reason_bongkar_kota',
            27 => 'remarks_kota',
            28 => 'nama_kapal_kota',
            29 => 'etd_kota',
            30 => 'eta_kota',
            31 => 'atd_kota',
            32 => 'ata_kota',
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
     * Bangun 1 baris (index HARUS sinkron dengan <thead> di
     * kota/data_monitoring.blade.php).
     */
    private function renderRowColumns($r, array $lists)
    {
        $id = $r->id;

        $textInput = function ($name, $value) {
            return '<input type="text" name="' . $name . '" value="' . e($value) . '">';
        };

        $numberInput = function ($name, $value) {
            return '<input type="number" name="' . $name . '" value="' . e($value) . '">';
        };

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

        $keluar   = $r->_keluar ?? null;
        $blocked  = $r->_blocked ?? false;
        $estimasi = $r->_tanggal_estimasi ?? null;
        $blockedStatus = $r->_blocked_status ?? null;

        $tiba = $r->tanggal_tiba_kota ? strtotime($r->tanggal_tiba_kota) : null;
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

            if (!$r->tanggal_tiba_kota && $estimasi) {
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

        $alertHtml = $r->tanggal_tiba_kota
            ? '<span class="badge green">✅ TIBA</span>'
            : '<span class="badge ' . $alertClass . '">' . e($alert) . '</span>';

        $sla_tiba = $r->sla_tiba_kota ?? '-';
        $sla_tiba_html = $sla_tiba == '-'
            ? '-'
            : ($sla_tiba == 'On Time'
                ? '<span class="badge green">On Time</span>'
                : '<span class="badge red">' . e($sla_tiba) . '</span>');

        $sla_bongkar = $r->sla_bongkar_kota ?? '-';
        $sla_bongkar_html = $sla_bongkar == '-'
            ? '-'
            : ($sla_bongkar == 'On Time'
                ? '<span class="badge green">On Time</span>'
                : '<span class="badge red">' . e($sla_bongkar) . '</span>');

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

        return [
            // 0 Tanggal Keluar Gudang
            $blocked
                ? '<span class="badge red">' . e($blockedLabel) . '</span>'
                : ($keluar ? '<span class="badge green">' . date('d-m-Y', $keluar) . '</span>' : '-'),
            // 1 Act PGI Date (editable)
            '<input type="date" name="act_pgi_date" value="' . ($r->act_pgi_date_kota ? date('Y-m-d', strtotime($r->act_pgi_date_kota)) : '') . '">',
            // 2 Dist Channel
            e($r->dist_channel_kota),
            // 3 Area
            e($r->area_kota),
            // 4 No Shipment
            e($r->no_shipment_kota),
            // 5 Tujuan (editable, searchable dropdown dari tujuanfillterr)
            $selectBox('tujuan', $r->tujuan_kota, $lists['tujuanList'], 'Pilih Tujuan'),
            // 6 Ekspedisi
            e($r->ekpedisi_kota),
            // 7 PIC (editable)
            $textInput('pic_monitoring', $r->pic_monitoring_kota),
            // 8 Status (editable select)
            '<select name="status_kendaraan" class="form-select status-select">'
                . '<option value="On Track"' . ($r->status_kendaraan_kota == 'On Track' ? ' selected' : '') . '>🟢 On Track</option>'
                . '<option value="Potential Delay"' . ($r->status_kendaraan_kota == 'Potential Delay' ? ' selected' : '') . '>🔴 Potential Delay</option>'
                . '</select>',
            // 9 Alert
            $alertHtml,
            // 10 Total DO Qty
            e($r->total_do_qty_car_kota),
            // 11 Selisih Qty Do (editable)
            '<input type="number" name="selisih_qty" class="row-selisih-qty" data-total-do="' . e($r->total_do_qty_car_kota) . '" value="' . e($r->selisih_qty_kota) . '">',
            // 12 Biaya Kuli (editable)
            '<input type="number" name="biaya_kuli" class="row-biaya-kuli" value="' . e($r->biaya_kuli_kota) . '">',
            // 13 Total Biaya Kuli
            '<input type="text" name="total_biaya_kuli" class="row-total-biaya-kuli" value="Rp ' . number_format($r->total_biaya_kuli_kota ?? 0, 0, ',', '.') . '" readonly>',
            // 14 Qty Actual Do
            '<input type="number" name="qty_monitoring" class="row-qty-monitoring" value="' . e($r->qty_monitoring_kota) . '" readonly style="background:#f1f5f9;color:#0284c7;font-weight:600;">',
            // 15 Reason Qty (editable select)
            $selectBox('remarks_qty', $r->remarks_qty_kota, $lists['akurasiQty'], 'Pilih Reason Qty'),
            // 16 Urutan Bongkar (editable)
            $numberInput('act_urutan_bongkar', $r->act_urutan_bongkar_kota),
            // 17 Estimasi Tiba
            e($estimasi_show),
            // 18 Tanggal Tiba (editable)
            '<input type="datetime-local" name="tanggal_tiba" data-required="true" data-label="Tgl Tiba" value="' . ($r->tanggal_tiba_kota ? date('Y-m-d\TH:i', strtotime($r->tanggal_tiba_kota)) : '') . '">',
            // 19 Lama Perjalanan
            e($lama_perjalanan),
            // 20 SLA Tiba
            $sla_tiba_html,
            // 21 Tanggal Bongkar (editable)
            '<input type="datetime-local" name="tanggal_bongkar" data-required="true" data-label="Tgl Bongkar" value="' . ($r->tanggal_bongkar_kota ? date('Y-m-d\TH:i', strtotime($r->tanggal_bongkar_kota)) : '') . '">',
            // 22 Status Bongkar
            $statusBongkarHtml,
            // 23 Overstay
            e($r->overstay_days_kota ?? '-'),
            // 24 SLA Bongkar
            $sla_bongkar_html,
            // 25 Reason Tiba (editable select)
            $selectBox('reason_tiba', $r->reason_tiba_kota, $lists['akurasiTiba'], 'Pilih Reason Tiba'),
            // 26 Reason Bongkar (editable select)
            $selectBox('reason_bongkar', $r->reason_bongkar_kota, $lists['akurasiBongkar'], 'Pilih Reason Bongkar'),
            // 27 Remarks (editable)
            $textInput('remarks', $r->remarks_kota),
            // 28 Nama Kapal (editable)
            $textInput('nama_kapal', $r->nama_kapal_kota),
            // 29 ETD
            '<input type="date" name="ETD" value="' . ($r->etd_kota ? date('Y-m-d', strtotime($r->etd_kota)) : '') . '">',
            // 30 ETA
            '<input type="date" name="ETA" value="' . ($r->eta_kota ? date('Y-m-d', strtotime($r->eta_kota)) : '') . '">',
            // 31 ATD
            '<input type="date" name="ATD" value="' . ($r->atd_kota ? date('Y-m-d', strtotime($r->atd_kota)) : '') . '">',
            // 32 ATA
            '<input type="date" name="ATA" value="' . ($r->ata_kota ? date('Y-m-d', strtotime($r->ata_kota)) : '') . '">',
            // 33 Kelengkapan Data
            $kelengkapanHtml,
            // 34 Action
            '<span class="save-status"></span><button type="button" class="save-btn" data-id="' . $id . '" onclick="saveRow(this)">SAVE</button>',
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

        $logistik->tujuan_kota         = $request->input('tujuan', $logistik->tujuan_kota);
        $logistik->pic_monitoring_kota   = $request->pic_monitoring;
        $logistik->status_kendaraan_kota = $request->status_kendaraan;
        $logistik->remarks_qty_kota     = $request->remarks_qty;
        $logistik->action_required_kota  = $request->action_required;

        $logistik->act_urutan_bongkar_kota = $request->act_urutan_bongkar;

        $logistik->total_do_qty_car_kota = $request->total_do_qty_car ?? $logistik->total_do_qty_car_kota;
        $logistik->selisih_qty_kota      = $request->selisih_qty;
        $logistik->biaya_kuli_kota       = $request->biaya_kuli;

        $logistik->qty_monitoring_kota = ($logistik->total_do_qty_car_kota ?? 0) - ($logistik->selisih_qty_kota ?? 0);
        $logistik->total_biaya_kuli_kota = ($logistik->qty_monitoring_kota ?? 0) * ($logistik->biaya_kuli_kota ?? 0);

        $logistik->tanggal_tiba_kota    = $request->tanggal_tiba;
        $logistik->tanggal_bongkar_kota = $request->tanggal_bongkar;

        $logistik->overstay_days_kota   = $overstay;
        $logistik->lama_perjalanan_kota = $lama_perjalanan;

        $logistik->reason_tiba_kota    = $request->reason_tiba;
        $logistik->reason_bongkar_kota = $request->reason_bongkar;

        $logistik->remarks_kota   = $request->remarks;
        $logistik->act_pgi_date_kota = $request->input('act_pgi_date');
        $logistik->created_by_kota   = $request->input('created_by');

        if ($request->filled('nama_kapal')) {
            $logistik->nama_kapal_kota = $request->nama_kapal;
            $logistik->etd_kota = $request->etd;
            $logistik->eta_kota = $request->eta;
            $logistik->atd_kota = $request->atd;
            $logistik->ata_kota = $request->ata;
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