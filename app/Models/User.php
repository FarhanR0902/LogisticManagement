<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $table = 'users';

    protected $fillable = [
        'name',
        'username',
        'password',
        'role',
        'dist_channel',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    // OPTIONAL: biar aman kalau role kosong
    public function getRoleAttribute($value)
    {
        return $value ?: 'user';
    }
}
