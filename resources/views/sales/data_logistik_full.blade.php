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
            .filter-box {
                background: #fff;
                padding: 15px;
                border-radius: 12px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
                margin-bottom: 15px;
            }

            /* FORM FILTER */
            .filter-box form {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
                align-items: center;
            }

            /* INPUT & SELECT */
            .filter-box input,
            .filter-box select {
                padding: 10px 12px;
                border: 1px solid #ddd;
                border-radius: 8px;
                font-size: 13px;
                outline: none;
                transition: 0.2s;
            }

            .filter-box input:focus,
            .filter-box select:focus {
                border-color: #3b82f6;
                box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
            }

            /* BUTTON FILTER */
            .filter-box button {
                padding: 10px 14px;
                background: #22c55e;
                color: #fff;
                border: none;
                border-radius: 8px;
                cursor: pointer;
                font-size: 13px;
                transition: 0.2s;
            }

            .filter-box button:hover {
                background: #16a34a;
            }

            .col-small {
                width: 70px !important;
                min-width: 70px !important;
                max-width: 80px !important;
                font-size: 11px;
                text-align: center;
                white-space: nowrap;
            }

            /* RESET LINK */
            .filter-box a {
                padding: 10px 14px;
                background: #ef4444;
                color: #fff;
                border-radius: 8px;
                text-decoration: none;
                font-size: 13px;
                transition: 0.2s;
            }

            .filter-box a:hover {
                background: #dc2626;
            }

            /* ================= IMPORT BOX MODERN ================= */
            .import-box {
                background: linear-gradient(135deg, #ffffff, #f1f5f9);
                padding: 18px;
                border-radius: 14px;
                box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
                margin-bottom: 15px;
                border: 1px solid #e5e7eb;
            }

            /* FORM LAYOUT */
            .import-box form {
                display: flex;
                gap: 12px;
                align-items: center;
                flex-wrap: wrap;
            }

            /* FILE INPUT */
            .import-box input[type="file"] {
                padding: 10px;
                border: 1px dashed #cbd5e1;
                border-radius: 10px;
                background: #f8fafc;
                cursor: pointer;
                font-size: 13px;
                transition: 0.2s;
            }

            .import-box input[type="file"]:hover {
                border-color: #3b82f6;
                background: #eff6ff;
            }

            /* BUTTON IMPORT */
            .import-box button {
                padding: 10px 16px;
                background: linear-gradient(135deg, #3b82f6, #2563eb);
                color: white;
                border: none;
                border-radius: 10px;
                font-size: 13px;
                font-weight: 500;
                cursor: pointer;
                transition: 0.2s;
                box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3);
            }

            .import-box button:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 14px rgba(59, 130, 246, 0.4);
            }

            .import-box button:active {
                transform: scale(0.98);
            }

            .badge {
                padding: 4px 8px;
                border-radius: 6px;
                color: #fff;
                font-size: 11px;
                font-weight: bold;
            }

            .green {
                background: #22c55e;
            }

            .red {
                background: #ef4444;
            }

            .gray {
                background: #6b7280;
            }

            .badge {
                padding: 6px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: bold;
                color: white;
            }

            .badge-mp {
                background: #28a745;
            }

            .badge-cmd {
                background: #007bff;
            }

            .badge-jess {
                background: #ff9800;
            }

            .badge-default {
                background: #6c757d;
            }
            .badge-critical {
    background: #7f1d1d;
    color: white;
}
        </style>
    </head>

    <body>

        @include('template.sidebar')

        <div class="container">

            <h2>📦 DATA LOGISTIK</h2>

           

            <style>
                .archive-form {
                    margin: 20px 0;
                }

                .badge-green {
                    background: #22c55e;
                }

                .badge-red {
                    background: #ef4444;
                }

                .badge-gray {
                    background: #6b7280;
                }

                .badge-orange {
                    background: #f97316;
                }

                .archive-btn {
                    background: linear-gradient(135deg, #2563eb, #1d4ed8);
                    color: white;
                    border: none;
                    padding: 12px 22px;
                    border-radius: 12px;
                    font-size: 14px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: 0.3s ease;
                    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                }

                .archive-btn:hover {
                    transform: translateY(-2px);
                    background: linear-gradient(135deg, #1d4ed8, #1e40af);
                    box-shadow: 0 8px 18px rgba(37, 99, 235, 0.35);
                }

                .archive-btn:active {
                    transform: scale(0.98);
                }

                .badge.gray {
                    background: #9ca3af;
                }

                .badge.blue {
                    background: #3b82f6;
                }

                .badge.yellow {
                    background: #facc15;
                    color: #000;
                }

                .badge.orange {
                    background: #f97316;
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
                        <th class="col-small">Lead Time</th>

                        <th>Planner</th>
                        <th>No Shipment</th>
                        <th>Dist Channel</th>
                        <th>Tujuan</th>
                        <th>Area</th>
                        <th>Ketersediaan Unit</th>
                        <th>Mobil</th>
                        <!-- <th>Perubahan Mobil</th> -->
                        <th>Nilai Muatan</th>
                        <th>Biaya Kirim</th>
                        <th>CR</th>
                        <th>Kategori Ekspedisi</th>
                        <th>Ekspedisi</th>
                        <th>Nama Driver</th>
                        <th>No Pol</th>
                        <th>Tanggal Dapat Unit</th>
                        <th>Tanggal Tiba KACS</th>
                        <th>Status Mobil</th>
                        <th>Keterangan</th>
                        <th>Lama Waktu Pencarian</th>
                        <th>SLA Dapat Mobil</th>
                        <th>Planning Loading</th>
                        <th>Tanggal Keluar KACS</th>
                        <th>Lama Di KACS</th>
                        <th>Status KACS</th>
                        <th>SLA Loading</th>
                        <th>Tanggal Tiba Sentul</th>
                        <!-- <th>Planning Loading 2</th> -->
                        <th>Tanggal Keluar Sentul</th>
                        <th>Lama Di Sentul</th>
                        <th>SLA Loading Sentul</th>
                        <th>Status Sentul</th>

                        <th>Tanggal Tiba CCIE</th>
                        <!-- <th>Planning Loading 3</th> -->
                        <th>Tanggal Keluar CCIE</th>
                        <th>Lama Di CCIE</th>
                        <th>SLA Loading CCIE</th>
                        <th>Status CCIE</th>



                        <th>PIC Monitoring</th>
                        <th>Nama Kapal</th>
                        <th>ETD</th>
                        <th>ETA</th>
                        <th>Monitoring Alert</th>
                        <th>Alert</th>
                      

                        <th class="col-small">Urutan Bongkar</th>
                        <th>total_do_qty_car</th>
                        <th>Act PGI Date</th>

                        <th>Created By</th>
                        <th>ATD</th>
                        <th>ATA</th>
                        <th>Tanggal Estimasi</th>
                        <th>Tanggal Tiba</th>
                        <th>Lama Perjalanan</th>

                        <th>SLA Tiba</th>
                        <th>Tanggal Bongkar</th>
                        <th>Overstay</th>
                        <th>SLA Bongkar</th>
                        <th>Reason Tiba</th>
                        <th>Reason Bongkar</th>
                        <th>Status Akhir</th>
                    
                        <th>Status Alert</th>

                        <th>Remarks</th>
                        <th>Route</th>
                        <th>Shipping Point</th>
                        <th>Pulau</th>
                        <th>Via Kirim</th>


                    </tr>
                </thead>

                <tbody>
                    @php
                    function badgeSLA($sla) {
                    if (empty($sla) || $sla == '-') {
                    return '<span class="badge badge-gray">-</span>';
                    }

                    $slaLower = strtolower($sla);

                    if ($slaLower == 'on time' || $slaLower == 'h+0') {
                    return '<span class="badge badge-green">'.$sla.'</span>';
                    }

                    if (preg_match('/h\+1/i', $sla)) {
                    return '<span class="badge badge-orange">'.$sla.'</span>';
                    }

                    if (preg_match('/h\+/i', $sla)) {
                    return '<span class="badge badge-red">'.$sla.'</span>';
                    }

                    return '<span class="badge badge-gray">'.$sla.'</span>';
                    }
                    @endphp
                    @foreach($logistik as $r)

                    @php

                    // ================= NORMALISASI TANGGAL =================
                    $keluar = (!empty($r->tanggal_keluar_gudang) && $r->tanggal_keluar_gudang != 'mm/dd/yyyy')
                    ? strtotime(date('Y-m-d', strtotime($r->tanggal_keluar_gudang)))
                    : null;

                    $tiba = (!empty($r->tanggal_tiba) && $r->tanggal_tiba != 'mm/dd/yyyy')
                    ? strtotime(date('Y-m-d', strtotime($r->tanggal_tiba)))
                    : null;

                    $bongkar = (!empty($r->tanggal_bongkar) && $r->tanggal_bongkar != 'mm/dd/yyyy')
                    ? strtotime(date('Y-m-d', strtotime($r->tanggal_bongkar)))
                    : null;

                    // ================= LEADTIME =================
                    $leadtime = is_numeric($r->transport_lead_time)
                    ? (int) $r->transport_lead_time
                    : 0;

                    // ================= ESTIMASI TIBA =================
                    $estimasi = $keluar
                    ? strtotime("+$leadtime days", $keluar)
                    : null;

                    $estimasi_show = $estimasi
                    ? date('Y-m-d', $estimasi)
                    : '-';

                    // ================= LAMA PERJALANAN =================
                    $lama_perjalanan = ($keluar && $tiba)
                    ? max(0, ceil(($tiba - $keluar) / 86400))
                    : null;

                    // ================= SLA TIBA =================
                    $sla_tiba = '-';

                    if ($tiba && $estimasi) {
                    $sla_tiba = ($tiba
                    <= $estimasi) ? 'On Time' : 'Delay' ;
                        }

                        //=================OVERSTAY BONGKAR=================// RULE:
                        // 0 hari=ON TIME
                        //>0 hari = DELAY
                    $over_bongkar = null;

                    if ($tiba && $bongkar) {
                    $over_bongkar = max(0, ceil(($bongkar - $tiba) / 86400));
                    }

                    // ================= SLA BONGKAR =================
                    $sla_bongkar = '-';

                    if ($tiba && $bongkar) {
                    $sla_bongkar = ($over_bongkar <= 0)
                        ? 'On Time'
                        : 'Delay' ;
                        }

                        //=================ALERT ESTIMASI=================
                        $alert='-' ;

                        /*
                        |--------------------------------------------------------------------------
                        | SUDAH DELIVER
                        |--------------------------------------------------------------------------
                        */
                        if ($r->sla_bongkar == 'Delay') {

                        $alert = 'DELAY DELIVERY';

                        } elseif ($r->sla_bongkar == 'On Time') {

                        $alert = 'AMAN';

                        }

                        /*
                        |--------------------------------------------------------------------------
                        | BELUM DELIVER
                        |--------------------------------------------------------------------------
                        */
                        elseif ($estimasi) {

                        $today = strtotime(date('Y-m-d'));
                        $selisih = ceil(($estimasi - $today) / 86400);

                        if ($selisih < 0) {

                            $alert='TERLAMBAT' ;

                            } elseif ($selisih <=2) {

                            $alert='WARNING H-2' ;

                            } else {

                            $alert='AMAN' ;

                            }
                            }
                            @endphp

                            <tr>

                            <td>{{ $r->no }}</td>
                            <td>{{ $r->tanggal_naik_logistik ? date('d-m-Y', strtotime($r->tanggal_naik_logistik)) : '-' }}</td>
                            <td>{{ $r->rencana_kirim ? date('d-m-Y', strtotime($r->rencana_kirim)) : '-' }}</td>
                            <td>{{ $r->transport_lead_time }}</td>
                            <td>{{ $r->planner }}</td>
                            <td>{{ $r->no_shipment }}</td>
                            <td>
                                @php
                                $channel = trim($r->dist_channel ?? '');
                                @endphp

                                @if($channel == 'GT - MP')
                                <span class="badge badge-mp">
                                    {{ $channel }}
                                </span>

                                @elseif($channel == 'GT - CMD')
                                <span class="badge badge-cmd">
                                    {{ $channel }}
                                </span>

                                @elseif($channel == 'GT - JESS')
                                <span class="badge badge-jess">
                                    {{ $channel }}
                                </span>

                                @else
                                <span class="badge badge-default">
                                    {{ $channel ?: '-' }}
                                </span>
                                @endif
                            </td>
                            <td>{{ $r->tujuan }}</td>
                            <td>{{ $r->area }}</td>
                            <td>
                                @php
                                $unit = $r->ketersediaan_unit ?? '-';
                                @endphp

                                @if(empty($unit) || $unit == '-')
                                <span class="badge gray">-</span>

                                @elseif(strtoupper($unit) == 'SUDAH DAPAT')
                                <span class="badge green">SUDAH DAPAT</span>

                                @elseif(strtoupper($unit) == 'BELUM DAPAT')
                                <span class="badge red">BELUM DAPAT</span>

                                @else
                                <span class="badge orange">
                                    {{ $unit }}
                                </span>
                                @endif
                            </td>
                            <td>{{ $r->mobil }}</td>
                            <!-- <td>{{ $r->perubahan_mobil }}</td> -->

                            <td>Rp {{ number_format($r->nilai_muatan, 0, ',', '.') }}</td>
                            <td>Rp {{ number_format($r->biaya_kirim, 0, ',', '.') }}</td>

                            <td>{{ $r->cr }}</td>
                            <td>
                                @php
                                $kategori = $r->kategori_ekspedisi ?? '-';
                                @endphp

                                @if(empty($kategori) || $kategori == '-')
                                <span class="badge gray">-</span>

                                @elseif(strtolower($kategori) == 'kontrak')
                                <span class="badge yellow">Kontrak</span>

                                @elseif(strtolower($kategori) == 'oncall')
                                <span class="badge blue">Oncall</span>

                                @else
                                <span class="badge orange">
                                    {{ $kategori }}
                                </span>
                                @endif
                            </td>
                            <td>{{ $r->ekpedisi }}</td>
                            <td>{{ $r->nama_driver }}</td>
                            <!-- <td>{{ $r->no_pol }}</td> -->
                            <td>{{ $r->no_pol ?? '-' }}</td>

                            <td>
                                {{ $r->tanggal_dpt_unit ? date('d-m-Y H:i', strtotime($r->tanggal_dpt_unit)) : '-' }}
                            </td>

                            <td>
                                {{ $r->tanggal_tiba_gudang ? date('d-m-Y H:i', strtotime($r->tanggal_tiba_gudang)) : '-' }}
                            </td>

                            {{-- STATUS --}}
                            <td>
                                @if(empty($r->status_pengiriman) || $r->status_pengiriman == '-')
                                <span class="badge gray">-</span>

                                @elseif($r->status_pengiriman == 'On Time')
                                <span class="badge green">On Time</span>

                                @else
                                <span class="badge red">
                                    {{ $r->status_pengiriman }}
                                </span>
                                @endif
                            </td>

                            {{-- KETERANGAN --}}
                            <td>{{ $r->keterangan ?? '-' }}</td>

                            {{-- LAMA WAKTU PENCARIAN --}}
                            <td>{{ $r->lama_waktu_pencarian ?? '-' }}</td>

                            {{-- SLA DAPAT MOBIL --}}
                            <td>
                                @php
                                $sla = $r->sla_dapat_mobil ?? null;
                                @endphp

                                @if(empty($sla))
                                <span class="badge gray">-</span>

                                @elseif(strtolower($sla) == 'on time' || $sla == 'H+0')
                                <span class="badge green">{{ $sla }}</span>

                                @elseif(preg_match('/h\+1/i', $sla))
                                <span class="badge orange">{{ $sla }}</span>

                                @elseif(preg_match('/h\+/i', $sla))
                                <span class="badge red">{{ $sla }}</span>

                                @else
                                <span class="badge gray">{{ $sla }}</span>
                                @endif
                            </td>

                            <td>{{ $r->planning_loading ? date('d-m-Y H:i', strtotime($r->planning_loading)) : '-' }}</td>

                            <td>{{ $r->tanggal_keluar_gudang ? date('d-m-Y H:i', strtotime($r->tanggal_keluar_gudang)) : '-' }}</td>
                            <td>{{ $r->lama_digudang }} Hari</td>
                            <td>
                                @php
                                $status = $r->status ?? '-';
                                @endphp

                                @if(empty($status) || $status == '-')
                                <span class="badge gray">-</span>

                                @elseif(strtolower($status) == 'on time')
                                <span class="badge green">On Time</span>

                                @elseif(strtolower($status) == 'delay')
                                <span class="badge red">Delay</span>

                                @elseif(strtolower($status) == 'on site')
                                <span class="badge orange">On Site</span>

                                @else
                                <span class="badge gray">
                                    {{ $status }}
                                </span>
                                @endif
                            </td>
                            <td>{!! badgeSLA($r->sla_loading) !!}</td>
                            {{-- ================= GUDANG 2 ================= --}}
                            <td>
                                {{ $r->tanggal_tiba_gudang_2 ? date('d-m-Y H:i', strtotime($r->tanggal_tiba_gudang_2)) : '-' }}
                            </td>

                            <td>
                                {{ $r->tanggal_keluar_gudang_2 ? date('d-m-Y H:i', strtotime($r->tanggal_keluar_gudang_2)) : '-' }}
                            </td>

                            <td>{{ $r->lama_digudang_2 ?? '-' }} Hari</td>
                            <td>{!! badgeSLA($r->sla_loading_2) !!}</td>
                            <td>
                                @php
                                $status2 = $r->status_gudang_2 ?? null;
                                @endphp

                                @if(empty($status2))
                                <span class="badge gray">-</span>

                                @elseif(strtolower($status2) == 'on time')
                                <span class="badge green">On Time</span>

                                @elseif(strtolower($status2) == 'delay')
                                <span class="badge red">Delay</span>

                                @elseif(strtolower($status2) == 'on site')
                                <span class="badge orange">On Site</span>

                                @else
                                <span class="badge gray">{{ $status2 }}</span>
                                @endif
                            </td>



                            {{-- ================= GUDANG 3 ================= --}}
                            <td>
                                {{ $r->tanggal_tiba_gudang_3 ? date('d-m-Y H:i', strtotime($r->tanggal_tiba_gudang_3)) : '-' }}
                            </td>

                            <td>
                                {{ $r->tanggal_keluar_gudang_3 ? date('d-m-Y H:i', strtotime($r->tanggal_keluar_gudang_3)) : '-' }}
                            </td>

                            <td>{{ $r->lama_digudang_3 ?? '-' }} Hari</td>
                            <td>{!! badgeSLA($r->sla_loading_3) !!}</td>
                            <td>
                                @php
                                $status3 = $r->status_gudang_3 ?? null;
                                @endphp

                                @if(empty($status3))
                                <span class="badge gray">-</span>

                                @elseif(strtolower($status3) == 'on time')
                                <span class="badge green">On Time</span>

                                @elseif(strtolower($status3) == 'delay')
                                <span class="badge red">Delay</span>

                                @elseif(strtolower($status3) == 'on site')
                                <span class="badge orange">On Site</span>

                                @else
                                <span class="badge gray">{{ $status3 }}</span>
                                @endif
                            </td>




                            {{-- ================= SLA DAPAT MOBIL ================= --}}


                            <td>{{ $r->pic_monitoring }}</td>
                            <td>{{ $r->nama_kapal }}</td>
                            <td>{{ $r->etd }}</td>
                            <td>{{ $r->eta }}</td>
                            <td>{{ $r->status_kendaraan }}</td>
                                   <td>

                                @if($alert == 'WARNING H-2')
                                <span style="background:orange;color:white;padding:3px 6px;border-radius:5px;">
                                    ⚠ WARNING H-2
                                </span>

                                @elseif($alert == 'TERLAMBAT')
                                <span style="background:red;color:white;padding:3px 6px;border-radius:5px;">
                                    ⛔ TERLAMBAT
                                </span>

                                @elseif($alert == 'DELAY DELIVERY')
                                <span style="background:#dc2626;color:white;padding:3px 6px;border-radius:5px;">
                                    🚚 DELAY DELIVERY
                                </span>

                                @else
                                <span style="background:green;color:white;padding:3px 6px;border-radius:5px;">
                                    ✔ AMAN
                                </span>
                                @endif

                            </td>
                     


                            <td>{{ $r->act_urutan_bongkar }}</td>
                            <td>{{ $r->total_do_qty_car }}</td>
                            <td>{{ $r->act_pgi_date ? date('d-m-Y H:i', strtotime($r->act_pgi_date)) : '-' }}</td>


                            <td>{{ $r->created_by ?? '-' }}</td>
                            <td>{{ $r->atd }}</td>
                            <td>{{ $r->ata }}</td>
                            <td>{{ $estimasi_show ? date('d-m-Y', strtotime($estimasi_show)) : '-' }}</td>
                            <td>{{ $r->tanggal_tiba ? date('d-m-Y H:i', strtotime($r->tanggal_tiba)) : '-' }}</td>

                            <td>
                                {{ !empty($lama_perjalanan) ? $lama_perjalanan . ' Hari' : '-' }}
                            </td>


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
                            <td>{{ $r->tanggal_bongkar ? date('d-m-Y H:i', strtotime($r->tanggal_bongkar)) : '-' }}</td>
                            <td>{{ $over_bongkar }} Hari</td>
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
                            <td>{{ $r->reason_tiba }}</td>
                            <td>{{ $r->reason_bongkar }}</td>
                            <!-- <td>

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

                            </td> -->
<td>
    @php
        $alert = strtolower(trim($r->monitoring_alert ?? ''));
    @endphp

    @if($alert == 'aman')
        <span class="badge green">✅ Aman</span>

    @elseif(str_contains($alert, 'pembongkaran'))
        <span class="badge orange">⚠ Delay Pembongkaran</span>

    @elseif(str_contains($alert, 'perjalanan'))
        <span class="badge red">🚚 Delay Perjalanan</span>

    @elseif(str_contains($alert, 'total'))
        <span class="badge red">🚨 Delay Total</span>

    @else
        <span class="badge gray">
            {{ $r->monitoring_alert ?? '-' }}
        </span>
    @endif
</td>
                     
                     <td>
    @php
        $statusAkhir = strtolower(trim($r->status_akhir ?? ''));
    @endphp

    @if($statusAkhir == 'delivered on time')
        <span class="badge green">
            ✅ Delivered On Time
        </span>

    @elseif($statusAkhir == 'delivered delay')
        <span class="badge orange">
            ⚠ Delivered Delay
        </span>

    @elseif($statusAkhir == 'delay')
        <span class="badge red">
            🚚 Delay
        </span>

    @elseif($statusAkhir == 'critical delay')
        <span class="badge red">
            🚨 Critical Delay
        </span>

    @else
        <span class="badge gray">
            {{ $r->status_akhir ?? '-' }}
        </span>
    @endif
</td>

                            <td>{{ $r->remarks }}</td>
                            <td>{{ $r->route }}</td>
                         <td>
    {{ $r->route ? explode('-', trim($r->route))[0] : '-' }}
</td>
                            <td>{{ $r->pulau }}</td>
                            <td>{{ $r->via_kirim }}</td>







                            </tr>

                            @endforeach

                </tbody>

            </table>

        </div>
        </div>


        <!-- <script>
            $(document).ready(function() {

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

                        let area = $('#filterArea').val();
                        let month = $('#filterMonth').val();
                        let year = $('#filterYear').val();

                        // KOLOM TANGGAL NAIK LOGISTIK
                        let tanggal = data[1];

                        // KOLOM AREA
                        let dataArea = data[7];

                        // =====================
                        // FILTER AREA
                        // =====================
                        if (area && dataArea != area) {
                            return false;
                        }

                        // =====================
                        // VALIDASI DATE
                        // =====================
                        if (tanggal) {

                            let date = new Date(tanggal);

                            let rowMonth = date.getMonth() + 1;
                            let rowYear = date.getFullYear();

                            // FILTER BULAN
                            if (month && rowMonth != parseInt(month)) {
                                return false;
                            }

                            // FILTER TAHUN
                            if (year && rowYear != parseInt(year)) {
                                return false;
                            }
                        }

                        return true;
                    }
                );

                // =========================
                // TRIGGER FILTER
                // =========================
                $('#filterArea, #filterMonth, #filterYear').on('change', function() {
                    table.draw();
                });

            });
        </script> -->

       <script>
$(document).ready(function() {

    let table = $('#tableLogistik').DataTable({
        scrollX: true,
        pageLength: 10,
        autoWidth: false,
        
        // =========================================================
        // DRAW CALLBACK: Otomatis Hitung CR Berdasarkan Muatan Gabungan
        // =========================================================
        "drawCallback": function(settings) {
            let api = this.api();
            let shipmentGroups = {}; // Tempat menampung akumulasi TOTAL NILAI MUATAN

            // -----------------------------------------------------
            // PASS 1: Hitung Total Nilai Muatan per No Shipment
            // -----------------------------------------------------
            api.rows({ page: 'current', search: 'applied' }).every(function(rowIdx, tableLoop, rowLoop) {
                let data = this.data();
                let node = this.node();

                // 1. Ambil No Shipment dari data array DataTables (Index 5)
                let noShipment = data[5] ? data[5].trim() : '';

                // 2. DETEKSI OTOMATIS KOLOM RP
                let rawNilaiMuatan = "";
                let cellsRp = [];

                // Cari semua td di baris ini yang mengandung teks "Rp"
                $(node).find('td').each(function() {
                    if ($(this).text().includes('Rp')) {
                        cellsRp.push($(this));
                    }
                });

                // Ambil text Nilai Muatan (Kolom Rp Pertama)
                if (cellsRp.length >= 2) {
                    rawNilaiMuatan = cellsRp[0].text().trim(); 
                } else {
                    rawNilaiMuatan = $(node).find('td').eq(9).text().trim(); // Fallback index 9
                }

                // 3. BERSIHKAN ANGKA MUATAN
                let cleanNilaiMuatan = rawNilaiMuatan.replace(/[^0-9]/g, "");
                let nilaiMuatan = parseFloat(cleanNilaiMuatan) || 0;

                // Akumulasikan Nilai Muatan jika No Shipment valid
                if (noShipment && noShipment !== '-' && noShipment !== '') {
                    if (!shipmentGroups[noShipment]) {
                        shipmentGroups[noShipment] = {
                            totalMuatan: 0
                        };
                    }
                    shipmentGroups[noShipment].totalMuatan += nilaiMuatan;
                }
            });

            // Helper internal untuk memformat angka kembali ke Rupiah saat render teks td
            function formatKeRupiahText(angka) {
                return 'Rp ' + String(angka).replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            }

            // -----------------------------------------------------
            // PASS 2: Cetak Hasil & Hitung Cost Ratio Per Baris
            // -----------------------------------------------------
            api.rows({ page: 'current', search: 'applied' }).every(function(rowIdx, tableLoop, rowLoop) {
                let data = this.data();
                let node = this.node();
                let noShipment = data[5] ? data[5].trim() : '';

                let cellsRp = [];
                $(node).find('td').each(function() {
                    if ($(this).text().includes('Rp')) {
                        cellsRp.push($(this));
                    }
                });

                let cellMuatan = cellsRp.length >= 2 ? cellsRp[0] : $(node).find('td').eq(9);
                let cellBiaya = cellsRp.length >= 2 ? cellsRp[1] : $(node).find('td').eq(10);
                let cellCR = cellsRp.length >= 2 ? cellsRp[1].next('td') : $(node).find('td').eq(11);

                // Ambil Biaya Kirim asli di baris ini apa adanya (tidak digabung/tidak diubah)
                let biayaMurni = parseFloat(cellBiaya.text().trim().replace(/[^0-9]/g, "")) || 0;
                let costRatio = 0;

                if (noShipment && noShipment !== '-' && noShipment !== '') {
                    // Ambil hasil akumulasi total muatan dari PASS 1
                    let totalMuatanGabungan = shipmentGroups[noShipment].totalMuatan;

                    // Tampilkan total nilai muatan gabungan di kolom Nilai Muatan
                    cellMuatan.text(formatKeRupiahText(totalMuatanGabungan));

                    // Rumus baru: Biaya Kirim baris ini / Total Muatan Gabungan
                    if (totalMuatanGabungan > 0) {
                        costRatio = (biayaMurni / totalMuatanGabungan) * 100;
                    }

                    // Cetak ke kolom CR
                    if (costRatio > 0) {
                        cellCR.html('<span class="text-primary font-weight-bold" style="color: #0056b3; font-weight: bold;">' + costRatio.toFixed(4) + '%</span>');
                    } else {
                        // Jika baris ini biaya kirimnya 0 atau kosong, maka CR otomatis 0.0000%
                        cellCR.html('<span class="text-muted" style="color: #9e9e9e; font-size: 11px;">0.0000%</span>');
                    }

                } else {
                    // KONDISI JIKA TIDAK ADA NO SHIPMENT (DATA NORMAL)
                    let nilaiMuatanMurni = parseFloat(cellMuatan.text().trim().replace(/[^0-9]/g, "")) || 0;
                    
                    if (nilaiMuatanMurni > 0) {
                        costRatio = (biayaMurni / nilaiMuatanMurni) * 100;
                    }

                    if (costRatio > 0) {
                        cellCR.html('<span class="text-primary font-weight-bold" style="color: #0056b3; font-weight: bold;">' + costRatio.toFixed(4) + '%</span>');
                    } else {
                        cellCR.html('<span class="text-muted">-</span>');
                    }
                }
            });
        }
    });

    // ==========================================
    // FILTER CUSTOM (AREA, BULAN, TAHUN)
    // ==========================================
    $.fn.dataTable.ext.search.push(
        function(settings, data, dataIndex) {
            let filterArea = $('#filterArea').val() ? $('#filterArea').val().trim() : '';
            let filterMonth = $('#filterMonth').val();
            let filterYear = $('#filterYear').val();

            let tanggal = data[1] ? data[1].trim() : ''; 
            let dataArea = data[7] ? data[7].trim() : ''; 

            if (filterArea && dataArea !== filterArea) return false;

            if (filterMonth || filterYear) {
                if (!tanggal || tanggal === '-') return false;
                let rowMonth, rowYear;
                if (tanggal.includes('-') || tanggal.includes('/')) {
                    let separator = tanggal.includes('-') ? '-' : '/';
                    let parts = tanggal.split(separator);
                    if (parts[0].length === 4) {
                        rowYear = parseInt(parts[0]);
                        rowMonth = parseInt(parts[1]);
                    } else {
                        rowYear = parseInt(parts[2]);
                        rowMonth = parseInt(parts[1]);
                    }
                } else {
                    let date = new Date(tanggal);
                    if (isNaN(date.getTime())) return false;
                    rowMonth = date.getMonth() + 1;
                    rowYear = date.getFullYear();
                }
                if (filterMonth && rowMonth !== parseInt(filterMonth)) return false;
                if (filterYear && rowYear !== parseInt(filterYear)) return false;
            }
            return true;
        }
    );

    $('#filterArea, #filterMonth, #filterYear').on('change', function() {
        table.draw();
    });

});
</script>   

    </body>

    </html>