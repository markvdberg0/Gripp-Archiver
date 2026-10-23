<?php

namespace App\Models\Concerns;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasTags
{
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable')->orderBy('name');
    }

    /** Labels op naam koppelen; onbekende namen worden aangemaakt. */
    public function syncTagNames(array $names): void
    {
        $ids = collect($names)->map(fn ($n) => trim((string) $n))->filter()->unique()
            ->map(fn ($n) => Tag::firstOrCreate(['name' => $n])->id);
        $this->tags()->sync($ids->all());
    }
}
