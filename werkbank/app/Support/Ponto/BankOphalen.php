<?php

namespace App\Support\Ponto;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Support\Audit;
use App\Support\Iban;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Rekeningen en transacties uit Ponto overnemen (alleen lezen) en daarna koppelvoorstellen maken.
 * Herhaalbaar: een transactie wordt op het Ponto-kenmerk één keer opgeslagen.
 */
class BankOphalen
{
    public function __construct(private PontoClient $client, private Koppelvoorstellen $matcher) {}

    /** @return array{accounts: int, new: int, known: int, proposals: int} */
    public function run(): array
    {
        // Niet twee runs tegelijk (scheduler en knop).
        return Cache::lock('ponto.ophalen', 600)->block(5, function () {
            $stats = ['accounts' => 0, 'new' => 0, 'known' => 0, 'proposals' => 0];
            foreach ($this->client->accounts() as $resource) {
                $account = $this->account($resource);
                $stats['accounts']++;
                $firstRun = ! $account->transactions()->exists();
                foreach ($this->client->transactionPages($account->ponto_id) as $page) {
                    $newOnPage = 0;
                    foreach ($page as $tx) {
                        if (BankTransaction::where('ponto_id', $tx['id'])->exists()) {
                            $stats['known']++;

                            continue;
                        }
                        $this->store($account, $tx);
                        $newOnPage++;
                        $stats['new']++;
                    }
                    // Ponto geeft de nieuwste transacties eerst: een pagina zonder nieuwe betekent dat we bij zijn.
                    if (! $firstRun && $newOnPage === 0 && $page !== []) {
                        break;
                    }
                }
                $account->forceFill(['fetched_at' => now()])->save();
            }
            $stats['proposals'] = $this->matcher->run();
            Audit::log('bank_opgehaald', null, "Ponto: {$stats['accounts']} rekening(en), {$stats['new']} nieuwe transactie(s), {$stats['proposals']} voorstel(len)");

            return $stats;
        });
    }

    private function account(array $r): BankAccount
    {
        $a = $r['attributes'] ?? [];
        $meta = $r['meta'] ?? [];

        return BankAccount::updateOrCreate(['ponto_id' => $r['id']], [
            'iban' => ($a['referenceType'] ?? 'IBAN') === 'IBAN' ? Iban::normalize((string) ($a['reference'] ?? '')) ?: null : null,
            'description' => mb_substr((string) ($a['description'] ?? $a['product'] ?? ''), 0, 255) ?: null,
            'holder_name' => mb_substr((string) ($a['holderName'] ?? ''), 0, 255) ?: null,
            'currency' => $a['currency'] ?? 'EUR',
            'current_balance' => $a['currentBalance'] ?? null,
            'available_balance' => $a['availableBalance'] ?? null,
            'authorization_expires_at' => self::time($a['authorizationExpirationExpectedAt'] ?? null),
            'synchronized_at' => self::time($meta['synchronizedAt'] ?? null),
            'availability' => $meta['availability'] ?? null,
        ]);
    }

    private function store(BankAccount $account, array $r): void
    {
        $a = $r['attributes'] ?? [];
        $date = self::time($a['valueDate'] ?? null) ?? self::time($a['executionDate'] ?? null) ?? self::time($a['createdAt'] ?? null) ?? now();
        $counterpart = $a['counterpartName'] ?? null;
        DB::transaction(fn () => BankTransaction::create([
            'ponto_id' => $r['id'],
            'bank_account_id' => $account->id,
            'date' => $date->toDateString(),
            'amount' => round((float) ($a['amount'] ?? 0), 2),
            'currency' => $a['currency'] ?? 'EUR',
            'counterpart_name' => is_string($counterpart) ? mb_substr($counterpart, 0, 255) : null,
            'counterpart_iban' => isset($a['counterpartReference']) ? mb_substr(Iban::normalize((string) $a['counterpartReference']), 0, 40) : null,
            'remittance' => $a['remittanceInformation'] ?? null,
            'description' => $a['description'] ?? $a['additionalInformation'] ?? null,
            'end_to_end_id' => isset($a['endToEndId']) ? mb_substr((string) $a['endToEndId'], 0, 35) : null,
        ]));
    }

    private static function time(?string $v): ?CarbonImmutable
    {
        return $v ? CarbonImmutable::parse($v)->setTimezone(config('app.timezone')) : null;
    }
}
