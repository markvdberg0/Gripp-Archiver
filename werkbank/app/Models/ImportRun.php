<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRun extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['dry_run' => 'boolean', 'summary' => 'array', 'issues' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Totalen over alle entiteiten: created, updated, unchanged, conflicts, skipped, errors. */
    public function totals(): array
    {
        $t = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'conflicts' => 0, 'skipped' => 0, 'errors' => 0];
        foreach ($this->summary['entities'] ?? [] as $e) {
            foreach ($t as $k => $v) {
                $t[$k] += $e[$k] ?? 0;
            }
        }

        return $t;
    }
}
