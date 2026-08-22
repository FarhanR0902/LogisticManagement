<?php



namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TarifPengiriman extends Model
{
    protected $table = 'tarif_pengiriman';

    protected $fillable = [
        'servc_agent',
        'ekpedisi',
        'sh',
        'mobil',
        'routew',
        'route',
        'biaya_kirim',
        'unit',
        'per',
        'uom',
        'd',
        'tx',
        'e',
        's_1',
        's_2',
        'valid_from',
        'valid_to',
    ];

    public $timestamps = false;
}