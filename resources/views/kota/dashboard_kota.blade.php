@include('template.sidebar')

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DASHBOARD KOTA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body { background: #f3f4f6; font-family: 'Segoe UI'; margin: 0; }
        .container-fluid { margin-left: 250px; width: calc(100% - 250px); padding: 20px; }
        .title { font-size: 24px; font-weight: bold; margin-bottom: 20px; color: #111827; }
        .stat-card { background: #fff; border-radius: 12px; padding: 18px; box-shadow: 0 5px 20px rgba(0,0,0,.08); }
        .stat-value { font-size: 28px; font-weight: bold; color: #111827; }
        .stat-label { font-size: 13px; color: #6b7280; }
        .row-cards { display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 20px; }
        .row-cards .stat-card { flex: 1; min-width: 180px; }
    </style>
</head>

<body>
    <div class="container-fluid px-3">
        <div class="title">🏙️ DASHBOARD KOTA</div>

        <div class="row-cards">
            <div class="stat-card">
                <div class="stat-value">{{ $total_data }}</div>
                <div class="stat-label">Total Shipment</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">{{ $total_tiba_ontime }}</div>
                <div class="stat-label">Tiba On Time</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">{{ $total_tiba_delay }}</div>
                <div class="stat-label">Tiba Delay</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">{{ $total_bongkar_ontime }}</div>
                <div class="stat-label">Bongkar On Time</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">{{ $total_bongkar_delay }}</div>
                <div class="stat-label">Bongkar Delay</div>
            </div>
        </div>

        <div class="row-cards">
            <div class="stat-card">
                <div class="stat-value">{{ $belum_tiba }}</div>
                <div class="stat-label">Belum Tiba</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">{{ $belum_bongkar }}</div>
                <div class="stat-label">Belum Bongkar</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">{{ $delivered_ontime }}</div>
                <div class="stat-label">Delivered On Time</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">{{ $delivered_delay }}</div>
                <div class="stat-label">Delivered Delay</div>
            </div>
        </div>

        <div class="stat-card">
            <b>Summary per Area</b>
            <table class="table table-sm mt-2">
                <thead>
                    <tr><th>Area</th><th>Total</th></tr>
                </thead>
                <tbody>
                    @foreach($summary_area as $s)
                    <tr>
                        <td>{{ $s->area_kota }}</td>
                        <td>{{ $s->total }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <a href="{{ route('kota.datalogistik') }}" class="btn btn-primary mt-3">
            Lihat Data Monitoring Kota
        </a>
    </div>
</body>
</html>