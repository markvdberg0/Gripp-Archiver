<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Offer;
use App\Models\PurchaseOrder;
use App\Models\StoredFile;
use App\Models\Tag;
use App\Support\RelatieSamenvoegen;
use Tests\Support\FakeBackup;
use Tests\TestCase;

class CompanyMergeTest extends TestCase
{
    public function test_samenvoegen_verplaatst_alles_en_vult_lege_velden_aan(): void
    {
        $this->actingAsRole('administratie');
        $keep = Company::factory()->create(['name' => 'Bakker Installatie B.V.', 'email' => 'info@bakker.test', 'phone' => null, 'is_customer' => true]);
        $gone = Company::factory()->create(['name' => 'Bakker Installatie', 'email' => 'oud@bakker.test', 'phone' => '038-1234567', 'is_supplier' => true, 'notes' => 'Oude notitie']);
        $contact = Contact::factory()->create(['company_id' => $gone->id]);
        $offer = Offer::factory()->create(['company_id' => $gone->id]);
        $invoice = Invoice::factory()->create(['company_id' => $gone->id]);
        $po = PurchaseOrder::factory()->create(['supplier_id' => $gone->id]);
        $offerUpdated = $offer->updated_at;
        $tagShared = Tag::create(['name' => 'Zwolle']);
        $tagOnly = Tag::create(['name' => 'Groot']);
        $keep->tags()->attach($tagShared);
        $gone->tags()->attach([$tagShared->id, $tagOnly->id]);
        $file = StoredFile::create(['name' => 'kvk.pdf', 'path' => 'x/kvk.pdf', 'size' => 1, 'sha256' => str_repeat('a', 64), 'mime_type' => 'application/pdf']);
        $gone->files()->attach($file->id, ['role' => 'bijlage', 'created_at' => now()]);

        $this->get(route('relaties.samenvoegen', $gone))->assertOk()->assertSee($keep->name); // kandidaat op naam
        $this->get(route('relaties.samenvoegen', [$gone, 'doel' => $keep->id]))->assertOk()->assertSee('Contactpersonen')->assertSee('phone');

        $this->post(route('relaties.samenvoegen.store', $gone), ['doel' => $keep->id, 'aanvullen' => '1', 'bevestig' => '1'])
            ->assertRedirect(route('relaties.show', $keep));

        $this->assertSame($keep->id, $contact->fresh()->company_id);
        $this->assertSame($keep->id, $offer->fresh()->company_id);
        $this->assertEquals($offerUpdated, $offer->fresh()->updated_at); // document zelf niet als gewijzigd gemarkeerd
        $this->assertSame($keep->id, $invoice->fresh()->company_id);
        $this->assertSame($keep->id, $po->fresh()->supplier_id);
        $this->assertEqualsCanonicalizing(['Zwolle', 'Groot'], $keep->fresh()->tags->pluck('name')->all());
        $this->assertSame(['kvk.pdf'], $keep->fresh()->files->pluck('name')->all());

        $keep->refresh();
        $this->assertSame('info@bakker.test', $keep->email); // gevuld veld blijft
        $this->assertSame('038-1234567', $keep->phone); // leeg veld aangevuld
        $this->assertTrue($keep->is_customer && $keep->is_supplier);
        $this->assertStringContainsString('Oude notitie', $keep->notes);

        $gone = Company::withTrashed()->find($gone->id);
        $this->assertTrue($gone->trashed());
        $this->assertSame($keep->id, $gone->merged_into_id);
        $this->assertSame(1, AuditLog::where('event', 'relatie_samengevoegd')->count());

        // Oude link verwijst door
        $this->get(route('relaties.show', $gone->id))->assertRedirect(route('relaties.show', $keep));
        $this->get(route('relaties.samenvoegen', $gone->id))->assertNotFound();
    }

    public function test_bevestiging_en_rechten(): void
    {
        $a = Company::factory()->create();
        $b = Company::factory()->create();

        $this->actingAsRole('projectleider');
        $this->get(route('relaties.dubbel'))->assertForbidden();
        $this->post(route('relaties.samenvoegen.store', $a), ['doel' => $b->id, 'bevestig' => '1'])->assertForbidden();

        $this->actingAsRole('administratie');
        $this->post(route('relaties.samenvoegen.store', $a), ['doel' => $b->id])->assertSessionHasErrors('bevestig');
        $this->post(route('relaties.samenvoegen.store', $a), ['doel' => $a->id, 'bevestig' => '1'])->assertSessionHasErrors('doel');
        $this->assertFalse($a->fresh()->trashed());
    }

    public function test_dubbelingen_op_naam_kvk_en_btw(): void
    {
        $this->actingAsRole('beheerder');
        Company::factory()->create(['name' => 'Staal & Co B.V.']);
        Company::factory()->create(['name' => 'staal co bv']);
        Company::factory()->create(['name' => 'Alpha', 'coc_number' => '12345678']);
        Company::factory()->create(['name' => 'Beta', 'coc_number' => '1234 5678']);
        Company::factory()->create(['name' => 'Uniek', 'vat_number' => 'NL001234567B01']);

        $groups = RelatieSamenvoegen::duplicates();
        $this->assertCount(2, $groups);
        $this->assertEqualsCanonicalizing(['naam', 'KvK-nummer'], $groups->pluck('reden')->all());
        $this->assertSame('staal co', RelatieSamenvoegen::normalizeName('Staal & Co B.V.'));

        $this->get(route('relaties.dubbel'))->assertOk()->assertSee('Staal &amp; Co B.V.', false)->assertSee('Beta');
    }

    public function test_import_na_samenvoegen_volgt_de_blijvende_relatie(): void
    {
        $backup = $this->backup();
        $this->assertSame('geslaagd', $this->runImport($backup)->status);
        $bakker = Company::where('gripp_id', 1)->firstOrFail();
        $staal = Company::where('gripp_id', 2)->firstOrFail();
        RelatieSamenvoegen::merge($staal, $bakker, fillEmpty: false);

        $backup->update('company', 2, ['companyname' => 'Staal & Co (gewijzigd in Gripp)'])
            ->update('offer', 218, ['name' => 'Concept station v2'])
            ->set('contact', array_merge(FakeBackup::defaultData()['contact'], [['id' => 14, 'firstname' => 'Nieuw', 'lastname' => 'Contact', 'company' => ['id' => 2]]]));
        $run = $this->runImport($backup);

        $this->assertSame('geslaagd', $run->status, (string) $run->error);
        $this->assertSame(0, $run->summary['entities']['company']['conflicts']);
        $this->assertSame(1, $run->summary['entities']['company']['skipped']);
        $this->assertSame('Staal & Co', Company::withTrashed()->find($staal->id)->name); // niet bijgewerkt
        $this->assertTrue(Company::withTrashed()->find($staal->id)->trashed());
        $this->assertSame($bakker->id, Offer::where('gripp_id', 218)->value('company_id'));
        $this->assertSame('Concept station v2', Offer::where('gripp_id', 218)->value('name'));
        $this->assertSame($bakker->id, Contact::where('gripp_id', 14)->value('company_id'));
        $this->assertSame($bakker->id, Contact::where('gripp_id', 12)->value('company_id'));
    }
}
