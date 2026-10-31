<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $table = 'logistik_pengiriman_pasuruan';

    private function hasIndex(string $name): bool
    {
        return !empty(DB::select("SHOW INDEX FROM `{$this->table}` WHERE Key_name = ?", [$name]));
    }

    public function up(): void
    {
        // filter utama Sales: periode + dist_channel
        if (!$this->hasIndex('idx_pasuruan_periode_channel')) {
            Schema::table($this->table, function (Blueprint $t) {
                $t->index(['periode_shipment_pasuruan', 'dist_channel_pasuruan'], 'idx_pasuruan_periode_channel');
            });
        }

        // GROUP BY shipment + tujuan, rincian DO, lockShipments, groupAggPasuruan
        if (!$this->hasIndex('idx_pasuruan_ship_tujuan')) {
            Schema::table($this->table, function (Blueprint $t) {
                $t->index(['no_shipment_pasuruan', 'tujuan_pasuruan'], 'idx_pasuruan_ship_tujuan');
            });
        }
    }

    public function down(): void
    {
        if ($this->hasIndex('idx_pasuruan_periode_channel')) {
            Schema::table($this->table, fn (Blueprint $t) => $t->dropIndex('idx_pasuruan_periode_channel'));
        }
        if ($this->hasIndex('idx_pasuruan_ship_tujuan')) {
            Schema::table($this->table, fn (Blueprint $t) => $t->dropIndex('idx_pasuruan_ship_tujuan'));
        }
    }
};