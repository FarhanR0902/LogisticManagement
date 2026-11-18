<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAHAP 1: index untuk halaman Data Planner (tidak mengubah kolom / isi data).
 *
 * Kalau muncul "Specified key was too long", kolom no_shipment / tujuan / planner
 * bertipe TEXT atau varchar terlalu panjang. Ubah ke varchar(100-255) dulu.
 * Kalau muncul "Duplicate key name", index itu sudah ada: hapus baris index tersebut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logistik_pengiriman', function (Blueprint $t) {
            // filter periode + tanggal shipment (kalender & filter tanggal)
            $t->index(['periode_shipment', 'tanggal_shipment'], 'idx_periode_tgl');
            // daftar planner per periode + filter planner
            $t->index(['periode_shipment', 'planner'], 'idx_periode_planner');
            // total per no_shipment + tujuan (plannerGroupAgg, grup shipment)
            $t->index(['no_shipment', 'tujuan'], 'idx_ship_tujuan');
            // kunci urutan shipment per planner
            $t->index(['planner', 'tanggal_shipment'], 'idx_planner_tgl');
        });
    }

    public function down(): void
    {
        Schema::table('logistik_pengiriman', function (Blueprint $t) {
            $t->dropIndex('idx_periode_tgl');
            $t->dropIndex('idx_periode_planner');
            $t->dropIndex('idx_ship_tujuan');
            $t->dropIndex('idx_planner_tgl');
        });
    }
};