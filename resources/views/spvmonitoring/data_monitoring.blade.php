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
    <!-- <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script> -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

    <style>
        body {
            background: #f3f4f6;
            font-family: 'Segoe UI';
            margin: 0;
        }

        .container {
            width: calc(100% - 250px);
            margin-left: 250px;
            padding: 20px;
        }

      .title {
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 20px;
    color: #111827; /* atau #000 */
}

        .card {
            background: #fff;
            padding: 15px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            overflow: auto;
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

        .filter-box select {
            min-width: 180px;
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
        }

        .green {
            background: #22c55e;
        }

        .red {
            background: #ef4444;
        }

        .orange {
            background: #f59e0b;
        }

        .card {
            overflow-x: auto;
        }

        #tableMonitoring {
            width: 100% !important;
        }

        #tableMonitoring th {
            text-align: center;
            vertical-align: middle;
        }

        #tableMonitoring td {
            vertical-align: middle;
            white-space: nowrap;
        }

        #tableMonitoring input[type=text] {
            min-width: 120px;
        }

        #tableMonitoring input[type=number] {
            width: 70px;
        }

        #tableMonitoring input[type=datetime-local] {
            width: 170px;
        }

        .select2-container {
            min-width: 160px !important;
        }

        .save-btn {
            min-width: 70px;
        }

        #tableMonitoring th,
        #tableMonitoring td {
            text-align: left;
            vertical-align: middle;
            white-space: nowrap;
        }

        #tableMonitoring input[type="text"] {
            min-width: 120px;
        }

        #tableMonitoring input[type="number"] {
            width: 70px;
        }

        #tableMonitoring input[type="datetime-local"] {
            width: 170px;
        }

        #tableMonitoring .save-btn {
            width: 70px;
        }

        #tableMonitoring .badge {
            display: inline-block;
            min-width: 70px;
            text-align: center;
        }

        .select2-container {
            min-width: 140px !important;
        }

        .select2-selection {
            height: 32px !important;
        }

        .dataTables_wrapper {
            overflow-x: auto;
        }

        .card {
            overflow-x: auto;
        }

        /* =========================
   NOTIF PANEL + TOAST SYSTEM
========================= */



        @keyframes slideIn {
            from {
                transform: translateX(120%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* PANEL LIST NOTIF */
        .notif-panel {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 320px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .2);
            z-index: 99998;
            overflow: hidden;
            font-size: 12px;
        }

        .highlight-row td {
            background: #f90303 !important;
            animation: blinkRow 1s infinite alternate;
        }

        .highlight-row {
            background-color: #d32c0f !important;
            /* Warna kuning latar soft */
            border: 2px solid #ff0202 !important;
            /* Garis pinggir penanda */
            transition: background-color 0.4s ease;
        }

        @keyframes blinkRow {
            from {
                background: #ffffff;
            }

            to {
                background: #ffffff;
            }
        }

        .notif-header {
            background: #111827;
            color: #fff;
            padding: 10px;
            font-weight: bold;
        }

        .notif-body {
            max-height: 250px;
            overflow-y: auto;
        }

        .notif-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }

        .notif-search {
            width: 100%;
            padding: 8px;
            border: none;
            border-bottom: 1px solid #ddd;
            outline: none;
        }

        .status-select {
            min-width: 150px;
            padding: 6px;
            border-radius: 6px;
            border: 1px solid #ddd;
        }

        .transport-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .transport-tabs a {
            padding: 10px 18px;
            text-decoration: none;
            border-radius: 10px;
            background: #e5e7eb;
            color: #111827;
            font-weight: 600;
            transition: .3s;
        }

        .transport-tabs a:hover {
            background: #d1d5db;
        }

        .blue {
            background: #2563eb;
        }

        .transport-tabs a.active {
            background: #2563eb;
            color: #fff;
        }

        .btn-filter {
            display: inline-block;
            padding: 8px 15px;
            background: #e5e7eb;
            color: #111827;
            border-radius: 8px;
            text-decoration: none;
            margin-right: 5px;
            font-weight: 600;
        }

        .btn-filter.active {
            background: #2563eb;
            color: #fff;
        }

        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            width: 350px;
            z-index: 99999;
        }

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

        .toast {
            background: #111827;
            color: #fff;
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
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
            from {
                transform: translateX(120%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* PANEL LIST NOTIF */
        .notif-panel {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 320px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .2);
            z-index: 99998;
            overflow: hidden;
            font-size: 12px;
        }

        .notif-header {
            background: #111827;
            color: #fff;
            padding: 10px;
            font-weight: bold;
        }

        .notif-body {
            max-height: 250px;
            overflow-y: auto;
        }

        .notif-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }

        .notif-search {
            width: 100%;
            padding: 8px;
            border: none;
            border-bottom: 1px solid #ddd;
            outline: none;
        }

        .highlight-row {
            background: #ffffff !important;
            animation: blinkRow 1s infinite alternate;
        }

        @keyframes blinkRow {
            from {
                background: #ffffff;
            }

            to {
                background: #ffffff;
            }
        }

        .card {
            width: 100%;
        }

        .dataTables_wrapper {
            width: 100%;
        }

        .table-responsive {
            width: 100%;
        }

        .container-fluid {
            margin-left: 250px;
            /* sama dengan lebar sidebar */
            width: calc(100% - 250px);
            padding: 20px;
        }

        .card {
            width: 100%;
            overflow-x: auto;
        }

        .sidebar {
            position: fixed;
            left: 0;
            width: 250px;
        }

        .dataTables_wrapper {
            width: 100%;
        }

        table.dataTable {
            width: 100% !important;
        }

        .input-filled {
            background: #bbf7d0 !important;
            border: 2px solid #16a34a !important;
        }

        .input-empty {
            background: #fecaca !important;
            border: 2px solid #dc2626 !important;
        }

        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            width: 380px;
            z-index: 999999;
        }

        .notif-toast {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #ffffff, #f8fafc);
            border-radius: 16px;
            padding: 15px;
            margin-bottom: 12px;
            cursor: pointer;

            box-shadow:
                0 10px 30px rgba(0, 0, 0, .15),
                0 2px 10px rgba(0, 0, 0, .08);

            animation: slideIn .4s ease;
            transition: all .25s ease;
        }

        .notif-toast:hover {
            transform: translateY(-3px) scale(1.02);
        }

        .notif-toast::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 6px;
            height: 100%;
            background: #f59e0b;
        }

        .notif-toast.urgent::before {
            background: #ef4444;
        }

        .notif-toast.h1::before {
            background: #dc2626;
        }

        .notif-top {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .notif-icon {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            background: #fef3c7;
        }

        .notif-title {
            font-weight: 700;
            font-size: 15px;
            color: #111827;
        }

        .notif-sub {
            font-size: 12px;
            color: #6b7280;
        }

        .notif-body {
            font-size: 13px;
            line-height: 1.6;
            color: #374151;
        }

        .notif-shipment {
            font-weight: 700;
            color: #2563eb;
        }

        .notif-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 4px;
            background: #2563eb;
            animation: countdown 5s linear forwards;
        }

        @keyframes countdown {
            from {
                width: 100%;
            }

            to {
                width: 0%;
            }
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(120%);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .highlight-row td {
            background: #ff4000 !important;
            animation: pulseRow .8s infinite alternate;
        }

        @keyframes pulseRow {
            from {
                background: #fde68a;
            }

            to {
                background: #fef3c7;
            }
        }

        /* @keyframes slideIn{
    from{
        transform: translateX(120%);
        opacity:0;
    }
    to{
        transform: translateX(0);
        opacity:1;
    }
} */
    .blue{
    background:#2563eb;
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
    
    <!-- <div id="notifBox" style="
    position:fixed;
    top:20px;
    right:20px;
    width:350px;
    background:#111827;
    color:#fff;
    padding:15px;
    border-radius:12px;
    box-shadow:0 10px 25px rgba(0,0,0,.3);
    display:none;
    z-index:9999;
">
    <h4 style="margin-bottom:10px;">⚠ Estimasi Tiba H-1</h4>
    <div id="notifContent" style="font-size:13px; line-height:1.5;"></div>

    <button onclick="$('#notifBox').fadeOut()"
        style="margin-top:10px;padding:6px 10px;border:none;background:#ef4444;color:#fff;border-radius:6px;">
        Tutup
    </button>
</div> -->
    <!-- TOAST POPUP -->
    <div class="toast-container" id="toastContainer"></div>


    <!-- PANEL LIST NOTIF (SEARCHABLE) -->

    <div class="container-fluid px-3">
        @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
        @endif

        <div class="title">🚚 DATA MONITORING</div>

        <div class="toast-container" id="toastContainer"></div>
                 <div class="mb-3">
<a href="{{ route('monitoring.export', [
    'pic_monitoring' => request('pic_monitoring'),
    'area' => request('area')
]) }}" class="btn btn-success">
    Export Excel
</a>
</div>
        <!-- <div id="notifBox" style="
position:fixed;
top:20px;
right:20px;
width:350px;
background:#111827;
color:#fff;
padding:15px;
border-radius:12px;
box-shadow:0 10px 25px rgba(0,0,0,.3);
display:none;
z-index:9999;
">
            <h4 style="margin-bottom:10px;"> Estimasi Tiba ⚠️ </h4>
            <div id="notifContent"></div>
        </div> -->





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

                {{-- TOMBOL RESET FILTER AKAN MUNCUL HANYA JIKA ADA SALAH SATU FILTER YANG AKTIF --}}
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

        <!-- MODAL -->

        <div class="card">

            <!-- 🔥 TARUH DI SINI -->
<!-- 
            <div id="notifBox" style="
position:fixed;
top:20px;
right:20px;
width:350px;
background:#111827;
color:#fff;
padding:15px;
border-radius:12px;
box-shadow:0 10px 25px rgba(0,0,0,.3);
display:none;
z-index:9999;
">
                <h4 style="margin-bottom:10px;"> Estimasi Tiba ⚠️</h4>
                <div id="notifContent"></div>
            </div> -->
            <table id="tableMonitoring" class="display nowrap">
                <thead>
                    <tr>

                        <!-- EDITABLE -->

                        <th>Tanggal Keluar Gudang</th>
                        <!-- <th>Transportasi</th>
                         -->
                        <th class="editable">Act PGI Date</th>
                        <th>Dist Channel</th>

                        <th>Area</th>
                        <th>No Shipment</th>
                        <th>Tujuan</th>
                        <th>Ekspedisi</th>

                        <th class="editable">PIC</th>
                   
                        <th class="editable">Status</th>
                        <th class="editable">Action</th>
                         <th>Total DO Qty</th>
                        <th class="editable">Total DO Qty Actual</th>
                         <th class="editable">Biaya Kuli</th>
                          <th>hasil kuli</th>
                          <th>Selisih Qty</th>

                        <th class="editable">Reason Qty</th>
                        <th class="editable">Urutan</th>
                        <!-- READONLY -->





                        <!-- EDITABLE -->


                        <!-- READONLY -->
                        <th>Keluar</th>
                        <th>Estimasi</th>
                       

                        <!-- EDITABLE -->
                        <th class="editable">Tiba</th>

                        <!-- READONLY -->
                        <th>Lama</th>
                        <th>SLA Tiba</th>

                        <!-- EDITABLE -->
                        <th class="editable">Bongkar</th>
<th>Status Bongkar</th>

                        <!-- READONLY -->
                        <th>Overstay</th>
                        <th>SLA Bongkar</th>

                        <!-- EDITABLE -->


                        <!-- EDITABLE -->
                        <th class="editable">Reason Tiba</th>
                        <th class="editable">Reason Bongkar</th>
                        <th class="editable">Remarks</th>
                             <th class="editable">Nama Kapal</th>

                        <th>ETD</th>
                        <th>ETA</th>
                         <th>ATD</th>
                        <th>ATA</th>



                        <!-- READONLY -->
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
// =========================
// 1. KELUAR GUDANG TERAKHIR
// =========================
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

$keluar = $keluarTimestamp ?: null;

// =========================
// 2. TIBA
// =========================
$tiba = $r->tanggal_tiba
    ? strtotime($r->tanggal_tiba)
    : null;

// =========================
// 3. LAMA PERJALANAN
// =========================
$lama_perjalanan = '-';

if ($tiba && $keluar) {
    $lama_perjalanan = floor(($tiba - $keluar) / 86400);
}

// =========================
// 4. ESTIMASI TIBA
// =========================

   
// =========================
// 4. ESTIMASI TIBA (DATABASE)
// =========================

$estimasi = !empty($r->estimasi_tiba)
    ? strtotime($r->estimasi_tiba)
    : null;

$estimasi_show = $estimasi
    ? date('d-m-Y', $estimasi)
    : '-';
$alert = '-';
$alertClass = '';
$alertText = '';

if (!$r->tanggal_tiba && $estimasi) {

    $today = strtotime(date('Y-m-d'));
    $hariSisa = floor(($estimasi - $today) / 86400);

    if ($hariSisa < 0) {
        $alertText = 'OVERDUE';
        $alertClass = 'red';
    } elseif ($hariSisa == 0) {
        $alertText = 'H-0';
        $alertClass = 'red';
    } elseif ($hariSisa == 1) {
        $alertText = 'H-1';
        $alertClass = 'red';
    } elseif ($hariSisa == 2) {
        $alertText = 'H-2';
        $alertClass = 'orange';
    } elseif ($hariSisa == 3) {
        $alertText = 'H-3';
        $alertClass = 'orange';
    } elseif ($hariSisa <= 7) {
        $alertText = 'H-' . $hariSisa;
        $alertClass = 'blue';
    } else {
        $alertText = 'ON TRACK';
        $alertClass = 'green';
    }

    $alert = $alertText;
}
// =========================
// 5. SLA TIBA
// =========================
// =========================
// 5. SLA TIBA
// =========================

$sla_tiba = $r->sla_tiba;

// =========================
//$sla_bongkar = $r->sla_bongkar;


    // =========================
    // STATUS BONGKAR
    // =========================

    $statusBongkar = '-';
    $statusBongkarClass = '';

    if ($r->tanggal_bongkar) {

        // Kalau tanggal bongkar sudah diinput
        $statusBongkar = 'Sudah Bongkar';
        $statusBongkarClass = 'green';

    } elseif ($r->tanggal_tiba) {

        // Kalau sudah tiba tapi belum bongkar
        $tanggalTiba = strtotime(date('Y-m-d', strtotime($r->tanggal_tiba)));
        $today = strtotime(date('Y-m-d'));

        $hariBongkar = floor(($today - $tanggalTiba) / 86400);

        $statusBongkar = 'Pending Bongkar H+' . max(0, $hariBongkar);

        // Warna status
        if ($hariBongkar == 0) {
            $statusBongkarClass = 'orange';
        } elseif ($hariBongkar == 1) {
            $statusBongkarClass = 'red';
        } else {
            $statusBongkarClass = 'red';
        }
    }

@endphp
                      <tr
    class="{{ ($alert ?? '') == 'H-1' ?  : '' }}"
    data-id="{{ $r->id }}"
    data-shipment="{{ $r->no_shipment }}"
    data-keluar="{{ $keluar ? date('Y-m-d', $keluar) : '' }}">
                     <td>
    {{ $keluar 
        ? date('d-m-Y ', $keluar) 
        : '-' }}
</td>



                       <td>{{ $r->create_tgl ? \Carbon\Carbon::parse($r->create_tgl)->format('d/m/Y H:i') : '-' }}</td>

                        <td>{{ $r->dist_channel }}</td>

                        <td>{{ $r->area }}</td>
                        <td>{{ $r->no_shipment }}</td>

                        <td>{{ $r->tujuan }}</td>



                        <td>{{ $r->ekpedisi }}</td>




                        <td>
                            <input type="text" name="pic_monitoring"
                                value="{{ $r->pic_monitoring }}">
                        </td>
                       

                        <td>
                            <select name="status_kendaraan" class="form-select status-select">
                                <option value="On Track"
                                    {{ $r->status_kendaraan == 'On Track' ? 'selected' : '' }}>
                                    🟢 On Track
                                </option>

                                <option value="Potential Delay"
                                    {{ $r->status_kendaraan == 'Potential Delay' ? 'selected' : '' }}>
                                    🔴 Potential Delay
                                </option>
                            </select>
                        </td>
                     <td>
    @if($r->tanggal_tiba)

        <span class="badge green">
            ✅ TIBA
        </span>

    @else

        <span class="badge {{ $alertClass }}">
            {{ $alert }}
        </span>

    @endif
</td>

                      <td>{{ $r->total_do_qty_car }}</td>
                                  <td>
                            <input type="number"
                                name="qty_monitoring"
                                value="{{ $r->qty_monitoring }}">
                        </td>
                        <td>
    <input type="number"
           name="biaya_kuli"
           value="{{ $r->biaya_kuli }}">
</td>

<td>
    <input type="text"
           name="total_biaya_kuli"
           value="Rp {{ number_format($r->total_biaya_kuli ?? 0, 0, ',', '.') }}"
           readonly>
</td>
                            <td>
    <input type="number"
           name="selisih_qty"
           value="{{ $r->selisih_qty }}"
           readonly>
</td>
                                  <td>
                            <input type="text"
                                name="remarks_qty"
                                value="{{ $r->remarks_qty }}">
                        </td>

                        <td>
                            <input type="number"
                                name="act_urutan_bongkar"
                                value="{{ $r->act_urutan_bongkar }}">
                        </td>

                      <td>
    {{ $keluar 
        ? date('d-m-Y H:i', $keluar) 
        : '-' }}
</td>

                  <td>
    {{ $r->estimasi_tiba ? date('d-m-Y', strtotime($r->estimasi_tiba)) : '-' }}
</td>
                       
                        <td>
                            <input type="datetime-local"
                                name="tanggal_tiba"
                                value="{{ $r->tanggal_tiba
                ? date('Y-m-d\TH:i', strtotime($r->tanggal_tiba))
                : '' }}">
                        </td>
<td>{{ $lama_perjalanan }}</td>


                        <td>

                            @if($sla_tiba == '-')

                            -

                            @elseif($sla_tiba == 'On Time')

                            <span class="badge green">
                                On Time
                            </span>

                            @else

                            <span class="badge red">
                                {{ $sla_tiba }}
                            </span>

                            @endif

                        </td>

                        <td>
                            <input type="datetime-local"
                                name="tanggal_bongkar"
                                value="{{ $r->tanggal_bongkar
                ? date('Y-m-d\TH:i', strtotime($r->tanggal_bongkar))
                : '' }}">
                        </td>
                   <td>
    @if($statusBongkar != '-')
        <span class="badge {{ $statusBongkarClass }}">
            {{ $statusBongkar }}
        </span>
    @else
        -
    @endif
</td>

                       <td>{{ $r->overstay_days ?? '-' }}</td>

                        <td>

                            @if($sla_bongkar == '-')

                            -

                            @elseif($sla_bongkar == 'On Time')

                            <span class="badge green">
                                On Time
                            </span>

                            @else

                            <span class="badge red">
                                {{ $sla_bongkar }}
                            </span>

                            @endif

                        </td>



                        <td>
                            <select name="reason_tiba"
                                class="reason-select searchable-select">

                                <option value="">Pilih Reason Tiba</option>

                                @foreach($akurasiTiba as $item)
                                <option value="{{ $item }}"
                                    {{ $r->reason_tiba == $item ? 'selected' : '' }}>
                                    {{ $item }}
                                </option>
                                @endforeach

                            </select>

                            <span class="d-none txt">
                                {{ $r->reason_tiba }}
                            </span>
                        </td>

                        <td>
                            <select name="reason_bongkar"
                                class="reason-select searchable-select">

                                <option value="">Pilih Reason Bongkar</option>

                                @foreach($akurasiBongkar as $item)
                                <option value="{{ $item }}"
                                    {{ $r->reason_bongkar == $item ? 'selected' : '' }}>
                                    {{ $item }}
                                </option>
                                @endforeach

                            </select>

                            <span class="d-none txt">
                                {{ $r->reason_bongkar }}
                            </span>
                        </td>
                        <td>
                            <input type="text"
                                name="remarks"
                                value="{{ $r->remarks }}">
                        </td>

                           <td>
                            <input type="text" name="nama_kapal"
                                value="{{ $r->nama_kapal }}">
                        </td>

                        <td>
                            <input type="date"
                                name="ETD"
                                value="{{ $r->etd ? date('Y-m-d', strtotime($r->etd)) : '' }}">
                        </td>

                        <td>
                            <input type="date"
                                name="ETA"
                                value="{{ $r->eta ? date('Y-m-d', strtotime($r->eta)) : '' }}">
                        </td>
                         <td>
                            <input type="date"
                                name="ATD"
                                value="{{ $r->atd ? date('Y-m-d', strtotime($r->atd)) : '' }}">
                        </td>

                        <td>
                            <input type="date"
                                name="ATA"
                                value="{{ $r->ata ? date('Y-m-d', strtotime($r->ata)) : '' }}">
                        </td>


                        <td>
                            <span class="save-status"></span>

                            <button
                                type="button"
                                class="save-btn"
                                onclick="saveRow({{ $r->id }})">
                                SAVE
                            </button>
                        </td>

                        </tr>

                        <div id="toastContainer" class="toast-container"></div>
                        @endforeach

                        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
                </tbody>
            </table>

           <script>
    let table;
    let notif = [];
    let notifTimer = null;
    let notifShown = false;
    let saveTimer;

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
            { width: "120px", targets: [0, 1, 2, 6, 9] },
            { width: "140px", targets: [3, 8] },
            { width: "350px", targets: 4 },
            { width: "150px", targets: [5, 20] },
            { width: "80px", targets: [7, 14, 21] },
            { width: "180px", targets: [10, 13, 18, 19] },
            { width: "70px", targets: 11 },
            { width: "90px", targets: 12 },
            { width: "100px", targets: [15, 16, 17] }
        ]
    });

        // Custom Filter Tanggal Import
        var importTglFilter = '';
        $('#filterImportTgl').on('change', function() {
            importTglFilter = $(this).val();
            table.draw();
        });

        // Custom Filter Tanggal Keluar Gudang
        $('#filterKeluarGudangTgl').on('change', function() {
            table.draw();
        });

        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
            var rowNode = table.row(dataIndex).node();
            
            // Logika Filter Import
            if (importTglFilter !== '') {
                var importTglRow = $(rowNode).attr('data-import') || '';
                if (importTglRow !== importTglFilter) return false;
            }

            // Logika Filter Keluar Gudang
            let filterDate = $('#filterKeluarGudangTgl').val();
            let tanggalKeluar = $(rowNode).attr('data-keluar');
            if (filterDate && tanggalKeluar !== filterDate) return false;

            return true;
        });

        // Inisialisasi Select2 Global & Modal
        $('.filter-box .searchable').select2({ width: '180px' });
        $('#shipModal .searchable').select2({
            width: '100%',
            dropdownParent: $('#shipModal')
        });

        // Submit Form Modal
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

        // Jalankan Notifikasi Pertama Kali
        generateNotif(true);
        setInterval(generateNotif, 1000000n); // refresh tiap 1 menit

        table.on('draw', function () {
            generateNotif(false);
        });

        // Warna Input Tanggal
        updateDateColor();
    });


    // Menghitung Notifikasi
function generateNotif(showPopup = true) {

    notif = [];

    table.rows().every(function () {

        let row = $(this.node());

        let shipment = row.data('shipment');
        let estimasi = row.find('.estimasi-tiba').data('estimasi');
        let tujuan = row.find('.estimasi-tiba').data('tujuan');
        let tanggalTiba = row.find('[name="tanggal_tiba"]').val();

        if (!shipment || !estimasi) return;

        // Sudah tiba = tidak notif
        if (tanggalTiba && tanggalTiba.trim() !== '') return;

        let estDate = new Date(estimasi + 'T00:00:00');

        let today = new Date();
        today.setHours(0,0,0,0);

        let diff = Math.ceil(
            (estDate - today) /
            (1000 * 60 * 60 * 24)
        );

        // Hanya notif di hari tertentu
        if (![7,5,3,2,1].includes(diff)) return;

        let level = 'followup';

        if (diff <= 3) {
            level = 'urgent';
        }

        if (diff === 1) {
            level = 'critical';
        }

        notif.push({
            shipment,
            tujuan,
            estimasi,
            diff,
            level
        });
    });

    renderNotifList("", showPopup);
}

function formatRupiah(angka) {
    return 'Rp ' + Number(angka).toLocaleString('id-ID');
}

$(document).on('input', 'input[name="qty_monitoring"], input[name="biaya_kuli"]', function () {

    let row = $(this).closest('tr');

    let qty = parseInt(row.find('input[name="qty_monitoring"]').val()) || 0;
    let biaya = parseInt(row.find('input[name="biaya_kuli"]').val()) || 0;

    let total = qty * biaya;

    row.find('input[name="total_biaya_kuli"]').val(formatRupiah(total));

});

    // Render HTML Notifikasi ke dalam box
    function renderNotifList(filter = "", showPopup = true) {
        let html = "";
        notif
            .filter(i => String(i.shipment || '').toLowerCase().includes(String(filter || '').toLowerCase()))
            .forEach(i => {
                html += `
                <div class="notif-item" data-shipment="${i.shipment}" style="padding:12px 14px; border-radius:10px; margin-bottom:10px; cursor:pointer; background: linear-gradient(135deg, #111827, #1f2937); border: 1px solid rgba(255,255,255,0.06); transition: all 0.2s ease;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <b style="color:#ffffff; font-size:14px; letter-spacing:0.3px;">🚚 ${i.shipment}</b>
                        <span style="font-size:11px; padding:4px 8px; border-radius:999px; background:${i.diff <= 1 ? '#dc2626' : '#f59e0b'}; color:white; font-weight:600;">H${i.diff === 1 ? 'BESOK' : 'H-' + i.diff}</span>
                    </div>
                    <div style="margin-top:6px; font-size:12px; color:#cbd5e1;">📦 Estimasi: <b style="color:#fff">${i.estimasi}</b></div>
                    <div style="margin-top:4px; font-size:12px; color:#cbd5e1;">📍 Tujuan: <b style="color:#fff">${i.tujuan}</b></div>
                    <div style="margin-top:4px; font-size:11px; color:#94a3b8;">Klik untuk lihat detail shipment →</div>
                </div>`;
            });

        $('#notifContent').html(html);

        if (notif.length > 0 && showPopup && !notifShown) {
            notifShown = true;
            $('#notifBox').stop(true,true).fadeIn();
            clearTimeout(notifTimer);
            notifTimer = setTimeout(() => {
                $('#notifBox').fadeOut(300);
                notifShown = false;
            }, 5000);
        }
    }


    // Filter list notifikasi berdasarkan keyup (Typo 'renderNotisfList' diperbaiki di sini)
    $('#notifSearch').on('keyup', function () {
        renderNotifList($(this).val(), false);
    });

    // Event klik list notif untuk filter DataTable
    $(document).on('click', '.notif-item', function () {
        $('#notifBox').fadeOut(200);
        let shipment = String($(this).data('shipment') || '');
        table.search('').columns().search('');
        table.column(4).search('^' + shipment + '$', true, false).draw();
    });

    // Fungsi Save Row Ajax
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
                remarks: row.find('[name="remarks"]').val(),
                act_pgi_date: row.find('[name="act_pgi_date"]').val(),
                total_do_qty_car: row.find('[name="total_do_qty_car"]').val(),
                    qty_monitoring: row.find('[name="qty_monitoring"]').val(),
    selisih_qty: row.find('[name="selisih_qty"]').val(),
      biaya_kuli: row.find('[name="biaya_kuli"]').val(),
    //   total_biaya_kuli: row.find('[name="total_biaya_kuli"]').val(),/
    remarks_qty: row.find('[name="remarks_qty"]').val(),
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

    // Auto Save saat input berubah
    $(document).on('change', '#tableMonitoring input, #tableMonitoring select', function() {
        let row = $(this).closest('tr');
        let id = row.data('id');
        clearTimeout(saveTimer);
        saveTimer = setTimeout(function() {
            saveRow(id);
        }, 500);
    });
    $(document).on('input', '[name="total_do_qty_car"], [name="qty_monitoring"]', function () {

    let row = $(this).closest('tr');

    let total = parseFloat(row.find('[name="total_do_qty_car"]').val()) || 0;
    let qty   = parseFloat(row.find('[name="qty_monitoring"]').val()) || 0;

    row.find('[name="selisih_qty"]').val(total - qty);

});

    // Fungsi warna input tanggal kosong/berisi
    function updateDateColor() {
        $('#tableMonitoring input[type="date"], #tableMonitoring input[type="datetime-local"]').each(function() {
            if ($(this).val() && $(this).val().trim() !== '') {
                $(this).removeClass('input-empty').addClass('input-filled');
            } else {
                $(this).removeClass('input-filled').addClass('input-empty');
            }
        });
    }

    $(document).on('change', '#tableMonitoring input[type="date"], #tableMonitoring input[type="datetime-local"]', function() {
        updateDateColor();
    });

    // Re-init Select2 pada kolom Reason setiap kali Table di-render ulang (pagination / sorting)
    function initReasonSelect() {
        $('.searchable-select').each(function () {
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

    $(document).ready(function () {
        initReasonSelect();
        table.on('draw.dt', function () {
            initReasonSelect();
        });
    });
</script>

</html>
