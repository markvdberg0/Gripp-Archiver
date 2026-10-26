<?php

namespace App\Import\Mappers;

use App\Import\Context;
use App\Import\Gripp;
use App\Import\ImportSkip;
use App\Import\Mapper;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestLine;

/**
 * Verlofdagen. Aanname: velden date, amount (uren) en absencerequest, volgens de Gripp-API; de echte
 * back-up bevatte bij het bouwen geen regels om dit aan te toetsen.
 */
class AbsenceRequestLineMapper extends Mapper
{
    public function entity(): string
    {
        return 'absencerequestline';
    }

    public function model(): string
    {
        return LeaveRequestLine::class;
    }

    public function parentConflict(array $row, Context $ctx): ?string
    {
        return $this->parentState(LeaveRequest::class, $ctx->id(LeaveRequest::class, $row['absencerequest'] ?? null));
    }

    public function map(array $row, Context $ctx): ?array
    {
        $hours = $row['amount'] ?? $row['hours'] ?? null;

        return [
            'leave_request_id' => $ctx->id(LeaveRequest::class, $row['absencerequest'] ?? null) ?? throw new ImportSkip('Verlofaanvraag bij deze dag ontbreekt (of is niet overgenomen).'),
            'date' => Gripp::day($row['date'] ?? null) ?? throw new ImportSkip('Verlofdag zonder datum.'),
            'hours' => is_numeric($hours) ? $hours : 0,
        ];
    }
}
