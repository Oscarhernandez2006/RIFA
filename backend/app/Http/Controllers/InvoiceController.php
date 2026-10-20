<?php

namespace App\Http\Controllers;

use App\Exceptions\InvoiceServiceUnavailable;
use App\Models\Invoice;
use App\Services\InvoiceLookup;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    private const DUPLICATE = 'Esta factura ya fue registrada en la campaña.';

    public function index(Request $request): JsonResponse
    {
        $invoices = $request->user()->invoices()->latest()->latest('id')->get();

        return response()->json([
            'invoices' => $invoices->map->toApi()->values(),
        ])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, InvoiceLookup $lookup): JsonResponse
    {
        $request->merge([
            'invoice_number' => is_string($request->input('invoice_number'))
                ? strtoupper(trim($request->input('invoice_number')))
                : $request->input('invoice_number'),
        ]);

        $request->validate([
            'invoice_number' => ['required', 'string', 'max:20', 'regex:/^[0-9A-Z]{2,4}-\d{1,10}$/'],
        ], [
            'invoice_number.required' => 'Ingresa el número de tu factura.',
            'invoice_number.string' => 'El número de factura no es válido.',
            'invoice_number.max' => 'El número de factura no es válido.',
            'invoice_number.regex' => 'Escribe el número como aparece en tu factura, por ejemplo 07F-00028641.',
        ]);

        [$type, $digits] = explode('-', $request->input('invoice_number'));
        $consecutive = (int) $digits;
        $company = (int) config('services.invoices.company');

        if ($consecutive < 1) {
            $this->fail('El número de factura no es válido.');
        }

        $alreadyRegistered = Invoice::where('company_id', $company)
            ->where('document_type', $type)
            ->where('consecutive', $consecutive)
            ->exists();

        if ($alreadyRegistered) {
            $this->fail(self::DUPLICATE);
        }

        try {
            $found = $lookup->find($type, $consecutive);
        } catch (InvoiceServiceUnavailable) {
            return response()->json([
                'message' => 'No pudimos validar tu factura en este momento. Inténtalo de nuevo en unos minutos.',
            ], 503);
        }

        if ($found === null) {
            $this->fail('No encontramos esta factura. Revisa el número y que la compra sea entre el 1 de octubre y el 31 de diciembre de 2026.');
        }

        if ($found['annulled']) {
            $this->fail('Esta factura está anulada y no puede participar.');
        }

        try {
            $invoice = $request->user()->invoices()->create([
                'company_id' => $company,
                'document_type' => $type,
                'consecutive' => $consecutive,
                'document_number' => $found['document_number'],
                'issued_on' => $found['issued_on'],
                'point_of_sale' => $found['point_of_sale'],
                'net_total' => $found['net_total'],
            ]);
        } catch (UniqueConstraintViolationException) {
            $this->fail(self::DUPLICATE);
        }

        return response()->json(['invoice' => $invoice->toApi()], 201);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['invoice_number' => [$message]]);
    }
}
