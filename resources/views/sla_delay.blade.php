@include('template.sidebar')

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>{{ $title }}</title>

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
    background:white;
    padding:20px;
    border-radius:10px;
    box-shadow:0 2px 10px rgba(0,0,0,0.1);
    margin-top:15px;
    overflow-x:auto;
}

table{
    width:100%;
    border-collapse:collapse;
    font-size:12px;
    white-space:nowrap;
}

th{
    background:#dc3545;
    color:white;
    padding:10px;
    text-align:center;
}

td{
    padding:10px;
    border-bottom:1px solid #ddd;
    text-align:center;
}

.badge-delay{
    background:#dc3545;
    color:white;
    padding:5px 10px;
    border-radius:5px;
    font-weight:bold;
}

/* BUTTON */
.btn{
    padding:8px 14px;
    border-radius:6px;
    text-decoration:none;
    display:inline-block;
    font-size:14px;
    margin-right:5px;
    border:none;
    cursor:pointer;
}

.btn-blue{ background:#007bff; color:#fff; }
.btn-green{ background:#28a745; color:#fff; }

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

h2{
    margin-bottom:15px;
}

/* RESPONSIVE */
@media(max-width:768px){
    .container{ margin-left:0; }
    table{ display:block; overflow-x:auto; }
}

</style>

</head>

<body>

<div class="container">

<h2>{{ $title }}</h2>

<!-- FILTER -->
<div class="filter-box">

<form method="GET" action="{{ url('/sla/delay') }}">

    <select name="bulan">
        <option value="">Bulan</option>
        @for($i=1; $i<=12; $i++)
            @php $val = str_pad($i,2,'0',STR_PAD_LEFT); @endphp
            <option value="{{ $val }}" {{ request('bulan')==$val?'selected':'' }}>
                {{ date('F', mktime(0,0,0,$i,1)) }}
            </option>
        @endfor
    </select>

    <select name="tahun">
        <option value="">Tahun</option>
        @for($y=2023; $y<=date('Y'); $y++)
            <option value="{{ $y }}" {{ request('tahun')==$y?'selected':'' }}>
                {{ $y }}
            </option>
        @endfor
    </select>

    <select name="area">
        <option value="">Area</option>
        @foreach($list_area as $a)
            <option value="{{ $a->area }}" {{ request('area')==$a->area?'selected':'' }}>
                {{ $a->area }}
            </option>
        @endforeach
    </select>

    <button type="submit" class="btn btn-green">Filter</button>

    <a href="{{ url('/dashboard') }}" class="btn btn-blue">⬅ Dashboard</a>

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
    <th>Nilai Muatan</th>
    <th>Biaya Kirim</th>
    <th>CR</th>
    <th>Kategori Ekspedisi</th>
    <th>Ekspedisi</th>
    <th>Nama Driver</th>
    <th>No Pol</th>
    <th>Status</th>
    <th>Tgl Dpt Unit</th>
    <th>Planning Loading</th>
    <th>Tgl Tiba Gudang</th>
    <th>Tgl Keluar Gudang</th>
    <th>Lama Digudang</th>
    <th>SLA Loading</th>
    <th>Keterangan</th>
    <th>Lama Pencarian</th>
    <th>SLA Mobil</th>
    <th>Status SLA</th>
</tr>

</thead>

<tbody>

@if(!empty($list) && count($list) > 0)

@foreach($list as $r)

<tr>
    <td>{{ $loop->iteration }}</td>

    <td>{{ $r->tanggal_naik_logistik ?? '-' }}</td>
    <td>{{ $r->rencana_kirim ?? '-' }}</td>
    <td>{{ $r->transport_lead_time ?? '-' }}</td>
    <td>{{ $r->planner ?? '-' }}</td>
    <td>{{ $r->no_shipment ?? '-' }}</td>
    <td>{{ $r->tujuan ?? '-' }}</td>
    <td>{{ $r->area ?? '-' }}</td>
    <td>{{ $r->ketersediaan_unit ?? '-' }}</td>
    <td>{{ $r->mobil ?? '-' }}</td>
    <td>{{ $r->perubahan_mobil ?? '-' }}</td>
    <td>{{ number_format($r->nilai_muatan ?? 0) }}</td>
    <td>{{ number_format($r->biaya_kirim ?? 0) }}</td>
    <td>{{ $r->cr ?? '-' }}</td>
    <td>{{ $r->kategori_ekspedisi ?? '-' }}</td>
    <td>{{ $r->ekspedisi ?? '-' }}</td>
    <td>{{ $r->nama_driver ?? '-' }}</td>
    <td>{{ $r->no_pol ?? '-' }}</td>
    <td>{{ $r->status_pengiriman ?? '-' }}</td>
    <td>{{ $r->tanggal_dpt_unit ?? '-' }}</td>
    <td>{{ $r->planning_loading ?? '-' }}</td>
    <td>{{ $r->tanggal_tiba_gudang ?? '-' }}</td>
    <td>{{ $r->tanggal_keluar_gudang ?? '-' }}</td>
    <td>{{ $r->lama_digudang ?? '-' }}</td>
    <td>{{ $r->sla_ketepatan_loading ?? '-' }}</td>
    <td>{{ $r->keterangan ?? '-' }}</td>
    <td>{{ $r->lama_waktu_pencarian ?? '-' }}</td>
    <td>{{ $r->sla_dapat_mobil ?? '-' }}</td>

    <td>
        <span class="badge-delay">DELAY</span>
    </td>
</tr>

@endforeach

@else

<tr>
    <td colspan="30">Tidak ada data</td>
</tr>

@endif

</tbody>

</table>

</div>

</div>

</body>
</html>