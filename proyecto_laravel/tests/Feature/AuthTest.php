<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Autenticación mínima (RF01/RF14): registro público exclusivamente
 * CLIENTE, mensajes genéricos al fallar y acceso protegido.
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_creates_cliente_only_account(): void
    {
        $response = $this->from('/registrarse')->post(route('register.store'), [
            'name' => 'Aythami Reyes',
            'email' => 'aythami@keydeploy.test',
            'password' => 'clave12345',
            'password_confirmation' => 'clave12345',
            'role' => 'administrador', // intento de autoasignación de rol interno
        ]);

        $response->assertRedirect(route('projects.index'))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', [
            'email' => 'aythami@keydeploy.test',
            'role' => 'cliente',
        ]);
        $this->assertAuthenticated();
    }

    public function test_registration_rejects_mismatched_password_confirmation(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Cliente fallido',
            'email' => 'fallido@keydeploy.test',
            'password' => 'clave12345',
            'password_confirmation' => 'otraclave9',
        ])->assertSessionHasErrors(['password']);

        $this->assertDatabaseMissing('users', ['email' => 'fallido@keydeploy.test']);
        $this->assertGuest();
    }

    public function test_login_with_wrong_credentials_shows_generic_error(): void
    {
        User::factory()->create([
            'email' => 'demo@keydeploy.test',
            'password' => 'clave12345',
        ]);

        $response = $this->post(route('login.store'), [
            'email' => 'demo@keydeploy.test',
            'password' => 'incorrecta9',
        ]);

        // Sin revelar qué credencial falló.
        $response->assertRedirect()->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    public function test_login_with_valid_credentials_redirects_to_own_projects(): void
    {
        User::factory()->create([
            'email' => 'demo@keydeploy.test',
            'password' => 'clave12345',
        ]);

        $this->post(route('login.store'), [
            'email' => 'demo@keydeploy.test',
            'password' => 'clave12345',
        ]);

        $this->assertAuthenticated();
        $this->get(route('projects.index'))->assertOk();
    }

    public function test_unauthenticated_request_redirects_to_login(): void
    {
        $this->get(route('projects.index'))
            ->assertRedirect(route('login'));
    }
}
