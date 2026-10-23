<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CostCenter;
use App\Models\Ledger;
use App\Models\Project;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\Setting;
use App\Models\User;
use App\Models\VatRate;
use Tests\TestCase;

class PurchaseTest extends TestCase
{
    private VatRate $vat;

    private VatRate $eu;

    private Company $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vat = VatRate::create(['name' => 'Btw 21%', 'percentage' => 21, 'kind' => 'inkoop']);
        $this->eu = VatRate::create(['name' => 'Btw binnen EU 21%', 'percentage' => 21, 'kind' => 'inkoop', 'reverse_charge' => true]);
        $this->supplier = Company::factory()->supplier()->create(['supplier_payment_term_days' => 14]);
    }

    private function invoicePayload(array $overrides = []): array
    {
        return array_merge([
            'supplier_id' => $this->supplier->id, 'invoice_number' => 'F-100', 'date' => '2026-10-01', 'payment_method' => 'overboeking', 'expected_total' => '1.210,00',
            'lines' => [['row_type' => 'normaal', 'title' => 'Kabel', 'quantity' => '1', 'unit_price' => '1000', 'vat_rate_id' => $this->vat->id,
                'ledger_id' => Ledger::create(['number' => '7000', 'name' => 'Inkoop'])->id, 'cost_center_id' => CostCenter::create(['name' => 'Montage'])->id]],
        ], $overrides);
    }

    public function test_inkoopopdracht_met_nummer_status_en_pdf(): void
    {
        $this->actingAsRole('projectleider');
        $project = Project::factory()->create();
        $this->post('/inkoopopdrachten', [
            'name' => 'Trafo', 'supplier_id' => $this->supplier->id, 'project_id' => $project->id, 'date' => '2026-10-01',
            'lines' => [['row_type' => 'normaal', 'title' => 'Trafo 630', 'quantity' => '1', 'unit_price' => '12000', 'vat_rate_id' => $this->vat->id]],
        ])->assertRedirect();
        $order = PurchaseOrder::firstOrFail();
        $this->assertSame(1, $order->number);
        $this->assertSame('12000.00', $order->total_excl);
        $this->assertSame($project->id, $order->lines->first()->project_id, 'regel neemt de opdracht over');

        $this->post("/inkoopopdrachten/{$order->id}/status/bevestigd")->assertRedirect();
        $this->assertSame('bevestigd', $order->fresh()->status);
        $this->assertStringStartsWith('%PDF', $this->get("/inkoopopdrachten/{$order->id}/pdf")->assertOk()->getContent());
        $this->delete("/inkoopopdrachten/{$order->id}")->assertSessionHasErrors('inkoopopdracht'); // niet meer concept
    }

    public function test_inkoopfactuur_uit_inkoopopdracht(): void
    {
        $this->actingAsRole('administratie');
        $order = PurchaseOrder::create(['number' => 5, 'name' => 'Trafo', 'supplier_id' => $this->supplier->id, 'date' => '2026-10-01']);
        $line = $order->lines()->create(['row_type' => 'normaal', 'title' => 'Trafo', 'quantity' => 1, 'unit_price' => 1000, 'vat_rate_id' => $this->vat->id]);
        $order->recalculate();

        $this->get("/inkoopfacturen/create?purchase_order_id={$order->id}")->assertOk()->assertSee('Trafo')->assertSee('name="lines[0][purchase_order_line_id]" value="'.$line->id.'"', false);
        $this->post('/inkoopfacturen', $this->invoicePayload(['purchase_order_id' => $order->id, 'lines' => [
            ['row_type' => 'normaal', 'title' => 'Trafo', 'quantity' => '1', 'unit_price' => '1000', 'vat_rate_id' => $this->vat->id, 'purchase_order_line_id' => $line->id],
        ]]))->assertRedirect();
        $invoice = PurchaseInvoice::firstOrFail();
        $this->assertSame(1, $invoice->booking_number);
        $this->assertSame('2026-10-15', $invoice->due_date->toDateString()); // termijn leverancier 14 dagen
        $this->assertSame('1210.00', $invoice->total_incl);
        $this->assertSame(1000.0, $order->invoicedExcl());
    }

    public function test_btw_verlegd_telt_niet_mee_in_totaal(): void
    {
        $this->actingAsRole('administratie');
        $this->post('/inkoopfacturen', $this->invoicePayload(['expected_total' => '1000', 'lines' => [
            ['row_type' => 'normaal', 'title' => 'Onderdelen', 'quantity' => '1', 'unit_price' => '1000', 'vat_rate_id' => $this->eu->id],
        ]]));
        $invoice = PurchaseInvoice::firstOrFail();
        $this->assertSame('1000.00', $invoice->total_excl);
        $this->assertSame('0.00', $invoice->total_vat);
        $this->assertSame('1000.00', $invoice->total_incl);
        $this->get("/inkoopfacturen/{$invoice->id}")->assertSee('(verlegd)');
    }

    public function test_controle_en_goedkeuring_met_vier_ogen(): void
    {
        Setting::put(['inkoop.vier_ogen_drempel' => '500']);
        $clerk = User::factory()->role('administratie')->create();
        $this->actingAs($clerk);
        $this->post('/inkoopfacturen', $this->invoicePayload(['expected_total' => '1.000,00']))->assertRedirect();
        $invoice = PurchaseInvoice::firstOrFail();

        // totaal klopt niet met de factuur → blokkeert
        $this->post("/inkoopfacturen/{$invoice->id}/fase/indienen");
        $this->assertSame('controle', $invoice->fresh()->phase);
        $this->post("/inkoopfacturen/{$invoice->id}/fase/goedkeuren")->assertSessionHasErrors('inkoopfactuur');

        $this->put("/inkoopfacturen/{$invoice->id}", $this->invoicePayload())->assertRedirect();
        // zelf ingevoerd en boven de drempel → iemand anders moet goedkeuren
        $this->post("/inkoopfacturen/{$invoice->id}/fase/goedkeuren")->assertSessionHasErrors('inkoopfactuur');
        $this->assertSame('controle', $invoice->fresh()->phase);

        $boss = User::factory()->role('administratie')->create();
        $this->actingAs($boss);
        $this->post("/inkoopfacturen/{$invoice->id}/fase/goedkeuren")->assertSessionHasNoErrors();
        $invoice->refresh();
        $this->assertSame('akkoord', $invoice->phase);
        $this->assertSame($boss->id, $invoice->approved_by);
        $this->assertFalse($invoice->isEditable());
        $this->put("/inkoopfacturen/{$invoice->id}", $this->invoicePayload())->assertStatus(422);

        $this->post("/inkoopfacturen/{$invoice->id}/betalingen", ['date' => '2026-10-10', 'amount' => '1210'])->assertRedirect();
        $this->assertSame('0.00', $invoice->fresh()->total_open);
        $this->post("/inkoopfacturen/{$invoice->id}/fase/verwerken");
        $this->assertSame('verwerkt', $invoice->fresh()->phase);
    }

    public function test_dubbel_factuurnummer_blokkeert(): void
    {
        $this->actingAsRole('administratie');
        $this->post('/inkoopfacturen', $this->invoicePayload());
        $this->post('/inkoopfacturen', $this->invoicePayload());
        $second = PurchaseInvoice::latest('id')->firstOrFail();
        $this->post("/inkoopfacturen/{$second->id}/fase/goedkeuren")->assertSessionHasErrors('inkoopfactuur');
        $this->assertSame(2, $second->booking_number);
    }

    public function test_on_hold_en_vrijgeven(): void
    {
        $this->actingAsRole('administratie');
        $this->post('/inkoopfacturen', $this->invoicePayload());
        $invoice = PurchaseInvoice::firstOrFail();
        $this->post("/inkoopfacturen/{$invoice->id}/fase/hold", ['hold_reason' => ''])->assertSessionHasErrors('hold_reason');
        $this->post("/inkoopfacturen/{$invoice->id}/fase/hold", ['hold_reason' => 'Geen PO'])->assertRedirect();
        $this->assertTrue($invoice->fresh()->on_hold);
        $this->post("/inkoopfacturen/{$invoice->id}/fase/goedkeuren")->assertStatus(422);
        $this->get('/inkoopfacturen?fase=on_hold')->assertSee('F-100');
        $this->post("/inkoopfacturen/{$invoice->id}/fase/vrijgeven");
        $this->assertFalse($invoice->fresh()->on_hold);
    }

    public function test_projectleider_mag_invoeren_niet_goedkeuren(): void
    {
        $this->actingAsRole('projectleider');
        $this->post('/inkoopfacturen', $this->invoicePayload())->assertRedirect();
        $invoice = PurchaseInvoice::firstOrFail();
        $this->post("/inkoopfacturen/{$invoice->id}/fase/goedkeuren")->assertSessionHasErrors('inkoopfactuur');
        $this->assertSame('concept', $invoice->fresh()->phase);
        $this->actingAsRole('medewerker');
        $this->get('/inkoopfacturen')->assertForbidden();
    }
}
