@include('template.sidebar')

<!DOCTYPE html>
<html lang="id">

<head>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <meta charset="UTF-8">
    <title>Sudah Dapat Armada</title>

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
        .btn-success{ background:#28a745; }
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
            background:#28a745;
            color:white;
            position:sticky;
            top:0;
        }

        .badge-success{
            background:#28a745;
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

        .highlight-source{
    background:#fff3cd !important;
    border:2px solid #ffc107 !important;
    
}
    </style>
</head>

<body>

@php
    $bulan = request('bulan');
    $tahun = request('tahun');
@endphp

<div class="container">

    <h2>🚛 DATA SUDAH DAPAT ARMADA</h2>

    <!-- FILTER -->
   

    <!-- TABLE -->
    <div class="table-container">

        <table id="tableArmada" class="display nowrap">
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

                    <th>Status</th>
                </tr>
            </thead>

<tbody>

@php
    $markedShipment = [];
@endphp

@forelse($logistik as $row)

<tr>

    <td></td>

    <td
        @if(
            !empty($row->no_shipment) &&
            !in_array($row->no_shipment,$markedShipment)
        )
            class="highlight-source"
            @php $markedShipment[] = $row->no_shipment; @endphp
        @endif
    >
        {{ $row->no_shipment ?? '-' }}
    </td>

    <td>{{ $row->planner ?? '-' }}</td>

    <td>{{ $row->area ?? '-' }}</td>

    <td>{{ $row->tujuan ?? '-' }}</td>

    <td>{{ $row->rencana_kirim ?? '-' }}</td>

    <td>
        <span class="badge-success">
            Sudah Dapat
        </span>
    </td>

    <td>{{ $row->mobil ?? '-' }}</td>

    <td>{{ $row->ekpedisi ?? '-' }}</td>

    <td>{{ $row->lama_waktu_pencarian ?? '-' }}</td>

    <td>
        {{ $row->tanggal_dpt_unit
            ? date('d-m-Y H:i',strtotime($row->tanggal_dpt_unit))
            : '-' }}
    </td>

    <td>
        @if(($row->lama_pencarian ?? 0) >= 2)
            <span class="badge-delay">
                Delay Armada
            </span>
        @else
            <span class="badge-success">
                Done
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
$(function(){

    var table = $('#tableArmada').DataTable({

       scrollX:false,
autoWidth:false,

        pageLength:10,

        lengthMenu:[
            [10,25,50,100,-1],
            [10,25,50,100,"Semua"]
        ],

        columnDefs:[
            {
                targets:0,
                searchable:false,
                orderable:false
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