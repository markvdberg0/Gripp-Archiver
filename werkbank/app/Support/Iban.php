<?php

namespace App\Support;

/** IBAN controleren (ISO 13616: lengte per land en mod-97). */
final class Iban
{
    /** Lengtes van SEPA-landen. */
    private const LENGTHS = [
        'AD' => 24, 'AT' => 20, 'BE' => 16, 'BG' => 22, 'CH' => 21, 'CY' => 28, 'CZ' => 24, 'DE' => 22, 'DK' => 18, 'EE' => 20,
        'ES' => 24, 'FI' => 18, 'FR' => 27, 'GB' => 22, 'GI' => 23, 'GR' => 27, 'HR' => 21, 'HU' => 28, 'IE' => 22, 'IS' => 26,
        'IT' => 27, 'LI' => 21, 'LT' => 20, 'LU' => 20, 'LV' => 21, 'MC' => 27, 'MT' => 31, 'NL' => 18, 'NO' => 15, 'PL' => 28,
        'PT' => 25, 'RO' => 24, 'SE' => 24, 'SI' => 19, 'SK' => 24, 'SM' => 27, 'VA' => 22,
    ];

    public static function normalize(string $iban): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $iban));
    }

    public static function valid(string $iban): bool
    {
        $iban = self::normalize($iban);
        $country = substr($iban, 0, 2);
        if (! preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]+$/', $iban) || (self::LENGTHS[$country] ?? null) !== strlen($iban)) {
            return false;
        }
        $digits = '';
        foreach (str_split(substr($iban, 4).substr($iban, 0, 4)) as $ch) {
            $digits .= ctype_alpha($ch) ? (string) (ord($ch) - 55) : $ch;
        }
        $rest = 0;
        foreach (str_split($digits, 7) as $chunk) {
            $rest = (int) ($rest.$chunk) % 97;
        }

        return $rest === 1;
    }

    /** NL91 ABNA 0417 1643 00 */
    public static function format(string $iban): string
    {
        return trim(chunk_split(self::normalize($iban), 4, ' '));
    }
}
