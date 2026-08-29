<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>DATA LOGISTIK</title>

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

        tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        tbody tr:hover {
            background: #dbeafe;
            transition: .2s;
        }

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

        .archive-form {
            margin: 20px 0;
        }

        .archive-btn {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            border: none;
            padding: 13px 24px;
            border-radius: 12px;
            font-size: 16px;
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

        .green, .badge-green { background: linear-gradient(135deg, #16a34a, #22c55e); }
        .red, .badge-red { background: linear-gradient(135deg, #dc2626, #ef4444); }
        .gray, .badge-gray { background: #9ca3af; }
        .orange, .badge-orange { background: #f97316; }
        .blue, .badge-blue { background: #3b82f6; }
        .yellow, .badge-yellow { background: #facc15; color: #000; }
        .success, .badge-success { background: linear-gradient(135deg, #16a34a, #22c55e); }
        .badge-purple { background: #8b5cf6; }
        .badge-pink { background: #ec4899; }
        .badge-cyan { background: #06b6d4; }
        .badge-default { background: #64748b; }
        .badge-warning { background: linear-gradient(135deg, #f59e0b, #fbbf24); color: #222; }
        .badge-info { background: linear-gradient(135deg, #2563eb, #3b82f6); }
        .badge-danger { background: linear-gradient(135deg, #dc2626, #ef4444); }
        .badge-secondary { background: linear-gradient(135deg, #00e5ff, #9ca3af); }

        .bg-success { background: #22c55e !important; color: #fff; }
        .bg-warning { background: #f59e0b !important; color: #000 !important; }
        .bg-secondary { background: #64748b !important; color: #fff; }

        .col-small {
            width: 110px !important;
            min-width: 110px !important;
            max-width: 130px !important;
            font-size: 15px;
            text-align: center;
        }

        .badge-status {
            padding: 9px 14px;
            border-radius: 20px;
            font-size: 15px;
            font-weight: 600;
            display: inline-block;
            color: #fff;
        }

        .status-belum { background: #ef4444; }
        .status-perjalanan { background: #f59e0b; }
        .status-sudah { background: #22c55e; }
        .status-default { background: #64748b; }

        .status-badge {
            display: inline-block;
            padding: 9px 16px;
            border-radius: 20px;
            font-size: 15px;
            font-weight: 700;
            text-align: center;
            line-height: 1.3;
            min-width: 180px;
            color: #fff;
        }

        .status-transit { background: #2563eb; }
        .status-unloading { background: #f59e0b; color: #fff; }
        .status-ontime { background: linear-gradient(135deg, #16a34a, #22c55e); }
        .status-delay { background: linear-gradient(135deg, #dc2626, #ef4444); }
        .status-wait { background: linear-gradient(135deg, #6b7280, #9ca3af); }

        .btn-export {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background: #198754;
            color: #fff !important;
            border: none;
            border-radius: 8px;
            font-size: 16px;
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

        /* ================= DATATABLE ================= */

        .dataTables_wrapper {
            font-size: 17px !important;
        }

        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            font-size: 17px !important;
            font-weight: 500;
        }

        .dataTables_length select {
            font-size: 17px !important;
            padding: 11px 16px !important;
            min-width: 100px;
            height: 46px;
        }

        .dataTables_filter input {
            font-size: 17px !important;
            padding: 11px 16px !important;
            min-width: 300px;
            height: 46px;
            border-radius: 10px !important;
        }

        .dataTables_info {
            font-size: 17px !important;
            padding-top: 16px !important;
        }

        .dataTables_paginate .paginate_button {
            font-size: 16px !important;
            padding: 9px 16px !important;
            margin: 0 3px !important;
            border-radius: 8px !important;
        }

        #tableLogistik td,
        #tableLogistik th {
            font-size: 16px !important;
        }

        .badge, .badge-status {
            font-size: 15px !important;
            padding: 9px 14px !important;
        }

        /* overlay loading di atas tabel saat ajax.reload() */
        #tableLogistikWrapper {
            position: relative;
        }

        #dtLoadingOverlay {
            position: absolute;
            inset: 0;
            background: rgba(255, 255, 255, .65);
            display: none;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
            color: #2563eb;
            z-index: 5;
            border-radius: 16px;
        }

        @media (min-width: 1600px) {
            .dataTables_wrapper { font-size: 18px !important; }
            #tableLogistik td, #tableLogistik th { font-size: 17px !important; }
            .dataTables_filter input { min-width: 360px; }
        }

        @media(max-width:768px) {
            .container { margin-left: 0; padding: 15px; }
            h2 { font-size: 26px; }
            table { font-size: 15px; }
            th { font-size: 15px; padding: 12px; }
            td { font-size: 15px; padding: 10px; }
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
    </style>
</head>

<body>

    @include('template.sidebar')

    <div class="container">

        <h2>📦 DATA LOGISTIK</h2>

        <div class="import-box">
            <form action="{{ url('/logistik/import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="file" name="file" required>
                <button type="submit">📤 Import Excel</button>
            </form>
        </div>

        <a href="#" id="btnExportExcel" class="btn-export">
            <i class="fa fa-file-excel"></i>
            Export Excel
        </a>

        <form action="{{ url('/logistik/archive-all') }}"
            method="POST"
            onsubmit="return confirm('Pindahkan semua data ke Storage?')"
            class="archive-form">
            @csrf
            <button type="submit" class="archive-btn">
                🗄 Archive All (Move to Storage)
            </button>
        </form>

        <div class="filter-box">
            <form id="filterForm" onsubmit="return false;">

                <select id="filterArea" name="area">
                    <option value="">Semua Area</option>
                    @foreach($areaList as $a)
                        <option value="{{ $a }}">{{ $a }}</option>
                    @endforeach
                </select>

                <input type="date" id="filterDate" name="date">

                <select id="filterMonth" name="month">
                    <option value="">Semua Bulan</option>
                    @for($i = 1; $i <= 12; $i++)
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

                <select id="filterPic" name="pic_monitoring">
                    <option value="">Semua PIC</option>
                    @foreach($picList as $p)
                        <option value="{{ $p }}">{{ $p }}</option>
                    @endforeach
                </select>

                <button type="button" id="btnResetFilter"
                    style="padding:13px 20px;background:#ef4444;color:#fff;border:none;border-radius:10px;font-weight:600;cursor:pointer;">
                    Reset Filter
                </button>

            </form>
        </div>

        <div class="card">
            <div id="tableLogistikWrapper">

                <div id="dtLoadingOverlay">⏳ Memuat data...</div>

                <table id="tableLogistik" class="display nowrap" style="width:100%">
                    <thead>
                        <tr>
                            <th>Tanggal Naik Logistik</th>
                            <th>Rencana Kirim</th>
                            <th class="col-small">Lead Time</th>
                            <th>Nama Driver</th>
                            <th>No Pol</th>
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
                            <th>Planning Loading KACS</th>
                            <th>Tanggal Tiba KACS</th>
                            <th>Tanggal Keluar KACS</th>
                            <th>Lama Di KACS</th>
                            <th>Status KACS</th>
                            <th>SLA Loading</th>
                            <th>Planning Loading Sentul</th>
                            <th>Tanggal Tiba Sentul</th>
                            <th>Tanggal Keluar Sentul</th>
                            <th>Lama Di Sentul</th>
                            <th>SLA Loading Sentul</th>
                            <th>Status Sentul</th>
                            <th>Planning Loading CCIE</th>
                            <th>Tanggal Tiba CCIE</th>
                            <th>Tanggal Keluar CCIE</th>
                            <th>Lama Di CCIE</th>
                            <th>SLA Loading CCIE</th>
                            <th>Status CCIE</th>
                            <th>PIC Monitoring</th>
                            <th>Nama Kapal</th>
                            <th>ETD</th>
                            <th>ETA</th>
                            <th>Monitoring Alert</th>
                            <th>Alert</th>
                            <th class="col-small">Urutan Bongkar</th>
                            <th>Actual Delivery Quantity</th>
                            <th>Biaya Kuli</th>
                            <th>Total Biaya Kuli</th>
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
                            <th>Estimasi Tiba Di Customer</th>
                            <th>Ontime/Delay Admin</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Sengaja dikosongkan: data diisi oleh DataTables via AJAX
                             (fullDataLogistikAjax). Jangan loop @foreach di sini lagi,
                             karena itu penyebab lag saat data ribuan baris. --}}
                    </tbody>
                </table>

            </div>
        </div>

    </div>

    <script>
        $(document).ready(function() {

            const $overlay = $('#dtLoadingOverlay');

            let table = $('#tableLogistik').DataTable({
                serverSide: true,
                processing: false, // pakai overlay sendiri, bukan bawaan DataTables
                searching: true,
                scrollX: true,
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                autoWidth: false,
                order: [],

                // PENTING: pakai POST, bukan GET.
                // Dengan 74 kolom, DataTables mengirim parameter
                // columns[0][data], columns[0][searchable], dst untuk
                // SETIAP kolom. Kalau lewat GET, itu semua masuk ke query
                // string URL dan gampang melebihi batas panjang URL
                // (error 414 Request-URI Too Long). Lewat POST, semua
                // parameter itu masuk ke request body, bukan URL.
                ajax: {
                    url: "{{ route('full.data.logistik.ajax') }}",
                    type: 'POST',
                    data: function (d) {
                        d._token         = "{{ csrf_token() }}";
                        d.area           = $('#filterArea').val();
                        d.date           = $('#filterDate').val();
                        d.month          = $('#filterMonth').val();
                        d.year           = $('#filterYear').val();
                        d.pic_monitoring = $('#filterPic').val();
                    },
                    beforeSend: function () { $overlay.css('display', 'flex'); },
                    complete:  function () { $overlay.hide(); },
                    error: function (xhr) {
                        $overlay.hide();
                        console.error('Gagal memuat data logistik:', xhr.status, xhr.responseText);
                        alert('Gagal memuat data. Coba refresh halaman.');
                    }
                },

                // Index kolom di sini HARUS sinkron urutannya dengan array
                // yang dikembalikan renderFullDataRow() di SpvPlannerController.
                columns: [
                    { data: 0 },  { data: 1 },  { data: 2 },  { data: 3 },  { data: 4 },
                    { data: 5 },  { data: 6 },  { data: 7 },  { data: 8 },  { data: 9 },
                    { data: 10 }, { data: 11 }, { data: 12 }, { data: 13 }, { data: 14 },
                    { data: 15 }, { data: 16 }, { data: 17 }, { data: 18 }, { data: 19 },
                    { data: 20 }, { data: 21 }, { data: 22 }, { data: 23 }, { data: 24 },
                    { data: 25 }, { data: 26 }, { data: 27 }, { data: 28 }, { data: 29 },
                    { data: 30 }, { data: 31 }, { data: 32 }, { data: 33 }, { data: 34 },
                    { data: 35 }, { data: 36 }, { data: 37 }, { data: 38 }, { data: 39 },
                    { data: 40 }, { data: 41 }, { data: 42 }, { data: 43 }, { data: 44 },
                    { data: 45 }, { data: 46 }, { data: 47 }, { data: 48 }, { data: 49 },
                    { data: 50 }, { data: 51 }, { data: 52 }, { data: 53 }, { data: 54 },
                    { data: 55 }, { data: 56 }, { data: 57 }, { data: 58 }, { data: 59 },
                    { data: 60 }, { data: 61 }, { data: 62 }, { data: 63 }, { data: 64 },
                    { data: 65 }, { data: 66 }, { data: 67 }, { data: 68 }, { data: 69 },
                    { data: 70 }, { data: 71 }, { data: 72 }, { data: 73 }
                ]
            });

            // ==========================================
            // FILTER (AREA, TANGGAL, BULAN, TAHUN, PIC)
            // -> reload data via ajax, TIDAK reload halaman
            // ==========================================
            $('#filterArea, #filterDate, #filterMonth, #filterYear, #filterPic')
                .on('change', function () {
                    table.ajax.reload();
                });

            $('#btnResetFilter').on('click', function () {
                $('#filterArea').val('');
                $('#filterDate').val('');
                $('#filterMonth').val('');
                $('#filterYear').val('');
                $('#filterPic').val('');
                table.ajax.reload();
            });

            // ==========================================
            // EXPORT EXCEL SESUAI FILTER AKTIF
            // ==========================================
            $('#btnExportExcel').on('click', function (e) {
                e.preventDefault();

                let params = new URLSearchParams();

                let filterArea  = $('#filterArea').val();
                let filterDate  = $('#filterDate').val();
                let filterMonth = $('#filterMonth').val();
                let filterYear  = $('#filterYear').val();

                if (filterArea)  params.append('area', filterArea);
                if (filterDate)  params.append('date', filterDate);
                if (filterMonth) params.append('month', filterMonth);
                if (filterYear)  params.append('year', filterYear);

                let baseUrl = "{{ route('logistik.export') }}";
                let finalUrl = params.toString() ? (baseUrl + '?' + params.toString()) : baseUrl;

                window.location.href = finalUrl;
            });
        });
    </script>

</body>

</html>