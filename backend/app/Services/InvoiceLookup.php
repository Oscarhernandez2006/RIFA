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
     * @return array{document_number: string, issued_on: string, point_of_sale: ?string, net_total: float, annulled: bool}|null
     */
    public function find(string $type, int $consecutive): ?array
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
                    'cia' => $config['company'],
                    'tipo_docto' => $type,
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
            fn ($row) => is_array($row) && $this->matches($row['nro_documento'] ?? null, $type, $consecutive),
        ));

        if ($rows === []) {
            return null;
        }

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

        $pointOfSale = is_string($row['punto_venta'] ?? null) ? trim($row['punto_venta']) : '';

        return [
            'document_number' => trim($row['nro_documento']),
            'issued_on' => $issuedOn,
            'point_of_sale' => $pointOfSale !== '' ? mb_substr($pointOfSale, 0, 120) : null,
            'net_total' => (float) $row['fac_valor_neto'],
            'annulled' => collect($rows)->contains(fn ($item) => filled($item['fecha_ts_anulacion'] ?? null)),
        ];
    }

    private function matches(mixed $number, string $type, int $consecutive): bool
    {
        return is_string($number)
            && preg_match('/^\s*([0-9A-Z]+)-0*(\d+)\s*$/i', $number, $parts) === 1
            && strtoupper($parts[1]) === $type
            && (int) $parts[2] === $consecutive;
    }
}
