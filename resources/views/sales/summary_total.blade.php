<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Summary Total</title>

    <style>
        body{
            font-family: Arial;
            background:#f4f6f9;
        }

        .container{
            margin-left:260px;
            padding:20px;
        }

        table{
            width:100%;
            border-collapse:collapse;
            background:#fff;
        }

        th, td{
            padding:10px;
            border:1px solid #ddd;
            text-align:left;
        }

        th{
            background:#111827;
            color:#fff;
        }

        h2{
            margin-bottom:15px;
        }

        .card{
            display:inline-block;
            padding:15px;
            background:#22c55e;
            color:#fff;
            margin-bottom:20px;
            border-radius:8px;
        }
    </style>
</head>

<body>

@include('template.sidebar')

<div class="container">

    <h2>📊 SUMMARY TOTAL LOGISTIK</h2>

    <div class="card">
        Total Data: {{ $logistik->count() }}
    </div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal Naik</th>
                <th>No Shipment</th>
                <th>Tujuan</th>
                <th>Area</th>
                <th>Status Gudang</th>
                <th>Status Akhir</th>
            </tr>
        </thead>

        <tbody>
            @foreach($logistik as $i => $row)
            <tr>
                <td>{{ $i+1 }}</td>
                <td>{{ $row->tanggal_naik_logistik ?? '-' }}</td>
                <td>{{ $row->no_shipment ?? '-' }}</td>
                <td>{{ $row->tujuan ?? '-' }}</td>
                <td>{{ $row->area ?? '-' }}</td>
                <td>{{ $row->status ?? '-' }}</td>
                <td>{{ $row->status_akhir ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

</div>

</body>
</html>