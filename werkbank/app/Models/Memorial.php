<?php

namespace App\Models;

use App\Models\Concerns\FromGripp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Memoriaalboeking uit Gripp (alleen-lezen archief). */
class Memorial extends Model
{
    use FromGripp;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(MemorialLine::class);
    }
}
