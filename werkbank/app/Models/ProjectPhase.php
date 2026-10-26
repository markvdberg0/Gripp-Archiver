<?php

namespace App\Models;

use App\Models\Concerns\FromGripp;
use Illuminate\Database\Eloquent\Model;

class ProjectPhase extends Model
{
    use FromGripp;

    protected $guarded = ['id'];

    public static function options(): array
    {
        return static::query()->orderBy('sort')->orderBy('id')->pluck('name', 'id')->all();
    }
}
