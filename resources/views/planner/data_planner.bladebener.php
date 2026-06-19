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
            font-size: 13px;
        }

        table.dataTable thead th {
            background: #0f172a !important;
            color: #f8fafc !important;
            text-align: center;
            vertical-align: middle;
            font-weight: 600;
            padding: 12px 10px !important;
            border-bottom: none !important;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
        }

        table.dataTable tbody tr {
            background-color: #ffffff;
            transition: background-color 0.2s ease;
        }

        table.dataTable tbody tr:hover {
            background-color: #f1f5f9 !important;
        }

        table.dataTable tbody td {
            padding: 8px 10px !important;
            vertical-align: middle;
            border-bottom: 1px solid #e2e8f0 !important;
            color: #475569;
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
    </style>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body>

    <div class="container-fluid-custom">

        <div class="page-header">
            <div class="title">Data Planner</div>
            <button type="button" class="btn btn-primary d-flex align-items-center gap-2" style="background: #0284c7; border: none; border-radius: 8px; padding: 10px 16px;" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="fa-solid fa-plus"></i> Add New Shipment
            </button>
        </div>

        @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert" style="border-left: 5px solid #16a34a !important; background: white;">
            <span class="text-success fw-semibold"><i class="fa-solid fa-circle-check me-2"></i> {{ session('success') }}</span>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        @endif

        <div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content shadow-lg border-0" style="border-radius: 16px;">
                    <form action="{{ route('planner.store') }}" method="POST">
                        @csrf
                        <div class="modal-header border-bottom-0 pt-4 px-4">
                            <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-ship me-2 text-primary"></i>Add New Shipment</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
                                    <label>Ketersediaan Unit</label>
                                    <input type="text" name="ketersediaan_unit" class="form-control">
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
                                    <input type="number" name="nilai_muatan" class="form-control">
                                </div>
                                <div class="field-box">
                                    <label>Biaya Kirim</label>
                                    <input type="number" name="biaya_kirim" class="form-control">
                                </div>
                                <div class="field-box">
                                    <label>CR</label>
                                    <input type="text" name="cr" class="form-control">
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
                                    <label>Nama Driver</label>
                                    <input type="text" name="nama_driver" class="form-control">
                                </div>
                                <div class="field-box">
                                    <label>No Polisi</label>
                                    <input type="text" name="no_pol" class="form-control">
                                </div>
                                <div class="field-box">
                                    <label>Tanggal Naik Logistik</label>
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

        <div class="card">
            <div class="table-responsive">
                <table id="tablePlanner" class="display nowrap table table-hover" style="width:100%">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Tanggal Naik Logistik</th>
                            <th>Rencana Kirim</th>
                            <th>Lead Time</th>
                            <th>Planner</th>
                            <th>No Shipment</th>
                            <th>Dist Channel</th>
                            <th>Tujuan</th>
                            <th>Area</th>
                            <th>Ketersediaan Unit</th>
                            <th>Mobil</th>
                            <th>Perubahan Mobil</th>
                            <th>Nilai Muatan</th>
                            <th>Biaya Kirim</th>
                            <th>CR</th>
                            <th>Kategori Ekspedisi</th>
                            <th>Ekspedisi</th>
                            <th>Nama Driver</th>
                            <th>No Pol</th>
                            <th>Tanggal Dapat Unit</th>
                            <th>Tanggal Tiba KACS</th>
                            <th>Status Mobil</th>
                            <th>Keterangan</th>
                            <th>Lama Waktu Pencarian</th>
                            <th>SLA Dapat Mobil</th>
                            <th>Planning Loading</th>
                            <th>Tanggal Keluar KACS</th>
                            <th>Lama Di KACS</th>
                            <th>Status KACS</th>
                            <th>SLA Loading</th>
                            <th>Tanggal Tiba Sentul</th>
                            <th>Tanggal Keluar Sentul</th>
                            <th>Lama Di Sentul</th>
                            <th>Status Sentul</th>
                            <th>SLA Loading Sentul</th>
                            <th>Tanggal Tiba CCIE</th>
                            <th>Tanggal Keluar CCIE</th>
                            <th>Lama Di CCIE</th>
                            <th>Status CCIE</th>
                            <th>SLA Loading CCIE</th>
                            <th>Route</th>
                            <th>Shipping Point</th>
                            <th>Pulau</th>
                            <th>Via Kirim</th>
                            <th style="min-width: 130px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($logistik as $r)
                        <tr>
                            <form id="form-update-{{ $r->id }}" action="{{ route('planner.update', $r->id) }}" method="POST">
                                @csrf
                                @method('PUT')
                            </form>

                            <td class="text-center fw-semibold">{{ $r->no }}</td>
                            <td>
                                <input type="datetime-local" form="form-update-{{ $r->id }}" name="tanggal_naik_logistik" value="{{ $r->tanggal_naik_logistik ? date('Y-m-d\TH:i', strtotime($r->tanggal_naik_logistik)) : '' }}">
                            </td>
                            <td>
                                <input type="datetime-local" form="form-update-{{ $r->id }}" name="rencana_kirim" value="{{ $r->rencana_kirim ? date('Y-m-d\TH:i', strtotime($r->rencana_kirim)) : '' }}">
                            </td>
                            <td><input type="text" form="form-update-{{ $r->id }}" name="transport_lead_time" value="{{ $r->transport_lead_time }}"></td>
                            <td><input type="text" form="form-update-{{ $r->id }}" name="planner" value="{{ $r->planner }}"></td>
                            <td><input type="text" form="form-update-{{ $r->id }}" name="no_shipment" value="{{ $r->no_shipment }}"></td>
                            <td><input type="text" form="form-update-{{ $r->id }}" name="dist_channel" value="{{ $r->dist_channel }}"></td>
                            <td class="fw-semibold">{{ $r->tujuan }}</td>
                            <td>{{ $r->area }}</td>
                            <td><input type="text" form="form-update-{{ $r->id }}" name="ketersediaan_unit" value="{{ $r->ketersediaan_unit }}"></td>
                            <td><input type="text" form="form-update-{{ $r->id }}" name="mobil" value="{{ $r->mobil }}"></td>
                            <td><input type="text" form="form-update-{{ $r->id }}" name="perubahan_mobil" value="{{ $r->perubahan_mobil }}"></td>
                            <td><input type="number" form="form-update-{{ $r->id }}" name="nilai_muatan" value="{{ $r->nilai_muatan }}"></td>
                            <td><input type="number" form="form-update-{{ $r->id }}" name="biaya_kirim" value="{{ $r->biaya_kirim }}"></td>
                            <td><input type="text" form="form-update-{{ $r->id }}" name="cr" value="{{ $r->cr }}"></td>
                            <td><input type="text" form="form-update-{{ $r->id }}" name="kategori_ekspedisi" value="{{ $r->kategori_ekspedisi }}"></td>
                            <td><input type="text" form="form-update-{{ $r->id }}" name="ekpedisi" value="{{ $r->ekpedisi }}"></td>
                            <td><input type="text" form="form-update-{{ $r->id }}" name="nama_driver" value="{{ $r->nama_driver }}"></td>
                            <td><input type="text" form="form-update-{{ $r->id }}" name="no_pol" value="{{ $r->no_pol }}"></td>
                            <td>
                                <input type="datetime-local" form="form-update-{{ $r->id }}" name="tanggal_dpt_unit" value="{{ $r->tanggal_dpt_unit ? date('Y-m-d\TH:i', strtotime($r->tanggal_dpt_unit)) : '' }}">
                            </td>
                            <td>
                                <input type="datetime-local" form="form-update-{{ $r->id }}" name="tanggal_tiba_gudang" value="{{ $r->tanggal_tiba_gudang ? date('Y-m-d\TH:i', strtotime($r->tanggal_tiba_gudang)) : '' }}">
                            </td>
                         <td>
    <span class="badge-status {{ $r->status_pengiriman == 'Terlambat' ? 'bg-danger text-white' : ($r->status_pengiriman == 'Sudah Dapat' ? 'bg-success text-white' : 'bg-secondary text-white') }}">
        {{ $r->status_pengiriman ?? '-' }}
    </span>
</td>
                            <td><input type="text" form="form-update-{{ $r->id }}" name="keterangan" value="{{ $r->keterangan }}"></td>
                            <td class="text-center fw-medium text-primary">{{ $r->lama_waktu_pencarian }}</td>
                           <td>
    <span class="badge-status {{ $r->sla_dapat_mobil == 'Delay' ? 'bg-danger text-white' : ($r->sla_dapat_mobil == 'On Time' ? 'bg-success text-white' : 'bg-secondary text-white') }}">
        {{ $r->sla_dapat_mobil ?? '-' }}
    </span>
</td>
                            <td>
                                <input type="datetime-local" form="form-update-{{ $r->id }}" name="planning_loading" value="{{ $r->planning_loading ? date('Y-m-d\TH:i', strtotime($r->planning_loading)) : '' }}">
                            </td>
                            <td>
                                <input type="datetime-local" form="form-update-{{ $r->id }}" name="tanggal_keluar_gudang" value="{{ $r->tanggal_keluar_gudang ? date('Y-m-d\TH:i', strtotime($r->tanggal_keluar_gudang)) : '' }}">
                            </td>
                            <td class="text-center">{{ $r->lama_digudang }}</td>
                            <td class="text-center">{{ $r->status }}</td>
                            <td class="text-center">{{ $r->sla_loading }}</td>
                            
                            <td>
                                <input type="datetime-local" form="form-update-{{ $r->id }}" name="tanggal_tiba_gudang_2" value="{{ $r->tanggal_tiba_gudang_2 ? date('Y-m-d\TH:i', strtotime($r->tanggal_tiba_gudang_2)) : '' }}">
                            </td>
                            <td>
                                <input type="datetime-local" form="form-update-{{ $r->id }}" name="tanggal_keluar_gudang_2" value="{{ $r->tanggal_keluar_gudang_2 ? date('Y-m-d\TH:i', strtotime($r->tanggal_keluar_gudang_2)) : '' }}">
                            </td>
                            <td class="text-center">{{ $r->lama_digudang_2 }}</td>
                            <td class="text-center">{{ $r->status_gudang_2 }}</td>
                            <td class="text-center">{{ $r->sla_loading_2 }}</td>
                            
                            <td>
                                <input type="datetime-local" form="form-update-{{ $r->id }}" name="tanggal_tiba_gudang_3" value="{{ $r->tanggal_tiba_gudang_3 ? date('Y-m-d\TH:i', strtotime($r->tanggal_tiba_gudang_3)) : '' }}">
                            </td>
                            <td>
                                <input type="datetime-local" form="form-update-{{ $r->id }}" name="tanggal_keluar_gudang_3" value="{{ $r->tanggal_keluar_gudang_3 ? date('Y-m-d\TH:i', strtotime($r->tanggal_keluar_gudang_3)) : '' }}">
                            </td>
                            <td class="text-center">{{ $r->lama_digudang_3 }}</td>
                            <td class="text-center">{{ $r->status_gudang_3 }}</td>
                            <td class="text-center">{{ $r->sla_loading_3 }}</td>
                            <td>{{ $r->route }}</td>
                            <td class="text-center">{{ $r->route ? explode('-', trim($r->route))[0] : '-' }}</td>
                            <td>{{ $r->pulau }}</td>
                            <td>{{ $r->via_kirim }}</td>
                            <td>
                                <div class="btn-action">
                                    <button type="submit" form="form-update-{{ $r->id }}" class="btn btn-success btn-sm px-2 d-flex align-items-center gap-1">
                                        <i class="fa-solid fa-floppy-disk"></i> Save
                                    </button>
                                    <a href="{{ route('planner.delete',$r->id) }}" class="btn btn-danger btn-sm px-2 d-flex align-items-center gap-1" onclick="return confirm('Hapus data ini?')">
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
            $('#tablePlanner').DataTable({
                scrollX: true,
                pageLength: 10,
                columnDefs: [
                    { className: "dt-center", targets: [0, 21, 23, 24, 27, 28, 29, 32, 33, 34, 37, 38, 39, 41, 44] }
                ],
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Cari data shipment...",
                    lengthMenu: "Tampilkan _MENU_ data",
                    info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                    paginate: {
                        next: "<i class='fa-solid fa-chevron-right'></i>",
                        previous: "<i class='fa-solid fa-chevron-left'></i>"
                    }
                }
            });
        });
    </script>

</body>

</html>