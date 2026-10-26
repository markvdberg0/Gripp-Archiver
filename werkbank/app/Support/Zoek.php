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

    /**
     * Zoek de term in een of meer kolommen (OF), hoofdletterongevoelig. SQLite en MariaDB/MySQL zijn dat met
     * LIKE al; PostgreSQL niet, daar wordt ILIKE gebruikt (met een cast, zodat ook nummerkolommen werken).
     */
    public static function in(Builder $q, ?string $term, array $columns): Builder
    {
        if ($term === null || trim($term) === '') {
            return $q;
        }
        $like = self::like($term);
        $pgsql = $q->getConnection()->getDriverName() === 'pgsql';

        return $q->where(function (Builder $w) use ($columns, $like, $pgsql) {
            foreach ($columns as $c) {
                $pgsql
                    ? $w->orWhereRaw($w->getQuery()->getGrammar()->wrap($c).'::text ILIKE ?', [$like])
                    : $w->orWhere($c, 'like', $like);
            }
        });
    }
}
