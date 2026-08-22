<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class VerifyAccountMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public string $verifyUrl;

    public function __construct(User $user)
    {
        $this->user = $user;

        // link signed, expired dalam 60 menit
        $this->verifyUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->email),
            ]
        );
    }

    public function build()
    {
        return $this->subject('Verifikasi Akun - Logistik Cimory')
            ->view('emails.verify-account');
    }
}