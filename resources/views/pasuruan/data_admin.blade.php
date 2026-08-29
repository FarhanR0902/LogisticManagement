@include('template.sidebar')

<!DOCTYPE html>
<html lang="id">

<head>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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

        .green { background-color: #22c55e !important; }
        .red { background-color: #ef4444 !important; }
        .gray { background-color: #64748b !important; }
        .orange { background-color: #f97316 !important; }
        .yellow { background-color: #facc15 !important; color: #000 !important; }

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

        .dataTables_wrapper { padding-top: 10px; }

        table.dataTable {
            border-collapse: collapse !important;
            font-size: 15px;
        }

        table.dataTable tbody tr {
            background-color: #ffffff;
            transition: background-color 0.2s ease;
        }

        table.dataTable tbody tr:hover { background-color: #f1f5f9 !important; }

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

        table input[type="date"] { width: 165px; }

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

        .form-horizontal-scroll .field-box { flex: 0 0 240px; }

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

        .btn-action { display: inline-flex; gap: 5px; }

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

        .bg-orange { background: #fd7e14 !important; color: #fff; }

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

        input[type="datetime-local"] { min-width: 190px; }

        input[type="date"]:focus,
        input[type="datetime-local"]:focus {
            border-color: #38bdf8 !important;
            outline: none;
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.2);
        }

        td input[readonly] {
            background: #f1f5f9 !important;
            cursor: not-allowed;
        }

        /* loading overlay biar user tahu tabel lagi fetch data */
        #tablePlanner_processing {
            background: rgba(255, 255, 255, 0.85) !important;
            font-weight: 600;
            color: #0284c7;
        }

        /* ============================================================
         * TOMBOL SAVE ALL — badge jumlah baris belum tersimpan
         * ============================================================ */
        #btnSaveAllPasuruan .badge {
            font-size: 11px;
            padding: 3px 8px;
        }

        tr.row-dirty-pasuruan td {
            background-color: #fff7ed !important;
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

                {{-- ==========================================================
                     TOMBOL SAVE ALL — kirim semua baris yang berubah sekaligus,
                     tanpa harus edit satu-satu / nunggu autosave per field.
                     ========================================================== --}}
                <button type="button" id="btnSaveAllPasuruan"
                    class="btn btn-primary d-flex align-items-center gap-2"
                    style="background:#0284c7; border:none; border-radius:8px; padding:10px 16px;">
                    <i class="fa-solid fa-floppy-disk"></i>
                    Save All
                    <span id="unsavedCountPasuruan" class="badge bg-danger rounded-pill" style="display:none;">0</span>
                </button>

                <a href="{{ route('planner.export') }}"
                    class="btn btn-success d-flex align-items-center gap-2"
                    style="border-radius:8px;padding:10px 16px;">
                    <i class="fa-solid fa-file-excel"></i>
                    Export Excel
                </a>
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
                                    <select name="no_shipment_pasuruan" id="noShipmentTransportLaut" class="form-select">
                                        <option value="">Pilih Shipment</option>
                                    </select>
                                    <small class="text-muted">Ketik untuk mencari No Shipment (diambil dari server).</small>
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

                <div class="card">
                    <div class="table-responsive">
                        <table id="tablePlanner" class="display nowrap table table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th class="th-default">Tanggal Import</th>
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
                                    <th class="th-oren">No Pol</th>
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
                                {{-- SENGAJA DIKOSONGKAN — baris diisi lewat AJAX (server-side
                                     processing) oleh dataAjaxPasuruan(), bukan lewat Blade
                                     @foreach lagi. Ini kunci percepatan loading untuk data
                                     ribuan baris: server hanya mengirim baris yang sedang
                                     ditampilkan di halaman aktif (pageLength), bukan
                                     seluruh isi tabel sekaligus. Hidden form per baris juga
                                     sudah otomatis ikut terkirim menyatu di kolom pertama
                                     (lihat $hiddenForm di renderRowColumnsPasuruan()),
                                     jadi tidak perlu lagi @foreach form terpisah di luar
                                     tabel seperti versi lama. --}}
                            </tbody>
                        </table>
                    </div>
                </div>

                <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
            </div>

          <script>
                $(document).ready(function() {

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

                    function jalankanMaskingRupiahTabel() {
                        $('.row-nilai-muatan, .row-biaya-kirim, .modal-nilai-muatan, .modal-biaya-kirim').each(function() {
                            let v = $(this).val();
                            if (v && !v.includes('Rp')) {
                                $(this).val(formatKeRupiah(v));
                            }
                        });
                    }

                    function updateDateColor() {
                        $('input[type="date"], input[type="datetime-local"]').each(function() {
                            if ($(this).val()) {
                                $(this).removeClass('input-empty').addClass('input-filled');
                            } else {
                                $(this).removeClass('input-filled').addClass('input-empty');
                            }
                        });
                    }

                    function formatRupiah(angka) {
                        return 'Rp ' + new Intl.NumberFormat('id-ID').format(angka);
                    }

                    function hitungTotalKuli(row) {
                        let qty = parseFloat(row.find('input[name="actual_delivery_quantity_pasuruan"]').val()) || 0;
                        let biayaRaw = (row.find('input[name="biaya_kuli_pasuruan"]').val() || '').replace(/\./g, '') || 0;
                        let biaya = parseFloat(biayaRaw) || 0;
                        let total = qty * biaya;
                        row.find('input[name="total_biaya_kuli_pasuruan"]').val(formatRupiah(total));
                    }

                    // ========================================================
                    // select2 untuk select PER-BARIS. Karena tiap draw() ganti
                    // total isi <tbody> (bukan cuma sembunyi/tampil seperti
                    // client-side paging dulu), DOM selalu baru — jadi aman
                    // untuk langsung init tanpa guard select2-hidden-accessible.
                    // ========================================================
                    function initSelect2RowLevel() {
                        $('.reason-tiba-select').select2({
                            theme: 'bootstrap-5', width: '100%', placeholder: 'Pilih Reason Tiba',
                            allowClear: true, dropdownAutoWidth: true, dropdownParent: $('body')
                        });
                        $('.reason-bongkar-select').select2({
                            theme: 'bootstrap-5', width: '100%', placeholder: 'Pilih Reason Bongkar',
                            allowClear: true, dropdownAutoWidth: true, dropdownParent: $('body')
                        });
                        $('.select-tarif-row').select2({
                            theme: 'bootstrap-5', width: '100%',
                            allowClear: true, dropdownAutoWidth: true, dropdownParent: $('body')
                        });
                        $('.reason-selisih-select').select2({
                            theme: 'bootstrap-5', width: '100%', placeholder: 'Pilih Reason Selisih',
                            allowClear: true, dropdownAutoWidth: true, dropdownParent: $('body')
                        });
                    }

                    // ============================================================
                    // DATA TARIF PENGIRIMAN — dipakai untuk cascading dropdown
                    // (Route -> Mobil -> Ekspedisi) dan preview biaya_kirim di
                    // browser. Nilai final tetap dihitung ulang & disimpan oleh
                    // controller saat submit/autosave, ini cuma preview visual.
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
                            scope.find('[name="biaya_kirim_pasuruan"]').val(formatKeRupiah(match.biaya_kirim));
                        }
                    }

                    // --- Cascading untuk MODAL Add New Shipment ---
                    $('#addModal').on('change', '[name="route_pasuruan"]', function() {
                        updateCascadeMobilEkspedisi($('#addModal'), $(this).val());
                    });
                    $('#addModal').on('change', '[name="route_pasuruan"], [name="mobil_pasuruan"], [name="ekspedisi_pasuruan"]', function() {
                        previewBiayaKirim($('#addModal'));
                    });

                    // --- Cascading untuk PER-BARIS di tabel (event delegation,
                    //     tetap jalan walau baris diganti tiap draw AJAX) ---
                    $(document).on('change', '#tablePlanner [name="route_pasuruan"]', function() {
                        let row = $(this).closest('tr');
                        let currentMobil = row.find('[name="mobil_pasuruan"]').val();
                        let currentEks = row.find('[name="ekspedisi_pasuruan"]').val();
                        updateCascadeMobilEkspedisi(row, $(this).val(), currentMobil, currentEks);
                    });
                    $(document).on('change', '#tablePlanner [name="route_pasuruan"], #tablePlanner [name="mobil_pasuruan"], #tablePlanner [name="ekspedisi_pasuruan"]', function() {
                        previewBiayaKirim($(this).closest('tr'));
                    });

                    // ========================================================
                    // FILTER STATE — dikirim sebagai parameter tambahan ke
                    // dataAjaxPasuruan() lewat ajax.data. Controller kamu
                    // sudah baca planner_filter / area_filter / create_tgl_filter.
                    // ========================================================
                    var plannerFilter = '';
                    var areaFilter = '';
                    var createTglFilter = '';

                    // ========================================================
                    // INISIALISASI DATATABLES — SERVER-SIDE PROCESSING
                    // ========================================================
                    var table = $('#tablePlanner').DataTable({
                        processing: true,
                        serverSide: true,
                        ordering: false, // controller belum baca parameter order; sorting selalu by id desc
                        scrollX: true,
                        pageLength: 10,
                        lengthMenu: [10, 25, 50, 100, 250],
                        ajax: {
                            // Dipakai POST karena tabel ini punya banyak kolom —
                            // GET bisa kena limit panjang URL (414).
                            url: "{{ route('pasuruan.dataAjaxPasuruan') }}",
                            type: 'POST',
                            data: function(d) {
                                d._token = "{{ csrf_token() }}";
                                d.planner_filter = plannerFilter;
                                d.area_filter = areaFilter;
                                d.create_tgl_filter = createTglFilter;
                            }
                        },
                        columnDefs: [{
                            className: "dt-center",
                            targets: [0, 25, 26, 27, 28, 29, 30]
                        }],

                        // ====================================================
                        // rowCallback — ambil id baris dari hidden form
                        // id="form-update-{id}" (sudah otomatis ikut terkirim
                        // di kolom pertama, lihat $hiddenForm di controller),
                        // lalu simpan ke atribut data-id pada <tr> supaya bisa
                        // dipakai Save All / dirty tracking.
                        // ====================================================
                        rowCallback: function(row, data) {
                            let $row = $(row);
                            let $hiddenForm = $row.find('form[id^="form-update-"]').first();
                            if ($hiddenForm.length) {
                                let id = $hiddenForm.attr('id').replace('form-update-', '');
                                $row.attr('data-id', id);
                            }
                        },

                        drawCallback: function() {
                            jalankanMaskingRupiahTabel();
                            initSelect2RowLevel();
                            updateDateColor();

                            // init cascading Route -> Mobil/Ekspedisi utk baris
                            // yang baru datang dari server (supaya opsi mobil/
                            // ekspedisi ke-filter sesuai route tersimpan)
                            $('#tablePlanner tbody tr').each(function() {
                                let row = $(this);
                                let routeVal = row.find('[name="route_pasuruan"]').val();
                                let mobilVal = row.find('[name="mobil_pasuruan"]').val();
                                let eksVal = row.find('[name="ekspedisi_pasuruan"]').val();
                                if (routeVal) {
                                    updateCascadeMobilEkspedisi(row, routeVal, mobilVal, eksVal);
                                }
                                // NOTE: rencana_kirim_pasuruan TIDAK lagi dihitung/
                                // di-lock otomatis di sini — sekarang full manual.

                                // baris yang masih dirty (belum ke-save & belum
                                // di-clear) tetap ditandai visual setelah reload
                                let id = row.data('id');
                                if (id && dirtyRowsPasuruan.has(String(id))) {
                                    row.addClass('row-dirty-pasuruan');
                                }
                            });

                            this.api().columns.adjust();
                        }
                    });

                    $('#filterPlanner').on('change', function() {
                        plannerFilter = $(this).val() || '';
                        table.draw();
                    });

                    $('#filterArea').on('change', function() {
                        areaFilter = $(this).val() || '';
                        table.draw();
                    });

                    $('#filterCreateTgl').on('change', function() {
                        createTglFilter = $(this).val() || '';
                        table.draw();
                    });

                    // ========================================================
                    // SELECT2 UNTUK FILTER HEADER (init sekali saja, elemen ini
                    // statis / tidak diganti tiap draw AJAX)
                    // ========================================================
                    $('.planner-select').select2({
                        theme: 'bootstrap-5', width: '100%', placeholder: 'Semua Planner', allowClear: true
                    });
                    $('.area-select').select2({
                        theme: 'bootstrap-5', width: '100%', placeholder: 'Semua Area', allowClear: true
                    });
                    $('.select-tarif').select2({
                        theme: 'bootstrap-5', width: '100%', allowClear: true, dropdownParent: $('#addModal')
                    });

                    // ==========================================================
                    // DIRTY TRACKING + SAVE ALL
                    // Sebelumnya: tiap ketik -> tunggu 500ms -> auto kirim AJAX
                    // per baris (autosave langsung).
                    // Sekarang: tiap ketik cuma DITANDAI dirty (visual oranye +
                    // badge jumlah). Kirim ke server HANYA saat tombol
                    // "Save All" diklik, sekaligus untuk semua baris yang dirty.
                    // ==========================================================
                    let dirtyRowsPasuruan = new Set();

                    function updateUnsavedBadgePasuruan() {
                        if (dirtyRowsPasuruan.size > 0) {
                            $('#unsavedCountPasuruan').text(dirtyRowsPasuruan.size).show();
                        } else {
                            $('#unsavedCountPasuruan').hide();
                        }
                    }

                    function markRowDirtyPasuruan($el) {
                        let row = $el.closest('tr');
                        let id = row.data('id');
                        if (!id) return;
                        dirtyRowsPasuruan.add(String(id));
                        row.addClass('row-dirty-pasuruan');
                        updateUnsavedBadgePasuruan();
                    }

                    // saveRow SEKARANG return promise AJAX-nya (bukan
                    // fire-and-forget), supaya Save All bisa nunggu semua
                    // request selesai lewat $.when()
                    function saveRow(id) {
                        let row = $('tr[data-id="' + id + '"]');
                        if (!row.length) return $.Deferred().resolve();

                        return $.ajax({
                            url: "{{ url('pasuruan') }}/" + id,   // respects subfolder/base path
                            type: 'POST',
                            data: {
                                _token: '{{ csrf_token() }}',
                                _method: 'PUT',
                                tanggal_terima_po_pasuruan: row.find('[name="tanggal_terima_po_pasuruan"]').val(),
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
                                reason_waktu_bongkar_pasuruan: row.find('[name="reason_waktu_bongkar_pasuruan"]').val(),
                                nama_kapal_pasuruan: row.find('[name="nama_kapal_pasuruan"]').val(),
                                etd_pasuruan: row.find('[name="etd_pasuruan"]').val(),
                                eta_pasuruan: row.find('[name="eta_pasuruan"]').val(),
                                atd_pasuruan: row.find('[name="atd_pasuruan"]').val(),
                                ata_pasuruan: row.find('[name="ata_pasuruan"]').val(),
                                pic_monitoring_pasuruan: row.find('[name="pic_monitoring_pasuruan"]').val(),
                                remarks_pasuruan: row.find('[name="remarks_pasuruan"]').val(),
                                nama_driver_pasuruan: row.find('[name="nama_driver_pasuruan"]').val(),
                                no_pol_pasuruan: row.find('[name="no_pol_pasuruan"]').val(),
                                act_urutan_bongkar_pasuruan: row.find('[name="act_urutan_bongkar_pasuruan"]').val(),
                                tanggal_tiba_pasuruan: row.find('[name="tanggal_tiba_pasuruan"]').val(),
                                tanggal_bongkar_pasuruan: row.find('[name="tanggal_bongkar_pasuruan"]').val()
                            },
                            success: function() {
                                console.log("Saved " + id);
                            },
                            error: function(xhr) {
                                console.log("Gagal save row " + id, xhr.status, xhr.responseText);
                            }
                        });
                    }

                    // Ganti listener lama: sekarang cuma menandai dirty,
                    // TIDAK langsung kirim AJAX tiap kali user mengetik
                    $(document).on('change input', '#tablePlanner input, #tablePlanner select, #tablePlanner textarea', function() {
                        markRowDirtyPasuruan($(this));
                    });

                    // ==========================================================
                    // TOMBOL SAVE ALL
                    // ==========================================================
                    $('#btnSaveAllPasuruan').on('click', function() {
                        if (dirtyRowsPasuruan.size === 0) {
                            alert('Belum ada perubahan untuk disimpan.');
                            return;
                        }

                        let btn = $(this);
                        btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...');

                        let ids = Array.from(dirtyRowsPasuruan);
                        let requests = ids.map(id => saveRow(id));

                        $.when.apply($, requests)
                            .done(function() {
                                dirtyRowsPasuruan.clear();
                                updateUnsavedBadgePasuruan();
                                alert('Semua perubahan (' + ids.length + ' baris) berhasil disimpan!');
                                table.ajax.reload(null, false); // refresh data, tetap di halaman yang sama
                            })
                            .fail(function() {
                                alert('Sebagian data gagal disimpan, cek console (F12) untuk detail.');
                            })
                            .always(function() {
                                btn.prop('disabled', false).html(
                                    '<i class="fa-solid fa-floppy-disk"></i> Save All ' +
                                    '<span id="unsavedCountPasuruan" class="badge bg-danger rounded-pill" style="display:none;">0</span>'
                                );
                                updateUnsavedBadgePasuruan();
                            });
                    });

                    $(document).on('input', '.row-nilai-muatan, .row-biaya-kirim', function() {
                        $(this).val(formatKeRupiah(ambilAngkaMurni($(this).val())));
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

                    $(document).on('input', 'input[name="biaya_kuli_pasuruan"]', function() {
                        let raw = $(this).val().replace(/\D/g, '');
                        $(this).val(raw ? new Intl.NumberFormat('id-ID').format(raw) : '');
                        hitungTotalKuli($(this).closest('tr'));
                    });

                    $(document).on('change', 'input[type="date"], input[type="datetime-local"]', function() {
                        updateDateColor();
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

                    $(document).on('input', '[name="total_do_pasuruan"], [name="selisih_quantity_pasuruan"]', function() {
                        let row = $(this).closest('tr');
                        let total = parseFloat(row.find('[name="total_do_pasuruan"]').val()) || 0;
                        let selisih = parseFloat(row.find('[name="selisih_quantity_pasuruan"]').val()) || 0;
                        let actual = total - selisih;
                        row.find('[name="actual_delivery_quantity_pasuruan"]').val(actual);
                        hitungTotalKuli(row);
                    });

                    // ========================================================
                    // Dropdown No Shipment untuk modal Transport Laut.
                    // ========================================================
                    $('#transportLautModal').on('show.bs.modal', function() {
                        let $select = $('#noShipmentTransportLaut');
                        if ($select.data('loaded')) return;

                        $.get("{{ route('pasuruan.listNoShipment') }}", function(list) {
                            $select.empty().append('<option value="">Pilih Shipment</option>');
                            list.forEach(function(item) {
                                $select.append(`<option value="${item.no_shipment_pasuruan}">${item.no_shipment_pasuruan} - ${item.tujuan_pasuruan ?? ''}</option>`);
                            });
                            $select.data('loaded', true);
                        }).fail(function() {
                            console.log('Gagal memuat daftar No Shipment untuk Transport Laut.');
                        });
                    });

                    // ========================================================
                    // Peringatan sebelum menutup/reload halaman kalau masih
                    // ada perubahan yang belum di-Save All
                    // ========================================================
                    window.addEventListener('beforeunload', function(e) {
                        if (dirtyRowsPasuruan.size > 0) {
                            e.preventDefault();
                            e.returnValue = '';
                        }
                    });
                });
            </script>

</body>

</html>