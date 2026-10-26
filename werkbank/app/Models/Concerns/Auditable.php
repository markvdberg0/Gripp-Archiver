<?php

namespace App\Models\Concerns;

use App\Support\Audit;

/** Legt aanmaken, wijzigen en verwijderen van het model vast in het logboek. */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($m) => Audit::log('aangemaakt', $m, $m->auditLabel(), Audit::snapshot($m)));
        static::updated(function ($m) {
            $diff = Audit::diff($m);
            if ($diff) {
                Audit::log('gewijzigd', $m, $m->auditLabel(), $diff);
            }
        });
        static::deleted(fn ($m) => Audit::log('verwijderd', $m, $m->auditLabel()));
    }

    public function auditLabel(): string
    {
        return class_basename($this).' '.($this->name ?? $this->number ?? '#'.$this->getKey());
    }
}
