<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\VatRate;
use Tests\TestCase;

class ImportPurchaseTest extends TestCase
{
    public function test_inkoopopdrachten_inkoopfacturen_en_betalingen(): void
    {
        $run = $this->runImport($this->backup());
        $this->assertSame('geslaagd', $run->status, (string) $run->error);

        $order = PurchaseOrder::where('gripp_id', 8001)->firstOrFail();
        $this->assertSame('Staal & Co', $order->supplier->name, 'leverancier uit "company" als "supplier" geen relatie is');
        $this->assertSame('bevestigd', $order->status);
        $this->assertSame('12000.00', $order->total_excl);
        $this->assertSame('14520.00', $order->total_incl);
        $project = Project::where('gripp_id', 901)->firstOrFail();
        $this->assertSame($project->id, $order->project_id, 'opdracht afgeleid uit de regels');
        $line = PurchaseOrderLine::where('gripp_id', 8501)->firstOrFail();
        $this->assertSame('TR-630', $line->supplier_code);
        $this->assertSame('stuks', $line->unit->name);

        $processed = PurchaseInvoice::where('gripp_id', 6001)->firstOrFail();
        $this->assertSame(1201, $processed->booking_number);
        $this->assertSame('INK-1', $processed->invoice_number);
        $this->assertSame('verwerkt', $processed->phase);
        $this->assertSame('4520.00', $processed->total_open);
        $this->assertSame('12000.00', $processed->total_excl);
        $this->assertSame($order->id, $processed->purchase_order_id, 'inkoopopdracht afgeleid uit de regels');
        $this->assertSame(['kvk-uittreksel.pdf'], $processed->files->pluck('name')->all());
        $this->assertFalse($processed->isLocallyModified());

        $hold = PurchaseInvoice::where('gripp_id', 6002)->firstOrFail();
        $this->assertTrue($hold->on_hold);
        $this->assertTrue($hold->inbox);
        $this->assertSame('controle', $hold->phase);
        $this->assertTrue(VatRate::where('gripp_id', 137)->value('reverse_charge'));
        $this->assertSame('1000.00', $hold->computeTotals()['incl'], 'verlegde btw niet in het totaal');

        // dubbel boeknummer: tweede zonder nummer, met melding
        $this->assertNull(PurchaseInvoice::where('gripp_id', 6003)->value('booking_number'));
        $this->assertStringContainsString('Boeknummer 1201', collect($run->issues['purchaseinvoice'])->pluck('message')->join(' '));

        $checks = collect($run->summary['checks'])->keyBy('label');
        foreach (['purchaseorder: som totaal excl. btw', 'purchaseinvoice: som totaal incl. btw', 'purchaseinvoice: som openstaand incl. btw', 'purchasepayment: inkoopfacturen waarvan totaal − betalingen = openstaand', 'purchaseinvoiceline: inkoopfacturen (met regels) waarvan regels optellen tot het totaal incl. btw', 'purchaseorderline: inkoopopdrachten waarvan regels optellen tot het totaal'] as $label) {
            $this->assertTrue($checks[$label]['ok'], $label);
        }
    }

    public function test_afwijkend_totaal_wordt_per_document_gemeld(): void
    {
        $backup = $this->backup();
        $backup->update('purchaseorder', 8001, ['totalincldiscountexclvat' => '11999.97']);
        $run = $this->runImport($backup);
        $check = collect($run->summary['checks'])->firstWhere('label', 'purchaseorderline: inkoopopdrachten waarvan regels optellen tot het totaal');
        $this->assertFalse($check['ok']);
        $this->assertStringContainsString('regels tellen op tot 12.000,00, totaal in Gripp is 11.999,97', collect($run->issues['purchaseorderline'])->pluck('message')->join(' '));
    }

    public function test_tweede_import_zonder_wijzigingen(): void
    {
        $backup = $this->backup();
        $this->runImport($backup);
        $second = $this->runImport($backup);
        $this->assertSame(0, $second->totals()['created'] + $second->totals()['updated'] + $second->totals()['conflicts']);
    }
}
