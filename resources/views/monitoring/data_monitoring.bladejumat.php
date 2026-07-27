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
            background: #fef08a !important;
            animation: blinkRow 1s infinite alternate;
        }

        .highlight-row {
            background-color: #fff3cd !important;
            /* Warna kuning latar soft */
            border: 2px solid #ffc107 !important;
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
            background: #fde68a !important;
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
                                    @foreach($logistik as $r)
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
            <h4 style="margin-bottom:10px;"> Estimasi Tiba ⚠️ </h4>
            <div id="notifContent"></div>
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
            </div>
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
                        <th class="editable">Nama Kapal</th>

                        <th>ETD</th>
                        <th>ETA</th>
                        <th class="editable">Status</th>
                        <th class="editable">Action</th>
                        <th class="editable">Total DO Qty</th>
                        <th class="editable">Urutan</th>
                        <!-- READONLY -->





                        <!-- EDITABLE -->


                        <!-- READONLY -->
                        <th>Keluar</th>
                        <th>Estimasi</th>
                        <th>ATD</th>
                        <th>ATA</th>

                        <!-- EDITABLE -->
                        <th class="editable">Tiba</th>

                        <!-- READONLY -->
                        <th>Lama</th>
                        <th>SLA Tiba</th>

                        <!-- EDITABLE -->
                        <th class="editable">Bongkar</th>

                        <!-- READONLY -->
                        <th>Overstay</th>
                        <th>SLA Bongkar</th>

                        <!-- EDITABLE -->


                        <!-- EDITABLE -->
                        <th class="editable">Reason Tiba</th>
                        <th class="editable">Reason Bongkar</th>
                        <th class="editable">Remarks</th>



                        <!-- READONLY -->
                        <th>Action</th>

                    </tr>
                </thead>

                <tbody>
                    @foreach($logistik as $r)

                    @php

                    $keluar = $r->tanggal_keluar_gudang
                    ? strtotime(date('Y-m-d', strtotime($r->tanggal_keluar_gudang)))
                    : null;

                    $tiba = $r->tanggal_tiba
                    ? strtotime(date('Y-m-d', strtotime($r->tanggal_tiba)))
                    : null;

                    $bongkar = $r->tanggal_bongkar
                    ? strtotime(date('Y-m-d', strtotime($r->tanggal_bongkar)))
                    : null;

                    $leadtime = (int)$r->transport_lead_time;

                    $estimasi = $keluar
                    ? strtotime("+{$leadtime} days", $keluar)
                    : null;


                    /*
                    |--------------------------------------------------------------------------
                    | SLA TIBA
                    |--------------------------------------------------------------------------
                    */

                    $lama = '-';
                    $sla_tiba = '-';

                    if($tiba){

                    $lama = floor(
                    ($tiba - $keluar) / 86400
                    );

                    $selisihTiba = floor(
                    ($tiba - $estimasi) / 86400
                    );

                    if($selisihTiba <= 0){

                        $sla_tiba='On Time' ;

                        }else{

                        $sla_tiba='H+' . $selisihTiba;

                        }

                        }


                        /*
                        |--------------------------------------------------------------------------
                        | SLA BONGKAR
                        |--------------------------------------------------------------------------
                        */

                        $overstay='-' ;
                        $sla_bongkar='-' ;

                        if($tiba && $bongkar){

                        $overstay=floor(
                        ($bongkar - $tiba) / 86400
                        );

                        if($overstay <=0){

                        $sla_bongkar='On Time' ;

                        }else{

                        $sla_bongkar='H+' . $overstay;

                        }

                        }
                        @endphp
                        <tr
                        data-id="{{ $r->id }}"
                        data-shipment="{{ $r->no_shipment }}"
                        data-keluar="{{ $r->tanggal_keluar_gudang ? date('Y-m-d', strtotime($r->tanggal_keluar_gudang)) : '' }}">
                       <td>
                            {{ $r->tanggal_keluar_gudang
            ? date('d-m-Y H:i', strtotime($r->tanggal_keluar_gudang))
            : '-' }}
                        </td>



                        <td>
                            <input type="date"
                                name="act_pgi_date"
                                value="{{ $r->act_pgi_date ? date('Y-m-d', strtotime($r->act_pgi_date)) : '' }}">
                        </td>

                        <td>{{ $r->dist_channel }}</td>

                        <td>{{ $r->area }}</td>
                        <td>{{ $r->no_shipment }}</td>

                        <td>{{ $r->tujuan }}</td>



                        <td>{{ $r->ekpedisi }}</td>




                        <td>
                            <input type="text" name="pic_monitoring"
                                value="{{ $r->pic_monitoring }}">
                        </td>
                        <td>{{ $r->nama_kapal }}</td>

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
                            <input type="text" name="action_required"
                                value="{{ $r->action_required }}">
                        </td>

                        <td>
                            <input type="number"
                                name="total_do_qty_car"
                                value="{{ $r->total_do_qty_car }}">
                        </td>

                        <td>
                            <input type="number"
                                name="act_urutan_bongkar"
                                value="{{ $r->act_urutan_bongkar }}">
                        </td>

                        <td>
                            {{ $r->tanggal_keluar_gudang
            ? date('d-m-Y H:i', strtotime($r->tanggal_keluar_gudang))
            : '-' }}
                        </td>

                        <td class="estimasi-tiba"
                            data-shipment="{{ $r->no_shipment }}"
                            data-tujuan="{{ $r->tujuan }}"
                            data-estimasi="{{ $estimasi ? date('Y-m-d',$estimasi) : '' }}">

                            {{ $estimasi
        ? date('d-m-Y', $estimasi)
        : '-' }}

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
                            <input type="datetime-local"
                                name="tanggal_tiba"
                                value="{{ $r->tanggal_tiba
                ? date('Y-m-d\TH:i', strtotime($r->tanggal_tiba))
                : '' }}">
                        </td>

                        <td>{{ $lama }}</td>

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

                        <td>{{ $overstay }}</td>

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
                

                $(document).ready(function() {

                    table = $('#tableMonitoring').DataTable({

                        scrollX: true,
                        scrollCollapse: true,
                        autoWidth: false,
                        fixedHeader: true,
                        pageLength: 10,
                        orderCellsTop: true,
                        ordering: true,

                        columnDefs: [



                            {
                                width: "120px",
                                targets: 0
                            },
                            {
                                width: "120px",
                                targets: 1
                            },
                            {
                                width: "120px",
                                targets: 2
                            },
                            {
                                width: "140px",
                                targets: 3
                            },
                            {
                                width: "350px",
                                targets: 4
                            },
                            {
                                width: "150px",
                                targets: 5
                            },
                            {
                                width: "120px",
                                targets: 6
                            },
                            {
                                width: "80px",
                                targets: 7
                            },
                            {
                                width: "140px",
                                targets: 8
                            },
                            {
                                width: "120px",
                                targets: 9
                            },
                            {
                                width: "180px",
                                targets: 10
                            },
                            {
                                width: "70px",
                                targets: 11
                            },
                            {
                                width: "90px",
                                targets: 12
                            },
                            {
                                width: "180px",
                                targets: 13
                            },
                            {
                                width: "80px",
                                targets: 14
                            },
                            {
                                width: "100px",
                                targets: 15
                            },
                            {
                                width: "100px",
                                targets: 16
                            },
                            {
                                width: "100px",
                                targets: 17
                            },
                            {
                                width: "180px",
                                targets: 18
                            },
                            {
                                width: "180px",
                                targets: 19
                            },
                            {
                                width: "150px",
                                targets: 20
                            },
                            {
                                width: "80px",
                                targets: 21
                            }

                        ]

                    });

                    $('.searchable-select').select2({
                        width: '100%',
                        placeholder: 'Pilih Reason',
                        allowClear: true
                    });

                    table.columns.adjust().draw();

                    $('#shipModal form').on('submit', function(e) {
                        e.preventDefault();

                        $.ajax({
                            url: $(this).attr('action'),
                            type: 'POST',
                            data: $(this).serialize(),

                            success: function(res) {

                                console.log(res);

                                // tutup modal
                                $('#shipModal').modal('hide');

                                // reset form
                                $('#shipModal form')[0].reset();

                                // notif sederhana
                                alert(res.message);

                                // optional refresh data table
                                location.reload();
                            },

                            error: function(err) {
                                console.log(err.responseText);
                                alert('Gagal update data');
                            }
                        });
                    });
                    // =====================
                    // FILTER TANGGAL IMPORT
                    // =====================
                    var importTglFilter = '';

                    $('#filterImportTgl').on('change', function() {
                        importTglFilter = $(this).val();
                        table.draw();
                    });

                    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                        var rowNode = table.row(dataIndex).node();

                        if (importTglFilter !== '') {
                            var importTglRow = $(rowNode).attr('data-import') || '';
                            if (importTglRow !== importTglFilter) {
                                return false;
                            }
                        }

                        return true;
                    });
                    let notif = [];

                    // function generateNotifEstimasi() {

                    //     console.log('=== GENERATE NOTIF ===');

                    //     notif = [];

                    //     console.log(
                    //         'Jumlah Row:',
                    //         $('#tableMonitoring tbody tr').length
                    //     );

                    // notif = [];

                    // let shipment = row.attr('data-shipment');

                    // let tanggalKeluar = row.attr('data-keluar');

                    // if (!tanggalKeluar) {
                    //     return;
                    // }

                    // let today = new Date();
                    // today.setHours(0,0,0,0);

                    // let keluarDate = new Date(tanggalKeluar + 'T00:00:00');
                    // keluarDate.setHours(0,0,0,0);

                    // let umurKeluar = Math.floor(
                    //     (today - keluarDate) /
                    //     (1000 * 60 * 60 * 24)
                    // );

                    // if (umurKeluar > 30) {
                    //     return;
                    // }

                    // console.log('Shipment:', shipment);

                    // let el = row.find('.estimasi-tiba');
                    // let estimasiText = el.data('estimasi');

                    // if (!estimasiText) {
                    //     return;
                    // }

                    // let tujuan = row.find('td:eq(5)').text().trim();

                    // let tanggalBongkar =
                    //     row.find('[name="tanggal_bongkar"]').val();

                    // if (tanggalBongkar) {
                    //     return;
                    // }

                    // let estDate = new Date(
                    //     estimasiText + 'T00:00:00'
                    // );

                    // estDate.setHours(0,0,0,0);

                    // let diff = Math.floor(
                    //     (estDate - today) /
                    //     (1000 * 60 * 60 * 24)
                    // );

                    // console.log('Diff:', diff);
                    //     // tampilkan semua yang belum bongkar
                    //     if (diff <= 30) {

                    //         notif.push({
                    //             shipment: shipment,
                    //             tujuan: tujuan,
                    //             estimasi: estimasiText,
                    //             diff: diff
                    //         });

                    //         console.log('MASUK NOTIF');
                    //     }

                    // }

                    // console.log('TOTAL NOTIF:', notif.length);
                    // console.log('NOTIF AKHIR:', notif);

                    // renderToastNotif();
function generateNotif(showPopup = true) {  
    notif = [];

    table.rows().every(function () {

        let row = $(this.node());
        let tujuan = row.find('td:eq(5)').text().trim();
        let shipment = row.data('shipment');
        let estimasi = row.find('.estimasi-tiba').data('estimasi');
        let tanggalTiba = row.find('[name="tanggal_tiba"]').val();

        if (!shipment || !estimasi) return;
        if (tanggalTiba && tanggalTiba.trim() !== '') {
    return;
}

        let estDate = new Date(estimasi + 'T00:00:00');
        let today = new Date();
        today.setHours(0,0,0,0);

        let diff = Math.ceil((estDate - today) / (1000 * 60 * 60 * 24));

        if (diff >= 0 && diff <= 10) {
            notif.push({
                shipment,
                tujuan,
                estimasi,
                diff
            });
        }
    });

    renderNotifList("", showPopup);
}
table.on('draw', function () {
    generateNotif(false);
});

let notifShown = false;

function renderNotifList(filter = "", showPopup = true) {

    let html = "";

    notif
        .filter(i =>
            String(i.shipment || '')
                .toLowerCase()
                .includes(String(filter || '').toLowerCase())
        )
        .forEach(i => {

      html += `
<div class="notif-item"
    data-shipment="${i.shipment}"
    style="
        padding:12px 14px;
        border-radius:10px;
        margin-bottom:10px;
        cursor:pointer;
        background: linear-gradient(135deg, #111827, #1f2937);
        border: 1px solid rgba(255,255,255,0.06);
        transition: all 0.2s ease;
    ">

    <div style="display:flex; justify-content:space-between; align-items:center;">
        <b style="color:#ffffff; font-size:14px; letter-spacing:0.3px;">
            🚚 ${i.shipment}
        </b>

        <span style="
            font-size:11px;
            padding:4px 8px;
            border-radius:999px;
            background:${i.diff <= 1 ? '#dc2626' : '#f59e0b'};
            color:white;
            font-weight:600;
        ">
            H-${i.diff}
        </span>
    </div>

    <div style="
        margin-top:6px;
        font-size:12px;
        color:#cbd5e1;
    ">
        📦 Estimasi: <b style="color:#fff">${i.estimasi}</b>
    </div>
    
    
<div style="
    margin-top:4px;
    font-size:12px;
    color:#cbd5e1;
">
    📍 Tujuan: <b style="color:#fff">${i.tujuan}</b>
</div>

    <div style="
        margin-top:4px;
        font-size:11px;
        color:#94a3b8;
    ">
        Klik untuk lihat detail shipment →
    </div>
</div>
`;
        });

    $('#notifContent').html(html);

   if (notif.length > 0 && showPopup && !notifShown) {

    notifShown = true;

    $('#notifBox').stop(true,true).fadeIn();

    notifTimer = setTimeout(() => {

        $('#notifBox').fadeOut(300);

        notifShown = false; // <-- reset

    }, 5000);
}
}
$(document).on('click', '.notif-item', function () {

    $('#notifBox').fadeOut(200);

    let shipment = String($(this).data('shipment') || '');

    table.search('').columns().search('');

    table.column(4).search('^' + shipment + '$', true, false).draw();

});
$(document).ready(function () {
    generateNotif();

    setTimeout(() => {
        generateNotif();
    }, 1000);
});

function renderNotifLsist(filter = "") {
    let html = "";

    notif
        .filter(i => i.shipment.toLowerCase().includes(filter.toLowerCase()))
        .forEach(i => {

            html += `
            <div class="notif-item" data-shipment="${i.shipment}">
                <b>${i.shipment}</b><br>
                Estimasi: ${i.estimasi}<br>
                <small>H-${i.diff}</small>
            </div>
        `;
        });

    $('#notifContent').html(html);
}

$('#notifSearch').on('keyup', function () {
    renderNotisfList($(this).val());
});

$(document).ready(function () {
    generateNotif();

    // optional refresh tiap 1 menit
    setInterval(generateNotif, 60000);
});

//                     function generateNotifEstimasi() {

//                         console.log('=== GENERATE NOTIF ===');

//                         notif = [];

//                         console.log(
//                             'HTML Row:',
//                             $('#tableMonitoring tbody tr').length
//                         );

//                         console.log(
//                             'DataTable Row:',
//                             table.rows().count()
//                         );

//                         table.rows().every(function() {

//                             let row = $(this.node());

//                             let shipment = row.attr('data-shipment');

//                             let tanggalKeluar = row.attr('data-keluar');

//                             if (!tanggalKeluar) {
//                                 return;
//                             }

//                             let today = new Date();
//                             today.setHours(0, 0, 0, 0);

//                             let keluarDate = new Date(
//                                 tanggalKeluar + 'T00:00:00'
//                             );

//                             keluarDate.setHours(0, 0, 0, 0);

//                             let umurKeluar = Math.floor(
//                                 (today - keluarDate) /
//                                 (1000 * 60 * 60 * 24)
//                             );

//                             // hanya shipment yg keluar <= 30 hari
//                             if (umurKeluar > 30) {
//                                 return;
//                             }

//                             console.log('Shipment:', shipment);

//                             let el = row.find('.estimasi-tiba');

//                             let estimasiText = el.data('estimasi');

//                             if (!estimasiText) {
//                                 return;
//                             }

//                             let tujuan = row.find('td:eq(5)').text().trim();

//                             let tanggalBongkar =
//                                 row.find('[name="tanggal_bongkar"]').val();

//                             if (tanggalBongkar) {
//                                 return;
//                             }

//                             let estDate = new Date(
//                                 estimasiText + 'T00:00:00'
//                             );

//                             estDate.setHours(0, 0, 0, 0);

//                             let diff = Math.floor(
//                                 (estDate - today) /
//                                 (1000 * 60 * 60 * 24)
//                             );

//                             console.log('Diff:', diff);

//                             if (diff <= 30) {

//                                 notif.push({
//                                     shipment,
//                                     tujuan,
//                                     estimasi: estimasiText,
//                                     diff
//                                 });

//                                 console.log('MASUK NOTIF');
//                             }

//                         });

//                         console.log('TOTAL NOTIF:', notif.length);
//                         console.log('NOTIF AKHIR:', notif);

//                         renderToastNotif();
//                     }

//                     function renderToastNotif() {

//                         $('#toastContainer').html('');

//                         notif.forEach(function(item) {

//                             let cls = '';
//                             let icon = '📦';
//                             let title = 'FOLLOW UP';

//                             if (item.diff <= 7) {
//                                 cls = 'urgent';
//                                 icon = '🚨';
//                                 title = 'URGENT';
//                             }

//                             if (item.diff === 1) {
//                                 cls = 'h1';
//                                 icon = '🔥';
//                                 title = 'H-1 ALERT';
//                             }

//                             let toast = $(`
//             <div class="notif-toast ${cls}"
//                  data-shipment="${item.shipment}">

//                 <div class="notif-top">
//                     <div class="notif-icon">${icon}</div>

//                     <div>
//                         <div class="notif-title">${title}</div>
//                         <div class="notif-sub">Klik untuk buka shipment</div>
//                     </div>
//                 </div>

//                 <div class="notif-body">
//                     <div>Shipment : <b>${item.shipment}</b></div>
//                     <div>Tujuan : ${item.tujuan}</div>
//                     <div>ETA : ${item.estimasi} (H-${item.diff})</div>
//                 </div>

//                 <div class="notif-progress"></div>

//             </div>
//         `);

//                             $('#toastContainer').append(toast);

//                             setTimeout(() => {
//                                 toast.fadeOut(500, function() {
//                                     $(this).remove();
//                                 });
//                             }, 5000);

//                         });
//                     }
//                     console.log('TOTAL NOTIF:', notif.length);
//                     // HAPUS DUA FUNGSI CLICK LAMA KAMU, GANTI DENGAN INI:
//               $(document).on('click', '.notif-toast', function () {

//     let shipment = $(this).data('shipment');
//     if (!shipment) return;

//     table.search(shipment).draw();

//     setTimeout(() => {

//         let targetRow = $('tr[data-shipment="' + shipment + '"]');

//         if (!targetRow.length) return;

//         $('.highlight-row').removeClass('highlight-row');
//         targetRow.addClass('highlight-row');

//         $('html, body').animate({
//             scrollTop: targetRow.offset().top - 150
//         }, 500);

//     }, 300);
// });



//                     $(document).ready(function() {

//                         generateNotifEstimasi();

//                         // refresh real-time
//                         setInterval(function() {
//                             generateNotifEstimasi();
//                         }, 60000);

//                     });



                    //  let notif = [];



                    //                     let notif = [];

                    // $('.estimasi-tiba').each(function() {

                    //     let shipment = $(this).data('shipment');
                    //     let tujuan = $(this).data('tujuan');
                    //     let estimasi = $(this).data('estimasi');

                    //     if (!estimasi) return;

                    //     let row = $(this).closest('tr');

                    //     let tanggalTiba = row.find('[name="tanggal_tiba"]').val();
                    //     let tanggalBongkar = row.find('[name="tanggal_bongkar"]').val();

                    //     if (tanggalBongkar) return;

                    //     let estDate = new Date(estimasi + "T00:00:00");
                    //     let today = new Date();

                    //     estDate.setHours(0, 0, 0, 0);
                    //     today.setHours(0, 0, 0, 0);

                    //     let diff = Math.floor(
                    //         (estDate - today) / (1000 * 60 * 60 * 24)
                    //     );

                    //     if (diff <= 30 && diff >= 0) {

                    //         notif.push({
                    //             shipment,
                    //             tujuan,
                    //             estimasi,
                    //             diff
                    //         });

                    //     }

                    // });


                    // // =========================
                    // // TOAST POPUP (KANAN ATAS)
                    // // =========================

                    // if (notif.length > 0) {

                    //     notif.forEach(item => {

                    //         let color = "#f59e0b";
                    //         let label = "⚠ FOLLOW UP";

                    //         if (item.diff <= 7) {
                    //             color = "#ef4444";
                    //             label = "🚨 URGENT";
                    //         }

                    //         if (item.diff === 1) {
                    //             label = "🔥 H-1 ALERT";
                    //         }

                    //         let html = `
                    // <div class="toast toast-shipment"
                    //      data-shipment="${item.shipment}"
                    //      style="border-left:5px solid ${color};cursor:pointer">

                    //     <strong>${label}</strong>
                    //     No Shipment: ${item.shipment}<br>
                    //     <small>Estimasi: ${item.estimasi} (H-${item.diff})</small>

                    //     <div style="margin-top:5px;color:#60a5fa">
                    //         Klik untuk buka data
                    //     </div>
                    // </div>
                    // `;

                    //         $('#toastContainer').append(html);

                    //     });

                    //     setTimeout(() => {

                    //         $('.toast').fadeOut(300, function() {
                    //             $(this).remove();
                    //         });

                    //     }, 8000);
                    // }


                    // // =====================================
                    // // KLIK TOAST → SCROLL KE ROW
                    // // =====================================

                    // $(document).on('click', '.toast-shipment', function() {

                    //     let shipment = $(this).data('shipment');

                    //     let targetRow = $('tr[data-shipment="' + shipment + '"]');

                    //     if (targetRow.length) {

                    //         $('html, body').animate({
                    //             scrollTop: targetRow.offset().top - 150
                    //         }, 600);

                    //         $('.highlight-row').removeClass('highlight-row');

                    //         targetRow.addClass('highlight-row');

                    //         setTimeout(() => {
                    //             targetRow.removeClass('highlight-row');
                    //         }, 5000);

                    //     }

                    // });


                    // =====================================
                    // KLIK TOAST → SEARCH DATATABLE
                    // =====================================




                    // =====================================
                    // PANEL LIST NOTIF
                    // =====================================

                    // function renderToastNotif() {

                    //     $('#toastContainer').html('');

                    //     if (notif.length === 0) {
                    //         return;
                    //     }

                    //     notif.forEach(item => {

                    //         let color = "#f59e0b";
                    //         let label = "⚠ FOLLOW UP";

                    //         if (item.diff <= 7) {
                    //             color = "#ef4444";
                    //             label = "🚨 URGENT";
                    //         }

                    //         if (item.diff === 1) {
                    //             color = "#dc2626";
                    //             label = "🔥 H-1 ALERT";
                    //         }

                    //         let html = `
                    //             <div class="toast toast-shipment"
                    //                  data-shipment="${item.shipment}"
                    //                  style="border-left:5px solid ${color};cursor:pointer">

                    //                 <strong>${label}</strong>

                    //                 Shipment :
                    //                 ${item.shipment}<br>

                    //                 Tujuan :
                    //                 ${item.tujuan}<br>

                    //                 <small>
                    //                     ETA : ${item.estimasi}
                    //                     (H-${item.diff})
                    //                 </small>

                    //             </div>
                    //         `;

                    //         $('#toastContainer').append(html);
                    //     });

                    // }

                    // if (notif.length > 0) {

                    //     let html = "";

                    //     notif.forEach((item, i) => {
                    //         html += `
                    //             <div style="margin-bottom:10px;padding-bottom:10px;border-bottom:1px solid #374151;">
                    //                 ${i+1}. ${item.replace(/\n/g, "<br>")}
                    //             </div>
                    //         `;
                    //     });

                    //     $('#notifContent').html(html);
                    //     $('#notifBox').fadeIn();

                    // }
                    // setTimeout(() => {
                    //     $('#notifBox').fadeOut();
                    // }, 10000);

                });

                // function saveRow(id) {

                //     let row = $('tr[data-id="' + id + '"]');

                //     let data = {

                //         pic_monitoring: row.find('[name="pic_monitoring"]').val(),
                //         status_kendaraan: row.find('[name="status_kendaraan"]').val(),
                //         action_required: row.find('[name="action_required"]').val(),
                //         act_urutan_bongkar: row.find('[name="act_urutan_bongkar"]').val(),
                //         tanggal_tiba: row.find('[name="tanggal_tiba"]').val(),
                //         tanggal_bongkar: row.find('[name="tanggal_bongkar"]').val(),
                //         // monitoring_alert: row.find('[name="monitoring_alert"]').val(),
                //         reason_tiba: row.find('[name="reason_tiba"]').val(),
                //         reason_bongkar: row.find('[name="reason_bongkar"]').val(),
                //         remarks: row.find('[name="remarks"]').val(),
                //         act_pgi_date: row.find('[name="act_pgi_date"]').val(),
                //         created_by: row.find('[name="created_by"]').val(),
                //         total_do_qty_car: row.find('[name="total_do_qty_car"]').val()

                //     };

                //     $.ajax({

                //         url: '/spvmonitoring/update/' + id,

                //         type: 'POST',

                //         data: {
                //             _token: '{{ csrf_token() }}',
                //             _method: 'PUT',
                //             ...data
                //         },

                //         beforeSend: function() {

                //             row.find('.save-btn')
                //                 .prop('disabled', true)
                //                 .text('Saving...');

                //         },

                //         success: function() {

                //             row.find('.save-btn')
                //                 .prop('disabled', false)
                //                 .text('SAVE');

                //             alert('✅ Data berhasil disimpan');

                //             location.reload();

                //         },

                //         error: function(xhr) {

                //             row.find('.save-btn')
                //                 .prop('disabled', false)
                //                 .text('SAVE');

                //             console.log(xhr.responseText);

                //             alert('❌ Gagal menyimpan data');

                //         }

                //     });

                // }

                // let notif = [];

                // function generateNotifEstimasi() {

                //     notif = [];

                //     $('#tableMonitoring tbody tr').each(function () {

                //         let row = $(this);

                //         let shipment = row.data('shipment');

                //         let tujuan = row.find('td:eq(5)').text().trim();

                //         let estimasiText = row.find('.estimasi-tiba').data('estimasi');

                //         if (!estimasiText) return;

                //         let tanggalBongkar = row.find('[name="tanggal_bongkar"]').val();

                //         if (tanggalBongkar) return;

                //         let estDate = new Date(estimasiText);
                //         let today = new Date();

                //         estDate.setHours(0,0,0,0);
                //         today.setHours(0,0,0,0);

                //         let diff = Math.floor(
                //             (estDate - today) / 86400000
                //         );

                //         if(diff >= 0 && diff <= 30){

                //             notif.push({
                //                 shipment,
                //                 tujuan,
                //                 estimasi: estimasiText,
                //                 diff
                //             });

                //         }

                //     });

                //     renderToastNotif();
                // }

                // function renderToastNotif() {

                //     $('#toastContainer').html('');

                //     notif.forEach(item => {

                //         let color = '#f59e0b';
                //         let label = '⚠ FOLLOW UP';

                //         if(item.diff <= 7){
                //             color = '#ef4444';
                //             label = '🚨 URGENT';
                //         }

                //         if(item.diff === 1){
                //             label = '🔥 H-1 ALERT';
                //         }

                //         $('#toastContainer').append(`
                //             <div class="toast toast-shipment"
                //                  data-shipment="${item.shipment}"
                //                  style="border-left:5px solid ${color};cursor:pointer">

                //                 <strong>${label}</strong>
                //                 No Shipment: ${item.shipment}<br>
                //                 <small>Estimasi: ${item.estimasi} (H-${item.diff})</small>

                //             </div>
                //         `);

                //     });

                // }
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
                            created_by: row.find('[name="created_by"]').val(),
                            total_do_qty_car: row.find('[name="total_do_qty_car"]').val()
                        },

                        beforeSend: function() {

                            row.find('.save-btn')
                                .prop('disabled', true)
                                .text('Saving...');

                            row.find('.save-status')
                                .html('⏳ Saving...');

                        },

                        success: function(response) {

                            console.log(response);

                            row.find('.save-btn')
                                .prop('disabled', false)
                                .text('SAVE');

                            row.find('.save-status')
                                .html('✅ Saved');

                            setTimeout(function() {
                                row.find('.save-status').html('');
                            }, 2000);

                        },

                        error: function(xhr) {

                            row.find('.save-btn')
                                .prop('disabled', false)
                                .text('SAVE');

                            row.find('.save-status')
                                .html('❌ Error');

                            console.log(xhr.responseJSON);
                            console.log(xhr.responseText);

                        }

                    });

                }

                /*
                |--------------------------------------------------------------------------
                | AUTO SAVE
                |--------------------------------------------------------------------------
                */
                let saveTimer;

                $(document).on(
                    'change',
                    '#tableMonitoring input, #tableMonitoring select',
                    function() {

                        let row = $(this).closest('tr');
                        let id = row.data('id');

                        console.log('AUTO SAVE:', id);

                        clearTimeout(saveTimer);

                        saveTimer = setTimeout(function() {
                            saveRow(id);
                        }, 500);

                    }
                );

                /*
                |--------------------------------------------------------------------------
                | SELECT2
                |--------------------------------------------------------------------------
                */
                $(document).ready(function() {

                    // FILTER DI ATAS TABLE
                    $('.filter-box .searchable').select2({
                        width: '180px'
                    });

                    // SELECT DALAM MODAL
                    $('#shipModal .searchable').select2({
                        width: '100%',
                        dropdownParent: $('#shipModal')
                    });

                });

                // function generateNotifEstimasi() {

                //     console.log('MASUK FUNCTION');

                //     notif = [];

                //     console.log(
                //         'Jumlah Row:',
                //         $('#tableMonitoring tbody tr').length
                //     );

                //     $('#tableMonitoring tbody tr').each(function () {

                //         let row = $(this);

                //         console.log(
                //             'Shipment:',
                //             row.attr('data-shipment')
                //         );

                //     });

                // }

                // $(document).ready(function () {

                //     generateNotifEstimasi();

                // });
                function updateDateColor() {

                    $('#tableMonitoring input[type="date"], #tableMonitoring input[type="datetime-local"]').each(function() {

                        if ($(this).val() && $(this).val().trim() !== '') {

                            $(this)
                                .removeClass('input-empty')
                                .addClass('input-filled');

                        } else {

                            $(this)
                                .removeClass('input-filled')
                                .addClass('input-empty');
                        }

                    });
                }

                // pertama kali load
                $(document).ready(function() {
                    updateDateColor();
                });

                // saat user ubah tanggal
                $(document).on('change',
                    '#tableMonitoring input[type="date"], #tableMonitoring input[type="datetime-local"]',
                    function() {
                        updateDateColor();
                    }
                );


                $(document).ready(function() {

                    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {

                        let filterDate = $('#filterKeluarGudangTgl').val();

                        let row = table.row(dataIndex).node();
                        let tanggalKeluar = $(row).attr('data-keluar');

                        if (!filterDate) return true;
                        if (!tanggalKeluar) return false;

                        return tanggalKeluar === filterDate;
                    });

                    $('#filterKeluarGudangTgl').on('change', function() {
                        table.draw();
                    });

                });

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