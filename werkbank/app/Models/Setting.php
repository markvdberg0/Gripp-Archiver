<?php

namespace App\Models;

use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Instellingen in de database (bedrijfsgegevens, nummering, standaardteksten). */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public const DEFAULTS = [
        'bedrijf.naam' => '', 'bedrijf.adres' => '', 'bedrijf.postcode' => '', 'bedrijf.plaats' => '', 'bedrijf.land' => 'Nederland',
        'bedrijf.telefoon' => '', 'bedrijf.email' => '', 'bedrijf.website' => '', 'bedrijf.kvk' => '', 'bedrijf.btw' => '',
        'bedrijf.iban' => '', 'bedrijf.bic' => '',
        'offerte.geldigheid_dagen' => '30', 'offerte.voettekst' => '', 'factuur.betaaltermijn' => '30', 'factuur.voettekst' => '',
        'nummer.offerte' => '', 'nummer.opdracht' => '', 'nummer.factuur' => '', 'nummer.inkoopopdracht' => '',
    ];

    public static function get(string $key, ?string $default = null): ?string
    {
        $all = Cache::rememberForever('werkbank.settings', fn () => static::query()->pluck('value', 'key')->all());

        return ($all[$key] ?? null) !== null && $all[$key] !== '' ? $all[$key] : ($default ?? (self::DEFAULTS[$key] ?? null));
    }

    public static function put(array $values): void
    {
        $changes = [];
        foreach ($values as $key => $value) {
            $old = static::query()->find($key)?->value;
            if ((string) $old !== (string) $value) {
                $changes[$key] = [$old, $value];
                static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
            }
        }
        Cache::forget('werkbank.settings');
        if ($changes) {
            Audit::log('instellingen_gewijzigd', null, 'Instellingen', $changes);
        }
    }

    public static function flush(): void
    {
        Cache::forget('werkbank.settings');
    }
}
