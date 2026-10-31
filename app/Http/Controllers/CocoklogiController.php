<?php

namespace App\Http\Controllers;

use App\Imports\CocoklogiImport;
use App\Models\LogistikPengiriman;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;

class CocoklogiController extends Controller
{
    private const LAST_RUN_KEY = 'cocoklogi_last_run';

    /** Info untuk tombol di halaman Data Logistik: kapan terakhir cocoklogi & apakah sudah lewat 7 hari */
    public function status()
    {
        $last    = Cache::get(self::LAST_RUN_KEY);
        $overdue = !$last || Carbon::parse($last)->lt(now()->subDays(7));

        return response()->json(['last' => $last, 'overdue' => $overdue]);
    }

    /**
     * Bandingkan Excel vs DB. TIDAK menghapus apa pun.
     * mode = grup (no_shipment + tujuan)  |  do (no_shipment + no_do)
     */
    public function preview(Request $request)
    {
        $request->validate([
            'file'      => 'required|mimes:xlsx,xls,csv',
            'mode'      => 'required|in:grup,do',
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
        ]);

        $mode = $request->mode;

        $imp = new CocoklogiImport();
        try {
            Excel::import($imp, $request->file('file'));
        } catch (\Throwable $e) {
            logger()->error('COCOKLOGI IMPORT GAGAL', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'File gagal dibaca: ' . mb_substr($e->getMessage(), 0, 200)], 422);
        }

        if (empty($imp->groups)) {
            return response()->json(['error' => 'Kolom no_shipment tidak terbaca di Excel. Cek nama header.'], 422);
        }
        if ($mode === 'do' && empty($imp->dos)) {
            return response()->json(['error' => 'Kolom no_do tidak terbaca di Excel, mode DO tidak bisa dipakai.'], 422);
        }

        $q = LogistikPengiriman::query();
        $this->filterByDistChannel($q);

        $filtered = $request->filled('date_from') || $request->filled('date_to');
        if ($request->filled('date_from')) $q->whereDate('tanggal_naik_logistik', '>=', $request->date_from);
        if ($request->filled('date_to'))   $q->whereDate('tanggal_naik_logistik', '<=', $request->date_to);

        // ---------- kelompokkan baris DB ----------
        $dbItems = [];   // key => [no_shipment, tujuan, no_do, ids[]]

        foreach ($q->select('id', 'no_shipment', 'tujuan', 'no_do')->orderBy('id')->cursor() as $r) {
            $ship = CocoklogiImport::normNo($r->no_shipment);
            if ($ship === '') continue;   // tanpa no_shipment tidak bisa dicocokkan -> tidak pernah ikut terhapus

            if ($mode === 'grup') {
                $k = $ship . '|' . CocoklogiImport::normTujuan($r->tujuan);
                $dbItems[$k]['no_shipment'] = $r->no_shipment;
                $dbItems[$k]['tujuan']      = $r->tujuan;
                $dbItems[$k]['no_do']       = null;
                $dbItems[$k]['ids'][]       = $r->id;
            } else {
                $do = CocoklogiImport::normNo($r->no_do);
                if ($do === '') continue; // DO kosong tidak bisa dicocokkan

                $k = $ship . '|' . $do;
                $dbItems[$k]['no_shipment'] = $r->no_shipment;
                $dbItems[$k]['tujuan']      = $r->tujuan;
                $dbItems[$k]['no_do']       = $r->no_do;
                $dbItems[$k]['ids'][]       = $r->id;
            }
        }

        $excelKeys = $mode === 'grup' ? $imp->groups : $imp->dos;

        // ---------- ada di sistem, tidak ada di Excel ----------
        $missing = [];
        foreach ($dbItems as $k => $g) {
            if (!isset($excelKeys[$k])) {
                $missing[] = [
                    'no_shipment' => $g['no_shipment'],
                    'tujuan'      => $g['tujuan'],
                    'no_do'       => $g['no_do'],
                    'jml_do'      => count($g['ids']),
                    'ids'         => $g['ids'],
                ];
            }
        }

        // ---------- ada di Excel, belum ada di sistem ----------
        // Kalau pakai filter periode, DB-nya cuma sebagian sehingga hasilnya menyesatkan -> tidak dihitung.
        $notInDb = null;
        if (!$filtered) {
            $notInDb = [];
            foreach (array_diff_key($excelKeys, $dbItems) as $v) {
                $notInDb[] = ['no_shipment' => $v[0], 'tujuan_atau_do' => $v[1]];
            }
        }

        $token = bin2hex(random_bytes(8));
        Cache::put('cocok_' . $token, $missing, 1800);   // 30 menit; penghapusan hanya boleh dari daftar ini
        Cache::forever(self::LAST_RUN_KEY, now()->toDateTimeString());

        $totalDb = count($dbItems);

        return response()->json([
            'token'          => $token,
            'mode'           => $mode,
            'total_excel'    => count($excelKeys),
            'total_db'       => $totalDb,
            'skipped_excel'  => $imp->skippedNoShipment,
            'warning'        => $totalDb > 0 && (count($missing) / $totalDb) > 0.5,
            'missing'        => array_map(fn($m) => [
                'no_shipment' => $m['no_shipment'],
                'tujuan'      => $m['tujuan'],
                'no_do'       => $m['no_do'],
                'jml_do'      => $m['jml_do'],
            ], $missing),
            'not_in_db'       => $notInDb === null ? null : array_slice($notInDb, 0, 500),
            'not_in_db_total' => $notInDb === null ? null : count($notInDb),
        ]);
    }

    /**
     * Arsipkan ke logistik_storage lalu hapus dari logistik_pengiriman.
     * Hanya baris yang muncul di preview (token) yang bisa dihapus.
     */
    public function hapus(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'idx'   => 'required|array|min:1',
            'idx.*' => 'integer|min:0',
        ]);

        $missing = Cache::get('cocok_' . $request->token);
        if (!$missing) {
            return response()->json(['error' => 'Sesi preview habis, upload ulang Excel.'], 410);
        }

        $ids = [];
        foreach (array_unique($request->idx) as $i) {
            if (isset($missing[(int) $i])) {
                array_push($ids, ...$missing[(int) $i]['ids']);
            }
        }
        $ids = array_values(array_unique($ids));

        if (empty($ids)) {
            return response()->json(['error' => 'Tidak ada data valid yang dipilih.'], 422);
        }

        $cols = array_values(array_diff(
            array_intersect(
                Schema::getColumnListing('logistik_pengiriman'),
                Schema::getColumnListing('logistik_storage')
            ),
            ['id']
        ));
        $list = '`' . implode('`,`', $cols) . '`';

        try {
            $deleted = 0;

            DB::transaction(function () use ($ids, $list, &$deleted) {
                foreach (array_chunk($ids, 1000) as $chunk) {
                    $in = implode(',', array_fill(0, count($chunk), '?'));

                    $archived = DB::affectingStatement(
                        "INSERT INTO logistik_storage ({$list}) SELECT {$list} FROM logistik_pengiriman WHERE id IN ({$in})",
                        $chunk
                    );
                    $removed = DB::table('logistik_pengiriman')->whereIn('id', $chunk)->delete();

                    // pengaman: yang diarsipkan harus sama dengan yang dihapus, kalau tidak batalkan semua
                    if ($archived !== $removed) {
                        throw new \RuntimeException("Arsip ({$archived}) dan hapus ({$removed}) tidak sama, dibatalkan.");
                    }
                    $deleted += $removed;
                }
            });
        } catch (\Throwable $e) {
            logger()->error('COCOKLOGI HAPUS GAGAL', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Gagal menghapus: ' . mb_substr($e->getMessage(), 0, 200)], 500);
        }

        logger()->info('COCOKLOGI HAPUS', ['deleted' => $deleted, 'user' => optional($request->user())->id]);

        Cache::forget('cocok_' . $request->token);
        Cache::forget('logistik_shipment_agg');
        foreach (['area', 'dist_channel', 'pic_monitoring'] as $c) {
            Cache::forget("list_{$c}");
        }

        return response()->json(['deleted' => $deleted]);
    }

    private function filterByDistChannel($query)
    {
        $channel = session('dist_channel');

        if ($channel) {
            $query->whereRaw('LOWER(TRIM(dist_channel)) = ?', [$channel]);
        }

        return $query;
    }
}