<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Command contoh bawaan Laravel (boleh dihapus)
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Bangun ulang tabel logistik_gabungan tiap 5 menit
Schedule::command('gabungan:refresh')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/gabungan_refresh.log'));

// Import SAP tiap hari jam 02:00
Schedule::command('sap:import-excel')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/sap_import.log'));