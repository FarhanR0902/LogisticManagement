@include('template.sidebar')
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilih Periode</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', system-ui, sans-serif; background:#f8fafc; }
        .container-fluid-custom { width: calc(100% - 260px); margin-left: 260px; padding: 30px; }
        .title { font-size:24px; font-weight:700; color:#1e293b; }
        .title::after { content:''; display:block; width:50px; height:4px; background:#0284c7; border-radius:2px; margin-top:5px; }
        .periode-card { background:#fff; border:none; border-radius:12px; padding:18px 20px; height:100%;
            box-shadow:0 4px 6px -1px rgba(0,0,0,.05); transition:all .15s ease; text-decoration:none; color:inherit; display:block; }
        .periode-card:hover { transform:translateY(-3px); box-shadow:0 10px 20px rgba(2,132,199,.15); }
        .periode-card h5 { font-weight:700; color:#0f172a; }
    </style>
</head>
<body>
<div class="container-fluid-custom">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="title">Pilih Periode Shipment</div>
        <input type="text" id="cariPeriode" class="form-control" style="max-width:260px" placeholder="Cari periode...">
    </div>

    <div class="row g-3" id="listPeriode">
        @forelse($periodes as $p)
        @php $label = $p->periode === '__KOSONG__' ? '(Periode belum diisi)' : $p->periode; @endphp
        <div class="col-md-3 periode-item" data-nama="{{ strtolower($label) }}">
            <a class="periode-card" href="{{ route('planner.data', ['periode' => $p->periode]) }}">
                <h5><i class="fa-solid fa-calendar-days text-primary me-2"></i>{{ $label }}</h5>
                <div class="text-muted small mb-2">
                    {{ $p->terakhir ? 'Shipment terakhir: ' . date('d/m/Y', strtotime($p->terakhir)) : '-' }}
                </div>
                <span class="badge bg-secondary">{{ $p->total }} shipment</span>
                @if($p->belum > 0)
                    <span class="badge bg-danger">{{ $p->belum }} belum selesai</span>
                @else
                    <span class="badge bg-success">Selesai semua</span>
                @endif
            </a>
        </div>
        @empty
        <div class="col-12 text-muted">Belum ada data periode.</div>
        @endforelse
    </div>
</div>

<script>
document.getElementById('cariPeriode').addEventListener('input', function () {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.periode-item').forEach(function (el) {
        el.style.display = el.dataset.nama.includes(q) ? '' : 'none';
    });
});
</script>
</body>
</html>