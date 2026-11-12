<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PDO;

class SapShipmentService
{
    /**
     * Ambil shipment N hari terakhir.
     * Return: array baris, key = slug header Excel (nomer_shipment, sold_to_party_name, ...)
     */
    public function fetch(int $days = 7): array
    {
        $rows = config('sap.mode') === 'api'
            ? $this->fetchApi($days)
            : $this->fetchOdbc($days);

        return array_map(fn($r) => $this->normalizeKeys((array) $r), $rows);
    }

    // =====================================================
    // MODE ODBC (langsung ke SAP HANA)
    // =====================================================
    private function fetchOdbc(int $days): array
    {
        $stmt = $this->pdo()->prepare($this->query());
        $stmt->execute([$days]);

        $rows = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = $r;
        }
        return $rows;
    }

    private function pdo(): PDO
    {
        $dsn = sprintf('odbc:Driver={%s};ServerNode=%s;', config('sap.driver'), config('sap.node'));
        if (config('sap.db')) {
            $dsn .= 'DatabaseName=' . config('sap.db') . ';';
        }

        return new PDO($dsn, config('sap.user'), config('sap.pass'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }

    private function query(): string
    {
        $s = config('sap.schema');

        // Kalau sudah ada view dari tim SAP -> pakai ini (paling disarankan)
        if ($view = config('sap.view')) {
            $col = config('sap.view_date_col');
            return "SELECT * FROM {$s}.{$view} WHERE {$col} >= ADD_DAYS(CURRENT_DATE, -?)";
        }

        $m = config('sap.mandt');

        // TEMPLATE: tabel/kolom standar SAP SD. WAJIB diverifikasi tim SAP.
        return "
            SELECT
                TO_VARCHAR(k.ERDAT, 'MM-YYYY')       AS \"periode_shipment\",
                TO_VARCHAR(k.ERDAT, 'YYYY-MM-DD')    AS \"tanggal_shipment\",
                NULL                                 AS \"planner_name\",
                k.TKNUM                              AS \"nomer_shipment\",
                c.NAME1                              AS \"sold_to_party_name\",
                l.VGBEL                              AS \"sales_document\",
                v.BSTNK                              AS \"customer_reference\",
                TO_VARCHAR(v.GUEEN, 'YYYY-MM-DD')    AS \"contract_end_date\",
                d.VBELN                              AS \"no_do\",
                k.VSART                              AS \"channel\",
                lf.NAME1                             AS \"vendor\",
                k.ROUTE                              AS \"route_name\",
                k.SHTYP                              AS \"shipping_type\",
                SUM(l.NETWR)                         AS \"sum_of_nilai_do\",
                SUM(l.LFIMG)                         AS \"sum_of_qty_do\",
                SUM(l.VOLUM)                         AS \"sum_of_kubikasi2\",
                SUM(l.NTGEW) / 1000                  AS \"sum_of_tonase3\"
            FROM {$s}.VTTK k
            JOIN {$s}.VTTP p  ON p.MANDT = k.MANDT AND p.TKNUM = k.TKNUM
            JOIN {$s}.LIKP d  ON d.MANDT = p.MANDT AND d.VBELN = p.VBELN
            JOIN {$s}.LIPS l  ON l.MANDT = d.MANDT AND l.VBELN = d.VBELN
            LEFT JOIN {$s}.VBAK v  ON v.MANDT = l.MANDT AND v.VBELN = l.VGBEL
            LEFT JOIN {$s}.KNA1 c  ON c.MANDT = d.MANDT AND c.KUNNR = d.KUNAG
            LEFT JOIN {$s}.LFA1 lf ON lf.MANDT = k.MANDT AND lf.LIFNR = k.TDLNR
            WHERE k.MANDT = '{$m}'
              AND k.ERDAT >= ADD_DAYS(CURRENT_DATE, -?)
            GROUP BY k.ERDAT, k.TKNUM, c.NAME1, l.VGBEL, v.BSTNK, v.GUEEN,
                     d.VBELN, k.VSART, lf.NAME1, k.ROUTE, k.SHTYP
        ";
    }

    // =====================================================
    // MODE API (REST / OData dari tim SAP)
    // =====================================================
    private function fetchApi(int $days): array
    {
        $http = Http::timeout(120)->acceptJson();

        if ($token = config('sap.api_token')) {
            $http = $http->withToken($token);
        } elseif (config('sap.api_user')) {
            $http = $http->withBasicAuth(config('sap.api_user'), config('sap.api_pass'));
        }

        $json = $http->get(config('sap.api_url'), ['days' => $days])->throw()->json() ?? [];

        // dukung bentuk umum: {data:[...]}, OData {d:{results:[...]}}, {value:[...]}, atau array langsung
        return $json['data']
            ?? $json['d']['results']
            ?? $json['value']
            ?? (array_is_list($json) ? $json : []);
    }

    /** "Sold-To Party Name" / "SOLD_TO_PARTY_NAME" -> "sold_to_party_name" (sama dengan slug header Excel) */
    private function normalizeKeys(array $row): array
    {
        $out = [];
        foreach ($row as $k => $v) {
            $out[Str::slug((string) $k, '_')] = is_string($v) ? trim($v) : $v;
        }
        return $out;
    }
}