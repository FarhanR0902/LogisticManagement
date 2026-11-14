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
.badge{display:inline-block;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:600;text-decoration:none;margin:2px 2px 2px 0}
.badge-green{background:#dcfce7;color:#16a34a}
.badge-red{background:#fee2e2;color:#dc2626}
.td-left{text-align:left}
.mt{margin-top:25px}

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
.bg-transit{background:#14b8a6;}
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
          $total_in_transit = $total_in_transit ?? 0;  
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
        <a href="{{ route('monitoring.intransit') }}" class="small-box bg-transit">
    <p>🚚 In Transit</p>
    <h3>{{ $total_in_transit }}</h3>
</a>

        <a href="/monitoring/sla-ontime" class="small-box bg-success">
            <p>On Time Tiba</p>
            <h3>{{ $total_tiba_ontime }}</h3>
        </a>

        <a href="/monitoring/sla-delay?type=tiba" class="small-box bg-danger">
            <p>Delay Tiba</p>
            <h3>{{ $total_tiba_delay }}</h3>
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
@php
    $bulanId = ['01'=>'Jan','02'=>'Feb','03'=>'Mar','04'=>'Apr','05'=>'Mei','06'=>'Jun',
                '07'=>'Jul','08'=>'Agu','09'=>'Sep','10'=>'Okt','11'=>'Nov','12'=>'Des'];
    $fmtPeriode = function ($p) use ($bulanId) {
        $p = trim((string) $p);
        if ($p === '' || !str_contains($p, '-')) return 'Tanpa periode';
        [$m, $y] = explode('-', $p, 2);
        return ($bulanId[$m] ?? $m) . ' ' . $y;
    };
    $alertBelum = $alert_pic_belum ?? collect();
    $alertSudah = $alert_pic_sudah ?? collect();
    $punyaRoute = \Illuminate\Support\Facades\Route::has('manager.gabungan.alert');
@endphp

<div class="table-box mt">
    <h3>🕵️ Summary PIC Monitoring</h3>
    <div style="overflow-x:auto">
    <table>
        <thead>
            <tr>
                <th>PIC Monitoring</th>
                <th>Jumlah</th>
                <th>On Time</th>
                <th>Delay</th>
                <th>Belum Input Tgl Tiba</th>
                <th>Belum Tiba &amp; Lewat Estimasi</th>
                <th>Belum Input Sama Sekali</th>
                <th>Sudah Keluar Gudang, Belum Input Tiba</th>
            </tr>
        </thead>
        <tbody>
        @forelse(($summary_pic_monitoring ?? collect()) as $m)
            @php
                $nama  = $m->pic_monitoring;
                $belum = $alertBelum[$nama] ?? collect();
                $sudah = $alertSudah[$nama] ?? collect();
                $link  = fn($tipe, $periode) => $punyaRoute
                    ? route('manager.gabungan.alert', ['tipe' => $tipe, 'nama' => $nama,
                        'periode_shipment' => $periode ?: 'KOSONG', 'sumber' => 'JAKARTA'])
                    : null;
                    $link  = fn($tipe, $periode) => $punyaRoute
    ? route('manager.gabungan.alert', [
        'tipe' => $tipe,
        'nama' => $nama,
        'periode_shipment' => $periode ?: 'KOSONG',
        'sumber' => 'JAKARTA',
        'back' => route('monitoring.dashboard', [], false),
    ])
    : null;
            @endphp
            <tr>
                <td>{{ $nama }}</td>
                <td>{{ number_format($m->total) }}</td>
                <td><span class="badge badge-green">{{ number_format($m->total_ontime) }}</span></td>
                <td><span class="badge badge-red">{{ number_format($m->total_delay) }}</span></td>
                <td><span class="badge {{ $m->total_belum_tiba > 0 ? 'badge-red' : 'badge-green' }}">{{ number_format($m->total_belum_tiba) }}</span></td>
                <td><span class="badge {{ $m->total_lewat_estimasi > 0 ? 'badge-red' : 'badge-green' }}">{{ number_format($m->total_lewat_estimasi) }}</span></td>

                <td class="td-left">
                    @forelse($belum as $r)
                        <a @if($u = $link('monitoring_belum', $r->periode_shipment)) href="{{ $u }}" @endif class="badge badge-red">
                            ⚠️ {{ $fmtPeriode($r->periode_shipment) }}: {{ number_format($r->total) }}
                        </a>
                    @empty
                        <span class="badge badge-green">✓ Aman</span>
                    @endforelse
                </td>

                <td class="td-left">
                    @forelse($sudah as $r)
                        <a @if($u = $link('monitoring_sudah', $r->periode_shipment)) href="{{ $u }}" @endif class="badge badge-red">
                            ⚠️ {{ $fmtPeriode($r->periode_shipment) }}: {{ number_format($r->total) }}
                        </a>
                    @empty
                        <span class="badge badge-green">✓ Aman</span>
                    @endforelse
                </td>
            </tr>
        @empty
            <tr><td colspan="8">Belum ada data PIC monitoring</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
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