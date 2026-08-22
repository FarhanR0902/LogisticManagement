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
        }

        /* ================= CONTAINER ================= */

        .container {
            margin-left: 260px;
            padding: 30px;
        }

        h2 {
            font-size: 32px;
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
            font-size: 14px;
        }

        th {
            background: #03c03c;
            color: #fff;
            padding: 14px 12px;
            white-space: nowrap;
            font-size: 14px;
            font-weight: 600;
            text-align: center;
            border: 1px solid #dbeafe;
        }

        td {
            padding: 12px 10px;
            border: 1px solid #e2e8f0;
            white-space: nowrap;
            text-align: left;
            vertical-align: middle;
            font-size: 14px;
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
            padding: 12px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 14px;
            min-width: 180px;
            outline: none;
        }

        .filter-box input:focus,
        .filter-box select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, .15);
        }

        .filter-box button {
            padding: 12px 18px;
            background: #22c55e;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .filter-box button:hover {
            background: #16a34a;
        }

        .filter-box a {
            padding: 12px 18px;
            background: #ef4444;
            color: white;
            border-radius: 10px;
            text-decoration: none;
            font-size: 14px;
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
            padding: 12px;
            border: 2px dashed #cbd5e1;
            border-radius: 10px;
            background: #f8fafc;
            font-size: 14px;
        }

        .import-box input[type=file]:hover {
            border-color: #2563eb;
        }

        .import-box button {
            padding: 12px 18px;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            font-size: 14px;
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
            padding: 14px 24px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: .3s;
        }

        .archive-btn:hover {
            transform: translateY(-2px);
        }

        /* ================= BADGE ================= */

        .badge {
            padding: 8px 14px;
            border-radius: 20px;
            color: white;
            font-size: 13px;
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
            width: 100px !important;
            min-width: 100px !important;
            max-width: 120px !important;
            font-size: 13px;
            text-align: center;
        }

        /* ================= DATATABLE ================= */

        .dataTables_wrapper {
            font-size: 14px;
        }

        .dataTables_filter input {
            padding: 8px 12px !important;
            border-radius: 8px !important;
        }

        .dataTables_length select {
            padding: 6px 10px !important;
        }

        /* ================= RESPONSIVE ================= */

        @media(max-width:768px) {

            .container {
                margin-left: 0;
                padding: 15px;
            }

            h2 {
                font-size: 24px;
            }

            table {
                font-size: 13px;
            }

            th {
                font-size: 13px;
                padding: 10px;
            }

            td {
                font-size: 13px;
                padding: 8px;
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
            padding: 7px 14px;
            border-radius: 999px;
            font-size: 13px;
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

        .cr-value{
    color:#0056b3;
    font-weight:700;
    font-size:14px;
}

.badge-duplicate{
    display:inline-block;
    padding:7px 12px;
    border-radius:20px;
    background:linear-gradient(135deg,#7c3aed,#a855f7);
    color:#fff;
    font-size:12px;
    font-weight:700;
    white-space:nowrap;
    box-shadow:0 2px 8px rgba(124,58,237,.25);
}
.badge-duplicate{
    background:#ff9800;
    color:#fff;
    padding:7px 14px;
    border-radius:20px;
    font-size:13px;
    font-weight:700;
    display:inline-block;
}

.cr-value{
    color:#0056b3;
    font-weight:700;
    font-size:14px;
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
                padding: 12px 22px;
                border-radius: 12px;
                font-size: 14px;
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
                font-size: 16px !important;
            }

            .dataTables_wrapper .dataTables_length,
            .dataTables_wrapper .dataTables_filter,
            .dataTables_wrapper .dataTables_info,
            .dataTables_wrapper .dataTables_paginate {
                font-size: 16px !important;
                font-weight: 500;
            }

            /* Show entries */
            .dataTables_length select {
                font-size: 16px !important;
                padding: 10px 14px !important;
                min-width: 90px;
                height: 42px;
            }

            /* Search box */
            .dataTables_filter input {
                font-size: 16px !important;
                padding: 10px 14px !important;
                min-width: 280px;
                height: 42px;
                border-radius: 10px !important;
            }

            /* Info text */
            .dataTables_info {
                font-size: 16px !important;
                padding-top: 15px !important;
            }

            /* Pagination */
            .dataTables_paginate .paginate_button {
                font-size: 15px !important;
                padding: 8px 14px !important;
                margin: 0 3px !important;
                border-radius: 8px !important;
            }

            /* Table font lebih besar */
            #tableLogistik td {
                font-size: 15px !important;
            }

            #tableLogistik th {
                font-size: 15px !important;
            }

            /* Badge ikut membesar */
            .badge,
            .badge-status {
                font-size: 14px !important;
                padding: 8px 12px !important;
            }

            /* Responsive */
            @media (min-width: 1600px) {

                .dataTables_wrapper {
                    font-size: 17px !important;
                }

                #tableLogistik td {
                    font-size: 16px !important;
                }

                #tableLogistik th {
                    font-size: 16px !important;
                }

                .dataTables_filter input {
                    min-width: 350px;
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
                padding: 8px 12px;
                border-radius: 20px;
                font-size: 13px;
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
                padding: 8px 14px;
                border-radius: 20px;
                font-size: 13px;
                font-weight: 700;
                text-align: center;
                line-height: 1.3;
                min-width: 170px;
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

padding: 10px 18px;

background: #198754;
color: #fff !important;

border: none;
border-radius: 8px;

font-size: 14px;
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
font-size: 16px;
}
        </style>

       <div class="filter-box">

    <form id="filterForm">

        <select id="filterPlanner" name="planner">
            <option value="">Semua Planner</option>
            @foreach($planners as $p)
                <option value="{{ $p }}">{{ $p }}</option>
            @endforeach
        </select>

        <select id="filterArea" name="area">
            <option value="">Semua Area</option>
            @foreach($areas as $a)
                <option value="{{ $a }}">{{ $a }}</option>
            @endforeach
        </select>

        <input type="date" id="filterDate">

        <select id="filterMonth" name="month">
            <option value="">Semua Bulan</option>
            @for($i=1;$i<=12;$i++)
                <option value="{{ $i }}">{{ $i }}</option>
            @endfor
        </select>

        <select id="filterYear" name="year">
            <option value="">Semua Tahun</option>

            @php
                $startYear = 2023;
                $endYear = date('Y') + 1;
            @endphp

            @for($i = $startYear; $i <= $endYear; $i++)
                <option value="{{ $i }}">{{ $i }}</option>
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
                    <th>Planning Loading Pasuruan</th>
                    <th>Tanggal Tiba Pasuruan</th>
                    <th>Tanggal Keluar Pasuruan</th>




                    <th>PIC Monitoring</th>
                    <th>Nama Kapal</th>
                    <th>ETD</th>
                    <th>ETA</th>

                    <th>Alert</th>


                    <th class="col-small">Urutan Bongkar</th>

                    <th>Actual Delivery Quantity</th>
                    <th>Biaya Kuli Pasuruan</th>
                    <th>Total Biaya Kuli Pasuruan</th>
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
                     <th>Status Bongkar</th>
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
                    <th>Estimasi Admin</th>
                    <th>Ontime/Delay Admin</th>


                </tr>
            </thead>

            <tbody>
                @php
                  $shownShipment = [];
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

   @php

$shipmentGroups = [];

foreach ($logistik->groupBy('no_shipment_pasuruan') as $shipment => $rows) {

    $shipmentGroups[$shipment] = [

        // SUM muatan seluruh customer dalam shipment
        'totalMuatan' => $rows->sum(function ($x) {
            return (float) $x->nilai_muatan_pasuruan;
        }),

        // MAX biaya kirim dalam shipment
        'totalBiaya' => $rows->max(function ($x) {
            return (float) $x->biaya_kirim_pasuruan;
        }),

    ];
}

@endphp


                @foreach($logistik as $r)

           @php
  

        $shipment = trim($r->no_shipment_pasuruan);

        $isDuplicate = in_array($shipment, $shownShipment, true);

        if (!$isDuplicate) {
            $shownShipment[] = $shipment;
        }

        $jumlahData = $logistik
            ->where('no_shipment_pasuruan', $shipment)
            ->count();

        $cr = $crShipment[$shipment] ?? 0;


// ================= NORMALISASI TANGGAL =================

$estimasiAdminPasuruan = null;

if (!empty($rencanaKirimTampil) && !empty($r->transport_lead_time_pasuruan)) {
    $estimasiAdminPasuruan = $rencanaKirimTampil->copy()
        ->addDays((int) $r->transport_lead_time_pasuruan);
}

$statusEstimasiAdminPasuruan = '-';

if ($estimasiAdminPasuruan && !empty($r->tanggal_tiba_pasuruan)) {

    $tanggalTibaPasuruan = \Carbon\Carbon::parse($r->tanggal_tiba_pasuruan);

    $statusEstimasiAdminPasuruan =
        $tanggalTibaPasuruan->lte($estimasiAdminPasuruan)
            ? 'On Time'
            : 'Delay';

} elseif ($estimasiAdminPasuruan && empty($r->tanggal_tiba_pasuruan)) {

    $statusEstimasiAdminPasuruan =
        now()->startOfDay()->gt($estimasiAdminPasuruan->copy()->startOfDay())
            ? 'Delay'
            : 'Belum Tiba';
}
$viaKirim = strtoupper(trim($r->via_kirim_pasuruan ?? ''));

if ($viaKirim === 'DARAT' && !empty($r->tanggal_terima_po_pasuruan)) {
    $rencanaKirimTampil = \Carbon\Carbon::parse($r->tanggal_terima_po_pasuruan)->addDays(4);
} elseif (!empty($r->rencana_kirim_pasuruan)) {
    $rencanaKirimTampil = \Carbon\Carbon::parse($r->rencana_kirim_pasuruan);
} else {
    $rencanaKirimTampil = null;
}

$planningLoading = (!empty($r->planning_loading_pasuruan) && $r->planning_loading_pasuruan != 'mm/dd/yyyy')
    ? strtotime($r->planning_loading_pasuruan)
    : null;

$tibaGudang = (!empty($r->tanggal_tiba_gudang_pasuruan) && $r->tanggal_tiba_gudang_pasuruan != 'mm/dd/yyyy')
    ? strtotime($r->tanggal_tiba_gudang_pasuruan)
    : null;

$keluarGudang = (!empty($r->tanggal_keluar_gudang_pasuruan) && $r->tanggal_keluar_gudang_pasuruan != 'mm/dd/yyyy')
    ? strtotime($r->tanggal_keluar_gudang_pasuruan)
    : null;

// ================= LEAD TIME =================

$leadtime = is_numeric($r->transport_lead_time_pasuruan)
    ? (int) $r->transport_lead_time_pasuruan
    : 0;

// ================= LAMA DI GUDANG =================

$lama_digudang = ($tibaGudang && $keluarGudang)
    ? max(0, ceil(($keluarGudang - $tibaGudang) / 86400))
    : null;

// ================= SLA LOADING =================

$sla_loading = '-';

if ($planningLoading && $keluarGudang) {

    $selisih = ceil(($keluarGudang - $planningLoading) / 86400);

    if ($selisih <= 0) {
        $sla_loading = 'H+0';
    } elseif ($selisih == 1) {
        $sla_loading = 'H+1';
    } else {
        $sla_loading = 'H+' . $selisih;
    }
}

@endphp
                        <tr>


                        <td>{{ $r->tanggal_terima_po_pasuruan ? date('d-m-Y', strtotime($r->tanggal_terima_po_pasuruan)) : '-' }}</td>
                       <td>{{ $rencanaKirimTampil ? $rencanaKirimTampil->format('d-m-Y') : '-' }}</td>
                        <td>{{ $r->transport_lead_time_pasuruan }}</td>

                        <td>{{ $r->planner_pasuruan }}</td>
                        <td>{{ $r->no_shipment_pasuruan }}</td>
                    <td>
@php

$dpt            = $r->tanggal_dpt_unit_pasuruan;
$tibaGudang     = $r->tanggal_tiba_gudang_pasuruan;
$keluarGudang   = $r->tanggal_keluar_gudang_pasuruan;
$tibaTujuan     = $r->tanggal_tiba_pasuruan;
$bongkarTujuan  = $r->tanggal_bongkar_pasuruan;

// =========================
// STATUS PENGIRIMAN
// =========================

if (empty($dpt)) {

    $status = 'MENCARI UNIT';
    $badge  = 'red';

}
elseif (empty($tibaGudang)) {

    $status = 'PERJALANAN KE GUDANG';
    $badge  = 'orange';

}
elseif (!empty($tibaGudang) && empty($keluarGudang)) {

    $status = 'DI GUDANG';
    $badge  = 'blue';

}
elseif (!empty($keluarGudang) && empty($tibaTujuan)) {

    $status = 'PERJALANAN KE TUJUAN';
    $badge  = 'yellow';

}
elseif (!empty($tibaTujuan) && empty($bongkarTujuan)) {

    $status = 'TIBA DI TUJUAN';
    $badge  = 'success';

}
elseif (!empty($tibaTujuan) && !empty($bongkarTujuan)) {

    $status = 'SUDAH SELESAI';
    $badge  = 'green';

}
else {

    $status = '-';
    $badge  = 'gray';

}

@endphp

<span class="badge {{ $badge }}">
    {{ $status }}
</span>

</td>
                        <td>
                            @php
                            $channel = trim($r->dist_channel_pasuruan ?? '');

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
                        <td>{{ $r->tujuan_pasuruan }}</td>
                        <td>{{ $r->area_pasuruan }}</td>
                      <td>
@php
    if (!empty($r->tanggal_dpt_unit_pasuruan)) {
        $statusMobil = 'Sudah Dapat Unit';
        $badgeClass = 'status-sudah';
    } else {
        $statusMobil = 'Belum Dapat Unit';
        $badgeClass = 'status-belum';
    }
@endphp

<span class="badge-status {{ $badgeClass }}">
    {{ $statusMobil }}
</span>
</td>
                        <td>{{ $r->mobil_pasuruan }}</td>
                        <td>{{ $r->total_do_pasuruan }}</td>
                        <!-- <td>{{ $r->perubahan_mobil_pasuruan }}</td> -->

                    <td>Rp {{ number_format($r->nilai_muatan_pasuruan, 0, ',', '.') }}</td>
<td>Rp {{ number_format($r->biaya_kirim_pasuruan, 0, ',', '.') }}</td>

<td class="cr-cell">
    <span class="text-muted">-</span>
</td>
                        <td>
                            @php
                            $kategori = $r->kategori_ekspedisi_pasuruan ?? '-';
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
                        <td>{{ $r->ekspedisi_pasuruan }}</td>


                        <td>
                            {{ $r->tanggal_dpt_unit_pasuruan ? date('d-m-Y  ', strtotime($r->tanggal_dpt_unit_pasuruan)) : '-' }}
                        </td>





                        {{-- LAMA WAKTU PENCARIAN --}}
                       <td>
@php
    $lamaPencarian = '-';

    if (!empty($r->rencana_kirim_pasuruan) && !empty($r->tanggal_dpt_unit_pasuruan)) {

        $rencana = strtotime(date('Y-m-d', strtotime($r->rencana_kirim_pasuruan)));
        $dapatUnit = strtotime(date('Y-m-d', strtotime($r->tanggal_dpt_unit_pasuruan)));

        $selisih = floor(($dapatUnit - $rencana) / 86400);

        if ($selisih <= 0) {
            $lamaPencarian = 'H+0';
        } else {
            $lamaPencarian = 'H+' . $selisih;
        }
    }
@endphp

{{ $lamaPencarian }}
</td>

<td>
@php
    $sla = '-';

    if (!empty($r->rencana_kirim_pasuruan) && !empty($r->tanggal_dpt_unit_pasuruan)) {

        $rencana = strtotime(date('Y-m-d', strtotime($r->rencana_kirim_pasuruan)));
        $dapatUnit = strtotime(date('Y-m-d', strtotime($r->tanggal_dpt_unit_pasuruan)));

        $selisih = floor(($dapatUnit - $rencana) / 86400);

        $sla = ($selisih <= 0) ? 'On Time' : 'Delay';
    }
@endphp

@if($sla == 'On Time')
    <span class="badge green">On Time</span>
@elseif($sla == 'Delay')
    <span class="badge red">Delay</span>
@else
    <span class="badge gray">-</span>
@endif
</td>

                        <td>{{ $r->planning_loading_pasuruan ? date('d-m-Y  ', strtotime($r->planning_loading_pasuruan)) : '-' }}</td>
                        <td>
                            {{ $r->tanggal_tiba_gudang_pasuruan ? date('d-m-Y  ', strtotime($r->tanggal_tiba_gudang_pasuruan)) : '-' }}
                        </td>


                        <td>{{ $r->tanggal_keluar_gudang_pasuruan ? date('d-m-Y  ', strtotime($r->tanggal_keluar_gudang_pasuruan)) : '-' }}</td>


                        <td>{{ $r->pic_monitoring_pasuruan }}</td>
                       <td>{{ $r->nama_kapal_pasuruan }}</td>
                           <td>{{ $r->etd_pasuruan }}</td>
                          <td>{{ $r->eta_pasuruan }}</td>

                      <td>
@php
    $alert = '-';
    $badgeClass = 'badge-secondary';

    // Jika sudah tiba
    if (!empty($r->tanggal_tiba_pasuruan)) {

        $alert = '✓ Tiba';
        $badgeClass = 'badge-success';

    }
    // Jika belum tiba, hitung berdasarkan estimasi
    elseif (!empty($r->estimasi_tiba_pasuruan)) {

        $estimasi = strtotime(date('Y-m-d', strtotime($r->estimasi_tiba_pasuruan)));
        $today = strtotime(date('Y-m-d'));

        $sisaHari = floor(($estimasi - $today) / 86400);

        if ($sisaHari < 0) {
            $alert = 'OVERDUE';
            $badgeClass = 'badge-danger';

        } elseif ($sisaHari == 0) {
            $alert = 'H-0';
            $badgeClass = 'badge-danger';

        } elseif ($sisaHari == 1) {
            $alert = 'H-1';
            $badgeClass = 'badge-danger';

        } elseif ($sisaHari == 2) {
            $alert = 'H-2';
            $badgeClass = 'badge-warning';

        } elseif ($sisaHari == 3) {
            $alert = 'H-3';
            $badgeClass = 'badge-warning';

        } elseif ($sisaHari <= 7) {
            $alert = 'H-' . $sisaHari;
            $badgeClass = 'badge-info';

        } else {
            $alert = 'ON TRACK';
            $badgeClass = 'badge-success';
        }
    }
@endphp

<span class="badge {{ $badgeClass }}">
    {{ $alert }}
</span>
</td>


                         <td>{{ $r->act_urutan_bongkar_pasuruan }}</td>

                          <td>{{ $r->actual_delivery_quantity_pasuruan }}</td>
                          <td>
    Rp {{ number_format($r->biaya_kuli_pasuruan ?? 0, 0, ',', '.') }}
</td>
<td>
    Rp {{ number_format($r->total_biaya_kuli_pasuruan ?? 0, 0, ',', '.') }}
</td>
                   <td>
@php
    $totalDo = is_numeric($r->total_do_pasuruan) ? (float) $r->total_do_pasuruan : 0;

    // FIXED: sebelumnya cuma dicek null/'' - tapi kalau kolomnya default 0
    // di database (bukan NULL), 0 masih lolos dianggap "sudah diisi" dan
    // muncul keterangan "Berkurang sekian" padahal actual-nya memang belum
    // pernah diinput oleh user. Sekarang 0 juga dianggap belum diisi.
    $actualRaw = $r->actual_delivery_quantity_pasuruan;
    $actualBelumDiisi = ($actualRaw === null || $actualRaw === '' || (float) $actualRaw == 0);

    if ($actualBelumDiisi) {

        $selisihBadge = 'badge-secondary';
        $selisihLabel = '-';
        $selisihQty = null;

    } else {

        $actualQty = (float) $r->actual_delivery_quantity_pasuruan;
        $selisihQty = $totalDo - $actualQty;

        if ($selisihQty == 0) {
            $selisihBadge = 'badge-success';
            $selisihLabel = 'Sesuai (0)';
        } elseif ($selisihQty > 0) {
            $selisihBadge = 'badge-danger';
            $selisihLabel = 'Berkurang ' . number_format($selisihQty, 0, ',', '.');
        } else {
            $selisihBadge = 'badge-warning';
            $selisihLabel = 'Lebih ' . number_format(abs($selisihQty), 0, ',', '.');
        }

    }
@endphp

<span class="badge {{ $selisihBadge }}">
    {{ $selisihLabel }}
</span>

<input
    type="hidden"
    form="form-update-{{ $r->id }}"
    name="selisih_quantity_pasuruan"
    value="{{ $selisihQty }}">
</td>
                         <td>{{ $r->reason_selisih_quantity_pasuruan }}</td>
                         <td>{{ $r->act_pgi_date_pasuruan ? date('d-m-Y', strtotime($r->act_pgi_date_pasuruan)) : '-' }}</td>


                          <td>{{ $r->atd_pasuruan ? date('d-m-Y', strtotime($r->atd_pasuruan)) : '-' }}</td>
                           <td>{{ $r->ata_pasuruan ? date('d-m-Y', strtotime($r->ata_pasuruan)) : '-' }}</td>
                         <td>
                                    {{ $r->estimasi_tiba_pasuruan ? date('d-m-Y', strtotime($r->estimasi_tiba_pasuruan)) : '-' }}
                                    <input type="hidden"
                                        form="form-update-{{ $r->id }}"
                                        name="estimasi_tiba_pasuruan"
                                        value="{{ $r->estimasi_tiba_pasuruan ? date('Y-m-d', strtotime($r->estimasi_tiba_pasuruan)) : '' }}">
                                </td>
                      <td>
    {{ $r->tanggal_tiba_pasuruan
        ? date('d-m-Y h:i A', strtotime($r->tanggal_tiba_pasuruan))
        : '-' }}
</td>
                        <td>{{ $r->lama_perjalanan_pasuruan ?? '-' }}</td>

                     <td>
    @php
        $slaTibaVal = '-';
        $slaTibaClass = 'gray';

        if (!empty($r->tanggal_tiba_pasuruan) && !empty($r->estimasi_tiba_pasuruan)) {

            // FIXED: dibandingkan per TANGGAL kalender saja (jam di-strip
            // dulu pakai date('Y-m-d')), bukan sebagai timestamp penuh.
            // tanggal_tiba_pasuruan sekarang datetime (ada jam, misal
            // 14:45), sementara estimasi_tiba_pasuruan biasanya hasil
            // generate tanpa jam (selalu 00:00:00). Kalau dibandingkan
            // sebagai timestamp mentah, tiba jam berapa pun di atas
            // 00:00 akan selalu dianggap "lebih besar" dari estimasi
            // walau tanggalnya sama persis → SLA jadi selalu Delay.
            $tibaDate = strtotime(date('Y-m-d', strtotime($r->tanggal_tiba_pasuruan)));
            $estimasiDate = strtotime(date('Y-m-d', strtotime($r->estimasi_tiba_pasuruan)));

            $slaTibaVal = ($tibaDate <= $estimasiDate) ? 'On Time' : 'Delay';
            $slaTibaClass = ($slaTibaVal == 'On Time') ? 'green' : 'red';
        }
    @endphp

    <span class="badge {{ $slaTibaClass }}">{{ $slaTibaVal }}</span>
</td>
                       <td>
    {{ $r->tanggal_bongkar_pasuruan
        ? date('d-m-Y h:i A', strtotime($r->tanggal_bongkar_pasuruan))
        : '-' }}
</td>
<td class="text-center">
    @php
        if (!empty($r->tanggal_bongkar_pasuruan)) {

            // Kalau tanggal bongkar sudah diisi
            $statusBongkar = 'Telah Bongkar';
            $statusBongkarClass = 'green';

        } elseif (!empty($r->tanggal_tiba_pasuruan)) {

            // Kalau sudah tiba tapi belum bongkar
            $tanggalTiba = strtotime(
                date('Y-m-d', strtotime($r->tanggal_tiba_pasuruan))
            );

            $hariIni = strtotime(date('Y-m-d'));

            $selisihHari = floor(
                ($hariIni - $tanggalTiba) / 86400
            );

            $selisihHari = max(0, $selisihHari);

            $statusBongkar = 'H+' . $selisihHari;

            if ($selisihHari == 0) {
                $statusBongkarClass = 'orange';
            } else {
                $statusBongkarClass = 'red';
            }

        } else {

            $statusBongkar = '-';
            $statusBongkarClass = 'gray';
        }
    @endphp

    <span class="badge status-bongkar {{ $statusBongkarClass }}">
        {{ $statusBongkar }}
    </span>
</td>


                        <td>
@php
    $overstayText = '';

    if (!empty($r->tanggal_tiba_pasuruan) && !empty($r->tanggal_bongkar_pasuruan)) {

        $tiba = strtotime(date('Y-m-d', strtotime($r->tanggal_tiba_pasuruan)));
        $bongkar = strtotime(date('Y-m-d', strtotime($r->tanggal_bongkar_pasuruan)));

        $overstay = max(0, floor(($bongkar - $tiba) / 86400));

        $overstayText = ($overstay == 0)
            ? '0 Hari'
            : "H+{$overstay} Hari";
    }

    $overstayBadge = ($overstayText === '' )
        ? 'gray'
        : (($overstayText === '0 Hari') ? 'green' : 'red');
@endphp

@if($overstayText === '')
    <span class="badge gray">-</span>
@else
    <span class="badge {{ $overstayBadge }}">{{ $overstayText }}</span>
@endif
<input
    type="hidden"
    form="form-update-{{ $r->id }}"
    name="overstay_days_pasuruan"
    value="{{ $overstayText }}">
</td>
                        <td>
@php
    $slaBongkar = '';

    if (!empty($r->tanggal_tiba_pasuruan) && !empty($r->tanggal_bongkar_pasuruan)) {

        $tiba = strtotime(date('Y-m-d', strtotime($r->tanggal_tiba_pasuruan)));
        $bongkar = strtotime(date('Y-m-d', strtotime($r->tanggal_bongkar_pasuruan)));

        $selisih = floor(($bongkar - $tiba) / 86400);

        $slaBongkar = ($selisih <= 0) ? 'On Time' : 'Delay';
    }
@endphp

@if($slaBongkar === '')
    <span class="badge gray">-</span>
@else
    <span class="badge {{ $slaBongkar === 'On Time' ? 'green' : 'red' }}">{{ $slaBongkar }}</span>
@endif
<input
    type="hidden"
    form="form-update-{{ $r->id }}"
    name="sla_bongkar_pasuruan"
    value="{{ $slaBongkar }}">
</td>
 <td>{{ $r->reason_waktu_tiba_pasuruan }}</td>
                       <td>{{ $r->reason_waktu_bongkar_pasuruan }}</td>
                        <td>
                            @php
                            $slaTiba = strtoupper(trim($r->sla_tiba_pasuruan ?? ''));
                            $slaBongkar = strtoupper(trim($r->sla_bongkar_pasuruan ?? ''));
                            @endphp

                            @if(empty($r->tanggal_tiba_pasuruan))

                            <span class="status-badge status-transit">
                                🚚 Dalam Perjalanan
                            </span>

                            @elseif(!empty($r->tanggal_tiba_pasuruan) && empty($r->tanggal_bongkar_pasuruan))

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
                            $slaTiba = strtoupper(trim($r->sla_tiba_pasuruan ?? ''));
                            $slaBongkar = strtoupper(trim($r->sla_bongkar_pasuruan ?? ''));
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
                        <td>{{ $r->remarks_pasuruan }}</td>
                        <td>{{ $r->route_pasuruan }}</td>
                        <td>
                            {{ $r->route_pasuruan ? explode('-', trim($r->route_pasuruan))[0] : '-' }}
                        </td>
                        <td>{{ $r->pulau_pasuruan}}</td>
                        <td>{{ $r->via_kirim_pasuruan }}</td>
                        <td>
    {{ $estimasiAdminPasuruan ? $estimasiAdminPasuruan->format('d-m-Y') : '-' }}
</td>

<td>
    @if($statusEstimasiAdminPasuruan == 'On Time')
        <span class="badge green">On Time</span>

    @elseif($statusEstimasiAdminPasuruan == 'Delay')
        <span class="badge red">Delay</span>

    @elseif($statusEstimasiAdminPasuruan == 'Belum Tiba')
        <span class="badge orange">Belum Tiba</span>

    @else
        <span class="badge gray">-</span>
    @endif
</td>







                        </tr>

                        @endforeach

            </tbody>

        </table>

    </div>
    </div>


    <script>
$(document).ready(function() {

    let table = $('#tableLogistik').DataTable({
        scrollX: true,
        pageLength: 10,
        autoWidth: false,

        // =========================================================
        // DRAW CALLBACK: Hitung CR Berdasarkan Muatan Gabungan per Shipment
        // =========================================================
        "drawCallback": function(settings) {
            let api = this.api();
            let shipmentGroups = {};

            // -----------------------------------------------------
            // PASS 1: Hitung Total Nilai Muatan & MAX Biaya Kirim per No Shipment
            // -----------------------------------------------------
            api.rows({ search: 'applied' }).every(function(rowIdx, tableLoop, rowLoop) {
                let data = this.data();
                let node = this.node();

                let noShipment = data[4] ? data[4].trim() : '';

                let cellsRp = [];
                $(node).find('td').each(function() {
                    if ($(this).text().includes('Rp')) {
                        cellsRp.push($(this));
                    }
                });

                let rawNilaiMuatan = cellsRp.length >= 2
                    ? cellsRp[0].text().trim()
                    : $(node).find('td').eq(12).text().trim();

                let nilaiMuatan = parseFloat(rawNilaiMuatan.replace(/[^0-9]/g, "")) || 0;

                if (noShipment && noShipment !== '-' && noShipment !== '') {

                    if (!shipmentGroups[noShipment]) {
                        shipmentGroups[noShipment] = {
                            totalMuatan: 0,
                            totalBiaya: 0,
                            totalRow: 0
                        };
                    }

                    let rawBiaya = cellsRp.length >= 2
                        ? cellsRp[1].text().trim()
                        : $(node).find('td').eq(13).text().trim();

                    let biayaMurni = parseFloat(rawBiaya.replace(/[^0-9]/g, "")) || 0;

                    shipmentGroups[noShipment].totalMuatan += nilaiMuatan;

                    // MAX biaya kirim (bukan SUM), karena biaya_kirim sama untuk semua baris shipment yang sama
                    shipmentGroups[noShipment].totalBiaya = Math.max(
                        shipmentGroups[noShipment].totalBiaya,
                        biayaMurni
                    );

                    shipmentGroups[noShipment].totalRow++;
                }
            });

            // -----------------------------------------------------
            // PASS 2: Tulis hasil CR ke tiap baris (halaman aktif saja)
            // -----------------------------------------------------
            api.rows({ page: 'current', search: 'applied' }).every(function(rowIdx, tableLoop, rowLoop) {
                let data = this.data();
                let node = this.node();
                let noShipment = data[4] ? data[4].trim() : '';

                let cellsRp = [];
                $(node).find('td').each(function() {
                    if ($(this).text().includes('Rp')) {
                        cellsRp.push($(this));
                    }
                });

                let cellMuatan = cellsRp.length >= 2 ? cellsRp[0] : $(node).find('td').eq(12);
                let cellBiaya  = cellsRp.length >= 2 ? cellsRp[1] : $(node).find('td').eq(13);
                let cellCR     = cellsRp.length >= 2 ? cellsRp[1].next('td') : $(node).find('td').eq(14);

                let costRatio = 0;

                if (noShipment && noShipment !== '-' && noShipment !== '' && shipmentGroups[noShipment]) {

                    let totalMuatan = shipmentGroups[noShipment].totalMuatan;
                    let totalBiaya  = shipmentGroups[noShipment].totalBiaya;

                    let nilaiMuatanBaris = parseFloat(
                        cellMuatan.text().trim().replace(/[^0-9]/g, "")
                    ) || 0;

                    if (totalMuatan > 0 && nilaiMuatanBaris > 0) {
                        let totalCR = (totalBiaya / totalMuatan) * 100;
                        let kontribusi = nilaiMuatanBaris / totalMuatan;
                        costRatio = kontribusi * totalCR;
                    }

                    cellCR.html(costRatio > 0
                        ? '<span class="cr-value">' + costRatio.toFixed(4).replace('.', ',') + '%</span>'
                        : '<span class="text-muted">0,0000%</span>'
                    );

                } else {

                    let nilaiMuatanMurni = parseFloat(cellMuatan.text().trim().replace(/[^0-9]/g, "")) || 0;
                    let biayaMurni = parseFloat(cellBiaya.text().trim().replace(/[^0-9]/g, "")) || 0;

                    if (nilaiMuatanMurni > 0) {
                        costRatio = (biayaMurni / nilaiMuatanMurni) * 100;
                    }

                    cellCR.html(costRatio > 0
                        ? '<span class="cr-value">' + costRatio.toFixed(4).replace('.', ',') + '%</span>'
                        : '<span class="text-muted">-</span>'
                    );
                }
            });
        }
    });

    // ==========================================
    // FILTER CUSTOM (AREA, PLANNER, DATE, BULAN, TAHUN)
    // ==========================================
    $.fn.dataTable.ext.search.push(
        function(settings, data, dataIndex) {
            let filterArea    = $('#filterArea').val() ? $('#filterArea').val().trim() : '';
            let filterPlanner = $('#filterPlanner').val() ? $('#filterPlanner').val().trim() : '';
            let filterMonth   = $('#filterMonth').val();
            let filterYear    = $('#filterYear').val();
            let filterDate    = $('#filterDate').val();

            // 1. FILTER TANGGAL (Kolom 0)
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

            // 2. FILTER PLANNER (Kolom 3)
            let rawPlanner  = data[3] ? data[3] : '';
            let dataPlanner = $('<div>').html(rawPlanner).text().trim();
            if (filterPlanner && dataPlanner !== filterPlanner) {
                return false;
            }

            // 3. FILTER AREA (Kolom 8)
            let rawArea  = data[8] ? data[8] : '';
            let dataArea = $('<div>').html(rawArea).text().trim();
            if (filterArea && dataArea !== filterArea) {
                return false;
            }

            // 4. FILTER BULAN & TAHUN
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

            return true;
        }
    );

    // Event trigger saat dropdown / input berubah
    $('#filterArea, #filterPlanner, #filterDate, #filterMonth, #filterYear').on('change', function() {
        table.draw();
    });
});

// ==========================================
// EXPORT EXCEL SESUAI FILTER AKTIF
// ==========================================
$('#btnExportExcel').on('click', function(e) {
    e.preventDefault();

    let params = new URLSearchParams();

    let filterArea    = $('#filterArea').val();
    let filterPlanner = $('#filterPlanner').val();
    let filterDate    = $('#filterDate').val();
    let filterMonth   = $('#filterMonth').val();
    let filterYear    = $('#filterYear').val();

    if (filterArea)    params.append('area', filterArea);
    if (filterPlanner) params.append('planner', filterPlanner);
    if (filterDate)    params.append('date', filterDate);
    if (filterMonth)   params.append('month', filterMonth);
    if (filterYear)    params.append('year', filterYear);

    let baseUrl = "{{ route('pasuruan.export') }}";
    let finalUrl = params.toString() ? (baseUrl + '?' + params.toString()) : baseUrl;

    window.location.href = finalUrl;
});
</script>

</body>

</html>
