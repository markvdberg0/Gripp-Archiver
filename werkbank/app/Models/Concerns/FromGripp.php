<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Records die uit een Gripp-back-up kunnen komen. De importer zet gripp_synced_at en updated_at op
 * hetzelfde moment; is updated_at later, dan is het record daarna in Werkbank gewijzigd.
 * Wordt een geïmporteerd record definitief verwijderd, dan wordt dat vastgelegd (gripp_tombstones),
 * zodat een volgende import het niet terugzet.
 */
trait FromGripp
{
    public static function bootFromGripp(): void
    {
        static::deleted(function ($model) {
            if ($model->gripp_id === null) {
                return;
            }
            // Zacht verwijderen wordt al herkend via deleted_at.
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }
            DB::table('gripp_tombstones')->insertOrIgnore(['model' => $model->getMorphClass(), 'gripp_id' => $model->gripp_id, 'deleted_at' => now()]);
        });
    }

    public function initializeFromGripp(): void
    {
        $this->mergeCasts(['gripp_synced_at' => 'datetime']);
    }

    public function isLocallyModified(): bool
    {
        if ($this->gripp_synced_at === null || $this->updated_at === null) {
            return false;
        }

        return $this->updated_at->greaterThan($this->gripp_synced_at->copy()->addSecond());
    }
}
