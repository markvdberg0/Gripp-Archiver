<?php

namespace App\Support;

/**
 * Berekening van documentregels en -totalen. Dezelfde regel als in de Gripp-data (gecontroleerd op
 * 221 offertes/opdrachten en 71 facturen): regeltotaal = aantal × prijs × (1 − korting%), afgerond
 * op centen; groeps- en tekstregels tellen niet mee. Btw per tarief over de som van de regels.
 */
final class Bedragen
{
    public static function lineTotal(string $rowType, mixed $quantity, mixed $unitPrice, mixed $discount): string
    {
        if ($rowType !== 'normaal') {
            return '0.00';
        }
        $total = (float) $quantity * (float) $unitPrice * (1 - (float) $discount / 100);

        return number_format(round($total, 2), 2, '.', '');
    }

    /**
     * @param  iterable<object{row_type: string, total_excl: mixed, vat_rate_id: ?int}>  $lines
     * @param  array<int, float>  $percentages  vat_rate_id => percentage
     * @param  list<int>  $reverseCharge  tarieven met verlegde btw: berekend en getoond, niet in het totaal
     * @return array{excl: string, vat: string, incl: string, per_rate: array<int|string, array{base: string, vat: string, percentage: float, reverse_charge: bool}>}
     */
    public static function totals(iterable $lines, array $percentages, array $reverseCharge = []): array
    {
        $base = [];
        foreach ($lines as $l) {
            if ($l->row_type !== 'normaal') {
                continue;
            }
            $key = $l->vat_rate_id ?? 'geen';
            $base[$key] = ($base[$key] ?? 0) + (float) $l->total_excl;
        }
        $excl = 0;
        $vat = 0;
        $perRate = [];
        foreach ($base as $rateId => $sum) {
            $pct = (float) ($percentages[$rateId] ?? 0);
            $v = round($sum * $pct / 100, 2);
            $reversed = in_array($rateId, $reverseCharge, true);
            $excl += $sum;
            $vat += $reversed ? 0 : $v;
            $perRate[$rateId] = ['base' => self::fmt($sum), 'vat' => self::fmt($v), 'percentage' => $pct, 'reverse_charge' => $reversed];
        }

        return ['excl' => self::fmt($excl), 'vat' => self::fmt($vat), 'incl' => self::fmt($excl + $vat), 'per_rate' => $perRate];
    }

    /** "1.234,56", "1234,56", "1234.56" of "-12" → "1234.56". Null bij lege invoer. */
    public static function parse(mixed $input): ?string
    {
        if ($input === null || (is_string($input) && trim($input) === '')) {
            return null;
        }
        if (is_int($input) || is_float($input)) {
            return (string) $input;
        }
        $s = str_replace([' ', '€', "\u{a0}"], '', trim((string) $input));
        if (str_contains($s, ',')) {
            $s = str_replace(['.', ','], ['', '.'], $s);
        }

        return is_numeric($s) ? $s : (string) $input; // ongeldige invoer laten staan voor de validatie
    }

    /** Waarde voor een invoerveld: komma als decimaalteken, geen duizendtallen, geen overbodige nullen. */
    public static function input(mixed $n, int $minDecimals = 2): string
    {
        if ($n === null || $n === '') {
            return '';
        }
        $s = number_format((float) $n, 4, ',', '');
        $s = rtrim($s, '0');
        $dec = strlen(substr(strrchr($s, ','), 1));
        if ($dec < $minDecimals) {
            $s .= str_repeat('0', $minDecimals - $dec);
        }

        return rtrim($s, ',');
    }

    private static function fmt(float $n): string
    {
        return number_format(round($n, 2), 2, '.', '');
    }
}
