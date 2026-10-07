@include('template.sidebar')

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>In Gudang</title>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #eef2f7; }
        .container { width: calc(100% - 250px); margin-left: 250px; padding: 35px; }
        .page-title { margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
        .page-title h2 { font-size: 34px; font-weight: 700; color: #111827; }
        .btn-back { background: #111827; color: #fff; padding: 10px 16px; border-radius: 10px; font-size: 14px; }

        .kpi-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px; margin-bottom: 25px; }
        .kpi { border-radius: 18px; padding: 24px; color: #fff; min-height: 130px;
               display: flex; flex-direction: column; justify-content: space-between;
               box-shadow: 0 4px 12px rgba(0,0,0,.08); }
        .kpi h4 { font-size: 20px; font-weight: 600; }
        .kpi h2 { font-size: 34px; font-weight: bold; }
        .pink { background: #ec4899; }
        .red { background: #ef4444; }
        .orange { background: #f59e0b; }

        .card { background: #fff; border-radius: 16px; padding: 22px; margin-bottom: 25px;
                box-shadow: 0 4px 12px rgba(0,0,0,.05); }

        .filter { display: flex; gap: 10px; flex-wrap: wrap; }
        .filter input, .filter select { padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 10px; font-size: 14px; }
        .filter input { min-width: 260px; }
        .filter button { padding: 10px 20px; border: 0; border-radius: 10px; background: #3b82f6; color: #fff; font-weight: 600; cursor: pointer; }
        .filter a.reset { padding: 10px 16px; border-radius: 10px; background: #e5e7eb; font-size: 14px; }

        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #111827; color: #fff; padding: 14px; font-size: 14px; white-space: nowrap; }
        td { padding: 14px; border-bottom: 1px solid #e5e7eb; text-align: center; font-size: 14px; }
        tr:hover { background: #f9fafb; }

        .badge { display: inline-block; padding: 6px 12px; border-radius: 10px; color: #fff; font-size: 12px; font-weight: bold; }
        .pagination-wrap { margin-top: 18px; }
        a { text-decoration: none; color: inherit; }

        @media(max-width:768px) {
            .container { width: 100%; margin-left: 0; padding: 15px; }
        }
    </style>
</head>

<body>
<div class="container">

    <div class="page-title">
        <h2>🏭 IN GUDANG</h2>
        <a href="{{ url('/planner/dashboard') }}" class="btn-back">← Dashboard</a>
    </div>

    <div class="kpi-row">
        <div class="kpi pink">
            <h4>Total In Gudang</h4>
            <h2>{{ $summary['total'] }}</h2>
        </div>
        <div class="kpi red">
            <h4>Belum Diinput</h4>
            <h2>{{ $summary['belum_input'] }}</h2>
        </div>
        <div class="kpi orange">
            <h4>Belum Keluar Gudang</h4>
            <h2>{{ $summary['sedang'] }}</h2>
        </div>
    </div>

    <div class="card">
        <form method="GET" action="{{ route('planner.ingudang') }}" class="filter">
            <input type="text" name="q" placeholder="Cari shipment / tujuan / driver..." value="{{ request('q') }}">

            <select name="area">
                <option value="">Semua Area</option>
                @foreach($areaList as $a)
                    <option value="{{ $a }}" @selected(request('area') === $a)>{{ $a }}</option>
                @endforeach
            </select>

            <select name="planner">
                <option value="">Semua Planner</option>
                @foreach($plannerList as $p)
                    <option value="{{ $p }}" @selected(request('planner') === $p)>{{ $p }}</option>
                @endforeach
            </select>

            <button type="submit">Filter</button>
            <a href="{{ route('planner.ingudang') }}" class="reset">Reset</a>
        </form>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>No Shipment</th>
                        <th>Planner</th>
                        <th>Tujuan</th>
                        <th>Area</th>
                        <th>Rencana Kirim</th>
                        <th>Ekspedisi</th>
                        <th>Driver / No Pol</th>
                        <th>Gudang Selesai</th>
                        <th>Posisi / Status</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($list as $r)
                    <tr>
                        <td>{{ $list->firstItem() + $loop->index }}</td>
                        <td><b>{{ $r->no_shipment }}</b></td>
                        <td>{{ $r->planner ?: '-' }}</td>
                        <td>{{ $r->tujuan ?: '-' }}</td>
                        <td>{{ $r->area ?: '-' }}</td>
                        <td>{{ $r->rencana_kirim ? date('d-m-Y', strtotime($r->rencana_kirim)) : '-' }}</td>
                        <td>{{ $r->ekpedisi ?: '-' }}</td>
                        <td>{{ $r->nama_driver ?: '-' }} / {{ $r->no_pol ?: '-' }}</td>
                        <td>{{ $r->gudang_selesai }}</td>
                        <td><span class="badge {{ $r->posisi_class }}">{{ $r->posisi_label }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="10">Tidak ada shipment yang masih di gudang</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination-wrap">
            {{ $list->links() }}
        </div>
    </div>

</div>
</body>
</html>