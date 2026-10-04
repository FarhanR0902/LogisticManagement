<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

trait LogsInput
{
    /**
     * Bungkus 1 aksi save. Snapshot sebelum & sesudah, catat hasilnya.
     * Exception tetap dilempar ulang supaya controller bisa balas error.
     */
    private function loggedSave(string $module, string $action, $id, array $payload, callable $fn)
    {
        $payload = collect($payload)->except(['_token', '_method'])->all();

        $noShipment = null;
        $ids = [];
        $before = [];
        try {
            $noShipment = DB::table('logistik_pengiriman')->where('id', $id)->value('no_shipment');
            $ids    = $this->inputRowIds([$noShipment, $payload['no_shipment'] ?? null], $id);
            $before = $this->inputRows($ids);
        } catch (\Throwable $e) {
            logger()->error('LOG SNAPSHOT SEBELUM GAGAL: ' . $e->getMessage());
        }

        try {
            $result = $fn();
        } catch (\Throwable $e) {
            $this->writeInputLog($module, $action, $id, $noShipment, 'failed',
                get_class($e) . ': ' . $e->getMessage(), $payload);
            throw $e;
        }

        $changes = [];
        try {
            $changes = $this->inputDiff($before, $this->inputRows($ids));
        } catch (\Throwable $e) {
            logger()->error('LOG SNAPSHOT SESUDAH GAGAL: ' . $e->getMessage());
        }

        $this->writeInputLog($module, $action, $id, $noShipment,
            empty($changes) ? 'no_change' : 'success', null, $payload, $changes);

        return $result;
    }

    private function inputRowIds(array $noShipments, $id): array
    {
        $noShipments = array_values(array_filter(array_unique($noShipments), fn ($v) => $v !== null && $v !== ''));

        if (!$id && empty($noShipments)) {
            return []; // jangan sampai where kosong = ambil semua baris
        }

        return DB::table('logistik_pengiriman')
            ->where(function ($w) use ($noShipments, $id) {
                if ($id) $w->orWhere('id', $id);
                if ($noShipments) $w->orWhereIn('no_shipment', $noShipments);
            })
            ->pluck('id')->all();
    }

    private function inputRows(array $ids): array
    {
        if (empty($ids)) return [];

        return DB::table('logistik_pengiriman')->whereIn('id', $ids)->get()
            ->keyBy('id')->map(fn ($r) => (array) $r)->all();
    }

    private function inputDiff(array $before, array $after): array
    {
        $out = [];
        foreach ($after as $rid => $row) {
            $old = $before[$rid] ?? [];
            foreach ($row as $col => $new) {
                if ($col === 'updated_at') continue;
                $o = $old[$col] ?? null;
                if ((string) $o !== (string) $new) {
                    $out[$rid][$col] = ['old' => $o, 'new' => $new];
                }
            }
        }
        return $out;
    }

    private function writeInputLog(string $module, string $action, $id, $noShipment,
                                   string $status, ?string $error, array $payload, array $changes = []): void
    {
        try {
            $nulled = [];
            foreach ($changes as $rid => $cols) {
                foreach ($cols as $col => $c) {
                    $oldFilled = $c['old'] !== null && $c['old'] !== '';
                    $newEmpty  = $c['new'] === null || $c['new'] === '';
                    if ($oldFilled && $newEmpty) $nulled[] = "{$rid}:{$col}";
                }
            }

            $flags = JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE;

            DB::table('input_logs')->insert([
                'module'        => $module,
                'action'        => $action,
                'user_id'       => auth()->id(),
                'logistik_id'   => is_numeric($id) ? $id : null,
                'no_shipment'   => $noShipment,
                'status'        => $status,
                'error_message' => $error ? mb_substr($error, 0, 1000) : null,
                'payload_keys'  => json_encode(array_keys($payload), $flags),
                'payload'       => json_encode($payload, $flags),
                'changes'       => $changes ? json_encode($changes, $flags) : null,
                'nulled_fields' => $nulled ? json_encode($nulled, $flags) : null,
                'rows_affected' => count($changes),
                'ip'            => request()->ip(),
                'created_at'    => now(),
            ]);
        } catch (\Throwable $e) {
            // log gagal tidak boleh bikin save ikut gagal
            logger()->error('LOG INPUT GAGAL: ' . $e->getMessage());
        }
    }
}