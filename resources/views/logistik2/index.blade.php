<!DOCTYPE html>
<html>
<head>
    <title>Logistik 2</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body { background:#f1f5f9; font-family:Segoe UI; }
        .container { margin-left:240px; padding:20px; }

        table {
            font-size:14px;
            background:white;
        }

        th {
            background:#2563eb;
            color:white;
        }

        td, th {
            padding:10px;
            white-space:nowrap;
        }

        .badge {
            padding:6px 10px;
            border-radius:20px;
            color:white;
            font-size:12px;
        }

        .green { background:#22c55e; }
        .red { background:#ef4444; }
        .orange { background:#f97316; }
        .gray { background:#64748b; }
    </style>
</head>

<body>

<div class="container">

    <h2>📦 LOGISTIK 2</h2>

    <!-- FILTER -->
    <form method="GET" class="mb-3">
        <select name="area" class="form-control" style="width:200px; display:inline;">
            <option value="">-- Area --</option>
            @foreach($areaList as $a)
                <option value="{{ $a }}">{{ $a }}</option>
            @endforeach
        </select>

        <input type="text" name="search" placeholder="Search..." class="form-control" style="width:200px; display:inline;">

        <button class="btn btn-primary">Filter</button>
    </form>

    <!-- TABLE -->
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>No Shipment</th>
                <th>Area</th>
                <th>Tujuan</th>
                <th>Dist Channel</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>

        <tbody>
            @foreach($data as $r)
            <tr>
                <td>{{ $r->no_shipment }}</td>
                <td>{{ $r->area }}</td>
                <td>{{ $r->tujuan }}</td>

                <td>
                    <span class="badge gray">
                        {{ $r->dist_channel ?? '-' }}
                    </span>
                </td>

                <td>
                    @if($r->status_pengiriman == 'On Time')
                        <span class="badge green">On Time</span>
                    @else
                        <span class="badge red">Delay</span>
                    @endif
                </td>

                <td>
                    <a href="/logistik2/{{ $r->id }}" class="btn btn-sm btn-info">Detail</a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

</div>

</body>
</html>