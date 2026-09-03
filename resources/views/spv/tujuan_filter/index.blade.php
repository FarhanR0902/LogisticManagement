@extends('layouts.app')

@section('content')

<style>
/* =========================================================
   PAGE
========================================================= */

.tf-page {
    padding: 20px 15px 40px;
    width: 100%;
    max-width: 100%;
}

.tf-page *,
.tf-page *::before,
.tf-page *::after {
    box-sizing: border-box;
}


/* =========================================================
   HEADER
========================================================= */

.tf-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 18px;
}

.tf-title {
    margin: 0;
    color: #1e293b;
    font-size: 22px;
    font-weight: 700;
}

.tf-subtitle {
    display: block;
    margin-top: 4px;
    color: #94a3b8;
    font-size: 12px;
}

.tf-btn-add {
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

.tf-btn-add:hover {
    background: #1d4ed8;
    color: #fff !important;
}


/* =========================================================
   CARD
========================================================= */

.tf-card {
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

.tf-alert {
    border: 0;
    border-radius: 8px;
    font-size: 12px;
    margin-bottom: 18px;
}


/* =========================================================
   FILTER
========================================================= */

.tf-filter-header {
    display: flex;
    align-items: center;
    gap: 8px;

    min-height: 48px;
    padding: 0 17px;

    background: #f8fafc;
    border-bottom: 1px solid #e9eef5;
}

.tf-filter-header i {
    color: #64748b;
    font-size: 12px;
}

.tf-filter-title {
    margin: 0;
    color: #334155;
    font-size: 13px;
    font-weight: 700;
}

.tf-filter-body {
    padding: 17px;
}

.tf-filter-label {
    display: block;
    margin-bottom: 7px;

    color: #475569;
    font-size: 12px;
    font-weight: 600;
}

.tf-search-wrapper {
    position: relative;
}

.tf-search-wrapper i {
    position: absolute;
    left: 13px;
    top: 50%;

    transform: translateY(-50%);

    color: #94a3b8;
    font-size: 12px;

    z-index: 2;
}

.tf-search-input,
.tf-select-input {
    width: 100%;
    height: 38px;

    border: 1px solid #dbe2ea;
    border-radius: 7px;

    color: #334155;
    font-size: 12px;

    outline: none !important;
    box-shadow: none !important;
}

.tf-search-input {
    padding: 0 12px 0 35px;
}

.tf-select-input {
    padding: 0 12px;
}

.tf-search-input:focus,
.tf-select-input:focus {
    border-color: #93c5fd;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .08) !important;
}

.tf-filter-buttons {
    display: flex;
    align-items: center;
    gap: 7px;
    height: 38px;
}

/* Grid filter per kolom (Div, Customer ID, Tujuan, dst) */
.tf-filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
    gap: 12px 14px;
    margin-bottom: 14px;
}

.tf-filter-item {
    display: flex;
    flex-direction: column;
}

.tf-filter-buttons-row {
    display: flex;
    justify-content: flex-end;
    gap: 7px;
}

.tf-btn-filter {
    height: 38px;

    padding: 0 15px;

    border-radius: 7px;

    font-size: 12px;
    font-weight: 600;
}

.tf-btn-filter-reset {
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    color: #475569;
}

.tf-btn-filter-reset:hover {
    background: #e2e8f0;
    color: #334155;
}


/* =========================================================
   IMPORT
========================================================= */

.tf-import-body {
    padding: 17px;
}

.tf-import-input {
    height: 38px;
    padding: 6px 12px;

    border: 1px solid #dbe2ea;
    border-radius: 7px;

    font-size: 12px;
}

.tf-btn-import {
    height: 38px;

    padding: 0 18px;

    border: 0;
    border-radius: 7px;

    background: #475569;
    color: #fff;

    font-size: 12px;
    font-weight: 600;
}

.tf-btn-import:hover {
    background: #334155;
    color: #fff;
}


/* =========================================================
   TABLE HEADER
========================================================= */

.tf-table-top {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 15px;
    flex-wrap: wrap;

    min-height: 62px;
    padding: 12px 17px;

    border-bottom: 1px solid #e9eef5;
}

.tf-table-title {
    margin: 0;

    color: #334155;
    font-size: 13px;
    font-weight: 700;
}

.tf-table-description {
    margin-top: 3px;

    color: #94a3b8;
    font-size: 11px;
}

.tf-data-count {
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
   BULK ACTIONS
========================================================= */

.tf-bulk-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.tf-btn-bulk {
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

.tf-btn-bulk:hover:not(:disabled) {
    background: #fee2e2;
    color: #b91c1c;
}

.tf-btn-bulk:disabled {
    opacity: .5;
    cursor: not-allowed;
}

.tf-btn-bulk-all {
    background: #dc2626;
    border-color: #dc2626;
    color: #fff;
}

.tf-btn-bulk-all:hover {
    background: #b91c1c;
    color: #fff;
}

/* Edit Terpilih button */
.tf-btn-bulk-edit {
    border-color: #bfdbfe;
    background: #eff6ff;
    color: #2563eb;
}

.tf-btn-bulk-edit:hover:not(:disabled) {
    background: #dbeafe;
    color: #1d4ed8;
}


/* =========================================================
   TABLE WRAPPER
========================================================= */

.tf-table-wrapper {
    width: 100%;
    overflow-x: auto;
    overflow-y: visible;

    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 #f8fafc;
}

.tf-table-wrapper::-webkit-scrollbar {
    height: 7px;
}

.tf-table-wrapper::-webkit-scrollbar-track {
    background: #f8fafc;
}

.tf-table-wrapper::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}


/* =========================================================
   TABLE
========================================================= */

.tf-table {
    width: max-content;
    min-width: 100%;

    margin: 0;

    border-collapse: separate;
    border-spacing: 0;

    table-layout: auto;
}

.tf-table th,
.tf-table td {
    white-space: nowrap;
}

.tf-table thead th {
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
}

.tf-table thead th:first-child {
    text-align: center;
}

.tf-table tbody td {
    height: 46px;

    padding: 0 11px;

    background: #fff;

    border: 0;
    border-bottom: 1px solid #eef2f7;

    color: #475569;

    font-size: 11px;

    vertical-align: middle;
}

.tf-table tbody tr:hover td {
    background: #f8fafc;
}

.tf-table tbody tr:last-child td {
    border-bottom: 0;
}

.tf-table tbody tr.row-selected td {
    background: #eff6ff;
}


/* =========================================================
   COLUMN WIDTH
========================================================= */

.tf-col-check {
    width: 40px;
    min-width: 40px;
    text-align: center !important;
}

.tf-col-no {
    width: 50px;
    min-width: 50px;
    text-align: center !important;
}

.tf-col-tujuan {
    min-width: 220px;
}

.tf-col-area {
    min-width: 150px;
}

.tf-col-channel {
    min-width: 160px;
}

.tf-col-planner {
    min-width: 120px;
}

.tf-col-monitoring {
    min-width: 120px;
}

.tf-col-biaya_kuli {
    min-width: 130px;
}

.tf-col-transport_lead_time {
    min-width: 150px;
}

.tf-col-status {
    width: 100px;
    min-width: 100px;
    text-align: center !important;
}

.tf-col-action {
    width: 130px;
    min-width: 130px;
}


/* =========================================================
   STICKY ACTION COLUMN
========================================================= */

.tf-table thead th.tf-action-sticky {
    position: sticky;
    right: 0;

    z-index: 20;

    background: #172033;

    box-shadow: -6px 0 10px rgba(15, 23, 42, .08);
}

.tf-table tbody td.tf-action-sticky {
    position: sticky;
    right: 0;

    z-index: 10;

    background: #fff;

    box-shadow: -6px 0 10px rgba(15, 23, 42, .05);
}

.tf-table tbody tr:hover td.tf-action-sticky {
    background: #f8fafc;
}


/* =========================================================
   TEXT & BADGES
========================================================= */

.tf-tujuan-text {
    color: #1e293b;
    font-weight: 600;
}

.tf-area-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    min-width: 42px;
    height: 24px;

    padding: 0 9px;

    background: #eff6ff;
    border: 1px solid #dbeafe;
    border-radius: 5px;

    color: #2563eb;

    font-size: 10px;
    font-weight: 700;
    white-space: nowrap;
}

.tf-channel-text {
    color: #475569;
}

.tf-status-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    height: 24px;
    padding: 0 10px;

    border-radius: 5px;

    font-size: 10px;
    font-weight: 700;
}

.tf-status-active {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #15803d;
}

.tf-status-inactive {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #64748b;
}

.tf-center {
    text-align: center !important;
}


/* =========================================================
   ACTION BUTTON
========================================================= */

.tf-action-wrapper {
    display: flex;
    align-items: center;
    justify-content: center;

    gap: 5px;

    min-width: 110px;
}

.tf-action-btn {
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

    border: 0;
}

.tf-action-btn i {
    font-size: 11px;
}

.tf-action-edit {
    background: #fff7ed;
    border: 1px solid #fed7aa;
    color: #ea580c !important;
}

.tf-action-edit:hover {
    background: #ffedd5;
    color: #c2410c !important;
}

.tf-action-delete {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #dc2626 !important;
}

.tf-action-delete:hover {
    background: #fee2e2;
    color: #b91c1c !important;
}


/* =========================================================
   EMPTY
========================================================= */

.tf-empty-row td {
    height: 260px !important;

    text-align: center !important;

    background: #fff !important;

    white-space: normal !important;
}

.tf-empty-icon {
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

.tf-empty-title {
    margin-bottom: 4px;

    color: #475569;
    font-size: 13px;
    font-weight: 700;
}

.tf-empty-description {
    margin: 0;

    color: #94a3b8;
    font-size: 11px;
}


/* =========================================================
   PAGINATION
========================================================= */

.tf-pagination-area {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 15px;

    min-height: 62px;
    padding: 12px 17px;

    border-top: 1px solid #e9eef5;

    overflow: hidden;
}

.tf-pagination-info {
    flex: 0 0 auto;

    color: #94a3b8;
    font-size: 11px;

    white-space: nowrap;
}

.tf-pagination-scroll {
    flex: 1 1 auto;

    display: flex;
    justify-content: flex-end;

    min-width: 0;

    overflow-x: auto;
    overflow-y: hidden;

    scrollbar-width: thin;
}

.tf-pagination-scroll::-webkit-scrollbar {
    height: 4px;
}

.tf-pagination-scroll::-webkit-scrollbar-track {
    background: transparent;
}

.tf-pagination-scroll::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}

.tf-custom-pagination {
    display: flex !important;
    align-items: center;

    flex-wrap: nowrap !important;

    gap: 4px;

    margin: 0;
    padding: 0;

    list-style: none;

    white-space: nowrap;
}

.tf-custom-pagination li {
    flex: 0 0 auto;

    display: block;
}

.tf-custom-pagination a,
.tf-custom-pagination span {
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

.tf-custom-pagination a:hover {
    background: #f1f5f9;
    color: #334155;
    border-color: #cbd5e1;
}

.tf-custom-pagination .active span {
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
}

.tf-custom-pagination .disabled span {
    background: #f8fafc;
    color: #cbd5e1;
}


/* =========================================================
   BULK EDIT MODAL
   (self-contained: shown/hidden with plain JS via the "show"
   class below — tidak butuh jQuery / Bootstrap JS sama sekali)
========================================================= */

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

/* Kalau Bootstrap JS/CSS juga ada di layout lain dan sempat
   nyuntik .modal-backdrop sendiri, sembunyikan biar gak dobel /
   gak polos-unstyled — kita udah gelapin background sendiri di atas. */
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

.tf-form-group {
    margin-bottom: 14px;
}

.tf-form-group:last-child {
    margin-bottom: 0;
}

.tf-form-label {
    display: block;
    margin-bottom: 6px;

    color: #475569;
    font-size: 12px;
    font-weight: 600;
}

.tf-form-input {
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

.tf-form-input:focus {
    border-color: #93c5fd;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .08) !important;
}

.tf-btn-cancel {
    height: 36px;
    padding: 0 15px;

    border: 1px solid #e2e8f0;
    border-radius: 7px;

    background: #f1f5f9;
    color: #475569;

    font-size: 12px;
    font-weight: 600;
}

.tf-btn-cancel:hover {
    background: #e2e8f0;
    color: #334155;
}

.tf-btn-submit {
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

.tf-btn-submit:hover {
    background: #1d4ed8;
    color: #fff;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 768px) {

    .tf-page {
        padding: 12px 8px 30px;
    }

    .tf-header {
        align-items: stretch;
        flex-direction: column;
    }

    .tf-btn-add {
        width: 100%;
    }

    .tf-filter-body,
    .tf-import-body {
        padding: 13px;
    }

    .tf-filter-buttons {
        margin-top: 10px;
        width: 100%;
    }

    .tf-filter-grid {
        grid-template-columns: 1fr;
    }

    .tf-filter-buttons-row {
        flex-direction: column;
    }

    .tf-filter-buttons-row .tf-btn-filter {
        width: 100%;
    }

    .tf-btn-filter {
        flex: 1;
    }

    .tf-table-top {
        align-items: flex-start;
        flex-direction: column;
    }

    .tf-bulk-actions {
        width: 100%;
    }

    .tf-btn-bulk {
        flex: 1;
    }

    .tf-pagination-area {
        align-items: flex-start;
        flex-direction: column;
    }

    .tf-pagination-scroll {
        width: 100%;
        justify-content: flex-start;
    }

    .tf-pagination-info {
        width: 100%;
    }

    #bulkEditModal .modal-dialog {
        margin: 20px auto;
    }
}

}
</style>

<div class="container-fluid tf-page">

{{-- =====================================================
     PAGE HEADER
====================================================== --}}

<div class="tf-header">

    <div>
        <h4 class="tf-title">
            Tujuan Filter
        </h4>

        <span class="tf-subtitle">
            Master data tujuan &amp; area pengiriman
        </span>
    </div>

    <a href="{{ route('spvplanner.tujuan.create') }}" class="tf-btn-add">
        <i class="fas fa-plus"></i>
        Tambah Tujuan
    </a>

</div>


{{-- =====================================================
     ALERTS
====================================================== --}}

@if (session('success'))
    <div class="alert alert-success tf-alert alert-dismissible fade show">
        <i class="fas fa-check-circle mr-2"></i>
        {{ session('success') }}
        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger tf-alert alert-dismissible fade show">
        <i class="fas fa-exclamation-circle mr-2"></i>
        {{ session('error') }}
        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger tf-alert alert-dismissible fade show">
        <strong><i class="fas fa-exclamation-circle mr-1"></i> Terjadi kesalahan</strong>
        <ul class="mb-0 mt-2">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
    </div>
@endif

@if (session('conflicts') && count(session('conflicts')) > 0)
    <div class="alert alert-warning tf-alert">
        <strong>Konflik area saat import:</strong>
        <ul class="mb-0 mt-2">
            @foreach (session('conflicts') as $c)
                <li>{{ $c }}</li>
            @endforeach
        </ul>
    </div>
@endif


{{-- =====================================================
     FILTER
====================================================== --}}

<div class="tf-card">

    <div class="tf-filter-header">
        <i class="fas fa-filter"></i>
        <h6 class="tf-filter-title">Filter Data</h6>
    </div>

    <div class="tf-filter-body">

        <form method="GET" action="{{ route('spvplanner.tujuan.index') }}">

            <div class="tf-filter-grid">

                <div class="tf-filter-item">
                    <label class="tf-filter-label">Pencarian Umum</label>
                    <div class="tf-search-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" class="tf-search-input"
                               value="{{ request('search') }}" placeholder="Cari di semua kolom...">
                    </div>
                </div>

                <div class="tf-filter-item">
                    <label class="tf-filter-label">Div</label>
                    <input type="text" name="Div" class="tf-select-input"
                           value="{{ request('Div') }}" placeholder="Semua Div">
                </div>

                <div class="tf-filter-item">
                    <label class="tf-filter-label">Customer ID</label>
                    <input type="text" name="customer_id" class="tf-select-input"
                           value="{{ request('customer_id') }}" placeholder="Semua Customer ID">
                </div>

                <div class="tf-filter-item">
                    <label class="tf-filter-label">Tujuan</label>
                    <input type="text" name="tujuan" class="tf-select-input"
                           value="{{ request('tujuan') }}" placeholder="Nama tujuan">
                </div>

                <div class="tf-filter-item">
                    <label class="tf-filter-label">Distribution Channel</label>
                    <input type="text" name="dist_channel" class="tf-select-input"
                           value="{{ request('dist_channel') }}" placeholder="Semua channel">
                </div>

                <div class="tf-filter-item">
                    <label class="tf-filter-label">Pulau</label>
                    <select name="pulau" class="tf-select-input">
                        <option value="">-- Semua Pulau --</option>
                        @foreach ($list_pulau as $pulau)
                            <option value="{{ $pulau }}" @selected(request('pulau') == $pulau)>{{ $pulau }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="tf-filter-item">
                    <label class="tf-filter-label">Area</label>
                    <select name="area" class="tf-select-input">
                        <option value="">-- Semua Area --</option>
                        @foreach ($list_area as $area)
                            <option value="{{ $area }}" @selected(request('area') == $area)>{{ $area }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="tf-filter-item">
                    <label class="tf-filter-label">Planner</label>
                    <input type="text" name="Planner" class="tf-select-input"
                           value="{{ request('Planner') }}" placeholder="Semua Planner">
                </div>

                <div class="tf-filter-item">
                    <label class="tf-filter-label">Monitoring</label>
                    <input type="text" name="Monitoring" class="tf-select-input"
                           value="{{ request('Monitoring') }}" placeholder="Semua Monitoring">
                </div>

                <div class="tf-filter-item">
                    <label class="tf-filter-label">Biaya Kuli</label>
                    <input type="text" name="biaya_kuli" class="tf-select-input"
                           value="{{ request('biaya_kuli') }}" placeholder="Cari biaya kuli">
                </div>

                <div class="tf-filter-item">
                    <label class="tf-filter-label">Transport Lead Time</label>
                    <input type="text" name="transport_lead_time" class="tf-select-input"
                           value="{{ request('transport_lead_time') }}" placeholder="Cari lead time">
                </div>

            </div>

            <div class="tf-filter-buttons-row">
                <button type="submit" class="tf-btn-filter" style="background:#2563eb;color:#fff;border:0;">
                    <i class="fas fa-search mr-1"></i> Cari
                </button>
                <a href="{{ route('spvplanner.tujuan.index') }}" class="tf-btn-filter tf-btn-filter-reset" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center;">
                    <i class="fas fa-sync-alt mr-1"></i> Reset
                </a>
            </div>

        </form>

    </div>

</div>


{{-- =====================================================
     IMPORT
====================================================== --}}

<div class="tf-card">

    <div class="tf-filter-header">
        <i class="fas fa-file-import"></i>
        <h6 class="tf-filter-title">Import CSV</h6>
    </div>

    <div class="tf-import-body">

        <form method="POST" action="{{ route('spvplanner.tujuan.import') }}" enctype="multipart/form-data"
              class="row g-2 align-items-end">
            @csrf
            <div class="col-md-7">
                <label class="tf-filter-label">File CSV (kolom: tujuan,area)</label>
                <input type="file" name="file" class="form-control tf-import-input" accept=".csv,.txt" required>
            </div>
            <div class="col-md-3">
                <button type="submit" class="tf-btn-import">
                    <i class="fas fa-upload mr-1"></i> Import
                </button>
            </div>
        </form>

    </div>

</div>


{{-- =====================================================
     DATA TABLE
====================================================== --}}

<div class="tf-card">

    <div class="tf-table-top">

        <div>
            <h6 class="tf-table-title">
                <i class="fas fa-list mr-1"></i>
                Daftar Tujuan
            </h6>
            <div class="tf-table-description">
                Data master tujuan &amp; area pengiriman
            </div>
        </div>

        <div class="tf-bulk-actions">

            <button type="button" id="btnDeleteSelected" class="tf-btn-bulk" disabled>
                <i class="fas fa-trash"></i>
                Hapus Terpilih (<span id="selectedCount">0</span>)
            </button>

            <button type="button" id="btnDeleteAll" class="tf-btn-bulk tf-btn-bulk-all">
                <i class="fas fa-trash-alt"></i>
                Hapus Semua Data
            </button>

            <button type="button" id="btnEditSelected" class="tf-btn-bulk tf-btn-bulk-edit" disabled>
                <i class="fas fa-edit"></i>
                Edit Terpilih (<span id="selectedCountEdit">0</span>)
            </button>

            @if (method_exists($data, 'total'))
                <span class="tf-data-count">
                    <i class="fas fa-database"></i>
                    {{ number_format($data->total(), 0, ',', '.') }} Data
                </span>
            @endif

        </div>

    </div>

    <div class="tf-table-wrapper">

        <form id="bulkForm" method="POST" action="{{ route('spvplanner.tujuan.bulk-destroy') }}">
            @csrf

            <table class="tf-table">

                <thead>
                    <tr>
                        <th class="tf-col-check">
                            <input type="checkbox" id="checkAll" title="Pilih Semua di Halaman Ini">
                        </th>
                        <th class="tf-col-no">No</th>
                        <th class="tf-col-tujuan">Div</th>
                        <th class="tf-col-tujuan">Customer ID</th>
                        <th class="tf-col-tujuan">Tujuan</th>
                        <th class="tf-col-channel">Distribution Channel</th>
                        <th class="tf-col-area">Pulau</th>
                        <th class="tf-col-area">Area</th>
                        <th class="tf-col-planner">Planner</th>
                        <th class="tf-col-monitoring">Monitoring</th>
                        <th class="tf-col-biaya_kuli">Biaya Kuli</th>
                        <th class="tf-col-transport_lead_time">Transport Lead Time</th>
                        <th class="tf-col-action tf-action-sticky">Aksi</th>
                    </tr>
                </thead>

                <tbody>

                    @forelse ($data as $row)
                        <tr data-row-id="{{ $row->id }}">
                            <td class="tf-col-check">
                                <input type="checkbox" name="ids[]" value="{{ $row->id }}" class="row-check">
                            </td>

                            <td class="tf-col-no">
                                {{ $data->firstItem() + $loop->index }}
                            </td>

                            <td class="tf-col-tujuan">
                                <span class="tf-channel-text">{{ $row->Div ?: '-' }}</span>
                            </td>

                            <td class="tf-col-tujuan">
                                <span class="tf-channel-text">{{ $row->customer_id ?: '-' }}</span>
                            </td>

                            <td class="tf-col-tujuan">
                                <span class="tf-tujuan-text">{{ $row->tujuan }}</span>
                            </td>

                            <td class="tf-col-channel">
                                <span class="tf-channel-text">{{ $row->dist_channel ?: '-' }}</span>
                            </td>

                            <td class="tf-col-area">
                                <span class="tf-channel-text">{{ $row->pulau ?: '-' }}</span>
                            </td>

                            <td class="tf-col-area">
                                <span class="tf-area-badge">{{ $row->area }}</span>
                            </td>

                            <td class="tf-col-planner">
                                <span class="tf-channel-text">{{ $row->Planner ?: '-' }}</span>
                            </td>

                            <td class="tf-col-monitoring">
                                <span class="tf-channel-text">{{ $row->Monitoring ?: '-' }}</span>
                            </td>

                            <td class="tf-col-biaya_kuli">
                                <span class="tf-channel-text">{{ $row->biaya_kuli ?: '-' }}</span>
                            </td>

                            <td class="tf-col-transport_lead_time">
                                <span class="tf-channel-text">{{ $row->transport_lead_time ?: '-' }}</span>
                            </td>

                            <td class="tf-col-action tf-action-sticky">
                                <div class="tf-action-wrapper">

                                    <a href="{{ route('spvplanner.tujuan.edit', $row->id) }}"
                                       class="tf-action-btn tf-action-edit" title="Edit Data">
                                        <i class="fas fa-edit"></i>
                                        <span>Edit</span>
                                    </a>

                                    <button type="button"
                                            class="tf-action-btn tf-action-delete btn-delete-single"
                                            data-id="{{ $row->id }}"
                                            title="Hapus Data">
                                        <i class="fas fa-trash"></i>
                                        <span>Hapus</span>
                                    </button>

                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr class="tf-empty-row">
                            <td colspan="13">
                                <div class="tf-empty-icon">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div class="tf-empty-title">Belum ada data tujuan</div>
                                <p class="tf-empty-description">Data tujuan &amp; area belum tersedia.</p>
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

    @if (method_exists($data, 'total') && $data->total() > 0)

        <div class="tf-pagination-area">

            <div class="tf-pagination-info">
                Menampilkan
                <strong>{{ $data->firstItem() }}</strong> -
                <strong>{{ $data->lastItem() }}</strong>
                dari <strong>{{ $data->total() }}</strong> data
            </div>

            @if ($data->lastPage() > 1)

                <div class="tf-pagination-scroll">

                    <ul class="tf-custom-pagination">

                        @if ($data->onFirstPage())
                            <li class="disabled">
                                <span><i class="fas fa-chevron-left"></i></span>
                            </li>
                        @else
                            <li>
                                <a href="{{ $data->appends(request()->query())->url($data->currentPage() - 1) }}">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        @endif

                        @php
                            $current = $data->currentPage();
                            $last = $data->lastPage();
                            $start = max(1, $current - 2);
                            $end = min($last, $current + 2);
                        @endphp

                        @if ($start > 1)
                            <li>
                                <a href="{{ $data->appends(request()->query())->url(1) }}">1</a>
                            </li>
                            @if ($start > 2)
                                <li class="disabled"><span>...</span></li>
                            @endif
                        @endif

                        @for ($page = $start; $page <= $end; $page++)
                            @if ($page == $current)
                                <li class="active"><span>{{ $page }}</span></li>
                            @else
                                <li>
                                    <a href="{{ $data->appends(request()->query())->url($page) }}">{{ $page }}</a>
                                </li>
                            @endif
                        @endfor

                        @if ($end < $last)
                            @if ($end < $last - 1)
                                <li class="disabled"><span>...</span></li>
                            @endif
                            <li>
                                <a href="{{ $data->appends(request()->query())->url($last) }}">{{ $last }}</a>
                            </li>
                        @endif

                        @if ($data->hasMorePages())
                            <li>
                                <a href="{{ $data->appends(request()->query())->url($current + 1) }}">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        @else
                            <li class="disabled">
                                <span><i class="fas fa-chevron-right"></i></span>
                            </li>
                        @endif

                    </ul>

                </div>

            @endif

        </div>

    @endif

</div>

</div>

{{-- =====================================================
     BULK EDIT MODAL
====================================================== --}}

<div class="modal fade" id="bulkEditModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">

      <form id="bulkEditForm" method="POST" action="{{ route('spvplanner.tujuan.bulk-update') }}">
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

          <div class="tf-form-group">
            <label class="tf-form-label">Div</label>
            <input type="text" name="Div" class="tf-form-input" placeholder="Kosongkan jika tidak diubah">
          </div>

          <div class="tf-form-group">
            <label class="tf-form-label">Distribution Channel</label>
            <input type="text" name="dist_channel" class="tf-form-input" placeholder="Kosongkan jika tidak diubah">
          </div>

          <div class="tf-form-group">
            <label class="tf-form-label">Pulau</label>
            <input type="text" name="pulau" class="tf-form-input" placeholder="Kosongkan jika tidak diubah">
          </div>

          <div class="tf-form-group">
            <label class="tf-form-label">Area</label>
            <input type="text" name="area" class="tf-form-input" list="area-list-bulk" placeholder="Kosongkan jika tidak diubah">
            <datalist id="area-list-bulk">
                @foreach ($list_area as $area)
                    <option value="{{ $area }}">
                @endforeach
            </datalist>
          </div>

          <div class="tf-form-group">
            <label class="tf-form-label">Planner</label>
            <input type="text" name="Planner" class="tf-form-input" placeholder="Kosongkan jika tidak diubah">
          </div>

          <div class="tf-form-group">
            <label class="tf-form-label">Monitoring</label>
            <input type="text" name="Monitoring" class="tf-form-input" placeholder="Kosongkan jika tidak diubah">
          </div>

          <div class="tf-form-group">
            <label class="tf-form-label">Biaya Kuli</label>
            <input type="text" name="biaya_kuli" class="tf-form-input" placeholder="Kosongkan jika tidak diubah">
          </div>

          <div class="tf-form-group">
            <label class="tf-form-label">Transport Lead Time</label>
            <input type="number" name="transport_lead_time" class="tf-form-input" min="0" placeholder="Kosongkan jika tidak diubah">
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="tf-btn-cancel" data-dismiss="modal">Batal</button>
          <button type="submit" class="tf-btn-submit">
              <i class="fas fa-save mr-1"></i> Simpan Perubahan
          </button>
        </div>
      </form>

    </div>
  </div>
</div>

{{-- =====================================================
     JS: CHECKBOX, BULK DELETE, DELETE ALL, DELETE SINGLE, BULK EDIT
     (murni vanilla JS, tidak butuh jQuery / Bootstrap JS sama sekali)
====================================================== --}}

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

        // sinkronkan status "select all" kalau semua/tidak semua tercentang
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

    // ===== Modal Edit Terpilih (vanilla JS, TIDAK pakai jQuery/$) =====
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

    // Tombol close (x) & "Batal" — keduanya punya [data-dismiss="modal"]
    bulkEditModal?.querySelectorAll('[data-dismiss="modal"]').forEach(function (el) {
        el.addEventListener('click', closeBulkEditModal);
    });

    // Klik di area gelap luar modal-content untuk menutup
    bulkEditModal?.addEventListener('click', function (e) {
        if (e.target === bulkEditModal) closeBulkEditModal();
    });

    // Tombol Escape untuk menutup
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && bulkEditModal?.classList.contains('show')) {
            closeBulkEditModal();
        }
    });

    // ===== Hapus Terpilih (bulk) =====
    btnDeleteSelected?.addEventListener('click', function () {
        const checked = document.querySelectorAll('.row-check:checked');
        if (checked.length === 0) return;

        if (confirm('Yakin ingin menghapus ' + checked.length + ' data tujuan yang dipilih?')) {
            bulkForm.submit();
        }
    });

    // ===== Hapus Semua (delete all) =====
    btnDeleteAll?.addEventListener('click', function () {
        const confirmText = prompt('Ini akan menghapus SEMUA data tujuan tanpa terkecuali.\nKetik "HAPUS SEMUA" untuk konfirmasi:');

        if (confirmText === null) return; // user cancel

        if (confirmText.trim().toUpperCase() !== 'HAPUS SEMUA') {
            alert('Konfirmasi tidak sesuai, aksi dibatalkan.');
            return;
        }

        btnDeleteAll.disabled = true;

        fetch("{{ route('spvplanner.tujuan.destroy-all') }}", {
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

    // ===== Hapus satu baris =====
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-delete-single');
        if (!btn) return;

        const id = btn.dataset.id;
        if (!confirm('Yakin ingin menghapus tujuan ini?')) return;

        btn.disabled = true;

        fetch('/spvplanner/tujuan/' + id, {
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