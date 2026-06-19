@include('template.sidebar')

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>DATA PLANNER</title>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

<link rel="stylesheet"
href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Segoe UI',sans-serif;
}

body{
    background:#f3f4f6;
}

/* CONTAINER */

.container{
    width:calc(100% - 250px);
    margin-left:250px;
    padding:25px;
}

/* TITLE */

.title{
    font-size:28px;
    font-weight:bold;
    margin-bottom:20px;
    color:#111827;
}

/* CARD */

.card{
    background:#fff;
    padding:20px;
    border-radius:20px;
    box-shadow:0 5px 20px rgba(0,0,0,.08);
    margin-bottom:20px;
}

/* IMPORT */

.import-box{
    display:flex;
    gap:10px;
    align-items:center;
}

input[type=file]{
    padding:10px;
    border:1px solid #d1d5db;
    border-radius:10px;
    background:#fff;
}

button{
    background:#22c55e;
    color:#fff;
    border:none;
    padding:12px 18px;
    border-radius:10px;
    cursor:pointer;
    font-weight:600;
    transition:.2s;
}

button:hover{
    background:#16a34a;
}

/* TABLE */

table{
    width:100%;
    border-collapse:collapse;
    font-size:13px;
}

th{
    background:#111827;
    color:#fff;
    padding:12px;
    white-space:nowrap;
    text-align:center;
}

td{
    padding:10px;
    border:1px solid #e5e7eb;
    white-space:nowrap;
    text-align:center;
}

tr:hover{
    background:#f9fafb;
}

/* DATATABLE */

.dataTables_wrapper{
    margin-top:10px;
}

.dataTables_filter input{
    border:1px solid #ccc;
    padding:6px;
    border-radius:6px;
}

.dataTables_length select{
    padding:5px;
    border-radius:6px;
}

</style>

</head>

<body>

<div class="container">

    <div class="title">
        📦 DATA PLANNER
    </div>

    {{-- IMPORT --}}
    <div class="card">

        <form action="{{ url('/logistik/import') }}"
              method="POST"
              enctype="multipart/form-data">

            @csrf

            <div class="import-box">

                <input type="file" name="file" required>

                <button type="submit">
                    Import Excel
                </button>

            </div>

        </form>

    </div>

    {{-- TABLE --}}
    <div class="card">

        <table id="tablePlanner" class="display nowrap">

            <thead>

                <tr>

                     <th>No</th>
    <th>Tanggal Naik Logistik</th>
    <th>Rencana Kirim</th>
    <th>Lead Time</th>
    <th>Planner</th>
    <th>No Shipment</th>
    <th>Tujuan</th>
    <th>Area</th>

    <th>Ketersediaan Unit</th>
    <th>Mobil</th>
    <th>Perubahan Mobil</th>

    <th>Nilai Muatan</th>
    <th>Biaya Kirim</th>
    <th>CR</th>

    <th>Kategori Ekspedisi</th>
    <th>Ekspedisi</th>
    <th>Nama Driver</th>
    <th>No Pol</th>

    <th>Status Pengiriman</th>

    <th>Tanggal Dapat Unit</th>
    <th>Planning Loading</th>

    <th>Tanggal Tiba KACS</th>
    <th>Tanggal Keluar KACS</th>
    <th>Lama DiKACS</th>
    <th>Status KACS</th>
    <th>SLA Loading KACS</th>

    <th>Keterangan</th>
    <th>Lama Waktu Pencarian</th>
    <th>SLA Dapat Mobil</th>

    <th>Tanggal Tiba SENTUL</th>
    <th>Tanggal Keluar SENTUL</th>
    <th>Lama Di SENTUL</th>
    <th>Status SENTUL</th>
    <th>SLA Loading SENTUL</th>

    <th>Tanggal Tiba CCIE </th>
    <th>Tanggal Keluar CCIE</th>
    <th>Lama Di CCIE</th>
    <th>Status Gudang CCIE</th>
    <th>SLA Loading CCIE</th>

                </tr>

            </thead>

            <tbody>

                @foreach($logistik as $r)

                <tr>

                  <td>{{ $r->no }}</td>

<td>{{ $r->tanggal_naik_logistik }}</td>
<td>{{ $r->rencana_kirim }}</td>
<td>{{ $r->transport_lead_time }}</td>

<td>{{ $r->planner }}</td>
<td>{{ $r->no_shipment }}</td>

<td>{{ $r->tujuan }}</td>
<td>{{ $r->area }}</td>

<td>{{ $r->ketersediaan_unit }}</td>
<td>{{ $r->mobil }}</td>
<td>{{ $r->perubahan_mobil }}</td>

<td>Rp {{ number_format($r->nilai_muatan,0,',','.') }}</td>
<td>Rp {{ number_format($r->biaya_kirim,0,',','.') }}</td>

<td>{{ $r->cr }}</td>

<td>{{ $r->kategori_ekspedisi }}</td>
<td>{{ $r->ekpedisi }}</td>
<td>{{ $r->nama_driver }}</td>
<td>{{ $r->no_pol }}</td>

<td>{{ $r->status_pengiriman }}</td>

<td>{{ $r->tanggal_dpt_unit }}</td>
<td>{{ $r->planning_loading }}</td>

<td>{{ $r->tanggal_tiba_gudang }}</td>
<td>{{ $r->tanggal_keluar_gudang }}</td>
<td>{{ $r->lama_digudang }}</td>
<td>{{ $r->status }}</td>
<td>{{ $r->sla_loading }}</td>

<td>{{ $r->keterangan }}</td>
<td>{{ $r->lama_waktu_pencarian }}</td>
<td>{{ $r->sla_dapat_mobil }}</td>

<td>{{ $r->tanggal_tiba_gudang_2 }}</td>
<td>{{ $r->tanggal_keluar_gudang_2 }}</td>
<td>{{ $r->lama_digudang_2 }}</td>
<td>{{ $r->status_gudang_2 }}</td>
<td>{{ $r->sla_loading_2 }}</td>

<td>{{ $r->tanggal_tiba_gudang_3 }}</td>
<td>{{ $r->tanggal_keluar_gudang_3 }}</td>
<td>{{ $r->lama_digudang_3 }}</td>
<td>{{ $r->status_gudang_3 ?? '-' }}</td>
<td>{{ $r->sla_loading_3 ?? '-' }}</td>

                </tr>

                @endforeach

            </tbody>

        </table>

    </div>

</div>

<script>

$(document).ready(function(){

    $('#tablePlanner').DataTable({
        scrollX:true,
        pageLength:10
    });

});

</script>

</body>
</html>