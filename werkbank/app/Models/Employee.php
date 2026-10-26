<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\FromGripp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use Auditable, FromGripp, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'employed_since' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getNameAttribute(): string
    {
        return trim(implode(' ', array_filter([$this->first_name, $this->infix, $this->last_name])));
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /** Verlofsaldo in uren voor een jaar: recht, goedgekeurd (telt mee), aangevraagd en wat overblijft. */
    public function leaveBalance(int $year): array
    {
        $allowance = (float) ($this->leave_hours_per_year ?? Setting::get('verlof.uren_per_jaar', '160'));
        $counting = array_keys(array_filter(LeaveRequest::TYPES, fn ($t) => $t[1]));
        $sum = fn (string $status) => (float) LeaveRequestLine::query()
            ->whereHas('request', fn ($q) => $q->where('employee_id', $this->id)->where('status', $status)->whereIn('type', $counting))
            ->whereYear('date', $year)->sum('hours');
        $approved = $sum('goedgekeurd');
        $pending = $sum('aangevraagd');

        return ['recht' => $allowance, 'goedgekeurd' => $approved, 'aangevraagd' => $pending, 'over' => $allowance - $approved];
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('active', true);
    }

    public static function options(): array
    {
        return static::query()->orderBy('first_name')->get()->mapWithKeys(fn ($e) => [$e->id => $e->name.($e->active ? '' : ' (inactief)')])->all();
    }
}
