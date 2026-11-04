<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>DATA LOGISTIK GABUNGAN</title>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f1f5f9; margin: 0; color: #1e293b; font-size: 16px; }
        .container { margin-left: 260px; padding: 30px; }
        h2 { font-size: 34px; font-weight: 700; margin-bottom: 20px; color: #0f172a; }
        .card { background: #fff; padding: 20px; border-radius: 16px; overflow: auto; box-shadow: 0 4px 20px rgba(0,0,0,.08); }

        table { width: 100%; border-collapse: collapse; font-size: 16px; }
        th { background: #03c03c; color: #fff; padding: 16px 14px; white-space: nowrap; font-size: 16px; font-weight: 600; text-align: center; border: 1px solid #dbeafe; }
        td { padding: 14px 12px; border: 1px solid #e2e8f0; white-space: nowrap; text-align: left; vertical-align: middle; font-size: 16px; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        tbody tr:hover { background: #dbeafe; transition: .2s; }

        .filter-box { background: #fff; padding: 20px; border-radius: 16px; box-shadow: 0 4px 16px rgba(0,0,0,.08); margin-bottom: 20px; }
        .filter-box form { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
        .filter-box input, .filter-box select { padding: 13px 16px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 16px; min-width: 190px; outline: none; }
        .filter-box input:focus, .filter-box select:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.15); }
        .filter-box a { padding: 13px 20px; background: #ef4444; color: #fff; border-radius: 10px; text-decoration: none; font-size: 16px; font-weight: 600; }
        .filter-box a:hover { background: #dc2626; }
        .tgl-lbl { display: flex; align-items: center; gap: 8px; font-size: 15px; font-weight: 600; color: #475569; }
        .tgl-lbl input { min-width: 170px; }
        #linkInfo { margin-top: 12px; font-size: 14px; color: #475569; display: none; }

        .dataTables_wrapper { font-size: 17px !important; }
        .dataTables_length select { font-size: 17px !important; padding: 11px 16px !important; min-width: 100px; height: 46px; }
        .dataTables_filter input { font-size: 17px !important; padding: 11px 16px !important; min-width: 300px; height: 46px; border-radius: 10px !important; }
        .dataTables_info { font-size: 17px !important; padding-top: 16px !important; }
        .dataTables_paginate .paginate_button { font-size: 16px !important; padding: 9px 16px !important; margin: 0 3px !important; border-radius: 8px !important; }
        .dataTables_processing { background: rgba(255,255,255,.92) !important; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,.12); padding: 14px 22px !important; font-weight: 700 !important; color: #0f172a !important; }

        .btn-tujuan { color: #2563eb; font-weight: 600; text-decoration: none; }
        .btn-tujuan:hover { text-decoration: underline; }

        .badge { display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; border-radius: 999px; font-size: 15px; font-weight: 700; color: #fff; letter-spacing: .3px; box-shadow: 0 2px 8px rgba(0,0,0,.15); }
        .green { background: linear-gradient(135deg, #16a34a, #22c55e) !important; }
        .red { background: linear-gradient(135deg, #dc2626, #ef4444) !important; }
        .orange { background: #f97316 !important; }
        .blue { background: linear-gradient(135deg, #2563eb, #3b82f6) !important; }
        .yellow { background: #facc15 !important; color: #000 !important; }
        .gray { background: #64748b !important; }
        .pink { background: #ec4899 !important; }

        @media (max-width: 768px) {
            .container { margin-left: 0; padding: 15px; }
            h2 { font-size: 26px; }
            .filter-box form { flex-direction: column; align-items: stretch; }
            .filter-box input, .filter-box select { width: 100%; }
        }
    </style>
</head>

<body>
@include('template.sidebar')

<div class="container">
    <h2>📦 DATA LOGISTIK (Jakarta + Pasuruan)</h2>

    <div class="filter-box">
        <form id="filterForm" onsubmit="return false;">
            <select id="fSumber">
                <option value="semua">Jakarta + Pasuruan</option>
                <option value="jakarta">Jakarta</option>
                <option value="pasuruan">Pasuruan</option>
            </select>

            <select id="fArea">
                <option value="">Semua Area</option>
                @foreach($areaList as $a)<option value="{{ $a }}">{{ $a }}</option>@endforeach
            </select>

            <select id="fBasis">
                <option value="periode_shipment">Periode Shipment</option>
                <option value="rencana_kirim">Rencana Kirim</option>
                <option value="tanggal_naik_logistik">Tanggal Naik Logistik</option>
            </select>

            <label class="tgl-lbl">Dari <input type="date" id="fFrom"></label>
            <label class="tgl-lbl">Sampai <input type="date" id="fTo"></label>
            <input type="month" id="fMonth">

            <a href="#" id="btnReset">Reset</a>
        </form>
        <div id="linkInfo"></div>
    </div>

    <div class="card">
        <table id="tbl" class="display nowrap" style="width:100%">
            <thead><tr>
                <th>Sumber</th><th>Tanggal Shipment</th><th>Rencana Kirim</th><th>Lead Time</th>
                <th>Planner</th><th>No Shipment</th><th>Status</th><th>Dist Channel</th>
                <th>Tujuan</th><th>Area</th><th>Pulau</th><th>Mobil</th>
                <th>Qty</th><th>Kubikasi</th><th>Tonase</th><th>Total Kubik</th><th>Total Tonase</th>
                <th>Hasil Kubik</th><th>Hasil Tonase</th>
                <th>Nilai Muatan</th><th>Biaya Kirim</th><th>CR</th><th>Pengiriman Optimal</th>
                <th>Kategori Ekspedisi</th><th>Ekspedisi</th><th>Tgl Dapat Unit</th><th>Tgl Keluar Gudang (Terakhir)</th>
                <th>Estimasi Tiba</th><th>Tgl Tiba</th><th>SLA Tiba</th><th>Tgl Bongkar</th><th>SLA Bongkar</th>
            </tr></thead>
            <tbody></tbody>
        </table>
    </div>
</div>
<script>
$(function () {
    var qs = new URLSearchParams(location.search);

    // filter dari hyperlink dashboard yang tidak punya input di halaman ini
    var linkFilter = {
        area:         qs.getAll('area[]').concat(qs.getAll('area')).filter(Boolean),
        pulau:        qs.get('pulau') || '',
        dist_channel: qs.get('dist_channel') || '',
        date:         qs.get('date') || '',
        year:         qs.get('year') || ''
    };

    // isi input dari link dashboard (SEBELUM DataTable dibuat)
    [['sumber','fSumber'], ['tgl_basis','fBasis'], ['date_from','fFrom'], ['date_to','fTo'], ['month','fMonth']]
        .forEach(function (kv) {
            var v = qs.get(kv[0]);
            if (!v) return;
            if (kv[0] === 'sumber') v = v.toLowerCase();   // JAKARTA -> jakarta
            $('#' + kv[1]).val(v);
        });
    if (!$('#fSumber').val()) $('#fSumber').val('semua');
    if (linkFilter.area.length === 1) $('#fArea').val(linkFilter.area[0]);

    function renderLinkInfo() {
        var parts = [];
        if (linkFilter.area.length > 1) parts.push('Area: ' + linkFilter.area.join(', '));
        if (linkFilter.pulau)           parts.push('Pulau: ' + linkFilter.pulau);
        if (linkFilter.dist_channel)    parts.push('Dist Channel: ' + linkFilter.dist_channel);
        if (linkFilter.date)            parts.push('Tanggal: ' + linkFilter.date);
        if (linkFilter.year)            parts.push('Tahun: ' + linkFilter.year);
        $('#linkInfo').toggle(parts.length > 0)
            .text(parts.length ? 'Filter dari dashboard: ' + parts.join(' | ') : '');
    }
    renderLinkInfo();

    // urutan HARUS sama dengan <th> (32 kolom)
    var cols = ['sumber_badge','tgl_shipment','rencana_kirim','lead_time','planner','no_shipment','status_badge',
        'dist_channel','tujuan_html','area','pulau','mobil',
        'qty','kubikasi','tonase','total_kubik','total_tonase',
        'hasil_kubik','hasil_tonase',
        'nilai_fmt','biaya_fmt','cr_fmt','pengiriman_optimal',
        'kategori','ekspedisi','tgl_dapat_unit','tgl_keluar_gudang',
        'tgl_estimasi','tgl_tiba','sla_tiba','tgl_bongkar','sla_bongkar'];

    var table = $('#tbl').DataTable({
        processing: true, serverSide: true, scrollX: true, pageLength: 10,
        order: [],
        language: {
            processing: "Memuat data...", search: "Cari:", lengthMenu: "Tampilkan _MENU_ data",
            info: "Menampilkan _START_ - _END_ dari _TOTAL_ data", infoEmpty: "Tidak ada data",
            infoFiltered: "(difilter dari _MAX_ total data)",
            paginate: { first: "Awal", last: "Akhir", next: "Selanjutnya", previous: "Sebelumnya" }
        },
        ajax: {
            url: "{{ route('sales.data.gabungan.ajax') }}",
            type: 'POST',
            headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
            data: function (d) {
                d.sumber       = $('#fSumber').val();
                d.area         = $('#fArea').val() ? [$('#fArea').val()] : linkFilter.area;
                d.tgl_basis    = $('#fBasis').val();
                d.date_from    = $('#fFrom').val();
                d.date_to      = $('#fTo').val();
                d.month        = $('#fMonth').val();
                d.pulau        = linkFilter.pulau;
                d.dist_channel = linkFilter.dist_channel;
                d.date         = linkFilter.date;
                d.year         = linkFilter.year;
            },
            error: function (xhr) {
                console.error('AJAX gagal', xhr.status, xhr.responseText);
                alert('Gagal memuat data (' + xhr.status + '). Cek Console / laravel.log.');
            }
        },
        columns: cols.map(function (c) { return { data: c, defaultContent: '-', orderable: false }; })
    });

    // muat ulang tabel tiap filter diubah
    $('#fSumber, #fArea, #fBasis, #fFrom, #fTo, #fMonth').on('change', function () {
        table.ajax.reload();
    });

    $('#btnReset').on('click', function (e) {
        e.preventDefault();
        $('#filterForm')[0].reset();
        linkFilter = { area: [], pulau: '', dist_channel: '', date: '', year: '' };
        history.replaceState(null, '', location.pathname);
        renderLinkInfo();
        table.ajax.reload();
    });

    // ===== RINCIAN DO =====
    var detailUrl = "{{ route('sales.data.gabungan.detail') }}";

    function esc(s) {
        return String(s == null ? '-' : s).replace(/[&<>"']/g, function (c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }

    function buildDetail(rows) {
        if (!rows || !rows.length) return '<div style="padding:10px">Tidak ada rincian.</div>';

        var total = 0;
        var h = '<table style="margin:8px 0 8px 40px;width:auto;background:#fff">' +
            '<thead><tr>' +
            '<th>Sales Order</th><th>Customer Reference</th><th>PO Expired</th><th>NO DO</th>' +
            '<th>Qty Barang</th><th>Nilai Muatan</th><th>Kirim Kontribusi</th><th>Total Kubik</th><th>Total Tonase</th>' +
            '</tr></thead><tbody>';

        rows.forEach(function (r) {
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

    $('#tbl tbody').on('click', '.btn-tujuan', function (e) {
        e.preventDefault();
        var $a  = $(this),
            row = table.row($a.closest('tr'));

        if (row.child.isShown()) {
            row.child.hide();
            $a.html($a.html().replace('▼', '▶'));
            return;
        }

        row.child('<div style="padding:10px">Memuat rincian...</div>').show();
        $a.html($a.html().replace('▶', '▼'));

        $.get(detailUrl, {
            sumber: $a.data('sumber'),
            no_shipment: $a.data('shipment'),
            tujuan: $a.data('tujuan')
        }).done(function (res) {
            row.child(buildDetail(res.data)).show();
        }).fail(function (xhr) {
            console.error('Rincian gagal', xhr.status, xhr.responseText);
            row.child('<div style="padding:10px;color:#dc2626">Gagal memuat rincian (' + xhr.status + ').</div>').show();
        });
    });
});
</script>
</body>
</html>