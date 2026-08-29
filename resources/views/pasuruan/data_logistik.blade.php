<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>DATA LOGISTIK</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <style>
            body {
                font-family: 'Segoe UI', Arial, sans-serif;
                background: #f1f5f9;
                margin: 0;
                color: #1e293b;
                font-size: 16px;
            }

            /* ================= CONTAINER ================= */

            .container {
                margin-left: 260px;
                padding: 30px;
            }

            h2 {
                font-size: 34px;
                font-weight: 700;
                margin-bottom: 20px;
                color: #0f172a;
            }

            /* ================= CARD ================= */

            .card {
                background: #fff;
                padding: 20px;
                border-radius: 16px;
                overflow: auto;
                box-shadow: 0 4px 20px rgba(0, 0, 0, .08);
            }

            /* ================= TABLE ================= */

            table {
                width: 100%;
                border-collapse: collapse;
                font-size: 16px;
            }

            th {
                background: #03c03c;
                color: #fff;
                padding: 16px 14px;
                white-space: nowrap;
                font-size: 16px;
                font-weight: 600;
                text-align: center;
                border: 1px solid #dbeafe;
            }

            td {
                padding: 14px 12px;
                border: 1px solid #e2e8f0;
                white-space: nowrap;
                text-align: left;
                vertical-align: middle;
                font-size: 16px;
            }

            /* Zebra */

            tbody tr:nth-child(even) {
                background: #f8fafc;
            }

            tbody tr:hover {
                background: #dbeafe;
                transition: .2s;
            }

            /* ================= FILTER ================= */

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
                padding: 13px 16px;
                border: 1px solid #cbd5e1;
                border-radius: 10px;
                font-size: 16px;
                min-width: 190px;
                outline: none;
            }

            .filter-box input:focus,
            .filter-box select:focus {
                border-color: #3b82f6;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, .15);
            }

            .filter-box button {
                padding: 13px 20px;
                background: #22c55e;
                color: white;
                border: none;
                border-radius: 10px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
            }

            .filter-box button:hover {
                background: #16a34a;
            }

            .filter-box a {
                padding: 13px 20px;
                background: #ef4444;
                color: white;
                border-radius: 10px;
                text-decoration: none;
                font-size: 16px;
                font-weight: 600;
            }

            .filter-box a:hover {
                background: #dc2626;
            }

            /* ================= IMPORT BOX ================= */

            .import-box {
                background: #fff;
                padding: 20px;
                border-radius: 16px;
                box-shadow: 0 4px 16px rgba(0, 0, 0, .08);
                margin-bottom: 20px;
            }

            .import-box form {
                display: flex;
                gap: 12px;
                align-items: center;
                flex-wrap: wrap;
            }

            .import-box input[type=file] {
                padding: 13px;
                border: 2px dashed #cbd5e1;
                border-radius: 10px;
                background: #f8fafc;
                font-size: 16px;
            }

            .import-box input[type=file]:hover {
                border-color: #2563eb;
            }

            .import-box button {
                padding: 13px 20px;
                border: none;
                border-radius: 10px;
                background: linear-gradient(135deg, #3b82f6, #2563eb);
                color: white;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
            }

            .import-box button:hover {
                transform: translateY(-2px);
            }

            /* ================= ARCHIVE BUTTON ================= */

            .archive-form {
                margin: 20px 0;
            }

            .archive-btn {
                background: linear-gradient(135deg, #2563eb, #1d4ed8);
                color: white;
                border: none;
                padding: 15px 26px;
                border-radius: 12px;
                font-size: 17px;
                font-weight: 600;
                cursor: pointer;
                transition: .3s;
            }

            .archive-btn:hover {
                transform: translateY(-2px);
            }

            /* ================= BADGE ================= */

            .badge {
                padding: 9px 16px;
                border-radius: 20px;
                color: white;
                font-size: 15px;
                font-weight: 600;
                display: inline-block;
            }

            .green {
                background: #22c55e;
            }

            .red {
                background: #ef4444;
            }

            .gray {
                background: #64748b;
            }

            .orange {
                background: #f97316;
            }

            .blue {
                background: #2563eb;
            }

            .yellow {
                background: #facc15;
                color: #000;
            }

            .badge-mp {
                background: #16a34a;
            }

            .badge-cmd {
                background: #2563eb;
            }

            .badge-jess {
                background: #f59e0b;
            }

            .badge-default {
                background: #64748b;
            }

            .badge-green {
                background: #22c55e;
            }

            .badge-red {
                background: #ef4444;
            }

            .badge-gray {
                background: #64748b;
            }

            .badge-orange {
                background: #f97316;
            }

            .bg-success {
                background: #22c55e !important;
            }

            .bg-warning {
                background: #ef4444 !important;
                color: #ffffff !important;
            }

            .bg-secondary {
                background: #64748b !important;
            }

            /* ================= SMALL COLUMN ================= */

            .col-small {
                width: 110px !important;
                min-width: 110px !important;
                max-width: 130px !important;
                font-size: 15px;
                text-align: center;
            }

            /* ================= DATATABLE ================= */

            .dataTables_wrapper {
                font-size: 16px;
            }

            .dataTables_filter input {
                padding: 9px 14px !important;
                border-radius: 8px !important;
                font-size: 16px !important;
            }

            .dataTables_length select {
                padding: 7px 12px !important;
                font-size: 16px !important;
            }

            /* ================= RESPONSIVE ================= */

            @media(max-width:768px) {

                .container {
                    margin-left: 0;
                    padding: 15px;
                }

                h2 {
                    font-size: 26px;
                }

                table {
                    font-size: 15px;
                }

                th {
                    font-size: 15px;
                    padding: 12px;
                }

                td {
                    font-size: 15px;
                    padding: 10px;
                }

                .filter-box form,
                .import-box form {
                    flex-direction: column;
                    align-items: stretch;
                }

                .filter-box input,
                .filter-box select,
                .filter-box button,
                .import-box button {
                    width: 100%;
                }
            }

            .badge {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 9px 16px;
                border-radius: 999px;
                font-size: 15px;
                font-weight: 700;
                color: #fff;
                letter-spacing: .3px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, .15);
            }

            /* Hijau */
            .badge-success {
                background: linear-gradient(135deg, #16a34a, #22c55e);
            }

            /* Kuning */
            .badge-warning {
                background: linear-gradient(135deg, #f59e0b, #fbbf24);
                color: #222;
            }

            /* Biru */
            .badge-info {
                background: linear-gradient(135deg, #2563eb, #3b82f6);
            }

            /* Merah */
            .badge-danger {
                background: linear-gradient(135deg, #dc2626, #ef4444);
            }

            /* Abu */
            .badge-secondary {
                background: linear-gradient(135deg, #00e5ff, #9ca3af);
            }

            .archive-form {
                margin: 20px 0;
            }

            .badge-green {
                background: #22c55e;
            }

            .badge-red {
                background: #ef4444;
            }

            .badge-gray {
                background: #6b7280;
            }

            .badge-orange {
                background: #f97316;
            }

            .archive-btn {
                background: linear-gradient(135deg, #2563eb, #1d4ed8);
                color: white;
                border: none;
                padding: 12px 22px;
                border-radius: 12px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                transition: 0.3s ease;
                box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }

            .archive-btn:hover {
                transform: translateY(-2px);
                background: linear-gradient(135deg, #1d4ed8, #1e40af);
                box-shadow: 0 8px 18px rgba(37, 99, 235, 0.35);
            }

            .archive-btn:active {
                transform: scale(0.98);
            }

            .badge.gray {
                background: #9ca3af;
            }

            .badge.blue {
                background: #3b82f6;
            }

            .badge.yellow {
                background: #facc15;
                color: #000;
            }

            .badge.orange {
                background: #f97316;
            }

            .badge.success {
                background: #028e31;
            }

            /* ================= DATATABLES XL ================= */

            .dataTables_wrapper {
                font-size: 16px !important;
            }

            .dataTables_wrapper .dataTables_length,
            .dataTables_wrapper .dataTables_filter,
            .dataTables_wrapper .dataTables_info,
            .dataTables_wrapper .dataTables_paginate {
                font-size: 16px !important;
                font-weight: 500;
            }

            /* Show entries */
            .dataTables_length select {
                font-size: 16px !important;
                padding: 10px 14px !important;
                min-width: 90px;
                height: 42px;
            }

            /* Search box */
            .dataTables_filter input {
                font-size: 16px !important;
                padding: 10px 14px !important;
                min-width: 280px;
                height: 42px;
                border-radius: 10px !important;
            }

            /* Info text */
            .dataTables_info {
                font-size: 16px !important;
                padding-top: 15px !important;
            }

            /* Pagination */
            .dataTables_paginate .paginate_button {
                font-size: 15px !important;
                padding: 8px 14px !important;
                margin: 0 3px !important;
                border-radius: 8px !important;
            }

            /* Table font lebih besar */
            #tableLogistik td {
                font-size: 15px !important;
            }

            #tableLogistik th {
                font-size: 15px !important;
            }

            /* Badge ikut membesar */
            .badge,
            .badge-status {
                font-size: 14px !important;
                padding: 8px 12px !important;
            }

            /* Responsive */
            @media (min-width: 1600px) {

                .dataTables_wrapper {
                    font-size: 17px !important;
                }

                #tableLogistik td {
                    font-size: 16px !important;
                }

                #tableLogistik th {
                    font-size: 16px !important;
                }

                .dataTables_filter input {
                    min-width: 350px;
                }
            }

            .badge-green {
                background: #22c55e;
                color: white;
            }

            .badge-blue {
                background: #3b82f6;
                color: white;
            }

            .badge-orange {
                background: #f97316;
                color: white;
            }

            .badge-red {
                background: #ef4444;
                color: white;
            }

            .badge-purple {
                background: #8b5cf6;
                color: white;
            }

            .badge-pink {
                background: #ec4899;
                color: white;
            }

            .badge-cyan {
                background: #06b6d4;
                color: white;
            }

            .badge-yellow {
                background: #eab308;
                color: black;
            }

            .badge-status {
                padding: 8px 12px;
                border-radius: 20px;
                font-size: 13px;
                font-weight: 600;
                display: inline-block;
            }

            .status-belum {
                background: #ef4444;
                color: white;
            }

            .status-perjalanan {
                background: #f59e0b;
                color: white;
            }

            .status-sudah {
                background: #22c55e;
                color: white;
            }

            .status-default {
                background: #64748b;
                color: white;
            }

            .status-badge {
                display: inline-block;
                padding: 8px 14px;
                border-radius: 20px;
                font-size: 13px;
                font-weight: 700;
                text-align: center;
                line-height: 1.3;
                min-width: 170px;
                color: #fff;
            }

            .status-transit {
                background: #2563eb;
                /* Biru */
            }

            .status-unloading {
                background: #f59e0b;
                /* Orange */
                color: #fff;
            }

            .status-ontime {
                background: #16a34a;
                /* Hijau */
            }

            .status-delay {
                background: #dc2626;
                /* Merah */
            }

            .status-ontime {
                background: linear-gradient(135deg, #16a34a, #22c55e);
            }

            .status-delay {
                background: linear-gradient(135deg, #dc2626, #ef4444);
            }

            .status-wait {
                background: linear-gradient(135deg, #6b7280, #9ca3af);
            }

            .btn-export {
display: inline-flex;
align-items: center;
gap: 8px;

padding: 10px 18px;

background: #198754;
color: #fff !important;

border: none;
border-radius: 8px;

font-size: 14px;
font-weight: 600;

text-decoration: none;
cursor: pointer;

transition: all .25s ease;
box-shadow: 0 3px 10px rgba(25, 135, 84, .25);
}

.btn-export:hover {
background: #157347;
color: #fff !important;

transform: translateY(-2px);
box-shadow: 0 6px 15px rgba(25, 135, 84, .35);
}

.btn-export:active {
transform: scale(.98);
}

.btn-export i {
font-size: 16px;
}

/* Loading overlay saat DataTables ambil data dari server */
#tableLogistik_processing {
    background: rgba(255,255,255,.85) !important;
    color: #2563eb !important;
    font-weight: 700 !important;
    font-size: 16px !important;
    border-radius: 12px;
    padding: 14px 20px !important;
    box-shadow: 0 4px 20px rgba(0,0,0,.12);
}
        </style>

</head>

<body>

    @include('template.sidebar')

    <div class="container">

        <h2>📦 DATA LOGISTIK</h2>

        <div class="import-box">
            <form action="{{ route('spvplanner.import.pasuruan') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="file" name="file" required>
                <button type="submit">📤 Import Excel</button>
            </form>
        </div>

        <a href="#" id="btnExportExcel" class="btn-export">
            <i class="fa fa-file-excel"></i>
            Export Excel
        </a>

        <!-- HAPUS SEMUA -->
        <form action="{{ route('spvplanner.archive') }}" method="POST"
              onsubmit="return confirm('Pindahkan semua data ke Storage?')" class="archive-form">
            @csrf
        </form>

        <div class="filter-box">
            <form id="filterForm">

                <select id="filterPlanner" name="planner">
                    <option value="">Semua Planner</option>
                    @foreach($planners as $p)
                        <option value="{{ $p }}">{{ $p }}</option>
                    @endforeach
                </select>

                <select id="filterArea" name="area">
                    <option value="">Semua Area</option>
                    @foreach($areas as $a)
                        <option value="{{ $a }}">{{ $a }}</option>
                    @endforeach
                </select>

                <input type="date" id="filterDate">

                <select id="filterMonth" name="month">
                    <option value="">Semua Bulan</option>
                    @for($i=1;$i<=12;$i++)
                        <option value="{{ $i }}">{{ $i }}</option>
                    @endfor
                </select>

                <select id="filterYear" name="year">
                    <option value="">Semua Tahun</option>
                    @php
                        $startYear = 2023;
                        $endYear = date('Y') + 1;
                    @endphp
                    @for($i = $startYear; $i <= $endYear; $i++)
                        <option value="{{ $i }}">{{ $i }}</option>
                    @endfor
                </select>

            </form>
        </div>

        <div class="card">
            <table id="tableLogistik" class="display nowrap" style="width:100%">

                <thead>
                    <tr>
                        <th>Tanggal Naik Logistik</th>
                        <th>Rencana Kirim</th>
                        <th class="col-small">Lead Time</th>
                        <th>Planner</th>
                        <th>No Shipment</th>
                        <th>Update Posisi Mobil</th>
                        <th>Dist Channel</th>
                        <th>Tujuan</th>
                        <th>Area</th>
                        <th>Ketersediaan Unit</th>
                        <th>Mobil</th>
                        <th>Delivery Quantity</th>
                        <th>Nilai Muatan</th>
                        <th>Biaya Kirim</th>
                        <th>CR</th>
                        <th>Kategori Ekspedisi</th>
                        <th>Ekspedisi</th>
                        <th>Tanggal Dapat Unit</th>
                        <th>Lama Waktu Pencarian</th>
                        <th>SLA Dapat Mobil</th>
                        <th>Planning Loading Pasuruan</th>
                        <th>Tanggal Tiba Pasuruan</th>
                        <th>Tanggal Keluar Pasuruan</th>
                        <th>PIC Monitoring</th>
                        <th>Nama Kapal</th>
                        <th>ETD</th>
                        <th>ETA</th>
                        <th>Alert</th>
                        <th class="col-small">Urutan Bongkar</th>
                        <th>Actual Delivery Quantity</th>
                        <th>Selsih quantity</th>
                        <th>Reason Selisih Quantity</th>
                        <th>Act PGI Date</th>
                        <th>ATD</th>
                        <th>ATA</th>
                        <th>Tanggal Estimasi</th>
                        <th>Tanggal Tiba</th>
                        <th>Lama Perjalanan</th>
                        <th>SLA Tiba</th>
                        <th>Tanggal Bongkar</th>
                        <th>Status Bongkar</th>
                        <th>Overstay</th>
                        <th>SLA Bongkar</th>
                        <th>Reason Tiba</th>
                        <th>Reason Bongkar</th>
                        <th>Status Akhir</th>
                        <th>Status Alert</th>
                        <th>Remarks</th>
                        <th>Route</th>
                        <th>Shipping Point</th>
                        <th>Pulau</th>
                        <th>Via Kirim</th>
                    </tr>
                </thead>

                <tbody>
                    <!-- Data diisi otomatis oleh DataTables via AJAX (server-side) -->
                </tbody>

            </table>
        </div>

    </div>

    <script>
    $(document).ready(function() {
$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
});
        let table = $('#tableLogistik').DataTable({
            processing: true,
            serverSide: true,
            scrollX: true,
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            autoWidth: false,
            order: [[0, 'desc']],

            ajax: {
                url: "{{ route('pasuruan.dataLogistikAjax') }}",
                type: 'POST',
                data: function(d) {
                    d.planner = $('#filterPlanner').val();
                    d.area    = $('#filterArea').val();
                    d.date    = $('#filterDate').val();
                    d.month   = $('#filterMonth').val();
                    d.year    = $('#filterYear').val();
                }
            },

            // Urutan kolom di bawah ini HARUS SAMA PERSIS dengan urutan <th> di atas,
            // dan HARUS SAMA PERSIS dengan urutan key yang dikembalikan controller
            // (PasuruanController::dataLogistikAjaxPasuruan -> $data->map(...)).
            columns: [
                { data: 'tanggal_naik_fmt' },
                { data: 'rencana_kirim_fmt' },
                { data: 'lead_time' },
                { data: 'planner' },
                { data: 'no_shipment' },
                { data: 'posisi_mobil_badge' },
                { data: 'dist_channel_badge' },
                { data: 'tujuan' },
                { data: 'area' },
                { data: 'ketersediaan_badge' },
                { data: 'mobil' },
                { data: 'delivery_qty' },
                { data: 'nilai_muatan_fmt' },
                { data: 'biaya_kirim_fmt' },
                { data: 'cr_fmt' },
                { data: 'kategori_ekspedisi_badge' },
                { data: 'ekspedisi' },
                { data: 'tanggal_dpt_fmt' },
                { data: 'lama_pencarian' },
                { data: 'sla_dapat_mobil_badge' },
                { data: 'planning_loading_fmt' },
                { data: 'tiba_gudang_fmt' },
                { data: 'keluar_gudang_fmt' },
                { data: 'pic_monitoring' },
                { data: 'nama_kapal' },
                { data: 'etd' },
                { data: 'eta' },
                { data: 'alert_badge' },
                { data: 'urutan_bongkar' },
                { data: 'actual_delivery_qty' },
                { data: 'selisih_qty_badge' },
                { data: 'reason_selisih_qty' },
                { data: 'act_pgi_fmt' },
                { data: 'atd_fmt' },
                { data: 'ata_fmt' },
                { data: 'estimasi_fmt' },
                { data: 'tiba_fmt' },
                { data: 'lama_perjalanan' },
                { data: 'sla_tiba_badge' },
                { data: 'bongkar_fmt' },
                { data: 'status_bongkar_badge' },
                { data: 'overstay_badge' },
                { data: 'sla_bongkar_badge' },
                { data: 'reason_tiba' },
                { data: 'reason_bongkar' },
                { data: 'status_akhir_badge' },
                { data: 'status_alert_badge' },
                { data: 'remarks' },
                { data: 'route' },
                { data: 'shipping_point' },
                { data: 'pulau' },
                { data: 'via_kirim' },
            ],

            language: {
                processing: "Memuat data...",
                emptyTable: "Tidak ada data",
                zeroRecords: "Data tidak ditemukan",
                search: "Cari:",
                lengthMenu: "Tampilkan _MENU_ baris",
                info: "Menampilkan _START_ - _END_ dari _TOTAL_ baris",
                infoEmpty: "Menampilkan 0 baris",
                infoFiltered: "(disaring dari _MAX_ total baris)",
                paginate: {
                    first: "Awal",
                    last: "Akhir",
                    next: "Berikutnya",
                    previous: "Sebelumnya"
                }
            }

            // CATATAN: drawCallback penghitung CR (2-pass parsing DOM) SUDAH
            // DIHAPUS. CR sekarang dihitung di server (kolom cr_fmt) dan
            // di-cache 5 menit lewat Cache::remember('pasuruan_shipment_agg', ...)
            // di PasuruanController, jadi tidak perlu dihitung ulang di browser
            // tiap kali table di-draw / ganti halaman / filter.
        });

        // Filter dropdown/date/month/year -> reload data dari server (bukan filter client-side lagi)
        $('#filterArea, #filterPlanner, #filterDate, #filterMonth, #filterYear').on('change', function() {
            table.ajax.reload();
        });
    });

    // ==========================================
    // EXPORT EXCEL SESUAI FILTER AKTIF
    // ==========================================
    $('#btnExportExcel').on('click', function(e) {
        e.preventDefault();

        let params = new URLSearchParams();

        let filterArea    = $('#filterArea').val();
        let filterPlanner = $('#filterPlanner').val();
        let filterDate    = $('#filterDate').val();
        let filterMonth   = $('#filterMonth').val();
        let filterYear    = $('#filterYear').val();

        if (filterArea)    params.append('area', filterArea);
        if (filterPlanner) params.append('planner', filterPlanner);
        if (filterDate)    params.append('date', filterDate);
        if (filterMonth)   params.append('month', filterMonth);
        if (filterYear)    params.append('year', filterYear);

        let baseUrl = "{{ route('pasuruan.export') }}";
        let finalUrl = params.toString() ? (baseUrl + '?' + params.toString()) : baseUrl;

        window.location.href = finalUrl;
    });
    </script>

</body>

</html>