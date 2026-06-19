@include('template.sidebar')

<!DOCTYPE html>
<html lang="id">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Summary Area Sales</title>

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
    margin-left:260px;
    padding:25px;
}

/* HEADER */
.header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:25px;
}

.header h2{
    color:#111827;
}

.header p{
    color:#6b7280;
    margin-top:5px;
}

.btn{
    padding:10px 16px;
    border-radius:8px;
    text-decoration:none;
    color:white;
    background:#2563eb;
    font-size:14px;
}

.btn:hover{
    background:#1d4ed8;
}

/* SUMMARY */
.summary-box{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:20px;
    margin-bottom:25px;
}

.card-summary{
    background: #fff;
    padding: 22px 24px;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    border-left: 5px solid #2563eb;

    display: flex;
    flex-direction: column;
    justify-content: center;
    min-height: 110px;

    transition: all .3s ease;
}

.card-summary:hover{
    transform: translateY(-3px);
    box-shadow: 0 8px 18px rgba(0,0,0,0.08);
}

.card-summary h4{
    margin: 0;
    font-size: 13px;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: .5px;
}

.card-summary h2{
    margin-top: 10px;
    font-size: 32px;
    font-weight: 700;
    color: #111827;
    line-height: 1;
}

/* TABLE */
.card{
    background:white;
    border-radius:15px;
    padding:20px;
    box-shadow:0 4px 10px rgba(0,0,0,.05);
}

.table-responsive{
    overflow-x:auto;
}

table{
    width:100%;
    border-collapse:collapse;
}

thead{
    background:#111827;
    color:white;
}

th,td{
    padding:12px;
    text-align:center;
    border-bottom:1px solid #e5e7eb;
}

tbody tr:hover{
    background:#f9fafb;
}

.badge{
    background:#dbeafe;
    color:#1d4ed8;
    padding:6px 12px;
    border-radius:20px;
    font-size:12px;
    font-weight:bold;
}

.btn-detail{
    background:#16a34a;
    color:white;
    padding:8px 12px;
    border-radius:6px;
    text-decoration:none;
    font-size:12px;
}

.btn-detail:hover{
    background:#15803d;
}

.empty{
    text-align:center;
    padding:20px;
    color:#6b7280;
}

@media(max-width:768px){

    .container{
        margin-left:0;
        padding:15px;
    }

    .header{
        flex-direction:column;
        align-items:flex-start;
        gap:10px;
    }
}

</style>

</head>

<body>

<div class="container">

    <div class="header">
        <div>
            <h2>📍 Summary Area </h2>
            <p>Ringkasan total shipment berdasarkan area</p>
        </div>

        <a href="{{ route('sales.dashboard') }}" class="btn">
            ⬅ Dashboard
        </a>
    </div>

    {{-- SUMMARY CARD --}}
    <div class="summary-box">

        <div class="card-summary">
            <h4>Total Area</h4>
            <h2>{{ count($summary_area) }}</h2>
        </div>

        <div class="card-summary">
            <h4>Total Shipment</h4>
            <h2>{{ collect($summary_area)->sum('total') }}</h2>
        </div>

    </div>

    {{-- TABLE --}}
    <div class="card">

        <div class="table-responsive">

            <table>

                <thead>
                    <tr>
                        <th>No</th>
                        <th>Area</th>
                        <th>Total Shipment</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                @forelse($summary_area as $key => $row)

                    <tr>

                        <td>{{ $key + 1 }}</td>

                        <td>
                            <span class="badge">
                                {{ $row->area }}
                            </span>
                        </td>

                        <td>
                            {{ number_format($row->total) }}
                        </td>

                        <td>

                            <a
                                href="{{ route('sales.summary.area.detail', ['area' => $row->area]) }}"
                                class="btn-detail">
                                🔍 Detail
                            </a>

                        </td>

                    </tr>

                @empty

                    <tr>
                        <td colspan="4" class="empty">
                            Tidak ada data area
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