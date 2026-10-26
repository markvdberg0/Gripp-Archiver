<?php

namespace App\Jobs;

use App\Import\Importer;
use App\Models\ImportRun;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Import vanuit de webinterface. Er draait er altijd maar één tegelijk. */
class RunImport implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public ImportRun $run) {}

    public function uniqueId(): string
    {
        return 'import';
    }

    public function handle(Importer $importer): void
    {
        $importer->run($this->run);
    }
}
