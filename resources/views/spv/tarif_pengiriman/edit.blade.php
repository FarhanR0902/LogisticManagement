@extends('layouts.app')

@section('content')

<div class="container-fluid">

    <div class="card">

        <div class="card-header">
            <h4 class="mb-0">Edit Tarif Pengiriman</h4>
        </div>

        <div class="card-body">

            <form action="{{ route('spvplanner.tarif.update', $data->id) }}"
                  method="POST">

                @csrf
                @method('PUT')

                <div class="row">

                    @php
                        $fields = [
                            'servc_agent' => 'Service Agent',
                            'ekpedisi' => 'Ekspedisi',
                            'sh' => 'SH',
                            'mobil' => 'Mobil',
                            'routew' => 'Route W',
                            'route' => 'Route',
                            'biaya_kirim' => 'Biaya Kirim',
                            'unit' => 'Unit',
                            'per' => 'Per',
                            'uom' => 'UOM',
                            'd' => 'D',
                            'tx' => 'TX',
                            'e' => 'E',
                            's_1' => 'S1',
                            's_2' => 'S2',
                            'valid_from' => 'Valid From',
                            'valid_to' => 'Valid To',
                        ];
                    @endphp

                    @foreach($fields as $name => $label)

                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                {{ $label }}
                            </label>

                            <input type="text"
                                   name="{{ $name }}"
                                   class="form-control @error($name) is-invalid @enderror"
                                   value="{{ old($name, $data->$name) }}">

                            @error($name)
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror

                        </div>

                    @endforeach

                </div>

                <div class="mt-3">

                    <button type="submit"
                            class="btn btn-success">
                        <i class="fas fa-save"></i>
                        Update
                    </button>

                    <a href="{{ route('spvplanner.tarif.index') }}"
                       class="btn btn-secondary">
                        Kembali
                    </a>

                </div>

            </form>

        </div>

    </div>

</div>

@endsection