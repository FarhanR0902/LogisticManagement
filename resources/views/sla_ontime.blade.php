@include('template.sidebar')

<!DOCTYPE html>
<html lang="id">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>SLA Dapat Mobil - ONTIME</title>

<style>

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
}

th{
    background:#28a745;
    color:#fff;
    padding:8px;
    text-align:center;
    position:sticky;
    top:0;
}

td{
    padding:6px;
    border-bottom:1px solid #ddd;
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

</style>

</head>

<body>

<div class="container">

<h2>🚛 SLA DAPAT MOBIL - ONTIME</h2>

<!-- FILTER -->



<a href="{{ url('/dashboard') }}" class="btn btn-blue">Dashboard</a>

</form>

</div>

<!-- TABLE -->
<div class="card">

<table>

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
    <th>Ketersediaan Unit</th>
    <th>Mobil</th>
    <th>Perubahan Mobil</th>
    <th>Kategori Ekspedisi</th>
    <th>Ekspedisi</th>
    <th>Nama Driver</th>
    <th>No Pol</th>
    <th>Status</th>
    <th>Tanggal Dapat Unit</th>
    <th>Planning Loading</th>
    <th>Tanggal Tiba Di Gudang</th>
    <th>Tanggal Keluar Gudang</th>
    <th>Lama Digudang</th>
    <th>Status Gudang</th>
    <th>SLA Ketepatan Loading</th>
    <th>Keterangan</th>
    <th>Lama Waktu Pencarian</th>
    <th>SLA Dapat Mobil</th>
    <th>Status Akhir</th>
</tr>
</thead>

<tbody>

@if(!empty($list) && count($list)>0)

@foreach($list as $r)
<tr>
    <td>{{ $loop->iteration }}</td>
    <td>{{ $r->tanggal_naik_logistik }}</td>
    <td>{{ $r->rencana_kirim }}</td>
    <td>{{ $r->transport_lead_time }}</td>
    <td>{{ $r->planner }}</td>
    <td>{{ $r->no_shipment }}</td>
    <td>{{ $r->tujuan }}</td>
    <td>{{ $r->area }}</td>
    <td>{{ $r->ketersediaan_unit }}</td>
    <td>{{ $r->mobil }}</td>
    <td>{{ $r->perubahan_mobil }}</td>
    <td>{{ $r->kategori_ekspedisi }}</td>
    <td>{{ $r->ekspedisi }}</td>
    <td>{{ $r->nama_driver }}</td>
    <td>{{ $r->no_pol }}</td>
    <td>{{ $r->status_pengiriman }}</td>
    <td>{{ $r->tanggal_dpt_unit }}</td>
    <td>{{ $r->planning_loading }}</td>
    <td>{{ $r->tanggal_tiba_gudang }}</td>
    <td>{{ $r->tanggal_keluar_gudang }}</td>
    <td>{{ $r->lama_digudang }}</td>
    <td>{{ $r->status_gudang }}</td>
    <td>{{ $r->sla_loading }}</td>
    <td>{{ $r->keterangan }}</td>
    <td>{{ $r->lama_waktu_pencarian }}</td>
    <td>
        <span class="status">
            {{ $r->sla_dapat_mobil }}
        </span>
    </td>
    <td>{{ $r->status_akhir }}</td>
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

</body>
</html>