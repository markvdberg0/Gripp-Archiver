<?php

namespace Tests\Feature;

use App\Models\CalendarItem;
use App\Models\Hour;
use App\Models\OfferLine;
use App\Models\Project;
use App\Models\ProjectLine;
use App\Models\Task;
use App\Models\TimelineEntry;
use Tests\TestCase;

class ImportProjectsTest extends TestCase
{
    public function test_opdrachten_regels_taken_agenda_en_uren(): void
    {
        $backup = $this->backup();
        $backup->update('timelineentry', 503, ['project' => ['id' => 901], 'task' => ['id' => 31]]);
        $run = $this->runImport($backup);
        $this->assertSame('geslaagd', $run->status, (string) $run->error);

        $project = Project::where('gripp_id', 901)->firstOrFail();
        $this->assertSame(890140, $project->number);
        $this->assertSame('Uitvoering', $project->phase->name);
        $this->assertSame('Bakker Installatie B.V.', $project->company->name);
        $this->assertSame(['Anna de Vries', 'Jan Jansen'], $project->employees->pluck('name')->sort()->values()->all());
        $this->assertSame(['materiaalplanning' => 'week 30'], $project->extra_fields);
        $this->assertSame('Industrieweg 5, Zwolle', $project->work_address);

        // offerprojectline 900 hoort bij de opdracht, niet bij een offerte
        $line = ProjectLine::where('gripp_id', 900)->firstOrFail();
        $this->assertSame($project->id, $line->project_id);
        $this->assertNull(OfferLine::where('gripp_id', 900)->first());
        $this->assertSame('1000.00', $line->total_excl);

        $task = Task::where('gripp_id', 31)->firstOrFail();
        $this->assertSame($project->id, $task->project_id);
        $this->assertSame('Anna de Vries', $task->employee->name);
        $this->assertSame('14:30:00', $task->deadline_time);
        $this->assertSame('Montage', $task->type->name);

        $item = CalendarItem::where('gripp_id', 41)->firstOrFail();
        $this->assertSame($task->id, $item->task_id);
        $this->assertSame('09:00:00', $item->start_time);

        $approved = Hour::where('gripp_id', 51)->firstOrFail();
        $this->assertSame('gefiatteerd', $approved->status);
        $this->assertSame($line->id, $approved->project_line_id);
        $this->assertSame('Anna de Vries', $approved->approver->name);
        $this->assertSame('concept', Hour::where('gripp_id', 52)->value('status'));

        $timeline = TimelineEntry::where('gripp_id', 503)->first();
        $this->assertSame($project->id, $timeline->project_id);
        $this->assertSame($task->id, $timeline->task_id);

        $checks = collect($run->summary['checks'])->keyBy('label');
        foreach (['project: som totaal excl. btw', 'projectline: opdrachten waarvan regels optellen tot het totaal', 'hour: totaal aantal uren'] as $label) {
            $this->assertTrue($checks[$label]['ok'], $label);
        }
    }

    public function test_herhaald_zonder_duplicaten(): void
    {
        $backup = $this->backup();
        $this->runImport($backup);
        $second = $this->runImport($backup);
        $this->assertSame(0, $second->totals()['created']);
        $this->assertSame(1, Project::count());
        $this->assertSame(2, Hour::count());
        $this->assertSame(2, Project::first()->employees()->count());
    }
}
