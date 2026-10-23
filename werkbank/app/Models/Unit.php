<?php

namespace App\Models;

use App\Models\Concerns\FromGripp;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use FromGripp;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['hours_per_unit' => 'decimal:2'];
    }

    public const TYPES = ['aantal' => 'Aantallen', 'uren' => 'Uren'];

    public static function options(): array
    {
        return static::query()->orderBy('name')->pluck('name', 'id')->all();
    }
}
