<?php

namespace App\Http\Controllers;

use App\Models\TujuanFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TujuanFilterController extends Controller
{
    /**
     * List + search + filter by area, dengan pagination.
     */
    public function index(Request $request)
    {
        $query = TujuanFilter::query();

        if ($search = $request->get('q')) {
            $query->where('tujuan', 'like', "%{$search}%");
        }

        if ($area = $request->get('area')) {
            $query->where('area', $area);
        }

        $data = $query->orderBy('tujuan')->paginate(25)->withQueryString();

        // Dropdown daftar area yang sudah ada, buat filter & datalist saran input
        $list_area = TujuanFilter::select('area')
            ->distinct()
            ->orderBy('area')
            ->pluck('area');

        return view('tujuan-filter.index', compact('data', 'list_area'));
    }

    /**
     * Simpan tujuan baru. Kalau customer/alamat baru muncul di data shipment
     * tapi belum ada di sini, tambahkan lewat form ini.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tujuan' => 'required|string|max:255|unique:tujuan_filters,tujuan',
            'area'   => 'required|string|max:100',
            'dist_channel' => 'nullable|string|max:100',
        ], [
            'tujuan.unique' => 'Tujuan ini sudah terdaftar, silakan edit data yang sudah ada.',
        ]);

        TujuanFilter::create($validated);

        return back()->with('success', "Tujuan \"{$validated['tujuan']}\" berhasil ditambahkan ke area {$validated['area']}.");
    }

    public function update(Request $request, TujuanFilter $tujuanFilter)
    {
        $validated = $request->validate([
            'tujuan' => ['required', 'string', 'max:255', Rule::unique('tujuan_filters', 'tujuan')->ignore($tujuanFilter->id)],
            'area'   => 'required|string|max:100',
            'dist_channel' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $tujuanFilter->update($validated);

        return back()->with('success', 'Data tujuan berhasil diupdate.');
    }

    public function destroy(TujuanFilter $tujuanFilter)
    {
        $tujuanFilter->delete();

        return back()->with('success', 'Data tujuan berhasil dihapus.');
    }

    /**
     * Import massal dari CSV (kolom: tujuan,area).
     * Baris dengan tujuan yang sudah ada akan di-skip (tidak menimpa),
     * supaya data yang sudah dikurasi manual tidak tertimpa import lama.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ]);

        $path = $request->file('file')->getRealPath();
        $handle = fopen($path, 'r');

        // Buang BOM UTF-8 kalau ada
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle);
        $header = array_map(fn($h) => strtolower(trim($h)), $header);

        $idxTujuan = array_search('tujuan', $header);
        $idxArea = array_search('area', $header);

        if ($idxTujuan === false || $idxArea === false) {
            return back()->with('error', 'Format CSV salah. Kolom wajib: tujuan,area');
        }

        $inserted = 0;
        $skipped = 0;
        $conflicts = [];

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                $tujuan = trim($row[$idxTujuan] ?? '');
                $area = trim($row[$idxArea] ?? '');

                if ($tujuan === '' || $area === '') {
                    continue;
                }

                $existing = TujuanFilter::where('tujuan', $tujuan)->first();

                if ($existing) {
                    $normalizedArea = strtoupper(str_replace(' ', '_', $area));
                    if ($existing->area !== $normalizedArea) {
                        $conflicts[] = "{$tujuan}: DB punya '{$existing->area}', CSV punya '{$normalizedArea}' (dilewati)";
                    }
                    $skipped++;
                    continue;
                }

                TujuanFilter::create([
                    'tujuan' => $tujuan,
                    'area' => $area,
                ]);
                $inserted++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($handle);
            return back()->with('error', 'Import gagal: ' . $e->getMessage());
        }

        fclose($handle);

        $message = "Import selesai. {$inserted} tujuan baru ditambahkan, {$skipped} dilewati (sudah ada).";
        if (count($conflicts) > 0) {
            $message .= ' Ada ' . count($conflicts) . ' konflik area (lihat detail di bawah).';
        }

        return back()
            ->with('success', $message)
            ->with('conflicts', $conflicts);
    }
}
