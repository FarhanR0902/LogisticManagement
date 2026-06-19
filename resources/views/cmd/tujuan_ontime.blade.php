@include('template.sidebar')

<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Data On Time Customer</title>

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
            padding: 8px 12px;
            border-radius: 5px;
            font-size: 13px;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: #007bff;
            color: #fff;
        }

        .btn-success {
            background: #28a745;
            color: #fff;
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
            background: #28a745;
            color: white;
        }

        .empty {
            text-align: center;
            padding: 20px;
        }

        .badge-green {
            color: green;
            font-weight: bold;
        }

        .badge-red {
            color: red;
            font-weight: bold;
        }
    </style>
</head>

<body>

    <div class="container">

        <h2>📊 DATA ON TIME CUSTOMER</h2>

        <div class="topbar">
            <a href="{{ url('/monitoring/dashboard') }}" class="btn btn-primary">
                ⬅ Dashboard
            </a>

            <a href="{{ url('/export') }}" class="btn btn-success">
                📥 Export
            </a>
        </div>

        <div class="table-container">

            <table>

                <thead>
                    <tr>
                        <th>No</th>
                        <th>No Shipment</th>
                        <th>Dist Channel</th>
                        <th>Tanggal Naik</th>
                        <th>Rencana Kirim</th>
                        <th>Lead Time</th>
                        <th>Tujuan</th>
                        <th>Area</th>
                        <th>Ekspedisi</th>
                        <th>Driver</th>
                        <th>No Polisi</th>
                        <th>Tanggal Keluar Gudang</th>
                        <th>Tgl Estimasi Tiba</th>
                        <th>Tanggal Tiba</th>
                        <th>Lama Perjalanan (Hari)</th>

                        <th>SLA Tiba</th>
                        <th>Reason Tiba</th>

                    </tr>
                </thead>

                <tbody>

@forelse($logistik as $i => $row)

                    @php
                    $keluar = $row->tanggal_keluar_gudang
                    ? strtotime($row->tanggal_keluar_gudang)
                    : strtotime($row->rencana_kirim);

                    $tiba = $row->tanggal_tiba
                    ? strtotime($row->tanggal_tiba)
                    : null;

                    $lead = (int) $row->transport_lead_time;

                    $estimasi = $keluar ? strtotime("+$lead days", $keluar) : null;

                    $lama_perjalanan = ($keluar && $tiba)
                    ? max(0, floor(($tiba - $keluar) / 86400))
                    : 0;

                    $estimasi_show = $estimasi ? date('Y-m-d', $estimasi) : '-';
                    @endphp

                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $row->no_shipment }}</td>
                        <td>{{ $row->dist_channel }}</td>
                        <td>{{ $row->tanggal_naik_logistik }}</td>
                        <td>{{ $row->rencana_kirim }}</td>
                        <td>{{ $row->transport_lead_time }}</td>
                        <td>{{ $row->tujuan }}</td>
                        <td>{{ $row->area }}</td>
                        <td>{{ $row->ekpedisi }}</td>
                        <td>{{ $row->nama_driver }}</td>
                        <td>{{ $row->no_pol }}</td>
                        <td>{{ $row->tanggal_keluar_gudang ?? '-' }}</td>
                        <td>{{ $estimasi_show }}</td>
                        <td>{{ $row->tanggal_tiba ?? '-' }}</td>

                        <td>{{ $lama_perjalanan }}</td>

                        <td>
@if(in_array(strtolower($row->sla_tiba), ['on time', 'h+0']))
                            <span class="badge-green">{{ $row->sla_tiba }}</span>
                            @else
                            <span class="badge-red">{{ $row->sla_tiba }}</span>
                            @endif
                        </td>
                       <td>{{ $row->reason_tiba }}</td>


                    </tr>

                    @empty

                    <tr>
                        <td colspan="15" class="empty">
                            Data tidak ditemukan
                        </td>
                    </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>

</body>

</html>