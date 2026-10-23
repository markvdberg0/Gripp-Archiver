<?php

namespace App\Import\Mappers;

use App\Import\Context;
use App\Import\Gripp;
use App\Import\LineMapper;
use App\Models\CostCenter;
use App\Models\Ledger;
use App\Models\Project;
use App\Models\ProjectLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Illuminate\Support\Facades\DB;

class PurchaseOrderLineMapper extends LineMapper
{
    public function entity(): string
    {
        return 'purchaseorderline';
    }

    public function model(): string
    {
        return PurchaseOrderLine::class;
    }

    protected function vatKind(): string
    {
        return 'inkoop';
    }

    protected function parentColumn(): string
    {
        return 'purchase_order_id';
    }

    protected function parentId(array $row, Context $ctx): ?int
    {
        return $ctx->id(PurchaseOrder::class, $row['purchaseorder'] ?? null);
    }

    public function map(array $row, Context $ctx): ?array
    {
        return parent::map($row, $ctx) + [
            'ledger_id' => $ctx->id(Ledger::class, $row['ledger'] ?? null),
            'cost_center_id' => $ctx->id(CostCenter::class, $row['costheading'] ?? null),
            'project_id' => $ctx->id(Project::class, $row['project'] ?? null),
            'project_line_id' => $ctx->id(ProjectLine::class, $row['projectline'] ?? null),
            'supplier_code' => Gripp::str($row['ordercodesupplier'] ?? null, 100),
        ];
    }

    /** Btw-totalen uit de regels (Gripp levert alleen excl.) en de opdracht van de inkoopopdracht afleiden. */
    public function checks(Context $ctx): array
    {
        $orders = PurchaseOrder::withTrashed()->whereNotNull('gripp_id')->with('lines')->get();
        $check = $this->totalsCheck($orders, $ctx, 'inkoopopdrachten waarvan regels optellen tot het totaal');
        foreach ($orders as $order) {
            $t = $order->computeTotals();
            if (! $ctx->dryRun) {
                $project = $order->project_id ?? $order->lines->whereNotNull('project_id')->countBy('project_id')->sortDesc()->keys()->first();
                DB::table('purchase_orders')->where('id', $order->id)->update(['total_vat' => $t['vat'], 'total_incl' => (float) $order->total_excl + (float) $t['vat'], 'project_id' => $project]);
            }
        }

        return array_merge(parent::checks($ctx), [$check]);
    }
}
