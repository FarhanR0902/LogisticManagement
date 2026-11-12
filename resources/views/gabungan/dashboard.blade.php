@include('template.sidebar')
@php
    $sumber = strtoupper(request('sumber', ''));

    $judulDashboard = match ($sumber) {
        'JAKARTA'  => 'Dashboard Pengiriman Jakarta',
        'PASURUAN' => 'Dashboard Pengiriman Pasuruan',
        default    => 'Dashboard Pengiriman',
    };

    $eyebrowDashboard = match ($sumber) {
        'JAKARTA'  => 'Logistik · Jakarta',
        'PASURUAN' => 'Logistik · Pasuruan',
        default    => 'Logistik · Jakarta + Pasuruan',
    };

    // link kartu KPI: bawa semua filter yang aktif
    $qs             = request()->query();
    $listRouteName  = $listRoute ?? 'manager.gabungan.list';
    $transitRoute   = $inTransitRoute ?? 'manager.gabungan.intransit';
    $dataRouteName  = $dataRoute ?? 'manager.gabungan.data';
    $linkKpi        = fn(string $kpi) => $kpi === 'total'
        ? route($dataRouteName, $qs)
        : route($listRouteName, array_merge($qs, ['kpi' => $kpi]));

    // filter multi-pick
    $areaSel  = array_filter((array) request('area', []), 'strlen');
    $distSel  = array_filter((array) request('dist_channel', []), 'strlen');
    $pulauSel = array_filter((array) request('pulau', []), 'strlen');
    $monthSel = array_filter((array) request('month', []), 'strlen');

    $namaBulan = ['01'=>'Jan','02'=>'Feb','03'=>'Mar','04'=>'Apr','05'=>'Mei','06'=>'Jun',
                  '07'=>'Jul','08'=>'Agu','09'=>'Sep','10'=>'Okt','11'=>'Nov','12'=>'Des'];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $judulDashboard }}</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Leaflet (peta sebaran) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

<!-- Tom Select (dropdown yang bisa di-search) -->
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

<style>

/* ================= RESET ================= */
*{ margin:0; padding:0; box-sizing:border-box; }

:root{
    --bg:#f2f4f9;
    --surface:#ffffff;
    --border:#e6e9f0;

    --text-primary:#0f172a;
    --text-secondary:#64748b;
    --text-muted:#94a3b8;

    --primary:#4f46e5;
    --primary-light:#eef2ff;
    --teal:#0d9488;
    --teal-light:#effaf8;
    --green:#16a34a;
    --green-light:#eefbf1;
    --amber:#f59e0b;
    --amber-light:#fef7e9;
    --red:#e11d48;
    --red-light:#fdeef2;
    --purple:#8b5cf6;
    --purple-light:#f4f0fe;
    --slate:#334155;
    --slate-light:#f1f3f7;

    --radius-lg:16px;
    --radius-md:12px;
    --radius-sm:8px;

    --shadow:0 1px 2px rgba(15,23,42,.04), 0 8px 20px rgba(15,23,42,.05);
    --sidebar-w:260px;   /* sama dengan lebar .sidebar di template */
}

body{
    background:var(--bg);
    font-family:'Inter',sans-serif;
    color:var(--text-primary);
    -webkit-font-smoothing:antialiased;
}

h1,h2,h3,h4{ font-family:'Plus Jakarta Sans',sans-serif; }

/* ================= CONTAINER (tidak ketutupan sidebar) ================= */
.container{
    margin-left:var(--sidebar-w);
    width:auto;              /* jangan pakai 100vw: bikin halaman melebar */
    max-width:none;
    min-width:0;
    padding:28px 32px 48px;
}

/* ================= HEADER ================= */
.page-header{
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    flex-wrap:wrap;
    gap:16px;
    margin-bottom:22px;
}
.page-title .eyebrow{
    font-size:12px;
    font-weight:700;
    letter-spacing:.08em;
    text-transform:uppercase;
    color:var(--primary);
    margin-bottom:6px;
}
.page-title h1{ font-size:26px; font-weight:700; color:var(--text-primary); }
.page-title p{ margin-top:4px; font-size:13.5px; color:var(--text-secondary); }
.route-rule{
    height:3px;
    width:64px;
    border-radius:2px;
    background:linear-gradient(90deg,var(--primary),var(--teal));
    margin-top:10px;
}

/* ================= FILTER BAR ================= */
.filter-box{
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:var(--radius-lg);
    padding:16px 18px;
    margin-bottom:22px;
    box-shadow:var(--shadow);
}
.filter-form{
    display:flex;
    align-items:flex-end;
    gap:14px;
    flex-wrap:wrap;
}
.field{ display:flex; flex-direction:column; gap:6px; }
.field label{
    font-size:11.5px;
    font-weight:600;
    color:var(--text-secondary);
    text-transform:uppercase;
    letter-spacing:.04em;
}
.filter-form input[type="date"],
.filter-form input[type="month"],
.filter-form select:not(.tomselected){
    height:38px;
    padding:0 12px;
    border:1px solid var(--border);
    border-radius:var(--radius-sm);
    outline:none;
    font-family:'Inter',sans-serif;
    font-size:13.5px;
    color:var(--text-primary);
    background:#fbfcfe;
    min-width:150px;
    transition:border-color .15s;
}
.filter-form input[type="date"]:focus,
.filter-form input[type="month"]:focus,
.filter-form select:not(.tomselected):focus{
    border-color:var(--primary);
    background:#fff;
}
.filter-actions{ display:flex; gap:8px; margin-left:auto; }

.btn{
    height:38px;
    padding:0 18px;
    border-radius:var(--radius-sm);
    border:none;
    font-size:13.5px;
    font-weight:600;
    cursor:pointer;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    text-decoration:none;
    transition:filter .15s, transform .15s;
}
.btn:active{ transform:translateY(1px); }
.btn-primary{ background:var(--primary); color:#fff; }
.btn-primary:hover{ filter:brightness(1.08); }
.btn-ghost{ background:var(--slate-light); color:var(--slate); }
.btn-ghost:hover{ filter:brightness(.97); }

/* ================= FILTER PULAU ================= */
.pulau-section{
    margin-top:16px;
    padding-top:16px;
    border-top:1px dashed var(--border);
}
.pulau-section-label{
    font-size:11.5px;
    font-weight:700;
    color:var(--text-secondary);
    text-transform:uppercase;
    letter-spacing:.04em;
    margin-bottom:10px;
}
.pulau-grid{
    display:grid;
    grid-template-columns:repeat(7, minmax(0,1fr));
    gap:14px;
}
.pulau-box{
    position:relative;
    border:1.5px solid var(--border);
    background:#fbfcfe;
    border-radius:var(--radius-md);
    padding:12px 10px;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    gap:4px;
    cursor:pointer;
    text-align:center;
    transition:all .15s ease;
    user-select:none;
}
.pulau-box:hover{
    border-color:var(--primary);
    background:var(--primary-light);
    transform:translateY(-2px);
}
.pulau-box .pulau-icon{ font-size:18px; line-height:1; }
.pulau-box .pulau-name{
    font-size:11.5px;
    font-weight:700;
    color:var(--text-secondary);
    text-transform:uppercase;
    letter-spacing:.03em;
}
.pulau-box .pulau-count{ font-size:10.5px; color:var(--text-muted); }
.pulau-box.active,
.pulau-box.active:hover{
    border-color:var(--primary);
    background:linear-gradient(135deg,var(--primary),#3730a3);
    box-shadow:0 8px 18px rgba(79,70,229,.28);
}
.pulau-box.active .pulau-name,
.pulau-box.active .pulau-count{ color:#fff; }
.pulau-box.active::after{
    content:"✓";
    position:absolute;
    top:6px;
    right:8px;
    width:18px;
    height:18px;
    border-radius:50%;
    background:#fff;
    color:var(--primary);
    font-size:12px;
    font-weight:800;
    line-height:18px;
    text-align:center;
}
.pulau-selected{
    display:flex;
    align-items:center;
    flex-wrap:wrap;
    gap:6px;
    margin-bottom:10px;
    font-size:12.5px;
    color:var(--text-secondary);
}
.pulau-chip{
    display:inline-flex;
    align-items:center;
    padding:3px 10px;
    border-radius:999px;
    background:var(--primary-light);
    color:var(--primary);
    font-weight:700;
    font-size:12px;
}

/* ================= KPI CARDS ================= */
.section-label{
    font-size:12.5px;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.06em;
    color:var(--text-muted);
    margin:26px 0 12px;
}
.kpi-row{
    display:grid;
    grid-template-columns:repeat(9,minmax(0,1fr));
    gap:14px;
}
.card{
    background:var(--surface);
    border:1px solid var(--border);
    border-left:4px solid var(--primary);
    border-radius:var(--radius-md);
    padding:16px 18px;
    box-shadow:var(--shadow);
    display:block;
    text-decoration:none;
    color:inherit;
    transition:transform .2s ease, box-shadow .2s ease;
}
.card:hover{ transform:translateY(-4px); box-shadow:0 15px 25px rgba(0,0,0,.12); }
.card h4{
    font-size:12px;
    font-weight:600;
    color:var(--text-secondary);
    text-transform:uppercase;
    letter-spacing:.03em;
    margin-bottom:8px;
}
.card h1{
    font-size:26px;
    font-weight:700;
    font-family:'Plus Jakarta Sans',sans-serif;
    color:var(--text-primary);
}
.card:hover h1{ color:var(--primary); }

.card.blue{ border-left-color:var(--primary); background:linear-gradient(90deg, rgba(79,70,229,.16) 0%, rgba(79,70,229,.03) 55%, #fff 100%); }
.card.green{ border-left-color:var(--green); background:linear-gradient(90deg, rgba(22,163,74,.16) 0%, rgba(22,163,74,.03) 55%, #fff 100%); }
.card.red{ border-left-color:var(--red); background:linear-gradient(90deg, rgba(225,29,72,.16) 0%, rgba(225,29,72,.03) 55%, #fff 100%); }
.card.teal{ border-left-color:var(--teal); background:linear-gradient(90deg, rgba(13,148,136,.16) 0%, rgba(13,148,136,.03) 55%, #fff 100%); }
.card.orange{ border-left-color:var(--amber); background:linear-gradient(90deg, rgba(245,158,11,.18) 0%, rgba(245,158,11,.03) 55%, #fff 100%); }
.card.purple{ border-left-color:var(--purple); background:linear-gradient(90deg, rgba(139,92,246,.16) 0%, rgba(139,92,246,.03) 55%, #fff 100%); }
.card.dark{ border-left-color:var(--slate); background:linear-gradient(90deg, rgba(51,65,85,.14) 0%, rgba(51,65,85,.03) 55%, #fff 100%); }

/* ================= HERO TOTALS ================= */
.total-row{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:14px;
}
.hero-card{
    border-radius:var(--radius-lg);
    padding:20px 22px;
    color:#fff;
    box-shadow:var(--shadow);
    display:flex;
    flex-direction:column;
    gap:8px;
    min-height:110px;
    justify-content:center;
}
.hero-card h3{
    font-size:12.5px;
    font-weight:600;
    text-transform:uppercase;
    letter-spacing:.04em;
    opacity:.85;
}
.hero-card .hero-value{
    font-size:24px;
    font-weight:700;
    font-family:'Plus Jakarta Sans',sans-serif;
}
.hero-card.hero-green{ background:linear-gradient(135deg,#16a34a,#0d9488); }
.hero-card.hero-blue{ background:linear-gradient(135deg,#4f46e5,#3730a3); }
.hero-card.hero-red{ background:linear-gradient(135deg,#e11d48,#9f1239); }

/* ================= CHART ================= */
.chart-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:16px;
}
.chart-box{
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:var(--radius-lg);
    padding:18px 20px;
    box-shadow:var(--shadow);
    height:340px;
    display:flex;
    flex-direction:column;
    min-width:0;
}
.chart-box h3{
    font-size:14.5px;
    font-weight:600;
    margin-bottom:12px;
    color:var(--text-primary);
    display:flex;
    align-items:center;
    gap:8px;
}
.chart-wrapper{ flex:1; position:relative; min-height:0; }

/* ================= TABLE ================= */
.table-box{
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:var(--radius-lg);
    padding:20px 22px;
    box-shadow:var(--shadow);
    min-width:0;
}
.table-box h3{
    font-size:15px;
    font-weight:600;
    margin-bottom:14px;
    display:flex;
    align-items:center;
    gap:8px;
}
.table-scroll{ overflow-x:auto; }

table{ width:100%; border-collapse:collapse; font-size:13.5px; }
thead th{
    background:var(--slate-light);
    color:var(--text-secondary);
    padding:10px 12px;
    font-size:11.5px;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.03em;
    text-align:left;
    white-space:nowrap;
}
thead th:first-child{ border-top-left-radius:var(--radius-sm); }
thead th:last-child{ border-top-right-radius:var(--radius-sm); }
tbody td{
    padding:10px 12px;
    border-bottom:1px solid var(--border);
    color:var(--text-primary);
}
tbody tr:hover{ background:#fafbff; }
tbody tr:last-child td{ border-bottom:none; }

.num{ text-align:right; font-variant-numeric:tabular-nums; }
.center{ text-align:center; }

.badge{
    display:inline-block;
    padding:3px 10px;
    border-radius:999px;
    font-size:12px;
    font-weight:600;
}
.badge-green{ background:var(--green-light); color:var(--green); }
.badge-red{ background:var(--red-light); color:var(--red); }

.empty-row{ text-align:center; color:var(--text-muted); padding:22px 0; }

/* ================= TABEL ALERT (Planner & PIC) ================= */
.tbl-alert thead th{ white-space:normal; line-height:1.3; vertical-align:bottom; }
.tbl-alert thead th small{ font-weight:500; text-transform:none; letter-spacing:0; color:var(--text-muted); }
.tbl-alert tbody td{ vertical-align:middle; }
.tbl-alert td:first-child{ font-weight:600; white-space:nowrap; }

.alert-cell{
    display:flex;
    flex-wrap:wrap;
    gap:4px;
    min-width:170px;
}
.alert-cell .badge{
    white-space:nowrap;        /* "Nov 2026: 341" tidak patah */
    font-size:11.5px;
    padding:3px 9px;
    text-decoration:none;
}
a.badge.badge-red:hover{ filter:brightness(.93); }

/* ================= LAYOUT STACK ================= */
.stack{
    display:flex;
    flex-direction:column;
    gap:22px;
    margin-top:22px;
}
.two-col{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:16px;
}
.one-col{
    display:grid;
    grid-template-columns:minmax(0,1fr);
    gap:16px;
}
.two-col > *, .one-col > *{ min-width:0; }

/* ================= PETA ================= */
#shipmentMap{ height:480px; border-radius:var(--radius-lg); overflow:hidden; }
.map-legend{
    display:flex;
    gap:18px;
    flex-wrap:wrap;
    margin-top:14px;
    font-size:12.5px;
    color:var(--text-secondary);
}
.map-legend span{ display:inline-flex; align-items:center; gap:6px; }
.map-legend i{ width:12px; height:12px; border-radius:50%; display:inline-block; }

/* ================= TOM SELECT ================= */
.filter-form .field .ts-wrapper{ width:260px; min-width:260px; max-width:420px; }
.filter-form .ts-control{
    min-height:38px;
    padding:4px 8px;
    border:1px solid var(--border);
    border-radius:var(--radius-sm);
    background:#fbfcfe;
    font-size:13.5px;
    box-shadow:none;
}
.filter-form .ts-wrapper.focus .ts-control{ border-color:var(--primary); background:#fff; box-shadow:none; }
.filter-form .ts-wrapper.multi .ts-control{ flex-wrap:wrap; }
.filter-form .ts-wrapper.multi .ts-control > div{ margin:2px 4px 2px 0; }
.filter-form .ts-wrapper.multi .ts-control > input{
    height:auto;
    min-width:60px;
    flex:1 1 60px;
    padding:0;
    border:none;
    background:transparent;
}
.ts-dropdown{ font-size:13.5px; border-radius:var(--radius-sm); }

/* ================= RESPONSIVE ================= */
@media(max-width:1400px){
    .kpi-row{ grid-template-columns:repeat(4,minmax(0,1fr)); }
    .pulau-grid{ grid-template-columns:repeat(4,minmax(0,1fr)); }
    .chart-grid, .two-col{ grid-template-columns:minmax(0,1fr); }
    .total-row{ grid-template-columns:minmax(0,1fr); }
}
@media(max-width:768px){
    .container{ margin-left:0; width:100%; padding:18px; }
    .kpi-row, .pulau-grid{ grid-template-columns:repeat(2,minmax(0,1fr)); }
    .filter-actions{ margin-left:0; width:100%; }
    .filter-form select:not(.tomselected),
    .filter-form input[type="date"],
    .filter-form input[type="month"]{ min-width:130px; flex:1; }
    .filter-form .field .ts-wrapper{ min-width:130px; width:100%; flex:1; }
    #shipmentMap{ height:340px; }
}

</style>
</head>

<body>

<div class="container">

<!-- HEADER -->
<div class="page-header">
    <div class="page-title">
        <div class="eyebrow">{{ $eyebrowDashboard }}</div>
        <h1>{{ $judulDashboard }}</h1>
        <p>Ringkasan performa gudang, armada, dan ketepatan waktu pengiriman.</p>
        <div class="route-rule"></div>
    </div>
</div>

<!-- FILTER -->
<div class="filter-box">
    <form method="GET" action="{{ url()->current() }}" class="filter-form" id="filterForm">
        <div class="field">
            <label for="f-basis">Basis tanggal</label>
            <select id="f-basis" name="tgl_basis">
                <option value="periode_shipment" {{ request('tgl_basis', 'periode_shipment') == 'periode_shipment' ? 'selected' : '' }}>Periode Shipment</option>
                <option value="rencana_kirim" {{ request('tgl_basis') == 'rencana_kirim' ? 'selected' : '' }}>Rencana Kirim</option>
                <option value="tanggal_naik_logistik" {{ request('tgl_basis') == 'tanggal_naik_logistik' ? 'selected' : '' }}>Tanggal Naik Logistik</option>
            </select>
        </div>

        <div class="field">
            <label for="f-from">Periode dari</label>
            <input id="f-from" type="date" name="date_from" value="{{ request('date_from') }}">
        </div>

        <div class="field">
            <label for="f-to">Sampai</label>
            <input id="f-to" type="date" name="date_to" value="{{ request('date_to') }}">
        </div>

        <div class="field">
            <label for="f-month">Bulan</label>
            <select id="f-month" name="month[]" multiple>
                @for($y = date('Y') + 1; $y >= 2020; $y--)
                    @for($m = 12; $m >= 1; $m--)
                        @php $val = sprintf('%d-%02d', $y, $m); @endphp
                        <option value="{{ $val }}" {{ in_array($val, $monthSel) ? 'selected' : '' }}>
                            {{ $namaBulan[sprintf('%02d', $m)] }} {{ $y }}
                        </option>
                    @endfor
                @endfor
            </select>
        </div>

        <div class="field">
            <label for="f-year">Tahun</label>
            <select id="f-year" name="year">
                <option value="">Semua tahun</option>
                @for($y = date('Y'); $y >= 2020; $y--)
                    <option value="{{ $y }}" {{ request('year') == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>
        </div>

        <div class="field">
            <label for="f-area">Area</label>
            <select id="f-area" name="area[]" multiple>
                @foreach($list_area as $a)
                    <option value="{{ $a->area }}" {{ in_array($a->area, $areaSel) ? 'selected' : '' }}>{{ $a->area }}</option>
                @endforeach
            </select>
        </div>

        @if(isset($list_dist_channel))
        <div class="field">
            <label for="f-dist">Dist. channel</label>
            <select id="f-dist" name="dist_channel[]" multiple>
                @foreach($list_dist_channel as $d)
                    <option value="{{ $d->dist_channel }}" {{ in_array($d->dist_channel, $distSel) ? 'selected' : '' }}>{{ $d->dist_channel }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="f-sumber">Sumber</label>
            <select id="f-sumber" name="sumber">
                <option value="">Jakarta + Pasuruan</option>
                <option value="JAKARTA"  {{ request('sumber')=='JAKARTA'  ? 'selected' : '' }}>Jakarta</option>
                <option value="PASURUAN" {{ request('sumber')=='PASURUAN' ? 'selected' : '' }}>Pasuruan</option>
            </select>
        </div>
        @endif

        <!-- Filter pulau dikirim lewat hidden input ini -->
        <div id="pulauInputs">
            @foreach($pulauSel as $p)
                <input type="hidden" name="pulau[]" value="{{ $p }}">
            @endforeach
        </div>

        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">Terapkan filter</button>
            <a href="{{ url()->current() }}" class="btn btn-ghost">Reset</a>
        </div>
    </form>

    <!-- FILTER PULAU -->
    <div class="pulau-section">
        <div class="pulau-section-label">📍 Filter cepat berdasarkan pulau</div>
        <div class="pulau-selected" id="pulauSelected"></div>

        @php
            $pulauList = [
                'JAWA'       => ['🏝️', 'Jawa'],
                'SUMATERA'   => ['🌋', 'Sumatera'],
                'KALIMANTAN' => ['🌳', 'Kalimantan'],
                'SULAWESI'   => ['🐚', 'Sulawesi'],
                // 'BALI_NUSRA' => ['🏖️', 'Bali & Nusra'],
                'MALUKU'     => ['🌊', 'Maluku'],
                'PAPUA'      => ['🦅', 'Papua'],
            ];
        @endphp

        <div class="pulau-grid" id="pulauGrid">
            <div class="pulau-box {{ empty($pulauSel) ? 'active' : '' }}" data-pulau="">
                <div class="pulau-icon">🗺️</div>
                <div class="pulau-name">Semua Pulau</div>
                <div class="pulau-count" data-count-for="">-</div>
            </div>

            @foreach($pulauList as $kode => [$ikon, $nama])
                <div class="pulau-box {{ in_array($kode, $pulauSel) ? 'active' : '' }}" data-pulau="{{ $kode }}">
                    <div class="pulau-icon">{{ $ikon }}</div>
                    <div class="pulau-name">{{ $nama }}</div>
                    <div class="pulau-count" data-count-for="{{ $kode }}">-</div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<!-- KPI -->
<div class="section-label">Ringkasan status</div>
<div class="kpi-row">
    <a href="{{ $linkKpi('total') }}" class="card blue">
        <h4>Total Shipment</h4>
        <h1>{{ $total_data }}</h1>
    </a>

    <a href="{{ route($transitRoute, $qs) }}" class="card teal">
        <h4>🚚 In Transit</h4>
        <h1>{{ $total_in_transit ?? 0 }}</h1>
    </a>

    <a href="{{ $linkKpi('gudang_ontime') }}" class="card green">
        <h4>Sudah Tiba Di Gudang</h4>
        <h1>{{ $gudang_ontime }}</h1>
    </a>

    <a href="{{ $linkKpi('sla_delay') }}" class="card red">
        <h4>Belum Tiba Di Gudang</h4>
        <h1>{{ $total_sla_delay }}</h1>
    </a>

    <a href="{{ $linkKpi('belum_armada') }}" class="card red">
        <h4>Belum Dapat Armada</h4>
        <h1>{{ $gudang_delay }}</h1>
    </a>

    <a href="{{ $linkKpi('tujuan_ontime') }}" class="card teal">
        <h4>Tiba Tujuan OnTime</h4>
        <h1>{{ $customer_ontime }}</h1>
    </a>

    <a href="{{ $linkKpi('tujuan_delay') }}" class="card orange">
        <h4>Tiba Tujuan Delay</h4>
        <h1>{{ $customer_delay }}</h1>
    </a>

    <a href="{{ $linkKpi('bongkar_ontime') }}" class="card purple">
        <h4>Bongkar OnTime</h4>
        <h1>{{ $bongkar_ontime }}</h1>
    </a>

    <a href="{{ $linkKpi('bongkar_delay') }}" class="card dark">
        <h4>Bongkar Delay</h4>
        <h1>{{ $bongkar_delay }}</h1>
    </a>
</div>

<!-- HERO TOTALS -->
<div class="section-label">Nilai &amp; biaya</div>
<div class="total-row">
    <div class="hero-card hero-green">
        <h3>Total nilai muatan</h3>
        <div class="hero-value">Rp {{ number_format($totalNilaiMuatan,0,',','.') }}</div>
    </div>

    <div class="hero-card hero-blue">
        <h3>Total biaya kirim</h3>
        <div class="hero-value">Rp {{ number_format($totalBiayaKirim,0,',','.') }}</div>
    </div>

    <div class="hero-card hero-red">
        <h3>Cost ratio</h3>
        @php
            $ratio = ($totalNilaiMuatan ?? 0) > 0
                ? ($totalBiayaKirim / $totalNilaiMuatan) * 100
                : 0;
        @endphp
        <div class="hero-value">{{ number_format($ratio, 2) }}%</div>
    </div>
</div>

<div class="stack">

    <!-- PETA SEBARAN -->
    <div class="table-box">
        <div id="shipmentMap"></div>

        <div class="map-legend">
            <span><i style="background:#16a34a;"></i> Nilai muatan rendah</span>
            <span><i style="background:#f59e0b;"></i> Nilai muatan sedang</span>
            <span><i style="background:#e11d48;"></i> Nilai muatan tinggi</span>
        </div>

        <div id="mapDebugBox" style="margin-top:14px;"></div>
    </div>

    <!-- CHART -->
    <div class="chart-grid">
        <div class="chart-box">
            <h3>📍 SLA bongkar</h3>
            <div class="chart-wrapper"><canvas id="chartBongkar"></canvas></div>
        </div>

        <div class="chart-box">
            <h3>🌍 Summary area</h3>
            <div class="chart-wrapper"><canvas id="chartArea"></canvas></div>
        </div>

        <div class="chart-box">
            <h3>📦 Kategori ekspedisi</h3>
            <div class="chart-wrapper"><canvas id="chartEkspedisi"></canvas></div>
        </div>

        <div class="chart-box">
            <h3>📍 Top tujuan</h3>
            <div class="chart-wrapper"><canvas id="chartTujuan"></canvas></div>
        </div>
    </div>

    <!-- TABLE AREA -->
    <div class="table-box">
        <h3>📍 Summary area detail</h3>
        <div class="table-scroll">
        <table>
            <thead>
            <tr>
                <th>No</th>
                <th>Area</th>
                <th class="num">Shipment</th>
                <th class="num">Biaya</th>
                <th class="num">Muatan</th>
            </tr>
            </thead>
            <tbody>
            @forelse($summary_area->take(10) as $key => $a)
            <tr>
                <td>{{ $key+1 }}</td>
                <td>{{ $a->area }}</td>
                <td class="num">{{ number_format($a->total_shipment) }}</td>
                <td class="num">Rp {{ number_format($a->total_biaya,0,',','.') }}</td>
                <td class="num">Rp {{ number_format($a->total_muatan,0,',','.') }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="empty-row">Belum ada data</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>

    <!-- TABLE TUJUAN -->
    <div class="table-box">
        <h3>📍 Summary tujuan detail</h3>
        <div class="table-scroll">
        <table>
            <thead>
            <tr>
                <th>No</th>
                <th>Tujuan</th>
                <th class="num">Shipment</th>
                <th class="num">Biaya</th>
                <th class="num">Muatan</th>
            </tr>
            </thead>
            <tbody>
            @forelse($summary_tujuan->take(10) as $key => $t)
            <tr>
                <td>{{ $key+1 }}</td>
                <td>{{ $t->tujuan }}</td>
                <td class="num">{{ number_format($t->total_shipment) }}</td>
                <td class="num">Rp {{ number_format($t->total_biaya,0,',','.') }}</td>
                <td class="num">Rp {{ number_format($t->total_muatan,0,',','.') }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="empty-row">Belum ada data tujuan</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>

    <!-- SUMMARY PLANNER + PIC MONITORING (satu tabel per baris) -->
    <div class="one-col">

        <div class="table-box">
            <h3>👤 Summary Planner</h3>
            <div class="table-scroll">
            <table class="tbl-alert">
                <thead>
                    <tr>
                        <th>Planner</th>
                        <th class="num">Jumlah</th>
                        <th class="num">Belum Isi No Shipment</th>
                        <th class="num">On Time</th>
                        <th class="num">Delay</th>
                        <th>Alert per Periode<br><small>keluar gudang terakhir belum diinput</small></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($summary_planner as $p)
                    <tr>
                        <td>{{ $p->planner }}</td>
                        <td class="num">{{ number_format($p->total) }}</td>
                        <td class="num">
                            <span class="badge {{ $p->total_belum_rencana > 0 ? 'badge-red' : 'badge-green' }}">
                                {{ number_format($p->total_belum_rencana) }}
                            </span>
                        </td>
                        <td class="num"><span class="badge badge-green">{{ number_format($p->total_ontime) }}</span></td>
                        <td class="num"><span class="badge badge-red">{{ number_format($p->total_delay) }}</span></td>
                        <td>@include('gabungan._alert_periode', [
                            'items' => $alert_planner[$p->planner] ?? collect(),
                            'tipe'  => 'planner',
                            'nama'  => $p->planner,
                        ])</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-row">Belum ada data planner</td></tr>
                @endforelse
                </tbody>
            </table>
            </div>
        </div>

        <div class="table-box">
            <h3>🕵️ Summary PIC Monitoring</h3>
            <div class="table-scroll">
            <table class="tbl-alert">
                <thead>
                    <tr>
                        <th>PIC Monitoring</th>
                        <th class="num">Jumlah</th>
                        <th class="num">On Time</th>
                        <th class="num">Delay</th>
                        <th class="num">Belum Input Tgl Tiba</th>
                        <th class="num">Belum Tiba &amp; Lewat Estimasi</th>
                        <th>Belum Input Sama Sekali</th>
                        <th>Sudah Keluar Gudang, Belum Input Tiba</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($summary_pic_monitoring as $m)
                    <tr>
                        <td>{{ $m->pic_monitoring }}</td>
                        <td class="num">{{ number_format($m->total) }}</td>
                        <td class="num"><span class="badge badge-green">{{ number_format($m->total_ontime) }}</span></td>
                        <td class="num"><span class="badge badge-red">{{ number_format($m->total_delay) }}</span></td>
                        <td class="num">
                            <span class="badge {{ $m->total_belum_tiba > 0 ? 'badge-red' : 'badge-green' }}">
                                {{ number_format($m->total_belum_tiba) }}
                            </span>
                        </td>
                        <td class="num">
                            <span class="badge {{ $m->total_lewat_estimasi > 0 ? 'badge-red' : 'badge-green' }}">
                                {{ number_format($m->total_lewat_estimasi) }}
                            </span>
                        </td>
                        <td>@include('gabungan._alert_periode', [
                            'items' => $alert_pic_belum[$m->pic_monitoring] ?? collect(),
                            'tipe'  => 'monitoring_belum',
                            'nama'  => $m->pic_monitoring,
                        ])</td>
                        <td>@include('gabungan._alert_periode', [
                            'items' => $alert_pic_sudah[$m->pic_monitoring] ?? collect(),
                            'tipe'  => 'monitoring_sudah',
                            'nama'  => $m->pic_monitoring,
                        ])</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty-row">Belum ada data PIC monitoring</td></tr>
                @endforelse
                </tbody>
            </table>
            </div>
        </div>

    </div>

    <!-- MONITORING + PLANNER (ringkasan persentase) -->
    <div class="two-col">

        <div class="table-box">
            <h3>📊 Summary monitoring</h3>
            <table>
                <thead>
                    <tr>
                        <th>Kategori</th>
                        <th class="num">Total</th>
                        <th class="num">Persentase</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>On time tiba</td>
                        <td class="num">{{ number_format($customer_ontime) }}</td>
                        <td class="num"><span class="badge badge-green">{{ number_format($summary_monitoring['tiba_ontime'],2) }}%</span></td>
                    </tr>
                    <tr>
                        <td>Delay tiba</td>
                        <td class="num">{{ number_format($customer_delay) }}</td>
                        <td class="num"><span class="badge badge-red">{{ number_format($summary_monitoring['tiba_delay'],2) }}%</span></td>
                    </tr>
                    <tr>
                        <td>Bongkar on time</td>
                        <td class="num">{{ number_format($bongkar_ontime) }}</td>
                        <td class="num"><span class="badge badge-green">{{ number_format($summary_monitoring['bongkar_ontime'],2) }}%</span></td>
                    </tr>
                    <tr>
                        <td>Bongkar delay</td>
                        <td class="num">{{ number_format($bongkar_delay) }}</td>
                        <td class="num"><span class="badge badge-red">{{ number_format($summary_monitoring['bongkar_delay'],2) }}%</span></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="table-box">
            <h3>📋 Summary planner</h3>
            <table>
                <thead>
                    <tr>
                        <th>Kategori</th>
                        <th class="num">Total</th>
                        <th class="num">Persentase</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>On time</td>
                        <td class="num">{{ number_format($planner_ontime) }}</td>
                        <td class="num"><span class="badge badge-green">{{ number_format($ontime_rate,2) }}%</span></td>
                    </tr>
                    <tr>
                        <td>Delay</td>
                        <td class="num">{{ number_format($planner_delay) }}</td>
                        <td class="num"><span class="badge badge-red">{{ number_format($delay_rate,2) }}%</span></td>
                    </tr>
                    <tr>
                        <td>Armada ready</td>
                        <td class="num">{{ number_format($planner_armada) }}</td>
                        <td class="num"><span class="badge badge-green">{{ number_format($armada_rate,2) }}%</span></td>
                    </tr>
                    <tr>
                        <td>Pending armada</td>
                        <td class="num">{{ number_format($planner_belum_armada) }}</td>
                        <td class="num"><span class="badge badge-red">{{ number_format(100 - $armada_rate,2) }}%</span></td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>
</div>

</div>

<!-- ================= JS ================= -->
<script>

const labels = @json($label ?? []);
const values = @json($value ?? []);

Chart.defaults.font = { family: "'Inter', sans-serif", size: 12 };
Chart.defaults.color = '#64748b';

const barOpts = {
    responsive:true,
    maintainAspectRatio:false,
    plugins:{ legend:{ display:false } },
    scales:{ y:{ beginAtZero:true, grid:{ color:'#eef0f5' } }, x:{ grid:{ display:false } } }
};

new Chart(document.getElementById('chartEkspedisi'), {
    type:'bar',
    data:{ labels:labels, datasets:[{ label:'Kategori Ekspedisi', data:values, backgroundColor:'#4f46e5', borderRadius:6, maxBarThickness:36 }] },
    options:barOpts
});

new Chart(document.getElementById('chartBongkar'), {
    type:'doughnut',
    data:{
        labels:['On Time','Delay'],
        datasets:[{ data:[{{ $bongkar_ontime }},{{ $bongkar_delay }}], backgroundColor:['#8b5cf6','#e11d48'], borderWidth:0 }]
    },
    options:{ responsive:true, maintainAspectRatio:false, cutout:'65%', plugins:{ legend:{ position:'bottom' } } }
});

new Chart(document.getElementById('chartArea'), {
    type:'bar',
    data:{
        labels:@json($summary_area->take(5)->pluck('area')),
        datasets:[{ data:@json($summary_area->take(5)->pluck('total_shipment')), backgroundColor:'#0d9488', borderRadius:6, maxBarThickness:36 }]
    },
    options:barOpts
});

new Chart(document.getElementById('chartTujuan'), {
    type:'bar',
    data:{
        labels:@json($summary_tujuan->take(5)->pluck('tujuan')),
        datasets:[{ data:@json($summary_tujuan->take(5)->pluck('total_shipment')), backgroundColor:'#f59e0b', borderRadius:6, maxBarThickness:36 }]
    },
    options:barOpts
});

// ================= PETA SEBARAN NILAI MUATAN PER AREA =================
const summaryAreaMap = @json($summary_area ?? []);

// Key HARUS sama persis dengan nilai kolom area.
const koordinatArea = {
    'BENGKULU'              : [-3.7928, 102.2608],
    'ACEH'                  : [5.5483, 95.3238],
    'BANTEN'                : [-6.1149, 106.1503],
    'GORONTALO'             : [0.5435, 123.0568],
    'JAMBI'                 : [-1.6101, 103.6131],
    'JABODEBEK'             : [-6.2088, 106.8456],
    'JAWA_BARAT'            : [-6.9175, 107.6191],
    'JAWA_TENGAH'           : [-6.9667, 110.4167],
    'JAWA_TIMUR'            : [-7.2575, 112.7521],
    'KALIMANTAN_BARAT'      : [-0.0263, 109.3425],
    'KALIMANTAN_SELATAN'    : [-3.3194, 114.5908],
    'KALIMANTAN_TENGAH'     : [-2.2090, 113.9213],
    'KALIMANTAN_TIMUR'      : [-0.5022, 117.1536],
    'KALIMANTAN_UTARA'      : [2.8383, 117.3676],
    'KEP._BANGKA_BELITUNG'  : [-2.1316, 106.1169],
    'KEP._RIAU'             : [0.9186, 104.4562],
    'LAMPUNG'               : [-5.4293, 105.2610],
    'NUSA_TENGGARA_BARAT'   : [-8.5833, 116.1167],
    'NUSA_TENGGARA_TIMUR'   : [-10.1772, 123.6070],
    'PAPUA_BARAT'           : [-0.8615, 134.0620],
    'PAPUA_BARAT_DAYA'      : [-0.8762, 131.2558],
    'PAPUA_SELATAN'         : [-8.4667, 140.4000],
    'PAPUA_TENGAH'          : [-3.6079, 135.9591],
    'PROV._BALI'            : [-8.6705, 115.2126],
    'PROV._MALUKU'          : [-3.6954, 128.1814],
    'PROV._MALUKU_UTARA'    : [0.7833, 127.3667],
    'PROV._PAPUA'           : [-2.5337, 140.7181],
    'RIAU'                  : [0.5333, 101.4500],
    'SULAWESI_BARAT'        : [-2.6784, 118.8879],
    'SULAWESI_SELATAN'      : [-5.1477, 119.4327],
    'SULAWESI_TENGAH'       : [-0.8917, 119.8707],
    'SULAWESI_TENGGARA'     : [-3.9450, 122.4989],
    'SULAWESI_UTARA'        : [1.4748, 124.8421],
    'SUMATERA_BARAT'        : [-0.9471, 100.4172],
    'SUMATERA_SELATAN'      : [-2.9761, 104.7754],
    'SUMATERA_UTARA'        : [3.5952, 98.6722],
    'YOGYAKARTA'            : [-7.7956, 110.3695],
};

// WAJIB sinkron dengan PULAU_MAP di controller.
const pulauAreaMap = {
    'JAWA': ['JABODEBEK','BANTEN','JAWA_BARAT','JAWA_TENGAH','JAWA_TIMUR','YOGYAKARTA'],
    'SUMATERA': ['ACEH','SUMATERA_UTARA','SUMATERA_BARAT','RIAU','KEP._RIAU','JAMBI','SUMATERA_SELATAN','BENGKULU','LAMPUNG','KEP._BANGKA_BELITUNG'],
    'KALIMANTAN': ['KALIMANTAN_BARAT','KALIMANTAN_TENGAH','KALIMANTAN_SELATAN','KALIMANTAN_TIMUR','KALIMANTAN_UTARA'],
    'SULAWESI': ['SULAWESI_UTARA','SULAWESI_TENGAH','SULAWESI_SELATAN','SULAWESI_TENGGARA','SULAWESI_BARAT','GORONTALO'],
    'BALI_NUSRA': ['PROV._BALI','NUSA_TENGGARA_BARAT','NUSA_TENGGARA_TIMUR'],
    'MALUKU': ['PROV._MALUKU','PROV._MALUKU_UTARA'],
    'PAPUA': ['PROV._PAPUA','PAPUA_BARAT','PAPUA_BARAT_DAYA','PAPUA_SELATAN','PAPUA_TENGAH'],
};

function normalisasiNama(str){
    return (str || '').toString().trim().toUpperCase();
}

// Isi angka "jumlah area" di tiap kotak pulau
(function isiJumlahAreaPulau(){
    const counts = {};
    Object.keys(pulauAreaMap).forEach(p => counts[p] = 0);

    summaryAreaMap.forEach(a => {
        const key = normalisasiNama(a.area);
        for (const pulau in pulauAreaMap) {
            if (pulauAreaMap[pulau].includes(key)) { counts[pulau]++; break; }
        }
    });

    document.querySelectorAll('[data-count-for]').forEach(el => {
        const p = el.getAttribute('data-count-for');
        el.textContent = (p === '' ? summaryAreaMap.length : (counts[p] || 0)) + ' area';
    });
})();

// ================= AUTO-FILTER =================
const JEDA_AUTO_FILTER = 700; // ms
let timerAutoFilter = null;
function jadwalkanSubmit() {
    clearTimeout(timerAutoFilter);
    timerAutoFilter = setTimeout(function () {
        document.getElementById('filterForm').submit();
    }, JEDA_AUTO_FILTER);
}

// Kotak pulau
(function () {
    const boxes   = Array.from(document.querySelectorAll('.pulau-box'));
    const semua   = boxes.find(b => b.getAttribute('data-pulau') === '');
    const wrap    = document.getElementById('pulauInputs');
    const ringkas = document.getElementById('pulauSelected');

    function sinkron() {
        const dipilih = boxes.filter(b =>
            b.getAttribute('data-pulau') !== '' && b.classList.contains('active'));

        semua.classList.toggle('active', dipilih.length === 0);

        wrap.innerHTML = '';
        dipilih.forEach(b => {
            const inp = document.createElement('input');
            inp.type  = 'hidden';
            inp.name  = 'pulau[]';
            inp.value = b.getAttribute('data-pulau');
            wrap.appendChild(inp);
        });

        ringkas.innerHTML = dipilih.length === 0
            ? 'Dipilih: <span class="pulau-chip">Semua pulau</span>'
            : 'Dipilih: ' + dipilih.map(b =>
                '<span class="pulau-chip">' + b.querySelector('.pulau-name').textContent + '</span>'
              ).join('');
    }

    // keadaan awal diambil dari URL
    const params  = new URLSearchParams(location.search);
    const dariUrl = params.getAll('pulau[]').concat(params.getAll('pulau')).filter(Boolean);
    boxes.forEach(b => {
        const p = b.getAttribute('data-pulau');
        if (p !== '') b.classList.toggle('active', dariUrl.includes(p));
    });
    sinkron();

    boxes.forEach(box => {
        box.addEventListener('click', function () {
            if (this.getAttribute('data-pulau') === '') {
                boxes.forEach(b => b.classList.remove('active'));
            } else {
                this.classList.toggle('active');
            }
            sinkron();
            jadwalkanSubmit();
        });
    });
})();

// Format Rupiah ringkas (mis. 125Jt, 2.3M, 1.1T)
function formatRupiahRingkas(value){
    value = Number(value) || 0;
    const abs = Math.abs(value);
    if (abs >= 1e12) return (value/1e12).toFixed(1).replace(/\.0$/,'') + 'T';
    if (abs >= 1e9)  return (value/1e9).toFixed(1).replace(/\.0$/,'') + 'M';
    if (abs >= 1e6)  return (value/1e6).toFixed(1).replace(/\.0$/,'') + 'Jt';
    if (abs >= 1e3)  return (value/1e3).toFixed(1).replace(/\.0$/,'') + 'Rb';
    return value.toString();
}

if (document.getElementById('shipmentMap')) {

    const shipmentMap = L.map('shipmentMap').setView([-2.5, 118], 5);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 18
    }).addTo(shipmentMap);

    let maxMuatan = 1;
    summaryAreaMap.forEach(a => {
        if (Number(a.total_muatan) > maxMuatan) maxMuatan = Number(a.total_muatan);
    });

    const boundsList = [];

    summaryAreaMap.forEach(a => {
        const koordinat = koordinatArea[normalisasiNama(a.area)];
        if (!koordinat) return;

        const nilaiMuatan = Number(a.total_muatan) || 0;
        const ratio = nilaiMuatan / maxMuatan;

        let warna = '#16a34a';
        if (ratio > 0.66) warna = '#e11d48';
        else if (ratio > 0.33) warna = '#f59e0b';

        const radius = 16 + (ratio * 24);

        const icon = L.divIcon({
            className: '',
            html: `<div style="width:${radius*2}px;height:${radius*2}px;border-radius:50%;background:${warna};
                    border:3px solid #fff;box-shadow:0 4px 10px rgba(0,0,0,.25);display:flex;align-items:center;
                    justify-content:center;color:#fff;font-weight:700;font-size:${radius > 26 ? 13 : 11}px;
                    white-space:nowrap;">${formatRupiahRingkas(nilaiMuatan)}</div>`,
            iconSize: [radius*2, radius*2],
            iconAnchor: [radius, radius]
        });

        L.marker(koordinat, { icon: icon }).addTo(shipmentMap).bindPopup(`
            <strong>${a.area}</strong><br>
            Jumlah shipment: <b>${a.total_shipment}</b><br>
            Total nilai muatan: <b>Rp ${nilaiMuatan.toLocaleString('id-ID')}</b><br>
            Total biaya kirim: Rp ${Number(a.total_biaya).toLocaleString('id-ID')}
        `);

        boundsList.push(koordinat);
    });

    if (boundsList.length > 0) shipmentMap.fitBounds(boundsList, { padding: [40, 40] });

    const debugBox = document.getElementById('mapDebugBox');
    if (debugBox && summaryAreaMap.length === 0) {
        debugBox.innerHTML = `<div style="background:#fdeef2;border:1px solid #e11d48;color:#9f1239;
                        padding:12px 14px;border-radius:10px;font-size:13px;">
                        ⚠️ Data area kosong untuk filter yang sedang aktif.</div>`;
    }
}

// ================= DROPDOWN MULTI (bisa di-search) =================
['#f-area', '#f-dist', '#f-month'].forEach(function (sel) {
    const el = document.querySelector(sel);
    if (!el) return;
    new TomSelect(el, {
        create: false,
        maxOptions: 500,
        maxItems: null,
        closeAfterSelect: false,
        hideSelected: true,
        placeholder: 'Semua',
        plugins: { remove_button: {}, clear_button: { title: 'Hapus semua pilihan' } },
        onChange: function () {
            if (window.filterSiap) jadwalkanSubmit();
        }
    });
});

document.querySelectorAll(
    '#filterForm input[type="date"], #filterForm input[type="month"], #filterForm select:not(.tomselected)'
).forEach(function (el) {
    el.addEventListener('change', jadwalkanSubmit);
});

window.filterSiap = true;
</script>

</body>
</html>