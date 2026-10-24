<?php

namespace Tests\Feature;

use App\Mail\DocumentMail;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Offer;
use App\Models\PurchaseOrder;
use App\Models\TimelineEntry;
use App\Models\User;
use App\Notifications\WachtwoordHerstellen;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MailTest extends TestCase
{
    private function finalInvoice(array $attrs = []): Invoice
    {
        return Invoice::factory()->create($attrs + [
            'status' => 'definitief', 'number' => 200100, 'date' => now()->subDays(30)->toDateString(),
            'due_date' => now()->subDays(16)->toDateString(), 'total_excl' => 100, 'total_vat' => 21, 'total_incl' => 121, 'total_open' => 121,
        ]);
    }

    public function test_offerte_mailen_zet_status_verzonden_en_bewaart_pdf_in_timeline(): void
    {
        Mail::fake();
        $user = $this->actingAsRole('projectleider');
        $offer = Offer::factory()->create(['company_id' => Company::factory()->create(['email' => 'klant@example.test'])->id]);

        $this->get("/mail/offerte/{$offer->id}")->assertOk()->assertSee('klant@example.test')->assertSee((string) $offer->number);
        $this->post("/mail/offerte/{$offer->id}", [
            'to' => 'klant@example.test; tweede@example.test', 'subject' => 'Offerte', 'body' => 'Zie bijlage.', 'bcc_self' => '1',
        ])->assertRedirect(route('offertes.show', $offer));

        Mail::assertSent(DocumentMail::class, function (DocumentMail $m) use ($user) {
            return $m->hasTo('klant@example.test') && $m->hasTo('tweede@example.test') && $m->hasBcc($user->email)
                && $m->hasReplyTo($user->email) && count($m->attachments()) === 1;
        });
        $this->assertSame('verzonden', $offer->fresh()->status);
        $entry = TimelineEntry::where('offer_id', $offer->id)->where('type', 'email_verzonden')->firstOrFail();
        $this->assertStringContainsString('klant@example.test', $entry->body);
        $this->assertSame(1, $entry->files()->count());
        $this->assertSame(1, AuditLog::where('event', 'email_verzonden')->count());
    }

    public function test_ongeldig_adres_verstuurt_niets(): void
    {
        Mail::fake();
        $this->actingAsRole('projectleider');
        $offer = Offer::factory()->create();

        $this->post("/mail/offerte/{$offer->id}", ['to' => 'geen-adres', 'subject' => 'x', 'body' => 'y'])->assertSessionHasErrors('to');
        Mail::assertNothingSent();
        $this->assertSame('concept', $offer->fresh()->status);
    }

    public function test_conceptfactuur_en_sjabloon_kunnen_niet_worden_gemaild(): void
    {
        Mail::fake();
        $this->actingAsRole('administratie');
        $invoice = Invoice::factory()->create();
        $this->get("/mail/factuur/{$invoice->id}")->assertStatus(422);
        $this->post("/mail/factuur/{$invoice->id}", ['to' => 'a@example.test', 'subject' => 'x', 'body' => 'y'])->assertStatus(422);

        $template = Offer::factory()->create(['is_template' => true, 'number' => null]);
        $this->get("/mail/offerte/{$template->id}")->assertStatus(422);
        Mail::assertNothingSent();
    }

    public function test_herinnering_verhoogt_niveau(): void
    {
        Mail::fake();
        $this->actingAsRole('administratie');
        $invoice = $this->finalInvoice();

        $this->get("/mail/herinnering/{$invoice->id}")->assertOk();
        $this->assertSame(0, (int) $invoice->fresh()->reminder_level); // voorbeeld wijzigt niets
        $this->post("/mail/herinnering/{$invoice->id}", ['to' => 'a@example.test', 'subject' => 'Herinnering', 'body' => 'Graag betalen.'])
            ->assertRedirect(route('facturen.show', $invoice));

        $invoice->refresh();
        $this->assertSame(1, (int) $invoice->reminder_level);
        $this->assertNotNull($invoice->last_reminder_at);
        Mail::assertSent(DocumentMail::class, 1);

        $notDue = $this->finalInvoice(['number' => 200101, 'due_date' => now()->addDays(10)->toDateString()]);
        $this->get("/mail/herinnering/{$notDue->id}")->assertStatus(422);
    }

    public function test_rechten_per_soort(): void
    {
        Mail::fake();
        $this->actingAsRole('medewerker');
        $offer = Offer::factory()->create();
        $this->get("/mail/offerte/{$offer->id}")->assertForbidden();

        $this->actingAsRole('projectleider');
        $invoice = $this->finalInvoice();
        $this->get("/mail/factuur/{$invoice->id}")->assertForbidden();
        $po = PurchaseOrder::factory()->create();
        $this->get("/mail/inkoopopdracht/{$po->id}")->assertOk();
        Mail::assertNothingSent();
    }

    public function test_mislukte_verzending_draait_alles_terug(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP onbereikbaar'));
        $this->actingAsRole('projectleider');
        $offer = Offer::factory()->create();

        $this->post("/mail/offerte/{$offer->id}", ['to' => 'a@example.test', 'subject' => 'x', 'body' => 'y'])->assertSessionHasErrors('to');
        $this->assertSame('concept', $offer->fresh()->status);
        $this->assertSame(0, TimelineEntry::where('type', 'email_verzonden')->count());
    }

    public function test_wachtwoord_vergeten_en_herstellen(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'a@example.test', 'must_change_password' => true]);

        $this->get('/inloggen')->assertSee('Wachtwoord vergeten?');
        $this->get('/wachtwoord-vergeten')->assertOk();
        $this->post('/wachtwoord-vergeten', ['email' => 'onbekend@example.test'])->assertSessionHas('status');
        Notification::assertNothingSent();

        $this->post('/wachtwoord-vergeten', ['email' => 'a@example.test'])->assertSessionHas('status');
        $token = null;
        Notification::assertSentTo($user, WachtwoordHerstellen::class, function (WachtwoordHerstellen $n) use (&$token, $user) {
            $token = $n->token;

            return str_contains($n->toMail($user)->actionUrl, '/wachtwoord-herstellen/');
        });

        $this->get("/wachtwoord-herstellen/{$token}?email=a@example.test")->assertOk();
        $this->post('/wachtwoord-herstellen', ['token' => 'fout', 'email' => 'a@example.test', 'password' => 'nieuw-wachtwoord-2', 'password_confirmation' => 'nieuw-wachtwoord-2'])
            ->assertSessionHasErrors('email');
        $this->post('/wachtwoord-herstellen', ['token' => $token, 'email' => 'a@example.test', 'password' => 'nieuw-wachtwoord-2', 'password_confirmation' => 'nieuw-wachtwoord-2'])
            ->assertRedirect(route('login'));

        $user->refresh();
        $this->assertTrue(Hash::check('nieuw-wachtwoord-2', $user->password));
        $this->assertFalse($user->must_change_password);
        $this->assertSame(1, AuditLog::where('event', 'wachtwoord_hersteld')->count());

        // token is eenmalig
        $this->post('/wachtwoord-herstellen', ['token' => $token, 'email' => 'a@example.test', 'password' => 'nog-een-keer-3', 'password_confirmation' => 'nog-een-keer-3'])
            ->assertSessionHasErrors('email');
    }

    public function test_inactief_account_krijgt_geen_herstelmail(): void
    {
        Notification::fake();
        User::factory()->inactive()->create(['email' => 'b@example.test']);
        $this->post('/wachtwoord-vergeten', ['email' => 'b@example.test'])->assertSessionHas('status');
        Notification::assertNothingSent();
    }
}
