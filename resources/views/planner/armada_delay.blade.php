@include('template.sidebar')

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Armada Delay</title>

    <style>
        body {
            font-family: Arial;
            background: #f5f5f5;
            margin: 0;
        }

        .container {
            margin-left: 250px;
            padding: 20px;
        }

        h2 {
            margin-bottom: 15px;
        }

        .topbar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 15px;
            align-items: center;
        }

        .btn {
            padding: 8px 12px;
            border-radius: 6px;
            text-decoration: none;
            color: white;
            font-size: 13px;
            border: none;
            cursor: pointer;
        }

        .btn-blue    { background: #007bff; }
        .btn-warning { background: #ffc107; color: black; }

        select {
            padding: 7px;
            border-radius: 5px;
        }

        .table-container {
            background: white;
            border-radius: 10px;
            overflow: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            white-space: nowrap;
        }

        th, td {
            padding: 8px;
            border: 1px solid #ddd;
            font-size: 13px;
            text-align: center;
        }

        th {
            background: #ffc107;
            color: black;
            position: sticky;
            top: 0;
        }

        .badge-delay {
            background: #ffc107;
            color: black;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: bold;
        }

        .badge-success {
            background: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
        }

        .total-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 10px 18px;
            font-size: 14px;
            font-weight: bold;
            color: #856404;
        }
    </style>
</head>

<body>

@php
    $bulan = request('bulan');
    $tahun = request('tahun');
@endphp

<div class="container">

    <h2>⚠️ DATA ARMADA DELAY</h2>

    <!-- TOPBAR -->
    <div class="topbar">

        <a href="{{ url('/dashboard') }}" class="btn btn-blue">⬅ Dashboard</a>
        <a href="{{ url('/armada') }}" class="btn btn-blue">🚛 Sudah Dapat Armada</a>

        <form method="GET" action="{{ url('/armada-delay') }}" style="display:flex; gap:5px;">
            <select name="bulan">
                <option value="">Bulan</option>
                @for($i = 1; $i <= 12; $i++)
                    <option value="{{ $i }}" {{ $bulan == $i ? 'selected' : '' }}>
                        {{ date('F', mktime(0,0,0,$i,1)) }}
                    </option>
                @endfor
            </select>

            <select name="tahun">
                <option value="">Tahun</option>
                @for($y = 2023; $y <= date('Y'); $y++)
                    <option value="{{ $y }}" {{ $tahun == $y ? 'selected' : '' }}>
                        {{ $y }}
                    </option>
                @endfor
            </select>

            <button class="btn btn-warning">Filter</button>
        </form>

        <div class="total-box">
            ⚠️ Total Delay: {{ $logistik->count() }} Shipment
        </div>

    </div>

    <!-- TABLE -->
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>No Shipment</th>
                    <th>Planner</th>
                    <th>Area</th>
                    <th>Tujuan</th>
                    <th>Rencana Kirim</th>
                    <th>Status Armada</th>
                    <th>Mobil</th>
                    <th>Ekspedisi</th>
                    <th>Lama Pencarian</th>
                    <th>Tanggal Dapat Unit</th>
                    <th>Tanggal Tiba Gudang</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            @forelse($logistik as $i => $row)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $row->no_shipment ?? '-' }}</td>
                    <td>{{ $row->planner ?? '-' }}</td>
                    <td>{{ $row->area ?? '-' }}</td>
                    <td>{{ $row->tujuan ?? '-' }}</td>
                    <td>{{ $row->rencana_kirim ?? '-' }}</td>

                    <td>
                        <span class="badge-success">Sudah Dapat</span>
                    </td>

                    <td>{{ $row->mobil ?? '-' }}</td>
                    <td>{{ $row->ekpedisi ?? '-' }}</td>

                    <td>{{ $row->lama_waktu_pencarian ?? '-' }}</td>

                    <td>
                        {{ $row->tanggal_dpt_unit
                            ? date('d-m-Y H:i', strtotime($row->tanggal_dpt_unit))
                            : '-' }}
                    </td>
                    <td>
                        {{ $row->tanggal_tiba_gudang
                            ? date('d-m-Y H:i', strtotime($row->tanggal_tiba_gudang))
                            : '-' }}
                    </td>

                    <td>
                        <span class="badge-delay">⚠️ Delay Armada</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="13">Tidak ada data delay</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

</div>

</body>
</html>