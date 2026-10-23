<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Offer;
use App\Models\OfferLine;
use App\Models\Packet;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\VatRate;
use Tests\TestCase;

class OfferTest extends TestCase
{
    private VatRate $vat21;

    private VatRate $vat9;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vat21 = VatRate::create(['name' => 'Btw 21%', 'percentage' => 21, 'kind' => 'beide']);
        $this->vat9 = VatRate::create(['name' => 'Btw 9%', 'percentage' => 9, 'kind' => 'beide']);
    }

    private function payload(Company $company, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Compactstation 630 kVA', 'company_id' => $company->id, 'date' => '2026-10-01', 'valid_until' => '2026-10-31', 'chance' => 60,
            'lines' => [
                ['row_type' => 'groep', 'title' => 'Station'],
                ['row_type' => 'normaal', 'title' => 'Montage', 'quantity' => '2', 'unit_price' => '1.234,50', 'discount' => '0', 'vat_rate_id' => $this->vat21->id],
                ['row_type' => 'normaal', 'title' => 'Boek', 'quantity' => '3', 'unit_price' => '100', 'discount' => '10', 'vat_rate_id' => $this->vat9->id],
                ['row_type' => 'normaal', 'title' => '', 'quantity' => '', 'unit_price' => ''], // lege regel wordt genegeerd
            ],
        ], $overrides);
    }

    public function test_offerte_maken_met_regels_en_totalen(): void
    {
        $this->actingAsRole('projectleider');
        $company = Company::factory()->create();

        $this->post('/offertes', $this->payload($company))->assertRedirect();

        $offer = Offer::firstOrFail();
        $this->assertSame(1, $offer->number);
        $this->assertSame('concept', $offer->status);
        $this->assertCount(3, $offer->lines);
        $this->assertSame(['groep', 'normaal', 'normaal'], $offer->lines->pluck('row_type')->all());
        $this->assertSame('2469.00', $offer->lines[1]->total_excl);
        $this->assertSame('270.00', $offer->lines[2]->total_excl);
        $this->assertSame('2739.00', $offer->total_excl);
        $this->assertSame('542.79', $offer->total_vat); // 518,49 + 24,30
        $this->assertSame('3281.79', $offer->total_incl);
        $this->assertSame(1, AuditLog::where('auditable_type', 'offer')->where('event', 'aangemaakt')->count());
    }

    public function test_validatie_van_offerte_en_regels(): void
    {
        $this->actingAsRole('projectleider');
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $contact = Contact::factory()->create(['company_id' => $other->id]);

        $this->post('/offertes', $this->payload($company, ['name' => '', 'contact_id' => $contact->id, 'lines' => [
            ['row_type' => 'normaal', 'title' => 'X', 'quantity' => 'veel', 'unit_price' => '10', 'discount' => '150', 'vat_rate_id' => null],
        ]]))->assertSessionHasErrors(['name', 'contact_id', 'lines.0.quantity', 'lines.0.discount', 'lines.0.vat_rate_id']);
        $this->assertSame(0, Offer::count());
    }

    public function test_nummering_sluit_aan_op_bestaande_en_instelling(): void
    {
        $this->actingAsRole('projectleider');
        $company = Company::factory()->create();
        Offer::factory()->create(['number' => 890320]);
        $this->post('/offertes', $this->payload($company));
        $this->assertTrue(Offer::where('number', 890321)->exists());

        Setting::put(['nummer.offerte' => '900000']);
        $this->post('/offertes', $this->payload($company));
        $this->assertTrue(Offer::where('number', 900000)->exists());
        $this->post('/offertes', $this->payload($company));
        $this->assertTrue(Offer::where('number', 900001)->exists());
    }

    public function test_regels_wijzigen_verwijderen_en_volgorde(): void
    {
        $this->actingAsRole('projectleider');
        $company = Company::factory()->create();
        $this->post('/offertes', $this->payload($company));
        $offer = Offer::firstOrFail();
        [$group, $a, $b] = $offer->lines->all();

        $this->put("/offertes/{$offer->id}", $this->payload($company, ['lines' => [
            ['id' => $b->id, 'row_type' => 'normaal', 'title' => 'Boek', 'quantity' => '1', 'unit_price' => '50', 'discount' => '0', 'vat_rate_id' => $this->vat9->id],
            ['id' => $group->id, 'row_type' => 'groep', 'title' => 'Station'],
        ]]))->assertRedirect(route('offertes.show', $offer));

        $offer->refresh()->load('lines');
        $this->assertSame([$b->id, $group->id], $offer->lines->pluck('id')->all());
        $this->assertNull(OfferLine::find($a->id));
        $this->assertSame('50.00', $offer->total_excl);
        $this->assertSame('54.50', $offer->total_incl);
    }

    public function test_statusovergangen_en_vergrendeling(): void
    {
        $this->actingAsRole('projectleider');
        $company = Company::factory()->create();
        $this->post('/offertes', $this->payload($company));
        $offer = Offer::firstOrFail();

        $this->post("/offertes/{$offer->id}/status/verzonden")->assertRedirect();
        $this->assertSame('verzonden', $offer->fresh()->status);
        $this->post("/offertes/{$offer->id}/status/geaccepteerd")->assertRedirect();
        $offer->refresh();
        $this->assertSame('geaccepteerd', $offer->acceptance);
        $this->assertNotNull($offer->accepted_at);
        $this->assertSame(100, $offer->chance);

        // Geaccepteerd = vergrendeld
        $this->get("/offertes/{$offer->id}/edit")->assertRedirect(route('offertes.show', $offer));
        $this->put("/offertes/{$offer->id}", $this->payload($company))->assertStatus(422);
        $this->delete("/offertes/{$offer->id}")->assertSessionHasErrors('offerte');
        $this->assertSame(1, $offer->timeline()->where('type', 'systeem')->where('body', 'like', '%geaccepteerd%')->count());

        $this->post("/offertes/{$offer->id}/status/heropenen");
        $this->assertNull($offer->fresh()->acceptance);
        $this->post("/offertes/{$offer->id}/status/afgewezen", ['rejection_reason' => 'Te duur']);
        $this->assertSame('Te duur', $offer->fresh()->rejection_reason);
    }

    public function test_kopie_wordt_nieuw_concept(): void
    {
        $this->actingAsRole('projectleider');
        $company = Company::factory()->create();
        $this->post('/offertes', $this->payload($company));
        $offer = Offer::firstOrFail();
        $this->post("/offertes/{$offer->id}/status/geaccepteerd");

        $this->post("/offertes/{$offer->id}/kopie")->assertRedirect();
        $copy = Offer::where('id', '!=', $offer->id)->firstOrFail();
        $this->assertSame(2, $copy->number);
        $this->assertSame('concept', $copy->status);
        $this->assertNull($copy->acceptance);
        $this->assertCount(3, $copy->lines);
        $this->assertSame($offer->total_excl, $copy->total_excl);
    }

    public function test_sjabloon_zonder_nummer_en_offerte_op_basis_van_sjabloon(): void
    {
        $this->actingAsRole('projectleider');
        $this->post('/offertes', $this->payload(Company::factory()->create(), ['company_id' => '', 'is_template' => '1', 'name' => 'Basis station']))->assertRedirect();
        $template = Offer::firstOrFail();
        $this->assertTrue($template->is_template);
        $this->assertNull($template->number);
        $this->assertNull($template->company_id);
        $this->get('/offertes')->assertDontSee('Basis station');
        $this->get('/offertes?sjablonen=1')->assertSee('Basis station');
        $this->post("/offertes/{$template->id}/status/verzonden")->assertStatus(422);

        $this->post("/offertes/{$template->id}/kopie");
        $offer = Offer::where('is_template', false)->firstOrFail();
        $this->assertSame(1, $offer->number);
        $this->assertCount(3, $offer->lines);
    }

    public function test_pakket_toevoegen(): void
    {
        $this->actingAsRole('projectleider');
        $company = Company::factory()->create();
        $packet = Packet::create(['name' => 'Basis']);
        $packet->lines()->create(['row_type' => 'normaal', 'title' => 'Kabel', 'quantity' => 10, 'unit_price' => 12.5, 'vat_rate_id' => $this->vat21->id]);

        $this->post('/offertes', $this->payload($company, ['lines' => [], 'packet_id' => $packet->id]))->assertRedirect();
        $offer = Offer::firstOrFail();
        $this->assertSame(['Basis', 'Kabel'], $offer->lines->pluck('title')->all());
        $this->assertSame('125.00', $offer->total_excl);
    }

    public function test_pdf_en_concept_verwijderen(): void
    {
        $this->actingAsRole('projectleider');
        $company = Company::factory()->create();
        $this->post('/offertes', $this->payload($company));
        $offer = Offer::firstOrFail();

        $pdf = $this->get("/offertes/{$offer->id}/pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->delete("/offertes/{$offer->id}")->assertRedirect(route('offertes.index'));
        $this->assertSoftDeleted($offer);
    }

    public function test_medewerker_mag_bekijken_niet_wijzigen(): void
    {
        $offer = Offer::factory()->create();
        $this->actingAsRole('medewerker');
        $this->get('/offertes')->assertOk()->assertSee((string) $offer->number);
        $this->get("/offertes/{$offer->id}")->assertOk()->assertDontSee('Wijzigen');
        $this->get('/offertes/create')->assertForbidden();
        $this->post("/offertes/{$offer->id}/status/verzonden")->assertForbidden();
    }

    public function test_filters_en_som(): void
    {
        $this->actingAsRole('medewerker');
        Offer::factory()->create(['name' => 'Open offerte', 'status' => 'verzonden', 'total_excl' => 1000, 'chance' => 50]);
        Offer::factory()->create(['name' => 'Gewonnen', 'status' => 'verzonden', 'acceptance' => 'geaccepteerd', 'total_excl' => 500]);
        Offer::factory()->create(['name' => 'Oud', 'archived' => true]);

        $this->get('/offertes?status=open')->assertSee('Open offerte')->assertDontSee('Gewonnen')->assertSee('gewogen € 500,00');
        $this->get('/offertes?status=geaccepteerd')->assertSee('Gewonnen')->assertDontSee('Open offerte');
        $this->get('/offertes')->assertDontSee('Oud');
        $this->get('/offertes?archief=1')->assertSee('Oud');
    }

    public function test_product_beheer_met_komma_prijzen(): void
    {
        $this->actingAsRole('administratie');
        $unit = Unit::create(['name' => 'stuks']);
        $this->post('/producten', ['name' => 'Kabel', 'selling_price' => '1.250,75', 'buying_price' => '800', 'unit_id' => $unit->id, 'vat_rate_id' => $this->vat21->id, 'invoice_basis' => 'vast'])->assertRedirect();
        $product = Product::firstOrFail();
        $this->assertSame('1250.7500', $product->selling_price);
        $this->assertEquals(36.0, $product->margin());
        $this->get("/producten/{$product->id}")->assertOk()->assertSee('€ 1.250,75');

        $this->actingAsRole('projectleider');
        $this->get('/producten/create')->assertForbidden();
    }

    public function test_instellingen_en_stamgegevens(): void
    {
        $this->actingAsRole('administratie');
        $this->put('/beheer/instellingen', ['bedrijf__naam' => 'Mijn Bedrijf B.V.', 'offerte__geldigheid_dagen' => 14, 'factuur__betaaltermijn' => 30, 'inkoop__vier_ogen_drempel' => 2500])->assertRedirect();
        $this->assertSame('Mijn Bedrijf B.V.', Setting::get('bedrijf.naam'));
        $this->assertSame(1, AuditLog::where('event', 'instellingen_gewijzigd')->count());

        $this->post('/beheer/stamgegevens/btw', ['name' => 'Btw 0%', 'percentage' => '0', 'kind' => 'verkoop', 'active' => '1'])->assertRedirect();
        $this->assertTrue(VatRate::where('name', 'Btw 0%')->exists());
        $this->post('/beheer/stamgegevens/btw', ['name' => '', 'percentage' => '200', 'kind' => 'x'])->assertSessionHasErrors(['name', 'percentage', 'kind']);
        $this->get('/beheer/stamgegevens/onbekend')->assertNotFound();

        $this->actingAsRole('projectleider');
        $this->get('/beheer/instellingen')->assertForbidden();
    }
}
