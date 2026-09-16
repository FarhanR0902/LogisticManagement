<?php

namespace App\Http\Controllers\Spv;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\TarifPengiriman;
use Illuminate\Http\Request;

class TarifPengirimanController extends Controller
{
    /**
     * Cek role SPV Planner
     */
    private function checkRole()
    {
        if (!auth()->check() || auth()->user()->role !== 'spvplanner') {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }
    }

    /**
     * Menampilkan data
     */
    public function index(Request $request)
    {
        $this->checkRole();

        $query = TarifPengiriman::query();

        // Search
        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('servc_agent', 'like', "%{$search}%")
                    ->orWhere('ekpedisi', 'like', "%{$search}%")
                    ->orWhere('mobil', 'like', "%{$search}%")
                    ->orWhere('routew', 'like', "%{$search}%")
                    ->orWhere('route', 'like', "%{$search}%")
                    ->orWhere('tonase', 'like', "%{$search}%")
                    ->orWhere('kubikasi', 'like', "%{$search}%");
            });
        }

        $data = $query
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('spv.tarif_pengiriman.index', compact('data'));
    }

    /**
     * Form tambah
     */
    public function create()
    {
        $this->checkRole();

        return view('spv.tarif_pengiriman.create');
    }

    public function import(Request $request)
{
    $this->checkRole();

    $request->validate([
        'file' => 'required|file|mimes:csv,txt',
    ]);

    $path   = $request->file('file')->getRealPath();
    $handle = fopen($path, 'r');

    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        rewind($handle);
    }

    $header = fgetcsv($handle);
    $header = array_map(fn($h) => strtolower(trim($h)), $header);

    $idxServcAgent = array_search('servc_agent', $header);
    $idxEkpedisi   = array_search('ekpedisi', $header);
    $idxSh         = array_search('sh', $header);
    $idxMobil      = array_search('mobil', $header);
    $idxRoutew     = array_search('routew', $header);
    $idxRoute      = array_search('route', $header);
    $idxBiayaKirim = array_search('biaya_kirim', $header);
    $idxUnit       = array_search('unit', $header);
    $idxPer        = array_search('per', $header);
    $idxUom        = array_search('uom', $header);
    $idxD          = array_search('d', $header);
    $idxTx         = array_search('tx', $header);
    $idxE          = array_search('e', $header);
    $idxS1         = array_search('s_1', $header);
    $idxS2         = array_search('s_2', $header);
    $idxValidFrom  = array_search('valid_from', $header);
    $idxValidTo    = array_search('valid_to', $header);
    $idxTonase     = array_search('tonase', $header);
    $idxKubikasi   = array_search('kubikasi', $header);

    if ($idxRoute === false) {
        fclose($handle);
        return back()->with('error', 'Kolom route tidak ditemukan.');
    }

    $inserted = 0;
    $skipped  = 0;
    $batch    = [];
    $now      = now();

    DB::beginTransaction();

    try {
        while (($row = fgetcsv($handle)) !== false) {
            $route = trim($row[$idxRoute] ?? '');

            if ($route === '') {
                $skipped++;
                continue;
            }

            // create langsung, bukan updateOrCreate -- setiap baris
            // CSV WAJIB jadi row baru, meskipun route-nya sama dengan
            // baris lain di file yang sama
            $batch[] = [
                'servc_agent' => $idxServcAgent !== false ? trim($row[$idxServcAgent] ?? '') ?: null : null,
                'ekpedisi'    => $idxEkpedisi   !== false ? trim($row[$idxEkpedisi] ?? '')   ?: null : null,
                'sh'          => $idxSh         !== false ? trim($row[$idxSh] ?? '')         ?: null : null,
                'mobil'       => $idxMobil      !== false ? trim($row[$idxMobil] ?? '')      ?: null : null,
                'routew'      => $idxRoutew     !== false ? trim($row[$idxRoutew] ?? '')     ?: null : null,
                'route'       => $route,
                'biaya_kirim' => $idxBiayaKirim !== false ? trim($row[$idxBiayaKirim] ?? '') ?: null : null,
                'unit'        => $idxUnit       !== false ? trim($row[$idxUnit] ?? '')       ?: null : null,
                'per'         => $idxPer        !== false ? trim($row[$idxPer] ?? '')        ?: null : null,
                'uom'         => $idxUom        !== false ? trim($row[$idxUom] ?? '')        ?: null : null,
                'd'           => $idxD          !== false ? trim($row[$idxD] ?? '')          ?: null : null,
                'tx'          => $idxTx         !== false ? trim($row[$idxTx] ?? '')         ?: null : null,
                'e'           => $idxE          !== false ? trim($row[$idxE] ?? '')          ?: null : null,
                's_1'         => $idxS1         !== false ? trim($row[$idxS1] ?? '')         ?: null : null,
                's_2'         => $idxS2         !== false ? trim($row[$idxS2] ?? '')         ?: null : null,
                'valid_from'  => $idxValidFrom  !== false ? trim($row[$idxValidFrom] ?? '')  ?: null : null,
                'valid_to'    => $idxValidTo    !== false ? trim($row[$idxValidTo] ?? '')    ?: null : null,
                'tonase'      => $idxTonase     !== false ? trim($row[$idxTonase] ?? '')     ?: null : null,
                'kubikasi'    => $idxKubikasi   !== false ? trim($row[$idxKubikasi] ?? '')   ?: null : null,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
            $inserted++;

            // insert per 500 baris supaya query tidak terlalu besar sekaligus
            if (count($batch) >= 500) {
                TarifPengiriman::insert($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            TarifPengiriman::insert($batch);
        }

        DB::commit();
    } catch (\Throwable $e) {
        DB::rollBack();
        fclose($handle);

        return back()->with('error', 'Import gagal: ' . $e->getMessage());
    }

    fclose($handle);

    $msg = "Import selesai. {$inserted} data berhasil dimasukkan.";
    if ($skipped > 0) {
        $msg .= " {$skipped} baris dilewati karena kolom route kosong.";
    }

    return back()->with('success', $msg);
}

    /**
     * Simpan data
     */
    public function store(Request $request)
    {
        $this->checkRole();

        $validated = $request->validate([
            'servc_agent' => 'nullable|string|max:10',
            'ekpedisi' => 'nullable|string|max:100',
            'sh' => 'nullable|string|max:10',
            'mobil' => 'nullable|string|max:50',
            'routew' => 'nullable|string|max:20',
            'route' => 'nullable|string|max:100',
            'biaya_kirim' => 'nullable|string|max:30',
            'unit' => 'nullable|string|max:10',
            'per' => 'nullable|string|max:10',
            'uom' => 'nullable|string|max:10',
            'd' => 'nullable|string|max:10',
            'tx' => 'nullable|string|max:10',
            'e' => 'nullable|string|max:10',
            's_1' => 'nullable|string|max:10',
            's_2' => 'nullable|string|max:10',
            'valid_from' => 'nullable|string|max:20',
            'valid_to' => 'nullable|string|max:20',
            'tonase' => 'nullable|string|max:20',
            'kubikasi' => 'nullable|string|max:20',
        ]);

        TarifPengiriman::create($validated);

        return redirect()
            ->route('spvplanner.tarif.index')
            ->with('success', 'Data tarif berhasil ditambahkan.');
    }
    

    /**
     * Form edit
     */
    public function edit($id)
    {
        $this->checkRole();

        $data = TarifPengiriman::findOrFail($id);

        return view('spv.tarif_pengiriman.edit', compact('data'));
    }

    /**
     * Update
     */
    public function update(Request $request, $id)
    {
        $this->checkRole();

        $data = TarifPengiriman::findOrFail($id);

        $validated = $request->validate([
            'servc_agent' => 'nullable|string|max:10',
            'ekpedisi' => 'nullable|string|max:100',
            'sh' => 'nullable|string|max:10',
            'mobil' => 'nullable|string|max:50',
            'routew' => 'nullable|string|max:20',
            'route' => 'nullable|string|max:100',
            'biaya_kirim' => 'nullable|string|max:30',
            'unit' => 'nullable|string|max:10',
            'per' => 'nullable|string|max:10',
            'uom' => 'nullable|string|max:10',
            'd' => 'nullable|string|max:10',
            'tx' => 'nullable|string|max:10',
            'e' => 'nullable|string|max:10',
            's_1' => 'nullable|string|max:10',
            's_2' => 'nullable|string|max:10',
            'valid_from' => 'nullable|string|max:20',
            'valid_to' => 'nullable|string|max:20',
            'tonase' => 'nullable|string|max:20',
            'kubikasi' => 'nullable|string|max:20',
        ]);

        $data->update($validated);

        return redirect()
            ->route('spvplanner.tarif.index')
            ->with('success', 'Data tarif berhasil diperbarui.');
    }

    /**
     * Hapus
     */
    // public function destroy($id)
    // {
    //     $this->checkRole();

    //     $data = TarifPengiriman::findOrFail($id);

    //     $data->delete();

    //     return redirect()
    //         ->route('spvplanner.tarif.index')
    //         ->with('success', 'Data tarif berhasil dihapus.');
    // }

    public function destroy($id)
{
    $this->checkRole();

    $data = TarifPengiriman::findOrFail($id);
    $data->delete();

    if (request()->wantsJson()) {
        return response()->json(['message' => 'Data tarif berhasil dihapus.']);
    }

    return redirect()
        ->route('spvplanner.tarif.index')
        ->with('success', 'Data tarif berhasil dihapus.');
}
    /**
 * Hapus banyak data sekaligus (checkbox terpilih)
 */
public function bulkDestroy(Request $request)
{
    $this->checkRole();

    $ids = $request->input('ids', []);

    if (empty($ids)) {
        return back()->with('error', 'Tidak ada data yang dipilih.');
    }

    TarifPengiriman::whereIn('id', $ids)->delete();

    return back()->with('success', count($ids) . ' data tarif berhasil dihapus.');
}

/**
 * Hapus SEMUA data tarif (tanpa terkecuali)
 */
public function destroyAll(Request $request)
{
    $this->checkRole();

    $count = TarifPengiriman::count();
    TarifPengiriman::truncate();

    return response()->json([
        'message' => "Semua data tarif ({$count}) berhasil dihapus.",
    ]);
}

/**
 * Update banyak data sekaligus (bulk edit) — hanya field yang diisi
 * yang akan diterapkan ke semua data terpilih.
 */
public function bulkUpdate(Request $request)
{
    $this->checkRole();

    $ids = $request->input('ids', []);

    if (empty($ids)) {
        return back()->with('error', 'Tidak ada data yang dipilih.');
    }

    $fields = [
        'servc_agent', 'ekpedisi', 'sh', 'mobil', 'routew', 'route',
        'biaya_kirim', 'unit', 'per', 'uom', 'd', 'tx', 'e',
        's_1', 's_2', 'valid_from', 'valid_to', 'tonase', 'kubikasi',
    ];

    $updateData = [];

    foreach ($fields as $field) {
        $value = $request->input($field);

        // hanya masukkan field yang benar-benar diisi (tidak kosong)
        if ($value !== null && trim($value) !== '') {
            $updateData[$field] = trim($value);
        }
    }

    if (empty($updateData)) {
        return back()->with('error', 'Tidak ada field yang diisi untuk diubah.');
    }

    TarifPengiriman::whereIn('id', $ids)->update($updateData);

    return back()->with('success', count($ids) . ' data tarif berhasil diperbarui.');
}
}