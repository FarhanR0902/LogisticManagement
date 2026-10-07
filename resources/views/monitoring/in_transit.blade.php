<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IN TRANSIT</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f1f5f9; margin: 0; color: #1e293b; }
        .container { margin-left: 260px; padding: 30px; }
        h2 { font-size: 30px; font-weight: 700; margin: 0 0 4px; color: #0f172a; }
        .subtitle { color: #64748b; margin: 0 0 22px; font-size: 14px; }

        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 16px; margin-bottom: 20px; }
        .stat { background: #fff; border-radius: 14px; padding: 18px 20px; box-shadow: 0 4px 16px rgba(0,0,0,.07); border-left: 6px solid #64748b; }
        .stat .label { font-size: 13px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: .4px; }
        .stat .value { font-size: 32px; font-weight: 800; margin-top: 4px; }
        .stat.total   { border-color: #2563eb; }
        .stat.overdue { border-color: #ef4444; } .stat.overdue .value { color: #ef4444; }
        .stat.soon    { border-color: #f97316; } .stat.soon .value    { color: #f97316; }
        .stat.ontrack { border-color: #22c55e; } .stat.ontrack .value { color: #16a34a; }

        .filter-box { background: #fff; padding: 16px 20px; border-radius: 14px; box-shadow: 0 4px 16px rgba(0,0,0,.07); margin-bottom: 20px; }
        .filter-box form { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .filter-box input[type=text], .filter-box select { padding: 11px 14px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 15px; min-width: 190px; outline: none; }
        .filter-box input[type=text]:focus, .filter-box select:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.15); }
        .btn { padding: 11px 20px; border: none; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; color: #fff; text-decoration: none; display: inline-block; }
        .btn-primary { background: #2563eb; } .btn-reset { background: #ef4444; }

        /* ===== MULTI SELECT DROPDOWN (checklist + search) ===== */
        .ms-dd { position: relative; width: 220px; flex: 0 0 220px; }
        .ms-dd-btn {
            width: 100%; height: 44px; display: flex; justify-content: space-between; align-items: center; gap: 8px;
            background: #fff; border: 1px solid #cbd5e1; border-radius: 10px; padding: 0 14px;
            font-size: 15px; cursor: pointer; color: #1e293b;
        }
        .ms-dd-btn:hover { border-color: #3b82f6; }
        .ms-dd-btn .ms-label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .ms-dd.has-value .ms-dd-btn { border-color: #3b82f6; background: #eff6ff; }
        .ms-dd.has-value .ms-label { font-weight: 600; color: #1e40af; }
        .ms-dd-panel {
            position: absolute; top: 48px; left: 0; z-index: 9999; width: 270px;
            background: #fff; border: 1px solid #cbd5e1; border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,.2); padding: 10px;
        }
        .ms-dd-panel .ms-search { width: 100%; box-sizing: border-box; min-width: 0 !important; padding: 8px 10px !important; font-size: 14px !important; margin-bottom: 4px; }
        .ms-dd-actions { display: flex; justify-content: space-between; font-size: 13px; margin: 6px 2px; }
        .ms-dd-actions a { color: #2563eb; text-decoration: none; font-weight: 600; }
        .ms-dd-actions a:hover { text-decoration: underline; }
        .ms-dd-list { max-height: 260px; overflow-y: auto; }
        .ms-dd-item { display: flex; align-items: center; gap: 8px; padding: 5px 6px; margin: 0; font-size: 14px; cursor: pointer; }
        .ms-dd-item:hover { background: #f1f5f9; border-radius: 6px; }
        .ms-dd-item input { width: auto; min-width: 0; margin: 0; }
        .ms-empty { padding: 8px; font-size: 13px; color: #94a3b8; display: none; }

        .card { background: #fff; padding: 16px; border-radius: 14px; box-shadow: 0 4px 20px rgba(0,0,0,.08); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { background: #2563eb; color: #fff; padding: 13px 12px; white-space: nowrap; text-align: center; font-weight: 600; border: 1px solid #dbeafe; }
        td { padding: 11px 12px; border: 1px solid #e2e8f0; white-space: nowrap; vertical-align: middle; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        tbody tr:hover { background: #dbeafe; }
        td.center { text-align: center; }

        /* ===== sortable header ===== */
        th a.sort-link { color: #fff; text-decoration: none; display: block; }
        th a.sort-link i { margin-left: 6px; font-size: 12px; opacity: .6; }
        th a.sort-link.active i { opacity: 1; }
        th a.sort-link:hover { text-decoration: underline; }

        .badge { display: inline-block; padding: 6px 12px; border-radius: 999px; font-size: 13px; font-weight: 700; color: #fff; }
        .green { background: #22c55e; } .red { background: #ef4444; } .orange { background: #f97316; }
        .blue { background: #3b82f6; } .gray { background: #94a3b8; }

        .empty { text-align: center; padding: 40px; color: #64748b; font-size: 16px; }
        .pagination-wrap { margin-top: 16px; }
        .pagination { display: flex; flex-wrap: wrap; gap: 6px; list-style: none; padding: 0; margin: 0; justify-content: flex-end; }
        .pagination li { display: inline-block; }
        .pagination li a,
        .pagination li span {
            display: block; padding: 8px 14px; border: 1px solid #cbd5e1; border-radius: 8px;
            background: #fff; color: #2563eb; text-decoration: none; font-size: 14px; font-weight: 600;
        }
        .pagination li a:hover { background: #dbeafe; }
        .pagination li.active span,
        .pagination li.active a { background: #2563eb; border-color: #2563eb; color: #fff; }
        .pagination li.disabled span,
        .pagination li.disabled a { color: #94a3b8; background: #f1f5f9; cursor: not-allowed; }

        @media (max-width: 768px) {
            .container { margin-left: 0; padding: 15px; }
            .filter-box input[type=text], .filter-box select, .btn { width: 100%; }
            .ms-dd { width: 100%; flex: 1 1 100%; }
            .ms-dd-panel { width: 100%; }
        }
    </style>
</head>

<body>

    @include('template.sidebar')

    <div class="container">

        <h2>🚚 In Transit</h2>

        @php
            $gudangAsalList = $gudangAsalList ?? ['KACS', 'SENTUL', 'CCIE'];

            // dropdown multi: name => [label default, daftar pilihan]
            $multiFilters = [
                'area'           => ['Semua Area', $areaList],
                'pic_monitoring' => ['Semua PIC', $picList],
                'gudang_asal'    => ['Semua Gudang Asal', $gudangAsalList],
            ];
        @endphp

        <div class="filter-box">
            <form method="GET" action="{{ $formRoute ?? route('monitoring.intransit') }}">

                {{-- supaya sort tidak hilang saat tombol Filter ditekan --}}
                <input type="hidden" name="sort" value="{{ request('sort') }}">
                <input type="hidden" name="dir"  value="{{ request('dir') }}">

                <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari shipment / tujuan / ekspedisi / driver / no pol...">

                @foreach($multiFilters as $name => [$defaultLabel, $items])
                    @continue(!count($items))
                    @php $selected = array_values(array_filter((array) request($name, []))); @endphp
                    <div class="ms-dd {{ count($selected) ? 'has-value' : '' }}" data-default="{{ $defaultLabel }}">
                        <button type="button" class="ms-dd-btn">
                            <span class="ms-label">{{ $defaultLabel }}</span>
                            <span>▾</span>
                        </button>
                        <div class="ms-dd-panel" style="display:none;">
                            <input type="text" class="ms-search" placeholder="Cari..." autocomplete="off">
                            <div class="ms-dd-actions">
                                <a href="#" class="ms-all">Pilih semua</a>
                                <a href="#" class="ms-clear">Hapus</a>
                            </div>
                            <div class="ms-dd-list">
                                @foreach($items as $item)
                                    <label class="ms-dd-item">
                                        <input type="checkbox" class="ms-chk" name="{{ $name }}[]" value="{{ $item }}"
                                            {{ in_array((string) $item, array_map('strval', $selected), true) ? 'checked' : '' }}>
                                        <span>{{ $item }}</span>
                                    </label>
                                @endforeach
                                <div class="ms-empty">Tidak ditemukan</div>
                            </div>
                        </div>
                    </div>
                @endforeach

                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Filter</button>
                <a href="{{ $formRoute ?? route('monitoring.intransit') }}" class="btn btn-reset">Reset</a>
                <a href="{{ ($exportRoute ?? route('monitoring.intransit.export')) . '?' . http_build_query(request()->except('page')) }}"
                   class="btn btn-success" style="background:#16a34a;">
                    <i class="fa-solid fa-file-excel"></i> Export Excel
                </a>
            </form>
        </div>

        <div class="card">

            @php
                $sortCols = [
                    'no_shipment'    => 'No Shipment',
                    'tujuan'         => 'Tujuan',
                    'area'           => 'Area',
                    'dist_channel'   => 'Dist Channel',
                    'ekpedisi'       => 'Ekspedisi',
                    'mobil'          => 'Mobil',
                    'nama_driver'    => 'Nama Driver',
                    'no_pol'         => 'No Pol',
                    'pic_monitoring' => 'PIC Monitoring',
                    'gudang_asal'    => 'Keluar Dari',
                    'tanggal_keluar' => 'Tanggal Keluar',
                    'lama_jalan'     => 'Lama Di Jalan',
                    'estimasi'       => 'Estimasi Tiba',
                    'alert'          => 'Alert',
                    'remarks'        => 'Remarks',
                ];
                $curSort = request('sort');
                $curDir  = request('dir') === 'desc' ? 'desc' : 'asc';
            @endphp

            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        @foreach($sortCols as $key => $label)
                            @php
                                $active  = $curSort === $key;
                                $nextDir = ($active && $curDir === 'asc') ? 'desc' : 'asc';
                                $url     = request()->fullUrlWithQuery(['sort' => $key, 'dir' => $nextDir, 'page' => null]);
                                $icon    = $active ? ($curDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort';
                            @endphp
                            <th>
                                <a href="{{ $url }}" class="sort-link {{ $active ? 'active' : '' }}">
                                    {{ $label }} <i class="fa-solid {{ $icon }}"></i>
                                </a>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($list as $i => $r)
                        <tr>
                            <td class="center">{{ $list->firstItem() + $i }}</td>
                            <td><b>{{ $r->no_shipment }}</b></td>
                            <td>{{ $r->tujuan }}</td>
                            <td>{{ $r->area }}</td>
                            <td>{{ $r->dist_channel }}</td>
                            <td>{{ $r->ekpedisi }}</td>
                            <td>{{ $r->mobil }}</td>
                            <td>{{ $r->nama_driver }}</td>
                            <td>{{ $r->no_pol }}</td>
                            <td>{{ $r->pic_monitoring ?: '-' }}</td>
                            <td class="center"><span class="badge blue">{{ $r->gudang_asal }}</span></td>
                            <td class="center">{{ $r->keluar_label }}</td>
                            <td class="center">{{ $r->hari_transit !== null ? $r->hari_transit . ' Hari' : '-' }}</td>
                            <td class="center">{{ $r->estimasi_label }}</td>
                            <td class="center"><span class="badge {{ $r->alert_class }}">{{ $r->alert_label }}</span></td>
                            <td>{{ $r->remarks }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="16" class="empty">✅ Tidak ada shipment yang sedang in transit.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="pagination-wrap">
                {{ $list->links('pagination::bootstrap-4') }}
            </div>
        </div>

    </div>

    <script>
        (function () {
            // label tombol: 0 = default, 1-2 = nama, >2 = "N dipilih"
            function updateLabel(dd) {
                var vals = Array.prototype.map.call(dd.querySelectorAll('.ms-chk:checked'), function (c) { return c.value; });
                var label = dd.getAttribute('data-default');
                if (vals.length === 1 || vals.length === 2) label = vals.join(', ');
                else if (vals.length > 2) label = vals.length + ' dipilih';
                dd.querySelector('.ms-label').textContent = label;
                dd.classList.toggle('has-value', vals.length > 0);
            }

            function visibleItems(dd) {
                return Array.prototype.filter.call(dd.querySelectorAll('.ms-dd-item'), function (el) {
                    return el.style.display !== 'none';
                });
            }

            document.querySelectorAll('.ms-dd').forEach(function (dd) {
                var panel  = dd.querySelector('.ms-dd-panel');
                var search = dd.querySelector('.ms-search');
                var empty  = dd.querySelector('.ms-empty');

                updateLabel(dd); // kondisi awal dari hasil filter sebelumnya

                // buka / tutup panel (tutup dropdown lain)
                dd.querySelector('.ms-dd-btn').addEventListener('click', function (e) {
                    e.stopPropagation();
                    var willOpen = panel.style.display === 'none';
                    document.querySelectorAll('.ms-dd-panel').forEach(function (p) { p.style.display = 'none'; });
                    panel.style.display = willOpen ? 'block' : 'none';
                    if (willOpen) search.focus();
                });

                panel.addEventListener('click', function (e) { e.stopPropagation(); });

                // search di dalam dropdown
                search.addEventListener('input', function () {
                    var q = search.value.toLowerCase().trim();
                    var shown = 0;
                    dd.querySelectorAll('.ms-dd-item').forEach(function (el) {
                        var ok = el.textContent.toLowerCase().indexOf(q) !== -1;
                        el.style.display = ok ? '' : 'none';
                        if (ok) shown++;
                    });
                    empty.style.display = shown ? 'none' : 'block';
                });

                dd.querySelectorAll('.ms-chk').forEach(function (c) {
                    c.addEventListener('change', function () { updateLabel(dd); });
                });

                // pilih semua (hanya yang tampil sesuai hasil search)
                dd.querySelector('.ms-all').addEventListener('click', function (e) {
                    e.preventDefault();
                    visibleItems(dd).forEach(function (el) { el.querySelector('.ms-chk').checked = true; });
                    updateLabel(dd);
                });

                dd.querySelector('.ms-clear').addEventListener('click', function (e) {
                    e.preventDefault();
                    dd.querySelectorAll('.ms-chk').forEach(function (c) { c.checked = false; });
                    updateLabel(dd);
                });
            });

            // klik di luar -> tutup semua panel
            document.addEventListener('click', function () {
                document.querySelectorAll('.ms-dd-panel').forEach(function (p) { p.style.display = 'none'; });
            });
        })();
    </script>

</body>

</html>