<?php

namespace App\Console\Commands;

use App\Support\Audit;
use App\Support\ContractFacturatie;
use Illuminate\Console\Command;

class ContractInvoicesCommand extends Command
{
    protected $signature = 'werkbank:contracten-factureren';

    protected $description = 'Maak conceptfacturen voor contracten waarvan de factuurdatum is bereikt';

    public function handle(): int
    {
        $invoices = ContractFacturatie::run();
        foreach ($invoices as $invoice) {
            $this->line("Conceptfactuur #{$invoice->id}: {$invoice->subject}");
        }
        if ($invoices) {
            Audit::log('contracten_gefactureerd', null, count($invoices).' conceptfactuur/-facturen');
        }
        $this->info(count($invoices).' conceptfactuur/-facturen aangemaakt.');

        return self::SUCCESS;
    }
}
