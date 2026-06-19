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

        .d-none {
            display: none !important;
        }

        .filter-box {
            display: flex;
            gap: 10px;
            flex-wrap: nowrap;
            align-items: center;
            margin-bottom: 15px;
            overflow-x: auto;
        }

        .filter-box form {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: nowrap;
        }

        .filter-box select {
            min-width: 180px;
            white-space: nowrap;
        }

        .filter-box select {
            min-width: 180px;
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

        th.editable {
            background: linear-gradient(135deg, #2563eb, #1e40af);
        }

        td {
            border: 1px solid #e5e7eb;
            padding: 5px;
        }

        input,
        select {
            width: 100%;
            padding: 5px;
            font-size: 12px;
            border: 1px solid #d1d5db;
            border-radius: 5px;
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

        .green {
            background: #22c55e;
        }

        .red {
            background: #ef4444;
        }

        .orange {
            background: #f59e0b;
        }

        .card {
            overflow-x: auto;
        }

        #tableMonitoring {
            width: 100% !important;
        }

        #tableMonitoring th {
            text-align: center;
            vertical-align: middle;
        }

        #tableMonitoring td {
            vertical-align: middle;
            white-space: nowrap;
        }

        #tableMonitoring input[type=text] {
            min-width: 120px;
        }

        #tableMonitoring input[type=number] {
            width: 70px;
        }

        #tableMonitoring input[type=datetime-local] {
            width: 170px;
        }

        .select2-container {
            min-width: 160px !important;
        }

        .save-btn {
            min-width: 70px;
        }

        #tableMonitoring th,
        #tableMonitoring td {
            text-align: left;
            vertical-align: middle;
            white-space: nowrap;
        }

        #tableMonitoring input[type="text"] {
            min-width: 120px;
        }

        #tableMonitoring input[type="number"] {
            width: 70px;
        }

        #tableMonitoring input[type="datetime-local"] {
            width: 170px;
        }

        #tableMonitoring .save-btn {
            width: 70px;
        }

        #tableMonitoring .badge {
            display: inline-block;
            min-width: 70px;
            text-align: center;
        }

        .select2-container {
            min-width: 140px !important;
        }

        .select2-selection {
            height: 32px !important;
        }

        .dataTables_wrapper {
            overflow-x: auto;
        }

        .card {
            overflow-x: auto;
        }

        /* =========================
   NOTIF PANEL + TOAST SYSTEM
========================= */

        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            width: 350px;
            z-index: 99999;
        }

        .toast {
            background: #111827;
            color: #fff;
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            animation: slideIn .3s ease;
            border-left: 5px solid #f59e0b;
            font-size: 12px;
        }

        .toast strong {
            display: block;
            margin-bottom: 5px;
            color: #fbbf24;
        }

        @keyframes slideIn {
            from {
                transform: translateX(120%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* PANEL LIST NOTIF */
        .notif-panel {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 320px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .2);
            z-index: 99998;
            overflow: hidden;
            font-size: 12px;
        }

        .notif-header {
            background: #111827;
            color: #fff;
            padding: 10px;
            font-weight: bold;
        }

        .notif-body {
            max-height: 250px;
            overflow-y: auto;
        }

        .notif-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }

        .notif-search {
            width: 100%;
            padding: 8px;
            border: none;
            border-bottom: 1px solid #ddd;
            outline: none;
        }
    </style>

</head>

<body>
    <!-- <div id="notifBox" style="
    position:fixed;
    top:20px;
    right:20px;
    width:350px;
    background:#111827;
    color:#fff;
    padding:15px;
    border-radius:12px;
    box-shadow:0 10px 25px rgba(0,0,0,.3);
    display:none;
    z-index:9999;
">
    <h4 style="margin-bottom:10px;">⚠ Estimasi Tiba H-1</h4>
    <div id="notifContent" style="font-size:13px; line-height:1.5;"></div>

    <button onclick="$('#notifBox').fadeOut()"
        style="margin-top:10px;padding:6px 10px;border:none;background:#ef4444;color:#fff;border-radius:6px;">
        Tutup
    </button>
</div> -->
    <!-- TOAST POPUP -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- PANEL LIST NOTIF (SEARCHABLE) -->

    <div class="container">

        <div class="title">🚚 DATA MONITORING</div>

        <a href="{{ route('monitoring.datalogistik') }}"
            style="padding:8px 12px;background:#ef4444;color:#fff;border-radius:8px;text-decoration:none;">
            Reset Filter
        </a>

        {{-- FILTER --}}
        <div class="filter-box">
            <form method="GET" action="{{ route('spvmonitoring.datalogistik') }}">
                <select class="searchable" name="pic_monitoring" onchange="this.form.submit()">
                    <option value="">PIC Monitoring</option>

                    @foreach($picList as $pic)
                    <option value="{{ $pic }}"
                        {{ request('pic_monitoring') == $pic ? 'selected' : '' }}>
                        {{ $pic }}
                    </option>
                    @endforeach
                </select>

                <select class="searchable" name="area" onchange="this.form.submit()">
                    <option value="">AREA</option>
                    @foreach($areaList as $area)
                    <option value="{{ $area }}" {{ request('area')==$area?'selected':'' }}>
                        {{ $area }}
                    </option>
                    @endforeach
                </select>

                <select class="searchable" name="bulan" onchange="this.form.submit()">
                    <option value="">BULAN</option>
                    @for($i=1;$i<=12;$i++)
                        <option value="{{ $i }}" {{ request('bulan')==$i?'selected':'' }}>{{ $i }}</option>
                        @endfor
                </select>

                <select class="searchable" name="tahun" onchange="this.form.submit()">
                    <option value="">TAHUN</option>
                    @for($i=2023;$i<=2030;$i++)
                        <option value="{{ $i }}" {{ request('tahun')==$i?'selected':'' }}>{{ $i }}</option>
                        @endfor
                </select>

            </form>
        </div>

        <div class="card">

            <!-- 🔥 TARUH DI SINI -->

            <div id="notifBox" style="
position:fixed;
top:20px;
right:20px;
width:350px;
background:#111827;
color:#fff;
padding:15px;
border-radius:12px;
box-shadow:0 10px 25px rgba(0,0,0,.3);
display:none;
z-index:9999;
">
                <h4 style="margin-bottom:10px;">⚠ Estimasi Tiba H-1</h4>
                <div id="notifContent"></div>
            </div>
            <table id="tableMonitoring" class="display nowrap">
                <thead>
                    <tr>

                        <!-- EDITABLE -->
                        <th>No</th>
                        <th class="editable">PIC</th>
                        <th class="editable">Status</th>
                        <th class="editable">Action</th>

                        <!-- READONLY -->
                        <th>No Shipment</th>
                        <th>Tujuan</th>
                        <th>Area</th>
                        <th>Ekspedisi</th>

                        <!-- EDITABLE -->
                        <th class="editable">Urutan</th>

                        <!-- READONLY -->
                        <th>Keluar</th>
                        <th>Estimasi</th>

                        <!-- EDITABLE -->
                        <th class="editable">Tiba</th>

                        <!-- READONLY -->
                        <th>Lama</th>
                        <th>SLA Tiba</th>

                        <!-- EDITABLE -->
                        <th class="editable">Bongkar</th>

                        <!-- READONLY -->
                        <th>Overstay</th>
                        <th>SLA Bongkar</th>

                        <!-- EDITABLE -->
                        <th class="editable">Alert</th>

                        <!-- READONLY -->
                        <th>Status Akhir</th>

                        <!-- EDITABLE -->
                        <th class="editable">Reason Tiba</th>
                        <th class="editable">Reason Bongkar</th>
                        <th class="editable">Remarks</th>
                      
                        <th class="editable">Act PGI Date</th>

                        <th class="editable">Created By</th>


                        <th class="editable">Total DO Qty</th>

                        <!-- READONLY -->
                        <th>Action</th>

                    </tr>
                </thead>

                <tbody>
                    @foreach($logistik as $r)

                    @php

                    $keluar = $r->tanggal_keluar_gudang
                    ? strtotime(date('Y-m-d', strtotime($r->tanggal_keluar_gudang)))
                    : null;

                    $tiba = $r->tanggal_tiba
                    ? strtotime(date('Y-m-d', strtotime($r->tanggal_tiba)))
                    : null;

                    $bongkar = $r->tanggal_bongkar
                    ? strtotime(date('Y-m-d', strtotime($r->tanggal_bongkar)))
                    : null;

                    $leadtime = (int)$r->transport_lead_time;

                    $estimasi = $keluar
                    ? strtotime("+{$leadtime} days", $keluar)
                    : null;


                    /*
                    |--------------------------------------------------------------------------
                    | SLA TIBA
                    |--------------------------------------------------------------------------
                    */

                    $lama = '-';
                    $sla_tiba = '-';

                    if($tiba){

                    $lama = floor(
                    ($tiba - $keluar) / 86400
                    );

                    $selisihTiba = floor(
                    ($tiba - $estimasi) / 86400
                    );

                    if($selisihTiba <= 0){

                        $sla_tiba='On Time' ;

                        }else{

                        $sla_tiba='H+' . $selisihTiba;

                        }

                        }


                        /*
                        |--------------------------------------------------------------------------
                        | SLA BONGKAR
                        |--------------------------------------------------------------------------
                        */

                        $overstay='-' ;
                        $sla_bongkar='-' ;

                        if($tiba && $bongkar){

                        $overstay=floor(
                        ($bongkar - $tiba) / 86400
                        );

                        if($overstay <=0){

                        $sla_bongkar='On Time' ;

                        }else{

                        $sla_bongkar='H+' . $overstay;

                        }

                        }

                        @endphp

                        <tr data-id="{{ $r->id }}">

                        <td>{{ $r->no }}</td>
                        <td>
                            <input type="text" name="pic_monitoring"
                                value="{{ $r->pic_monitoring }}">
                        </td>

                        <td>
                            <input type="text" name="status_kendaraan"
                                value="{{ $r->status_kendaraan }}">
                        </td>

                        <td>
                            <input type="text" name="action_required"
                                value="{{ $r->action_required }}">
                        </td>

                        <td>{{ $r->no_shipment }}</td>

                        <td>{{ $r->tujuan }}</td>

                        <td>{{ $r->area }}</td>

                        <td>{{ $r->ekpedisi }}</td>

                        <td>
                            <input type="number"
                                name="act_urutan_bongkar"
                                value="{{ $r->act_urutan_bongkar }}">
                        </td>

                        <td>
                            {{ $r->tanggal_keluar_gudang
            ? date('d-m-Y H:i', strtotime($r->tanggal_keluar_gudang))
            : '-' }}
                        </td>

                        <td class="estimasi-tiba"
                            data-shipment="{{ $r->no_shipment }}"
                            data-tujuan="{{ $r->tujuan }}"
                            data-estimasi="{{ $estimasi ? date('Y-m-d',$estimasi) : '' }}">

                            {{ $estimasi
        ? date('d-m-Y', $estimasi)
        : '-' }}

                        </td>
                        <td>
                            <input type="datetime-local"
                                name="tanggal_tiba"
                                value="{{ $r->tanggal_tiba
                ? date('Y-m-d\TH:i', strtotime($r->tanggal_tiba))
                : '' }}">
                        </td>

                        <td>{{ $lama }}</td>

                        <td>

                            @if($sla_tiba == '-')

                            -

                            @elseif($sla_tiba == 'On Time')

                            <span class="badge green">
                                On Time
                            </span>

                            @else

                            <span class="badge red">
                                {{ $sla_tiba }}
                            </span>

                            @endif

                        </td>

                        <td>
                            <input type="datetime-local"
                                name="tanggal_bongkar"
                                value="{{ $r->tanggal_bongkar
                ? date('Y-m-d\TH:i', strtotime($r->tanggal_bongkar))
                : '' }}">
                        </td>

                        <td>{{ $overstay }}</td>

                        <td>

                            @if($sla_bongkar == '-')

                            -

                            @elseif($sla_bongkar == 'On Time')

                            <span class="badge green">
                                On Time
                            </span>

                            @else

                            <span class="badge red">
                                {{ $sla_bongkar }}
                            </span>

                            @endif

                        </td>

                        <td>
                            <input type="text"
                                name="monitoring_alert"
                                value="{{ $r->monitoring_alert }}">
                        </td>
                        <td>

                            @if($r->sla_bongkar == 'On Time')

                            <span class="badge green">
                                Delivered (On Time)
                            </span>

                            @elseif($r->sla_bongkar == 'Delay')

                            <span class="badge red">
                                Delivered (Delay)
                            </span>

                            @elseif($r->tanggal_tiba && !$r->tanggal_bongkar)

                            <span class="badge orange">
                                On Site
                            </span>

                            @else

                            <span class="badge red">
                                On Track
                            </span>

                            @endif

                        </td>

                        <td>
                            <select name="reason_tiba"
                                class="reason-select searchable-select">

                                <option value="">Pilih Reason Tiba</option>

                                @foreach($akurasiTiba as $item)
                                <option value="{{ $item }}"
                                    {{ $r->reason_tiba == $item ? 'selected' : '' }}>
                                    {{ $item }}
                                </option>
                                @endforeach

                            </select>

                            <span class="d-none txt">
                                {{ $r->reason_tiba }}
                            </span>
                        </td>

                        <td>
                            <select name="reason_bongkar"
                                class="reason-select searchable-select">

                                <option value="">Pilih Reason Bongkar</option>

                                @foreach($akurasiBongkar as $item)
                                <option value="{{ $item }}"
                                    {{ $r->reason_bongkar == $item ? 'selected' : '' }}>
                                    {{ $item }}
                                </option>
                                @endforeach

                            </select>

                            <span class="d-none txt">
                                {{ $r->reason_bongkar }}
                            </span>
                        </td>
                        <td>
                            <input type="text"
                                name="remarks"
                                value="{{ $r->remarks }}">
                        </td>

                        <td>
                            <input type="date"
                                name="act_pgi_date"
                                value="{{ $r->act_pgi_date ? date('Y-m-d', strtotime($r->act_pgi_date)) : '' }}">
                        </td>


                        <td>
                            <input type="text"
                                name="created_by"
                                value="{{ $r->created_by }}">
                        </td>


                        <td>
                            <input type="number"
                                name="total_do_qty_car"
                                value="{{ $r->total_do_qty_car }}">
                        </td>

                        <td>
                            <button
                                type="button"
                                class="save-btn"
                                onclick="saveRow({{ $r->id }})">
                                SAVE
                            </button>
                        </td>

                        </tr>


                        @endforeach
                </tbody>
            </table>

            <script>
                let table;

                $(document).ready(function() {

                    table = $('#tableMonitoring').DataTable({

                        scrollX: true,
                        scrollCollapse: true,
                        autoWidth: false,
                        fixedHeader: true,
                        pageLength: 10,
                        orderCellsTop: true,
                        ordering: true,

                        columnDefs: [

                            {
                                width: "120px",
                                targets: 0
                            },
                            {
                                width: "120px",
                                targets: 1
                            },
                            {
                                width: "120px",
                                targets: 2
                            },
                            {
                                width: "140px",
                                targets: 3
                            },
                            {
                                width: "350px",
                                targets: 4
                            },
                            {
                                width: "150px",
                                targets: 5
                            },
                            {
                                width: "120px",
                                targets: 6
                            },
                            {
                                width: "80px",
                                targets: 7
                            },
                            {
                                width: "140px",
                                targets: 8
                            },
                            {
                                width: "120px",
                                targets: 9
                            },
                            {
                                width: "180px",
                                targets: 10
                            },
                            {
                                width: "70px",
                                targets: 11
                            },
                            {
                                width: "90px",
                                targets: 12
                            },
                            {
                                width: "180px",
                                targets: 13
                            },
                            {
                                width: "80px",
                                targets: 14
                            },
                            {
                                width: "100px",
                                targets: 15
                            },
                            {
                                width: "100px",
                                targets: 16
                            },
                            {
                                width: "100px",
                                targets: 17
                            },
                            {
                                width: "180px",
                                targets: 18
                            },
                            {
                                width: "180px",
                                targets: 19
                            },
                            {
                                width: "150px",
                                targets: 20
                            },
                            {
                                width: "80px",
                                targets: 21
                            }

                        ]

                    });

                    $('.searchable-select').select2({
                        width: '100%',
                        placeholder: 'Pilih Reason',
                        allowClear: true
                    });

                    table.columns.adjust().draw();



                    let notif = [];

                    $('.estimasi-tiba').each(function() {

                        let shipment = $(this).data('shipment');
                        let tujuan = $(this).data('tujuan');
                        let estimasi = $(this).data('estimasi');

                        if (!estimasi) return;

                        let row = $(this).closest('tr');

                        // ✅ INI KUNCI UTAMA: kalau sudah diinput, STOP TOTAL
                        let tanggalTiba = row.find('[name="tanggal_tiba"]').val();
                        let tanggalBongkar = row.find('[name="tanggal_bongkar"]').val();

                        // kalau sudah ada progress → jangan notif lagi
                        if (tanggalBongkar) return;

                        let estDate = new Date(estimasi + "T00:00:00");
                        let today = new Date();

                        estDate.setHours(0, 0, 0, 0);
                        today.setHours(0, 0, 0, 0);

                        let diff = Math.floor((estDate - today) / (1000 * 60 * 60 * 24));

                        // tetap pakai rule kamu (≤ 30 hari)
                        if (diff <= 30 && diff >= 0) {
                            notif.push({
                                shipment,
                                tujuan,
                                estimasi,
                                diff
                            });
                        }

                    });


                    // =========================
                    // TOAST POPUP (KANAN ATAS)
                    // =========================
                    if (notif.length > 0) {

                        notif.forEach(item => {

                            let color = "#f59e0b";
                            let label = "⚠ FOLLOW UP";

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
               
                <small>Estimasi: ${item.estimasi} (H-${item.diff})</small>
            </div>
        `;

                            $('#toastContainer').append(html);

                        });

                        setTimeout(() => {
                            $('.toast').fadeOut(300, function() {
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

                    $('#notifSearch').on('keyup', function() {
                        renderNotifList($(this).val());
                    });


                    // if (notif.length > 0) {

                    //     let html = "";

                    //     notif.forEach((item, i) => {
                    //         html += `
                    //             <div style="margin-bottom:10px;padding-bottom:10px;border-bottom:1px solid #374151;">
                    //                 ${i+1}. ${item.replace(/\n/g, "<br>")}
                    //             </div>
                    //         `;
                    //     });

                    //     $('#notifContent').html(html);
                    //     $('#notifBox').fadeIn();

                    // }
                    // setTimeout(() => {
                    //     $('#notifBox').fadeOut();
                    // }, 10000);

                });

                function saveRow(id) {

                    let row = $('tr[data-id="' + id + '"]');

                    let data = {

                        pic_monitoring: row.find('[name="pic_monitoring"]').val(),
                        status_kendaraan: row.find('[name="status_kendaraan"]').val(),
                        action_required: row.find('[name="action_required"]').val(),
                        act_urutan_bongkar: row.find('[name="act_urutan_bongkar"]').val(),
                        tanggal_tiba: row.find('[name="tanggal_tiba"]').val(),
                        tanggal_bongkar: row.find('[name="tanggal_bongkar"]').val(),
                        monitoring_alert: row.find('[name="monitoring_alert"]').val(),
                        reason_tiba: row.find('[name="reason_tiba"]').val(),
                        reason_bongkar: row.find('[name="reason_bongkar"]').val(),
                        remarks: row.find('[name="remarks"]').val(),
                        act_pgi_date: row.find('[name="act_pgi_date"]').val(),
created_by: row.find('[name="created_by"]').val(),
total_do_qty_car: row.find('[name="total_do_qty_car"]').val()

                    };

                    $.ajax({

                        url: '/monitoring/update/' + id,

                        type: 'POST',

                        data: {
                            _token: '{{ csrf_token() }}',
                            _method: 'PUT',
                            ...data
                        },

                        beforeSend: function() {

                            row.find('.save-btn')
                                .prop('disabled', true)
                                .text('Saving...');

                        },

                        success: function() {

                            row.find('.save-btn')
                                .prop('disabled', false)
                                .text('SAVE');

                            alert('✅ Data berhasil disimpan');

                            location.reload();

                        },

                        error: function(xhr) {

                            row.find('.save-btn')
                                .prop('disabled', false)
                                .text('SAVE');

                            console.log(xhr.responseText);

                            alert('❌ Gagal menyimpan data');

                        }

                    });

                }
            </script>

</html>