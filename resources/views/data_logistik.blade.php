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

        .col-small {
            width: 110px !important;
            min-width: 110px !important;
            max-width: 130px !important;
            font-size: 15px;
            text-align: center;
        }

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

            .filter-box form {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-box input,
            .filter-box select,
            .filter-box button {
                width: 100%;
            }
        }

        /* ===== BADGES ===== */
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

        .badge-purple {
            background: #8b5cf6 !important;
        }

        .badge-pink {
            background: #ec4899 !important;
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

        .status-sudah {
            background: #22c55e;
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
        }
    </style>
</head>

<body>

    @include('template.sidebar')

    <div class="container">

        <h2>📦 DATA LOGISTIK</h2>

        <a href="#" id="btnExportExcel" class="btn-export">
            <i class="fa fa-file-excel"></i> Export Excel
        </a>

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

             

                <a href="#" id="btnResetFilter">Reset</a>
            </form>
        </div>

        <div class="card">
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
                        <th>SLA Loading KACS</th>
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
                        <th>Estimasi Tiba Di Customer</th>
                        <th>Ontime/Delay Admin</th>
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

            let table = $('#tableLogistik').DataTable({
                processing: true,
                serverSide: true,
                deferRender: true, // hanya render row yg tampil di halaman ini
                scrollX: true,
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                order: [
                    [0, 'desc']
                ],
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
                    type: 'POST', // GET bikin querystring ~70 kolom kelewat panjang -> 414 Request-URI Too Large
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: function(d) {
                        d.area = $('#filterArea').val();
                        d.date = $('#filterDate').val();
                        d.month = $('#filterMonth').val();
                        d.year = $('#filterYear').val();
                        d.pic_monitoring = $('#filterPic').val();
                    }
                },

                columns: [
                    { data: 'tanggal_naik_logistik_fmt', name: 'tanggal_naik_logistik' },
                    { data: 'rencana_kirim_fmt', name: 'rencana_kirim' },
                    { data: 'transport_lead_time', name: 'transport_lead_time', className: 'col-small' },
                    { data: 'nama_driver', name: 'nama_driver' },
                    { data: 'no_pol', name: 'no_pol' },
                    { data: 'planner', name: 'planner' },
                    { data: 'no_shipment', name: 'no_shipment' },
                    { data: 'status_pengiriman_badge', name: 'status_pengiriman', orderable: false, searchable: false },
                    { data: 'dist_channel_badge', name: 'dist_channel' },
                    { data: 'tujuan', name: 'tujuan' },
                    { data: 'area', name: 'area' },
                    { data: 'ketersediaan_unit_badge', name: 'ketersediaan_unit', orderable: false, searchable: false },
                    { data: 'mobil', name: 'mobil' },
                    { data: 'total_do_qty_car', name: 'total_do_qty_car' },
                    { data: 'nilai_muatan_fmt', name: 'nilai_muatan' },
                    { data: 'biaya_kirim_fmt', name: 'biaya_kirim' },
                    { data: 'cr_fmt', name: 'cr', orderable: false, searchable: false },
                    { data: 'kategori_ekspedisi_badge', name: 'kategori_ekspedisi' },
                    { data: 'ekpedisi', name: 'ekpedisi' },
                    { data: 'tanggal_dpt_unit_fmt', name: 'tanggal_dpt_unit' },
                    { data: 'lama_waktu_pencarian', name: 'lama_waktu_pencarian', orderable: false, defaultContent: '-' },
                    { data: 'sla_dapat_mobil_badge', name: 'sla_dapat_mobil' },

                    // GUDANG 1 - KACS
                    { data: 'planning_loading_fmt', name: 'planning_loading' },
                    { data: 'tanggal_tiba_gudang_fmt', name: 'tanggal_tiba_gudang' },
                    { data: 'tanggal_keluar_gudang_fmt', name: 'tanggal_keluar_gudang' },
                    { data: 'durasi_gudang1_fmt', name: 'durasi_gudang1', orderable: false, searchable: false },
                    { data: 'status_gudang1_badge', name: 'status_gudang1', orderable: false, searchable: false },
                    { data: 'sla_loading1_badge', name: 'sla_loading1', orderable: false, searchable: false },

                    // GUDANG 2 - SENTUL
                    { data: 'planning_loading_2', name: 'planning_loading_2' },
                    { data: 'tanggal_tiba_gudang_2', name: 'tanggal_tiba_gudang_2' },
                    { data: 'tanggal_keluar_gudang_2', name: 'tanggal_keluar_gudang_2' },
                    { data: 'lama_digudang_2', name: 'lama_digudang_2', defaultContent: '-' },
                    { data: 'sla_loading2_badge', name: 'sla_loading_2', orderable: false, searchable: false },
                    { data: 'status_gudang2_badge', name: 'status_gudang_2', orderable: false, searchable: false },

                    // GUDANG 3 - CCIE
                    { data: 'planning_loading_3', name: 'planning_loading_3' },
                    { data: 'tanggal_tiba_gudang_3', name: 'tanggal_tiba_gudang_3' },
                    { data: 'tanggal_keluar_gudang_3', name: 'tanggal_keluar_gudang_3' },
                    { data: 'lama_digudang_3', name: 'lama_digudang_3', defaultContent: '-' },
                    { data: 'sla_loading3_badge', name: 'sla_loading_3', orderable: false, searchable: false },
                    { data: 'status_gudang3_badge', name: 'status_gudang_3', orderable: false, searchable: false },

                    { data: 'pic_monitoring', name: 'pic_monitoring' },
                    { data: 'nama_kapal', name: 'nama_kapal' },
                    { data: 'etd', name: 'etd' },
                    { data: 'eta', name: 'eta' },
                    { data: 'status_kendaraan_badge', name: 'status_kendaraan', orderable: false, searchable: false },
                    { data: 'alert_badge', name: 'alert', orderable: false, searchable: false },

                    { data: 'act_urutan_bongkar', name: 'act_urutan_bongkar', className: 'col-small' },
                    { data: 'qty_monitoring', name: 'qty_monitoring' },
                    { data: 'biaya_kuli', name: 'biaya_kuli' },
                    { data: 'total_biaya_kuli', name: 'total_biaya_kuli' },
                    { data: 'selisih_qty', name: 'selisih_qty' },
                    { data: 'remarks_qty', name: 'remarks_qty' },
                    { data: 'create_tgl', name: 'create_tgl' },

                    { data: 'atd', name: 'atd' },
                    { data: 'ata', name: 'ata' },
                    { data: 'estimasi_tiba_fmt', name: 'estimasi_tiba', orderable: false },
                    { data: 'tanggal_tiba_fmt', name: 'tanggal_tiba' },
                    { data: 'lama_perjalanan', name: 'lama_perjalanan', defaultContent: '-' },

                    { data: 'sla_tiba_badge', name: 'sla_tiba' },
                    { data: 'tanggal_bongkar_fmt', name: 'tanggal_bongkar' },
                    { data: 'status_bongkar_badge', name: 'status_bongkar', orderable: false, searchable: false },
                    { data: 'overstay_days', name: 'overstay_days' },
                    { data: 'sla_bongkar_badge', name: 'sla_bongkar' },
                    { data: 'reason_tiba', name: 'reason_tiba' },
                    { data: 'reason_bongkar', name: 'reason_bongkar' },
                    { data: 'status_akhir_badge', name: 'status_akhir', orderable: false, searchable: false },
                    { data: 'status_alert_badge', name: 'status_alert', orderable: false, searchable: false },

                    { data: 'remarks', name: 'remarks' },
                    { data: 'route', name: 'route' },
                    { data: 'route_awal', name: 'route_awal', orderable: false, searchable: false },
                    { data: 'pulau', name: 'pulau' },
                    { data: 'via_kirim', name: 'via_kirim' },
                    { data: 'estimasi_admin_fmt', name: 'estimasi_admin', orderable: false },
                    { data: 'estimasi_admin_status_badge', name: 'estimasi_admin_status', orderable: false, searchable: false },
                ]
            });

            // Reload table setiap filter berubah (server-side, jadi ringan)
            $('#filterArea, #filterDate, #filterMonth, #filterYear, #filterPic').on('change', function() {
                table.ajax.reload();
            });

            $('#btnResetFilter').on('click', function(e) {
                e.preventDefault();
                $('#filterForm')[0].reset();
                table.ajax.reload();
            });

            // ==========================================
            // EXPORT EXCEL SESUAI FILTER AKTIF
            // ==========================================
            $('#btnExportExcel').on('click', function(e) {
                e.preventDefault();

                let params = new URLSearchParams();

                let filterArea = $('#filterArea').val();
                let filterDate = $('#filterDate').val();
                let filterMonth = $('#filterMonth').val();
                let filterYear = $('#filterYear').val();

                if (filterArea) params.append('area', filterArea);
                if (filterDate) params.append('date', filterDate);
                if (filterMonth) params.append('month', filterMonth);
                if (filterYear) params.append('year', filterYear);

                let baseUrl = "{{ route('logistik.export') }}";
                let finalUrl = params.toString() ? (baseUrl + '?' + params.toString()) : baseUrl;

                window.location.href = finalUrl;
            });
        });
    </script>

</body>

</html>