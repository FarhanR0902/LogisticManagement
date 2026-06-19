@include('template.sidebar')

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Dashboard Monitoring</title>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Segoe UI',sans-serif;
}

body{
    background:#eef2f7;
}

/* CONTAINER */
.container{
    width:calc(100% - 250px);
    margin-left:250px;
    padding:25px;
}

/* TITLE */
.page-title{
    font-size:28px;
    margin-bottom:25px;
    color:#0f172a;
    font-weight:bold;
}

/* GRID KPI */
.card-row{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:18px;
    margin-bottom:25px;
}

/* BOX */
.small-box{
    padding:22px;
    border-radius:16px;
    color:white;
    text-decoration:none;
    display:block;
    transition:.3s;
    box-shadow:0 6px 15px rgba(0,0,0,0.08);
    min-height:120px;

    display:flex;
    flex-direction:column;
    justify-content:space-between;
}

.small-box:hover{
    transform:translateY(-4px);
}

.small-box p{
    font-size:15px;
    opacity:.9;
}

.small-box h3{
    font-size:34px;
}

/* COLORS */
.bg-info{background:#0ea5e9;}
.bg-success{background:#22c55e;}
.bg-danger{background:#ef4444;}
.bg-warning{background:#f59e0b;color:#111827;}
.bg-purple{background:#8b5cf6;}

/* CHART */
.chart-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:20px;
    margin-bottom:25px;
}

.chart-box{
    background:white;
    padding:22px;
    border-radius:16px;
    box-shadow:0 4px 12px rgba(0,0,0,.05);

    height:420px;

    display:flex;
    flex-direction:column;
}

.chart-box h3{
    margin-bottom:15px;
    color:#111827;
}

.chart-wrapper{
    flex:1;
    position:relative;
}

/* TABLE */
.table-box{
    background:white;
    padding:22px;
    border-radius:16px;
    box-shadow:0 4px 12px rgba(0,0,0,.05);
}

.table-box h3{
    margin-bottom:15px;
    color:#111827;
}

table{
    width:100%;
    border-collapse:collapse;
}

th{
    background:#0f766e;
    color:white;
    padding:14px;
}

td{
    padding:14px;
    border-bottom:1px solid #e5e7eb;
    text-align:center;
}

tr:hover{
    background:#f1f5f9;
}

.detail-btn{
    background:#0ea5e9;
    color:white;
    padding:7px 12px;
    border-radius:8px;
    text-decoration:none;
    font-size:13px;
}

.detail-btn:hover{
    opacity:.9;
}

/* RESPONSIVE */
@media(max-width:1200px){

    .card-row{
        grid-template-columns:repeat(2,1fr);
    }

}

@media(max-width:900px){

    .chart-grid{
        grid-template-columns:1fr;
    }

}

@media(max-width:768px){

    .container{
        width:100%;
        margin-left:0;
        padding:15px;
    }

    .card-row{
        grid-template-columns:1fr;
    }

}

</style>

</head>

<body>

<div class="container">

    <h2 class="page-title">📡 Dashboard Monitoring</h2>

    @php
        $total_data = $total_data ?? 0;
        $total_tiba_ontime = $total_tiba_ontime ?? 0;
        $total_tiba_delay = $total_tiba_delay ?? 0;
        $total_final_delay = $total_final_delay ?? 0;
        $total_bongkar_ontime = $total_bongkar_ontime ?? 0;
        $total_bongkar_delay = $total_bongkar_delay ?? 0;
    @endphp

    <!-- ================= KPI ================= -->
    <div class="card-row">

        <a href="/datalogistik" class="small-box bg-info">
            <p>Total Shipment</p>
            <h3>{{ $total_data }}</h3>
        </a>

        <a href="/monitoring/sla-ontime" class="small-box bg-success">
            <p>On Time Tiba</p>
            <h3>{{ $total_tiba_ontime }}</h3>
        </a>

        <a href="/monitoring/sla-delay?type=tiba" class="small-box bg-danger">
            <p>Delay Tiba</p>
            <h3>{{ $total_tiba_delay }}</h3>
        </a>

        <a href="#" class="small-box bg-warning">
            <p>Final Delay</p>
            <h3>{{ $total_final_delay }}</h3>
        </a>

        <a href="/monitoring/bongkar/ontime" class="small-box bg-purple">
            <p>Bongkar On Time</p>
            <h3>{{ $total_bongkar_ontime }}</h3>
        </a>

        <a href="/monitoring/bongkar/delay" class="small-box bg-danger">
            <p>Bongkar Delay</p>
            <h3>{{ $total_bongkar_delay }}</h3>
        </a>

    </div>

    <!-- ================= CHART ================= -->
    <div class="chart-grid">

        <!-- SLA -->
        <div class="chart-box">

            <h3>📊 SLA Performance</h3>

            <div class="chart-wrapper">
                <canvas id="slaChart"></canvas>
            </div>

        </div>

        <!-- BONGKAR -->
        <div class="chart-box">

            <h3>📦 Bongkar Performance</h3>

            <div class="chart-wrapper">
                <canvas id="bongkarChart"></canvas>
            </div>

        </div>

    </div>

    <div class="card">

   

    <!-- ================= SUMMARY AREA ================= -->
    <div class="table-box">

        <h3>📍 Summary Area</h3>

        <table>

            <thead>
                <tr>
                    <th>No</th>
                    <th>Area</th>
                    <th>Total</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>

              @forelse($summary_area->take(10) as $key => $a)

<tr>
    <td>{{ $key+1 }}</td>

    <td>{{ $a->area }}</td>

    <td>{{ $a->total }}</td>

    <td>
        <a 
            href="{{ route('monitoring.summary.area.detail', ['area' => $a->area]) }}"
            class="detail-btn">
            Detail
        </a>
    </td>
</tr>

@empty

<tr>
    <td colspan="4">Tidak ada data</td>
</tr>

@endforelse
            </tbody>

        </table>

    </div>

</div>

<div class="table-box">

<h3>📋 Summary Monitoring</h3>

<table>
    <thead>
        <tr>
            <th>Kategori</th>
            <th>Total</th>
            <th>Persentase</th>
        </tr>
    </thead>

    <tbody>

        {{-- ON TIME --}}
        <tr>
            <td>On Time (Tiba)</td>
            <td>{{ $total_tiba_ontime }}</td>
            <td>
                {{ $total_data > 0 ? number_format(($total_tiba_ontime / $total_data) * 100, 2) : 0 }}%
            </td>
        </tr>

        {{-- DELAY --}}
        <tr>
            <td>Delay (Tiba)</td>
            <td>{{ $total_tiba_delay }}</td>
            <td>
                {{ $total_data > 0 ? number_format(($total_tiba_delay / $total_data) * 100, 2) : 0 }}%
            </td>
        </tr>

        {{-- BONGKAR ONTIME --}}
        <tr>
            <td>Bongkar On Time</td>
            <td>{{ $total_bongkar_ontime }}</td>
            <td>
                {{ $total_data > 0 ? number_format(($total_bongkar_ontime / $total_data) * 100, 2) : 0 }}%
            </td>
        </tr>

        {{-- BONGKAR DELAY --}}
        <tr>
            <td>Bongkar Delay</td>
            <td>{{ $total_bongkar_delay }}</td>
            <td>
                {{ $total_data > 0 ? number_format(($total_bongkar_delay / $total_data) * 100, 2) : 0 }}%
            </td>
        </tr>

    </tbody>
</table>

</div>

<script>

/* ================= SLA CHART ================= */

new Chart(document.getElementById('slaChart'), {

    type:'bar',

    data:{
        labels:['On Time','Delay'],

        datasets:[{
            label:'Shipment',

            data:[
                {{ $total_tiba_ontime }},
                {{ $total_tiba_delay }}
            ],

            backgroundColor:[
                '#22c55e',
                '#ef4444'
            ],

            borderRadius:10
        }]
    },

    options:{
        responsive:true,
        maintainAspectRatio:false,

        plugins:{
            legend:{
                display:false
            }
        }
    }

});

/* ================= BONGKAR CHART ================= */

new Chart(document.getElementById('bongkarChart'), {

    type:'doughnut',

    data:{
        labels:['On Time','Delay'],

        datasets:[{

            data:[
                {{ $total_bongkar_ontime }},
                {{ $total_bongkar_delay }}
            ],

            backgroundColor:[
                '#8b5cf6',
                '#ef4444'
            ]

        }]
    },

    options:{
        responsive:true,
        maintainAspectRatio:false,

        plugins:{
            legend:{
                position:'bottom'
            }
        }
    }

});

</script>

</body>
</html>