// database/migrations/xxxx_add_perf_columns_to_logistik_pengiriman.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Http\Controllers\Planner\PlannerController;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('logistik_pengiriman', function (Blueprint $t) {
            // default 1: kalau ada import yang lupa sync, hasilnya duplikat (kelihatan), bukan baris hilang
            $t->tinyInteger('is_rep')->default(1)->after('id');
        });

        $expr = (new PlannerController)->selesaiGudangExpr();
        DB::statement("ALTER TABLE logistik_pengiriman ADD COLUMN selesai_gudang TINYINT AS ({$expr}) STORED");

        // backfill is_rep
        DB::statement("UPDATE logistik_pengiriman SET is_rep = 0 WHERE TRIM(COALESCE(no_shipment,'')) <> ''");
        DB::statement("
            UPDATE logistik_pengiriman l
            JOIN (SELECT MIN(id) AS id FROM logistik_pengiriman
                  WHERE TRIM(COALESCE(no_shipment,'')) <> ''
                  GROUP BY TRIM(no_shipment), COALESCE(tujuan,'')) r ON r.id = l.id
            SET l.is_rep = 1
        ");

        Schema::table('logistik_pengiriman', function (Blueprint $t) {
            $t->index(['periode_shipment', 'is_rep', 'selesai_gudang', 'tanggal_shipment'], 'idx_pl_list');
            $t->index(['is_rep', 'selesai_gudang', 'planner', 'tanggal_shipment'], 'idx_pl_bloker');
            $t->index(['no_shipment', 'tujuan'], 'idx_pl_group');
            $t->index('planner', 'idx_pl_planner');
        });
    }

    public function down(): void
    {
        Schema::table('logistik_pengiriman', function (Blueprint $t) {
            $t->dropIndex('idx_pl_list');
            $t->dropIndex('idx_pl_bloker');
            $t->dropIndex('idx_pl_group');
            $t->dropIndex('idx_pl_planner');
            $t->dropColumn(['is_rep', 'selesai_gudang']);
        });
    }
};