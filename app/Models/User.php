<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerifyAccountMail;

class User extends Authenticatable implements MustVerifyEmail
{
    use Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'dist_channel',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    // OPTIONAL: biar aman kalau role kosong
    public function getRoleAttribute($value)
    {
        return $value ?: 'user';
    }

    // Override: pakai Mailable custom kita, bukan notifikasi default Laravel
    public function sendEmailVerificationNotification()
    {
        Mail::to($this->email)->send(new VerifyAccountMail($this));
    }
}