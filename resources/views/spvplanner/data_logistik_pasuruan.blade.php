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
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f1f5f9; margin: 0; color: #1e293b; }
        .container { margin-left: 260px; padding: 30px; }
        h2 { font-size: 32px; font-weight: 700; margin-bottom: 20px; color: #0f172a; }
        .card { background: #fff; padding: 20px; border-radius: 16px; overflow: auto; box-shadow: 0 4px 20px rgba(0,0,0,.08); }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { background: #03c03c; color: #fff; padding: 14px 12px; white-space: nowrap; font-size: 14px; font-weight: 600; text-align: center; border: 1px solid #dbeafe; }
        td { padding: 12px 10px; border: 1px solid #e2e8f0; white-space: nowrap; text-align: left; vertical-align: middle; font-size: 14px; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        tbody tr:hover { background: #dbeafe; transition: .2s; }
        .col-small { width: 110px !important; min-width: 110px !important; max-width: 130px !important; text-align: center; }
        .cr-col, .cr-cell { min-width: 120px !important; max-width: 120px !important; width: 120px !important; text-align: center; }

        /* ===== FILTER ===== */
        .filter-box { background: #fff; padding: 20px; border-radius: 16px; box-shadow: 0 4px 16px rgba(0,0,0,.08); margin-bottom: 20px; }
        .filter-box form { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
        .filter-box input, .filter-box select { padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 14px; min-width: 180px; outline: none; }
        .filter-box input:focus, .filter-box select:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.15); }
        .filter-box button.reset-btn { padding: 12px 20px; background: #ef4444; color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .filter-box button.reset-btn:hover { background: #dc2626; }

        /* ===== IMPORT / ARCHIVE (khusus planner) ===== */
        .import-box { background: #fff; padding: 20px; border-radius: 16px; box-shadow: 0 4px 16px rgba(0,0,0,.08); margin-bottom: 20px; }
        .import-box form { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
        .import-box input[type=file] { padding: 13px; border: 2px dashed #cbd5e1; border-radius: 10px; background: #f8fafc; font-size: 14px; }
        .import-box input[type=file]:hover { border-color: #2563eb; }
        .import-box button { padding: 13px 20px; border: none; border-radius: 10px; background: linear-gradient(135deg,#3b82f6,#2563eb); color: #fff; font-size: 14px; font-weight: 600; cursor: pointer; }
        .import-box button:hover { transform: translateY(-2px); }

        .archive-form { margin: 20px 0; }
        .archive-btn { background: linear-gradient(135deg,#2563eb,#1d4ed8); color: #fff; border: none; padding: 12px 22px; border-radius: 12px; font-size: 14px; font-weight: 600; cursor: pointer; transition: .3s ease; box-shadow: 0 4px 12px rgba(37,99,235,.25); display: inline-flex; align-items: center; gap: 8px; }
        .archive-btn:hover { transform: translateY(-2px); background: linear-gradient(135deg,#1d4ed8,#1e40af); }

        .btn-export { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; margin-bottom: 15px; background: #198754; color: #fff !important; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; transition: all .25s ease; box-shadow: 0 3px 10px rgba(25,135,84,.25); }
        .btn-export:hover { background: #157347; color: #fff !important; transform: translateY(-2px); }

        /* ===== BADGE ===== */
        .badge { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 999px; font-size: 13px; font-weight: 700; color: #fff; letter-spacing: .3px; box-shadow: 0 2px 8px rgba(0,0,0,.15); }
        .badge.green, .badge-green { background: #22c55e; }
        .badge.red, .badge-red { background: #ef4444; }
        .badge.gray, .badge-gray { background: #64748b; }
        .badge.orange, .badge-orange { background: #f97316; }
        .badge.blue, .badge-blue { background: #2563eb; }
        .badge.yellow, .badge-yellow { background: #facc15; color: #000; }
        .badge-purple { background: #8b5cf6; } .badge-pink { background: #ec4899; }
        .badge-cyan { background: #06b6d4; } .badge-default { background: #64748b; }
        .badge-success { background: linear-gradient(135deg,#16a34a,#22c55e); }
        .badge-warning { background: linear-gradient(135deg,#f59e0b,#fbbf24); color: #222; }
        .badge-info { background: linear-gradient(135deg,#2563eb,#3b82f6); }
        .badge.cream, .badge-cream { background: #e8d5b0; color: #5b4636; }
        .badge-danger { background: linear-gradient(135deg,#dc2626,#ef4444); }
        .badge-secondary { background: linear-gradient(135deg,#00e5ff,#9ca3af); }

        .cr-value { color: #0056b3; font-weight: 700; font-size: 14px; }
        .text-muted { color: #94a3b8; }

        .badge-status { display: inline-block; padding: 8px 14px; border-radius: 20px; font-size: 13px; font-weight: 700; color: #fff; }
        .status-belum { background: #ef4444; } .status-sudah { background: #22c55e; }

        .status-badge { display: inline-block; padding: 8px 14px; border-radius: 20px; font-size: 13px; font-weight: 700; text-align: center; line-height: 1.3; min-width: 170px; color: #fff; }
        .status-transit { background: #2563eb; } .status-unloading { background: #f59e0b; }
        .status-ontime { background: linear-gradient(135deg,#16a34a,#22c55e); }
        .status-delay { background: linear-gradient(135deg,#dc2626,#ef4444); }
        .status-bongkar { font-size: 13px; }

        .btn-tujuan { color: #2563eb; font-weight: 600; text-decoration: none; }
        .btn-tujuan:hover { text-decoration: underline; }

        /* ===== DATATABLES ===== */
        .dataTables_wrapper { font-size: 16px !important; }
        .dataTables_filter input { padding: 10px 14px !important; border-radius: 10px !important; min-width: 280px; }

        @media (max-width: 768px) {
            .container { margin-left: 0; padding: 15px; }
            h2 { font-size: 26px; }
            .filter-box form, .import-box form { flex-direction: column; align-items: stretch; }
            .filter-box input, .filter-box select, .filter-box button, .import-box button { width: 100%; }
        }
    </style>
</head>

<body>

    @include('template.sidebar')

    <div class="container">

        <h2>📦 DATA LOGISTIK PASURUAN</h2>

        {{-- ================= IMPORT ================= --}}
        <div class="import-box">
            <form action="{{ route('spvplanner.import.pasuruan') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="file" name="file" required>
                <button type="submit">📤 Import Excel</button>
            </form>
        </div>

        {{-- ================= EXPORT ================= --}}
        <a href="#" id="btnExportExcel" class="btn-export">
            <i class="fa fa-file-excel"></i>
            Export Excel
        </a>

        {{-- ================= ARCHIVE ================= --}}
        <form action="{{ route('spvplanner.archive') }}" method="POST"
              onsubmit="return confirm('Pindahkan semua data ke Storage?')" class="archive-form">
            @csrf
            <button type="submit" class="archive-btn">🗄 Archive All (Move to Storage)</button>
        </form>

        {{-- ================= FILTER ================= --}}
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
                    @php $startYear = 2023; $endYear = date('Y') + 1; @endphp
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
                        <th class="cr-col">CR</th>
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
                        <th>Biaya Kuli</th>
                        <th>Total Biaya Kuli</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- SENGAJA KOSONG: diisi via AJAX oleh DataTables -->
                </tbody>
            </table>
        </div>

    </div>

    <script>
    $(document).ready(function () {

        let table = $('#tableLogistik').DataTable({
            serverSide: true,
            processing: true,
            scrollX: true,
            pageLength: 10,
            autoWidth: false,
            scrollCollapse: true,

            ajax: {
                url: "{{ route('spvplanner.data.pasuruan.ajax') }}",
                type: 'POST',
                data: function (d) {
                    d._token  = "{{ csrf_token() }}";
                    d.area    = $('#filterArea').val();
                    d.planner = $('#filterPlanner').val();
                    d.date    = $('#filterDate').val();
                    d.month   = $('#filterMonth').val();
                    d.year    = $('#filterYear').val();
                }
            },

            // urutan HARUS sama persis dengan <thead> & key dari dataLogistikPasuruanAjax()
            columns: [
                { data: 'tanggal_terima_po_fmt' },
                { data: 'rencana_kirim_fmt' },
                { data: 'transport_lead_time' },
                { data: 'planner' },
                { data: 'no_shipment' },
                { data: 'status_pengiriman_badge' },
                { data: 'dist_channel_badge' },
                { data: 'tujuan_html' },
                { data: 'area' },
                { data: 'ketersediaan_unit_badge' },
                { data: 'mobil' },
                { data: 'total_do' },
                { data: 'nilai_muatan_fmt' },
                { data: 'biaya_kirim_fmt' },
                { data: 'cr_fmt', className: 'cr-cell' },
                { data: 'kategori_ekspedisi_badge' },
                { data: 'ekspedisi' },
                { data: 'tanggal_dpt_unit_fmt' },
                { data: 'lama_waktu_pencarian' },
                { data: 'sla_dapat_mobil_badge' },
                { data: 'planning_loading_fmt' },
                { data: 'tanggal_tiba_gudang_fmt' },
                { data: 'tanggal_keluar_gudang_fmt' },
                { data: 'pic_monitoring' },
                { data: 'nama_kapal' },
                { data: 'etd' },
                { data: 'eta' },
                { data: 'alert_badge' },
                { data: 'act_urutan_bongkar' },
                { data: 'actual_delivery_quantity' },
                { data: 'selisih_qty_badge' },
                { data: 'reason_selisih_quantity' },
                { data: 'act_pgi_date_fmt' },
                { data: 'atd_fmt' },
                { data: 'ata_fmt' },
                { data: 'estimasi_tiba_fmt' },
                { data: 'tanggal_tiba_fmt' },
                { data: 'lama_perjalanan' },
                { data: 'sla_tiba_badge' },
                { data: 'tanggal_bongkar_fmt' },
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
                { data: 'biaya_kuli' },
                { data: 'total_biaya_kuli' }
            ]
        });

        // ==========================================
        // RINCIAN DO (klik tujuan)
        // ==========================================
        function esc(s) {
            return String(s ?? '-').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        }

        function buildDetail(rows) {
            if (!rows.length) return '<div style="padding:10px">Tidak ada rincian.</div>';

            var total = 0;
            var h = '<table style="margin:8px 0 8px 40px;width:auto;background:#fff">'
                  + '<thead><tr>'
                  + '<th>Sales Document</th><th>Customer Reference</th><th>Contract End Date</th>'
                  + '<th>No Delivery</th><th>Qty</th><th>Nilai Muatan</th><th>Kirim Kontribusi</th>'
                  + '<th>Total Kubik</th><th>Total Tonase</th>'
                  + '</tr></thead><tbody>';

            rows.forEach(function (r) {
                total += Number(r.kirim_kontribusi_raw) || 0;
                h += '<tr>'
                   + '<td>' + esc(r.sales_document) + '</td>'
                   + '<td>' + esc(r.customer_reference) + '</td>'
                   + '<td>' + esc(r.contract_end_date) + '</td>'
                   + '<td>' + esc(r.no_delivery) + '</td>'
                   + '<td>' + esc(r.qty) + '</td>'
                   + '<td>' + esc(r.nilai_muatan) + '</td>'
                   + '<td>' + esc(r.kirim_kontribusi) + '</td>'
                   + '<td>' + esc(r.total_kubik) + '</td>'
                   + '<td>' + esc(r.total_tonase) + '</td>'
                   + '</tr>';
            });

            h += '<tr style="font-weight:700;background:#f1f5f9">'
               + '<td colspan="6" style="text-align:right">Total Kirim Kontribusi</td>'
               + '<td>Rp ' + Math.round(total).toLocaleString('id-ID') + '</td>'
               + '<td colspan="2"></td></tr>';

            return h + '</tbody></table>';
        }

        $('#tableLogistik tbody').on('click', '.btn-tujuan', function (e) {
            e.preventDefault();
            var $a = $(this), tr = $a.closest('tr'), row = table.row(tr);

            if (row.child.isShown()) {
                row.child.hide();
                $a.html($a.html().replace('▼', '▶'));
                return;
            }

            row.child('<div style="padding:10px">Memuat rincian...</div>').show();
            $a.html($a.html().replace('▶', '▼'));

            $.get("{{ route('spvplanner.data.pasuruan.detail') }}", {
                no_shipment: $a.data('shipment'),
                tujuan: $a.data('tujuan')
            }).done(function (res) {
                row.child(buildDetail(res.data)).show();
            }).fail(function () {
                row.child('<div style="padding:10px;color:#dc2626">Gagal memuat rincian.</div>').show();
            });
        });

        // ==========================================
        // FILTER
        // ==========================================
        $('#filterArea, #filterPlanner, #filterDate, #filterMonth, #filterYear').on('change', function () {
            table.draw();
        });

        $('#btnResetFilter').on('click', function () {
            $('#filterForm')[0].reset();
            table.draw();
        });

        $(window).on('resize', function () {
            table.columns.adjust();
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