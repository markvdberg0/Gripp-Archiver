<?php

namespace Tests\Feature;

use App\Import\Importer;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Employee;
use App\Models\ImportRun;
use App\Models\StoredFile;
use App\Models\Tag;
use App\Models\TimelineEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportTest extends TestCase
{
    public function test_droogloop_slaat_niets_op_maar_rapporteert_wel(): void
    {
        $run = $this->runImport($this->backup(), dryRun: true);

        $this->assertSame('geslaagd', $run->status, (string) $run->error);
        $this->assertSame(3, $run->summary['entities']['company']['created']);
        $this->assertSame(0, Company::count());
        $this->assertSame(0, StoredFile::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_import_neemt_relaties_contactpersonen_en_koppelingen_over(): void
    {
        $run = $this->runImport($this->backup());
        $this->assertSame('geslaagd', $run->status, (string) $run->error);

        $bakker = Company::where('gripp_id', 1)->firstOrFail();
        $this->assertSame('Bakker Installatie B.V.', $bakker->name);
        $this->assertSame(1000001, $bakker->number);
        $this->assertTrue($bakker->is_customer);
        $this->assertSame('Zwolle', $bakker->visit_city);
        $this->assertSame('NL91ABNA0417164300', $bakker->iban);
        $this->assertSame('Jan Jansen', $bakker->accountManager->name);
        $this->assertSame(['Zwolle'], $bakker->tags->pluck('name')->all());
        $this->assertSame('2025-01-10 09:30', $bakker->created_at->format('Y-m-d H:i'));
        $this->assertSame(['kvk-uittreksel.pdf'], $bakker->files->pluck('name')->all());

        $staal = Company::where('gripp_id', 2)->first();
        $this->assertTrue($staal->is_supplier);
        $this->assertSame('incasso', $staal->payment_method);
        $this->assertSame('post', $staal->invoice_send_by);
        $this->assertSame(14, $staal->supplier_payment_term_days);
        $this->assertFalse(Company::where('gripp_id', 3)->first()->active);

        $piet = Contact::where('gripp_id', 11)->first();
        $this->assertSame($bakker->id, $piet->company_id);
        $this->assertSame('dhr', $piet->salutation);
        $this->assertSame('mevr', Contact::where('gripp_id', 12)->first()->salutation);
        $this->assertSame(['Groot'], Contact::where('gripp_id', 12)->first()->tags->pluck('name')->all());

        // Gebroken verwijzing: contactpersoon wel geïmporteerd, zonder relatie, en gemeld.
        $this->assertNull(Contact::where('gripp_id', 13)->first()->company_id);
        $this->assertStringContainsString('bestaat niet', collect($run->issues['contact'])->pluck('message')->join(' '));

        $mail = TimelineEntry::where('gripp_id', 501)->first();
        $this->assertSame('email_ontvangen', $mail->type);
        $this->assertSame($bakker->id, $mail->company_id);
        $this->assertSame($piet->id, $mail->contact_id);
        $this->assertSame('Anna de Vries', $mail->employee->name);
        $this->assertSame('2026-09-22 10:15', $mail->occurred_at->format('Y-m-d H:i'));
        $this->assertSame(['mail.pdf'], $mail->files->pluck('name')->all());
        $this->assertSame('notitie', TimelineEntry::where('gripp_id', 502)->value('type'));
        $this->assertSame('systeem', TimelineEntry::where('gripp_id', 503)->value('type'));
    }

    public function test_bestanden_worden_op_inhoud_ontdubbeld_en_ontbrekende_inhoud_gemeld(): void
    {
        $run = $this->runImport($this->backup());

        $kvk = StoredFile::where('gripp_id', 9001)->first();
        $kopie = StoredFile::where('gripp_id', 9004)->first();
        $this->assertSame($kvk->sha256, $kopie->sha256);
        $this->assertSame($kvk->path, $kopie->path);
        $this->assertTrue($kvk->hasContent());
        $this->assertSame('application/pdf', $kvk->mime_type);
        $this->assertCount(2, Storage::disk('local')->allFiles()); // kvk/kopie gedeeld + mail
        $this->assertNull(StoredFile::where('gripp_id', 9003)->first()->path);
        $this->assertSame(1, $run->summary['files']['missing']);
    }

    public function test_medewerkers_zonder_gevoelige_gegevens(): void
    {
        $this->runImport($this->backup());

        $jan = Employee::where('gripp_id', 100)->first();
        $this->assertSame('Jan Jansen', $jan->name);
        foreach (['password', 'username', 'socialsecuritynumber', 'bankaccount', 'bsn', 'iban'] as $col) {
            $this->assertFalse(Schema::hasColumn('employees', $col), $col);
        }
        $dump = json_encode(DB::table('employees')->get());
        $this->assertStringNotContainsString('123456782', $dump);
        $this->assertStringNotContainsString('geheimehash', $dump);
    }

    public function test_herhaalde_import_maakt_geen_duplicaten(): void
    {
        $backup = $this->backup();
        $this->runImport($backup);
        $counts = [Company::count(), Contact::count(), TimelineEntry::count(), StoredFile::count(), Tag::count(), DB::table('attachments')->count(), DB::table('taggables')->count()];

        $second = $this->runImport($backup);

        $this->assertSame($counts, [Company::count(), Contact::count(), TimelineEntry::count(), StoredFile::count(), Tag::count(), DB::table('attachments')->count(), DB::table('taggables')->count()]);
        $this->assertSame(0, $second->totals()['created']);
        $this->assertSame(0, $second->totals()['updated']);
        $this->assertSame($second->totals()['unchanged'], collect($second->summary['entities'])->sum('source'));
    }

    public function test_bronwijziging_wordt_bijgewerkt(): void
    {
        $backup = $this->backup();
        $this->runImport($backup);
        $backup->update('company', 1, ['companyname' => 'Bakker Techniek B.V.', 'tags' => [['id' => 2, 'searchname' => 'Groot']]]);

        $run = $this->runImport($backup);

        $this->assertSame(1, $run->summary['entities']['company']['updated']);
        $company = Company::where('gripp_id', 1)->first();
        $this->assertSame('Bakker Techniek B.V.', $company->name);
        $this->assertSame(['Groot'], $company->tags->pluck('name')->all());
    }

    public function test_lokale_wijziging_wordt_niet_overschreven(): void
    {
        $backup = $this->backup();
        $this->runImport($backup);
        $this->travel(5)->minutes();
        Company::where('gripp_id', 1)->first()->update(['name' => 'Lokaal aangepast']);
        $backup->update('company', 1, ['companyname' => 'Bron aangepast']);

        $run = $this->runImport($backup);

        $this->assertSame('Lokaal aangepast', Company::where('gripp_id', 1)->value('name'));
        $this->assertSame(1, $run->summary['entities']['company']['conflicts']);
        $this->assertSame('conflict', $run->issues['company'][0]['type']);
    }

    public function test_lokaal_verwijderd_wordt_niet_hersteld(): void
    {
        $backup = $this->backup();
        $this->runImport($backup);
        Company::where('gripp_id', 3)->first()->delete();
        $backup->update('company', 3, ['companyname' => 'Toch gewijzigd']);

        $run = $this->runImport($backup);

        $this->assertNull(Company::where('gripp_id', 3)->first());
        $this->assertSame(1, $run->summary['entities']['company']['conflicts']);
    }

    public function test_verdwenen_bronrecords_blijven_bestaan(): void
    {
        $backup = $this->backup();
        $this->runImport($backup);
        $backup->remove('contact', 12);

        $run = $this->runImport($backup);

        $this->assertNotNull(Contact::where('gripp_id', 12)->first());
        $this->assertSame(1, $run->summary['entities']['contact']['missing_in_source']);
    }

    public function test_mislukte_import_verandert_niets(): void
    {
        $backup = $this->backup()->write();
        file_put_contents($backup->dir.'/data/current/raw/contact.json', '{kapot');
        $run = ImportRun::create(['source_path' => $backup->dir, 'dry_run' => false]);

        app(Importer::class)->run($run);

        $this->assertSame('mislukt', $run->status);
        $this->assertSame(0, Company::count(), 'ook eerder in de run geïmporteerde relaties zijn teruggedraaid');
        $this->assertSame(0, Tag::count());
    }

    public function test_onbekende_map_geeft_duidelijke_fout(): void
    {
        $run = ImportRun::create(['source_path' => sys_get_temp_dir().'/bestaat-niet-'.uniqid(), 'dry_run' => true]);
        app(Importer::class)->run($run);
        $this->assertSame('mislukt', $run->status);
        $this->assertStringContainsString('niet gevonden', $run->error);
    }

    public function test_deelimport_alleen_gekozen_onderdelen(): void
    {
        $run = $this->runImport($this->backup(), only: 'tag,company');
        $this->assertSame(['tag', 'company'], array_keys($run->summary['entities']));
        $this->assertSame(0, Contact::count());
        $this->assertSame(3, Company::count());
    }

    public function test_controletotalen_en_logboek(): void
    {
        $run = $this->runImport($this->backup());
        $check = collect($run->summary['checks'])->firstWhere('label', 'company: aantal records');
        $this->assertTrue($check['ok']);
        $this->assertSame(3, $check['source']);
        // Losse wijzigingen worden tijdens een import niet gelogd, de run zelf wel.
        $this->assertSame(1, AuditLog::where('event', 'import')->count());
        $this->assertSame(0, AuditLog::where('event', 'aangemaakt')->count());
    }

    public function test_import_via_artisan(): void
    {
        $backup = $this->backup()->write();
        $this->artisan('werkbank:import', ['pad' => $backup->dir, '--droogloop' => true])
            ->expectsOutputToContain('Droogloop klaar')
            ->assertSuccessful();
        $this->assertSame(0, Company::count());
    }
}
