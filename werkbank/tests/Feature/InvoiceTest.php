<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Setting;
use App\Models\VatRate;
use App\Support\ContractFacturatie;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    private VatRate $vat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vat = VatRate::create(['name' => 'Btw 21%', 'percentage' => 21, 'kind' => 'beide']);
    }

    private function payload(Company $company, array $overrides = []): array
    {
        return array_merge([
            'company_id' => $company->id, 'subject' => 'Werkzaamheden oktober', 'payment_method' => 'overboeking',
            'lines' => [['row_type' => 'normaal', 'title' => 'Montage', 'quantity' => '10', 'unit_price' => '85', 'vat_rate_id' => $this->vat->id]],
        ], $overrides);
    }

    private function draft(array $overrides = []): Invoice
    {
        $company = Company::factory()->create(['payment_term_days' => 14]);
        $this->post('/facturen', $this->payload($company, $overrides))->assertRedirect();

        return Invoice::latest('id')->firstOrFail();
    }

    public function test_concept_heeft_geen_nummer_en_definitief_maken_kent_nummer_en_vervaldatum_toe(): void
    {
        $this->actingAsRole('administratie');
        Carbon::setTestNow('2026-10-20');
        $invoice = $this->draft();
        $this->assertNull($invoice->number);
        $this->assertSame('concept', $invoice->status);
        $this->assertSame('850.00', $invoice->total_excl);
        $this->assertSame('1028.50', $invoice->total_incl);
        $this->get('/facturen/'.$invoice->id.'/pdf')->assertOk();

        Setting::put(['nummer.factuur' => '200515']);
        $this->post("/facturen/{$invoice->id}/definitief")->assertRedirect();
        $invoice->refresh();
        $this->assertSame(200515, $invoice->number);
        $this->assertSame('2026-10-20', $invoice->date->toDateString());
        $this->assertSame('2026-11-03', $invoice->due_date->toDateString()); // betaaltermijn relatie 14 dagen
        $this->assertSame('1028.50', $invoice->total_open);
        $this->assertSame('open', $invoice->state());

        // daarna vergrendeld
        $this->get("/facturen/{$invoice->id}/edit")->assertRedirect(route('facturen.show', $invoice));
        $this->put("/facturen/{$invoice->id}", $this->payload($invoice->company))->assertStatus(422);
        $this->delete("/facturen/{$invoice->id}")->assertSessionHasErrors('factuur');

        $second = $this->draft();
        $this->post("/facturen/{$second->id}/definitief");
        $this->assertSame(200516, $second->fresh()->number);
        Carbon::setTestNow();
    }

    public function test_zonder_regels_niet_definitief(): void
    {
        $this->actingAsRole('administratie');
        $invoice = $this->draft(['lines' => []]);
        $this->post("/facturen/{$invoice->id}/definitief")->assertSessionHasErrors('factuur');
        $this->assertNull($invoice->fresh()->number);
    }

    public function test_betalingen_openstaand_en_betaald(): void
    {
        $this->actingAsRole('administratie');
        $invoice = $this->draft();
        $this->post("/facturen/{$invoice->id}/betalingen", ['date' => '2026-10-21', 'amount' => '100'])->assertStatus(422); // concept
        $this->post("/facturen/{$invoice->id}/definitief");

        $this->post("/facturen/{$invoice->id}/betalingen", ['date' => '2026-10-21', 'amount' => '500,00', 'notes' => 'Bank'])->assertRedirect();
        $this->assertSame('528.50', $invoice->fresh()->total_open);
        $this->post("/facturen/{$invoice->id}/betalingen", ['date' => '2026-10-22', 'amount' => '528,50']);
        $invoice->refresh();
        $this->assertSame('0.00', $invoice->total_open);
        $this->assertSame('betaald', $invoice->state());

        $payment = $invoice->payments()->first();
        $this->delete("/facturen/{$invoice->id}/betalingen/{$payment->id}");
        $this->assertSame('500.00', $invoice->fresh()->total_open);
    }

    public function test_herinnering_alleen_bij_vervallen(): void
    {
        $this->actingAsRole('administratie');
        $invoice = $this->draft(['date' => '2026-01-01', 'due_date' => '2026-01-31']);
        $this->post("/facturen/{$invoice->id}/herinnering")->assertStatus(422);
        $this->post("/facturen/{$invoice->id}/definitief");
        $this->post("/facturen/{$invoice->id}/herinnering")->assertRedirect();
        $this->post("/facturen/{$invoice->id}/herinnering");
        $invoice->refresh();
        $this->assertSame(2, $invoice->reminder_level);
        $this->assertSame('vervallen', $invoice->state());
        $this->assertStringContainsString('tweede herinnering', $invoice->stateLabel());
        $this->get('/facturen?status=vervallen')->assertSee('Werkzaamheden oktober');
        $pdf = $this->get("/facturen/{$invoice->id}/pdf")->assertOk();
        $this->assertStringContainsString('Herinnering', $pdf->headers->get('content-disposition'));
    }

    public function test_creditfactuur(): void
    {
        $this->actingAsRole('administratie');
        $invoice = $this->draft();
        $this->post("/facturen/{$invoice->id}/definitief");
        $this->post("/facturen/{$invoice->id}/crediteren")->assertRedirect();
        $credit = Invoice::where('credit_of_id', $invoice->id)->firstOrFail();
        $this->assertSame('-850.00', $credit->total_excl);
        $this->assertSame('-1028.50', $credit->total_incl);
        $this->assertTrue($credit->isCredit());
        $this->assertSame('concept', $credit->status);
        $this->post("/facturen/{$credit->id}/definitief");
        $this->assertSame('Creditfactuur '.$credit->fresh()->number, $credit->fresh()->title());
    }

    public function test_factuur_uit_opdracht_restregels_en_termijn(): void
    {
        $this->actingAsRole('administratie');
        $project = Project::factory()->create(['total_excl' => 1000]);
        $a = $project->lines()->create(['sort' => 0, 'row_type' => 'normaal', 'title' => 'Montage', 'quantity' => 10, 'unit_price' => 80, 'vat_rate_id' => $this->vat->id]);
        $b = $project->lines()->create(['sort' => 1, 'row_type' => 'normaal', 'title' => 'Materiaal', 'quantity' => 1, 'unit_price' => 200, 'vat_rate_id' => $this->vat->id]);

        $this->get("/facturen/create?project_id={$project->id}")->assertOk()->assertSee('Montage')->assertSee('Materiaal');

        // eerste factuur: 4 van 10 uur montage
        $this->post('/facturen', $this->payload($project->company, ['project_id' => $project->id, 'lines' => [
            ['row_type' => 'normaal', 'title' => 'Montage', 'quantity' => '4', 'unit_price' => '80', 'vat_rate_id' => $this->vat->id, 'project_id' => $project->id, 'project_line_id' => $a->id],
        ]]));
        $this->assertSame([$a->id => 4.0], $project->invoicedQuantities());

        $page = $this->get("/facturen/create?project_id={$project->id}")->assertOk();
        $page->assertSee('value="6"', false); // resterend 6 uur
        $this->get("/facturen/create?project_id={$project->id}&percentage=30")->assertSee('Termijn 30% van opdracht')->assertSee('300,00');
        $this->get("/opdrachten/{$project->id}")->assertSee('Facturen');
    }

    public function test_contract_maakt_periodieke_conceptfacturen(): void
    {
        $this->actingAsRole('administratie');
        $company = Company::factory()->create();
        $this->post('/contracten', [
            'name' => 'Huur station', 'company_id' => $company->id, 'status' => 'actief', 'frequency' => 'maand',
            'start_date' => '2026-08-01', 'next_invoice_date' => '2026-08-01',
            'lines' => [['row_type' => 'normaal', 'title' => 'Huur', 'quantity' => '1', 'unit_price' => '850', 'vat_rate_id' => $this->vat->id]],
        ])->assertRedirect();
        $contract = Contract::firstOrFail();
        $this->assertSame('850.00', $contract->total_excl);

        Carbon::setTestNow('2026-10-15');
        $this->artisan('werkbank:contracten-factureren')->expectsOutputToContain('3 conceptfactuur')->assertSuccessful();
        $this->assertSame(3, Invoice::where('contract_id', $contract->id)->count());
        $this->assertSame('2026-11-01', $contract->fresh()->next_invoice_date->toDateString());
        $this->assertStringContainsString('01-10-2026 t/m 31-10-2026', Invoice::latest('id')->first()->subject);
        // nogmaals: niets nieuws
        $this->artisan('werkbank:contracten-factureren')->expectsOutputToContain('0 conceptfactuur');
        Carbon::setTestNow();
    }

    public function test_contract_stopt_op_einddatum(): void
    {
        $contract = Contract::create(['number' => 1, 'name' => 'Kort', 'company_id' => Company::factory()->create()->id, 'frequency' => 'maand', 'start_date' => '2026-01-01', 'end_date' => '2026-02-28', 'next_invoice_date' => '2026-01-01']);
        ContractFacturatie::run(Carbon::parse('2026-12-31'));
        $this->assertSame(2, Invoice::count());
        $this->assertNull($contract->fresh()->next_invoice_date);
    }

    public function test_rechten(): void
    {
        $invoice = Invoice::factory()->create();
        $this->actingAsRole('projectleider');
        $this->get('/facturen')->assertOk();
        $this->get("/facturen/{$invoice->id}")->assertOk()->assertDontSee('Definitief maken');
        $this->get('/facturen/create')->assertForbidden();
        $this->post("/facturen/{$invoice->id}/definitief")->assertForbidden();
        $this->actingAsRole('medewerker');
        $this->get('/facturen')->assertForbidden();
    }
}
