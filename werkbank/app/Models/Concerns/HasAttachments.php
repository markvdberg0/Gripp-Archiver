<?php

namespace App\Models\Concerns;

use App\Models\StoredFile;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasAttachments
{
    public function files(): MorphToMany
    {
        return $this->morphToMany(StoredFile::class, 'attachable', 'attachments', 'attachable_id', 'file_id')
            ->withPivot('role', 'created_at');
    }
}
