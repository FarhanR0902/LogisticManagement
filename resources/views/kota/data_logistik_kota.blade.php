<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DATA LOGISTIK KOTA (FULL)</title>

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

    <!-- Select2 untuk dropdown searchable -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f1f5f9;
            margin: 0;
            color: #1e293b;
            font-size: 15px;
        }

        .container {
            margin-left: 260px;
            padding: 30px;
        }

        h2 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 6px;
            color: #0f172a;
        }

        .subtitle {
            color: #64748b;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .toolbar {
            display: flex;
            gap: 10px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .card {
            background: #fff;
            padding: 20px;
            border-radius: 16px;
            overflow: auto;
            box-shadow: 0 4px 20px rgba(0, 0, 0, .08);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        th {
            background: #03c03c;
            color: #fff;
            padding: 10px 8px;
            white-space: nowrap;
            font-size: 12.5px;
            font-weight: 600;
            text-align: center;
            border: 1px solid #dbeafe;
        }

        td {
            padding: 6px 6px;
            border: 1px solid #e2e8f0;
            white-space: nowrap;
            text-align: left;
            vertical-align: middle;
            font-size: 13px;
        }

        tbody tr:nth-child(even) { background: #f8fafc; }
        tbody tr:hover { background: #dbeafe; transition: .2s; }

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
            font-size: 15px;
            min-width: 180px;
            outline: none;
        }

        .filter-box input:focus,
        .filter-box select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, .15);
        }

        .filter-box button,
        .btn-export,
        .btn-alert {
            padding: 12px 18px;
            background: #22c55e;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .filter-box button:hover { background: #16a34a; }

        .filter-box a.reset-link {
            padding: 12px 18px;
            background: #ef4444;
            color: white;
            border-radius: 10px;
            text-decoration: none;
            font-size: 15px;
            font-weight: 600;
        }
        .filter-box a.reset-link:hover { background: #dc2626; }

        .btn-alert { background: #f59e0b; }
        .btn-alert:hover { background: #d97706; }

        /* ===== Input di dalam tabel ===== */
        td input[type="text"],
        td input[type="number"],
        td input[type="date"],
        td input[type="datetime-local"],
        td select {
            width: 100%;
            min-width: 100px;
            padding: 5px 6px;
            font-size: 12.5px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            outline: none;
            background: #fff;
        }

        td input[readonly] {
            background: #f1f5f9;
            color: #0284c7;
            font-weight: 600;
        }

        .save-btn {
            padding: 7px 14px;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }
        .save-btn:hover { background: #1d4ed8; }
        .save-status {
            display: block;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 4px;
            min-height: 14px;
        }

        /* ===== BADGES ===== */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            color: #fff;
            letter-spacing: .3px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .15);
            white-space: nowrap;
        }

        .green  { background: linear-gradient(135deg, #16a34a, #22c55e) !important; }
        .red    { background: linear-gradient(135deg, #dc2626, #ef4444) !important; color: #fff !important; }
        .orange { background: #f97316 !important; }
        .blue   { background: linear-gradient(135deg, #2563eb, #3b82f6) !important; }
        .gray   { background: #64748b !important; }

        .completeness-badge { font-size: 11px; }

        select.status-select option[value="On Track"] { color: #16a34a; }
        select.status-select option[value="Potential Delay"] { color: #ef4444; }

        .select2-container { min-width: 140px !important; }
        .select2-container .select2-selection--single {
            height: 32px !important;
            border-radius: 6px !important;
            border: 1px solid #cbd5e1 !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 30px !important;
            font-size: 12.5px;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 30px !important; }

        /* ===== MODAL ALERT ===== */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, .55);
            z-index: 1000;
            align-items: flex-start;
            justify-content: center;
            padding-top: 60px;
        }
        .modal-box {
            background: #fff;
            width: 640px;
            max-width: 92%;
            max-height: 80vh;
            overflow: auto;
            border-radius: 14px;
            padding: 22px;
            box-shadow: 0 10px 40px rgba(0,0,0,.25);
        }
        .modal-box h3 { margin-top: 0; }
        .modal-close { float: right; cursor: pointer; font-weight: 700; color: #64748b; }
        .alert-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .alert-summary-pill {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 999px;
            background: #fee2e2;
            color: #b91c1c;
            font-weight: 700;
            font-size: 13px;
            margin-right: 8px;
        }

        @media(max-width:768px) {
            .container { margin-left: 0; padding: 15px; }
            h2 { font-size: 22px; }
            .filter-box form { flex-direction: column; align-items: stretch; }
            .filter-box input, .filter-box select, .filter-box button { width: 100%; }
        }
    </style>
</head>

<body>

    @include('template.sidebar')

    <div class="container">

        <h2>🚚 DATA LOGISTIK KOTA</h2>
        <div class="subtitle">Menampilkan seluruh 91 kolom tabel <code>logistik_pengiriman_kota</code> + 5 kolom hasil kalkulasi (Estimasi Live, Alert, Status Bongkar, Kelengkapan Data, Aksi).</div>

        <div class="toolbar">
            <a href="#" id="btnCekAlert" class="btn-alert">🔔 Cek Alert Pending</a>
            <form action="{{ route('kota.import') }}" method="POST" enctype="multipart/form-data" class="d-flex align-items-center" style="gap:10px;">
    @csrf
    <input type="file" name="file" accept=".xlsx,.xls,.csv" required style="padding:10px;border-radius:10px;border:1px solid #cbd5e1;">
    <button type="submit" class="btn-export" style="background:#2563eb;">
        📥 Import Excel
    </button>
</form>
        </div>

        <div class="filter-box">
            <form id="filterForm" onsubmit="return false;">

                <select id="filterArea" name="area">
                    <option value="">Semua Area</option>
                    @foreach($areaList as $a)
                        <option value="{{ $a }}">{{ $a }}</option>
                    @endforeach
                </select>

                <select id="filterPic" name="pic_monitoring">
                    <option value="">Semua PIC</option>
                    @foreach($picList as $p)
                        <option value="{{ $p }}">{{ $p }}</option>
                    @endforeach
                </select>

                <select id="filterMonth" name="bulan">
                    <option value="">Semua Bulan</option>
                    @for($i = 1; $i <= 12; $i++)
                        <option value="{{ $i }}">{{ $i }}</option>
                    @endfor
                </select>

                <select id="filterYear" name="tahun">
                    <option value="">Semua Tahun</option>
                    @php
                        $startYear = 2023;
                        $endYear = date('Y') + 1;
                    @endphp
                    @for($i = $startYear; $i <= $endYear; $i++)
                        <option value="{{ $i }}">{{ $i }}</option>
                    @endfor
                </select>

                <input type="date" id="filterKeluarGudang" name="keluar_gudang_tgl" title="Tanggal Keluar Gudang">

                <a href="#" id="btnResetFilter" class="reset-link">Reset</a>
            </form>
        </div>

        <div class="card">
            <table id="tableLogistikKota" class="display nowrap" style="width:100%">
                <thead>
                    <tr>
                        {{-- 0-90: kolom RAW sesuai urutan tabel database --}}
                        <th>ID</th>                             {{-- 0 --}}
                        <th>No Kota</th>                        {{-- 1 --}}
                        <th>Tgl Naik Logistik</th>              {{-- 2 --}}
                        <th>Rencana Kirim</th>                  {{-- 3 --}}
                        <th>Transport Lead Time</th>            {{-- 4 --}}
                        <th>Estimasi Admin</th>                 {{-- 5 --}}
                        <th>Planner</th>                        {{-- 6 --}}
                        <th>No Shipment</th>                    {{-- 7 --}}
                        <th>Tujuan</th>                         {{-- 8 --}}
                        <th>Area</th>                           {{-- 9 --}}
                        <th>Ketersediaan Unit</th>              {{-- 10 --}}
                        <th>Mobil</th>                          {{-- 11 --}}
                        <th>Perubahan Mobil</th>                {{-- 12 --}}
                        <th>Nilai Muatan</th>                   {{-- 13 --}}
                        <th>Biaya Kirim</th>                    {{-- 14 --}}
                       
                        <th>CR</th>    
                          <th>Tgl Dpt Unit</th>                   {{-- 28 --}}
                        <th>Planning Loading 1</th>             {{-- 29 --}}
                        <th>Tgl Tiba Gudang 1</th>              {{-- 30 --}}
                        <th>Tgl Keluar Gudang 1</th>            {{-- 31 --}}
                        <th>Lama Digudang 1</th>                {{-- 32 --}}
                        <th>Status Gudang 1</th>                {{-- 33 --}}
                        <th>SLA Loading 1</th>                  {{-- 34 --}}
                        <th>Keterangan</th>                     {{-- 35 --}}
                        <th>Lama Waktu Pencarian</th>           {{-- 36 --}}
                        <th>SLA Dapat Mobil</th>                {{-- 37 --}}
                         <th>Tgl Tiba Gudang 2</th>              {{-- 59 --}}
                        <th>Planning Loading 2</th>             {{-- 60 --}}
                        <th>Tgl Keluar Gudang 2</th>            {{-- 61 --}}
                        <th>Lama Digudang 2</th>                {{-- 62 --}}
                        <th>Status Gudang 2</th>                {{-- 63 --}}
                        <th>SLA Loading 2</th>                  {{-- 64 --}}
                        <th>Tgl Tiba Gudang 3</th>              {{-- 65 --}}
                        <th>Planning Loading 3</th>             {{-- 66 --}}
                        <th>Tgl Keluar Gudang 3</th>            {{-- 67 --}}
                        <th>Lama Digudang 3</th>                {{-- 68 --}}
                        <th>Status Gudang 3</th>                {{-- 69 --}}
                        <th>SLA Loading 3</th>          
                         <th>Biaya Kuli</th>                     {{-- 15 --}}
                        <th>Total Biaya Kuli</th>               {{-- 16 --}}                         {{-- 17 --}}
                        <th>Kategori Ekspedisi</th>             {{-- 18 --}}
                        <th>Ekspedisi</th>                      {{-- 19 --}}
                        <th>Nama Kapal</th>                     {{-- 20 --}}
                        <th>ETD</th>                            {{-- 21 --}}
                        <th>ETA</th>                            {{-- 22 --}}
                        <th>ATD</th>                            {{-- 23 --}}
                        <th>ATA</th>                            {{-- 24 --}}
                        <th>Nama Driver</th>                    {{-- 25 --}}
                        <th>No Polisi</th>                      {{-- 26 --}}
                        <th>Status Pengiriman</th>              {{-- 27 --}}
                      
                        <th>PIC Monitoring</th>                 {{-- 38 --}}
                        <th>Status Kendaraan</th>               {{-- 39 --}}
                        <th>Monitoring Alert</th>               {{-- 40 --}}
                        <th>Action Required</th>                {{-- 41 --}}
                        <th>Urutan Bongkar (Act)</th>           {{-- 42 --}}
                        <th>Tanggal Tiba</th>                   {{-- 43 --}}
                        <th>Lama Perjalanan</th>                {{-- 44 --}}
                        <th>SLA Tiba</th>                       {{-- 45 --}}
                        <th>Tanggal Bongkar</th>                {{-- 46 --}}
                        <th>Overstay Days</th>                  {{-- 47 --}}
                        <th>SLA Bongkar</th>                    {{-- 48 --}}
                        <th>Reason Tiba</th>                    {{-- 49 --}}
                        <th>Reason Bongkar</th>                 {{-- 50 --}}
                        <th>Status Akhir</th>                   {{-- 51 --}}
                        <th>Created At</th>                     {{-- 52 --}}
                        <th>Updated At</th>                     {{-- 53 --}}
                        <th>Tgl Tiba Estimasi</th>              {{-- 54 --}}
                        <th>Remarks</th>                        {{-- 55 --}}
                        <th>Dist Channel</th>                   {{-- 56 --}}
                        <th>Transportasi</th>                   {{-- 57 --}}
                        <th>Transport Laut</th>                 {{-- 58 --}}
                             
                        <th>Cust Grp 5 Desc</th>                {{-- 71 --}}
                        <th>Cust Grp 3 Desc</th>                {{-- 72 --}}
                        <th>Ship No</th>                        {{-- 73 --}}
                        <th>Cust Desc</th>                      {{-- 74 --}}
                        <th>Addt Text 4</th>                    {{-- 75 --}}
                        <th>Service Agent</th>                  {{-- 76 --}}
                        <th>Urutan Bongkar</th>                 {{-- 77 --}}
                        <th>Act PGI Date</th>                   {{-- 78 --}}
                        <th>Created By</th>                     {{-- 79 --}}
                        <th>Total DO Qty Car</th>               {{-- 80 --}}
                        <th>Route</th>                          {{-- 81 --}}
                        <th>Shipping Point</th>                 {{-- 82 --}}
                        <th>Kubikasi</th>                       {{-- 83 --}}
                        <th>Pulau</th>                          {{-- 84 --}}
                        <th>Via Kirim</th>                      {{-- 85 --}}
                        <th>Estimasi Tiba</th>                  {{-- 86 --}}
                        <th>Create Tgl</th>                     {{-- 87 --}}
                        <th>Qty Monitoring</th>                 {{-- 88 --}}
                        <th>Remarks Qty</th>                    {{-- 89 --}}
                        <th>Selisih Qty</th>                    {{-- 90 --}}

                        {{-- 91-95: kolom hasil kalkulasi (bukan kolom DB langsung) --}}
                        <th>Estimasi Tiba (Live)</th>           {{-- 91 --}}
                        <th>Alert</th>                          {{-- 92 --}}
                        <th>Status Bongkar</th>                 {{-- 93 --}}
                        <th>Kelengkapan Data</th>               {{-- 94 --}}
                        <th>Aksi</th>                           {{-- 95 --}}
                    </tr>
                </thead>
                <tbody>
                    {{-- Kosong: seluruh baris dimuat via AJAX oleh DataTables serverSide --}}
                </tbody>
            </table>
        </div>

    </div>

    <!-- ===== MODAL ALERT ===== -->
    <div class="modal-overlay" id="modalAlert">
        <div class="modal-box">
            <span class="modal-close" id="btnCloseAlert">✕ Tutup</span>
            <h3>🔔 Shipment Pending / Data Tidak Lengkap</h3>
            <div id="alertSummary"></div>
            <div id="alertList"></div>
        </div>
    </div>

    <script>
        $(document).ready(function() {

            const csrfToken = $('meta[name="csrf-token"]').attr('content');

            // Index kolom yang outputnya HTML (input/select/badge) -> tidak bisa
            // di-sort/search langsung oleh DataTables karena isinya bukan teks polos.
            const htmlColumns = [8, 15, 16, 20, 21, 22, 23, 24, 38, 39, 42, 43, 46, 49, 50, 55, 78, 80, 88, 89, 90, 91, 92, 93, 94, 95];

            let table = $('#tableLogistikKota').DataTable({
                processing: true,
                serverSide: true,
                deferRender: true,
                scrollX: true,
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                order: [[31, 'desc']], // default sort: Tgl Keluar Gudang 1
                language: {
                    processing: "Memuat data...",
                    search: "Cari:",
                    lengthMenu: "Tampilkan _MENU_ data",
                    info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
                    infoEmpty: "Tidak ada data",
                    infoFiltered: "(difilter dari _MAX_ total data)",
                    paginate: {
                        first: "Awal",
                        last: "Akhir",
                        next: "Selanjutnya",
                        previous: "Sebelumnya"
                    }
                },

                ajax: {
                    url: "{{ route('kota.datalogistik.ajax') }}",
                    type: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    data: function(d) {
                        d.area              = $('#filterArea').val();
                        d.pic_monitoring     = $('#filterPic').val();
                        d.bulan              = $('#filterMonth').val();
                        d.tahun              = $('#filterYear').val();
                        d.keluar_gudang_tgl  = $('#filterKeluarGudang').val();
                    }
                },

                columnDefs: [
                    { targets: htmlColumns, orderable: false, searchable: false }
                ],

                drawCallback: function() {
                    $('.searchable-select').each(function() {
                        if (!$(this).hasClass('select2-hidden-accessible')) {
                            $(this).select2({
                                width: '100%',
                                placeholder: $(this).data('placeholder'),
                                allowClear: true
                            });
                        }
                    });

                    $('.status-select').each(function() {
                        applyStatusColor(this);
                    });
                }
            });

            $('#filterArea, #filterPic, #filterMonth, #filterYear, #filterKeluarGudang').on('change', function() {
                table.ajax.reload();
            });

            $('#btnResetFilter').on('click', function(e) {
                e.preventDefault();
                $('#filterForm')[0].reset();
                $('.select2-hidden-accessible').val(null).trigger('change');
                table.ajax.reload();
            });

            // ==========================================
            // AUTO HITUNG QTY MONITORING & TOTAL BIAYA KULI (live preview)
            // ==========================================
            $(document).on('input', '.row-selisih-qty, .row-biaya-kuli', function() {
                const tr = $(this).closest('tr');
                const totalDo = parseFloat(tr.find('.row-selisih-qty').data('total-do')) || 0;
                const selisih = parseFloat(tr.find('.row-selisih-qty').val()) || 0;
                const biayaKuli = parseFloat(tr.find('.row-biaya-kuli').val()) || 0;

                const qtyMonitoring = totalDo - selisih;
                const totalBiayaKuli = qtyMonitoring * biayaKuli;

                tr.find('.row-qty-monitoring').val(qtyMonitoring);
                tr.find('.row-total-biaya-kuli').val('Rp ' + totalBiayaKuli.toLocaleString('id-ID'));
            });

            $(document).on('change', '.status-select', function() {
                applyStatusColor(this);
            });

            function applyStatusColor(el) {
                const val = $(el).val();
                if (val === 'Potential Delay') {
                    $(el).css({ color: '#dc2626', fontWeight: 700 });
                } else {
                    $(el).css({ color: '#16a34a', fontWeight: 700 });
                }
            }

            // ==========================================
            // SAVE ROW (auto-save per baris)
            // ==========================================
            window.saveRow = function(btn) {
                const id = $(btn).data('id');
                const tr = $(btn).closest('tr');
                const statusEl = tr.find('.save-status');

                let payload = {};
                tr.find('input, select, textarea').each(function() {
                    const name = $(this).attr('name');
                    if (!name) return;
                    payload[name] = $(this).val();
                });

                statusEl.text('Menyimpan...').css('color', '#f59e0b');

                payload._method = 'PUT';

                $.ajax({
                    url: "{{ url('kota/update') }}/" + id,
                    type: 'PUT',
                    data: payload,
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    success: function() {
                        statusEl.text('✅ Tersimpan').css('color', '#16a34a');
                        setTimeout(() => statusEl.text(''), 2500);
                    },
                    error: function(xhr) {
                        statusEl.text('❌ Gagal simpan').css('color', '#ef4444');
                        console.error(xhr.responseText);
                    }
                });
            };

            // ==========================================
            // MODAL ALERT
            // ==========================================
            $('#btnCekAlert').on('click', function(e) {
                e.preventDefault();

                $.ajax({
                    url: "{{ route('kota.alerts') }}",
                    type: 'GET',
                    data: {
                        area: $('#filterArea').val(),
                        pic_monitoring: $('#filterPic').val(),
                        bulan: $('#filterMonth').val(),
                        tahun: $('#filterYear').val(),
                        keluar_gudang_tgl: $('#filterKeluarGudang').val()
                    },
                    success: function(res) {
                        let summaryHtml = '<span class="alert-summary-pill">Total Pending: ' + res.totalAlert + '</span>';

                        Object.keys(res.missingSummary || {}).forEach(function(key) {
                            summaryHtml += '<span class="alert-summary-pill">' + key + ': ' + res.missingSummary[key] + '</span>';
                        });

                        $('#alertSummary').html(summaryHtml);

                        let listHtml = '';
                        if (!res.alerts || res.alerts.length === 0) {
                            listHtml = '<p>Tidak ada data pending 🎉</p>';
                        } else {
                            res.alerts.forEach(function(a) {
                                listHtml += '<div class="alert-row">'
                                    + '<div><b>' + (a.shipment || '-') + '</b><br>'
                                    + '<small>Estimasi Tiba: ' + (a.estimasi || '-') + '</small></div>'
                                    + '<div>' + a.missing.map(m => '<span class="badge red">' + m + '</span>').join(' ') + '</div>'
                                    + '</div>';
                            });
                        }
                        $('#alertList').html(listHtml);

                        $('#modalAlert').css('display', 'flex');
                    },
                    error: function(xhr) {
                        console.error(xhr.responseText);
                        alert('Gagal memuat data alert.');
                    }
                });
            });

            $('#btnCloseAlert, #modalAlert').on('click', function(e) {
                if (e.target.id === 'modalAlert' || e.target.id === 'btnCloseAlert') {
                    $('#modalAlert').css('display', 'none');
                }
            });

        });
    </script>

</body>

</html>