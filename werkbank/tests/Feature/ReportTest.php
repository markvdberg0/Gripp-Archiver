<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Cost;
use App\Models\Invoice;
use App\Models\Memorial;
use App\Models\Tag;
use Tests\TestCase;

class ReportTest extends TestCase
{
    private function invoice(Company $company, string $date, string $due, float $incl, array $payments = []): Invoice
    {
        $invoice = Invoice::factory()->create(['company_id' => $company->id, 'status' => 'verzonden', 'number' => random_int(1000, 99999), 'date' => $date, 'due_date' => $due, 'total_incl' => $incl, 'total_excl' => round($incl / 1.21, 2)]);
        foreach ($payments as [$d, $a]) {
            $invoice->payments()->create(['date' => $d, 'amount' => $a]);
        }
        $invoice->refreshPaid();

        return $invoice;
    }

    public function test_openstaande_debiteuren_op_peildatum_met_ouderdom(): void
    {
        $this->actingAsRole('administratie');
        $a = Company::factory()->create(['name' => 'Klant A']);
        $this->invoice($a, '2026-01-01', '2026-01-31', 1000, [['2026-03-15', 400]]);
        $this->invoice($a, '2026-02-20', '2026-03-22', 500);
        $this->invoice(Company::factory()->create(['name' => 'Klant B']), '2026-04-01', '2026-05-01', 300); // na de peildatum

        $page = $this->get('/overzichten/open/debiteuren?peildatum=2026-03-01')->assertOk();
        // op 1 maart: factuur 1 nog geheel open (betaling pas 15 maart) en 29 dagen vervallen; factuur 2 niet vervallen
        $page->assertSee('Klant A')->assertDontSee('Klant B')->assertSee('€ 1.500,00');
        $data = $page->viewData('bucketTotals');
        $this->assertSame(500.0, $data['niet vervallen']);
        $this->assertSame(1000.0, $data['1–30 dagen']);

        $later = $this->get('/overzichten/open/debiteuren?peildatum=2026-06-30')->viewData('total');
        $this->assertSame(1400.0, $later); // 600 + 500 + 300

        $csv = $this->get('/overzichten/open/debiteuren?peildatum=2026-03-01&export=csv')->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('"Klant A";', $csv);
        $this->assertStringContainsString('1000,00', $csv);
    }

    public function test_omzet_per_maand_en_pijplijn(): void
    {
        $this->actingAsRole('administratie');
        $c = Company::factory()->create();
        $this->invoice($c, '2026-03-10', '2026-04-09', 1210);
        Invoice::factory()->create(['company_id' => $c->id, 'status' => 'concept', 'date' => '2026-03-11', 'total_excl' => 5000]); // concept telt niet

        $months = $this->get('/overzichten/omzet?jaar=2026')->assertOk()->viewData('months');
        $this->assertSame(1000.0, $months[2]['sales']);
        $this->assertSame(1000.0, $months->sum('sales'));
        $this->get('/overzichten/pijplijn')->assertOk();
        $this->get('/overzichten/omzet?jaar=2026&export=csv')->assertOk()->assertDownload('omzet-2026.csv');
    }

    public function test_rechten_op_overzichten(): void
    {
        $this->actingAsRole('projectleider');
        $this->get('/overzichten')->assertOk();
        $this->get('/overzichten/open/debiteuren')->assertOk();
        $this->get('/overzichten/uren')->assertOk();
        $this->actingAsRole('alleen_lezen');
        $this->get('/overzichten/uren')->assertForbidden();
        $this->actingAsRole('medewerker');
        $this->get('/overzichten')->assertForbidden();
    }

    public function test_csv_export_relaties_zonder_formule_injectie(): void
    {
        $this->actingAsRole('medewerker');
        Company::factory()->create(['name' => '=HYPERLINK("http://evil")']);
        $csv = $this->get('/relaties?export=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertSame(1, AuditLog::where('event', 'export')->count());
    }

    public function test_contactpersoon_anonimiseren(): void
    {
        $contact = Contact::factory()->create(['first_name' => 'Geheim', 'last_name' => 'Persoon', 'email' => 'geheim@example.test']);
        $contact->syncTagNames(['VIP']);
        $this->actingAsRole('administratie');
        $contact->update(['phone' => '0612345678']);
        $this->post("/contactpersonen/{$contact->id}/anonimiseren")->assertForbidden();

        $this->actingAsRole('beheerder');
        $this->post("/contactpersonen/{$contact->id}/anonimiseren")->assertRedirect();
        $contact->refresh();
        $this->assertSame('Geanonimiseerd', $contact->last_name);
        $this->assertNull($contact->email);
        $this->assertNull($contact->phone);
        $this->assertFalse($contact->active);
        $this->assertCount(0, $contact->tags);
        $this->assertTrue(Tag::where('name', 'VIP')->exists());
        $dump = json_encode(AuditLog::all());
        $this->assertStringNotContainsString('geheim@example.test', $dump);
        $this->assertStringNotContainsString('0612345678', $dump);
        $this->assertSame(1, AuditLog::where('event', 'geanonimiseerd')->count());
    }

    public function test_memoriaal_en_kosten_import_en_scherm(): void
    {
        $run = $this->runImport($this->backup());
        $memorial = Memorial::where('gripp_id', 1)->firstOrFail();
        $this->assertSame('Handmatig', $memorial->type);
        $this->assertSame('0.00', number_format((float) $memorial->lines->sum('amount'), 2, '.', ''));
        $this->assertSame('99.95', Cost::where('gripp_id', 1)->value('amount'));
        $this->assertTrue(collect($run->summary['checks'])->firstWhere('label', 'cost: som bedragen')['ok']);

        $this->actingAsRole('administratie');
        $this->get('/memoriaal-en-kosten')->assertOk()->assertSee('Correctie')->assertSee('Abonnement');
    }

    public function test_dashboard_toont_tegels_volgens_rechten(): void
    {
        $this->runImport($this->backup());
        $this->actingAsRole('administratie');
        $this->get('/')->assertOk()->assertSee('Openstaand debiteuren')->assertSee('Inkoopfacturen ter goedkeuring')->assertSee('Open offertes');
        $this->actingAsRole('medewerker');
        $this->get('/')->assertOk()->assertDontSee('Openstaand debiteuren')->assertSee('Actieve klanten');
    }
}
