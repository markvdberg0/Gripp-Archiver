<?php

namespace App\Import\Mappers;

use App\Import\Context;
use App\Import\LineMapper;
use App\Models\ContractLine;
use App\Models\CostCenter;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Ledger;
use App\Models\Project;
use App\Models\ProjectLine;
use Illuminate\Support\Facades\DB;

class InvoiceLineMapper extends LineMapper
{
    public function entity(): string
    {
        return 'invoiceline';
    }

    public function model(): string
    {
        return InvoiceLine::class;
    }

    protected function parentColumn(): string
    {
        return 'invoice_id';
    }

    protected function parentId(array $row, Context $ctx): ?int
    {
        return $ctx->id(Invoice::class, $row['invoice'] ?? null);
    }

    public function map(array $row, Context $ctx): ?array
    {
        return parent::map($row, $ctx) + [
            'ledger_id' => $ctx->id(Ledger::class, $row['ledger'] ?? null),
            'cost_center_id' => $ctx->id(CostCenter::class, $row['costheading'] ?? null),
            'project_id' => $ctx->id(Project::class, $row['project'] ?? null),
            'project_line_id' => $ctx->id(ProjectLine::class, $row['part'] ?? null),
            'contract_line_id' => $ctx->id(ContractLine::class, $row['contractline'] ?? null),
        ];
    }

    /** Herberekening uit de regels tegenover de Gripp-totalen, en de hoofdopdracht van elke factuur afleiden. */
    public function checks(Context $ctx): array
    {
        if (! $ctx->dryRun) {
            // Hoofdopdracht van een factuur: de opdracht waar de meeste regels naar verwijzen.
            foreach (Invoice::whereNotNull('gripp_id')->whereNull('project_id')->with('lines')->get() as $invoice) {
                $project = $invoice->lines->whereNotNull('project_id')->countBy('project_id')->sortDesc()->keys()->first();
                if ($project) {
                    // Rechtstreeks, zonder updated_at te raken (anders lijkt de factuur lokaal gewijzigd).
                    DB::table('invoices')->where('id', $invoice->id)->update(['project_id' => $project]);
                }
            }
        }
        $invoices = Invoice::withTrashed()->whereNotNull('gripp_id')->with('lines')->get();

        return array_merge(parent::checks($ctx), [$this->totalsCheck($invoices, $ctx, 'facturen waarvan regels optellen tot het totaal')]);
    }
}
