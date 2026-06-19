<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up(): void
{
    Schema::create('logistik_pengiriman', function (Blueprint $table) {
        $table->id();

        $table->string('no')->nullable();
        $table->date('tanggal_naik_logistik')->nullable();
        $table->date('rencana_kirim')->nullable();
        $table->string('transport_lead_time')->nullable();
        $table->string('planner')->nullable();
        $table->string('no_shipment')->nullable();
        $table->string('tujuan')->nullable();
        $table->string('area')->nullable();

        $table->string('ketersediaan_unit')->nullable();
        $table->string('mobil')->nullable();
        $table->string('perubahan_mobil')->nullable();

        $table->decimal('nilai_muatan', 15, 2)->nullable();
        $table->decimal('biaya_kirim', 15, 2)->nullable();
        $table->string('cr')->nullable();

        $table->string('kategori_ekspedisi')->nullable();
        $table->string('ekspedisi')->nullable();
        $table->string('nama_driver')->nullable();
        $table->string('no_pol')->nullable();

        $table->string('status_pengiriman')->nullable();
        $table->date('tanggal_dpt_unit')->nullable();
        $table->date('planning_loading')->nullable();
        $table->date('tanggal_tiba_gudang')->nullable();
        $table->date('tanggal_keluar_gudang')->nullable();

        $table->integer('lama_digudang')->nullable();
        $table->string('status_gudang')->nullable();

        $table->string('sla_loading')->nullable();
        $table->text('keterangan')->nullable();

        $table->string('lama_waktu_pencarian')->nullable();
        $table->string('sla_dapat_mobil')->nullable();
        $table->string('pic_monitoring')->nullable();

        $table->string('status_kendaraan')->nullable();
        $table->string('monitoring_alert')->nullable();
        $table->string('action_required')->nullable();

        $table->string('act_urutan_bongkar')->nullable();

        $table->date('tanggal_tiba')->nullable();
        $table->string('lama_perjalanan')->nullable();
        $table->string('sla_tiba')->nullable();

        $table->date('tanggal_bongkar')->nullable();
        $table->integer('overstay_days')->nullable();
        $table->string('sla_bongkar')->nullable();

        $table->string('status_akhir')->nullable();

        $table->timestamps();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logistik_pengiriman');
    }
};
