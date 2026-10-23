<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\TimelineEntry;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    private function valid(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Nieuwe Klant B.V.', 'relation_type' => 'bedrijf', 'is_customer' => '1', 'invoice_send_by' => 'email',
            'payment_method' => 'overboeking', 'active' => '1', 'visit_city' => 'Deventer', 'tags' => 'Zwolle, Nieuw',
        ], $overrides);
    }

    public function test_lijst_zoeken_en_filteren(): void
    {
        $this->actingAsRole('medewerker');
        Company::factory()->create(['name' => 'Alfa Techniek', 'visit_city' => 'Zwolle']);
        Company::factory()->supplier()->create(['name' => 'Beta Staal']);
        Company::factory()->create(['name' => 'Gamma Oud', 'active' => false]);

        $this->get('/relaties')->assertOk()->assertSee('Alfa Techniek')->assertSee('Beta Staal')->assertDontSee('Gamma Oud');
        $this->get('/relaties?q=zwolle')->assertSee('Alfa Techniek')->assertDontSee('Beta Staal'); // hoofdletterongevoelig
        $this->get('/relaties?q=ALFA')->assertSee('Alfa Techniek');
        $this->get('/relaties?rol=is_supplier')->assertSee('Beta Staal')->assertDontSee('Alfa Techniek');
        $this->get('/relaties?actief=0')->assertSee('Gamma Oud');
        $this->get('/relaties?q=%25')->assertOk()->assertDontSee('Alfa Techniek'); // % wordt letterlijk gezocht
    }

    public function test_aanmaken_met_validatie_labels_en_logboek(): void
    {
        $this->actingAsRole('administratie');

        $this->post('/relaties', $this->valid(['name' => '', 'email' => 'geen-mail', 'iban' => 'XX']))
            ->assertSessionHasErrors(['name', 'email', 'iban']);

        $this->post('/relaties', $this->valid(['iban' => 'nl91 abna 0417 1643 00']))->assertRedirect();
        $company = Company::where('name', 'Nieuwe Klant B.V.')->firstOrFail();
        $this->assertSame('NL91ABNA0417164300', $company->iban);
        $this->assertSame(1, $company->number);
        $this->assertEqualsCanonicalizing(['Zwolle', 'Nieuw'], $company->tags->pluck('name')->all());
        $log = AuditLog::where('event', 'aangemaakt')->where('auditable_type', 'company')->first();
        $this->assertSame('***', $log->changes['iban'][1], 'IBAN gemaskeerd in het logboek');
    }

    public function test_wijzigen_wordt_gelogd_met_oude_en_nieuwe_waarde(): void
    {
        $this->actingAsRole('administratie');
        $company = Company::factory()->create(['name' => 'Oud']);

        $this->put("/relaties/{$company->id}", $this->valid(['name' => 'Nieuw', 'number' => $company->number]))->assertRedirect(route('relaties.show', $company));

        $log = AuditLog::where('event', 'gewijzigd')->latest('id')->first();
        $this->assertSame(['Oud', 'Nieuw'], $log->changes['name']);
    }

    public function test_relatienummer_moet_uniek_zijn(): void
    {
        $this->actingAsRole('administratie');
        Company::factory()->create(['number' => 5000]);
        $this->post('/relaties', $this->valid(['number' => 5000]))->assertSessionHasErrors('number');
    }

    public function test_detail_toont_contactpersonen_en_veilige_timeline(): void
    {
        $this->actingAsRole('medewerker');
        $company = Company::factory()->create();
        Contact::factory()->create(['company_id' => $company->id, 'first_name' => 'Klaas', 'last_name' => 'Vaak']);
        TimelineEntry::create(['company_id' => $company->id, 'type' => 'email_ontvangen', 'occurred_at' => now(), 'body' => '<p>Hallo<script>alert("x")</script><img src="https://evil.test/a.png"></p>']);

        $this->get("/relaties/{$company->id}")->assertOk()
            ->assertSee('Klaas Vaak')
            ->assertSee('Hallo', false)
            ->assertDontSee('<script>alert', false)
            ->assertDontSee('evil.test', false)
            ->assertDontSee('Relatie verwijderen'); // geen bewerkrechten
    }

    public function test_bankgegevens_alleen_met_financieel_recht(): void
    {
        $company = Company::factory()->create(['iban' => 'NL91ABNA0417164300']);
        $this->actingAsRole('projectleider');
        $this->get("/relaties/{$company->id}")->assertDontSee('NL91ABNA0417164300');
        $this->actingAsRole('administratie');
        $this->get("/relaties/{$company->id}")->assertSee('NL91ABNA0417164300');
    }

    public function test_projectleider_kan_iban_niet_wijzigen(): void
    {
        $company = Company::factory()->create(['iban' => 'NL91ABNA0417164300']);
        $this->actingAsRole('projectleider');
        $this->put("/relaties/{$company->id}", $this->valid(['iban' => 'NL02RABO0123456789', 'number' => $company->number]))->assertRedirect();
        $this->assertSame('NL91ABNA0417164300', $company->fresh()->iban);
    }

    public function test_notitie_toevoegen(): void
    {
        $user = $this->actingAsRole('projectleider');
        $company = Company::factory()->create();
        $this->post("/relaties/{$company->id}/notities", ['type' => 'telefoon', 'body' => 'Gebeld over planning'])->assertRedirect();
        $entry = $company->timeline()->first();
        $this->assertSame('telefoon', $entry->type);
        $this->assertSame($user->id, $entry->user_id);
    }

    public function test_verwijderen_is_zacht_en_alleen_met_recht(): void
    {
        $company = Company::factory()->create();
        $this->actingAsRole('medewerker');
        $this->delete("/relaties/{$company->id}")->assertForbidden();
        $this->actingAsRole('administratie');
        $this->delete("/relaties/{$company->id}")->assertRedirect(route('relaties.index'));
        $this->assertSoftDeleted($company);
    }

    public function test_contactpersoon_beheren(): void
    {
        $this->actingAsRole('administratie');
        $company = Company::factory()->create();
        $this->post('/contactpersonen', ['company_id' => $company->id, 'last_name' => 'Jansen', 'first_name' => 'Els', 'email' => 'els@example.test', 'active' => '1', 'show_on_company' => '1'])->assertRedirect();
        $contact = Contact::where('last_name', 'Jansen')->firstOrFail();
        $this->get("/relaties/{$company->id}")->assertSee('Els Jansen');
        $this->post('/contactpersonen', ['last_name' => ''])->assertSessionHasErrors('last_name');
        $this->delete("/contactpersonen/{$contact->id}")->assertRedirect(route('relaties.show', $company));
        $this->assertSoftDeleted($contact);
    }
}
