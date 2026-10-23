<?php

namespace App\Support;

use App\Models\DocumentLine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Regels uit een formulier valideren en opslaan bij een document (offerte, opdracht, factuur, …).
 * Invoer: lines[n][id|row_type|product_id|title|description|quantity|unit_id|unit_price|buying_price|discount|vat_rate_id|…].
 * Regels die niet meer in de invoer staan worden verwijderd; de volgorde van de invoer wordt de sortering.
 */
final class Regels
{
    private const NUMERIC = ['quantity', 'unit_price', 'buying_price', 'discount'];

    /** Komma-getallen omzetten vóór de validatie. */
    public static function prepare(Request $request): void
    {
        $lines = [];
        foreach ((array) $request->input('lines', []) as $line) {
            if (! is_array($line)) {
                continue;
            }
            foreach (self::NUMERIC as $f) {
                if (array_key_exists($f, $line)) {
                    $line[$f] = Bedragen::parse($line[$f]);
                }
            }
            // Volledig lege regels negeren.
            if (($line['title'] ?? '') === '' && ($line['description'] ?? '') === '' && empty($line['product_id']) && empty($line['id'])) {
                continue;
            }
            $lines[] = $line;
        }
        $request->merge(['lines' => $lines]);
    }

    public static function rules(array $extra = []): array
    {
        return array_merge([
            'lines' => ['array', 'max:1000'],
            'lines.*.id' => ['nullable', 'integer'],
            'lines.*.row_type' => ['required', Rule::in(array_keys(DocumentLine::ROW_TYPES))],
            'lines.*.product_id' => ['nullable', 'exists:products,id'],
            'lines.*.title' => ['nullable', 'string', 'max:500'],
            'lines.*.description' => ['nullable', 'string', 'max:5000'],
            'lines.*.quantity' => ['nullable', 'numeric', 'between:-999999999,999999999'],
            'lines.*.unit_id' => ['nullable', 'exists:units,id'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'between:-999999999,999999999'],
            'lines.*.buying_price' => ['nullable', 'numeric', 'between:-999999999,999999999'],
            'lines.*.discount' => ['nullable', 'numeric', 'between:0,100'],
            'lines.*.vat_rate_id' => ['nullable', 'required_if:lines.*.row_type,normaal', 'exists:vat_rates,id'],
        ], $extra);
    }

    public static function attributes(): array
    {
        return [
            'lines.*.title' => 'omschrijving', 'lines.*.quantity' => 'aantal', 'lines.*.unit_price' => 'prijs',
            'lines.*.discount' => 'korting', 'lines.*.vat_rate_id' => 'btw-tarief', 'lines.*.row_type' => 'soort regel',
        ];
    }

    /**
     * @param  Model  $document  model met lines()-relatie
     * @param  list<string>  $extraFields  extra kolommen die uit de invoer worden overgenomen (bv. ledger_id)
     */
    public static function sync(Model $document, array $input, array $extraFields = []): void
    {
        $relation = $document->lines();
        $existing = $relation->get()->keyBy('id');
        $keep = [];
        foreach (array_values($input) as $i => $row) {
            $line = isset($row['id']) && $existing->has((int) $row['id']) ? $existing[(int) $row['id']] : $relation->make();
            $type = $row['row_type'] ?? 'normaal';
            $attrs = [
                'sort' => $i,
                'row_type' => $type,
                'product_id' => $row['product_id'] ?? null,
                'title' => $row['title'] ?? null,
                'description' => $row['description'] ?? null,
                'quantity' => $type === 'normaal' ? ($row['quantity'] ?? 1) : ($row['quantity'] ?? 0),
                'unit_id' => $row['unit_id'] ?? null,
                'unit_price' => $row['unit_price'] ?? 0,
                'discount' => $row['discount'] ?? 0,
                'vat_rate_id' => $row['vat_rate_id'] ?? null,
            ];
            if (array_key_exists('buying_price', $row)) {
                $attrs['buying_price'] = $row['buying_price'] ?? 0;
            }
            foreach ($extraFields as $f) {
                $attrs[$f] = $row[$f] ?? null;
            }
            $line->fill($attrs)->save();
            $keep[] = $line->id;
        }
        $relation->whereNotIn('id', $keep ?: [0])->delete();
        $document->unsetRelation('lines');
    }

    /** Regels kopiëren naar een ander document (bijvoorbeeld offerte → opdracht of opdracht → factuur). */
    public static function copy(Model $from, Model $to, array $extra = []): void
    {
        foreach ($from->lines()->get() as $line) {
            $to->lines()->create(array_merge($line->only(['sort', 'row_type', 'product_id', 'title', 'description', 'quantity', 'unit_id', 'unit_price', 'buying_price', 'discount', 'vat_rate_id']), $extra));
        }
        $to->unsetRelation('lines');
    }
}
