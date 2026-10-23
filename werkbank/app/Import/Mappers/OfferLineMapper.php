<?php

namespace App\Import\Mappers;

use App\Import\Context;
use App\Import\Gripp;
use App\Import\LineMapper;
use App\Models\Offer;
use App\Models\OfferLine;

/** Offerteregels: de regels in offerprojectline die bij een offerte horen (niet bij een opdracht). */
class OfferLineMapper extends LineMapper
{
    public function entity(): string
    {
        return 'offerprojectline';
    }

    public function key(): string
    {
        return 'offerline';
    }

    public function model(): string
    {
        return OfferLine::class;
    }

    public function accepts(array $row, Context $ctx): bool
    {
        $base = $row['offerprojectbase'] ?? null;
        $discr = is_array($base) ? ($base['discr'] ?? null) : null;

        return $discr !== null ? $discr === 'offerte' : $ctx->id(Offer::class, $base) !== null;
    }

    protected function parentColumn(): string
    {
        return 'offer_id';
    }

    protected function parentId(array $row, Context $ctx): ?int
    {
        return $ctx->id(Offer::class, $row['offerprojectbase'] ?? null);
    }

    /** Herberekende totalen uit de regels tegenover de door Gripp opgegeven totalen. */
    public function checks(Context $ctx): array
    {
        $offers = Offer::withTrashed()->whereNotNull('gripp_id')->with('lines')->get();
        $ok = $offers->filter(fn (Offer $o) => abs((float) $o->computeTotals()['excl'] - (float) $o->total_excl) < 0.02)->count();

        return array_merge(parent::checks($ctx), [[
            'label' => 'offerline: offertes waarvan regels optellen tot het totaal',
            'source' => $offers->count(),
            'local' => $ok,
            'ok' => $ok === $offers->count(),
        ]]);
    }
}
