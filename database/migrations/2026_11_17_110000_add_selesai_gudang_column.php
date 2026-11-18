<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * OPSIONAL (tahap 2): kolom selesai_gudang untuk Data Planner.
 * Pasang kalau data sudah besar (periode terbesar puluhan ribu baris ke atas).
 * Index dasar ada di migration 2026_11_17_100000_add_planner_indexes.php.
 * Setelah migrate, jalankan: php artisan cache:clear
 *
 * 1) Kolom selesai_gudang (GENERATED STORED) = hasil dari selesaiGudangSql() yang lama.
 *    Dihitung MySQL sekali saat data ditulis, jadi bisa di-ORDER BY / WHERE lewat index
 *    (sebelumnya CASE + belasan TRIM dihitung ulang untuk setiap baris di setiap request).
 *    JANGAN di-insert / di-update manual, dan jangan masukkan ke $fillable.
 *
 * 2) Dua index yang memakai kolom itu.
 *
 * Catatan:
 * - Butuh MySQL 5.7+ / MariaDB 10.2+.
 * - ADD COLUMN STORED membangun ulang tabel: backup dulu, jalankan di luar jam kerja.
 * - Kalau muncul "Specified key was too long", kolom (no_shipment / tujuan / planner)
 *   bertipe TEXT atau varchar terlalu panjang. Ubah ke varchar(100-255) dulu.
 */
return new class extends Migration
{
    private const SUFFIX = ['', '_2', '_3', '_4', '_5'];   // KACS, SENTUL, CCIE, P5, FIFATEX

    /** Harus identik dengan PlannerController::selesaiGudangSql() yang lama */
    private function selesaiExpr(): string
    {
        $f = fn($c) => "NULLIF(TRIM({$c}), '') IS NOT NULL";
        $e = fn($c) => "NULLIF(TRIM({$c}), '') IS NULL";

        $menggantung = [];
        foreach (self::SUFFIX as $s) {
            $menggantung[] = '((' . $f("planning_loading{$s}")
                . ' OR ' . $f("tanggal_tiba_gudang{$s}")
                . ') AND ' . $e("tanggal_keluar_gudang{$s}") . ')';
        }

        $adaKeluar = '(' . implode(' OR ', array_map(
            fn($s) => $f("tanggal_keluar_gudang{$s}"), self::SUFFIX
        )) . ')';

        return "CASE WHEN {$adaKeluar} AND NOT (" . implode(' OR ', $menggantung) . ") THEN 1 ELSE 0 END";
    }

    public function up(): void
    {
        if (!Schema::hasColumn('logistik_pengiriman', 'selesai_gudang')) {
            DB::statement(
                'ALTER TABLE logistik_pengiriman '
                . 'ADD COLUMN selesai_gudang TINYINT(1) GENERATED ALWAYS AS (' . $this->selesaiExpr() . ') STORED'
            );
        }

        Schema::table('logistik_pengiriman', function (Blueprint $t) {
            // daftar utama dataAjax: periode -> belum selesai dulu -> tanggal shipment
            $t->index(['periode_shipment', 'selesai_gudang', 'tanggal_shipment'], 'idx_periode_selesai');
            // kunci urutan shipment (blokerMap / pemblokUrutan)
            $t->index(['selesai_gudang', 'planner', 'tanggal_shipment'], 'idx_selesai_planner');
        });
    }

    public function down(): void
    {
        Schema::table('logistik_pengiriman', function (Blueprint $t) {
            $t->dropIndex('idx_periode_selesai');
            $t->dropIndex('idx_selesai_planner');
        });

        if (Schema::hasColumn('logistik_pengiriman', 'selesai_gudang')) {
            DB::statement('ALTER TABLE logistik_pengiriman DROP COLUMN selesai_gudang');
        }
    }
};