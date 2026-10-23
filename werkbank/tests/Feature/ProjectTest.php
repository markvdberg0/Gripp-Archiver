<?php

namespace Tests\Feature;

use App\Models\CalendarItem;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Hour;
use App\Models\Offer;
use App\Models\Project;
use App\Models\ProjectLine;
use App\Models\Task;
use App\Models\User;
use App\Models\VatRate;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    private VatRate $vat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vat = VatRate::create(['name' => 'Btw 21%', 'percentage' => 21, 'kind' => 'beide']);
    }

    private function acceptedOffer(): Offer
    {
        $offer = Offer::factory()->create(['status' => 'verzonden', 'acceptance' => 'geaccepteerd', 'name' => 'Station Deventer', 'client_reference' => 'PO-7']);
        $offer->lines()->create(['sort' => 0, 'row_type' => 'normaal', 'title' => 'Montage', 'quantity' => 10, 'unit_price' => 80, 'vat_rate_id' => $this->vat->id]);
        $offer->recalculate();

        return $offer;
    }

    public function test_opdracht_uit_geaccepteerde_offerte(): void
    {
        $this->actingAsRole('projectleider');
        $offer = $this->acceptedOffer();

        $this->post("/offertes/{$offer->id}/opdracht")->assertRedirect();

        $project = Project::firstOrFail();
        $this->assertSame($offer->id, $project->offer_id);
        $this->assertSame('Station Deventer', $project->name);
        $this->assertSame($offer->company_id, $project->company_id);
        $this->assertSame('PO-7', $project->client_reference);
        $this->assertSame('800.00', $project->total_excl);
        $this->assertSame(['Montage'], $project->lines->pluck('title')->all());
        $this->assertSame(1, $project->timeline()->count());

        // tweede keer: geen tweede opdracht
        $this->post("/offertes/{$offer->id}/opdracht")->assertRedirect(route('opdrachten.show', $project));
        $this->assertSame(1, Project::count());
        $this->get("/offertes/{$offer->id}")->assertSee((string) $project->number);
    }

    public function test_geen_opdracht_van_open_offerte_en_niet_heropenen_na_opdracht(): void
    {
        $this->actingAsRole('projectleider');
        $open = Offer::factory()->create(['status' => 'verzonden']);
        $this->post("/offertes/{$open->id}/opdracht")->assertStatus(422);

        $offer = $this->acceptedOffer();
        $this->post("/offertes/{$offer->id}/opdracht");
        $this->post("/offertes/{$offer->id}/status/heropenen")->assertStatus(422);
    }

    public function test_opdracht_handmatig_met_medewerkers_en_regels(): void
    {
        $this->actingAsRole('projectleider');
        $company = Company::factory()->create();
        [$a, $b] = Employee::factory()->count(2)->create();

        $this->post('/opdrachten', [
            'name' => 'Onderhoud', 'company_id' => $company->id, 'employees' => [$a->id, $b->id], 'start_date' => '2026-10-01', 'end_date' => '2026-09-01',
            'lines' => [['row_type' => 'normaal', 'title' => 'Uur', 'quantity' => '4', 'unit_price' => '95', 'vat_rate_id' => $this->vat->id]],
        ])->assertSessionHasErrors('end_date');

        $this->post('/opdrachten', [
            'name' => 'Onderhoud', 'company_id' => $company->id, 'employees' => [$a->id, $b->id], 'start_date' => '2026-10-01',
            'lines' => [['row_type' => 'normaal', 'title' => 'Uur', 'quantity' => '4', 'unit_price' => '95', 'vat_rate_id' => $this->vat->id]],
        ])->assertRedirect();
        $project = Project::firstOrFail();
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $project->employees->pluck('id')->all());
        $this->assertSame('380.00', $project->total_excl);
        $this->get("/opdrachten/{$project->id}")->assertOk()->assertSee('Onderhoud');
        $pdf = $this->get("/opdrachten/{$project->id}/pdf")->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_opdracht_met_uren_niet_verwijderen_wel_archiveren(): void
    {
        $this->actingAsRole('projectleider');
        $project = Project::factory()->create();
        Hour::create(['date' => now(), 'employee_id' => Employee::factory()->create()->id, 'project_id' => $project->id, 'hours' => 2]);

        $this->delete("/opdrachten/{$project->id}")->assertSessionHasErrors('opdracht');
        $this->post("/opdrachten/{$project->id}/archiveren")->assertRedirect();
        $this->assertTrue($project->fresh()->archived);
    }

    public function test_uren_schrijven_indienen_en_fiatteren(): void
    {
        $user = User::factory()->role('medewerker')->create();
        $me = Employee::factory()->create(['user_id' => $user->id]);
        $project = Project::factory()->create();
        $line = $project->lines()->create(['row_type' => 'normaal', 'title' => 'Montage', 'quantity' => 10, 'unit_price' => 80]);
        $this->actingAs($user);

        $this->get('/uren?project_id='.$project->id)->assertOk()->assertSee('Montage');
        $this->post('/uren', ['date' => '2026-10-19', 'project_id' => $project->id, 'project_line_id' => $line->id, 'hours' => '7,5', 'description' => 'Kast gebouwd'])->assertRedirect();
        $this->post('/uren', ['date' => '2026-10-19', 'project_id' => $project->id, 'hours' => '25'])->assertSessionHasErrors('hours');
        $other = ProjectLine::create(['project_id' => Project::factory()->create()->id, 'title' => 'x']);
        $this->post('/uren', ['date' => '2026-10-19', 'project_id' => $project->id, 'project_line_id' => $other->id, 'hours' => '1'])->assertSessionHasErrors('project_line_id');

        $hour = Hour::firstOrFail();
        $this->assertSame('7.50', $hour->hours);
        $this->assertSame($me->id, $hour->employee_id);
        $this->assertSame('concept', $hour->status);

        $this->post('/uren/indienen', ['week' => '2026-10-19'])->assertRedirect();
        $this->assertSame('definitief', $hour->fresh()->status);
        $this->delete("/uren/{$hour->id}")->assertStatus(422); // niet meer te verwijderen
        $this->get('/uren/fiatteren')->assertForbidden();

        $leader = User::factory()->role('projectleider')->create();
        $leaderEmployee = Employee::factory()->create(['user_id' => $leader->id]);
        $this->actingAs($leader);
        $this->get('/uren/fiatteren')->assertOk()->assertSee('Kast gebouwd');
        $this->post('/uren/fiatteren', ['ids' => [$hour->id], 'actie' => 'fiatteren'])->assertRedirect();
        $hour->refresh();
        $this->assertSame('gefiatteerd', $hour->status);
        $this->assertSame($leaderEmployee->id, $hour->approved_by);
        $this->get("/opdrachten/{$project->id}")->assertSee('Geschreven: <strong>7,5</strong>', false);
    }

    public function test_uren_van_een_ander_niet_aanpassen(): void
    {
        $user = User::factory()->role('medewerker')->create();
        Employee::factory()->create(['user_id' => $user->id]);
        $hour = Hour::create(['date' => now(), 'employee_id' => Employee::factory()->create()->id, 'project_id' => Project::factory()->create()->id, 'hours' => 2]);
        $this->actingAs($user);
        $this->delete("/uren/{$hour->id}")->assertForbidden();
    }

    public function test_zonder_gekoppelde_medewerker_een_melding(): void
    {
        $this->actingAsRole('medewerker');
        $this->get('/uren')->assertOk()->assertSee('niet gekoppeld aan een medewerker');
    }

    public function test_taken_afronden_en_inplannen(): void
    {
        $this->actingAsRole('medewerker');
        $project = Project::factory()->create();
        $employee = Employee::factory()->create();

        $this->post('/taken', ['title' => 'Kabel afroepen', 'project_id' => $project->id, 'employee_id' => $employee->id, 'deadline' => '2020-01-01'])->assertRedirect();
        $task = Task::firstOrFail();
        $this->assertSame($project->company_id, $task->company_id);
        $this->assertTrue($task->isOverdue());
        $this->get('/taken?wie=iedereen')->assertSee('te laat');

        $this->post("/taken/{$task->id}/inplannen", ['employee_id' => $employee->id, 'date' => '2026-10-20', 'start_time' => '09:00', 'hours' => '2'])->assertRedirect();
        $this->assertSame(1, CalendarItem::where('task_id', $task->id)->count());
        $this->get('/planning?week=2026-10-20')->assertOk()->assertSee('Kabel afroepen');

        $this->post("/taken/{$task->id}/afronden");
        $this->assertTrue($task->fresh()->completed);
        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_alleen_lezen_kan_geen_uren_schrijven(): void
    {
        $this->actingAsRole('alleen_lezen');
        $this->get('/uren')->assertForbidden();
        $this->get('/opdrachten')->assertOk();
        $this->get('/opdrachten/create')->assertForbidden();
    }
}
