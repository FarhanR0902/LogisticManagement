<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function hasIndex(string $name): bool
    {
        return !empty(DB::select("SHOW INDEX FROM logistik_pengiriman WHERE Key_name = ?", [$name]));
    }

    public function up(): void
    {
        if (!$this->hasIndex('idx_lp_shipment')) {
            DB::statement('CREATE INDEX idx_lp_shipment ON logistik_pengiriman (no_shipment(60))');
        }
    }

    public function down(): void
    {
        if ($this->hasIndex('idx_lp_shipment')) {
            DB::statement('DROP INDEX idx_lp_shipment ON logistik_pengiriman');
        }
    }
};