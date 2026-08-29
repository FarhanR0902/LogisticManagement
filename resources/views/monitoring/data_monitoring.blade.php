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
            margin-bottom: 15px; overflow-x: auto;
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
        #tableMonitoring input[type=datetime-local] { width: 170px; }
        #tableMonitoring .save-btn { width: 70px; }
        #tableMonitoring .badge { display: inline-block; min-width: 70px; text-align: center; }

        .select2-container { min-width: 140px !important; }
        .select2-selection { height: 32px !important; }
        .dataTables_wrapper { overflow-x: auto; width: 100%; }

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
        .alert-item:hover { background: #f3f4f6; transform: translateY(-1px); }
        .alert-item .alert-top { display: flex; justify-content: space-between; align-items: center; }
        .alert-item .alert-missing { font-size: 12px; color: #6b7280; margin-top: 4px; white-space: normal; }

        .highlight-row td { background: #fde68a !important; transition: background-color .3s ease; }

        .completeness-badge { white-space: normal; max-width: 220px; line-height: 1.4; }

        #tableMonitoring select.status-select { min-width: 160px !important; width: 160px !important; }
        #tableMonitoring td:has(select.status-select) { min-width: 160px; }
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
                                <select name="no_shipment" class="form-select searchable">
                                    <option value="">Pilih Shipment</option>
                                    @foreach($shipmentList as $s)
                                    <option value="{{ $s->no_shipment }}">
                                        {{ $s->no_shipment }} - {{ $s->tujuan }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label>Nama Kapal</label>
                                <input type="text" name="nama_kapal" class="form-control">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label>ETD</label>
                                <input type="date" name="etd" class="form-control">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label>ETA</label>
                                <input type="date" name="eta" class="form-control">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label>ATD</label>
                                <input type="date" name="atd" class="form-control">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label>ATA</label>
                                <input type="date" name="ata" class="form-control">
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

    <div class="container-fluid px-3">
        @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="title">🚚 DATA MONITORING</div>

        <div class="mb-3">
            <a href="{{ route('monitoring.export', [
                'pic_monitoring' => request('pic_monitoring'),
                'area' => request('area')
            ]) }}" class="btn btn-success">
                Export Excel
            </a>
        </div>

        {{-- FILTER --}}
        <div class="filter-box">
            <select class="searchable" id="filter_pic_monitoring">
                <option value="">PIC Monitoring</option>
                @foreach($picList as $pic)
                <option value="{{ $pic }}">{{ $pic }}</option>
                @endforeach
            </select>

            <select class="searchable" id="filter_area">
                <option value="">AREA</option>
                @foreach($areaList as $area)
                <option value="{{ $area }}">{{ $area }}</option>
                @endforeach
            </select>

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

        <div class="col-md-3">
            <label class="form-label fw-bold">Filter Tanggal Keluar Gudang</label>
            <input type="date" id="filterKeluarGudangTgl" class="form-control">
        </div>

        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#shipModal">
            + Shipment Laut
        </button>

        {{-- ===== SUMMARY: FIELD YANG PALING BANYAK KOSONG ===== --}}
        <div class="card mb-3">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                <b style="font-size:14px; color:#374151;">📋 Field belum lengkap:</b>
            </div>
            <div class="missing-field-box" id="missingFieldSummary">
                <span class="badge gray">Menghitung...</span>
            </div>
        </div>

        {{-- ===== ALERT CONTROL BOX (data dari endpoint ringan /monitoring/alerts) ===== --}}
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
                        <th>Act PGI Date</th>
                        <th>Dist Channel</th>
                        <th>Area</th>
                        <th>No Shipment</th>
                        <th>Tujuan</th>
                        <th>Ekspedisi</th>
                        <th class="editable">PIC</th>
                        <th class="editable">Status</th>
                        <th class="editable">Alert</th>
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
                        <th>Kelengkapan Data</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>

            <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

            <script>
                let table;
                let saveTimer;

                $(document).ready(function() {

                    // ================= DATATABLES SERVER-SIDE =================
                    table = $('#tableMonitoring').DataTable({
                        processing: true,
                        serverSide: true,
                         autoWidth: false,
                        searchDelay: 600, // debounce, jangan tembak query tiap keystroke
                        ajax: {
                            url: "{{ route('monitoring.datalogistik.ajax') }}",
                            data: function(d) {
                                d.pic_monitoring = $('#filter_pic_monitoring').val();
                                d.area = $('#filter_area').val();
                                d.bulan = $('#filter_bulan').val();
                                d.tahun = $('#filter_tahun').val();
                                d.keluar_gudang_tgl = $('#filterKeluarGudangTgl').val();
                            }
                        },
                        scrollX: true,
                        scrollCollapse: true,
                        autoWidth: false,
                        pageLength: 10,
                        lengthMenu: [10, 25, 50, 100],
                        ordering: true, // sorting dikirim ke backend (dataAjax) via parameter order
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
                            { width: "180px", targets: [10, 13, 18, 21] },
                            { width: "220px", targets: [33] },
                            // kolom badge/HTML hasil kalkulasi -> tidak ada kolom DB
                            // 1:1 buat di-sort, jadi matikan klik-sort di sini saja
                            { orderable: false, targets: [9, 22, 33, 34] },
                        ],
                        createdRow: function(row, data, dataIndex) {
                            // ambil id dari tombol SAVE (kolom terakhir) supaya row bisa dicari nanti
                            let $btn = $(row).find('.save-btn');
                            $(row).attr('data-id', $btn.data('id'));
                        }
                    });

                    // filter berubah -> reload dari server (bukan hitung ulang di JS)
                    $('#filter_pic_monitoring, #filter_area, #filter_bulan, #filter_tahun, #filterKeluarGudangTgl')
                        .on('change', function() {
                            table.draw();
                            loadAlertControl(false);
                        });

                    $('#btnResetFilter').on('click', function() {
                        $('#filter_pic_monitoring, #filter_area, #filter_bulan, #filter_tahun').val('').trigger('change.select2');
                        $('#filterKeluarGudangTgl').val('');
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

                    // init select2 utk kolom reason & re-init tiap kali draw (hanya utk baris yg tampil, ringan)
              table.on('draw.dt', function() {
    initReasonSelect();
    // recalculate lebar header vs body setelah select2/badge/input ke-render
    setTimeout(function() {
        table.columns.adjust();
    }, 0);
});

$(window).on('resize', function() {
    if (table) {
        table.columns.adjust();
    }
});

                    // ================= ALERT CONTROL (dari endpoint ringan) =================
                    loadAlertControl(true);
                });

                function loadAlertControl(showToastIfAny) {
                    $.ajax({
                        url: "{{ route('monitoring.alerts') }}",
                        type: 'GET',
                        data: {
                            pic_monitoring: $('#filter_pic_monitoring').val(),
                            area: $('#filter_area').val(),
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
                        let estimasiInfo = a.estimasi ? (' • Estimasi ' + a.estimasi) : '';
                        return '' +
                            '<div class="alert-item" data-shipment="' + a.shipment + '">' +
                                '<div class="alert-top">' +
                                    '<b>🚚 ' + a.shipment + '</b>' +
                                    '<span class="badge ' + sev + '">' + a.emptyCount + ' kosong</span>' +
                                '</div>' +
                                '<div class="alert-missing">Belum diisi: ' + a.missing.join(', ') + estimasiInfo + '</div>' +
                            '</div>';
                    }).join('');

                    $('#alertControlList').html(html);
                }

                // Klik item alert -> filter tabel by no_shipment (server-side search),
                // bukan scroll-highlight (karena datanya paginated, row belum tentu di halaman ini)
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

                function saveRow(btnEl) {
                    let row = $(btnEl).closest('tr');
                    let id = $(btnEl).data('id');

                    $.ajax({
                        url: '/monitoring/update/' + id,
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            _method: 'PUT',
                            pic_monitoring: row.find('[name="pic_monitoring"]').val(),
                            status_kendaraan: row.find('[name="status_kendaraan"]').val(),
                            action_required: row.find('[name="action_required"]').val(),
                            act_urutan_bongkar: row.find('[name="act_urutan_bongkar"]').val(),
                            tanggal_tiba: row.find('[name="tanggal_tiba"]').val(),
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
                        },
                        beforeSend: function() {
                            row.find('.save-btn').prop('disabled', true).text('Saving...');
                            row.find('.save-status').html('⏳ Saving...');
                        },
                        success: function() {
                            row.find('.save-btn').prop('disabled', false).text('SAVE');
                            row.find('.save-status').html('✅ Saved');
                            setTimeout(function() { row.find('.save-status').html(''); }, 2000);
                            // refresh alert control ringan (bukan reload semua tabel)
                            loadAlertControl(false);
                        },
                        error: function() {
                            row.find('.save-btn').prop('disabled', false).text('SAVE');
                            row.find('.save-status').html('❌ Error');
                        }
                    });
                }

                // Auto-save saat input berubah (hanya baris yang sedang tampil, jadi ringan)
                $(document).on('change', '#tableMonitoring input, #tableMonitoring select', function() {
                    let row = $(this).closest('tr');
                    let btn = row.find('.save-btn')[0];
                    clearTimeout(saveTimer);
                    saveTimer = setTimeout(function() { saveRow(btn); }, 500);
                });

                function initReasonSelect() {
                    $('.searchable-select').each(function() {
                        if ($(this).hasClass('select2-hidden-accessible')) {
                            $(this).select2('destroy');
                        }
                        $(this).select2({
                            width: 'resolve',
                            placeholder: 'Pilih Reason',
                            allowClear: true
                        });
                    });
                }
            </script>

        </div>
    </div>

</body>
</html>