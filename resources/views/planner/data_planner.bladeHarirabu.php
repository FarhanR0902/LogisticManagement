@include('template.sidebar')

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>DATA PLANNER</title>

    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', sans-serif;
        }

        body {
            background: #f3f4f6;
        }

        .container {
            width: calc(100% - 250px);
            margin-left: 250px;
            padding: 20px;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 15px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, .08);
        }

        .title {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 15px;
        }

        table {
            width: 100%;
            font-size: 12px;
        }

        th {
            background: #111827;
            color: white;
            text-align: center;
            white-space: nowrap;
            padding: 8px;
        }

        td {
            white-space: nowrap;
            padding: 4px;
        }

        input {
            width: 120px;
            padding: 4px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 12px;
        }

        .btn-save {
            background: #16a34a;
            color: white;
            border: none;
            padding: 6px 10px;
            border-radius: 5px;
        }

        .btn-save:hover {
            background: #15803d;
        }

        .form-horizontal-scroll {
            overflow-x: auto;
            overflow-y: hidden;
            white-space: nowrap;
            padding-bottom: 10px;
        }

        .form-horizontal-scroll .field-box {
            display: inline-block;
            width: 220px;
            margin-right: 10px;
            vertical-align: top;
        }

        .form-horizontal-scroll label {
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 3px;
            display: block;
        }

        .form-horizontal-scroll .form-control {
            width: 100%;
        }
    </style>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</head>

<body>

    <div class="container">

        <div class="title">DATA PLANNER</div>

        @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
        @endif

        <!-- BUTTON -->
        <button type="button"
            class="btn btn-success mb-3"
            data-bs-toggle="modal"
            data-bs-target="#addModal">
            + Add New Shipment
        </button>

        <!-- MODAL ADD -->
        <div class="modal fade" id="addModal" tabindex="-1">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">

                    <form action="{{ route('planner.store') }}" method="POST">
                        @csrf

                        <div class="modal-header">
                            <h5 class="modal-title">Add New Shipment</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">

                            <div class="form-horizontal-scroll">

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
                                    <label>Lead Time</label>
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
                                    <label>Planning Loading Sentul</label>
                                    <input type="datetime-local" name="planning_loading_2" class="form-control">
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
                                    <label>Planning Loading CCIE</label>
                                    <input type="datetime-local" name="planning_loading_3" class="form-control">
                                </div>

                                <div class="field-box">
                                    <label>Tanggal Keluar CCIE</label>
                                    <input type="datetime-local" name="tanggal_keluar_gudang_3" class="form-control">
                                </div>

                                
                                <div class="field-box" style="width:400px;">
                                    <label>Keterangan</label>
                                    <textarea name="keterangan" rows="2" class="form-control"></textarea>
                                </div>

                            </div>

                        </div>

                        <div class="modal-footer">
                            <button type="button"
                                class="btn btn-secondary"
                                data-bs-dismiss="modal">
                                Close
                            </button>

                            <button type="submit"
                                class="btn btn-success">
                                Save Shipment
                            </button>
                        </div>

                    </form>

                </div>
            </div>
        </div>

        <!-- TABLE -->
        <div class="card">
            <table id="tablePlanner" class="display nowrap">

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






                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($logistik as $r)
                    <tr>
                        <form action="{{ route('planner.update',$r->id) }}" method="POST">
                            @csrf
                            @method('PUT')

                            <td>{{ $r->no }}</td>

                            <td>
                                <input type="datetime-local"
                                    name="tanggal_naik_logistik"
                                    value="{{ $r->tanggal_naik_logistik ? date('Y-m-d\TH:i',strtotime($r->tanggal_naik_logistik)) : '' }}">
                            </td>

                            <td>
                                <input type="datetime-local"
                                    name="rencana_kirim"
                                    value="{{ $r->rencana_kirim ? date('Y-m-d\TH:i',strtotime($r->rencana_kirim)) : '' }}">
                            </td>

                            <td><input type="text" name="transport_lead_time" value="{{ $r->transport_lead_time }}"></td>

                            <td><input type="text" name="planner" value="{{ $r->planner }}"></td>

                            <td><input type="text" name="no_shipment" value="{{ $r->no_shipment }}"></td>

                            <td><input type="text" name="dist_channel" value="{{ $r->dist_channel }}"></td>

                            <td><input type="text" name="tujuan" value="{{ $r->tujuan }}"></td>

                            <td><input type="text" name="area" value="{{ $r->area }}"></td>

                            <td><input type="text" name="ketersediaan_unit" value="{{ $r->ketersediaan_unit }}"></td>

                            <td><input type="text" name="mobil" value="{{ $r->mobil }}"></td>

                            <td><input type="text" name="perubahan_mobil" value="{{ $r->perubahan_mobil }}"></td>

                            <td><input type="number" name="nilai_muatan" value="{{ $r->nilai_muatan }}"></td>

                            <td><input type="number" name="biaya_kirim" value="{{ $r->biaya_kirim }}"></td>

                            <td><input type="text" name="cr" value="{{ $r->cr }}"></td>

                            <td><input type="text" name="kategori_ekspedisi" value="{{ $r->kategori_ekspedisi }}"></td>

                            <td><input type="text" name="ekpedisi" value="{{ $r->ekpedisi }}"></td>

                            <td><input type="text" name="nama_driver" value="{{ $r->nama_driver }}"></td>

                            <td><input type="text" name="no_pol" value="{{ $r->no_pol }}"></td>

                            <td>
                                <input type="datetime-local"
                                    name="tanggal_dpt_unit"
                                    value="{{ $r->tanggal_dpt_unit ? date('Y-m-d\TH:i',strtotime($r->tanggal_dpt_unit)) : '' }}">
                            </td>

                            <td>
                                <input type="datetime-local"
                                    name="tanggal_tiba_gudang"
                                    value="{{ $r->tanggal_tiba_gudang ? date('Y-m-d\TH:i',strtotime($r->tanggal_tiba_gudang)) : '' }}">
                            </td>

                            <td><input type="text" name="status_pengiriman" value="{{ $r->status_pengiriman }}"></td>

                            <td><input type="text" name="keterangan" value="{{ $r->keterangan }}"></td>

                            <td><input type="text" name="lama_waktu_pencarian" value="{{ $r->lama_waktu_pencarian }}"></td>

                            <td><input type="text" name="sla_dapat_mobil" value="{{ $r->sla_dapat_mobil }}"></td>

                            <td>
                                <input type="datetime-local"
                                    name="planning_loading"
                                    value="{{ $r->planning_loading ? date('Y-m-d\TH:i',strtotime($r->planning_loading)) : '' }}">
                            </td>

                            <td>
                                <input type="datetime-local"
                                    name="tanggal_keluar_gudang"
                                    value="{{ $r->tanggal_keluar_gudang ? date('Y-m-d\TH:i',strtotime($r->tanggal_keluar_gudang)) : '' }}">
                            </td>

                            <td><input type="text" name="lama_digudang" value="{{ $r->lama_digudang }}"></td>

                            <td><input type="text" name="status" value="{{ $r->status }}"></td>

                            <td><input type="text" name="sla_loading" value="{{ $r->sla_loading }}"></td>

                            <!-- GUDANG 2 -->

                            <td>
                                <input type="datetime-local"
                                    name="tanggal_tiba_gudang_2"
                                    value="{{ $r->tanggal_tiba_gudang_2 ? date('Y-m-d\TH:i',strtotime($r->tanggal_tiba_gudang_2)) : '' }}">
                            </td>

                            <td>
                                <input type="datetime-local"
                                    name="tanggal_keluar_gudang_2"
                                    value="{{ $r->tanggal_keluar_gudang_2 ? date('Y-m-d\TH:i',strtotime($r->tanggal_keluar_gudang_2)) : '' }}">
                            </td>

                            <td><input type="text" name="lama_digudang_2" value="{{ $r->lama_digudang_2 }}"></td>

                            <td><input type="text" name="sla_loading_2" value="{{ $r->sla_loading_2 }}"></td>

                            <td><input type="text" name="status_gudang_2" value="{{ $r->status_gudang_2 }}"></td>

                            <!-- GUDANG 3 -->

                            <td>
                                <input type="datetime-local"
                                    name="tanggal_tiba_gudang_3"
                                    value="{{ $r->tanggal_tiba_gudang_3 ? date('Y-m-d\TH:i',strtotime($r->tanggal_tiba_gudang_3)) : '' }}">
                            </td>

                            <td>
                                <input type="datetime-local"
                                    name="tanggal_keluar_gudang_3"
                                    value="{{ $r->tanggal_keluar_gudang_3 ? date('Y-m-d\TH:i',strtotime($r->tanggal_keluar_gudang_3)) : '' }}">
                            </td>

                            <td><input type="text" name="lama_digudang_3" value="{{ $r->lama_digudang_3 }}"></td>

                            <td><input type="text" name="sla_loading_3" value="{{ $r->sla_loading_3 }}"></td>

                            <td><input type="text" name="status_gudang_3" value="{{ $r->status_gudang_3 }}"></td>

                            <td>
                                <input type="text" name="route" value="{{ $r->route }}" class="form-control form-control-sm">
                            </td>

                            <td>
                                <input type="text"
                                    name="route_first"
                                    value="{{ $r->route ? explode('-', trim($r->route))[0] : '-' }}"
                                    class="form-control form-control-sm">
                            </td>

                            <td>
                                <input type="text" name="pulau" value="{{ $r->pulau }}" class="form-control form-control-sm">
                            </td>

                            <td>
                                <input type="text" name="via_kirim" value="{{ $r->via_kirim }}" class="form-control form-control-sm">
                            </td>

                       
                            <td>
                                <button type="submit" class="btn btn-success btn-sm">
                                    Save
                                </button>

                                <a href="{{ route('planner.delete',$r->id) }}"
                                    class="btn btn-danger btn-sm"
                                    onclick="return confirm('Hapus data ini?')">
                                    Delete
                                </a>
                            </td>

                        </form>
                    </tr>
                    @endforeach
                </tbody>

            </table>
        </div>

    </div>

    <script>
        $(document).ready(function() {
            $('#tablePlanner').DataTable({
                scrollX: true,
                pageLength: 10
            });
        });
    </script>

</body>

</html>