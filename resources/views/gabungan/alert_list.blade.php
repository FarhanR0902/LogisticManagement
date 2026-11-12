@include('template.sidebar')
@php
    $d = fn($v) => $v ? date('d-m-Y', strtotime($v)) : '-';
    $labelPeriode = $periode === 'KOSONG' ? 'Tanpa periode' : ($periode ?: 'Semua periode');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Alert {{ $nama }}</title>
<style>
    body{background:#f2f4f9;font-family:'Segoe UI',sans-serif;color:#0f172a;margin:0}
    .container{margin-left:260px;padding:28px 32px}
    .box{background:#fff;border:1px solid #e6e9f0;border-radius:14px;padding:20px;overflow-x:auto}
    h2{margin:0 0 4px} .sub{color:#64748b;font-size:13.5px;margin-bottom:16px}
    .back{display:inline-block;margin-bottom:14px;color:#4f46e5;text-decoration:none;font-weight:600}
    table{width:100%;border-collapse:collapse;font-size:13px}
    th{background:#f1f3f7;color:#64748b;text-transform:uppercase;font-size:11.5px;padding:10px;text-align:left;white-space:nowrap}
    td{padding:9px 10px;border-bottom:1px solid #e6e9f0;white-space:nowrap}
    .pager{margin-top:14px}
</style>
</head>
<body>
<div class="container">
    <a class="back" href="{{ url()->previous() }}">← Kembali ke dashboard</a>

    <div class="box">
     <h2>{{ $tipe === 'planner' ? 'Planner' : 'PIC Monitoring' }}: {{ $nama }}</h2>
     <div class="sub">
    Periode shipment: <b>{{ $labelPeriode }}</b> ·
    @switch($tipe)
        @case('planner') Tanggal keluar gudang terakhir belum diinput @break
        @case('monitoring_belum') Belum input sama sekali (belum keluar gudang, tgl tiba kosong) @break
        @case('monitoring_sudah') Sudah keluar gudang, tgl tiba belum diinput @break
    @endswitch
    · Total: <b>{{ number_format($rows->total()) }}</b>
</div>

        <table>
            <thead>
            <tr>
                <th>No</th><th>Sumber</th><th>Periode</th><th>Tgl Shipment</th>
                <th>Planner</th><th>PIC Monitoring</th><th>No Shipment</th><th>Tujuan</th>
                <th>Area</th><th>Mobil</th><th>Rencana Kirim</th><th>Dapat Unit</th>
                <th>Keluar Gudang Terakhir</th><th>Tgl Tiba</th>
            </tr>
            </thead>
            <tbody>
            @forelse($rows as $i => $r)
                @php
                    $keluar = null;
                    foreach ($gudang as $s) {
                        $v = $r->{'tanggal_keluar_gudang' . $s} ?? null;
                        if (!empty($v) && ($keluar === null || strtotime($v) > $keluar)) $keluar = strtotime($v);
                    }
                @endphp
                <tr>
                    <td>{{ $rows->firstItem() + $i }}</td>
                    <td>{{ $r->sumber }}</td>
                    <td>{{ $r->periode_shipment ?: '-' }}</td>
                    <td>{{ $d($r->tanggal_shipment) }}</td>
                    <td>{{ $r->planner }}</td>
                    <td>{{ $r->pic_monitoring }}</td>
                    <td>{{ $r->no_shipment ?: '-' }}</td>
                    <td>{{ $r->tujuan }}</td>
                    <td>{{ $r->area }}</td>
                    <td>{{ $r->mobil }}</td>
                    <td>{{ $d($r->rencana_kirim) }}</td>
                    <td>{{ $d($r->tanggal_dpt_unit) }}</td>
                    <td>{{ $keluar ? date('d-m-Y', $keluar) : '-' }}</td>
                    <td>{{ $d($r->tanggal_tiba) }}</td>
                </tr>
            @empty
                <tr><td colspan="14" style="text-align:center;color:#94a3b8;padding:22px">Tidak ada data</td></tr>
            @endforelse
            </tbody>
        </table>

        <div class="pager">{{ $rows->links() }}</div>
    </div>
</div>
</body>
</html>