<?php

namespace Tests;

use App\Import\Importer;
use App\Models\ImportRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeBackup;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /** @var list<FakeBackup> */
    private array $backups = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        foreach ($this->backups as $b) {
            $b->cleanup();
        }
        parent::tearDown();
    }

    protected function actingAsRole(string $role): User
    {
        $user = User::factory()->role($role)->create();
        $this->actingAs($user);

        return $user;
    }

    protected function backup(): FakeBackup
    {
        return $this->backups[] = FakeBackup::make();
    }

    protected function runImport(FakeBackup $backup, bool $dryRun = false, ?string $only = null): ImportRun
    {
        $backup->write();
        $run = ImportRun::create(['source_path' => $backup->dir, 'dry_run' => $dryRun, 'only' => $only]);

        return app(Importer::class)->run($run)->refresh();
    }
}
