<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule; // 1. Import facade Schedule

// Command contoh bawaan Laravel (boleh dibiarkan atau dihapus)
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 2. Daftarkan Schedule Otomatis Kamu di sini:
Schedule::command('sap:import-excel')
    ->dailyAt('02:00') // Berjalan otomatis setiap hari jam 02:00 pagi
    ->withoutOverlapping() // Mencegah proses bentrok jika import sebelumnya belum selesai
    ->appendOutputTo(storage_path('logs/sap_import.log')); // Catat log hasilnya ke file