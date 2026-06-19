@extends('layouts.app')

@section('content')

<style>
.container-fluid{
    padding:20px;
}

h3{
    margin-bottom:15px;
    color:#1e293b;
    font-weight:600;
}

.card{
    background:#fff;
    border-radius:12px;
    box-shadow:0 4px 12px rgba(0,0,0,0.08);
    overflow:hidden;
}

.card-body{ padding:15px; }

.table-responsive{ overflow-x:auto; }

table{
    width:100%;
    border-collapse:collapse;
    font-size:13px;
}

thead{
    background:#dc2626;
    color:#fff;
}

th, td{
    padding:10px;
    border:1px solid #e5e7eb;
    text-align:center;
    white-space:nowrap;
}

tbody tr:hover{
    background:#f9fafb;
}

.badge{
    padding:6px 10px;
    border-radius:6px;
    font-size:12px;
    color:#fff;
}

.bg-danger{ background:#ef4444; }
.bg-warning{ background:#f59e0b; }
</style>

<div class="container-fluid">

<h3>🚨 Bongkar Delay</h3>

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
            <th>Tujuan</th>
            <th>Area</th>
            <th>Ekspedisi</th>
            <th>Driver</th>
            <th>No Pol</th>
            <th>Urutan</th>
            <th>Tiba</th>
            <!-- <th>Perjalanan</th>
            <th>SLA Tiba</th> -->
            <th>Bongkar</th>
            <th>Overstay</th>
            <th>SLA Bongkar</th>
            <th>Reason Bongkar</th>
        </tr>
    </thead>

    <tbody>
    @forelse($list as $key => $r)

        @php
            // amanin date
            $tiba = $r->tanggal_tiba ? strtotime($r->tanggal_tiba) : null;
            $keluar = $r->tanggal_keluar_gudang ? strtotime($r->tanggal_keluar_gudang) : null;
            $bongkar = $r->tanggal_bongkar ? strtotime($r->tanggal_bongkar) : null;

            // OVERSTAY
            $overstay = '-';
            $sla_bongkar = '-';

            if($tiba && $bongkar){
                $overstay = floor(($bongkar - $tiba) / 86400);

                // 🔥 INI FILTER UTAMA DELAY
                if($overstay > 0){
                    $sla_bongkar = 'H+' . $overstay;
                }else{
                    $sla_bongkar = 'On Time';
                }
            }
        @endphp

        {{-- 🔥 HANYA DELAY --}}
        @if($sla_bongkar != 'On Time')

        <tr>
            <td>{{ $key + 1 }}</td>
            <td>{{ $r->tanggal_naik_logistik }}</td>
            <td>{{ $r->rencana_kirim }}</td>
            <td>{{ $r->transport_lead_time }}</td>
            <td>{{ $r->no_shipment }}</td>
            <td>{{ $r->tujuan }}</td>
            <td>{{ $r->area }}</td>
            <td>{{ $r->ekpedisi }}</td>
            <td>{{ $r->nama_driver }}</td>
            <td>{{ $r->no_pol }}</td>
            <td>{{ $r->act_urutan_bongkar }}</td>
            <td>{{ $r->tanggal_tiba }}</td>
            <!-- <td>
                {{ $keluar && $tiba ? floor(($tiba-$keluar)/86400) : '-' }}
            </td> -->
<!-- 
            <td>
                @if($r->sla_tiba == 'On Time')
                    <span class="badge bg-warning">On Time</span>
                @else
                    <span class="badge bg-danger">{{ $r->sla_tiba }}</span>
                @endif
            </td> -->

            <td>{{ $r->tanggal_bongkar }}</td>
            <td>{{ $overstay }}</td>

            <td>
                <span class="badge bg-danger">
                    {{ $sla_bongkar }}
                </span>
            </td>

            <td>{{ $r->reason_bongkar }}</td>
        </tr>

        @endif

    @empty
        <tr>
            <td colspan="18">Tidak ada data delay</td>
        </tr>
    @endforelse
    </tbody>

</table>

</div>

</div>
</div>

</div>

@endsection