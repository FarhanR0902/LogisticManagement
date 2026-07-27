@include('template.sidebar')

<!DOCTYPE html>
<html>
<head>

<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

<!-- Heatmap plugin -->
<script src="https://unpkg.com/leaflet.heat/dist/leaflet-heat.js"></script>
<meta charset="utf-8">
<title>Manager Dashboard</title>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>

/* ================= RESET ================= */
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Segoe UI',sans-serif;
}

body{
    background:#eef2f7;
}

/* ================= CONTAINER ================= */
.container{
    margin-left:250px;
    padding:25px;
}

/* ================= TITLE ================= */
.page-title{
    margin-bottom:20px;
}

.page-title h2{
    font-size:26px;
    color:#111827;
}

/* ================= KPI ================= */
.kpi-row{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:16px;
    margin-bottom:20px;
}

.card{
    border-radius:14px;
    padding:18px;
    color:#fff;
    text-decoration:none;
    min-height:110px;
    display:flex;
    flex-direction:column;
    justify-content:space-between;
    box-shadow:0 6px 16px rgba(0,0,0,.08);
    transition:.2s;
}

.card:hover{
    transform:translateY(-3px);
}

.card h4{
    font-size:14px;
}

.card h1{
    font-size:28px;
}

/* COLORS */
.blue{background:#3b82f6;}
.green{background:#22c55e;}
.red{background:#ef4444;}
.orange{background:#f59e0b;}
.purple{background:#8b5cf6;}
.dark{background:#111827;}
.teal{background:#0f766e;}

/* ================= TOTAL ================= */
.total-row{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:16px;
    margin-bottom:20px;
}

.total-card{
    background:#fff;
    border-radius:14px;
    padding:18px;
    box-shadow:0 6px 16px rgba(0,0,0,.06);
}

.total-card h3{
    font-size:14px;
    color:#6b7280;
}

.total-card h1{
    font-size:26px;
    color:#111827;
}

/* ================= GAYA KARTU "KLAIM" (mengikuti referensi dashboard klaim
   asuransi kamu) - dipakai khusus di section Nilai Muatan & CR per Pulau
   supaya kelihatan bedanya, bukan cuma pakai .total-card yang sama seperti
   card lain di halaman ini ================= */

.claim-style-row{
    display:flex;
    gap:16px;
    flex-wrap:wrap;
    margin-bottom:16px;
}

.claim-style-card{
    flex:1;
    min-width:220px;
    background:#fff;
    border-radius:20px;
    padding:20px 24px;
    box-shadow:0 6px 18px rgba(0,0,0,.06);
}

.claim-style-card .claim-label{
    font-size:12px;
    font-weight:700;
    color:#374151;
    letter-spacing:.5px;
    text-transform:uppercase;
    margin-bottom:10px;
}

.claim-style-card .claim-value{
    font-size:28px;
    font-weight:800;
}

.claim-value-dark{color:#111827;}
.claim-value-green{color:#16a34a;}
.claim-value-purple{color:#7c3aed;}

.claim-chart-box{
    background:#fff;
    border-radius:20px;
    padding:20px;
    box-shadow:0 6px 18px rgba(0,0,0,.06);
    margin-bottom:16px;
}

.claim-chart-box h4{
    text-align:center;
    font-size:15px;
    color:#111827;
    margin-bottom:14px;
}

.claim-chart-wrapper{
    position:relative;
    height:340px;
}

@media(max-width:768px){
    .claim-style-row{flex-direction:column;}
}

/* ================= CHART GRID ================= */
.chart-grid{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:16px;
    margin-bottom:20px;
}

/* ================= CHART BOX (FIXED) ================= */
.chart-box{
    background:#fff;
    border-radius:14px;
    padding:16px;
    box-shadow:0 6px 16px rgba(0,0,0,.06);
    height:360px;
    display:flex;
    flex-direction:column;
}

.chart-box h3{
    font-size:15px;
    margin-bottom:10px;
    color:#111827;
}

.chart-wrapper{
    flex:1;
    position:relative;
}

/* ================= TABLE ================= */
.table-box{
    background:#fff;
    border-radius:14px;
    padding:18px;
    margin-bottom:20px;
    box-shadow:0 6px 16px rgba(0,0,0,.06);
}

table{
    width:100%;
    border-collapse:collapse;
}

th{
    background:#111827;
    color:#fff;
    padding:10px;
    font-size:13px;
}

td{
    padding:10px;
    border-bottom:1px solid #eee;
    font-size:13px;
    text-align:center;
}

/* ================= RESPONSIVE ================= */
@media(max-width:1100px){
    .kpi-row{grid-template-columns:repeat(2,1fr);}
    .chart-grid{grid-template-columns:1fr;}
    .total-row{grid-template-columns:1fr;}
}

@media(max-width:600px){
    .container{
        margin-left:0;
        padding:15px;
    }
    .kpi-row{grid-template-columns:1fr;}
}

/* TOTAL CARD COLORS (MATCH CHART STYLE) */

/* ================= KPI MODERN STYLE ================= */

.kpi-blue{
    background: linear-gradient(135deg,#ffffff,#dbeafe);
    border-left:6px solid #3b82f6;
    color:#111827;
}

.kpi-green{
    background: linear-gradient(135deg,#ffffff,#dcfce7);
    border-left:6px solid #22c55e;
    color:#111827;
}

.kpi-red{
    background: linear-gradient(135deg,#ffffff,#fee2e2);
    border-left:6px solid #ef4444;
    color:#111827;
}

.kpi-teal{
    background: linear-gradient(135deg,#ffffff,#ccfbf1);
    border-left:6px solid #0f766e;
    color:#111827;
}

.kpi-orange{
    background: linear-gradient(135deg,#ffffff,#ffedd5);
    border-left:6px solid #f59e0b;
    color:#111827;
}

.kpi-purple{
    background: linear-gradient(135deg,#ffffff,#ede9fe);
    border-left:6px solid #8b5cf6;
    color:#111827;
}

.kpi-dark{
    background: linear-gradient(135deg,#ffffff,#e5e7eb);
    border-left:6px solid #111827;
    color:#111827;
}

/* hover biar hidup */
.card:hover{
    transform:translateY(-5px);
    box-shadow:0 10px 25px rgba(0,0,0,.12);
}

.filter-box{
    margin-bottom:20px;
    background:#fff;
    padding:15px;
    border-radius:14px;
    box-shadow:0 6px 16px rgba(0,0,0,.06);
}

.filter-form{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:center;
}

.filter-form input,
.filter-form select{
    padding:10px;
    border:1px solid #ddd;
    border-radius:10px;
    outline:none;
}

.filter-form button{
    padding:10px 15px;
    background:linear-gradient(135deg,#22c55e,#16a34a);
    color:#fff;
    border:none;
    border-radius:10px;
    cursor:pointer;
}

.reset-btn{
    padding:10px 15px;
    background:#ef4444;
    color:#fff;
    border-radius:10px;
    text-decoration:none;
}

</style>
</head>

<body>

<div class="container">

<!-- TITLE -->
<div class="page-title">
    <h2>📊 MANAGER DASHBOARD</h2>
</div>

<div class="filter-box">

    <form method="GET" action="{{ url()->current() }}" class="filter-form">

        <!-- DAY -->
        <input type="date" name="date" value="{{ request('date') }}">

        <!-- MONTH -->
        <input type="month" name="month" value="{{ request('month') }}">

        <!-- YEAR -->
        <select name="year">
            <option value="">-- Year --</option>
            @for($y = date('Y'); $y >= 2020; $y--)
                <option value="{{ $y }}" {{ request('year') == $y ? 'selected' : '' }}>
                    {{ $y }}
                </option>
            @endfor
        </select>

        <!-- AREA -->
<select name="area">

    <option value="">-- All Area --</option>

    @foreach($list_area as $a)

        <option
            value="{{ $a->area }}"
            {{ request('area') == $a->area ? 'selected' : '' }}
        >
            {{ $a->area }}
        </option>

    @endforeach

</select>

        <button type="submit">Filter</button>

        <a href="{{ url()->current() }}" class="reset-btn">Reset</a>

    </form>

</div>

<!-- KPI -->
<div class="kpi-row">

<a href="{{ url('/datalogistik') }}?{{ http_build_query(request()->query()) }}" class="card blue">
    <h4>Total Shipment</h4>
    <h1>{{ $total_data }}</h1>
</a>

<a href="{{ route('manager.gudang.ontime', request()->query()) }}" class="card green">
    <h4>Gudang MS OnTime</h4>
    <h1>{{ $gudang_ontime }}</h1>
</a>

<a href="{{ route('manager.gudang.delay', request()->query()) }}" class="card red">
    <h4>Gudang MS Delay</h4>
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



<div class="total-row">
 <div class="card green">
        <h3>Total Nilai Muatan</h3>
        <h1>Rp {{ number_format($totalNilaiMuatan,0,',','.') }}</h1>
    </div>

    <div class="card blue">
        <h3>Total Biaya Kirim</h3>
        <h1>Rp {{ number_format($totalBiayaKirim,0,',','.') }}</h1>
    </div>

    <div class="card red">
        <h3>Cost Ratio</h3>
        @php
            $ratio = ($totalNilaiMuatan ?? 0) > 0
                ? ($totalBiayaKirim / $totalNilaiMuatan) * 100
                : 0;
        @endphp
        <h1>{{ number_format($ratio,2) }}%</h1>
    </div>

</div>

<!-- CHART -->
<div class="chart-grid">

<div class="chart-box">
    <h3>📍 SLA Bongkar</h3>
    <div class="chart-wrapper">
        <canvas id="chartBongkar"></canvas>
    </div>
</div>

<div class="chart-box">
    <h3>🌍 Summary Area</h3>
    <div class="chart-wrapper">
        <canvas id="chartArea"></canvas>
    </div>
</div>

<div class="chart-box">
    <h3>📦 Keterangan Ekspedisi</h3>
    <div class="chart-wrapper">
        <canvas id="chartEkspedisi"></canvas>
    </div>
</div>

<div class="chart-box">
    <h3>📍 Top Tujuan</h3>
    <div class="chart-wrapper">
        <canvas id="chartTujuan"></canvas>
    </div>
</div>

</div>

<!-- TABLE -->
<div class="table-box">

<h3>📍 Summary Area Detail</h3>

<table>
<thead>
<tr>
    <th>No</th>
    <th>Area</th>
    <th>Shipment</th>
    <th>Biaya</th>
    <th>Muatan</th>
</tr>
</thead>

<tbody>
@forelse($summary_area->take(20) as $key => $a)
<tr>
    <td>{{ $key+1 }}</td>
    <td>{{ $a->area }}</td>
    <td>{{ $a->total_shipment }}</td>

    <td>Rp {{ number_format($a->total_biaya,0,',','.') }}</td>
    <td>Rp {{ number_format($a->total_muatan,0,',','.') }}</td>
</tr>
@empty
<tr>
    <td colspan="5">No data</td>
</tr>
@endforelse
</tbody>

</table>

</div>

<!-- TABLE TUJUAN -->
<div class="table-box">

    <h3>📍 Summary Tujuan Detail</h3>

    <div style="overflow-x:auto;">

    <table>
        <thead>
        <tr>
            <th>No</th>
            <th>Tujuan</th>
            <th>Shipment</th>
            <th>Biaya</th>
            <th>Muatan</th>
        </tr>
        </thead>

        <tbody>
        @forelse($summary_tujuan->take(20) as $key => $t)
        <tr>
            <td>{{ $key+1 }}</td>
            <td style="text-align:left; font-weight:500;">
                {{ $t->tujuan }}
            </td>
            <td>
                <span >
                    {{ $t->total_shipment }}
                </span>
            </td>
            <td style="text-align:right;">
                Rp {{ number_format($t->total_biaya,0,',','.') }}
            </td>
            <td style="text-align:right;">
                Rp {{ number_format($t->total_muatan,0,',','.') }}
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="5" style="text-align:center;color:#999;">
                No data tujuan
            </td>
        </tr>
        @endforelse
        </tbody>

    </table>

    </div>

</div>

<!-- ================= NILAI MUATAN & CR PER PULAU (BARU) ================= -->
<div class="table-box">

    <h3>🏝️ Nilai Muatan &amp; CR per Pulau</h3>

    {{-- 3 kartu ringkasan gaya "klaim asuransi" (referensi kamu): angka besar
         berwarna, bukan kotak berwarna. Urutan & warna disamakan persis:
         CR (hitam, dulunya "Total Klaim Asuransi"), Nilai Muatan (hijau,
         dulunya "Jumlah Nominal Klaim Asuransi"), Biaya Kirim (ungu,
         dulunya "Rata-Rata Nominal Klaim Asuransi"). --}}
    <div class="claim-style-row">

        <div class="claim-style-card">
            <div class="claim-label">CR</div>
            <div class="claim-value claim-value-dark">{{ number_format($ratio,2) }}%</div>
        </div>

        <div class="claim-style-card">
            <div class="claim-label">Nilai Muatan</div>
            <div class="claim-value claim-value-green">Rp{{ number_format($totalNilaiMuatan,0,',','.') }}</div>
        </div>

        <div class="claim-style-card">
            <div class="claim-label">Biaya Kirim</div>
            <div class="claim-value claim-value-purple">Rp{{ number_format($totalBiayaKirim,0,',','.') }}</div>
        </div>

    </div>

    {{-- Chart kombinasi bar + line, style card terpisah dgn judul di tengah,
         mirip chart "Presentase Klaim Asuransi Bulanan" di referensi kamu:
         bar biru = Nilai Muatan, line oranye = CR%, sumbu kiri Rp / sumbu
         kanan %. --}}
    <div class="claim-chart-box">
        <h4>📊 Presentase Nilai Muatan &amp; CR per Pulau</h4>
        <div class="claim-chart-wrapper">
            <canvas id="chartPulau"></canvas>
        </div>
    </div>

    <div style="overflow-x:auto; margin-top:16px;">

    <table>
        <thead>
        <tr>
            <th>No</th>
            <th>Pulau</th>
            <th>Shipment</th>
            <th>Nilai Muatan</th>
            <th>Biaya Kirim</th>
            <th>CR (%)</th>
        </tr>
        </thead>

        <tbody>
        @forelse(($summary_pulau ?? collect()) as $key => $p)
        <tr>
            <td>{{ $key+1 }}</td>
            <td style="text-align:left; font-weight:500;">
                {{ $p->pulau_pasuruan }}
            </td>
            <td>{{ $p->total_shipment }}</td>
            <td style="text-align:right;">
                Rp {{ number_format($p->total_muatan,0,',','.') }}
            </td>
            <td style="text-align:right;">
                Rp {{ number_format($p->total_biaya,0,',','.') }}
            </td>
            <td>{{ number_format($p->cr,2) }}%</td>
        </tr>
        @empty
        <tr>
            <td colspan="6" style="text-align:center;color:#999;">
                No data pulau
            </td>
        </tr>
        @endforelse
        </tbody>

    </table>

    </div>

</div>

<!-- ================= SUMMARY PERSENTASE ================= -->
<div class="table-box">

    <h3>📊 Summary Monitoring (Persentase)</h3>

    <table>
        <thead>
            <tr>
                <th>Kategori</th>
                <th>Total</th>
                <th>Persentase</th>
            </tr>
        </thead>

        <tbody>

            <tr>
                <td>On Time Tiba</td>
                <td>{{ $customer_ontime }}</td>
                <td>{{ number_format($summary_monitoring['tiba_ontime'],2) }}%</td>
            </tr>

            <tr>
                <td>Delay Tiba</td>
                <td>{{ $customer_delay }}</td>
                <td>{{ number_format($summary_monitoring['tiba_delay'],2) }}%</td>
            </tr>

            <tr>
                <td>Bongkar On Time</td>
                <td>{{ $bongkar_ontime }}</td>
                <td>{{ number_format($summary_monitoring['bongkar_ontime'],2) }}%</td>
            </tr>

            <tr>
                <td>Bongkar Delay</td>
                <td>{{ $bongkar_delay }}</td>
                <td>{{ number_format($summary_monitoring['bongkar_delay'],2) }}%</td>
            </tr>

        </tbody>
    </table>

</div>

<!-- ================= SUMMARY PLANNER ================= -->
<div class="table-box">

    <h3>📋 Summary Planner</h3>

    <table>

        <thead>
            <tr>
                <th>Kategori</th>
                <th>Total</th>
                <th>Persentase</th>
            </tr>
        </thead>

        <tbody>

            <tr>
                <td>On Time</td>
                <td>{{ $planner_ontime }}</td>
                <td>{{ number_format($ontime_rate,2) }}%</td>
            </tr>

            <tr>
                <td>Delay</td>
                <td>{{ $planner_delay }}</td>
                <td>{{ number_format($delay_rate,2) }}%</td>
            </tr>

            <tr>
                <td>Armada Ready</td>
                <td>{{ $planner_armada }}</td>
                <td>{{ number_format($armada_rate,2) }}%</td>
            </tr>

            <tr>
                <td>Pending Armada</td>
                <td>{{ $planner_belum_armada }}</td>
                <td>{{ number_format(100 - $armada_rate,2) }}%</td>
            </tr>

        </tbody>

    </table>

</div>

</div>
<!-- ⬆️ FIXED: penutup .container dipindah ke sini (setelah Summary Planner).
     Sebelumnya </div> penutup .container ada tepat setelah "Summary Tujuan
     Detail", jadi section "Summary Monitoring (Persentase)" & "Summary
     Planner" render DI LUAR .container - kehilangan margin-left:250px
     sehingga ketimpa/numpuk sama sidebar. Sekarang semua section ada di
     dalam .container. -->

<!-- ================= CHART JS (TETAP PUNYA KAMU) ================= -->
<script>

const labels = @json($label ?? []);
const values = @json($value ?? []);

new Chart(document.getElementById('chartEkspedisi'), {
    type:'bar',
    data:{
        labels:labels,
        datasets:[{
            label:'Kategori Ekspedisi',
            data:values,
            borderWidth:1
        }]
    },
    options:{responsive:true,maintainAspectRatio:false}
});
new Chart(document.getElementById('chartBongkar'), {
    type:'doughnut',
    data:{
        labels:['On Time','Delay'],
        datasets:[{
            data:[{{ $bongkar_ontime }},{{ $bongkar_delay }}],
            backgroundColor:['#8b5cf6','#111827']
        }]
    },
    options:{responsive:true,maintainAspectRatio:false}
});

new Chart(document.getElementById('chartArea'), {
    type:'bar',
    data:{
        labels:[
            @foreach($summary_area->take(5) as $a)
                '{{ $a->area }}',
            @endforeach
        ],
        datasets:[{
            data:[
                @foreach($summary_area->take(5) as $a)
                    {{ $a->total_shipment }},
                @endforeach
            ],
            backgroundColor:'#3b82f6'
        }]
    },
    options:{responsive:true,maintainAspectRatio:false}
});

new Chart(document.getElementById('chartTujuan'), {
    type:'bar',
    data:{
        labels:[
            @foreach($summary_tujuan->take(5) as $t)
                '{{ $t->tujuan }}',
            @endforeach
        ],
        datasets:[{
            data:[
                @foreach($summary_tujuan->take(5) as $t)
                    {{ $t->total_shipment }},
                @endforeach
            ],
            backgroundColor:'#f59e0b'
        }]
    },
    options:{responsive:true,maintainAspectRatio:false}
});

// ================= CHART PULAU (BARU) =================
// Bar = Nilai Muatan per Pulau (sumbu kiri, Rp)
// Line = CR (%) per Pulau (sumbu kanan, %)
// Data dikirim dari PasuruanController::dashboard() -> summary_pulau
const labelPulau  = @json($label_pulau ?? []);
const muatanPulau = @json($value_muatan_pulau ?? []);
const crPulau     = @json($value_cr_pulau ?? []);

new Chart(document.getElementById('chartPulau'), {
    // FIXED: sebelumnya tidak ada "type" di level root config. Untuk mixed
    // chart (bar + line dalam 1 canvas), Chart.js tetap butuh type di root
    // sebagai fallback - kalau tidak ada, chart bisa gagal render sama
    // sekali (canvas kosong, tanpa error yang kelihatan di halaman).
    type: 'bar',
    data: {
        labels: labelPulau,
        datasets: [
            {
                type: 'bar',
                label: 'Nilai Muatan (Rp)',
                data: muatanPulau,
                backgroundColor: '#3b82f6',
                yAxisID: 'yMuatan',
                order: 2
            },
            {
                type: 'line',
                label: 'CR (%)',
                data: crPulau,
                borderColor: '#f59e0b',
                backgroundColor: '#f59e0b',
                tension: 0.3,
                yAxisID: 'yCr',
                order: 1
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top' }
        },
        scales: {
            yMuatan: {
                type: 'linear',
                position: 'left',
                title: { display: true, text: 'Nilai Muatan (Rp)' },
                ticks: {
                    callback: function(value) {
                        return 'Rp ' + Number(value).toLocaleString('id-ID');
                    }
                }
            },
            yCr: {
                type: 'linear',
                position: 'right',
                title: { display: true, text: 'CR (%)' },
                grid: { drawOnChartArea: false },
                ticks: {
                    callback: function(value) {
                        return value + '%';
                    }
                }
            }
        }
    }
});

</script>

</body>
</html>