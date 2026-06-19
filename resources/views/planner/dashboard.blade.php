@include('template.sidebar')

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planner Dashboard</title>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: #eef2f7;
        }

        .container {
            width: calc(100% - 250px);
            margin-left: 250px;
            padding: 25px;
        }

        /* ================= TITLE ================= */

        .page-title {
            margin-bottom: 25px;
        }

        .page-title h2 {
            font-size: 28px;
            color: #111827;
        }

        /* ================= KPI ================= */

        .kpi-row {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 18px;
            margin-bottom: 25px;
        }

        .kpi {
            border-radius: 16px;
            padding: 22px;
            color: white;
            min-height: 120px;

            display: flex;
            flex-direction: column;
            justify-content: space-between;

            transition: .3s;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .08);
        }

        .kpi:hover {
            transform: translateY(-4px);
        }

        .kpi h4 {
            font-size: 15px;
            font-weight: 500;
        }

        .kpi h2 {
            font-size: 34px;
            margin-top: 10px;
            font-weight: bold;
        }

        /* ================= COLORS ================= */

        .blue {
            background: #3b82f6;
        }

        .green {
            background: #22c55e;
        }

        .red {
            background: #ef4444;
        }

        .orange {
            background: #f59e0b;
        }

        .purple {
            background: #8b5cf6;
        }

        /* ================= CARD ================= */

        .card {
            background: white;
            border-radius: 16px;
            padding: 22px;
            margin-bottom: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .05);
        }

        .card h3 {
            margin-bottom: 18px;
            color: #111827;
        }

        /* ================= CHART ================= */

        .chart-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }

        .chart-card {
            background: white;
            border-radius: 16px;
            padding: 22px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .05);

            height: 420px;

            display: flex;
            flex-direction: column;
        }

        .chart-card h3 {
            margin-bottom: 18px;
            color: #111827;
        }

        .chart-wrapper {
            flex: 1;
            position: relative;
        }

        /* ================= INSIGHT ================= */

        .insight {
            line-height: 2;
            color: #374151;
            font-size: 15px;
        }

        .insight b {
            color: #111827;
        }

        .badge {
            display: inline-block;
            margin-top: 15px;
            padding: 8px 14px;
            border-radius: 10px;
            color: white;
            font-size: 12px;
            font-weight: bold;
        }

        .badge-green {
            background: #22c55e;
        }

        .badge-orange {
            background: #f59e0b;
        }

        .badge-red {
            background: #ef4444;
        }

        /* ================= TABLE ================= */

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #111827;
            color: white;
            padding: 14px;
            font-size: 14px;
        }

        td {
            padding: 14px;
            border-bottom: 1px solid #e5e7eb;
            text-align: center;
            font-size: 14px;
        }

        tr:hover {
            background: #f9fafb;
        }

        /* ================= LINK ================= */

        a {
            text-decoration: none;
            color: inherit;
        }

        /* ================= RESPONSIVE ================= */

        @media(max-width:1400px) {

            .kpi-row {
                grid-template-columns: repeat(3, 1fr);
            }

        }

        @media(max-width:1000px) {

            .chart-grid {
                grid-template-columns: 1fr;
            }

            .kpi-row {
                grid-template-columns: repeat(2, 1fr);
            }

        }

        @media(max-width:768px) {

            .container {
                width: 100%;
                margin-left: 0;
                padding: 15px;
            }

            .kpi-row {
                grid-template-columns: 1fr;
            }

        }
    </style>
</head>

<body>

    @php

    $valid_total = ($ontime + $delay);

$ontime_rate = $valid_total > 0
    ? round(($ontime / $valid_total) * 100, 2)
    : 0;

$delay_rate = $valid_total > 0
    ? round(($delay / $valid_total) * 100, 2)
    : 0;
    $armada = $armada ?? 0;
    $belum_armada = $belum_armada ?? 0;

    $ontime_rate = $total_data > 0
    ? round(($ontime / $total_data) * 100, 2)
    : 0;

    $delay_rate = $total_data > 0
    ? round(($delay / $total_data) * 100, 2)
    : 0;

    $armada_total = $armada + $belum_armada;

    $armada_rate = $armada_total > 0
    ? round(($armada / $armada_total) * 100, 2)
    : 0;

    @endphp

    <div class="container">

        <!-- ================= TITLE ================= -->

        <div class="page-title">
            <h2>📦 PLANNER DASHBOARD</h2>
        </div>

        <!-- ================= KPI ================= -->

       <div class="kpi-row">

    <a href="{{ url('/datalogistik') }}">
        <div class="kpi blue">
            <h4>Total Shipment</h4>
            <h2>{{ $total_data }}</h2>
        </div>
    </a>

    <a href="{{ route('armada') }}">
        <div class="kpi green">
            <h4>Armada Ready</h4>
            <h2>{{ $armada }}</h2>
        </div>
    </a>

    <a href="{{ route('planner.belum_armada') }}">
        <div class="kpi purple">
            <h4>Pending Armada</h4>
            <h2>{{ $belum_armada }}</h2>
        </div>
    </a>

    <a href="{{ route('planner.sla.ontime') }}">
        <div class="kpi orange">
            <h4>SLA On Time</h4>
            <h2>{{ $ontime }}</h2>
        </div>
    </a>

    <a href="{{ route('armada.delay') }}">
        <div class="kpi red">
            <h4>SLA Delay</h4>
            <h2>{{ $delay }}</h2>
        </div>
    </a>

</div>
        <!-- ================= EXECUTIVE INSIGHT ================= -->

        <!-- <div class="card">

    <h3>📊 Executive Insight</h3>

    <div class="insight">

        <p>
            Total shipment :
            <b>{{ $total_data }}</b>
        </p>

        <p>
            Total On Time :
            <b>{{ $ontime }}</b>
            ({{ $ontime_rate }}%)
        </p>

        <p>
            Total Delay :
            <b>{{ $delay }}</b>
            ({{ $delay_rate }}%)
        </p>

        <p>
            Armada Ready :
            <b>{{ $armada }}</b>
            ({{ $armada_rate }}%)
        </p>

        @if($delay_rate >= 20)

            <span class="badge badge-red">
                HIGH DELAY ALERT
            </span>

        @elseif($delay_rate >= 10)

            <span class="badge badge-orange">
                MODERATE DELAY
            </span>

        @else

            <span class="badge badge-green">
                GOOD PERFORMANCE
            </span>

        @endif

    </div>

</div> -->

        <!-- ================= CHART ================= -->

        <div class="chart-grid">

            <!-- SLA -->
            <div class="chart-card">

                <h3>📊 SLA Performance</h3>

                <div class="chart-wrapper">
                    <canvas id="slaChart"></canvas>
                </div>

            </div>

            <!-- ARMADA -->
            <div class="chart-card">

                <h3>🚚 Armada Status</h3>

                <div class="chart-wrapper">
                    <canvas id="armadaChart"></canvas>
                </div>

            </div>

        </div>

        <!-- ================= TABLE ================= -->

        <div class="card">

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
                        <td>{{ $ontime }}</td>
                        <td>{{ $ontime_rate }}%</td>
                    </tr>

                    <tr>
                        <td>Delay</td>
                        <td>{{ $delay }}</td>
                        <td>{{ $delay_rate }}%</td>
                    </tr>

                    <tr>
                        <td>Armada Ready</td>
                        <td>{{ $armada }}</td>
                        <td>{{ $armada_rate }}%</td>
                    </tr>

                    <tr>
                        <td>Pending Armada</td>
                        <td>{{ $belum_armada }}</td>
                        <td>{{ 100 - $armada_rate }}%</td>
                    </tr>

                </tbody>

            </table>

        </div>

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

                    @forelse($summary_area as $key => $s)

                    <tr>
                        <td>{{ $key+1 }}</td>
                        <td>{{ $s->area }}</td>
                        <td>{{ $s->total }}</td>
                        <td>
                            <a href="{{ route('monitoring.summary.area.detail', ['area' => $s->area]) }}">
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

    </div>

    <script>
    /* ================= SLA CHART ================= */
    new Chart(document.getElementById('slaChart'), {
        type: 'bar',
        data: {
            labels: ['On Time', 'Delay'],
            datasets: [{
                label: 'Shipment',
                data: [{{ $ontime }}, {{ $delay }}],
                backgroundColor: ['#22c55e', '#ef4444'],
                borderRadius: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let total = {{ $ontime }} + {{ $delay }};
                            let pct = total > 0 ? ((context.raw / total) * 100).toFixed(1) : 0;
                            return `${context.raw} Shipment (${pct}%)`;
                        }
                    }
                }
            },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 } }
            },
            onClick: function(evt, e) {
                if (e.length) {
                    let index = e[0].index;
                    if (index === 0) window.location.href = "{{ url('/planner/sla-ontime') }}";
                    if (index === 1) window.location.href = "{{ url('/planner/sla-delay') }}";
                }
            }
        }
    });

    /* ================= ARMADA CHART ================= */
    new Chart(document.getElementById('armadaChart'), {
        type: 'doughnut',
        data: {
            labels: ['Ready', 'Pending'],
            datasets: [{
                data: [{{ $armada }}, {{ $belum_armada }}],
                backgroundColor: ['#3b82f6', '#8b5cf6']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let total = {{ $armada }} + {{ $belum_armada }};
                            let pct = total > 0 ? ((context.raw / total) * 100).toFixed(1) : 0;
                            return `${context.labels} : ${context.raw} (${pct}%)`;
                        }
                    }
                }
            },
            onClick: function(evt, e) {
                if (e.length) {
                    let index = e[0].index;
                    if (index === 0) window.location.href = "{{ url('/planner/armada') }}";
                    if (index === 1) window.location.href = "{{ url('/planner/belum-armada') }}";
                }
            }
        }
    });
</script>
</body>

</html>