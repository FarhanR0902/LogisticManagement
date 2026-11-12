@php
    $qs = collect(request()->query())->except(['month', 'year', 'page'])->all();
@endphp

@forelse($items as $a)
    @php
        $pp    = explode('-', (string) $a->periode_shipment);
        $ada   = count($pp) === 2;
        $label = $ada ? (($namaBulan[$pp[0]] ?? $pp[0]) . ' ' . $pp[1]) : 'Tanpa periode';
        $url   = route('manager.gabungan.alert', array_merge($qs, [
            'tipe'             => $tipe,
            'nama'             => $nama,
            'periode_shipment' => $ada ? $a->periode_shipment : 'KOSONG',
        ]));
    @endphp
    <a href="{{ $url }}" class="badge badge-red"
       style="margin:2px 2px 2px 0; text-decoration:none;">
        ⚠️ {{ $label }}: {{ number_format($a->total) }}
    </a>
@empty
    <span class="badge badge-green">✓ Aman</span>
@endforelse