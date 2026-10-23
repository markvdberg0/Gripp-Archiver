<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Hour;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\OfferLine;
use App\Models\Payment;
use App\Models\Project;
use App\Models\PurchaseInvoice;
use App\Models\Setting;
use App\Models\StoredFile;
use App\Models\TimelineEntry;
use App\Models\User;
use App\Models\VatRate;
use App\Support\Bedragen;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Regressietests voor de bevindingen uit de codereview. */
class ReviewFixesTest extends TestCase
{
    private function purchaseInvoice(User $creator, float $amount): PurchaseInvoice
    {
        $vat = VatRate::firstOrCreate(['name' => 'Btw 21%'], ['percentage' => 21, 'kind' => 'beide']);
        $invoice = PurchaseInvoice::create(['supplier_id' => Company::factory()->supplier()->create()->id, 'invoice_number' => 'X-'.uniqid(), 'date' => now(), 'phase' => 'controle', 'created_by' => $creator->id, 'updated_by' => $creator->id]);
        $invoice->lines()->create(['row_type' => 'normaal', 'title' => 'Iets', 'quantity' => 1, 'unit_price' => $amount, 'vat_rate_id' => $vat->id]);
        $invoice->recalculate();

        return $invoice;
    }

    public function test_vier_ogen_geldt_ook_voor_wie_de_factuur_wijzigde(): void
    {
        Setting::put(['inkoop.vier_ogen_drempel' => '500']);
        $a = User::factory()->role('administratie')->create();
        $b = User::factory()->role('administratie')->create();
        $invoice = $this->purchaseInvoice($a, 200);
        $vat = VatRate::first();

        $this->actingAs($b);
        $this->put("/inkoopfacturen/{$invoice->id}", [
            'supplier_id' => $invoice->supplier_id, 'invoice_number' => $invoice->invoice_number, 'date' => '2026-10-01', 'payment_method' => 'overboeking',
            'lines' => [['row_type' => 'normaal', 'title' => 'Iets', 'quantity' => '1', 'unit_price' => '20000', 'vat_rate_id' => $vat->id]],
        ])->assertRedirect();
        $this->post("/inkoopfacturen/{$invoice->id}/fase/goedkeuren")->assertSessionHasErrors('inkoopfactuur');
        $this->assertSame('controle', $invoice->fresh()->phase);
    }

    public function test_administratie_kan_de_vier_ogendrempel_niet_wijzigen(): void
    {
        Setting::put(['inkoop.vier_ogen_drempel' => '2500']);
        $this->actingAsRole('administratie');
        $this->put('/beheer/instellingen', ['offerte__geldigheid_dagen' => 30, 'factuur__betaaltermijn' => 30, 'inkoop__vier_ogen_drempel' => 99999999])->assertRedirect();
        $this->assertSame('2500', Setting::get('inkoop.vier_ogen_drempel'));
    }

    public function test_verwerkte_of_betaalde_inkoopfactuur_niet_terug_en_vrijgeven_alleen_door_goedkeurder(): void
    {
        $a = User::factory()->role('administratie')->create();
        $invoice = $this->purchaseInvoice($a, 100);
        $invoice->update(['phase' => 'verwerkt']);
        $this->actingAsRole('beheerder');
        $this->post("/inkoopfacturen/{$invoice->id}/fase/terug")->assertSessionHasErrors('inkoopfactuur');
        $this->assertSame('verwerkt', $invoice->fresh()->phase);

        $held = $this->purchaseInvoice($a, 100);
        $held->update(['on_hold' => true, 'hold_reason' => 'Geen PO', 'phase' => 'akkoord']);
        $this->post("/inkoopfacturen/{$held->id}/betalingen", ['date' => '2026-10-01', 'amount' => '121'])->assertStatus(422);
        $this->actingAsRole('projectleider');
        $this->post("/inkoopfacturen/{$held->id}/fase/vrijgeven")->assertForbidden();
        $this->assertTrue($held->fresh()->on_hold);
    }

    public function test_lokaal_verwijderde_betaling_en_regel_komen_niet_terug(): void
    {
        $backup = $this->backup();
        $this->runImport($backup);
        Payment::where('gripp_id', 4001)->first()->delete();
        OfferLine::where('gripp_id', 112)->first()->delete();

        $backup->update('payment', 4001, ['notes' => 'gewijzigd in Gripp']);
        $run = $this->runImport($backup);

        $this->assertNull(Payment::where('gripp_id', 4001)->first());
        $this->assertNull(OfferLine::where('gripp_id', 112)->first(), 'ongewijzigde maar lokaal verwijderde regel blijft weg');
        $this->assertSame(1, $run->summary['entities']['payment']['conflicts']);
    }

    public function test_regels_onder_lokaal_gewijzigd_document_worden_niet_bijgewerkt(): void
    {
        $backup = $this->backup();
        $this->runImport($backup);
        $this->travel(2)->minutes();
        Invoice::where('gripp_id', 2001)->first()->update(['subject' => 'Lokaal']);
        $backup->update('invoiceline', 3001, ['sellingprice' => '999.00']);

        $run = $this->runImport($backup);

        $this->assertSame('1000.0000', InvoiceLine::where('gripp_id', 3001)->value('unit_price'));
        $this->assertSame(1, $run->summary['entities']['invoiceline']['conflicts']);
    }

    public function test_dubbel_definitief_maken_geeft_geen_tweede_nummer(): void
    {
        $this->actingAsRole('administratie');
        $vat = VatRate::create(['name' => 'Btw 21%', 'percentage' => 21]);
        $invoice = Invoice::factory()->create();
        $invoice->lines()->create(['row_type' => 'normaal', 'title' => 'X', 'quantity' => 1, 'unit_price' => 10, 'vat_rate_id' => $vat->id]);
        $this->post("/facturen/{$invoice->id}/definitief");
        $number = $invoice->fresh()->number;
        // tweede verzoek met hetzelfde (nog niet ververste) record
        $this->post("/facturen/{$invoice->id}/definitief")->assertStatus(422);
        $this->assertSame($number, $invoice->fresh()->number);
        $this->assertSame(1, $invoice->timeline()->count());
    }

    public function test_factuur_maar_een_keer_crediteren(): void
    {
        $this->actingAsRole('administratie');
        $invoice = Invoice::factory()->create(['status' => 'verzonden', 'number' => 5]);
        $this->post("/facturen/{$invoice->id}/crediteren")->assertRedirect();
        $this->post("/facturen/{$invoice->id}/crediteren")->assertSessionHasErrors('factuur');
        $this->assertSame(1, Invoice::where('credit_of_id', $invoice->id)->count());
    }

    public function test_eigen_uren_niet_zelf_fiatteren(): void
    {
        $leader = User::factory()->role('projectleider')->create();
        $me = Employee::factory()->create(['user_id' => $leader->id]);
        $project = Project::factory()->create();
        $own = Hour::create(['date' => now(), 'employee_id' => $me->id, 'project_id' => $project->id, 'hours' => 4, 'status' => 'definitief']);
        $other = Hour::create(['date' => now(), 'employee_id' => Employee::factory()->create()->id, 'project_id' => $project->id, 'hours' => 2, 'status' => 'definitief']);
        $this->actingAs($leader);
        $this->post('/uren/fiatteren', ['ids' => [$own->id, $other->id], 'actie' => 'fiatteren']);
        $this->assertSame('definitief', $own->fresh()->status);
        $this->assertSame('gefiatteerd', $other->fresh()->status);
    }

    public function test_financiele_timeline_en_bijlagen_niet_zichtbaar_zonder_recht(): void
    {
        $company = Company::factory()->create();
        $invoice = Invoice::factory()->create(['company_id' => $company->id]);
        $entry = TimelineEntry::create(['company_id' => $company->id, 'invoice_id' => $invoice->id, 'type' => 'email_ontvangen', 'occurred_at' => now(), 'subject' => 'Factuur ter info']);
        $file = StoredFile::create(['name' => 'factuur.pdf', 'path' => 'bestanden/aa/x', 'mime_type' => 'application/pdf']);
        Storage::disk('local')->put('bestanden/aa/x', '%PDF-1.4');
        $entry->files()->attach($file->id, ['role' => 'bijlage', 'created_at' => now()]);

        $this->actingAsRole('medewerker');
        $this->get("/relaties/{$company->id}")->assertOk()->assertDontSee('Factuur ter info');
        $this->get("/bestanden/{$file->id}")->assertForbidden();

        $this->actingAsRole('administratie');
        $this->get("/relaties/{$company->id}")->assertSee('Factuur ter info');
        $this->get("/bestanden/{$file->id}")->assertOk();
    }

    public function test_bestandsnaam_met_slash_is_downloadbaar(): void
    {
        $company = Company::factory()->create();
        $file = StoredFile::create(['name' => 'offerte 1/2.pdf', 'path' => 'bestanden/bb/y', 'mime_type' => 'application/pdf']);
        Storage::disk('local')->put('bestanden/bb/y', '%PDF-1.4');
        $company->files()->attach($file->id, ['role' => 'bijlage', 'created_at' => now()]);
        $this->actingAsRole('medewerker');
        $this->get("/bestanden/{$file->id}")->assertOk();
    }

    public function test_getallen_met_duizendtalscheiding(): void
    {
        $this->assertSame('1250', Bedragen::parse('1.250'));
        $this->assertSame('12500000', Bedragen::parse('12.500.000'));
        $this->assertSame('1234.56', Bedragen::parse('1,234.56'));
        $this->assertSame('1234.56', Bedragen::parse('1.234,56'));
        $this->assertSame('1.25', Bedragen::parse('1.25'));
        $this->assertSame('7.5', Bedragen::parse('7,5'));
    }
}
