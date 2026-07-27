@include('template.sidebar')

<!DOCTYPE html>
<html lang="id">
<head>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Master Tujuan &amp; Area</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
*{ margin:0; padding:0; box-sizing:border-box; }

:root{
    --bg:#f2f4f9;
    --surface:#ffffff;
    --border:#e6e9f0;
    --text-primary:#0f172a;
    --text-secondary:#64748b;
    --text-muted:#94a3b8;
    --primary:#4f46e5;
    --primary-light:#eef2ff;
    --green:#16a34a;
    --green-light:#eefbf1;
    --red:#e11d48;
    --red-light:#fdeef2;
    --amber:#f59e0b;
    --amber-light:#fef7e9;
    --slate:#334155;
    --slate-light:#f1f3f7;
    --radius-lg:16px;
    --radius-md:12px;
    --radius-sm:8px;
    --shadow:0 1px 2px rgba(15,23,42,.04), 0 8px 20px rgba(15,23,42,.05);
}

body{ background:var(--bg); font-family:'Inter',sans-serif; color:var(--text-primary); -webkit-font-smoothing:antialiased; }
h1,h2,h3,h4{ font-family:'Plus Jakarta Sans',sans-serif; }

.container{ margin-left:250px; width:calc(100vw - 250px); padding:28px 32px 48px; }

.page-header{ margin-bottom:22px; }
.eyebrow{ font-size:12px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--primary); margin-bottom:6px; }
.page-header h1{ font-size:26px; font-weight:700; }
.page-header p{ margin-top:4px; font-size:13.5px; color:var(--text-secondary); }
.route-rule{ height:3px; width:64px; border-radius:2px; background:linear-gradient(90deg,var(--primary),#0d9488); margin-top:10px; }

.alert{ padding:12px 16px; border-radius:var(--radius-sm); font-size:13.5px; margin-bottom:18px; }
.alert-success{ background:var(--green-light); color:var(--green); border:1px solid var(--green); }
.alert-error{ background:var(--red-light); color:var(--red); border:1px solid var(--red); }
.alert-warning{ background:var(--amber-light); color:#b45309; border:1px solid var(--amber); }
.alert ul{ margin-top:8px; padding-left:18px; }

.grid-2{ display:grid; grid-template-columns:1.3fr 1fr; gap:16px; margin-bottom:22px; }
@media(max-width:1100px){ .grid-2{ grid-template-columns:1fr; } .container{ margin-left:0; width:100%; } }

.box{ background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-lg); padding:20px 22px; box-shadow:var(--shadow); }
.box h3{ font-size:15px; font-weight:600; margin-bottom:14px; }

.form-row{ display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; }
.field{ display:flex; flex-direction:column; gap:6px; flex:1; min-width:160px; }
.field label{ font-size:11.5px; font-weight:600; color:var(--text-secondary); text-transform:uppercase; letter-spacing:.04em; }
.field input, .field select{
    height:38px; padding:0 12px; border:1px solid var(--border); border-radius:var(--radius-sm);
    outline:none; font-family:'Inter',sans-serif; font-size:13.5px; background:#fbfcfe;
}
.field input:focus, .field select:focus{ border-color:var(--primary); background:#fff; }

.btn{
    height:38px; padding:0 16px; border-radius:var(--radius-sm); border:none; font-size:13px; font-weight:600;
    cursor:pointer; display:inline-flex; align-items:center; justify-content:center; text-decoration:none;
    transition:filter .15s;
}
.btn-primary{ background:var(--primary); color:#fff; }
.btn-primary:hover{ filter:brightness(1.08); }
.btn-ghost{ background:var(--slate-light); color:var(--slate); }
.btn-danger{ background:var(--red-light); color:var(--red); }
.btn-sm{ height:30px; padding:0 10px; font-size:12px; }

.table-scroll{ overflow-x:auto; margin-top:18px; }
table{ width:100%; border-collapse:collapse; font-size:13.5px; }
thead th{
    background:var(--slate-light); color:var(--text-secondary); padding:10px 12px; font-size:11.5px;
    font-weight:700; text-transform:uppercase; letter-spacing:.03em; text-align:left; white-space:nowrap;
}
tbody td{ padding:10px 12px; border-bottom:1px solid var(--border); }
tbody tr:hover{ background:#fafbff; }
.badge{ display:inline-block; padding:3px 10px; border-radius:999px; font-size:12px; font-weight:600; }
.badge-green{ background:var(--green-light); color:var(--green); }
.badge-red{ background:var(--red-light); color:var(--red); }
.actions{ display:flex; gap:6px; }
.pagination{ margin-top:16px; }
.help-text{ font-size:12px; color:var(--text-muted); margin-top:8px; }
.edit-row td{ background:#fbfcff; }
</style>
</head>
<body>

<div class="container">

    <div class="page-header">
        <div class="eyebrow">Master Data &middot; Logistik Pasuruan</div>
        <h1>Master Tujuan &amp; Area</h1>
        <p>Kelola pemetaan customer/tujuan pengiriman ke area besar. Tujuan baru di sini otomatis kepakai di summary area, summary tujuan, dan peta sebaran dashboard.</p>
        <div class="route-rule"></div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    @if(session('conflicts') && count(session('conflicts')) > 0)
        <div class="alert alert-warning">
            Ditemukan konflik area saat import (tujuan sudah ada dengan area berbeda, baris CSV dilewati):
            <ul>
                @foreach(session('conflicts') as $c)
                    <li>{{ $c }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid-2">

        <!-- FORM TAMBAH TUJUAN BARU -->
        <div class="box">
            <h3>➕ Tambah tujuan baru</h3>
            <form method="POST" action="{{ route('tujuan-filter.store') }}">
                @csrf
                <div class="form-row">
                    <div class="field">
                        <label>Nama tujuan / customer</label>
                        <input type="text" name="tujuan" placeholder="Cth: PT. CONTOH JAYA - KOTA" required value="{{ old('tujuan') }}">
                    </div>
                    <div class="field">
                        <label>Area</label>
                        <input type="text" name="area" list="area-list" placeholder="Cth: JAWA_BARAT" required value="{{ old('area') }}">
                        <datalist id="area-list">
                            @foreach($list_area as $a)
                                <option value="{{ $a }}">
                            @endforeach
                        </datalist>
                    </div>
                    <div class="field">
                        <label>Dist. channel (opsional)</label>
                        <input type="text" name="dist_channel" value="{{ old('dist_channel') }}">
                    </div>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
                @error('tujuan') <p class="help-text" style="color:var(--red)">{{ $message }}</p> @enderror
                @error('area') <p class="help-text" style="color:var(--red)">{{ $message }}</p> @enderror
            </form>
            <p class="help-text">
                Area akan otomatis dinormalisasi jadi UPPERCASE_DENGAN_UNDERSCORE, sama seperti format yang dipakai di peta dashboard.
                Kalau nama area baru sama sekali (belum pernah ada), jangan lupa tambahkan juga koordinatnya di
                <code>koordinatAreaPasuruan</code> / tabel <code>master_areas</code>.
            </p>
        </div>

        <!-- IMPORT CSV -->
        <div class="box">
            <h3>📥 Import dari CSV</h3>
            <form method="POST" action="{{ route('tujuan-filter.import') }}" enctype="multipart/form-data">
                @csrf
                <div class="form-row">
                    <div class="field" style="flex:2;">
                        <label>File CSV (kolom: tujuan, area)</label>
                        <input type="file" name="file" accept=".csv,.txt" required>
                    </div>
                    <button type="submit" class="btn btn-ghost">Import</button>
                </div>
            </form>
            <p class="help-text">
                Tujuan yang sudah ada di database TIDAK akan ditimpa (biar data yang sudah dikurasi manual aman).
                Kalau ada tujuan yang areanya beda antara CSV dan database, baris itu di-skip dan dilaporkan sebagai konflik di atas.
            </p>
        </div>

    </div>

    <!-- SEARCH & FILTER -->
    <div class="box">
        <h3>🔍 Cari &amp; kelola tujuan</h3>
        <form method="GET" action="{{ route('tujuan-filter.index') }}" class="form-row">
            <div class="field">
                <label>Cari nama tujuan</label>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Ketik nama customer...">
            </div>
            <div class="field">
                <label>Filter area</label>
                <select name="area">
                    <option value="">Semua area</option>
                    @foreach($list_area as $a)
                        <option value="{{ $a }}" {{ request('area') == $a ? 'selected' : '' }}>{{ $a }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Cari</button>
            <a href="{{ route('tujuan-filter.index') }}" class="btn btn-ghost">Reset</a>
        </form>

        <div class="table-scroll">
        <table>
            <thead>
            <tr>
                <th>Tujuan</th>
                <th>Area</th>
                <th>Dist. Channel</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
            </thead>
            <tbody>
            @forelse($data as $item)
            <tr>
                <td>
                    <input type="text" name="tujuan" form="edit-form-{{ $item->id }}" value="{{ $item->tujuan }}" style="width:100%; border:1px solid transparent; background:transparent; font:inherit;" onfocus="this.style.border='1px solid var(--border)'; this.style.background='#fff';">
                </td>
                <td>
                    <input type="text" name="area" form="edit-form-{{ $item->id }}" value="{{ $item->area }}" list="area-list" style="width:160px; border:1px solid transparent; background:transparent; font:inherit;" onfocus="this.style.border='1px solid var(--border)'; this.style.background='#fff';">
                </td>
                <td>
                    <input type="text" name="dist_channel" form="edit-form-{{ $item->id }}" value="{{ $item->dist_channel }}" style="width:120px; border:1px solid transparent; background:transparent; font:inherit;" onfocus="this.style.border='1px solid var(--border)'; this.style.background='#fff';">
                </td>
                <td>
                    <label style="display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                        <input type="checkbox" name="is_active" form="edit-form-{{ $item->id }}" value="1" {{ $item->is_active ? 'checked' : '' }}>
                        <span class="badge {{ $item->is_active ? 'badge-green' : 'badge-red' }}">
                            {{ $item->is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </label>
                </td>
                <td class="actions">
                    <!-- Form edit: input-inputnya ada di kolom-kolom sebelah kiri, terhubung lewat atribut form="" -->
                    <form id="edit-form-{{ $item->id }}" method="POST" action="{{ route('tujuan-filter.update', $item) }}">
                        @csrf
                        @method('PUT')
                    </form>
                    <button type="submit" form="edit-form-{{ $item->id }}" class="btn btn-primary btn-sm">Simpan</button>

                    <form id="delete-form-{{ $item->id }}" method="POST" action="{{ route('tujuan-filter.destroy', $item) }}" onsubmit="return confirm('Hapus tujuan &quot;{{ $item->tujuan }}&quot;?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="5" style="text-align:center; color:var(--text-muted); padding:22px 0;">Belum ada data tujuan.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>

        <div class="pagination">
            {{ $data->links() }}
        </div>
    </div>

</div>

</body>
</html>
