@extends('layouts.app')

@push('styles')
<style>
    body{
        font-family:'Segoe UI',Arial,sans-serif;
        background:#f1f5f9;
        color:#1e293b;
    }

    .container-fluid{
        max-width:1200px;
        margin:auto;
        padding:30px 20px;
    }

    .card{
        border:none;
        border-radius:16px;
        overflow:hidden;
        box-shadow:0 4px 20px rgba(0,0,0,.08);
    }

    .card-header{
        background:#03c03c;
        color:#fff;
        padding:18px 25px;
    }

    .card-header h4{
        margin:0;
        font-size:28px;
        font-weight:700;
    }

    .card-body{
        padding:30px;
    }

    .row{
        row-gap:18px;
    }

    .form-label{
        display:block;
        font-weight:600;
        color:#334155;
        margin-bottom:7px;
        font-size:15px;
    }

    .form-control{
        width:100%;
        height:45px;
        border:1px solid #cbd5e1;
        border-radius:10px;
        padding:10px 14px;
        font-size:15px;
        transition:.25s;
        background:#fff;
    }

    .form-control:focus{
        border-color:#2563eb;
        box-shadow:0 0 0 3px rgba(37,99,235,.15);
        outline:none;
    }

    .invalid-feedback{
        font-size:13px;
    }

    .action-button{
        margin-top:30px;
        display:flex;
        gap:12px;
    }

    .btn{
        padding:11px 24px;
        border-radius:10px;
        font-weight:600;
        font-size:15px;
        transition:.25s;
    }

    .btn-primary{
        background:linear-gradient(135deg,#2563eb,#1d4ed8);
        border:none;
    }

    .btn-primary:hover{
        transform:translateY(-2px);
        box-shadow:0 6px 15px rgba(37,99,235,.25);
    }

    .btn-secondary{
        background:#64748b;
        border:none;
        color:#fff;
    }

    .btn-secondary:hover{
        background:#475569;
        color:#fff;
    }

    @media(max-width:992px){

        .col-lg-4{
            margin-bottom:15px;
        }

    }

    @media(max-width:768px){

        .container-fluid{
            padding:15px;
        }

        .card-body{
            padding:20px;
        }

        .card-header h4{
            font-size:22px;
        }

        .action-button{
            flex-direction:column;
        }

        .action-button .btn{
            width:100%;
        }

    }
</style>
@endpush

@section('content')

<div class="container-fluid">

    <div class="card">

        <div class="card-header">
            <h4>Tambah Tarif Pengiriman</h4>
        </div>

        <div class="card-body">

            <form action="{{ route('spvplanner.tarif.store') }}" method="POST">

                @csrf

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

                <div class="row">

                    @foreach($fields as $name => $label)

                    <div class="col-lg-4 col-md-6">

                        <label class="form-label">
                            {{ $label }}
                        </label>

                        <input
                            type="text"
                            name="{{ $name }}"
                            value="{{ old($name) }}"
                            class="form-control @error($name) is-invalid @enderror">

                        @error($name)
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                        @enderror

                    </div>

                    @endforeach

                </div>

                <div class="action-button">

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>
                        Simpan
                    </button>

                    <a href="{{ route('spvplanner.tarif.index') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>
                        Kembali
                    </a>

                </div>

            </form>

        </div>

    </div>

</div>

@endsection