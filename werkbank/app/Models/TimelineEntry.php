<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\FromGripp;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Builder;
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

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Recht dat nodig is om dit item (en de bijlagen) te zien: items bij facturen of inkoop zijn financieel. */
    public function requiredAbility(): string
    {
        return match (true) {
            $this->purchase_invoice_id !== null || $this->purchase_order_id !== null => 'inkoop.bekijken',
            $this->invoice_id !== null || $this->contract_id !== null => 'facturen.bekijken',
            default => 'relaties.bekijken',
        };
    }

    /** Alleen items die deze gebruiker mag zien. */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if (! $user->can('inkoop.bekijken')) {
            $q->whereNull('purchase_invoice_id')->whereNull('purchase_order_id');
        }
        if (! $user->can('facturen.bekijken')) {
            $q->whereNull('invoice_id')->whereNull('contract_id');
        }

        return $q;
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
