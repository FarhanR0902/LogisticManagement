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

.card-body{
    padding:15px;
}

.table-responsive{
    overflow-x:auto;
}

table{
    width:100%;
    border-collapse:collapse;
    font-size:13px;
}

thead{
    background:#16a34a;
    color:white;
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

.bg-success{ background:#22c55e; }
.bg-danger{ background:#ef4444; }

@media(max-width:768px){
    th,td{
        font-size:11px;
        padding:8px;
    }
}
</style>

<div class="container-fluid">

    <h3>🚚 Bongkar On Time</h3>

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
                            <!-- <th>SLA Tiba</th> -->
                            <th>Bongkar</th>
                            <th>Overstay</th>
                            <th>SLA Bongkar</th>
                            <th>Reason Bongkar</th>
                        </tr>
                    </thead>

                    <tbody>

                        @forelse($list as $key => $row)

                        <tr>
                            <td>{{ $key + 1 }}</td>

                            <td>{{ $row->tanggal_naik_logistik ?? '-' }}</td>
                            <td>{{ $row->rencana_kirim ?? '-' }}</td>
                            <td>{{ $row->transport_lead_time ?? '-' }}</td>

                            <td>{{ $row->no_shipment ?? '-' }}</td>
                            <td>{{ $row->tujuan ?? '-' }}</td>
                            <td>{{ $row->area ?? '-' }}</td>

                            {{-- FIX TYPO --}}
                            <td>{{ $row->ekpedisi ?? '-' }}</td>

                            <td>{{ $row->nama_driver ?? '-' }}</td>
                            <td>{{ $row->no_pol ?? '-' }}</td>

                            <td>{{ $row->act_urutan_bongkar ?? '-' }}</td>

                            <td>{{ $row->tanggal_tiba ?? '-' }}</td>
<!-- 
                            {{-- SLA TIBA --}}
                            <td>
                                @if(($row->sla_tiba ?? '') == 'On Time')
                                    <span class="badge bg-success">
                                        {{ $row->sla_tiba }}
                                    </span>
                                @else
                                    <span class="badge bg-danger">
                                        {{ $row->sla_tiba }}
                                    </span>
                                @endif
                            </td> -->

                            <td>{{ $row->tanggal_bongkar ?? '-' }}</td>

                            <td>{{ $row->overstay_days ?? 0 }}</td>

                            {{-- SLA BONGKAR --}}
                            <td>
                                @if(($row->sla_bongkar ?? '') == 'On Time')
                                    <span class="badge bg-success">
                                        {{ $row->sla_bongkar }}
                                    </span>
                                @else
                                    <span class="badge bg-danger">
                                        {{ $row->sla_bongkar }}
                                    </span>
                                @endif
                            </td>

                            <td>{{ $row->reason_bongkar ?? '-' }}</td>

                        </tr>

                        @empty

                        <tr>
                            <td colspan="17">Tidak ada data bongkar on time</td>
                        </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </div>
    </div>
</div>

@endsection