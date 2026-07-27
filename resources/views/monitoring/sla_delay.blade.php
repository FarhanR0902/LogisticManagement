@include('template.sidebar')

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet"
href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <meta charset="utf-8">
    <title>Customer Delay</title>

    <style>
        .dataTables_wrapper{
    font-size:13px;
}

.dataTables_length,
.dataTables_filter,
.dataTables_info,
.dataTables_paginate{
    margin:10px 0;
}

.dataTables_filter{
    float:right;
}

.dataTables_length{
    float:left;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current{
    background:#dc3545 !important;
    color:#fff !important;
    border:1px solid #dc3545 !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button:hover{
    background:#bb2d3b !important;
    color:#fff !important;
}
        body{
            font-family:Arial,sans-serif;
            background:#f5f5f5;
            margin:0;
        }

        .container{
            width:calc(100% - 250px);
            margin-left:250px;
            padding:20px;
        }

        h2{
            margin-bottom:15px;
        }

        .topbar{
            margin-bottom:15px;
            display:flex;
            gap:10px;
            align-items:center;
            flex-wrap:wrap;
        }

        .btn{
            display:inline-block;
            padding:8px 12px;
            background:#dc3545;
            color:#fff;
            text-decoration:none;
            border-radius:5px;
            font-size:13px;
            border:none;
            cursor:pointer;
        }

        .btn-success{
            background:#198754;
        }

        select{
            padding:7px;
            border-radius:5px;
            border:1px solid #ccc;
        }

        .table-container{
            overflow-x:auto;
            background:#fff;
            border-radius:10px;
            box-shadow:0 2px 8px rgba(0,0,0,.1);
        }

        table{
            width:100%;
            border-collapse:collapse;
            white-space:nowrap;
        }

        th,td{
            border:1px solid #ddd;
            padding:8px;
            text-align:center;
            font-size:13px;
        }

        th{
            background:#dc3545;
            color:#fff;
        }

        .status-delay{
            color:#dc3545;
            font-weight:bold;
        }

        .empty{
            text-align:center;
            padding:20px;
        }

        .info-box{
            background:#fee2e2;
            padding:12px;
            border-radius:8px;
            margin-bottom:15px;
            font-size:13px;
            color:#991b1b;
        }
    </style>
</head>

<body>

<div class="container">

    <h2>📊 CUSTOMER DELAY</h2>

   
    <div class="table-container">

    <table id="tableCustomerDelay" class="display nowrap">

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

                <th>Tanggal Keluar Gudang KACS</th>
                <th>Tanggal Keluar Gudang SENTUL</th>
                <th>Tanggal Keluar Gudang CCIE</th>

                <th>Tanggal Estimasi</th>
                <th>Tanggal Tiba</th>

                <th>SLA Tiba</th>
                <th>Reason Tiba</th>

            </tr>

            </thead>

            <tbody>

            @forelse($logistik as $i=>$row)

                <tr>

                    <td>{{ $i+1 }}</td>

                    <td>{{ $row->no_shipment }}</td>

                    <td>{{ $row->tanggal_naik_logistik }}</td>

                    <td>{{ $row->rencana_kirim }}</td>

                    <td>{{ $row->transport_lead_time }}</td>

                    <td>{{ $row->tujuan }}</td>

                    <td>{{ $row->area }}</td>

                    <td>{{ $row->ekpedisi }}</td>

                    <td>{{ $row->tanggal_keluar_gudang }}</td>

                    <td>{{ $row->tanggal_keluar_gudang_2 }}</td>

                    <td>{{ $row->tanggal_keluar_gudang_3 }}</td>

                    <td>{{ $row->estimasi_tiba }}</td>

                    <td>{{ $row->tanggal_tiba }}</td>

                    <td>
                        <span class="status-delay">
                            {{ $row->sla_tiba }}
                        </span>
                    </td>

                    <td>{{ $row->reason_tiba }}</td>

                </tr>

            @empty

                <tr>

                    <td colspan="15" class="empty">
                        Data Delay tidak ditemukan
                    </td>

                </tr>

            @endforelse

            </tbody>

        </table>

    </div>

</div>
<script>
$(document).ready(function(){

    $.fn.dataTable.ext.type.search.html = function(data){
        return $('<div>').html(data).text();
    };

    var table = $('#tableCustomerDelay').DataTable({

      scrollX:false,
autoWidth:false,

        pageLength:10,

        lengthMenu:[
            [10,25,50,100,-1],
            [10,25,50,100,"Semua"]
        ],

        ordering:true,
        searching:true,
        paging:true,
        info:true,

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
                first:"Awal",
                last:"Akhir",
                previous:"<<",
                next:">>"
            }
        }

    });

    table.on('order.dt search.dt draw.dt', function(){

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