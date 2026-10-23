<?php

namespace App\Console\Commands;

use App\Support\Backup;
use Illuminate\Console\Command;
use Throwable;

class RestoreCommand extends Command
{
    protected $signature = 'werkbank:herstel
        {bestand : Pad naar een back-up (zip), of de bestandsnaam in WERKBANK_BACKUP_PATH}
        {--controleer : Alleen controleren, niets terugzetten}
        {--force : Niet om bevestiging vragen}
        {--zonder-veiligheidsbackup : Geen back-up van de huidige toestand maken vóór het herstel}';

    protected $description = 'Zet een Werkbank-back-up terug (vervangt alle huidige gegevens)';

    public function handle(): int
    {
        $path = $this->argument('bestand');
        if (! is_file($path)) {
            $path = Backup::directory().DIRECTORY_SEPARATOR.basename($path);
        }
        try {
            $manifest = Backup::verify($path);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $rows = array_sum(array_column($manifest['tables'], 'rows'));
        $this->info("Back-up in orde: gemaakt {$manifest['created_at']}, {$rows} rijen, ".count($manifest['files']).' bestanden.');
        if ($this->option('controleer')) {
            return self::SUCCESS;
        }
        if (! $this->option('force') && ! $this->confirm('Alle huidige gegevens in Werkbank worden vervangen door deze back-up. Doorgaan?')) {
            $this->line('Afgebroken; er is niets gewijzigd.');

            return self::FAILURE;
        }
        // Veiligheidsback-up hier maken, zodat de naam ook bij een mislukt herstel bekend is.
        $safety = $this->option('zonder-veiligheidsbackup') ? null : Backup::create('voor-herstel');
        if ($safety) {
            $this->line("Back-up van de toestand vóór het herstel: {$safety}");
        }
        try {
            Backup::restore($path, false);
        } catch (Throwable $e) {
            $this->error('Herstel mislukt: '.$e->getMessage());
            if ($safety) {
                $this->warn('Zet de vorige toestand terug met: php artisan werkbank:herstel '.basename($safety).' --zonder-veiligheidsbackup');
            }

            return self::FAILURE;
        }
        $this->info('Herstel voltooid.');

        return self::SUCCESS;
    }
}
