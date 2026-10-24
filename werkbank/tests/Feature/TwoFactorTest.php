<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Support\Totp;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    private function enabledUser(array $attrs = []): array
    {
        $user = User::factory()->create($attrs + ['email' => 'a@example.test']);
        $secret = Totp::secret();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->saveQuietly();
        $codes = $user->newRecoveryCodes();

        return [$user->fresh(), $secret, $codes];
    }

    public function test_rfc6238_testvector(): void
    {
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'; // "12345678901234567890"
        $this->assertSame('287082', Totp::code($secret, Totp::step(59)));
        $this->assertSame('081804', Totp::code($secret, Totp::step(1111111109)));
        $this->assertSame(Totp::step(59), Totp::verify($secret, '287 082', null, 59));
        $this->assertNull(Totp::verify($secret, '287082', Totp::step(59), 59)); // al gebruikt
        $this->assertNull(Totp::verify($secret, '287082', null, 59 + 90)); // te oud
        $this->assertSame(32, strlen(Totp::secret()));
        $this->assertStringContainsString('<svg', Totp::qrSvg(Totp::uri($secret, 'a@example.test', 'Werkbank')));
    }

    public function test_instellen_en_bevestigen(): void
    {
        $user = $this->actingAsRole('medewerker');
        $this->get('/account/tweestaps')->assertOk()->assertSee('<svg', false);
        $secret = session('tweestaps.geheim');
        $this->assertNotNull($secret);

        $this->post('/account/tweestaps', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasTwoFactor());

        $this->post('/account/tweestaps', ['code' => Totp::code($secret, Totp::step())])->assertRedirect('/account/tweestaps')->assertSessionHas('tweestaps.codes');
        $user->refresh();
        $this->assertTrue($user->hasTwoFactor());
        $this->assertCount(8, $user->two_factor_recovery_codes);
        $this->assertNotSame($secret, $user->getRawOriginal('two_factor_secret')); // versleuteld opgeslagen
        $this->assertSame(1, AuditLog::where('event', 'tweestaps_aangezet')->count());
        $this->assertStringNotContainsString($secret, json_encode(AuditLog::all()->toArray()));

        $this->delete('/account/tweestaps', ['wachtwoord_uit' => 'fout'])->assertSessionHasErrors('wachtwoord_uit');
        $this->delete('/account/tweestaps', ['wachtwoord_uit' => 'geheim-wachtwoord-1'])->assertRedirect();
        $this->assertFalse($user->fresh()->hasTwoFactor());
    }

    public function test_inloggen_vraagt_code_en_weigert_hergebruik(): void
    {
        [$user, $secret] = $this->enabledUser();

        $this->post('/inloggen', ['email' => 'a@example.test', 'password' => 'geheim-wachtwoord-1'])->assertRedirect(route('login.tweestaps'));
        $this->assertGuest();
        $this->get('/')->assertRedirect(route('login'));
        $this->post('/inloggen/tweestaps', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $code = Totp::code($secret, Totp::step());
        $this->post('/inloggen/tweestaps', ['code' => $code])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);

        // dezelfde code nog een keer: geweigerd
        $this->post('/uitloggen');
        $this->post('/inloggen', ['email' => 'a@example.test', 'password' => 'geheim-wachtwoord-1']);
        $this->post('/inloggen/tweestaps', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_herstelcode_werkt_een_keer(): void
    {
        [$user, , $codes] = $this->enabledUser();

        $this->post('/inloggen', ['email' => 'a@example.test', 'password' => 'geheim-wachtwoord-1']);
        $this->post('/inloggen/tweestaps', ['code' => strtoupper($codes[0])])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertCount(7, $user->fresh()->two_factor_recovery_codes);
        $this->assertSame(1, AuditLog::where('event', 'tweestaps_herstelcode_gebruikt')->count());

        $this->post('/uitloggen');
        $this->post('/inloggen', ['email' => 'a@example.test', 'password' => 'geheim-wachtwoord-1']);
        $this->post('/inloggen/tweestaps', ['code' => $codes[0]])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_te_veel_pogingen_of_verlopen(): void
    {
        $this->enabledUser();
        $this->post('/inloggen', ['email' => 'a@example.test', 'password' => 'geheim-wachtwoord-1']);
        for ($i = 0; $i < 4; $i++) {
            $this->post('/inloggen/tweestaps', ['code' => '000000'])->assertSessionHasErrors('code');
        }
        $this->post('/inloggen/tweestaps', ['code' => '000000'])->assertRedirect(route('login'));
        $this->get('/inloggen/tweestaps')->assertRedirect(route('login'));

        [$other] = $this->enabledUser(['email' => 'b@example.test']);
        $this->withSession(['tweestaps.login' => ['id' => $other->id, 'remember' => false, 'tot' => time() - 1, 'pogingen' => 0]])
            ->post('/inloggen/tweestaps', ['code' => '123456'])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_beheerder_zet_uit_en_verplicht_stellen(): void
    {
        [$user] = $this->enabledUser();
        $this->actingAsRole('beheerder');
        $this->get(route('beheer.gebruikers.edit', $user))->assertOk()->assertSee('Tweestapsverificatie uitzetten');
        $this->post(route('beheer.gebruikers.tweestaps', $user))->assertRedirect();
        $this->assertFalse($user->fresh()->hasTwoFactor());
        $this->assertSame(1, AuditLog::where('event', 'tweestaps_reset')->count());

        Setting::put(['beveiliging.tweestaps_verplicht' => '1']);
        $this->actingAs($user->fresh());
        $this->get('/')->assertRedirect(route('account.tweestaps'));
        $this->get('/account/tweestaps')->assertOk();
    }

    public function test_alleen_beheerder_wijzigt_verplichting(): void
    {
        $this->actingAsRole('administratie');
        $this->get(route('beheer.instellingen.edit'))->assertOk()->assertSee('niet verplicht');
        $this->put(route('beheer.instellingen.update'), ['offerte__geldigheid_dagen' => 30, 'factuur__betaaltermijn' => 14, 'beveiliging__tweestaps_verplicht' => '1'])
            ->assertRedirect();
        $this->assertSame('0', Setting::get('beveiliging.tweestaps_verplicht'));
        $this->assertSame('14', Setting::get('factuur.betaaltermijn'));
    }
}
