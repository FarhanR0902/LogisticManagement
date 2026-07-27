{{-- resources/views/belum_armada.blade.php --}}

@include('template.sidebar')

<!DOCTYPE html>
<html lang="id">

<head>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Belum Dapat Armada</title>

    <style>

        body{
            font-family: Arial;
            background: #f5f5f5;
            margin: 0;
            padding: 0;
        }

        .container{
            margin-left: 250px;
            padding: 20px;
        }

        h2{
            margin-bottom: 15px;
        }

        .topbar{
            margin-bottom: 15px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        .btn{
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

        .btn:hover{
            opacity: 0.9;
        }

        .btn-success{
            background: #28a745;
        }

        .btn-warning{
            background: #ffc107;
            color: black;
        }

        .btn-blue{
            background: #007bff;
            color: white;
        }

        select,
        button{
            padding: 7px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }

        .table-container{
            overflow-x: auto;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        table{
            width: 100%;
            border-collapse: collapse;
            white-space: nowrap;
        }

        th,
        td{
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
            font-size: 13px;
        }

        th{
            background: #dc3545;
            color: white;
            position: sticky;
            top: 0;
        }

        tr:nth-child(even){
            background: #f2f2f2;
        }

        tr:hover{
            background: #ffeaea;
        }

        .badge-belum{
            background: #dc3545;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .badge-delay{
            background: #ffc107;
            color: black;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        @media(max-width:768px){

            .container{
                margin-left: 0;
                padding: 15px;
            }

        }

        table.dataTable thead th{
    background:#dc3545 !important;
    color:#fff !important;
}

.dataTables_wrapper{
    font-size:13px;
}

.dataTables_wrapper .dataTables_filter,
.dataTables_wrapper .dataTables_length{
    margin-bottom:10px;
}

.dataTables_wrapper .dataTables_filter input{
    padding:5px;
    width:220px;
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

    <!-- TOPBAR -->
    <div class="topbar">

        <!-- DASHBOARD -->
        <a href="{{ url('/dashboard') }}"
           class="btn btn-blue">
            ⬅ Dashboard
        </a>

        <!-- FILTER -->
        <form method="GET"
              action="{{ url('/belum-armada') }}"
              style="display:flex; gap:5px; flex-wrap:wrap;">

            <!-- BULAN -->
            <select name="bulan">

                <option value="">-- Bulan --</option>

                @for($i = 1; $i <= 12; $i++)

                    <option value="{{ $i }}"
                        {{ $bulan == $i ? 'selected' : '' }}>

                        {{ date('F', mktime(0,0,0,$i,1)) }}

                    </option>

                @endfor

            </select>

            <!-- TAHUN -->
            <select name="tahun">

                <option value="">-- Tahun --</option>

                @for($y = 2023; $y <= date('Y'); $y++)

                    <option value="{{ $y }}"
                        {{ $tahun == $y ? 'selected' : '' }}>

                        {{ $y }}

                    </option>

                @endfor

            </select>

            <button type="submit"
                    class="btn btn-warning">

                Filter

            </button>

        </form>

        <!-- EXPORT -->
        <a href="{{ url('/export') }}"
           class="btn btn-success">

            📥 Export CSV

        </a>

    </div>

    <!-- TABLE -->
    <div class="table-container">

      <table id="tableBelumArmada" class="display nowrap">

            <thead>

                <tr>

                    <th>No</th>
                    <th>No Shipment</th>
                    <th>Planner</th>
                    <th>Area</th>
                    <th>Tujuan</th>
                    <th>Rencana Kirim</th>
                    <th>Ketersediaan Unit</th>
                    <th>Mobil</th>
                    <th>Ekspedisi</th>
                    <th>Driver</th>
                    <th>Lama Pencarian</th>
                    <th>Status</th>

                </tr>

            </thead>

            <tbody>

            @forelse ($logistik as $index => $row)

                <tr>

                    <td></td>

                    <td>{{ $row->no_shipment ?? '-' }}</td>

                    <td>{{ $row->planner ?? '-' }}</td>

                    <td>{{ $row->area ?? '-' }}</td>

                    <td>{{ $row->tujuan ?? '-' }}</td>

                    <td>{{ $row->rencana_kirim ?? '-' }}</td>

                    <td>

                        <span class="badge-belum">

                            {{ $row->ketersediaan_unit ?? 'Belum Dapat' }}

                        </span>

                    </td>

                    <td>{{ $row->mobil ?: '-' }}</td>

                    <td>{{ $row->ekspedisi ?? '-' }}</td>

                    <td>{{ $row->nama_driver ?? '-' }}</td>

                    <td>

                        {{ $row->lama_pencarian ?? 0 }} Hari

                    </td>

                    <td>

                        @if(($row->lama_pencarian ?? 0) >= 2)

                            <span class="badge-delay">

                                Delay Cari Armada

                            </span>

                        @else

                            <span class="badge-belum">

                                Waiting Armada

                            </span>

                        @endif

                    </td>

                </tr>

            @empty

                <tr>

                    <td colspan="12">

                        Data tidak ditemukan

                    </td>

                </tr>

            @endforelse

            </tbody>

        </table>

    </div>

</div>
<script>
$(document).ready(function(){

    var table = $('#tableBelumArmada').DataTable({

        scrollX:true,

        pageLength:10,

        lengthMenu:[
            [10,25,50,100,-1],
            [10,25,50,100,"Semua"]
        ],

        columnDefs:[
            {
                targets:0,
                orderable:false,
                searchable:false
            }
        ],

        order:[[1,'asc']],

        language:{
            search:"Cari :",
            lengthMenu:"Tampilkan _MENU_ data",
            info:"Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
            infoEmpty:"Tidak ada data",
            zeroRecords:"Data tidak ditemukan",
            paginate:{
                previous:"<<",
                next:">>"
            }
        }

    });

    table.on('order.dt search.dt draw.dt',function(){

        let start = table.page.info().start;

        table.column(0,{
            search:'applied',
            order:'applied'
        }).nodes().each(function(cell,i){

            cell.innerHTML = start + i + 1;

        });

    }).draw();

});
</script>





</body>
</html>