<?php

namespace Tests\Feature;

use App\Jobs\RunImport;
use App\Models\Company;
use App\Models\Employee;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminTest extends TestCase
{
    public function test_beheer_alleen_voor_beheerder(): void
    {
        foreach (['administratie', 'projectleider', 'medewerker', 'alleen_lezen'] as $role) {
            $this->actingAsRole($role);
            $this->get('/beheer/gebruikers')->assertForbidden();
            $this->get('/beheer/import')->assertForbidden();
        }
        $this->actingAsRole('beheerder');
        $this->get('/beheer/gebruikers')->assertOk();
        $this->get('/beheer/import')->assertOk();
        $this->get('/beheer/logboek')->assertOk();
    }

    public function test_alleen_lezen_kan_niets_wijzigen(): void
    {
        $this->actingAsRole('alleen_lezen');
        $this->get('/relaties')->assertOk()->assertDontSee('Nieuwe relatie');
        $this->get('/relaties/create')->assertForbidden();
        $this->post('/relaties', ['name' => 'X'])->assertForbidden();
    }

    public function test_gebruiker_aanmaken_met_tijdelijk_wachtwoord_en_medewerker(): void
    {
        $this->actingAsRole('beheerder');
        $employee = Employee::factory()->create();
        $response = $this->post('/beheer/gebruikers', ['name' => 'Nieuw', 'email' => 'nieuw@example.test', 'role' => 'projectleider', 'active' => '1', 'employee_id' => $employee->id]);
        $response->assertSessionHasNoErrors();
        $user = User::where('email', 'nieuw@example.test')->firstOrFail();
        $response->assertRedirect(route('beheer.gebruikers.edit', $user))->assertSessionHas('status');
        $this->assertTrue($user->must_change_password);
        $this->assertSame($user->id, $employee->fresh()->user_id);
    }

    public function test_laatste_beheerder_blijft_beheerder(): void
    {
        $me = $this->actingAsRole('beheerder');
        $this->put("/beheer/gebruikers/{$me->id}", ['name' => $me->name, 'email' => $me->email, 'role' => 'medewerker', 'active' => '1'])->assertSessionHasErrors('role');
        $this->assertSame('beheerder', $me->fresh()->role);
    }

    public function test_wachtwoord_reset_door_beheerder(): void
    {
        $this->actingAsRole('beheerder');
        $other = User::factory()->create();
        $this->post("/beheer/gebruikers/{$other->id}/wachtwoord")->assertSessionHas('status');
        $this->assertTrue($other->fresh()->must_change_password);
    }

    public function test_import_vanuit_scherm_zet_taak_in_de_wachtrij(): void
    {
        Queue::fake();
        $backup = $this->backup()->write();
        config(['werkbank.import_path' => $backup->dir]);
        $this->actingAsRole('beheerder');

        $this->get('/beheer/import')->assertOk()->assertSee('staging-test');
        $this->post('/beheer/import', ['mode' => 'droogloop'])->assertRedirect();
        Queue::assertPushed(RunImport::class, fn ($job) => $job->run->dry_run === true);
        $this->post('/beheer/import', ['mode' => 'import'])->assertSessionHasErrors('mode'); // er loopt er al een
    }

    public function test_importrapport_tonen(): void
    {
        $run = $this->runImport($this->backup());
        $this->actingAsRole('beheerder');
        $this->get("/beheer/import/{$run->id}")->assertOk()->assertSee('Per onderdeel')->assertSee('bestaat niet');
    }

    public function test_bestand_uploaden_en_downloaden_met_rechten(): void
    {
        $company = Company::factory()->create();
        $this->actingAsRole('administratie');
        $this->post('/bestanden', ['type' => 'company', 'id' => $company->id, 'bestand' => UploadedFile::fake()->createWithContent('offerte.pdf', "%PDF-1.4\n%%EOF")])->assertRedirect();
        $file = StoredFile::where('name', 'offerte.pdf')->firstOrFail();
        $this->assertTrue($file->hasContent());
        $this->get("/bestanden/{$file->id}")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAsRole('medewerker');
        $this->get("/bestanden/{$file->id}")->assertOk(); // relaties.bekijken
        $this->post('/bestanden', ['type' => 'company', 'id' => $company->id, 'bestand' => UploadedFile::fake()->create('x.pdf')])->assertForbidden();
    }

    public function test_bestand_zonder_koppeling_alleen_voor_beheer(): void
    {
        $file = StoredFile::create(['name' => 'los.pdf']);
        $this->actingAsRole('administratie');
        $this->get("/bestanden/{$file->id}")->assertForbidden();
    }

    public function test_zoeken(): void
    {
        $this->actingAsRole('medewerker');
        Company::factory()->create(['name' => 'Zoekbaar B.V.']);
        $this->get('/zoeken?q=zoekb')->assertOk()->assertSee('Zoekbaar B.V.');
        $this->get('/zoeken?q=z')->assertSee('minstens twee tekens');
    }
}
