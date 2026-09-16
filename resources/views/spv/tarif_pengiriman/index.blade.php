@extends('layouts.app')

@section('content')

<style>
/* =========================================================
   PAGE
========================================================= */

.tarif-page {
    padding: 20px 15px 40px;
    width: 100%;
    max-width: 100%;
}

.tarif-page *,
.tarif-page *::before,
.tarif-page *::after {
    box-sizing: border-box;
}


/* =========================================================
   HEADER
========================================================= */

.tarif-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 18px;
}

.tarif-title {
    margin: 0;
    color: #1e293b;
    font-size: 22px;
    font-weight: 700;
}

.tarif-subtitle {
    display: block;
    margin-top: 4px;
    color: #94a3b8;
    font-size: 12px;
}

.btn-add {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;

    height: 38px;
    padding: 0 15px;

    border: 0;
    border-radius: 7px;

    background: #2563eb;
    color: #fff !important;

    font-size: 12px;
    font-weight: 600;

    text-decoration: none !important;

    white-space: nowrap;

    transition: .2s ease;
}

.btn-add:hover {
    background: #1d4ed8;
    color: #fff !important;
}


/* =========================================================
   CARD
========================================================= */

.tarif-card {
    background: #fff;
    border: 0;
    border-radius: 10px;
    box-shadow: 0 2px 12px rgba(15, 23, 42, .06);
    overflow: hidden;
    margin-bottom: 18px;
}


/* =========================================================
   ALERT
========================================================= */

.tarif-alert {
    border: 0;
    border-radius: 8px;
    font-size: 12px;
    margin-bottom: 18px;
}


/* =========================================================
   FILTER
========================================================= */

.filter-header {
    display: flex;
    align-items: center;
    gap: 8px;

    min-height: 48px;
    padding: 0 17px;

    background: #f8fafc;
    border-bottom: 1px solid #e9eef5;
}

.filter-header i {
    color: #64748b;
    font-size: 12px;
}

.filter-title {
    margin: 0;
    color: #334155;
    font-size: 13px;
    font-weight: 700;
}

.filter-body {
    padding: 17px;
}

.filter-label {
    display: block;
    margin-bottom: 7px;

    color: #475569;
    font-size: 12px;
    font-weight: 600;
}

.search-wrapper {
    position: relative;
}

.search-wrapper i {
    position: absolute;
    left: 13px;
    top: 50%;

    transform: translateY(-50%);

    color: #94a3b8;
    font-size: 12px;

    z-index: 2;
}

.search-input {
    width: 100%;
    height: 38px;

    padding: 0 12px 0 35px;

    border: 1px solid #dbe2ea;
    border-radius: 7px;

    color: #334155;
    font-size: 12px;

    outline: none !important;
    box-shadow: none !important;
}

.search-input:focus {
    border-color: #93c5fd;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .08) !important;
}

.filter-buttons {
    display: flex;
    align-items: center;
    gap: 7px;
    height: 38px;
}

.btn-filter {
    height: 38px;

    padding: 0 15px;

    border-radius: 7px;

    font-size: 12px;
    font-weight: 600;
}

.btn-filter-reset {
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    color: #475569;
}

.btn-filter-reset:hover {
    background: #e2e8f0;
    color: #334155;
}


/* =========================================================
   TABLE HEADER
========================================================= */

.table-top {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 15px;

    min-height: 62px;
    padding: 12px 17px;

    border-bottom: 1px solid #e9eef5;
}

.table-heading {
    min-width: 0;
}

.table-title {
    margin: 0;

    color: #334155;
    font-size: 13px;
    font-weight: 700;
}

.table-description {
    margin-top: 3px;

    color: #94a3b8;
    font-size: 11px;
}

.data-count {
    display: inline-flex;
    align-items: center;
    gap: 6px;

    height: 28px;
    padding: 0 9px;

    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;

    color: #64748b;
    font-size: 11px;
    font-weight: 600;

    white-space: nowrap;
}


/* =========================================================
   TABLE WRAPPER
========================================================= */

.table-wrapper {
    width: 100%;
    overflow-x: auto;
    overflow-y: visible;

    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 #f8fafc;
}

.table-wrapper::-webkit-scrollbar {
    height: 7px;
}

.table-wrapper::-webkit-scrollbar-track {
    background: #f8fafc;
}

.table-wrapper::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}


/* =========================================================
   TABLE
========================================================= */

.tarif-table {
    width: max-content;
    min-width: 100%;

    margin: 0;

    border-collapse: separate;
    border-spacing: 0;

    table-layout: auto;
}

.tarif-table th,
.tarif-table td {
    white-space: nowrap;
}


/* =========================================================
   TABLE HEADER
========================================================= */

.tarif-table thead th {
    height: 44px;

    padding: 0 11px;

    background: #1e293b;
    color: #f8fafc;

    border: 0;
    border-bottom: 1px solid #334155;

    font-size: 10px;
    font-weight: 700;

    vertical-align: middle;

    text-align: left;

    position: relative;
    z-index: 3;
}

.tarif-table thead th:first-child {
    text-align: center;
}


/* =========================================================
   TABLE BODY
========================================================= */

.tarif-table tbody td {
    height: 46px;

    padding: 0 11px;

    background: #fff;

    border: 0;
    border-bottom: 1px solid #eef2f7;

    color: #475569;

    font-size: 11px;

    vertical-align: middle;
}

.tarif-table tbody tr:hover td {
    background: #f8fafc;
}

.tarif-table tbody tr:last-child td {
    border-bottom: 0;
}


/* =========================================================
   COLUMN WIDTH
========================================================= */

.col-no {
    width: 50px;
    min-width: 50px;
    text-align: center !important;
}

.col-agent {
    min-width: 110px;
}

.col-ekspedisi {
    min-width: 210px;
}

.col-sh {
    width: 55px;
    min-width: 55px;
    text-align: center !important;
}

.col-mobil {
    min-width: 180px;
}

.col-routew {
    min-width: 130px;
}

.col-route {
    min-width: 180px;
}

.col-biaya {
    min-width: 145px;
    text-align: right !important;
}

.col-unit {
    width: 70px;
    min-width: 70px;
    text-align: center !important;
}

.col-s {
    width: 60px;
    min-width: 60px;
    text-align: center !important;
}

.col-date {
    min-width: 105px;
}

.col-action {
    width: 125px;
    min-width: 125px;
}


/* =========================================================
   STICKY ACTION COLUMN
   INI YANG BIKIN AKSI SELALU KELIHATAN
========================================================= */

.tarif-table thead th.action-sticky {
    position: sticky;
    right: 0;

    z-index: 20;

    background: #172033;

    box-shadow: -6px 0 10px rgba(15, 23, 42, .08);
}

.tarif-table tbody td.action-sticky {
    position: sticky;
    right: 0;

    z-index: 10;

    background: #fff;

    box-shadow: -6px 0 10px rgba(15, 23, 42, .05);
}

.tarif-table tbody tr:hover td.action-sticky {
    background: #f8fafc;
}


/* =========================================================
   TEXT
========================================================= */

.agent-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    min-width: 42px;
    height: 24px;

    padding: 0 7px;

    background: #eff6ff;
    border: 1px solid #dbeafe;
    border-radius: 5px;

    color: #2563eb;

    font-size: 10px;
    font-weight: 700;
}

.ekspedisi-text {
    color: #1e293b;
    font-weight: 600;
}

.route-text {
    max-width: 220px;

    overflow: hidden;
    text-overflow: ellipsis;

    color: #475569;
}

.mobil-text {
    color: #475569;
}

.biaya-text {
    color: #15803d !important;
    font-weight: 700;
    text-align: right;
}

.date-text {
    color: #64748b;
    font-size: 10px;
}

.center {
    text-align: center !important;
}


/* =========================================================
   ACTION BUTTON
========================================================= */

.action-wrapper {
    display: flex;
    align-items: center;
    justify-content: center;

    gap: 5px;

    min-width: 105px;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;

    height: 29px;
    min-width: 31px;

    padding: 0 8px;

    border-radius: 5px;

    font-size: 10px;
    font-weight: 600;

    line-height: 1;

    text-decoration: none !important;

    cursor: pointer;

    transition: .15s ease;
}

.action-btn i {
    font-size: 11px;
}

.action-edit {
    background: #fff7ed;
    border: 1px solid #fed7aa;
    color: #ea580c !important;
}

.action-edit:hover {
    background: #ffedd5;
    color: #c2410c !important;
}

.action-delete {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #dc2626 !important;
}

.action-delete:hover {
    background: #fee2e2;
    color: #b91c1c !important;
}


/* =========================================================
   EMPTY
========================================================= */

.empty-row td {
    height: 260px !important;

    text-align: center !important;

    background: #fff !important;

    white-space: normal !important;
}

.empty-icon {
    display: flex;
    align-items: center;
    justify-content: center;

    width: 55px;
    height: 55px;

    margin: 0 auto 12px;

    border-radius: 50%;

    background: #f1f5f9;
    color: #94a3b8;

    font-size: 19px;
}

.empty-title {
    margin-bottom: 4px;

    color: #475569;
    font-size: 13px;
    font-weight: 700;
}

.empty-description {
    margin: 0;

    color: #94a3b8;
    font-size: 11px;
}


/* =========================================================
   PAGINATION
========================================================= */

.pagination-area {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 15px;

    min-height: 62px;
    padding: 12px 17px;

    border-top: 1px solid #e9eef5;

    overflow: hidden;
}

.pagination-info {
    flex: 0 0 auto;

    color: #94a3b8;
    font-size: 11px;

    white-space: nowrap;
}

.pagination-scroll {
    flex: 1 1 auto;

    display: flex;
    justify-content: flex-end;

    min-width: 0;

    overflow-x: auto;
    overflow-y: hidden;

    scrollbar-width: thin;
}

.pagination-scroll::-webkit-scrollbar {
    height: 4px;
}

.pagination-scroll::-webkit-scrollbar-track {
    background: transparent;
}

.pagination-scroll::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}

.custom-pagination {
    display: flex !important;
    align-items: center;

    flex-wrap: nowrap !important;

    gap: 4px;

    margin: 0;
    padding: 0;

    list-style: none;

    white-space: nowrap;
}

.custom-pagination li {
    flex: 0 0 auto;

    display: block;
}

.custom-pagination a,
.custom-pagination span {
    display: inline-flex;

    align-items: center;
    justify-content: center;

    min-width: 31px;
    height: 31px;

    padding: 0 8px;

    border: 1px solid #e2e8f0;
    border-radius: 6px;

    background: #fff;
    color: #64748b;

    font-size: 10px;
    font-weight: 600;

    text-decoration: none !important;

    white-space: nowrap;
}

.custom-pagination a:hover {
    background: #f1f5f9;
    color: #334155;
    border-color: #cbd5e1;
}

.custom-pagination .active span {
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
}

.custom-pagination .disabled span {
    background: #f8fafc;
    color: #cbd5e1;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 768px) {

    .tarif-page {
        padding: 12px 8px 30px;
    }

    .tarif-header {
        align-items: stretch;
        flex-direction: column;
    }

    .btn-add {
        width: 100%;
    }

    .filter-body {
        padding: 13px;
    }

    .filter-buttons {
        margin-top: 10px;
        width: 100%;
    }

    .btn-filter {
        flex: 1;
    }

    .table-top {
        align-items: flex-start;
        flex-direction: column;
    }

    .pagination-area {
        align-items: flex-start;
        flex-direction: column;
    }

    .pagination-scroll {
        width: 100%;
        justify-content: flex-start;
    }

    .pagination-info {
        width: 100%;
    }
}


/* =========================================================
   DESKTOP
========================================================= */

@media (min-width: 769px) {

    .filter-search-col {
        width: 60%;
    }

    .filter-action-col {
        width: 40%;
    }

}

.tarif-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 20px;
}

.tarif-title {
    font-size: 20px;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}

.tarif-subtitle {
    font-size: 13px;
    color: #64748b;
}

.tarif-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

/* ===== Tombol Tambah Data (referensi, biar konsisten) ===== */
.btn-add {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    background: #22c55e;
    color: #fff;
    border-radius: 10px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    transition: .2s;
}
.btn-add:hover {
    background: #16a34a;
    color: #fff;
}

/* ===== Form Import ===== */
.import-form {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    padding: 4px;
}

/* Input file asli disembunyikan, diganti label custom */
.file-input-label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 7px 12px;
    background: #f1f5f9;
    color: #475569;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    white-space: nowrap;
    max-width: 180px;
    overflow: hidden;
    text-overflow: ellipsis;
    transition: .2s;
}
.file-input-label:hover {
    background: #e2e8f0;
}
.file-input-label input[type="file"] {
    display: none;
}
.file-input-label span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.btn-import {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: #2563eb;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: .2s;
}
.btn-import:hover {
    background: #1d4ed8;
}

/* ===== Responsive ===== */
@media (max-width: 768px) {
    .tarif-header {
        flex-direction: column;
        align-items: stretch;
    }
    .tarif-actions {
        flex-direction: column;
        align-items: stretch;
    }
    .import-form {
        flex-direction: column;
        align-items: stretch;
    }
    .file-input-label {
        max-width: 100%;
        justify-content: center;
    }
}.col-check {
    width: 40px;
    min-width: 40px;
    text-align: center !important;
}

.tarif-table tbody tr.row-selected td {
    background: #eff6ff;
}

.bulk-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.btn-bulk {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    height: 32px;
    padding: 0 12px;
    border-radius: 6px;
    border: 1px solid #fecaca;
    background: #fef2f2;
    color: #dc2626;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: .15s ease;
    white-space: nowrap;
}
.btn-bulk:hover:not(:disabled) {
    background: #fee2e2;
    color: #b91c1c;
}
.btn-bulk:disabled {
    opacity: .5;
    cursor: not-allowed;
}
.btn-bulk-all {
    background: #dc2626;
    border-color: #dc2626;
    color: #fff;
}
.btn-bulk-all:hover {
    background: #b91c1c;
    color: #fff;
}
.btn-bulk-edit {
    border-color: #bfdbfe;
    background: #eff6ff;
    color: #2563eb;
}
.btn-bulk-edit:hover:not(:disabled) {
    background: #dbeafe;
    color: #1d4ed8;
}

/* ===== Modal Bulk Edit ===== */
#bulkEditModal.modal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 1050;
    overflow-x: hidden;
    overflow-y: auto;
    background: rgba(15, 23, 42, .55);
}
#bulkEditModal.modal.show {
    display: block;
}
.modal-backdrop {
    display: none !important;
}
#bulkEditModal .modal-dialog {
    width: 100%;
    max-width: 480px;
    margin: 50px auto;
    padding: 0 15px;
}
#bulkEditModal .modal-content {
    background: #fff;
    border: 0;
    border-radius: 10px;
    box-shadow: 0 10px 40px rgba(15, 23, 42, .25);
    overflow: hidden;
}
#bulkEditModal .modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 15px 20px;
    background: #f8fafc;
    border-bottom: 1px solid #e9eef5;
}
#bulkEditModal .modal-title {
    margin: 0;
    color: #1e293b;
    font-size: 15px;
    font-weight: 700;
}
#bulkEditModal .close {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    padding: 0;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: #94a3b8;
    font-size: 18px;
    line-height: 1;
    cursor: pointer;
}
#bulkEditModal .close:hover {
    background: #f1f5f9;
    color: #475569;
}
#bulkEditModal .modal-body {
    padding: 18px 20px;
    max-height: 65vh;
    overflow-y: auto;
}
#bulkEditModal .modal-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    padding: 14px 20px;
    background: #f8fafc;
    border-top: 1px solid #e9eef5;
}
.form-group-tf {
    margin-bottom: 14px;
}
.form-group-tf:last-child {
    margin-bottom: 0;
}
.form-label-tf {
    display: block;
    margin-bottom: 6px;
    color: #475569;
    font-size: 12px;
    font-weight: 600;
}
.form-input-tf {
    width: 100%;
    height: 38px;
    padding: 0 12px;
    border: 1px solid #dbe2ea;
    border-radius: 7px;
    color: #334155;
    font-size: 12px;
    outline: none !important;
    box-shadow: none !important;
}
.form-input-tf:focus {
    border-color: #93c5fd;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .08) !important;
}
.btn-cancel-tf {
    height: 36px;
    padding: 0 15px;
    border: 1px solid #e2e8f0;
    border-radius: 7px;
    background: #f1f5f9;
    color: #475569;
    font-size: 12px;
    font-weight: 600;
}
.btn-cancel-tf:hover {
    background: #e2e8f0;
    color: #334155;
}
.btn-submit-tf {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    height: 36px;
    padding: 0 16px;
    border: 0;
    border-radius: 7px;
    background: #2563eb;
    color: #fff;
    font-size: 12px;
    font-weight: 600;
}
.btn-submit-tf:hover {
    background: #1d4ed8;
    color: #fff;
}

@media (max-width: 768px) {
    .bulk-actions { width: 100%; }
    .btn-bulk { flex: 1; }
    #bulkEditModal .modal-dialog { margin: 20px auto; }
}

</style>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<div class="container-fluid tarif-page">


{{-- =====================================================
     PAGE HEADER
====================================================== --}}

<div class="tarif-header">

    <div>
        <h4 class="tarif-title">
            Data Tarif Pengiriman
        </h4>
        <span class="tarif-subtitle">
            Master tarif pengiriman
        </span>
    </div>

    <div class="tarif-actions">

        <a href="{{ route('spvplanner.tarif.create') }}" class="btn-add">
            <i class="fas fa-plus"></i>
            Tambah Data
        </a>

        <form action="{{ route('spvplanner.tarif.import') }}" method="POST" enctype="multipart/form-data" class="import-form">
            @csrf
            <label class="file-input-label">
                <i class="fas fa-file-csv"></i>
                <span id="fileNameLabel">Pilih File CSV</span>
                <input type="file" name="file" accept=".csv,.txt" required
                       onchange="document.getElementById('fileNameLabel').innerText = this.files[0]?.name || 'Pilih File CSV'">
            </label>
            <button type="submit" class="btn-import">
                <i class="fas fa-upload"></i>
                Import CSV
            </button>
        </form>

    </div>

</div>

{{-- =====================================================
     SUCCESS
====================================================== --}}

@if(session('success'))

    <div class="alert alert-success tarif-alert alert-dismissible fade show">

        <i class="fas fa-check-circle mr-2"></i>

        {{ session('success') }}

        <button type="button"
                class="close"
                data-dismiss="alert">

            <span>&times;</span>

        </button>

    </div>

@endif


{{-- =====================================================
     ERROR
====================================================== --}}

@if($errors->any())

    <div class="alert alert-danger tarif-alert alert-dismissible fade show">

        <strong>
            <i class="fas fa-exclamation-circle mr-1"></i>
            Terjadi kesalahan
        </strong>

        <ul class="mb-0 mt-2">

            @foreach($errors->all() as $error)

                <li>{{ $error }}</li>

            @endforeach

        </ul>

        <button type="button"
                class="close"
                data-dismiss="alert">

            <span>&times;</span>

        </button>

    </div>

@endif


{{-- =====================================================
     FILTER
====================================================== --}}

<div class="tarif-card">

    <div class="filter-header">

        <i class="fas fa-filter"></i>

        <h6 class="filter-title">
            Filter Data
        </h6>

    </div>

    <div class="filter-body">

        <form method="GET"
              action="{{ route('spvplanner.tarif.index') }}">

            <div class="row align-items-end">

                <div class="col-lg-7 col-md-8">

                    <label class="filter-label">
                        Pencarian
                    </label>

                    <div class="search-wrapper">

                        <i class="fas fa-search"></i>

                        <input
                            type="text"
                            name="search"
                            class="form-control search-input"
                            value="{{ request('search') }}"
                            placeholder="Cari service agent, ekspedisi, mobil, route..."
                        >

                    </div>

                </div>


                <div class="col-lg-5 col-md-4">

                    <div class="filter-buttons">

                        <button type="submit"
                                class="btn btn-primary btn-filter">

                            <i class="fas fa-search mr-1"></i>

                            Cari

                        </button>

                        <a href="{{ route('spvplanner.tarif.index') }}"
                           class="btn btn-filter btn-filter-reset">

                            <i class="fas fa-sync-alt mr-1"></i>

                            Reset

                        </a>

                    </div>

                </div>

            </div>

        </form>

    </div>

</div>


{{-- =====================================================
     DATA TABLE
====================================================== --}}

<div class="tarif-card">

    {{-- TABLE HEADER --}}

   <div class="table-top">

    <div class="table-heading">
        <h6 class="table-title">
            <i class="fas fa-list mr-1"></i>
            Daftar Tarif Pengiriman
        </h6>
        <div class="table-description">
            Data master tarif pengiriman
        </div>
    </div>

    <div class="bulk-actions">

        <button type="button" id="btnDeleteSelected" class="btn-bulk" disabled>
            <i class="fas fa-trash"></i>
            Hapus Terpilih (<span id="selectedCount">0</span>)
        </button>

        <button type="button" id="btnDeleteAll" class="btn-bulk btn-bulk-all">
            <i class="fas fa-trash-alt"></i>
            Hapus Semua Data
        </button>

        <button type="button" id="btnEditSelected" class="btn-bulk btn-bulk-edit" disabled>
            <i class="fas fa-edit"></i>
            Edit Terpilih (<span id="selectedCountEdit">0</span>)
        </button>

        @if(method_exists($data, 'total'))
            <span class="data-count">
                <i class="fas fa-database"></i>
                {{ number_format($data->total(), 0, ',', '.') }} Data
            </span>
        @endif

    </div>

</div>


    {{-- TABLE --}}

  <div class="table-wrapper">

    <form id="bulkForm" method="POST" action="{{ route('spvplanner.tarif.bulk-destroy') }}">
        @csrf
        @method('DELETE')

        <table class="tarif-table">

            <thead>
                <tr>
                    <th class="col-check">
                        <input type="checkbox" id="checkAll" title="Pilih Semua di Halaman Ini">
                    </th>
                    <th class="col-no">No</th>
                    <th class="col-agent">Service Agent</th>
                    <th class="col-ekspedisi">Ekspedisi</th>
                    <th class="col-sh center">SH</th>
                    <th class="col-mobil">Mobil</th>
                    <th class="col-routew">Route W</th>
                    <th class="col-route">Route</th>
                    <th class="col-biaya">Biaya Kirim</th>
                    <th class="col-unit center">Unit</th>
                    <th class="col-s center">S1</th>
                    <th class="col-s center">S2</th>
                    <th class="col-date">Valid From</th>
                    <th class="col-date">Valid To</th>
                    <th class="col-date">Tonase</th>
                    <th class="col-date">Kubikasi</th>
                    <th class="col-action action-sticky">Aksi</th>
                </tr>
            </thead>

            <tbody>

                @forelse($data as $item)

                    <tr data-row-id="{{ $item->id }}">

                        <td class="col-check">
                            <input type="checkbox" name="ids[]" value="{{ $item->id }}" class="row-check">
                        </td>

                        <td class="col-no">
                            @if(method_exists($data, 'firstItem'))
                                {{ $data->firstItem() + $loop->index }}
                            @else
                                {{ $loop->iteration }}
                            @endif
                        </td>

                        <td class="col-agent">
                            @if(!empty($item->servc_agent))
                                <span class="agent-badge">{{ $item->servc_agent }}</span>
                            @else
                                -
                            @endif
                        </td>

                        <td class="col-ekspedisi">
                            <span class="ekspedisi-text">{{ $item->ekpedisi ?: '-' }}</span>
                        </td>

                        <td class="col-sh center">{{ $item->sh ?: '-' }}</td>

                        <td class="col-mobil">
                            <span class="mobil-text">{{ $item->mobil ?: '-' }}</span>
                        </td>

                        <td class="col-routew">{{ $item->routew ?: '-' }}</td>

                        <td class="col-route">
                            <div class="route-text" title="{{ $item->route }}">
                                {{ $item->route ?: '-' }}
                            </div>
                        </td>

                        <td class="col-biaya biaya-text">
                            @php
                                $rawBiaya = trim((string) ($item->biaya_kirim ?? ''));
                                $cleanBiaya = preg_replace('/[^0-9]/', '', $rawBiaya);
                            @endphp
                            @if($cleanBiaya !== '' && (float) $cleanBiaya > 0)
                                Rp {{ number_format((float) $cleanBiaya, 0, ',', '.') }}
                            @else
                                -
                            @endif
                        </td>

                        <td class="col-unit center">{{ $item->unit ?: '-' }}</td>
                        <td class="col-s center">{{ $item->s_1 ?: '-' }}</td>
                        <td class="col-s center">{{ $item->s_2 ?: '-' }}</td>
                        <td class="col-date date-text">{{ $item->valid_from ?: '-' }}</td>
                        <td class="col-date date-text">{{ $item->valid_to ?: '-' }}</td>
                        <td class="col-date date-text">{{ $item->tonase ?: '-' }}</td>
                        <td class="col-date date-text">{{ $item->kubikasi ?: '-' }}</td>

                        <td class="col-action action-sticky">
                            <div class="action-wrapper">

                                <a href="{{ route('spvplanner.tarif.edit', $item->id) }}"
                                   class="action-btn action-edit" title="Edit Data">
                                    <i class="fas fa-edit"></i>
                                    <span>Edit</span>
                                </a>

                                <button type="button"
                                        class="action-btn action-delete btn-delete-single"
                                        data-id="{{ $item->id }}"
                                        title="Hapus Data">
                                    <i class="fas fa-trash"></i>
                                    <span>Hapus</span>
                                </button>

                            </div>
                        </td>

                    </tr>

                @empty

                    <tr class="empty-row">
                        <td colspan="17">
                            <div class="empty-icon">
                                <i class="fas fa-file-invoice"></i>
                            </div>
                            <div class="empty-title">Belum ada data tarif</div>
                            <p class="empty-description">Data tarif pengiriman belum tersedia.</p>
                        </td>
                    </tr>

                @endforelse

            </tbody>

        </table>

    </form>

</div>

    {{-- =====================================================
         PAGINATION
    ====================================================== --}}

    @if(method_exists($data, 'total') && $data->total() > 0)

        <div class="pagination-area">

            {{-- INFO --}}

            <div class="pagination-info">

                Menampilkan

                <strong>
                    {{ $data->firstItem() }}
                </strong>

                -

                <strong>
                    {{ $data->lastItem() }}
                </strong>

                dari

                <strong>
                    {{ $data->total() }}
                </strong>

                data

            </div>


            {{-- PAGINATION --}}

            @if($data->lastPage() > 1)

                <div class="pagination-scroll">

                    <ul class="custom-pagination">

                        {{-- PREVIOUS --}}

                        @if($data->onFirstPage())

                            <li class="disabled">

                                <span>
                                    <i class="fas fa-chevron-left"></i>
                                </span>

                            </li>

                        @else

                            <li>

                                <a href="{{ $data->appends(request()->query())->url($data->currentPage() - 1) }}">

                                    <i class="fas fa-chevron-left"></i>

                                </a>

                            </li>

                        @endif


                        {{-- PAGE NUMBERS --}}

                        @php

                            $current = $data->currentPage();
                            $last = $data->lastPage();

                            $start = max(1, $current - 2);
                            $end = min($last, $current + 2);

                        @endphp


                        {{-- FIRST PAGE --}}

                        @if($start > 1)

                            <li>

                                <a href="{{ $data->appends(request()->query())->url(1) }}">
                                    1
                                </a>

                            </li>

                            @if($start > 2)

                                <li class="disabled">

                                    <span>...</span>

                                </li>

                            @endif

                        @endif


                        {{-- RANGE --}}

                        @for($page = $start; $page <= $end; $page++)

                            @if($page == $current)

                                <li class="active">

                                    <span>
                                        {{ $page }}
                                    </span>

                                </li>

                            @else

                                <li>

                                    <a href="{{ $data->appends(request()->query())->url($page) }}">
                                        {{ $page }}
                                    </a>

                                </li>

                            @endif

                        @endfor


                        {{-- LAST PAGE --}}

                        @if($end < $last)

                            @if($end < $last - 1)

                                <li class="disabled">

                                    <span>...</span>

                                </li>

                            @endif

                            <li>

                                <a href="{{ $data->appends(request()->query())->url($last) }}">
                                    {{ $last }}
                                </a>

                            </li>

                        @endif


                        {{-- NEXT --}}

                        @if($data->hasMorePages())

                            <li>

                                <a href="{{ $data->appends(request()->query())->url($current + 1) }}">

                                    <i class="fas fa-chevron-right"></i>

                                </a>

                            </li>

                        @else

                            <li class="disabled">

                                <span>

                                    <i class="fas fa-chevron-right"></i>

                                </span>

                            </li>

                        @endif

                    </ul>

                </div>

            @endif

        </div>

    @endif

</div>

</div>
<div class="modal fade" id="bulkEditModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">

      <form id="bulkEditForm" method="POST" action="{{ route('spvplanner.tarif.bulk-update') }}">
        @csrf
        <div id="bulkEditIdsContainer"></div>

        <div class="modal-header">
          <h5 class="modal-title">Edit <span id="bulkEditCount">0</span> Data Terpilih</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
        </div>

        <div class="modal-body">
          <p class="text-muted" style="font-size:12px;">
            Kosongkan field yang tidak ingin diubah — hanya field yang diisi yang akan diterapkan ke semua data terpilih.
          </p>

          <div class="form-group-tf">
            <label class="form-label-tf">Service Agent</label>
            <input type="text" name="servc_agent" class="form-input-tf" placeholder="Kosongkan jika tidak diubah">
          </div>

          <div class="form-group-tf">
            <label class="form-label-tf">Ekspedisi</label>
            <input type="text" name="ekpedisi" class="form-input-tf" placeholder="Kosongkan jika tidak diubah">
          </div>

          <div class="form-group-tf">
            <label class="form-label-tf">Mobil</label>
            <input type="text" name="mobil" class="form-input-tf" placeholder="Kosongkan jika tidak diubah">
          </div>

          <div class="form-group-tf">
            <label class="form-label-tf">Route</label>
            <input type="text" name="route" class="form-input-tf" placeholder="Kosongkan jika tidak diubah">
          </div>

          <div class="form-group-tf">
            <label class="form-label-tf">Route W</label>
            <input type="text" name="routew" class="form-input-tf" placeholder="Kosongkan jika tidak diubah">
          </div>

          <div class="form-group-tf">
            <label class="form-label-tf">Biaya Kirim</label>
            <input type="text" name="biaya_kirim" class="form-input-tf" placeholder="Kosongkan jika tidak diubah">
          </div>

          <div class="form-group-tf">
            <label class="form-label-tf">Tonase</label>
            <input type="text" name="tonase" class="form-input-tf" placeholder="Kosongkan jika tidak diubah">
          </div>

          <div class="form-group-tf">
            <label class="form-label-tf">Kubikasi</label>
            <input type="text" name="kubikasi" class="form-input-tf" placeholder="Kosongkan jika tidak diubah">
          </div>

          <div class="form-group-tf">
            <label class="form-label-tf">Valid From</label>
            <input type="text" name="valid_from" class="form-input-tf" placeholder="Kosongkan jika tidak diubah">
          </div>

          <div class="form-group-tf">
            <label class="form-label-tf">Valid To</label>
            <input type="text" name="valid_to" class="form-input-tf" placeholder="Kosongkan jika tidak diubah">
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-cancel-tf" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn-submit-tf">
              <i class="fas fa-save mr-1"></i> Simpan Perubahan
          </button>
        </div>
      </form>

    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const checkAll          = document.getElementById('checkAll');
    const btnDeleteSelected = document.getElementById('btnDeleteSelected');
    const btnDeleteAll      = document.getElementById('btnDeleteAll');
    const selectedCountEl   = document.getElementById('selectedCount');
    const bulkForm          = document.getElementById('bulkForm');
    const csrfToken         = '{{ csrf_token() }}';

    const btnEditSelected      = document.getElementById('btnEditSelected');
    const selectedCountEditEl  = document.getElementById('selectedCountEdit');
    const bulkEditForm         = document.getElementById('bulkEditForm');
    const bulkEditIdsContainer = document.getElementById('bulkEditIdsContainer');
    const bulkEditCount        = document.getElementById('bulkEditCount');
    const bulkEditModal        = document.getElementById('bulkEditModal');

    function getRowChecks() {
        return document.querySelectorAll('.row-check');
    }

    function updateSelectedCount() {
        const checked = document.querySelectorAll('.row-check:checked');

        selectedCountEl.textContent = checked.length;
        btnDeleteSelected.disabled = checked.length === 0;

        getRowChecks().forEach(function (cb) {
            const tr = cb.closest('tr');
            if (tr) tr.classList.toggle('row-selected', cb.checked);
        });

        const total = getRowChecks().length;
        if (checkAll) {
            checkAll.checked = total > 0 && checked.length === total;
            checkAll.indeterminate = checked.length > 0 && checked.length < total;
        }

        if (btnEditSelected) {
            selectedCountEditEl.textContent = checked.length;
            btnEditSelected.disabled = checked.length === 0;
        }
    }

    checkAll?.addEventListener('change', function () {
        getRowChecks().forEach(function (cb) {
            cb.checked = checkAll.checked;
        });
        updateSelectedCount();
    });

    document.addEventListener('change', function (e) {
        if (e.target.classList && e.target.classList.contains('row-check')) {
            updateSelectedCount();
        }
    });

    function openBulkEditModal() {
        bulkEditModal.classList.add('show');
        bulkEditModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeBulkEditModal() {
        bulkEditModal.classList.remove('show');
        bulkEditModal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    btnEditSelected?.addEventListener('click', function () {
        const checked = document.querySelectorAll('.row-check:checked');
        if (checked.length === 0) return;

        bulkEditForm.reset();

        bulkEditIdsContainer.innerHTML = '';
        checked.forEach(function (cb) {
            const hidden = document.createElement('input');
            hidden.type  = 'hidden';
            hidden.name  = 'ids[]';
            hidden.value = cb.value;
            bulkEditIdsContainer.appendChild(hidden);
        });

        bulkEditCount.textContent = checked.length;

        openBulkEditModal();
    });

    bulkEditModal?.querySelectorAll('[data-dismiss="modal"]').forEach(function (el) {
        el.addEventListener('click', closeBulkEditModal);
    });

    bulkEditModal?.addEventListener('click', function (e) {
        if (e.target === bulkEditModal) closeBulkEditModal();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && bulkEditModal?.classList.contains('show')) {
            closeBulkEditModal();
        }
    });

    btnDeleteSelected?.addEventListener('click', function () {
        const checked = document.querySelectorAll('.row-check:checked');
        if (checked.length === 0) return;

        if (confirm('Yakin ingin menghapus ' + checked.length + ' data tarif yang dipilih?')) {
            bulkForm.submit();
        }
    });

    btnDeleteAll?.addEventListener('click', function () {
        const confirmText = prompt('Ini akan menghapus SEMUA data tarif tanpa terkecuali.\nKetik "HAPUS SEMUA" untuk konfirmasi:');

        if (confirmText === null) return;

        if (confirmText.trim().toUpperCase() !== 'HAPUS SEMUA') {
            alert('Konfirmasi tidak sesuai, aksi dibatalkan.');
            return;
        }

        btnDeleteAll.disabled = true;

        fetch("{{ route('spvplanner.tarif.destroy-all') }}", {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            }
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            alert(data.message || 'Semua data berhasil dihapus');
            window.location.reload();
        })
        .catch(function () {
            alert('Gagal menghapus semua data');
            btnDeleteAll.disabled = false;
        });
    });

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-delete-single');
        if (!btn) return;

        const id = btn.dataset.id;
        if (!confirm('Yakin ingin menghapus tarif ini?')) return;

        btn.disabled = true;

        fetch('/spvplanner/tarif/' + id, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            }
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            alert(data.message || 'Data berhasil dihapus');
            window.location.reload();
        })
        .catch(function () {
            alert('Gagal menghapus data');
            btn.disabled = false;
        });
    });

});
</script>
@endsection
