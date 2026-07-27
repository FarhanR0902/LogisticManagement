@include('template.sidebar')

<!DOCTYPE html>
<html>

<head>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>

<meta charset="utf-8">
<title>Bongkar Delay - Pasuruan</title>

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
    background:#007bff;
    color:#fff;
    text-decoration:none;
    border-radius:5px;
    font-size:13px;
    border:none;
    cursor:pointer;
}

.btn-success{
    background:#28a745;
}

input[type="date"],
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

</style>

</head>

<body>

<div class="container">

<h2>🚨 BONGKAR DELAY - PASURUAN</h2>

<div class="topbar">



</div>

<div class="table-container">

<table id="tableBongkarDelayPasuruan" class="display nowrap">

<thead>

<tr>

<th>No</th>
<th>No Shipment</th>
<th>Planner</th>
<th>Area</th>
<th>Tujuan</th>
<th>Ekspedisi</th>
<th>No Pol</th>
<th>Driver</th>
<th>Tanggal Tiba</th>
<th>Tanggal Bongkar</th>
<th>Overstay</th>
<th>SLA Bongkar</th>
<th>Reason Bongkar</th>
<th>Keterangan Bongkar</th>

</tr>

</thead>

<tbody>

@forelse($list as $row)

<tr>

<td></td>

<td>{{ $row->no_shipment_pasuruan }}</td>

<td>{{ $row->planner_pasuruan }}</td>

<td>{{ $row->area_pasuruan }}</td>

<td>{{ $row->tujuan_pasuruan }}</td>

<td>{{ $row->ekpedisi_pasuruan }}</td>

<td>{{ $row->no_pol_pasuruan }}</td>

<td>{{ $row->nama_driver_pasuruan }}</td>

<td>{{ $row->tanggal_tiba_pasuruan }}</td>

<td>{{ $row->tanggal_bongkar_pasuruan }}</td>

<td>{{ $row->overstay_days_pasuruan }}</td>

<td>

@php
$status = trim($row->sla_bongkar_pasuruan ?? '');
@endphp

<span class="status-delay">
    {{ $status !== '' ? $status : 'Delay' }}
</span>

</td>

<td>{{ $row->reason_waktu_bongkar_pasuruan }}</td>

<td>{{ $row->keterangan_waktu_bongkar_pasuruan }}</td>

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

var table=$("#tableBongkarDelayPasuruan").DataTable({

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

order:[[9,"desc"]],

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