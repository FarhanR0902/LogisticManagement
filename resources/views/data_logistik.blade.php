<!DOCTYPE html>
    <html lang="id">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>DATA LOGISTIK</title>

        <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

        <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
        <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

        <style>
            body {
                font-family: 'Segoe UI', Arial, sans-serif;
                background: #f1f5f9;
                margin: 0;
                color: #1e293b;
                font-size: 16px;
            }

            /* ================= CONTAINER ================= */

            .container {
                margin-left: 260px;
                padding: 30px;
            }

            h2 {
                font-size: 34px;
                font-weight: 700;
                margin-bottom: 20px;
                color: #0f172a;
            }

            /* ================= CARD ================= */

            .card {
                background: #fff;
                padding: 20px;
                border-radius: 16px;
                overflow: auto;
                box-shadow: 0 4px 20px rgba(0, 0, 0, .08);
            }

            /* ================= TABLE ================= */

            table {
                width: 100%;
                border-collapse: collapse;
                font-size: 16px;
            }

            th {
                background: #03c03c;
                color: #fff;
                padding: 16px 14px;
                white-space: nowrap;
                font-size: 16px;
                font-weight: 600;
                text-align: center;
                border: 1px solid #dbeafe;
            }

            td {
                padding: 14px 12px;
                border: 1px solid #e2e8f0;
                white-space: nowrap;
                text-align: left;
                vertical-align: middle;
                font-size: 16px;
            }

            /* Zebra */

            tbody tr:nth-child(even) {
                background: #f8fafc;
            }

            tbody tr:hover {
                background: #dbeafe;
                transition: .2s;
            }

            /* ================= FILTER ================= */

            .filter-box {
                background: #fff;
                padding: 20px;
                border-radius: 16px;
                box-shadow: 0 4px 16px rgba(0, 0, 0, .08);
                margin-bottom: 20px;
            }

            .filter-box form {
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
                align-items: center;
            }

            .filter-box input,
            .filter-box select {
                padding: 13px 16px;
                border: 1px solid #cbd5e1;
                border-radius: 10px;
                font-size: 16px;
                min-width: 190px;
                outline: none;
            }

            .filter-box input:focus,
            .filter-box select:focus {
                border-color: #3b82f6;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, .15);
            }

            .filter-box button {
                padding: 13px 20px;
                background: #22c55e;
                color: white;
                border: none;
                border-radius: 10px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
            }

            .filter-box button:hover {
                background: #16a34a;
            }

            .filter-box a {
                padding: 13px 20px;
                background: #ef4444;
                color: white;
                border-radius: 10px;
                text-decoration: none;
                font-size: 16px;
                font-weight: 600;
            }

            .filter-box a:hover {
                background: #dc2626;
            }

            /* ================= IMPORT BOX ================= */

            .import-box {
                background: #fff;
                padding: 20px;
                border-radius: 16px;
                box-shadow: 0 4px 16px rgba(0, 0, 0, .08);
                margin-bottom: 20px;
            }

            .import-box form {
                display: flex;
                gap: 12px;
                align-items: center;
                flex-wrap: wrap;
            }

            .import-box input[type=file] {
                padding: 13px;
                border: 2px dashed #cbd5e1;
                border-radius: 10px;
                background: #f8fafc;
                font-size: 16px;
            }

            .import-box input[type=file]:hover {
                border-color: #2563eb;
            }

            .import-box button {
                padding: 13px 20px;
                border: none;
                border-radius: 10px;
                background: linear-gradient(135deg, #3b82f6, #2563eb);
                color: white;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
            }

            .import-box button:hover {
                transform: translateY(-2px);
            }

            /* ================= ARCHIVE BUTTON ================= */

            .archive-form {
                margin: 20px 0;
            }

            .archive-btn {
                background: linear-gradient(135deg, #2563eb, #1d4ed8);
                color: white;
                border: none;
                padding: 15px 26px;
                border-radius: 12px;
                font-size: 17px;
                font-weight: 600;
                cursor: pointer;
                transition: .3s;
            }

            .archive-btn:hover {
                transform: translateY(-2px);
            }

            /* ================= BADGE ================= */

            .badge {
                padding: 9px 16px;
                border-radius: 20px;
                color: white;
                font-size: 15px;
                font-weight: 600;
                display: inline-block;
            }

            .green {
                background: #22c55e;
            }

            .red {
                background: #ef4444;
            }

            .gray {
                background: #64748b;
            }

            .orange {
                background: #f97316;
            }

            .blue {
                background: #2563eb;
            }

            .yellow {
                background: #facc15;
                color: #000;
            }

            .badge-mp {
                background: #16a34a;
            }

            .badge-cmd {
                background: #2563eb;
            }

            .badge-jess {
                background: #f59e0b;
            }

            .badge-default {
                background: #64748b;
            }

            .badge-green {
                background: #22c55e;
            }

            .badge-red {
                background: #ef4444;
            }

            .badge-gray {
                background: #64748b;
            }

            .badge-orange {
                background: #f97316;
            }

            .bg-success {
                background: #22c55e !important;
            }

            .bg-warning {
                background: #ef4444 !important;
                color: #ffffff !important;
            }

            .bg-secondary {
                background: #64748b !important;
            }

            /* ================= SMALL COLUMN ================= */

            .col-small {
                width: 110px !important;
                min-width: 110px !important;
                max-width: 130px !important;
                font-size: 15px;
                text-align: center;
            }

            /* ================= DATATABLE ================= */

            .dataTables_wrapper {
                font-size: 16px;
            }

            .dataTables_filter input {
                padding: 9px 14px !important;
                border-radius: 8px !important;
                font-size: 16px !important;
            }

            .dataTables_length select {
                padding: 7px 12px !important;
                font-size: 16px !important;
            }

            /* ================= RESPONSIVE ================= */

            @media(max-width:768px) {

                .container {
                    margin-left: 0;
                    padding: 15px;
                }

                h2 {
                    font-size: 26px;
                }

                table {
                    font-size: 15px;
                }

                th {
                    font-size: 15px;
                    padding: 12px;
                }

                td {
                    font-size: 15px;
                    padding: 10px;
                }

                .filter-box form,
                .import-box form {
                    flex-direction: column;
                    align-items: stretch;
                }

                .filter-box input,
                .filter-box select,
                .filter-box button,
                .import-box button {
                    width: 100%;
                }
            }

            .badge {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 9px 16px;
                border-radius: 999px;
                font-size: 15px;
                font-weight: 700;
                color: #fff;
                letter-spacing: .3px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, .15);
            }

            /* Hijau */
            .badge-success {
                background: linear-gradient(135deg, #16a34a, #22c55e);
            }

            /* Kuning */
            .badge-warning {
                background: linear-gradient(135deg, #f59e0b, #fbbf24);
                color: #222;
            }

            /* Biru */
            .badge-info {
                background: linear-gradient(135deg, #2563eb, #3b82f6);
            }

            /* Merah */
            .badge-danger {
                background: linear-gradient(135deg, #dc2626, #ef4444);
            }

            /* Abu */
            .badge-secondary {
                background: linear-gradient(135deg, #00e5ff, #9ca3af);
            }
        </style>
    </head>

    <body>

        @include('template.sidebar')

        <div class="container">

            <h2>📦 DATA LOGISTIK</h2>

        

    <a href="#" id="btnExportExcel" class="btn-export">
    <i class="fa fa-file-excel"></i>
    Export Excel
</a>

            <!-- FILTER -->


            <!-- HAPUS SEMUA -->
        \
            <style>
                .archive-form {
                    margin: 20px 0;
                }

                .badge-green {
                    background: #22c55e;
                }

                .badge-red {
                    background: #ef4444;
                }

                .badge-gray {
                    background: #6b7280;
                }

                .badge-orange {
                    background: #f97316;
                }

                .archive-btn {
                    background: linear-gradient(135deg, #2563eb, #1d4ed8);
                    color: white;
                    border: none;
                    padding: 13px 24px;
                    border-radius: 12px;
                    font-size: 16px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: 0.3s ease;
                    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                }

                .archive-btn:hover {
                    transform: translateY(-2px);
                    background: linear-gradient(135deg, #1d4ed8, #1e40af);
                    box-shadow: 0 8px 18px rgba(37, 99, 235, 0.35);
                }

                .archive-btn:active {
                    transform: scale(0.98);
                }

                .badge.gray {
                    background: #9ca3af;
                }

                .badge.blue {
                    background: #3b82f6;
                }

                .badge.yellow {
                    background: #facc15;
                    color: #000;
                }

                .badge.orange {
                    background: #f97316;
                }

                .badge.success {
                    background: #028e31;
                }

                /* ================= DATATABLES XL ================= */

                .dataTables_wrapper {
                    font-size: 17px !important;
                }

                .dataTables_wrapper .dataTables_length,
                .dataTables_wrapper .dataTables_filter,
                .dataTables_wrapper .dataTables_info,
                .dataTables_wrapper .dataTables_paginate {
                    font-size: 17px !important;
                    font-weight: 500;
                }

                /* Show entries */
                .dataTables_length select {
                    font-size: 17px !important;
                    padding: 11px 16px !important;
                    min-width: 100px;
                    height: 46px;
                }

                /* Search box */
                .dataTables_filter input {
                    font-size: 17px !important;
                    padding: 11px 16px !important;
                    min-width: 300px;
                    height: 46px;
                    border-radius: 10px !important;
                }

                /* Info text */
                .dataTables_info {
                    font-size: 17px !important;
                    padding-top: 16px !important;
                }

                /* Pagination */
                .dataTables_paginate .paginate_button {
                    font-size: 16px !important;
                    padding: 9px 16px !important;
                    margin: 0 3px !important;
                    border-radius: 8px !important;
                }

                /* Table font lebih besar */
                #tableLogistik td {
                    font-size: 16px !important;
                }

                #tableLogistik th {
                    font-size: 16px !important;
                }

                /* Badge ikut membesar */
                .badge,
                .badge-status {
                    font-size: 15px !important;
                    padding: 9px 14px !important;
                }

                /* Responsive */
                @media (min-width: 1600px) {

                    .dataTables_wrapper {
                        font-size: 18px !important;
                    }

                    #tableLogistik td {
                        font-size: 17px !important;
                    }

                    #tableLogistik th {
                        font-size: 17px !important;
                    }

                    .dataTables_filter input {
                        min-width: 360px;
                    }
                }

                .badge-green {
                    background: #22c55e;
                    color: white;
                }

                .badge-blue {
                    background: #3b82f6;
                    color: white;
                }

                .badge-orange {
                    background: #f97316;
                    color: white;
                }

                .badge-red {
                    background: #ef4444;
                    color: white;
                }

                .badge-purple {
                    background: #8b5cf6;
                    color: white;
                }

                .badge-pink {
                    background: #ec4899;
                    color: white;
                }

                .badge-cyan {
                    background: #06b6d4;
                    color: white;
                }

                .badge-yellow {
                    background: #eab308;
                    color: black;
                }

                .badge-status {
                    padding: 9px 14px;
                    border-radius: 20px;
                    font-size: 15px;
                    font-weight: 600;
                    display: inline-block;
                }

                .status-belum {
                    background: #ef4444;
                    color: white;
                }

                .status-perjalanan {
                    background: #f59e0b;
                    color: white;
                }

                .status-sudah {
                    background: #22c55e;
                    color: white;
                }

                .status-default {
                    background: #64748b;
                    color: white;
                }

                .status-badge {
                    display: inline-block;
                    padding: 9px 16px;
                    border-radius: 20px;
                    font-size: 15px;
                    font-weight: 700;
                    text-align: center;
                    line-height: 1.3;
                    min-width: 180px;
                    color: #fff;
                }

                .status-transit {
                    background: #2563eb;
                    /* Biru */
                }

                .status-unloading {
                    background: #f59e0b;
                    /* Orange */
                    color: #fff;
                }

                .status-ontime {
                    background: #16a34a;
                    /* Hijau */
                }

                .status-delay {
                    background: #dc2626;
                    /* Merah */
                }

                .status-ontime {
                    background: linear-gradient(135deg, #16a34a, #22c55e);
                }

                .status-delay {
                    background: linear-gradient(135deg, #dc2626, #ef4444);
                }

                .status-wait {
                    background: linear-gradient(135deg, #6b7280, #9ca3af);
                }

                .btn-export {
    display: inline-flex;
    align-items: center;
    gap: 8px;

    padding: 12px 20px;

    background: #198754;
    color: #fff !important;

    border: none;
    border-radius: 8px;

    font-size: 16px;
    font-weight: 600;

    text-decoration: none;
    cursor: pointer;

    transition: all .25s ease;
    box-shadow: 0 3px 10px rgba(25, 135, 84, .25);
}

.btn-export:hover {
    background: #157347;
    color: #fff !important;

    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(25, 135, 84, .35);
}

.btn-export:active {
    transform: scale(.98);
}

.btn-export i {
    font-size: 18px;
}
            </style>

         <div class="filter-box">

<form id="filterForm" method="GET" action="{{ url('/datalogistik') }}">

    <select id="filterArea" name="area" onchange="this.form.submit()">
        <option value="">Semua Area</option>
        @foreach($areaList as $a)
            <option value="{{ $a }}" {{ request('area') == $a ? 'selected' : '' }}>
                {{ $a }}
            </option>
        @endforeach
    </select>

    <input
        type="date"
        id="filterDate"
        name="date"
        value="{{ request('date') }}"
        onchange="this.form.submit()"
    >

    <select id="filterMonth" name="month" onchange="this.form.submit()">
        <option value="">Semua Bulan</option>
        @for($i=1;$i<=12;$i++)
            <option value="{{ $i }}" {{ request('month') == $i ? 'selected' : '' }}>
                {{ $i }}
            </option>
        @endfor
    </select>

    <select id="filterYear" name="year" onchange="this.form.submit()">
        <option value="">Semua Tahun</option>

        @php
            $startYear = 2023;
            $endYear = date('Y') + 1;
        @endphp

        @for($i=$startYear;$i<=$endYear;$i++)
            <option value="{{ $i }}" {{ request('year') == $i ? 'selected' : '' }}>
                {{ $i }}
            </option>
        @endfor
    </select>

</form>

</div>
            <table id="tableLogistik" class="display nowrap">

                <thead>
                    <tr>

                        <th>Tanggal Naik Logistik</th>
                        <th>Rencana Kirim</th>
                        <th class="col-small">Lead Time</th>
                            <th>Nama Driver</th>
                                <th>No Pol</th>
                        <th>Planner</th>
                        <th>No Shipment</th>
                        <th>Update Posisi Mobil</th>
                        <th>Dist Channel</th>
                        <th>Tujuan</th>
                        <th>Area</th>
                        <th>Ketersediaan Unit</th>
                        <th>Mobil</th>
                        <th>Delivery Quantity</th>
                        <!-- <th>Perubahan Mobil</th> -->
                        <th>Nilai Muatan</th>
                        <th>Biaya Kirim</th>
                        <th>CR</th>
                        <th>Kategori Ekspedisi</th>
                        <th>Ekspedisi</th>

                        <th>Tanggal Dapat Unit</th>



                        <th>Lama Waktu Pencarian</th>
                        <th>SLA Dapat Mobil</th>
                        <th>Planning Loading KACS</th>
                        <th>Tanggal Tiba KACS</th>
                        <th>Tanggal Keluar KACS</th>
                        <th>Lama Di KACS</th>
                        <th>Status KACS</th>
                        <th>SLA Loading</th>
                        <th>Planning Loading Sentul</th>
                        <th>Tanggal Tiba Sentul</th>
                        <!-- <th>Planning Loading 2</th> -->
                        <th>Tanggal Keluar Sentul</th>
                        <th>Lama Di Sentul</th>
                        <th>SLA Loading Sentul</th>
                        <th>Status Sentul</th>
                        <th>Planning Loading CCIE</th>

                        <th>Tanggal Tiba CCIE</th>
                        <!-- <th>Planning Loading 3</th> -->
                        <th>Tanggal Keluar CCIE</th>
                        <th>Lama Di CCIE</th>
                        <th>SLA Loading CCIE</th>
                        <th>Status CCIE</th>




                        <th>PIC Monitoring</th>
                        <th>Nama Kapal</th>
                        <th>ETD</th>
                        <th>ETA</th>
                        <th>Monitoring Alert</th>
                        <th>Alert</th>


                        <th class="col-small">Urutan Bongkar</th>

                        <th>Actual Delivery Quantity</th>
                        <th>Selsih quantity</th>
                        <th>Reason Selisih Quantity</th>
                        <th>Act PGI Date</th>

                        <!-- <th>Created By</th> -->
                        <th>ATD</th>
                        <th>ATA</th>
                        <th>Tanggal Estimasi</th>
                        <th>Tanggal Tiba</th>
                        <th>Lama Perjalanan</th>

                        <th>SLA Tiba</th>
                        <th>Tanggal Bongkar</th>
                        <th>Overstay</th>
                        <th>SLA Bongkar</th>
                        <th>Reason Tiba</th>
                        <th>Reason Bongkar</th>
                        <th>Status Akhir</th>

                        <th>Status Alert</th>

                        <th>Remarks</th>
                        <th>Route</th>
                        <th>Shipping Point</th>
                        <th>Pulau</th>
                        <th>Via Kirim</th>


                    </tr>
                </thead>

                <tbody>
                    @php
                    function badgeSLA($sla)
                    {
                    $sla = trim((string)$sla);

                    if ($sla === '' || $sla === '-' || $sla === 'null') {
                    return '<span class="badge badge-gray">-</span>';
                    }

                    $slaLower = strtolower($sla);

                    // Sesuai SLA / On Time
                    if (
                    in_array($slaLower, [
                    'sesuai sla',
                    'on time',
                    'ontime',
                    'h+0'
                    ])
                    ) {
                    return '<span class="badge badge-green">'.$sla.'</span>';
                    }

                    // H+1
                    if (preg_match('/^h\+1$/i', $sla)) {
                    return '<span class="badge badge-orange">'.$sla.'</span>';
                    }

                    // H+2 dst
                    if (preg_match('/^h\+\d+$/i', $sla)) {
                    return '<span class="badge badge-red">'.$sla.'</span>';
                    }

                    return '<span class="badge badge-gray">'.$sla.'</span>';
                    }
                    @endphp
                    @foreach($logistik as $r)

                    @php

                    // ================= NORMALISASI TANGGAL =================

                    $datesKeluar = [
                    $r->tanggal_keluar_gudang,
                    $r->tanggal_keluar_gudang_2,
                    $r->tanggal_keluar_gudang_3,
                    ];

                    $keluar = collect($datesKeluar)
                    ->filter(function ($t) {
                    return !empty($t) && $t != 'mm/dd/yyyy';
                    })
                    ->map(function ($t) {
                    return strtotime($t);
                    })
                    ->max();

                    $tiba = (!empty($r->tanggal_tiba) && $r->tanggal_tiba != 'mm/dd/yyyy')
                    ? strtotime(date('Y-m-d', strtotime($r->tanggal_tiba)))
                    : null;

                    $bongkar = (!empty($r->tanggal_bongkar) && $r->tanggal_bongkar != 'mm/dd/yyyy')
                    ? strtotime(date('Y-m-d', strtotime($r->tanggal_bongkar)))
                    : null;

                    // ================= LEADTIME =================
                    $leadtime = is_numeric($r->transport_lead_time)
                    ? (int) $r->transport_lead_time
                    : 0;
                    // ================= ESTIMASI TIBA =================

                    $estimasi = $r->estimasi_tiba
                    ? strtotime($r->estimasi_tiba)
                    : null;

                    $estimasi_show = $estimasi
                    ? date('d-m-Y', $estimasi)
                    : '-';

                    // ================= LAMA PERJALANAN =================
                    $lama_perjalanan = ($keluar && $tiba)
                    ? max(0, ceil(($tiba - $keluar) / 86400))
                    : null;

                    // ================= SLA TIBA =================
                    $sla_tiba = '-';

                    if ($tiba && $estimasi) {
                    $sla_tiba = ($tiba
                    <= $estimasi) ? 'On Time' : 'Delay' ;
                        }

                        //=================OVERSTAY BONGKAR=================// RULE:
                        // 0 hari=ON TIME
                        //>0 hari = DELAY
                    $over_bongkar = null;

                    if ($tiba && $bongkar) {
                    $over_bongkar = max(0, ceil(($bongkar - $tiba) / 86400));
                    }

                    // ================= SLA BONGKAR =================
                    $sla_bongkar = '-';

                    if ($tiba && $bongkar) {
                    $sla_bongkar = ($over_bongkar <= 0)
                        ? 'On Time'
                        : 'Delay' ;
                        }

                        //=================ALERT ESTIMASI=================$alert='-' ;

                        /*
                        |--------------------------------------------------------------------------
                        | SUDAH DELIVER
                        |--------------------------------------------------------------------------
                        */
                        if ($r->sla_bongkar == 'Delay') {

                        $alert = 'DELAY DELIVERY';

                        } elseif ($r->sla_bongkar == 'On Time') {

                        $alert = 'AMAN';

                        }

                        /*
                        |--------------------------------------------------------------------------
                        | BELUM DELIVER
                        |--------------------------------------------------------------------------
                        */
                        elseif ($estimasi) {

                        $today = strtotime(date('Y-m-d'));
                        $selisih = ceil(($estimasi - $today) / 86400);

                        if ($selisih < 0) {

                            $alert='TERLAMBAT' ;

                            } elseif ($selisih <=2) {

                            $alert='WARNING H-2' ;

                            } else {

                            $alert='AMAN' ;

                            }
                            }
                            @endphp

                            <tr>


                            <td>{{ $r->tanggal_naik_logistik ? date('d-m-Y', strtotime($r->tanggal_naik_logistik)) : '-' }}</td>
                            <td>{{ $r->rencana_kirim ? date('d-m-Y', strtotime($r->rencana_kirim)) : '-' }}</td>
                            <td>{{ $r->transport_lead_time }}</td>
                                 <td>{{ $r->nama_driver }}</td>
                                      <td>{{ $r->no_pol }}</td>
                            <td>{{ $r->planner }}</td>
                            <td>{{ $r->no_shipment }}</td>
                            <td>
                                @php
                                $dpt = $r->tanggal_dpt_unit;

                                $tibaG1 = $r->tanggal_tiba_gudang;
                                $tibaG2 = $r->tanggal_tiba_gudang_2;
                                $tibaG3 = $r->tanggal_tiba_gudang_3;

                                // TRUE jika salah satu gudang sudah diisi
                                $tibaG = !empty($tibaG1) || !empty($tibaG2) || !empty($tibaG3);

                                $keluarG = !empty($r->tanggal_keluar_gudang)
                                || !empty($r->tanggal_keluar_gudang_2)
                                || !empty($r->tanggal_keluar_gudang_3);

                                // =========================
                                // CARI GUDANG BERIKUTNYA
                                // =========================

                                // =========================
                                // URUTKAN GUDANG BERDASARKAN PLANNING
                                // =========================

                                $gudang = collect([
    [
        'nama'     => 'KACS',
        'planning' => $r->planning_loading,
        'tiba'     => $r->tanggal_tiba_gudang,
        'keluar'   => $r->tanggal_keluar_gudang,
    ],
    [
        'nama'     => 'SENTUL',
        'planning' => $r->planning_loading_2,
        'tiba'     => $r->tanggal_tiba_gudang_2,
        'keluar'   => $r->tanggal_keluar_gudang_2,
    ],
    [
        'nama'     => 'CCIE',
        'planning' => $r->planning_loading_3,
        'tiba'     => $r->tanggal_tiba_gudang_3,
        'keluar'   => $r->tanggal_keluar_gudang_3,
    ],
])

// hanya gudang yang punya planning
->filter(function ($g) {
    return !empty($g['planning']);
})

// urut berdasarkan planning paling awal
->sortBy(function ($g) {
    return strtotime($g['planning']);
})

->values();

$adaPlanningGudang = $gudang->count() > 0;

$statusGudang = null;

foreach ($gudang as $g) {

    // masih menuju gudang
    if (empty($g['tiba'])) {

        $statusGudang = [
            'status' => 'PERJALANAN KE ' . $g['nama'],
            'badge'  => 'yellow'
        ];

        break;
    }

    // sedang di gudang
    if (!empty($g['tiba']) && empty($g['keluar'])) {

        $statusGudang = [
            'status' => 'DI GUDANG ' . $g['nama'],
            'badge'  => 'blue'
        ];

        break;
    }
}

$tibaAkhir    = $r->tanggal_tiba;
$bongkarAkhir = $r->tanggal_bongkar;

if (empty($dpt)) {

    // belum dapat armada
    $status = 'MENCARI UNIT';
    $badge  = 'red';

}
elseif (!$adaPlanningGudang && empty($tibaAkhir)) {

    // belum ada planning gudang sama sekali
    $status = 'PERJALANAN KE GUDANG';
    $badge  = 'orange';

}
elseif ($statusGudang) {

    // masih proses di gudang
    $status = $statusGudang['status'];
    $badge  = $statusGudang['badge'];

}
elseif (empty($tibaAkhir)) {

    // semua gudang selesai, menuju tujuan
    $status = 'PERJALANAN KE TUJUAN';
    $badge  = 'yellow';

}
elseif (!empty($tibaAkhir) && !empty($bongkarAkhir)) {

    $status = 'SUDAH SELESAI';
    $badge  = 'green';

}
elseif (!empty($tibaAkhir)) {

    $status = 'SUDAH TIBA TUJUAN';
    $badge  = 'success';

}
else {

    $status = '-';
    $badge  = 'gray';

}
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

                                // =========================
                                // GUDANG BERIKUTNYA
                                // =========================

                                $nextGudang = collect([
                                [
                                'nama' => 'KACS',
                                'planning' => $r->planning_loading,
                                'tiba' => $r->tanggal_tiba_gudang,
                                ],
                                [
                                'nama' => 'SENTUL',
                                'planning' => $r->planning_loading_2,
                                'tiba' => $r->tanggal_tiba_gudang_2,
                                ],
                                [
                                'nama' => 'CCIE',
                                'planning' => $r->planning_loading_3,
                                'tiba' => $r->tanggal_tiba_gudang_3,
                                ],
                                ])
                                ->filter(function ($g) use ($keluarTimestamp) {

                                // planning harus ada
                                if (empty($g['planning'])) {
                                return false;
                                }

                                // belum tiba di gudang tsb
                                if (!empty($g['tiba'])) {
                                return false;
                                }

                                // kalau belum pernah keluar gudang sama sekali,
                                // planning pertama boleh dipilih
                                if (!$keluarTimestamp) {
                                return true;
                                }

                                // planning harus sesudah / sama dengan keluar terakhir
                                return strtotime($g['planning']) >= $keluarTimestamp;
                                })
                                ->sortBy(function ($g) use ($keluarTimestamp) {

                                if (!$keluarTimestamp) {
                                return strtotime($g['planning']);
                                }

                                return abs(strtotime($g['planning']) - $keluarTimestamp);
                                })
                                ->first();

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
                                    $alertText='OVERDUE' ;
                                    $alertClass='red' ;
                                    } elseif ($hariSisa==0) {
                                    $alertText='H-0' ;
                                    $alertClass='red' ;
                                    } elseif ($hariSisa==1) {
                                    $alertText='H-1' ;
                                    $alertClass='red' ;
                                    } elseif ($hariSisa==2) {
                                    $alertText='H-2' ;
                                    $alertClass='orange' ;
                                    } elseif ($hariSisa==3) {
                                    $alertText='H-3' ;
                                    $alertClass='orange' ;
                                    } elseif ($hariSisa <=7) {
                                    $alertText='H-' . $hariSisa;
                                    $alertClass='blue' ;
                                    } else {
                                    $alertText='ON TRACK' ;
                                    $alertClass='green' ;
                                    }

                                    $alert=$alertText;
                                    }
                                    //=========================// 5. SLA TIBA
                                    //=========================//=========================// 5. SLA TIBA
                                    //=========================$sla_tiba=$r->sla_tiba;

                                    // =========================
                                    //$sla_bongkar = $r->sla_bongkar;
                                    @endphp

                                    <span class="badge {{ $badge }}">
                                        {{ $status }}
                                    </span>
                            </td>

                            <td>
                                @php
                                $channel = trim($r->dist_channel ?? '');

                                $classes = [
                                'badge-green',
                                'badge-blue',
                                'badge-orange',
                                'badge-red',
                                'badge-purple',
                                'badge-pink',
                                'badge-cyan',
                                'badge-yellow'
                                ];

                                $badgeClass = $channel
                                ? $classes[abs(crc32($channel)) % count($classes)]
                                : 'badge-default';
                                @endphp

                                <span class="badge {{ $badgeClass }}">
                                    {{ $channel ?: '-' }}
                                </span>
                            </td>
                            <td>{{ $r->tujuan }}</td>
                            <td>{{ $r->area }}</td>
                            <td>
                                @php
                                if (empty($r->rencana_kirim) || empty($r->tanggal_dpt_unit)) {
                                $statusMobil = 'BELUM DAPAT';
                                $badgeClass = 'status-belum';
                                } else {
                                $statusMobil = 'SUDAH DAPAT';
                                $badgeClass = 'status-sudah';
                                }
                                @endphp

                                <span class="badge-status {{ $badgeClass }}">
                                    {{ $statusMobil }}
                                </span>
                            </td>
                            <td>{{ $r->mobil }}</td>
                            <td>{{ $r->total_do_qty_car }}</td>
                            <!-- <td>{{ $r->perubahan_mobil }}</td> -->

                            <td>Rp {{ number_format($r->nilai_muatan, 0, ',', '.') }}</td>
                            <td>Rp {{ number_format($r->biaya_kirim, 0, ',', '.') }}</td>

                            <td>
                                @if(is_numeric($r->cr))
                                {{ number_format((float)$r->cr, 4, ',', '.') }}%
                                @else
                                {{ $r->cr }}
                                @endif
                            </td>
                            <td>
                                @php
                                $kategori = $r->kategori_ekspedisi ?? '-';
                                @endphp

                                @if(empty($kategori) || $kategori == '-')
                                <span class="badge gray">-</span>

                                @elseif(strtolower($kategori) == 'kontrak')
                                <span class="badge yellow">Kontrak</span>

                                @elseif(strtolower($kategori) == 'oncall')
                                <span class="badge blue">Oncall</span>

                                @else
                                <span class="badge orange">
                                    {{ $kategori }}
                                </span>
                                @endif
                            </td>
                            <td>{{ $r->ekpedisi }}</td>


                            <td>
                                {{ $r->tanggal_dpt_unit ? date('d-m-Y  ', strtotime($r->tanggal_dpt_unit)) : '-' }}
                            </td>





                            {{-- LAMA WAKTU PENCARIAN --}}
                            <td>{{ $r->lama_waktu_pencarian ?? '-' }}</td>


                            <td>

                                @php
                                $sla = trim($r->sla_dapat_mobil ?? '');
                                @endphp

                                @if(empty($sla))
                                <span class="badge gray">-</span>

                                @elseif(strtolower($sla) == 'on time' || strtoupper($sla) == 'H+0')
                                <span class="badge green">{{ $sla }}</span>

                                @elseif(strtolower($sla) == 'delay')
                                <span class="badge red">Delay</span>

                                @elseif(preg_match('/h\+1/i', $sla))
                                <span class="badge orange">{{ $sla }}</span>

                                @elseif(preg_match('/h\+/i', $sla))
                                <span class="badge red">{{ $sla }}</span>

                                @else
                                <span class="badge gray">{{ $sla }}</span>
                                @endif
                            </td>

                            <td>{{ $r->planning_loading ? date('d-m-Y  ', strtotime($r->planning_loading)) : '-' }}</td>
                            <td>
                                {{ $r->tanggal_tiba_gudang ? date('d-m-Y  ', strtotime($r->tanggal_tiba_gudang)) : '-' }}
                            </td>


                            <td>{{ $r->tanggal_keluar_gudang ? date('d-m-Y  ', strtotime($r->tanggal_keluar_gudang)) : '-' }}</td>
                            <!-- KOLOM 1: DURASI (Tetap aman mempertahankan perhitungan jam detail) -->
                            <td class="text-center">
                                @php
                                $planning = $r->planning_loading;
                                $tiba = $r->tanggal_tiba_gudang;
                                $durasiText = '-';

                                if (!empty($planning) && !empty($tiba)) {
                                $start = \Carbon\Carbon::parse($planning);
                                $end = \Carbon\Carbon::parse($tiba);

                                $totalMenit = $start->diffInMinutes($end);
                                $desimalHari = $totalMenit / 1440;

                                $hari = floor($desimalHari);
                                $jam = round(($desimalHari - $hari) * 24);

                                if ($jam == 24) {
                                $jam = 0;
                                $hari += 1;
                                }

                                if ($hari > 0 && $jam > 0) {
                                $durasiText = "{$hari} Hari {$jam} Jam";
                                } elseif ($hari > 0) {
                                $durasiText = "{$hari} Hari";
                                } elseif ($jam > 0) {
                                $durasiText = "{$jam} Jam";
                                } else {
                                $durasiText = "0 Jam";
                                }
                                }
                                @endphp

                                {{ $durasiText }}
                            </td>

                            <!-- KOLOM 2: STATUS GUDANG (On Time / Delay) -->
                            <td class="text-center">
                                @php
                                $statusText = '-';

                                if (!empty($r->planning_loading) && !empty($r->tanggal_tiba_gudang)) {
                                $startDay = \Carbon\Carbon::parse($r->planning_loading)->startOfDay();
                                $endDay = \Carbon\Carbon::parse($r->tanggal_tiba_gudang)->startOfDay();

                                $statusText = $endDay->gt($startDay) ? 'Delay' : 'On Time';
                                }
                                @endphp

                                @if($statusText === 'Delay')
                                <span class="badge red">Delay</span>

                                @elseif($statusText === 'On Time')
                                <span class="badge green">On Time</span>

                                @else
                                <span class="badge gray">-</span>
                                @endif
                            </td>

                            <!-- KOLOM 3: SLA LOADING (Sesuai SLA / H+) -->
                            <td class="text-center">
                                @php
                                $slaLoadingClean = '-';

                                if (!empty($r->planning_loading) && !empty($r->tanggal_tiba_gudang)) {
                                $start = \Carbon\Carbon::parse($r->planning_loading)->startOfDay();
                                $end = \Carbon\Carbon::parse($r->tanggal_tiba_gudang)->startOfDay();

                                if ($end->gt($start)) {
                                $selisihHari = $start->diffInDays($end);
                                $slaLoadingClean = "H+{$selisihHari}";
                                } else {
                                $slaLoadingClean = 'Sesuai SLA';
                                }
                                }
                                @endphp

                                @if($slaLoadingClean === 'Sesuai SLA')
                                <span class="badge bg-success">Sesuai SLA</span>
                                @elseif(str_contains($slaLoadingClean, 'H+'))
                                <span class="badge bg-warning text-dark">{{ $slaLoadingClean }}</span>
                                @else
                                <span class="badge bg-secondary">{{ $slaLoadingClean }}</span>
                                @endif
                            </td>
                            {{-- ================= GUDANG 2 ================= --}}

                            <td>{{ $r->planning_loading_2 ? date('d-m-Y  ', strtotime($r->planning_loading_2)) : '-' }}</td>
                            <td>
                                {{ $r->tanggal_tiba_gudang_2 ? date('d-m-Y  ', strtotime($r->tanggal_tiba_gudang_2)) : '-' }}
                            </td>

                            <td>
                                {{ $r->tanggal_keluar_gudang_2 ? date('d-m-Y  ', strtotime($r->tanggal_keluar_gudang_2)) : '-' }}
                            </td>

                            <td>{{ $r->lama_digudang_2 ?? '-' }}</td>
                            <td>{!! badgeSLA($r->sla_loading_2) !!}</td>
                            <td>
                                @php
                                $status2 = $r->status_gudang_2 ?? null;
                                @endphp

                                @if(empty($status2))
                                <span class="badge gray">-</span>

                                @elseif(strtolower($status2) == 'on time')
                                <span class="badge green">On Time</span>

                                @elseif(strtolower($status2) == 'delay')
                                <span class="badge red">Delay</span>

                                @elseif(strtolower($status2) == 'on site')
                                <span class="badge orange">On Site</span>

                                @else
                                <span class="badge gray">{{ $status2 }}</span>
                                @endif
                            </td>



                            {{-- ================= GUDANG 3 ================= --}}
                            <td>{{ $r->planning_loading_3 ? date('d-m-Y  ', strtotime($r->planning_loading_3)) : '-' }}</td>
                            <td>
                                {{ $r->tanggal_tiba_gudang_3 ? date('d-m-Y  ', strtotime($r->tanggal_tiba_gudang_3)) : '-' }}
                            </td>

                            <td>
                                {{ $r->tanggal_keluar_gudang_3 ? date('d-m-Y  ', strtotime($r->tanggal_keluar_gudang_3)) : '-' }}
                            </td>

                            <td>{{ $r->lama_digudang_3 ?? '-' }}</td>
                            <td>{!! badgeSLA($r->sla_loading_3) !!}</td>
                            <td>
                                @php
                                $status3 = $r->status_gudang_3 ?? null;
                                @endphp

                                @if(empty($status3))
                                <span class="badge gray">-</span>

                                @elseif(strtolower($status3) == 'on time')
                                <span class="badge green">On Time</span>

                                @elseif(strtolower($status3) == 'delay')
                                <span class="badge red">Delay</span>

                                @elseif(strtolower($status3) == 'on site')
                                <span class="badge orange">On Site</span>

                                @else
                                <span class="badge gray">{{ $status3 }}</span>
                                @endif
                            </td>





                            {{-- ================= SLA DAPAT MOBIL ================= --}}


                            <td>{{ $r->pic_monitoring }}</td>
                            <td>{{ $r->nama_kapal }}</td>
                            <td>{{ $r->etd }}</td>
                            <td>{{ $r->eta }}</td>
                            <td>
                                @php
                                $status = trim($r->status_kendaraan ?? '');
                                @endphp

                                @if($status == 'On Track')
                                <span class="badge green">🟢 On Track</span>

                                @elseif($status == 'Potential Delay')
                                <span class="badge red">🔴 Potential Delay</span>

                                @else
                                <span class="badge gray">-</span>
                                @endif
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


                            <td>{{ $r->act_urutan_bongkar }}</td>

                            <td>{{ $r->qty_monitoring }}</td>
                            <td>{{ $r->selisih_qty }}</td>
                            <td>{{ $r->remarks_qty }}</td>
                            <td>{{ $r->act_pgi_date ? date('d-m-Y  ', strtotime($r->act_pgi_date)) : '-' }}</td>


                            <!-- <td>{{ $r->created_by ?? '-' }}</td> -->
                            <td>{{ $r->atd }}</td>
                            <td>{{ $r->ata }}</td>
                            <td>{{ $estimasi_show }}</td>
                          <td>
    {{ $r->tanggal_tiba
        ? date('d-m-Y h:i A', strtotime($r->tanggal_tiba))
        : '-' }}
</td>

                            <td>
                                {{ !empty($lama_perjalanan) ? $lama_perjalanan . ' Hari' : '-' }}
                            </td>


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
    {{ $r->tanggal_bongkar
        ? date('d-m-Y h:i A', strtotime($r->tanggal_bongkar))
        : '-' }}
</td>
                            <td>{{ $over_bongkar }} Hari</td>
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
                            <td>{{ $r->reason_tiba }}</td>
                            <td>{{ $r->reason_bongkar }}</td>
                            <!-- <td>

                                @if($r->sla_bongkar == 'On Time')

                                <span class="badge green">
                                    Delivered (On Time)
                                </span>

                                @elseif($r->sla_bongkar == 'Delay')

                                <span class="badge red">
                                    Delivered (Delay)
                                </span>

                                @elseif($r->tanggal_tiba && !$r->tanggal_bongkar)

                                <span class="badge orange">
                                    On Site
                                </span>

                                @else

                                <span class="badge red">
                                    On Track
                                </span>

                                @endif

                            </td> -->
                            <td>
                                @php
                                $slaTiba = strtoupper(trim($r->sla_tiba ?? ''));
                                $slaBongkar = strtoupper(trim($r->sla_bongkar ?? ''));
                                @endphp

                                @if(empty($r->tanggal_tiba))

                                <span class="status-badge status-transit">
                                    🚚 Dalam Perjalanan
                                </span>

                                @elseif(!empty($r->tanggal_tiba) && empty($r->tanggal_bongkar))

                                <span class="status-badge status-unloading">
                                    📦 Sudah Tiba <br> Dalam Pembongkaran
                                </span>

                                @elseif($slaTiba == 'ON TIME' && $slaBongkar == 'ON TIME')

                                <span class="status-badge status-ontime">
                                    ✅ Pengiriman On Time
                                </span>

                                @else

                                <span class="status-badge status-delay">
                                    🚨 Pengiriman Delay
                                </span>

                                @endif
                            </td>
                            <td>
                                @php
                                $slaTiba = strtoupper(trim($r->sla_tiba ?? ''));
                                $slaBongkar = strtoupper(trim($r->sla_bongkar ?? ''));
                                @endphp

                                @if($slaTiba == 'ON TIME' && $slaBongkar == 'ON TIME')
                                <span class="badge badge-success">
                                    🟢 Delivered Ontime
                                </span>

                                @elseif($slaTiba == 'DELAY' && $slaBongkar == 'ON TIME')
                                <span class="badge badge-warning">
                                    🚚 Delay Perjalanan
                                </span>

                                @elseif($slaTiba == 'ON TIME' && $slaBongkar == 'DELAY')
                                <span class="badge badge-info">
                                    📦 Delay Pembongkaran
                                </span>

                                @elseif($slaTiba == 'DELAY' && $slaBongkar == 'DELAY')
                                <span class="badge badge-danger">
                                    🔥 Delivered Delay
                                </span>

                                @else
                                <span class="badge badge-secondary">
                                    ⏳ Belum Selesai
                                </span>
                                @endif
                            </td>
                            <td>{{ $r->remarks }}</td>
                            <td>{{ $r->route }}</td>
                            <td>
                                {{ $r->route ? explode('-', trim($r->route))[0] : '-' }}
                            </td>
                            <td>{{ $r->pulau }}</td>
                            <td>{{ $r->via_kirim }}</td>







                            </tr>

                            @endforeach

                </tbody>

            </table>

        </div>
        </div>


        <!-- <script>
            $(document).ready(function() {

                let table = $('#tableLogistik').DataTable({
                    scrollX: true,
                    pageLength: 10,
                    autoWidth: false
                });

                // =========================
                // FILTER CUSTOM
                // =========================
                $.fn.dataTable.ext.search.push(
                    function(settings, data, dataIndex) {

                        let area = $('#filterArea').val();
                        let month = $('#filterMonth').val();
                        let year = $('#filterYear').val();

                        // KOLOM TANGGAL NAIK LOGISTIK
                        let tanggal = data[1];

                        // KOLOM AREA
                        let dataArea = data[7];

                        // =====================
                        // FILTER AREA
                        // =====================
                        if (area && dataArea != area) {
                            return false;
                        }

                        // =====================
                        // VALIDASI DATE
                        // =====================
                        if (tanggal) {

                            let date = new Date(tanggal);

                            let rowMonth = date.getMonth() + 1;
                            let rowYear = date.getFullYear();

                            // FILTER BULAN
                            if (month && rowMonth != parseInt(month)) {
                                return false;
                            }

                            // FILTER TAHUN
                            if (year && rowYear != parseInt(year)) {
                                return false;
                            }
                        }

                        return true;
                    }
                );

                // =========================
                // TRIGGER FILTER
                // =========================
                $('#filterArea, #filterMonth, #filterYear').on('change', function() {
                    table.draw();
                });

            });
        </script> -->

        <script>
            $(document).ready(function() {

                let table = $('#tableLogistik').DataTable({
                    scrollX: true,
                    pageLength: 10,
                    autoWidth: false,

                    // =========================================================
                    // DRAW CALLBACK: Otomatis Hitung CR Berdasarkan Muatan Gabungan
                    // =========================================================
                    "drawCallback": function(settings) {
                        let api = this.api();
                        let shipmentGroups = {}; // Tempat menampung akumulasi TOTAL NILAI MUATAN

                        // -----------------------------------------------------
                        // PASS 1: Hitung Total Nilai Muatan per No Shipment
                        // -----------------------------------------------------
                        api.rows({

                            search: 'applied'
                        }).every(function(rowIdx, tableLoop, rowLoop) {
                            let data = this.data();
                            let node = this.node();

                            // 1. Ambil No Shipment dari data array DataTables (Index 5)
                            let noShipment = data[4] ? data[4].trim() : '';

                            // 2. DETEKSI OTOMATIS KOLOM RP
                            let rawNilaiMuatan = "";
                            let cellsRp = [];

                            // Cari semua td di baris ini yang mengandung teks "Rp"
                            $(node).find('td').each(function() {
                                if ($(this).text().includes('Rp')) {
                                    cellsRp.push($(this));
                                }
                            });

                            // Ambil text Nilai Muatan (Kolom Rp Pertama)
                            if (cellsRp.length >= 2) {
                                rawNilaiMuatan = cellsRp[0].text().trim();
                            } else {
                                rawNilaiMuatan = $(node).find('td').eq(9).text().trim(); // Fallback index 9
                            }

                            // 3. BERSIHKAN ANGKA MUATAN
                            let cleanNilaiMuatan = rawNilaiMuatan.replace(/[^0-9]/g, "");
                            let nilaiMuatan = parseFloat(cleanNilaiMuatan) || 0;

                            // Akumulasikan Nilai Muatan jika No Shipment valid
                            if (noShipment && noShipment !== '-' && noShipment !== '') {
                                if (!shipmentGroups[noShipment]) {
                                    shipmentGroups[noShipment] = {
                                        totalMuatan: 0,
                                        totalBiaya: 0
                                    };
                                }
                                let rawBiaya = "";

                                if (cellsRp.length >= 2) {
                                    rawBiaya = cellsRp[1].text().trim();
                                } else {
                                    rawBiaya = $(node).find('td').eq(10).text().trim();
                                }

                                let biayaMurni = parseFloat(rawBiaya.replace(/[^0-9]/g, "")) || 0;
                                shipmentGroups[noShipment].totalMuatan += nilaiMuatan;
                                shipmentGroups[noShipment].totalBiaya += biayaMurni;
                            }

                        });

                        // Helper internal untuk memformat angka kembali ke Rupiah saat render teks td
                        function formatKeRupiahText(angka) {
                            return 'Rp ' + String(angka).replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                        }

                        // -----------------------------------------------------
                        // PASS 2: Cetak Hasil & Hitung Cost Ratio Per Baris
                        // -----------------------------------------------------
                        api.rows({
                            page: 'current',
                            search: 'applied'
                        }).every(function(rowIdx, tableLoop, rowLoop) {
                            let data = this.data();
                            let node = this.node();
                            let noShipment = data[4] ? data[4].trim() : '';

                            let cellsRp = [];
                            $(node).find('td').each(function() {
                                if ($(this).text().includes('Rp')) {
                                    cellsRp.push($(this));
                                }
                            });

                            let cellMuatan = cellsRp.length >= 2 ? cellsRp[0] : $(node).find('td').eq(9);
                            let cellBiaya = cellsRp.length >= 2 ? cellsRp[1] : $(node).find('td').eq(10);
                            let cellCR = cellsRp.length >= 2 ? cellsRp[1].next('td') : $(node).find('td').eq(11);
                          
                            // Ambil Biaya Kirim asli di baris ini apa adanya (tidak digabung/tidak diubah)
                            let biayaMurni = parseFloat(cellBiaya.text().trim().replace(/[^0-9]/g, "")) || 0;
                            let costRatio = 0;

                            if (noShipment && noShipment !== '-' && noShipment !== '') {
                                // Ambil hasil akumulasi total muatan dari PASS 1
                                let totalMuatan = shipmentGroups[noShipment].totalMuatan;
                                let totalBiaya = shipmentGroups[noShipment].totalBiaya;

                                if (totalMuatan > 0) {
                                    costRatio = (totalBiaya / totalMuatan) * 100;
                                }

                                // Cetak ke kolom CR
                                if (costRatio > 0) {
                                    cellCR.html('<span class="text-primary font-weight-bold" style="color: #0056b3; font-weight: bold;">' + costRatio.toFixed(4) + '%</span>');
                                } else {
                                    // Jika baris ini biaya kirimnya 0 atau kosong, maka CR otomatis 0.0000%
                                    cellCR.html('<span class="text-muted" style="color: #9e9e9e; font-size: 11px;">0.0000%</span>');
                                }

                            } else {
                                // KONDISI JIKA TIDAK ADA NO SHIPMENT (DATA NORMAL)
                                let nilaiMuatanMurni = parseFloat(cellMuatan.text().trim().replace(/[^0-9]/g, "")) || 0;

                                if (nilaiMuatanMurni > 0) {
                                    costRatio = (biayaMurni / nilaiMuatanMurni) * 100;
                                }

                                if (costRatio > 0) {
                                    cellCR.html('<span class="text-primary font-weight-bold" style="color: #0056b3; font-weight: bold;">' + costRatio.toFixed(4) + '%</span>');
                                } else {
                                    cellCR.html('<span class="text-muted">-</span>');
                                }
                            }
                        });
                    }
                });

                // ==========================================
                // FILTER CUSTOM (AREA, BULAN, TAHUN)
                // ==========================================
                $.fn.dataTable.ext.search.push(
                    
                    function(settings, data, dataIndex) {

                     
                        let filterArea = $('#filterArea').val() ? $('#filterArea').val().trim() : '';
                        let filterMonth = $('#filterMonth').val();
                        let filterYear = $('#filterYear').val();
                        let filterDate = $('#filterDate').val();

                        let tanggal = data[0] ? data[0].trim() : '';
                        if (filterDate) {
                            let rowDate = '';

                            if (tanggal.includes('/')) {
                                let p = tanggal.split('/');
                                rowDate = p[2] + '-' + p[1].padStart(2, '0') + '-' + p[0].padStart(2, '0');
                            } else if (tanggal.includes('-')) {
                                if (tanggal.split('-')[0].length == 4) {
                                    rowDate = tanggal.substring(0, 10);
                                } else {
                                    let p = tanggal.split('-');
                                    rowDate = p[2] + '-' + p[1].padStart(2, '0') + '-' + p[0].padStart(2, '0');
                                }
                            }

                            if (rowDate !== filterDate) {
                                return false;
                            }
                        }
                        let dataArea = data[8] ? data[8].trim() : '';

                        if (filterArea && dataArea !== filterArea) return false;

                        if (filterMonth || filterYear) {
                            if (!tanggal || tanggal === '-') return false;
                            let rowMonth, rowYear;
                            if (tanggal.includes('-') || tanggal.includes('/')) {
                                let separator = tanggal.includes('-') ? '-' : '/';
                                let parts = tanggal.split(separator);
                                if (parts[0].length === 4) {
                                    rowYear = parseInt(parts[0]);
                                    rowMonth = parseInt(parts[1]);
                                } else {
                                    rowYear = parseInt(parts[2]);
                                    rowMonth = parseInt(parts[1]);
                                }
                            } else {
                                let date = new Date(tanggal);
                                if (isNaN(date.getTime())) return false;
                                rowMonth = date.getMonth() + 1;
                                rowYear = date.getFullYear();
                            }
                            if (filterMonth && rowMonth !== parseInt(filterMonth)) return false;
                            if (filterYear && rowYear !== parseInt(filterYear)) return false;
                        }
                       console.log(data);

                        return true;
                        
                    }

                );
                
                

               $('#filterArea, #filterDate, #filterMonth, #filterYear').on('change', function() {
    console.log('draw');
    table.draw();
});
            });

            // ==========================================
// EXPORT EXCEL SESUAI FILTER AKTIF
// ==========================================
$('#btnExportExcel').on('click', function(e) {
    e.preventDefault();

    let params = new URLSearchParams();

    let filterArea  = $('#filterArea').val();
    let filterDate  = $('#filterDate').val();
    let filterMonth = $('#filterMonth').val();
    let filterYear  = $('#filterYear').val();

    if (filterArea)  params.append('area', filterArea);
    if (filterDate)  params.append('date', filterDate);
    if (filterMonth) params.append('month', filterMonth);
    if (filterYear)  params.append('year', filterYear);

    let baseUrl = "{{ route('logistik.export') }}";
    let finalUrl = params.toString() ? (baseUrl + '?' + params.toString()) : baseUrl;

    window.location.href = finalUrl;
});
        </script>

    </body>

    </html>