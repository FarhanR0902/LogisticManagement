<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TujuanFilter extends Model
{
    use HasFactory;

    protected $table = 'tujuanfillterr';

    public $timestamps = false;

    protected $fillable = [
        'Div',
        'customer_id',
        'tujuan',
        'dist_channel',
        'pulau',
        'area',
        'Planner',
        'Monitoring',
        'biaya_kuli',
        'transport_lead_time',
    ];

    public function setAreaAttribute($value): void
    {
        $this->attributes['area'] = strtoupper(str_replace(' ', '_', trim($value)));
    }

    public function setTujuanAttribute($value): void
    {
        $this->attributes['tujuan'] = trim($value);
    }

    public static function areaFor(string $tujuan): ?string
    {
        return static::where('tujuan', trim($tujuan))->value('area');
    }
}