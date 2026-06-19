@include('template.sidebar')

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Storage Archive</title>

<style>
body{
    font-family:'Segoe UI',sans-serif;
    background:#f3f4f6;
}

.container{
    margin-left:250px;
    padding:20px;
}

h2{
    margin-bottom:15px;
    color:#111827;
}

.kpi-row{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:15px;
    margin-bottom:15px;
}

.card{
    background:#fff;
    padding:15px;
    border-radius:12px;
    box-shadow:0 6px 16px rgba(0,0,0,.06);
}

.filter-box{
    background:#fff;
    padding:15px;
    border-radius:12px;
    margin-bottom:15px;
}

.filter{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:10px;
}

select, input{
    padding:10px;
    border:1px solid #ddd;
    border-radius:8px;
}

button{
    padding:10px;
    background:#3b82f6;
    color:#fff;
    border:none;
    border-radius:8px;
    cursor:pointer;
}

.table-box{
    background:#fff;
    padding:15px;
    border-radius:12px;
    overflow-x:auto;
}

table{
    width:100%;
    border-collapse:collapse;
}

th{
    background:#111827;
    color:#fff;
    padding:10px;
    font-size:13px;
}

td{
    padding:10px;
    border-bottom:1px solid #eee;
    font-size:13px;
    text-align:center;
}
</style>
</head>

<body>

<div class="container">

<h2>🗄 STORAGE ARCHIVE LOGISTIK</h2>

@if(session('success'))
<div style="background:#dcfce7;padding:10px;border-radius:8px;margin-bottom:10px;">
    {{ session('success') }}
</div>
@endif

<!-- ================= KPI ================= -->
<div class="kpi-row">

    <div class="card">
        <h4>Total Data</h4>
        <h1>{{ $total_data }}</h1>
    </div>

    <div class="card">
        <h4>Total Biaya</h4>
        <h1>Rp {{ number_format($total_biaya,0,',','.') }}</h1>
    </div>

    <div class="card">
        <h4>Total Muatan</h4>
        <h1>Rp {{ number_format($total_muatan,0,',','.') }}</h1>
    </div>

<div class="card">
    <h3>Cost Ratio</h3>
    <h1>{{ number_format($cost_ratio ?? 0, 2) }}%</h1>
</div>

</div>

<!-- ================= FILTER ================= -->
<div class="filter-box">

<form method="GET" class="filter">

<select name="month">
    <option value="">All Month</option>

    @for($m = 1; $m <= 12; $m++)
        <option value="{{ $m }}" {{ request('month') == $m ? 'selected' : '' }}>
            {{ \Carbon\Carbon::create()->month($m)->format('F') }}
        </option>
    @endfor
</select>

    <select name="year">
        <option value="">All Year</option>
        @for($y=date('Y'); $y>=2020; $y--)
            <option value="{{ $y }}" {{ request('year') == $y ? 'selected' : '' }}>
                {{ $y }}
            </option>
        @endfor
    </select>

    <select name="area">
        <option value="">All Area</option>
        @foreach($list_area as $a)
            <option value="{{ $a->area }}" {{ request('area') == $a->area ? 'selected' : '' }}>
                {{ $a->area }}
            </option>
        @endforeach
    </select>

    <button type="submit">Filter</button>

</form>

</div>
<a href="{{ url('/storage/export?' . http_build_query(request()->all())) }}"
   style="
        padding:10px 15px;
        background:green;
        color:white;
        border-radius:8px;
        text-decoration:none;
        display:inline-block;
        margin-right:10px;
   ">
   Export Excel
</a>

<form action="{{ url('/storage/delete-all') }}"
      method="POST"
      style="display:inline-block;"
      onsubmit="return confirm('Yakin ingin menghapus SEMUA data archive?')">

    @csrf
    @method('DELETE')

    <button type="submit"
        style="
            background:#ef4444;
            color:white;
            border:none;
            padding:10px 15px;
            border-radius:8px;
            cursor:pointer;
            font-weight:bold;
        ">
        🗑 Hapus Semua Data
    </button>

</form>
<!-- ================= TABLE ================= -->
<div class="table-box">

<table>

<thead>
<tr>
    <th>No</th>
    <th>No Shipment</th>
    <th>Tanggal Naik</th>
    <th>Tanggal Tiba</th>
    <th>Area</th>
    <th>Tujuan</th>
    <th>Biaya</th>
    <th>Muatan</th>
    <th>Status</th>
</tr>
</thead>

<tbody>

@forelse($data as $i => $row)
<tr>
    <td>{{ $i+1 }}</td>
    <td>{{ $row->no_shipment }}</td>
    <td>{{ $row->tanggal_naik_logistik }}</td>
    <td>{{ $row->tanggal_tiba }}</td>
    <td>{{ $row->area }}</td>
    <td>{{ $row->tujuan }}</td>
    <td>Rp {{ number_format($row->biaya_kirim,0,',','.') }}</td>
    <td>Rp {{ number_format($row->nilai_muatan,0,',','.') }}</td>
    <td>{{ $row->status_akhir }}</td>
</tr>
@empty
<tr>
    <td colspan="9">No Data Found</td>
</tr>
@endforelse

</tbody>

</table>

</div>

</div>

</body>
</html>