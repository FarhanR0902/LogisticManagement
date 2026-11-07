<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Manager\GabunganController;

/**
 * Dashboard gabungan Jakarta + Pasuruan untuk SALES.
 *
 * Semua logika (KPI, grouping shipment + tujuan, list, in transit,
 * data gabungan, detail DO) diwarisi dari Manager\GabunganController.
 * Di sini hanya:
 *   1. nama route / view diganti ke versi sales
 *   2. scope data: periode shipment (default) + dist_channel
 */
class SalesGabunganController extends GabunganController
{
    protected string $dashboardView  = 'gabungan.dashboard';
    protected string $listRoute      = 'sales.gabungan.list';
    protected string $inTransitRoute = 'sales.gabungan.intransit';
    protected string $dataRoute      = 'sales.data.gabungan';
    protected string $dataView       = 'sales.data_gabungan';

    // periode default Sales (format di DB: "MM-YYYY")
    private const PERIODE_SHIPMENT = '11-2026';

    private const MODERN_MARKET_CHANNELS = [
        'MT - Minimarket', 'MT - Supermarket', 'MT - Lokal', 'MT - Hypermarket', 'MT - Ecommerce',
    ];

    /* =====================================================
     * HOOK SCOPE (dipanggil di src() parent, sebelum UNION)
     * ===================================================== */

    protected function scopeJakarta($q)
    {
        if ($this->pakaiKunciPeriode()) {
            $q->where('periode_shipment', self::PERIODE_SHIPMENT);
        }
        $this->scopeDistChannel($q, 'dist_channel');
    }

    protected function scopePasuruan($q)
    {
        if ($this->pakaiKunciPeriode()) {
            $q->where('periode_shipment_pasuruan', self::PERIODE_SHIPMENT);
        }
        $this->scopeDistChannel($q, 'dist_channel_pasuruan');
    }

    /**
     * Kunci periode (11-2026) hanya aktif kalau:
     *  - bukan In Transit ($periodeAktif = false dari parent), dan
     *  - user belum memilih filter periode sendiri (bulan/tahun/tanggal).
     */
    private function pakaiKunciPeriode(): bool
    {
        $r = request();

        return $this->periodeAktif
            && !array_filter((array) $r->input('month', []), 'strlen')
            && !$r->filled('year')
            && !$r->filled('date')
            && !$r->filled('date_from')
            && !$r->filled('date_to');
    }

    /** Nilai dist_channel efektif: user modern_market dikunci, lainnya ikut session. */
    private function resolveDistChannel()
    {
        $u = auth()->user();

        if ($u && (
            strtolower((string) $u->username) === 'modern_market'
            || strtoupper((string) $u->dist_channel) === 'MODERN_MARKET'
        )) {
            return 'MODERN_MARKET';
        }

        return session('dist_channel');
    }

    private function scopeDistChannel($query, string $col)
    {
        $v = $this->resolveDistChannel();

        if (is_array($v)) {
            $v = array_values(array_filter($v, 'strlen'));
            return empty($v) ? $query : $query->whereIn($col, $v);
        }

        if (empty($v) || strtoupper((string) $v) === 'ALL') {
            return $query;
        }

        if (strtoupper($v) === 'MODERN_MARKET') {
            return $query->whereIn($col, self::MODERN_MARKET_CHANNELS);
        }

        if (strtoupper($v) === 'MT') {
            return $query->where($col, 'like', 'MT -%');
        }

        return $query->whereIn($col, (array) $v);
    }
}