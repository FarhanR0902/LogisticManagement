<?php

namespace App\Console\Commands;

use App\Support\PicMonitoring;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Taruh di app/Console/Commands/FillPicMonitoring.php
 *
 *   php artisan monitoring:fill-pic            -> isi yang masih kosong saja
 *   php artisan monitoring:fill-pic --force    -> timpa semua sesuai aturan
 */
class FillPicMonitoring extends Command
{
    protected $signature = 'monitoring:fill-pic {--force : timpa juga pic_monitoring yang sudah terisi}';

    protected $description = 'Isi pic_monitoring otomatis dari no_shipment dan planner';

    public function handle(): int
    {
        $n = PicMonitoring::fillMissing((bool) $this->option('force'));

        // daftar PIC di dropdown filter di-cache 1 jam
        Cache::forget('monitoring_pic_list');

        $this->info("{$n} baris diupdate.");

        return self::SUCCESS;
    }
}