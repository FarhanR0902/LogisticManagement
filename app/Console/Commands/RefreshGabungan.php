<?php

namespace App\Console\Commands;

use App\Http\Controllers\Manager\GabunganController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RefreshGabungan extends Command
{
    protected $signature   = 'gabungan:refresh';
    protected $description = 'Bangun ulang tabel logistik_gabungan (Jakarta + Pasuruan)';

    public function handle(): int
    {
        $t0 = microtime(true);
        $q  = app(GabunganController::class)->srcUnion();

        DB::statement('DROP TABLE IF EXISTS logistik_gabungan_new');
        DB::statement('CREATE TABLE logistik_gabungan_new AS ' . $q->toSql(), $q->getBindings());

        // panjang prefix mengikuti tipe kolom
        $info = DB::table('information_schema.columns')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'logistik_gabungan_new')
            ->get(['COLUMN_NAME as n', 'CHARACTER_MAXIMUM_LENGTH as l'])
            ->keyBy('n');

        $k = function (string $c, int $p = 60) use ($info) {
            $l = $info[$c]->l ?? null;
            return ($l !== null && $l > $p) ? "`{$c}`({$p})" : "`{$c}`";
        };

        DB::statement('ALTER TABLE logistik_gabungan_new
            ADD PRIMARY KEY (id),
            ADD INDEX i_periode (' . $k('periode_shipment') . '),
            ADD INDEX i_sumber_periode (' . $k('sumber') . ', ' . $k('periode_shipment') . '),
            ADD INDEX i_area (' . $k('area') . '),
            ADD INDEX i_group (' . $k('sumber') . ', ' . $k('no_shipment') . ', ' . $k('tujuan') . '),
            ADD INDEX i_tgl_shipment (' . $k('tanggal_shipment') . ')');

        if (Schema::hasTable('logistik_gabungan')) {
            DB::statement('RENAME TABLE logistik_gabungan TO logistik_gabungan_old, logistik_gabungan_new TO logistik_gabungan');
            DB::statement('DROP TABLE logistik_gabungan_old');
        } else {
            DB::statement('RENAME TABLE logistik_gabungan_new TO logistik_gabungan');
        }

        $n = DB::table('logistik_gabungan')->count();
        $this->info("logistik_gabungan: {$n} baris, " . round(microtime(true) - $t0, 1) . ' detik');

        return self::SUCCESS;
    }
}