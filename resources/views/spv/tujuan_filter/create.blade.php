@extends('layouts.app')

@section('content')

<style>
.tf-form-page {
    padding: 20px 15px 40px;
    max-width: 720px;
    margin: 0 auto;
}

.tf-form-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 18px;
}

.tf-form-title {
    margin: 0;
    color: #1e293b;
    font-size: 22px;
    font-weight: 700;
}

.tf-form-subtitle {
    display: block;
    margin-top: 4px;
    color: #94a3b8;
    font-size: 12px;
}

.tf-btn-back {
    display: inline-flex;
    align-items: center;
    gap: 6px;

    height: 38px;
    padding: 0 15px;

    border: 1px solid #e2e8f0;
    border-radius: 7px;

    background: #f8fafc;
    color: #475569 !important;

    font-size: 12px;
    font-weight: 600;

    text-decoration: none !important;
}

.tf-btn-back:hover {
    background: #f1f5f9;
    color: #334155 !important;
}

.tf-form-card {
    background: #fff;
    border: 0;
    border-radius: 10px;
    box-shadow: 0 2px 12px rgba(15, 23, 42, .06);
    padding: 24px;
}

.tf-form-group {
    margin-bottom: 18px;
}

.tf-form-label {
    display: block;
    margin-bottom: 7px;

    color: #334155;
    font-size: 12px;
    font-weight: 700;
}

.tf-form-label .required {
    color: #dc2626;
}

.tf-form-input {
    width: 100%;
    height: 40px;

    padding: 0 13px;

    border: 1px solid #dbe2ea;
    border-radius: 7px;

    color: #334155;
    font-size: 13px;

    outline: none !important;
    box-shadow: none !important;
}

.tf-form-input:focus {
    border-color: #93c5fd;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .08) !important;
}

.tf-form-actions {
    display: flex;
    gap: 8px;
    margin-top: 22px;
}

.tf-btn-submit {
    height: 40px;
    padding: 0 20px;

    border: 0;
    border-radius: 7px;

    background: #2563eb;
    color: #fff;

    font-size: 13px;
    font-weight: 600;
}

.tf-btn-submit:hover {
    background: #1d4ed8;
    color: #fff;
}

.tf-btn-cancel {
    height: 40px;
    padding: 0 20px;

    border: 1px solid #e2e8f0;
    border-radius: 7px;

    background: #f8fafc;
    color: #475569;

    font-size: 13px;
    font-weight: 600;

    text-decoration: none !important;
    display: inline-flex;
    align-items: center;
}

.tf-btn-cancel:hover {
    background: #f1f5f9;
    color: #334155;
}
</style>

<div class="container-fluid tf-form-page">

    <div class="tf-form-header">
        <div>
            <h4 class="tf-form-title">Tambah Tujuan</h4>
            <span class="tf-form-subtitle">Tambahkan data tujuan &amp; area baru</span>
        </div>
        <a href="{{ route('spvplanner.tujuan.index') }}" class="tf-btn-back">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger mb-3">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="tf-form-card">

        <form method="POST" action="{{ route('spvplanner.tujuan.store') }}">
            @csrf

            <div class="row">

                <div class="col-md-6 tf-form-group">
                    <label class="tf-form-label">Div</label>
                    <input type="text" name="Div" class="tf-form-input"
                           value="{{ old('Div') }}" maxlength="50"
                           placeholder="Divisi">
                </div>

                <div class="col-md-6 tf-form-group">
                    <label class="tf-form-label">Customer ID</label>
                    <input type="text" name="customer_id" class="tf-form-input"
                           value="{{ old('customer_id') }}" maxlength="50"
                           placeholder="Customer ID">
                </div>

            </div>

            <div class="tf-form-group">
                <label class="tf-form-label">Tujuan <span class="required">*</span></label>
                <input type="text" name="tujuan" class="tf-form-input"
                       value="{{ old('tujuan') }}" required maxlength="255"
                       placeholder="Nama tujuan / customer">
            </div>

            <div class="row">

                <div class="col-md-6 tf-form-group">
                    <label class="tf-form-label">Area <span class="required">*</span></label>
                    <input type="text" name="area" class="tf-form-input" list="area-list"
                           value="{{ old('area') }}" required maxlength="100"
                           placeholder="Nama area">
                    <datalist id="area-list">
                        @foreach ($list_area as $area)
                            <option value="{{ $area }}">
                        @endforeach
                    </datalist>
                </div>

                <div class="col-md-6 tf-form-group">
                    <label class="tf-form-label">Pulau</label>
                    <input type="text" name="pulau" class="tf-form-input"
                           value="{{ old('pulau') }}" maxlength="100"
                           placeholder="Nama pulau">
                </div>

            </div>

            <div class="tf-form-group">
                <label class="tf-form-label">Distribution Channel</label>
                <input type="text" name="dist_channel" class="tf-form-input"
                       value="{{ old('dist_channel') }}" maxlength="100"
                       placeholder="Opsional">
            </div>

            <div class="row">

                <div class="col-md-6 tf-form-group">
                    <label class="tf-form-label">Planner</label>
                    <input type="text" name="Planner" class="tf-form-input"
                           value="{{ old('Planner') }}" maxlength="100"
                           placeholder="Nama planner">
                </div>

                <div class="col-md-6 tf-form-group">
                    <label class="tf-form-label">Monitoring</label>
                    <input type="text" name="Monitoring" class="tf-form-input"
                           value="{{ old('Monitoring') }}" maxlength="100"
                           placeholder="Nama PIC monitoring">
                </div>

            </div>

            <div class="tf-form-group">
                <label class="tf-form-label">Biaya Kuli</label>
                <input type="text" name="biaya_kuli" class="tf-form-input"
                       value="{{ old('biaya_kuli') }}" maxlength="30"
                       placeholder="Contoh: 50000">
            </div>

             <div class="tf-form-group">
                <label class="tf-form-label">Lead time Customer</label>
               <input type="number" name="transport_lead_time" class="tf-form-input"
       value="{{ old('transport_lead_time') }}" min="0"
       placeholder="Lead Time Customer (Hari)">
            </div>

            <div class="tf-form-actions">
                <button type="submit" class="tf-btn-submit">
                    <i class="fas fa-save mr-1"></i> Simpan
                </button>
                <a href="{{ route('spvplanner.tujuan.index') }}" class="tf-btn-cancel">Batal</a>
            </div>

        </form>

    </div>

</div>

@endsection