<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Analitik Logistics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .pivot-header { background-color: #d9edf7; font-weight: bold; }
        .pivot-group { background-color: #f8f9fa; font-weight: bold; cursor: pointer; }
        .pivot-sub { padding-left: 2rem !important; }
        .total-row { background-color: #e9ecef; font-weight: bold; }
        .badge-score { font-size: 0.85rem; padding: 0.35em 0.65em; }
        .legend-table td, .legend-table th { font-size: 0.85rem; padding: 0.4rem 0.6rem; }
    </style>
</head>
<body class="bg-light">

<div class="container-fluid py-4">
    <h3 class="fw-bold mb-4"><i class="bi bi-speedometer2"></i> Dashboard Analitik Pengiriman</h3>

    <!-- FILTER BOARD -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form id="filterForm" class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Tahun</label>
                    <select id="filterYear" class="form-select">
                        <option value="">-- Semua Tahun --</option>
                        @for($y = date('Y'); $y >= 2020; $y--)
                            <option value="{{ $y }}">{{ $y }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Bulan</label>
                    <select id="filterMonth" class="form-select">
                        <option value="">-- Semua Bulan --</option>
                        @foreach([1=>'Januari', 2=>'Februari', 3=>'Maret', 4=>'April', 5=>'Mei', 6=>'Juni', 7=>'Juli', 8=>'Agustus', 9=>'September', 10=>'Oktober', 11=>'November', 12=>'Desember'] as $num =>$name)
                            <option value="{{ $num }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Tanggal</label>
                    <select id="filterDay" class="form-select">
                        <option value="">-- Semua Tanggal --</option>
                        @for($d = 1; $d <= 31; $d++)
                            <option value="{{ $d }}">{{ $d }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">PIC</label>
                    <select id="filterPic" class="form-select">
                        <option value="">-- Semua PIC --</option>
                        <!-- Diisi otomatis oleh JS dari data pic_options -->
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="button" id="btnFilter" class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Terapkan Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- PIVOT TABLES ROW -->
    <div class="row g-4">
        <!-- TABLE 1: Rata-Rata Kubikasi & Tonase per PIC -->
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-bold py-3">
                    <i class="bi bi-bar-chart-line"></i> Average Kubikasi & Tonase per PIC
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-bordered mb-0 align-middle">
                        <thead class="pivot-header">
                            <tr>
                                <th>PIC / Driver</th>
                                <th class="text-end">Avg Kubikasi</th>
                                <th class="text-end">Avg Tonase</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyAvgPic">
                            <!-- Populated by JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TABLE 2: Count Toko/Tujuan per PIC -->
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-bold py-3">
                    <i class="bi bi-shop"></i> Count Toko / Tujuan per PIC
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-bordered mb-0 align-middle">
                        <thead class="pivot-header">
                            <tr>
                                <th>PIC / Tujuan</th>
                                <th class="text-end">Jumlah Data / Toko</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyCountToko">
                            <!-- Populated by JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

    <!-- KETERANGAN / RUBRIK PENILAIAN -->
    <div class="row g-4 mt-1">
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-bold py-3">
                    <i class="bi bi-info-circle"></i> Rubrik Skor — Jumlah Toko
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-bordered mb-0 legend-table">
                        <thead class="pivot-header">
                            <tr>
                                <th>Kategori</th>
                                <th>Jumlah Toko</th>
                                <th class="text-end">Skor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td>Excellent</td><td>8</td><td class="text-end"><span class="badge bg-success badge-score">100</span></td></tr>
                            <tr><td>Good</td><td>5 - 7</td><td class="text-end"><span class="badge bg-primary badge-score">80</span></td></tr>
                            <tr><td>Need Improvement</td><td>3 - 4</td><td class="text-end"><span class="badge bg-warning text-dark badge-score">50</span></td></tr>
                            <tr><td>Less Than Expectation</td><td>2</td><td class="text-end"><span class="badge bg-orange text-white badge-score" style="background-color:#fd7e14;">40</span></td></tr>
                            <tr><td>Fail</td><td>1</td><td class="text-end"><span class="badge bg-danger badge-score">20</span></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-body pt-2 pb-3">
                    <small class="text-muted">Skor dihitung per pengiriman (per baris) lalu dirata-ratakan per PIC/bulan.</small>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-bold py-3">
                    <i class="bi bi-info-circle"></i> Rubrik Skor — Kubikasi/Tonase
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-bordered mb-0 legend-table">
                        <thead class="pivot-header">
                            <tr>
                                <th>Kategori</th>
                                <th>Range Optimalisasi</th>
                                <th class="text-end">Skor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td>Excellent</td><td>&gt; 85%</td><td class="text-end"><span class="badge bg-success badge-score">100</span></td></tr>
                            <tr><td>Good</td><td>60% - 85%</td><td class="text-end"><span class="badge bg-primary badge-score">80</span></td></tr>
                            <tr><td>Need Improvement</td><td>30% - 60%</td><td class="text-end"><span class="badge bg-warning text-dark badge-score">60</span></td></tr>
                            <tr><td>Less Than Expectation</td><td>15% - 30%</td><td class="text-end"><span class="badge bg-orange text-white badge-score" style="background-color:#fd7e14;">40</span></td></tr>
                            <tr><td>Fail</td><td>&lt; 15%</td><td class="text-end"><span class="badge bg-danger badge-score">20</span></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-body pt-2 pb-3">
                    <small class="text-muted">Diambil dari nilai tertinggi antara rata-rata Kubikasi vs Tonase per PIC/bulan.</small>
                </div>
            </div>
        </div>
    </div>

<div class="row g-4 mt-1">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-bold py-3">
                    <i class="bi bi-speedometer"></i> KPI PIC / Driver — Tepat Waktu vs Pending per Bulan
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-bordered mb-0 align-middle">
                        <thead class="pivot-header">
                            <tr>
                                <th>Bulan</th>
                                <th>PIC / Driver</th>
                                <th class="text-end">Tepat Waktu</th>
                                <th class="text-end">Pending</th>
                                <th class="text-end">Total</th>
                                <th class="text-end">% Tepat Waktu</th>
                                <th class="text-end">% Pending</th>
                                <th class="text-end">Avg Toko</th>
                                <th class="text-end">Skor Toko</th>
                                <th class="text-end">Kubikasi/Tonase</th>
                                <th class="text-end">Skor Kubikasi/Tonase</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyKpiPicDriver"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ALASAN PENDING: per Bulan > Alasan > PIC -->
    <div class="row g-4 mt-1">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-bold py-3">
                    <i class="bi bi-exclamation-triangle"></i> Alasan Pending per Bulan
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-bordered mb-0 align-middle">
                        <thead class="pivot-header">
                            <tr>
                                <th>Bulan</th>
                                <th>Alasan Pending</th>
                                <th>PIC</th>
                                <th class="text-end">Tepat Waktu</th>
                                <th class="text-end">Pending</th>
                                <th class="text-end">Total</th>
                                <th class="text-end">% Tepat Waktu</th>
                                <th class="text-end">% Pending</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyKpiAlasan"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- AREA (TUJUAN) per Bulan -->
    <div class="row g-4 mt-1 mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-bold py-3">
                    <i class="bi bi-geo-alt"></i> Area per Bulan
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-bordered mb-0 align-middle">
                        <thead class="pivot-header">
                            <tr>
                                <th>Bulan</th>
                                <th>Area </th>
                                <th class="text-end">Count</th>
                                <th class="text-end">%</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyKpiArea"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
$(document).ready(function() {

    let picOptionsLoaded = false;

    // Badge warna sesuai rubrik skor (100=hijau, 80=biru, 60/50=kuning, 40=oranye, 20=merah)
    function scoreBadge(score) {
        if (score === null || score === undefined || score === '-') {
            return '<span class="text-muted">-</span>';
        }
        let cls = 'bg-danger';
        if (score >= 100) cls = 'bg-success';
        else if (score >= 80) cls = 'bg-primary';
        else if (score >= 50) cls = 'bg-warning text-dark';
        else if (score >= 40) cls = 'bg-orange text-white';
        else cls = 'bg-danger';

        let style = (cls.indexOf('bg-orange') !== -1) ? 'style="background-color:#fd7e14;"' : '';
        return `<span class="badge ${cls} badge-score" ${style}>${score}</span>`;
    }

    function loadDashboardData() {
        let year  = $('#filterYear').val();
        let month = $('#filterMonth').val();
        let day   = $('#filterDay').val();
        let pic   = $('#filterPic').val();

        $.ajax({
            url: "{{ route('kota.dashboard.data') }}",
            type: "GET",
            data: { year: year, month: month, day: day, pic: pic },
            success: function(res) {
                renderAvgTable(res.avg_pic);
                renderCountTokoTable(res.count_toko);
                renderKpiPicDriver(res.kpi_pic_driver);      // BARU
                renderKpiAlasanPending(res.kpi_alasan_pending); // BARU
                renderKpiArea(res.kpi_area);
                populatePicOptions(res.pic_options);
            }
        });
    }

    // Isi dropdown PIC sekali saja (dari daftar penuh, tidak menyusut saat difilter)
    function populatePicOptions(picOptions) {
        if (picOptionsLoaded || !picOptions) return;

        let currentVal = $('#filterPic').val();
        let html = '<option value="">-- Semua PIC --</option>';
        picOptions.forEach(function(pic) {
            html += `<option value="${pic}">${pic}</option>`;
        });
        $('#filterPic').html(html).val(currentVal);
        picOptionsLoaded = true;
    }

    // Render Table 1 (Pivot Avg Kubikasi & Tonase)
    function renderAvgTable(avgPic) {
        let html = '';
        if(avgPic.data.length === 0) {
            html = '<tr><td colspan="3" class="text-center text-muted">Tidak ada data</td></tr>';
        } else {
            avgPic.data.forEach(function(group) {
                html += `<tr class="pivot-group">
                    <td><i class="bi bi-dash-square"></i> ${group.pic}</td>
                    <td class="text-end">${group.avg_kubikasi}%</td>
                    <td class="text-end">${group.avg_tonase}%</td>
                </tr>`;

                group.drivers.forEach(function(driver) {
                    html += `<tr>
                        <td class="pivot-sub">${driver.nama_driver}</td>
                        <td class="text-end">${driver.avg_kubikasi}%</td>
                        <td class="text-end">${driver.avg_tonase}%</td>
                    </tr>`;
                });
            });

            html += `<tr class="total-row">
                <td>Grand Total</td>
                <td class="text-end">${avgPic.grand_avg_kubikasi}%</td>
                <td class="text-end">${avgPic.grand_avg_tonase}%</td>
            </tr>`;
        }
        $('#tbodyAvgPic').html(html);
    }
    function renderKpiPicDriver(kpi) {
    let html = '';
    if (!kpi.months || kpi.months.length === 0) {
        html = '<tr><td colspan="11" class="text-center text-muted">Tidak ada data</td></tr>';
    } else {
        kpi.months.forEach(function(month) {
            month.pics.forEach(function(pic, idx) {
                let avgToko  = pic.avg_jumlah_toko ?? '-';
                let pctKT    = pic.pct_kubikasi_tonase !== null ? pic.pct_kubikasi_tonase + '%' : '-';

                html += `<tr class="pivot-group">
                    ${idx === 0 ? `<td rowspan="${month.pics.length + 1}">${month.bulan}</td>` : ''}
                    <td><i class="bi bi-dash-square"></i> ${pic.pic}</td>
                    <td class="text-end">${pic.tepat_waktu}</td>
                    <td class="text-end">${pic.pending}</td>
                    <td class="text-end">${pic.total}</td>
                    <td class="text-end">${pic.pct_tepat_waktu}%</td>
                    <td class="text-end">${pic.pct_pending}%</td>
                    <td class="text-end">${avgToko}</td>
                    <td class="text-end">${scoreBadge(pic.penilaian_toko)}</td>
                    <td class="text-end">${pctKT}</td>
                    <td class="text-end">${scoreBadge(pic.penilaian_kubikasi_tonase)}</td>
                </tr>`;
            });

            html += `<tr class="total-row">
                <td>${month.bulan} Total</td>
                <td class="text-end">${month.total_tepat_waktu}</td>
                <td class="text-end">${month.total_pending}</td>
                <td class="text-end">${month.total}</td>
                <td class="text-end">${month.pct_tepat_waktu}%</td>
                <td class="text-end">${month.pct_pending}%</td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>`;
        });
    }
    $('#tbodyKpiPicDriver').html(html);
}

// TABLE 4: Alasan Pending
function renderKpiAlasanPending(kpi) {
    let html = '';
    if (!kpi.months || kpi.months.length === 0) {
        html = '<tr><td colspan="8" class="text-center text-muted">Tidak ada data</td></tr>';
    } else {
        kpi.months.forEach(function(month) {
            month.alasan_groups.forEach(function(group) {
                group.pics.forEach(function(pic, idx) {
                    html += `<tr>
                        ${idx === 0 ? `<td>${month.bulan}</td><td class="pivot-group">${group.alasan}</td>` : `<td></td><td></td>`}
                        <td class="pivot-sub">${pic.pic}</td>
                        <td class="text-end">${pic.tepat_waktu}</td>
                        <td class="text-end">${pic.pending}</td>
                        <td class="text-end">${pic.total}</td>
                        <td class="text-end">${pic.pct_tepat_waktu}%</td>
                        <td class="text-end">${pic.pct_pending}%</td>
                    </tr>`;
                });

                html += `<tr class="total-row">
                    <td></td>
                    <td>${group.alasan} Total</td>
                    <td></td>
                    <td class="text-end">${group.tepat_waktu}</td>
                    <td class="text-end">${group.pending}</td>
                    <td class="text-end">${group.total}</td>
                    <td class="text-end">${group.pct_tepat_waktu}%</td>
                    <td class="text-end">${group.pct_pending}%</td>
                </tr>`;
            });
        });
    }
    $('#tbodyKpiAlasan').html(html);
}

// TABLE 5: Area / Tujuan
function renderKpiArea(kpi) {
    let html = '';
    if (!kpi.months || kpi.months.length === 0) {
        html = '<tr><td colspan="4" class="text-center text-muted">Tidak ada data</td></tr>';
    } else {
        kpi.months.forEach(function(month) {
            month.areas.forEach(function(area, idx) {
                html += `<tr>
                    ${idx === 0 ? `<td rowspan="${month.areas.length + 1}">${month.bulan}</td>` : ''}
                    <td>${area.area}</td>
                    <td class="text-end">${area.total}</td>
                    <td class="text-end">${area.pct}%</td>
                </tr>`;
            });

            html += `<tr class="total-row">
                <td>${month.bulan} Total</td>
                <td class="text-end">${month.total}</td>
                <td class="text-end">100%</td>
            </tr>`;
        });
    }
    $('#tbodyKpiArea').html(html);
}

    // Render Table 2 (Pivot Count Toko)
    function renderCountTokoTable(countToko) {
        let html = '';
        if(countToko.length === 0) {
            html = '<tr><td colspan="2" class="text-center text-muted">Tidak ada data</td></tr>';
        } else {
            countToko.forEach(function(group) {
                html += `<tr class="pivot-group">
                    <td><i class="bi bi-dash-square"></i> ${group.pic}</td>
                    <td class="text-end fw-bold">${group.total_pic}</td>
                </tr>`;

                group.tujuans.forEach(function(item, idx) {
                    html += `<tr>
                        <td class="pivot-sub">${idx + 1}. ${item.tujuan}</td>
                        <td class="text-end">${item.total}</td>
                    </tr>`;
                });

                html += `<tr class="total-row">
                    <td>${group.pic} Total</td>
                    <td class="text-end">${group.total_pic}</td>
                </tr>`;
            });
        }
        $('#tbodyCountToko').html(html);
    }

    $('#btnFilter').click(function() {
        loadDashboardData();
    });

    // Load pertama kali
    loadDashboardData();
});
</script>
</body>
</html>