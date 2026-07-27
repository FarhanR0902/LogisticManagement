@include('template.sidebar')

<!DOCTYPE html>
<html lang="id">

<head>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>SLA Tiba Armada Di Gudang</title>

<style>
.highlight-exit{
    background:#cfe2ff !important;
    border:2px solid #0d6efd !important;
}
body{
    font-family:Arial;
    background:#f4f6f9;
    margin:0;
}

.container{
    margin-left:260px;
    padding:20px;
}

.card{
    background:#fff;
    padding:20px;
    border-radius:10px;
    box-shadow:0 2px 10px rgba(0,0,0,0.1);
    margin-top:15px;
    overflow:auto;
}

table{
    width:100%;
    border-collapse:collapse;
    font-size:12px;
    white-space:nowrap;
    border:1px solid #ddd;
}

th{
    background:#28a745;
    color:#fff;
    padding:8px;
    text-align:center;
    position:sticky;
    top:0;
    border:1px solid #ddd;
}

td{
    padding:6px;
    border:1px solid #ddd;
    text-align:center;
}

.status{
    background:#d4edda;
    color:#155724;
    padding:4px 8px;
    border-radius:5px;
    font-weight:bold;
}

/* FILTER */
.filter-box{
    background:#fff;
    padding:15px;
    border-radius:10px;
    box-shadow:0 2px 10px rgba(0,0,0,0.05);
}

select{
    padding:8px;
    margin-right:10px;
    border:1px solid #ccc;
    border-radius:5px;
}

.btn{
    padding:8px 14px;
    border-radius:6px;
    border:none;
    cursor:pointer;
}

.btn-green{background:#28a745;color:#fff}
.btn-blue{background:#007bff;color:#fff}

.bg-red{
    background:#f8d7da;
    color:#721c24;
}
.bg-green{
    background:#d4edda;
    color:#155724;
}
    .highlight-source{
    background:#fff3cd !important;
    border:2px solid #ffc107 !important;
  
}

.status{
    padding:4px 8px;
    border-radius:5px;
    font-weight:bold;
    display:inline-block;
}

.bg-green{
    background:#d4edda;
    color:#155724;
}

.bg-red{
    background:#f8d7da;
    color:#721c24;
}
.highlight-source{
    background:#fff3cd !important;
    border:2px solid #ffc107 !important;
}

.highlight-exit{
    background:#cfe2ff !important;
    border:2px solid #0d6efd !important;
   
}

</style>

</head>

<body>

<div class="container">

<h2>🚛 SUDAH TIBA ARMADA DI GUDANG</h2>


<!-- TABLE -->
<div class="card">

<table id="tableOntime" class="display nowrap">

<thead>
<tr>
    <th>No</th>
    <th>Tanggal Naik Logistik</th>
    <th>Rencana Kirim</th>
    <th>Transport Lead Time</th>
    <th>Planner</th>
    <th>No Shipment</th>
    <th>Tujuan</th>
    <th>Area</th>
   
    <th>Mobil</th>
    <!-- <th>Perubahan Mobil</th> -->
    <th>Kategori Ekspedisi</th>
    <th>Ekspedisi</th>
    <!-- <th>Nama Driver</th>
    <th>No Pol</th> -->
    <th>Status</th>
    <th>Tanggal Dapat Unit</th>
    <th>Planning Loading</th>
    <th>Tanggal Tiba Di Gudang 1</th>
    <th>Tanggal Tiba Di Gudang 2</th>
    <th>Tanggal Tiba Di Gudang 3</th>
    <th>Tanggal Keluar Gudang 1</th>
    <th>Tanggal Keluar Gudang 2</th>
    <th>Tanggal Keluar Gudang</th>
    <th>Lama Digudang</th>
    <!-- <th>Status Gudang</th> -->
    <th>SLA Ketepatan Loading</th>
    <!-- <th>Keterangan</th> -->
    <th>Lama Waktu Pencarian</th>
    <th>SLA Dapat Mobil</th>

</tr>
</thead>

<tbody>

@if(!empty($list) && count($list)>0)
@php
$markedShipment = [];
@endphp
@foreach($list as $r)
<tr>
    <td>{{ $loop->iteration }}</td>
    <td>{{ $r->tanggal_naik_logistik }}</td>
    <td>{{ $r->rencana_kirim }}</td>
    <td>{{ $r->transport_lead_time }}</td>
    <td>{{ $r->planner }}</td>
<td
    @if(
        (
            !empty($r->tanggal_tiba_gudang) ||
            !empty($r->tanggal_tiba_gudang_2) ||
            !empty($r->tanggal_tiba_gudang_3)
        )
        &&
        !in_array($r->no_shipment, $markedShipment)
    )
        class="highlight-source"
        @php $markedShipment[] = $r->no_shipment; @endphp
    @endif
>
    {{ $r->no_shipment }}
</td>
    <td>{{ $r->tujuan }}</td>
    <td>{{ $r->area }}</td>
    <!-- <td>{{ $r->ketersediaan_unit }}</td> -->
    <td>{{ $r->mobil }}</td>
    <!-- <td>{{ $r->perubahan_mobil }}</td> -->
    <td>{{ $r->kategori_ekspedisi }}</td>
    <td>{{ $r->ekpedisi }}</td>
    <!-- <td>{{ $r->nama_driver }}</td>
    <td>{{ $r->no_pol }}</td> -->
<td>{{ $r->status_pengiriman }}</td>

<td>
    {{ $r->tanggal_dpt_unit }}
</td>
    <td>{{ $r->planning_loading }}</td>
<td class="{{ ($r->gudang_sla ?? null) == 1 ? 'highlight-source' : '' }}">
    {{ $r->tanggal_tiba_gudang }}

    @if(($r->gudang_sla ?? null) == 1)
        <br><small>Tiba Tercepat</small>
    @endif
</td>

<td class="{{ ($r->gudang_sla ?? null) == 2 ? 'highlight-source' : '' }}">
    {{ $r->tanggal_tiba_gudang_2 }}

    @if(($r->gudang_sla ?? null) == 2)
        <br><small>Tiba Tercepat</small>
    @endif
</td>

<td class="{{ ($r->gudang_sla ?? null) == 3 ? 'highlight-source' : '' }}">
    {{ $r->tanggal_tiba_gudang_3 }}

    @if(($r->gudang_sla ?? null) == 3)
        <br><small>Tiba Tercepat</small>
    @endif
</td>
   <td class="{{ ($r->gudang_keluar_terakhir ?? null) == 1 ? 'highlight-exit' : '' }}">
    {{ $r->tanggal_keluar_gudang }}

    @if(($r->gudang_keluar_terakhir ?? null) == 1)
        <br><small>Keluar Terakhir</small>
    @endif
</td>

<td class="{{ ($r->gudang_keluar_terakhir ?? null) == 2 ? 'highlight-exit' : '' }}">
    {{ $r->tanggal_keluar_gudang_2 }}

    @if(($r->gudang_keluar_terakhir ?? null) == 2)
        <br><small>Keluar Terakhir</small>
    @endif
</td>

<td class="{{ ($r->gudang_keluar_terakhir ?? null) == 3 ? 'highlight-exit' : '' }}">
    {{ $r->tanggal_keluar_gudang_3 }}

    @if(($r->gudang_keluar_terakhir ?? null) == 3)
        <br><small>Keluar Terakhir</small>
    @endif
</td>
    
    <td>{{ $r->lama_digudang }}</td>
    <td>{{ $r->status_gudang }}</td>
    <td>{{ $r->sla_loading }}</td>
    <!-- <td>{{ $r->keterangan }}</td> -->
    <td>{{ $r->lama_waktu_pencarian }}</td>
    

   
</tr>
@endforeach

@else

<tr>
    <td colspan="28">Tidak ada data</td>
</tr>

@endif

</tbody>

</table>

</div>

</div>
<script>
$(document).ready(function () {

    $.fn.dataTable.ext.type.search.html = function (data) {
        return $('<div>').html(data).text();
    };

    $('#tableOntime').DataTable({
        scrollX: true,
        pageLength: 10,
        lengthMenu: [
            [10, 25, 50, 100, -1],
            [10, 25, 50, 100, "Semua"]
        ],
        ordering: true,
        searching: true,
        paging: true,
        info: true,
        language: {
            search: "Cari :",
            lengthMenu: "Tampilkan _MENU_ data",
            info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
            infoEmpty: "Tidak ada data",
            zeroRecords: "Data tidak ditemukan",
            paginate: {
                first: "Awal",
                last: "Akhir",
                next: ">>",
                previous: "<<"
            }
        }
    });

});
</script>
</body>
</html>