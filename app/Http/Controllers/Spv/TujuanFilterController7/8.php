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
     * Cek role SPV Planner
     */
    private function checkRole()
    {
        if (!auth()->check() || auth()->user()->role !== 'spvplanner','spvmonitoring') {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }
    }

    /**
     * Aturan validasi dipusatkan supaya store() & update() konsisten,
     * sesuai header: id, Div, customer_id, tujuan, dist_channel, pulau,
     * area, Planner, Monitoring, biaya_kuli, transport_lead_time
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
                    ? Rule::unique(self::TABLE, 'tujuan')->ignore($ignoreId)
                    : Rule::unique(self::TABLE, 'tujuan'),
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
            'tujuan.unique'   => 'Tujuan ini sudah terdaftar, silakan edit data yang sudah ada.',
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
     * List + search + filter by area, dengan pagination.
     */
    public function index(Request $request)
    {
        $this->checkRole();

        $query = TujuanFilter::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('tujuan', 'like', "%{$search}%");
        }

        if ($request->filled('area')) {
            $query->where('area', $request->area);
        }

        $data = $query->orderBy('tujuan')->paginate(20)->withQueryString();

        $list_area = TujuanFilter::select('area')
            ->distinct()
            ->orderBy('area')
            ->pluck('area');

        return view('spv.tujuan_filter.index', compact('data', 'list_area'));
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

        $validated['is_active'] = true;

        TujuanFilter::create($validated);

        return redirect()
            ->route('spvplanner.tujuan.index')
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
                'is_active' => 'nullable|boolean',
            ]),
            $this->validationMessages()
        );

        $validated['is_active'] = $request->boolean('is_active', true);

        $data->update($validated);

        return redirect()
            ->route('spvplanner.tujuan.index')
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
            ->route('spvplanner.tujuan.index')
            ->with('success', "Tujuan \"{$tujuan}\" berhasil dihapus.");
    }

    /**
     * Import massal dari CSV. Semua 10 kolom didukung.
     * Baris dengan tujuan yang sudah ada akan DITIMPA (updateOrCreate).
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
        $updated  = 0;

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($handle)) !== false) {
                $tujuan = trim($row[$idxTujuan] ?? '');

                if ($tujuan === '') {
                    continue;
                }

                // Jika biaya_kuli tidak ada atau kosong, isi 0
                $biayaKuli = 0;

                if ($idxBiayaKuli !== false) {
                    $nilai = trim($row[$idxBiayaKuli] ?? '');

                    if ($nilai !== '') {
                        $biayaKuli = (int) str_replace(',', '', $nilai);
                    }
                }

                // Jika transport_lead_time tidak ada atau kosong, isi null
                $transportLeadTime = null;

                if ($idxTransportLeadTime !== false) {
                    $nilaiLead = trim($row[$idxTransportLeadTime] ?? '');

                    if ($nilaiLead !== '') {
                        $transportLeadTime = (int) preg_replace('/[^0-9]/', '', $nilaiLead);
                    }
                }

                $model = TujuanFilter::updateOrCreate(
                    ['tujuan' => $tujuan],
                    [
                        'Div'                  => $idxDiv !== false ? trim($row[$idxDiv]) : null,
                        'customer_id'          => $idxCustomer !== false ? trim($row[$idxCustomer]) : null,
                        'dist_channel'         => $idxDistChannel !== false ? trim($row[$idxDistChannel]) : null,
                        'pulau'                => $idxPulau !== false ? trim($row[$idxPulau]) : null,
                        'area'                 => $idxArea !== false ? trim($row[$idxArea]) : null,
                        'Planner'              => $idxPlanner !== false ? trim($row[$idxPlanner]) : null,
                        'Monitoring'           => $idxMonitoring !== false ? trim($row[$idxMonitoring]) : null,
                        'biaya_kuli'           => $biayaKuli,
                        'transport_lead_time'  => $transportLeadTime,
                    ]
                );

                if ($model->wasRecentlyCreated) {
                    $inserted++;
                } else {
                    $updated++;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($handle);

            return back()->with('error', 'Import gagal: ' . $e->getMessage());
        }

        fclose($handle);

        return back()->with(
            'success',
            "Import selesai. {$inserted} data baru, {$updated} data diperbarui."
        );
    }
}   