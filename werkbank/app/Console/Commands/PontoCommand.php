<?php

namespace App\Console\Commands;

use App\Support\Ponto\BankOphalen;
use App\Support\Ponto\PontoClient;
use Illuminate\Console\Command;
use Throwable;

/** Banktransacties uit Ponto ophalen en koppelvoorstellen maken. Alleen lezen; boekt niets. */
class PontoCommand extends Command
{
    protected $signature = 'werkbank:bank-ophalen';

    protected $description = 'Haal rekeningen en transacties op uit Ponto en maak koppelvoorstellen (boekt niets)';

    public function handle(BankOphalen $ophalen): int
    {
        if (! PontoClient::configured()) {
            $this->warn('Ponto is niet ingesteld (PONTO_CLIENT_ID en PONTO_CLIENT_SECRET in .env).');

            return self::SUCCESS;
        }
        try {
            $s = $ophalen->run();
        } catch (Throwable $e) {
            report($e);
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info("{$s['accounts']} rekening(en), {$s['new']} nieuwe transactie(s), {$s['proposals']} met een koppelvoorstel.");

        return self::SUCCESS;
    }
}
