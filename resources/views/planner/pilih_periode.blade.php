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
        .container-fluid-custom { width: calc(100% - 260px); margin-left: 260px; padding: 30px; padding-bottom: 100px; }
        .title { font-size:24px; font-weight:700; color:#1e293b; }
        .title::after { content:''; display:block; width:50px; height:4px; background:#0284c7; border-radius:2px; margin-top:5px; }
        .periode-card { background:#fff; border:2px solid transparent; border-radius:12px; padding:18px 20px; height:100%;
            box-shadow:0 4px 6px -1px rgba(0,0,0,.05); transition:all .15s ease; color:inherit; display:block;
            cursor:pointer; position:relative; user-select:none; }
        .periode-card:hover { transform:translateY(-3px); box-shadow:0 10px 20px rgba(2,132,199,.15); }
        .periode-card h5 { font-weight:700; color:#0f172a; padding-right:28px; }
        .periode-card .cek { position:absolute; top:16px; right:16px; width:20px; height:20px; cursor:pointer; }
        .periode-card.dipilih { border-color:#0284c7; background:#f0f9ff; }

        .action-bar { position:fixed; left:260px; right:0; bottom:0; background:#fff; padding:14px 30px;
            border-top:1px solid #e2e8f0; box-shadow:0 -4px 12px rgba(0,0,0,.06);
            display:flex; align-items:center; gap:12px; z-index:50; }
    </style>
</head>
<body>
<div class="container-fluid-custom">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div class="title">Pilih Periode Shipment</div>
        <div class="d-flex align-items-center gap-2">
            <input type="text" id="cariPeriode" class="form-control" style="max-width:260px" placeholder="Cari periode...">
            <a href="{{ route('planner.data', ['periode' => '__SEMUA__']) }}" class="btn btn-outline-primary text-nowrap">
                <i class="fa-solid fa-layer-group me-1"></i> Semua Periode
            </a>
        </div>
    </div>

    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" id="cekSemua">
        <label class="form-check-label fw-semibold" for="cekSemua">Pilih semua yang tampil</label>
    </div>

    <div class="row g-3" id="listPeriode">
        @forelse($periodes as $p)
        @php $label = $p->periode === '__KOSONG__' ? '(Periode belum diisi)' : $p->periode; @endphp
        <div class="col-md-3 periode-item" data-nama="{{ strtolower($label) }}">
            <div class="periode-card" data-periode="{{ $p->periode }}">
                <input type="checkbox" class="cek form-check-input" value="{{ $p->periode }}">
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
            </div>
        </div>
        @empty
        <div class="col-12 text-muted">Belum ada data periode.</div>
        @endforelse
    </div>
</div>

<div class="action-bar">
    <span class="text-muted"><b id="jumlahDipilih">0</b> periode dipilih</span>
    <button type="button" id="btnBuka" class="btn btn-primary ms-auto" disabled>
        <i class="fa-solid fa-arrow-right me-1"></i> Buka Data Planner
    </button>
</div>

<script>
    const BASE_URL = @json(route('planner.data'));
    const cekSemua = document.getElementById('cekSemua');
    const btnBuka  = document.getElementById('btnBuka');
    const jumlahEl = document.getElementById('jumlahDipilih');

    const semuaCek   = () => [...document.querySelectorAll('.periode-card .cek')];
    const tampilCek  = () => semuaCek().filter(c => c.closest('.periode-item').style.display !== 'none');
    const dipilihCek = () => semuaCek().filter(c => c.checked);

    function refresh() {
        semuaCek().forEach(c => c.closest('.periode-card').classList.toggle('dipilih', c.checked));
        const n = dipilihCek().length;
        jumlahEl.textContent = n;
        btnBuka.disabled = n === 0;

        const tampil = tampilCek();
        cekSemua.checked = tampil.length > 0 && tampil.every(c => c.checked);
    }

    // klik di mana saja pada kartu = toggle
    document.querySelectorAll('.periode-card').forEach(function (card) {
        card.addEventListener('click', function (e) {
            const cek = card.querySelector('.cek');
            if (e.target !== cek) cek.checked = !cek.checked;
            refresh();
        });
    });

    cekSemua.addEventListener('change', function () {
        tampilCek().forEach(c => { c.checked = cekSemua.checked; });
        refresh();
    });

    btnBuka.addEventListener('click', function () {
        const dipilih = dipilihCek().map(c => c.value);
        if (!dipilih.length) return;
        window.location.href = BASE_URL + '?periode=' + encodeURIComponent(dipilih.join(','));
    });

    document.getElementById('cariPeriode').addEventListener('input', function () {
        const q = this.value.toLowerCase();
        document.querySelectorAll('.periode-item').forEach(function (el) {
            el.style.display = el.dataset.nama.includes(q) ? '' : 'none';
        });
        refresh();
    });

    refresh();
</script>
</body>
</html>