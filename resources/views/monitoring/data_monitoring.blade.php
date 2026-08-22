@include('template.sidebar')

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>DATA MONITORING</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

    <style>
        body {
            background: #f3f4f6;
            font-family: 'Segoe UI';
            margin: 0;
        }

        .container-fluid {
            margin-left: 250px;
            width: calc(100% - 250px);
            padding: 20px;
        }

        .title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #111827;
        }

        .card {
            background: #fff;
            padding: 15px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            overflow: auto;
            width: 100%;
        }

        .d-none {
            display: none !important;
        }

        .filter-box {
            display: flex;
            gap: 10px;
            flex-wrap: nowrap;
            align-items: center;
            margin-bottom: 15px;
            overflow-x: auto;
        }

        .filter-box form {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: nowrap;
        }

        .filter-box select {
            min-width: 180px;
            white-space: nowrap;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 20px;
            white-space: nowrap;
        }

        th {
            background: #111827;
            color: #fff;
            padding: 14px;
            font-size: 15px;
            text-align: center;
        }

        th.editable {
            background: linear-gradient(135deg, #2563eb, #1e40af);
        }

        td {
            border: 1px solid #e5e7eb;
            padding: 10px;
            font-size: 14px;
        }

        input,
        select {
            width: 100%;
            font-size: 14px;
            padding: 8px;
            border: 1px solid #d1d5db;
            border-radius: 5px;
        }

        .save-btn {
            background: #22c55e;
            border: none;
            color: #fff;
            padding: 7px 12px;
            border-radius: 6px;
        }

        .badge {
            padding: 5px 8px;
            border-radius: 20px;
            color: #fff;
            font-size: 11px;
            display: inline-block;
        }

        .green { background: #22c55e; }
        .red { background: #ef4444; }
        .orange { background: #f59e0b; }
        .blue { background: #2563eb; }
        .gray { background: #9ca3af; }

        #tableMonitoring { width: 100% !important; }
        #tableMonitoring th { text-align: left; vertical-align: middle; }
        #tableMonitoring td { vertical-align: middle; white-space: nowrap; }
        #tableMonitoring input[type=text] { min-width: 120px; }
        #tableMonitoring input[type=number] { width: 70px; }
        #tableMonitoring input[type=datetime-local] { width: 170px; }
        #tableMonitoring .save-btn { width: 70px; }
        #tableMonitoring .badge { display: inline-block; min-width: 70px; text-align: center; }

        .select2-container { min-width: 140px !important; }
        .select2-selection { height: 32px !important; }
        .dataTables_wrapper { overflow-x: auto; width: 100%; }

        .input-filled { background: #bbf7d0 !important; border: 2px solid #16a34a !important; }
        .input-empty { background: #fecaca !important; border: 2px solid #dc2626 !important; }

        /* ===== TOAST ===== */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            width: 350px;
            z-index: 99999;
        }

        .toast {
            background: #111827;
            color: #fff;
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, .3);
            animation: slideIn .3s ease;
            border-left: 5px solid #f59e0b;
            font-size: 12px;
        }

        .toast strong {
            display: block;
            margin-bottom: 5px;
            color: #fbbf24;
        }

        @keyframes slideIn {
            from { transform: translateX(120%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        /* ===== ALERT CONTROL / SUMMARY BOX ===== */
        .summary-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 15px;
            align-items: flex-start;
        }

        .missing-field-box {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }

        #alertControlBox {
            width: 100%;
        }

        #alertControlBox .box-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        #alertControlBox .box-header b {
            font-size: 15px;
            color: #111827;
        }

        #alertControlList {
            max-height: 260px;
            overflow-y: auto;
        }

        .alert-item {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 10px 12px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all .15s ease;
        }

        .alert-item:hover {
            background: #f3f4f6;
            transform: translateY(-1px);
        }

        .alert-item .alert-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .alert-item .alert-missing {
            font-size: 12px;
            color: #6b7280;
            margin-top: 4px;
            white-space: normal;
        }

        .highlight-row td {
            background: #fde68a !important;
            transition: background-color .3s ease;
        }

        .completeness-badge {
            white-space: normal;
            max-width: 220px;
            line-height: 1.4;
        }

        /* Cegah select status (On Track / Potential Delay) keremes/mengecil */
        #tableMonitoring select.status-select {
            min-width: 160px !important;
            width: 160px !important;
        }

        #tableMonitoring td:has(select.status-select) {
            min-width: 160px;
        }
    </style>

</head>

<body>

    <!-- MODAL -->
    <div class="modal fade" id="shipModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">

                <form action="/monitoring/update-transport-laut" method="POST">
                    @csrf

                    <div class="modal-header">
                        <h5 class="modal-title">Shipment Laut</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <div class="row">

                            <div class="col-md-6 mb-2">
                                <label>No Shipment</label>
                                <select name="no_shipment" class="form-select searchable">
                                    <option value="">Pilih Shipment</option>
                                    @foreach($logistik->unique('no_shipment') as $r)
                                    <option value="{{ $r->no_shipment }}">
                                        {{ $r->no_shipment }} - {{ $r->tujuan }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6 mb-2">
                                <label>Nama Kapal</label>
                                <input type="text" name="nama_kapal" class="form-control">
                            </div>

                            <div class="col-md-6 mb-2">
                                <label>ETD</label>
                                <input type="date" name="etd" class="form-control">
                            </div>

                            <div class="col-md-6 mb-2">
                                <label>ETA</label>
                                <input type="date" name="eta" class="form-control">
                            </div>

                            <div class="col-md-6 mb-2">
                                <label>ATD</label>
                                <input type="date" name="atd" class="form-control">
                            </div>

                            <div class="col-md-6 mb-2">
                                <label>ATA</label>
                                <input type="date" name="ata" class="form-control">
                            </div>

                        </div>
                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button class="btn btn-success">Save</button>
                    </div>

                </form>

            </div>
        </div>
    </div>

    <!-- TOAST POPUP (satu-satunya container, jangan duplikat) -->
    <div class="toast-container" id="toastContainer"></div>

    <div class="container-fluid px-3">
        @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
        @endif

        <div class="title">🚚 DATA MONITORING</div>

        <div class="mb-3">
            <a href="{{ route('monitoring.export', [
    'pic_monitoring' => request('pic_monitoring'),
    'area' => request('area')
]) }}" class="btn btn-success">
                Export Excel
            </a>
        </div>

        {{-- FILTER --}}
        <div class="filter-box">
            <form method="GET" action="{{ route('monitoring.datalogistik') }}" class="d-flex align-items-center gap-2 flex-wrap">

                <select class="searchable" name="pic_monitoring" onchange="this.form.submit()">
                    <option value="">PIC Monitoring</option>
                    @foreach($picList as $pic)
                    <option value="{{ $pic }}" {{ request('pic_monitoring') == $pic ? 'selected' : '' }}>
                        {{ $pic }}
                    </option>
                    @endforeach
                </select>

                <select class="searchable" name="area" onchange="this.form.submit()">
                    <option value="">AREA</option>
                    @foreach($areaList as $area)
                    <option value="{{ $area }}" {{ request('area') == $area ? 'selected' : '' }}>
                        {{ $area }}
                    </option>
                    @endforeach
                </select>

                <select class="searchable" name="bulan" onchange="this.form.submit()">
                    <option value="">BULAN</option>
                    @for($i=1; $i<=12; $i++)
                        <option value="{{ $i }}" {{ request('bulan') == $i ? 'selected' : '' }}>{{ $i }}</option>
                        @endfor
                </select>

                <select class="searchable" name="tahun" onchange="this.form.submit()">
                    <option value="">TAHUN</option>
                    @for($i=2023; $i<=2030; $i++)
                        <option value="{{ $i }}" {{ request('tahun') == $i ? 'selected' : '' }}>{{ $i }}</option>
                        @endfor
                </select>

                @if(request('pic_monitoring') || request('area') || request('bulan') || request('tahun'))
                <a href="{{ route('monitoring.datalogistik') }}" class="btn btn-secondary btn-sm d-flex align-items-center gap-1" style="height: 38px; padding: 0 15px;">
                    🔄 Reset Filter
                </a>
                @endif

            </form>
        </div>

        <div class="col-md-3">
            <label class="form-label fw-bold">Filter Tanggal Keluar Gudang</label>
            <input type="date" id="filterKeluarGudangTgl" class="form-control">
        </div>

        <!-- BUTTON -->
        <button class="btn btn-primary mb-3"
            data-bs-toggle="modal"
            data-bs-target="#shipModal">
            + Shipment Laut
        </button>

        {{-- ===== SUMMARY: FIELD YANG PALING BANYAK KOSONG ===== --}}
        <div class="card mb-3">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                <b style="font-size:14px; color:#374151;">📋 Field belum lengkap:</b>
            </div>
            <div class="missing-field-box" id="missingFieldSummary">
                <span class="badge gray">Menghitung...</span>
            </div>
        </div>

        {{-- ===== ALERT CONTROL BOX ===== --}}
        <div class="card mb-3" id="alertControlBox">
            <div class="box-header">
                <b>🔔 Alert Control — Lewat Estimasi Tiba</b>
                <span class="badge red" id="alertControlCount">0 Alert</span>
            </div>
            <div id="alertControlList">
                <div class="p-2" style="color:#6b7280; font-size:13px;">Memuat data...</div>
            </div>
        </div>

        <div class="card">
            <table id="tableMonitoring" class="display nowrap">
                <thead>
                    <tr>
                        <th>Tanggal Keluar Gudang</th>
                        <th class="editable">Act PGI Date</th>
                        <th>Dist Channel</th>
                        <th>Area</th>
                        <th>No Shipment</th>
                        <th>Tujuan</th>
                        <th>Ekspedisi</th>
                        <th class="editable">PIC</th>
                        <th class="editable">Status</th>
                        <th class="editable">Alert</th>
                        <th>Total DO Qty</th>
                        <th class="editable">Selisih Qty Do</th>
                        <th class="editable">Biaya Kuli</th>
                        <th>Total Biaya kuli</th>
                        <th>Qty Actual Do</th>
                        <th class="editable">Reason Qty</th>
                        <th class="editable">Urutan Bongkar</th>
                        <th>Estimasi Tiba</th>
                        <th class="editable">Tanggal Tiba</th>
                        <th>Lama Perjalanan</th>
                        <th>SLA Tiba</th>
                        <th class="editable">Tanggal Bongkar</th>
                        <th>Status Bongkar</th>
                        <th>Overstay</th>
                        <th>SLA Bongkar</th>
                        <th class="editable">Reason Tiba</th>
                        <th class="editable">Reason Bongkar</th>
                        <th class="editable">Remarks</th>
                        <th class="editable">Nama Kapal</th>
                        <th>ETD</th>
                        <th>ETA</th>
                        <th>ATD</th>
                        <th>ATA</th>
                        <th>Kelengkapan Data</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($logistik as $r)
                    @php
                    $sla_tiba = $r->sla_tiba ?? '-';
                    $sla_bongkar = $r->sla_bongkar ?? '-';
                    $overstay = $r->overstay_days ?? '-';
                    $lama_perjalanan = $r->lama_perjalanan ?? '-';
                    $status_akhir = $r->status_akhir ?? '-';

                    $keluarTimestamp = collect([
                    $r->tanggal_keluar_gudang,
                    $r->tanggal_keluar_gudang_2,
                    $r->tanggal_keluar_gudang_3,
                    ])
                    ->filter()
                    ->map(function ($d) {
                    return strtotime($d);
                    })
                    ->max();

                    $keluar = (!empty($r->gudang_blocked)) ? null : ($keluarTimestamp ?: null);

                    $tiba = $r->tanggal_tiba ? strtotime($r->tanggal_tiba) : null;

                    $lama_perjalanan = '-';
                    if ($tiba && $keluar) {
                    $lama_perjalanan = floor(($tiba - $keluar) / 86400);
                    }

                    $estimasi = !empty($r->estimasi_tiba) ? strtotime($r->estimasi_tiba) : null;

                    $alert = '-';
                    $alertClass = '';
                    $alertText = '';

                    if (!empty($r->gudang_blocked)) {
                    $estimasi_show = 'Menuju Gudang Berikutnya';
                    $alertClass = 'gray';
                    $alert = '-';
                    } else {
                    $estimasi_show = $estimasi ? date('d-m-Y', $estimasi) : '-';

                    if (!$r->tanggal_tiba && $estimasi) {
                    $today = strtotime(date('Y-m-d'));
                    $hariSisa = floor(($estimasi - $today) / 86400);

                    if ($hariSisa < 0) {
                    $alertText = 'Pending Tiba  H+' . abs($hariSisa);
                    $alertClass = 'red';
                    } elseif ($hariSisa==0) {
                    $alertText='H-0' ; $alertClass='red' ;
                    } elseif ($hariSisa==1) {
                    $alertText='H-1' ; $alertClass='red' ;
                    } elseif ($hariSisa==2) {
                    $alertText='H-2' ; $alertClass='orange' ;
                    } elseif ($hariSisa==3) {
                    $alertText='H-3' ; $alertClass='orange' ;
                    } elseif ($hariSisa <=7) {
                    $alertText='H-' . $hariSisa; $alertClass='blue' ;
                    } else {
                    $alertText='ON TRACK' ; $alertClass='green' ;
                    }
                    $alert=$alertText;
                    }
                    }

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

                    if ($hariBongkar == 0) {
                    $statusBongkarClass = 'orange';
                    } else {
                    $statusBongkarClass = 'red';
                    }
                    }
                    @endphp
                    <tr
                        data-id="{{ $r->id }}"
                        data-shipment="{{ $r->no_shipment }}"
                        data-keluar="{{ $keluar ? date('Y-m-d', $keluar) : '' }}"
                        data-estimasi="{{ (empty($r->gudang_blocked) && $estimasi) ? date('Y-m-d', $estimasi) : '' }}">

                        <td>{{ $keluar ? date('d-m-Y ', $keluar) : '-' }}</td>

                        <td>{{ $r->create_tgl ? \Carbon\Carbon::parse($r->create_tgl)->format('d/m/Y H:i') : '-' }}</td>

                        <td>{{ $r->dist_channel }}</td>
                        <td>{{ $r->area }}</td>
                        <td>{{ $r->no_shipment }}</td>
                        <td>{{ $r->tujuan }}</td>
                        <td>{{ $r->ekpedisi }}</td>

                        <td>
                            <input type="text" name="pic_monitoring" value="{{ $r->pic_monitoring }}">
                        </td>

                        <td>
                            <select name="status_kendaraan" class="form-select status-select">
                                <option value="On Track" {{ $r->status_kendaraan == 'On Track' ? 'selected' : '' }}>
                                    🟢 On Track
                                </option>
                                <option value="Potential Delay" {{ $r->status_kendaraan == 'Potential Delay' ? 'selected' : '' }}>
                                    🔴 Potential Delay
                                </option>
                            </select>
                        </td>

                        <td>
                            @if($r->tanggal_tiba)
                            <span class="badge green">✅ TIBA</span>
                            @else
                            <span class="badge {{ $alertClass }}">{{ $alert }}</span>
                            @endif
                        </td>

                        <td>{{ $r->total_do_qty_car }}</td>

                        <td data-label="Selisih Qty">
                            <input type="number"
                                form="form-update-{{ $r->id }}"
                                name="selisih_qty"
                                class="row-selisih-qty"
                                data-total-do="{{ $r->total_do_qty_car }}"
                                value="{{ $r->selisih_qty }}">
                        </td>

                        <td data-label="Biaya Kuli">
                            <input type="number"
                                name="biaya_kuli"
                                class="row-biaya-kuli"
                                form="form-update-{{ $r->id }}"
                                value="{{ $r->biaya_kuli }}">
                        </td>

                        <td>
                            <input type="text"
                                name="total_biaya_kuli"
                                class="row-total-biaya-kuli"
                                value="Rp {{ number_format($r->total_biaya_kuli ?? 0, 0, ',', '.') }}"
                                readonly>
                        </td>

                        <td>
                            <input type="number"
                                form="form-update-{{ $r->id }}"
                                name="qty_monitoring"
                                class="row-qty-monitoring"
                                value="{{ $r->qty_monitoring }}"
                                readonly
                                style="background:#f1f5f9;color:#0284c7;font-weight:600;">
                        </td>

                        <td>
                            <select name="remarks_qty" class="reason-select searchable-select">
                                <option value="">Pilih Reason Qty</option>
                                @foreach($akurasiQty as $item)
                                <option value="{{ $item }}" {{ $r->remarks_qty == $item ? 'selected' : '' }}>
                                    {{ $item }}
                                </option>
                                @endforeach
                            </select>
                            <span class="d-none txt">{{ $r->remarks_qty }}</span>
                        </td>

                        <td>
                            <input type="number" name="act_urutan_bongkar" value="{{ $r->act_urutan_bongkar }}">
                        </td>

                        <td>{{ $estimasi_show }}</td>

                        <td data-required="true" data-label="Tgl Tiba">
                            <input type="datetime-local"
                                name="tanggal_tiba"
                                value="{{ $r->tanggal_tiba ? date('Y-m-d\TH:i', strtotime($r->tanggal_tiba)) : '' }}">
                        </td>

                        <td>{{ $lama_perjalanan }}</td>

                        <td>
                            @if($sla_tiba == '-')
                            -
                            @elseif($sla_tiba == 'On Time')
                            <span class="badge green">On Time</span>
                            @else
                            <span class="badge red">{{ $sla_tiba }}</span>
                            @endif
                        </td>

                        <td data-required="true" data-label="Tgl Bongkar">
                            <input type="datetime-local"
                                name="tanggal_bongkar"
                                value="{{ $r->tanggal_bongkar ? date('Y-m-d\TH:i', strtotime($r->tanggal_bongkar)) : '' }}">
                        </td>

                        <td>
                            @if($statusBongkar != '-')
                            <span class="badge {{ $statusBongkarClass }}">{{ $statusBongkar }}</span>
                            @else
                            -
                            @endif
                        </td>

                        <td>{{ $r->overstay_days ?? '-' }}</td>

                        <td>
                            @if($sla_bongkar == '-')
                            -
                            @elseif($sla_bongkar == 'On Time')
                            <span class="badge green">On Time</span>
                            @else
                            <span class="badge red">{{ $sla_bongkar }}</span>
                            @endif
                        </td>

                        <td>
                            <select name="reason_tiba" class="reason-select searchable-select">
                                <option value="">Pilih Reason Tiba</option>
                                @foreach($akurasiTiba as $item)
                                <option value="{{ $item }}" {{ $r->reason_tiba == $item ? 'selected' : '' }}>
                                    {{ $item }}
                                </option>
                                @endforeach
                            </select>
                            <span class="d-none txt">{{ $r->reason_tiba }}</span>
                        </td>

                        <td>
                            <select name="reason_bongkar" class="reason-select searchable-select">
                                <option value="">Pilih Reason Bongkar</option>
                                @foreach($akurasiBongkar as $item)
                                <option value="{{ $item }}" {{ $r->reason_bongkar == $item ? 'selected' : '' }}>
                                    {{ $item }}
                                </option>
                                @endforeach
                            </select>
                            <span class="d-none txt">{{ $r->reason_bongkar }}</span>
                        </td>

                        <td>
                            <input type="text" name="remarks" value="{{ $r->remarks }}">
                        </td>

                        <td>
                            <input type="text" name="nama_kapal" value="{{ $r->nama_kapal }}">
                        </td>

                        <td>
                            <input type="date" name="ETD" value="{{ $r->etd ? date('Y-m-d', strtotime($r->etd)) : '' }}">
                        </td>

                        <td>
                            <input type="date" name="ETA" value="{{ $r->eta ? date('Y-m-d', strtotime($r->eta)) : '' }}">
                        </td>

                        <td>
                            <input type="date" name="ATD" value="{{ $r->atd ? date('Y-m-d', strtotime($r->atd)) : '' }}">
                        </td>

                        <td>
                            <input type="date" name="ATA" value="{{ $r->ata ? date('Y-m-d', strtotime($r->ata)) : '' }}">
                        </td>

                        {{-- BADGE KELENGKAPAN DATA (diisi JS) --}}
                        <td>
                            <span class="badge completeness-badge gray">-</span>
                        </td>

                        <td>
                            <span class="save-status"></span>
                            <button type="button" class="save-btn" onclick="saveRow({{ $r->id }})">
                                SAVE
                            </button>
                        </td>

                    </tr>
                    @endforeach
                </tbody>
            </table>

            <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

            <script>
                let table;
                let saveTimer;

                $(document).ready(function() {

                    table = $('#tableMonitoring').DataTable({
                        scrollX: true,
                        scrollCollapse: true,
                        autoWidth: false,
                        fixedHeader: true,
                        pageLength: 10,
                        lengthMenu: [10, 25, 50, 100],
                        orderCellsTop: true,
                        ordering: true,
                        deferRender: true,
                        language: {
                            search: "Cari:",
                            lengthMenu: "Tampilkan _MENU_ data",
                            info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
                            paginate: { previous: "«", next: "»" }
                        },
                        columnDefs: [
                            { width: "120px", targets: [0, 1, 2, 6, 9] },   // Tgl Keluar, Act PGI, Dist Channel, Ekspedisi, Alert
                            { width: "160px", targets: [8] },               // Status (select on track/potential delay)
                            { width: "140px", targets: [3] },               // Area
                            { width: "350px", targets: 4 },                 // No Shipment
                            { width: "150px", targets: [5] },               // Tujuan
                            { width: "180px", targets: [10, 13, 18, 19, 21] }, // Total DO Qty, Total Biaya, Estimasi Tiba, Tgl Tiba, Tgl Bongkar
                            { width: "220px", targets: [32] }               // Kelengkapan Data
                        ]
                    });

                    // Custom filter: Tanggal Keluar Gudang
                    $('#filterKeluarGudangTgl').on('change', function() {
                        table.draw();
                    });

                    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                        let rowNode = table.row(dataIndex).node();
                        let filterDate = $('#filterKeluarGudangTgl').val();
                        let tanggalKeluar = $(rowNode).attr('data-keluar');
                        if (filterDate && tanggalKeluar !== filterDate) return false;
                        return true;
                    });

                    $('.filter-box .searchable').select2({ width: '180px' });
                    $('#shipModal .searchable').select2({ width: '100%', dropdownParent: $('#shipModal') });

                    $('#shipModal form').on('submit', function(e) {
                        e.preventDefault();
                        $.ajax({
                            url: $(this).attr('action'),
                            type: 'POST',
                            data: $(this).serialize(),
                            success: function(res) {
                                $('#shipModal').modal('hide');
                                $('#shipModal form')[0].reset();
                                alert(res.message);
                                location.reload();
                            },
                            error: function(err) {
                                alert('Gagal update data');
                            }
                        });
                    });

                    initReasonSelect();
                    updateFieldStatus(true); // true = tampilkan toast sekali di awal kalau ada yang kosong

                    table.on('draw.dt', function() {
                        initReasonSelect();
                        updateFieldStatus(false);
                    });
                });

                function formatRupiah(angka) {
                    return 'Rp ' + Number(angka).toLocaleString('id-ID');
                }

                $(document).on('input', 'input[name="qty_monitoring"], input[name="biaya_kuli"]', function() {
                    let row = $(this).closest('tr');
                    let qty = parseInt(row.find('input[name="qty_monitoring"]').val()) || 0;
                    let biaya = parseInt(row.find('input[name="biaya_kuli"]').val()) || 0;
                    let total = qty * biaya;
                    row.find('input[name="total_biaya_kuli"]').val(formatRupiah(total));
                });

                $(document).on('input', '[name="total_do_qty_car"], [name="selisih_qty"]', function() {
                    let row = $(this).closest('tr');
                    let total = parseFloat(row.find('[name="total_do_qty_car"]').val()) || 0;
                    let selisih = parseFloat(row.find('[name="selisih_qty"]').val()) || 0;
                    let qtyMonitoring = total - selisih;
                    row.find('[name="qty_monitoring"]').val(qtyMonitoring).trigger('input');
                });

                // ==========================================================
                // ALERT CONTROL (versi final):
                // - HANYA cek Tgl Tiba & Tgl Bongkar.
                // - Row dianggap "alert" kalau sudah melewati estimasi_tiba
                //   (data-estimasi < hari ini) DAN Tgl Tiba & Tgl Bongkar
                //   MASIH KOSONG. Kalau belum lewat estimasi (masih H-),
                //   TIDAK dianggap alert sama sekali.
                // - Selisih Qty & Biaya Kuli TIDAK lagi ikut dihitung.
                // ==========================================================
                function updateFieldStatus(showToast) {
                    showToast = showToast || false;

                    let alertList = [];
                    let missingSummary = {}; // { "Tgl Tiba": 3, "Tgl Bongkar": 3 }
                    let todayStr = new Date().toISOString().slice(0, 10);
                    let anyOverdue = false;

                    $('#tableMonitoring tbody tr').each(function() {
                        let row = $(this);
                        let missingFields = [];

                        let estimasiStr = (row.attr('data-estimasi') || '').trim();
                        let isOverdue = estimasiStr !== '' && estimasiStr < todayStr;
                        if (isOverdue) anyOverdue = true;

                        row.find('td[data-required="true"]').each(function() {
                            let td = $(this);
                            let input = td.find('input, select');
                            let val = (input.val() || '').toString().trim();
                            let filled = val !== '';
                            let label = td.data('label'); // "Tgl Tiba" atau "Tgl Bongkar"

                            // hanya dianggap kosong/alert kalau sudah lewat estimasi tiba
                            let countAsMissing = !filled && isOverdue;

                            input.toggleClass('input-filled', filled)
                                 .toggleClass('input-empty', countAsMissing);

                            if (countAsMissing) {
                                missingFields.push(label);
                                missingSummary[label] = (missingSummary[label] || 0) + 1;
                            }
                        });

                        let badge = row.find('.completeness-badge');
                        let emptyCount = missingFields.length;

                        if (!isOverdue) {
                            // belum lewat estimasi tiba (atau tidak ada estimasi) -> netral, bukan "Lengkap"
                            badge.attr('class', 'badge completeness-badge gray')
                                 .text('-')
                                 .attr('title', 'Belum jatuh tempo estimasi tiba');
                        } else if (emptyCount === 0) {
                            badge.attr('class', 'badge completeness-badge green')
                                 .text('✅ Lengkap')
                                 .attr('title', 'Data lengkap');
                        } else {
                            let cls = emptyCount === 1 ? 'orange' : 'red';
                            let text = '❌ ' + missingFields.join(', ');
                            badge.attr('class', 'badge completeness-badge ' + cls)
                                 .text(text)
                                 .attr('title', text);

                            alertList.push({
                                id: row.data('id'),
                                shipment: row.data('shipment'),
                                missing: missingFields,
                                emptyCount: emptyCount,
                                estimasi: estimasiStr
                            });
                        }
                    });

                    renderMissingFieldSummary(missingSummary, anyOverdue);
                    renderAlertControl(alertList);

                    if (showToast && alertList.length > 0) {
                        showToastMsg('⚠ ' + alertList.length + ' shipment sudah lewat estimasi tiba, tapi Tgl Tiba/Tgl Bongkar belum diisi');
                    }
                }

                function renderMissingFieldSummary(missingSummary, anyOverdue) {
                    let entries = Object.entries(missingSummary).sort((a, b) => b[1] - a[1]);

                    if (entries.length === 0) {
                        if (anyOverdue) {
                            $('#missingFieldSummary').html('<span class="badge green">✅ Semua data lengkap</span>');
                        } else {
                            $('#missingFieldSummary').html('<span class="badge gray">- Belum ada yang jatuh tempo estimasi tiba</span>');
                        }
                        return;
                    }

                    let html = entries.map(function(e) {
                        return '<span class="badge red">' + e[0] + ': ' + e[1] + '</span>';
                    }).join(' ');

                    $('#missingFieldSummary').html(html);
                }

                function renderAlertControl(alertList) {
                    $('#alertControlCount').text(alertList.length + ' Alert');

                    if (alertList.length === 0) {
                        $('#alertControlList').html('<div class="p-2" style="color:#22c55e;">✅ Tidak ada shipment yang lewat estimasi tiba</div>');
                        return;
                    }

                    // urutkan dari yang paling lama lewat estimasinya
                    alertList.sort(function(a, b) { return a.estimasi.localeCompare(b.estimasi); });

                    let html = alertList.map(function(a) {
                        let sev = a.emptyCount === 2 ? 'red' : 'orange';
                        let estimasiInfo = a.estimasi ? (' • Estimasi ' + a.estimasi) : '';
                        return '' +
                            '<div class="alert-item" data-id="' + a.id + '">' +
                                '<div class="alert-top">' +
                                    '<b>🚚 ' + a.shipment + '</b>' +
                                    '<span class="badge ' + sev + '">' + a.emptyCount + ' kosong</span>' +
                                '</div>' +
                                '<div class="alert-missing">Belum diisi: ' + a.missing.join(', ') + estimasiInfo + '</div>' +
                            '</div>';
                    }).join('');

                    $('#alertControlList').html(html);
                }

                // Klik item alert -> lompat & highlight row terkait di tabel
                $(document).on('click', '.alert-item', function() {
                    let id = $(this).data('id');
                    table.search('').columns().search('').draw();

                    setTimeout(function() {
                        let $row = $('tr[data-id="' + id + '"]');
                        if ($row.length) {
                            $row.addClass('highlight-row');
                            $row.get(0).scrollIntoView({ behavior: 'smooth', block: 'center' });
                            setTimeout(function() { $row.removeClass('highlight-row'); }, 2000);
                        }
                    }, 150);
                });

                function showToastMsg(msg) {
                    let toast = $(
                        '<div class="toast"><strong>Perhatian</strong>' + msg + '</div>'
                    );
                    $('#toastContainer').append(toast);
                    setTimeout(function() {
                        toast.fadeOut(400, function() { toast.remove(); });
                    }, 6000);
                }

                function saveRow(id) {
                    let row = $('tr[data-id="' + id + '"]');
                    $.ajax({
                        url: '/monitoring/update/' + id,
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            _method: 'PUT',
                            pic_monitoring: row.find('[name="pic_monitoring"]').val(),
                            status_kendaraan: row.find('[name="status_kendaraan"]').val(),
                            action_required: row.find('[name="action_required"]').val(),
                            act_urutan_bongkar: row.find('[name="act_urutan_bongkar"]').val(),
                            tanggal_tiba: row.find('[name="tanggal_tiba"]').val(),
                            tanggal_bongkar: row.find('[name="tanggal_bongkar"]').val(),
                            reason_tiba: row.find('[name="reason_tiba"]').val(),
                            reason_bongkar: row.find('[name="reason_bongkar"]').val(),
                            remarks_qty: row.find('[name="remarks_qty"]').val(),
                            remarks: row.find('[name="remarks"]').val(),
                            act_pgi_date: row.find('[name="act_pgi_date"]').val(),
                            total_do_qty_car: row.find('[name="total_do_qty_car"]').val(),
                            qty_monitoring: row.find('[name="qty_monitoring"]').val(),
                            selisih_qty: row.find('[name="selisih_qty"]').val(),
                            biaya_kuli: row.find('[name="biaya_kuli"]').val(),
                        },
                        beforeSend: function() {
                            row.find('.save-btn').prop('disabled', true).text('Saving...');
                            row.find('.save-status').html('⏳ Saving...');
                        },
                        success: function(response) {
                            row.find('.save-btn').prop('disabled', false).text('SAVE');
                            row.find('.save-status').html('✅ Saved');
                            setTimeout(function() { row.find('.save-status').html(''); }, 2000);
                        },
                        error: function(xhr) {
                            row.find('.save-btn').prop('disabled', false).text('SAVE');
                            row.find('.save-status').html('❌ Error');
                        }
                    });
                }

                // Auto-save saat input berubah
                $(document).on('change', '#tableMonitoring input, #tableMonitoring select', function() {
                    let row = $(this).closest('tr');
                    let id = row.data('id');
                    clearTimeout(saveTimer);
                    saveTimer = setTimeout(function() {
                        saveRow(id);
                    }, 500);
                });

                function initReasonSelect() {
                    $('.searchable-select').each(function() {
                        if ($(this).hasClass('select2-hidden-accessible')) {
                            $(this).select2('destroy');
                        }
                        $(this).select2({
                            width: 'resolve',
                            placeholder: 'Pilih Reason',
                            allowClear: true
                        });
                    });
                }
            </script>

        </div>
    </div>

</body>
</html>