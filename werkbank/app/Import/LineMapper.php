<?php

namespace App\Import;

use App\Models\Product;
use App\Models\Unit;

/**
 * Basis voor documentregels (Gripp: offerprojectline, invoiceline, packetline, …). Regels hebben in Gripp
 * een eigen id en verwijzen naar hun document; ze worden na het document geïmporteerd.
 */
abstract class LineMapper extends Mapper
{
    /** Kolom in de regeltabel die naar het document verwijst, bv. offer_id. */
    abstract protected function parentColumn(): string;

    /** Lokaal id van het document bij dit bronrecord. */
    abstract protected function parentId(array $row, Context $ctx): ?int;

    /** 'verkoop' of 'inkoop' (voor het aanmaken van btw-tarieven). */
    protected function vatKind(): string
    {
        return 'verkoop';
    }

    public function map(array $row, Context $ctx): ?array
    {
        $parent = $this->parentId($row, $ctx) ?? throw new ImportSkip('Document bij deze regel ontbreekt.');
        $productId = $ctx->id(Product::class, $row['product'] ?? null);
        $isGroup = Gripp::is($row['rowtype'] ?? null, 'groeplabel', 'group', 'groep');
        $isText = Gripp::is($row['rowtype'] ?? null, 'tekst', 'text');
        // Gripp: additionalsubject is de regeltitel; zonder titel geldt de productnaam.
        $title = Gripp::str($row['additionalsubject'] ?? null, 500);
        if ($title === null && ($label = Gripp::label($row['product'] ?? null))) {
            $title = mb_substr(preg_replace('/\s*\(\d+\)$/', '', $label), 0, 500);
        }
        $unitId = $ctx->id(Unit::class, $row['unit'] ?? null);
        if ($unitId === null && is_string($row['unit'] ?? null) && trim($row['unit']) !== '') {
            $unitId = Unit::firstOrCreate(['name' => mb_substr(trim($row['unit']), 0, 50)], ['type' => 'aantal'])->id;
        }

        return [
            $this->parentColumn() => $parent,
            'sort' => (int) ($row['_ordering'] ?? 0),
            'row_type' => $isGroup ? 'groep' : ($isText ? 'tekst' : 'normaal'),
            'product_id' => $productId,
            'title' => $title,
            'description' => Gripp::text($row['description'] ?? null),
            'quantity' => is_numeric($row['amount'] ?? null) ? $row['amount'] : 0,
            'unit_id' => $unitId,
            'unit_price' => Gripp::money($row['sellingprice'] ?? 0, 4),
            'buying_price' => Gripp::money($row['buyingprice'] ?? 0, 4),
            'discount' => is_numeric($row['discount'] ?? null) ? $row['discount'] : 0,
            'vat_rate_id' => $ctx->vatRate($row['vat'] ?? null, $this->vatKind()),
        ];
    }
}
