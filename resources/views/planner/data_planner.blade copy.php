@include('template.sidebar')

<!DOCTYPE html>
<html lang="id">

<head>
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

        /* Inline Input Fields inside Table */
        table input[type="text"],
        table input[type="number"],
        table input[type="datetime-local"] {
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

        table input[type="datetime-local"] {
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
    </style>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body>

    <div class="container-fluid-custom">

        <div class="page-header">
            <div class="title">Data Planner</div>

            <div class="d-flex align-items-center gap-2">

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
                <button type="button"
                    class="btn btn-success d-flex align-items-center gap-2"
                    style="border: none; border-radius: 8px; padding: 10px 16px;"
                    data-bs-toggle="modal"
                    data-bs-target="#modalGudang23">

                    <i class="fa-solid fa-warehouse"></i>
                    Gudang 2 & 3
                </button>

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
                    <div class="modal-content shadow-lg border-0" style="border-radius: 16px;">
                       <form action="{{ route('planner.store') }}" method="POST">
                            @csrf
                            <div class="modal-header border-bottom-0 pt-4 px-4">
                                <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-ship me-2 text-primary"></i>Add New Shipment</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>

                            <div class="field-box">
    <label>Create Tanggal</label>
    <input type="datetime-local" name="create_tgl" class="form-control">
</div>
                            <div class="modal-body px-4">
                                <div class="form-horizontal-scroll bg-light p-3 rounded-3 border">
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
                                        <input type="text" name="mobil" class="form-control">
                                    </div>
                                    <div class="field-box">
                                        <label>Perubahan Mobil</label>
                                        <input type="text" name="perubahan_mobil" class="form-control">
                                    </div>
                                    <div class="field-box">
                                        <label>Nilai Muatan</label>
                                        <input type="text" name="nilai_muatan" class="form-control modal-nilai-muatan input-rupiah">
                                    </div>
                                    <div class="field-box">
                                        <label>Biaya Kirim</label>
                                        <input type="text" name="biaya_kirim" class="form-control modal-biaya-kirim input-rupiah">
                                    </div>
                                    <div class="field-box">
                                        <label>CR (%)</label>
                                        <input type="text" name="cr" class="form-control modal-cr" readonly style="background-color: #e2e8f0;">
                                    </div>
                                    <div class="field-box">
                                        <label>Kategori Ekspedisi</label>
                                        <input type="text" name="kategori_ekspedisi" class="form-control">
                                    </div>
                                    <div class="field-box">
                                        <label>Ekspedisi</label>
                                        <input type="text" name="ekpedisi" class="form-control">
                                    </div>

                                    <div class="field-box">
                                        <label>Tanggal Teriima Dari Admin</label>
                                        <input type="datetime-local" name="tanggal_naik_logistik" class="form-control">
                                    </div>
                                    <div class="field-box">
                                        <label>Rencana Kirim</label>
                                        <input type="datetime-local" name="rencana_kirim" class="form-control">
                                    </div>
                                    <div class="field-box">
                                        <label>Tanggal Dapat Unit</label>
                                        <input type="datetime-local" name="tanggal_dpt_unit" class="form-control">
                                    </div>
                                    <div class="field-box">
                                        <label>Tanggal Tiba KACS</label>
                                        <input type="datetime-local" name="tanggal_tiba_gudang" class="form-control">
                                    </div>
                                    <div class="field-box">
                                        <label>Planning Loading KACS</label>
                                        <input type="datetime-local" name="planning_loading" class="form-control">
                                    </div>
                                    <div class="field-box">
                                        <label>Tanggal Keluar KACS</label>
                                        <input type="datetime-local" name="tanggal_keluar_gudang" class="form-control">
                                    </div>
                                    <div class="field-box">
                                        <label>Tanggal Tiba Sentul</label>
                                        <input type="datetime-local" name="tanggal_tiba_gudang_2" class="form-control">
                                    </div>
                                    <div class="field-box">
                                        <label>Tanggal Keluar Sentul</label>
                                        <input type="datetime-local" name="tanggal_keluar_gudang_2" class="form-control">
                                    </div>
                                    <div class="field-box">
                                        <label>Tanggal Tiba CCIE</label>
                                        <input type="datetime-local" name="tanggal_tiba_gudang_3" class="form-control">
                                    </div>
                                    <div class="field-box">
                                        <label>Tanggal Keluar CCIE</label>
                                        <input type="datetime-local" name="tanggal_keluar_gudang_3" class="form-control">
                                    </div>
                                    <div class="field-box" style="flex: 0 0 400px;">
                                        <label>Keterangan</label>
                                        <textarea name="keterangan" rows="2" class="form-control"></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer border-top-0 pb-4 px-4">
                                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal" style="border-radius: 8px;">Close</button>
                                <button type="submit" class="btn btn-success px-4" style="background: #16a34a; border: none; border-radius: 8px;">Save Shipment</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="modal fade" id="modalGudang23" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content shadow-lg border-0">

                       <form action="{{ route('planner.store') }}" method="POST">

                            <input type="hidden" name="no_shipment" id="selectedNoShipment">
                            @csrf

                            <div class="modal-header">
                                <h5 class="modal-title fw-bold">
                                    <i class="fa-solid fa-warehouse me-2"></i>
                                    Gudang 2 & 3
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>

                            <div class="modal-body">

                                <div class="mb-3">
                                    <label class="form-label fw-bold">Cari No Shipment</label>

                                    <select id="searchNoShipment" class="form-select">
                                        <option value="">-- Pilih No Shipment --</option>
                                    </select>
                                </div>
                                <!-- ================= GUDANG 2 ================= -->
                                <div class="mb-4 p-3 border rounded bg-light">
                                    <h6 class="fw-bold text-primary mb-3">Gudang 2</h6>

                                    <div class="mb-3">
                                        <label>Tanggal Tiba Gudang 2</label>
                                        <input type="datetime-local" name="tanggal_tiba_gudang_2" class="form-control">
                                    </div>

                                    <div class="mb-3">
                                        <label>Tanggal Keluar Gudang 2</label>
                                        <input type="datetime-local" name="tanggal_keluar_gudang_2" class="form-control">
                                    </div>
                                </div>

                                <!-- ================= GUDANG 3 ================= -->
                                <div class="mb-3 p-3 border rounded bg-light">
                                    <h6 class="fw-bold text-success mb-3">Gudang 3</h6>

                                    <div class="mb-3">
                                        <label>Tanggal Tiba Gudang 3</label>
                                        <input type="datetime-local" name="tanggal_tiba_gudang_3" class="form-control">
                                    </div>

                                    <div class="mb-3">
                                        <label>Tanggal Keluar Gudang 3</label>
                                        <input type="datetime-local" name="tanggal_keluar_gudang_3" class="form-control">
                                    </div>
                                </div>

                            </div>

                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    Close
                                </button>
                                <button type="submit" class="btn btn-success">
                                    Save Data
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
                    </select>
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






                                <th class="th-oren">Mobil</th>




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


                                <th class="th-default" style="min-width:130px;">Save & Hapus</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($logistik as $r)
                            <tr>
                                <form class="d-none" id="form-update-{{ $r->id }}" action="{{ route('planner.update', $r->id) }}" method="POST">
                                    @csrf
                                    @method('PUT')
                                </form>


                                <td>{{ $r->create_tgl ? \Carbon\Carbon::parse($r->create_tgl)->format('d/m/Y H:i') : '-' }}</td>
                                <td><input type="text" form="form-update-{{ $r->id }}" name="planner" value="{{ $r->planner }}"></td>
                                <td><input type="text" form="form-update-{{ $r->id }}" name="no_shipment" class="row-no-shipment" value="{{ $r->no_shipment }}"></td>

                                <td>

                                    <input type="datetime-local" form="form-update-{{ $r->id }}" name="tanggal_naik_logistik" value="{{ $r->tanggal_naik_logistik ? date('Y-m-d\TH:i', strtotime($r->tanggal_naik_logistik)) : '' }}">
                                </td>
                                <td>
                                    <input type="datetime-local" form="form-update-{{ $r->id }}" name="rencana_kirim" value="{{ $r->rencana_kirim ? date('Y-m-d\TH:i', strtotime($r->rencana_kirim)) : '' }}">
                                </td>

                                <td>
                                    <input type="datetime-local" form="form-update-{{ $r->id }}" name="tanggal_dpt_unit" value="{{ $r->tanggal_dpt_unit ? date('Y-m-d\TH:i', strtotime($r->tanggal_dpt_unit)) : '' }}">
                                </td>
                                <td>
                                    <input type="datetime-local" form="form-update-{{ $r->id }}" name="planning_loading" value="{{ $r->planning_loading ? date('Y-m-d\TH:i', strtotime($r->planning_loading)) : '' }}">
                                </td>
                                <td>
                                    <input type="datetime-local" form="form-update-{{ $r->id }}" name="tanggal_tiba_gudang" value="{{ $r->tanggal_tiba_gudang ? date('Y-m-d\TH:i', strtotime($r->tanggal_tiba_gudang)) : '' }}">
                                </td>

                                <td>
                                    <input type="datetime-local" form="form-update-{{ $r->id }}" name="tanggal_keluar_gudang" value="{{ $r->tanggal_keluar_gudang ? date('Y-m-d\TH:i', strtotime($r->tanggal_keluar_gudang)) : '' }}">
                                </td>
                                  <td>
                                    <input type="datetime-local" form="form-update-{{ $r->id }}" name="planning_loading_2" value="{{ $r->planning_loading_2 ? date('Y-m-d\TH:i', strtotime($r->planning_loading_2)) : '' }}">
                                </td>
                                <td>
                                    <input type="datetime-local" form="form-update-{{ $r->id }}" name="tanggal_tiba_gudang_2" value="{{ $r->tanggal_tiba_gudang_2 ? date('Y-m-d\TH:i', strtotime($r->tanggal_tiba_gudang_2)) : '' }}">
                                </td>
                                <td>
                                    <input type="datetime-local" form="form-update-{{ $r->id }}" name="tanggal_keluar_gudang_2" value="{{ $r->tanggal_keluar_gudang_2 ? date('Y-m-d\TH:i', strtotime($r->tanggal_keluar_gudang_2)) : '' }}">
                                </td>
                                  <td>
                                    <input type="datetime-local" form="form-update-{{ $r->id }}" name="planning_loading_3" value="{{ $r->planning_loading_3 ? date('Y-m-d\TH:i', strtotime($r->planning_loading_3)) : '' }}">
                                </td>
                                <td>
                                    <input type="datetime-local" form="form-update-{{ $r->id }}" name="tanggal_tiba_gudang_3" value="{{ $r->tanggal_tiba_gudang_3 ? date('Y-m-d\TH:i', strtotime($r->tanggal_tiba_gudang_3)) : '' }}">
                                </td>
                                <td>
                                    <input type="datetime-local" form="form-update-{{ $r->id }}" name="tanggal_keluar_gudang_3" value="{{ $r->tanggal_keluar_gudang_3 ? date('Y-m-d\TH:i', strtotime($r->tanggal_keluar_gudang_3)) : '' }}">
                                </td>

                                <td class="fw-semibold">{{ $r->tujuan }}</td>
                                <td>{{ $r->route }}</td>
                                <td>{{ $r->pulau }}</td>
                                <td>{{ $r->area }}</td>
                                <td>{{ $r->via_kirim }}</td>

                                <td><input type="text" form="form-update-{{ $r->id }}" name="dist_channel" value="{{ $r->dist_channel }}"></td>
                                <td><input type="text" form="form-update-{{ $r->id }}" name="kategori_ekspedisi" value="{{ $r->kategori_ekspedisi }}"></td>
                                <td><input type="text" form="form-update-{{ $r->id }}" name="ekpedisi" value="{{ $r->ekpedisi }}"></td>

                                <td><input type="text" form="form-update-{{ $r->id }}" name="transport_lead_time" value="{{ $r->transport_lead_time }}"></td>





                                <td><input type="text" form="form-update-{{ $r->id }}" name="mobil" value="{{ $r->mobil }}"></td>



                                <td><input type="text" form="form-update-{{ $r->id }}" name="nilai_muatan" class="row-nilai-muatan input-rupiah" value="{{ $r->nilai_muatan }}"></td>
                                <td><input type="text" form="form-update-{{ $r->id }}" name="biaya_kirim" class="row-biaya-kirim input-rupiah" value="{{ $r->biaya_kirim }}"></td>
                                <td><input type="text" form="form-update-{{ $r->id }}" name="cr" class="row-cr" value="{{ $r->cr }}" readonly style="background-color: #f1f5f9; color: #0284c7; font-weight: 600;"></td>





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
                                    <span class="badge-status {{ $r->sla_dapat_mobil == 'Delay' ? 'bg-danger text-white' : ($r->sla_dapat_mobil == 'On Time' ? 'bg-success text-white' : 'bg-secondary text-white') }}">
                                        {{ $r->sla_dapat_mobil ?? '-' }}
                                    </span>
                                </td>
                                

                                <td>
    @php
        $hari = floor($r->lama_digudang);
        $sisaJam = ($r->lama_digudang - $hari) * 24;
        $jam = floor($sisaJam);
        $menit = floor(($sisaJam - $jam) * 60);
    @endphp

    H+{{ $hari }} Hari {{ $jam }} Jam {{ $menit }} Menit
</td>

    <td class="text-center">
@php
$statusGudang = strtoupper(trim(
    $r->status_gudang

));
@endphp

@if($statusGudang === 'ON TIME' || $statusGudang === 'ONTIME')
    <span class="badge bg-success">On Time</span>

@elseif($statusGudang === 'DELAY')
    <span class="badge bg-danger">Delay</span>

@else
    <span class="badge bg-secondary">-</span>
@endif
</td>

                                <td class="text-center">
                                    @if($r->sla_loading == 'Sesuai SLA')
                                    <span class="badge bg-success">{{ $r->sla_loading }}</span>
                                    @elseif(str_contains($r->sla_loading, 'H+'))
                                    <span class="badge bg-warning text-dark">{{ $r->sla_loading }}</span>
                                    @else
                                    <span class="badge bg-secondary">{{ $r->sla_loading }}</span>
                                    @endif
                                </td>

                                <td class="text-center">{{ $r->lama_digudang_2 }}</td>

                                <td class="text-center">
                                    @if($r->status_gudang_2 == 'On Time')
                                    <span class="badge bg-success">On Time</span>
                                    @elseif($r->status_gudang_2 == 'Delay')
                                    <span class="badge bg-danger">Delay</span>
                                    @else
                                    <span class="badge bg-secondary">-</span>
                                    @endif
                                </td>

                                <td class="text-center">
                                    @if($r->sla_loading_2 == 'Sesuai SLA')
                                    <span class="badge bg-success">{{ $r->sla_loading_2 }}</span>
                                    @elseif(str_contains($r->sla_loading_2, 'H+'))
                                    <span class="badge bg-warning text-dark">{{ $r->sla_loading_2 }}</span>
                                    @else
                                    <span class="badge bg-secondary">{{ $r->sla_loading_2 }}</span>
                                    @endif
                                </td>




                                <td class="text-center">{{ $r->lama_digudang_3 }}</td>

                                <td class="text-center">
                                    @if($r->status_gudang_3 == 'On Time')
                                    <span class="badge bg-success">On Time</span>
                                    @elseif($r->status_gudang_3 == 'Delay')
                                    <span class="badge bg-danger">Delay</span>
                                    @else
                                    <span class="badge bg-secondary">-</span>
                                    @endif
                                </td>

                                <td class="text-center">
                                    @if($r->sla_loading_3 == 'Sesuai SLA')
                                    <span class="badge bg-success">{{ $r->sla_loading_3 }}</span>
                                    @elseif(str_contains($r->sla_loading_3, 'H+'))
                                    <span class="badge bg-warning text-dark">{{ $r->sla_loading_3 }}</span>
                                    @else
                                    <span class="badge bg-secondary">{{ $r->sla_loading_3 }}</span>
                                    @endif
                                </td>

                                <td class="text-center">{{ $r->route ? explode('-', trim($r->route))[0] : '-' }}</td>

                                <td>
                                    <div class="btn-action">
                                        <button type="submit" form="form-update-{{ $r->id }}" class="btn btn-success btn-sm px-2 d-flex align-items-center gap-1">
                                            <i class="fa-solid fa-floppy-disk"></i> Save
                                        </button>
                                        <a href="{{ route('planner.delete',$r->id) }}"
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

        </div>
        <script>
            $(document).ready(function() {

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
                function jalankanMaskingRupiahTabel() {
                    $('.row-nilai-muatan, .row-biaya-kirim, .modal-nilai-muatan, .modal-biaya-kirim').each(function() {
                        let v = $(this).val();
                        // Jika data masih berupa angka mentah database, langsung sulap ke Rupiah
                        if (v && !v.includes('Rp')) {
                            $(this).val(formatKeRupiah(v));
                        }
                    });
                }

                // ========================================================
                // 3. INITIALIZATION DATATABLES
                // ========================================================
                var table = $('#tablePlanner').DataTable({
                    scrollX: true,
                    pageLength: 10,
                    columnDefs: [{
                        className: "dt-center",
                        targets: [0, 21, 23, 24, 27, 28, 29, 32, 33, 34, 37, 38, 39]
                    }],
                    language: {

                        searchPlaceholder: "Cari DataTables",

                       
                        paginate: {
                            next: "<i class='fa-solid fa-chevron-right'></i>",
                            previous: "<i class='fa-solid fa-chevron-left'></i>"
                        }
                    },


                    drawCallback: function(settings) {
                        // Tiap ganti page atau filter, pastikan format rupiah & hitungan CR tetap terjaga
                        jalankanMaskingRupiahTabel();
                        hitungSemuaCostRatioTabel();
                    }
                });

                // ======================================
                // LOAD DATA PLANNER KE DROPDOWN
                // ======================================

                function loadPlannerFilter() {

                    $('#filterPlanner option:not(:first)').remove();

                    let planners = [];

                    $('input[name="planner"]').each(function() {

                        let planner = $(this).val().trim();

                        if (
                            planner !== '' &&
                            !planners.includes(planner)
                        ) {
                            planners.push(planner);
                        }

                    });

                    planners.sort();

                    planners.forEach(function(planner) {

                        $('#filterPlanner').append(
                            `<option value="${planner}">
                ${planner}
            </option>`
                        );

                    });
                }

                loadPlannerFilter();


                // ======================================
                // FILTER PLANNER DARI INPUT VALUE
                // ======================================

                var plannerFilter = '';

                $('#filterPlanner').on('change', function() {

                    plannerFilter = $(this).val();

                    table.draw();

                });

                $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {

                    var rowNode = table.row(dataIndex).node();

                    // =====================
                    // FILTER PLANNER
                    // =====================

                    if (plannerFilter !== '') {

                        var plannerValue = $(rowNode)
                            .find('input[name="planner"]')
                            .val();

                        if (plannerValue !== plannerFilter) {
                            return false;
                        }
                    }

                    // =====================
                    // FILTER CREATE TGL
                    // =====================

                    if (createTglFilter !== '') {

                        var createTglText = $(rowNode)
                            .find('td:eq(0)')
                            .text()
                            .trim();

                        if (createTglText !== '-') {

                            // 11/06/2026 10:52
                            var parts = createTglText.split(' ')[0].split('/');

                            var tanggalRow =
                                parts[2] + '-' +
                                parts[1] + '-' +
                                parts[0];

                            if (tanggalRow !== createTglFilter) {
                                return false;
                            }
                        }
                    }

                    return true;

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

                var createTglFilter = '';

                $('#filterCreateTgl').on('change', function() {

                    createTglFilter = $(this).val();

                    table.draw();

                });



                // Jalankan sekali di awal saat halaman pertama kali dibuka
                jalankanMaskingRupiahTabel();

                // ========================================================
                // 4. FUNGSI HITUNG CR (Handling Duplikat Shipment Jadi 1)
                // ========================================================
                // ========================================================
                // 4. FUNGSI HITUNG CR (Handling Duplikat Shipment - Biaya Digabung)
                // ========================================================
                function hitungSemuaCostRatioTabel() {

                    var shipmentGroups = {};

                    // =========================
                    // A. GROUPING DATA
                    // =========================
                    $('.row-no-shipment').each(function() {

                        var noShipment = $(this).val() ? $(this).val().trim() : '';
                        if (noShipment === '') return;

                        var currentRow = $(this).closest('tr');

                        var muatan = ambilAngkaMurni(
                            currentRow.find('.row-nilai-muatan').val()
                        );

                        var biaya = ambilAngkaMurni(
                            currentRow.find('.row-biaya-kirim').val()
                        );

                        if (!shipmentGroups[noShipment]) {
                            shipmentGroups[noShipment] = {
                                rows: [],
                                totalMuatan: 0,
                                totalBiaya: 0,
                                mainNoShipment: noShipment // 🔥 MASTER REFERENCE
                            };
                        }

                        shipmentGroups[noShipment].rows.push({
                            element: currentRow,
                            muatan: muatan,
                            biaya: biaya
                        });

                        shipmentGroups[noShipment].totalMuatan += muatan;
                        shipmentGroups[noShipment].totalBiaya += biaya;
                    });

                    // =========================
                    // B. HITUNG & OUTPUT
                    // =========================
                    $.each(shipmentGroups, function(noShipment, data) {

                        var hasilCrGlobal = 0;

                        if (data.totalMuatan > 0) {
                            hasilCrGlobal = (data.totalBiaya / data.totalMuatan) * 100;
                        }

                        $.each(data.rows, function(index, item) {

                            var row = item.element;

                            if (index === 0) {

                                // MASTER ROW
                                row.find('.row-biaya-kirim')
                                    .val(formatKeRupiah(data.totalBiaya));

                                row.find('.row-cr')
                                    .val(hasilCrGlobal.toFixed(4) + '%');

                                row.find('.row-note')
                                    .val('MASTER');

                            } else {

                                // DUPLICATE ROW
                                row.find('.row-biaya-kirim')
                                    .val(formatKeRupiah(0));

                                // 🔥 FIX UTAMA: kasih referensi no shipment
                                row.find('.row-cr')
                                    .val(
                                        '0.0000% (Duplikat dari ' + data.mainNoShipment + ')'
                                    );

                                row.find('.row-note')
                                    .val(
                                        'DUPLIKAT dari ' + data.mainNoShipment
                                    );
                            }
                        });
                    });
                }

                // SAMPAI } YG DIATAS
                // ========================================================
                // 5. EVENT LISTENER: Ketik Otomatis Berformat Rupiah
                // ========================================================
            $(document).on('input', '.row-nilai-muatan, .row-biaya-kirim', function () {
    let raw = $(this).val().replace(/[^0-9]/g, '');

    $(this).data('raw', raw); // simpan angka asli

    hitungSemuaCostRatioTabel();
});

                $(document).on('change', '.row-no-shipment', function() {
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

                if (tdCount !== 43) {
                    console.log('Row ke', i + 1, 'jumlah TD =', tdCount);
                }
            });
            console.log(
                $('#tablePlanner tbody tr').length,
                $('#tablePlanner tbody tr td:first-child').length
            );
            $('#tablePlanner tbody tr').each(function(i) {
                if ($(this).children('td').length != 43) {
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

            let autosaveTimer = {};

            $(document).on('input change', '.autosave-row input, .autosave-row select, .autosave-row textarea', function() {

                let row = $(this).closest('tr');
                let id = row.find('.autosave-row').data('id');

                if (!id) return;

                clearTimeout(autosaveTimer[id]);

                autosaveTimer[id] = setTimeout(function() {

                    let data = {};

                    row.find('input, select, textarea').each(function() {

                        let name = $(this).attr('name');
                        if (!name) return;

                        let value = $(this).val();

                        // clean rupiah field
                        if (name === 'biaya_kirim' || name === 'nilai_muatan') {
                            value = value.replace(/[^0-9]/g, '');
                        }

                        data[name] = value;
                    });

                    $.ajax({
                        url: "/planner/autosave-row/" + id,
                        type: "POST",
                        data: {
                            _token: "{{ csrf_token() }}",
                            ...data
                        },
                        success: function() {
                            console.log('AUTO SAVE OK ID:', id);
                        },
                        error: function(xhr) {
                            console.log('AUTO SAVE ERROR:', xhr.responseText);
                        }
                    });

                }, 600);
            });

            function updateDateColor() {

    $('input[type="datetime-local"]').each(function() {

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
$(document).on('change', 'input[type="datetime-local"]', function() {
    updateDateColor();
});
        </script>

</body>

</html>