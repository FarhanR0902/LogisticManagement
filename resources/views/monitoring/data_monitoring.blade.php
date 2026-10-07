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
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        body { background: #f3f4f6; font-family: 'Segoe UI'; margin: 0; }

        .container-fluid { margin-left: 250px; width: calc(100% - 250px); padding: 20px; }

        .title { font-size: 24px; font-weight: bold; margin-bottom: 20px; color: #111827; }

        .card {
            background: #fff; padding: 15px; border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08); overflow: auto; width: 100%;
        }

        .d-none { display: none !important; }

        .filter-box {
            display: flex; gap: 10px; flex-wrap: nowrap; align-items: center;
            margin-bottom: 15px; overflow-x: visible;
        }
        .filter-box form { display: flex; gap: 10px; align-items: center; flex-wrap: nowrap; }
        .filter-box select { min-width: 180px; white-space: nowrap; }

        table { width: 100%; border-collapse: collapse; font-size: 20px; white-space: nowrap; }

        th { background: #111827; color: #fff; padding: 14px; font-size: 15px; text-align: center; }
        th.editable { background: linear-gradient(135deg, #2563eb, #1e40af); }

        td { border: 1px solid #e5e7eb; padding: 10px; font-size: 14px; }

        input, select {
            width: 100%; font-size: 14px; padding: 8px; border: 1px solid #d1d5db; border-radius: 5px;
        }

        .save-btn { background: #22c55e; border: none; color: #fff; padding: 7px 12px; border-radius: 6px; }

        .badge { padding: 5px 8px; border-radius: 20px; color: #fff; font-size: 11px; display: inline-block; }

        .green { background: #22c55e; }
        .red { background: #ef4444; }
        .orange { background: #f59e0b; }
        .blue { background: #2563eb; }
        .gray { background: #9ca3af; }

        #tableMonitoring { width: 100% !important; }
        #tableMonitoring th { text-align: left; vertical-align: middle; }
        #tableMonitoring td { vertical-align: middle; white-space: nowrap; }
        #tableMonitoring input[type=text] { min-width: 120px; }
        #tableMonitoring input[type=number] { width: 70px; }
        #tableMonitoring .dt-pair { display: flex; gap: 6px; }
        #tableMonitoring .dt-pair input[type=text].flatpickr-date { width: 100px; }
        #tableMonitoring .dt-pair input[type=text].flatpickr-time { width: 75px; }
        #tableMonitoring .save-btn { width: 70px; }
        #tableMonitoring .badge { display: inline-block; min-width: 70px; text-align: center; }

        /* semua baris sama tinggi */
        #tableMonitoring tbody td {
            height: 56px;
            padding: 6px 10px;
            vertical-align: middle;
            white-space: nowrap;
            overflow: hidden;
        }
        #tableMonitoring tbody td .badge {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            vertical-align: middle;
        }
        #tableMonitoring tbody td input,
        #tableMonitoring tbody td select {
            height: 36px;
            box-sizing: border-box;
        }
        #tableMonitoring .dt-pair { align-items: center; }

        .select2-container { min-width: 140px !important; }
        /* tabel & select biasa tetap 32px */
        .select2-container--default .select2-selection--single { height: 32px !important; }

        .dataTables_wrapper { width: 100%; }
        .dataTables_scrollBody { border-bottom: 1px solid #e5e7eb; }
        .card:has(#tableMonitoring) { overflow: visible; }

        .input-filled { background: #bbf7d0 !important; border: 2px solid #16a34a !important; }
        .input-empty { background: #fecaca !important; border: 2px solid #dc2626 !important; }

        .toast-container { position: fixed; top: 20px; right: 20px; width: 350px; z-index: 99999; }

        .toast {
            background: #111827; color: #fff; padding: 12px 14px; border-radius: 10px;
            margin-bottom: 10px; box-shadow: 0 10px 25px rgba(0, 0, 0, .3);
            animation: slideIn .3s ease; border-left: 5px solid #f59e0b; font-size: 12px;
        }
        .toast strong { display: block; margin-bottom: 5px; color: #fbbf24; }

        @keyframes slideIn {
            from { transform: translateX(120%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        #kelengkapanMenu {
            position: fixed; z-index: 99998; width: 250px;
            background: #fff; border: 1px solid #d1d5db; border-radius: 10px;
            padding: 8px; box-shadow: 0 10px 25px rgba(0,0,0,.2);
        }
        .kel-btn {
            display: block; width: 100%; text-align: left; margin-bottom: 4px;
            background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 6px;
            padding: 7px 10px; font-size: 13px; cursor: pointer;
        }
        .kel-btn:hover { background: #e5e7eb; }

        .summary-row { display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 15px; align-items: flex-start; }
        .missing-field-box { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }

        #alertControlBox { width: 100%; }
        #alertControlBox .box-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        #alertControlBox .box-header b { font-size: 15px; color: #111827; }

        #alertControlList { max-height: 260px; overflow-y: auto; }

        .alert-item {
            background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px;
            padding: 10px 12px; margin-bottom: 8px; cursor: pointer; transition: all .15s ease;
        }
        #tableMonitoring .dt-pair input.input-jam { width: 75px; text-align: center; }
#tableMonitoring .dt-pair .flatpickr-date,
#tableMonitoring .dt-pair input.form-control { width: 100px; }
.input-jam.is-invalid { border-color: #ef4444 !important; background: #fee2e2; }
        .kel-check { display: flex; align-items: center; gap: 8px; font-size: 13px; padding: 4px 6px; cursor: pointer; margin: 0; }
        .kel-check:hover { background: #f3f4f6; border-radius: 6px; }
        .kel-check input[type=checkbox] { width: auto; padding: 0; margin: 0; }
        .alert-item:hover { background: #f3f4f6; transform: translateY(-1px); }
        .alert-item .alert-top { display: flex; justify-content: space-between; align-items: center; }
        .alert-item .alert-missing { font-size: 12px; color: #6b7280; margin-top: 4px; white-space: normal; }

        .highlight-row td { background: #fde68a !important; transition: background-color .3s ease; }

        .completeness-badge {
            white-space: nowrap;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            vertical-align: middle;
            line-height: 1.4;
        }

        /* ===== AREA DROPDOWN (checklist + search) ===== */
        .area-dd { position: relative; width: 220px; flex: 0 0 220px; }
        .area-dd-btn {
            width: 100%; height: 38px; display: flex; justify-content: space-between; align-items: center;
            background: #fff; border: 1px solid #d1d5db; border-radius: 5px; padding: 0 10px;
            font-size: 14px; cursor: pointer;
        }
        .area-dd-btn span:first-child { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .area-dd-panel {
            position: absolute; top: 42px; left: 0; z-index: 9999; width: 260px;
            background: #fff; border: 1px solid #d1d5db; border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,.2); padding: 8px;
        }
        #areaDDSearch { margin-bottom: 4px; }
        .area-dd-actions { display: flex; justify-content: space-between; font-size: 12px; margin: 6px 2px; }
        .area-dd-list { max-height: 260px; overflow-y: auto; }
        .area-dd-item { display: flex; align-items: center; gap: 8px; padding: 4px 6px; margin: 0; font-size: 13px; cursor: pointer; }
        .area-dd-item:hover { background: #f3f4f6; border-radius: 6px; }
        .area-dd-item input { width: auto; padding: 0; margin: 0; }

        #tableMonitoring select.status-select { min-width: 160px !important; width: 160px !important; }
        #tableMonitoring td:has(select.status-select) { min-width: 160px; }

        /* indikator baris yang sudah diedit tapi belum disimpan */
        tr.row-dirty td { background: #fef9c3 !important; }
        tr.row-dirty .save-btn { background: #f59e0b; }

        #btnSaveAll .badge { color: #111827; }
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
                                <select name="no_shipment" id="shipNoShipment" class="form-select searchable">
                                    <option value="">Pilih Shipment</option>
                                    @foreach($shipmentList as $s)
                                    <option value="{{ $s->no_shipment }}"
                                        data-nama-kapal="{{ $s->nama_kapal }}"
                                        data-etd="{{ $s->etd ? date('Y-m-d', strtotime($s->etd)) : '' }}"
                                        data-eta="{{ $s->eta ? date('Y-m-d', strtotime($s->eta)) : '' }}"
                                        data-atd="{{ $s->atd ? date('Y-m-d', strtotime($s->atd)) : '' }}"
                                        data-ata="{{ $s->ata ? date('Y-m-d', strtotime($s->ata)) : '' }}">
                                        {{ $s->no_shipment }} - {{ $s->tujuan }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label>Nama Kapal</label>
                                <input type="text" name="nama_kapal" id="shipNamaKapal" class="form-control">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label>ETD</label>
                                <input type="date" name="etd" id="shipEtd" class="form-control">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label>ETA</label>
                                <input type="date" name="eta" id="shipEta" class="form-control">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label>ATD</label>
                                <input type="date" name="atd" id="shipAtd" class="form-control">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label>ATA</label>
                                <input type="date" name="ata" id="shipAta" class="form-control">
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

    <div class="toast-container" id="toastContainer"></div>

    <div id="kelengkapanMenu" style="display:none;">
        <button type="button" id="btnKelSort" class="kel-btn">↕ Sort</button>
        <button type="button" id="btnKelFilter" class="kel-btn">🔍 Filter</button>
        <div id="kelFilterPanel" style="display:none; margin-top:6px;">
            <label class="kel-check"><input type="checkbox" class="kel-chk" value="lengkap"> ✅ Lengkap</label>
            <label class="kel-check"><input type="checkbox" class="kel-chk" value="belum_lengkap"> ❌ Belum lengkap (sudah lewat estimasi)</label>
            <label class="kel-check"><input type="checkbox" class="kel-chk" value="belum_jatuh_tempo"> ➖ Belum jatuh tempo</label>
            <label class="kel-check"><input type="checkbox" class="kel-chk" value="sampai_tujuan"> 🚚 Sampai Tujuan (belum bongkar)</label>
        </div>
    </div>

    <div class="container-fluid px-3">
        @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="title">🚚 DATA MONITORING</div>

        <div class="mb-3 d-flex align-items-center gap-2">
            <button type="button" id="btnExport" class="btn btn-success">Export Excel</button>

            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#shipModal">
                + Shipment Laut
            </button>

            <button id="btnSaveAll" type="button" class="btn btn-primary">
                <span id="btnSaveAllLabel">💾 Simpan Semua</span>
                <span class="badge bg-light" id="dirtyCount">0</span>
                <small class="ms-1">(<span id="editedCount">0</span> data diedit)</small>
            </button>
        </div>

        {{-- FILTER --}}
        <div class="filter-box">
            <select class="searchable" id="filter_pic_monitoring">
                <option value="">PIC Monitoring</option>
                @foreach($picList as $pic)
                <option value="{{ $pic }}">{{ $pic }}</option>
                @endforeach
            </select>

            {{-- AREA: dropdown checklist + search --}}
            <div class="area-dd" id="areaDD">
                <button type="button" class="area-dd-btn" id="areaDDBtn">
                    <span id="areaDDLabel">AREA</span> <span>▾</span>
                </button>
                <div class="area-dd-panel" id="areaDDPanel" style="display:none;">
                    <input type="text" id="areaDDSearch" placeholder="Cari area..." autocomplete="off">
                    <div class="area-dd-actions">
                        <a href="#" id="areaDDAll">Pilih semua</a>
                        <a href="#" id="areaDDClear">Hapus</a>
                    </div>
                    <div class="area-dd-list" id="areaDDList">
                        @foreach($areaList as $area)
                        <label class="area-dd-item">
                            <input type="checkbox" class="area-chk" value="{{ $area }}">
                            <span>{{ $area }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <select class="searchable" id="filter_bulan">
                <option value="">BULAN</option>
                @for($i=1; $i<=12; $i++)
                    <option value="{{ $i }}">{{ $i }}</option>
                @endfor
            </select>

            <select class="searchable" id="filter_tahun">
                <option value="">TAHUN</option>
                @for($i=2023; $i<=2030; $i++)
                    <option value="{{ $i }}">{{ $i }}</option>
                @endfor
            </select>

            <button type="button" class="btn btn-secondary btn-sm" id="btnResetFilter" style="height:38px;">
                🔄 Reset Filter
            </button>
        </div>

        <div class="d-flex gap-3 mb-3 align-items-end">
            <div>
                <label class="form-label fw-bold">Filter Tanggal Keluar Gudang</label>
                <input type="date" id="filterKeluarGudangTgl" class="form-control">
            </div>
            <div>
                <label class="form-label fw-bold">Export: Dari Tanggal</label>
                <input type="date" id="exportTglDari" class="form-control">
            </div>
            <div>
                <label class="form-label fw-bold">Sampai Tanggal</label>
                <input type="date" id="exportTglSampai" class="form-control">
            </div>
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
                <b>🔔 Alert Control — Lewat Estimasi Tiba</b>
                <span class="badge red" id="alertControlCount">0 Alert</span>
            </div>
            <div id="alertControlList">
                <div class="p-2" style="color:#6b7280; font-size:13px;">Memuat data...</div>
            </div>
        </div>

        <div class="card">
            <table id="tableMonitoring" class="display nowrap">
                <thead>
                    <tr>
                        <th>Tanggal Keluar Gudang</th>
                        <th class="editable">Act PGI Date</th>
                        <th>Dist Channel</th>
                        <th>Area</th>
                        <th>No Shipment</th>
                        <th class="editable">Tujuan</th>
                        <th>Ekspedisi</th>
                        <th class="editable">PIC</th>
                        <th class="editable">Status</th>
                        <th>Alert</th>
                        <th>Total DO Qty</th>
                        <th class="editable">Selisih Qty Do</th>
                        <th class="editable">Biaya Kuli</th>
                        <th>Total Biaya kuli</th>
                        <th>Qty Actual Do</th>
                        <th class="editable">Reason Qty</th>
                        <th class="editable">Urutan Bongkar</th>
                        <th>Estimasi Tiba</th>
                        <th class="editable">Tanggal Tiba</th>
                        <th>Lama Perjalanan</th>
                        <th>SLA Tiba</th>
                        <th class="editable">Tanggal Bongkar</th>
                        <th>Status Bongkar</th>
                        <th>Overstay</th>
                        <th>SLA Bongkar</th>
                        <th class="editable">Reason Tiba</th>
                        <th class="editable">Reason Bongkar</th>
                        <th class="editable">Remarks</th>
                        <th class="editable">Nama Kapal</th>
                        <th>ETD</th>
                        <th>ETA</th>
                        <th>ATD</th>
                        <th>ATA</th>
                        <th class="th-kelengkapan" style="cursor:pointer;">Kelengkapan Data <span class="kel-ind"></span></th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>

            <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

            <script>
                let table;
                let kelSort = '';     // '', 'desc', 'asc'
                let kelFilter = [];
                let lastOrderStr = null;
                let editedRowIds = new Set(); // id baris yang PERNAH diedit selama sesi ini

                // ================= AREA DROPDOWN (checklist + search) =================
                function getSelectedAreas() {
                    return $('.area-chk:checked').map(function() { return this.value; }).get();
                }

                function updateAreaLabel() {
                    const vals = getSelectedAreas();
                    let label = 'AREA';
                    if (vals.length === 1) label = vals[0];
                    else if (vals.length === 2) label = vals.join(', ');
                    else if (vals.length > 2) label = vals.length + ' area dipilih';
                    $('#areaDDLabel').text(label).css('font-weight', vals.length ? '600' : '400');
                }

                let areaTimer = null;
                function applyAreaFilter() {
                    updateAreaLabel();
                    clearTimeout(areaTimer);
                    areaTimer = setTimeout(function() { // debounce supaya klik cepat tidak banjir request
                        table.draw();
                        loadAlertControl(false);
                    }, 300);
                }

                // buka / tutup panel
                $(document).on('click', '#areaDDBtn', function(e) {
                    e.stopPropagation();
                    $('#areaDDPanel').toggle();
                    if ($('#areaDDPanel').is(':visible')) $('#areaDDSearch').trigger('focus');
                });

                // klik di dalam panel jangan menutup panel
                $(document).on('click', '#areaDDPanel', function(e) { e.stopPropagation(); });

                // klik di luar -> tutup
                $(document).on('click', function(e) {
                    if (!$(e.target).closest('#areaDD').length) $('#areaDDPanel').hide();
                });

                // search di dalam dropdown
                $(document).on('input', '#areaDDSearch', function() {
                    const q = $(this).val().toLowerCase().trim();
                    $('.area-dd-item').each(function() {
                        $(this).toggle($(this).text().toLowerCase().indexOf(q) !== -1);
                    });
                });

                // centang / hapus centang satu area
                $(document).on('change', '.area-chk', applyAreaFilter);

                // pilih semua (hanya yang tampil sesuai hasil search)
                $(document).on('click', '#areaDDAll', function(e) {
                    e.preventDefault();
                    $('.area-dd-item:visible .area-chk').prop('checked', true);
                    applyAreaFilter();
                });

                // hapus semua
                $(document).on('click', '#areaDDClear', function(e) {
                    e.preventDefault();
                    $('.area-chk').prop('checked', false);
                    applyAreaFilter();
                });

                $(document).ready(function() {

                    // failsafe: paksa tombol Simpan Semua selalu bisa diklik
                    $('#btnSaveAll').prop('disabled', false);

                    // ================= DATATABLES SERVER-SIDE =================
                    table = $('#tableMonitoring').DataTable({
                        processing: true,
                        serverSide: true,
                        autoWidth: false,
                        searchDelay: 600,
                        ajax: {
                            url: "{{ route('monitoring.datalogistik.ajax') }}",
                            data: function(d) {
                                d.pic_monitoring = $('#filter_pic_monitoring').val();
                                d.area = getSelectedAreas();
                                d.bulan = $('#filter_bulan').val();
                                d.tahun = $('#filter_tahun').val();
                                d.keluar_gudang_tgl = $('#filterKeluarGudangTgl').val();

                                // kalau user klik sort kolom lain, sort kelengkapan otomatis dilepas
                                let curOrder = JSON.stringify(d.order);
                                if (lastOrderStr !== null && curOrder !== lastOrderStr && kelSort) {
                                    kelSort = '';
                                    updateKelIndicator();
                                }
                                lastOrderStr = curOrder;

                                d.kelengkapan = kelFilter;
                                d.kelengkapan_sort = kelSort;
                            }
                        },
                        scrollX: true,
                        scrollY: 'calc(100vh - 220px)',
                        scrollCollapse: true,
                        pageLength: 50,
                        lengthMenu: [200, 300, 400, 500],
                        ordering: true,
                        order: [[4, 'asc']], // default: No Shipment ascending
                        deferRender: true,
                        language: {
                            search: "Cari:",
                            lengthMenu: "Tampilkan _MENU_ data",
                            info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
                            processing: "Memuat data...",
                            paginate: { previous: "«", next: "»" }
                        },
                        columnDefs: [
                            { width: "120px", targets: [0, 1, 2, 6, 9] },
                            { width: "160px", targets: [8] },
                            { width: "140px", targets: [3] },
                            { width: "350px", targets: 4 },
                            { width: "150px", targets: [5] },
                            { width: "180px", targets: [10, 13] },
                            { width: "185px", targets: [18, 21] },
                            { width: "220px", targets: [33] },
                            // kolom badge/HTML hasil kalkulasi -> tidak bisa di-sort
                            { orderable: false, targets: [9, 22, 33, 34] },
                        ],
                        createdRow: function(row, data, dataIndex) {
                            let $btn = $(row).find('.save-btn');
                            $(row).attr('data-id', $btn.data('id'));
                        }
                    });

                    $('#shipNoShipment').on('change', function() {
                        let opt = $(this).find('option:selected');
                        $('#shipNamaKapal').val(opt.data('nama-kapal') || '');
                        $('#shipEtd').val(opt.data('etd') || '');
                        $('#shipEta').val(opt.data('eta') || '');
                        $('#shipAtd').val(opt.data('atd') || '');
                        $('#shipAta').val(opt.data('ata') || '');
                    });

                    // filter berubah -> reload dari server (area ditangani handler area sendiri)
                    $('#filter_pic_monitoring, #filter_bulan, #filter_tahun, #filterKeluarGudangTgl')
                        .on('change', function() {
                            table.draw();
                            loadAlertControl(false);
                        });

                    $('#btnResetFilter').on('click', function() {
                        $('#filter_pic_monitoring, #filter_bulan, #filter_tahun').val('').trigger('change.select2');
                        $('.area-chk').prop('checked', false);
                        $('#areaDDSearch').val('');
                        $('.area-dd-item').show();
                        updateAreaLabel();
                        $('#filterKeluarGudangTgl').val('');
                        kelSort = ''; kelFilter = [];
                        $('.kel-chk').prop('checked', false);
                        updateKelIndicator();
                        table.draw();
                        loadAlertControl(false);
                    });

                    $('.filter-box .searchable').select2({ width: '180px' });
                    $('#shipModal .searchable').select2({ width: '100%', dropdownParent: $('#shipModal') });

                    $('#shipModal form').on('submit', function(e) {
                        e.preventDefault();
                        $.ajax({
                            url: $(this).attr('action'),
                            type: 'POST',
                            data: $(this).serialize(),
                            success: function(res) {
                                $('#shipModal').modal('hide');
                                $('#shipModal form')[0].reset();
                                alert(res.message);
                                table.draw();
                            },
                            error: function() { alert('Gagal update data'); }
                        });
                    });

                    // init select2 & flatpickr tiap kali draw (hanya baris yang tampil)
                    table.on('draw.dt', function() {
                        initReasonSelect();
                        initDateTimePickers();
                        setTimeout(function() {
                            table.columns.adjust();
                        }, 0);
                        updateDirtyCount();
                    });

                    $(window).on('resize', function() {
                        if (table) {
                            table.columns.adjust();
                        }
                    });

                    // ================= ALERT CONTROL =================
                    loadAlertControl(true);
                });

                function esc(s) {
                    return String(s ?? '')
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#39;');
                }

                function loadAlertControl(showToastIfAny) {
                    $.ajax({
                        url: "{{ route('monitoring.alerts') }}",
                        type: 'GET',
                        data: {
                            pic_monitoring: $('#filter_pic_monitoring').val(),
                            area: getSelectedAreas(),
                            bulan: $('#filter_bulan').val(),
                            tahun: $('#filter_tahun').val(),
                            keluar_gudang_tgl: $('#filterKeluarGudangTgl').val(),
                        },
                        success: function(res) {
                            renderMissingFieldSummary(res.missingSummary);
                            renderAlertControl(res.alerts, res.totalAlert);

                            if (showToastIfAny && res.alerts.length > 0) {
                                showToastMsg('⚠ ' + res.totalAlert + ' shipment sudah lewat estimasi tiba, tapi Tgl Tiba/Tgl Bongkar belum diisi');
                            }
                        }
                    });
                }

                function renderMissingFieldSummary(missingSummary) {
                    let entries = Object.entries(missingSummary || {}).sort((a, b) => b[1] - a[1]);

                    if (entries.length === 0) {
                        $('#missingFieldSummary').html('<span class="badge green">✅ Semua data lengkap</span>');
                        return;
                    }

                    let html = entries.map(function(e) {
                        return '<span class="badge red">' + e[0] + ': ' + e[1] + '</span>';
                    }).join(' ');

                    $('#missingFieldSummary').html(html);
                }

                function renderAlertControl(alertList, totalAlert) {
                    $('#alertControlCount').text((totalAlert ?? alertList.length) + ' Alert');

                    if (!alertList || alertList.length === 0) {
                        $('#alertControlList').html('<div class="p-2" style="color:#22c55e;">✅ Tidak ada shipment yang lewat estimasi tiba</div>');
                        return;
                    }

                    let html = alertList.map(function(a) {
                        let sev = a.emptyCount === 2 ? 'red' : 'orange';
                        let estimasiInfo = a.estimasi ? (' • Estimasi ' + esc(a.estimasi)) : '';
                        return '' +
                            '<div class="alert-item" data-shipment="' + esc(a.shipment) + '">' +
                                '<div class="alert-top">' +
                                    '<b style="font-size:17px;">🚚 ' + esc(a.shipment) + '</b>' +
                                    '<span class="badge ' + sev + '">' + a.emptyCount + ' kosong</span>' +
                                '</div>' +
                                '<div style="font-size:16px;margin-top:2px;">' +
                                    '<b>' + esc(a.tujuan || '-') + '</b>' +
                                    '<b> • ' + esc(a.area || '-') + '</b>' +
                                    '<b> • ' + esc(a.pic_monitoring || '-') + '</b>' +
                                '</div>' +
                                '<div class="alert-missing">Belum diisi: ' + a.missing.join(', ') + estimasiInfo + '</div>' +
                            '</div>';
                    }).join('');

                    $('#alertControlList').html(html);
                }

                // Klik item alert -> filter tabel by no_shipment (server-side search)
                $(document).on('click', '.alert-item', function() {
                    let shipment = $(this).data('shipment');
                    table.search(shipment).draw();
                    $('html, body').animate({ scrollTop: $('#tableMonitoring').offset().top - 80 }, 400);
                });

                function showToastMsg(msg) {
                    let toast = $('<div class="toast"><strong>Perhatian</strong>' + msg + '</div>');
                    $('#toastContainer').append(toast);
                    setTimeout(function() {
                        toast.fadeOut(400, function() { toast.remove(); });
                    }, 6000);
                }

                // ================= KELENGKAPAN: sort & filter =================
                function updateKelIndicator() {
                    let t = '';
                    if (kelSort === 'desc') t += ' ⬇';
                    if (kelSort === 'asc')  t += ' ⬆';
                    if (kelFilter.length) t += ' 🔍' + kelFilter.length;
                    $('.kel-ind').text(t);

                    $('#btnKelSort').text(
                        kelSort === 'desc' ? '⬇ Sort: Paling banyak kosong dulu' :
                        kelSort === 'asc'  ? '⬆ Sort: Lengkap dulu' : '↕ Sort'
                    );
                }

                // klik header -> buka menu (Sort & Filter)
                $(document).on('click', 'th.th-kelengkapan', function(e) {
                    e.stopPropagation();
                    let $m = $('#kelengkapanMenu');
                    if ($m.is(':visible')) { $m.hide(); return; }
                    let r = this.getBoundingClientRect();
                    $('#kelFilterPanel').hide();
                    $m.css({ top: r.bottom + 4, left: Math.max(8, Math.min(r.left, window.innerWidth - 260)) }).show();
                });

                // klik di luar menu -> tutup
                $(document).on('click', function(e) {
                    if (!$(e.target).closest('#kelengkapanMenu').length) {
                        $('#kelengkapanMenu').hide();
                    }
                });

                // tombol Sort: off -> desc -> asc -> off
                $('#btnKelSort').on('click', function() {
                    kelSort = kelSort === '' ? 'desc' : (kelSort === 'desc' ? 'asc' : '');
                    updateKelIndicator();
                    $('#kelengkapanMenu').hide();
                    table.draw();
                });

                // tombol Filter: tampilkan pilihan
                $('#btnKelFilter').on('click', function() {
                    $('#kelFilterPanel').toggle();
                });

                $(document).on('change', '.kel-chk', function() {
                    kelFilter = $('.kel-chk:checked').map(function() { return this.value; }).get();
                    updateKelIndicator();
                    table.draw();
                });

                function formatRupiah(angka) {
                    return 'Rp ' + Number(angka).toLocaleString('id-ID');
                }

                $(document).on('input', 'input[name="qty_monitoring"], input[name="biaya_kuli"]', function() {
                    let row = $(this).closest('tr');
                    let qty = parseInt(row.find('input[name="qty_monitoring"]').val()) || 0;
                    let biaya = parseInt(row.find('input[name="biaya_kuli"]').val()) || 0;
                    row.find('input[name="total_biaya_kuli"]').val(formatRupiah(qty * biaya));
                });

                $(document).on('input', '[name="total_do_qty_car"], [name="selisih_qty"]', function() {
                    let row = $(this).closest('tr');
                    let total = parseFloat(row.find('[name="total_do_qty_car"]').val()) || 0;
                    let selisih = parseFloat(row.find('[name="selisih_qty"]').val()) || 0;
                    row.find('[name="qty_monitoring"]').val(total - selisih).trigger('input');
                });

                // ================= kumpulkan data 1 baris jadi object =================
                function getRowData(row) {
                    return {
                        id: row.attr('data-id'),
                        pic_monitoring: row.find('[name="pic_monitoring"]').val(),
                        status_kendaraan: row.find('[name="status_kendaraan"]').val(),
                        waktu_tiba: row.find('[name="waktu_tiba"]').val(),
                        waktu_bongkar: row.find('[name="waktu_bongkar"]').val(),
                        action_required: row.find('[name="action_required"]').val(),
                        act_urutan_bongkar: row.find('[name="act_urutan_bongkar"]').val(),
                        tanggal_tiba: row.find('[name="tanggal_tiba"]').val(),
                        tujuan: row.find('[name="tujuan"]').val(),
                        tanggal_bongkar: row.find('[name="tanggal_bongkar"]').val(),
                        reason_tiba: row.find('[name="reason_tiba"]').val(),
                        reason_bongkar: row.find('[name="reason_bongkar"]').val(),
                        remarks_qty: row.find('[name="remarks_qty"]').val(),
                        remarks: row.find('[name="remarks"]').val(),
                        act_pgi_date: row.find('[name="act_pgi_date"]').val(),
                        total_do_qty_car: row.find('[name="total_do_qty_car"]').val(),
                        qty_monitoring: row.find('[name="qty_monitoring"]').val(),
                        selisih_qty: row.find('[name="selisih_qty"]').val(),
                        biaya_kuli: row.find('[name="biaya_kuli"]').val(),
                        nama_kapal: row.find('[name="nama_kapal"]').val(),
                        etd: row.find('[name="etd"]').val(),
                        eta: row.find('[name="eta"]').val(),
                        atd: row.find('[name="atd"]').val(),
                        ata: row.find('[name="ata"]').val(),
                    };
                }

                // jumlah baris dirty (belum tersimpan saat ini)
                function updateDirtyCount() {
                    let n = $('#tableMonitoring tbody tr.row-dirty').length;
                    $('#dirtyCount').text(n);
                }

                // jumlah data yang PERNAH diedit di sesi ini
                function updateEditedCount() {
                    $('#editedCount').text(editedRowIds.size);
                }

                function saveRow(btnEl) {
                    let row = $(btnEl).closest('tr');

                    $.ajax({
                        url: '/monitoring/update/' + row.attr('data-id'),
                        type: 'POST',
                        data: Object.assign(
                            { _token: '{{ csrf_token() }}', _method: 'PUT' },
                            getRowData(row)
                        ),
                        beforeSend: function() {
                            row.find('.save-btn').prop('disabled', true).text('Saving...');
                            row.find('.save-status').html('⏳ Saving...');
                        },
                        success: function() {
                            row.find('.save-btn').prop('disabled', false).text('SAVE');
                            row.find('.save-status').html('✅ Saved');
                            row.removeClass('row-dirty');
                            updateDirtyCount();
                            setTimeout(function() { row.find('.save-status').html(''); }, 2000);
                            loadAlertControl(false);
                        },
                        error: function() {
                            row.find('.save-btn').prop('disabled', false).text('SAVE');
                            row.find('.save-status').html('❌ Error');
                        }
                    });
                }

                // ================= Simpan Semua (batch) =================
                $(document).on('click', '#btnSaveAll', function() {
                    let $btn = $(this);
                    let $dirtyRows = $('#tableMonitoring tbody tr.row-dirty');

                    if ($dirtyRows.length === 0) {
                        $dirtyRows = $('#tableMonitoring tbody tr');
                    }

                    if ($dirtyRows.length === 0) {
                        showToastMsg('Tidak ada data untuk disimpan.');
                        return;
                    }

                    let rows = [];
                    $dirtyRows.each(function() {
                        rows.push(getRowData($(this)));
                    });

                    $btn.prop('disabled', true);
                    $('#btnSaveAllLabel').text('Menyimpan...');
                    $dirtyRows.find('.save-status').html('⏳ Saving...');

                    $.ajax({
                        url: "{{ route('monitoring.update.batch') }}",
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            rows: rows,
                        },
                        success: function(res) {
                            (res.results || []).forEach(function(r) {
                                let row = $dirtyRows.filter('[data-id="' + r.id + '"]');
                                if (r.status === 'success') {
                                    row.removeClass('row-dirty');
                                    row.find('.save-status').html('✅ Saved');
                                    setTimeout(function() { row.find('.save-status').html(''); }, 2000);
                                } else {
                                    row.find('.save-status').html('❌ ' + (r.message || 'Error'));
                                }
                            });

                            showToastMsg(res.message);
                            updateDirtyCount();
                            loadAlertControl(false);
                        },
                        error: function() {
                            showToastMsg('❌ Gagal menyimpan batch, coba lagi.');
                            $dirtyRows.find('.save-status').html('❌ Error');
                        },
                        complete: function() {
                            $btn.prop('disabled', false);
                            $('#btnSaveAllLabel').text('💾 Simpan Semua');
                            updateDirtyCount();
                        }
                    });
                });

                // ================= input/select berubah -> LANGSUNG save =================
               $(document).on('change', '#tableMonitoring input, #tableMonitoring select', function() {
    // abaikan altInput flatpickr & kotak search select2 (keduanya tidak punya atribut name)
    if (!$(this).attr('name')) return;

    let row = $(this).closest('tr');
    row.addClass('row-dirty');

    let id = row.attr('data-id');
    if (id) {
        editedRowIds.add(id);
    }
    updateEditedCount();

    updateDirtyCount();
    saveRow(row.find('.save-btn')[0]);
});
                function initReasonSelect() {
                    $('.searchable-select').each(function() {
                        if ($(this).hasClass('select2-hidden-accessible')) {
                            $(this).select2('destroy');
                        }
                        $(this).select2({
                            width: 'resolve',
                            placeholder: $(this).data('placeholder') || 'Pilih...',
                            allowClear: true,
                            dropdownParent: $('body')
                        });
                    });
                }

              // "07102026" / "071026" / "2026-10-07" -> Date (null kalau tidak valid)
function parseTypedDate(str) {
    if (!str) return null;
    str = String(str).trim();

    if (/^\d{4}-\d{2}-\d{2}$/.test(str)) {
        const [y, m, d] = str.split('-').map(Number);
        return new Date(y, m - 1, d);
    }

    const digits = str.replace(/\D/g, '');
    let d, m, y;
    if (digits.length === 8)      { d = +digits.slice(0, 2); m = +digits.slice(2, 4); y = +digits.slice(4); }
    else if (digits.length === 6) { d = +digits.slice(0, 2); m = +digits.slice(2, 4); y = 2000 + +digits.slice(4); }
    else return null;

    const dt = new Date(y, m - 1, d);
    return (dt.getFullYear() === y && dt.getMonth() === m - 1 && dt.getDate() === d) ? dt : null;
}

// "1435" -> "14:35", "935" -> "09:35", "9" -> "09:00", tidak valid -> ""
function normalizeJam(raw) {
    const d = String(raw || '').replace(/\D/g, '');
    if (d === '') return '';
    let h, m;
    if (d.length <= 2)       { h = +d; m = 0; }
    else if (d.length === 3) { h = +d.slice(0, 1); m = +d.slice(1); }
    else                     { h = +d.slice(0, 2); m = +d.slice(2, 4); }
    if (h > 23 || m > 59) return '';
    return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
}

function initDateTimePickers() {
    // ---- TANGGAL: bisa diketik ddmmyyyy, kalender tetap bisa diklik ----
    $('.flatpickr-date').each(function () {
        if (this._flatpickr) return;
        flatpickr(this, {
            dateFormat: 'Y-m-d',          // nilai yang dikirim ke server
            altInput: true,
            altFormat: 'd-m-Y',           // tampilan
            allowInput: true,
            parseDate: function (str) { return parseTypedDate(str); },
            onReady: function (sel, val, fp) {
                fp.altInput.placeholder = 'ddmmyyyy';
                fp.altInput.setAttribute('inputmode', 'numeric');
            }
        });
    });

    // ---- JAM: ketik 1435 -> 14:35 ----
    $('.input-jam').each(function () {
        if (this.dataset.jamInit) return;
        this.dataset.jamInit = '1';
        const el = this;

        // saat mengetik: sisipkan ":" otomatis
        el.addEventListener('input', function () {
            const d = el.value.replace(/\D/g, '').slice(0, 4);
            el.value = d.length > 2 ? d.slice(0, 2) + ':' + d.slice(2) : d;
        });

        // normalisasi SEBELUM handler auto-save (change terpicu lebih dulu daripada blur)
        el.addEventListener('change', function () {
            const hasil = normalizeJam(el.value);
            if (el.value !== '' && hasil === '') {
                el.classList.add('is-invalid');
                setTimeout(function () { el.classList.remove('is-invalid'); }, 1500);
            }
            el.value = hasil;
        });

        // Enter = keluar dari input supaya tersimpan
        el.addEventListener('keydown', function (e) { if (e.key === 'Enter') el.blur(); });
    });
}

                // ================= EXPORT =================
                $(document).on('click', '#btnExport', function() {
                    const params = new URLSearchParams();
                    const add = (k, v) => { if (v) params.append(k, v); };

                    add('pic_monitoring',    $('#filter_pic_monitoring').val());
                    getSelectedAreas().forEach(a => params.append('area[]', a));
                    add('bulan',             $('#filter_bulan').val());
                    add('tahun',             $('#filter_tahun').val());
                    add('keluar_gudang_tgl', $('#filterKeluarGudangTgl').val());
                    add('tgl_dari',          $('#exportTglDari').val());
                    add('tgl_sampai',        $('#exportTglSampai').val());

                    window.location.href = "{{ route('monitoring.export') }}" +
                        (params.toString() ? '?' + params.toString() : '');
                });
            </script>
        </div>
    </div>

</body>
</html>