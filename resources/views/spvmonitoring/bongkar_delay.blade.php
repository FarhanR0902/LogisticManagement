@extends('layouts.app')

@section('content')

<style>
.container-fluid { padding: 20px; }

h3 { margin-bottom: 15px; color: #1e293b; font-weight: 600; }

.filter-box {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
    flex-wrap: wrap;
    align-items: center;
}

.filter-box select,
.filter-box input {
    padding: 7px 10px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 13px;
    min-width: 160px;
}

.btn-filter {
    padding: 7px 16px;
    background: #ef4444;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    cursor: pointer;
}

.btn-reset {
    padding: 7px 16px;
    background: #6b7280;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    cursor: pointer;
    text-decoration: none;
}

.card { background: #fff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); overflow: hidden; }
.card-body { padding: 15px; }
.table-responsive { overflow-x: auto; }

table { width: 100%; border-collapse: collapse; font-size: 13px; }
thead { background: #ef4444; color: white; }
th, td { padding: 10px; border: 1px solid #e5e7eb; text-align: center; white-space: nowrap; }
tbody tr:hover { background: #f9fafb; }

.badge { padding: 5px 10px; border-radius: 6px; font-size: 12px; color: white; display: inline-block; }
.bg-success { background: #22c55e; }
.bg-danger  { background: #ef4444; }
.bg-warning { background: #f59e0b; }

.summary-bar {
    display: flex;
    gap: 12px;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.summary-card {
    background: #fff;
    border-radius: 10px;
    padding: 12px 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.07);
    font-size: 13px;
    border-left: 4px solid #ef4444;
}

.summary-card span { font-size: 22px; font-weight: 700; color: #ef4444; display: block; }
</style>

<div class="container-fluid">

    <h3>📦 Bongkar Delay</h3>

    {{-- SUMMARY --}}
    <div class="summary-bar">
        <div class="summary-card">
            Total Delay
            <span>{{ $list->count() }}</span>
        </div>
        <div class="summary-card" style="border-color:#f59e0b">
            Rata-rata Overstay
            <span style="color:#f59e0b">
                {{ $list->count() > 0 ? number_format($list->avg(fn($r) => \Carbon\Carbon::parse($r->tanggal_tiba)->diffInDays(\Carbon\Carbon::parse($r->tanggal_bongkar))), 1) : 0 }} hari
            </span>
        </div>
    </div>

    {{-- FILTER --}}
    <form method="GET" action="{{ request()->url() }}">
        <div class="filter-box">
            <select name="area">
                <option value="">Semua Area</option>
                @foreach($areaList as $a)
                    <option value="{{ $a }}" {{ request('area') == $a ? 'selected' : '' }}>
                        {{ $a }}
                    </option>
                @endforeach
            </select>

            <input type="date" name="tanggal_bongkar"
                   value="{{ request('tanggal_bongkar') }}"
                   placeholder="Filter tanggal bongkar">

            <button type="submit" class="btn-filter">Filter</button>
            <a href="{{ request()->url() }}" class="btn-reset">Reset</a>
        </div>
    </form>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>No Shipment</th>
                            <th>Tujuan</th>
                            <th>Area</th>
                            <th>Ekspedisi</th>
                            <th>Lead Time</th>
                            <th>Tiba</th>
                            <th>Lama Perjalanan</th>
                            <th>SLA Tiba</th>
                            <th>Bongkar</th>
                            <th>Overstay (hari)</th>
                            <th>SLA Bongkar</th>
                            <th>Reason Bongkar</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($list as $key => $row)
                        @php
                            $overstay = \Carbon\Carbon::parse($row->tanggal_tiba)
                                ->diffInDays(\Carbon\Carbon::parse($row->tanggal_bongkar));

                            $keluar   = $row->tanggal_keluar_gudang
                                ? \Carbon\Carbon::parse($row->tanggal_keluar_gudang)
                                : null;
                            $tiba     = \Carbon\Carbon::parse($row->tanggal_tiba);
                            $estimasi = $keluar
                                ? $keluar->copy()->addDays((int)$row->transport_lead_time)
                                : null;

                            $sla_tiba = '-';
                            if ($estimasi) {
                                $sla_tiba = $tiba->lte($estimasi) ? 'On Time' : 'Delay';
                            }

                            $lama = $keluar ? $keluar->diffInDays($tiba) . ' hari' : '-';
                        @endphp
                        <tr>
                            <td>{{ $key + 1 }}</td>
                            <td>{{ $row->no_shipment }}</td>
                            <td>{{ $row->tujuan }}</td>
                            <td>{{ $row->area }}</td>
                            <td>{{ $row->ekpedisi }}</td>
                            <td>{{ $row->transport_lead_time }} hari</td>
                            <td>{{ $row->tanggal_tiba ? \Carbon\Carbon::parse($row->tanggal_tiba)->format('d-m-Y H:i') : '-' }}</td>
                            <td>{{ $lama }}</td>
                            <td>
                                <span class="badge {{ $sla_tiba == 'On Time' ? 'bg-success' : 'bg-danger' }}">
                                    {{ $sla_tiba }}
                                </span>
                            </td>
                            <td>{{ \Carbon\Carbon::parse($row->tanggal_bongkar)->format('d-m-Y H:i') }}</td>
                            <td>{{ $overstay }} hari</td>
                            <td>
                                <span class="badge bg-danger">Delay (H+{{ $overstay }})</span>
                            </td>
                            <td>{{ $row->reason_bongkar ?? '-' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="13" style="padding:20px;color:#6b7280">
                                Tidak ada data bongkar delay
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@endsection