<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\FromGripp;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasLines;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Contract: vaste regels die periodiek worden gefactureerd. */
class Contract extends Model
{
    use Auditable, FromGripp, HasAttachments, HasLines, SoftDeletes;

    public const STATUS = ['actief' => 'Actief', 'beeindigd' => 'Beëindigd'];

    public const FREQUENCIES = ['week' => 'Iedere week', '4weken' => 'Iedere 4 weken', 'maand' => 'Iedere maand', 'kwartaal' => 'Ieder kwartaal', 'halfjaar' => 'Ieder halfjaar', 'jaar' => 'Ieder jaar'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date', 'end_date' => 'date', 'next_invoice_date' => 'date', 'auto_renew' => 'boolean',
            'total_excl' => 'decimal:2', 'total_vat' => 'decimal:2', 'total_incl' => 'decimal:2',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ContractLine::class)->orderBy('sort')->orderBy('id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public static function advance(CarbonInterface $date, string $frequency): CarbonInterface
    {
        return match ($frequency) {
            'week' => $date->copy()->addWeek(),
            '4weken' => $date->copy()->addWeeks(4),
            'kwartaal' => $date->copy()->addMonthsNoOverflow(3),
            'halfjaar' => $date->copy()->addMonthsNoOverflow(6),
            'jaar' => $date->copy()->addYearNoOverflow(),
            default => $date->copy()->addMonthNoOverflow(),
        };
    }

    /** Moet er (vandaag of eerder) een factuur worden gemaakt? */
    public function isDue(?CarbonInterface $on = null): bool
    {
        $on ??= today();

        return $this->status === 'actief' && $this->next_invoice_date !== null && $this->next_invoice_date->lte($on)
            && ($this->end_date === null || $this->next_invoice_date->lte($this->end_date) || $this->auto_renew);
    }

    public function auditLabel(): string
    {
        return "Contract {$this->number}";
    }
}
