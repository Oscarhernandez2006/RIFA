<?php

namespace App\Services;

use App\Exceptions\InvoiceServiceUnavailable;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class InvoiceLookup
{
    /**
     * Busca la factura por consecutivo dentro del punto de venta indicado (id_co).
     * La API no filtra por tienda y el consecutivo se repite entre tiendas.
     *
     * @return array{document_type: string, document_number: string, issued_on: string, point_of_sale: ?string, net_total: float, annulled: bool}|null
     */
    public function find(int $consecutive, int $pointOfSale, int $company): ?array
    {
        $config = config('services.invoices');

        if (blank($config['token'] ?? null)) {
            Log::error('Invoice API token is not configured.');
            throw new InvoiceServiceUnavailable;
        }

        try {
            // The token travels as a query parameter: never log URLs or exception messages from this call.
            $response = Http::acceptJson()
                ->connectTimeout(5)
                ->timeout(20)
                ->get($config['url'], [
                    'fecha_inicio' => $config['start_date'],
                    'fecha_fin' => $config['end_date'],
                    'cia' => $company,
                    'consec' => $consecutive,
                    'token' => $config['token'],
                ]);
        } catch (ConnectionException) {
            Log::warning('Invoice API connection failed.');
            throw new InvoiceServiceUnavailable;
        }

        if (! $response->successful()) {
            Log::warning('Invoice API returned an error.', ['status' => $response->status()]);
            throw new InvoiceServiceUnavailable;
        }

        $rows = $response->json('data');

        if (! is_array($rows)) {
            Log::warning('Invoice API returned an unexpected payload.');
            throw new InvoiceServiceUnavailable;
        }

        $rows = array_values(array_filter(
            $rows,
            fn ($row) => is_array($row)
                && is_numeric($row['id_co'] ?? null)
                && (int) $row['id_co'] === $pointOfSale
                && $this->parse($row['nro_documento'] ?? null, $consecutive) !== null,
        ));

        if ($rows === []) {
            return null;
        }

        $row = $rows[0];
        $documentType = $this->parse($row['nro_documento'], $consecutive);

        try {
            $issuedOn = CarbonImmutable::parse($row['fecha_documento'])->toDateString();
        } catch (Throwable) {
            Log::warning('Invoice API returned an invalid document date.');
            throw new InvoiceServiceUnavailable;
        }

        if (! is_numeric($row['fac_valor_neto'] ?? null)) {
            Log::warning('Invoice API returned an invalid net total.');
            throw new InvoiceServiceUnavailable;
        }

        $pointOfSaleName = is_string($row['punto_venta'] ?? null) ? trim($row['punto_venta']) : '';

        return [
            'document_type' => $documentType,
            'document_number' => trim($row['nro_documento']),
            'issued_on' => $issuedOn,
            'point_of_sale' => $pointOfSaleName !== '' ? mb_substr($pointOfSaleName, 0, 120) : null,
            'net_total' => (float) $row['fac_valor_neto'],
            'annulled' => collect($rows)->contains(fn ($item) => filled($item['fecha_ts_anulacion'] ?? null)),
        ];
    }

    /**
     * Devuelve el tipo de documento (prefijo) si el número corresponde al consecutivo buscado.
     */
    private function parse(mixed $number, int $consecutive): ?string
    {
        if (! is_string($number) || preg_match('/^\s*([0-9A-Z]{1,5})-0*(\d+)\s*$/i', $number, $parts) !== 1) {
            return null;
        }

        return (int) $parts[2] === $consecutive ? strtoupper($parts[1]) : null;
    }
}
