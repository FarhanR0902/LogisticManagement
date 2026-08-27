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
            border-collapse: separate !important;
            border-spacing: 0;
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

        #tablePlanner .select2-container { width: 150px !important; }

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

        #tablePlanner .select2-container--bootstrap-5 .select2-selection__arrow { height: 28px; top: 0; }

        #tablePlanner .select2-container--bootstrap-5.select2-container--focus .select2-selection,
        #tablePlanner .select2-container--bootstrap-5.select2-container--open .select2-selection {
            border-color: #38bdf8;
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.2);
        }

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

        #addModal .select2-container { width: 100% !important; }

        #addModal .select2-container--bootstrap-5 .select2-selection {
            min-height: 38px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            font-size: 13px;
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

        .missing-field-box { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }

        #alertControlBox .box-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        #alertControlBox .box-header b { font-size: 15px; color: #1e293b; }

        #alertControlList { max-height: 260px; overflow-y: auto; }

        .alert-item {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 10px 12px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all .15s ease;
        }

        .alert-item:hover { background: #f1f5f9; transform: translateY(-1px); }

        .alert-item .alert-top { display: flex; justify-content: space-between; align-items: center; }

        .alert-item .alert-missing { font-size: 12px; color: #6b7280; margin-top: 4px; white-space: normal; }

        .completeness-badge { white-space: normal; max-width: 220px; line-height: 1.4; }

        .highlight-row td { background: #fde68a !important; transition: background-color .3s ease; }

        .toast-container { position: fixed; top: 20px; right: 20px; width: 350px; z-index: 99999; }

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

        .toast strong { display: block; margin-bottom: 5px; color: #fbbf24; }

        @keyframes slideIn {
            from { transform: translateX(120%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        /* Loading overlay ringan untuk tabel saat ajax reload */
        .dt-loading-overlay {
            position: absolute;
            inset: 0;
            background: rgba(255,255,255,0.6);
            display: none;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: #0284c7;
            z-index: 10;
        }
        #tablePlannerWrap { position: relative; }
    </style>
</head>

<body>

    <div class="toast-container" id="toastContainer"></div>

    <div class="container-fluid-custom">

        <div class="page-header">
            <div class="title">Data Planner</div>

            <div class="d-flex align-items-center gap-2">
                <a href="#" id="btnExport" class="btn btn-success">Export</a>

                <button type="button" id="btnSaveAll"
                    class="btn btn-primary d-flex align-items-center gap-2"
                    style="background:#0284c7; border:none; border-radius:8px; padding:10px 16px;">
                    <i class="fa-solid fa-floppy-disk"></i>
                    Save
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

        <div class="row mb-3">

            <div class="col-md-3">
                <label class="form-label fw-bold">Filter data Import</label>
                <input type="date" id="filterCreateTgl" class="form-control">
            </div>

            <div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-centered">
                    <div class="modal-content shadow-lg border-0" style="border-radius:16px;">

                        <form action="{{ route('spvplanner.store') }}" method="POST">
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
                                    <input type="date" name="create_tgl" class="form-control">
                                </div>

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
                                        <input type="text" name="nilai_muatan" class="form-control modal-nilai-muatan input-rupiah">
                                    </div>

                                    <div class="field-box">
                                        <label>Biaya Kirim</label>
                                        <input type="text" name="biaya_kirim" class="form-control modal-biaya-kirim input-rupiah">
                                    </div>

                                    <div class="field-box">
                                        <label>CR (%)</label>
                                        <input type="text" name="cr" readonly class="form-control modal-cr" style="background:#e2e8f0;">
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
                                        <textarea name="keterangan" rows="2" class="form-control"></textarea>
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
                    <select id="filterPlanner" class="form-select">
                        <option value="">Semua Planner</option>
                        @foreach($planners as $planner)
                            <option value="{{ $planner }}">{{ $planner }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold">Filter Area</label>
                    <select id="filterArea" class="form-select">
                        <option value="">Semua Area</option>
                        @foreach($areas as $area)
                            <option value="{{ $area }}">{{ $area }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- ===== SUMMARY: FIELD YANG PALING BANYAK KOSONG (via AJAX ringan) ===== --}}
                <div class="card mb-3">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <b style="font-size:14px; color:#374151;">📋 Field belum lengkap:</b>
                    </div>
                    <div class="missing-field-box" id="missingFieldSummary">
                        <span class="badge gray">Menghitung...</span>
                    </div>
                </div>

                {{-- ===== ALERT CONTROL BOX (via AJAX ringan) ===== --}}
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
                    <div class="table-responsive" id="tablePlannerWrap">
                        <div class="dt-loading-overlay" id="dtLoadingOverlay">Memuat data...</div>
                        <table id="tablePlanner" class="display nowrap table table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th class="th-default">Tanggal Import</th>
                                    <th class="th-oren">Nama Planner</th>
                                    <th class="th-oren">No Shipment</th>

                                    <th class="th-edit">Tanggal Terima Dari Admin</th>
                                    <th class="th-edit">Rencana Kirim</th>
                                    <th class="th-edit">Tanggal Dapat Unit</th>
                                    <th class="th-edit">Planning Loading <span style="color:#0047FF;font-weight:900;">KACS</span></th>
                                    <th class="th-edit">Tanggal Tiba <span style="color:#0047FF;font-weight:900;">KACS</span></th>
                                    <th class="th-edit">Tanggal Keluar <span style="color:#0047FF;font-weight:900;">KACS</span></th>
                                    <th class="th-edit">Planning Loading <span style="color:#FF6B00;font-weight:900;">Sentul</span></th>
                                    <th class="th-edit">Tanggal Tiba <span style="color:#FF6B00;font-weight:900;">Sentul</span></th>
                                    <th class="th-edit">Tanggal Keluar <span style="color:#FF6B00;font-weight:900;">Sentul</span></th>
                                    <th class="th-edit">Planning Loading <span style="color:#FF0033;font-weight:900;">CCIE</span></th>
                                    <th class="th-edit">Tanggal Tiba <span style="color:#FF0033;font-weight:900;">CCIE</span></th>
                                    <th class="th-edit">Tanggal Keluar <span style="color:#FF0033;font-weight:900;">CCIE</span></th>

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
                                {{-- Kosong: diisi via server-side DataTables (ajax) --}}
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

                    // ========================================================
                    // HELPER RUPIAH
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

                    // Row sudah datang dari server dalam format Rupiah/format akhir,
                    // fungsi ini hanya jaga-jaga untuk input baru di modal.
                    function jalankanMaskingRupiahModal() {
                        $('.modal-nilai-muatan, .modal-biaya-kirim').each(function() {
                            let v = $(this).val();
                            if (v && !v.includes('Rp')) {
                                $(this).val(formatKeRupiah(v));
                            }
                        });
                    }

                    // ========================================================
                    // FILTER STATE
                    // ========================================================
                    var areaFilter = '';
                    var plannerFilter = '';
                    var createTglFilter = '';

                    // ========================================================
                    // INIT DATATABLES - SERVER SIDE
                    // ========================================================
                    var table = $('#tablePlanner').DataTable({
                        serverSide: true,
                        processing: true,
                        scrollX: true,
                        autoWidth: false,
                        pageLength: 10,
                        searchDelay: 400,
                        ajax: {
                            url: "{{ route('spvplanner.data.ajax') }}",
                            type: 'POST',
                            data: function(d) {
                                d.planner_filter = plannerFilter;
                                d.area_filter = areaFilter;
                                d.create_tgl_filter = createTglFilter;
                                d._token = '{{ csrf_token() }}';
                            },
                            beforeSend: function() {
                                $('#dtLoadingOverlay').css('display', 'flex');
                            },
                            complete: function() {
                                $('#dtLoadingOverlay').hide();
                            }
                        },
                        columnDefs: [{
                            className: "dt-center",
                            targets: [0, 1, 2, 27, 31, 33, 34, 37, 38, 39, 43]
                        }],
                        rowCallback: function(row, data, index) {
                            // data terakhir array kolom biasa; kita simpan id lewat data attribute
                            // id diselipkan lewat kolom Hapus (delete link) -> ambil dari sana
                            let $row = $(row);
                            let deleteLink = $row.find('a[href*="/spvplanner/delete/"]').attr('href');
                            let id = deleteLink ? deleteLink.split('/').pop() : null;
                            if (id) {
                                $row.attr('data-id', id);
                                $row.addClass('autosave-row');
                            }
                        },
                        drawCallback: function() {
                            jalankanMaskingRupiahModal();
                            initSelect2Row();
                            hitungSemuaCostRatioTabel();
                            updateDateColor();
                            this.api().columns.adjust();
                        }
                    });

                    // ========================================================
                    // HITUNG CR (LIVE PREVIEW) - hanya di baris yang sedang tampil
                    // (halaman aktif, max ~10-25 baris karena server-side)
                    // ========================================================
                    function hitungSemuaCostRatioTabel() {

                        var shipmentGroups = {};

                        $('#tablePlanner tbody tr').each(function() {
                            var row = $(this);
                            var noShipment = (row.find('.row-no-shipment').val() || '').trim();
                            if (!noShipment) return;

                            var muatan = ambilAngkaMurni(row.find('.row-nilai-muatan').val());
                            var biaya = ambilAngkaMurni(row.find('.row-biaya-kirim').val());

                            if (!shipmentGroups[noShipment]) {
                                shipmentGroups[noShipment] = { totalMuatan: 0, totalBiaya: 0 };
                            }

                            shipmentGroups[noShipment].totalMuatan += muatan;
                            shipmentGroups[noShipment].totalBiaya = Math.max(
                                shipmentGroups[noShipment].totalBiaya,
                                biaya
                            );
                        });

                        $('#tablePlanner tbody tr').each(function() {
                            var row = $(this);
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
                        markRowDirty($(this));
                    });

                    $(document).on('input', '.row-biaya-kirim', function() {
                        $(this).val(formatKeRupiah(ambilAngkaMurni($(this).val())));
                        hitungSemuaCostRatioTabel();
                        markRowDirty($(this));
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

                    // Proteksi backend: kembalikan ke angka murni sebelum submit form modal
                    $('form').on('submit', function() {
                        $('.modal-nilai-muatan, .modal-biaya-kirim').each(function() {
                            let nilaiSekarang = $(this).val();
                            if (nilaiSekarang) {
                                $(this).val(nilaiSekarang.replace(/[^0-9]/g, ''));
                            }
                        });
                    });

                    // =========================
                    // SELECT2 (filter atas)
                    // =========================
                    $('#filterPlanner, #filterArea').select2({
                        theme: 'bootstrap-5',
                        width: '100%',
                        placeholder: function() { return $(this).find('option:first').text(); },
                        allowClear: true
                    });

                    // Select2 untuk dropdown DI DALAM baris (hanya baris yang sedang tampil,
                    // karena server-side otomatis hanya render 1 halaman)
                    function initSelect2Row() {
                        $('#tablePlanner tbody tr .select2-row').each(function() {
                            if (!$(this).hasClass('select2-hidden-accessible')) {
                                $(this).select2({
                                    theme: 'bootstrap-5',
                                    width: '150px',
                                    dropdownAutoWidth: true,
                                    dropdownParent: $('body'),
                                    placeholder: 'Cari...',
                                    allowClear: true
                                });

                                if ($(this).is('.row-route, .row-mobil, .row-ekpedisi')) {
                                    $(this).off('select2:select.autotarif').on('select2:select.autotarif', function() {
                                        let row = $(this).closest('tr');
                                        cariBiayaKirimRow(row);
                                        markRowDirty($(this));
                                    });
                                }

                                $(this).off('select2:select.dirty select2:clear.dirty')
                                    .on('select2:select.dirty select2:clear.dirty', function() {
                                        markRowDirty($(this));
                                    });
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
                        }
                    }

                    // ======================
                    // FILTER: Area / Planner / Tanggal Import
                    // (kirim ulang ke server, bukan filter DOM)
                    // ======================
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

                    // ==========================================================
                    // DIRTY TRACKING + SAVE ALL (autosave per baris, tetap sama)
                    // ==========================================================
                    let dirtyRows = new Set();

                    function updateUnsavedBadge() {
                        if (dirtyRows.size > 0) {
                            $('#unsavedCount').text(dirtyRows.size).show();
                        } else {
                            $('#unsavedCount').hide();
                        }
                    }

                    function markRowDirty($el) {
                        let row = $el.closest('tr');
                        let id = row.data('id');
                        if (id) {
                            dirtyRows.add(id);
                            updateUnsavedBadge();
                        }
                    }

                    $(document).on('change input',
                        '#tablePlanner input, #tablePlanner select, #tablePlanner textarea',
                        function() {
                            markRowDirty($(this));
                        }
                    );

                    function saveRow(id) {
                        let row = $('tr[data-id="' + id + '"]');

                        return $.ajax({
                            url: '/spvplanner/autosave-row/' + id,
                            type: 'POST',
                            data: {
                                _token: '{{ csrf_token() }}',

                                planner: row.find('[name="planner"]').val(),
                                no_shipment: row.find('[name="no_shipment"]').val(),

                                tanggal_naik_logistik: row.find('[name="tanggal_naik_logistik"]').val(),
                                rencana_kirim: row.find('[name="rencana_kirim"]').val(),
                                tanggal_dpt_unit: row.find('[name="tanggal_dpt_unit"]').val(),

                                planning_loading: row.find('[name="planning_loading"]').val(),
                                tanggal_tiba_gudang: row.find('[name="tanggal_tiba_gudang"]').val(),
                                tanggal_keluar_gudang: row.find('[name="tanggal_keluar_gudang"]').val(),

                                planning_loading_2: row.find('[name="planning_loading_2"]').val(),
                                tanggal_tiba_gudang_2: row.find('[name="tanggal_tiba_gudang_2"]').val(),
                                tanggal_keluar_gudang_2: row.find('[name="tanggal_keluar_gudang_2"]').val(),

                                planning_loading_3: row.find('[name="planning_loading_3"]').val(),
                                tanggal_tiba_gudang_3: row.find('[name="tanggal_tiba_gudang_3"]').val(),
                                tanggal_keluar_gudang_3: row.find('[name="tanggal_keluar_gudang_3"]').val(),

                                tujuan: row.find('[name="tujuan"]').val(),
                                route: row.find('[name="route"]').val(),
                                pulau: row.find('[name="pulau"]').val(),
                                area: row.find('[name="area"]').val(),
                                via_kirim: row.find('[name="via_kirim"]').val(),

                                dist_channel: row.find('[name="dist_channel"]').val(),
                                kategori_ekspedisi: row.find('[name="kategori_ekspedisi"]').val(),
                                ekpedisi: row.find('[name="ekpedisi"]').val(),
                                transport_lead_time: row.find('[name="transport_lead_time"]').val(),

                                nama_driver: row.find('[name="nama_driver"]').val(),
                                no_pol: row.find('[name="no_pol"]').val(),
                                mobil: row.find('[name="mobil"]').val(),
                                total_do_qty_car: row.find('[name="total_do_qty_car"]').val(),

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
                                loadAlertControl();
                                table.ajax.reload(null, false); // refresh data tanpa reset halaman
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
                    // ALERT CONTROL — sekarang lewat AJAX ringan, BUKAN scan DOM
                    // ==========================================================
                   function loadAlertControl() {
    $.getJSON("{{ route('spvplanner.alerts') }}", {
        planner_filter: plannerFilter,
        area_filter: areaFilter
    }, function(res) {
        renderMissingFieldSummaryPlanner(res.missingSummary || {});
        renderAlertControlPlanner(res.alerts || []);
    }).fail(function() {
        $('#missingFieldSummary').html('<span class="badge gray">Gagal memuat</span>');
        $('#alertControlList').html('<div class="p-2" style="color:#ef4444;">Gagal memuat data</div>');
    });
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

                    // Klik item alert -> cari baris via search DataTables server-side,
                    // lalu highlight begitu ketemu.
                    $(document).on('click', '.alert-item', function() {
                        let id = $(this).data('id');
                        let shipmentText = $(this).find('b').text().replace('🚚', '').trim();

                        // pakai search box DataTables (server-side) untuk lompat ke shipment terkait
                        table.search(shipmentText).draw();

                        setTimeout(function() {
                            let $row = $('#tablePlanner tr[data-id="' + id + '"]');
                            if ($row.length) {
                                $row.addClass('highlight-row');
                                $row.get(0).scrollIntoView({ behavior: 'smooth', block: 'center' });
                                setTimeout(function() { $row.removeClass('highlight-row'); }, 2000);
                            }
                        }, 500);
                    });

                    loadAlertControl();
                    $('#filterArea').on('change', function() {
    areaFilter = $(this).val();
    table.draw();
    loadAlertControl();
});

$('#filterPlanner').on('change', function() {
    plannerFilter = $(this).val();
    table.draw();
    loadAlertControl();
});

                    function showToastMsgPlanner(msg) {
                        let toast = $('<div class="toast"><strong>Perhatian</strong>' + msg + '</div>');
                        $('#toastContainer').append(toast);
                        setTimeout(function() {
                            toast.fadeOut(400, function() { toast.remove(); });
                        }, 6000);
                    }

                    function updateDateColor() {
                        $('#tablePlanner input[type="date"]').each(function() {
                            if ($(this).val()) {
                                $(this).removeClass('input-empty').addClass('input-filled');
                            } else {
                                $(this).removeClass('input-filled').addClass('input-empty');
                            }
                        });
                    }

                    $(document).on('change', '#tablePlanner input[type="date"]', function() {
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

                    $('.select2-modal').select2({
                        theme: 'bootstrap-5',
                        dropdownParent: $('#addModal'),
                        width: '100%'
                    });
                });

                $('#formGudang23').on('submit', function(e) {
                    e.preventDefault();
                    $.ajax({
                        url: "{{ route('spvplanner.updateGudang23') }}",
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
            </script>

</body>

</html>