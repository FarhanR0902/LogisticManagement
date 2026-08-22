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
         * Cek role SPV Planner
         */
        private function checkRole()
        {
            if (!auth()->check() || auth()->user()->role !== 'spvplanner') {
                abort(403, 'Anda tidak memiliki akses ke halaman ini.');
            }
        }

        /**
         * Menampilkan data + search + filter area
         */
        public function index(Request $request)
        {
            $this->checkRole();

            $query = TujuanFilter::query();

            // Search
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where('tujuan', 'like', "%{$search}%");
            }

            // Filter area
            if ($request->filled('area')) {
                $query->where('area', $request->area);
            }

            $data = $query
                ->orderBy('tujuan')
                ->paginate(20)
                ->withQueryString();

            // Dropdown daftar area yang sudah ada, buat filter & datalist saran input
            $list_area = TujuanFilter::select('area')
                ->distinct()
                ->orderBy('area')
                ->pluck('area');

            return view('spv.tujuan_filter.index', compact('data', 'list_area'));
        }

        /**
         * Form tambah
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
         * Simpan data
         */
        public function store(Request $request)
        {
            $this->checkRole();

        $validated = $request->validate([
        'tujuan'        => 'required|string|max:255|unique:tujuanfillterr,tujuan',
        'area'          => 'required|string|max:100',
        'dist_channel'  => 'nullable|string|max:100',
    ], [
        'tujuan.unique' => 'Tujuan ini sudah terdaftar, silakan edit data yang sudah ada.',
    ]);
            $validated['is_active'] = true;

            TujuanFilter::create($validated);

            return redirect()
                ->route('spvplanner.tujuan.index')
                ->with('success', "Tujuan \"{$validated['tujuan']}\" berhasil ditambahkan ke area {$validated['area']}.");
        }

        /**
         * Form edit
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
         * Update
         */
        public function update(Request $request, $id)
        {
            $this->checkRole();

            $data = TujuanFilter::findOrFail($id);

            $validated = $request->validate([
                'tujuan' => ['required', 'string', 'max:255', Rule::unique('tujuan_filters', 'tujuan')->ignore($data->id)],
                'area' => 'required|string|max:100',
                'dist_channel' => 'nullable|string|max:100',
                'is_active' => 'nullable|boolean',
            ]);

            $validated['is_active'] = $request->boolean('is_active', true);

            $data->update($validated);

            return redirect()
                ->route('spvplanner.tujuan.index')
                ->with('success', 'Data tujuan berhasil diperbarui.');
        }

        /**
         * Hapus
         */
    public function import(Request $request)
    {
        $this->checkRole();

        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ]);

        $path = $request->file('file')->getRealPath();
        $handle = fopen($path, 'r');

        // Hilangkan BOM UTF-8
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle);
        $header = array_map(fn($h) => strtolower(trim($h)), $header);

        // Ambil index kolom
        $idxDiv         = array_search('div', $header);
        $idxCustomer    = array_search('customer_id', $header);
        $idxTujuan      = array_search('tujuan', $header);
        $idxDistChannel = array_search('dist_channel', $header);
        $idxPulau       = array_search('pulau', $header);
        $idxArea        = array_search('area', $header);
        $idxPlanner     = array_search('planner', $header);
        $idxMonitoring  = array_search('monitoring', $header);
        $idxBiayaKuli   = array_search('biaya_kuli', $header);

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

                if ($tujuan == '') {
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
            

                $model = TujuanFilter::updateOrCreate(

                    ['tujuan' => $tujuan],

                    [
                        'Div'          => $idxDiv !== false ? trim($row[$idxDiv]) : null,
                        'customer_id'  => $idxCustomer !== false ? trim($row[$idxCustomer]) : null,
                        'dist_channel' => $idxDistChannel !== false ? trim($row[$idxDistChannel]) : null,
                        'pulau'        => $idxPulau !== false ? trim($row[$idxPulau]) : null,
                        'area'         => $idxArea !== false ? trim($row[$idxArea]) : null,
                        'Planner'      => $idxPlanner !== false ? trim($row[$idxPlanner]) : null,
                        'Monitoring'   => $idxMonitoring !== false ? trim($row[$idxMonitoring]) : null,
                        'biaya_kuli'   => $biayaKuli,
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