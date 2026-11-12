<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\LogistikImport;

class ImportSapLogistik extends Command
{
    protected $signature = 'sap:import-excel';
    protected $description = 'Otomatis import data laporan SAP HANA ke database logistik';

    public function handle()
    {
        $this->info('Memulai import data SAP HANA...');

        // Tentukan path file Excel/CSV laporan SAP yang di-export otomatis ke folder storage
        // Contoh: storage_path('app/imports/laporan_sap.xlsx')
        $filePath = storage_path('app/imports/laporan_sap.xlsx');

        if (!file_exists($filePath)) {
            $this->error('File laporan SAP tidak ditemukan di: ' . $filePath);
            return;
        }

        try {
            $import = new LogistikImport();
            Excel::import($import, $filePath);

            $this->info('Import berhasil!');
            $this->info('Data Masuk (Inserted): ' . $import->getInsertedCount());
            $this->info('Data Diperbarui (Updated): ' . $import->getUpdatedCount());
            $this->info('Data Gagal (Failed): ' . $import->getFailedCount());
        } catch (\Exception $e) {
            $this->error('Terjadi kesalahan saat import: ' . $e->getMessage());
        }
    }
}