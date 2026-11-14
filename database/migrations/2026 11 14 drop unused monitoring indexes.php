<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Index di kolom yang SERING DITULIS saat SAVE (tiap simpan mengubah semua DO dalam shipment)
// dan tidak bisa dipakai query filter (IS NULL / TRIM / DATE() tidak memakai index).
return new class extends Migration
{
    private array $drop = ['idx_lp_estimasi', 'idx_lp_tiba', 'idx_lp_bongkar', 'idx_lp_pic'];

    public function up(): void
    {
        foreach ($this->drop as $name) {
            try { DB::statement("DROP INDEX {$name} ON logistik_pengiriman"); } catch (\Throwable $e) {}
        }
    }

    public function down(): void {}
};