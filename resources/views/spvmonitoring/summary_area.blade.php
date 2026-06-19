@include('template.sidebar')

<!DOCTYPE html>
<html>
<head>
    <title>Summary Area Monitoring</title>

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

        a{
            display: inline-block;
            padding: 6px 10px;
            background: #2563eb;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            font-size: 12px;
            transition: 0.2s;
        }

        a:hover{
            background: #1d4ed8;
        }

        .empty{
            text-align: center;
            padding: 20px;
            color: #6b7280;
        }

    </style>
</head>

<body>

<div class="container">

    <h3>📍 Summary Area Monitoring</h3>

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
             <td>{{ $s->total_shipment }}</td>
                <td>
                    <a href="{{ route('monitoring.summary.area.detail', ['area' => $s->area]) }}">
                        Detail
                    </a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="4" class="empty">Tidak ada data</td>
            </tr>
            @endforelse
        </tbody>
    </table>

</div>

</body>
</html>