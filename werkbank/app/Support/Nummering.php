<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * Doorlopende nummers voor documenten. Het volgende nummer is het hoogste van: de instelling
 * "nummer.<soort>" en het hoogste bestaande nummer + 1. Zo sluit de nummering na een import vanzelf aan.
 * Moet binnen een transactie worden aangeroepen; de instellingsrij wordt vergrendeld.
 */
final class Nummering
{
    public static function next(string $soort, string $table, string $column = 'number'): int
    {
        $key = "nummer.$soort";
        Setting::query()->firstOrCreate(['key' => $key], ['value' => '']);
        $row = Setting::query()->whereKey($key)->lockForUpdate()->first();
        $max = (int) DB::table($table)->max($column);
        $next = max((int) $row->value, $max + 1, 1);
        $row->update(['value' => (string) ($next + 1)]);
        Setting::flush();

        return $next;
    }

    /** Het nummer dat de volgende keer zou worden uitgegeven (zonder het te reserveren). */
    public static function peek(string $soort, string $table, string $column = 'number'): int
    {
        return max((int) Setting::get("nummer.$soort", '0'), (int) DB::table($table)->max($column) + 1, 1);
    }
}
