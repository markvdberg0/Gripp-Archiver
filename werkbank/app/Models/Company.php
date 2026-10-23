<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\FromGripp;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasTags;
use App\Support\Zoek;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use Auditable, FromGripp, HasAttachments, HasFactory, HasTags, SoftDeletes;

    public const ROLLEN = ['is_customer' => 'Klant', 'is_supplier' => 'Leverancier', 'is_prospect' => 'Prospect', 'is_lead' => 'Lead'];

    public const BETAALWIJZEN = ['overboeking' => 'Overboeking', 'incasso' => 'Automatische incasso', 'online' => 'Online betaling'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_customer' => 'boolean', 'is_supplier' => 'boolean', 'is_prospect' => 'boolean', 'is_lead' => 'boolean',
            'vat_shifted' => 'boolean', 'active' => 'boolean', 'founded_on' => 'date',
        ];
    }

    public function accountManager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'account_manager_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class)->orderBy('last_name');
    }

    public function timeline(): HasMany
    {
        return $this->hasMany(TimelineEntry::class)->latest('occurred_at');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function roleLabels(): array
    {
        return array_values(array_filter(self::ROLLEN, fn ($label, $field) => $this->{$field}, ARRAY_FILTER_USE_BOTH));
    }

    public function visitAddress(): string
    {
        return self::address($this->visit_street, $this->visit_house_number, $this->visit_postcode, $this->visit_city);
    }

    public static function address(?string $street, ?string $nr, ?string $pc, ?string $city): string
    {
        $line1 = trim("$street $nr");
        $line2 = trim("$pc $city");

        return trim($line1.($line1 && $line2 ? ', ' : '').$line2);
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        return Zoek::in($q, $term, ['name', 'legal_name', 'email', 'visit_city', 'number']);
    }

    public static function options(?string $role = null): array
    {
        return static::query()->when($role, fn ($q) => $q->where($role, true))->orderBy('name')->pluck('name', 'id')->all();
    }
}
