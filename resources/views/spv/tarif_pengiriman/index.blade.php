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

</style>

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

    <a href="{{ route('spvplanner.tarif.create') }}"
       class="btn-add">

        <i class="fas fa-plus"></i>

        Tambah Data

    </a>

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


        @if(method_exists($data, 'total'))

            <span class="data-count">

                <i class="fas fa-database"></i>

                {{ number_format($data->total(), 0, ',', '.') }}

                Data

            </span>

        @endif

    </div>


    {{-- TABLE --}}

    <div class="table-wrapper">

        <table class="tarif-table">

            <thead>

                <tr>

                    <th class="col-no">
                        No
                    </th>

                    <th class="col-agent">
                        Service Agent
                    </th>

                    <th class="col-ekspedisi">
                        Ekspedisi
                    </th>

                    <th class="col-sh center">
                        SH
                    </th>

                    <th class="col-mobil">
                        Mobil
                    </th>

                    <th class="col-routew">
                        Route W
                    </th>

                    <th class="col-route">
                        Route
                    </th>

                    <th class="col-biaya">
                        Biaya Kirim
                    </th>

                    <th class="col-unit center">
                        Unit
                    </th>

                    <th class="col-s center">
                        S1
                    </th>

                    <th class="col-s center">
                        S2
                    </th>

                    <th class="col-date">
                        Valid From
                    </th>

                    <th class="col-date">
                        Valid To
                    </th>

                    {{-- STICKY ACTION --}}

                    <th class="col-action action-sticky">
                        Aksi
                    </th>

                </tr>

            </thead>


            <tbody>

                @forelse($data as $item)

                    <tr>

                        {{-- NO --}}

                        <td class="col-no">

                            @if(method_exists($data, 'firstItem'))

                                {{ $data->firstItem() + $loop->index }}

                            @else

                                {{ $loop->iteration }}

                            @endif

                        </td>


                        {{-- SERVICE AGENT --}}

                        <td class="col-agent">

                            @if(!empty($item->servc_agent))

                                <span class="agent-badge">

                                    {{ $item->servc_agent }}

                                </span>

                            @else

                                -

                            @endif

                        </td>


                        {{-- EKSPEDISI --}}

                        <td class="col-ekspedisi">

                            <span class="ekspedisi-text">

                                {{ $item->ekpedisi ?: '-' }}

                            </span>

                        </td>


                        {{-- SH --}}

                        <td class="col-sh center">

                            {{ $item->sh ?: '-' }}

                        </td>


                        {{-- MOBIL --}}

                        <td class="col-mobil">

                            <span class="mobil-text">

                                {{ $item->mobil ?: '-' }}

                            </span>

                        </td>


                        {{-- ROUTE W --}}

                        <td class="col-routew">

                            {{ $item->routew ?: '-' }}

                        </td>


                        {{-- ROUTE --}}

                        <td class="col-route">

                            <div class="route-text"
                                 title="{{ $item->route }}">

                                {{ $item->route ?: '-' }}

                            </div>

                        </td>


                        {{-- BIAYA KIRIM --}}

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


                        {{-- UNIT --}}

                        <td class="col-unit center">

                            {{ $item->unit ?: '-' }}

                        </td>


                        {{-- S1 --}}

                        <td class="col-s center">

                            {{ $item->s_1 ?: '-' }}

                        </td>


                        {{-- S2 --}}

                        <td class="col-s center">

                            {{ $item->s_2 ?: '-' }}

                        </td>


                        {{-- VALID FROM --}}

                        <td class="col-date date-text">

                            {{ $item->valid_from ?: '-' }}

                        </td>


                        {{-- VALID TO --}}

                        <td class="col-date date-text">

                            {{ $item->valid_to ?: '-' }}

                        </td>


                        {{-- =================================================
                             ACTION
                        ================================================== --}}

                        <td class="col-action action-sticky">

                            <div class="action-wrapper">

                                {{-- EDIT --}}

                                <a href="{{ route('spvplanner.tarif.edit', $item->id) }}"
                                   class="action-btn action-edit"
                                   title="Edit Data">

                                    <i class="fas fa-edit"></i>

                                    <span>Edit</span>

                                </a>


                                {{-- DELETE --}}

                                <form
                                    action="{{ route('spvplanner.tarif.destroy', $item->id) }}"
                                    method="POST"
                                    style="display:inline !important; margin:0 !important;"
                                    onsubmit="return confirm('Yakin ingin menghapus data ini?')"
                                >

                                    @csrf

                                    @method('DELETE')

                                    <button
                                        type="submit"
                                        class="action-btn action-delete"
                                        title="Hapus Data"
                                    >

                                        <i class="fas fa-trash"></i>
                                         <span>Hapus</span>

                                    </button>

                                </form>

                            </div>

                        </td>

                    </tr>

                @empty

                    <tr class="empty-row">

                        <td colspan="14">

                            <div class="empty-icon">

                                <i class="fas fa-file-invoice"></i>

                            </div>

                            <div class="empty-title">

                                Belum ada data tarif

                            </div>

                            <p class="empty-description">

                                Data tarif pengiriman belum tersedia.

                            </p>

                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

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

@endsection
