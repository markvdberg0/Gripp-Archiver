<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectLine;
use Tests\TestCase;

class ImportInvoicesTest extends TestCase
{
    public function test_facturen_regels_betalingen_en_contracten(): void
    {
        $backup = $this->backup();
        $backup->invoicePdfs = [2001 => "%PDF-1.4\nfactuur\n%%EOF"];
        $run = $this->runImport($backup);
        $this->assertSame('geslaagd', $run->status, (string) $run->error);

        $invoice = Invoice::where('gripp_id', 2001)->firstOrFail();
        $this->assertSame(200441, $invoice->number);
        $this->assertSame('verzonden', $invoice->status);
        $this->assertSame(1, $invoice->reminder_level);
        $this->assertSame('1210.00', $invoice->total_incl);
        $this->assertSame('710.00', $invoice->total_open);
        $this->assertSame('Piet Bakker', $invoice->contact->name);
        $this->assertSame('2025-07-31', $invoice->due_date->toDateString());

        // regel gekoppeld aan opdracht en opdrachtregel; hoofdopdracht van de factuur afgeleid
        $line = InvoiceLine::where('gripp_id', 3001)->firstOrFail();
        $project = Project::where('gripp_id', 901)->firstOrFail();
        $this->assertSame($project->id, $line->project_id);
        $this->assertSame(ProjectLine::where('gripp_id', 900)->value('id'), $line->project_line_id);
        $this->assertSame('8000', $line->ledger->number);
        $this->assertSame($project->id, $invoice->project_id);
        $this->assertFalse($invoice->fresh()->isLocallyModified(), 'afleiden van de opdracht telt niet als lokale wijziging');

        $this->assertSame(['Deelbetaling'], $invoice->payments->pluck('notes')->all());
        $this->assertSame(2, Payment::count());

        // creditnota en concept
        $this->assertTrue(Invoice::where('gripp_id', 2002)->first()->isCredit());
        $concept = Invoice::where('gripp_id', 2003)->first();
        $this->assertNull($concept->number);
        $this->assertSame('concept', $concept->status);

        // factuur-PDF uit het archief
        $this->assertSame('Factuur 200441 (Gripp).pdf', $invoice->files()->wherePivot('role', 'document')->value('name'));

        $contract = Contract::where('gripp_id', 61)->firstOrFail();
        $this->assertSame('maand', $contract->frequency);
        $this->assertSame('actief', $contract->status);
        $this->assertNull($contract->next_invoice_date, 'na import worden niet vanzelf facturen gemaakt');
        $this->assertSame('850.00', $contract->total_excl);

        $checks = collect($run->summary['checks'])->keyBy('label');
        foreach (['invoice: som totaal incl. btw', 'invoice: som openstaand incl. btw', 'invoiceline: facturen waarvan regels optellen tot het totaal', 'payment: facturen waarvan totaal − betalingen = openstaand'] as $label) {
            $this->assertTrue($checks[$label]['ok'], $label);
        }
    }

    public function test_tweede_import_zonder_wijzigingen_of_conflicten(): void
    {
        $backup = $this->backup();
        $this->runImport($backup);
        $second = $this->runImport($backup);
        $this->assertSame(0, $second->totals()['created']);
        $this->assertSame(0, $second->totals()['updated']);
        $this->assertSame(0, $second->totals()['conflicts']);
    }
}
