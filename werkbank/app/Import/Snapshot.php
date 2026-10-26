<?php

namespace App\Import;

use RuntimeException;

/**
 * Leest een snapshot uit een back-upmap van de Gripp Back-up-tool (zie README in de hoofdmap):
 *   <back-upmap>/data/current/raw/<entiteit>.json   ruwe API-records
 *   <back-upmap>/data/current/state.json            welke bijlagen en factuur-PDF's er zijn
 *   <back-upmap>/data/current/documents.json        PDF's van offertes, opdrachten, inkoopopdrachten
 *   <back-upmap>/archive/index.json                 bijlagen over runs heen
 *   <back-upmap>/archive/...                        de bestanden zelf
 * Alleen lezen: de back-upmap wordt nooit gewijzigd.
 */
final class Snapshot
{
    public readonly string $dataDir;

    public readonly string $archiveDir;

    private array $cache = [];

    private ?array $fileIndex = null;

    private ?array $state = null;

    public function __construct(string $path, string $snapshot = 'current')
    {
        $path = rtrim($path, '/\\');
        if (! is_dir($path)) {
            throw new RuntimeException("Back-upmap niet gevonden: {$path}");
        }
        if (! preg_match('#^(current|previous|history/[A-Za-z0-9_.-]+)$#', $snapshot)) {
            throw new RuntimeException("Ongeldige snapshot: {$snapshot}");
        }
        // Zowel de back-upmap zelf als een snapshotmap (met raw/) wordt geaccepteerd.
        if (is_dir($path.'/raw')) {
            $this->dataDir = $path;
            // data/current → ../../archive, data/history/<x> → ../../../archive
            $this->archiveDir = is_dir(dirname($path, 2).'/archive') ? dirname($path, 2).'/archive' : dirname($path, 3).'/archive';
        } else {
            $this->dataDir = $path.'/data/'.$snapshot;
            $this->archiveDir = $path.'/archive';
        }
        if (! is_dir($this->dataDir.'/raw')) {
            throw new RuntimeException("Geen snapshot gevonden in {$this->dataDir} (map raw/ ontbreekt). Maak eerst een back-up.");
        }
    }

    public function batch(): ?string
    {
        return $this->state()['batch'] ?? basename($this->dataDir);
    }

    public function status(): ?string
    {
        return $this->state()['status'] ?? null;
    }

    public function has(string $entity): bool
    {
        return is_file($this->dataDir.'/raw/'.$entity.'.json');
    }

    /** @return list<array<string, mixed>> */
    public function rows(string $entity): array
    {
        if (! isset($this->cache[$entity])) {
            $file = $this->dataDir.'/raw/'.$entity.'.json';
            $rows = is_file($file) ? json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR) : [];
            $this->cache[$entity] = is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
        }

        return $this->cache[$entity];
    }

    public function forget(string $entity): void
    {
        unset($this->cache[$entity]);
    }

    /** Bijlage met Gripp file-id: ['name', 'path' (absoluut), 'size', 'sha256'] of null. */
    public function file(int|string $id): ?array
    {
        $this->fileIndex ??= $this->loadIndex('files');

        return $this->resolve($this->fileIndex[(string) $id] ?? null);
    }

    /** Factuur-PDF bij een Gripp-factuur-id. */
    public function invoicePdf(int|string $invoiceId): ?array
    {
        $idx = $this->cache['__invoicePdfs'] ??= $this->loadIndex('invoicePdfs');

        return $this->resolve($idx[(string) $invoiceId] ?? null);
    }

    /** Document-PDF (offer, project, purchaseorder) uit documents.json. */
    public function document(string $entity, int|string $id): ?array
    {
        $docs = $this->cache['__documents'] ??= $this->readJson($this->dataDir.'/documents.json') ?? [];

        return $this->resolve($docs[$entity][(string) $id] ?? null);
    }

    private function resolve(?array $e): ?array
    {
        if (! $e || empty($e['ok']) || empty($e['path'])) {
            return null;
        }
        $rel = str_replace('\\', '/', $e['path']);
        if (str_contains($rel, '..')) {
            return null; // nooit buiten de archiefmap
        }
        $abs = $this->archiveDir.'/'.$rel;

        return is_file($abs) ? ['name' => $e['name'] ?? basename($rel), 'path' => $abs, 'size' => $e['size'] ?? filesize($abs), 'sha256' => $e['sha256'] ?? null] : null;
    }

    private function loadIndex(string $key): array
    {
        $fromState = array_filter($this->state()[$key] ?? [], fn ($e) => ! empty($e['ok']));
        $index = $this->readJson($this->archiveDir.'/index.json')[$key] ?? [];

        return $fromState + $index;
    }

    private function state(): array
    {
        return $this->state ??= $this->readJson($this->dataDir.'/state.json') ?? [];
    }

    private function readJson(string $file): ?array
    {
        if (! is_file($file)) {
            return null;
        }
        $v = json_decode((string) file_get_contents($file), true);

        return is_array($v) ? $v : null;
    }
}
