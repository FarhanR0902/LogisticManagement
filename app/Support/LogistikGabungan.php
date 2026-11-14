<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LogistikGabungan
{
    private const OFFSET = 1000000000;

    /** $filter dijalankan di KEDUA tabel sebelum di-UNION, jadi yang digabung hanya data terfilter */
    public static function union(?callable $filter = null)
    {
        $a = Schema::getColumnListing('logistik_pengiriman');
        $b = array_flip(Schema::getColumnListing('logistik_storage'));

        $selA = [];
        $selB = [];
        foreach ($a as $c) {
            $selA[] = "`{$c}`";
            $selB[] = $c === 'id'
                ? '`id` + ' . self::OFFSET . ' AS `id`'
                : (isset($b[$c]) ? "`{$c}`" : "NULL AS `{$c}`");
        }

        $q1 = DB::table('logistik_pengiriman')->selectRaw(implode(', ', $selA) . ", 'AKTIF' AS sumber");
        $q2 = DB::table('logistik_storage')->selectRaw(implode(', ', $selB) . ", 'STORAGE' AS sumber");

        if ($filter) {
            $filter($q1);
            $filter($q2);
        }

        return $q1->unionAll($q2);
    }
    /** Salin gabungan aktif + storage SEKALI ke tabel kerja, lalu beri primary key */
public static function materialize(string $tabel): void
{
    DB::statement("DROP TABLE IF EXISTS `{$tabel}`");

    $u = self::union();
    DB::statement("CREATE TABLE `{$tabel}` AS " . $u->toSql(), $u->getBindings());
    DB::statement("ALTER TABLE `{$tabel}` ADD PRIMARY KEY (`id`)");
}

    public static function query(string $alias = 'v', ?callable $filter = null)
    {
        return DB::query()->fromSub(self::union($filter), $alias);
    }
}