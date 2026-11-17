<?php

namespace App\Http\Controllers;

use App\Exceptions\InvoiceServiceUnavailable;
use App\Models\Invoice;
use App\Services\InvoiceLookup;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    private const DUPLICATE = 'Esta factura ya fue registrada en la campaña.';

    private const NUMBER_HELP = 'Escribe solo los dígitos que van después de la letra C en tu factura, por ejemplo 25533.';

    public function index(Request $request): JsonResponse
    {
        $invoices = $request->user()->invoices()->latest()->latest('id')->get();

        return response()->json([
            'invoices' => $invoices->map->toApi()->values(),
            'tickets_total' => (int) $invoices->sum('tickets'),
            'ticket_value' => (int) config('services.invoices.ticket_value'),
            'points_of_sale' => collect(config('services.invoices.points_of_sale'))
                ->map(fn (array $store, int $id) => ['id' => $id, 'name' => $store['name']])
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values(),
        ])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, InvoiceLookup $lookup): JsonResponse
    {
        $request->merge([
            'invoice_number' => is_string($request->input('invoice_number'))
                ? strtoupper(preg_replace('/\s+/', '', $request->input('invoice_number')))
                : $request->input('invoice_number'),
        ]);

        $pointsOfSale = array_keys(config('services.invoices.points_of_sale'));

        $request->validate([
            // Se admite el consecutivo solo (25533) o con el prefijo del tiquete/API (931C25533, 01F-00025533).
            // El prefijo debe incluir una letra para no confundir dígitos del consecutivo con un prefijo.
            'invoice_number' => ['required', 'string', 'max:20', 'regex:/^(?:(?=[0-9A-Z]*[A-Z])[0-9A-Z]{1,5}-?)?\d{1,10}$/'],
            'point_of_sale' => ['required', 'integer', Rule::in($pointsOfSale)],
        ], [
            'invoice_number.required' => 'Ingresa el número de tu factura.',
            'invoice_number.string' => 'El número de factura no es válido.',
            'invoice_number.max' => 'El número de factura no es válido.',
            'invoice_number.regex' => self::NUMBER_HELP,
            'point_of_sale.required' => 'Selecciona el punto de venta donde hiciste la compra.',
            'point_of_sale.integer' => 'El punto de venta no es válido.',
            'point_of_sale.in' => 'El punto de venta no es válido.',
        ]);

        preg_match('/(\d{1,10})$/', $request->input('invoice_number'), $digits);
        $consecutive = (int) $digits[1];
        $pointOfSale = (int) $request->input('point_of_sale');
        $company = (int) config("services.invoices.points_of_sale.{$pointOfSale}.company");

        if ($consecutive < 1) {
            $this->fail('El número de factura no es válido.');
        }

        try {
            $candidates = $lookup->find($consecutive, $pointOfSale, $company);
        } catch (InvoiceServiceUnavailable) {
            return response()->json([
                'message' => 'No pudimos validar tu factura en este momento. Inténtalo de nuevo en unos minutos.',
            ], 503);
        }

        if ($candidates === []) {
            $this->fail('No encontramos esta factura en el punto de venta seleccionado. Revisa el número y la tienda, y que la compra sea entre el 1 de octubre y el 31 de diciembre de 2026.');
        }

        // Varias series de la tienda pueden compartir el consecutivo: se descartan las ya
        // registradas y se toma la más reciente que no esté anulada.
        $registered = Invoice::where('company_id', $company)
            ->where('point_of_sale_id', $pointOfSale)
            ->where('consecutive', $consecutive)
            ->pluck('document_type')
            ->all();

        $available = array_values(array_filter($candidates, fn (array $candidate) => ! in_array($candidate['document_type'], $registered, true)));

        if ($available === []) {
            $this->fail(self::DUPLICATE);
        }

        $found = collect($available)->first(fn (array $candidate) => ! $candidate['annulled']);

        if ($found === null) {
            $this->fail('Esta factura está anulada y no puede participar.');
        }

        $ticketValue = (int) config('services.invoices.ticket_value');
        $tickets = Invoice::ticketsFor($found['net_total'], $ticketValue);

        if ($tickets < 1) {
            $this->fail(sprintf(
                'Esta factura no alcanza el mínimo de %s para obtener una boleta. Se otorga una boleta por cada %s de compra.',
                $this->pesos($ticketValue),
                $this->pesos($ticketValue),
            ));
        }

        try {
            $invoice = $request->user()->invoices()->create([
                'company_id' => $company,
                'point_of_sale_id' => $pointOfSale,
                'document_type' => $found['document_type'],
                'consecutive' => $consecutive,
                'document_number' => $found['document_number'],
                'issued_on' => $found['issued_on'],
                'point_of_sale' => $found['point_of_sale'],
                'net_total' => $found['net_total'],
                'tickets' => $tickets,
            ]);
        } catch (UniqueConstraintViolationException) {
            $this->fail(self::DUPLICATE);
        }

        return response()->json(['invoice' => $invoice->toApi()], 201);
    }

    private function pesos(int $amount): string
    {
        return '$'.number_format($amount, 0, ',', '.');
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['invoice_number' => [$message]]);
    }
}
