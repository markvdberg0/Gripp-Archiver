<?php

namespace App\Models;

use App\Models\Concerns\FromGripp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class VatRate extends Model
{
    use FromGripp;

    public const KINDS = ['verkoop' => 'Verkoop', 'inkoop' => 'Inkoop', 'beide' => 'Verkoop en inkoop'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['percentage' => 'decimal:2', 'active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('werkbank.vat'));
        static::deleted(fn () => Cache::forget('werkbank.vat'));
    }

    /** @return array<int, float> id => percentage */
    public static function percentages(): array
    {
        return Cache::rememberForever('werkbank.vat', fn () => static::query()->pluck('percentage', 'id')->map(fn ($p) => (float) $p)->all());
    }

    /** Opties voor een keuzelijst, met percentage als data-attribuut (voor de live berekening). */
    public static function options(string $kind = 'verkoop'): array
    {
        return static::query()->where('active', true)->whereIn('kind', [$kind, 'beide'])->orderByDesc('percentage')->orderBy('name')->get()->all();
    }
}
