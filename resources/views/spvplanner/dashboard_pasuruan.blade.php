@include('template.sidebar')

<!DOCTYPE html>
<html lang="id">
<head>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pasuruan Dashboard</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Leaflet (peta sebaran) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.heat/dist/leaflet-heat.js"></script>

<style>

/* ================= RESET ================= */
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

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

    --sidebar-w:250px;
}

body{
    background:var(--bg);
    font-family:'Inter',sans-serif;
    color:var(--text-primary);
    -webkit-font-smoothing:antialiased;
}

h1,h2,h3,h4{
    font-family:'Plus Jakarta Sans',sans-serif;
}

/* ================= CONTAINER ================= */
.container{
    margin-left:250px;
    width:calc(100vw - 250px);
    max-width:none;
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

.page-title h1{
    font-size:26px;
    font-weight:700;
    color:var(--text-primary);
}

.page-title p{
    margin-top:4px;
    font-size:13.5px;
    color:var(--text-secondary);
}

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

.field{
    display:flex;
    flex-direction:column;
    gap:6px;
}

.field label{
    font-size:11.5px;
    font-weight:600;
    color:var(--text-secondary);
    text-transform:uppercase;
    letter-spacing:.04em;
}

.filter-form input,
.filter-form select{
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

.filter-form input:focus,
.filter-form select:focus{
    border-color:var(--primary);
    background:#fff;
}

.filter-actions{
    display:flex;
    gap:8px;
    margin-left:auto;
}

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

.btn-primary{
    background:var(--primary);
    color:#fff;
}

.btn-primary:hover{ filter:brightness(1.08); }

.btn-ghost{
    background:var(--slate-light);
    color:var(--slate);
}

.btn-ghost:hover{ filter:brightness(0.97); }

/* ================= FILTER PULAU (BOX GRID) ================= */
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
    grid-template-columns:repeat(8, minmax(0,1fr));
    gap:10px;
}

@media(max-width:1400px){
    .pulau-grid{ grid-template-columns:repeat(4, minmax(0,1fr)); }
}
@media(max-width:768px){
    .pulau-grid{ grid-template-columns:repeat(2, minmax(0,1fr)); }
}

.pulau-box{
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

.pulau-box .pulau-icon{
    font-size:18px;
    line-height:1;
}

.pulau-box .pulau-name{
    font-size:11.5px;
    font-weight:700;
    color:var(--text-secondary);
    text-transform:uppercase;
    letter-spacing:.03em;
}

.pulau-box .pulau-count{
    font-size:10.5px;
    color:var(--text-muted);
}

.pulau-box.active{
    border-color:var(--primary);
    background:linear-gradient(135deg,var(--primary),#3730a3);
    box-shadow:0 8px 18px rgba(79,70,229,.28);
}

.pulau-box.active .pulau-name,
.pulau-box.active .pulau-count{
    color:#fff;
}

/* ================= STAT CARDS ================= */
.section-label{
    font-size:12.5px;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.06em;
    color:var(--text-muted);
    margin:26px 0 12px;
}

.section-label:first-of-type{ margin-top:0; }

.kpi-row{
    display:grid;
    grid-template-columns:repeat(8,minmax(0,1fr));
    gap:14px;
}

.stat-link{
    text-decoration:none;
    color:inherit;
    cursor:pointer;
    transition:all .2s ease;
}

.stat-link:hover{
    transform:translateY(-4px);
    box-shadow:0 15px 25px rgba(0,0,0,.12);
}

.stat-link:hover .stat-value{
    color:var(--primary);
}
@media(max-width:1400px){

    .kpi-row{
        grid-template-columns:repeat(4,1fr);
    }

    .chart-grid{
        grid-template-columns:1fr;
    }

    .total-row{
        grid-template-columns:1fr;
    }

    .two-col{
        grid-template-columns:1fr;
    }

}

@media(max-width:768px){

    .container{
        margin-left:0;
        width:100%;
        padding:18px;
    }

    .kpi-row{
        grid-template-columns:repeat(2,1fr);
    }

    .filter-actions{
        margin-left:0;
        width:100%;
    }

    .filter-form select,
    .filter-form input{
        min-width:130px;
        flex:1;
    }

}

.stat-card{
    background:var(--surface);
    border:1px solid var(--border);
    border-left:4px solid var(--accent, var(--primary));
    border-radius:var(--radius-md);
    padding:16px 18px;
    box-shadow:var(--shadow);
    display:flex;
    flex-direction:column;
    gap:10px;
}

.stat-card .stat-top{
    display:flex;
    align-items:center;
    justify-content:space-between;
}

.stat-card .stat-icon{
    width:34px;
    height:34px;
    border-radius:10px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:16px;
    background:var(--accent-light, var(--primary-light));
}

.stat-card h4{
    font-size:12.5px;
    font-weight:600;
    color:var(--text-secondary);
    text-transform:uppercase;
    letter-spacing:.03em;
}

.stat-card .stat-value{
    font-size:26px;
    font-weight:700;
    color:var(--text-primary);
    font-family:'Plus Jakarta Sans',sans-serif;
}

.stat-card.c-primary{ --accent:var(--primary); --accent-light:var(--primary-light); }
.stat-card.c-green{ --accent:var(--green); --accent-light:var(--green-light); }
.stat-card.c-red{ --accent:var(--red); --accent-light:var(--red-light); }
.stat-card.c-teal{ --accent:var(--teal); --accent-light:var(--teal-light); }
.stat-card.c-amber{ --accent:var(--amber); --accent-light:var(--amber-light); }
.stat-card.c-purple{ --accent:var(--purple); --accent-light:var(--purple-light); }
.stat-card.c-slate{ --accent:var(--slate); --accent-light:var(--slate-light); }

/* ================= KPI LINK CARDS ================= */
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

.card:hover{
    transform:translateY(-4px);
    box-shadow:0 15px 25px rgba(0,0,0,.12);
}

.card h4{
    font-size:12.5px;
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

/* Gradien: warna solid di kiri (border), memudar jadi putih ke arah kanan */
.card.blue{
    border-left-color:var(--primary);
    background:linear-gradient(90deg, rgba(79,70,229,.16) 0%, rgba(79,70,229,.03) 55%, #ffffff 100%);
}
.card.green{
    border-left-color:var(--green);
    background:linear-gradient(90deg, rgba(22,163,74,.16) 0%, rgba(22,163,74,.03) 55%, #ffffff 100%);
}
.card.red{
    border-left-color:var(--red);
    background:linear-gradient(90deg, rgba(225,29,72,.16) 0%, rgba(225,29,72,.03) 55%, #ffffff 100%);
}
.card.teal{
    border-left-color:var(--teal);
    background:linear-gradient(90deg, rgba(13,148,136,.16) 0%, rgba(13,148,136,.03) 55%, #ffffff 100%);
}
.card.orange{
    border-left-color:var(--amber);
    background:linear-gradient(90deg, rgba(245,158,11,.18) 0%, rgba(245,158,11,.03) 55%, #ffffff 100%);
}
.card.purple{
    border-left-color:var(--purple);
    background:linear-gradient(90deg, rgba(139,92,246,.16) 0%, rgba(139,92,246,.03) 55%, #ffffff 100%);
}
.card.dark{
    border-left-color:var(--slate);
    background:linear-gradient(90deg, rgba(51,65,85,.14) 0%, rgba(51,65,85,.03) 55%, #ffffff 100%);
}

/* ================= HERO TOTALS ================= */
.total-row{
    display:grid;
    grid-template-columns:repeat(3,1fr);
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

/* ================= CHART GRID ================= */
.chart-grid{
    display:grid;
    grid-template-columns:repeat(2,1fr);
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

.chart-wrapper{
    flex:1;
    position:relative;
    min-height:0;
}

/* ================= TABLE ================= */
.table-box{
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:var(--radius-lg);
    padding:20px 22px;
    box-shadow:var(--shadow);
}

.table-box h3{
    font-size:15px;
    font-weight:600;
    margin-bottom:14px;
    display:flex;
    align-items:center;
    gap:8px;
}

.table-scroll{
    overflow-x:auto;
}

table{
    width:100%;
    border-collapse:collapse;
    font-size:13.5px;
}

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

.empty-row{
    text-align:center;
    color:var(--text-muted);
    padding:22px 0;
}

/* ================= LAYOUT STACK ================= */
.stack{
    display:flex;
    flex-direction:column;
    gap:22px;
    margin-top:22px;
}

.two-col{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:16px;
}

/* ================= PETA SEBARAN PENGIRIMAN ================= */
#shipmentMap{
    height:480px;
    border-radius:var(--radius-lg);
    overflow:hidden;
}

.map-legend{
    display:flex;
    gap:18px;
    flex-wrap:wrap;
    margin-top:14px;
    font-size:12.5px;
    color:var(--text-secondary);
}

.map-legend span{
    display:inline-flex;
    align-items:center;
    gap:6px;
}

.map-legend i{
    width:12px;
    height:12px;
    border-radius:50%;
    display:inline-block;
}

.map-note{
    margin-top:10px;
    font-size:12px;
    color:var(--text-muted);
}

.map-note code{
    background:var(--slate-light);
    padding:1px 6px;
    border-radius:4px;
}

/* ================= RESPONSIVE ================= */
@media(max-width:1100px){
    .chart-grid{ grid-template-columns:1fr; }
    .total-row{ grid-template-columns:1fr; }
    .two-col{ grid-template-columns:1fr; }
}

@media(max-width:768px){
    .container{
        margin-left:0;
        padding:18px;
    }
    .filter-actions{ margin-left:0; width:100%; }
    .filter-form select,
    .filter-form input{ min-width:130px; flex:1; }
    #shipmentMap{ height:340px; }
}



</style>
</head>

<body>

<div class="container">

<!-- HEADER -->
<div class="page-header">
    <div class="page-title">
        <div class="eyebrow">Logistik &middot; Pasuruan</div>
        <h1>Dashboard pengiriman Pasuruan</h1>
        <p>Ringkasan performa gudang, armada, dan ketepatan waktu pengiriman.</p>
        <div class="route-rule"></div>
    </div>
</div>

<!-- FILTER -->
<div class="filter-box">
    <form method="GET" action="{{ url()->current() }}" class="filter-form" id="filterForm">

        <div class="field">
            <label for="f-date">Tanggal</label>
            <input id="f-date" type="date" name="date" value="{{ request('date') }}">
        </div>

        <div class="field">
            <label for="f-month">Bulan</label>
            <input id="f-month" type="month" name="month" value="{{ request('month') }}">
        </div>

        <div class="field">
            <label for="f-year">Tahun</label>
            <select id="f-year" name="year">
                <option value="">Semua tahun</option>
                @for($y = date('Y'); $y >= 2020; $y--)
                    <option value="{{ $y }}" {{ request('year') == $y ? 'selected' : '' }}>
                        {{ $y }}
                    </option>
                @endfor
            </select>
        </div>

        <div class="field">
            <label for="f-area">Area</label>
            <select id="f-area" name="area">
                <option value="">Semua area</option>
                @foreach($list_area as $a)
                    <option value="{{ $a->area }}" {{ request('area') == $a->area ? 'selected' : '' }}>
                        {{ $a->area }}
                    </option>
                @endforeach
            </select>
        </div>

        @if(isset($list_dist_channel))
        <div class="field">
            <label for="f-dist">Dist. channel</label>
            <select id="f-dist" name="dist_channel">
                <option value="">Semua channel</option>
                @foreach($list_dist_channel as $d)
                    <option value="{{ $d->dist_channel_pasuruan }}" {{ request('dist_channel_pasuruan') == $d->dist_channel_pasuruan? 'selected' : '' }}>
                        {{ $d->dist_channel_pasuruan }}
                    </option>
                @endforeach
            </select>
        </div>
        @endif

        <!-- Filter pulau dikirim lewat hidden input ini, diisi otomatis pas kotak pulau diklik -->
        <input type="hidden" name="pulau" id="f-pulau" value="{{ request('pulau') }}">

        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">Terapkan filter</button>
            <a href="{{ url()->current() }}" class="btn btn-ghost">Reset</a>
        </div>

    </form>

    <!-- ================= FILTER PULAU (BOX, BUKAN DROPDOWN) ================= -->
    <div class="pulau-section">
        <div class="pulau-section-label">📍 Filter cepat berdasarkan pulau</div>
        <div class="pulau-grid" id="pulauGrid">
            <div class="pulau-box {{ !request('pulau') ? 'active' : '' }}" data-pulau="">
                <div class="pulau-icon">🗺️</div>
                <div class="pulau-name">Semua Pulau</div>
                <div class="pulau-count" data-count-for="">-</div>
            </div>
            <div class="pulau-box {{ request('pulau') == 'JAWA' ? 'active' : '' }}" data-pulau="JAWA">
                <div class="pulau-icon">🏝️</div>
                <div class="pulau-name">Jawa</div>
                <div class="pulau-count" data-count-for="JAWA">-</div>
            </div>
            <div class="pulau-box {{ request('pulau') == 'SUMATERA' ? 'active' : '' }}" data-pulau="SUMATERA">
                <div class="pulau-icon">🌋</div>
                <div class="pulau-name">Sumatera</div>
                <div class="pulau-count" data-count-for="SUMATERA">-</div>
            </div>
            <div class="pulau-box {{ request('pulau') == 'KALIMANTAN' ? 'active' : '' }}" data-pulau="KALIMANTAN">
                <div class="pulau-icon">🌳</div>
                <div class="pulau-name">Kalimantan</div>
                <div class="pulau-count" data-count-for="KALIMANTAN">-</div>
            </div>
            <div class="pulau-box {{ request('pulau') == 'SULAWESI' ? 'active' : '' }}" data-pulau="SULAWESI">
                <div class="pulau-icon">🐚</div>
                <div class="pulau-name">Sulawesi</div>
                <div class="pulau-count" data-count-for="SULAWESI">-</div>
            </div>
            <!-- <div class="pulau-box {{ request('pulau') == 'BALI_NUSRA' ? 'active' : '' }}" data-pulau="BALI_NUSRA">
                <div class="pulau-icon">🏖️</div>
                <div class="pulau-name">Bali &amp; Nusra</div>
                <div class="pulau-count" data-count-for="BALI_NUSRA">-</div>
            </div> -->
         
            <div class="pulau-box {{ request('pulau') == 'PAPUA' ? 'active' : '' }}" data-pulau="PAPUA">
                <div class="pulau-icon">🦅</div>
                <div class="pulau-name">Papua</div>
                <div class="pulau-count" data-count-for="PAPUA">-</div>
            </div>
        </div>
    </div>
</div>

<!-- KPI -->
<div class="section-label">Ringkasan status</div>

<div class="kpi-row">

<a href="{{ url('/datalogistik') }}?{{ http_build_query(request()->query()) }}" class="card blue">
    <h4>Total Shipment</h4>
    <h1>{{ $total_data }}</h1>
</a>

<a href="{{ route('manager.gudang.ontime', request()->query()) }}" class="card green">
    <h4>Sudah Tiba Di Gudang</h4>
    <h1>{{ $gudang_ontime }}</h1>
</a>

<a href="{{ route('manager.gudang.delay', request()->query()) }}" class="card red">
    <h4>Belum Tiba Di Gudang</h4>
    <h1>{{ $gudang_delay }}</h1>
</a>

<a href="{{ route('manager.customer.ontime', request()->query()) }}" class="card teal">
    <h4>Tiba Tujuan OnTime</h4>
    <h1>{{ $customer_ontime }}</h1>
</a>

<a href="{{ route('manager.customer.delay', request()->query()) }}" class="card orange">
    <h4>Tiba Tujuan Delay</h4>
    <h1>{{ $customer_delay }}</h1>
</a>

<a href="{{ route('manager.bongkar.ontime', request()->query()) }}" class="card purple">
    <h4>Bongkar OnTime</h4>
    <h1>{{ $bongkar_ontime }}</h1>
</a>

<a href="{{ route('manager.bongkar.delay', request()->query()) }}" class="card dark">
    <h4>Bongkar Delay</h4>
    <h1>{{ $bongkar_delay }}</h1>
</a>

<a href="{{ route('manager.summary.area', request()->query()) }}" class="card blue">
    <h4>Summary Area</h4>
    <h1>{{ count($summary_area) }}</h1>
</a>

</div>

<!-- HERO TOTALS -->
<div class="section-label">Nilai & biaya</div>
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
        <div class="hero-value">{{ number_format($ratio,2) }}%</div>
    </div>

</div>

<!-- CHART & PETA -->
<div class="stack">

<!-- PETA SEBARAN -->
<div class="table-box">
    <h3>🗺️ Peta sebaran nilai muatan per area</h3>

    <div id="shipmentMap"></div>

    <div class="map-legend">
        <span><i style="background:#16a34a;"></i> Nilai muatan rendah</span>
        <span><i style="background:#f59e0b;"></i> Nilai muatan sedang</span>
        <span><i style="background:#e11d48;"></i> Nilai muatan tinggi</span>
    </div>

    <p class="map-note">
        Angka di setiap lingkaran = total nilai muatan (Rp, format ringkas) dari area tersebut.
        Klik lingkaran untuk lihat detail lengkap (jumlah shipment, nilai muatan, biaya kirim).
        Koordinat diambil per area di variabel <code>koordinatAreaPasuruan</code>.
    </p>

    <div id="mapDebugBox" style="margin-top:14px;"></div>
</div>

<div class="chart-grid">

    <div class="chart-box">
        <h3>📍 SLA bongkar</h3>
        <div class="chart-wrapper">
            <canvas id="chartBongkar"></canvas>
        </div>
    </div>

    <div class="chart-box">
        <h3>🌍 Summary area</h3>
        <div class="chart-wrapper">
            <canvas id="chartArea"></canvas>
        </div>
    </div>

    <div class="chart-box">
        <h3>📦 Kategori ekspedisi</h3>
        <div class="chart-wrapper">
            <canvas id="chartEkspedisi"></canvas>
        </div>
    </div>

    <div class="chart-box">
        <h3>📍 Top tujuan</h3>
        <div class="chart-wrapper">
            <canvas id="chartTujuan"></canvas>
        </div>
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
            <td>{{ $a->area_pasuruan }}</td>
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
            <td>{{ $t->tujuan_pasuruan }}</td>
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

<!-- TWO COL: MONITORING + PLANNER -->
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

<!-- ================= CHART JS ================= -->
<script>

const labels = @json($label ?? []);
const values = @json($value ?? []);

const chartFont = { family: "'Inter', sans-serif", size: 12 };
Chart.defaults.font = chartFont;
Chart.defaults.color = '#64748b';

new Chart(document.getElementById('chartEkspedisi'), {
    type:'bar',
    data:{
        labels:labels,
        datasets:[{
            label:'Kategori Ekspedisi',
            data:values,
            backgroundColor:'#4f46e5',
            borderRadius:6,
            maxBarThickness:36
        }]
    },
    options:{
        responsive:true,
        maintainAspectRatio:false,
        plugins:{ legend:{ display:false } },
        scales:{ y:{ beginAtZero:true, grid:{ color:'#eef0f5' } }, x:{ grid:{ display:false } } }
    }
});

new Chart(document.getElementById('chartBongkar'), {
    type:'doughnut',
    data:{
        labels:['On Time','Delay'],
        datasets:[{
            data:[{{ $bongkar_ontime }},{{ $bongkar_delay }}],
            backgroundColor:['#8b5cf6','#e11d48'],
            borderWidth:0
        }]
    },
    options:{
        responsive:true,
        maintainAspectRatio:false,
        cutout:'65%',
        plugins:{ legend:{ position:'bottom' } }
    }
});

new Chart(document.getElementById('chartArea'), {
    type:'bar',
    data:{
        labels:[
            @foreach($summary_area->take(5) as $a)
                '{{ $a->area_pasuruan }}',
            @endforeach
        ],
        datasets:[{
            data:[
                @foreach($summary_area->take(5) as $a)
                    {{ $a->total_shipment }},
                @endforeach
            ],
            backgroundColor:'#0d9488',
            borderRadius:6,
            maxBarThickness:36
        }]
    },
    options:{
        responsive:true,
        maintainAspectRatio:false,
        plugins:{ legend:{ display:false } },
        scales:{ y:{ beginAtZero:true, grid:{ color:'#eef0f5' } }, x:{ grid:{ display:false } } }
    }
});

new Chart(document.getElementById('chartTujuan'), {
    type:'bar',
    data:{
        labels:[
            @foreach($summary_tujuan->take(5) as $t)
                '{{ $t->tujuan_pasuruan }}',
            @endforeach
        ],
        datasets:[{
            data:[
                @foreach($summary_tujuan->take(5) as $t)
                    {{ $t->total_shipment }},
                @endforeach
            ],
            backgroundColor:'#f59e0b',
            borderRadius:6,
            maxBarThickness:36
        }]
    },
    options:{
        responsive:true,
        maintainAspectRatio:false,
        plugins:{ legend:{ display:false } },
        scales:{ y:{ beginAtZero:true, grid:{ color:'#eef0f5' } }, x:{ grid:{ display:false } } }
    }
});

// ================= PETA SEBARAN NILAI MUATAN PER AREA (PASURUAN) =================
// Sumber data: $summary_area dari ManagerController@dashboardPasuruan()
// Kolom yang dipakai: area_pasuruan, total_shipment, total_biaya, total_muatan
const summaryAreaMapPasuruan = @json($summary_area ?? []);

// Koordinat representatif per area (ibu kota provinsi/wilayah).
// Key HARUS sama persis (huruf besar, underscore) dengan nilai kolom area_pasuruan.
// Kalau ada nama area yang belum terdaftar di sini, area tersebut akan
// otomatis tampil di panel "Belum ada koordinatnya" di bawah peta.
const koordinatAreaPasuruan = {
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

// ================= MAPPING AREA -> PULAU (buat filter box & counter) =================
// Dipakai untuk: (1) hitung jumlah area per pulau di badge kotak filter,
// (2) fallback filter di sisi client kalau server belum sempat filter.
// WAJIB sinkron dengan mapping PHP $PULAU_MAP di controller (dashboardPasuruan()).
const pulauAreaMap = {
    'JAWA': ['JABODEBEK','BANTEN','JAWA_BARAT','JAWA_TENGAH','JAWA_TIMUR','YOGYAKARTA'],
    'SUMATERA': ['ACEH','SUMATERA_UTARA','SUMATERA_BARAT','RIAU','KEP._RIAU','JAMBI','SUMATERA_SELATAN','BENGKULU','LAMPUNG','KEP._BANGKA_BELITUNG'],
    'KALIMANTAN': ['KALIMANTAN_BARAT','KALIMANTAN_TENGAH','KALIMANTAN_SELATAN','KALIMANTAN_TIMUR','KALIMANTAN_UTARA'],
    'SULAWESI': ['SULAWESI_UTARA','SULAWESI_TENGAH','SULAWESI_SELATAN','SULAWESI_TENGGARA','SULAWESI_BARAT','GORONTALO'],
    'BALI_NUSRA': ['PROV._BALI','NUSA_TENGGARA_BARAT','NUSA_TENGGARA_TIMUR'],
    'MALUKU': ['PROV._MALUKU','PROV._MALUKU_UTARA'],
    'PAPUA': ['PROV._PAPUA','PAPUA_BARAT','PAPUA_BARAT_DAYA','PAPUA_SELATAN','PAPUA_TENGAH'],
};

function normalisasiNamaPasuruan(str){
    return (str || '').toString().trim().toUpperCase();
}

// Isi angka badge "jumlah area" di tiap kotak pulau, berdasarkan data summary_area
// yang sedang tampil (hasil filter tanggal/bulan/tahun/area/dist_channel saat ini).
(function isiJumlahAreaPulau(){
    const counts = {};
    Object.keys(pulauAreaMap).forEach(p => counts[p] = 0);

    summaryAreaMapPasuruan.forEach(a => {
        const key = normalisasiNamaPasuruan(a.area_pasuruan);
        for (const pulau in pulauAreaMap) {
            if (pulauAreaMap[pulau].includes(key)) {
                counts[pulau]++;
                break;
            }
        }
    });

    document.querySelectorAll('[data-count-for]').forEach(el => {
        const p = el.getAttribute('data-count-for');
        if (p === '') {
            el.textContent = summaryAreaMapPasuruan.length + ' area';
        } else {
            el.textContent = (counts[p] || 0) + ' area';
        }
    });
})();

// Klik kotak pulau -> isi hidden input #f-pulau -> submit form filter (reload halaman
// dengan query string ?pulau=JAWA dst, sekaligus mempertahankan filter tanggal/area lain).
document.querySelectorAll('.pulau-box').forEach(box => {
    box.addEventListener('click', function(){
        document.getElementById('f-pulau').value = this.getAttribute('data-pulau');
        document.getElementById('filterForm').submit();
    });
});

// Format Rupiah jadi ringkas biar muat di lingkaran (mis. 125Jt, 2.3M, 1.1T)
function formatRupiahRingkasPasuruan(value){
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
    summaryAreaMapPasuruan.forEach(a => {
        if (Number(a.total_muatan) > maxMuatan) maxMuatan = Number(a.total_muatan);
    });

    const boundsList = [];
    const matched = [];
    const unmatched = [];

    summaryAreaMapPasuruan.forEach(a => {

        const key = normalisasiNamaPasuruan(a.area_pasuruan);
        const koordinat = koordinatAreaPasuruan[key];

        if (!koordinat) {
            unmatched.push(a.area_pasuruan + ' (Rp ' + Number(a.total_muatan).toLocaleString('id-ID') + ')');
            return; // skip kalau belum ada koordinatnya
        }

        matched.push(a.area_pasuruan);

        const nilaiMuatan = Number(a.total_muatan) || 0;
        const ratio = nilaiMuatan / maxMuatan;

        // warna berdasarkan besar nilai muatan
        let warna = '#16a34a'; // hijau = rendah
        if (ratio > 0.66) {
            warna = '#e11d48'; // merah = tinggi
        } else if (ratio > 0.33) {
            warna = '#f59e0b'; // oranye = sedang
        }

        const radius = 16 + (ratio * 24); // px

        const icon = L.divIcon({
            className: '',
            html: `
                <div style="
                    width:${radius*2}px;
                    height:${radius*2}px;
                    border-radius:50%;
                    background:${warna};
                    border:3px solid #fff;
                    box-shadow:0 4px 10px rgba(0,0,0,.25);
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    color:#fff;
                    font-weight:700;
                    font-size:${radius > 26 ? 13 : 11}px;
                    white-space:nowrap;
                ">${formatRupiahRingkasPasuruan(nilaiMuatan)}</div>
            `,
            iconSize: [radius*2, radius*2],
            iconAnchor: [radius, radius]
        });

        const marker = L.marker(koordinat, { icon: icon }).addTo(shipmentMap);

        marker.bindPopup(`
            <strong>${a.area_pasuruan}</strong><br>
            Jumlah shipment: <b>${a.total_shipment}</b><br>
            Total nilai muatan: <b>Rp ${nilaiMuatan.toLocaleString('id-ID')}</b><br>
            Total biaya kirim: Rp ${Number(a.total_biaya).toLocaleString('id-ID')}
        `);

        boundsList.push(koordinat);
    });

    if (boundsList.length > 0) {
        shipmentMap.fitBounds(boundsList, { padding: [40, 40] });
    }

    // ================= DEBUG PANEL =================
    const debugBox = document.getElementById('mapDebugBox');

    if (debugBox) {

        let html = '';

        if (summaryAreaMapPasuruan.length === 0) {

            html += `<div style="background:#fdeef2;border:1px solid #e11d48;color:#9f1239;
                        padding:12px 14px;border-radius:10px;font-size:13px;">
                        ⚠️ Data <code>$summary_area</code> dari controller KOSONG (0 baris).
                        Cek filter tanggal/area yang aktif, atau kolom <code>area_pasuruan</code>
                        di database memang NULL semua.
                      </div>`;

        } else {

            html += `<div style="background:#eef2ff;border:1px solid #4f46e5;color:#3730a3;
                        padding:12px 14px;border-radius:10px;font-size:13px;margin-bottom:10px;">
                        Total area dari controller: <b>${summaryAreaMapPasuruan.length}</b> baris.
                        Cocok dengan koordinat (muncul di peta): <b>${matched.length}</b>.
                        Belum ada koordinatnya (tidak muncul di peta): <b>${unmatched.length}</b>.
                      </div>`;

            if (unmatched.length > 0) {
                html += `<details style="background:#fef7e9;border:1px solid #f59e0b;
                            padding:12px 14px;border-radius:10px;font-size:13px;">
                            <summary style="cursor:pointer;font-weight:600;color:#b45309;">
                                ⚠️ Lihat ${unmatched.length} nama area yang BELUM ada koordinatnya
                            </summary>
                            <ul style="margin-top:8px;padding-left:18px;color:#78350f;">
                                ${unmatched.map(u => `<li>${u}</li>`).join('')}
                            </ul>
                          </details>`;
            }
        }

        debugBox.innerHTML = html;
    }
}

</script>

</body>
</html>