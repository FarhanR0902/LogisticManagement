<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

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

        /* ================= IMPORT / ARCHIVE ================= */
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

        /* ================= BADGES ================= */
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

        .pink,
        .badge-pink {
            background: #00ffe1 !important;
            color: #000 !important;
        }

        .green,
        .badge-green,
        .bg-success,
        .badge-success {
            background: linear-gradient(135deg, #16a34a, #22c55e) !important;
        }

        .red,
        .badge-red,
        .badge-danger {
            background: linear-gradient(135deg, #dc2626, #ef4444) !important;
            color: #fff !important;
        }

        .bg-warning,
        .badge-warning {
            background: linear-gradient(135deg, #f59e0b, #fbbf24) !important;
            color: #222 !important;
        }

        .orange,
        .badge-orange {
            background: #f97316 !important;
        }

        .blue,
        .badge-blue,
        .badge-info {
            background: linear-gradient(135deg, #2563eb, #3b82f6) !important;
        }

        .yellow,
        .badge-yellow {
            background: #facc15 !important;
            color: #000 !important;
        }

        .gray,
        .badge-gray,
        .bg-secondary,
        .badge-secondary {
            background: #64748b !important;
        }

        .badge-default {
            background: #64748b;
        }

        .badge-purple {
            background: #8b5cf6 !important;
        }

        .badge-cyan {
            background: #06b6d4 !important;
        }

        .badge-status {
            display: inline-block;
            padding: 9px 16px;
            border-radius: 20px;
            font-size: 15px;
            font-weight: 700;
            color: #fff;
        }

        .status-belum {
            background: #ef4444;
        }

        .status-perjalanan {
            background: #f59e0b;
        }

        .status-sudah {
            background: #22c55e;
        }

        .status-default {
            background: #64748b;
        }

        .status-badge {
            display: inline-block;
            padding: 9px 16px;
            border-radius: 20px;
            font-size: 15px;
            font-weight: 700;
            text-align: center;
            min-width: 180px;
            color: #fff;
        }

        .status-transit {
            background: #2563eb;
        }

        .status-unloading {
            background: #f59e0b;
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

        /* ================= TOMBOL EXPORT / HAPUS ================= */
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
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(25, 135, 84, .35);
        }

        .btn-export:active {
            transform: scale(.98);
        }

        .btn-trash {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background: #eb0c0c;
            color: #fff !important;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all .25s ease;
            box-shadow: 0 3px 10px rgba(235, 12, 12, .25);
        }

        .btn-trash:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(235, 12, 12, .35);
        }

        .btn-trash:active {
            transform: scale(.98);
        }

        .col-small {
            width: 110px !important;
            min-width: 110px !important;
            max-width: 130px !important;
            font-size: 15px;
            text-align: center;
        }

        /* ================= DATATABLE ================= */
        .dataTables_wrapper {
            font-size: 17px !important;
        }

        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate,
        .dataTables_wrapper .dataTables_processing {
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

        .btn-tujuan {
            color: #2563eb;
            font-weight: 600;
            text-decoration: none;
        }

        .btn-tujuan:hover {
            text-decoration: underline;
        }

        .dataTables_processing {
            background: rgba(255, 255, 255, .92) !important;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, .12);
            padding: 14px 22px !important;
            font-weight: 700 !important;
            color: #0f172a !important;
        }

        #tableLogistik td,
        #tableLogistik th {
            font-size: 16px !important;
        }

        .badge,
        .badge-status {
            font-size: 15px !important;
            padding: 9px 14px !important;
        }

        @media (min-width: 1600px) {
            .dataTables_wrapper {
                font-size: 18px !important;
            }

            #tableLogistik td,
            #tableLogistik th {
                font-size: 17px !important;
            }

            .dataTables_filter input {
                min-width: 360px;
            }
        }

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

            th,
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

        /* ===== MULTI SELECT DROPDOWN ===== */
        .ms-dd {
            position: relative;
            width: 220px;
            flex: 0 0 220px;
        }

        .filter-box .ms-dd-btn {
            width: 100%;
            height: 46px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            background: #fff;
            color: #1e293b;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 0 16px;
            font-size: 16px;
            font-weight: 400;
            cursor: pointer;
        }

        .filter-box .ms-dd-btn:hover {
            background: #fff;
            border-color: #3b82f6;
        }

        .ms-dd-btn .ms-label {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .ms-dd.has-value .ms-dd-btn {
            border-color: #3b82f6;
            background: #eff6ff;
        }

        .ms-dd.has-value .ms-label {
            font-weight: 600;
            color: #1e40af;
        }

        .ms-dd-panel {
            position: absolute;
            top: 50px;
            left: 0;
            z-index: 9999;
            width: 280px;
            background: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, .2);
            padding: 10px;
        }

        .filter-box .ms-dd-panel .ms-search {
            width: 100%;
            box-sizing: border-box;
            min-width: 0;
            padding: 9px 12px;
            font-size: 15px;
            margin-bottom: 4px;
        }

        .ms-dd-actions {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            margin: 6px 2px;
        }

        .filter-box .ms-dd-actions a {
            background: none;
            padding: 0;
            border-radius: 0;
            font-size: 14px;
            color: #2563eb;
            font-weight: 600;
            text-decoration: none;
        }

        .filter-box .ms-dd-actions a:hover {
            background: none;
            text-decoration: underline;
        }

        .ms-dd-list {
            max-height: 260px;
            overflow-y: auto;
        }

        .ms-dd-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px;
            margin: 0;
            font-size: 15px;
            cursor: pointer;
        }

        .ms-dd-item:hover {
            background: #f1f5f9;
            border-radius: 6px;
        }

        .filter-box .ms-dd-item input {
            width: auto;
            min-width: 0;
            margin: 0;
            padding: 0;
        }

        .ms-empty {
            padding: 8px;
            font-size: 14px;
            color: #94a3b8;
            display: none;
        }

        @media (max-width: 768px) {
            .ms-dd {
                width: 100%;
                flex: 1 1 100%;
            }

            .ms-dd-panel {
                width: 100%;
            }
        }

        .tgl-lbl {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 15px;
            font-weight: 600;
            color: #475569;
        }

        .tgl-lbl input {
            min-width: 170px;
        }

        /* ================= TOOLBAR RAPI (import / aksi) ================= */
        .tools-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .tool-card {
            background: #fff;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, .08);
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .tool-title {
            margin: 0;
            font-size: 17px;
            font-weight: 700;
            color: #0f172a;
        }

        .tool-sub {
            margin: 0;
            font-size: 13px;
            line-height: 1.5;
            color: #64748b;
        }

        .tool-card form {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        .tool-card input[type=file] {
            flex: 1 1 220px;
            min-width: 0;
            padding: 11px;
            border: 2px dashed #cbd5e1;
            border-radius: 10px;
            background: #f8fafc;
            font-size: 15px;
        }

        .tool-card input[type=file]:hover {
            border-color: #2563eb;
        }

        .btn-primary-x {
            height: 44px;
            padding: 0 20px;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #fff;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
        }

        .btn-primary-x:hover {
            transform: translateY(-2px);
        }

        .btn-primary-x:disabled {
            opacity: .7;
            cursor: wait;
            transform: none;
        }

        .chk-lbl {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            cursor: pointer;
            color: #475569;
            flex-basis: 100%;
        }

        .chk-lbl input {
            width: 16px;
            height: 16px;
            margin: 0;
        }

        .action-card {
            background: #fff;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, .08);
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 20px 36px;
            align-items: flex-start;
        }

        .ag {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .ag-title {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .8px;
            text-transform: uppercase;
            color: #64748b;
        }

        .ag-row {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .ag-danger {
            margin-left: auto;
        }

        .action-card .archive-form {
            margin: 0;
        }

        .action-card .btn-export,
        .action-card .btn-trash,
        .action-card .archive-btn {
            height: 44px;
            padding: 0 18px;
            font-size: 15px;
            border-radius: 10px;
            box-sizing: border-box;
            white-space: nowrap;
        }

        .ag-select {
            height: 44px;
            padding: 0 14px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 15px;
            min-width: 210px;
            background: #fff;
            box-sizing: border-box;
        }

        @media (max-width: 1100px) {
            .tools-grid {
                grid-template-columns: 1fr;
            }

            .ag-danger {
                margin-left: 0;
            }
        }

        @media (max-width: 768px) {
            .action-card {
                flex-direction: column;
            }

            .ag,
            .ag-row,
            .ag-row form,
            .tool-card form {
                width: 100%;
            }

            .action-card .btn-export,
            .action-card .btn-trash,
            .action-card .archive-btn,
            .ag-select,
            .btn-primary-x {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>

<body>

    @include('template.sidebar')

    <div class="container">

        <h2>📦 DATA LOGISTIK</h2>

        {{-- ================= NOTIFIKASI ================= --}}
        @if(session('success'))
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;padding:16px 20px;border-radius:12px;margin-bottom:20px;">
            ✅ {{ session('success') }}
        </div>
        @endif

        @if(session('error') || session('import_error'))
        <div style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:16px 20px;border-radius:12px;margin-bottom:20px;">
            ❌ {{ session('error') ?? session('import_error') }}
        </div>
        @endif

        {{-- ================= HASIL IMPORT ================= --}}
        @if($r = session('import_result'))
        <div style="background:#fff;border-radius:16px;padding:20px;margin-bottom:20px;box-shadow:0 4px 16px rgba(0,0,0,.08);
                border-left:6px solid {{ $r['failed'] > 0 ? '#f59e0b' : '#16a34a' }};">

            <div style="font-size:20px;font-weight:700;margin-bottom:12px;">
                {{ $r['failed'] > 0 ? '⚠️ Import selesai, sebagian gagal' : '✅ Import berhasil' }}
            </div>

            <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:12px;">
                <span class="badge green">✅ {{ number_format($r['success']) }} data berhasil</span>
                <span class="badge blue">➕ {{ number_format($r['inserted']) }} baru</span>
                <span class="badge orange">🔄 {{ number_format($r['updated']) }} diperbarui</span>
                <span class="badge gray">⏭ {{ number_format($r['skipped']) }} dilewati</span>
                <span class="badge badge-default">♻️ {{ number_format($r['skipped_existing'] ?? 0) }} sudah ada</span>
                @if($r['failed'] > 0)
                <span class="badge red">❌ {{ number_format($r['failed']) }} gagal</span>
                @endif
                <span class="badge badge-default">📦 {{ number_format($r['shipments']) }} no shipment</span>
            </div>
            <div style="font-size:14px;color:#64748b;">
                Dilewati = baris kosong, atau No Shipment / Tujuan-nya kosong.<br>
                Sudah ada = No DO-nya sudah ada di database, jadi tidak diimport ulang.
            </div>
            <div style="font-size:14px;color:#64748b;margin-top:6px;">
                Tidak ada perubahan (data sama persis): <b>{{ number_format($r['unchanged'] ?? 0) }}</b>
            </div>

            @if(collect($r['by_date'] ?? [])->contains(fn($d) => $d['baru'] > 0 || $d['diperbarui'] > 0))
            <div style="margin-top:14px;font-weight:600;color:#2563eb;">Ringkasan per Tanggal Shipment:</div>
            <div style="max-height:260px;overflow:auto;margin-top:6px;">
                <table style="font-size:14px;">
                    <thead>
                        <tr>
                            <th>Tanggal Shipment</th>
                            <th>➕ Baru</th>
                            <th>🔄 Diperbarui</th>
                            <th>🟰 Tidak Berubah</th>
                            <th>♻️ Sudah Ada (dilewati)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($r['by_date'] as $d)
                        @if($d['baru'] > 0 || $d['diperbarui'] > 0)
                        <tr>
                            <td>{{ $d['tanggal'] }}</td>
                            <td>{{ number_format($d['baru']) }}</td>
                            <td>{{ number_format($d['diperbarui']) }}</td>
                            <td>{{ number_format($d['tidak_berubah']) }}</td>
                            <td>{{ number_format($d['sudah_ada']) }}</td>
                        </tr>
                        @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            @if(!empty($r['changes']))
            <div style="margin-top:14px;font-weight:600;color:#2563eb;">
                Perubahan pada data yang sudah ada ({{ number_format($r['changes_total']) }} shipment/tujuan):
            </div>
            <div style="max-height:300px;overflow:auto;margin-top:6px;">
                <table style="font-size:14px;">
                    <thead>
                        <tr>
                            <th>No Shipment</th>
                            <th>Tujuan</th>
                            <th>Alasan Diupdate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($r['changes'] as $c)
                        <tr>
                            <td>{{ $c['no_shipment'] }}</td>
                            <td>{{ $c['tujuan'] }}</td>
                            <td style="white-space:normal;">
                                @foreach($c['alasan'] as $a)
                                <div>• {{ \Illuminate\Support\Str::limit($a, 120) }}</div>
                                @endforeach
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($r['changes_total'] > count($r['changes']))
            <div style="font-size:13px;color:#64748b;margin-top:6px;">
                Menampilkan {{ count($r['changes']) }} dari {{ $r['changes_total'] }}.
            </div>
            @endif
            @endif

            @if($r['failed'] > 0)
            <div style="margin-top:14px;font-weight:600;color:#dc2626;">Baris yang gagal:</div>
            <div style="max-height:260px;overflow:auto;margin-top:6px;">
                <table style="font-size:14px;">
                    <thead>
                        <tr>
                            <th>No Shipment</th>
                            <th>Tujuan</th>
                            <th>Alasan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($r['failed_list'] as $f)
                        <tr>
                            <td>{{ $f['no_shipment'] }}</td>
                            <td>{{ $f['tujuan'] }}</td>
                            <td style="white-space:normal;">{{ \Illuminate\Support\Str::limit($f['error'], 160) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($r['failed'] > count($r['failed_list']))
            <div style="font-size:13px;color:#64748b;margin-top:6px;">
                Menampilkan {{ count($r['failed_list']) }} dari {{ $r['failed'] }} baris gagal. Selengkapnya ada di log.
            </div>
            @endif
            @endif

            @if(count($r['unknown_planner']))
            <div style="margin-top:12px;color:#b45309;">
                ⚠️ Kode planner tidak ada di master: <b>{{ implode(', ', $r['unknown_planner']) }}</b>
            </div>
            @endif

            @if(count($r['missing_columns']))
            <div style="margin-top:8px;color:#b45309;">
                ⚠️ Kolom belum ada di tabel (tidak tersimpan): <b>{{ implode(', ', $r['missing_columns']) }}</b>
            </div>
            @endif

            @if(count($r['ignored_headers']))
            <details style="margin-top:8px;color:#64748b;">
                <summary>Header Excel yang diabaikan ({{ count($r['ignored_headers']) }})</summary>
                {{ implode(', ', $r['ignored_headers']) }}
            </details>
            @endif
        </div>
        @endif

        {{-- ================= IMPORT ================= --}}
        <div class="tools-grid">

            <div class="tool-card">
                <h3 class="tool-title">📤 Import Data Logistik</h3>
                <form action="{{ url('/logistik/import') }}" method="POST" enctype="multipart/form-data" id="formImport">
                    @csrf
                    <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
                    <button type="submit" id="btnImport" class="btn-primary-x">Import Excel</button>
                    <label class="chk-lbl">
                        <input type="checkbox" name="update_existing" value="1">
                        Timpa data yang sudah ada
                    </label>
                </form>
                <p class="tool-sub">
                    Default: DO (<b>No DO</b>) yang sudah ada di database dilewati, hanya DO baru yang masuk.
                    Centang "Timpa" hanya kalau memang mau memperbarui data lama.
                </p>
            </div>

            <div class="tool-card">
                <h3 class="tool-title">📥 Import Qty DO, PGI Date, Tonase, Kubikasi</h3>
                <form action="{{ route('logistik.importQtyPgi') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
                    <button type="submit" class="btn-primary-x">Import Data</button>
                </form>
                <p class="tool-sub">
                    Memperbarui Qty DO, PGI Date, Tonase, dan Kubikasi pada data yang sudah ada.
                </p>
            </div>

        </div>

        {{-- ================= AKSI DATA ================= --}}
        <div class="action-card">

            <div class="ag">
                <div class="ag-title">Export</div>
                <div class="ag-row">
                    <a href="#" id="btnExportExcel" class="btn-export" data-mode="">
                        <i class="fa fa-file-excel"></i> Export Data (cepat)
                    </a>
                    <a href="#" id="btnExportLaporan" class="btn-export" data-mode="laporan">
                        <i class="fa fa-chart-column"></i> Laporan + Dashboard + Slicer
                    </a>
                </div>
            </div>

            <div class="ag">
                <div class="ag-title">Arsip</div>
                <div class="ag-row">
                    <form action="{{ url('/logistik/archive-all') }}"
                        method="POST"
                        onsubmit="return confirm('Pindahkan semua data ke Storage?')"
                        class="archive-form">
                        @csrf
                        <button type="submit" class="archive-btn">🗄 Archive All</button>
                    </form>

                    <form action="{{ route('logistik.archivePeriode') }}" method="POST" class="archive-form ag-row"
                        onsubmit="return confirm('Pindahkan semua data periode ' + this.periode.value + ' ke Storage?')">
                        @csrf
                        <select name="periode" required class="ag-select">
                            <option value="">-- Pilih Periode Shipment --</option>
                            @foreach($periodeList as $p)
                            <option value="{{ $p }}">{{ $p }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="archive-btn">🗄 Archive per Periode</button>
                    </form>
                </div>
            </div>

            <div class="ag">
                <div class="ag-title">Pencocokan</div>
                <div class="ag-row">
                    @include('cocoklogi')
                </div>
            </div>

            <div class="ag ag-danger">
                <div class="ag-title">Zona Bahaya</div>
                <div class="ag-row">
                    <a href="#" id="btnDeleteFiltered" class="btn-trash">
                        <i class="fa fa-trash"></i> Hapus Sesuai Filter / Pencarian
                    </a>
                </div>
            </div>

        </div>

        {{-- ================= FILTER ================= --}}
        <div class="filter-box">
            <form id="filterForm" onsubmit="return false;">

                <div class="ms-dd" id="filterArea" data-default="Semua Area">
                    <button type="button" class="ms-dd-btn">
                        <span class="ms-label">Semua Area</span>
                        <span>▾</span>
                    </button>
                    <div class="ms-dd-panel" style="display:none;">
                        <input type="text" class="ms-search" placeholder="Cari area..." autocomplete="off">
                        <div class="ms-dd-actions">
                            <a href="#" class="ms-all">Pilih semua</a>
                            <a href="#" class="ms-clear">Hapus</a>
                        </div>
                        <div class="ms-dd-list">
                            @foreach($areaList as $a)
                            <label class="ms-dd-item">
                                <input type="checkbox" class="ms-chk" name="area[]" value="{{ $a }}">
                                <span>{{ $a }}</span>
                            </label>
                            @endforeach
                            <div class="ms-empty">Tidak ditemukan</div>
                        </div>
                    </div>
                </div>

                <select id="filterPic" name="planner">
                    <option value="">Semua PIC</option>
                    @foreach($picList as $p)
                    <option value="{{ $p }}">{{ $p }}</option>
                    @endforeach
                </select>

                <select id="filterTglBasis" name="tgl_basis">
                    <option value="tanggal_shipment" selected>Tanggal Shipment</option>
                    <option value="tanggal_naik_logistik">Tanggal Naik Logistik</option>
                    <option value="rencana_kirim">Rencana Kirim</option>
                </select>

                <select id="filterPeriode" name="periode">
                    <option value="">Semua Periode Shipment</option>
                    @foreach($periodeList as $p)
                    <option value="{{ $p }}">{{ $p }}</option>
                    @endforeach
                </select>

                <label class="tgl-lbl">Dari
                    <input type="date" id="filterDateFrom" name="date_from">
                </label>
                <label class="tgl-lbl">Sampai
                    <input type="date" id="filterDateTo" name="date_to">
                </label>

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

                <a href="#" id="btnResetFilter">Reset</a>
            </form>
        </div>

        {{-- ================= TABEL ================= --}}
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
                        <th>Kota</th>
                        <th>Ketersediaan Unit</th>
                        <th>Mobil</th>
                        <th>Delivery Quantity</th>
                        <th>Nilai Muatan</th>
                        <th>Biaya Kirim</th>
                        <th>CR</th>
                        <th>CR Shipment</th>
                        <th>Kubikasi</th>
                        <th>Tonase</th>
                        <th>Total Kubik</th>
                        <th>Total Tonase</th>
                        <th>Hasil Kubik</th>
                        <th>Hasil Tonase</th>
                        <th>Pengiriman Optimal Shipment</th>
                        <th>Reason Tidak Optimal Per Shipment</th>
                        <th>Pengiriman Optimal Customer</th>

                        <th>Kategori Ekspedisi</th>
                        <th>Ekspedisi</th>
                        <th>Tanggal Dapat Unit</th>
                        <th>Lama Waktu Pencarian</th>
                        <th>SLA Dapat Mobil</th>

                        <th>Planning Loading KACS</th>
                        <th>Tanggal Tiba KACS</th>
                        <th>Waktu Tiba KACS</th>
                        <th>Tanggal Keluar KACS</th>
                        <th>Waktu Keluar KACS</th>
                        <th>Reason Gudang KACS</th>
                        <th>Lama Di KACS</th>
                        <th>Status KACS</th>
                        <th>SLA Loading KACS</th>

                        <th>Planning Loading Sentul</th>
                        <th>Tanggal Tiba Sentul</th>
                        <th>Waktu Tiba Sentul</th>
                        <th>Tanggal Keluar Sentul</th>
                        <th>Waktu Keluar Sentul</th>
                        <th>Reason Gudang Sentul</th>
                        <th>Lama Di Sentul</th>
                        <th>SLA Loading Sentul</th>
                        <th>Status Sentul</th>

                        <th>Planning Loading CCIE</th>
                        <th>Tanggal Tiba CCIE</th>
                        <th>Waktu Tiba CCIE</th>
                        <th>Tanggal Keluar CCIE</th>
                        <th>Waktu Keluar CCIE</th>
                        <th>Reason Gudang CCIE</th>
                        <th>Lama Di CCIE</th>
                        <th>SLA Loading CCIE</th>
                        <th>Status CCIE</th>

                        <th>Planning Loading P5</th>
                        <th>Tanggal Tiba P5</th>
                        <th>Waktu Tiba P5</th>
                        <th>Tanggal Keluar P5</th>
                        <th>Waktu Keluar P5</th>
                        <th>Reason Gudang P5</th>
                        <th>Lama Di P5</th>
                        <th>SLA Loading P5</th>
                        <th>Status P5</th>

                        <th>Planning Loading Vivatex</th>
                        <th>Tanggal Tiba Vivatex</th>
                        <th>Waktu Tiba Vivatex</th>
                        <th>Tanggal Keluar Vivatex</th>
                        <th>Waktu Keluar Vivatex</th>
                        <th>Reason Gudang Vivatex</th>
                        <th>Lama Di Vivatex</th>
                        <th>SLA Loading Vivatex</th>
                        <th>Status Vivatex</th>

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
                        <th>Selisih Quantity</th>
                        <th>Reason Selisih Quantity</th>
                        <th>Act PGI Date</th>
                        <th>ATD</th>
                        <th>ATA</th>
                        <th>Tanggal Estimasi</th>
                        <th>Tanggal Tiba</th>
                        <th>Waktu Tiba</th>
                        <th>Lama Perjalanan</th>
                        <th>SLA Tiba</th>
                        <th>Tanggal Bongkar</th>
                        <th>Waktu Bongkar</th>
                        <th>Status Bongkar</th>
                        <th>Overstay</th>
                        <th>SLA Bongkar</th>
                        <th>Reason Tiba</th>
                        <th>Reason Bongkar</th>
                        <th>Status Akhir</th>
                        <th>Status Alert</th>
                        <th>Remarks</th>
                        <th>Nama Driver</th>
                        <th>No Pol</th>
                        <th>Route</th>
                        <th>Shipping Point</th>
                        <th>Pulau</th>
                        <th>Via Kirim</th>
                        <th>Estimasi Tiba Di Customer</th>
                        <th>Ontime/Delay Admin</th>
                        <th>Create On</th>
                        <th>No Delivery</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- Kosong: seluruh baris dimuat via AJAX oleh DataTables serverSide --}}
                </tbody>
            </table>
        </div>

    </div>

    <script>
        $(document).ready(function() {

            // ---------- setup umum ----------
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            $('#formImport').on('submit', function() {
                $('#btnImport').prop('disabled', true).text('⏳ Mengimpor... jangan tutup halaman');
            });

            // filter yang dibawa dari dashboard lewat URL
            let qs = new URLSearchParams(window.location.search);

            // ---------- dropdown multi-select AREA ----------
            function getAreas() {
                return $('#filterArea .ms-chk:checked').map(function() {
                    return this.value;
                }).get();
            }

            function updateLabel(dd) {
                var vals = getAreas();
                var label = dd.attr('data-default');
                if (vals.length === 1 || vals.length === 2) label = vals.join(', ');
                else if (vals.length > 2) label = vals.length + ' dipilih';
                dd.find('.ms-label').text(label);
                dd.toggleClass('has-value', vals.length > 0);
            }

            var reloadTimer;

            function reloadDebounced() {
                clearTimeout(reloadTimer);
                reloadTimer = setTimeout(function() {
                    table.ajax.reload();
                }, 400);
            }

            (function initAreaDropdown() {
                var dd = $('#filterArea');
                var panel = dd.find('.ms-dd-panel');
                var search = dd.find('.ms-search');
                var empty = dd.find('.ms-empty');

                dd.find('.ms-dd-btn').on('click', function(e) {
                    e.stopPropagation();
                    var willOpen = panel.is(':hidden');
                    panel.toggle(willOpen);
                    if (willOpen) search.trigger('focus');
                });

                panel.on('click', function(e) {
                    e.stopPropagation();
                });
                $(document).on('click', function() {
                    panel.hide();
                });

                search.on('input', function() {
                    var q = this.value.toLowerCase().trim(),
                        shown = 0;
                    dd.find('.ms-dd-item').each(function() {
                        var ok = $(this).text().toLowerCase().indexOf(q) !== -1;
                        $(this).toggle(ok);
                        if (ok) shown++;
                    });
                    empty.toggle(shown === 0);
                });

                dd.find('.ms-chk').on('change', function() {
                    updateLabel(dd);
                    reloadDebounced();
                });

                // pilih semua: hanya yang tampil sesuai hasil search
                dd.find('.ms-all').on('click', function(e) {
                    e.preventDefault();
                    dd.find('.ms-dd-item:visible .ms-chk').prop('checked', true);
                    updateLabel(dd);
                    reloadDebounced();
                });

                dd.find('.ms-clear').on('click', function(e) {
                    e.preventDefault();
                    dd.find('.ms-chk').prop('checked', false);
                    updateLabel(dd);
                    reloadDebounced();
                });
            })();

            // ---------- isi filter dari URL dashboard (kalau ada) ----------
            (function prefillFromUrl() {
                var urlAreas = qs.getAll('area[]').concat(qs.getAll('area')).filter(Boolean);
                if (urlAreas.length) {
                    $('#filterArea .ms-chk').each(function() {
                        if (urlAreas.indexOf(this.value) !== -1) this.checked = true;
                    });
                    updateLabel($('#filterArea'));
                }
                if (qs.get('tgl_basis') && $('#filterTglBasis option[value="' + qs.get('tgl_basis') + '"]').length) {
                    $('#filterTglBasis').val(qs.get('tgl_basis'));
                }
                if (qs.get('date_from')) $('#filterDateFrom').val(qs.get('date_from'));
                if (qs.get('date_to')) $('#filterDateTo').val(qs.get('date_to'));
                if (qs.get('year') && $('#filterYear option[value="' + qs.get('year') + '"]').length) {
                    $('#filterYear').val(qs.get('year'));
                }
            })();

            // ---------- kumpulan semua filter (dropdown halaman + URL dashboard) ----------
            function getFilters() {
                return {
                    area: getAreas(),
                    tgl_basis: $('#filterTglBasis').val() || '',
                    periode: $('#filterPeriode').val() || '',
                    date_from: $('#filterDateFrom').val() || '',
                    date_to: $('#filterDateTo').val() || '',
                    date: qs.get('date') || '',
                    month: $('#filterMonth').val() || qs.get('month') || '',
                    year: $('#filterYear').val() || qs.get('year') || '',
                    planner: $('#filterPic').val() || '',
                    dist_channel: qs.get('dist_channel') || '',
                    pulau: qs.get('pulau') || ''
                };
            }

            // month dari dashboard bisa "2026-10"; deleteFiltered butuh month & year terpisah
            function filtersForDelete() {
                const f = getFilters();
                const m = String(f.month).match(/^(\d{4})-(\d{2})$/);
                if (m) {
                    f.year = f.year || m[1];
                    f.month = String(parseInt(m[2], 10));
                }
                return f;
            }

            // ---------- kolom gudang 4 & 5 (P5, Vivatex) ----------
            function gudangCols(s, n) {
                return [{
                        data: 'planning_loading' + s,
                        name: 'planning_loading' + s
                    },
                    {
                        data: 'tanggal_tiba_gudang' + s,
                        name: 'tanggal_tiba_gudang' + s
                    },
                    {
                        data: 'waktu_tiba_gudang' + s + '_fmt',
                        name: 'waktu_tiba_gudang' + s
                    },
                    {
                        data: 'tanggal_keluar_gudang' + s,
                        name: 'tanggal_keluar_gudang' + s
                    },
                    {
                        data: 'waktu_keluar_gudang' + s + '_fmt',
                        name: 'waktu_keluar_gudang' + s
                    },
                    {
                        data: 'reason_gudang' + s,
                        name: 'reason_gudang' + s,
                        defaultContent: '-'
                    },
                    {
                        data: 'lama_digudang' + s,
                        name: 'lama_digudang' + s,
                        defaultContent: '-'
                    },
                    {
                        data: 'sla_loading' + n + '_badge',
                        name: 'sla_loading' + s,
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'status_gudang' + n + '_badge',
                        name: 'status_gudang' + s,
                        orderable: false,
                        searchable: false
                    }
                ];
            }

            // ---------- DataTable ----------
            let table = $('#tableLogistik').DataTable({
                processing: true,
                serverSide: true,
                deferRender: true,
                scrollX: true,
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                order: [
                    [4, 'asc']
                ], // kolom ke-5 = no_shipment
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
                    url: "{{ route('logistik.ajax') }}",
                    type: 'POST', // GET bikin querystring kolom kepanjangan -> 414
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: function(d) {
                        $.extend(d, getFilters()); // area dikirim sebagai area[]=...
                    },
                    error: function(xhr) {
                        console.error('Gagal memuat data logistik:', xhr.status, xhr.responseText);
                    }
                },

                columns: [{
                        data: 'tanggal_naik_logistik_fmt',
                        name: 'tanggal_naik_logistik'
                    },
                    {
                        data: 'rencana_kirim_fmt',
                        name: 'rencana_kirim'
                    },
                    {
                        data: 'transport_lead_time',
                        name: 'transport_lead_time',
                        className: 'col-small'
                    },

                    {
                        data: 'planner',
                        name: 'planner'
                    },
                    {
                        data: 'no_shipment',
                        name: 'no_shipment'
                    },
                    {
                        data: 'status_pengiriman_badge',
                        name: 'status_pengiriman',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'dist_channel_badge',
                        name: 'dist_channel'
                    },
                    {
                        data: 'tujuan_html',
                        name: 'tujuan'
                    },
                    {
                        data: 'area',
                        name: 'area'
                    },
                    {
                        data: 'kota',
                        name: 'kota',
                        defaultContent: '-'
                    },
                    {
                        data: 'ketersediaan_unit_badge',
                        name: 'ketersediaan_unit',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'mobil',
                        name: 'mobil'
                    },
                    {
                        data: 'total_do_qty_car',
                        name: 'total_do_qty_car'
                    },
                    {
                        data: 'nilai_muatan_fmt',
                        name: 'nilai_muatan'
                    },
                    {
                        data: 'biaya_kirim_fmt',
                        name: 'biaya_kirim'
                    },
                    {
                        data: 'cr_fmt',
                        name: 'cr',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'cr_shipment_fmt',
                        name: 'cr_shipment',
                        searchable: false
                    },
                    {
                        data: 'kubikasi_fmt',
                        name: 'kubikasi',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'tonase_fmt',
                        name: 'tonase',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'total_kubik_fmt',
                        name: 'total_kubik',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'total_tonase_fmt',
                        name: 'total_tonase',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'hasil_kubik_fmt',
                        name: 'hasil_kubik',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'hasil_tonase_fmt',
                        name: 'hasil_tonase',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'pengiriman_optimal_badge',
                        name: 'pengiriman_optimal',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'reason_optimal',
                        name: 'reason_optimal',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'pengiriman_optimal_customer_badge',
                        name: 'pengiriman_optimal_customer',
                        orderable: false,
                        searchable: false
                    },

                    {
                        data: 'kategori_ekspedisi_badge',
                        name: 'kategori_ekspedisi'
                    },
                    {
                        data: 'ekpedisi',
                        name: 'ekpedisi'
                    },
                    {
                        data: 'tanggal_dpt_unit_fmt',
                        name: 'tanggal_dpt_unit'
                    },
                    {
                        data: 'lama_waktu_pencarian',
                        name: 'lama_waktu_pencarian',
                        orderable: false,
                        defaultContent: '-'
                    },
                    {
                        data: 'sla_dapat_mobil_badge',
                        name: 'sla_dapat_mobil'
                    },

                    // GUDANG 1 - KACS (9 kolom)
                    {
                        data: 'planning_loading_fmt',
                        name: 'planning_loading'
                    },
                    {
                        data: 'tanggal_tiba_gudang_fmt',
                        name: 'tanggal_tiba_gudang'
                    },
                    {
                        data: 'waktu_tiba_gudang_fmt',
                        name: 'waktu_tiba_gudang'
                    },
                    {
                        data: 'tanggal_keluar_gudang_fmt',
                        name: 'tanggal_keluar_gudang'
                    },
                    {
                        data: 'waktu_keluar_gudang_fmt',
                        name: 'waktu_keluar_gudang'
                    },
                    {
                        data: 'reason_gudang',
                        name: 'reason_gudang',
                        defaultContent: '-'
                    },
                    {
                        data: 'durasi_gudang1_fmt',
                        name: 'durasi_gudang1',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'status_gudang1_badge',
                        name: 'status_gudang1',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'sla_loading1_badge',
                        name: 'sla_loading1',
                        orderable: false,
                        searchable: false
                    },

                    // GUDANG 2 - SENTUL (9 kolom)
                    {
                        data: 'planning_loading_2',
                        name: 'planning_loading_2'
                    },
                    {
                        data: 'tanggal_tiba_gudang_2',
                        name: 'tanggal_tiba_gudang_2'
                    },
                    {
                        data: 'waktu_tiba_gudang_2_fmt',
                        name: 'waktu_tiba_gudang_2'
                    },
                    {
                        data: 'tanggal_keluar_gudang_2',
                        name: 'tanggal_keluar_gudang_2'
                    },
                    {
                        data: 'waktu_keluar_gudang_2_fmt',
                        name: 'waktu_keluar_gudang_2'
                    },
                    {
                        data: 'reason_gudang_2',
                        name: 'reason_gudang_2',
                        defaultContent: '-'
                    },
                    {
                        data: 'lama_digudang_2',
                        name: 'lama_digudang_2',
                        defaultContent: '-'
                    },
                    {
                        data: 'sla_loading2_badge',
                        name: 'sla_loading_2',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'status_gudang2_badge',
                        name: 'status_gudang_2',
                        orderable: false,
                        searchable: false
                    },

                    // GUDANG 3 - CCIE (9 kolom)
                    {
                        data: 'planning_loading_3',
                        name: 'planning_loading_3'
                    },
                    {
                        data: 'tanggal_tiba_gudang_3',
                        name: 'tanggal_tiba_gudang_3'
                    },
                    {
                        data: 'waktu_tiba_gudang_3_fmt',
                        name: 'waktu_tiba_gudang_3'
                    },
                    {
                        data: 'tanggal_keluar_gudang_3',
                        name: 'tanggal_keluar_gudang_3'
                    },
                    {
                        data: 'waktu_keluar_gudang_3_fmt',
                        name: 'waktu_keluar_gudang_3'
                    },
                    {
                        data: 'reason_gudang_3',
                        name: 'reason_gudang_3',
                        defaultContent: '-'
                    },
                    {
                        data: 'lama_digudang_3',
                        name: 'lama_digudang_3',
                        defaultContent: '-'
                    },
                    {
                        data: 'sla_loading3_badge',
                        name: 'sla_loading_3',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'status_gudang3_badge',
                        name: 'status_gudang_3',
                        orderable: false,
                        searchable: false
                    },

                    // GUDANG 4 - P5 & GUDANG 5 - VIVATEX (masing-masing 9 kolom)
                    ...gudangCols('_4', 4),
                    ...gudangCols('_5', 5),

                    {
                        data: 'pic_monitoring',
                        name: 'pic_monitoring'
                    },
                    {
                        data: 'nama_kapal',
                        name: 'nama_kapal'
                    },
                    {
                        data: 'etd',
                        name: 'etd'
                    },
                    {
                        data: 'eta',
                        name: 'eta'
                    },
                    {
                        data: 'status_kendaraan_badge',
                        name: 'status_kendaraan',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'alert_badge',
                        name: 'alert',
                        orderable: false,
                        searchable: false
                    },

                    {
                        data: 'act_urutan_bongkar',
                        name: 'act_urutan_bongkar',
                        className: 'col-small'
                    },
                    {
                        data: 'qty_monitoring',
                        name: 'qty_monitoring'
                    },
                    {
                        data: 'biaya_kuli',
                        name: 'biaya_kuli'
                    },
                    {
                        data: 'total_biaya_kuli',
                        name: 'total_biaya_kuli'
                    },
                    {
                        data: 'selisih_qty',
                        name: 'selisih_qty'
                    },
                    {
                        data: 'remarks_qty',
                        name: 'remarks_qty'
                    },
                    {
                        data: 'act_pgi_date',
                        name: 'act_pgi_date'
                    },

                    {
                        data: 'atd',
                        name: 'atd'
                    },
                    {
                        data: 'ata',
                        name: 'ata'
                    },
                    {
                        data: 'estimasi_tiba_fmt',
                        name: 'estimasi_tiba',
                        orderable: false
                    },
                    {
                        data: 'tanggal_tiba_fmt',
                        name: 'tanggal_tiba'
                    },
                    {
                        data: 'waktu_tiba_fmt',
                        name: 'waktu_tiba',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'lama_perjalanan',
                        name: 'lama_perjalanan',
                        defaultContent: '-'
                    },
                    {
                        data: 'sla_tiba_badge',
                        name: 'sla_tiba'
                    },
                    {
                        data: 'tanggal_bongkar_fmt',
                        name: 'tanggal_bongkar'
                    },
                    {
                        data: 'waktu_bongkar_fmt',
                        name: 'waktu_bongkar',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'status_bongkar_badge',
                        name: 'status_bongkar',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'overstay_days',
                        name: 'overstay_days'
                    },
                    {
                        data: 'sla_bongkar_badge',
                        name: 'sla_bongkar'
                    },
                    {
                        data: 'reason_tiba',
                        name: 'reason_tiba'
                    },
                    {
                        data: 'reason_bongkar',
                        name: 'reason_bongkar'
                    },
                    {
                        data: 'status_akhir_badge',
                        name: 'status_akhir',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'status_alert_badge',
                        name: 'status_alert',
                        orderable: false,
                        searchable: false
                    },

                    {
                        data: 'remarks',
                        name: 'remarks'
                    },
                    {
                        data: 'nama_driver',
                        name: 'nama_driver'
                    },
                    {
                        data: 'no_pol',
                        name: 'no_pol'
                    },
                    {
                        data: 'route',
                        name: 'route'
                    },
                    {
                        data: 'route_awal',
                        name: 'route_awal',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'pulau',
                        name: 'pulau'
                    },
                    {
                        data: 'via_kirim',
                        name: 'via_kirim'
                    },
                    {
                        data: 'estimasi_admin_fmt',
                        name: 'estimasi_admin',
                        orderable: false
                    },
                    {
                        data: 'estimasi_admin_status_badge',
                        name: 'estimasi_admin_status',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'create_on_fmt',
                        name: 'create_on'
                    },
                    {
                        data: 'no_delivery',
                        name: 'no_delivery',
                        defaultContent: '-'
                    }
                ]
            });

            // ---------- rincian DO (klik link Tujuan) ----------
            function esc(s) {
                return String(s ?? '-').replace(/[&<>"']/g, c => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                } [c]));
            }

            function buildDetail(rows) {
                if (!rows.length) return '<div style="padding:10px">Tidak ada rincian.</div>';

                var total = 0;
                var h = '<table style="margin:8px 0 8px 40px;width:auto;background:#fff">' +
                    '<thead><tr>' +
                    '<th>Sales Order</th><th>Customer Reference</th><th>PO Expired</th><th>NO DO</th>' +
                    '<th>Qty Barang</th><th>Nilai Muatan</th><th>Kirim Kontribusi</th><th>Total Kubik</th><th>Total Tonase</th>' +
                    '</tr></thead><tbody>';

                rows.forEach(function(r) {
                    total += Number(r.kirim_kontribusi_raw) || 0;
                    h += '<tr><td>' + esc(r.sales_document) + '</td><td>' + esc(r.customer_reference) +
                        '</td><td>' + esc(r.contract_end_date) + '</td><td>' + esc(r.no_do) +
                        '</td><td>' + esc(r.qty) + '</td><td>' + esc(r.nilai_muatan) +
                        '</td><td>' + esc(r.kirim_kontribusi) +
                        '</td><td>' + esc(r.total_kubik) + '</td><td>' + esc(r.total_tonase) + '</td></tr>';
                });

                h += '<tr style="font-weight:700;background:#f1f5f9">' +
                    '<td colspan="6" style="text-align:right">Total Kirim Kontribusi</td>' +
                    '<td>Rp ' + Math.round(total).toLocaleString('id-ID') + '</td>' +
                    '<td colspan="2"></td></tr>';

                return h + '</tbody></table>';
            }

            $('#tableLogistik tbody').on('click', '.btn-tujuan', function(e) {
                e.preventDefault();
                var $a = $(this),
                    tr = $a.closest('tr'),
                    row = table.row(tr);

                if (row.child.isShown()) {
                    row.child.hide();
                    $a.html($a.html().replace('▼', '▶'));
                    return;
                }

                row.child('<div style="padding:10px">Memuat rincian...</div>').show();
                $a.html($a.html().replace('▶', '▼'));

                $.get("{{ route('logistik.detail') }}", {
                    no_shipment: $a.data('shipment'),
                    tujuan: $a.data('tujuan')
                }).done(function(res) {
                    row.child(buildDetail(res.data)).show();
                }).fail(function() {
                    row.child('<div style="padding:10px;color:#dc2626">Gagal memuat rincian.</div>').show();
                });
            });

            // ---------- filter -> reload ajax ----------
            $('#filterPic, #filterTglBasis, #filterPeriode, #filterDateFrom, #filterDateTo, #filterMonth, #filterYear').on('change', function() {
                table.ajax.reload();
            });

            $('#btnResetFilter').on('click', function(e) {
                e.preventDefault();
                $('#filterForm')[0].reset(); // basis tanggal balik ke default
                $('#filterArea .ms-chk').prop('checked', false);
                $('#filterArea .ms-search').val('').trigger('input');
                updateLabel($('#filterArea'));

                // buang juga filter bawaan dari URL dashboard
                qs = new URLSearchParams('');
                if (window.history && history.replaceState) {
                    history.replaceState(null, '', window.location.pathname);
                }
                table.ajax.reload();
            });

            // ---------- hapus sesuai filter ----------
            $('#btnDeleteFiltered').on('click', function(e) {
                e.preventDefault();

                const jumlah = table.page.info().recordsDisplay;
                if (jumlah === 0) {
                    alert('Tidak ada data untuk dihapus.');
                    return;
                }

                const konfirmasi = prompt(
                    'Anda akan MENGHAPUS PERMANEN ' + jumlah + ' data sesuai filter/pencarian yang sedang aktif.\n\n' +
                    'Ketik "HAPUS" (huruf besar) untuk melanjutkan:'
                );
                if (konfirmasi !== 'HAPUS') {
                    alert('Dibatalkan.');
                    return;
                }

                $.ajax({
                    url: "{{ route('spvplanner.deleteFiltered') }}",
                    type: 'POST',
                    data: $.extend({
                        _token: "{{ csrf_token() }}",
                        search_value: table.search()
                    }, filtersForDelete()),
                    success: function(res) {
                        alert(res.message);
                        if (res.success) table.ajax.reload(null, false);
                    },
                    error: function(xhr) {
                        alert((xhr.responseJSON && xhr.responseJSON.message) || 'Gagal menghapus data.');
                    }
                });
            });

            // ---------- export sesuai filter aktif ----------
            // #btnExportExcel = cepat, #btnExportLaporan = dashboard + slicer
            $('#btnExportExcel, #btnExportLaporan').on('click', function(e) {
                e.preventDefault();

                let params = new URLSearchParams();
                const f = getFilters();

                Object.keys(f).forEach(function(k) {
                    if (Array.isArray(f[k])) {
                        f[k].forEach(v => params.append(k + '[]', v));
                    } else if (f[k]) {
                        params.append(k === 'periode' ? 'periode[]' : k, f[k]);
                    }
                });

                const q = table.search();
                if (q) {
                    params.append('search[value]', q);
                    params.append('search_value', q);
                }

                const baseUrl = "{{ route('logistik.export') }}";
                const mode = $(this).data('mode');
                const $btn = $(this);

                function download(m) {
                    let p = new URLSearchParams(params);
                    if (m) p.append('mode', m);
                    window.location.href = baseUrl + '?' + p.toString();
                }

                if (mode !== 'laporan') {
                    return download('');
                }

                // laporan: cek jumlah baris dulu
                let cek = new URLSearchParams(params);
                cek.append('mode', 'cek');
                $btn.css({
                    pointerEvents: 'none',
                    opacity: .6
                });

                $.getJSON(baseUrl + '?' + cek.toString())
                    .done(function(r) {
                        if (r.rows > r.max) {
                            if (confirm('Data ' + r.rows.toLocaleString('id-ID') + ' baris melebihi batas laporan (' +
                                    r.max.toLocaleString('id-ID') + ').\nDownload Export Data (cepat) saja?')) {
                                download('');
                            }
                            $btn.css({
                                pointerEvents: '',
                                opacity: ''
                            });
                        } else {
                            download('laporan');
                            alert('Laporan sedang dibuat di server. Download akan muncul otomatis, mohon tunggu beberapa menit dan jangan klik lagi.');
                            setTimeout(() => $btn.css({
                                pointerEvents: '',
                                opacity: ''
                            }), 120000);
                        }
                    })
                    .fail(function() {
                        alert('Gagal mengecek jumlah data.');
                        $btn.css({
                            pointerEvents: '',
                            opacity: ''
                        });
                    });
            });
        });
    </script>

</body>

</html>