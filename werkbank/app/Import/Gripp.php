<?php

namespace App\Import;

use Carbon\CarbonImmutable;

/** Hulpfuncties voor de vorm van Gripp-records. */
final class Gripp
{
    /** Verwijzing { id, searchname } of kaal id → id. */
    public static function id(mixed $v): ?int
    {
        if (is_array($v)) {
            $v = $v['id'] ?? null;
        }
        if ($v === null || $v === '' || $v === 0 || $v === '0') {
            return null;
        }

        return is_numeric($v) ? (int) $v : null;
    }

    /** @return list<int> */
    public static function ids(mixed $v): array
    {
        if ($v === null || $v === '') {
            return [];
        }
        $list = is_array($v) && array_is_list($v) ? $v : [$v];

        return array_values(array_unique(array_filter(array_map([self::class, 'id'], $list))));
    }

    /** Keuzelijstwaarde: { id, searchname } → searchname, of de kale string. */
    public static function label(mixed $v): ?string
    {
        if (is_array($v)) {
            $v = $v['searchname'] ?? $v['name'] ?? null;
        }
        $v = is_scalar($v) ? trim((string) $v) : null;

        return $v === '' ? null : $v;
    }

    /** Datum { date: 'Y-m-d H:i:s.u', timezone } of string → Carbon. */
    public static function date(mixed $v): ?CarbonImmutable
    {
        if (is_array($v)) {
            $tz = $v['timezone'] ?? 'Europe/Amsterdam';
            $v = $v['date'] ?? null;
        }
        if (! is_string($v) || trim($v) === '') {
            return null;
        }
        try {
            return CarbonImmutable::parse(substr($v, 0, 19), $tz ?? 'Europe/Amsterdam')->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    public static function day(mixed $v): ?string
    {
        return self::date($v)?->toDateString();
    }

    public static function str(mixed $v, int $max = 255): ?string
    {
        if (! is_scalar($v)) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : mb_substr($s, 0, $max);
    }

    public static function text(mixed $v): ?string
    {
        return is_scalar($v) && trim((string) $v) !== '' ? (string) $v : null;
    }

    public static function money(mixed $v, int $decimals = 2): string
    {
        return number_format(is_numeric($v) ? (float) $v : 0.0, $decimals, '.', '');
    }

    public static function bool(mixed $v): bool
    {
        return filter_var($v, FILTER_VALIDATE_BOOLEAN);
    }

    /** Vergelijk een label met kandidaten (hoofdletterongevoelig, NL en EN). */
    public static function is(mixed $v, string ...$candidates): bool
    {
        $l = mb_strtolower((string) self::label($v));

        return $l !== '' && in_array($l, array_map('mb_strtolower', $candidates), true);
    }
}
