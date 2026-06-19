{{-- resources/views/armada.blade.php --}}

@include('template.sidebar')

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Armada</title>

    <style>
        body {
            font-family: Arial;
            background: #f5f5f5;
            margin: 0;
            padding: 0;
        }

        .container {
            margin-left: 240px;
            padding: 20px;
        }

        h2 {
            margin-bottom: 15px;
        }

        .topbar {
            margin-bottom: 15px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        .btn {
            display: inline-block;
            padding: 8px 12px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 13px;
            border: none;
            cursor: pointer;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .btn-success {
            background: #28a745;
        }

        .btn-warning {
            background: #ffc107;
            color: black;
        }

        .btn-blue {
            background: #007bff;
            color: white;
        }

        select,
        button {
            padding: 7px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }

        .table-container {
            overflow-x: auto;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            white-space: nowrap;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
            font-size: 13px;
        }

        th {
            background: #17a2b8;
            color: white;
            position: sticky;
            top: 0;
        }

        tr:nth-child(even) {
            background: #f2f2f2;
        }

        tr:hover {
            background: #e9f5ff;
        }

        @media(max-width:768px) {
            .container {
                margin-left: 0;
            }
        }
    </style>
</head>

<body>

@php
    $bulan = request('bulan');
    $tahun = request('tahun');
@endphp

<div class="container">

    <h2>🚛 DATA ARMADA</h2>

    {{-- TOPBAR --}}
    <div class="topbar">

        {{-- DASHBOARD --}}
        <a href="{{ url('/dashboard') }}" class="btn btn-blue">
            ⬅ Dashboard
        </a>

        {{-- FILTER --}}
        <form method="GET" action="" style="display:flex; gap:5px;">

            {{-- BULAN --}}
            <select name="bulan">
                <option value="">-- Bulan --</option>

                @for ($i = 1; $i <= 12; $i++)

                    <option value="{{ $i }}"
                        {{ ($bulan == $i) ? 'selected' : '' }}>

                        {{ date('F', mktime(0, 0, 0, $i, 1)) }}

                    </option>

                @endfor
            </select>

            {{-- TAHUN --}}
            <select name="tahun">

                <option value="">-- Tahun --</option>

                @for ($y = 2023; $y <= date('Y'); $y++)

                    <option value="{{ $y }}"
                        {{ ($tahun == $y) ? 'selected' : '' }}>

                        {{ $y }}

                    </option>

                @endfor

            </select>

            <button type="submit" class="btn btn-warning">
                Filter
            </button>

        </form>

        {{-- EXPORT --}}
        <a href="{{ url('/export-armada?bulan=' . $bulan . '&tahun=' . $tahun) }}"
           class="btn btn-success">

            📥 Export CSV

        </a>

    </div>

    {{-- TABLE --}}
    <div class="table-container">

        <table>

            <thead>

                <tr>
                    <th>No</th>
                    <th>No Shipment</th>
                    <th>Tgl Naik Logistik</th>
                    <th>Ketersediaan Unit</th>
                    <th>Area</th>
                    <th>Tujuan</th>
                    <th>Lead Time</th>
                    <th>Rencana Kirim</th>
                    <th>Tanggal Dapat Unit</th>
                    <th>Tanggal Tiba Di Gudang</th>
                    <th>Lama Pencarian</th>
                </tr>

            </thead>

            <tbody>

            @forelse ($logistik ?? [] as $index => $row)

                <tr>

                    <td>{{ $index + 1 }}</td>

                    <td>{{ $row->no_shipment ?? '-' }}</td>

                    <td>{{ $row->tanggal_naik_logistik ?? '-' }}</td>

                    <td>{{ $row->ketersediaan_unit ?? '-' }}</td>

                    <td>{{ $row->area ?? '-' }}</td>

                    <td>{{ $row->tujuan ?? '-' }}</td>

                    <td>
                        {{ $row->transport_lead_time ?? '-' }} Hari
                    </td>

                    <td>{{ $row->rencana_kirim ?? '-' }}</td>

                    <td>{{ $row->tanggal_dpt_unit ?? '-' }}</td>

                    <td>{{ $row->tanggal_tiba_gudang ?? '-' }}</td>

                    <td>
                        {{ $row->lama_pencarian ?? '-' }} Hari
                    </td>

                </tr>

            @empty

                <tr>

                    <td colspan="11">
                        Data Armada tidak ditemukan
                    </td>

                </tr>

            @endforelse

            </tbody>

        </table>

    </div>

</div>

</body>
</html>