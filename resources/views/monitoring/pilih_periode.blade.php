@include('template.sidebar')

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PILIH PERIODE MONITORING</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f3f4f6; font-family:'Segoe UI'; margin:0; }
        .wrap { margin-left:250px; width:calc(100% - 250px); padding:30px; }
        .title { font-size:24px; font-weight:700; color:#111827; margin-bottom:6px; }
        .sub { color:#6b7280; font-size:14px; margin-bottom:20px; }
        .grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(240px,1fr)); gap:14px; }
        .pcard { display:block; background:#fff; border:2px solid #e5e7eb; border-radius:12px;
                 padding:16px; cursor:pointer; transition:.15s; position:relative; }
        .pcard:hover { border-color:#93c5fd; transform:translateY(-2px); }
        .pcard input { position:absolute; top:14px; right:14px; width:18px; height:18px; }
        .pcard:has(input:checked) { border-color:#2563eb; background:#eff6ff; }
        .pname { font-size:20px; font-weight:700; color:#111827; }
        .pmeta { font-size:13px; color:#6b7280; margin-top:6px; }
        .bdg { display:inline-block; padding:3px 9px; border-radius:20px; color:#fff; font-size:12px; font-weight:600; }
        .bdg.red { background:#ef4444; } .bdg.green { background:#22c55e; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="title">📅 Pilih Periode Shipment</div>
    <div class="sub">Pilih satu atau beberapa periode. Data di luar pilihan tidak dimuat supaya halaman ringan.</div>

    <form method="GET" action="{{ route('monitoring.datalogistik') }}">
        <div class="d-flex gap-3 mb-3" style="font-size:14px;">
            <a href="#" id="pAll">Pilih semua</a>
            <a href="#" id="pClear">Hapus</a>
        </div>

        <div class="grid">
            @foreach($periodes as $p)
            <label class="pcard">
                <input type="checkbox" name="periode[]" value="{{ $p->periode }}" class="pchk">
                <div class="pname">{{ $p->periode === '__kosong__' ? '(Tanpa periode)' : $p->periode }}</div>
                <div class="pmeta">
                    {{ number_format($p->total, 0, ',', '.') }} shipment
                    @if($p->terakhir) • terakhir {{ date('d-m-Y', strtotime($p->terakhir)) }} @endif
                </div>
                <div class="mt-2">
                    @if($p->belum > 0)
                        <span class="bdg red">{{ $p->belum }} belum bongkar</span>
                    @else
                        <span class="bdg green">Semua selesai</span>
                    @endif
                </div>
            </label>
            @endforeach
        </div>

        <button type="submit" class="btn btn-primary mt-4" id="pOk" disabled>Tampilkan</button>
    </form>
</div>

<script>
    const chks = document.querySelectorAll('.pchk');
    const ok = document.getElementById('pOk');
    const refresh = () => ok.disabled = ![...chks].some(c => c.checked);
    chks.forEach(c => c.addEventListener('change', refresh));
    document.getElementById('pAll').onclick  = e => { e.preventDefault(); chks.forEach(c => c.checked = true);  refresh(); };
    document.getElementById('pClear').onclick = e => { e.preventDefault(); chks.forEach(c => c.checked = false); refresh(); };
</script>
</body>
</html>