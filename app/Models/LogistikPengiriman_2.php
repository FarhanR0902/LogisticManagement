<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogistikPengiriman2 extends Model
{
    protected $table = 'logistik_pengiriman_2';

    protected $primaryKey = 'id';

    const CREATED_AT = 'created_at_pasuruan';
    const UPDATED_AT = 'updated_at_pasuruan';

    protected $fillable = [

        'no_pasuruan',
        'tanggal_naik_logistik_pasuruan',
        'rencana_kirim_pasuruan',
        'transport_lead_time_pasuruan',
        'planner_pasuruan',
        'no_shipment_pasuruan',
        'dist_channel_pasuruan',
        'tujuan_pasuruan',
        'area_pasuruan',
        'ketersediaan_unit_pasuruan',
        'mobil_pasuruan',
        'perubahan_mobil',
        'nilai_muatan_pasuruan',
        'biaya_kirim_pasuruan',
        'cr_pasuruan',
        'kategori_ekspedisi_pasuruan',
        'ekpedisi_pasuruan',
        'nama_kapal_pasuruan',
        'etd_pasuruan',
        'eta_pasuruan',
        'atd_pasuruan',
        'ata_pasuruan',
        'nama_driver_pasuruan',
        'no_pol_pasuruan',
        'status_pengiriman_pasuruan',
        'tanggal_dpt_unit_pasuruan',
        'planning_loading_pasuruan',
        'tanggal_tiba_gudang_pasuruan',
        'tanggal_keluar_gudang_pasuruan',
        'lama_digudang_pasuruan',
        'status_gudang_pasuruan',
        'sla_loading_pasuruan',
        'keterangan_pasuruan',
        'lama_waktu_pencarian_pasuruan',
        'sla_dapat_mobil_pasuruan',
        'pic_monitoring_pasuruan',
        'status_kendaraan_pasuruan',
        'monitoring_alert_pasuruan',
        'action_required_pasuruan',
        'act_urutan_bongkar_pasuruan',
        'tanggal_tiba_pasuruan',
        'lama_perjalanan_pasuruan',
        'sla_tiba_pasuruan',
        'tanggal_bongkar_pasuruan',
        'overstay_days_pasuruan',
        'sla_bongkar_pasuruan',
        'reason_tiba_pasuruan',
        'reason_bongkar_pasuruan',
        'status_akhir_pasuruan',
        'remarks_pasuruan',
        'tanggal_tiba_estimasi_pasuruan',
        'transportasi_pasuruan',
        'transport_laut_pasuruan',
        'cust_grp_5_desc_pasuruan',
        'cust_grp_3_desc_pasuruan',
        'ship_no_pasuruan',
        'cust_desc_pasuruan',
        'addt_text_4_pasuruan',
        'service_agent_pasuruan',
        'urutan_bongkar_pasuruan',
        'act_pgi_date_pasuruan',
        'created_by_pasuruan',
        'total_do_qty_car_pasuruan',
        'route_pasuruan',
        'shipping_point_pasuruan',
        'pulau_pasuruan',
        'via_kirim_pasuruan',
        'estimasi_tiba_pasuruan',
        'create_tgl_pasuruan',

    ];
}