<?php

namespace Tests\Feature;

use App\Mail\DocumentMail;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\PurchaseInvoice;
use App\Models\Setting;
use App\Models\VatRate;
use App\Support\Iban;
use Illuminate\Support\Facades\Mail;
use SimpleXMLElement;
use Tests\TestCase;

class EInvoiceTest extends TestCase
{
    private const CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    private const CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';

    protected function setUp(): void
    {
        parent::setUp();
        Setting::put([
            'bedrijf.naam' => 'Voorbeeld Techniek B.V.', 'bedrijf.adres' => 'Hoofdstraat 1', 'bedrijf.postcode' => '8011 AA', 'bedrijf.plaats' => 'Zwolle',
            'bedrijf.kvk' => '87654321', 'bedrijf.btw' => 'NL001234567B01', 'bedrijf.iban' => 'NL91ABNA0417164300', 'bedrijf.email' => 'info@voorbeeld.test',
        ]);
    }

    private function finalInvoice(array $lines, array $company = []): Invoice
    {
        $this->actingAsRole('administratie');
        $c = Company::factory()->create($company + ['name' => 'Klant B.V.', 'coc_number' => '12345678', 'vat_number' => 'NL009876543B01', 'visit_city' => 'Deventer']);
        $this->post('/facturen', ['company_id' => $c->id, 'subject' => 'Werkzaamheden', 'payment_method' => 'overboeking', 'lines' => $lines])->assertRedirect();
        $invoice = Invoice::latest('id')->firstOrFail();
        $this->post("/facturen/{$invoice->id}/definitief")->assertRedirect();

        return $invoice->fresh();
    }

    private function xml(string $body): SimpleXMLElement
    {
        $xml = new SimpleXMLElement($body);
        $xml->registerXPathNamespace('cbc', self::CBC);
        $xml->registerXPathNamespace('cac', self::CAC);

        return $xml;
    }

    private function val(SimpleXMLElement $xml, string $path): string
    {
        return (string) ($xml->xpath($path)[0] ?? '');
    }

    public function test_ubl_factuur_klopt_met_de_totalen(): void
    {
        $v21 = VatRate::create(['name' => 'Btw 21%', 'percentage' => 21, 'kind' => 'beide']);
        $v9 = VatRate::create(['name' => 'Btw 9%', 'percentage' => 9, 'kind' => 'beide']);
        $invoice = $this->finalInvoice([
            ['row_type' => 'normaal', 'title' => 'Montage', 'quantity' => '7,5', 'unit_price' => '85', 'discount' => '10', 'vat_rate_id' => $v21->id],
            ['row_type' => 'groep', 'title' => 'Materiaal'],
            ['row_type' => 'normaal', 'title' => 'Kabel', 'quantity' => '3', 'unit_price' => '12,99', 'vat_rate_id' => $v9->id],
            ['row_type' => 'normaal', 'title' => 'Korting', 'quantity' => '1', 'unit_price' => '-25', 'vat_rate_id' => $v21->id],
        ]);

        $res = $this->get("/facturen/{$invoice->id}/ubl")->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $xml = $this->xml($res->getContent());
        $this->assertSame('Invoice', $xml->getName());
        $this->assertSame('urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0', $this->val($xml, '//cbc:CustomizationID'));
        $this->assertSame((string) $invoice->number, $this->val($xml, '/*/cbc:ID'));
        $this->assertSame('380', $this->val($xml, '//cbc:InvoiceTypeCode'));
        $this->assertSame('87654321', $this->val($xml, '//cac:AccountingSupplierParty//cbc:EndpointID'));
        $this->assertSame('12345678', $this->val($xml, '//cac:AccountingCustomerParty//cbc:EndpointID'));
        $this->assertSame('NL91ABNA0417164300', $this->val($xml, '//cac:PayeeFinancialAccount/cbc:ID'));

        $lines = $xml->xpath('//cac:InvoiceLine');
        $this->assertCount(3, $lines); // groepsregel telt niet
        $sum = 0;
        foreach ($lines as $line) {
            $line->registerXPathNamespace('cbc', self::CBC);
            $line->registerXPathNamespace('cac', self::CAC);
            $amount = (float) $line->xpath('cbc:LineExtensionAmount')[0];
            $qty = (float) $line->xpath('cbc:InvoicedQuantity')[0];
            $price = (float) $line->xpath('cac:Price/cbc:PriceAmount')[0];
            $this->assertEqualsWithDelta($amount, round($qty * $price, 2), 0.005, 'aantal × nettoprijs = regelbedrag');
            $this->assertGreaterThanOrEqual(0, $price);
            $sum += $amount;
        }
        $this->assertEqualsWithDelta((float) $invoice->total_excl, $sum, 0.001);
        $this->assertSame($invoice->total_excl, $this->val($xml, '//cac:LegalMonetaryTotal/cbc:LineExtensionAmount'));
        $this->assertSame($invoice->total_vat, $this->val($xml, '/*/cac:TaxTotal/cbc:TaxAmount'));
        $this->assertSame($invoice->total_incl, $this->val($xml, '//cac:LegalMonetaryTotal/cbc:PayableAmount'));
        $this->assertCount(2, $xml->xpath('//cac:TaxSubtotal'));
        $this->assertSame('573.75', $this->val($xml, '//cac:InvoiceLine[1]/cbc:LineExtensionAmount')); // 7,5 × 85 × 0,9
        $this->assertSame('C62', (string) $xml->xpath('//cac:InvoiceLine[1]/cbc:InvoicedQuantity/@unitCode')[0]); // geen eenheid = stuks
        $this->assertSame(1, AuditLog::where('event', 'ubl')->count());
        $this->get("/facturen/{$invoice->id}")->assertOk()->assertSee('UBL')->assertDontSee('E-factuur (UBL):');
    }

    public function test_verlegde_btw_en_ontbrekende_gegevens(): void
    {
        $rc = VatRate::create(['name' => 'Btw verlegd', 'percentage' => 21, 'kind' => 'verkoop', 'reverse_charge' => true]);
        $invoice = $this->finalInvoice([['row_type' => 'normaal', 'title' => 'Onderaanneming', 'quantity' => '1', 'unit_price' => '1000', 'vat_rate_id' => $rc->id]],
            ['coc_number' => null, 'vat_number' => null]);
        $xml = $this->xml($this->get("/facturen/{$invoice->id}/ubl")->getContent());
        $this->assertSame('AE', $this->val($xml, '//cac:TaxSubtotal/cac:TaxCategory/cbc:ID'));
        $this->assertSame('0.00', $this->val($xml, '/*/cac:TaxTotal/cbc:TaxAmount'));
        $this->assertSame('1000.00', $this->val($xml, '//cbc:PayableAmount'));
        $this->assertSame('', $this->val($xml, '//cac:AccountingCustomerParty//cbc:EndpointID'));
        $this->get("/facturen/{$invoice->id}")->assertSee('Geen KvK- of Nederlands btw-nummer bij relatie');
    }

    public function test_creditnota_en_concept(): void
    {
        $v21 = VatRate::create(['name' => 'Btw 21%', 'percentage' => 21, 'kind' => 'beide']);
        $invoice = $this->finalInvoice([['row_type' => 'normaal', 'title' => 'Montage', 'quantity' => '2', 'unit_price' => '100', 'vat_rate_id' => $v21->id]]);
        $this->post("/facturen/{$invoice->id}/crediteren")->assertRedirect();
        $credit = Invoice::where('credit_of_id', $invoice->id)->firstOrFail();
        $this->get("/facturen/{$credit->id}/ubl")->assertStatus(422); // nog concept
        $this->post("/facturen/{$credit->id}/definitief");

        $xml = $this->xml($this->get("/facturen/{$credit->id}/ubl")->assertOk()->getContent());
        $this->assertSame('CreditNote', $xml->getName());
        $this->assertSame('381', $this->val($xml, '//cbc:CreditNoteTypeCode'));
        $this->assertSame((string) $invoice->number, $this->val($xml, '//cac:BillingReference//cbc:ID'));
        $this->assertSame('242.00', $this->val($xml, '//cbc:PayableAmount'));
        $this->assertSame('2', $this->val($xml, '//cac:CreditNoteLine/cbc:CreditedQuantity'));
    }

    public function test_ubl_meesturen_met_de_factuurmail(): void
    {
        Mail::fake();
        $v21 = VatRate::create(['name' => 'Btw 21%', 'percentage' => 21, 'kind' => 'beide']);
        $invoice = $this->finalInvoice([['row_type' => 'normaal', 'title' => 'Montage', 'quantity' => '1', 'unit_price' => '100', 'vat_rate_id' => $v21->id]]);
        $this->get("/mail/factuur/{$invoice->id}")->assertSee('UBL-bestand');
        $this->post("/mail/factuur/{$invoice->id}", ['to' => 'klant@example.test', 'subject' => 'Factuur', 'body' => 'Bijgaand.', 'ubl' => '1'])->assertRedirect();
        Mail::assertSent(DocumentMail::class, fn (DocumentMail $m) => count($m->attachments()) === 2 && array_key_exists("factuur-{$invoice->number}.xml", $m->extra));
    }

    public function test_iban_controle(): void
    {
        $this->assertTrue(Iban::valid('NL91ABNA0417164300'));
        $this->assertTrue(Iban::valid('nl91 abna 0417 1643 00'));
        $this->assertTrue(Iban::valid('BE68539007547034'));
        $this->assertTrue(Iban::valid('DE89370400440532013000'));
        $this->assertFalse(Iban::valid('NL91ABNA0417164301'));
        $this->assertFalse(Iban::valid('NL91ABNA04171643'));
        $this->assertFalse(Iban::valid('XX00'));
        $this->assertSame('NL91 ABNA 0417 1643 00', Iban::format('NL91ABNA0417164300'));
    }

    public function test_sepa_betaalbestand(): void
    {
        $ok = Company::factory()->supplier()->create(['name' => 'Kabelhandel Ærø & Zn', 'iban' => 'NL20INGB0001234567']);
        $bad = Company::factory()->supplier()->create(['name' => 'Zonder IBAN', 'iban' => 'NL00FOUT']);
        $make = fn (array $a) => PurchaseInvoice::create($a + ['phase' => 'akkoord', 'total_incl' => 121, 'total_open' => 121, 'due_date' => now()->addDays(5)->toDateString(), 'date' => now()->toDateString()]);
        $a = $make(['booking_number' => 1201, 'invoice_number' => 'INK-1', 'supplier_id' => $ok->id]);
        $b = $make(['booking_number' => 1202, 'invoice_number' => 'INK-2', 'supplier_id' => $ok->id, 'total_open' => 50.5]);
        $hold = $make(['booking_number' => 1203, 'supplier_id' => $ok->id, 'on_hold' => true]);
        $noIban = $make(['booking_number' => 1204, 'supplier_id' => $bad->id]);
        $make(['booking_number' => 1205, 'supplier_id' => $ok->id, 'phase' => 'controle']);
        $make(['booking_number' => 1206, 'supplier_id' => $ok->id, 'total_open' => 0]);

        $this->actingAsRole('projectleider');
        $this->get('/inkoopfacturen-betalen')->assertForbidden();

        $this->actingAsRole('administratie');
        $this->get('/inkoopfacturen-betalen')->assertOk()->assertSee('1201')->assertSee('1204')->assertSee('geen geldig IBAN')->assertDontSee('1205')->assertDontSee('1206');
        $this->post('/inkoopfacturen-betalen', ['ids' => [$a->id, $noIban->id], 'execution_date' => now()->toDateString()])->assertSessionHasErrors('ids');
        $this->post('/inkoopfacturen-betalen', ['ids' => [$hold->id], 'execution_date' => now()->toDateString()])->assertSessionHasErrors('ids');
        $this->post('/inkoopfacturen-betalen', ['ids' => [$a->id], 'execution_date' => now()->subDay()->toDateString()])->assertSessionHasErrors('execution_date');
        $this->assertNull($a->fresh()->sepa_exported_at);

        $res = $this->post('/inkoopfacturen-betalen', ['ids' => [$a->id, $b->id], 'execution_date' => now()->addDay()->toDateString()])->assertSessionHasNoErrors()->assertOk();
        $xml = new SimpleXMLElement($res->getContent());
        $xml->registerXPathNamespace('p', 'urn:iso:std:iso:20022:tech:xsd:pain.001.001.09');
        $this->assertSame('2', (string) $xml->xpath('//p:GrpHdr/p:NbOfTxs')[0]);
        $this->assertSame('171.50', (string) $xml->xpath('//p:GrpHdr/p:CtrlSum')[0]);
        $this->assertSame('NL91ABNA0417164300', (string) $xml->xpath('//p:DbtrAcct/p:Id/p:IBAN')[0]);
        $this->assertSame('NOTPROVIDED', (string) $xml->xpath('//p:DbtrAgt//p:Othr/p:Id')[0]);
        $this->assertSame('NL20INGB0001234567', (string) $xml->xpath('//p:CdtTrfTxInf[1]//p:CdtrAcct/p:Id/p:IBAN')[0]);
        $this->assertSame('Kabelhandel AEro Zn', (string) $xml->xpath('//p:CdtTrfTxInf[1]/p:Cdtr/p:Nm')[0]); // SEPA-tekenset
        $this->assertSame('Factuur INK-2', (string) $xml->xpath('//p:CdtTrfTxInf[2]//p:Ustrd')[0]);
        $this->assertSame(now()->addDay()->toDateString(), (string) $xml->xpath('//p:ReqdExctnDt/p:Dt')[0]);

        $this->assertNotNull($a->fresh()->sepa_exported_at);
        $this->assertSame($a->fresh()->sepa_batch, $b->fresh()->sepa_batch);
        $this->assertSame('121.00', $a->fresh()->total_open); // nog niet als betaald geregistreerd
        $this->assertSame(1, AuditLog::where('event', 'sepa_bestand')->count());
        $this->get('/inkoopfacturen-betalen')->assertDontSee('INK-1');
        $this->get('/inkoopfacturen-betalen?ook_verwerkt=1')->assertSee('INK-1')->assertSee('in bestand');
    }

    public function test_sepa_zonder_eigen_iban(): void
    {
        Setting::put(['bedrijf.iban' => '']);
        $s = Company::factory()->supplier()->create(['iban' => 'NL20INGB0001234567']);
        $i = PurchaseInvoice::create(['booking_number' => 1, 'supplier_id' => $s->id, 'phase' => 'akkoord', 'total_incl' => 10, 'total_open' => 10]);
        $this->actingAsRole('administratie');
        $this->get('/inkoopfacturen-betalen')->assertOk()->assertSee('Eerst instellen');
        $this->post('/inkoopfacturen-betalen', ['ids' => [$i->id], 'execution_date' => now()->toDateString()])->assertSessionHasErrors('ids');
    }
}
