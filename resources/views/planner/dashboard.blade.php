@include('template.sidebar')

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planner Dashboard</title>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #eef2f7; }
        .container { width: calc(100% - 250px); margin-left: 250px; padding: 35px; }

        .page-title { margin-bottom: 25px; }
        .page-title h2 { font-size: 38px; font-weight: 700; color: #111827; }

        .kpi-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
            margin-bottom: 25px;
        }
        .kpi {
            border-radius: 18px;
            padding: 28px;
            color: white;
            min-height: 170px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: .3s;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .08);
        }
        .kpi:hover { transform: translateY(-4px); }
        .kpi h4 { font-size: 25px; font-weight: 600; }
        .kpi h2 { font-size: 34px; margin-top: 10px; font-weight: bold; }

        .blue { background: #3b82f6; }
        .teal { background: #00f1e1; }
        .green { background: #22c55e; }
        .red { background: #ef4444; }
        .orange { background: #f59e0b; }
        .purple { background: #8b5cf6; }
        .pink { background: #ec4899; }

        .card {
            background: white;
            border-radius: 16px;
            padding: 22px;
            margin-bottom: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .05);
        }
        .card h3 { margin-bottom: 18px; color: #111827; }

        .chart-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; }
        .chart-card {
            background: white;
            border-radius: 16px;
            padding: 22px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .05);
            height: 420px;
            display: flex;
            flex-direction: column;
        }
        .chart-card h3 { margin-bottom: 18px; color: #111827; }
        .chart-wrapper { flex: 1; position: relative; }

        .pbadge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            margin: 2px 2px 2px 0;
        }
        .pbadge-green { background: #dcfce7; color: #16a34a; }
        .pbadge-red { background: #fee2e2; color: #dc2626; }
        .td-left { text-align: left; }

        .tbl-border { border: 1px solid #d1d5db; }
        .tbl-border th,
        .tbl-border td { border: 1px solid #d1d5db; }
        .tbl-border td.td-left { padding: 10px 14px; min-width: 340px; }

        table { width: 100%; border-collapse: collapse; }
        th { background: #111827; color: white; padding: 14px; font-size: 14px; }
        td { padding: 14px; border-bottom: 1px solid #e5e7eb; text-align: center; font-size: 14px; }
        tr:hover { background: #f9fafb; }

        a { text-decoration: none; color: inherit; }

        @media(max-width:1400px) { .kpi-row { grid-template-columns: repeat(3, 1fr); } }
        @media(max-width:1000px) {
            .chart-grid { grid-template-columns: 1fr; }
            .kpi-row { grid-template-columns: repeat(2, 1fr); }
        }
        @media(max-width:768px) {
            .container { width: 100%; margin-left: 0; padding: 15px; }
            .kpi-row { grid-template-columns: 1fr; }
        }
    </style>
</head>

<body>

@php
    $ontime       = $ontime ?? 0;
    $delay        = $delay ?? 0;
    $armada       = $armada ?? 0;
    $belum_armada = $belum_armada ?? 0;

    $tiba_rate       = $total_data > 0 ? round(($ontime / $total_data) * 100, 2) : 0;
    $belum_tiba_rate = $total_data > 0 ? round(($delay / $total_data) * 100, 2) : 0;

    $armada_total = $armada + $belum_armada;
    $armada_rate  = $armada_total > 0 ? round(($armada / $armada_total) * 100, 2) : 0;

    $bulanId = ['01'=>'Jan','02'=>'Feb','03'=>'Mar','04'=>'Apr','05'=>'Mei','06'=>'Jun',
                '07'=>'Jul','08'=>'Agu','09'=>'Sep','10'=>'Okt','11'=>'Nov','12'=>'Des'];
    $fmtPeriode = function ($p) use ($bulanId) {
        $p = trim((string) $p);
        if ($p === '' || !str_contains($p, '-')) return 'Tanpa periode';
        [$m, $y] = explode('-', $p, 2);
        return ($bulanId[$m] ?? $m) . ' ' . $y;
    };
    $alertPlanner = $alert_planner ?? collect();
    $punyaRoute   = \Illuminate\Support\Facades\Route::has('manager.gabungan.alert');
    $backUrl      = request()->getRequestUri();
@endphp

<div class="container">

    <div class="page-title">
        <h2>📦 PLANNER DASHBOARD</h2>
    </div>

    <div class="kpi-row">
        <a href="{{ url('/datalogistik') }}">
            <div class="kpi blue"><h4>Total Armada</h4><h2>{{ $total_data }}</h2></div>
        </a>
        <a href="{{ route('planner.ingudang') }}">
            <div class="kpi pink"><h4>🏭 In Gudang</h4><h2>{{ $total_in_gudang ?? 0 }}</h2></div>
        </a>
        <a href="{{ route('planner.intransit') }}">
            <div class="kpi teal"><h4>🚚 In Transit</h4><h2>{{ $total_in_transit ?? 0 }}</h2></div>
        </a>
        <a href="{{ route('planner.sla.ontime') }}">
            <div class="kpi orange"><h4>Sudah Tiba Di Gudang</h4><h2>{{ $ontime }}</h2></div>
        </a>
        <a href="{{ route('planner.sla.delay') }}">
            <div class="kpi red"><h4>Belum Tiba Di Gudang</h4><h2>{{ $delay }}</h2></div>
        </a>
        <a href="{{ route('armada') }}">
            <div class="kpi green"><h4>Armada Ready</h4><h2>{{ $armada }}</h2></div>
        </a>
        <a href="{{ route('planner.belum_armada') }}">
            <div class="kpi purple"><h4>Pending Armada</h4><h2>{{ $belum_armada }}</h2></div>
        </a>
    </div>

    <div class="chart-grid">
        <div class="chart-card">
            <h3>📊 Performance Tiba Gudang</h3>
            <div class="chart-wrapper"><canvas id="slaChart"></canvas></div>
        </div>
        <div class="chart-card">
            <h3>🚚 Armada Status</h3>
            <div class="chart-wrapper"><canvas id="armadaChart"></canvas></div>
        </div>
    </div>

    <div class="card">
        <h3>📋 Summary Planner</h3>
        <table>
            <thead>
                <tr><th>Kategori</th><th>Total</th><th>Persentase</th></tr>
            </thead>
            <tbody>
                <tr><td>Sudah Tiba Gudang</td><td>{{ $ontime }}</td><td>{{ $tiba_rate }}%</td></tr>
                <tr><td>Belum Tiba Gudang</td><td>{{ $delay }}</td><td>{{ $belum_tiba_rate }}%</td></tr>
                <tr><td>Armada Ready</td><td>{{ $armada }}</td><td>{{ $armada_rate }}%</td></tr>
                <tr><td>Pending Armada</td><td>{{ $belum_armada }}</td><td>{{ 100 - $armada_rate }}%</td></tr>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3>📍 Summary Area</h3>
        <table>
            <thead>
                <tr><th>No</th><th>Area</th><th>Total</th><th>Action</th></tr>
            </thead>
            <tbody>
            @forelse($summary_area as $area => $total)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $area ?: '-' }}</td>
                    <td>{{ $total }}</td>
                    <td>
                        <a href="{{ route('monitoring.summary.area.detail', ['area' => $area]) }}">Detail</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4">Tidak ada data</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3>👤 Summary Planner per Orang</h3>
        <div style="overflow-x:auto">
        <table class="tbl-border">
            <thead>
                <tr>
                    <th>Planner</th>
                    <th>Jumlah</th>
                    <th>Belum Dapat Armada</th>
                    <th>On Time</th>
                    <th>Delay</th>
                    <th>Keluar Gudang Terakhir Belum Diinput</th>
                </tr>
            </thead>
            <tbody>
            @forelse(($summary_planner ?? collect()) as $p)
                @php
                    $nama  = $p->planner;
                    $belum = $alertPlanner[$nama] ?? collect();
                    $link  = fn($periode) => $punyaRoute
                        ? route('manager.gabungan.alert', [
                            'tipe' => 'planner',
                            'nama' => $nama,
                            'periode_shipment' => $periode ?: 'KOSONG',
                            'sumber' => 'JAKARTA',
                            'back' => $backUrl,
                        ])
                        : null;
                @endphp
                <tr>
                    <td>{{ $nama }}</td>
                    <td>{{ number_format($p->total) }}</td>
                    <td>
                        <span class="pbadge {{ $p->total_belum_armada > 0 ? 'pbadge-red' : 'pbadge-green' }}">
                            {{ number_format($p->total_belum_armada) }}
                        </span>
                    </td>
                    <td><span class="pbadge pbadge-green">{{ number_format($p->total_ontime ?? 0) }}</span></td>
                    <td><span class="pbadge pbadge-red">{{ number_format($p->total_delay ?? 0) }}</span></td>
                    <td class="td-left">
                        @forelse($belum as $r)
                            <a @if($u = $link($r->periode_shipment)) href="{{ $u }}" @endif class="pbadge pbadge-red">
                                ⚠️ {{ $fmtPeriode($r->periode_shipment) }}: {{ number_format($r->total) }}
                            </a>
                        @empty
                            <span class="pbadge pbadge-green">✓ Aman</span>
                        @endforelse
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">Belum ada data planner</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>

</div>

<script>
    new Chart(document.getElementById('slaChart'), {
        type: 'bar',
        data: {
            labels: ['Sudah Tiba Di Gudang', 'Belum Tiba Di Gudang'],
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
                        label: function (context) {
                            let total = {{ $ontime }} + {{ $delay }};
                            let pct = total > 0 ? ((context.raw / total) * 100).toFixed(1) : 0;
                            return `${context.raw} Shipment (${pct}%)`;
                        }
                    }
                }
            },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
            onClick: function (evt, e) {
                if (e.length) {
                    let index = e[0].index;
                    if (index === 0) window.location.href = "{{ url('/planner/sla-ontime') }}";
                    if (index === 1) window.location.href = "{{ url('/planner/sla-delay') }}";
                }
            }
        }
    });

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
                        label: function (context) {
                            let total = Number({{ $armada }}) + Number({{ $belum_armada }});
                            let pct = total > 0 ? ((context.raw / total) * 100).toFixed(1) : 0;
                            return `${context.label}: ${context.raw} (${pct}%)`;
                        }
                    }
                }
            },
            onClick: function (evt, elements) {
                if (!elements.length) return;
                let index = elements[0].index;
                if (index === 0) window.location.href = "{{ url('/planner/armada') }}";
                if (index === 1) window.location.href = "{{ url('/planner/belum-armada') }}";
            }
        }
    });
</script>
</body>

</html>