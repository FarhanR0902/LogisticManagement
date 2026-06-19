<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>DATA LOGISTIK</title>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

    <style>
        body {
            font-family: Arial;
            background: #f4f6f9;
            margin: 0;
        }

        .container {
            margin-left: 240px;
            padding: 20px;
        }

        .card {
            background: #fff;
            padding: 15px;
            border-radius: 10px;
            overflow: auto;
        }

        table {
            width: 100%;
            font-size: 12px;
            border-collapse: collapse;
        }

        th {
            background: #2e7d32;
            color: #fff;
            padding: 6px;
            white-space: nowrap;
        }

      td {
    padding: 6px;
    border: 1px solid #ddd;
    white-space: nowrap;
    text-align: left;
    vertical-align: middle;
}
        /* ================= FILTER BOX ================= */
.filter-box{
    background:#fff;
    padding:15px;
    border-radius:12px;
    box-shadow:0 4px 12px rgba(0,0,0,0.08);
    margin-bottom:15px;
}

/* FORM FILTER */
.filter-box form{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:center;
}

/* INPUT & SELECT */
.filter-box input,
.filter-box select{
    padding:10px 12px;
    border:1px solid #ddd;
    border-radius:8px;
    font-size:13px;
    outline:none;
    transition:0.2s;
}

.filter-box input:focus,
.filter-box select:focus{
    border-color:#3b82f6;
    box-shadow:0 0 0 2px rgba(59,130,246,0.15);
}

/* BUTTON FILTER */
.filter-box button{
    padding:10px 14px;
    background:#22c55e;
    color:#fff;
    border:none;
    border-radius:8px;
    cursor:pointer;
    font-size:13px;
    transition:0.2s;
}

.filter-box button:hover{
    background:#16a34a;
}

/* RESET LINK */
.filter-box a{
    padding:10px 14px;
    background:#ef4444;
    color:#fff;
    border-radius:8px;
    text-decoration:none;
    font-size:13px;
    transition:0.2s;
}

.filter-box a:hover{
    background:#dc2626;
}

/* ================= IMPORT BOX MODERN ================= */
.import-box{
    background: linear-gradient(135deg, #ffffff, #f1f5f9);
    padding: 18px;
    border-radius: 14px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.08);
    margin-bottom: 15px;
    border: 1px solid #e5e7eb;
}

/* FORM LAYOUT */
.import-box form{
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
}

/* FILE INPUT */
.import-box input[type="file"]{
    padding: 10px;
    border: 1px dashed #cbd5e1;
    border-radius: 10px;
    background: #f8fafc;
    cursor: pointer;
    font-size: 13px;
    transition: 0.2s;
}

.import-box input[type="file"]:hover{
    border-color: #3b82f6;
    background: #eff6ff;
}

/* BUTTON IMPORT */
.import-box button{
    padding: 10px 16px;
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: 0.2s;
    box-shadow: 0 4px 10px rgba(59,130,246,0.3);
}

.import-box button:hover{
    transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(59,130,246,0.4);
}

.import-box button:active{
    transform: scale(0.98);
}
    </style>
</head>

<body>

    @include('template.sidebar')

    <div class="container">

        <h2>📦 DATA LOGISTIK</h2>

      <div class="import-box">
    <form action="{{ url('/logistik/import') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <input type="file" name="file" required>

        <button type="submit">📤 Import Excel</button>
    </form>
</div>

<!-- FILTER -->


<!-- HAPUS SEMUA -->
<form action="{{ url('/logistik/archive-all') }}"
      method="POST"
      onsubmit="return confirm('Pindahkan semua data ke Storage?')"
      class="archive-form">

    @csrf

    <button type="submit" class="archive-btn">
        🗄 Archive All (Move to Storage)
    </button>

</form>

<style>

.archive-form{
    margin:20px 0;
}

.archive-btn{
    background:linear-gradient(135deg,#2563eb,#1d4ed8);
    color:white;
    border:none;
    padding:12px 22px;
    border-radius:12px;
    font-size:14px;
    font-weight:600;
    cursor:pointer;
    transition:0.3s ease;
    box-shadow:0 4px 12px rgba(37,99,235,0.25);
    display:inline-flex;
    align-items:center;
    gap:8px;
}

.archive-btn:hover{
    transform:translateY(-2px);
    background:linear-gradient(135deg,#1d4ed8,#1e40af);
    box-shadow:0 8px 18px rgba(37,99,235,0.35);
}

.archive-btn:active{
    transform:scale(0.98);
}

</style>

<div class="filter-box">

    <form id="filterForm">

        <select id="filterArea">
            <option value="">Semua Area</option>
            @foreach($areaList as $a)
                <option value="{{ $a }}">{{ $a }}</option>
            @endforeach
        </select>

        <select id="filterMonth">
            <option value="">Semua Bulan</option>
            @for($i=1;$i<=12;$i++)
                <option value="{{ $i }}">{{ $i }}</option>
            @endfor
        </select>

        <select id="filterYear">
            <option value="">Semua Tahun</option>
            @for($i=2023;$i<=2026;$i++)
                <option value="{{ $i }}">{{ $i }}</option>
            @endfor
        </select>

    </form>

</div>
            <table id="tableLogistik" class="display nowrap">

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
                        <th>Status</th>

                        <th>Tanggal Dpt Unit</th>
                        <th>Planning Loading</th>
                        <th>Tanggal Tiba Gudang</th>
                        <th>Tanggal Keluar Gudang</th>
                        <th>Lama Digudang</th>
                        <th>Status Gudang</th>
                        <th>SLA Loading</th>
                        <th>Keterangan</th>
                        <th>Lama Waktu Pencarian</th>
                        <th>SLA Dapat Mobil</th>

                        <th>PIC Monitoring</th>
                        <th>Status Kendaraan</th>
                        <th>Monitoring Alert</th>
                        <th>Action Required</th>

                        <th>Act Urutan Bongkar</th>
                        <th>Tanggal Estimasi</th>
                        <th>Tanggal Tiba</th>
                        <th>Lama Perjalanan</th>
                        <th>Overstay</th>
                        <th>SLA Tiba</th>
                        <th>Tanggal Bongkar</th>
                        <th>SLA Bongkar</th>
                        <th>Reason Tiba</th>
                        <th>Reason Bongkar</th>
                        <th>Status Final</th>
                        <th>Alert</th>
                        <th>Action</th>
                        <th>Remarks</th>
                    </tr>
                </thead>

                <tbody>
@foreach($logistik as $r)

        @php
$keluar = (!empty($r->tanggal_keluar_gudang) && $r->tanggal_keluar_gudang != 'mm/dd/yyyy')
    ? strtotime($r->tanggal_keluar_gudang)
    : null;

$tiba = (!empty($r->tanggal_tiba) && $r->tanggal_tiba != 'mm/dd/yyyy')
    ? strtotime($r->tanggal_tiba)
    : null;

$bongkar = (!empty($r->tanggal_bongkar) && $r->tanggal_bongkar != 'mm/dd/yyyy')
    ? strtotime($r->tanggal_bongkar)
    : null;

$leadtime = is_numeric($r->transport_lead_time) ? (int)$r->transport_lead_time : 0;

$estimasi = $keluar ? strtotime("+$leadtime days", $keluar) : null;

$lama_perjalanan = ($keluar && $tiba)
    ? max(0, floor(($tiba - $keluar) / 86400))
    : 0;

$over_bongkar = ($tiba && $bongkar)
    ? max(0, floor(($bongkar - $tiba) / 86400))
    : 0;

$estimasi_show = $estimasi ? date('Y-m-d', $estimasi) : '-';
@endphp

@php
$alert = '-';

if (!empty($estimasi)) {
    $now = strtotime(date('Y-m-d'));
    $selisih = ($estimasi - $now) / 86400;

    if ($selisih <= 2 && $selisih >= 0) {
        $alert = 'WARNING H-2';
    } elseif ($selisih < 0) {
        $alert = 'TERLAMBAT';
    } else {
        $alert = 'AMAN';
    }
}
@endphp

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

                        <td>{{ number_format($r->nilai_muatan) }}</td>
                        <td>{{ number_format($r->biaya_kirim) }}</td>

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

                        <td>{{ $r->pic_monitoring }}</td>
                        <td>{{ $r->status_kendaraan }}</td>
                        <td>{{ $r->monitoring_alert }}</td>
                        <td>{{ $r->action_required }}</td>

                        <td>{{ $r->act_urutan_bongkar }}</td>
                      <td>{{ $estimasi_show }}</td>
                        <td>{{ $r->tanggal_tiba ? date('d-m-Y H:i', strtotime($r->tanggal_tiba)) : '-' }}</td>
                       <td>{{ $lama_perjalanan }}</td>

                        <td>{{ $over_bongkar }}</td>

                        <td>{{ $r->sla_tiba }}</td>
                        <td>{{ $r->tanggal_bongkar ? date('d-m-Y H:i', strtotime($r->tanggal_bongkar)) : '-' }}</td>
                        <td>{{ $r->sla_bongkar }}</td>
                        <td>{{ $r->reason_tiba }}</td>
                        <td>{{ $r->reason_bongkar }}</td>
                        <td>{{ $r->status_akhir }}</td>

                        <td>
    @if($alert == 'WARNING H-2')
        <span style="background:orange;color:white;padding:3px 6px;border-radius:5px;">
            ⚠ {{ $alert }}
        </span>
    @elseif($alert == 'TERLAMBAT')
        <span style="background:red;color:white;padding:3px 6px;border-radius:5px;">
            ⛔ {{ $alert }}
        </span>
    @else
        <span style="background:green;color:white;padding:3px 6px;border-radius:5px;">
            ✔ {{ $alert }}
        </span>
    @endif
</td>
<td>
    <div style="display:flex; gap:5px; justify-content:center;">

        <!-- BUTTON EDIT -->
        <a href="{{ url('/logistik/edit/'.$r->id) }}"
           style="
                background:#3b82f6;
                color:white;
                padding:5px 10px;
                border-radius:6px;
                text-decoration:none;
                font-size:12px;
           ">
           ✏ Edit
        </a>

        <!-- BUTTON HAPUS -->
        <form action="{{ url('/logistik/delete/'.$r->id) }}"
              method="POST"
              onsubmit="return confirm('Yakin hapus data ini?')">

            @csrf
            @method('DELETE')

            <button type="submit"
                    style="
                        background:#ef4444;
                        color:white;
                        border:none;
                        padding:5px 10px;
                        border-radius:6px;
                        cursor:pointer;
                        font-size:12px;
                    ">
                🗑 Hapus
            </button>

        </form>

    </div>
</td>
                        <td>{{ $r->remarks }}</td>

                    </tr>

                    @endforeach

                </tbody>

            </table>

        </div>
    </div>


 <script>
$(document).ready(function () {

    let table = $('#tableLogistik').DataTable({
        scrollX: true,
        pageLength: 10,
        autoWidth: false
    });

    // =========================
    // FILTER CUSTOM
    // =========================
    $.fn.dataTable.ext.search.push(
        function(settings, data, dataIndex) {

            let area  = $('#filterArea').val();
            let month = $('#filterMonth').val();
            let year  = $('#filterYear').val();

            // KOLOM TANGGAL NAIK LOGISTIK
            let tanggal = data[1];

            // KOLOM AREA
            let dataArea = data[7];

            // =====================
            // FILTER AREA
            // =====================
            if(area && dataArea != area){
                return false;
            }

            // =====================
            // VALIDASI DATE
            // =====================
            if(tanggal){

                let date = new Date(tanggal);

                let rowMonth = date.getMonth() + 1;
                let rowYear  = date.getFullYear();

                // FILTER BULAN
                if(month && rowMonth != parseInt(month)){
                    return false;
                }

                // FILTER TAHUN
                if(year && rowYear != parseInt(year)){
                    return false;
                }
            }

            return true;
        }
    );

    // =========================
    // TRIGGER FILTER
    // =========================
    $('#filterArea, #filterMonth, #filterYear').on('change', function () {
        table.draw();
    });

});
</script>

</body>

</html>