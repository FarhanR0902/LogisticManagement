@extends('layouts.app')

@section('content')

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Data Kota</h3>
    </div>

    {{-- Alert Success --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}

            <button type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Close">
            </button>
        </div>
    @endif

    {{-- Alert Error --}}
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}

            <button type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Close">
            </button>
        </div>
    @endif

    {{-- Form Import Data Baru --}}
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="card-title mb-0">Import Data Kota</h5>
        </div>
        <div class="card-body">

            <form action="{{ route('kota.import.store') }}"
                  method="POST"
                  enctype="multipart/form-data"
                  class="d-flex align-items-center gap-2">

                @csrf

                <input
                    type="file"
                    name="file"
                    accept=".xlsx,.xls,.csv"
                    class="form-control"
                    style="max-width: 400px;"
                    required
                >

                <button type="submit" class="btn btn-primary">
                    Import Excel
                </button>

            </form>

        </div>
    </div>

    {{-- Form Update Alasan Pending --}}
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="card-title mb-0">Update Alasan Pending</h5>
        </div>
        <div class="card-body">

            <p class="text-muted small mb-2">
                Upload file dengan kolom <code>no_shipment</code>, <code>tujuan</code>, dan <code>alasan_pending</code>.
                Semua baris data yang sudah ada di database dengan kombinasi <strong>no_shipment + tujuan</strong> yang sama akan otomatis ter-update.
            </p>

            <form action="{{ route('kota.import.update.alasan.pending') }}"
                  method="POST"
                  enctype="multipart/form-data"
                  class="d-flex align-items-center gap-2">

                @csrf

                <input
                    type="file"
                    name="file"
                    accept=".xlsx,.xls,.csv"
                    class="form-control"
                    style="max-width: 400px;"
                    required
                >

                <button type="submit" class="btn btn-warning">
                    Update Alasan Pending
                </button>

            </form>

        </div>
    </div>

    {{-- Data Table --}}
    <div class="card">

        <div class="card-body">

            <div class="table-responsive">

                <table id="table-kota"
                       class="table table-striped table-bordered w-100">

                    <thead>
                        <tr>
                            <th>Kode Gudang</th>
                            <th>No SO</th>
                            <th>No DO</th>
                            <th>Create On</th>
                            <th>Tgl DO</th>
                            <th>Tanggal Kirim</th>
                            <th>Customer ID</th>
                            <th>Tujuan</th>
                            <th>Kode Barang</th>
                            <th>Nama Barang</th>
                            <th>Qty Produk</th>
                            <th>No Pol</th>
                            <th>Nama Driver</th>
                            <th>No Shipment</th>
                            <th>Ekpedisi</th>
                            <th>Jenis Mobil</th>
                            <th>Jenis Tonase</th>
                            <th>Kubikasi</th>
                            <th>Tonase</th>
                            <th>Pengiriman Optimal</th>
                            <th>Jumlah Toko</th>
                            <th>PIC Toko</th>
                            <th>PIC Driver</th>
                            <th>Waktu Pengiriman</th>
                            <th>Alasan Pending</th>
                        </tr>
                    </thead>

                    <tbody></tbody>

                </table>

            </div>

        </div>

    </div>

</div>

@endsection

@push('scripts')
<script>
$(document).ready(function () {

    $('#table-kota').DataTable({
        processing: true,
        serverSide: true,

        ajax: '{{ route("kota.import.data.ajax") }}',

        scrollX: true,

        pageLength: 25,

        columns: [
            { data: 'kode_gudang', defaultContent: '-' },
            { data: 'no_so', defaultContent: '-' },
            { data: 'no_do', defaultContent: '-' },
            { data: 'create_on', defaultContent: '-' },
            { data: 'tgl_do', defaultContent: '-' },
            { data: 'tanggal_kirim', defaultContent: '-' },
            { data: 'customer_id', defaultContent: '-' },
            { data: 'tujuan', defaultContent: '-' },
            { data: 'kode_barang', defaultContent: '-' },
            { data: 'nama_barang', defaultContent: '-' },
            { data: 'qty_prdk', defaultContent: '0' },
            { data: 'no_pol', defaultContent: '-' },
            { data: 'nama_driver', defaultContent: '-' },
            { data: 'no_shipment', defaultContent: '-' },
            { data: 'EKPEDISI', defaultContent: '-' },
            { data: 'jenis_mobil', defaultContent: '-' },
            { data: 'jenis_tonase', defaultContent: '-' },
            { data: 'Kubikasi', defaultContent: '0' },
            { data: 'tonase', defaultContent: '0' },
            { data: 'pengiriman_optimal', defaultContent: '-' },
            { data: 'jumlah_toko', defaultContent: '0' },
            { data: 'pic_toko', defaultContent: '-' },
            { data: 'pic_driver', defaultContent: '-' },
            { data: 'waktu_pengiriman', defaultContent: '-' },
            { data: 'alasan_pending', defaultContent: '-' }
        ]

    });

});
</script>
@endpush