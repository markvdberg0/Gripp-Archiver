<?php

namespace App\Import\Mappers;

use App\Import\Context;
use App\Import\Gripp;
use App\Import\Mapper;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Employee;
use App\Models\Offer;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimelineEntry;
use Illuminate\Database\Eloquent\Model;

/**
 * Timeline (notities, e-mails, systeemberichten). Koppelingen aan documenten (offertes, opdrachten,
 * facturen, …) komen erbij in de fase waarin die documenten bestaan; zie documentColumns().
 */
class TimelineMapper extends Mapper
{
    public const VERSION = 3;

    public function entity(): string
    {
        return 'timelineentry';
    }

    public function model(): string
    {
        return TimelineEntry::class;
    }

    /** Gripp-veld => [kolom, model]. Wordt per fase uitgebreid. */
    protected function documentColumns(): array
    {
        return [
            'offer' => ['offer_id', Offer::class],
            'project' => ['project_id', Project::class],
            'task' => ['task_id', Task::class],
        ];
    }

    public function map(array $row, Context $ctx): ?array
    {
        $type = $row['timelinetype'] ?? null;
        $attrs = [
            'type' => match (true) {
                Gripp::is($row['appointmenttype'] ?? null, 'bellen', 'telefoon') => 'telefoon',
                Gripp::is($type, 'receivedemail', 'ontvangen e-mail') => 'email_ontvangen',
                Gripp::is($type, 'sentemail', 'verzonden e-mail') => 'email_verzonden',
                Gripp::is($type, 'systemmessage', 'systeembericht') => 'systeem',
                default => 'notitie',
            },
            'subject' => Gripp::str($row['subject'] ?? null),
            'body' => Gripp::text($row['message'] ?? null),
            'occurred_at' => Gripp::date($row['date'] ?? null) ?? Gripp::date($row['createdon'] ?? null) ?? now(),
            'starred' => Gripp::bool($row['starred'] ?? false),
            'company_id' => $ctx->id(Company::class, $row['company'] ?? null),
            'contact_id' => $ctx->id(Contact::class, $row['contact'] ?? null),
            'employee_id' => $ctx->id(Employee::class, $row['employee'] ?? null),
        ];
        foreach ($this->documentColumns() as $field => [$column, $model]) {
            $attrs[$column] = $ctx->id($model, $row[$field] ?? null);
        }

        return $attrs;
    }

    public function after(Model $model, array $row, Context $ctx): void
    {
        $this->attachFiles($model, $row, $ctx);
    }
}
