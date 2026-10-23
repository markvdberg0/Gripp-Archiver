<?php

namespace App\Import\Mappers;

use App\Import\Context;
use App\Import\LineMapper;
use App\Models\CostCenter;
use App\Models\Ledger;
use App\Models\Project;
use App\Models\ProjectLine;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use App\Models\PurchaseOrderLine;
use Illuminate\Support\Facades\DB;

class PurchaseInvoiceLineMapper extends LineMapper
{
    public function entity(): string
    {
        return 'purchaseinvoiceline';
    }

    public function model(): string
    {
        return PurchaseInvoiceLine::class;
    }

    protected function vatKind(): string
    {
        return 'inkoop';
    }

    protected function parentColumn(): string
    {
        return 'purchase_invoice_id';
    }

    protected function parentId(array $row, Context $ctx): ?int
    {
        return $ctx->id(PurchaseInvoice::class, $row['purchaseinvoice'] ?? null);
    }

    public function map(array $row, Context $ctx): ?array
    {
        return parent::map($row, $ctx) + [
            'ledger_id' => $ctx->id(Ledger::class, $row['ledger'] ?? null),
            'cost_center_id' => $ctx->id(CostCenter::class, $row['costheading'] ?? null),
            'project_id' => $ctx->id(Project::class, $row['project'] ?? null),
            'project_line_id' => $ctx->id(ProjectLine::class, $row['part'] ?? null),
            'purchase_order_line_id' => $ctx->id(PurchaseOrderLine::class, $row['purchaseorderline'] ?? null),
        ];
    }

    /**
     * Regels tegenover het Gripp-totaal (incl. btw, met verlegde btw), en inkoopopdracht en opdracht van de
     * factuur afleiden uit de regels. Totalen excl./btw worden uit de regels gezet.
     */
    public function checks(Context $ctx): array
    {
        $invoices = PurchaseInvoice::withTrashed()->whereNotNull('gripp_id')->with('lines.purchaseOrderLine')->get();
        $check = $this->totalsCheck($invoices, $ctx, 'inkoopfacturen (met regels) waarvan regels optellen tot het totaal incl. btw', 'incl');
        foreach ($invoices as $invoice) {
            $t = $invoice->computeTotals();
            if (! $ctx->dryRun) {
                $order = $invoice->lines->pluck('purchaseOrderLine.purchase_order_id')->filter()->countBy()->sortDesc()->keys()->first();
                $project = $invoice->lines->whereNotNull('project_id')->countBy('project_id')->sortDesc()->keys()->first();
                $excl = $invoice->lines->isNotEmpty() ? $t['excl'] : $invoice->total_incl;
                DB::table('purchase_invoices')->where('id', $invoice->id)->update([
                    'purchase_order_id' => $invoice->purchase_order_id ?? $order, 'project_id' => $invoice->project_id ?? $project,
                    'total_excl' => $excl, 'total_vat' => round((float) $invoice->total_incl - (float) $excl, 2),
                ]);
            }
        }

        return array_merge(parent::checks($ctx), [$check]);
    }
}
