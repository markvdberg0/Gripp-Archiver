<?php

namespace App\Import\Mappers;

use App\Import\Context;
use App\Import\Gripp;
use App\Import\ImportSkip;
use App\Import\Mapper;
use App\Models\CalendarItem;
use App\Models\Employee;
use App\Models\Task;

class CalendarItemMapper extends Mapper
{
    public function entity(): string
    {
        return 'calendaritem';
    }

    public function model(): string
    {
        return CalendarItem::class;
    }

    public function map(array $row, Context $ctx): ?array
    {
        $date = Gripp::day($row['date'] ?? null) ?? throw new ImportSkip('Agenda-item zonder datum.');
        $time = Gripp::date($row['time'] ?? null);

        return [
            'employee_id' => $ctx->id(Employee::class, $row['calendaritememployee'] ?? null),
            'task_id' => $ctx->id(Task::class, $row['task'] ?? null),
            'date' => $date,
            'start_time' => $time && $time->format('H:i') !== '00:00' ? $time->format('H:i:s') : null,
            'hours' => is_numeric($row['hours'] ?? null) ? $row['hours'] : 0,
            'subject' => Gripp::str($row['subject'] ?? null),
            'completed_at' => Gripp::date($row['completedon'] ?? null),
        ];
    }
}
