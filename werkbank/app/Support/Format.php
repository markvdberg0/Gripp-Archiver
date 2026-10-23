<?php

namespace App\Support;

use DateTimeInterface;

/** Nederlandse weergave van bedragen, getallen en datums. */
final class Format
{
    public static function euro(mixed $amount, bool $sign = true): string
    {
        if ($amount === null || $amount === '') {
            return '';
        }

        return ($sign ? '€ ' : '').number_format((float) $amount, 2, ',', '.');
    }

    public static function number(mixed $n, int $decimals = 2): string
    {
        if ($n === null || $n === '') {
            return '';
        }
        $s = number_format((float) $n, $decimals, ',', '.');

        return $decimals > 0 ? rtrim(rtrim($s, '0'), ',') : $s;
    }

    public static function date(?DateTimeInterface $d): string
    {
        return $d ? $d->format('d-m-Y') : '';
    }

    public static function dateTime(?DateTimeInterface $d): string
    {
        return $d ? $d->format('d-m-Y H:i') : '';
    }
}
