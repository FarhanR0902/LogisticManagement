@include('template.sidebar')

<!DOCTYPE html>
<html lang="id">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Detail Area {{ $area }}</title>

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    font-family:'Segoe UI',sans-serif;
    background:#f4f6f9;
}

.container{
    width:calc(100% - 250px);
    margin-left:250px;
    padding:25px;
}

/* ================= HEADER ================= */

.page-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:25px;
}

.page-title h2{
    color:#111827;
    font-size:28px;
}

.page-title p{
    color:#6b7280;
    margin-top:5px;
}

/* ================= BUTTON ================= */

.btn{
    display:inline-block;
    padding:10px 16px;
    border-radius:10px;
    text-decoration:none;
    color:white;
    font-size:14px;
    font-weight:600;
    transition:.3s;
}

.btn-primary{
    background:#2563eb;
}

.btn-primary:hover{
    background:#1d4ed8;
}

/* ================= SUMMARY ================= */

.summary-box{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:20px;
    margin-bottom:25px;
}

.summary-card{
    background:white;
    border-radius:16px;
    padding:20px;
    box-shadow:0 4px 12px rgba(0,0,0,.05);
}

.summary-card h4{
    color:#6b7280;
    font-size:14px;
}

.summary-card h2{
    margin-top:10px;
    font-size:28px;
    color:#111827;
}

.blue{
    border-left:5px solid #2563eb;
}

.green{
    border-left:5px solid #16a34a;
}

.orange{
    border-left:5px solid #f59e0b;
}

/* ================= CARD ================= */

.card{
    background:white;
    border-radius:18px;
    padding:25px;
    box-shadow:0 5px 15px rgba(0,0,0,.06);
}

/* ================= TABLE ================= */

.table-responsive{
    overflow-x:auto;
}

table{
    width:100%;
    border-collapse:collapse;
    white-space:nowrap;
}

thead{
    background:#111827;
    color:white;
}

th{
    padding:14px;
    text-align:center;
    font-size:14px;
}

td{
    padding:12px;
    text-align:center;
    border-bottom:1px solid #e5e7eb;
    font-size:13px;
}

tbody tr:hover{
    background:#f9fafb;
}

/* ================= BADGE ================= */

.badge{
    display:inline-block;
    padding:6px 12px;
    border-radius:20px;
    font-size:12px;
    font-weight:bold;
}

.badge-area{
    background:#dbeafe;
    color:#1d4ed8;
}

.badge-success{
    background:#dcfce7;
    color:#166534;
}

.badge-danger{
    background:#fee2e2;
    color:#991b1b;
}

.empty{
    padding:25px;
    text-align:center;
    color:#6b7280;
}

/* ================= RESPONSIVE ================= */

@media(max-width:768px){

    .container{
        width:100%;
        margin-left:0;
        padding:15px;
    }

    .summary-box{
        grid-template-columns:1fr;
    }

    .page-header{
        flex-direction:column;
        align-items:flex-start;
        gap:15px;
    }
}

</style>
</head>

<body>

<div class="container">

    <!-- HEADER -->

    <div class="page-header">

        <div class="page-title">
            <h2>📍 Detail Area {{ $area }}</h2>
    
        </div>

        <a href="{{ route('sales.summary.area') }}" class="btn btn-primary">
            ⬅ Kembali
        </a>

    </div>

    <!-- KPI -->

    <div class="summary-box">

        <div class="summary-card blue">
            <h4>Area</h4>
            <h2>{{ $area }}</h2>
        </div>

        <div class="summary-card green">
            <h4>Total Shipment</h4>
            <h2>{{ $logistik->count() }}</h2>
        </div>

  

    </div>

    <!-- TABLE -->

    <div class="card">

        <div class="table-responsive">

            <table>

                <thead>

                    <tr>
                        <th>No</th>
                        <th>No Shipment</th>
                        <th>Tanggal Naik</th>
                        <th>Rencana Kirim</th>
                        <th>Lead Time</th>
                        <th>Tujuan</th>
                        <th>Area</th>
                        <th>Ekspedisi</th>
                        <th>Driver</th>
                        <th>No Polisi</th>
                        <th>Tanggal Tiba</th>
                        <th>SLA Tiba</th>
                        <th>Reason Tiba</th>
                        <th>Tanggal Bongkar</th>
                        <th>SLA Bongkar</th>
                        <th>Reason Bongkar </th>
                    </tr>

                </thead>

                <tbody>

                @forelse($logistik as $key => $row)

                    <tr>

                        <td>{{ $key + 1 }}</td>

                        <td>{{ $row->no_shipment }}</td>

                        <td>{{ $row->tanggal_naik_logistik ?? '-' }}</td>

                        <td>{{ $row->rencana_kirim ?? '-' }}</td>

                        <td>{{ $row->transport_lead_time ?? '-' }}</td>

                        <td>{{ $row->tujuan ?? '-' }}</td>

                        <td>
                            <span class="badge badge-area">
                                {{ $row->area }}
                            </span>
                        </td>

                        <td>{{ $row->ekpedisi ?? '-' }}</td>

                        <td>{{ $row->nama_driver ?? '-' }}</td>

                        <td>{{ $row->no_pol ?? '-' }}</td>

                        <td>{{ $row->tanggal_tiba ?? '-' }}</td>

                        <td>

                            @if(
                                strtolower(trim($row->sla_tiba ?? '')) == 'on time' ||
                                strtolower(trim($row->sla_tiba ?? '')) == 'ontime' ||
                                strtolower(trim($row->sla_tiba ?? '')) == 'h+0'
                            )

                                <span class="badge badge-success">
                                    {{ $row->sla_tiba }}
                                </span>

                            @else

                                <span class="badge badge-danger">
                                    {{ $row->sla_tiba }}
                                </span>

                            @endif

                        </td>

                        <td>{{ $row->reason_tiba ?? '-' }}</td>

                          <td>{{ $row->tanggal_bongkar ?? '-' }}</td>

                          <td>

                            @if(
                                strtolower(trim($row->sla_bongkar ?? '')) == 'on time' ||
                                strtolower(trim($row->sla_bongkar ?? '')) == 'ontime' ||
                                strtolower(trim($row->sla_bongkar ?? '')) == 'h+0'
                            )

                                <span class="badge badge-success">
                                    {{ $row->sla_bongkar }}
                                </span>

                            @else

                                <span class="badge badge-danger">
                                    {{ $row->sla_bongkar }}
                                </span>

                            @endif

                        </td>

                        <td>{{ $row->reason_bongkar ?? '-' }}</td>
                    </tr>

                @empty

                    <tr>
                        <td colspan="13" class="empty">
                            Tidak ada data shipment untuk area ini
                        </td>
                    </tr>

                @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>
</html>