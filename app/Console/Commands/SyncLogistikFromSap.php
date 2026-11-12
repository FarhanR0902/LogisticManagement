<?php

namespace App\Console\Commands;

use App\Imports\LogistikImport;
use App\Services\SapShipmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncLogistikFromSap extends Command
{
    protected $signature = 'logistik:sync-sap
                            {--days=7 : tarik shipment N hari terakhir}
                            {--dry : hanya tarik & tampilkan contoh, TIDAK menyimpan}';

    protected $description = 'Tarik data shipment dari SAP HANA ke logistik_pengiriman';

    public function handle(SapShipmentService $sap): int
    {
        $days = max(1, (int) $this->option('days'));
        $this->info("Menarik data SAP ({$days} hari terakhir), mode: " . config('sap.mode'));

        try {
            $rows = $sap->fetch($days);
        } catch (\Throwable $e) {
            logger()->error('SAP SYNC: gagal ambil data', ['error' => $e->getMessage()]);
            $this->error('Gagal konek/query SAP: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info('Baris dari SAP: ' . count($rows));

        if (empty($rows)) {
            $this->warn('Tidak ada data dari SAP.');
            return self::SUCCESS;
        }

        if ($this->option('dry')) {
            $this->warn('DRY RUN: tidak ada yang disimpan. Contoh 1 baris:');
            $this->line(json_encode($rows[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->line('Kolom: ' . implode(', ', array_keys($rows[0])));
            return self::SUCCESS;
        }

        $import = new LogistikImport();

        try {
            DB::transaction(function () use ($import, $rows) {
                $import->collection(collect($rows)->map(fn($r) => collect($r)));
                $import->finalize();
            });
        } catch (\Throwable $e) {
            logger()->error('SAP SYNC: gagal simpan', ['error' => $e->getMessage(), 'line' => $e->getLine()]);
            $this->error('Gagal simpan (rollback): ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info(sprintf(
            'Selesai. Baru: %d | Update: %d | Skip: %d | Gagal: %d',
            $import->getInsertedCount(),
            $import->getUpdatedCount(),
            $import->getSkippedCount(),
            $import->getFailedCount()
        ));

        if ($import->getFailedCount() > 0) {
            logger()->warning('SAP SYNC: ada baris gagal', $import->getFailedList());
            $this->warn('Ada baris gagal, lihat storage/logs/laravel.log');
        }

        return self::SUCCESS;
    }
}