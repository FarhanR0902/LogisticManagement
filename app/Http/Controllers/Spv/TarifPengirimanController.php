<?php

namespace App\Http\Controllers\Spv;

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
                    ->orWhere('route', 'like', "%{$search}%");
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
        ]);

        $data->update($validated);

        return redirect()
            ->route('spvplanner.tarif.index')
            ->with('success', 'Data tarif berhasil diperbarui.');
    }

    /**
     * Hapus
     */
    public function destroy($id)
    {
        $this->checkRole();

        $data = TarifPengiriman::findOrFail($id);

        $data->delete();

        return redirect()
            ->route('spvplanner.tarif.index')
            ->with('success', 'Data tarif berhasil dihapus.');
    }
}