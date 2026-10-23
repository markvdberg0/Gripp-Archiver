<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Back-up en herstel van Werkbank, onafhankelijk van het databasetype.
 *
 * Een back-up is één zip-bestand met:
 *   manifest.json            versie, tijdstip, migraties, aantallen en SHA-256 per onderdeel
 *   database/<tabel>.jsonl   alle rijen, één JSON-object per regel
 *   bestanden/…              de opgeslagen bestanden (ontdubbeld op SHA-256)
 * Zo kan een back-up van SQLite ook op MariaDB of PostgreSQL worden teruggezet.
 */
final class Backup
{
    /** Tijdelijke of afgeleide tabellen die niet in een back-up horen. */
    private const SKIP = ['migrations', 'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens'];

    public static function directory(): string
    {
        $dir = config('werkbank.backup_path') ?: storage_path('app/backups');
        File::ensureDirectoryExists($dir);

        return rtrim($dir, '/\\');
    }

    /** @return list<string> */
    public static function tables(): array
    {
        $tables = array_map(fn ($t) => $t['name'], Schema::getTables());

        return array_values(array_diff(array_unique($tables), self::SKIP));
    }

    /** Maakt een back-up en geeft het pad terug. */
    public static function create(?string $label = null): string
    {
        $name = 'werkbank-backup-'.now()->format('Ymd-His').($label ? '-'.preg_replace('/[^a-z0-9-]/i', '', $label) : '').'.zip';
        $path = self::directory().DIRECTORY_SEPARATOR.$name;
        $tmp = $path.'.tmp';
        $zip = new ZipArchive;
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Kan {$tmp} niet aanmaken.");
        }

        $manifest = [
            'app' => 'werkbank', 'format' => 1, 'created_at' => now()->toIso8601String(), 'app_name' => config('app.name'),
            'driver' => DB::connection()->getDriverName(),
            'migrations' => DB::table('migrations')->orderBy('id')->pluck('migration')->all(),
            'tables' => [], 'files' => [],
        ];
        $scratch = [];
        // Alle tabellen in één leestransactie: een samenhangende momentopname, ook als er tijdens de
        // back-up wordt gewerkt.
        DB::transaction(function () use (&$scratch, &$manifest, $zip) {
            foreach (self::tables() as $table) {
                $file = tempnam(sys_get_temp_dir(), 'wb');
                $scratch[] = $file;
                $out = fopen($file, 'wb');
                $rows = 0;
                // Op id gesorteerd: dan staat een record vóór records die ernaar verwijzen in dezelfde tabel
                // (bv. een creditfactuur na de factuur), wat PostgreSQL bij het terugzetten vereist.
                $query = DB::table($table);
                if (Schema::hasColumn($table, 'id')) {
                    $query->orderBy('id');
                }
                foreach ($query->cursor() as $row) {
                    fwrite($out, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)."\n");
                    $rows++;
                }
                fclose($out);
                $zip->addFile($file, "database/{$table}.jsonl");
                $manifest['tables'][$table] = ['rows' => $rows, 'sha256' => hash_file('sha256', $file)];
            }
        });

        $disk = Storage::disk('local');
        foreach ($disk->allFiles('bestanden') as $rel) {
            $abs = $disk->path($rel);
            $zip->addFile($abs, $rel);
            $manifest['files'][$rel] = hash_file('sha256', $abs);
        }
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        if (! $zip->close()) {
            throw new RuntimeException('Schrijven van de back-up is mislukt.');
        }
        foreach ($scratch as $f) {
            @unlink($f);
        }
        rename($tmp, $path);
        Audit::log('backup', null, $name, ['tabellen' => count($manifest['tables']), 'bestanden' => count($manifest['files'])]);

        return $path;
    }

    /**
     * Controleert een back-up zonder iets te wijzigen.
     *
     * @return array manifest
     */
    public static function verify(string $path): array
    {
        $zip = self::open($path);
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        if (! is_array($manifest) || ($manifest['app'] ?? null) !== 'werkbank') {
            throw new RuntimeException('Dit is geen Werkbank-back-up (manifest.json ontbreekt of is ongeldig).');
        }
        foreach ($manifest['tables'] as $table => $meta) {
            $data = $zip->getFromName("database/{$table}.jsonl");
            if ($data === false || hash('sha256', $data) !== $meta['sha256']) {
                throw new RuntimeException("Controlegetal klopt niet voor tabel {$table}: de back-up is beschadigd of gewijzigd.");
            }
        }
        foreach ($manifest['files'] as $rel => $sha) {
            $data = $zip->getFromName($rel);
            if ($data === false || hash('sha256', $data) !== $sha) {
                throw new RuntimeException("Controlegetal klopt niet voor bestand {$rel}.");
            }
        }
        $known = array_map(fn ($f) => pathinfo($f, PATHINFO_FILENAME), File::glob(database_path('migrations/*.php')));
        if ($unknown = array_diff($manifest['migrations'], $known)) {
            throw new RuntimeException('Deze back-up komt van een nieuwere versie van Werkbank (onbekende migraties: '.implode(', ', $unknown).'). Werk Werkbank eerst bij.');
        }
        $zip->close();

        return $manifest;
    }

    /**
     * Zet een back-up terug. Alle huidige gegevens worden vervangen. Vooraf wordt automatisch een
     * back-up van de huidige toestand gemaakt (tenzij $safetyBackup false is).
     *
     * @return array{manifest: array, safety: ?string}
     */
    public static function restore(string $path, bool $safetyBackup = true): array
    {
        $manifest = self::verify($path);
        $safety = $safetyBackup && Schema::hasTable('migrations') ? self::create('voor-herstel') : null;
        $zip = self::open($path);

        // 1. Schema van de back-up opbouwen: database leeg, dan precies de migraties van de back-up.
        Artisan::call('db:wipe', ['--force' => true]);
        $paths = array_map(fn ($m) => database_path("migrations/{$m}.php"), $manifest['migrations']);
        Artisan::call('migrate', ['--force' => true, '--path' => $paths, '--realpath' => true]);

        // 2. Gegevens terugzetten, in één transactie: mislukt een rij, dan blijft geen half gevulde database achter
        //    (wel een lege; zet dan de veiligheidsback-up terug).
        Schema::disableForeignKeyConstraints();
        DB::beginTransaction();
        try {
            $present = array_values(array_filter(array_keys($manifest['tables']), fn ($t) => Schema::hasTable($t)));
            foreach (self::insertOrder($present) as $table) {
                // Booleans: SQLite en MariaDB bewaren 0/1, PostgreSQL true/false. Omzetten maakt een back-up
                // uitwisselbaar tussen databasetypen.
                $booleans = array_column(array_filter(Schema::getColumns($table), fn ($c) => in_array(strtolower($c['type_name']), ['bool', 'boolean'], true)), 'name');
                $stream = $zip->getStream("database/{$table}.jsonl");
                $batch = [];
                while (($line = fgets($stream)) !== false) {
                    if (trim($line) === '') {
                        continue;
                    }
                    $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                    foreach ($booleans as $col) {
                        if (isset($row[$col])) {
                            $row[$col] = (bool) $row[$col];
                        }
                    }
                    $batch[] = $row;
                    if (count($batch) >= 200) {
                        DB::table($table)->insert($batch);
                        $batch = [];
                    }
                }
                if ($batch) {
                    DB::table($table)->insert($batch);
                }
                fclose($stream);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw new RuntimeException('Terugzetten van de gegevens is mislukt: '.$e->getMessage(), 0, $e);
        } finally {
            Schema::enableForeignKeyConstraints();
        }
        self::resetSequences(array_keys($manifest['tables']));

        // 3. Latere migraties (back-up van een oudere versie) alsnog uitvoeren.
        Artisan::call('migrate', ['--force' => true]);

        // 4. Bestanden terugzetten.
        $disk = Storage::disk('local');
        foreach (array_keys($manifest['files']) as $rel) {
            if (! str_starts_with($rel, 'bestanden/') || str_contains($rel, '..')) {
                continue;
            }
            $disk->put($rel, $zip->getFromName($rel));
        }
        $zip->close();
        Setting::flush();
        Cache::forget('werkbank.vat');
        Cache::forget('werkbank.vat.rc');
        Audit::log('herstel', null, basename($path), ['veiligheidsback-up' => $safety ? basename($safety) : null]);

        return ['manifest' => $manifest, 'safety' => $safety];
    }

    /** Verwijdert oude back-ups; de nieuwste $keep blijven staan. */
    public static function prune(int $keep): int
    {
        $files = self::list();
        $removed = 0;
        foreach (array_slice($files, $keep) as $f) {
            if (! str_contains($f['name'], 'voor-herstel')) {
                @unlink($f['path']);
                $removed++;
            }
        }

        return $removed;
    }

    /** @return list<array{name: string, path: string, size: int, time: int}> nieuwste eerst */
    public static function list(): array
    {
        $out = [];
        foreach (File::glob(self::directory().DIRECTORY_SEPARATOR.'werkbank-backup-*.zip') as $f) {
            $out[] = ['name' => basename($f), 'path' => $f, 'size' => filesize($f), 'time' => filemtime($f)];
        }
        usort($out, fn ($a, $b) => strcmp($b['name'], $a['name']));

        return $out;
    }

    private static function open(string $path): ZipArchive
    {
        if (! is_file($path)) {
            throw new RuntimeException("Back-up niet gevonden: {$path}");
        }
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Kan de back-up niet openen (geen geldig zip-bestand).');
        }

        return $zip;
    }

    /**
     * Volgorde waarin tabellen gevuld worden: eerst de tabellen waarnaar verwezen wordt. Nodig voor PostgreSQL,
     * waar het uitschakelen van foreign keys de controle niet uitstelt. Bij een kringverwijzing komen de
     * overgebleven tabellen er in de oorspronkelijke volgorde achter.
     *
     * @param  list<string>  $tables
     * @return list<string>
     */
    public static function insertOrder(array $tables): array
    {
        $deps = [];
        foreach ($tables as $t) {
            $deps[$t] = array_values(array_unique(array_filter(
                array_map(fn ($fk) => $fk['foreign_table'], Schema::getForeignKeys($t)),
                fn ($f) => $f !== $t && in_array($f, $tables, true)
            )));
        }
        $order = [];
        while ($deps) {
            $ready = array_keys(array_filter($deps, fn ($d) => ! array_diff($d, $order)));
            if (! $ready) {
                return array_merge($order, array_keys($deps));
            }
            foreach ($ready as $t) {
                $order[] = $t;
                unset($deps[$t]);
            }
        }

        return $order;
    }

    /** PostgreSQL: reeksen (auto-increment) na het invoegen met expliciete id's bijwerken. */
    private static function resetSequences(array $tables): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }
        foreach ($tables as $table) {
            if (Schema::hasColumn($table, 'id')) {
                DB::statement("SELECT setval(pg_get_serial_sequence('\"{$table}\"', 'id'), COALESCE((SELECT MAX(id) FROM \"{$table}\"), 0) + 1, false)");
            }
        }
    }
}
