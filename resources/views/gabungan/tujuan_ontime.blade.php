@include('template.sidebar')
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ request()->route('kpi') === 'tujuan_delay' ? 'Tiba Tujuan Delay' : 'Tiba Tujuan On Time' }} (Gabungan)</title>
<style>
body{font-family:Arial;background:#f4f6f9;margin:0}
.container{margin-left:260px;padding:20px}
.card{background:#fff;padding:20px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.1);margin-top:15px;overflow:auto}
table{width:100%;border-collapse:collapse;font-size:12px;white-space:nowrap;border:1px solid #ddd}
th{background:#28a745;color:#fff;padding:8px;text-align:center;position:sticky;top:0;border:1px solid #ddd}
td{padding:6px;border:1px solid #ddd;text-align:center}
.filter-box{background:#fff;padding:15px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.05)}
select,input[type=month],input[type=text]{padding:8px;margin-right:10px;border:1px solid #ccc;border-radius:5px}
.btn{padding:8px 14px;border-radius:6px;border:none;cursor:pointer;text-decoration:none;display:inline-block;font-size:13px}
.btn-green{background:#28a745;color:#fff}
.btn-blue{background:#007bff;color:#fff}
.tag{padding:3px 10px;border-radius:999px;color:#fff;font-weight:700;font-size:11px}
.tag-jkt{background:#2563eb}
.tag-psr{background:#ec4899}
.bg-green{background:#d4edda;color:#155724;padding:3px 8px;border-radius:5px;font-weight:bold}
.bg-red{background:#f8d7da;color:#721c24;padding:3px 8px;border-radius:5px;font-weight:bold}
.pager{margin-top:15px;display:flex;gap:14px;align-items:center;font-size:13px}
.pager a{padding:7px 14px;background:#007bff;color:#fff;border-radius:6px;text-decoration:none}
.pager span.off{padding:7px 14px;background:#cbd5e1;color:#64748b;border-radius:6px}
</style>
</head>
<body>
<div class="container">

@php
    $isDelay = request()->route('kpi') === 'tujuan_delay';

    $dt  = fn($v) => $v ? date('d-m-Y', strtotime($v)) : '-';

    // tanggal + jam (jam dari waktu_tiba; kalau kosong pakai jam bawaan tanggal)
    $dtj = function ($t, $w) {
        if (!$t) return '-';
        if ($w) return date('d-m-Y', strtotime($t)) . ' ' . substr($w, 0, 5);
        $has = date('H:i', strtotime($t)) !== '00:00';
        return date('d-m-Y', strtotime($t)) . ' ' . ($has ? date('H:i', strtotime($t)) : '--:--');
    };

    // timestamp tiba (sama dengan tibaDtSql() di controller)
    $ts = function ($t, $w) {
        if (!$t) return null;
        return $w
            ? strtotime(date('Y-m-d', strtotime($t)) . ' ' . substr($w, 0, 5))
            : strtotime($t);
    };

    // selisih tiba vs awal hari estimasi (aturan controller: < 24 jam = on time)
    $selisih = function ($tibaTs, $estimasi) use ($isDelay) {
        if (!$tibaTs || !$estimasi) return '-';
        $estTs = strtotime(date('Y-m-d', strtotime($estimasi)));
        $d = $tibaTs - $estTs;

        if ($d < 0) {
            $hari = intdiv(abs($d), 86400);
            return 'Lebih awal ' . ($hari > 0 ? $hari . ' hari' : 'kurang dari 1 hari');
        }
        if ($d < 86400) return 'Tepat di hari estimasi';

        $hari = intdiv($d, 86400);
        return 'Telat H+' . $hari;
    };

    $dashRoute = request()->routeIs('sales.*') ? 'sales.gabungan.dashboard' : 'gabungan.dashboard';
    $areaSel   = (array) request('area', []);
@endphp

<h2>🚚 TIBA TUJUAN {{ $isDelay ? 'DELAY' : 'ON TIME' }} (Jakarta + Pasuruan)</h2>

<div class="filter-box">
<form method="GET" action="{{ url()->current() }}">
    <select name="sumber">
        <option value="">Jakarta + Pasuruan</option>
        <option value="JAKARTA"  {{ request('sumber')=='JAKARTA'  ? 'selected' : '' }}>Jakarta</option>
        <option value="PASURUAN" {{ request('sumber')=='PASURUAN' ? 'selected' : '' }}>Pasuruan</option>
    </select>

    <input type="month" name="month" value="{{ request('month') }}">

    <select name="area[]">
        <option value="">Semua Area</option>
        @foreach($list_area as $a)
            <option value="{{ $a->area }}" {{ in_array($a->area, $areaSel) ? 'selected' : '' }}>{{ $a->area }}</option>
        @endforeach
    </select>

    <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari shipment / tujuan / ekspedisi...">

    @foreach(['tgl_basis','pulau','dist_channel','year','date','date_from','date_to'] as $k)
        @php $v = request($k); @endphp
        @if(is_array($v))
            @foreach(array_filter($v, 'strlen') as $item)
                <input type="hidden" name="{{ $k }}[]" value="{{ $item }}">
            @endforeach
        @elseif($v)
            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
        @endif
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
    <th>Estimasi Tiba</th>
    <th>Tanggal &amp; Jam Tiba</th>
    <th>Selisih</th>
    <th>SLA Tiba</th>
    <th>Reason Tiba</th>
</tr>
</thead>

<tbody>
@forelse($list as $r)
@php $tibaTs = $ts($r->tanggal_tiba, $r->waktu_tiba ?? null); @endphp
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
    <td>{{ $dt($r->tanggal_estimasi) }}</td>
    <td>{{ $dtj($r->tanggal_tiba, $r->waktu_tiba ?? null) }}</td>
    <td>{{ $selisih($tibaTs, $r->tanggal_estimasi) }}</td>
    <td><span class="{{ $isDelay ? 'bg-red' : 'bg-green' }}">{{ $isDelay ? 'Delay' : 'On Time' }}</span></td>
    <td style="text-align:left">{{ $r->reason_tiba ?: '-' }}</td>
</tr>
@empty
<tr><td colspan="14">Tidak ada data</td></tr>
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