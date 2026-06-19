@extends('layouts.app')

@section('content')

<style>
.container-fluid{
    padding:20px;
}

.page-title{
    margin-bottom:15px;
    color:#1e293b;
    font-weight:700;
    font-size:24px;
}

/* CARD */
.card{
    background:#fff;
    border-radius:12px;
    box-shadow:0 4px 12px rgba(0,0,0,0.08);
    overflow:hidden;
}

.card-body{
    padding:15px;
}

/* TOPBAR */
.topbar{
    margin-bottom:15px;
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}

.btn{
    display:inline-block;
    padding:8px 14px;
    border-radius:8px;
    text-decoration:none;
    color:white;
    font-size:13px;
    font-weight:600;
}

.btn-primary{
    background:#2563eb;
}

.btn-success{
    background:#16a34a;
}

/* TABLE */
.table-responsive{
    overflow-x:auto;
}

table{
    width:100%;
    border-collapse:collapse;
    font-size:13px;
    white-space:nowrap;
}

thead{
    background:#16a34a;
    color:white;
}

th, td{
    padding:10px;
    border:1px solid #e5e7eb;
    text-align:center;
}

tbody tr:hover{
    background:#f9fafb;
    transition:0.2s;
}

/* BADGE */
.badge{
    padding:6px 10px;
    border-radius:6px;
    font-size:12px;
    color:white;
    display:inline-block;
    font-weight:600;
}

.bg-success{
    background:#22c55e;
}

.bg-danger{
    background:#ef4444;
}

/* EMPTY */
.empty{
    text-align:center;
    padding:20px;
    color:#64748b;
}

/* RESPONSIVE */
@media(max-width:768px){

    .container-fluid{
        padding:10px;
    }

    th,td{
        font-size:11px;
        padding:8px;
    }

}
</style>

<div class="container-fluid">

    <h3 class="page-title">
        🚚 DATA BONGKAR ON TIME
    </h3>

    <div class="topbar">

        <a href="{{ route('manager.dashboard') }}" class="btn btn-primary">
            ⬅ Dashboard
        </a>

        <a href="{{ url('/export') }}" class="btn btn-success">
            📥 Export
        </a>

    </div>

    <div class="card">

        <div class="card-body">

            <div class="table-responsive">

                <table>

                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Tanggal Naik</th>
                            <th>Rencana Kirim</th>
                            <th>Lead Time</th>
                            <th>No Shipment</th>
                            <th>Dist Channel</th>
                            <th>Tujuan</th>
                            <th>Area</th>
                            <th>Ekspedisi</th>
                            <th>Driver</th>
                            <th>No Polisi</th>
                            <th>Urutan Bongkar</th>
                            <th>Tanggal Tiba</th>
                            <th>Lama Perjalanan</th>
                            <th>SLA Tiba</th>
                            <th>Tanggal Bongkar</th>
                            <th>Overstay</th>
                            <th>SLA Bongkar</th>
                            <th>Reason Bongkar</th>
                        </tr>
                    </thead>

                    <tbody>

                  @forelse($logistik->filter(function($item){
    return in_array(strtolower(trim($item->sla_bongkar)), [
        'on time',
        'ontime',
        'h+0'
    ]);
}) as $key => $row)

@php
    $keluar = $row->tanggal_keluar_gudang
        ? strtotime($row->tanggal_keluar_gudang)
        : strtotime($row->rencana_kirim);

    $tiba = $row->tanggal_tiba
        ? strtotime($row->tanggal_tiba)
        : null;

    $lama_perjalanan = ($keluar && $tiba)
        ? max(0, floor(($tiba - $keluar) / 86400))
        : 0;

    $bongkar = $row->tanggal_bongkar
        ? strtotime($row->tanggal_bongkar)
        : null;

    $overstay = ($tiba && $bongkar)
        ? max(0, floor(($bongkar - $tiba) / 86400))
        : 0;
@endphp

                        <tr>

                            <td>{{ $key + 1 }}</td>

                            <td>{{ $row->tanggal_naik_logistik ?? '-' }}</td>

                            <td>{{ $row->rencana_kirim ?? '-' }}</td>

                            <td>
                                {{ $row->transport_lead_time ?? 0 }} Hari
                            </td>

                            <td>{{ $row->no_shipment ?? '-' }}</td>

                            <td>{{ $row->dist_channel }}</td>

                            <td>{{ $row->tujuan ?? '-' }}</td>

                            <td>{{ $row->area ?? '-' }}</td>

                            <td>{{ $row->ekspedisi ?? $row->ekpedisi ?? '-' }}</td>

                            <td>{{ $row->nama_driver ?? '-' }}</td>

                            <td>{{ $row->no_pol ?? '-' }}</td>

                            <td>{{ $row->act_urutan_bongkar ?? '-' }}</td>

                            <td>{{ $row->tanggal_tiba ?? '-' }}</td>

                            <td>
                               {{ $lama_perjalanan }} Hari
                            </td>

                            <td>{{ $row->sla_tiba ?? '-' }}</td>

                            <td>{{ $row->tanggal_bongkar ?? '-' }}</td>

                            <td>
                                {{ $row->overstay_days ?? 0 }} Hari
                            </td>

                            <td>{{ $row->sla_bongkar ?? '-' }}</td>
<!-- 
                            <td>

                                @if(
                                    strtolower($row->sla_bongkar ?? '') == 'h+0'
                                    || strtolower($row->sla_bongkar ?? '') == 'on time'
                                    || strtolower($row->sla_bongkar ?? '') == 'ontime'
                                )

                                    <span class="badge bg-success">
                                        ON TIME
                                    </span>

                                @else

                                    <span class="badge bg-danger">
                                        DELAY
                                    </span>

                                @endif

                            </td> -->
                            <td>{{ $row->reason_bongkar }}</td>

                        </tr>

                        @empty

                        <tr>
                            <td colspan="18" class="empty">
                                Tidak ada data bongkar on time
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