@include('template.sidebar')

<!DOCTYPE html>
<html lang="id">

<head>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">


    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DATA PLANNER</title>

    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        body {
            background: #f8fafc;
            color: #334155;
        }

        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            color: #fff !important;
            font-size: 12px;
            font-weight: 300;
        }

        .green {
            background-color: #22c55e !important;
        }

        .red {
            background-color: #ef4444 !important;
        }

        .gray {
            background-color: #64748b !important;
        }

        .orange {
            background-color: #f97316 !important;
        }

        .yellow {
            background-color: #facc15 !important;
            color: #000 !important;
        }

        .container-fluid-custom {
            width: calc(100% - 260px);
            margin-left: 260px;
            padding: 30px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .title {
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
            position: relative;
        }

        .title::after {
            content: '';
            display: block;
            width: 50px;
            height: 4px;
            background: #0284c7;
            border-radius: 2px;
            margin-top: 5px;
        }

        .card {
            background: white;
            border-radius: 12px;
            border: none;
            padding: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        }

        /* Styling Table & DataTables */
        .dataTables_wrapper {
            padding-top: 10px;
        }

        /* FIX #3: border-collapse: collapse tidak kompatibel dengan scrollX:true
           (header-clone .dataTables_scrollHead jadi tidak sinkron lebar kolomnya
           dengan body table saat di-scroll horizontal). Ganti ke separate. */
        table.dataTable {
            border-collapse: separate !important;
            border-spacing: 0;
            font-size: 15px;
        }



        table.dataTable tbody tr {
            background-color: #ffffff;
            transition: background-color 0.2s ease;
        }

        table.dataTable tbody tr:hover {
            background-color: #f1f5f9 !important;
        }

        table.dataTable tbody td {
            padding: 12px 14px !important;
            vertical-align: middle;
            border-bottom: 1px solid #e2e8f0 !important;
            color: #475569;
            font-size: 15px;
        }
        /* ============================================= 
SELECT2 — SEARCHABLE DROPDOWN DI DALAM TABEL
============================================= */

/* Container select2 menyamai lebar & tinggi input lain */
#tablePlanner .select2-container {
width: 150px !important;
}

#tablePlanner .select2-container--bootstrap-5 .select2-selection {
min-height: 30px;
height: 30px;
padding: 0 8px;
border: 1px solid #cbd5e1;
border-radius: 6px;
font-size: 12px;
color: #334155;
background-color: #fff;
display: flex;
align-items: center;
}

#tablePlanner .select2-container--bootstrap-5 .select2-selection__rendered {
padding: 0;
line-height: 28px;
font-size: 12px;
}

#tablePlanner .select2-container--bootstrap-5 .select2-selection__arrow {
height: 28px;
top: 0;
}

#tablePlanner .select2-container--bootstrap-5.select2-container--focus .select2-selection,
#tablePlanner .select2-container--bootstrap-5.select2-container--open .select2-selection {
border-color: #38bdf8;
box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.2);
}

/* Dropdown hasil pencarian (di-append ke body, ikut style rapi) */
.select2-container--bootstrap-5 .select2-dropdown {
font-size: 13px;
border-radius: 8px;
border: 1px solid #cbd5e1;
box-shadow: 0 8px 20px rgba(0,0,0,0.12);
z-index: 3000;
}

.select2-container--bootstrap-5 .select2-search--dropdown .select2-search__field {
border-radius: 6px;
border: 1px solid #cbd5e1;
padding: 6px 8px;
font-size: 13px;
}

.select2-container--bootstrap-5 .select2-results__option--highlighted[aria-selected] {
background-color: #0284c7 !important;
color: #fff !important;
}

/* Select2 khusus di modal Add New Shipment, lebar penuh field-box */
#addModal .select2-container {
width: 100% !important;
}

#addModal .select2-container--bootstrap-5 .select2-selection {
min-height: 38px;
border-radius: 6px;
border: 1px solid #cbd5e1;
font-size: 13px;
}

        /* Inline Input Fields inside Table */
        table input[type="text"],
        table input[type="number"],
        table input[type="date"] {
            width: 140px;
            padding: 5px 8px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 12px;
            color: #334155;
            background-color: #fff;
            transition: all 0.2s;
        }

        table input:focus {
            border-color: #38bdf8;
            outline: none;
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.2);
        }

        table input[type="date"] {
            width: 165px;
        }

        /* Badge Statuses */
        .badge-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            text-align: center;
        }

        /* Modal Horizontal Scroll */
        .form-horizontal-scroll {
            overflow-x: auto;
            display: flex;
            gap: 15px;
            padding-bottom: 15px;
        }

        .form-horizontal-scroll .field-box {
            flex: 0 0 240px;
        }

        .form-horizontal-scroll label {
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 6px;
            display: block;
        }

        .form-horizontal-scroll .form-control {
            border-radius: 6px;
            font-size: 13px;
            border: 1px solid #cbd5e1;
        }

        /* Utility classes */
        .btn-action {
            display: inline-flex;
            gap: 5px;
        }

        /* DEFAULT HEADER (READ ONLY / NORMAL) */
        th.th-default {
            background: #00d0ff !important;
            color: #ffffff !important;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            text-align: center;
            vertical-align: middle;
            letter-spacing: 0.5px;
        }

        /* EDITABLE COLUMN */
        th.th-edit {
            background: #00ffa2 !important;
            /* orange */
            color: #111827 !important;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            text-align: center;
            vertical-align: middle;
            letter-spacing: 0.5px;
        }

        /* SYSTEM / AUTO CALCULATED */
        th.th-system {
            background: #2563eb !important;
            /* blue */
            color: #ffffff !important;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            text-align: center;
            vertical-align: middle;
            letter-spacing: 0.5px;
        }

        .th-oren {
            background-color: #ff9800 !important;
            color: white !important;
            text-align: center;
            font-weight: bold;
        }



        .bg-orange {
            background: #fd7e14 !important;
            color: #fff;
        }

        .input-filled {
            background-color: #dcfce7 !important;
            border: 2px solid #22c55e !important;
            color: #166534 !important;
            font-weight: 600;
        }

        .input-empty {
            background-color: #fef2f2 !important;
            border: 2px solid #ef4444 !important;
            color: #991b1b !important;
        }

        /* ===== ALERT CONTROL / SUMMARY BOX (PLANNER) ===== */
        .missing-field-box {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }

        #alertControlBox .box-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        #alertControlBox .box-header b {
            font-size: 15px;
            color: #1e293b;
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
            background: #f1f5f9;
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

        .completeness-badge {
            white-space: normal;
            max-width: 220px;
            line-height: 1.4;
        }

        .highlight-row td {
            background: #fde68a !important;
            transition: background-color .3s ease;
        }

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
    </style>

    <!-- FIX #2: script jQuery/DataTables/Bootstrap DIHAPUS dari <head> karena sudah
         dimuat ulang tepat sebelum $(document).ready() di bawah. Load sekali saja
         supaya tidak double-download & double-execute. -->
</head>

<body>

    <!-- TOAST CONTAINER -->
    <div class="toast-container" id="toastContainer"></div>

    <div class="container-fluid-custom">

        <div class="page-header">
            <div class="title">Data Planner</div>

            <div class="d-flex align-items-center gap-2">
                <a href="#" id="btnExport" class="btn btn-success">
                    Export
                </a>

                 <button type="button" id="btnSaveAll"
    class="btn btn-primary d-flex align-items-center gap-2"
    style="background:#0284c7; border:none; border-radius:8px; padding:10px 16px;">
    <i class="fa-solid fa-floppy-disk"></i>
    Save
    <span id="unsavedCount" class="badge bg-danger rounded-pill" style="display:none;">0</span>
</button>
                <!-- ADD NEW SHIPMENT -->
                <button type="button"
                    class="btn btn-primary d-flex align-items-center gap-2"
                    style="background: #0284c7; border: none; border-radius: 8px; padding: 10px 16px;"
                    data-bs-toggle="modal"
                    data-bs-target="#addModal">

                    <i class="fa-solid fa-plus"></i>
                    Add New Shipment
                </button>

                <!-- GUDANG 2 -->

            </div>
        </div>
        <div class="row mb-3">



            <div class="col-md-3">
                <label class="form-label fw-bold">
                    Filter data Import
                </label>

                <input type="date"
                    id="filterCreateTgl"
                    class="form-control">
            </div>


            <div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-centered">
                    <div class="modal-content shadow-lg border-0" style="border-radius:16px;">

                        <form action="{{ route('spvplanner.store') }}" method="POST">
                            @csrf

                            <!-- HEADER -->
                            <div class="modal-header border-bottom-0">
                                <h5 class="modal-title fw-bold">
                                    <i class="fa-solid fa-ship text-primary me-2"></i>
                                    Add New Shipment
                                </h5>

                                <button
                                    type="button"
                                    class="btn-close"
                                    data-bs-dismiss="modal">
                                </button>
                            </div>

                            <!-- BODY -->
                            <div class="modal-body">

                                <!-- Create Tanggal -->
                                <div class="mb-3" style="max-width:300px;">
                                    <label class="form-label fw-bold">
                                        Create Tanggal
                                    </label>

                                    <input
                                        type="date"
                                        name="create_tgl"
                                        class="form-control">
                                </div>

                                <!-- FORM SCROLL -->
                                <div class="form-horizontal-scroll bg-light rounded-3 border p-3">

                                    <div class="field-box">
                                        <label>No Shipment</label>
                                        <input type="text" name="no_shipment" class="form-control">
                                    </div>

                                    <div class="field-box">
                                        <label>Planner</label>
                                        <input type="text" name="planner" class="form-control">
                                    </div>

                                    <div class="field-box">
                                        <label>Dist Channel</label>
                                        <input type="text" name="dist_channel" class="form-control">
                                    </div>

                                    <div class="field-box">
                                        <label>Lead Time (Days)</label>
                                        <input type="number" name="transport_lead_time" class="form-control">
                                    </div>

                                    <div class="field-box">
                                        <label>Tujuan</label>
                                        <input type="text" name="tujuan" class="form-control">
                                    </div>

                                    <div class="field-box">
                                        <label>Area</label>
                                        <input type="text" name="area" class="form-control">
                                    </div>

                                <div class="field-box">
    <label>Mobil</label>
    <select name="mobil" class="form-control select2-modal">
        <option value="">-- Pilih Mobil --</option>
        @foreach($mobilList as $m)
            <option value="{{ $m }}">{{ $m }}</option>
        @endforeach
    </select>
</div>



                                    <div class="field-box">
                                        <label>Perubahan Mobil</label>
                                        <input type="text" name="perubahan_mobil" class="form-control">
                                    </div>

                                    <div class="field-box">
                                        <label>Nilai Muatan</label>
                                        <input type="text"
                                            name="nilai_muatan"
                                            class="form-control modal-nilai-muatan input-rupiah">
                                    </div>

                                    <div class="field-box">
                                        <label>Biaya Kirim</label>
                                        <input type="text"
                                            name="biaya_kirim"
                                            class="form-control modal-biaya-kirim input-rupiah">
                                    </div>

                                    <div class="field-box">
                                        <label>CR (%)</label>
                                        <input type="text"
                                            name="cr"
                                            readonly
                                            class="form-control modal-cr"
                                            style="background:#e2e8f0;">
                                    </div>

                                    <div class="field-box">
                                        <label>Kategori Ekspedisi</label>
                                        <input type="text" name="kategori_ekspedisi" class="form-control">
                                    </div>
<div class="field-box">
    <label>Ekspedisi</label>
    <select name="ekpedisi" class="form-control select2-modal">
        <option value="">-- Pilih Ekspedisi --</option>
        @foreach($ekpedisiList as $e)
            <option value="{{ $e }}">{{ $e }}</option>
        @endforeach
    </select>
</div>



                                    <div class="field-box">
                                        <label>Tanggal Terima Dari Admin</label>
                                        <input type="date" name="tanggal_naik_logistik" class="form-control">
                                    </div>

                                    <div class="field-box">
                                        <label>Rencana Kirim</label>
                                        <input type="date" name="rencana_kirim" class="form-control">
                                    </div>

                                    <div class="field-box">
                                        <label>Tanggal Dapat Unit</label>
                                        <input type="date" name="tanggal_dpt_unit" class="form-control">
                                    </div>

                                    <div class="field-box">
                                        <label>Tanggal Tiba KACS</label>
                                        <input type="date" name="tanggal_tiba_gudang" class="form-control">
                                    </div>

                                    <div class="field-box">
                                        <label>Planning Loading KACS</label>
                                        <input type="date" name="planning_loading" class="form-control">
                                    </div>

                                    <div class="field-box">
                                        <label>Tanggal Keluar KACS</label>
                                        <input type="date" name="tanggal_keluar_gudang" class="form-control">
                                    </div>

                                    <div class="field-box">
                                        <label>Tanggal Tiba Sentul</label>
                                        <input type="date" name="tanggal_tiba_gudang_2" class="form-control">
                                    </div>
                                    /
                                    <div class="field-box">
                                        <label>Tanggal Keluar Sentul</label>
                                        <input type="date" name="tanggal_keluar_gudang_2" class="form-control">
                                    </div>

                                    <div class="field-box">
                                        <label>Tanggal Tiba CCIE</label>
                                        <input type="date" name="tanggal_tiba_gudang_3" class="form-control">
                                    </div>

                                    <div class="field-box">
                                        <label>Tanggal Keluar CCIE</label>
                                        <input type="date" name="tanggal_keluar_gudang_3" class="form-control">
                                    </div>

                                    <div class="field-box field-wide">
                                        <label>Keterangan</label>
                                        <textarea
                                            name="keterangan"
                                            rows="2"
                                            class="form-control"></textarea>
                                    </div>

                                </div>

                            </div>

                            <!-- FOOTER -->
                            <div class="modal-footer border-top-0">

                                <button
                                    type="button"
                                    class="btn btn-secondary"
                                    data-bs-dismiss="modal">
                                    Close
                                </button>

                                <button
                                    type="submit"
                                    class="btn btn-success">
                                    Save Shipment
                                </button>

                            </div>

                        </form>

                    </div>
                </div>
            </div>
            <div class="row mb-3">

                <div class="col-md-3">
                    <label class="form-label fw-bold">
                        Filter Planner
                    </label>

                    <select id="filterPlanner" class="form-select">

                        <option value="">Semua Planner</option>

                        @foreach($planners as $planner)

                        <option value="{{ $planner }}">
                            {{ $planner }}
                        </option>

                        @endforeach

                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold">Filter Area</label>

                    <select id="filterArea" class="form-select">
                        <option value="">Semua Area</option>

                        @foreach($areas as $area)
                        <option value="{{ $area }}">
                            {{ $area }}
                        </option>
                        @endforeach
                    </select>
                </div>

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
                        <b>🔔 Alert Control — Data Belum Lengkap (Mobil, Ekspedisi, Route, Nama Driver, No Pol)</b>
                        <span class="badge red" id="alertControlCount">0 Alert</span>
                    </div>
                    <div id="alertControlList">
                        <div class="p-2" style="color:#6b7280; font-size:13px;">Memuat data...</div>
                    </div>
                </div>

                <div class="card">
                    <div class="table-responsive">
                        <table id="tablePlanner" class="display nowrap table table-hover" style="width:100%">
                            <thead>

                                <tr>

                                    <th class="th-default">Tanggal Import </th>
                                    <th class="th-oren">Nama Planner</th>
                                    <th class="th-oren">No Shipment</th>

                                    <th class="th-edit">Tanggal Terima Dari Admin</th>
                                    <th class="th-edit">Rencana Kirim</th>

                                    <th class="th-edit">Tanggal Dapat Unit</th>
                                    <th class="th-edit">
                                        Planning Loading <span style="color:#0047FF;font-weight:900;">KACS</span>
                                    </th>

                                    <th class="th-edit">
                                        Tanggal Tiba <span style="color:#0047FF;font-weight:900;">KACS</span>
                                    </th>

                                    <th class="th-edit">
                                        Tanggal Keluar <span style="color:#0047FF;font-weight:900;">KACS</span>
                                    </th>

                                    <th class="th-edit">
                                        Planning Loading <span style="color:#FF6B00;font-weight:900;">Sentul</span>
                                    </th>

                                    <th class="th-edit">
                                        Tanggal Tiba <span style="color:#FF6B00;font-weight:900;">Sentul</span>
                                    </th>

                                    <th class="th-edit">
                                        Tanggal Keluar <span style="color:#FF6B00;font-weight:900;">Sentul</span>
                                    </th>

                                    <th class="th-edit">
                                        Planning Loading <span style="color:#FF0033;font-weight:900;">CCIE</span>
                                    </th>

                                    <th class="th-edit">
                                        Tanggal Tiba <span style="color:#FF0033;font-weight:900;">CCIE</span>
                                    </th>

                                    <th class="th-edit">
                                        Tanggal Keluar <span style="color:#FF0033;font-weight:900;">CCIE</span>
                                    </th>



                                    <th class="th-default">Tujuan</th>
                                    <th class="th-default">Route</th>
                                    <th class="th-default">Pulau</th>
                                    <th class="th-default">Area</th>

                                    <th class="th-default">Via Kirim</th>
                                    <th class="th-oren">Dist Channel</th>
                                    <th class="th-oren">Kategori Ekspedisi</th>
                                    <th class="th-oren">Ekspedisi</th>


                                    <th class="th-oren">Lead Time</th>
                                    <th class="th-oren">Nama Driver</th>
                                    <th class="th-oren">No Pol</th>
                                    <th class="th-oren">Mobil</th>
                                    <th class="th-system">Total Qty</th>
                                    <th class="th-system">Nilai Muatan</th>
                                    <th class="th-system">Biaya Kirim</th>

                                    <th class="th-system">CR (%)</th>









                                    <th class="th-system">Status Mobil</th>

                                    <th class="th-system">Lama Waktu Pencarian</th>
                                    <th class="th-system">SLA Dapat Mobil</th>



                                    <th class="th-system">Lama Di KACS</th>
                                    <th class="th-system">Status KACS</th>
                                    <th class="th-system">SLA Loading</th>


                                    <th class="th-system">Lama Di Sentul</th>
                                    <th class="th-system">Status Sentul</th>
                                    <th class="th-system">SLA Loading Sentul</th>



                                    <th class="th-system">Lama Di CCIE</th>
                                    <th class="th-system">Status CCIE</th>
                                    <th class="th-system">SLA Loading CCIE</th>


                                    <th class="th-default">Shipping Point</th>

                                    <th class="th-system">Kelengkapan Data</th>

                                    <th class="th-default" style="min-width:130px;">Hapus</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($logistik as $r)
                                <tr class="autosave-row" data-id="{{ $r->id }}">
                                    <form class="d-none" id="form-update-{{ $r->id }}" action="{{ route('spvplanner.update', $r->id) }}" method="POST">
                                        @csrf
                                        @method('PUT')
                                    </form>


                                    <td>{{ $r->create_tgl ? \Carbon\Carbon::parse($r->create_tgl)->format('d/m/Y H:i') : '-' }}</td>
                                    <td><input type="text" form="form-update-{{ $r->id }}" name="planner" value="{{ $r->planner }}"></td>
                                    <td><input type="text" form="form-update-{{ $r->id }}" name="no_shipment" class="row-no-shipment" value="{{ $r->no_shipment }}"></td>

                                    <td>

                                        <input type="date" form="form-update-{{ $r->id }}" name="tanggal_naik_logistik" value="{{ $r->tanggal_naik_logistik ? date('Y-m-d', strtotime($r->tanggal_naik_logistik)) : '' }}">
                                    </td>
                                    <td>
                                        <input type="date" form="form-update-{{ $r->id }}" name="rencana_kirim" value="{{ $r->rencana_kirim ? date('Y-m-d', strtotime($r->rencana_kirim)) : '' }}">
                                    </td>

                                    <td>
                                        <input type="date" form="form-update-{{ $r->id }}" name="tanggal_dpt_unit" value="{{ $r->tanggal_dpt_unit ? date('Y-m-d', strtotime($r->tanggal_dpt_unit)) : '' }}">
                                    </td>
                                    <td>
                                        <input type="date" form="form-update-{{ $r->id }}" name="planning_loading" value="{{ $r->planning_loading ? date('Y-m-d', strtotime($r->planning_loading)) : '' }}">
                                    </td>
                                    <td>
                                        <input type="date" form="form-update-{{ $r->id }}" name="tanggal_tiba_gudang" value="{{ $r->tanggal_tiba_gudang ? date('Y-m-d', strtotime($r->tanggal_tiba_gudang)) : '' }}">
                                    </td>

                                    <td>
                                        <input type="date" form="form-update-{{ $r->id }}" name="tanggal_keluar_gudang" value="{{ $r->tanggal_keluar_gudang ? date('Y-m-d', strtotime($r->tanggal_keluar_gudang)) : '' }}">
                                    </td>
                                    <td>
                                        <input type="date" form="form-update-{{ $r->id }}" name="planning_loading_2" value="{{ $r->planning_loading_2 ? date('Y-m-d', strtotime($r->planning_loading_2)) : '' }}">
                                    </td>
                                    <td>
                                        <input type="date" form="form-update-{{ $r->id }}" name="tanggal_tiba_gudang_2" value="{{ $r->tanggal_tiba_gudang_2 ? date('Y-m-d', strtotime($r->tanggal_tiba_gudang_2)) : '' }}">
                                    </td>
                                    <td>
                                        <input type="date" form="form-update-{{ $r->id }}" name="tanggal_keluar_gudang_2" value="{{ $r->tanggal_keluar_gudang_2 ? date('Y-m-d', strtotime($r->tanggal_keluar_gudang_2)) : '' }}">
                                    </td>
                                    <td>
                                        <input type="date" form="form-update-{{ $r->id }}" name="planning_loading_3" value="{{ $r->planning_loading_3 ? date('Y-m-d', strtotime($r->planning_loading_3)) : '' }}">
                                    </td>
                                    <td>
                                        <input type="date" form="form-update-{{ $r->id }}" name="tanggal_tiba_gudang_3" value="{{ $r->tanggal_tiba_gudang_3 ? date('Y-m-d', strtotime($r->tanggal_tiba_gudang_3)) : '' }}">
                                    </td>
                                    <td>
                                        <input type="date" form="form-update-{{ $r->id }}" name="tanggal_keluar_gudang_3" value="{{ $r->tanggal_keluar_gudang_3 ? date('Y-m-d', strtotime($r->tanggal_keluar_gudang_3)) : '' }}">
                                    </td>

                                    <!-- <td class="fw-semibold">{{ $r->tujuan }}</td>
                                <td>{{ $r->route }}</td>
                                <td>{{ $r->pulau }}</td> -->

                                <td>
    <select
        form="form-update-{{ $r->id }}"
        name="tujuan"
        class="row-tujuan select2-row"
    >
        <option value="">-- Pilih --</option>

        @foreach($tujuanList as $t)
            <option
                value="{{ $t }}"
                {{ $r->tujuan == $t ? 'selected' : '' }}
            >
                {{ $t }}
            </option>
        @endforeach

        {{-- Kalau tujuan lama tidak ada di tujuanfillterr --}}
        @if($r->tujuan && !$tujuanList->contains($r->tujuan))
            <option value="{{ $r->tujuan }}" selected>
                {{ $r->tujuan }} (lama)
            </option>
        @endif
    </select>
</td>
                                <td data-required="true" data-label="Route">
<select form="form-update-{{ $r->id }}" name="route" class="row-route select2-row">
        <option value="">-- Pilih --</option>
        @foreach($routeList as $rt)
            <option value="{{ $rt }}" {{ $r->route == $rt ? 'selected' : '' }}>{{ $rt }}</option>
        @endforeach
        @if($r->route && !$routeList->contains($r->route))
            <option value="{{ $r->route }}" selected>{{ $r->route }} (lama)</option>
        @endif
    </select>
</td>
                            <td>
<select form="form-update-{{ $r->id }}" name="pulau" class="row-pulau select2-row">
        <option value="">-- Pilih --</option>
        @foreach($pulauList as $p)
            <option value="{{ $p }}" {{ $r->pulau == $p ? 'selected' : '' }}>{{ $p }}</option>
        @endforeach
        @if($r->pulau && !$pulauList->contains($r->pulau))
            <option value="{{ $r->pulau }}" selected>{{ $r->pulau }} (lama)</option>
        @endif
    </select>
</td>

                                    <td>
<select form="form-update-{{ $r->id }}" name="area" class="row-pulau select2-row">
        <option value="">-- Pilih --</option>
        @foreach($areas as $a)
            <option value="{{ $a }}" {{ $r->area == $a ? 'selected' : '' }}>{{ $a }}</option>
        @endforeach
        @if($r->area && !$areas->contains($r->area))
            <option value="{{ $r->area }}" selected>{{ $r->area }} (lama)</option>
        @endif
    </select>
</td>
                                    <td><input type="text" form="form-update-{{ $r->id }}" name="via_kirim" value="{{ $r->via_kirim }}"></td>

                                    <td>
<select form="form-update-{{ $r->id }}" name="dist_channel" class="row-pulau select2-row">
        <option value="">-- Pilih --</option>
        @foreach($distChannelList as $dc)
            <option value="{{ $dc }}" {{ $r->dist_channel == $dc ? 'selected' : '' }}>{{ $dc }}</option>
        @endforeach
        @if($r->dist_channel && !$distChannelList->contains($r->dist_channel))
            <option value="{{ $r->dist_channel }}" selected>{{ $r->dist_channel }} (lama)</option>
        @endif
    </select>
</td>
                                    <td><input type="text" form="form-update-{{ $r->id }}" name="kategori_ekspedisi" value="{{ $r->kategori_ekspedisi }}"></td>
                                <td data-required="true" data-label="Ekspedisi">
<select form="form-update-{{ $r->id }}" name="ekpedisi" class="row-ekpedisi select2-row">
        <option value="">-- Pilih --</option>
        @foreach($ekpedisiList as $e)
            <option value="{{ $e }}" {{ $r->ekpedisi == $e ? 'selected' : '' }}>{{ $e }}</option>
        @endforeach
        {{-- jaga-jaga kalau value lama tidak ada di list, biar tidak hilang --}}
        @if($r->ekpedisi && !$ekpedisiList->contains($r->ekpedisi))
            <option value="{{ $r->ekpedisi }}" selected>{{ $r->ekpedisi }} (lama)</option>
        @endif
    </select>
</td>

                                    <td><input type="text" form="form-update-{{ $r->id }}" name="transport_lead_time" value="{{ $r->transport_lead_time }}"></td>

                                    <td data-required="true" data-label="Nama Driver"><input type="text" form="form-update-{{ $r->id }}" name="nama_driver" value="{{ $r->nama_driver }}"></td>

                                    <td data-required="true" data-label="No Pol"><input type="text" form="form-update-{{ $r->id }}" name="no_pol" value="{{ $r->no_pol }}"></td>




<td data-required="true" data-label="Mobil">
<select form="form-update-{{ $r->id }}" name="mobil" class="row-mobil select2-row">
        <option value="">-- Pilih --</option>
        @foreach($mobilList as $m)
            <option value="{{ $m }}" {{ $r->mobil == $m ? 'selected' : '' }}>{{ $m }}</option>
        @endforeach
        @if($r->mobil && !$mobilList->contains($r->mobil))
            <option value="{{ $r->mobil }}" selected>{{ $r->mobil }} (lama)</option>
        @endif
    </select>
</td>

                                    <td>
                                        <input
                                            type="number"
                                            form="form-update-{{ $r->id }}"
                                            name="total_do_qty_car"
                                            value="{{ $r->total_do_qty_car }}">
                                    </td>
                                    <td>
                                        <input type="text"
                                            form="form-update-{{ $r->id }}"
                                            name="nilai_muatan"
                                            class="row-nilai-muatan input-rupiah"
                                            value="{{ $r->nilai_muatan }}">
                                    </td>

                                    <td>
                                        <input type="text"
                                            form="form-update-{{ $r->id }}"
                                            name="biaya_kirim"
                                            class="row-biaya-kirim input-rupiah"
                                            value="{{ $r->biaya_kirim }}">
                                    </td>

                                    <td>
                                        <input type="text"
                                            form="form-update-{{ $r->id }}"
                                            name="cr"
                                            class="row-cr"
                                            value="{{ is_numeric($r->cr) ? number_format((float)$r->cr,4) : $r->cr }}" readonly
                                            style="background:#f1f5f9;color:#0284c7;font-weight:600;">
                                    </td>




                                    <td>
                                        @php

                                        if (!empty($r->tanggal_dpt_unit)) {
                                        $statusMobil = 'SUDAH DAPAT';
                                        $badgeClass = 'bg-success text-white';
                                        } else {
                                        $statusMobil = 'BELUM DAPAT';
                                        $badgeClass = 'bg-danger text-white';
                                        }

                                        @endphp

                                        <span class="badge-status {{ $badgeClass }}">
                                            {{ $statusMobil }}
                                        </span>
                                    </td>
                                    <td class="text-center fw-medium text-primary">{{ $r->lama_waktu_pencarian }}</td>
                                    <td>
                                        @php

                                        if ($r->rencana_kirim && $r->tanggal_dpt_unit) {

                                        $area = strtoupper(trim($r->area));

                                        $rencana = strtotime(date('Y-m-d', strtotime($r->rencana_kirim)));
                                        $dptUnit = strtotime(date('Y-m-d', strtotime($r->tanggal_dpt_unit)));

                                        $selisihHari = floor(($dptUnit - $rencana) / 86400);

                                        // Tentukan batas SLA berdasarkan area
                                        if ($area == 'JABODEBEK' || $area == 'JABODETABEK' || $area == 'BANTEN') {

                                        $batasHari = 0;

                                        } elseif ($area == 'JAWA_BARAT') {

                                        $batasHari = 1;

                                        } else {

                                        $batasHari = 2;

                                        }

                                        // Tentukan text badge
                                        if ($selisihHari > $batasHari) {

                                        $text = 'H+' . ($selisihHari - $batasHari);

                                        } else {

                                        $text = 'Sesuai SLA';

                                        }

                                        } else {

                                        $text = '-';

                                        }

                                        @endphp

                                        <span class="badge-status {{ str_contains($text, 'H+') ? 'bg-danger text-white' : 'bg-success text-white' }}">
                                            {{ $text }}
                                        </span>
                                    </td>

                                    <!-- KOLOM 1: DURASI BERSIH (Lama Loading) -->
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

                                        @if(empty($statusText) || $statusText == '-')
                                        <span class="badge gray">-</span>

                                        @elseif(strtolower($statusText) == 'on time')
                                        <span class="badge green">On Time</span>

                                        @elseif(strtolower($statusText) == 'delay')
                                        <span class="badge red">Delay</span>

                                        @else
                                        <span class="badge gray">{{ $statusText }}</span>
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
                                        <span class="badge red">{{ $slaLoadingClean }}</span>

                                        @else
                                        <span class="badge bg-secondary">{{ $slaLoadingClean }}</span>
                                        @endif
                                    </td>
                                    <!-- KOLOM 1: DURASI BERSIH (Lama Loading 2) -->
                                    <!-- KOLOM 1: DURASI BERSIH (Lama Loading 2) -->
                                    <!-- KOLOM 1: DURASI (Tetap karena sudah aman) -->
                                    <td class="text-center">
                                        @php
                                        $planning2 = $r->planning_loading_2;
                                        $tiba2 = $r->tanggal_tiba_gudang_2;
                                        $durasiText2 = '-';

                                        if (!empty($planning2) && !empty($tiba2)) {
                                        $start2 = \Carbon\Carbon::parse($planning2);
                                        $end2 = \Carbon\Carbon::parse($tiba2);

                                        $desimalHari2 = $start2->diffInMinutes($end2) / 1440;

                                        $hari2 = floor($desimalHari2);
                                        $sisaJam2 = ($desimalHari2 - $hari2) * 24;
                                        $jam2 = round($sisaJam2);

                                        if ($jam2 == 24) {
                                        $jam2 = 0;
                                        $hari2 += 1;
                                        }

                                        if ($hari2 > 0 && $jam2 > 0) {
                                        $durasiText2 = "{$hari2} Hari {$jam2} Jam";
                                        } elseif ($hari2 > 0) {
                                        $durasiText2 = "{$hari2} Hari";
                                        } elseif ($jam2 > 0) {
                                        $durasiText2 = "{$jam2} Jam";
                                        } else {
                                        $durasiText2 = "0 Jam";
                                        }
                                        }
                                        @endphp

                                        {{ $durasiText2 }}
                                    </td>

                                    <!-- KOLOM 2: STATUS GUDANG (On Time / Delay 2) -->
                                    <td class="text-center">
                                        @php
                                        $statusText2 = '-';

                                        if (!empty($r->planning_loading_2) && !empty($r->tanggal_tiba_gudang_2)) {
                                        $startDay2 = \Carbon\Carbon::parse($r->planning_loading_2)->startOfDay();
                                        $endDay2 = \Carbon\Carbon::parse($r->tanggal_tiba_gudang_2)->startOfDay();

                                        $statusText2 = $endDay2->gt($startDay2) ? 'Delay' : 'On Time';
                                        }
                                        @endphp

                                        @if(empty($statusText2) || $statusText2 == '-')
                                        <span class="badge gray">-</span>

                                        @elseif(strtolower($statusText2) == 'on time')
                                        <span class="badge green">On Time</span>

                                        @elseif(strtolower($statusText2) == 'delay')
                                        <span class="badge red">Delay</span>

                                        @else
                                        <span class="badge gray">{{ $statusText2 }}</span>
                                        @endif
                                    </td>

                                    <!-- KOLOM 3: SLA LOADING (Sesuai SLA / H+ 2) -->
                                    <td class="text-center">
                                        @php
                                        $slaLoadingClean2 = '-';

                                        if (!empty($r->planning_loading_2) && !empty($r->tanggal_tiba_gudang_2)) {
                                        $start2 = \Carbon\Carbon::parse($r->planning_loading_2)->startOfDay();
                                        $end2 = \Carbon\Carbon::parse($r->tanggal_tiba_gudang_2)->startOfDay();

                                        if ($end2->gt($start2)) {
                                        $selisihHari2 = $start2->diffInDays($end2);
                                        $slaLoadingClean2 = "H+{$selisihHari2}";
                                        } else {
                                        $slaLoadingClean2 = 'Sesuai SLA';
                                        }
                                        }
                                        @endphp

                                        @if($slaLoadingClean2 === 'Sesuai SLA')
                                        <span class="badge bg-success">Sesuai SLA</span>
                                        @elseif(str_contains($slaLoadingClean2, 'H+'))
                                        <span class="badge red">{{ $slaLoadingClean2 }}</span>

                                        @else
                                        <span class="badge bg-secondary">{{ $slaLoadingClean2 }}</span>
                                        @endif
                                    </td>


                                    <!-- KOLOM 1: DURASI BERSIH (Lama Loading 3) -->
                                    <!-- KOLOM 1: DURASI BERSIH (Lama Loading 3) -->
                                    <!-- KOLOM 1: DURASI (Tetap seperti kode kamu karena sudah benar) -->
                                    <td class="text-center">
                                        @php
                                        $planning3 = $r->planning_loading_3;
                                        $tiba3 = $r->tanggal_tiba_gudang_3;
                                        $durasiText3 = '-';

                                        if (!empty($planning3) && !empty($tiba3)) {
                                        $start3 = \Carbon\Carbon::parse($planning3);
                                        $end3 = \Carbon\Carbon::parse($tiba3);

                                        $desimalHari3 = $start3->diffInMinutes($end3) / 1440;
                                        $hari3 = floor($desimalHari3);
                                        $sisaJam3 = ($desimalHari3 - $hari3) * 24;
                                        $jam3 = round($sisaJam3);

                                        if ($jam3 == 24) {
                                        $jam3 = 0;
                                        $hari3 += 1;
                                        }

                                        if ($hari3 > 0 && $jam3 > 0) {
                                        $durasiText3 = "{$hari3} Hari {$jam3} Jam";
                                        } elseif ($hari3 > 0) {
                                        $durasiText3 = "{$hari3} Hari";
                                        } elseif ($jam3 > 0) {
                                        $durasiText3 = "{$jam3} Jam";
                                        } else {
                                        $durasiText3 = "0 Jam";
                                        }
                                        }
                                        @endphp

                                        {{ $durasiText3 }}
                                    </td>

                                    <!-- KOLOM 2: STATUS GUDANG (On Time / Delay 3) -->
                                    <td class="text-center">
                                        @php
                                        $statusText3 = '-';

                                        if (!empty($r->planning_loading_3) && !empty($r->tanggal_tiba_gudang_3)) {
                                        $startDay3 = \Carbon\Carbon::parse($r->planning_loading_3)->startOfDay();
                                        $endDay3 = \Carbon\Carbon::parse($r->tanggal_tiba_gudang_3)->startOfDay();

                                        $statusText3 = $endDay3->gt($startDay3) ? 'Delay' : 'On Time';
                                        }
                                        @endphp

                                        @if(empty($statusText3) || $statusText3 == '-')
                                        <span class="badge gray">-</span>

                                        @elseif(strtolower($statusText3) == 'on time')
                                        <span class="badge green">On Time</span>

                                        @elseif(strtolower($statusText3) == 'delay')
                                        <span class="badge red">Delay</span>

                                        @else
                                        <span class="badge gray">{{ $statusText3 }}</span>
                                        @endif
                                    </td>

                                    <!-- KOLOM 3: SLA LOADING (Sesuai SLA / H+ 3) -->
                                    <td class="text-center">
                                        @php
                                        $slaLoadingClean3 = '-';

                                        if (!empty($r->planning_loading_3) && !empty($r->tanggal_tiba_gudang_3)) {
                                        $start3 = \Carbon\Carbon::parse($r->planning_loading_3)->startOfDay();
                                        $end3 = \Carbon\Carbon::parse($r->tanggal_tiba_gudang_3)->startOfDay();

                                        if ($end3->gt($start3)) {
                                        $selisihHari3 = $start3->diffInDays($end3);
                                        $slaLoadingClean3 = "H+{$selisihHari3}";
                                        } else {
                                        // Jika tanggalnya sama atau bahkan lebih awal, langsung masuk Sesuai SLA
                                        $slaLoadingClean3 = 'Sesuai SLA';
                                        }
                                        }
                                        @endphp

                                        @if($slaLoadingClean3 === 'Sesuai SLA')
                                        <span class="badge bg-success">Sesuai SLA</span>
                                        @elseif(str_contains($slaLoadingClean3, 'H+'))
                                        <span class="badge red">{{ $slaLoadingClean3 }}</span>

                                        @else
                                        <span class="badge bg-secondary">{{ $slaLoadingClean3 }}</span>
                                        @endif
                                    </td>

                                    <td class="text-center">{{ $r->route ? explode('-', trim($r->route))[0] : '-' }}</td>

                                    {{-- BADGE KELENGKAPAN DATA (diisi JS) --}}
                                    <td>
                                        <span class="badge completeness-badge gray">-</span>
                                    </td>

<td>
    <div class="btn-action">
        <a href="{{ route('spvplanner.delete',$r->id) }}"
            class="btn btn-danger btn-sm px-2 d-flex align-items-center gap-1"
            onclick="return confirm('Hapus data ini?')">
            <i class="fa-solid fa-trash"></i> Del
        </a>
    </div>
</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

            
                <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    
                <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

            
                <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
            </div>
            <script>

                    // ==========================================================
    // MASTER TARIF (preload sekali saat halaman dibuka)
    // ==========================================================
    const tarifList = @json($tarifPengiriman);

    function normalizeStr(v) {
        if (!v) return '';
        v = String(v).replace(/\u00A0/g, ' ');
        v = v.replace(/\s*-\s*/g, '-');
        v = v.replace(/\s+/g, ' ').trim();
        return v.toLowerCase();
    }

    function normalizeMobilStr(v) {
        if (!v) return '';
        v = String(v).replace(/\u00A0/g, ' ');
        v = v.replace(/\s+/g, ' ').trim();
        return v.toLowerCase();
    }

    function cariTarif(route, mobil, ekpedisi) {
        if (!route || !mobil) return null;

        const routeKey    = normalizeStr(route);
        const mobilKey    = normalizeMobilStr(mobil);
        const ekpedisiKey = ekpedisi ? normalizeStr(ekpedisi) : '';

        const candidates = tarifList.filter(t => normalizeStr(t.route) === routeKey);

        if (candidates.length === 0) return null;

        if (ekpedisiKey !== '') {
            const strict = candidates.find(t =>
                normalizeStr(t.ekpedisi) === ekpedisiKey &&
                normalizeMobilStr(t.mobil).startsWith(mobilKey)
            );
            if (strict) return strict;
        }

        return candidates.find(t =>
            normalizeMobilStr(t.mobil).startsWith(mobilKey)
        ) || null;
    }

                $(document).ready(function() {
                    $.fn.dataTable.ext.type.search.html = function(data) {
                        return $('<div>').html(data).text();
                    };

                    // ========================================================
                    // 1. HELPER: Mengubah Angka DB (.00) ke Format Rupiah Bulat
                    // ========================================================
                    function formatKeRupiah(angka) {
                        if (!angka) return '';

                        // Potong desimal database .00 di buntut jika ada (ex: 24159052.00 -> 24159052)
                        let stringMurni = String(angka).split('.')[0];

                        // Ambil hanya karakter angka saja
                        let angkaMurni = stringMurni.replace(/[^0-9]/g, '');

                        if (angkaMurni) {
                            // Beri titik ribuan dan tambahkan prefix Rp
                            return 'Rp ' + String(angkaMurni).replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                        }
                        return '';
                    }

                    // Helper untuk mengambil nilai angka murni agar bisa dihitung matematika
                    function ambilAngkaMurni(teks) {
                        if (!teks) return 0;
                        // Buang 'Rp' dan semua titik ribuan
                        let bersih = String(teks).replace(/[^0-9]/g, '');
                        return parseFloat(bersih) || 0;
                    }







                    // ========================================================
                    // 2. FUNGSI PENYULAP: Format Otomatis Saat Data Dimuat
                    // ========================================================
                    // FIX: hanya proses row yang sedang TAMPIL di halaman aktif
                    // (DataTables kasih display:none ke row di luar page),
                    // bukan semua row di DOM. Ini yang bikin tiap draw() -
                    // termasuk saat klik alert - jadi berat kalau data ratusan/ribuan baris.
                    function jalankanMaskingRupiahTabel() {
                        $('#tablePlanner tbody tr:visible .row-nilai-muatan, #tablePlanner tbody tr:visible .row-biaya-kirim, .modal-nilai-muatan, .modal-biaya-kirim').each(function() {
                            let v = $(this).val();
                            // Jika data masih berupa angka mentah database, langsung sulap ke Rupiah
                            if (v && !v.includes('Rp')) {
                                $(this).val(formatKeRupiah(v));
                            }
                        });
                    }


                    // ========================================================
                    // AUTO ISI RENCANA KIRIM = TANGGAL_NAIK_LOGISTIK + LEAD TIME
                    // ========================================================
                    function isDarat(val) {
                        return (val || '').trim().toUpperCase() === 'DARAT';
                    }

                    function hitungRencanaKirimJS(tglNaik, transportLeadTime) {
                        if (!tglNaik) return '';

                        let d = new Date(tglNaik);
                        let leadTime = parseInt(transportLeadTime) || 0;

                        d.setDate(d.getDate() + leadTime);

                        let yyyy = d.getFullYear();
                        let mm = String(d.getMonth() + 1).padStart(2, '0');
                        let dd = String(d.getDate()).padStart(2, '0');

                        return `${yyyy}-${mm}-${dd}`;
                    }

                    // ========================================================
                    // 3. INITIALIZATION DATATABLES
                    // ========================================================
                    var table = $('#tablePlanner').DataTable({
                        scrollX: true,
                        autoWidth: false,
                        pageLength: 10,
                        columnDefs: [{
                            className: "dt-center",
                            targets: [0, 21, 23, 24, 27, 28, 29, 32, 33, 34, 37, 38, 39]
                        }],
initComplete: function() {

    setTimeout(function() {

        jalankanMaskingRupiahTabel();
        hitungSemuaCostRatioTabel();
        initSelect2Row();
        updateFieldStatusPlanner(true); // true = tampilkan toast sekali di awal kalau ada yang kosong
table.columns.adjust();
    }, 0);

},

                        drawCallback: function() {

                            jalankanMaskingRupiahTabel();
                            initSelect2Row();
                            updateFieldStatusPlanner(false);
                             this.api().columns.adjust(); 
                        }

                    });
        

                    // ========================================================
                    // 4. FUNGSI HITUNG CR (SAMA SEPERTI DATA_LOGISTIK)
                    // ========================================================
                    function hitungSemuaCostRatioTabel() {

                        var shipmentGroups = {};
                        var dt = $('#tablePlanner').DataTable();

                        // PASS 1: total nilai muatan (SUM) & biaya kirim (MAX) per shipment
                        dt.rows({
                            search: 'applied'
                        }).every(function() {

                            var row = $(this.node());
                            var noShipment = (row.find('.row-no-shipment').val() || '').trim();

                            if (!noShipment) return;

                            var muatan = ambilAngkaMurni(row.find('.row-nilai-muatan').val());
                            var biaya = ambilAngkaMurni(row.find('.row-biaya-kirim').val());

                            if (!shipmentGroups[noShipment]) {
                                shipmentGroups[noShipment] = {
                                    totalMuatan: 0,
                                    totalBiaya: 0
                                };
                            }

                            shipmentGroups[noShipment].totalMuatan += muatan;

                            shipmentGroups[noShipment].totalBiaya = Math.max(
                                shipmentGroups[noShipment].totalBiaya,
                                biaya
                            );
                        });

                        // PASS 2: hitung & isi CR tiap baris (proporsional terhadap kontribusi muatan)
                        dt.rows({
                            search: 'applied'
                        }).every(function() {

                            var row = $(this.node());
                            var noShipment = (row.find('.row-no-shipment').val() || '').trim();
                            var crInput = row.find('.row-cr');
                            var costRatio = 0;

                            if (noShipment && shipmentGroups[noShipment]) {

                                var totalMuatan = shipmentGroups[noShipment].totalMuatan;
                                var totalBiaya = shipmentGroups[noShipment].totalBiaya;

                                var nilaiMuatanBaris = ambilAngkaMurni(row.find('.row-nilai-muatan').val());

                                if (totalMuatan > 0 && nilaiMuatanBaris > 0) {

                                    var totalCR = (totalBiaya / totalMuatan) * 100;
                                    var kontribusi = nilaiMuatanBaris / totalMuatan;

                                    costRatio = kontribusi * totalCR;
                                }

                                crInput.val(costRatio > 0 ? costRatio.toFixed(4) + '%' : '0.0000%');

                            } else {

                                var nilaiMuatanMurni = ambilAngkaMurni(row.find('.row-nilai-muatan').val());
                                var biayaMurni = ambilAngkaMurni(row.find('.row-biaya-kirim').val());

                                if (nilaiMuatanMurni > 0) {
                                    costRatio = (biayaMurni / nilaiMuatanMurni) * 100;
                                }

                                crInput.val(costRatio > 0 ? costRatio.toFixed(4) + '%' : '-');
                            }
                        });
                    }

                    // ========================================================
                    // 5. EVENT LISTENER: Ketik Otomatis Berformat Rupiah + Hitung CR
                    // ========================================================
                    $(document).on('input', '.row-nilai-muatan', function() {
                        $(this).val(formatKeRupiah(ambilAngkaMurni($(this).val())));
                        hitungSemuaCostRatioTabel();
                    });

                    $(document).on('input', '.row-biaya-kirim', function() {
                        $(this).val(formatKeRupiah(ambilAngkaMurni($(this).val())));
                        hitungSemuaCostRatioTabel();
                    });

                    // ========================================================
                    // 6. RUMUS MODAL: "ADD NEW SHIPMENT"
                    // ========================================================
                    $(document).on('input', '.modal-nilai-muatan, .modal-biaya-kirim', function() {
                        var muatanModal = ambilAngkaMurni($('.modal-nilai-muatan').val());
                        var biayaModal = ambilAngkaMurni($('.modal-biaya-kirim').val());

                        var crModal = 0;
                        if (muatanModal > 0) {
                            crModal = (biayaModal / muatanModal) * 100;
                        }
                        $('.modal-cr').val(crModal.toFixed(4) + '%');
                    });

                    // ========================================================
                    // 7. PROTEKSI BACKEND: Kembalikan ke Angka Murni Sebelum Disimpan
                    // ========================================================
                    $('form').on('submit', function() {
                        $('.row-nilai-muatan, .row-biaya-kirim, .modal-nilai-muatan, .modal-biaya-kirim').each(function() {
                            let nilaiSekarang = $(this).val();
                            if (nilaiSekarang) {
                                $(this).val(nilaiSekarang.replace(/[^0-9]/g, ''));
                            }
                        });
                    });
                    // =========================
                    // SELECT2
                    // =========================
                    $('#filterPlanner, #filterArea').select2({
                        theme: 'bootstrap-5',
                        width: '100%',
                        placeholder: function() {
                            return $(this).find('option:first').text();
                        },
                        allowClear: true
                    });

// FIX: hanya init select2 untuk row yang sedang tampil di halaman aktif,
// bukan semua row di DOM. Row di luar page (display:none) tidak perlu
// select2 aktif sampai user pindah halaman & drawCallback jalan lagi.
function initSelect2Row() {
    $('#tablePlanner tbody tr:visible .select2-row').each(function() {
        if (!$(this).hasClass('select2-hidden-accessible')) {
            $(this).select2({
                theme: 'bootstrap-5',
                width: '150px',
                dropdownAutoWidth: true,
                dropdownParent: $('body'), // biar dropdown tidak kepotong scrollX DataTables
                placeholder: 'Cari...',
                allowClear: true
            });

            // Pasang listener auto-isi biaya kirim LANGSUNG ke elemen ini
            // kalau dia kolom route / mobil / ekpedisi
            if ($(this).is('.row-route, .row-mobil, .row-ekpedisi')) {
                $(this).off('select2:select.autotarif').on('select2:select.autotarif', function() {
                    console.log('AUTOTARIF TERPICU pada:', $(this).attr('name'), '=', $(this).val());
                    let row = $(this).closest('tr');
                    cariBiayaKirimRow(row);
                });
            }

            // Update badge kelengkapan langsung setelah pilihan select2 berubah
            if ($(this).is('.row-mobil, .row-ekpedisi, .row-route')) {
                $(this).off('select2:select.completeness select2:clear.completeness')
                    .on('select2:select.completeness select2:clear.completeness', function() {
                        updateFieldStatusPlanner(false);
                    });
            }
        }
    });
}

                    // ======================
                    // FILTER STATE (satu deklarasi saja untuk masing-masing variabel)
                    // ======================
                    var areaFilter = '';
                    var plannerFilter = '';
                    var createTglFilter = '';

                    $('#filterArea').select2({
                        theme: 'bootstrap-5',
                        width: '100%',
                        placeholder: 'Cari Area...',
                        allowClear: true
                    });

                    $('#filterArea').on('change', function() {
                        areaFilter = $(this).val();
                        table.draw();
                    });

                    $('#filterPlanner').on('change', function() {
                        plannerFilter = $(this).val();
                        table.draw();
                    });

                    $('#filterCreateTgl').on('change', function() {
                        createTglFilter = $(this).val();
                        table.draw();
                    });

                    $('#btnExport').on('click', function(e) {

                        e.preventDefault();

                        let planner = $('#filterPlanner').val() || '';
                        let area = $('#filterArea').val() || '';

                        let url = "{{ route('planner.export') }}" +
                            "?planner=" + encodeURIComponent(planner) +
                            "&area=" + encodeURIComponent(area);

                        window.location.href = url;
                    });

                    // ========================================================
                    // FIX #1 (BUG UTAMA): sebelumnya ada .push() di dalam .push()
                    // yang terdaftar ulang setiap kali DataTables mengevaluasi
                    // sebuah baris -> array ext.search tumbuh terus (eksponensial)
                    // dan bikin browser makin lag/freeze. Sekarang HANYA didaftarkan
                    // SEKALI: reset dulu, lalu daftarkan 1 filter keyword + 1 filter
                    // area/planner/createTgl. Tidak ada push bersarang lagi.
                    // ========================================================
                    $.fn.dataTable.ext.search = [];

                    // FILTER 1: keyword search (kolom search bawaan DataTables)
                    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {

                        if (settings.nTable.id !== 'tablePlanner') {
                            return true;
                        }

                        var keyword = $('#tablePlanner_filter input').val().toLowerCase();

                        if (keyword == '') return true;

                        var row = $(table.row(dataIndex).node());

                        var semuaData = row.text().toLowerCase();

                        row.find('input,select,textarea').each(function() {
                            semuaData += ' ' + ($(this).val() || '').toLowerCase();
                        });

                        return semuaData.indexOf(keyword) !== -1;
                    });

                    // FILTER 2: area / planner / tanggal import
                    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {

                        if (settings.nTable.id !== 'tablePlanner') {
                            return true;
                        }

                        // FILTER AREA
                        if (areaFilter !== '') {
                            var areaValue = $(table.row(dataIndex).node())
                                .find('[name="area"]')
                                .val();

                            areaValue = (areaValue || '').trim();

                            if (areaValue !== areaFilter) {
                                return false;
                            }
                        }

                        // FILTER PLANNER
                        if (plannerFilter !== '') {
                            var plannerValue = $(table.row(dataIndex).node())
                                .find('input[name="planner"]')
                                .val();

                            plannerValue = (plannerValue || '').trim();

                            if (plannerValue !== plannerFilter) {
                                return false;
                            }
                        }

                        // FILTER CREATE TGL
                        if (createTglFilter !== '') {
                            var createTglText = (data[0] || '').trim();

                            if (createTglText !== '-') {
                                var parts = createTglText.split(' ')[0].split('/');
                                var tanggalRow = parts[2] + '-' + parts[1] + '-' + parts[0];

                                if (tanggalRow !== createTglFilter) {
                                    return false;
                                }
                            }
                        }

                        return true;
                    });

                    $('#tablePlanner_filter input').off().on('keyup', function() {
                        table.draw();
                    });




                    // ======================================
                    // REFRESH DROPDOWN JIKA PLANNER DIUBAH
                    // ======================================

                    $(document).on(
                        'change',
                        'input[name="planner"]',
                        function() {

                            loadPlannerFilter();

                        }
                    );



                    // Jalankan sekali di awal saat halaman pertama kali dibuka
                    jalankanMaskingRupiahTabel();


         function saveRow(id) {

let row = $('tr[data-id="' + id + '"]');

return $.ajax({

    url: '/planner/autosave-row/' + id,
    type: 'POST',

    data: {

        _token: '{{ csrf_token() }}',

        // ===== IDENTITAS =====
        planner: row.find('[name="planner"]').val(),
        no_shipment: row.find('[name="no_shipment"]').val(),

        // ===== TANGGAL UTAMA =====
        tanggal_naik_logistik: row.find('[name="tanggal_naik_logistik"]').val(),
        rencana_kirim: row.find('[name="rencana_kirim"]').val(),
        tanggal_dpt_unit: row.find('[name="tanggal_dpt_unit"]').val(),

        // ===== TANGGAL KACS =====
        planning_loading: row.find('[name="planning_loading"]').val(),
        tanggal_tiba_gudang: row.find('[name="tanggal_tiba_gudang"]').val(),
        tanggal_keluar_gudang: row.find('[name="tanggal_keluar_gudang"]').val(),

        // ===== TANGGAL SENTUL =====
        planning_loading_2: row.find('[name="planning_loading_2"]').val(),
        tanggal_tiba_gudang_2: row.find('[name="tanggal_tiba_gudang_2"]').val(),
        tanggal_keluar_gudang_2: row.find('[name="tanggal_keluar_gudang_2"]').val(),

        // ===== TANGGAL CCIE =====
        planning_loading_3: row.find('[name="planning_loading_3"]').val(),
        tanggal_tiba_gudang_3: row.find('[name="tanggal_tiba_gudang_3"]').val(),
        tanggal_keluar_gudang_3: row.find('[name="tanggal_keluar_gudang_3"]').val(),

        // ===== RUTE & LOKASI =====
        tujuan: row.find('[name="tujuan"]').val(),
        route: row.find('[name="route"]').val(),
        pulau: row.find('[name="pulau"]').val(),
        area: row.find('[name="area"]').val(),
        via_kirim: row.find('[name="via_kirim"]').val(),

        // ===== EKSPEDISI =====
        dist_channel: row.find('[name="dist_channel"]').val(),
        kategori_ekspedisi: row.find('[name="kategori_ekspedisi"]').val(),
        ekpedisi: row.find('[name="ekpedisi"]').val(),
        transport_lead_time: row.find('[name="transport_lead_time"]').val(),

        // ===== ARMADA / DRIVER =====
        nama_driver: row.find('[name="nama_driver"]').val(),
        no_pol: row.find('[name="no_pol"]').val(),
        mobil: row.find('[name="mobil"]').val(),
        total_do_qty_car: row.find('[name="total_do_qty_car"]').val(),

        // ===== NILAI / BIAYA =====
        nilai_muatan: ambilAngkaMurni(row.find('[name="nilai_muatan"]').val()),
        biaya_kirim: ambilAngkaMurni(row.find('[name="biaya_kirim"]').val()),
        cr: row.find('[name="cr"]').val()

    },

    success: function() {
        console.log("Saved " + id);
    },

    error: function(xhr) {
        console.log("Gagal save row " + id, xhr.status, xhr.responseText);
    }

});
}
                    function cariBiayaKirimRow(row) {
                        let route    = row.find('[name="route"]').val();
                        let mobil    = row.find('[name="mobil"]').val();
                        let ekpedisi = row.find('[name="ekpedisi"]').val();

                        if (!route || !mobil) return;

                        let tarif = cariTarif(route, mobil, ekpedisi);

                        if (tarif && tarif.biaya_kirim) {
                            let biayaInput = row.find('.row-biaya-kirim');
                            biayaInput.val(tarif.biaya_kirim).trigger('input');
                        } else {
                            console.log('Tarif tidak ditemukan untuk kombinasi ini');
                        }
                    }

            
           // Menyimpan id row yang datanya sudah diubah tapi belum di-save
let dirtyRows = new Set();

function updateUnsavedBadge() {
if (dirtyRows.size > 0) {
    $('#unsavedCount').text(dirtyRows.size).show();
} else {
    $('#unsavedCount').hide();
}
}

// Setiap ada perubahan di baris manapun, tandai row-nya "dirty"
// TIDAK langsung kirim ke server
$(document).on('change input',
'#tablePlanner input, #tablePlanner select, #tablePlanner textarea',
function() {
    let row = $(this).closest('tr');
    let id = row.data('id');
    if (id) {
        dirtyRows.add(id);
        updateUnsavedBadge();
    }
});

// Tombol Save All -> baru di sini semua row yang dirty dikirim
$('#btnSaveAll').on('click', function() {
if (dirtyRows.size === 0) {
    alert('Belum ada perubahan untuk disimpan.');
    return;
}

let btn = $(this);
btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...');

let ids = Array.from(dirtyRows);
let requests = ids.map(id => saveRow(id));

$.when.apply($, requests)
    .done(function() {
        dirtyRows.clear();
        updateUnsavedBadge();
        alert('Semua perubahan (' + ids.length + ' baris) berhasil disimpan!');
    })
    .fail(function() {
        alert('Sebagian data gagal disimpan, cek console.');
    })
    .always(function() {
        btn.prop('disabled', false)
           .html('<i class="fa-solid fa-floppy-disk"></i> Save <span id="unsavedCount" class="badge bg-danger rounded-pill" style="display:none;">0</span>');
    });
});

                    // ==========================================================
                    // ALERT CONTROL: cek 5 field wajib (Mobil, Ekspedisi, Route,
                    // Nama Driver, No Pol) — badge per-row, summary field, panel alert
                    // ==========================================================
                    function updateFieldStatusPlanner(showToast) {
                        showToast = showToast || false;

                        let alertList = [];
                        let missingSummary = {};

                        $('#tablePlanner tbody tr').each(function() {
                            let row = $(this);
                            let missingFields = [];

                            row.find('td[data-required="true"]').each(function() {
                                let td = $(this);
                                let field = td.find('input, select');
                                let val = (field.val() || '').toString().trim();
                                let filled = val !== '';
                                let label = td.data('label');

                                field.toggleClass('input-filled', filled)
                                     .toggleClass('input-empty', !filled);

                                if (!filled) {
                                    missingFields.push(label);
                                    missingSummary[label] = (missingSummary[label] || 0) + 1;
                                }
                            });

                            let badge = row.find('.completeness-badge');
                            let emptyCount = missingFields.length;

                            if (emptyCount === 0) {
                                badge.attr('class', 'badge completeness-badge green')
                                     .text('✅ Lengkap')
                                     .attr('title', 'Data lengkap');
                            } else {
                                let cls = emptyCount === 1 ? 'yellow' : emptyCount <= 3 ? 'orange' : 'red';
                                let text = '❌ ' + missingFields.join(', ');
                                badge.attr('class', 'badge completeness-badge ' + cls)
                                     .text(text)
                                     .attr('title', text);

                                alertList.push({
                                    id: row.data('id'),
                                    shipment: row.find('[name="no_shipment"]').val() || '(tanpa no shipment)',
                                    missing: missingFields,
                                    emptyCount: emptyCount
                                });
                            }
                        });

                        renderMissingFieldSummaryPlanner(missingSummary);
                        renderAlertControlPlanner(alertList);

                        if (showToast && alertList.length > 0) {
                            showToastMsgPlanner('⚠ ' + alertList.length + ' shipment belum lengkap datanya (Mobil / Ekspedisi / Route / Nama Driver / No Pol)');
                        }
                    }

                    function renderMissingFieldSummaryPlanner(missingSummary) {
                        let entries = Object.entries(missingSummary).sort((a, b) => b[1] - a[1]);

                        if (entries.length === 0) {
                            $('#missingFieldSummary').html('<span class="badge green">✅ Semua data lengkap</span>');
                            return;
                        }

                        let html = entries.map(function(e) {
                            return '<span class="badge red">' + e[0] + ': ' + e[1] + '</span>';
                        }).join(' ');

                        $('#missingFieldSummary').html(html);
                    }

                    function renderAlertControlPlanner(alertList) {
                        $('#alertControlCount').text(alertList.length + ' Alert');

                        if (alertList.length === 0) {
                            $('#alertControlList').html('<div class="p-2" style="color:#22c55e;">✅ Semua shipment sudah lengkap datanya</div>');
                            return;
                        }

                        alertList.sort(function(a, b) { return b.emptyCount - a.emptyCount; });

                        let html = alertList.map(function(a) {
                            let sev = a.emptyCount >= 4 ? 'red' : a.emptyCount >= 2 ? 'orange' : 'yellow';
                            return '' +
                                '<div class="alert-item" data-id="' + a.id + '">' +
                                    '<div class="alert-top">' +
                                        '<b>🚚 ' + a.shipment + '</b>' +
                                        '<span class="badge ' + sev + '">' + a.emptyCount + ' kosong</span>' +
                                    '</div>' +
                                    '<div class="alert-missing">Belum diisi: ' + a.missing.join(', ') + '</div>' +
                                '</div>';
                        }).join('');

                        $('#alertControlList').html(html);
                    }

                    // Klik item alert -> lompat & highlight row terkait di tabel
                    $(document).on('click', '.alert-item', function() {
                        let id = $(this).data('id');

                        // FIX: hanya reset & redraw kalau memang ada keyword aktif
                        // di search box. Sebelumnya table.draw() SELALU dipanggil,
                        // padahal drawCallback loop SEMUA row (masking rupiah,
                        // init select2, cek kelengkapan field) -> lambat kalau
                        // data banyak. Kalau search box kosong, skip draw sama sekali.
                        let currentKeyword = $('#tablePlanner_filter input').val();
                        if (currentKeyword) {
                            $('#tablePlanner_filter input').val('');
                            table.search('').draw();
                        }

                        setTimeout(function() {
                            let $row = $('#tablePlanner tr[data-id="' + id + '"]');
                            if ($row.length) {
                                $row.addClass('highlight-row');
                                $row.get(0).scrollIntoView({ behavior: 'smooth', block: 'center' });
                                setTimeout(function() { $row.removeClass('highlight-row'); }, 2000);
                            }
                        }, 150);
                    });

                    function showToastMsgPlanner(msg) {
                        let toast = $('<div class="toast"><strong>Perhatian</strong>' + msg + '</div>');
                        $('#toastContainer').append(toast);
                        setTimeout(function() {
                            toast.fadeOut(400, function() { toast.remove(); });
                        }, 6000);
                    }

                    // Update badge kelengkapan langsung saat 5 field target berubah
                    // (input text: nama_driver, no_pol — select2: mobil, ekpedisi, route sudah dihandle di initSelect2Row)
                    $(document).on('input', '[name="nama_driver"], [name="no_pol"]', function() {
                        updateFieldStatusPlanner(false);
                    });

                    // ========================================================
                    // 7. PROTEKSI BACKEND: Kembalikan ke Angka Murni Sebelum Disimpan
                    // ========================================================
                    $('form').on('submit', function() {
                        $('.row-nilai-muatan, .row-biaya-kirim, .modal-nilai-muatan, .modal-biaya-kirim').each(function() {
                            let nilaiSekarang = $(this).val();
                            if (nilaiSekarang) {
                                // Kirim angka bersih (ex: 24159052) ke Laravel biar database decimal tidak error
                                $(this).val(nilaiSekarang.replace(/[^0-9]/g, ''));
                            }
                        });
                    });
                });

                function loadNoShipment() {

                    let list = [];

                    $('input[name="no_shipment"]').each(function() {
                        let val = $(this).val()?.trim();

                        if (val && !list.includes(val)) {
                            list.push(val);
                        }
                    });

                    list.sort();

                    $('#searchNoShipment').html(`
            <option value="">-- Pilih No Shipment --</option>
        `);

                    list.forEach(function(item) {
                        $('#searchNoShipment').append(`
                <option value="${item}">${item}</option>
            `);
                    });
                }

                loadNoShipment();

                // =========================
                // AUTO FILL SELECTED SHIPMENT
                // =========================
                $(document).on('change', '#searchNoShipment', function() {
                    let val = $(this).val();
                    $('#selectedNoShipment').val(val);
                });

                console.log(
                    'TH =',
                    $('#tablePlanner thead th').length
                );

                console.log(
                    'TD row pertama =',
                    $('#tablePlanner tbody tr:first td').length
                );
                $('#tablePlanner tbody tr').each(function(i) {
                    let tdCount = $(this).find('td').length;

                    if (tdCount !== 44) {
                        console.log('Row ke', i + 1, 'jumlah TD =', tdCount);
                    }
                });
                console.log(
                    $('#tablePlanner tbody tr').length,
                    $('#tablePlanner tbody tr td:first-child').length
                );
                $('#tablePlanner tbody tr').each(function(i) {
                    if ($(this).children('td').length != 44) {
                        console.log('Row', i + 1, '=>', $(this).children('td').length);
                    }
                });

                $('#formGudang23').on('submit', function(e) {

                    e.preventDefault();

                    $.ajax({

                        url: "{{ route('planner.updateGudang23') }}",
                        type: "POST",

                        data: $(this).serialize(),

                        success: function(res) {

                            alert(res.message);

                            $('#modalGudang23').modal('hide');

                            location.reload();
                        },

                        error: function(xhr) {

                            console.log(xhr.responseText);

                            alert('Gagal update data');

                        }

                    });

                });

                function updateDateColor() {

                    $('input[type="date"]').each(function() {

                        if ($(this).val()) {
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

                // saat halaman pertama dibuka
                updateDateColor();

                // saat user mengubah tanggal
                $(document).on('change', 'input[type="date"]', function() {
                    updateDateColor();
                });
                // Update data-search setiap input berubah
                $(document).on('input', '#tablePlanner input', function() {

                    $(this)
                        .closest('td')
                        .attr('data-search', $(this).val());

                });

                document.addEventListener('paste', function(e) {

                    let el = document.activeElement;

                    if (el.type !== 'date') return;

                    e.preventDefault();

                    let txt = (e.clipboardData || window.clipboardData)
                        .getData('text')
                        .trim();

                    // dd/mm/yyyy
                    let m = txt.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);

                    if (m) {
                        let hasil =
                            m[3] + '-' +
                            m[2].padStart(2, '0') + '-' +
                            m[1].padStart(2, '0');

                        el.value = hasil;
                        el.dispatchEvent(new Event('change'));
                        return;
                    }

                    // yyyy-mm-dd
                    if (/^\d{4}-\d{2}-\d{2}$/.test(txt)) {
                        el.value = txt;
                        el.dispatchEvent(new Event('change'));
                    }

                });

                // ======================================
                // COPY & PASTE UNTUK INPUT DATE
                // ======================================

                $(document).on('copy', 'input[type="date"]', function(e) {
                    e.preventDefault();

                    const value = $(this).val();

                    e.originalEvent.clipboardData.setData('text/plain', value);
                });

                $(document).on('paste', 'input[type="date"]', function(e) {
                    e.preventDefault();

                    const pasted = (
                        e.originalEvent || e
                    ).clipboardData.getData('text').trim();

                    // format wajib YYYY-MM-DD
                    if (/^\d{4}-\d{2}-\d{2}$/.test(pasted)) {
                        $(this).val(pasted).trigger('change');
                    }
                });

                $('.select2-modal').select2({
    theme: 'bootstrap-5',
    dropdownParent: $('#addModal'),
    width: '100%'
});
            </script>

</body>

</html>