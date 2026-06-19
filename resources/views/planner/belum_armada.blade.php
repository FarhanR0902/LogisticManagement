@include('template.sidebar')

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Belum Dapat Armada</title>

    <style>
        body{
            font-family: Arial;
            background: #f5f5f5;
            margin: 0;
        }

        .container{
            margin-left: 250px;
            padding: 20px;
        }

        h2{
            margin-bottom: 15px;
        }

        .topbar{
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 15px;
            align-items: center;
        }

        .btn{
            padding: 8px 12px;
            border-radius: 6px;
            text-decoration: none;
            color: white;
            font-size: 13px;
            border: none;
        }

        .btn-blue{ background:#007bff; }
        .btn-success{ background:#ff0000; }
        .btn-warning{ background:#ffc107; color:black; }

        select{
            padding: 7px;
            border-radius: 5px;
        }

        .table-container{
            background:white;
            border-radius:10px;
            overflow:auto;
        }

        table{
            width:100%;
            border-collapse: collapse;
            white-space: nowrap;
        }

        th, td{
            padding:8px;
            border:1px solid #ddd;
            font-size:13px;
            text-align:center;
        }

        th{
            background:#ff0000;
            color:white;
            position:sticky;
            top:0;
        }

        .badge-success{
            background:#ff0000;
            color:white;
            padding:5px 10px;
            border-radius:20px;
        }

        .badge-delay{
            background:#ffc107;
            color:black;
            padding:5px 10px;
            border-radius:20px;
        }
    </style>
</head>

<body>

@php
    $bulan = request('bulan');
    $tahun = request('tahun');
@endphp

<div class="container">

    <h2>🚛 DATA BELUM DAPAT ARMADA</h2>

    <!-- FILTER -->
    <div class="topbar">

        <a href="{{ url('/dashboard') }}" class="btn btn-blue">
            ⬅ Dashboard
        </a>

        <form method="GET" action="{{ url('/armada') }}" style="display:flex; gap:5px;">

            <select name="bulan">
                <option value="">Bulan</option>
                @for($i=1;$i<=12;$i++)
                    <option value="{{ $i }}" {{ $bulan==$i?'selected':'' }}>
                        {{ date('F', mktime(0,0,0,$i,1)) }}
                    </option>
                @endfor
            </select>

            <select name="tahun">
                <option value="">Tahun</option>
                @for($y=2023;$y<=date('Y');$y++)
                    <option value="{{ $y }}" {{ $tahun==$y?'selected':'' }}>
                        {{ $y }}
                    </option>
                @endfor
            </select>

            <button class="btn btn-warning">Filter</button>

        </form>

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
                    <th>tanggal Dapat Unit</th>
                    <th>Tanggal Tiba Gudang</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>

            @forelse($logistik as $i => $row)

                <tr>
                    <td>{{ $i+1 }}</td>
                    <td>{{ $row->no_shipment ?? '-' }}</td>
                    <td>{{ $row->planner ?? '-' }}</td>
                    <td>{{ $row->area ?? '-' }}</td>
                    <td>{{ $row->tujuan ?? '-' }}</td>
                    <td>{{ $row->rencana_kirim ?? '-' }}</td>

               <td>
    <span class="badge-success">Belum Dapat</span>
</td>

                    <td>{{ $row->mobil ?? '-' }}</td>
                    <td>{{ $row->ekpedisi ?? '-' }}</td>

                   {{-- Lama Pencarian --}}
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
                        @if(($row->lama_pencarian ?? 0) >= 2)
                            <span class="badge-delay">Delay Armada</span>
                        @else
                            <span class="badge-success">On Track</span>
                        @endif
                    </td>

                </tr>

            @empty

                <tr>
                    <td colspan="12">Data tidak ditemukan</td>
                </tr>

            @endforelse

            </tbody>

        </table>

    </div>

</div>

</body>
</html>