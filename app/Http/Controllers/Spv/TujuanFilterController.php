<?php

namespace App\Http\Controllers\Spv;

use App\Http\Controllers\Controller;
use App\Models\TujuanFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TujuanFilterController extends Controller
{
    /**
     * Nama tabel dipusatkan di sini.
     */
    private const TABLE = 'tujuanfillterr';

    /**
     * Role yang diizinkan akses controller ini.
     * Dipakai bareng oleh SPV Planner & SPV Monitoring.
     */
    private const ALLOWED_ROLES = ['spvplanner', 'spvmonitoring'];

    /**
     * Cek role — sekarang izinkan spvplanner ATAU spvmonitoring.
     */
    private function checkRole()
    {
        $role = auth()->user()->role ?? null;

        if (!auth()->check() || !in_array($role, self::ALLOWED_ROLES, true)) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }
    }

    /**
     * Route index ditentukan dinamis sesuai role/prefix yang lagi dipakai,
     * supaya redirect() setelah store/update/destroy nggak salah tujuan
     * (spvplanner.tujuan.index vs spvmonitoring.tujuan.index).
     */
    private function indexRouteName(): string
    {
        if (request()->routeIs('spvmonitoring.*')) {
            return 'spvmonitoring.tujuan.index';
        }

        if (request()->routeIs('spvplanner.*')) {
            return 'spvplanner.tujuan.index';
        }

        // fallback terakhir kalau prefix route lain di luar 2 itu
        return auth()->user()->role === 'spvmonitoring'
            ? 'spvmonitoring.tujuan.index'
            : 'spvplanner.tujuan.index';
    }

    /**
     * Aturan validasi dipusatkan supaya store() & update() konsisten,
     * sesuai header: id, Div, customer_id, tujuan, dist_channel, pulau,
     * area, Planner, Monitoring, biaya_kuli, transport_lead_time
     *
     * PENTING: unique 'tujuan' sekarang di-scope per 'Div', karena satu
     * tujuan (nama customer) BISA muncul di lebih dari satu Div dengan
     * Planner/Monitoring yang berbeda (misal HO Meruya vs Pasuruan).
     * Kombinasi (Div, tujuan) itulah yang harus unik, bukan tujuan saja.
     */
    private function rules($ignoreId = null): array
    {
        return [
            'Div'                 => 'nullable|string|max:100',
            'customer_id'         => 'nullable|string|max:100',
            'tujuan'              => [
                'required',
                'string',
                'max:255',
                $ignoreId
                    ? Rule::unique(self::TABLE, 'tujuan')
                        ->ignore($ignoreId)
                        ->where(fn($q) => $q->where('Div', request('Div')))
                    : Rule::unique(self::TABLE, 'tujuan')
                        ->where(fn($q) => $q->where('Div', request('Div'))),
            ],
            'dist_channel'        => 'nullable|string|max:100',
            'pulau'               => 'nullable|string|max:100',
            'area'                => 'required|string|max:100',
            'Planner'             => 'nullable|string|max:100',
            'Monitoring'          => 'nullable|string|max:100',
            'biaya_kuli'          => 'nullable|numeric|min:0',
            'transport_lead_time' => 'nullable|integer|min:0',
        ];
    }

    private function validationMessages(): array
    {
        return [
            'tujuan.unique'   => 'Tujuan ini sudah terdaftar untuk Div yang sama, silakan edit data yang sudah ada.',
            'tujuan.required' => 'Kolom tujuan wajib diisi.',
            'area.required'   => 'Kolom area wajib diisi.',
        ];
    }

    /**
     * biaya_kuli sering diinput dengan koma ribuan (misal "12,000"),
     * dibersihkan dulu sebelum divalidasi/disimpan.
     */
    private function normalizeBiayaKuli(Request $request): void
    {
        if ($request->filled('biaya_kuli')) {
            $request->merge([
                'biaya_kuli' => (int) str_replace([',', '.', ' '], '', $request->input('biaya_kuli')),
            ]);
        }
    }

    /**
     * transport_lead_time kadang diinput dengan satuan (misal "3 hari"),
     * ambil angkanya saja.
     */
    private function normalizeTransportLeadTime(Request $request): void
    {
        if ($request->filled('transport_lead_time')) {
            $request->merge([
                'transport_lead_time' => (int) preg_replace('/[^0-9]/', '', $request->input('transport_lead_time')),
            ]);
        }
    }

    /**
     * List + search umum + filter per kolom (Div, Customer ID, Tujuan,
     * Distribution Channel, Pulau, Area, Planner, Monitoring, Biaya Kuli,
     * Transport Lead Time), dengan pagination.
     *
     * PENTING: filter per kolom di sini pakai LIKE (kecuali pulau & area
     * yang tetap exact-match lewat dropdown), supaya user bisa ketik
     * sebagian kata dan tetap ketemu.
     */
    public function index(Request $request)
    {
        $this->checkRole();

        $query = TujuanFilter::query();

        // ===== Pencarian umum (semua kolom) =====
        if ($request->filled('search')) {

            $search = $request->search;

            $query->where(function ($q) use ($search) {

                $q->where('Div', 'like', "%{$search}%")
                    ->orWhere('customer_id', 'like', "%{$search}%")
                    ->orWhere('tujuan', 'like', "%{$search}%")
                    ->orWhere('dist_channel', 'like', "%{$search}%")
                    ->orWhere('pulau', 'like', "%{$search}%")
                    ->orWhere('area', 'like', "%{$search}%")
                    ->orWhere('Planner', 'like', "%{$search}%")
                    ->orWhere('Monitoring', 'like', "%{$search}%")
                    ->orWhere('biaya_kuli', 'like', "%{$search}%")
                    ->orWhere('transport_lead_time', 'like', "%{$search}%");

            });
        }

        // ===== Filter per kolom (LIKE, boleh sebagian kata) =====
        $likeFilters = [
            'Div'                 => 'Div',
            'customer_id'         => 'customer_id',
            'tujuan'              => 'tujuan',
            'dist_channel'        => 'dist_channel',
            'Planner'             => 'Planner',
            'Monitoring'          => 'Monitoring',
            'biaya_kuli'          => 'biaya_kuli',
            'transport_lead_time' => 'transport_lead_time',
        ];

        foreach ($likeFilters as $requestKey => $column) {
            if ($request->filled($requestKey)) {
                $query->where($column, 'like', '%' . $request->input($requestKey) . '%');
            }
        }

        // ===== Filter dropdown (exact match) =====
        if ($request->filled('pulau')) {
            $query->where('pulau', $request->pulau);
        }

        if ($request->filled('area')) {
            $query->where('area', $request->area);
        }

        $data = $query->orderBy('tujuan')->paginate(100)->withQueryString();

        $list_area = TujuanFilter::select('area')
            ->distinct()
            ->orderBy('area')
            ->pluck('area');

        $list_pulau = TujuanFilter::select('pulau')
            ->whereNotNull('pulau')
            ->where('pulau', '!=', '')
            ->distinct()
            ->orderBy('pulau')
            ->pluck('pulau');

        return view('spv.tujuan_filter.index', compact('data', 'list_area', 'list_pulau'));
    }

    /**
     * Form tambah data baru.
     */
    public function create()
    {
        $this->checkRole();

        $list_area = TujuanFilter::select('area')
            ->distinct()
            ->orderBy('area')
            ->pluck('area');

        return view('spv.tujuan_filter.create', compact('list_area'));
    }

    /**
     * Simpan tujuan baru.
     */
    public function store(Request $request)
    {
        $this->checkRole();

        $this->normalizeBiayaKuli($request);
        $this->normalizeTransportLeadTime($request);

        $validated = $request->validate($this->rules(), $this->validationMessages());

        TujuanFilter::create($validated);

        return redirect()
            ->route($this->indexRouteName())
            ->with('success', "Tujuan \"{$validated['tujuan']}\" berhasil ditambahkan ke area {$validated['area']}.");
    }

    /**
     * Form edit data.
     */
    public function edit($id)
    {
        $this->checkRole();

        $data = TujuanFilter::findOrFail($id);

        $list_area = TujuanFilter::select('area')
            ->distinct()
            ->orderBy('area')
            ->pluck('area');

        return view('spv.tujuan_filter.edit', compact('data', 'list_area'));
    }

    /**
     * Update data.
     */
    public function update(Request $request, $id)
    {
        $this->checkRole();

        $data = TujuanFilter::findOrFail($id);

        $this->normalizeBiayaKuli($request);
        $this->normalizeTransportLeadTime($request);

        $validated = $request->validate(
            array_merge($this->rules($data->id), [

            ]),
            $this->validationMessages()
        );

        $data->update($validated);

        return redirect()
            ->route($this->indexRouteName())
            ->with('success', 'Data tujuan berhasil diperbarui.');
    }

    /**
     * Hapus data.
     */
    public function destroy($id)
    {
        $this->checkRole();

        $data = TujuanFilter::findOrFail($id);
        $tujuan = $data->tujuan;

        $data->delete();

        return redirect()
            ->route($this->indexRouteName())
            ->with('success', "Tujuan \"{$tujuan}\" berhasil dihapus.");
    }

    /**
     * Hapus beberapa data sekaligus (checkbox terpilih).
     */
    public function bulkDestroy(Request $request)
    {
        $this->checkRole();

        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer|exists:' . self::TABLE . ',id',
        ]);

        $count = TujuanFilter::whereIn('id', $request->ids)->delete();

        return redirect()
            ->route($this->indexRouteName())
            ->with('success', "{$count} data tujuan berhasil dihapus.");
    }

    /**
     * Update beberapa data sekaligus (checkbox terpilih).
     * Field yang DIKOSONGKAN di form tidak diubah -- hanya field
     * yang diisi yang diterapkan ke semua baris terpilih.
     */
    public function bulkUpdate(Request $request)
    {
        $this->checkRole();

        $request->validate([
            'ids'                 => 'required|array|min:1',
            'ids.*'               => 'integer|exists:' . self::TABLE . ',id',
            'Div'                 => 'nullable|string|max:100',
            'dist_channel'        => 'nullable|string|max:100',
            'pulau'               => 'nullable|string|max:100',
            'area'                => 'nullable|string|max:100',
            'Planner'             => 'nullable|string|max:100',
            'Monitoring'          => 'nullable|string|max:100',
            'biaya_kuli'          => 'nullable|string|max:30',
            'transport_lead_time' => 'nullable|string|max:20',
        ]);

        $this->normalizeBiayaKuli($request);
        $this->normalizeTransportLeadTime($request);

        $fields = [
            'Div', 'dist_channel', 'pulau', 'area',
            'Planner', 'Monitoring', 'biaya_kuli', 'transport_lead_time',
        ];

        $updateData = [];

        foreach ($fields as $field) {
            if ($request->filled($field)) {
                $updateData[$field] = $request->input($field);
            }
        }

        if (empty($updateData)) {
            return redirect()
                ->route($this->indexRouteName())
                ->with('error', 'Tidak ada field yang diisi, tidak ada data yang diubah.');
        }

        $count = TujuanFilter::whereIn('id', $request->ids)->update($updateData);

        return redirect()
            ->route($this->indexRouteName())
            ->with('success', "{$count} data tujuan berhasil diperbarui secara massal.");
    }

    /**
     * Hapus SEMUA data tujuan tanpa terkecuali.
     */
    public function destroyAll()
    {
        $this->checkRole();

        $count = TujuanFilter::count();

        // pakai delete(), bukan truncate(), supaya aman kalau ada
        // foreign key constraint dari tabel lain yang referensi ke sini
        TujuanFilter::query()->delete();

        return response()->json([
            'message' => "Semua data tujuan berhasil dihapus ({$count} data).",
        ]);
    }

    /**
     * Import massal dari CSV. Semua 10 kolom didukung.
     *
     * PENTING: setiap baris di CSV disimpan sebagai ROW BARU (create),
     * BUKAN updateOrCreate. Ini disengaja karena sumber data CSV bisa
     * punya baris dengan (Div, tujuan) yang sama persis tapi datanya
     * beda (misal Planner/area beda), dan semua baris itu tetap harus
     * masuk apa adanya -- tidak ada yang boleh saling menimpa.
     *
     * KONSEKUENSI: import ini TIDAK idempotent. Kalau file yang sama
     * di-import 2x, datanya akan DOBEL (bukan di-update). Kalau perlu
     * re-import bersih, hapus dulu data lama (pakai fitur "Hapus Semua
     * Data" atau "Hapus Terpilih") sebelum import ulang.
     */
    public function import(Request $request)
    {
        $this->checkRole();

        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ]);

        $path = $request->file('file')->getRealPath();
        $handle = fopen($path, 'r');

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle);
        $header = array_map(fn($h) => strtolower(trim($h)), $header);

        $idxDiv                = array_search('div', $header);
        $idxCustomer           = array_search('customer_id', $header);
        $idxTujuan             = array_search('tujuan', $header);
        $idxDistChannel        = array_search('dist_channel', $header);
        $idxPulau              = array_search('pulau', $header);
        $idxArea               = array_search('area', $header);
        $idxPlanner            = array_search('planner', $header);
        $idxMonitoring         = array_search('monitoring', $header);
        $idxBiayaKuli          = array_search('biaya_kuli', $header);
        $idxTransportLeadTime  = array_search('transport_lead_time', $header);

        if ($idxTujuan === false) {
            fclose($handle);
            return back()->with('error', 'Kolom tujuan tidak ditemukan.');
        }

        $inserted = 0;
        $skipped  = 0;
        $batch    = [];
        $now      = now();

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($handle)) !== false) {
                $tujuan = trim($row[$idxTujuan] ?? '');

                if ($tujuan === '') {
                    $skipped++;
                    continue;
                }

                $divValue = $idxDiv !== false ? trim($row[$idxDiv] ?? '') : null;
                $divValue = $divValue === '' ? null : $divValue;

                $biayaKuli = 0;

                if ($idxBiayaKuli !== false) {
                    $nilai = trim($row[$idxBiayaKuli] ?? '');

                    if ($nilai !== '') {
                        $biayaKuli = (int) str_replace(',', '', $nilai);
                    }
                }

                $transportLeadTime = null;

                if ($idxTransportLeadTime !== false) {
                    $nilaiLead = trim($row[$idxTransportLeadTime] ?? '');

                    if ($nilaiLead !== '') {
                        $transportLeadTime = (int) preg_replace('/[^0-9]/', '', $nilaiLead);
                    }
                }

                // create langsung, bukan updateOrCreate -- setiap baris
                // CSV WAJIB jadi row baru, meskipun (Div, tujuan) sama
                // dengan baris lain di file yang sama
                $batch[] = [
                    'Div'                 => $divValue,
                    'customer_id'         => $idxCustomer !== false ? trim($row[$idxCustomer]) : null,
                    'tujuan'              => $tujuan,
                    'dist_channel'        => $idxDistChannel !== false ? trim($row[$idxDistChannel]) : null,
                    'pulau'               => $idxPulau !== false ? trim($row[$idxPulau]) : null,
                    'area'                => $idxArea !== false ? trim($row[$idxArea]) : null,
                    'Planner'             => $idxPlanner !== false ? trim($row[$idxPlanner]) : null,
                    'Monitoring'          => $idxMonitoring !== false ? trim($row[$idxMonitoring]) : null,
                    'biaya_kuli'          => $biayaKuli,
                    'transport_lead_time' => $transportLeadTime,
                ];
                $inserted++;

                // insert per 500 baris supaya query tidak terlalu besar sekaligus
                if (count($batch) >= 500) {
                    TujuanFilter::insert($batch);
                    $batch = [];
                }
            }

            if (!empty($batch)) {
                TujuanFilter::insert($batch);
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
            $msg .= " {$skipped} baris dilewati karena kolom tujuan kosong.";
        }

        return back()->with('success', $msg);
    }
}