<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

final class Zoek
{
    /** LIKE-patroon voor een zoekterm, met % en _ ontsnapt. */
    public static function like(string $term): string
    {
        return '%'.addcslashes(trim($term), '%_\\').'%';
    }

    /** Zoek de term in een of meer kolommen (OF). */
    public static function in(Builder $q, ?string $term, array $columns): Builder
    {
        if ($term === null || trim($term) === '') {
            return $q;
        }
        $like = self::like($term);

        return $q->where(function (Builder $w) use ($columns, $like) {
            foreach ($columns as $c) {
                $w->orWhere($c, 'like', $like);
            }
        });
    }
}
