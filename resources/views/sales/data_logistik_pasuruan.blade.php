<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DATA LOGISTIK PASURUAN</title>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f1f5f9;
            margin: 0;
            color: #1e293b;
            font-size: 15px;
        }

        .container { margin-left: 260px; padding: 30px; }

        h2 {
            font-size: 32px;
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

        table { width: 100%; border-collapse: collapse; font-size: 14px; }

        th {
            background: #03c03c;
            color: #fff;
            padding: 14px 12px;
            white-space: nowrap;
            font-size: 14px;
            font-weight: 600;
            text-align: center;
            border: 1px solid #dbeafe;
        }

        td {
            padding: 12px 10px;
            border: 1px solid #e2e8f0;
            white-space: nowrap;
            text-align: left;
            vertical-align: middle;
            font-size: 14px;
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

        .filter-box form { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }

        .filter-box input,
        .filter-box select {
            padding: 12px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 14px;
            min-width: 180px;
            outline: none;
        }

        .filter-box input:focus,
        .filter-box select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, .15);
        }

        .filter-box button.reset-btn {
            padding: 12px 18px;
            background: #ef4444;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .filter-box button.reset-btn:hover { background: #dc2626; }

        .col-small {
            width: 100px !important;
            min-width: 100px !important;
            max-width: 120px !important;
            font-size: 13px;
            text-align: center;
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
            margin-bottom: 20px;
        }

        .btn-export:hover {
            background: #157347;
            color: #fff !important;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(25, 135, 84, .35);
        }

        /* ===== BADGE ===== */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
            color: #fff;
            letter-spacing: .3px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .15);
            white-space: nowrap;
        }

        .green  { background: #22c55e; }
        .red    { background: #ef4444; }
        .gray   { background: #64748b; }
        .orange { background: #f97316; }
        .blue   { background: #2563eb; }
        .yellow { background: #facc15; color: #000; }
        .success{ background: #028e31; }

        .badge-default { background: #64748b; }
        .badge-green   { background: #22c55e; color: #fff; }
        .badge-blue    { background: #3b82f6; color: #fff; }
        .badge-orange  { background: #f97316; color: #fff; }
        .badge-red     { background: #ef4444; color: #fff; }
        .badge-purple  { background: #8b5cf6; color: #fff; }
        .badge-pink    { background: #ec4899; color: #fff; }
        .badge-cyan    { background: #06b6d4; color: #fff; }
        .badge-yellow  { background: #eab308; color: #000; }
        .badge-gray    { background: #9ca3af; color: #fff; }

        .badge-success  { background: linear-gradient(135deg, #16a34a, #22c55e); }
        .badge-warning  { background: linear-gradient(135deg, #f59e0b, #fbbf24); color: #222; }
        .badge-info     { background: linear-gradient(135deg, #2563eb, #3b82f6); }
        .badge-danger   { background: linear-gradient(135deg, #dc2626, #ef4444); }
        .badge-secondary{ background: linear-gradient(135deg, #00e5ff, #9ca3af); }

        .badge-status {
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            display: inline-block;
        }
        .status-belum { background: #ef4444; color: #fff; }
        .status-sudah { background: #22c55e; color: #fff; }

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
        .status-transit  { background: #2563eb; }
        .status-unloading{ background: #f59e0b; }
        .status-ontime   { background: linear-gradient(135deg, #16a34a, #22c55e); }
        .status-delay    { background: linear-gradient(135deg, #dc2626, #ef4444); }

        .status-bongkar { padding: 8px 12px; border-radius: 20px; font-size: 13px; font-weight: 600; display: inline-block; color: #fff; }

        .cr-value   { color: #0056b3; font-weight: 700; font-size: 14px; }
        .text-muted { color: #94a3b8; }

        .badge-duplicate {
            background: #ff9800;
            color: #fff;
            padding: 7px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            display: inline-block;
            white-space: nowrap;
        }

        /* ================= DATATABLES ================= */
        .dataTables_wrapper { font-size: 14px !important; }
        .dataTables_filter input {
            font-size: 14px !important;
            padding: 9px 12px !important;
            min-width: 260px;
            height: 40px;
            border-radius: 8px !important;
        }
        .dataTables_length select {
            font-size: 14px !important;
            padding: 6px 10px !important;
            height: 40px;
        }
        .dataTables_paginate .paginate_button {
            font-size: 13px !important;
            padding: 7px 12px !important;
            margin: 0 3px !important;
            border-radius: 8px !important;
        }

        @media(max-width:768px) {
            .container { margin-left: 0; padding: 15px; }
            h2 { font-size: 24px; }
            .filter-box form { flex-direction: column; align-items: stretch; }
            .filter-box input,
            .filter-box select,
            .filter-box button { width: 100%; }
        }
    </style>
</head>

<body>

    @include('template.sidebar')

    <div class="container">

        <h2>📦 DATA LOGISTIK PASURUAN</h2>

        <a href="#" id="btnExportExcel" class="btn-export">
            <i class="fa fa-file-excel"></i> Export Excel
        </a>

        <!-- FILTER (server-side, dikirim via AJAX ke dataLogistikPasuruanAjax) -->
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

                <button type="button" id="btnResetFilter" class="reset-btn">Reset Filter</button>
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
                        <th>Tanggal Tiba Gudang</th>
                        <th>Tanggal Keluar Gudang</th>
                        <th>PIC Monitoring</th>
                        <th>Nama Kapal</th>
                        <th>ETD</th>
                        <th>ETA</th>
                        <th>Alert</th>
                        <th class="col-small">Urutan Bongkar</th>
                        <th>Actual Delivery Quantity</th>
                        <th>Selisih Quantity</th>
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
                    <!-- Data diisi lewat AJAX (server-side processing) -->
                </tbody>
            </table>
        </div>

    </div>

    <script>
    $(document).ready(function () {

        // Sorting per kolom dimatikan: backend hanya support urutan tetap
        // (orderByDesc('id')). Kalau butuh sorting per kolom, tambah handling
        // order[0][column] / order[0][dir] di dataLogistikPasuruanAjax().
        let table = $('#tableLogistik').DataTable({
            processing: true,
            serverSide: true,
            scrollX: true,
            pageLength: 10,
            autoWidth: false,
            ordering: false,
            ajax: {
                url: "{{ route('sales.data.pasuruan.ajax') }}",
                type: 'POST',
                data: function (d) {
                    d._token  = "{{ csrf_token() }}";
                    d.planner = $('#filterPlanner').val();
                    d.area    = $('#filterArea').val();
                    d.date    = $('#filterDate').val();
                    d.month   = $('#filterMonth').val();
                    d.year    = $('#filterYear').val();
                }
            },
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
                { data: 50 }, { data: 51 }
            ]
        });

        $('#filterPlanner, #filterArea, #filterDate, #filterMonth, #filterYear').on('change', function () {
            table.ajax.reload();
        });

        $('#btnResetFilter').on('click', function () {
            $('#filterForm')[0].reset();
            table.ajax.reload();
        });

        // ==========================================
        // EXPORT EXCEL SESUAI FILTER AKTIF
        // ==========================================
        $('#btnExportExcel').on('click', function (e) {
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

            let baseUrl  = "{{ route('pasuruan.export') }}";
            let finalUrl = params.toString() ? (baseUrl + '?' + params.toString()) : baseUrl;

            window.location.href = finalUrl;
        });
    });
    </script>

</body>
</html>