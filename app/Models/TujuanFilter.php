<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TujuanFilter extends Model
{
    use HasFactory;

    protected $fillable = [
        'tujuan',
        'area',
        'dist_channel',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Normalisasi otomatis setiap kali "area" diisi/diupdate,
     * supaya selalu konsisten format sama seperti object koordinatAreaPasuruan di JS
     * (uppercase, spasi diganti underscore).
     */
    public function setAreaAttribute($value): void
    {
        $this->attributes['area'] = strtoupper(str_replace(' ', '_', trim($value)));
    }

    public function setTujuanAttribute($value): void
    {
        $this->attributes['tujuan'] = trim($value);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Cari area untuk 1 nama tujuan tertentu. Dipakai kalau mau lookup manual,
     * atau untuk backfill data shipment lama yang area-nya masih kosong/salah.
     */
    public static function areaFor(string $tujuan): ?string
    {
        return static::where('tujuan', trim($tujuan))->value('area');
    }
}
