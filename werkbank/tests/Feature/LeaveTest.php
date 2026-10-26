<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\VerlofBeslissing;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Support\FakeBackup;
use Tests\TestCase;

class LeaveTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-20 09:00'); // dinsdag
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function employeeUser(string $role = 'medewerker'): User
    {
        $user = $this->actingAsRole($role);
        Employee::factory()->create(['user_id' => $user->id]);

        return $user->fresh();
    }

    public function test_aanvragen_slaat_weekend_over_en_weigert_overlap(): void
    {
        $user = $this->employeeUser();
        $this->get('/verlof')->assertOk()->assertSee('160');

        // vrijdag t/m dinsdag: 3 werkdagen
        $this->post('/verlof', ['type' => 'vakantie', 'from' => '2026-11-06', 'to' => '2026-11-10', 'hours_per_day' => '8', 'description' => 'Weekje weg'])
            ->assertRedirect(route('verlof.index'));
        $leave = LeaveRequest::with('lines')->firstOrFail();
        $this->assertSame('aangevraagd', $leave->status);
        $this->assertSame(['2026-11-06', '2026-11-09', '2026-11-10'], $leave->lines->map(fn ($l) => $l->date->toDateString())->all());
        $this->assertSame(['recht' => 160.0, 'goedgekeurd' => 0.0, 'aangevraagd' => 24.0, 'over' => 160.0], $user->employee->leaveBalance(2026));

        $this->post('/verlof', ['type' => 'vakantie', 'from' => '2026-11-10', 'to' => '2026-11-10', 'hours_per_day' => '4'])->assertSessionHasErrors('from');
        $this->post('/verlof', ['type' => 'vakantie', 'from' => '2026-11-07', 'to' => '2026-11-08', 'hours_per_day' => '8'])->assertSessionHasErrors('from'); // alleen weekend
        $this->post('/verlof', ['type' => 'vakantie', 'from' => '2026-11-12', 'to' => '2026-11-11', 'hours_per_day' => '8'])->assertSessionHasErrors('to');
        $this->assertSame(1, LeaveRequest::count());

        $this->post(route('verlof.intrekken', $leave))->assertRedirect();
        $this->assertSame('ingetrokken', $leave->fresh()->status);
        // na intrekken is de dag weer vrij
        $this->post('/verlof', ['type' => 'vakantie', 'from' => '2026-11-10', 'to' => '2026-11-10', 'hours_per_day' => '4'])->assertRedirect();
    }

    public function test_goedkeuren_afwijzen_en_vier_ogen(): void
    {
        Notification::fake();
        $worker = User::factory()->role('medewerker')->create();
        $workerEmployee = Employee::factory()->create(['user_id' => $worker->id, 'leave_hours_per_year' => 200]);
        $this->actingAs($worker)->post('/verlof', ['type' => 'vakantie', 'from' => '2026-12-21', 'to' => '2026-12-24', 'hours_per_day' => '8']);
        $this->actingAs($worker)->post('/verlof', ['type' => 'bijzonder', 'from' => '2026-11-02', 'to' => '2026-11-02', 'hours_per_day' => '8']);
        [$xmas, $special] = LeaveRequest::orderBy('id')->get()->all();

        $this->get('/verlof/goedkeuren')->assertForbidden();
        $this->post(route('verlof.beslissen', $xmas), ['actie' => 'goedkeuren'])->assertForbidden();

        $admin = $this->employeeUser('administratie');
        $this->get('/verlof/goedkeuren')->assertOk()->assertSee($workerEmployee->name)->assertSee('168'); // saldo na goedkeuren
        $this->post(route('verlof.beslissen', $special), ['actie' => 'afwijzen'])->assertSessionHasErrors('decision_note');
        $this->post(route('verlof.beslissen', $xmas), ['actie' => 'goedkeuren'])->assertRedirect();
        $this->post(route('verlof.beslissen', $special), ['actie' => 'goedkeuren'])->assertRedirect();
        $this->post(route('verlof.beslissen', $xmas), ['actie' => 'afwijzen', 'decision_note' => 'x'])->assertStatus(422); // al beslist

        $this->assertSame('goedgekeurd', $xmas->fresh()->status);
        $this->assertSame($admin->id, $xmas->fresh()->decided_by);
        $this->assertSame(1, AuditLog::where('event', 'verlof_goedgekeurd')->where('auditable_id', $xmas->id)->count());
        Notification::assertSentTo($worker, VerlofBeslissing::class, 2);
        // bijzonder verlof telt niet mee
        $this->assertSame(['recht' => 200.0, 'goedgekeurd' => 32.0, 'aangevraagd' => 0.0, 'over' => 168.0], $workerEmployee->leaveBalance(2026));

        // eigen aanvraag niet zelf goedkeuren
        $this->post('/verlof', ['type' => 'vakantie', 'from' => '2026-11-16', 'to' => '2026-11-16', 'hours_per_day' => '8']);
        $own = LeaveRequest::latest('id')->first();
        $this->post(route('verlof.beslissen', $own), ['actie' => 'goedkeuren'])->assertForbidden();

        // administratie ziet iedereen, de medewerker alleen zichzelf
        $this->get('/verlof?medewerker=alle')->assertOk()->assertSee($workerEmployee->name);
        $this->actingAs($worker)->get('/verlof?medewerker='.$admin->employee->id)->assertOk()->assertDontSee('16-11-2026');
        $this->actingAs($worker)->post(route('verlof.intrekken', $own))->assertForbidden();
    }

    public function test_planning_toont_verlof_en_standaard_uit_instellingen(): void
    {
        $user = $this->employeeUser('administratie');
        Setting::put(['verlof.uren_per_jaar' => '180']);
        $this->assertSame(180.0, $user->employee->leaveBalance(2026)['recht']);
        $this->post('/verlof', ['type' => 'vakantie', 'from' => '2026-10-21', 'to' => '2026-10-21', 'hours_per_day' => '4']);
        $this->get('/planning?week=2026-10-19')->assertOk()->assertSee('Verlof 4')->assertSee('(aangevraagd)');
        $this->get('/verlof/goedkeuren')->assertOk()->assertSee('iemand anders keurt hem goed');
    }

    public function test_alleen_lezen_heeft_geen_verlof(): void
    {
        $this->actingAsRole('alleen_lezen');
        $this->get('/verlof')->assertForbidden();
    }

    public function test_import_van_gripp_verlof(): void
    {
        $r = [FakeBackup::class, 'ref'];
        $d = [FakeBackup::class, 'date'];
        $backup = $this->backup()
            ->set('absencerequest', [
                ['id' => 1, 'employee' => $r(100), 'absencetype' => $r(1, 'Vakantie'), 'description' => 'Zomer', 'comment' => '', 'createdon' => $d('2026-05-01')],
                ['id' => 2, 'employee' => $r(103), 'absencetype' => $r(2, 'Ziekte'), 'description' => 'Griep'],
                ['id' => 3, 'employee' => $r(103), 'absencetype' => $r(3, 'Bijzonder verlof'), 'description' => 'Verhuizing'],
                ['id' => 4, 'employee' => $r(100), 'absencetype' => $r(1, 'Vakantie'), 'description' => 'Kerst'],
            ])
            ->set('absencerequestline', [
                ['id' => 11, 'absencerequest' => $r(1), 'date' => $d('2026-07-20'), 'amount' => 8, 'absencerequeststatus' => $r(2, 'Goedgekeurd')],
                ['id' => 12, 'absencerequest' => $r(1), 'date' => $d('2026-07-21'), 'amount' => 8, 'absencerequeststatus' => $r(2, 'Goedgekeurd')],
                ['id' => 13, 'absencerequest' => $r(2), 'date' => $d('2026-03-02'), 'amount' => 8, 'absencerequeststatus' => $r(2, 'Goedgekeurd')],
                ['id' => 14, 'absencerequest' => $r(4), 'date' => $d('2026-12-28'), 'amount' => 8, 'absencerequeststatus' => $r(1, 'Aangevraagd')],
            ]);
        $run = $this->runImport($backup);
        $this->assertSame('geslaagd', $run->status, (string) $run->error);

        $summer = LeaveRequest::where('gripp_id', 1)->with('lines')->firstOrFail();
        $this->assertSame('goedgekeurd', $summer->status);
        $this->assertSame('vakantie', $summer->type);
        $this->assertSame(16.0, $summer->hours());
        $this->assertSame('2026-05-01', $summer->created_at->toDateString());
        $this->assertNull(LeaveRequest::where('gripp_id', 2)->first()); // ziekte niet overgenomen
        $this->assertSame(1, $run->summary['entities']['absencerequestline']['skipped']); // dag bij ziekmelding
        $this->assertSame(['bijzonder', 'onbekend'], [LeaveRequest::where('gripp_id', 3)->value('type'), LeaveRequest::where('gripp_id', 3)->value('status')]);
        $this->assertSame('aangevraagd', LeaveRequest::where('gripp_id', 4)->value('status'));

        // status wijzigt in Gripp → aanvraag volgt
        $backup->update('absencerequestline', 14, ['absencerequeststatus' => $r(2, 'Goedgekeurd')]);
        $this->runImport($backup);
        $this->assertSame('goedgekeurd', LeaveRequest::where('gripp_id', 4)->value('status'));
        $this->assertSame(0, $this->runImport($backup)->totals()['updated']);
    }
}
