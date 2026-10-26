<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PurchaseInvoice;
use App\Models\PurchasePayment;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Bankkoppeling met nagebootste Ponto-antwoorden (zoals de sandbox: token → rekeningen → transacties in pagina's). */
class BankTest extends TestCase
{
    private const API = 'https://ponto.test/ponto-connect';

    private array $pages;

    private int $tokenStatus = 200;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.ponto.client_id' => 'client-123', 'services.ponto.client_secret' => 'geheim', 'services.ponto.url' => self::API]);
        Cache::flush();
        $this->pages = [
            1 => [$this->tx('t1', 121.00, 'Factuur 200100 Klant', 'NL20INGB0001234567'), $this->tx('t2', -60.50, 'Factuur INK-9', 'NL44RABO0123456789', 'WB-1201'), $this->tx('t3', 50.00, 'deel 200101')],
            2 => [$this->tx('t4', 75.00, 'betaling', 'NL18RABO0123459876'), $this->tx('t5', -12.34, 'Kosten betaalpakket')],
        ];
        Http::fake(function (Request $r) {
            $url = urldecode($r->url());

            return match (true) {
                str_ends_with($url, '/oauth2/token') => Http::response($this->tokenStatus === 200
                    ? ['access_token' => 'tok-1', 'expires_in' => 1800, 'scope' => '', 'token_type' => 'bearer']
                    : ['errors' => [['code' => 'invalidClient', 'detail' => 'Client authentication failed.']]], $this->tokenStatus),
                str_contains($url, '/accounts/acc-1/transactions') && str_contains($url, 'page[after]=p1') => Http::response(['data' => $this->pages[2], 'links' => []]),
                str_contains($url, '/accounts/acc-1/transactions') => Http::response(['data' => $this->pages[1], 'links' => ['next' => self::API.'/accounts/acc-1/transactions?page[after]=p1&page[limit]=100']]),
                str_contains($url, '/accounts') => Http::response(['data' => [[
                    'type' => 'account', 'id' => 'acc-1',
                    'attributes' => ['reference' => 'NL91ABNA0417164300', 'referenceType' => 'IBAN', 'description' => 'Zakelijk', 'currency' => 'EUR',
                        'currentBalance' => 1234.56, 'availableBalance' => 1200, 'authorizationExpirationExpectedAt' => now()->addDays(10)->toIso8601String(), 'holderName' => 'Voorbeeld B.V.'],
                    'meta' => ['synchronizedAt' => now()->subHour()->toIso8601String(), 'availability' => 'available'],
                ]], 'links' => []]),
                default => Http::response(['errors' => [['code' => 'notFound']]], 404),
            };
        });
    }

    private function tx(string $id, float $amount, string $remittance, ?string $iban = null, ?string $e2e = null): array
    {
        return ['type' => 'transaction', 'id' => $id, 'attributes' => [
            'amount' => $amount, 'currency' => 'EUR', 'remittanceInformation' => $remittance, 'remittanceInformationType' => 'unstructured',
            'counterpartName' => 'Tegenpartij '.$id, 'counterpartReference' => $iban, 'endToEndId' => $e2e,
            'valueDate' => '2026-10-20T00:00:00.000Z', 'executionDate' => '2026-10-20T00:00:00.000Z', 'createdAt' => '2026-10-20T09:00:00.000Z',
        ]];
    }

    private function documents(): array
    {
        $klant = Company::factory()->create(['iban' => 'NL18 RABO 0123 4598 76']);
        $make = fn (array $a) => Invoice::factory()->create($a + ['status' => 'definitief', 'date' => '2026-10-01', 'due_date' => '2026-10-15', 'total_excl' => 0, 'total_vat' => 0]);

        return [
            'exact' => $make(['number' => 200100, 'total_incl' => 121, 'total_open' => 121]),
            'deel' => $make(['number' => 200101, 'total_incl' => 100, 'total_open' => 100]),
            'iban' => $make(['number' => 200102, 'company_id' => $klant->id, 'total_incl' => 75, 'total_open' => 75]),
            'klein' => $make(['number' => 200103, 'total_incl' => 30, 'total_open' => 30]),
            'inkoop' => PurchaseInvoice::create(['booking_number' => 1201, 'invoice_number' => 'INK-9', 'supplier_id' => Company::factory()->supplier()->create()->id,
                'phase' => 'akkoord', 'total_incl' => 60.5, 'total_open' => 60.5]),
        ];
    }

    public function test_ophalen_slaat_op_maakt_voorstellen_en_start_geen_synchronisatie(): void
    {
        $docs = $this->documents();
        $this->artisan('werkbank:bank-ophalen')->expectsOutputToContain('5 nieuwe transactie(s), 4 met een koppelvoorstel')->assertSuccessful();

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/oauth2/token') && $r['grant_type'] === 'client_credentials'
            && $r->hasHeader('Authorization', 'Basic '.base64_encode('client-123:geheim')));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/transactions') && $r->hasHeader('Authorization', 'Bearer tok-1'));
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'synchronizations') || $r->method() !== 'GET' && ! str_ends_with($r->url(), '/oauth2/token'));

        $account = BankAccount::sole();
        $this->assertSame('NL91ABNA0417164300', $account->iban);
        $this->assertSame('1234.56', $account->current_balance);
        $this->assertTrue($account->authorizationExpiresSoon());
        $this->assertSame(5, BankTransaction::count());

        $p = fn (string $id) => BankTransaction::where('ponto_id', $id)->sole();
        $this->assertTrue($p('t1')->proposal->is($docs['exact']) && $p('t1')->proposal_certain);
        $this->assertTrue($p('t2')->proposal->is($docs['inkoop']) && $p('t2')->proposal_certain);
        $this->assertTrue($p('t3')->proposal->is($docs['deel']) && ! $p('t3')->proposal_certain);
        $this->assertTrue($p('t4')->proposal->is($docs['iban']) && ! $p('t4')->proposal_certain);
        $this->assertSame('nieuw', $p('t5')->status);
        $this->assertSame('2026-10-20', $p('t1')->date->toDateString());
        $this->assertSame('NL20INGB0001234567', $p('t1')->counterpart_iban);

        // Er is niets geboekt
        $this->assertSame(0, Payment::count() + PurchasePayment::count());
        $this->assertSame('121.00', $docs['exact']->fresh()->total_open);
    }

    public function test_tweede_run_is_idempotent_en_stopt_bij_bekende_pagina(): void
    {
        $this->documents();
        $this->artisan('werkbank:bank-ophalen')->assertSuccessful();
        $page2 = fn () => Http::recorded(fn (Request $r) => str_contains(urldecode($r->url()), 'page[after]=p1'))->count();
        $before = BankTransaction::count();
        $this->pages[1][] = $this->tx('t6', 10.00, 'nieuw');
        $this->artisan('werkbank:bank-ophalen')->expectsOutputToContain('1 nieuwe transactie(s)')->assertSuccessful();
        $this->assertSame($before + 1, BankTransaction::count());
        $this->assertSame(1, BankTransaction::where('ponto_id', 't1')->count());

        // Niets nieuws op de eerste pagina: verder bladeren is niet nodig.
        $requested = $page2();
        $this->artisan('werkbank:bank-ophalen')->expectsOutputToContain('0 nieuwe transactie(s)')->assertSuccessful();
        $this->assertSame($requested, $page2());
    }

    public function test_bevestigen_boekt_betalingen(): void
    {
        $docs = $this->documents();
        $this->artisan('werkbank:bank-ophalen');
        $user = $this->actingAsRole('administratie');
        $p = fn (string $id) => BankTransaction::where('ponto_id', $id)->sole();

        $this->get('/bank')->assertOk()->assertSee('Factuur 200100')->assertSee('Inkoopfactuur 1201')->assertSee('Zekere voorstellen boeken (2)')->assertSee('toestemming verloopt');
        $this->post('/bank/zeker-boeken')->assertRedirect()->assertSessionHas('status', '2 betaling(en) geboekt.');
        $this->assertSame('0.00', $docs['exact']->fresh()->total_open);
        $this->assertSame('0.00', $docs['inkoop']->fresh()->total_open);
        $this->assertSame('gekoppeld', $p('t1')->status);
        $this->assertInstanceOf(Payment::class, $p('t1')->payment);
        $this->assertInstanceOf(PurchasePayment::class, $p('t2')->payment);
        $this->assertSame('60.50', $p('t2')->payment->amount);
        $this->assertSame($user->id, $p('t1')->handled_by);
        $this->assertSame(2, AuditLog::where('event', 'bank_geboekt')->count());

        // Deelbetaling met bevestiging
        $this->post(route('bank.boeken', $p('t3')))->assertRedirect();
        $this->assertSame('50.00', $docs['deel']->fresh()->total_open);
        // Nogmaals boeken kan niet
        $this->post(route('bank.boeken', $p('t3')))->assertSessionHasErrors('bank');
        $this->assertSame(1, Payment::where('invoice_id', $docs['deel']->id)->count());

        // Zelf kiezen: niet meer dan openstaat, juiste richting, bestaand nummer
        $this->post(route('bank.boeken', $p('t4')), ['soort' => 'factuur', 'nummer' => 200103])->assertSessionHasErrors('bank'); // 75 > 30 open
        $this->post(route('bank.boeken', $p('t5')), ['soort' => 'factuur', 'nummer' => 200103])->assertSessionHasErrors('bank'); // afschrijving op verkoopfactuur
        $this->post(route('bank.boeken', $p('t5')), ['soort' => 'factuur', 'nummer' => 999999])->assertSessionHasErrors('nummer');
        $this->post(route('bank.boeken', $p('t4')), ['soort' => 'factuur', 'nummer' => 200102])->assertRedirect();
        $this->assertSame('0.00', $docs['iban']->fresh()->total_open);

        // Negeren en terugzetten
        $this->post(route('bank.negeren', $p('t5')))->assertRedirect();
        $this->assertSame('genegeerd', $p('t5')->status);
        $this->get('/bank?status=genegeerd')->assertOk()->assertSee('Kosten betaalpakket');
        $this->post(route('bank.heropenen', $p('t5')))->assertRedirect();
        $this->assertSame('nieuw', $p('t5')->status);
    }

    public function test_voorstel_vervalt_als_factuur_al_betaald_is(): void
    {
        $docs = $this->documents();
        $this->artisan('werkbank:bank-ophalen');
        $docs['exact']->payments()->create(['date' => '2026-10-19', 'amount' => 121]);
        $docs['exact']->refreshPaid();
        $this->artisan('werkbank:bank-ophalen');
        $t1 = BankTransaction::where('ponto_id', 't1')->sole();
        $this->assertSame('nieuw', $t1->status);
        $this->assertNull($t1->proposal_id);
    }

    public function test_rechten_en_niet_ingesteld(): void
    {
        $this->actingAsRole('projectleider');
        $this->get('/bank')->assertForbidden();

        config(['services.ponto.client_id' => null]);
        $this->actingAsRole('administratie');
        $this->get('/bank')->assertOk()->assertSee('Niet ingesteld');
        $this->post('/bank/ophalen')->assertStatus(422);
        $this->artisan('werkbank:bank-ophalen')->expectsOutputToContain('niet ingesteld')->assertSuccessful();
        Http::assertNothingSent();
    }

    public function test_foutmelding_van_ponto(): void
    {
        $this->tokenStatus = 401;
        $this->actingAsRole('administratie');
        $this->post('/bank/ophalen')->assertRedirect()->assertSessionHasErrors(['bank' => 'Ophalen mislukt: Aanmelden bij Ponto mislukt (401): Client authentication failed. Controleer PONTO_CLIENT_ID en PONTO_CLIENT_SECRET.']);
        $this->assertSame(0, BankTransaction::count());
    }
}
