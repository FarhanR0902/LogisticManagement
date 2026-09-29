<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>

    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f1f5f9; margin: 0; color: #1e293b; }
        .container { margin-left: 260px; padding: 30px; }
        h2 { font-size: 30px; margin: 0 0 6px; color: #0f172a; }
        .note { color: #64748b; font-size: 14px; margin-bottom: 20px; }

        .filter-box { background: #fff; padding: 16px 20px; border-radius: 16px; box-shadow: 0 4px 16px rgba(0,0,0,.08); margin-bottom: 20px; }
        .filter-box form { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
        .filter-box select, .filter-box button { padding: 11px 16px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 15px; }
        .filter-box button { background: #2563eb; color: #fff; border: none; font-weight: 600; cursor: pointer; }

        .cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 14px; margin-bottom: 24px; }
        .kcard { background: #fff; border-radius: 14px; padding: 16px; box-shadow: 0 4px 16px rgba(0,0,0,.08); border-left: 6px solid #9ca3af; }
        .kcard .lbl { font-size: 13px; color: #64748b; font-weight: 600; }
        .kcard .val { font-size: 28px; font-weight: 700; margin-top: 4px; }
        .kcard .tgt { font-size: 12px; color: #94a3b8; margin-top: 2px; }
        .kcard.green { border-color: #22c55e; } .kcard.green .val { color: #16a34a; }
        .kcard.orange { border-color: #f59e0b; } .kcard.orange .val { color: #d97706; }
        .kcard.red { border-color: #ef4444; } .kcard.red .val { color: #dc2626; }

        .card { background: #fff; padding: 20px; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,.08); overflow: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 15px; }
        th { background: #03c03c; color: #fff; padding: 12px; text-align: center; white-space: nowrap; }
        th small { display: block; font-weight: 400; opacity: .9; font-size: 11px; }
        td { padding: 11px 12px; border: 1px solid #e2e8f0; text-align: center; white-space: nowrap; }
        td.name { text-align: left; font-weight: 600; }
        tr.teamrow td { background: #eff6ff; font-weight: 700; }
        .pill { display: inline-block; min-width: 64px; padding: 6px 12px; border-radius: 999px; color: #fff; font-weight: 700; font-size: 14px; }
        .pill.green { background: #22c55e; } .pill.orange { background: #f59e0b; } .pill.red { background: #ef4444; } .pill.gray { background: #9ca3af; }

        @media(max-width:768px) { .container { margin-left: 0; padding: 15px; } }
    </style>
</head>

<body>
    @include('template.sidebar')

    @php
        // warna berdasarkan target
        $cls = function ($col, $v) {
            if ($v === null || $col['target'] === null) return 'gray';
            $t = $col['target'];
            if ($col['better'] === 'high') {
                return $v >= $t ? 'green' : ($v >= $t - 10 ? 'orange' : 'red');
            }
            return $v <= $t ? 'green' : ($v <= $t * 1.5 + 1 ? 'orange' : 'red');
        };
        $fmt = fn($col, $v) => $v === null ? '-' : (rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',') . $col['unit']);
        $year = request('year', date('Y'));
    @endphp

    <div class="container">
        <h2>{{ $title }}</h2>
        <div class="note">{{ $notes }}</div>

        <div class="filter-box">
            <form method="GET" action="{{ url()->current() }}">
                <select name="year">
                    <option value="all" @selected($year === 'all')>Semua Tahun</option>
                    @for ($y = 2023; $y <= date('Y') + 1; $y++)
                        <option value="{{ $y }}" @selected((string) $year === (string) $y)>{{ $y }}</option>
                    @endfor
                </select>

                <select name="month">
                    <option value="">Semua Bulan</option>
                    @for ($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" @selected((string) request('month') === (string) $m)>{{ $m }}</option>
                    @endfor
                </select>

                <select name="area">
                    <option value="">Semua Area</option>
                    @foreach ($areaList as $a)
                        <option value="{{ $a }}" @selected(request('area') === $a)>{{ $a }}</option>
                    @endforeach
                </select>

                <button type="submit">Terapkan</button>
            </form>
        </div>

        {{-- KARTU TIM --}}
        <div class="cards">
            <div class="kcard">
                <div class="lbl">Total Shipment</div>
                <div class="val" style="color:#0f172a">{{ number_format($team['total'], 0, ',', '.') }}</div>
            </div>
            @foreach ($columns as $col)
                @php $v = $team[$col['key']]; @endphp
                <div class="kcard {{ $cls($col, $v) }}">
                    <div class="lbl">{{ $col['label'] }}</div>
                    <div class="val">{{ $fmt($col, $v) }}</div>
                    @if ($col['target'] !== null)
                        <div class="tgt">Target {{ $col['better'] === 'high' ? '≥' : '≤' }} {{ $col['target'] }}{{ $col['unit'] }}</div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- SCORECARD PER ORANG --}}
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th style="text-align:left">{{ $personLabel }}</th>
                        <th>Shipment</th>
                        @foreach ($columns as $col)
                            <th>{{ $col['label'] }}
                                @if ($col['target'] !== null)
                                    <small>{{ $col['better'] === 'high' ? '≥' : '≤' }} {{ $col['target'] }}{{ $col['unit'] }}</small>
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <tr class="teamrow">
                        <td class="name">TOTAL TIM</td>
                        <td>{{ number_format($team['total'], 0, ',', '.') }}</td>
                        @foreach ($columns as $col)
                            <td><span class="pill {{ $cls($col, $team[$col['key']]) }}">{{ $fmt($col, $team[$col['key']]) }}</span></td>
                        @endforeach
                    </tr>

                    @forelse ($people as $p)
                        <tr>
                            <td class="name">{{ $p['name'] }}</td>
                            <td>{{ number_format($p['total'], 0, ',', '.') }}</td>
                            @foreach ($columns as $col)
                                <td><span class="pill {{ $cls($col, $p[$col['key']]) }}">{{ $fmt($col, $p[$col['key']]) }}</span></td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($columns) + 2 }}">Tidak ada data untuk filter ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>