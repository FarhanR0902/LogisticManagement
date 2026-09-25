<?php

namespace App\Http\Controllers\Kota;

use App\Http\Controllers\Controller;
use App\Imports\KotaImport;
use App\Models\Kota;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class KotaImportController extends Controller
{
    public function index()
    {
        return view('kota.import');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        $import = new KotaImport();
        Excel::import($import, $request->file('file'));

        return redirect()
            ->route('kota.import.index')
            ->with(
                'success',
                "Import selesai. {$import->getImportedCount()} baris diimport, {$import->getSkippedCount()} baris dilewati."
            );
    }

public function dataAjax(Request $request)
{
    $draw = (int) $request->input('draw', 1);
    $start = (int) $request->input('start', 0);
    $length = (int) $request->input('length', 25);
    $searchValue = trim((string) $request->input('search.value', ''));

    $query = Kota::query();

    $recordsTotal = Kota::count();

    if ($searchValue !== '') {
        $query->where(function ($q) use ($searchValue) {

            $cols = [
                'kode_gudang',
                'no_so',
                'no_do',
                'customer_id',
                'tujuan',
                'kode_barang',
                'nama_barang',
                'no_pol',
                'nama_driver',
                'no_shipment',
                'EKPEDISI',
                'jenis_mobil',
                'jenis_tonase',
                'Kubikasi',
                'tonase',
                'pengiriman_optimal',
                'jumlah_toko',
                'pic_toko',
                'pic_driver',
                'waktu_pengiriman',
            ];

            foreach ($cols as $col) {
                $q->orWhere($col, 'like', "%{$searchValue}%");
            }

        });
    }

    $recordsFiltered = (clone $query)->count();

    $rows = $query
        ->orderByDesc('id')
        ->skip($start)
        ->take($length)
        ->get();

    $data = $rows->map(function ($r) {

        return [
            'kode_gudang' => $r->kode_gudang ?? '-',
            'no_so' => $r->no_so ?? '-',
            'no_do' => $r->no_do ?? '-',

            'create_on' => $r->create_on
                ? date('d-m-Y', strtotime($r->create_on))
                : '-',

            'tgl_do' => $r->tgl_do
                ? date('d-m-Y', strtotime($r->tgl_do))
                : '-',

            'tanggal_kirim' => $r->tanggal_kirim
                ? date('d-m-Y', strtotime($r->tanggal_kirim))
                : '-',

            'customer_id' => $r->customer_id ?? '-',
            'tujuan' => $r->tujuan ?? '-',
            'kode_barang' => $r->kode_barang ?? '-',
            'nama_barang' => $r->nama_barang ?? '-',
            'qty_prdk' => $r->qty_prdk ?? 0,
            'no_pol' => $r->no_pol ?? '-',
            'nama_driver' => $r->nama_driver ?? '-',
            'no_shipment' => $r->no_shipment ?? '-',
            'EKPEDISI' => $r->EKPEDISI ?? '-',
            'jenis_mobil' => $r->jenis_mobil ?? '-',
            'jenis_tonase' => $r->jenis_tonase ?? '-',
            'Kubikasi' => $r->Kubikasi ?? 0,
            'tonase' => $r->tonase ?? 0,
            'pengiriman_optimal' => $r->pengiriman_optimal ?? '-',
            'jumlah_toko' => $r->jumlah_toko ?? 0,
            'pic_toko' => $r->pic_toko ?? '-',
            'pic_driver' => $r->pic_driver ?? '-',
            'waktu_pengiriman' => $r->waktu_pengiriman ?? '-',
        ];

    })->values();

    return response()->json([
        'draw' => $draw,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data' => $data,
    ]);
}
}