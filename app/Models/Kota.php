<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kota extends Model
{
    use HasFactory;

    // Paksa Eloquent menggunakan nama tabel 'kota'
    protected $table = 'kota'; 

    protected $fillable = [
        'kode_gudang',
        'no_so',
        'no_do',
        'create_on',
        'tgl_do',
        'tanggal_kirim',
        'customer_id',
        'alasan_pending',
        'alamat_customer',
        'area_kecil',
        'area_besar',
        'route',
        'tujuan',
        'kode_barang',
        'nama_barang',
        'qty_prdk',
        'no_pol',
        'nama_driver',
        'no_shipment',
        'EKPEDISI',
        'jenis_mobil',
        'jenis_tonase',
        'Kubikasi',
        'tonase',
        'pengiriman_optimal',
        'jumlah_toko',
        'pic_toko',
        'pic_driver',
        'waktu_pengiriman',
    ];
}