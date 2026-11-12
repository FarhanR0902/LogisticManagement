<?php

return [
    // 'odbc' = langsung ke SAP HANA | 'api' = lewat endpoint REST/OData dari tim SAP
    'mode'   => env('SAP_MODE', 'odbc'),

    // ----- mode odbc -----
    'driver' => env('SAP_HANA_DRIVER', 'HDBODBC'),
    'node'   => env('SAP_HANA_NODE'),          // contoh: 10.1.2.3:30015
    'db'     => env('SAP_HANA_DB'),
    'user'   => env('SAP_HANA_USER'),
    'pass'   => env('SAP_HANA_PASS'),
    'schema' => env('SAP_HANA_SCHEMA', 'SAPHANADB'),
    'mandt'  => env('SAP_HANA_MANDT', '300'),

    // Kalau tim SAP sudah buat view, isi nama view-nya -> query template diabaikan
    'view'          => env('SAP_HANA_VIEW'),
    'view_date_col' => env('SAP_HANA_VIEW_DATE_COL', 'tanggal_shipment'),

    // ----- mode api -----
    'api_url'   => env('SAP_API_URL'),
    'api_user'  => env('SAP_API_USER'),
    'api_pass'  => env('SAP_API_PASS'),
    'api_token' => env('SAP_API_TOKEN'),       // opsional, kalau pakai bearer token
];