<?php

namespace App\Models;

use App\Models\Concerns\FromGripp;
use Illuminate\Database\Eloquent\Model;

class CostCenter extends Model
{
    use FromGripp;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function label(): string
    {
        return trim(($this->number ? $this->number.' ' : '').$this->name);
    }

    public static function options(): array
    {
        return static::query()->where('active', true)->orderBy('number')->get()->mapWithKeys(fn ($c) => [$c->id => $c->label()])->all();
    }
}
