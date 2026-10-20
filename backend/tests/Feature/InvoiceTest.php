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
        ]]);
    }

    private function row(array $overrides = []): array
    {
        return [
            'fecha_documento' => '2026-10-19T00:00:00',
            'nro_documento' => '07F-00028641',
            'punto_venta' => 'PDV CENTRO',
            'fac_valor_neto' => 14700.0,
            'fecha_ts_anulacion' => null,
            'razon_social_cliente' => 'CLIENTE',
            ...$overrides,
        ];
    }

    private function fakeApi(array $rows, int $status = 200): void
    {
        Http::fake([self::URL.'*' => Http::response(['total' => count($rows), 'data' => $rows], $status)]);
    }

    public function test_existing_invoice_is_validated_and_saved_for_the_customer(): void
    {
        $user = User::factory()->create();
        $this->fakeApi([$this->row(), $this->row(['punto_venta' => 'OTRO'])]);

        $this->actingAs($user)
            ->postJson('/api/invoices', ['invoice_number' => ' 07f-00028641 '])
            ->assertCreated()
            ->assertJsonPath('invoice.number', '07F-00028641')
            ->assertJsonPath('invoice.issued_on', '2026-10-19')
            ->assertJsonPath('invoice.point_of_sale', 'PDV CENTRO')
            ->assertJsonPath('invoice.net_total', 14700)
            ->assertJsonMissingPath('invoice.razon_social_cliente');

        Http::assertSent(fn (Request $request) => $request->url() === self::URL.'?fecha_inicio=2026-10-01&fecha_fin=2026-12-31&cia=4&tipo_docto=07F&consec=28641&token=test-token');
        $this->assertDatabaseHas('invoices', [
            'user_id' => $user->id,
            'company_id' => 4,
            'document_type' => '07F',
            'consecutive' => 28641,
            'document_number' => '07F-00028641',
        ]);

        $this->getJson('/api/invoices')
            ->assertOk()
            ->assertJsonCount(1, 'invoices')
            ->assertJsonPath('invoices.0.number', '07F-00028641');
    }

    public function test_invalid_format_is_rejected_without_calling_the_api(): void
    {
        $user = User::factory()->create();

        foreach (['', '0700028641', '07F 00028641', '07F-ABC', '07F-0000000000000', '07F-00000000'] as $number) {
            $this->actingAs($user)
                ->postJson('/api/invoices', ['invoice_number' => $number])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('invoice_number');
        }

        Http::assertNothingSent();
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_missing_or_mismatched_invoice_is_not_saved(): void
    {
        $user = User::factory()->create();

        foreach ([[], [$this->row(['nro_documento' => '07F-00028642'])]] as $rows) {
            $this->fakeApi($rows);
            $this->actingAs($user)
                ->postJson('/api/invoices', ['invoice_number' => '07F-00028641'])
                ->assertUnprocessable()
                ->assertJsonPath('errors.invoice_number.0', 'No encontramos esta factura. Revisa el número y que la compra sea entre el 1 de octubre y el 31 de diciembre de 2026.');
        }

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_annulled_invoice_is_rejected(): void
    {
        $this->fakeApi([$this->row(['fecha_ts_anulacion' => '2026-10-19T10:00:00'])]);

        $this->actingAs(User::factory()->create())
            ->postJson('/api/invoices', ['invoice_number' => '07F-00028641'])
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
            'document_type' => '07F',
            'consecutive' => 28641,
            'document_number' => '07F-00028641',
            'issued_on' => '2026-10-19',
            'net_total' => 14700,
        ]);

        foreach ([$owner, User::factory()->create()] as $user) {
            $this->actingAs($user)
                ->postJson('/api/invoices', ['invoice_number' => '07F-28641'])
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
            $this->actingAs($user)
                ->postJson('/api/invoices', ['invoice_number' => '07F-00028641'])
                ->assertServiceUnavailable()
                ->assertJsonPath('message', 'No pudimos validar tu factura en este momento. Inténtalo de nuevo en unos minutos.');
        }

        config(['services.invoices.token' => null]);
        $this->actingAs($user)->postJson('/api/invoices', ['invoice_number' => '07F-00028641'])->assertServiceUnavailable();

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_customers_only_see_their_own_invoices(): void
    {
        [$first, $second] = User::factory()->count(2)->create();
        foreach ([[$first, 1], [$second, 2]] as [$user, $consecutive]) {
            Invoice::create([
                'user_id' => $user->id,
                'company_id' => 4,
                'document_type' => '07F',
                'consecutive' => $consecutive,
                'document_number' => sprintf('07F-%08d', $consecutive),
                'issued_on' => '2026-10-19',
                'net_total' => 1000,
            ]);
        }

        $this->actingAs($first)
            ->getJson('/api/invoices')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonCount(1, 'invoices')
            ->assertJsonPath('invoices.0.number', '07F-00000001');
    }

    public function test_invoice_routes_require_authentication(): void
    {
        $this->getJson('/api/invoices')->assertUnauthorized();
        $this->postJson('/api/invoices', ['invoice_number' => '07F-00028641'])->assertUnauthorized();
        Http::assertNothingSent();
    }

    public function test_invoice_registration_is_rate_limited(): void
    {
        $user = User::factory()->create();
        $this->fakeApi([]);

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->postJson('/api/invoices', ['invoice_number' => '07F-00028641'])->assertUnprocessable();
        }

        $this->actingAs($user)->postJson('/api/invoices', ['invoice_number' => '07F-00028641'])->assertTooManyRequests();
    }
}
