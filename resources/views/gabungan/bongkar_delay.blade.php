@include('template.sidebar')
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bongkar Delay (Gabungan)</title>
<style>
body{font-family:Arial;background:#f4f6f9;margin:0}
.container{margin-left:260px;padding:20px}
.card{background:#fff;padding:20px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.1);margin-top:15px;overflow:auto}
table{width:100%;border-collapse:collapse;font-size:12px;white-space:nowrap;border:1px solid #ddd}
th{background:#dc3545;color:#fff;padding:8px;text-align:center;position:sticky;top:0;border:1px solid #ddd}
td{padding:6px;border:1px solid #ddd;text-align:center}
.filter-box{background:#fff;padding:15px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.05)}
select,input[type=month],input[type=text]{padding:8px;margin-right:10px;border:1px solid #ccc;border-radius:5px}
.btn{padding:8px 14px;border-radius:6px;border:none;cursor:pointer;text-decoration:none;display:inline-block;font-size:13px}
.btn-green{background:#28a745;color:#fff}
.btn-blue{background:#007bff;color:#fff}
.tag{padding:3px 10px;border-radius:999px;color:#fff;font-weight:700;font-size:11px}
.tag-jkt{background:#2563eb}
.tag-psr{background:#ec4899}
.bg-red{background:#f8d7da;color:#721c24;padding:3px 8px;border-radius:5px;font-weight:bold}
.durasi{background:#fee2e2;color:#991b1b;padding:3px 8px;border-radius:5px;font-weight:bold}
.pager{margin-top:15px;display:flex;gap:14px;align-items:center;font-size:13px}
.pager a{padding:7px 14px;background:#007bff;color:#fff;border-radius:6px;text-decoration:none}
.pager span.off{padding:7px 14px;background:#cbd5e1;color:#64748b;border-radius:6px}
</style>
</head>
<body>
<div class="container">

<h2>⏳ BONGKAR DELAY (Jakarta + Pasuruan)</h2>

@php
    $dt = fn($v) => $v ? date('d-m-Y', strtotime($v)) : '-';
    $dashRoute = request()->routeIs('sales.*') ? 'sales.gabungan.dashboard' : 'gabungan.dashboard';

    // gabung tanggal + jam. Kalau kolom jam kosong (mis. Pasuruan),
    // pakai jam bawaan dari tanggalnya (datetime).
    $gabungDt = function ($tgl, $jam) {
        if (!$tgl) return null;
        $j  = trim((string) $jam);
        $ts = $j !== ''
            ? strtotime(date('Y-m-d', strtotime($tgl)) . ' ' . $j)
            : strtotime($tgl);
        return $ts ?: null;
    };
    $fmtDt = fn($ts) => $ts ? date('d-m-Y H:i', $ts) : '-';
    $durasi = function ($a, $b) {
        if (!$a || !$b) return '-';
        $s = max(0, $b - $a);
        return intdiv($s, 3600) . ' jam ' . intdiv($s % 3600, 60) . ' mnt';
    };
    // kelebihan di atas batas 24 jam
    $lebih = function ($a, $b) {
        if (!$a || !$b) return '-';
        $s = max(0, ($b - $a) - 86400);
        return intdiv($s, 3600) . ' jam ' . intdiv($s % 3600, 60) . ' mnt';
    };
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

    <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari shipment / tujuan / ekspedisi...">

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
    <th>Tiba → Bongkar</th>
    <th>Lebih dari 24 Jam</th>
    <th>Overstay</th>
    <th>SLA Bongkar</th>
    <th>Reason Bongkar</th>
</tr>
</thead>

<tbody>
@forelse($list as $r)
@php
    $tibaTs    = $gabungDt($r->tanggal_tiba,    $r->waktu_tiba ?? null);
    $bongkarTs = $gabungDt($r->tanggal_bongkar, $r->waktu_bongkar ?? null);
@endphp
<tr>
    <td>{{ $list->firstItem() + $loop->index }}</td>
    <td><span class="tag {{ $r->sumber === 'JAKARTA' ? 'tag-jkt' : 'tag-psr' }}">{{ $r->sumber }}</span></td>
    <td>{{ $r->no_shipment }}</td>
    <td>{{ $dt($r->tanggal_naik_logistik) }}</td>
    <td>{{ $dt($r->rencana_kirim) }}</td>
    <td>{{ $r->transport_lead_time ?? '-' }}</td>
    <td style="text-align:left">{{ $r->tujuan }}</td>
    <td>{{ $r->area ?: '-' }}</td>
    <td>{{ $r->ekpedisi ?: '-' }}</td>
    <td>{{ $r->act_urutan_bongkar ?: '-' }}</td>
    <td>{{ $fmtDt($tibaTs) }}</td>
    <td>{{ $fmtDt($bongkarTs) }}</td>
    <td><span class="durasi">{{ $durasi($tibaTs, $bongkarTs) }}</span></td>
    <td><b style="color:#b91c1c">{{ $lebih($tibaTs, $bongkarTs) }}</b></td>
    <td><b style="color:#b91c1c">{{ (float) $r->overstay_days }} hari</b></td>
    <td><span class="bg-red">Delay</span></td>
    <td style="text-align:left">{{ $r->reason_bongkar ?: '-' }}</td>
</tr>
@empty
<tr><td colspan="17">Tidak ada data</td></tr>
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