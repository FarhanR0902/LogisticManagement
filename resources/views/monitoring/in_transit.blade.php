<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IN TRANSIT</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f1f5f9; margin: 0; color: #1e293b; }
        .container { margin-left: 260px; padding: 30px; }
        h2 { font-size: 30px; font-weight: 700; margin: 0 0 4px; color: #0f172a; }
        .subtitle { color: #64748b; margin: 0 0 22px; font-size: 14px; }

        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 16px; margin-bottom: 20px; }
        .stat { background: #fff; border-radius: 14px; padding: 18px 20px; box-shadow: 0 4px 16px rgba(0,0,0,.07); border-left: 6px solid #64748b; }
        .stat .label { font-size: 13px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: .4px; }
        .stat .value { font-size: 32px; font-weight: 800; margin-top: 4px; }
        .stat.total   { border-color: #2563eb; }
        .stat.overdue { border-color: #ef4444; } .stat.overdue .value { color: #ef4444; }
        .stat.soon    { border-color: #f97316; } .stat.soon .value    { color: #f97316; }
        .stat.ontrack { border-color: #22c55e; } .stat.ontrack .value { color: #16a34a; }

        .filter-box { background: #fff; padding: 16px 20px; border-radius: 14px; box-shadow: 0 4px 16px rgba(0,0,0,.07); margin-bottom: 20px; }
        .filter-box form { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .filter-box input, .filter-box select { padding: 11px 14px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 15px; min-width: 190px; outline: none; }
        .filter-box input:focus, .filter-box select:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.15); }
        .btn { padding: 11px 20px; border: none; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; color: #fff; text-decoration: none; display: inline-block; }
        .btn-primary { background: #2563eb; } .btn-reset { background: #ef4444; }

        .card { background: #fff; padding: 16px; border-radius: 14px; box-shadow: 0 4px 20px rgba(0,0,0,.08); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { background: #2563eb; color: #fff; padding: 13px 12px; white-space: nowrap; text-align: center; font-weight: 600; border: 1px solid #dbeafe; }
        td { padding: 11px 12px; border: 1px solid #e2e8f0; white-space: nowrap; vertical-align: middle; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        tbody tr:hover { background: #dbeafe; }
        td.center { text-align: center; }

        .badge { display: inline-block; padding: 6px 12px; border-radius: 999px; font-size: 13px; font-weight: 700; color: #fff; }
        .green { background: #22c55e; } .red { background: #ef4444; } .orange { background: #f97316; }
        .blue { background: #3b82f6; } .gray { background: #94a3b8; }

        .empty { text-align: center; padding: 40px; color: #64748b; font-size: 16px; }
        .pagination-wrap { margin-top: 16px; }

        @media (max-width: 768px) {
            .container { margin-left: 0; padding: 15px; }
            .filter-box input, .filter-box select, .btn { width: 100%; }
        }
    </style>
</head>

<body>

    @include('template.sidebar')

    <div class="container">

        <h2>🚚 In Transit</h2>
      
        <div class="filter-box">
<form method="GET" action="{{ $formRoute ?? route('monitoring.intransit') }}">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari shipment / tujuan / ekspedisi / driver / no pol...">

                <select name="area">
                    <option value="">Semua Area</option>
                    @foreach($areaList as $a)
                        <option value="{{ $a }}" {{ request('area') == $a ? 'selected' : '' }}>{{ $a }}</option>
                    @endforeach
                </select>

                <select name="pic_monitoring">
                    <option value="">Semua PIC</option>
                    @foreach($picList as $p)
                        <option value="{{ $p }}" {{ request('pic_monitoring') == $p ? 'selected' : '' }}>{{ $p }}</option>
                    @endforeach
                </select>

                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Filter</button>
             <a href="{{ $formRoute ?? route('monitoring.intransit') }}" class="btn btn-reset">Reset</a>
        </div>

        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>No Shipment</th>
                        <th>Tujuan</th>
                        <th>Area</th>
                        <th>Dist Channel</th>
                        <th>Ekspedisi</th>
                        <th>Mobil</th>
                        <th>Nama Driver</th>
                        <th>No Pol</th>
                        <th>PIC Monitoring</th>
                        <th>Keluar Dari</th>
                        <th>Tanggal Keluar</th>
                        <th>Lama Di Jalan</th>
                        <th>Estimasi Tiba</th>
                        <th>Alert</th>
                        <th>Remarks</th>
                   
                        
                    </tr>
                </thead>
                <tbody>
                    @forelse($list as $i => $r)
                        <tr>
                            <td class="center">{{ $list->firstItem() + $i }}</td>
                            <td><b>{{ $r->no_shipment }}</b></td>
                            <td>{{ $r->tujuan }}</td>
                            <td>{{ $r->area }}</td>
                            <td>{{ $r->dist_channel }}</td>
                            <td>{{ $r->ekpedisi }}</td>
                            <td>{{ $r->mobil }}</td>
                            <td>{{ $r->nama_driver }}</td>
                            <td>{{ $r->no_pol }}</td>
                            <td>{{ $r->pic_monitoring ?: '-' }}</td>
                            <td class="center"><span class="badge blue">{{ $r->gudang_asal }}</span></td>
                            <td class="center">{{ $r->keluar_label }}</td>
                            <td class="center">{{ $r->hari_transit !== null ? $r->hari_transit . ' Hari' : '-' }}</td>
                            <td class="center">{{ $r->estimasi_label }}</td>
                            <td class="center"><span class="badge {{ $r->alert_class }}">{{ $r->alert_label }}</span></td>
                            <td>{{ $r->remarks }}</td>
                     
                        </tr>
                    @empty
                        <tr>
                            <td colspan="17" class="empty">✅ Tidak ada shipment yang sedang in transit.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="pagination-wrap">
                {{ $list->links() }}
            </div>
        </div>

    </div>

</body>

</html>