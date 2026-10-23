<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\FromGripp;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimelineEntry extends Model
{
    use Auditable, FromGripp, HasAttachments;

    public const TYPES = [
        'notitie' => 'Notitie', 'telefoon' => 'Telefoongesprek', 'email_ontvangen' => 'E-mail ontvangen',
        'email_verzonden' => 'E-mail verzonden', 'systeem' => 'Systeembericht',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'starred' => 'boolean'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function authorName(): string
    {
        return $this->user?->name ?? $this->employee?->name ?? 'Systeem';
    }

    public function auditLabel(): string
    {
        return 'Timeline '.($this->subject ?: '#'.$this->id);
    }
}
