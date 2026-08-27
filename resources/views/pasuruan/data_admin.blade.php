@include('template.sidebar')

<!DOCTYPE html>
<html lang="id">

<head>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DATA PASURUAN</title>

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
            font-weight: 600;
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

        .dataTables_wrapper {
            padding-top: 10px;
        }

        table.dataTable {
            border-collapse: collapse !important;
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


        .badge-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            text-align: center;
        }

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

        .btn-action {
            display: inline-flex;
            gap: 5px;
        }

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

        th.th-edit {
            background: #00ffa2 !important;
            color: #111827 !important;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            text-align: center;
            vertical-align: middle;
            letter-spacing: 0.5px;
        }

        th.th-system {
            background: #2563eb !important;
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

        /* =========================================================
           INPUT DATE & DATETIME-LOCAL
           ========================================================= */

        input[type="date"],
        input[type="datetime-local"] {
            width: 100%;
            min-width: 150px;
            padding: 4px 6px;
            font-size: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            box-sizing: border-box;
            color: #334155;
            background-color: #fff;
            transition: all 0.2s ease;
        }

        /* datetime-local lebih lebar */
        input[type="datetime-local"] {
            min-width: 190px;
        }

        /* Saat diklik */
        input[type="date"]:focus,
        input[type="datetime-local"]:focus {
            border-color: #38bdf8 !important;
            outline: none;
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.2);
        }

        /* readonly tetap abu-abu */
        td input[readonly] {
            background: #f1f5f9 !important;
            cursor: not-allowed;
        }
    </style>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body>

    <div class="container-fluid-custom">

        <div class="page-header">
            <div class="title">Data Pasuruan</div>

            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('planner.export') }}"
                    class="btn btn-success d-flex align-items-center gap-2"
                    style="border-radius:8px;padding:10px 16px;">
                    <i class="fa-solid fa-file-excel"></i>
                    Export Excel
                </a>
                <button type="button" id="btnSaveAll"
                    class="btn btn-primary d-flex align-items-center gap-2"
                    style="background:#0284c7; border:none; border-radius:8px; padding:10px 16px;">
                    <i class="fa-solid fa-floppy-disk"></i>
                    Save All
                    <span id="unsavedCount" class="badge bg-danger rounded-pill" style="display:none;">0</span>
                </button>
                <button type="button"
                    class="btn btn-primary d-flex align-items-center gap-2"
                    style="background: #0284c7; border: none; border-radius: 8px; padding: 10px 16px;"
                    data-bs-toggle="modal"
                    data-bs-target="#addModal">
                    <i class="fa-solid fa-plus"></i>
                    Add New Shipment
                </button>


            </div>
        </div>

        <div class="mb-3 d-flex gap-2">


            <button type="button"
                class="btn btn-success"
                data-bs-toggle="modal"
                data-bs-target="#transportLautModal">
                🚢 Input Transport Laut
            </button>
        </div>

        <div class="modal fade" id="transportLautModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form action="{{ route('pasuruan.updateTransportLaut') }}" method="POST">
                        @csrf

                        <div class="modal-header">
                            <h5 class="modal-title">Input Transport Laut Pasuruan</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>

                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label>No Shipment</label>
                                    <select name="no_shipment_pasuruan" class="form-select">
                                        <option value="">Pilih Shipment</option>
                                        @foreach($logistik->unique('no_shipment_pasuruan') as $r)
                                        <option value="{{ $r->no_shipment_pasuruan }}">
                                            {{ $r->no_shipment_pasuruan }} - {{ $r->tujuan_pasuruan }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label>Nama Kapal</label>
                                    <input type="text" name="nama_kapal_pasuruan" class="form-control">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label>ETD</label>
                                    <input type="date" name="etd_pasuruan" class="form-control">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label>ETA</label>
                                    <input type="date" name="eta_pasuruan" class="form-control">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label>ATD</label>
                                    <input type="date" name="atd_pasuruan" class="form-control">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label>ATA</label>
                                    <input type="date" name="ata_pasuruan" class="form-control">
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-success">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-3">
                <label class="form-label fw-bold">Filter data Import</label>
                <input type="date" id="filterCreateTgl" class="form-control">
            </div>

            <div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-centered">
                    <div class="modal-content shadow-lg border-0" style="border-radius:16px;">
                        <form action="{{ route('pasuruan.store') }}" method="POST">
                            @csrf

                            <div class="modal-header border-bottom-0">
                                <h5 class="modal-title fw-bold">
                                    <i class="fa-solid fa-ship text-primary me-2"></i>
                                    Add New Shipment
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>

                            <div class="modal-body">
                                <div class="mb-3" style="max-width:300px;">
                                    <label class="form-label fw-bold">Create Tanggal</label>
                                    <input type="date" name="create_tgl_pasuruan" class="form-control">
                                </div>

                                <div class="form-horizontal-scroll bg-light rounded-3 border p-3">
                                    <div class="field-box">
                                        <label>No Shipment</label>
                                        <input type="text" name="no_shipment_pasuruan" class="form-control">
                                    </div>
                                    <div class="field-box">
                                        <label>Planner</label>
                                        <input type="text" name="planner_pasuruan" class="form-control">
                                    </div>
                                    <div class="field-box">
                                        <label>Dist Channel</label>
                                        <input type="text" name="dist_channel_pasuruan" class="form-control">
                                    </div>
                                    <div class="field-box">
                                        <label>Lead Time (Days)</label>
                                        <input type="number" name="transport_lead_time_pasuruan" class="form-control">
                                    </div>
                                    <div class="field-box">
                                        <label>Tujuan</label>
                                        <input type="text" name="tujuan_pasuruan" class="form-control">
                                    </div>
                                    <div class="field-box">
                                        <label>Area</label>
                                        <input type="text" name="area_pasuruan" class="form-control">
                                    </div>
                                    <div class="field-box">
                                        <label>Route</label>
                                        <select name="route_pasuruan" class="form-control modal-route select-tarif">
                                            <option value="">Pilih Route</option>
                                            @foreach($routeOptions as $route)
                                            <option value="{{ $route }}">{{ $route }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field-box">
                                        <label>Mobil</label>
                                        <select name="mobil_pasuruan" class="form-control modal-mobil select-tarif">
                                            <option value="">Pilih Mobil</option>
                                            @foreach($mobilOptions as $mobil)
                                            <option value="{{ $mobil }}">{{ $mobil }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field-box">
                                        <label>Nilai Muatan</label>
                                        <input type="text" name="nilai_muatan_pasuruan" class="form-control modal-nilai-muatan input-rupiah">
                                    </div>
                                    <div class="field-box">
                                        <label>Biaya Kirim</label>
                                        <input type="text" name="biaya_kirim_pasuruan" class="form-control modal-biaya-kirim input-rupiah">
                                    </div>
                                    <div class="field-box">
                                        <label>CR (%)</label>
                                        <input type="text" name="cr_pasuruan" readonly class="form-control modal-cr" style="background:#e2e8f0;">
                                    </div>
                                    <div class="field-box">
                                        <label>Kategori Ekspedisi</label>
                                        <input type="text" name="kategori_ekspedisi_pasuruan" class="form-control">
                                    </div>
                                    <div class="field-box">
                                        <label>Ekspedisi</label>
                                        <select name="ekspedisi_pasuruan" class="form-control modal-ekspedisi select-tarif">
                                            <option value="">Pilih Ekspedisi</option>
                                            @foreach($ekspedisiOptions as $eks)
                                            <option value="{{ $eks }}">{{ $eks }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field-box">
                                        <label>Tanggal Terima Dari Admin</label>
                                        <input type="date" name="tanggal_terima_po_pasuruan" class="form-control">
                                    </div>
                                    <div class="field-box">
                                        <label>Rencana Kirim</label>
                                        <input type="date" name="rencana_kirim_pasuruan" class="form-control">
                                    </div>
                                    <div class="field-box">
                                        <label>Tanggal Dapat Unit</label>
                                        <input type="date" name="tanggal_dpt_unit_pasuruan" class="form-control">
                                    </div>
                                    <div class="field-box">
                                        <label>Tanggal Tiba Pasuruan</label>
                                        <input type="date" name="tanggal_tiba_gudang_pasuruan" class="form-control">
                                    </div>
                                    <div class="field-box">
                                        <label>Planning Loading Pasuruan</label>
                                        <input type="date" name="planning_loading_pasuruan" class="form-control">
                                    </div>
                                    <div class="field-box">
                                        <label>Tanggal Keluar Pasuruan</label>
                                        <input type="date" name="tanggal_keluar_gudang_pasuruan" class="form-control">
                                    </div>
                                </div>
                            </div>

                            <div class="modal-footer border-top-0">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="submit" class="btn btn-success">Save Shipment</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="row mb-3">

                <div class="col-md-3">
                    <label class="form-label fw-bold">Filter Planner</label>
                    <select id="filterPlanner" class="form-select planner-select">
                        <option value="">Semua Planner</option>
                        @foreach($planners as $planner)
                        <option value="{{ $planner }}">{{ $planner }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold">Filter Area</label>
                    <select id="filterArea" class="form-select area-select">
                        <option value="">Semua Area</option>
                        @foreach($areas as $area)
                        <option value="{{ $area }}">{{ $area }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- FIXED: filter Reason Waktu Tiba yang sebelumnya belum ada --}}


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
                                    <th class="th-edit">Planning Loading <span style="color:#0047FF;font-weight:900;">Pasuruan</span></th>
                                    <th class="th-edit">Tanggal Tiba <span style="color:#0047FF;font-weight:900;">Pasuruan</span></th>
                                    <th class="th-edit">Tanggal Keluar <span style="color:#0047FF;font-weight:900;">Pasuruan</span></th>
                                    <th class="th-default">Tujuan</th>
                                    <th class="th-default">Route</th>
                                    <th class="th-default">Pulau</th>
                                    <th class="th-default">Area</th>
                                    <th class="th-default">Via Kirim</th>
                                    <th class="th-oren">Dist Channel</th>
                                    <th class="th-oren">Kategori Ekspedisi</th>
                                    <th class="th-oren">Ekspedisi</th>
                                    <th class="th-oren">Lead Time</th>
                                    <th class="th-oren">Mobil</th>
                                    <th class="th-oren">Nama Driver</th>
                                    <th class="th-oren">No Pol </th>
                                    <th class="th-system">Total Qty</th>
                                    <th class="th-system">Nilai Muatan</th>
                                    <th class="th-system">Biaya Kirim</th>
                                    <th class="th-system">CR (%)</th>
                                    <th class="th-system">Status Mobil</th>
                                    <th class="th-system">Lama Waktu Pencarian</th>
                                    <th class="th-system">SLA Dapat Mobil</th>
                                    <th class="th-default">Shipping Point</th>
                                    <th class="th-oren">PIC Monitoring</th>
                                    <th class="th-oren">Act Pgi Date</th>
                                    <th class="th-oren">Urutan Bongkar</th>
                                    <th class="th-oren">Selisih Qty Do</th>
                                    <th class="th-oren">Actual Qty Do</th>
                                    <th class="th-oren">Biaya Kuli Pasuruan</th>
                                    <th class="th-oren">Total Biaya Kuli Pasuruan</th>
                                    <th class="th-oren">Reason Selisih Qty Do</th>
                                    <th class="th-system">Estimasi Tiba</th>
                                    <th class="th-oren">Tanggal Tiba</th>
                                    <th class="th-oren">Lama Perjalanan</th>
                                    <th class="th-system">SLA Tiba</th>
                                    <th class="th-oren">Tanggal Bongkar</th>
                                    <th class="th-oren">Status Bongkar</th>
                                    <th class="th-oren">Overstays</th>
                                    <th class="th-oren">Sla Bongkar</th>
                                    <th class="th-oren">Reason Waktu Tiba</th>
                                    <th class="th-oren">Reason Waktu Bongkar</th>
                                    <th class="th-oren">Remarks</th>
                                    <th class="th-oren">Nama Kapal</th>
                                    <th class="th-oren">ETD</th>
                                    <th class="th-oren">ETA</th>
                                    <th class="th-oren">ATD</th>
                                    <th class="th-oren">ATA</th>
                                    <th class="th-system">Estimasi Admin</th>
                                    <th class="th-system">Ontime/Delay Admin</th>


                                    <th class="th-default" style="min-width:130px;">Save & Hapus</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($logistik as $r)
                                <tr class="autosave-row" data-id="{{ $r->id }}">

                                    <td>{{ $r->created_at ? \Carbon\Carbon::parse($r->created_at)->format('d/m/Y') : '-' }}</td>

                                    <td>
                                        <input type="text" form="form-update-{{ $r->id }}" name="planner_pasuruan" value="{{ $r->planner_pasuruan }}">
                                    </td>

                                    <td>
                                        <input type="text" form="form-update-{{ $r->id }}" name="no_shipment_pasuruan" class="row-no-shipment" value="{{ $r->no_shipment_pasuruan }}">
                                    </td>

                                    <td>
                                        <input type="date" form="form-update-{{ $r->id }}" name="tanggal_terima_po_pasuruan" value="{{ $r->tanggal_terima_po_pasuruan ? date('Y-m-d', strtotime($r->tanggal_terima_po_pasuruan)) : '' }}">
                                    </td>

                                    <td>
                                        <input type="date" form="form-update-{{ $r->id }}" name="rencana_kirim_pasuruan" value="{{ $r->rencana_kirim_pasuruan ? date('Y-m-d', strtotime($r->rencana_kirim_pasuruan)) : '' }}">
                                    </td>

                                    <td>
                                        <input type="date" form="form-update-{{ $r->id }}" name="tanggal_dpt_unit_pasuruan" value="{{ $r->tanggal_dpt_unit_pasuruan ? date('Y-m-d', strtotime($r->tanggal_dpt_unit_pasuruan)) : '' }}">
                                    </td>

                                    <td>
                                        <input type="date" form="form-update-{{ $r->id }}" name="planning_loading_pasuruan" value="{{ $r->planning_loading_pasuruan ? date('Y-m-d', strtotime($r->planning_loading_pasuruan)) : '' }}">
                                    </td>

                                    <td>
                                        <input type="date" form="form-update-{{ $r->id }}" name="tanggal_tiba_gudang_pasuruan" value="{{ $r->tanggal_tiba_gudang_pasuruan ? date('Y-m-d', strtotime($r->tanggal_tiba_gudang_pasuruan)) : '' }}">
                                    </td>

                                    <td>
                                        <input type="date" form="form-update-{{ $r->id }}" name="tanggal_keluar_gudang_pasuruan" value="{{ $r->tanggal_keluar_gudang_pasuruan ? date('Y-m-d', strtotime($r->tanggal_keluar_gudang_pasuruan)) : '' }}">
                                    </td>

                                    <td><input type="text" form="form-update-{{ $r->id }}" name="tujuan_pasuruan" value="{{ $r->tujuan_pasuruan }}"></td>
                                    <td>
                                        <select form="form-update-{{ $r->id }}" name="route_pasuruan" class="row-route select-tarif-row">
                                            <option value="">Pilih Route</option>
                                            @foreach($routeOptions as $route)
                                            <option value="{{ $route }}" {{ $r->route_pasuruan == $route ? 'selected' : '' }}>{{ $route }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="text" form="form-update-{{ $r->id }}" name="pulau_pasuruan" value="{{ $r->pulau_pasuruan }}"></td>
                                    <td><input type="text" form="form-update-{{ $r->id }}" name="area_pasuruan" value="{{ $r->area_pasuruan }}"></td>
                                    <td><input type="text" form="form-update-{{ $r->id }}" name="via_kirim_pasuruan" value="{{ $r->via_kirim_pasuruan }}"></td>
                                    <td><input type="text" form="form-update-{{ $r->id }}" name="dist_channel_pasuruan" value="{{ $r->dist_channel_pasuruan }}"></td>
                                    <td><input type="text" form="form-update-{{ $r->id }}" name="kategori_ekspedisi_pasuruan" value="{{ $r->kategori_ekspedisi_pasuruan }}"></td>
                                    <td>
                                        <select form="form-update-{{ $r->id }}" name="ekspedisi_pasuruan" class="row-ekspedisi select-tarif-row">
                                            <option value="">Pilih Ekspedisi</option>
                                            @foreach($ekspedisiOptions as $eks)
                                            <option value="{{ $eks }}" {{ $r->ekspedisi_pasuruan == $eks ? 'selected' : '' }}>{{ $eks }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="text" form="form-update-{{ $r->id }}" name="transport_lead_time_pasuruan" value="{{ $r->transport_lead_time_pasuruan }}"></td>
                                    <td>
                                        <select form="form-update-{{ $r->id }}" name="mobil_pasuruan" class="row-mobil select-tarif-row">
                                            <option value="">Pilih Mobil</option>
                                            @foreach($mobilOptions as $mobil)
                                            <option value="{{ $mobil }}" {{ $r->mobil_pasuruan == $mobil ? 'selected' : '' }}>{{ $mobil }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="text" form="form-update-{{ $r->id }}" name="nama_driver_pasuruan" value="{{ $r->nama_driver_pasuruan }}"></td>
                                    <td><input type="text" form="form-update-{{ $r->id }}" name="no_pol_pasuruan" value="{{ $r->no_pol_pasuruan }}"></td>
                                    <td>
                                        <input type="number" form="form-update-{{ $r->id }}" name="total_do_pasuruan" value="{{ $r->total_do_pasuruan}}">
                                    </td>

                                    <td>
                                        <input type="text" form="form-update-{{ $r->id }}" name="nilai_muatan_pasuruan" class="row-nilai-muatan input-rupiah" value="{{ $r->nilai_muatan_pasuruan }}">
                                    </td>

                                    <td>
                                        <input type="text" form="form-update-{{ $r->id }}" name="biaya_kirim_pasuruan" class="row-biaya-kirim input-rupiah" value="{{ $r->biaya_kirim_pasuruan }}">
                                    </td>

                                    <td>
                                        <input type="text" form="form-update-{{ $r->id }}" name="cr_pasuruan" class="row-cr"
                                            value="{{ is_numeric($r->cr_pasuruan) ? number_format((float)$r->cr_pasuruan, 4) . '%' : $r->cr_pasuruan }}"
                                            readonly style="background:#f1f5f9;color:#0284c7;font-weight:600;">
                                    </td>

                                    <td>
                                        @php
                                        if (!empty($r->tanggal_dpt_unit_pasuruan)) {
                                        $statusMobil = 'SUDAH DAPAT';
                                        $badgeClass = 'bg-success text-white';
                                        } else {
                                        $statusMobil = 'BELUM DAPAT';
                                        $badgeClass = 'bg-danger text-white';
                                        }
                                        @endphp
                                        <span class="badge-status {{ $badgeClass }}">{{ $statusMobil }}</span>
                                    </td>

                                    <td class="text-center fw-medium text-primary">{{ $r->lama_waktu_pencarian_pasuruan }}</td>

                                    <td>
                                        @php
                                        if ($r->rencana_kirim_pasuruan && $r->tanggal_dpt_unit_pasuruan) {
                                        $area = strtoupper(trim($r->area_pasuruan));
                                        $rencana = strtotime(date('Y-m-d', strtotime($r->rencana_kirim_pasuruan)));
                                        $dptUnit = strtotime(date('Y-m-d', strtotime($r->tanggal_dpt_unit_pasuruan)));
                                        $selisihHari = floor(($dptUnit - $rencana) / 86400);

                                        if ($area == 'JABODEBEK' || $area == 'JABODETABEK') {
                                        $batasHari = 0;
                                        } elseif ($area == 'JAWA_BARAT') {
                                        $batasHari = 1;
                                        } else {
                                        $batasHari = 2;
                                        }

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

                                    <td class="text-center">{{ $r->route_pasuruan ? explode('-', trim($r->route_pasuruan))[0] : '-' }}</td>

                                    <td>
                                        <input type="text" form="form-update-{{ $r->id }}" name="pic_monitoring_pasuruan" value="{{ $r->pic_monitoring_pasuruan }}">
                                    </td>

                                    <td>{{ $r->created_at ? \Carbon\Carbon::parse($r->created_at)->format('d/m/Y') : '-' }}</td>

                                    <td>
                                        <input type="number" form="form-update-{{ $r->id }}" name="act_urutan_bongkar_pasuruan" value="{{ $r->act_urutan_bongkar_pasuruan }}">
                                    </td>

                                    {{--
                                        FIXED: selisih_quantity_pasuruan sekarang INPUT MANUAL
                                        dari user (sesuai controller), bukan lagi readonly.
                                        Posisi dipindah ke sini (tepat setelah Urutan Bongkar)
                                        sesuai permintaan. Value dikosongkan kalau memang belum
                                        pernah diisi (null/kosong/0), TIDAK menampilkan "0" secara
                                        default.
                                    --}}
                                    <td>
                                        <input type="number" form="form-update-{{ $r->id }}" name="selisih_quantity_pasuruan"
                                            value="{{ ($r->selisih_quantity_pasuruan === null || $r->selisih_quantity_pasuruan === '' || (float) $r->selisih_quantity_pasuruan === 0.0) ? '' : $r->selisih_quantity_pasuruan }}">
                                    </td>

                                    {{--
                                        FIXED (disesuaikan dengan controller update()/autosaveRow()):
                                        actual_delivery_quantity_pasuruan sekarang DIHITUNG OTOMATIS
                                        di controller = total_do_pasuruan - selisih_quantity_pasuruan.
                                        Jadi di view ini field ini dibuat READONLY, nilainya
                                        diturunkan lewat JS (lihat script di bawah) dan disimpan
                                        lewat form submit / autosave seperti biasa.
                                    --}}
                                    <td>
                                        <input type="number" form="form-update-{{ $r->id }}" name="actual_delivery_quantity_pasuruan" value="{{ $r->actual_delivery_quantity_pasuruan }}" readonly style="background:#f1f5f9;">
                                    </td>

                                    <td>
                                        <input type="text" form="form-update-{{ $r->id }}" name="biaya_kuli_pasuruan" class="rupiah-input"
                                            value="{{ $r->biaya_kuli_pasuruan ? number_format($r->biaya_kuli_pasuruan, 0, ',', '.') : '' }}">
                                    </td>
                                    <td>
                                        <input type="text" form="form-update-{{ $r->id }}" name="total_biaya_kuli_pasuruan"
                                            value="Rp {{ number_format($r->total_biaya_kuli_pasuruan ?? 0, 0, ',', '.') }}"
                                            readonly>
                                    </td>

                                    <td>
                                        <select form="form-update-{{ $r->id }}" name="reason_selisih_quantity_pasuruan" class="form-control reason-selisih-select">
                                            <option value="">Pilih Reason</option>
                                            @foreach($reasonSelisihQty as $reason)
                                            <option value="{{ $reason }}" {{ $r->reason_selisih_quantity_pasuruan == $reason ? 'selected' : '' }}>
                                                {{ $reason }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </td>

                                    <td>
                                        <input type="date" form="form-update-{{ $r->id }}" name="estimasi_tiba_pasuruan" value="{{ $r->estimasi_tiba_pasuruan ? date('Y-m-d', strtotime($r->estimasi_tiba_pasuruan)) : '' }}" readonly style="background:#f1f5f9;">
                                    </td>

                                    <td>
                                        <input type="datetime-local"
                                            form="form-update-{{ $r->id }}"
                                            name="tanggal_tiba_pasuruan"
                                            value="{{ $r->tanggal_tiba_pasuruan ? date('Y-m-d\TH:i', strtotime($r->tanggal_tiba_pasuruan)) : '' }}">
                                    </td>

                                    <td>
                                        <input type="number" step="0.01" form="form-update-{{ $r->id }}" name="lama_perjalanan_pasuruan" value="{{ $r->lama_perjalanan_pasuruan }}" readonly style="background:#f1f5f9;">
                                    </td>

                                    <td>
                                        <input type="text" form="form-update-{{ $r->id }}" name="sla_tiba_pasuruan" value="{{ $r->sla_tiba_pasuruan }}" readonly style="background:#f1f5f9;">
                                    </td>

                                    <td>
                                        <input type="datetime-local"
                                            form="form-update-{{ $r->id }}"
                                            name="tanggal_bongkar_pasuruan"
                                            value="{{ $r->tanggal_bongkar_pasuruan ? date('Y-m-d\TH:i', strtotime($r->tanggal_bongkar_pasuruan)) : '' }}">
                                    </td>

                                    {{-- STATUS BONGKAR --}}
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
                                        <input type="number"
                                            step="0.01"
                                            form="form-update-{{ $r->id }}"
                                            name="overstay_days_pasuruan"
                                            value="{{ $r->overstay_days_pasuruan }}"
                                            readonly
                                            style="background:#f1f5f9;">
                                    </td>

                                    <td>
                                        <input type="text" form="form-update-{{ $r->id }}" name="sla_bongkar_pasuruan" value="{{ $r->sla_bongkar_pasuruan }}" readonly style="background:#f1f5f9;">
                                    </td>

                                    <td>
                                        <select form="form-update-{{ $r->id }}" name="reason_waktu_tiba_pasuruan" class="form-control reason-tiba-select">
                                            <option value="">Pilih Reason</option>
                                            @foreach($reasonTiba as $reason)
                                            <option value="{{ $reason }}" {{ $r->reason_waktu_tiba_pasuruan == $reason ? 'selected' : '' }}>
                                                {{ $reason }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </td>

                                    <td>
                                        <select form="form-update-{{ $r->id }}" name="reason_waktu_bongkar_pasuruan" class="form-control reason-bongkar-select">
                                            <option value="">Pilih Reason</option>
                                            @foreach($reasonBongkar as $reason)
                                            <option value="{{ $reason }}" {{ $r->reason_waktu_bongkar_pasuruan == $reason ? 'selected' : '' }}>
                                                {{ $reason }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </td>

                                    <td>
                                        <input type="text" form="form-update-{{ $r->id }}" name="remarks_pasuruan" value="{{ $r->remarks_pasuruan }}">
                                    </td>

                                    <td>
                                        <input type="text" form="form-update-{{ $r->id }}" name="nama_kapal_pasuruan" value="{{ $r->nama_kapal_pasuruan }}">
                                    </td>

                                    <td>
                                        <input type="date" form="form-update-{{ $r->id }}" name="etd_pasuruan" value="{{ $r->etd_pasuruan ? date('Y-m-d', strtotime($r->etd_pasuruan)) : '' }}">
                                    </td>
                                    <td>
                                        <input type="date" form="form-update-{{ $r->id }}" name="eta_pasuruan" value="{{ $r->eta_pasuruan ? date('Y-m-d', strtotime($r->eta_pasuruan)) : '' }}">
                                    </td>
                                    <td>
                                        <input type="date" form="form-update-{{ $r->id }}" name="atd_pasuruan" value="{{ $r->atd_pasuruan ? date('Y-m-d', strtotime($r->atd_pasuruan)) : '' }}">
                                    </td>
                                    <td>
                                        <input type="date" form="form-update-{{ $r->id }}" name="ata_pasuruan" value="{{ $r->ata_pasuruan ? date('Y-m-d', strtotime($r->ata_pasuruan)) : '' }}">
                                    </td>

                                    @php
                                    $estimasiAdminPasuruan = null;

                                    if (!empty($r->rencana_kirim_pasuruan) && !empty($r->transport_lead_time_pasuruan)) {
                                    $estimasiAdminPasuruan = \Carbon\Carbon::parse($r->rencana_kirim_pasuruan)
                                    ->addDays((int) $r->transport_lead_time_pasuruan);
                                    }

                                    $statusEstimasiAdminPasuruan = '-';

                                    if ($estimasiAdminPasuruan && !empty($r->tanggal_tiba_pasuruan)) {

                                    // Sudah tiba -> bandingkan tanggal tiba vs estimasi
                                    $tanggalTibaPasuruan = \Carbon\Carbon::parse($r->tanggal_tiba_pasuruan);

                                    $statusEstimasiAdminPasuruan =
                                    $tanggalTibaPasuruan->lte($estimasiAdminPasuruan)
                                    ? 'On Time'
                                    : 'Delay';

                                    } elseif ($estimasiAdminPasuruan && empty($r->tanggal_tiba_pasuruan)) {

                                    // Belum tiba -> cek apakah hari ini sudah lewat estimasi
                                    $statusEstimasiAdminPasuruan =
                                    now()->startOfDay()->gt($estimasiAdminPasuruan->copy()->startOfDay())
                                    ? 'Delay'
                                    : 'Belum Tiba';
                                    }
                                    @endphp

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


                                    <td>
                                        <div class="btn-action">
                                            <a href="{{ route('pasuruan.destroy', $r->id) }}"
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

                {{--
                    Form update per baris DIPISAH dari <table>.
                    <form> tidak boleh jadi child langsung <tbody>/<tr> — kalau
                    dipaksa taruh di dalam tabel, browser cuma akan membuat form
                    PERTAMA dan mengabaikan sisanya (spec parsing HTML untuk tabel),
                    jadi tombol Save di baris ke-2 dst nggak akan pernah nyambung.
                    Taruh di luar tabel, tombol tetap terhubung lewat atribut
                    form="form-update-{id}" karena form attribute mencari berdasarkan
                    id di seluruh dokumen, tidak perlu bersebelahan secara DOM.
                --}}
                @foreach($logistik as $r)
                <form id="form-update-{{ $r->id }}" action="{{ route('pasuruan.update', $r->id) }}" method="POST" class="d-none">
                    @csrf
                    @method('PUT')
                </form>
                @endforeach

                <!-- FIXED: jQuery, Bootstrap JS, dan DataTables JS SEBELUMNYA
                     dimuat DUA KALI (sekali di <head>, sekali lagi di sini).
                     Memuat jQuery dua kali mereset instance $ global dan bisa
                     memicu perilaku aneh (event/plugin registrasi ganda).
                     Cukup select2 JS yang belum dimuat sebelumnya, jadi hanya
                     itu yang dipertahankan di sini. -->
                <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
            </div>

            <script>
                $(document).ready(function() {
                    $.fn.dataTable.ext.type.search.html = function(data) {
                        return $('<div>').html(data).text();
                    };

                    // ========================================================
                    // HELPER: format angka <-> rupiah
                    // ========================================================
                    function formatKeRupiah(angka) {
                        if (!angka) return '';
                        let stringMurni = String(angka).split('.')[0];
                        let angkaMurni = stringMurni.replace(/[^0-9]/g, '');
                        if (angkaMurni) {
                            return 'Rp ' + String(angkaMurni).replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                        }
                        return '';
                    }

                    function ambilAngkaMurni(teks) {
                        if (!teks) return 0;
                        let bersih = String(teks).replace(/[^0-9]/g, '');
                        return parseFloat(bersih) || 0;
                    }

                    // FIXED: helper baru untuk normalisasi nilai filter (Planner/Area)
                    // supaya perbandingan tidak lagi strict-sensitive terhadap
                    // spasi berlebih atau perbedaan huruf besar/kecil antara
                    // opsi dropdown ($planners/$areas) dan value asli yang
                    // tersimpan di kolom input tabel.
                    function normalizeFilterVal(v) {
                        return (v || '').toString().replace(/\s+/g, ' ').trim().toLowerCase();
                    }

                    function jalankanMaskingRupiahTabel() {
                        $('.row-nilai-muatan, .row-biaya-kirim, .modal-nilai-muatan, .modal-biaya-kirim').each(function() {
                            let v = $(this).val();
                            if (v && !v.includes('Rp')) {
                                $(this).val(formatKeRupiah(v));
                            }
                        });
                    }

                    // ========================================================
                    // Inisialisasi select2 untuk select PER-BARIS yang belum
                    // pernah di-init. DataTables memindahkan <tr> yang tidak
                    // tampil di halaman aktif keluar-masuk DOM saat paging,
                    // jadi select2 baris baru perlu di-init ulang tiap draw —
                    // TAPI dijaga supaya yang sudah pernah di-init tidak
                    // di-reinit lagi (guard .not('.select2-hidden-accessible')).
                    // dropdownParent: $('body') dipasang supaya posisi dropdown
                    // tetap stabil walau tabel di-scroll horizontal (scrollX:true).
                    // ========================================================
                    function initSelect2RowLevel() {
                        $('.reason-tiba-select').not('.select2-hidden-accessible').select2({
                            theme: 'bootstrap-5',
                            width: '100%',
                            placeholder: 'Pilih Reason Tiba',
                            allowClear: true,
                            dropdownAutoWidth: true,
                            dropdownParent: $('body')
                        });

                        $('.reason-bongkar-select').not('.select2-hidden-accessible').select2({
                            theme: 'bootstrap-5',
                            width: '100%',
                            placeholder: 'Pilih Reason Bongkar',
                            allowClear: true,
                            dropdownAutoWidth: true,
                            dropdownParent: $('body')
                        });

                        $('.select-tarif-row').not('.select2-hidden-accessible').select2({
                            theme: 'bootstrap-5',
                            width: '100%',
                            allowClear: true,
                            dropdownAutoWidth: true,
                            dropdownParent: $('body')
                        });

                        $('.reason-selisih-select').not('.select2-hidden-accessible').select2({
                            theme: 'bootstrap-5',
                            width: '100%',
                            placeholder: 'Pilih Reason Selisih',
                            allowClear: true,
                            dropdownAutoWidth: true,
                            dropdownParent: $('body')
                        });
                    }

                    // ============================================================
                    // DATA TARIF PENGIRIMAN dari controller, dipakai untuk:
                    // 1. Cascading dropdown (pilih Route -> filter Mobil -> filter Ekspedisi)
                    // 2. Preview biaya_kirim otomatis di browser (nilai FINAL tetap
                    //    dihitung ulang & disimpan oleh cariBiayaKirimOtomatisPasuruan()
                    //    di controller saat submit/autosave — ini cuma preview visual)
                    // ============================================================
                    const tarifData = @json($tarifPengiriman);

                    function normalizeTarif(v) {
                        if (!v) return '';
                        return String(v).replace(/\u00a0/g, ' ').replace(/\s*-\s*/g, '-').replace(/\s+/g, ' ').trim().toLowerCase();
                    }

                    function filterMobilByRoute(routeVal) {
                        if (!routeVal) return tarifData;
                        let key = normalizeTarif(routeVal);
                        return tarifData.filter(t => normalizeTarif(t.route) === key);
                    }

                    function isiOptionSelect($select, list, field, selectedVal) {
                        let current = selectedVal ?? $select.val();
                        let uniqueVals = [...new Set(list.map(t => t[field]).filter(Boolean))].sort();

                        $select.empty();
                        $select.append(`<option value="">Pilih ${field}</option>`);
                        uniqueVals.forEach(v => {
                            let sel = (v === current) ? 'selected' : '';
                            $select.append(`<option value="${v}" ${sel}>${v}</option>`);
                        });

                        // trigger supaya select2 (kalau sudah di-init) ikut update tampilan
                        if ($select.hasClass('select2-hidden-accessible')) {
                            $select.trigger('change.select2');
                        }
                    }

                    function updateCascadeMobilEkspedisi(scope, routeVal, keepMobil = null, keepEkspedisi = null) {
                        let filtered = filterMobilByRoute(routeVal);

                        let $mobilSelect = scope.find('[name="mobil_pasuruan"]');
                        let $eksSelect = scope.find('[name="ekspedisi_pasuruan"]');

                        isiOptionSelect($mobilSelect, filtered, 'mobil', keepMobil);
                        isiOptionSelect($eksSelect, filtered, 'ekpedisi', keepEkspedisi);
                    }

                    function previewBiayaKirim(scope) {
                        let route = scope.find('[name="route_pasuruan"]').val();
                        let mobil = scope.find('[name="mobil_pasuruan"]').val();
                        let eks = scope.find('[name="ekspedisi_pasuruan"]').val();

                        if (!route || !mobil) return;

                        let routeKey = normalizeTarif(route);
                        let mobilKey = normalizeTarif(mobil);
                        let eksKey = normalizeTarif(eks);

                        let candidates = tarifData.filter(t => normalizeTarif(t.route) === routeKey);

                        let match = null;
                        if (eksKey) {
                            match = candidates.find(t => normalizeTarif(t.ekpedisi) === eksKey && normalizeTarif(t.mobil).startsWith(mobilKey));
                        }
                        if (!match) {
                            match = candidates.find(t => normalizeTarif(t.mobil).startsWith(mobilKey));
                        }

                        if (match && match.biaya_kirim) {
                            let $biayaField = scope.find('[name="biaya_kirim_pasuruan"]');
                            $biayaField.val(formatKeRupiah(match.biaya_kirim));
                            if (scope.is('tr')) hitungSemuaCostRatioTabel();
                        }
                    }

                    // --- Cascading untuk MODAL Add New Shipment ---
                    $('#addModal').on('change', '[name="route_pasuruan"]', function() {
                        let modalScope = $('#addModal');
                        updateCascadeMobilEkspedisi(modalScope, $(this).val());
                    });

                    $('#addModal').on('change', '[name="route_pasuruan"], [name="mobil_pasuruan"], [name="ekspedisi_pasuruan"]', function() {
                        previewBiayaKirim($('#addModal'));
                    });

                    // --- Cascading untuk PER-BARIS di tabel ---
                    $(document).on('change', '#tablePlanner [name="route_pasuruan"]', function() {
                        let row = $(this).closest('tr');
                        let currentMobil = row.find('[name="mobil_pasuruan"]').val();
                        let currentEks = row.find('[name="ekspedisi_pasuruan"]').val();
                        updateCascadeMobilEkspedisi(row, $(this).val(), currentMobil, currentEks);
                    });

                    $(document).on('change', '#tablePlanner [name="route_pasuruan"], #tablePlanner [name="mobil_pasuruan"], #tablePlanner [name="ekspedisi_pasuruan"]', function() {
                        let row = $(this).closest('tr');
                        previewBiayaKirim(row);
                    });

                    // Inisialisasi cascading utk baris yang SUDAH ADA nilainya saat page
                    // load (supaya opsi mobil/ekspedisi ke-filter sesuai route tersimpan)
                    $('#tablePlanner tbody tr').each(function() {
                        let row = $(this);
                        let routeVal = row.find('[name="route_pasuruan"]').val();
                        let mobilVal = row.find('[name="mobil_pasuruan"]').val();
                        let eksVal = row.find('[name="ekspedisi_pasuruan"]').val();
                        if (routeVal) {
                            updateCascadeMobilEkspedisi(row, routeVal, mobilVal, eksVal);
                        }
                    });

                    function isDaratPasuruan(val) {
                        return (val || '').trim().toUpperCase() === 'DARAT';
                    }

                    function hitungRencanaKirimJSPasuruan(tglTerima) {
                        if (!tglTerima) return '';
                        let d = new Date(tglTerima);
                        d.setDate(d.getDate() + 4);
                        let yyyy = d.getFullYear();
                        let mm = String(d.getMonth() + 1).padStart(2, '0');
                        let dd = String(d.getDate()).padStart(2, '0');
                        return `${yyyy}-${mm}-${dd}`;
                    }

                    function autoRencanaKirimPasuruan(row) {
                        let via = row.find('[name="via_kirim_pasuruan"]').val();
                        let tglTerimaInput = row.find('[name="tanggal_terima_po_pasuruan"]');
                        let rencanaInput = row.find('[name="rencana_kirim_pasuruan"]');

                        if (isDaratPasuruan(via)) {
                            rencanaInput.val(hitungRencanaKirimJSPasuruan(tglTerimaInput.val()));
                            rencanaInput.prop('readonly', true).css('background', '#f1f5f9');
                        } else {
                            rencanaInput.prop('readonly', false).css('background', '');
                        }
                    }

                    // jalankan sekali saat halaman dibuka, untuk semua baris
                    $('#tablePlanner tbody tr').each(function() {
                        autoRencanaKirimPasuruan($(this));
                    });

                    // jalankan tiap kali via_kirim atau tanggal_terima_po berubah
                    $(document).on('input change', '#tablePlanner [name="via_kirim_pasuruan"], #tablePlanner [name="tanggal_terima_po_pasuruan"]', function() {
                        let row = $(this).closest('tr');
                        autoRencanaKirimPasuruan(row);
                        row.find('[name="rencana_kirim_pasuruan"]').trigger('change'); // supaya autosave ke-trigger juga
                    });
                    // ========================================================
                    // INISIALISASI DATATABLES
                    // ========================================================
                    var table = $('#tablePlanner').DataTable({
                        scrollX: true,
                        pageLength: 10,
                        columnDefs: [{
                            className: "dt-center",
                            targets: [0, 19, 20, 21, 22, 23, 24, 25, 26]
                        }],

                        initComplete: function() {
                            // FIXED (CRITICAL BUG): sebelumnya callback ini
                            // memakai variabel luar `table`, yang PADA SAAT
                            // initComplete jalan, boleh jadi belum ter-assign
                            // sepenuhnya. `this.api()` selalu aman dipakai
                            // karena tidak bergantung pada timing assignment
                            // variabel `table` di luar.
                            var apiRef = this.api();
                            setTimeout(function() {
                                jalankanMaskingRupiahTabel();
                                initSelect2RowLevel();
                                hitungSemuaCostRatioTabel();
                                // FIXED: select2 mengubah lebar cell setelah tabel
                                // sudah dirender dengan scrollX:true, sehingga
                                // header & body jadi tidak sinkron lebarnya
                                // ("header geser-geser"). columns.adjust()
                                // memaksa DataTables menghitung ulang & menyamakan
                                // lebar header dengan body.
                                apiRef.columns.adjust();
                            }, 0);

                            // ================================================
                            // FIXED: matikan search bawaan DataTables sepenuhnya.
                            // DataTables secara default membaca text node <td>
                            // untuk pencarian — karena hampir semua kolom di tabel
                            // ini isinya <input>/<select> (tidak ada text node),
                            // search bawaan selalu menganggap kolom itu kosong.
                            // Sebelumnya cuma di-.off('keyup') yang TIDAK mematikan
                            // event 'input'/'search'/'paste'/'cut' yang juga
                            // dipasang DataTables, jadi search bawaan (yang salah)
                            // tetap ikut jalan berbarengan dengan custom filter dan
                            // saling AND — inilah kenapa hasil search jadi kosong /
                            // tidak lengkap. Sekarang semua event bawaan dimatikan
                            // sekaligus, lalu diganti listener sendiri yang cuma
                            // menyimpan keyword ke variabel dan memanggil draw().
                            // ================================================
                            $('#tablePlanner_filter input')
                                .off('keyup input search paste cut')
                                .on('keyup input search paste cut', function() {
                                    globalKeyword = $(this).val().toLowerCase().trim();
                                    table.draw();
                                });
                        },

                        // FIXED: drawCallback sekarang HANYA menjalankan hal-hal
                        // yang memang harus diulang tiap redraw/paging (masking
                        // rupiah + init select2 baris baru). Binding filter/select2
                        // header dan ext.search dipindah ke $(document).ready supaya
                        // hanya terpasang SEKALI — sebelumnya kode lama memasang
                        // ulang semua event handler & filter di sini pada SETIAP
                        // draw, sehingga makin sering tabel redraw, makin banyak
                        // handler change() yang menumpuk (tiap ganti filter jadi
                        // memicu draw() berkali-kali lipat). Itu penyebab utama
                        // filter planner/area jadi lambat/ngaco setelah dipakai
                        // beberapa kali.
                        drawCallback: function() {
                            jalankanMaskingRupiahTabel();
                            initSelect2RowLevel();
                            // FIXED (CRITICAL BUG — ini penyebab utama filter
                            // Planner/Area/Tgl Import "tidak berfungsi"):
                            // drawCallback dipanggil DataTables secara SINKRON
                            // saat draw PERTAMA KALI, yaitu masih di DALAM proses
                            // pemanggilan `$('#tablePlanner').DataTable({...})`
                            // itu sendiri — SEBELUM baris `var table = ...` di
                            // luar sempat selesai assignment. Akibatnya waktu
                            // drawCallback pertama kali jalan, variabel `table`
                            // masih undefined, `table.columns.adjust()` melempar
                            // TypeError, dan exception ini MENGHENTIKAN seluruh
                            // sisa kode di dalam $(document).ready(...) —
                            // termasuk semua registrasi event filter Planner,
                            // Area, dan custom search (ext.search.push) yang
                            // posisinya ada SETELAH blok DataTable() ini. Itulah
                            // kenapa dropdown filter kelihatan ada tapi milih
                            // opsi tidak ngefek sama sekali: handler-nya memang
                            // tidak pernah kepasang.
                            //
                            // Fix: gunakan `this.api()` yang selalu tersedia
                            // sejak awal di dalam callback DataTables, tidak
                            // bergantung pada timing assignment variabel luar.
                            this.api().columns.adjust();
                        }
                    });

                    // Format input biaya_kuli jadi 1.000.000 saat diketik
                    $(document).on('input', 'input[name="biaya_kuli_pasuruan"]', function() {
                        let raw = $(this).val().replace(/\D/g, ''); // ambil angka saja
                        $(this).val(raw ? new Intl.NumberFormat('id-ID').format(raw) : '');
                        hitungTotalKuli($(this).closest('tr'));
                    });

                    function hitungTotalKuli(row) {
                        let qty = parseFloat(row.find('input[name="actual_delivery_quantity_pasuruan"]').val()) || 0;
                        let biayaRaw = row.find('input[name="biaya_kuli_pasuruan"]').val().replace(/\./g, '') || 0;
                        let biaya = parseFloat(biayaRaw) || 0;

                        let total = qty * biaya;
                        row.find('input[name="total_biaya_kuli_pasuruan"]').val(formatRupiah(total));
                    }

                    function formatRupiah(angka) {
                        return 'Rp ' + new Intl.NumberFormat('id-ID').format(angka);
                    }
                    // ========================================================
                    // SELECT2 UNTUK FILTER HEADER (init sekali saja)
                    // ========================================================
                    $('.planner-select').select2({
                        theme: 'bootstrap-5',
                        width: '100%',
                        placeholder: 'Semua Planner',
                        allowClear: true
                    });

                    $('.area-select').select2({
                        theme: 'bootstrap-5',
                        width: '100%',
                        placeholder: 'Semua Area',
                        allowClear: true
                    });

                    $('.reason-tiba-filter-select').select2({
                        theme: 'bootstrap-5',
                        width: '100%',
                        placeholder: 'Semua Reason Tiba',
                        allowClear: true
                    });

                    $('.reason-bongkar-filter-select').select2({
                        theme: 'bootstrap-5',
                        width: '100%',
                        placeholder: 'Semua Reason Bongkar',
                        allowClear: true
                    });

                    // Select2 untuk dropdown Route/Mobil/Ekspedisi di modal Add New Shipment
                    $('.select-tarif').select2({
                        theme: 'bootstrap-5',
                        width: '100%',
                        allowClear: true,
                        dropdownParent: $('#addModal')
                    });

                    // ========================================================
                    // FILTER PLANNER / AREA / REASON TIBA / REASON BONGKAR /
                    // CREATE TGL / GLOBAL KEYWORD SEARCH.
                    // Dipasang SEKALI. Variabel filter di-update lewat event
                    // change/input, lalu table.draw() dipanggil — bukan
                    // sebaliknya.
                    // ========================================================
                    var plannerFilter = '';
                    var areaFilter = '';
                    var reasonTibaFilter = '';
                    var reasonBongkarFilter = '';
                    var createTglFilter = '';
                    var globalKeyword = ''; // FIXED: dipakai oleh search box bawaan DataTables yang sudah di-override

                    $('#filterPlanner').on('change', function() {
                        plannerFilter = $(this).val() || '';
                        table.draw();
                    });

                    $('#filterArea').on('change', function() {
                        areaFilter = $(this).val() || '';
                        table.draw();
                    });

                    $('#filterReasonTiba').on('change', function() {
                        reasonTibaFilter = $(this).val() || '';
                        table.draw();
                    });

                    $('#filterReasonBongkar').on('change', function() {
                        reasonBongkarFilter = $(this).val() || '';
                        table.draw();
                    });

                    $('#filterCreateTgl').on('change', function() {
                        createTglFilter = $(this).val() || '';
                        table.draw();
                    });

                    // FIXED: satu-satunya ext.search.push untuk tabel ini
                    // (sebelumnya ada 2 tempat berbeda yang push/pop terpisah
                    // sehingga saling menghapus filter satu sama lain).
                    // Blok "GLOBAL KEYWORD SEARCH" di paling atas menggantikan
                    // search bawaan DataTables yang sudah dimatikan di atas —
                    // ini yang membuat search sekarang bisa membaca ISI SEMUA
                    // input/select di baris, bukan cuma text kosong di <td>.
                    //
                    // FIXED (bug filter Planner/Area/Tgl Import tidak berfungsi):
                    // 1) Filter Planner & Area sebelumnya pakai perbandingan
                    //    strict (!==) tanpa normalisasi -> gagal match kalau ada
                    //    spasi ekstra / beda huruf besar-kecil antara opsi
                    //    dropdown dan value asli di kolom tabel. Sekarang pakai
                    //    normalizeFilterVal() di kedua sisi.
                    // 2) Filter Tgl Import sebelumnya membiarkan baris yang
                    //    created_at-nya kosong ('-') LOLOS tanpa dicek sama
                    //    sekali saat filter aktif -> hasil filter kelihatan
                    //    salah/campur. Sekarang baris tanpa tanggal langsung
                    //    dibuang (return false) saat createTglFilter aktif.
                    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                        if (settings.nTable.id !== 'tablePlanner') return true;

                        var node = $(table.row(dataIndex).node());

                        // ============================================================
                        // GLOBAL KEYWORD SEARCH — cek ke SEMUA input/select/text di baris
                        // ============================================================
                        if (globalKeyword !== '') {
                            var textAll = '';

                            node.find('input,select,textarea').each(function() {
                                var $el = $(this);
                                if ($el.is('select')) {
                                    textAll += ' ' + ($el.find('option:selected').text() || '').toLowerCase();
                                } else {
                                    textAll += ' ' + ($el.val() || '').toLowerCase();
                                }
                            });

                            // biar angka Rupiah yang diformat titik-titik tetap
                            // ketemu walau user ngetik angka polos (5000000 vs
                            // Rp 5.000.000)
                            node.find('.row-nilai-muatan, .row-biaya-kirim').each(function() {
                                textAll += ' ' + ambilAngkaMurni($(this).val());
                            });

                            textAll += ' ' + node.text().toLowerCase();

                            if (textAll.indexOf(globalKeyword) === -1) return false;
                        }

                        // FILTER PLANNER — dinormalisasi (trim + lowercase + spasi rapat)
                        if (plannerFilter !== '') {
                            var plannerValue = normalizeFilterVal(node.find('input[name="planner_pasuruan"]').val());
                            if (plannerValue !== normalizeFilterVal(plannerFilter)) return false;
                        }

                        // FILTER AREA — dinormalisasi (trim + lowercase + spasi rapat)
                        if (areaFilter !== '') {
                            var areaValue = normalizeFilterVal(node.find('input[name="area_pasuruan"]').val());
                            if (areaValue !== normalizeFilterVal(areaFilter)) return false;
                        }

                        // FILTER REASON WAKTU TIBA
                        if (reasonTibaFilter !== '') {
                            var reasonTibaValue = (node.find('select[name="reason_waktu_tiba_pasuruan"]').val() || '').trim();
                            if (reasonTibaValue !== reasonTibaFilter) return false;
                        }

                        // FILTER REASON WAKTU BONGKAR
                        if (reasonBongkarFilter !== '') {
                            var reasonBongkarValue = (node.find('select[name="reason_waktu_bongkar_pasuruan"]').val() || '').trim();
                            if (reasonBongkarValue !== reasonBongkarFilter) return false;
                        }

                        // FILTER CREATE TGL (kolom pertama, format d/m/Y)
                        // FIXED: baris tanpa tanggal ('-' atau kosong) sekarang
                        // DIBUANG saat filter aktif, bukan otomatis lolos.
                        if (createTglFilter !== '') {
                            var createTglText = (data[0] || '').trim();

                            if (createTglText === '-' || createTglText === '') {
                                return false;
                            }

                            var parts = createTglText.split(' ')[0].split('/');
                            if (parts.length === 3) {
                                var tanggalRow = parts[2] + '-' + parts[1] + '-' + parts[0];
                                if (tanggalRow !== createTglFilter) return false;
                            } else {
                                // format tanggal tidak dikenali -> jangan diloloskan
                                return false;
                            }
                        }

                        return true;
                    });

                    // Jalankan sekali di awal
                    jalankanMaskingRupiahTabel();

                    // ========================================================
                    // AUTOSAVE PER BARIS
                    // ========================================================
                    function saveRow(id) {
                        let row = $('tr[data-id="' + id + '"]');

                        $.ajax({
                            url: '/planner/autosave-row/' + id,
                            type: 'POST',
                            data: {
                                _token: '{{ csrf_token() }}',
                                _method: 'PUT',
                                selisih_quantity_pasuruan: row.find('[name="selisih_quantity_pasuruan"]').val(),
                                reason_selisih_quantity_pasuruan: row.find('[name="reason_selisih_quantity_pasuruan"]').val(),
                                planner_pasuruan: row.find('[name="planner_pasuruan"]').val(),
                                no_shipment_pasuruan: row.find('[name="no_shipment_pasuruan"]').val(),
                                total_do_pasuruan: row.find('[name="total_do_pasuruan"]').val(),
                                actual_delivery_quantity_pasuruan: row.find('[name="actual_delivery_quantity_pasuruan"]').val(),
                                rencana_kirim_pasuruan: row.find('[name="rencana_kirim_pasuruan"]').val(),
                                tanggal_dpt_unit_pasuruan: row.find('[name="tanggal_dpt_unit_pasuruan"]').val(),
                                planning_loading_pasuruan: row.find('[name="planning_loading_pasuruan"]').val(),
                                tanggal_tiba_gudang_pasuruan: row.find('[name="tanggal_tiba_gudang_pasuruan"]').val(),
                                tanggal_keluar_gudang_pasuruan: row.find('[name="tanggal_keluar_gudang_pasuruan"]').val(),
                                tujuan_pasuruan: row.find('[name="tujuan_pasuruan"]').val(),
                                route_pasuruan: row.find('[name="route_pasuruan"]').val(),
                                pulau_pasuruan: row.find('[name="pulau_pasuruan"]').val(),
                                dist_channel_pasuruan: row.find('[name="dist_channel_pasuruan"]').val(),
                                kategori_ekspedisi_pasuruan: row.find('[name="kategori_ekspedisi_pasuruan"]').val(),
                                ekspedisi_pasuruan: row.find('[name="ekspedisi_pasuruan"]').val(),
                                transport_lead_time_pasuruan: row.find('[name="transport_lead_time_pasuruan"]').val(),
                                area_pasuruan: row.find('[name="area_pasuruan"]').val(),
                                via_kirim_pasuruan: row.find('[name="via_kirim_pasuruan"]').val(),
                                mobil_pasuruan: row.find('[name="mobil_pasuruan"]').val(),
                                biaya_kuli_pasuruan: ambilAngkaMurni(row.find('[name="biaya_kuli_pasuruan"]').val()),
                                nilai_muatan_pasuruan: ambilAngkaMurni(row.find('[name="nilai_muatan_pasuruan"]').val()),
                                biaya_kirim_pasuruan: ambilAngkaMurni(row.find('[name="biaya_kirim_pasuruan"]').val()),
                                cr_pasuruan: row.find('[name="cr_pasuruan"]').val(),
                                reason_waktu_tiba_pasuruan: row.find('[name="reason_waktu_tiba_pasuruan"]').val(),
                                reason_waktu_bongkar_pasuruan: row.find('[name="reason_waktu_bongkar_pasuruan"]').val()
                            },
                            success: function() {
                                console.log("Saved " + id);
                            }
                        });
                    }

                    let saveTimer;

                    $(document).on('change input', '#tablePlanner input,#tablePlanner select,#tablePlanner textarea', function() {
                        let row = $(this).closest('tr');
                        let id = row.data('id');

                        clearTimeout(saveTimer);
                        saveTimer = setTimeout(function() {
                            saveRow(id);
                        }, 500);
                    });

                    // ========================================================
                    // HITUNG CR (Cost Ratio) — akumulasi per No Shipment
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

                    $(document).on('input', '.row-nilai-muatan', function() {
                        $(this).val(formatKeRupiah(ambilAngkaMurni($(this).val())));
                        hitungSemuaCostRatioTabel();
                    });

                    $(document).on('input', '.row-biaya-kirim', function() {
                        $(this).val(formatKeRupiah(ambilAngkaMurni($(this).val())));
                        hitungSemuaCostRatioTabel();
                    });
                    $(document).on('input', '.modal-nilai-muatan, .modal-biaya-kirim', function() {
                        var muatanModal = ambilAngkaMurni($('.modal-nilai-muatan').val());
                        var biayaModal = ambilAngkaMurni($('.modal-biaya-kirim').val());
                        var crModal = 0;
                        if (muatanModal > 0) {
                            crModal = (biayaModal / muatanModal) * 100;
                        }
                        $('.modal-cr').val(crModal.toFixed(4) + '%');
                    });

                    $('form').on('submit', function() {
                        $('.row-nilai-muatan, .row-biaya-kirim, .modal-nilai-muatan, .modal-biaya-kirim').each(function() {
                            let nilaiSekarang = $(this).val();
                            if (nilaiSekarang) {
                                $(this).val(nilaiSekarang.replace(/[^0-9]/g, ''));
                            }
                        });
                    });

                    // ========================================================
                    // FIXED — bug "Reason keliatan belum kepilih sampai save":
                    // sebelumnya ada listener global input/change yang langsung
                    // memanggil table.draw() setiap ada perubahan apa pun di
                    // tabel. Klik opsi di dropdown select2 (Reason Tiba/Bongkar)
                    // men-trigger event 'change' pada <select> aslinya secara
                    // instan, TAPI render visual select2 (teks pilihan yang
                    // muncul di kotak) butuh giliran event loop berikutnya.
                    // Kalau draw() sempat kepanggil di tengah proses itu,
                    // DataTables merebuild baris dari DOM cache SEBELUM select2
                    // sempat menampilkan pilihan barunya — hasilnya kelihatan
                    // "balik ke placeholder" walau <select> aslinya sudah
                    // menyimpan value yang benar (makanya begitu disave/reload
                    // nilainya sudah benar). invalidate('dom') saja cukup untuk
                    // sinkronisasi data internal DataTables (dipakai saat
                    // search/sort) TANPA memaksa redraw visual.
                    // ========================================================
                    $(document).on('input change', '#tablePlanner input, #tablePlanner select, #tablePlanner textarea', function() {
                        table.row($(this).closest('tr')).invalidate('dom');
                    });
                });

                function loadNoShipment() {
                    let list = [];

                    $('input[name="no_shipment_pasuruan"]').each(function() {
                        let val = $(this).val()?.trim();
                        if (val && !list.includes(val)) {
                            list.push(val);
                        }
                    });

                    list.sort();

                    $('#searchNoShipment').html(`<option value="">-- Pilih No Shipment --</option>`);

                    list.forEach(function(item) {
                        $('#searchNoShipment').append(`<option value="${item}">${item}</option>`);
                    });
                }

                loadNoShipment();

                $(document).on('change', '#searchNoShipment', function() {
                    let val = $(this).val();
                    $('#selectedNoShipment').val(val);
                });

                var autosaveTimer = {};

                $(document).on('input change', '.autosave-row input, .autosave-row select, .autosave-row textarea', function() {
                    var row = $(this).closest('tr');
                    var id = row.attr('data-id');
                    if (!id) return;

                    clearTimeout(autosaveTimer[id]);

                    autosaveTimer[id] = setTimeout(function() {
                        var data = {
                            _token: "{{ csrf_token() }}",
                            planner_pasuruan: row.find('[name="planner_pasuruan"]').val(),
                            no_shipment_pasuruan: row.find('[name="no_shipment_pasuruan"]').val(),
                            rencana_kirim_pasuruan: row.find('[name="rencana_kirim_pasuruan"]').val(),
                            tanggal_dpt_unit_pasuruan: row.find('[name="tanggal_dpt_unit_pasuruan"]').val(),
                            biaya_kirim_pasuruan: ambilAngkaMurni(row.find('[name="biaya_kirim_pasuruan"]').val()),
                            nilai_muatan_pasuruan: ambilAngkaMurni(row.find('[name="nilai_muatan_pasuruan"]').val()),
                            cr_pasuruan: row.find('[name="cr_pasuruan"]').val()
                        };

                        $.ajax({
                            url: "/planner/autosave-row/" + id,
                            type: "POST",
                            data: data,
                            success: function() {
                                console.log("AUTO SAVE OK : " + id);
                            },
                            error: function(xhr) {
                                console.log(xhr.responseText);
                            }
                        });
                    }, 500);
                });

                function updateDateColor() {
                    $('input[type="date"], input[type="datetime-local"]').each(function() {

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

                updateDateColor();

                $(document).on(
                    'change',
                    'input[type="date"], input[type="datetime-local"]',
                    function() {
                        updateDateColor();
                    }
                );


                $(document).on('input', '#tablePlanner input', function() {
                    $(this).closest('td').attr('data-search', $(this).val());
                });

                document.addEventListener('paste', function(e) {
                    let el = document.activeElement;
                    if (el.type !== 'date') return;

                    e.preventDefault();

                    let txt = (e.clipboardData || window.clipboardData).getData('text').trim();

                    let m = txt.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
                    if (m) {
                        let hasil = m[3] + '-' + m[2].padStart(2, '0') + '-' + m[1].padStart(2, '0');
                        el.value = hasil;
                        el.dispatchEvent(new Event('change'));
                        return;
                    }

                    if (/^\d{4}-\d{2}-\d{2}$/.test(txt)) {
                        el.value = txt;
                        el.dispatchEvent(new Event('change'));
                    }
                });

                $(document).on('copy', 'input[type="date"]', function(e) {
                    e.preventDefault();
                    const value = $(this).val();
                    e.originalEvent.clipboardData.setData('text/plain', value);
                });

                $(document).on('paste', 'input[type="date"]', function(e) {
                    e.preventDefault();
                    const pasted = (e.originalEvent || e).clipboardData.getData('text').trim();
                    if (/^\d{4}-\d{2}-\d{2}$/.test(pasted)) {
                        $(this).val(pasted).trigger('change');
                    }
                });

                // ============================================================
                // FIXED (disesuaikan dengan controller):
                // Sebelumnya listener ini mendengarkan total_do +
                // actual_delivery_quantity untuk MENGHITUNG selisih (logika
                // lama, kebalik dari controller). Sekarang controller
                // menghitung actual_delivery_quantity_pasuruan = total_do -
                // selisih, jadi listener ini mendengarkan total_do +
                // SELISIH (yang sekarang input manual) untuk menampilkan
                // preview actual qty secara real-time di browser. Nilai
                // akhir yang benar tetap dihitung ulang & disimpan oleh
                // controller saat save/autosave.
                // ============================================================
                $(document).on('input', '[name="total_do_pasuruan"], [name="selisih_quantity_pasuruan"]', function() {
                    let row = $(this).closest('tr');
                    let total = parseFloat(row.find('[name="total_do_pasuruan"]').val()) || 0;
                    let selisih = parseFloat(row.find('[name="selisih_quantity_pasuruan"]').val()) || 0;
                    let actual = total - selisih;

                    row.find('[name="actual_delivery_quantity_pasuruan"]').val(actual);

                    // biaya kuli & total biaya kuli ikut ter-update di preview
                    // karena actual qty berubah
                    hitungTotalKuli(row);
                });
            </script>

</body>

</html>