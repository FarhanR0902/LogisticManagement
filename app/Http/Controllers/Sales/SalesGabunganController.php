<?php

namespace App\Http\Controllers\Sales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Manager\GabunganController;

/**
 * Dashboard gabungan Jakarta + Pasuruan untuk SALES.
 *
 * Semua logika (KPI, grouping shipment + tujuan, list, in transit, data gabungan)
 * diwarisi dari Manager\GabunganController. Di sini hanya:
 *   1. nama route / view diganti ke versi sales
 *   2. scope data: periode shipment + dist_channel (sama seperti SalesController)
 */
class SalesGabunganController extends GabunganController
{
    // view dashboard & list dipakai bersama dengan manager (blade sudah
    // membedakan lewat request()->routeIs('sales.*')), jadi cukup ganti route
    protected string $dashboardView  = 'gabungan.dashboard';
    protected string $listRoute      = 'sales.gabungan.list';
    protected string $inTransitRoute = 'sales.gabungan.intransit';
  protected string $dataRoute = 'sales.data.gabungan';
    protected string $dataView       = 'sales.data_gabungan';

    // sama dengan SalesController::PERIODE_SHIPMENT (format di DB: "MM-YYYY")
    private const PERIODE_SHIPMENT = '11-2026';

    private const MODERN_MARKET_CHANNELS = [
        'MT - Minimarket', 'MT - Supermarket', 'MT - Lokal', 'MT - Hypermarket', 'MT - Ecommerce',
    ];

    /* =====================================================
     * HOOK SCOPE (dipanggil di src() parent, sebelum UNION)
     * ===================================================== */
    // $periodeAktif di-set false oleh parent untuk In Transit: mobil yang
    // berangkat bulan lalu dan belum tiba tetap harus kelihatan.
    // Filter dist_channel tetap berlaku.
    protected function scopeJakarta($q)
    {
        if ($this->periodeAktif) {
            $q->where('periode_shipment', self::PERIODE_SHIPMENT);
        }
        $this->scopeDistChannel($q, 'dist_channel');
    }

    protected function scopePasuruan($q)
    {
        if ($this->periodeAktif) {
            $q->where('periode_shipment_pasuruan', self::PERIODE_SHIPMENT);
        }
        $this->scopeDistChannel($q, 'dist_channel_pasuruan');
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
    /** Rincian DO per shipment + tujuan (klik tujuan di Data Gabungan) */
public function detail(Request $request)
{
    $request->validate([
        'sumber'      => 'nullable|string',
        'no_shipment' => 'required|string',
        'tujuan'      => 'nullable|string',
    ]);

    $pasuruan = strtolower((string) $request->input('sumber')) === 'pasuruan';
    $sfx      = $pasuruan ? '_pasuruan' : '';
    $tujuan   = (string) $request->input('tujuan', '');

    $q = DB::table($pasuruan ? 'logistik_pengiriman_pasuruan' : 'logistik_pengiriman')
        ->where('no_shipment' . $sfx, $request->input('no_shipment'));

    $pasuruan ? $this->scopePasuruan($q) : $this->scopeJakarta($q);

    $tujuan !== ''
        ? $q->where('tujuan' . $sfx, $tujuan)
        : $q->where(fn($x) => $x->whereNull('tujuan' . $sfx)->orWhere('tujuan' . $sfx, ''));

    $fmtDate = fn($v) => $v ? date('d-m-Y', strtotime($v)) : '-';
    $fmtDes  = fn($v) => ($v === null || $v === '' || !is_numeric($v))
        ? '-' : number_format((float) $v, 2, ',', '.');

    $data = $q->orderBy('sales_document' . $sfx)->orderBy('id')->get()
        ->map(function ($row) use ($sfx, $pasuruan, $fmtDate, $fmtDes) {
            $r   = (array) $row;
            $get = fn($c) => $r[$c . $sfx] ?? null;

            // kolom qty beda nama antara Jakarta dan Pasuruan
            $qty = $pasuruan ? $get('total_do') : ($r['total_do_qty_car'] ?? null);
            $kk  = $get('kirim_kontribusi');

            return [
                'sales_document'       => $get('sales_document') ?: '-',
                'customer_reference'   => $get('customer_reference') ?: '-',
                'contract_end_date'    => $fmtDate($get('contract_end_date')),
                'no_do'                => $get('no_do') ?: '-',
                'no_delivery'          => $get('no_delivery') ?: '-',
                'qty'                  => $qty,
                'nilai_muatan'         => 'Rp ' . number_format((float) $get('nilai_muatan'), 0, ',', '.'),
                'kirim_kontribusi'     => $kk !== null && $kk !== ''
                    ? 'Rp ' . number_format((float) $kk, 0, ',', '.') : '-',
                'kirim_kontribusi_raw' => (float) $kk,
                'total_kubik'          => $fmtDes($get('total_kubik')),
                'total_tonase'         => $fmtDes($get('total_tonase')),
            ];
        });

    return response()->json(['data' => $data]);
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