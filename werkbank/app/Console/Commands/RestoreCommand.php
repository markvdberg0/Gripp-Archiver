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
        $result = Backup::restore($path, ! $this->option('zonder-veiligheidsbackup'));
        if ($result['safety']) {
            $this->line("Back-up van de toestand vóór het herstel: {$result['safety']}");
        }
        $this->info('Herstel voltooid.');

        return self::SUCCESS;
    }
}
