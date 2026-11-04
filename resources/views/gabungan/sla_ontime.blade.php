@include('template.sidebar')
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sudah Tiba Armada Di Gudang (Gabungan)</title>
<style>
body{font-family:Arial;background:#f4f6f9;margin:0}
.container{margin-left:260px;padding:20px}
.card{background:#fff;padding:20px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.1);margin-top:15px;overflow:auto}
table{width:100%;border-collapse:collapse;font-size:12px;white-space:nowrap;border:1px solid #ddd}
th{background:#28a745;color:#fff;padding:8px;text-align:center;position:sticky;top:0;border:1px solid #ddd}
td{padding:6px;border:1px solid #ddd;text-align:center}
.filter-box{background:#fff;padding:15px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.05)}
select,input[type=month]{padding:8px;margin-right:10px;border:1px solid #ccc;border-radius:5px}
.btn{padding:8px 14px;border-radius:6px;border:none;cursor:pointer;text-decoration:none;display:inline-block;font-size:13px}
.btn-green{background:#28a745;color:#fff}
.btn-blue{background:#007bff;color:#fff}
.tag{padding:3px 10px;border-radius:999px;color:#fff;font-weight:700;font-size:11px}
.tag-jkt{background:#2563eb}
.tag-psr{background:#ec4899}
.highlight-source{background:#fff3cd !important;border:2px solid #ffc107 !important}
.highlight-exit{background:#cfe2ff !important;border:2px solid #0d6efd !important}
</style>
</head>
<body>
<div class="container">

<h2>🚛 SUDAH TIBA ARMADA DI GUDANG (Jakarta + Pasuruan)</h2>

@php
    $sfx   = ['', '_2', '_3', '_4', '_5'];
    $namaJ = ['KACS', 'SENTUL', 'CCIE', 'P5', 'FIFATEX'];
    $namaP = ['PASURUAN', 'PUNTIR', '-', '-', '-'];
    $dt    = fn($v) => $v ? date('d-m-Y', strtotime($v)) : '';
    $dtm   = fn($v) => $v ? date('d-m-Y H:i', strtotime($v)) : '';
    $dashRoute = request()->routeIs('sales.*') ? 'sales.gabungan.dashboard' : 'gabungan.dashboard';
@endphp

<div class="filter-box">
<form method="GET" action="{{ url()->current() }}">
    <select name="sumber">
        <option value="">Jakarta + Pasuruan</option>
        <option value="JAKARTA"  {{ request('sumber')=='JAKARTA'  ? 'selected' : '' }}>Jakarta</option>
        <option value="PASURUAN" {{ request('sumber')=='PASURUAN' ? 'selected' : '' }}>Pasuruan</option>
    </select>

    <input type="month" name="month" value="{{ request('month') }}">

    <select name="area">
        <option value="">Semua Area</option>
        @foreach($list_area as $a)
            <option value="{{ $a->area }}" {{ request('area')==$a->area ? 'selected' : '' }}>{{ $a->area }}</option>
        @endforeach
    </select>

    @foreach(['tgl_basis','pulau','dist_channel','year','date','date_from','date_to'] as $k)
        @if(request($k))<input type="hidden" name="{{ $k }}" value="{{ request($k) }}">@endif
    @endforeach

    <button class="btn btn-green">Filter</button>
    <a href="{{ route($dashRoute, request()->query()) }}" class="btn btn-blue">Dashboard</a>
</form>
</div>

<div class="card">
<table>
<thead>
<tr>
    <th>No</th>
    <th>Sumber</th>
    <th>Tanggal Naik Logistik</th>
    <th>Rencana Kirim</th>
    <th>Lead Time</th>
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
    @foreach($namaJ as $i => $n)<th>Tiba Gudang {{ $i+1 }}<br><small>{{ $n }} / {{ $namaP[$i] }}</small></th>@endforeach
    @foreach($namaJ as $i => $n)<th>Keluar Gudang {{ $i+1 }}<br><small>{{ $n }} / {{ $namaP[$i] }}</small></th>@endforeach
    <th>Lama Digudang</th>
    <th>Status Gudang</th>
    <th>SLA Ketepatan Loading</th>
    <th>Lama Waktu Pencarian</th>
    <th>SLA Dapat Mobil</th>
</tr>
</thead>

<tbody>
@if(!empty($list) && count($list) > 0)
@php $markedShipment = []; @endphp
@foreach($list as $r)
@php
    $sf   = $r->gudang_keluar_terakhir ? $sfx[$r->gudang_keluar_terakhir - 1] : '';
    $nama = $r->sumber === 'JAKARTA' ? $namaJ : $namaP;
    $key  = $r->sumber . '|' . $r->no_shipment;
@endphp
<tr>
    <td>{{ $loop->iteration }}</td>
    <td><span class="tag {{ $r->sumber === 'JAKARTA' ? 'tag-jkt' : 'tag-psr' }}">{{ $r->sumber }}</span></td>
    <td>{{ $dt($r->tanggal_naik_logistik) }}</td>
    <td>{{ $dt($r->rencana_kirim) }}</td>
    <td>{{ $r->transport_lead_time }}</td>
    <td>{{ $r->planner }}</td>
    <td @if(!empty($r->gudang_sla) && !in_array($key, $markedShipment)) class="highlight-source" @php $markedShipment[] = $key; @endphp @endif>
        {{ $r->no_shipment }}
    </td>
    <td>{{ $r->tujuan }}</td>
    <td>{{ $r->area }}</td>
    <td>{{ $r->mobil }}</td>
    <td>{{ $r->kategori_ekspedisi }}</td>
    <td>{{ $r->ekpedisi }}</td>
    <td>{{ $r->status_pengiriman }}</td>
    <td>{{ $dt($r->tanggal_dpt_unit) }}</td>
    <td>{{ $dt($r->planning_loading) }}</td>

    @foreach($sfx as $idx => $s)
    <td class="{{ ($r->gudang_sla ?? null) == $idx + 1 ? 'highlight-source' : '' }}">
        {{ $dtm($r->{'tanggal_tiba_gudang' . $s} ?? null) }}
        @if(($r->gudang_sla ?? null) == $idx + 1)<br><small>Tiba Tercepat ({{ $nama[$idx] }})</small>@endif
    </td>
    @endforeach

    @foreach($sfx as $idx => $s)
    <td class="{{ ($r->gudang_keluar_terakhir ?? null) == $idx + 1 ? 'highlight-exit' : '' }}">
        {{ $dtm($r->{'tanggal_keluar_gudang' . $s} ?? null) }}
        @if(($r->gudang_keluar_terakhir ?? null) == $idx + 1)<br><small>Keluar Terakhir ({{ $nama[$idx] }})</small>@endif
    </td>
    @endforeach

    <td>{{ $r->{'lama_digudang' . $sf} ?? '' }}</td>
    <td>{{ $r->{'status_gudang' . $sf} ?? '' }}</td>
    <td>{{ $r->{'sla_loading' . $sf} ?? '' }}</td>
    <td>{{ $r->lama_waktu_pencarian ?? '' }}</td>
    <td>{{ $r->sla_dapat_mobil ?? '' }}</td>
</tr>
@endforeach
@else
<tr><td colspan="30">Tidak ada data</td></tr>
@endif
</tbody>
</table>
</div>

</div>
</body>
</html>