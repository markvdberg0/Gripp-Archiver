<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_gasten_gaan_naar_inloggen(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/inloggen')->assertOk()->assertSee('Inloggen');
    }

    public function test_inloggen_en_uitloggen(): void
    {
        $user = User::factory()->create(['email' => 'a@example.test']);

        $this->post('/inloggen', ['email' => 'a@example.test', 'password' => 'geheim-wachtwoord-1'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertSame(1, AuditLog::where('event', 'ingelogd')->count());

        $this->post('/uitloggen')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_verkeerd_wachtwoord_en_inactief_account(): void
    {
        User::factory()->create(['email' => 'a@example.test']);
        User::factory()->inactive()->create(['email' => 'b@example.test']);

        $this->post('/inloggen', ['email' => 'a@example.test', 'password' => 'fout'])->assertSessionHasErrors('email');
        $this->post('/inloggen', ['email' => 'b@example.test', 'password' => 'geheim-wachtwoord-1'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame(2, AuditLog::where('event', 'inloggen_mislukt')->count());
    }

    public function test_te_veel_pogingen_wordt_geblokkeerd(): void
    {
        User::factory()->create(['email' => 'a@example.test']);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/inloggen', ['email' => 'a@example.test', 'password' => 'fout']);
        }
        $this->post('/inloggen', ['email' => 'a@example.test', 'password' => 'geheim-wachtwoord-1'])->assertStatus(429);
    }

    public function test_gedeactiveerde_gebruiker_wordt_uitgelogd(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/')->assertOk();
        $user->update(['active' => false]);
        $this->actingAs($user->fresh())->get('/')->assertRedirect(route('login'));
    }

    public function test_tijdelijk_wachtwoord_moet_eerst_gewijzigd(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);
        $this->actingAs($user)->get('/relaties')->assertRedirect(route('account.edit'));

        $this->put('/account', ['current_password' => 'geheim-wachtwoord-1', 'password' => 'kort', 'password_confirmation' => 'kort'])->assertSessionHasErrors('password');
        $this->put('/account', ['current_password' => 'geheim-wachtwoord-1', 'password' => 'NieuwWachtwoord9', 'password_confirmation' => 'NieuwWachtwoord9'])->assertRedirect(route('dashboard'));
        $this->assertFalse($user->fresh()->must_change_password);
        $this->get('/relaties')->assertOk();
    }

    public function test_beveiligingsheaders(): void
    {
        $this->get('/inloggen')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy');
    }

    public function test_eerste_beheerder_via_artisan(): void
    {
        $this->artisan('werkbank:gebruiker', ['email' => 'baas@example.test', '--naam' => 'Baas'])
            ->expectsOutputToContain('Tijdelijk wachtwoord')->assertSuccessful();
        $user = User::where('email', 'baas@example.test')->first();
        $this->assertSame('beheerder', $user->role);
        $this->assertTrue($user->must_change_password);
        $this->artisan('werkbank:gebruiker', ['email' => 'baas@example.test'])->assertFailed();
    }
}
