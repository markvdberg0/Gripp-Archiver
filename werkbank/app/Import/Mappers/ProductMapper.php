<?php

namespace App\Import\Mappers;

use App\Import\Context;
use App\Import\Gripp;
use App\Import\Mapper;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Model;

class ProductMapper extends Mapper
{
    public function entity(): string
    {
        return 'product';
    }

    public function model(): string
    {
        return Product::class;
    }

    public function map(array $row, Context $ctx): ?array
    {
        return [
            'number' => is_numeric($row['number'] ?? null) ? (int) $row['number'] : null,
            'name' => Gripp::str($row['name'] ?? $row['searchname'] ?? null) ?? 'Product '.$row['id'],
            'description' => Gripp::text($row['description'] ?? null),
            'internal_note' => Gripp::text($row['internalnote'] ?? null),
            'unit_id' => $ctx->id(Unit::class, $row['unit'] ?? null),
            'selling_price' => Gripp::money($row['sellingprice'] ?? 0, 4),
            'buying_price' => Gripp::money($row['buyingprice'] ?? 0, 4),
            'vat_rate_id' => $ctx->vatRate($row['vat'] ?? null, 'verkoop'),
            'purchase_vat_rate_id' => $ctx->vatRate($row['buyingvat'] ?? null, 'inkoop'),
            'ledger_id' => $ctx->id(Ledger::class, $row['ledger'] ?? null),
            'purchase_ledger_id' => $ctx->id(Ledger::class, $row['purchaseledger'] ?? null),
            'cost_center_id' => $ctx->id(CostCenter::class, $row['costheading'] ?? null),
            'supplier_id' => $ctx->id(Company::class, $row['supplier'] ?? null),
            'supplier_code' => Gripp::str($row['supplierordercode'] ?? null, 100),
            'invoice_basis' => Gripp::is($row['invoicebasis'] ?? null, 'fixed', 'vast', 'vaste prijs') || empty($row['invoicebasis']) ? 'vast' : 'nacalculatie',
            'extra_fields' => self::customFields($row) ?: null,
            'archived' => Gripp::bool($row['archived'] ?? false),
        ];
    }

    public function after(Model $model, array $row, Context $ctx): void
    {
        $this->syncTags($model, $row, $ctx);
        $this->attachFiles($model, $row, $ctx, ['attachments_internal', 'attachments_external']);
    }

    /** Vrije velden (Gripp: customfield_<naam>) → ['naam' => waarde]. */
    public static function customFields(array $row): array
    {
        $out = [];
        foreach ($row as $k => $v) {
            if (str_starts_with($k, 'customfield_') && $v !== null && $v !== '') {
                $out[substr($k, 12)] = is_array($v) ? (Gripp::label($v) ?? json_encode($v)) : $v;
            }
        }

        return $out;
    }
}
