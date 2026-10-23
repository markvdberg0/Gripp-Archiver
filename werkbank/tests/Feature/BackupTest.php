<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Offer;
use App\Models\StoredFile;
use App\Support\Backup;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class BackupTest extends TestCase
{
    // Herstel wist en herbouwt de database; dat kan niet binnen de transactie van RefreshDatabase.
    use DatabaseMigrations;

    private string $dir;

    /** RefreshDatabase (uit TestCase) uitschakelen; DatabaseMigrations bouwt de database per test op. */
    public function refreshDatabase(): void {}

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/werkbank-backups-'.uniqid();
        config(['werkbank.backup_path' => $this->dir]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    public function test_backup_en_herstel_met_bestanden(): void
    {
        $this->runImport($this->backup());
        $companies = Company::count();
        $offer = Offer::where('gripp_id', 217)->firstOrFail();
        $originalName = $offer->name;
        $file = StoredFile::whereNotNull('path')->firstOrFail();
        $content = Storage::disk('local')->get($file->path);

        $this->artisan('werkbank:backup')->expectsOutputToContain('Back-up gemaakt en gecontroleerd')->assertSuccessful();
        $zip = Backup::list()[0]['path'];

        // Gegevens kwijt of gewijzigd
        $offer->update(['name' => 'Gewijzigd na de back-up']);
        Company::query()->delete();
        Storage::disk('local')->delete($file->path);

        $this->artisan('werkbank:herstel', ['bestand' => $zip, '--force' => true])->expectsOutputToContain('Herstel voltooid')->assertSuccessful();

        $this->assertSame($companies, Company::count());
        $this->assertSame($originalName, Offer::where('gripp_id', 217)->value('name'));
        $this->assertSame($content, Storage::disk('local')->get($file->path));
        // er is een veiligheidsback-up van de toestand vóór het herstel
        $this->assertCount(2, Backup::list());
        $this->assertTrue(collect(Backup::list())->contains(fn ($b) => str_contains($b['name'], 'voor-herstel')));
        // na herstel werkt de applicatie gewoon
        $this->actingAsRole('beheerder');
        $this->get('/relaties')->assertOk()->assertSee('Bakker Installatie B.V.');
    }

    public function test_gewijzigde_back_up_wordt_geweigerd(): void
    {
        Company::factory()->create(['name' => 'Origineel']);
        $path = Backup::create();
        $zip = new ZipArchive;
        $zip->open($path);
        $zip->addFromString('database/companies.jsonl', json_encode(['id' => 1, 'name' => 'Vervalst'])."\n");
        $zip->close();

        $this->artisan('werkbank:herstel', ['bestand' => $path, '--force' => true])->expectsOutputToContain('Controlegetal klopt niet')->assertFailed();
        $this->assertSame('Origineel', Company::first()->name);
    }

    public function test_controleren_wijzigt_niets_en_opruimen_bewaart_het_ingestelde_aantal(): void
    {
        Company::factory()->create();
        $path = Backup::create();
        $this->artisan('werkbank:herstel', ['bestand' => basename($path), '--controleer' => true])->expectsOutputToContain('Back-up in orde')->assertSuccessful();
        $this->assertSame(1, Company::count());

        foreach (['a', 'b', 'c'] as $label) {
            Backup::create($label);
        }
        $this->assertSame(2, Backup::prune(2));
        $this->assertCount(2, Backup::list());
    }

    public function test_beheerpagina_back_ups(): void
    {
        $this->actingAsRole('beheerder');
        $this->post('/beheer/backups')->assertRedirect();
        $this->get('/beheer/backups')->assertOk()->assertSee('werkbank-backup-');
        $name = Backup::list()[0]['name'];
        $this->get('/beheer/backups/'.$name)->assertOk()->assertDownload($name);
        $this->get('/beheer/backups/..%2F..%2F.env')->assertNotFound();

        $this->actingAsRole('administratie');
        $this->get('/beheer/backups')->assertForbidden();
    }
}
