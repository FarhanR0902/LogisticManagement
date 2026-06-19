@include('template.sidebar')

<!DOCTYPE html>
<html>
<head>
    <title>Detail Area: {{ $area }}</title>

    <style>
        body{
            font-family: 'Segoe UI', sans-serif;
            background: #f4f6f9;
            margin: 0;
        }

        .container{
            margin-left: 260px;
            padding: 20px;
        }

        h3{
            margin-bottom: 15px;
            color: #111827;
        }

        table{
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        thead{
            background: #111827;
            color: #fff;
        }

        th, td{
            padding: 12px 10px;
            text-align: left;
            font-size: 14px;
        }

        tbody tr{
            border-bottom: 1px solid #e5e7eb;
        }

        tbody tr:hover{
            background: #f9fafb;
        }

        .empty{
            text-align: center;
            padding: 20px;
            color: #6b7280;
        }

        .back-btn{
            display: inline-block;
            margin-top: 15px;
            padding: 10px 14px;
            background: #2563eb;
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            transition: 0.2s;
        }

        .back-btn:hover{
            background: #1d4ed8;
        }

        .badge{
            display: inline-block;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            background: #e5e7eb;
        }

    </style>
</head>

<body>

<div class="container">

    <h3>📍 Detail Area: {{ $area }}</h3>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>No Shipment</th>
                <th>Tujuan</th>
                <th>Area</th>
                <th>Ekspedisi</th>
            </tr>
        </thead>

        <tbody>
            @forelse($logistik as $key => $row)
            <tr>
                <td>{{ $key+1 }}</td>
                <td>{{ $row->tanggal_naik_logistik }}</td>
                <td><span class="badge">{{ $row->no_shipment }}</span></td>
                <td>{{ $row->tujuan }}</td>
                <td>{{ $row->area }}</td>
                <td>{{ $row->ekpedisi }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="empty">Tidak ada data</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <a href="{{ route('monitoring.summary.area') }}" class="back-btn">
        ← Kembali
    </a>

</div>

</body>
</html>