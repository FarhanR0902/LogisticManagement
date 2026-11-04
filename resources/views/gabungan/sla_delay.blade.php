@include('template.sidebar')
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Belum Tiba Armada Di Gudang (Gabungan)</title>
<style>
body{font-family:Arial;background:#f4f6f9;margin:0}
.container{margin-left:260px;padding:20px}
.card{background:#fff;padding:20px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.1);margin-top:15px;overflow:auto}
table{width:100%;border-collapse:collapse;font-size:12px;white-space:nowrap;border:1px solid #ddd}
th{background:#dc3545;color:#fff;padding:8px;text-align:center;position:sticky;top:0;border:1px solid #ddd}
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
.bg-red{background:#f8d7da;color:#721c24;padding:3px 8px;border-radius:5px;font-weight:bold}
.bg-green{background:#d4edda;color:#155724;padding:3px 8px;border-radius:5px;font-weight:bold}
.pager{margin-top:15px;display:flex;gap:14px;align-items:center;font-size:13px}
.pager a{padding:7px 14px;background:#007bff;color:#fff;border-radius:6px;text-decoration:none}
.pager span.off{padding:7px 14px;background:#cbd5e1;color:#64748b;border-radius:6px}
</style>
</head>
<body>
<div class="container">

<h2>⏳ BELUM TIBA ARMADA DI GUDANG (Jakarta + Pasuruan)</h2>

@php
    $sfx   = ['', '_2', '_3', '_4', '_5'];
    $namaJ = ['KACS', 'SENTUL', 'CCIE', 'P5', 'FIFATEX'];
    $namaP = ['PASURUAN', 'PUNTIR', '-', '-', '-'];
    $dt    = fn($v) => $v ? date('d-m-Y', strtotime($v)) : '';
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
    <a href="{{ route($dashRoute, request()->except('page')) }}" class="btn btn-blue">Dashboard</a>
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
    @foreach($namaJ as $i => $n)<th>Planning Loading {{ $i+1 }}<br><small>{{ $n }} / {{ $namaP[$i] }}</small></th>@endforeach
    <th>Lama Waktu Pencarian</th>
    <th>SLA Dapat Mobil</th>
</tr>
</thead>

<tbody>
@forelse($list as $r)
<tr>
    <td>{{ $list->firstItem() + $loop->index }}</td>
    <td><span class="tag {{ $r->sumber === 'JAKARTA' ? 'tag-jkt' : 'tag-psr' }}">{{ $r->sumber }}</span></td>
    <td>{{ $dt($r->tanggal_naik_logistik) }}</td>
    <td>{{ $dt($r->rencana_kirim) }}</td>
    <td>{{ $r->transport_lead_time }}</td>
    <td>{{ $r->planner }}</td>
    <td>{{ $r->no_shipment }}</td>
    <td>{{ $r->tujuan }}</td>
    <td>{{ $r->area }}</td>
    <td>{{ $r->mobil }}</td>
    <td>{{ $r->kategori_ekspedisi }}</td>
    <td>{{ $r->ekpedisi }}</td>
    <td>{{ $r->status_pengiriman ?? '' }}</td>
    <td class="highlight-source">{{ $dt($r->tanggal_dpt_unit) }}</td>
    @foreach($sfx as $s)
    <td>{{ $dt($r->{'planning_loading' . $s} ?? null) }}</td>
    @endforeach
    <td>{{ $r->lama_waktu_pencarian ?? '' }}</td>
    <td>
        @if(($r->sla_dapat_mobil ?? '') === 'Delay')<span class="bg-red">Delay</span>
        @elseif(($r->sla_dapat_mobil ?? '') === 'On Time')<span class="bg-green">On Time</span>
        @endif
    </td>
</tr>
@empty
<tr><td colspan="21">Tidak ada data</td></tr>
@endforelse
</tbody>
</table>

<div class="pager">
    @if($list->onFirstPage())<span class="off">« Sebelumnya</span>
    @else<a href="{{ $list->previousPageUrl() }}">« Sebelumnya</a>@endif

    <span>Halaman {{ $list->currentPage() }} / {{ $list->lastPage() }} (total {{ $list->total() }} data)</span>

    @if($list->hasMorePages())<a href="{{ $list->nextPageUrl() }}">Selanjutnya »</a>
    @else<span class="off">Selanjutnya »</span>@endif
</div>
</div>

</div>
</body>
</html>