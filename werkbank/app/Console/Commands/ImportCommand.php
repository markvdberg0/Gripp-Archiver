<?php

namespace App\Console\Commands;

use App\Import\Importer;
use App\Models\ImportRun;
use Illuminate\Console\Command;

class ImportCommand extends Command
{
    protected $signature = 'werkbank:import
        {pad? : Back-upmap van de Gripp Back-up (standaard WERKBANK_IMPORT_PATH)}
        {--droogloop : Alleen controleren; er wordt niets opgeslagen}
        {--alleen= : Alleen deze entiteiten, bv. company,contact}
        {--snapshot=current : current, previous of history/<naam>}';

    protected $description = 'Importeer een snapshot uit de Gripp Back-up (idempotent, met rapport)';

    public function handle(Importer $importer): int
    {
        $path = $this->argument('pad') ?? config('werkbank.import_path');
        if (! $path) {
            $this->error('Geef een back-upmap op, of zet WERKBANK_IMPORT_PATH in .env.');

            return self::FAILURE;
        }
        $run = ImportRun::create([
            'source_path' => $path,
            'snapshot' => $this->option('snapshot'),
            'dry_run' => (bool) $this->option('droogloop'),
            'only' => $this->option('alleen'),
        ]);
        $this->info(($run->dry_run ? 'Droogloop' : 'Import')." #{$run->id} uit {$path}");
        $importer->run($run, fn (string $line) => $this->line('  '.$line));

        $t = $run->totals();
        $f = $run->summary['files'] ?? [];
        $this->newLine();
        $this->line(sprintf('Totaal: %d nieuw, %d gewijzigd, %d ongewijzigd, %d conflicten, %d overgeslagen, %d fouten. Bestanden: %d opgeslagen, %d zonder inhoud. Duur %ss.',
            $t['created'], $t['updated'], $t['unchanged'], $t['conflicts'], $t['skipped'], $t['errors'], $f['copied'] ?? 0, $f['missing'] ?? 0, $run->summary['seconds'] ?? '?'));
        foreach ($run->issues ?? [] as $entity => $issues) {
            $this->warn("{$entity}: ".count($issues).' melding(en), bv. '.$issues[0]['message']);
        }
        if ($run->status !== 'geslaagd') {
            $this->error('Mislukt: '.$run->error.' Er is niets gewijzigd.');

            return self::FAILURE;
        }
        $this->info($run->dry_run ? 'Droogloop klaar: er is niets opgeslagen.' : 'Import klaar.');

        return self::SUCCESS;
    }
}
