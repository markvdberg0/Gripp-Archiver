<?php

namespace App\Models;

use App\Models\Concerns\FromGripp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequestLine extends Model
{
    use FromGripp;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class, 'leave_request_id');
    }
}
