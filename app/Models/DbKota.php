<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DbKota extends Model
{
    protected $table = 'dbkota';
    public $timestamps = false; // sesuaikan kalau ternyata ada created_at/updated_at
}