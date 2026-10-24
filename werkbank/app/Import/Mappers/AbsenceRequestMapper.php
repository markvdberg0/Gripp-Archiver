<?php

namespace App\Import\Mappers;

use App\Import\Context;
use App\Import\Gripp;
use App\Import\ImportSkip;
use App\Import\Mapper;
use App\Models\Employee;
use App\Models\LeaveRequest;

/**
 * Verlofaanvragen. Gripp legt de status per dag (regel) vast; de aanvraag krijgt de status van de regels.
 * Zonder regels is de status onbekend: dan wordt er niets verzonnen. Ziekmeldingen worden niet overgenomen
 * (gezondheidsgegevens).
 */
class AbsenceRequestMapper extends Mapper
{
    /** @var array<int, list<string>>|null aanvraag-id → statussen van de regels */
    private ?array $lineStatuses = null;

    public function entity(): string
    {
        return 'absencerequest';
    }

    public function model(): string
    {
        return LeaveRequest::class;
    }

    public function map(array $row, Context $ctx): ?array
    {
        $kind = mb_strtolower((string) Gripp::label($row['absencetype'] ?? null));
        if (preg_match('/ziek|verzuim/', $kind)) {
            throw new ImportSkip('Ziekmelding niet overgenomen (gezondheidsgegevens).');
        }

        return [
            'employee_id' => $ctx->id(Employee::class, $row['employee'] ?? null) ?? throw new ImportSkip('Verlof zonder (bekende) medewerker.'),
            'type' => match (true) {
                str_contains($kind, 'bijzonder') => 'bijzonder',
                str_contains($kind, 'onbetaald') => 'onbetaald',
                $kind === '' || (bool) preg_match('/vakantie|vrij|verlof|atv|adv/', $kind) => 'vakantie',
                default => 'overig',
            },
            'description' => Gripp::str($row['description'] ?? null) ?? Gripp::label($row['absencetype'] ?? null),
            'comment' => Gripp::text($row['comment'] ?? null),
            'status' => $this->status((int) $row['id'], $ctx),
        ];
    }

    public function hashExtra(array $row, Context $ctx): string
    {
        return $this->status((int) $row['id'], $ctx);
    }

    private function status(int $id, Context $ctx): string
    {
        if ($this->lineStatuses === null) {
            $this->lineStatuses = [];
            foreach ($ctx->snapshot->rows('absencerequestline') as $line) {
                if ($parent = Gripp::id($line['absencerequest'] ?? null)) {
                    $this->lineStatuses[$parent][] = self::lineStatus($line['absencerequeststatus'] ?? null);
                }
            }
        }
        $statuses = array_unique($this->lineStatuses[$id] ?? []);

        return match (true) {
            $statuses === [] => 'onbekend',
            count($statuses) === 1 => reset($statuses),
            default => 'aangevraagd', // gemengd: nog niet volledig beslist
        };
    }

    public static function lineStatus(mixed $status): string
    {
        return match (true) {
            Gripp::is($status, 'goedgekeurd', 'approved', 'akkoord', 'geaccordeerd') => 'goedgekeurd',
            Gripp::is($status, 'afgekeurd', 'afgewezen', 'rejected', 'declined') => 'afgewezen',
            Gripp::is($status, 'aangevraagd', 'in behandeling', 'requested', 'pending', 'open') => 'aangevraagd',
            default => 'onbekend',
        };
    }
}
