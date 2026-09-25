<?php

namespace App\Http\Controllers\Kota;

use App\Http\Controllers\Controller;
use App\Imports\KotaImport;
use App\Imports\KotaAlasanPendingImport;
use App\Models\Kota;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException as ExcelValidationException;
use Throwable;

class KotaImportController extends Controller
{
    /**
     * Halaman form import
     */
    public function index()
    {
        return view('kota.import');
    }

    /**
     * Proses upload & import file Excel/CSV (data baru)
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:10240',
        ]);

        try {
            $import = new KotaImport();
            Excel::import($import, $request->file('file'));

            return back()->with('success', sprintf(
                'Import selesai. Berhasil: %d baris, Dilewati: %d baris.',
                $import->getImportedCount(),
                $import->getSkippedCount()
            ));
        } catch (ExcelValidationException $e) {
            return back()->withErrors($e->failures()->map->errors()->flatten()->all());
        } catch (Throwable $e) {
            Log::error('KotaImport failed: ' . $e->getMessage(), ['exception' => $e]);
            return back()->with('error', 'Import gagal: ' . $e->getMessage());
        }
    }

    /**
     * Proses upload file khusus update alasan_pending berdasarkan no_shipment + tujuan
     */
    public function importAlasanPending(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:10240',
        ]);

        try {
            $import = new KotaAlasanPendingImport();
            Excel::import($import, $request->file('file'));

            return back()->with('success', sprintf(
                'Update alasan pending selesai. Kombinasi ditemukan: %d, Baris ter-update: %d, Tidak ditemukan: %d, Dilewati: %d.',
                $import->getMatchedKeysCount(),
                $import->getUpdatedRowsCount(),
                $import->getNotFoundKeysCount(),
                $import->getSkippedCount()
            ));
        } catch (Throwable $e) {
            Log::error('KotaAlasanPendingImport failed: ' . $e->getMessage(), ['exception' => $e]);
            return back()->with('error', 'Update alasan pending gagal: ' . $e->getMessage());
        }
    }

    /**
     * Halaman dashboard Kota
     */
    public function dashboard()
    {
        return view('kota.dashboard');
    }

    /**
     * Data agregat untuk semua pivot dashboard (dipanggil via AJAX)
     */
    public function dashboardData(Request $request)
    {
        $query = Kota::query();

        if ($request->filled('year')) {
            $query->whereYear('tgl_do', $request->year);
        }
        if ($request->filled('month')) {
            $query->whereMonth('tgl_do', $request->month);
        }
        if ($request->filled('day')) {
            $query->whereDay('tgl_do', $request->day);
        }
        if ($request->filled('pic')) {
            $query->where('pic_driver', $request->pic);
        }

        // ================= TABLE 1: Avg Kubikasi & Tonase per PIC/Driver =================
        $rows = (clone $query)
            ->select('pic_driver', 'nama_driver', 'Kubikasi', 'tonase')
            ->whereNotNull('pic_driver')
            ->get();

        $avgPicGrouped = $rows->groupBy('pic_driver')->map(function ($items, $pic) {
            $byDriver = $items->groupBy('nama_driver')->map(function ($driverItems, $driverName) {
                return [
                    'nama_driver'  => $driverName ?: '(Tanpa Nama)',
                    'avg_kubikasi' => round($driverItems->avg('Kubikasi'), 2),
                    'avg_tonase'   => round($driverItems->avg('tonase'), 2),
                ];
            })->values();

            return [
                'pic'          => $pic,
                'avg_kubikasi' => round($items->avg('Kubikasi'), 2),
                'avg_tonase'   => round($items->avg('tonase'), 2),
                'drivers'      => $byDriver,
            ];
        })->values();

        $avgPic = [
            'data'               => $avgPicGrouped,
            'grand_avg_kubikasi' => round($rows->avg('Kubikasi'), 2),
            'grand_avg_tonase'   => round($rows->avg('tonase'), 2),
        ];

        // ================= TABLE 2: Count Toko/Tujuan per PIC =================
        $rowsToko = (clone $query)
            ->select('pic_toko', 'tujuan')
            ->whereNotNull('pic_toko')
            ->get();

        $countToko = $rowsToko->groupBy('pic_toko')->map(function ($items, $pic) {
            $tujuans = $items->groupBy('tujuan')->map(function ($tujuanItems, $tujuanName) {
                return [
                    'tujuan' => $tujuanName ?: '(Tanpa Tujuan)',
                    'total'  => $tujuanItems->count(),
                ];
            })->values();

            return [
                'pic'       => $pic,
                'total_pic' => $items->count(),
                'tujuans'   => $tujuans,
            ];
        })->values();

        // ================= TABLE 3: KPI PIC/Driver — Tepat Waktu vs Pending per Bulan =================
        $kpiPicDriver = $this->buildKpiPicDriver(clone $query);

        // ================= TABLE 4: Alasan Pending per Bulan > PIC =================
        $kpiAlasanPending = $this->buildKpiAlasanPending(clone $query);

        // ================= TABLE 5: Area (Tujuan) per Bulan =================
        $kpiArea = $this->buildKpiArea(clone $query);

        // ================= DAFTAR PIC UNTUK DROPDOWN FILTER =================
        // Sengaja TIDAK ikut dibatasi oleh filter 'pic' yang sedang aktif,
        // supaya daftar pilihan di dropdown tidak menyusut saat sudah difilter.
        $picOptions = Kota::whereNotNull('pic_driver')
            ->where('pic_driver', '!=', '')
            ->distinct()
            ->orderBy('pic_driver')
            ->pluck('pic_driver');

        return response()->json([
            'avg_pic'            => $avgPic,
            'count_toko'         => $countToko,
            'kpi_pic_driver'     => $kpiPicDriver,
            'kpi_alasan_pending' => $kpiAlasanPending,
            'kpi_area'           => $kpiArea,
            'pic_options'        => $picOptions,
        ]);
    }

    /**
     * TABLE 3: Bulan -> PIC (pic_driver) -> Nama Driver
     * Menghitung count Tepat Waktu / Pending + persentase + penilaian (target tetap)
     */
    private function buildKpiPicDriver($query)
    {
        $rows = $query
            ->select('tgl_do', 'pic_driver', 'nama_driver', 'waktu_pengiriman', 'jumlah_toko', 'Kubikasi', 'tonase')
            ->whereNotNull('pic_driver')
            ->whereNotNull('tgl_do')
            ->get();

        $months = $rows->groupBy(function ($row) {
            return Carbon::parse($row->tgl_do)->format('m');
        })->map(function ($monthRows, $bulan) {

            $pics = $monthRows->groupBy('pic_driver')->map(function ($picRows, $pic) {

                $drivers = $picRows->groupBy(function ($r) {
                    return $r->nama_driver ?: '(Tanpa Nama)';
                })->map(function ($driverRows, $driverName) {
                    $tepat   = $driverRows->where('waktu_pengiriman', 'TEPAT WAKTU')->count();
                    $pending = $driverRows->where('waktu_pengiriman', 'PENDING')->count();
                    $total   = $driverRows->count();

                    return [
                        'nama_driver'     => $driverName,
                        'tepat_waktu'     => $tepat,
                        'pending'         => $pending,
                        'total'           => $total,
                        'pct_tepat_waktu' => $total ? round($tepat / $total * 100, 2) : 0,
                        'pct_pending'     => $total ? round($pending / $total * 100, 2) : 0,
                    ];
                })->values();

                $tepat   = $picRows->where('waktu_pengiriman', 'TEPAT WAKTU')->count();
                $pending = $picRows->where('waktu_pengiriman', 'PENDING')->count();
                $total   = $picRows->count();

                // ================= SKOR JUMLAH TOKO =================
                // PENTING: skor dihitung PER SHIPMENT (per baris), berdasarkan nilai
                // jumlah_toko masing-masing baris terhadap rubrik scoreJumlahToko(),
                // lalu di rata-rata untuk mendapat "Penilaian" per PIC.
                //
                // Ini BUKAN "rata-rata jumlah_toko lalu discore" — pendekatan itu
                // menghasilkan angka yang berbeda dari pivot Excel sumbernya.
                // Contoh (IRSAN): jumlah_toko 1..8 dengan count 34/45/84/85/78/52/19/126
                // -> skor per baris 20/40/50/50/80/80/80/100
                // -> rata-rata tertimbang = 67.78 ≈ 68 (sesuai pivot), BUKAN 50
                //    (yang akan didapat kalau men-score rata-rata jumlah_toko ≈4.89).
                $avgJumlahToko = $picRows->avg('jumlah_toko');
                $avgJumlahToko = $avgJumlahToko !== null ? round($avgJumlahToko, 2) : null;

                $skorTokoPerBaris = $picRows
                    ->filter(function ($r) {
                        return $r->jumlah_toko !== null;
                    })
                    ->map(function ($r) {
                        return $this->scoreJumlahToko((float) $r->jumlah_toko);
                    });

                $penilaianToko = $skorTokoPerBaris->count()
                    ? (int) round($skorTokoPerBaris->avg())
                    : null;

                // ================= SKOR KUBIKASI/TONASE =================
                // Berdasarkan nilai TERTINGGI antara avg_kubikasi vs avg_tonase dalam bulan itu
                // (pendekatan ini sudah sesuai dengan pivot sumber — tidak diubah)
                $avgKubikasi = $picRows->avg('Kubikasi');
                $avgTonase   = $picRows->avg('tonase');
                $pctKubikasiTonase = ($avgKubikasi !== null || $avgTonase !== null)
                    ? round(max($avgKubikasi ?? 0, $avgTonase ?? 0), 2)
                    : null;

                return [
                    'pic'                       => $pic,
                    'tepat_waktu'               => $tepat,
                    'pending'                   => $pending,
                    'total'                     => $total,
                    'pct_tepat_waktu'           => $total ? round($tepat / $total * 100, 2) : 0,
                    'pct_pending'               => $total ? round($pending / $total * 100, 2) : 0,
                    'avg_jumlah_toko'           => $avgJumlahToko,
                    'penilaian_toko'            => $penilaianToko,
                    'pct_kubikasi_tonase'       => $pctKubikasiTonase,
                    'penilaian_kubikasi_tonase' => $this->scoreKubikasiTonase($pctKubikasiTonase),
                    'drivers'                   => $drivers,
                ];
            })->values();

            $tepat   = $monthRows->where('waktu_pengiriman', 'TEPAT WAKTU')->count();
            $pending = $monthRows->where('waktu_pengiriman', 'PENDING')->count();
            $total   = $monthRows->count();

            return [
                'bulan'             => $bulan,
                'pics'              => $pics,
                'total_tepat_waktu' => $tepat,
                'total_pending'     => $pending,
                'total'             => $total,
                'pct_tepat_waktu'   => $total ? round($tepat / $total * 100, 2) : 0,
                'pct_pending'       => $total ? round($pending / $total * 100, 2) : 0,
            ];
        })->sortKeys()->values();

        $grandTepat   = $rows->where('waktu_pengiriman', 'TEPAT WAKTU')->count();
        $grandPending = $rows->where('waktu_pengiriman', 'PENDING')->count();
        $grandTotal   = $rows->count();

        return [
            'months'      => $months,
            'grand_total' => [
                'tepat_waktu'     => $grandTepat,
                'pending'         => $grandPending,
                'total'           => $grandTotal,
                'pct_tepat_waktu' => $grandTotal ? round($grandTepat / $grandTotal * 100, 2) : 0,
                'pct_pending'     => $grandTotal ? round($grandPending / $grandTotal * 100, 2) : 0,
            ],
        ];
    }

    /**
     * Skor "Jumlah Toko" untuk SATU baris/shipment (nilai jumlah_toko mentah, bukan rata-rata).
     * Rubrik:
     *   Excellent            : 8         -> 100
     *   Good                 : 5 - 7     -> 80
     *   Need Improvement     : 3 - 4     -> 50
     *   Less Than Expectation: 2         -> 40
     *   Fail                 : 1         -> 20
     * (di-generalisasi jadi >= agar tetap jalan walau nilainya di atas 8)
     */
    private function scoreJumlahToko(?float $jumlahToko): ?int
    {
        if ($jumlahToko === null) {
            return null;
        }

        return match (true) {
            $jumlahToko >= 8 => 100,
            $jumlahToko >= 5 => 80,
            $jumlahToko >= 3 => 50,
            $jumlahToko >= 2 => 40,
            $jumlahToko >= 1 => 20,
            default          => 0,
        };
    }

    /**
     * Skor "Kubikasi/Tonase" berdasarkan persentase tertinggi antara avg_kubikasi vs avg_tonase.
     * Rubrik:
     *   Excellent            : > 85%     -> 100
     *   Good                 : 60% - 85% -> 80
     *   Need Improvement     : 30% - 60% -> 60
     *   Less Than Expectation: 15% - 30% -> 40
     *   Fail                 : < 15%     -> 20
     */
    private function scoreKubikasiTonase(?float $pct): ?int
    {
        if ($pct === null) {
            return null;
        }

        return match (true) {
            $pct > 85  => 100,
            $pct >= 60 => 80,
            $pct >= 30 => 60,
            $pct >= 15 => 40,
            default    => 20,
        };
    }

    /**
     * TABLE 4: Bulan -> Alasan Pending (None kalau kosong) -> PIC
     * (dipakai di dashboard lama, per-bulan % dihitung dari total bulan itu sendiri)
     */
    private function buildKpiAlasanPending($query)
    {
        $rows = $query
            ->select('tgl_do', 'pic_driver', 'alasan_pending', 'waktu_pengiriman')
            ->whereNotNull('tgl_do')
            ->get();

        $months = $rows->groupBy(function ($row) {
            return Carbon::parse($row->tgl_do)->format('m');
        })->map(function ($monthRows, $bulan) {

            $alasanGroups = $monthRows->groupBy(function ($r) {
                return $r->alasan_pending ?: 'None';
            })->map(function ($alasanRows, $alasan) {

                $pics = $alasanRows->groupBy(function ($r) {
                    return $r->pic_driver ?: '(Tanpa PIC)';
                })->map(function ($picRows, $pic) {
                    $tepat   = $picRows->where('waktu_pengiriman', 'TEPAT WAKTU')->count();
                    $pending = $picRows->where('waktu_pengiriman', 'PENDING')->count();
                    $total   = $picRows->count();

                    return [
                        'pic'             => $pic,
                        'tepat_waktu'     => $tepat,
                        'pending'         => $pending,
                        'total'           => $total,
                        'pct_tepat_waktu' => $total ? round($tepat / $total * 100, 2) : 0,
                        'pct_pending'     => $total ? round($pending / $total * 100, 2) : 0,
                    ];
                })->values();

                $tepat   = $alasanRows->where('waktu_pengiriman', 'TEPAT WAKTU')->count();
                $pending = $alasanRows->where('waktu_pengiriman', 'PENDING')->count();
                $total   = $alasanRows->count();

                return [
                    'alasan'          => $alasan,
                    'pics'            => $pics,
                    'tepat_waktu'     => $tepat,
                    'pending'         => $pending,
                    'total'           => $total,
                    'pct_tepat_waktu' => $total ? round($tepat / $total * 100, 2) : 0,
                    'pct_pending'     => $total ? round($pending / $total * 100, 2) : 0,
                ];
            })->values();

            $tepat   = $monthRows->where('waktu_pengiriman', 'TEPAT WAKTU')->count();
            $pending = $monthRows->where('waktu_pengiriman', 'PENDING')->count();
            $total   = $monthRows->count();

            return [
                'bulan'         => $bulan,
                'alasan_groups' => $alasanGroups,
                'total'         => $total,
                'tepat_waktu'   => $tepat,
                'pending'       => $pending,
            ];
        })->sortKeys()->values();

        return ['months' => $months];
    }

    /**
     * TABLE 5: Bulan -> Area (kolom 'tujuan') -> count & %
     * (dipakai di dashboard lama)
     */
    private function buildKpiArea($query)
    {
        $rows = $query
            ->select('tgl_do', 'area_besar')
            ->whereNotNull('tgl_do')
            ->get();

        $months = $rows->groupBy(function ($row) {
            return Carbon::parse($row->tgl_do)->format('m');
        })->map(function ($monthRows, $bulan) {

            $total = $monthRows->count();

            $areas = $monthRows->groupBy(function ($r) {
                return $r->area_besar ?: '(Tanpa Area)';
            })->map(function ($areaRows, $area) use ($total) {
                $count = $areaRows->count();
                return [
                    'area'  => $area,
                    'total' => $count,
                    'pct'   => $total ? round($count / $total * 100, 2) : 0,
                ];
            })->sortByDesc('total')->values();

            return [
                'bulan' => $bulan,
                'areas' => $areas,
                'total' => $total,
            ];
        })->sortKeys()->values();

        return ['months' => $months];
    }

    /**
     * Halaman KPI Analysis (Alasan Pending & Area Besar, dengan selectpicker)
     */
    public function kpiAnalysis()
    {
        return view('kota.kpi-analysis');
    }

    /**
     * Data untuk halaman KPI Analysis
     * (% dihitung dari grand total keseluruhan data, sesuai pola pivot di gambar)
     */
    public function kpiAnalysisData(Request $request)
    {
        $query = Kota::query();

        if ($request->filled('year')) {
            $query->whereYear('tgl_do', $request->year);
        }
        if ($request->filled('month')) {
            $query->whereMonth('tgl_do', $request->month);
        }

        $allRows = $query->select('tgl_do', 'pic_driver', 'area_besar', 'alasan_pending', 'waktu_pengiriman')
            ->whereNotNull('tgl_do')
            ->get();

        $grandTotal = $allRows->count();

        return response()->json([
            'alasan_pending' => $this->buildAlasanPendingPivot($allRows, $grandTotal),
            'area_besar'     => $this->buildAreaBesarPivot($allRows, $grandTotal),
        ]);
    }

    /**
     * Pivot: Bulan -> Alasan Pending ('-' kalau kosong) -> PIC
     */
    private function buildAlasanPendingPivot($allRows, $grandTotal)
    {
        $months = $allRows->groupBy(function ($row) {
            return Carbon::parse($row->tgl_do)->format('m');
        })->map(function ($monthRows, $bulan) use ($grandTotal) {

            $alasanGroups = $monthRows->groupBy(function ($r) {
                return $r->alasan_pending ?: '-';
            })->map(function ($alasanRows, $alasan) use ($grandTotal) {

                $pics = $alasanRows->groupBy(function ($r) {
                    return $r->pic_driver ?: '(Tanpa PIC)';
                })->map(function ($picRows, $pic) use ($grandTotal) {
                    $tepat   = $picRows->where('waktu_pengiriman', 'TEPAT WAKTU')->count();
                    $pending = $picRows->where('waktu_pengiriman', 'PENDING')->count();
                    $total   = $picRows->count();

                    return [
                        'pic'             => $pic,
                        'tepat_waktu'     => $tepat,
                        'pending'         => $pending,
                        'pct_tepat_waktu' => $grandTotal ? round($tepat / $grandTotal * 100, 2) : 0,
                        'pct_pending'     => $grandTotal ? round($pending / $grandTotal * 100, 2) : 0,
                        'total'           => $total,
                        'total_pct'       => $grandTotal ? round($total / $grandTotal * 100, 2) : 0,
                    ];
                })->values();

                $tepat   = $alasanRows->where('waktu_pengiriman', 'TEPAT WAKTU')->count();
                $pending = $alasanRows->where('waktu_pengiriman', 'PENDING')->count();
                $total   = $alasanRows->count();

                return [
                    'alasan'          => $alasan,
                    'pics'            => $pics,
                    'tepat_waktu'     => $tepat,
                    'pending'         => $pending,
                    'pct_tepat_waktu' => $grandTotal ? round($tepat / $grandTotal * 100, 2) : 0,
                    'pct_pending'     => $grandTotal ? round($pending / $grandTotal * 100, 2) : 0,
                    'total'           => $total,
                    'total_pct'       => $grandTotal ? round($total / $grandTotal * 100, 2) : 0,
                ];
            })->values();

            return [
                'bulan'         => $bulan,
                'alasan_groups' => $alasanGroups,
            ];
        })->sortKeys()->values();

        return ['months' => $months];
    }

    /**
     * Pivot: Bulan -> Area Besar (kolom 'area_besar') -> count & %
     */
    private function buildAreaBesarPivot($allRows, $grandTotal)
    {
        $months = $allRows->groupBy(function ($row) {
            return Carbon::parse($row->tgl_do)->format('m');
        })->map(function ($monthRows, $bulan) use ($grandTotal) {

            $areas = $monthRows->groupBy(function ($r) {
                return $r->area_besar ?: '(Tanpa Area)';
            })->map(function ($areaRows, $area) use ($grandTotal) {
                $tepat   = $areaRows->where('waktu_pengiriman', 'TEPAT WAKTU')->count();
                $pending = $areaRows->where('waktu_pengiriman', 'PENDING')->count();
                $total   = $areaRows->count();

                return [
                    'area'            => $area,
                    'tepat_waktu'     => $tepat,
                    'pending'         => $pending,
                    'pct_tepat_waktu' => $grandTotal ? round($tepat / $grandTotal * 100, 2) : 0,
                    'pct_pending'     => $grandTotal ? round($pending / $grandTotal * 100, 2) : 0,
                    'total'           => $total,
                    'total_pct'       => $grandTotal ? round($total / $grandTotal * 100, 2) : 0,
                ];
            })->sortByDesc('total')->values();

            return [
                'bulan' => $bulan,
                'areas' => $areas,
            ];
        })->sortKeys()->values();

        return ['months' => $months];
    }

    /**
     * Data untuk DataTables server-side (dipanggil via AJAX)
     */
    public function dataAjax(Request $request)
    {
        $query = Kota::query();

        if ($request->filled('tujuan')) {
            $query->where('tujuan', $request->tujuan);
        }
        if ($request->filled('no_shipment')) {
            $query->where('no_shipment', 'like', '%' . $request->no_shipment . '%');
        }
        if ($request->filled('no_do')) {
            $query->where('no_do', 'like', '%' . $request->no_do . '%');
        }
        if ($request->filled('ekspedisi')) {
            $query->where('EKPEDISI', $request->ekspedisi);
        }
        if ($request->filled('pengiriman_optimal')) {
            $query->where('pengiriman_optimal', $request->pengiriman_optimal);
        }
        if ($request->filled('waktu_pengiriman')) {
            $query->where('waktu_pengiriman', $request->waktu_pengiriman);
        }
        if ($request->filled('alasan_pending')) {
            $query->where('alasan_pending', 'like', '%' . $request->alasan_pending . '%');
        }
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('tgl_do', [$request->start_date, $request->end_date]);
        }

        if ($request->filled('search.value')) {
            $search = $request->input('search.value');
            $query->where(function ($q) use ($search) {
                $q->where('no_do', 'like', "%{$search}%")
                  ->orWhere('no_shipment', 'like', "%{$search}%")
                  ->orWhere('tujuan', 'like', "%{$search}%")
                  ->orWhere('nama_driver', 'like', "%{$search}%")
                  ->orWhere('no_pol', 'like', "%{$search}%")
                  ->orWhere('EKPEDISI', 'like', "%{$search}%")
                  ->orWhere('alasan_pending', 'like', "%{$search}%");
            });
        }

        $totalRecords    = Kota::count();
        $filteredRecords = (clone $query)->count();

        $orderColumnIndex = $request->input('order.0.column');
        $orderDir         = $request->input('order.0.dir', 'desc');
        $columns          = $request->input('columns', []);

        if ($orderColumnIndex !== null && isset($columns[$orderColumnIndex]['data'])) {
            $orderColumn = $columns[$orderColumnIndex]['data'];
            $query->orderBy($orderColumn, $orderDir);
        } else {
            $query->orderByDesc('created_at');
        }

        $start  = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 25);

        $data = $length > 0
            ? $query->skip($start)->take($length)->get()
            : $query->get();

        return response()->json([
            'draw'            => (int) $request->input('draw', 1),
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data'            => $data,
        ]);
    }
}