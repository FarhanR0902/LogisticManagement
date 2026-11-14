<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logistik_pengiriman', function (Blueprint $t) {
            $t->index('no_shipment', 'idx_lp_no_shipment');
            $t->index('periode_shipment', 'idx_lp_periode');
            $t->index('tanggal_shipment', 'idx_lp_tgl_shipment');
            $t->index('area', 'idx_lp_area');
            $t->index(['planner', 'tanggal_shipment'], 'idx_lp_planner_tgl');
            $t->index(['periode_shipment', 'no_shipment', 'tujuan'], 'idx_lp_periode_ship_tujuan');
        });

        Schema::table('logistik_pengiriman_pasuruan', function (Blueprint $t) {
            $t->index('no_shipment_pasuruan', 'idx_lpp_no_shipment');
            $t->index('periode_shipment_pasuruan', 'idx_lpp_periode');
            $t->index('area_pasuruan', 'idx_lpp_area');
        });
    }

    public function down(): void
    {
        Schema::table('logistik_pengiriman', function (Blueprint $t) {
            $t->dropIndex('idx_lp_no_shipment');
            $t->dropIndex('idx_lp_periode');
            $t->dropIndex('idx_lp_tgl_shipment');
            $t->dropIndex('idx_lp_area');
            $t->dropIndex('idx_lp_planner_tgl');
            $t->dropIndex('idx_lp_periode_ship_tujuan');
        });

        Schema::table('logistik_pengiriman_pasuruan', function (Blueprint $t) {
            $t->dropIndex('idx_lpp_no_shipment');
            $t->dropIndex('idx_lpp_periode');
            $t->dropIndex('idx_lpp_area');
        });
    }
};