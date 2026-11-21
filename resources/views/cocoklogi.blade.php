{{-- Partial Cocoklogi: tombol + popup. Pasang dengan @include('cocoklogi') tepat setelah tombol Export Excel. --}}

<button type="button" id="btnCocoklogi" class="btn-export" style="background:#2563eb;margin-left:8px;position:relative">
    🔍 Cocoklogi
    <span id="clDot" style="display:none;position:absolute;top:-6px;right:-6px;background:#ef4444;color:#fff;border-radius:999px;font-size:12px;padding:2px 7px">!</span>
</button>

<div id="clModal" class="cl-overlay" style="display:none">
    <div class="cl-box">
        <div class="cl-head">
            <b style="font-size:20px">Cocoklogi Excel vs Sistem</b>
            <button type="button" id="clClose" class="cl-x">✕</button>
        </div>

        <div id="clLast" class="cl-muted"></div>

        <div class="cl-row">
            <input type="file" id="clFile" accept=".xlsx,.xls,.csv">
            <label><input type="radio" name="clMode" value="grup" checked> Per shipment + tujuan</label>
            <label><input type="radio" name="clMode" value="do"> Per DO (lebih detail)</label>
        </div>
        <div class="cl-row">
    <b>Periode shipment:</b>
    <div id="clPeriode" style="display:flex;gap:14px;flex-wrap:wrap">
        <span class="cl-muted">memuat...</span>
    </div>
</div>
<div class="cl-muted" style="margin-top:-4px">Kosongkan semua centang = cek semua periode.</div>
        <div class="cl-row">
            <span>Periode opsional (tgl naik logistik):</span>
            <input type="date" id="clFrom"> s/d <input type="date" id="clTo">
            <button type="button" id="clCek" class="cl-btn cl-blue">Cek</button>
        </div>

        <div id="clInfo" style="margin:10px 0"></div>

        <div id="clMissingWrap" style="display:none">
          <div class="cl-row" style="justify-content:space-between">
    <b>Ada di sistem, tidak ada di Excel</b>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button type="button" class="cl-btn cl-orange cl-aksi" data-aksi="arsip">📦 Pindahkan ke Storage</button>
        <button type="button" class="cl-btn cl-red cl-aksi" data-aksi="hapus">🗑 Hapus Permanen</button>
    </div>
</div>
            <div class="cl-scroll">
                <table class="cl-tbl" id="clTbl">
                    <thead><tr>
                        <th><input type="checkbox" id="clAll"></th>
                        <th>No Shipment</th><th>Tujuan</th><th class="cl-do">No DO</th><th>Jml DO</th>
                    </tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <div id="clNotDbWrap" style="display:none;margin-top:16px">
            <b>Ada di Excel, belum ada di sistem</b>
            <div class="cl-scroll">
                <table class="cl-tbl" id="clTbl2">
                    <thead><tr><th>No Shipment</th><th id="clTh2">Tujuan</th><th>Tanggal Shipment</tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
    .cl-overlay{position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:10000;display:flex;align-items:center;justify-content:center;padding:20px}
    .cl-box{background:#fff;border-radius:16px;width:min(1000px,100%);max-height:90vh;overflow:auto;padding:22px;box-shadow:0 20px 50px rgba(0,0,0,.3)}
    .cl-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px}
    .cl-x{background:none;border:none;font-size:22px;cursor:pointer;color:#64748b}
    .cl-muted{color:#64748b;font-size:14px;margin-bottom:10px}
    .cl-row{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin:10px 0}
    .cl-row input[type=date]{padding:9px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:15px}
    .cl-btn{padding:10px 18px;border:none;border-radius:8px;color:#fff;font-size:15px;font-weight:600;cursor:pointer}
    .cl-blue{background:#2563eb}.cl-red{background:#dc2626}.cl-orange{background:#f59e0b}
    .cl-btn:disabled{opacity:.6;cursor:wait}
    .cl-scroll{max-height:320px;overflow:auto;margin-top:8px;border:1px solid #e2e8f0;border-radius:8px}
    .cl-tbl{width:100%;border-collapse:collapse;font-size:15px}
    .cl-tbl th{position:sticky;top:0;background:#03c03c;color:#fff;padding:10px}
    .cl-tbl td{padding:8px 10px;border:1px solid #e2e8f0;white-space:normal}
    .cl-warn{color:#dc2626;font-weight:700}
</style>

<script>
$(function () {
    var token = null;
    var $m = $('#clModal');
    var csrf = $('meta[name="csrf-token"]').attr('content');
    var esc = function (s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    };

    function loadStatus() {
        $.getJSON("{{ route('cocoklogi.status') }}", function (r) {
            $('#clDot').toggle(!!r.overdue);
            $('#btnCocoklogi').attr('title', r.last ? 'Terakhir: ' + r.last : 'Belum pernah cocoklogi');
            $('#clLast').html(r.overdue
                ? '<span class="cl-warn">Sudah lebih dari 7 hari sejak cocoklogi terakhir (' + esc(r.last || 'belum pernah') + ').</span>'
                : 'Cocoklogi terakhir: ' + esc(r.last));
        });
    }
    loadStatus();
    function loadPeriode() {
    var picked = $('.cl-per:checked').map(function () { return this.value; }).get();
    $.getJSON("{{ route('cocoklogi.periode') }}", function (list) {
        if (!list.length) {
            $('#clPeriode').html('<span class="cl-muted">Belum ada periode</span>');
            return;
        }
        $('#clPeriode').html(list.map(function (p) {
            return '<label><input type="checkbox" class="cl-per" value="' + esc(p) + '"' +
                (picked.indexOf(String(p)) > -1 ? ' checked' : '') + '> ' + esc(p) + '</label>';
        }).join(''));
    });
}

   $('#btnCocoklogi').on('click', function (e) { e.preventDefault(); $m.show(); loadStatus(); loadPeriode(); });
    $('#clClose').on('click', function () { $m.hide(); });
    $m.on('mousedown', function (e) { if (e.target === this) $m.hide(); });

    $('#clCek').on('click', function () {
        var file = $('#clFile')[0].files[0];
        if (!file) return alert('Pilih file Excel dulu');

        var mode = $('input[name=clMode]:checked').val();
        var fd = new FormData();
        fd.append('file', file);
        fd.append('mode', mode);
        fd.append('date_from', $('#clFrom').val());
        fd.append('date_to', $('#clTo').val());
        $('.cl-per:checked').each(function () { fd.append('periode[]', this.value); });

        var $btn = $(this).prop('disabled', true);
        $('#clInfo').text('Memproses...');

        $.ajax({
            url: "{{ route('cocoklogi.preview') }}", type: 'POST', data: fd,
            processData: false, contentType: false,
            headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'}
        }).done(function (j) {
            token = j.token;
            var unit = mode === 'do' ? 'DO' : 'grup';

            $('#clInfo').html(
                'Excel: ' + j.total_excel + ' ' + unit + ' | Sistem: ' + j.total_db + ' ' + unit +
                ' | <b>Tidak ada di Excel: ' + j.missing.length + '</b> | ' +
                'Ada di Excel tapi belum di sistem: ' + (j.not_in_db_total == null ? '- (tidak dihitung saat pakai filter periode)' : j.not_in_db_total) +
                (j.skipped_excel ? '<br>' + j.skipped_excel + ' baris Excel dilewati karena no_shipment kosong.' : '') +
                (j.warning ? '<br><span class="cl-warn">⚠️ Lebih dari 50% data sistem tidak ada di Excel. Cek apakah Excel-nya lengkap atau isi filter periode.</span>' : '')
            );

            $('.cl-do').toggle(mode === 'do');
            $('#clTbl tbody').html(j.missing.map(function (m, i) {
                return '<tr><td><input type="checkbox" class="cl-chk" value="' + i + '"></td>' +
                    '<td>' + esc(m.no_shipment) + '</td><td>' + esc(m.tujuan) + '</td>' +
                    (mode === 'do' ? '<td>' + esc(m.no_do) + '</td>' : '') +
                    '<td>' + m.jml_do + '</td></tr>';
            }).join(''));
            $('#clAll').prop('checked', false);
            $('#clMissingWrap').toggle(j.missing.length > 0);

            var nd = j.not_in_db || [];
            $('#clTh2').text(mode === 'do' ? 'No DO' : 'Tujuan');
          $('#clTbl2 tbody').html(nd.map(function (x) {
    return '<tr><td>' + esc(x.no_shipment) + '</td><td>' + esc(x.tujuan_atau_do) + '</td>' +
        '<td>' + esc(x.tanggal_shipment || '-') + '</td></tr>';
}).join(''));
            $('#clNotDbWrap').toggle(nd.length > 0);

            loadStatus();
        }).fail(function (xhr) {
            $('#clInfo').text('');
            alert((xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message)) || 'Gagal memproses file');
        }).always(function () { $btn.prop('disabled', false); });
    });

    $('#clAll').on('change', function () { $('.cl-chk').prop('checked', this.checked); });

   $('.cl-aksi').on('click', function () {
    var aksi = $(this).data('aksi');
    var idx = $('.cl-chk:checked').map(function () { return parseInt(this.value, 10); }).get();
    if (!idx.length) return alert('Belum ada yang dicentang');

    var msg = aksi === 'arsip'
        ? 'Pindahkan ' + idx.length + ' baris ke logistik_storage? Data akan hilang dari Data Logistik tapi masih tersimpan di Storage.'
        : 'HAPUS PERMANEN ' + idx.length + ' baris? Data TIDAK diarsipkan dan tidak bisa dikembalikan.';
    if (!confirm(msg)) return;

    var $btns = $('.cl-aksi').prop('disabled', true);
    $.ajax({
        url: "{{ route('cocoklogi.hapus') }}", type: 'POST', contentType: 'application/json',
        data: JSON.stringify({token: token, idx: idx, aksi: aksi}),
        headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'}
    }).done(function (j) {
        alert(j.deleted + (j.aksi === 'arsip' ? ' baris dipindahkan ke storage' : ' baris dihapus permanen'));
        $m.hide();
        $('#clMissingWrap, #clNotDbWrap').hide();
        $('#clInfo').text('');
        $('#tableLogistik').DataTable().ajax.reload(null, false);
    }).fail(function (xhr) {
        alert((xhr.responseJSON && xhr.responseJSON.error) || 'Gagal memproses');
    }).always(function () { $btns.prop('disabled', false); });
});
});
</script>