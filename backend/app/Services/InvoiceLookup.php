<?php

namespace App\Services;

use App\Exceptions\InvoiceServiceUnavailable;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class InvoiceLookup
{
    /**
     * Busca las facturas con ese consecutivo en el punto de venta indicado (id_co).
     * La API no filtra por tienda; además cada tienda maneja varias series (01F, 02F,
     * 04F...) con contadores propios, por lo que un mismo consecutivo puede
     * corresponder a varias facturas. Se devuelven todas, de la más reciente a la más antigua.
     *
     * @return list<array{document_type: string, document_number: string, issued_on: string, point_of_sale: ?string, net_total: float, annulled: bool}>
     */
    public function find(int $consecutive, int $pointOfSale, int $company): array
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

        $rows = array_filter(
            $rows,
            fn ($row) => is_array($row)
                && is_numeric($row['id_co'] ?? null)
                && (int) $row['id_co'] === $pointOfSale
                && $this->parse($row['nro_documento'] ?? null, $consecutive) !== null,
        );

        // La API devuelve una fila por ítem de la factura: se agrupa por número completo y se
        // ordena de la más reciente a la más antigua (fecha y, para desempatar, hora de creación).
        return collect($rows)
            ->groupBy(fn (array $row) => $this->parse($row['nro_documento'], $consecutive))
            ->map(fn ($items, string $documentType) => $this->invoice($items->values()->all(), $documentType))
            ->sortByDesc(fn (array $invoice) => $invoice['issued_on'].' '.$invoice['created_at'])
            ->map(fn (array $invoice) => Arr::except($invoice, 'created_at'))
            ->values()
            ->all();
    }

    /**
     * @param  non-empty-list<array<string, mixed>>  $rows  Filas (ítems) de una misma factura.
     * @return array{document_type: string, document_number: string, issued_on: string, point_of_sale: ?string, net_total: float, annulled: bool}
     */
    private function invoice(array $rows, string $documentType): array
    {
        $row = $rows[0];

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
        $createdAt = is_string($row['fecha_ts_creacion'] ?? null) ? trim($row['fecha_ts_creacion']) : '';

        return [
            'document_type' => $documentType,
            'document_number' => trim($row['nro_documento']),
            'issued_on' => $issuedOn,
            'point_of_sale' => $pointOfSaleName !== '' ? mb_substr($pointOfSaleName, 0, 120) : null,
            'net_total' => (float) $row['fac_valor_neto'],
            'annulled' => collect($rows)->contains(fn ($item) => filled($item['fecha_ts_anulacion'] ?? null)),
            'created_at' => $createdAt,
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
