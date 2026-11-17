<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://invoices.test/ventas/facturas-pos';

    private const NOT_FOUND = 'No encontramos esta factura en el punto de venta seleccionado. Revisa el número y la tienda, y que la compra sea entre el 1 de octubre y el 31 de diciembre de 2026.';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config(['services.invoices' => [
            'url' => self::URL,
            'token' => 'test-token',
            'company' => 4,
            'start_date' => '2026-10-01',
            'end_date' => '2026-12-31',
            'ticket_value' => 70000,
            'points_of_sale' => [413 => 'La 93', 414 => 'Centro', 402 => 'Malambo'],
        ]]);
    }

    private function row(array $overrides = []): array
    {
        return [
            'fecha_documento' => '2026-11-17T00:00:00',
            'nro_documento' => '01F-00025533',
            'id_co' => '413',
            'punto_venta' => 'PDV LA 93',
            'fac_valor_neto' => 210000.0,
            'fecha_ts_anulacion' => null,
            'razon_social_cliente' => 'CLIENTE',
            ...$overrides,
        ];
    }

    private function fakeApi(array $rows, int $status = 200): void
    {
        Http::fake([self::URL.'*' => Http::response(['total' => count($rows), 'data' => $rows], $status)]);
    }

    private function register(User $user, string $number = '25533', int|string|null $pointOfSale = 413)
    {
        return $this->actingAs($user)->postJson('/api/invoices', array_filter([
            'invoice_number' => $number,
            'point_of_sale' => $pointOfSale,
        ], fn ($value) => $value !== null));
    }

    public function test_existing_invoice_is_validated_and_saved_for_the_customer(): void
    {
        $user = User::factory()->create();
        $this->fakeApi([$this->row(['tipo_comercial' => 'PATA DE RES']), $this->row(['tipo_comercial' => 'CHORIZO'])]);

        $this->register($user, ' 25533 ')
            ->assertCreated()
            ->assertJsonPath('invoice.number', '01F-00025533')
            ->assertJsonPath('invoice.consecutive', 25533)
            ->assertJsonPath('invoice.issued_on', '2026-11-17')
            ->assertJsonPath('invoice.point_of_sale', 'PDV LA 93')
            ->assertJsonPath('invoice.net_total', 210000)
            ->assertJsonPath('invoice.tickets', 3)
            ->assertJsonMissingPath('invoice.razon_social_cliente');

        Http::assertSent(fn (Request $request) => $request->url() === self::URL.'?fecha_inicio=2026-10-01&fecha_fin=2026-12-31&cia=4&consec=25533&token=test-token');
        $this->assertDatabaseHas('invoices', [
            'user_id' => $user->id,
            'company_id' => 4,
            'point_of_sale_id' => 413,
            'document_type' => '01F',
            'consecutive' => 25533,
            'document_number' => '01F-00025533',
            'tickets' => 3,
        ]);

        $this->getJson('/api/invoices')
            ->assertOk()
            ->assertJsonCount(1, 'invoices')
            ->assertJsonPath('invoices.0.number', '01F-00025533')
            ->assertJsonPath('invoices.0.tickets', 3)
            ->assertJsonPath('tickets_total', 3)
            ->assertJsonPath('ticket_value', 70000)
            ->assertJsonCount(3, 'points_of_sale')
            ->assertJsonPath('points_of_sale.0', ['id' => 413, 'name' => 'La 93']);
    }

    public function test_number_is_accepted_with_receipt_or_api_prefixes(): void
    {
        $user = User::factory()->create();
        $this->fakeApi([$this->row()]);

        foreach (['931C25533', '931c25533', '01F-00025533', '0025533'] as $number) {
            $this->register($user, $number)->assertCreated()->assertJsonPath('invoice.consecutive', 25533);
            Invoice::query()->delete();
        }
    }

    public function test_same_consecutive_in_another_store_is_a_different_invoice(): void
    {
        $user = User::factory()->create();
        $this->fakeApi([
            $this->row(['id_co' => '414', 'punto_venta' => 'PDV CENTRO', 'fac_valor_neto' => 70000.0]),
            $this->row(['id_co' => '413', 'punto_venta' => 'PDV LA 93', 'fac_valor_neto' => 140000.0]),
        ]);

        $this->register($user, '25533', 414)
            ->assertCreated()
            ->assertJsonPath('invoice.point_of_sale', 'PDV CENTRO')
            ->assertJsonPath('invoice.tickets', 1);

        $this->register($user, '25533', 413)
            ->assertCreated()
            ->assertJsonPath('invoice.point_of_sale', 'PDV LA 93')
            ->assertJsonPath('invoice.tickets', 2);

        $this->register($user, '25533', 402)
            ->assertUnprocessable()
            ->assertJsonPath('errors.invoice_number.0', self::NOT_FOUND);

        $this->assertDatabaseCount('invoices', 2);
    }

    public function test_point_of_sale_is_required_and_must_be_known(): void
    {
        $user = User::factory()->create();

        foreach ([null, 999, 'abc', ''] as $pointOfSale) {
            $this->register($user, '25533', $pointOfSale)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('point_of_sale');
        }

        Http::assertNothingSent();
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_tickets_are_one_per_full_ticket_value_and_remainder_is_discarded(): void
    {
        $user = User::factory()->create();
        $cases = [1 => [70000.0, 1], 2 => [139999.99, 1], 3 => [140000.0, 2], 4 => [150000.0, 2], 5 => [1000000.0, 14]];
        $this->fakeApi(array_map(
            fn (int $consecutive) => $this->row(['nro_documento' => sprintf('01F-%08d', $consecutive), 'fac_valor_neto' => $cases[$consecutive][0]]),
            array_keys($cases),
        ));

        foreach ($cases as $consecutive => [, $tickets]) {
            $this->register($user, (string) $consecutive)
                ->assertCreated()
                ->assertJsonPath('invoice.tickets', $tickets);
        }

        $this->actingAs($user)
            ->getJson('/api/invoices')
            ->assertOk()
            ->assertJsonCount(5, 'invoices')
            ->assertJsonPath('tickets_total', 20);
    }

    public function test_invoice_below_ticket_value_is_rejected_and_not_saved(): void
    {
        $user = User::factory()->create();
        $cases = [1 => 69999.99, 2 => 14700.0, 3 => 0.0];
        $this->fakeApi(array_map(
            fn (int $consecutive) => $this->row(['nro_documento' => sprintf('01F-%08d', $consecutive), 'fac_valor_neto' => $cases[$consecutive]]),
            array_keys($cases),
        ));

        foreach (array_keys($cases) as $consecutive) {
            $this->register($user, (string) $consecutive)
                ->assertUnprocessable()
                ->assertJsonPath('errors.invoice_number.0', 'Esta factura no alcanza el mínimo de $70.000 para obtener una boleta. Se otorga una boleta por cada $70.000 de compra.');
        }

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_invalid_format_is_rejected_without_calling_the_api(): void
    {
        $user = User::factory()->create();

        foreach (['', '25-533', 'ABCDEF25533', '25533A', '00000000000', '0', '931C-25533-1'] as $number) {
            $this->register($user, $number)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('invoice_number');
        }

        Http::assertNothingSent();
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_missing_or_mismatched_invoice_is_not_saved(): void
    {
        $user = User::factory()->create();
        $this->fakeApi([$this->row(['nro_documento' => '01F-00025534'])]);

        $this->register($user)
            ->assertUnprocessable()
            ->assertJsonPath('errors.invoice_number.0', self::NOT_FOUND);

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_annulled_invoice_is_rejected(): void
    {
        $this->fakeApi([$this->row(['fecha_ts_anulacion' => '2026-11-17T10:00:00'])]);

        $this->register(User::factory()->create())
            ->assertUnprocessable()
            ->assertJsonPath('errors.invoice_number.0', 'Esta factura está anulada y no puede participar.');

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_invoice_can_only_be_registered_once_in_the_campaign(): void
    {
        $owner = User::factory()->create();
        Invoice::create([
            'user_id' => $owner->id,
            'company_id' => 4,
            'point_of_sale_id' => 413,
            'document_type' => '01F',
            'consecutive' => 25533,
            'document_number' => '01F-00025533',
            'issued_on' => '2026-11-17',
            'net_total' => 210000,
            'tickets' => 3,
        ]);

        foreach ([$owner, User::factory()->create()] as $user) {
            $this->register($user, '931C25533')
                ->assertUnprocessable()
                ->assertJsonPath('errors.invoice_number.0', 'Esta factura ya fue registrada en la campaña.');
        }

        Http::assertNothingSent();
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_service_failures_return_unavailable_and_do_not_save(): void
    {
        $user = User::factory()->create();
        $failures = [
            fn () => Http::response(['detail' => 'unauthorized'], 401),
            fn () => Http::response('error', 500),
            fn () => Http::response(['unexpected' => true], 200),
            fn () => Http::response(['data' => [$this->row(['fac_valor_neto' => 'N/A'])]], 200),
            fn () => throw new ConnectionException('timeout'),
        ];

        foreach ($failures as $failure) {
            Http::fake([self::URL.'*' => $failure]);
            $this->register($user)
                ->assertServiceUnavailable()
                ->assertJsonPath('message', 'No pudimos validar tu factura en este momento. Inténtalo de nuevo en unos minutos.');
        }

        config(['services.invoices.token' => null]);
        $this->register($user)->assertServiceUnavailable();

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_customers_only_see_their_own_invoices(): void
    {
        [$first, $second] = User::factory()->count(2)->create();
        foreach ([[$first, 1], [$second, 2]] as [$user, $consecutive]) {
            Invoice::create([
                'user_id' => $user->id,
                'company_id' => 4,
                'point_of_sale_id' => 413,
                'document_type' => '01F',
                'consecutive' => $consecutive,
                'document_number' => sprintf('01F-%08d', $consecutive),
                'issued_on' => '2026-11-17',
                'net_total' => 70000,
                'tickets' => 1,
            ]);
        }

        $this->actingAs($first)
            ->getJson('/api/invoices')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonCount(1, 'invoices')
            ->assertJsonPath('invoices.0.number', '01F-00000001');
    }

    public function test_invoice_routes_require_authentication(): void
    {
        $this->getJson('/api/invoices')->assertUnauthorized();
        $this->postJson('/api/invoices', ['invoice_number' => '25533', 'point_of_sale' => 413])->assertUnauthorized();
        Http::assertNothingSent();
    }

    public function test_invoice_registration_is_rate_limited(): void
    {
        $user = User::factory()->create();
        $this->fakeApi([]);

        for ($i = 0; $i < 10; $i++) {
            $this->register($user)->assertUnprocessable();
        }

        $this->register($user)->assertTooManyRequests();
    }
}
