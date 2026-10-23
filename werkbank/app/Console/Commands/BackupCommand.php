<?php

namespace App\Console\Commands;

use App\Support\Backup;
use Illuminate\Console\Command;

class BackupCommand extends Command
{
    protected $signature = 'werkbank:backup {--label= : Extra label in de bestandsnaam} {--zonder-opruimen : Oude back-ups niet verwijderen}';

    protected $description = 'Maak een back-up van Werkbank (database en bestanden) in WERKBANK_BACKUP_PATH';

    public function handle(): int
    {
        $path = Backup::create($this->option('label'));
        $manifest = Backup::verify($path);
        $rows = array_sum(array_column($manifest['tables'], 'rows'));
        $this->info("Back-up gemaakt en gecontroleerd: {$path}");
        $this->line(sprintf('%d tabellen, %d rijen, %d bestanden, %.1f MB.', count($manifest['tables']), $rows, count($manifest['files']), filesize($path) / 1048576));
        if (! $this->option('zonder-opruimen')) {
            $removed = Backup::prune((int) config('werkbank.backup_keep', 14));
            if ($removed) {
                $this->line("{$removed} oude back-up(s) verwijderd (bewaard: ".config('werkbank.backup_keep', 14).').');
            }
        }

        return self::SUCCESS;
    }
}
