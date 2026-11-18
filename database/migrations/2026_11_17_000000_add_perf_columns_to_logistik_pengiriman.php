<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Performa data_planner:
 *  - selesai_gudang : generated column (otomatis ikut berubah saat tanggal gudang di-update)
 *  - is_rep         : 1 = baris wakil per no_shipment + tujuan (dijaga lewat LogistikPengiriman::refreshRep)
 *  - index untuk filter periode / urutan / lookup shipment / bloker planner
 */
return new class extends Migration
{
    private const SUFFIX = ['', '_2', '_3', '_4', '_5'];

    private function selesaiExpr(): string
    {
        $f = fn($c) => "NULLIF(TRIM($c),'') IS NOT NULL";
        $e = fn($c) => "NULLIF(TRIM($c),'') IS NULL";

        $menggantung = [];
        $adaKeluar   = [];
        foreach (self::SUFFIX as $s) {
            $menggantung[] = '((' . $f("planning_loading$s") . ' OR ' . $f("tanggal_tiba_gudang$s") . ') AND ' . $e("tanggal_keluar_gudang$s") . ')';
            $adaKeluar[]   = $f("tanggal_keluar_gudang$s");
        }

        return 'CASE WHEN (' . implode(' OR ', $adaKeluar) . ') AND NOT (' . implode(' OR ', $menggantung) . ') THEN 1 ELSE 0 END';
    }

    private function hasIndex(string $name): bool
    {
        return count(DB::select("SHOW INDEX FROM logistik_pengiriman WHERE Key_name = ?", [$name])) > 0;
    }

    public function up(): void
    {
        if (!Schema::hasColumn('logistik_pengiriman', 'selesai_gudang')) {
            DB::statement('ALTER TABLE logistik_pengiriman ADD COLUMN selesai_gudang TINYINT AS (' . $this->selesaiExpr() . ') STORED');
        }

        // default 1: baris baru yang lupa di-refresh tetap kelihatan (paling buruk muncul dobel), bukan hilang
        if (!Schema::hasColumn('logistik_pengiriman', 'is_rep')) {
            DB::statement('ALTER TABLE logistik_pengiriman ADD COLUMN is_rep TINYINT NOT NULL DEFAULT 1');
        }

        // isi awal is_rep
        DB::statement('UPDATE logistik_pengiriman SET is_rep = 0');
        DB::statement("UPDATE logistik_pengiriman l
            JOIN (SELECT MIN(id) AS id FROM logistik_pengiriman
                  GROUP BY COALESCE(NULLIF(TRIM(no_shipment),''), CONCAT('id',id)), COALESCE(tujuan,'')) r
              ON r.id = l.id
            SET l.is_rep = 1");

        $indexes = [
            'idx_lp_main'    => 'periode_shipment, is_rep, selesai_gudang, tanggal_shipment, id',
            'idx_lp_planner' => 'planner, is_rep, selesai_gudang, tanggal_shipment',
        ];
        foreach ($indexes as $name => $cols) {
            if (!$this->hasIndex($name)) {
                DB::statement("CREATE INDEX {$name} ON logistik_pengiriman ({$cols})");
            }
        }
    }

    public function down(): void
    {
        foreach (['idx_lp_main', 'idx_lp_planner'] as $name) {
            if ($this->hasIndex($name)) {
                DB::statement("DROP INDEX {$name} ON logistik_pengiriman");
            }
        }
        foreach (['selesai_gudang', 'is_rep'] as $col) {
            if (Schema::hasColumn('logistik_pengiriman', $col)) {
                DB::statement("ALTER TABLE logistik_pengiriman DROP COLUMN {$col}");
            }
        }
    }
};