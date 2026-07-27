@include('template.sidebar')

<!DOCTYPE html>
<html lang="id">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>SLA Tiba Armada Di Gudang - Pasuruan</title>

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
    display:inline-block;
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

</style>

</head>

<body>

<div class="container">

<h2>🚛 SUDAH TIBA ARMADA DI GUDANG - PASURUAN</h2>

<!-- FILTER -->
<div class="filter-box">

<form method="GET" action="{{ route('pasuruan.gudang.ontime') }}">



<!-- TABLE -->
<div class="card">

<table>

<thead>
<tr>
    <th>No</th>
    <th>Tanggal Terima PO</th>
    <th>Rencana Kirim</th>
    <th>Transport Lead Time</th>
    <th>Planner</th>
    <th>No Shipment</th>
    <th>Tujuan</th>
    <th>Area</th>
    <th>Mobil</th>
    <th>Kategori Ekspedisi</th>
    <th>Ekspedisi</th>
    <th>Status</th>
    <th>Tanggal Dapat Unit</th>
    <th>Planning Loading</th>
    <th>Tanggal Tiba Di Gudang</th>
    <th>Tanggal Keluar Gudang</th>
    <th>Lama Digudang</th>
    <th>SLA Ketepatan Loading</th>
    <th>Lama Waktu Pencarian</th>
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
    <td>{{ $r->tanggal_terima_po_pasuruan }}</td>
    <td>{{ $r->rencana_kirim_pasuruan }}</td>
    <td>{{ $r->transport_lead_time_pasuruan }}</td>
    <td>{{ $r->planner_pasuruan }}</td>
    <td
        @if(
            !empty($r->tanggal_tiba_gudang_pasuruan) &&
            !in_array($r->no_shipment_pasuruan, $markedShipment)
        )
            class="highlight-source"
            @php $markedShipment[] = $r->no_shipment_pasuruan; @endphp
        @endif
    >
        {{ $r->no_shipment_pasuruan }}
    </td>
    <td>{{ $r->tujuan_pasuruan }}</td>
    <td>{{ $r->area_pasuruan }}</td>
    <td>{{ $r->mobil_pasuruan }}</td>
    <td>{{ $r->kategori_ekspedisi_pasuruan }}</td>
    <td>{{ $r->ekspedisi_pasuruan }}</td>
    <td>
        @if($r->sla_dapat_mobil_pasuruan)
            <span class="status {{ strtolower($r->sla_dapat_mobil_pasuruan) == 'on time' ? 'bg-green' : 'bg-red' }}">
                {{ $r->sla_dapat_mobil_pasuruan }}
            </span>
        @endif
    </td>
    <td>{{ $r->tanggal_dpt_unit_pasuruan }}</td>
    <td>{{ $r->planning_loading_pasuruan }}</td>
    <td class="highlight-source">
        {{ $r->tanggal_tiba_gudang_pasuruan }}
    </td>
    <td class="highlight-exit">
        {{ $r->tanggal_keluar_gudang_pasuruan }}
    </td>
    <td>{{ $r->lama_digudang_pasuruan }}</td>
    <td>{{ $r->sla_ketepatan_loading_pasuruan }}</td>
    <td>{{ $r->lama_waktu_pencarian_pasuruan }}</td>
</tr>
@endforeach

@else

<tr>
    <td colspan="18">Tidak ada data</td>
</tr>

@endif

</tbody>

</table>

</div>

</div>

</body>
</html>