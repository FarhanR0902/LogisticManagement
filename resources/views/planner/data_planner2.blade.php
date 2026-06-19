@include('template.sidebar')

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>DATA PLANNER</title>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<style>

body{
    background:#f3f4f6;
    font-family:Segoe UI;
}

.container{
    width:calc(100% - 250px);
    margin-left:250px;
    padding:20px;
}

.title{
    font-size:26px;
    font-weight:bold;
    margin-bottom:15px;
    color:#22c55e
}

.card{
    background:#fff;
    padding:15px;
    border-radius:12px;
    box-shadow:0 5px 20px rgba(0,0,0,0.08);
    overflow:auto;
}

/* TABLE */
table{
    width:100%;
    border-collapse:collapse;
    font-size:12px;
}

th{
    background:#111827;
    color:#fff;
    padding:10px;
    white-space:nowrap;
}

td{
    padding:6px;
    border:1px solid #e5e7eb;
    white-space:nowrap;
    text-align:center;
}

input, select{
    width:100%;
    padding:4px;
    font-size:12px;
    border:1px solid #ccc;
    border-radius:4px;
}

/* BUTTON */
.save-btn{
    background:#22c55e;
    color:#fff;
    border:none;
    padding:6px 10px;
    border-radius:6px;
    cursor:pointer;
    font-weight:bold;
}

.save-btn:hover{
    background:#16a34a;
}

/* ALERT */
.alert{
    background:#dcfce7;
    padding:10px;
    margin-bottom:10px;
    border-radius:6px;
    color:#166534;
}

</style>

</head>

<body>

<div class="container">

<div class="title">📦 DATA PLANNER</div>

{{-- SUCCESS MESSAGE --}}
@if(session('success'))
<div class="alert">
    {{ session('success') }}
</div>
@endif

<div class="card">

<table id="plannerTable" class="display nowrap">

<thead>
<tr>
    <th>No</th>
    <th>Tgl Naik</th>
    <th>Rencana Kirim</th>
    <th>Lead Time</th>
    <th>Planner</th>
    <th>No Shipment</th>
    <th>Tujuan</th>
    <th>Area</th>
    <th>Unit</th>
    <th>Mobil</th>
    <th>Perubahan Mobil</th>
    <th>Nilai Muatan</th>
    <th>Biaya Kirim</th>
    <th>Ekspedisi</th>
    <th>Driver</th>
    <th>No Pol</th>
    <th>Status</th>
    <th>Action</th>
</tr>
</thead>

<tbody>

@foreach($logistik as $r)

<tr>

<form method="POST" action="{{ route('planner.update', $r->id) }}">
@csrf
@method('PUT')

<td>{{ $r->no }}</td>

{{-- TANGGAL --}}
<td>
<input type="date" name="tanggal_naik_logistik" value="{{ $r->tanggal_naik_logistik }}">
</td>

<td>
<input type="date" name="rencana_kirim" value="{{ $r->rencana_kirim }}">
</td>

<td>
<input type="number" name="transport_lead_time" value="{{ $r->transport_lead_time }}">
</td>

<td>
<input type="text" name="planner" value="{{ $r->planner }}">
</td>

<td>
<input type="text" name="no_shipment" value="{{ $r->no_shipment }}">
</td>

<td>
<input type="text" name="tujuan" value="{{ $r->tujuan }}">
</td>

<td>
<input type="text" name="area" value="{{ $r->area }}">
</td>

<td>
<input type="text" name="ketersediaan_unit" value="{{ $r->ketersediaan_unit }}">
</td>

<td>
<input type="text" name="mobil" value="{{ $r->mobil }}">
</td>

<td>
<input type="text" name="perubahan_mobil" value="{{ $r->perubahan_mobil }}">
</td>

<td>
<input type="number" name="nilai_muatan" value="{{ $r->nilai_muatan }}">
</td>

<td>
<input type="number" name="biaya_kirim" value="{{ $r->biaya_kirim }}">
</td>

<td>
<input type="text" name="ekpedisi" value="{{ $r->ekpedisi }}">
</td>

<td>
<input type="text" name="nama_driver" value="{{ $r->nama_driver }}">
</td>

<td>
<input type="text" name="no_pol" value="{{ $r->no_pol }}">
</td>

<td>
<select name="status_pengiriman">
    <option value="Pending" {{ $r->status_pengiriman=='Pending'?'selected':'' }}>Pending</option>
    <option value="On Process" {{ $r->status_pengiriman=='On Process'?'selected':'' }}>On Process</option>
    <option value="Delivered" {{ $r->status_pengiriman=='Delivered'?'selected':'' }}>Delivered</option>
    <option value="Delay" {{ $r->status_pengiriman=='Delay'?'selected':'' }}>Delay</option>
</select>
</td>

<td>
<button type="submit" class="save-btn">SAVE</button>
</td>

</form>

</tr>

@endforeach

</tbody>

</table>

</div>

</div>

<script>
$(document).ready(function(){
    $('#plannerTable').DataTable({
        scrollX:true,
        pageLength:10
    });
});
</script>

</body>
</html>