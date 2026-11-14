<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $table = 'logistik_pengiriman';

    /** nama_index => kolom (prefix untuk kolom teks panjang) */
    private function indexes(): array
    {
        return [
            'idx_lp_periode'     => 'periode_shipment(20)',
            'idx_lp_no_shipment' => 'no_shipment(40), tujuan(60)',
            'idx_lp_tgl_ship'    => 'tanggal_shipment',
            'idx_lp_estimasi'    => 'estimasi_tiba',
            'idx_lp_tiba'        => 'tanggal_tiba',
            'idx_lp_bongkar'     => 'tanggal_bongkar',
            'idx_lp_area'        => 'area(40)',
            'idx_lp_pic'         => 'pic_monitoring(30)',
        ];
    }

    public function up(): void
    {
        foreach ($this->indexes() as $name => $cols) {
            try {
                DB::statement("CREATE INDEX {$name} ON {$this->table} ({$cols})");
            } catch (\Throwable $e) {
                // index sudah ada / kolom tidak cocok: lewati, lanjut yang lain
                logger()->warning("Index {$name} dilewati: " . $e->getMessage());
            }
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->indexes()) as $name) {
            try {
                DB::statement("DROP INDEX {$name} ON {$this->table}");
            } catch (\Throwable $e) {
            }
        }
    }
};