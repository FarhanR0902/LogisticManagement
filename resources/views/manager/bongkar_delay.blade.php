@include('template.sidebar')

<!DOCTYPE html>
<html>

<head>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>

<meta charset="utf-8">
<title>Bongkar Delay</title>

<style>

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

</style>

</head>

<body>

<div class="container">

<h2>🚚 BONGKAR DELAY</h2>

<div class="table-container">

<table id="tableBongkarDelay" class="display nowrap">

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
<th>Urutan Bongkar</th>
<th>Tanggal Tiba</th>
<th>Tanggal Bongkar</th>
<th>Overstay</th>
<th>SLA Bongkar</th>
<th>Reason Bongkar</th>

</tr>

</thead>

<tbody>

@forelse($list as $row)

<tr>

<td></td>

<td>{{ $row->no_shipment }}</td>

<td>{{ $row->tanggal_naik_logistik }}</td>

<td>{{ $row->rencana_kirim }}</td>

<td>{{ $row->transport_lead_time }}</td>

<td>{{ $row->tujuan }}</td>

<td>{{ $row->area }}</td>

<td>{{ $row->ekpedisi }}</td>

<td>{{ $row->act_urutan_bongkar }}</td>

<td>{{ $row->tanggal_tiba }}</td>

<td>{{ $row->tanggal_bongkar }}</td>

<td>{{ $row->overstay_days }}</td>

<td>

@php
$status = trim($row->sla_bongkar ?? '');
@endphp

<span class="status-delay">
    {{ $status }}
</span>

</td>

<td>{{ $row->reason_bongkar }}</td>

</tr>

@empty

<tr>

<td colspan="14" class="empty">
Tidak ada data Bongkar Delay
</td>

</tr>

@endforelse

</tbody>

</table>

</div>

</div>

<script>

$(function(){

var table=$("#tableBongkarDelay").DataTable({

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
orderable:false,
searchable:false
}
],

order:[[1,"asc"]],

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

let start=table.page.info().start;

table.column(0,{
search:'applied',
order:'applied'
}).nodes().each(function(cell,i){

cell.innerHTML=start+i+1;

});

}).draw();

});

</script>

</body>
</html>