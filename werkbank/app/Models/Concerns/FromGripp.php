<?php

namespace App\Models\Concerns;

/**
 * Records die uit een Gripp-back-up kunnen komen. De importer zet gripp_synced_at en updated_at op
 * hetzelfde moment; is updated_at later, dan is het record daarna in Werkbank gewijzigd.
 */
trait FromGripp
{
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
