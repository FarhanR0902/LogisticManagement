@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="fw-bold mb-0"><i class="bi bi-clipboard-data"></i> KPI Analysis</h3>

        <select id="viewSelector" class="form-select" style="width: 220px;">
            <option value="alasan_pending">Alasan Pending</option>
            <option value="area_besar">Area Besar</option>
        </select>
    </div>

    <!-- ALASAN PENDING TABLE -->
    <div id="panelAlasanPending" class="card shadow-sm">
        <div class="card-header bg-white fw-bold py-3">Alasan Pending</div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-bordered mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Bulan</th>
                        <th>Alasan Pending</th>
                        <th>PIC</th>
                        <th class="text-end">Tepat Waktu</th>
                        <th class="text-end">Pending</th>
                        <th class="text-end">% Tepat Waktu</th>
                        <th class="text-end">% Pending</th>
                        <th class="text-end">Total Count</th>
                        <th class="text-end">Total %</th>
                    </tr>
                </thead>
                <tbody id="tbodyAlasanPending"></tbody>
            </table>
        </div>
    </div>

    <!-- AREA BESAR TABLE -->
    <div id="panelAreaBesar" class="card shadow-sm" style="display: none;">
        <div class="card-header bg-white fw-bold py-3">Area Besar</div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-bordered mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Bulan</th>
                        <th>Area Besar</th>
                        <th class="text-end">Tepat Waktu</th>
                        <th class="text-end">Pending</th>
                        <th class="text-end">% Tepat Waktu</th>
                        <th class="text-end">% Pending</th>
                        <th class="text-end">Total Count</th>
                        <th class="text-end">Total %</th>
                    </tr>
                </thead>
                <tbody id="tbodyAreaBesar"></tbody>
            </table>
        </div>
    </div>

</div>

<style>
    .pivot-group { background-color: #f8f9fa; font-weight: bold; }
    .pivot-sub { padding-left: 2rem !important; }
</style>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
$(document).ready(function () {

    let cachedData = null;

    function loadData() {
        $.get("{{ route('kota.kpi.data') }}", function (res) {
            cachedData = res;
            renderAlasanPending(res.alasan_pending);
            renderAreaBesar(res.area_besar);
        });
    }

    function renderAlasanPending(data) {
        let html = '';
        if (!data.months || data.months.length === 0) {
            html = '<tr><td colspan="9" class="text-center text-muted">Tidak ada data</td></tr>';
        } else {
            data.months.forEach(function (month) {
                month.alasan_groups.forEach(function (group) {
                    group.pics.forEach(function (pic, idx) {
                        html += `<tr>
                            ${idx === 0 ? `<td>${month.bulan}</td><td class="pivot-group">${group.alasan}</td>` : `<td></td><td></td>`}
                            <td class="pivot-sub">${pic.pic}</td>
                            <td class="text-end">${pic.tepat_waktu}</td>
                            <td class="text-end">${pic.pending}</td>
                            <td class="text-end">${pic.pct_tepat_waktu}%</td>
                            <td class="text-end">${pic.pct_pending}%</td>
                            <td class="text-end">${pic.total}</td>
                            <td class="text-end">${pic.total_pct}%</td>
                        </tr>`;
                    });
                });
            });
        }
        $('#tbodyAlasanPending').html(html);
    }

    function renderAreaBesar(data) {
        let html = '';
        if (!data.months || data.months.length === 0) {
            html = '<tr><td colspan="8" class="text-center text-muted">Tidak ada data</td></tr>';
        } else {
            data.months.forEach(function (month) {
                month.areas.forEach(function (area, idx) {
                    html += `<tr>
                        ${idx === 0 ? `<td>${month.bulan}</td>` : `<td></td>`}
                        <td>${area.area}</td>
                        <td class="text-end">${area.tepat_waktu}</td>
                        <td class="text-end">${area.pending}</td>
                        <td class="text-end">${area.pct_tepat_waktu}%</td>
                        <td class="text-end">${area.pct_pending}%</td>
                        <td class="text-end">${area.total}</td>
                        <td class="text-end">${area.total_pct}%</td>
                    </tr>`;
                });
            });
        }
        $('#tbodyAreaBesar').html(html);
    }

    $('#viewSelector').on('change', function () {
        if ($(this).val() === 'alasan_pending') {
            $('#panelAlasanPending').show();
            $('#panelAreaBesar').hide();
        } else {
            $('#panelAlasanPending').hide();
            $('#panelAreaBesar').show();
        }
    });

    loadData();
});
</script>
@endpush