<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function registration(): array
    {
        return [
            'name' => 'Cliente Prueba',
            'email' => 'CLIENTE@example.com',
            'phone' => '3001234567',
            'password' => 'Prueba12345!',
            'password_confirmation' => 'Prueba12345!',
        ];
    }

    public function test_registration_persists_customer_and_authenticates(): void
    {
        $this->postJson('/api/auth/register', $this->registration())
            ->assertCreated()
            ->assertJsonPath('user.email', 'cliente@example.com')
            ->assertJsonPath('user.phone', '3001234567')
            ->assertJsonMissingPath('user.password');

        $user = User::firstOrFail();
        $this->assertTrue(Hash::check('Prueba12345!', $user->password));
        $this->assertAuthenticatedAs($user);
        $this->getJson('/api/auth/user')->assertOk()->assertJsonPath('user.id', $user->id);
    }

    public function test_invalid_registration_does_not_create_user(): void
    {
        $this->postJson('/api/auth/register', [
            ...$this->registration(),
            'phone' => 'invalid',
            'password_confirmation' => 'different',
        ])->assertUnprocessable()->assertJsonValidationErrors(['phone', 'password']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_duplicate_email_is_rejected_case_insensitively(): void
    {
        User::factory()->create(['email' => 'cliente@example.com']);

        $this->postJson('/api/auth/register', $this->registration())
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_correct_credentials_login_and_logout(): void
    {
        $user = User::factory()->create(['password' => 'Prueba12345!']);

        $this->postJson('/api/auth/login', [
            'email' => strtoupper($user->email),
            'password' => 'Prueba12345!',
        ])->assertOk()->assertJsonPath('user.id', $user->id);
        $this->assertAuthenticatedAs($user);

        $this->postJson('/api/auth/logout')->assertOk();
        $this->assertGuest();
        $this->getJson('/api/auth/user')->assertUnauthorized();
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $user = User::factory()->create();
        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Incorrecta123',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertGuest();
    }

    public function test_private_routes_require_authentication(): void
    {
        $this->getJson('/api/auth/user')->assertUnauthorized();
        $this->postJson('/api/auth/logout')->assertUnauthorized();
        $this->getJson('/api/auth/csrf')->assertOk()->assertJsonStructure(['token']);
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'unknown@example.com', 'password' => 'Invalid123',
            ])->assertUnprocessable();
        }
        $this->postJson('/api/auth/login', [
            'email' => 'unknown@example.com', 'password' => 'Invalid123',
        ])->assertTooManyRequests();
    }
}
