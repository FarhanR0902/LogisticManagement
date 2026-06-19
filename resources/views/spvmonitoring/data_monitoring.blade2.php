@include('template.sidebar')

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>DATA MONITORING</title>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

    <style>
        body {
            background: #f3f4f6;
            font-family: 'Segoe UI';
            margin: 0;
        }

        .container {
            width: calc(100% - 250px);
            margin-left: 250px;
            padding: 20px;
        }

        .title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .card {
            background: #fff;
            padding: 15px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            overflow: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            white-space: nowrap;
        }

        th {
            background: #111827;
            color: #fff;
            padding: 10px;
            text-align: center;
        }

        td {
            border: 1px solid #e5e7eb;
            padding: 5px;
        }

        input, select {
            width: 100%;
            padding: 5px;
            font-size: 12px;
        }

        .save-btn {
            background: #22c55e;
            border: none;
            color: #fff;
            padding: 7px 12px;
            border-radius: 6px;
        }

        .badge {
            padding: 5px 8px;
            border-radius: 20px;
            color: #fff;
            font-size: 11px;
        }

        .green { background: #22c55e; }
        .red { background: #ef4444; }
        .orange { background: #f59e0b; }

        /* =========================
           TOAST NOTIF
        ========================= */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 99999;
            width: 320px;
        }

        .toast {
            background: #111827;
            color: #fff;
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
            animation: slideIn 0.3s ease;
            font-size: 13px;
            border-left: 4px solid #f59e0b;
        }

        .toast strong {
            display:block;
            margin-bottom:5px;
            color:#fbbf24;
        }

        @keyframes slideIn {
            from { transform: translateX(120%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>
</head>

<body>

<!-- TOAST CONTAINER -->
<div class="toast-container" id="toastContainer"></div>

<div class="container">

    <div class="title">🚚 DATA MONITORING</div>

    <div class="card">

        <table id="tableMonitoring" class="display nowrap">
            <thead>
                <tr>
                    <th>PIC</th>
                    <th>Status</th>
                    <th>No Shipment</th>
                    <th>Tujuan</th>
                    <th>Estimasi</th>
                </tr>
            </thead>

            <tbody>
            @foreach($logistik as $r)

            @php
                $keluar = $r->tanggal_keluar_gudang
                    ? strtotime(date('Y-m-d', strtotime($r->tanggal_keluar_gudang)))
                    : null;

                $leadtime = (int)$r->transport_lead_time;

                $estimasi = $keluar
                    ? strtotime("+{$leadtime} days", $keluar)
                    : null;
            @endphp

            <tr>
                <td>{{ $r->pic_monitoring }}</td>
                <td>{{ $r->status_kendaraan }}</td>
                <td class="estimasi-tiba"
                    data-shipment="{{ $r->no_shipment }}"
                    data-tujuan="{{ $r->tujuan }}"
                    data-estimasi="{{ $estimasi ? date('Y-m-d',$estimasi) : '' }}">
                    {{ $r->no_shipment }}
                </td>
                <td>{{ $r->tujuan }}</td>
                <td>{{ $estimasi ? date('d-m-Y',$estimasi) : '-' }}</td>
            </tr>

            @endforeach
            </tbody>
        </table>

    </div>
</div>

<script>

$(document).ready(function () {

    $('#tableMonitoring').DataTable({
        scrollX: true
    });
let notif = [];

// =========================
// CEK ESTIMASI (H-30)
// =========================
$('.estimasi-tiba').each(function () {

    let shipment = $(this).data('shipment');
    let tujuan   = $(this).data('tujuan');
    let estimasi = $(this).data('estimasi');

    if (!estimasi) return;

    let estDate = new Date(estimasi + "T00:00:00");
    let today   = new Date();

    estDate.setHours(0,0,0,0);
    today.setHours(0,0,0,0);

    let diff = Math.floor((estDate - today) / (1000 * 60 * 60 * 24));

    // MASUKIN SEMUA <= 30 HARI
    if (diff <= 30 && diff >= 0) {
        notif.push({
            shipment: shipment,
            tujuan: tujuan,
            estimasi: estimasi,
            diff: diff
        });
    }

});


// =========================
// TOAST POPUP (KANAN ATAS)
// =========================
if (notif.length > 0) {

    notif.forEach(item => {

        let color = "#f59e0b";
        let label = "⚠ WARNING";

        if (item.diff <= 7) {
            color = "#ef4444";
            label = "🚨 URGENT";
        }

        if (item.diff === 1) {
            label = "🔥 H-1 ALERT";
        }

        let html = `
            <div class="toast" style="border-left:5px solid ${color}">
                <strong>${label}</strong>
                No Shipment: ${item.shipment}<br>
                Tujuan: ${item.tujuan}<br>
                <small>Estimasi: ${item.estimasi} (H-${item.diff})</small>
            </div>
        `;

        $('#toastContainer').append(html);

    });

    setTimeout(() => {
        $('.toast').fadeOut(300, function () {
            $(this).remove();
        });
    }, 8000);
}


// =========================
// PANEL LIST (SEARCHABLE)
// =========================
function renderNotifList(filter = "") {

    let html = "";

    notif
    .filter(i => i.shipment.toLowerCase().includes(filter.toLowerCase()))
    .forEach(i => {

        html += `
            <div class="notif-item">
                <b>${i.shipment}</b><br>
                Tujuan: ${i.tujuan}<br>
                Estimasi: ${i.estimasi}<br>
                <small>H-${i.diff}</small>
            </div>
        `;

    });

    $('#notifList').html(html);
}

renderNotifList();

$('#notifSearch').on('keyup', function () {
    renderNotifList($(this).val());
});
</script>

</body>
</html>            