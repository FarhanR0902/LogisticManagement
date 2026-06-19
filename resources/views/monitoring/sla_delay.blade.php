@include('template.sidebar')

<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Data Delay</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            margin: 0;
        }

        .container {
            width: calc(100% - 250px);
            margin-left: 250px;
            padding: 20px;
        }

        h2 {
            margin-bottom: 15px;
        }

        .topbar {
            margin-bottom: 15px;
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            padding: 8px 12px;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 13px;
            border: none;
            cursor: pointer;
        }

        .btn-blue {
            background: #007bff;
        }

        .btn-success {
            background: #28a745;
        }

        select {
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
            background: #dc3545;
            color: white;
        }

        .status-delay {
            color: #dc3545;
            font-weight: bold;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: bold;
            color: white;
        }

        .badge-red {
            background: #dc3545;
        }

        .badge-orange {
            background: #fd7e14;
        }

        .badge-gray {
            background: #6c757d;
        }

        .empty {
            text-align: center;
            padding: 20px;
        }
    </style>
</head>

<body>

<div class="container">

    <h2>🚨 DATA DELAY</h2>

    <div class="topbar">

        <a href="{{ url('/monitoring/dashboard') }}" class="btn-blue btn">
            ⬅ Dashboard
        </a>

        <a href="{{ url('/export-delay') }}" class="btn-success btn">
            📥 Export Delay
        </a>

        <!-- FILTER -->
        <form method="GET">

            <select name="bulan">
                <option value="">Semua Bulan</option>

                @for($m=1;$m<=12;$m++)
                    @php $bln = str_pad($m,2,'0',STR_PAD_LEFT); @endphp
                    <option value="{{ $bln }}"
                        {{ request('bulan') == $bln ? 'selected' : '' }}>
                        {{ $bln }}
                    </option>
                @endfor
            </select>

            <select name="tahun">
                <option value="">Semua Tahun</option>

                @for($y=date('Y');$y>=2020;$y--)
                    <option value="{{ $y }}"
                        {{ request('tahun') == $y ? 'selected' : '' }}>
                        {{ $y }}
                    </option>
                @endfor
            </select>

            <button type="submit" class="btn">
                🔍 Filter
            </button>

        </form>

    </div>

    <!-- TABLE -->
    <div class="table-container">

        <table>

            <thead>
                <tr>
                    <th>No</th>
                    <th>No Shipment</th>
                    <th>Tanggal Naik</th>
                    <th>Rencana Kirim</th>
                    <th>Lead Time</th>
                    <th>Tujuan</th>
                    <th>Area</th>
                    <th>Ekspedisi</th>
                    <th>Driver</th>
                    <th>No Polisi</th>
                    <th>Tanggal Tiba</th>
                    <th>SLA Tiba</th>
                    <th>Reason Tiba</th>
                </tr>
            </thead>

            <tbody>

                @forelse($logistik as $i => $row)

                    @php
                        $sla = strtolower($row->sla_tiba ?? '');
                    @endphp

                    <tr>

                        <td>{{ $i + 1 }}</td>
                        <td>{{ $row->no_shipment }}</td>
                        <td>{{ $row->tanggal_naik_logistik }}</td>
                        <td>{{ $row->rencana_kirim }}</td>
                        <td>{{ $row->transport_lead_time }}</td>
                        <td>{{ $row->tujuan }}</td>
                        <td>{{ $row->area }}</td>
                        <td>{{ $row->ekpedisi }}</td>
                        <td>{{ $row->nama_driver }}</td>
                        <td>{{ $row->no_pol }}</td>
                        <td>{{ $row->tanggal_tiba }}</td>

                        <!-- SLA DELAY -->
                        <td>
                            @if($sla == 'delay' || $sla == 'critical delay')
                                <span class="badge badge-red">
                                    {{ $row->sla_tiba }}
                                </span>

                            @elseif(str_contains($sla, 'h+'))
                                <span class="badge badge-orange">
                                    {{ $row->sla_tiba }}
                                </span>

                            @else
                                <span class="badge badge-gray">
                                    {{ $row->sla_tiba }}
                                </span>
                            @endif
                        </td>

                        <td>{{ $row->reason_tiba }}</td>

                    </tr>

                @empty

                    <tr>
                        <td colspan="13" class="empty">
                            Data Delay tidak ditemukan
                        </td>
                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</div>

</body>
</html>