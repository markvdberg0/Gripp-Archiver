<?php

namespace Tests\Feature;

use App\Models\CostCenter;
use App\Models\Ledger;
use App\Models\Offer;
use App\Models\OfferLine;
use App\Models\Packet;
use App\Models\Product;
use App\Models\TimelineEntry;
use App\Models\Unit;
use App\Models\VatRate;
use Tests\TestCase;

class ImportSalesTest extends TestCase
{
    public function test_stamgegevens_en_producten(): void
    {
        $run = $this->runImport($this->backup());
        $this->assertSame('geslaagd', $run->status, (string) $run->error);

        $this->assertSame('uren', Unit::where('gripp_id', 1)->value('type'));
        $this->assertSame('credit', Ledger::where('gripp_id', 8000)->value('side'));
        $this->assertSame('KP-MON', CostCenter::where('gripp_id', 1)->value('number'));

        $p = Product::where('gripp_id', 66)->firstOrFail();
        $this->assertSame('5500.0000', $p->selling_price);
        $this->assertSame('uur', $p->unit->name);
        $this->assertSame('8000', $p->ledger->number);
        $this->assertSame('7000', $p->purchaseLedger->number);
        $this->assertSame('Staal & Co', $p->supplier->name);
        $this->assertSame(['voorraad' => '4'], $p->extra_fields);
        $this->assertSame(['Groot'], $p->tags->pluck('name')->all());

        // Btw-tarieven afgeleid uit verwijzingen (verkoop 27 en inkoop 127 zijn verschillende Gripp-tarieven)
        $this->assertSame('21.00', VatRate::where('gripp_id', 27)->value('percentage'));
        $this->assertSame('inkoop', VatRate::where('gripp_id', 127)->value('kind'));

        $packet = Packet::where('gripp_id', 1)->firstOrFail();
        $this->assertSame(['Transformator'], $packet->lines->pluck('title')->all());
    }

    public function test_offertes_met_regels_status_en_controles(): void
    {
        $run = $this->runImport($this->backup());

        $offer = Offer::where('gripp_id', 217)->firstOrFail();
        $this->assertSame(1002, $offer->number);
        $this->assertSame('verzonden', $offer->status);
        $this->assertSame('geaccepteerd', $offer->acceptance);
        $this->assertSame('2025-06-14', $offer->valid_until->toDateString());
        $this->assertSame('Bakker Installatie B.V.', $offer->company->name);
        $this->assertSame('Piet Bakker', $offer->contact->name);
        $this->assertSame('Acquisitie', $offer->phase->name);
        $this->assertSame('26750.00', $offer->total_excl);
        $this->assertSame('5617.50', $offer->total_vat);
        $this->assertSame(['levertijd' => '12 weken'], $offer->extra_fields);
        $this->assertSame('Offerte Project', $offer->template);

        $lines = $offer->lines;
        $this->assertSame(['groep', 'normaal', 'normaal'], $lines->pluck('row_type')->all());
        $this->assertSame(['Compactstation', 'Elektrische inrichting', 'Transformator 630 kVA'], $lines->pluck('title')->all());
        $this->assertSame('11000.00', $lines[1]->total_excl);
        $this->assertSame('15750.00', $lines[2]->total_excl);
        $this->assertSame('Inclusief aansluiten', $lines[1]->description);

        // Opdrachtregel (offerprojectbase = opdracht) is niet als offerteregel geïmporteerd.
        $this->assertNull(OfferLine::where('gripp_id', 900)->first());
        $this->assertSame(3, $run->summary['entities']['offerline']['source']);

        // Sjabloon (basisofferte)
        $template = Offer::where('gripp_id', 219)->firstOrFail();
        $this->assertTrue($template->is_template);
        $this->assertNull($template->number);

        // Timeline gekoppeld aan offerte
        $this->assertSame($offer->id, TimelineEntry::where('gripp_id', 502)->value('offer_id'));

        $checks = collect($run->summary['checks'])->keyBy('label');
        $this->assertTrue($checks['offer: som totaal excl. btw']['ok']);
        $this->assertTrue($checks['offerline: offertes waarvan regels optellen tot het totaal']['ok']);
    }

    public function test_offertedocument_uit_archief_gekoppeld(): void
    {
        $backup = $this->backup();
        $backup->documents = ['offer' => [217 => "%PDF-1.4\noffer\n%%EOF"]];
        $this->runImport($backup);

        $offer = Offer::where('gripp_id', 217)->firstOrFail();
        $doc = $offer->files->firstWhere('pivot.role', 'document');
        $this->assertSame('Offerte 1002 (Gripp).pdf', $doc->name);
        $this->assertTrue($doc->hasContent());

        // herhalen: geen tweede document
        $this->runImport($backup);
        $this->assertCount(1, $offer->fresh()->files()->wherePivot('role', 'document')->get());
    }

    public function test_herhaalde_import_en_lokaal_gewijzigde_offerte(): void
    {
        $backup = $this->backup();
        $this->runImport($backup);
        $offer = Offer::where('gripp_id', 217)->firstOrFail();
        $this->travel(2)->minutes();
        $offer->update(['name' => 'Lokaal']);
        $backup->update('offer', 217, ['name' => 'Bron gewijzigd']);

        $run = $this->runImport($backup);

        $this->assertSame('Lokaal', $offer->fresh()->name);
        $this->assertSame(1, $run->summary['entities']['offer']['conflicts']);
        $this->assertSame(3, OfferLine::count());
    }
}
