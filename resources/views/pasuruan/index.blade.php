@include('template.sidebar')

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logistik Pasuruan</title>

    <style>
        body{
            font-family: Arial;
            background:#f5f5f5;
            margin:0;
        }

        .container{
            margin-left:250px;
            padding:20px;
        }

        table{
            width:100%;
            border-collapse:collapse;
            background:#fff;
        }

        th,td{
            border:1px solid #ddd;
            padding:8px;
            text-align:center;
        }

        th{
            background:#0d6efd;
            color:#fff;
        }
    </style>
</head>
<body>

<div class="container">

    <h2>Logistik Pasuruan</h2>

    <table>
        <thead>
        <tr>
            <th>No Shipment</th>
            <th>Planner</th>
            <th>Tujuan</th>
            <th>Area</th>
        </tr>
        </thead>

        <tbody>

        @forelse($logistik as $row)

        <tr>
            <td>{{ $row->no_shipment_pasuruan }}</td>
            <td>{{ $row->planner_pasuruan }}</td>
            <td>{{ $row->tujuan_pasuruan }}</td>
            <td>{{ $row->area_pasuruan }}</td>
        </tr>

        @empty

        <tr>
            <td colspan="4">Belum ada data</td>
        </tr>

        @endforelse

        </tbody>

    </table>

</div>

</body>
</html>