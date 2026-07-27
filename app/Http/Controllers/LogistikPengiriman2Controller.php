<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LogistikPengiriman2;

class LogistikPengiriman2Controller extends Controller
{
    public function index(Request $request)
    {
        $query = LogistikPengiriman2::query();

        // FILTER
        if ($request->area) {
            $query->where('area', $request->area);
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('no_shipment', 'like', "%$search%")
                  ->orWhere('tujuan', 'like', "%$search%")
                  ->orWhere('dist_channel', 'like', "%$search%");
            });
        }

        $data = $query->orderBy('id', 'desc')->get();

        $areaList = LogistikPengiriman2::select('area')->distinct()->pluck('area');

        return view('logistik2.index', compact('data', 'areaList'));
    }

    public function show($id)
    {
        $data = LogistikPengiriman2::findOrFail($id);
        return view('logistik2.show', compact('data'));
    }
}