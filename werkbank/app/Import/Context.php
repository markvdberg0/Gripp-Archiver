<?php

namespace App\Import;

use App\Models\StoredFile;
use App\Models\VatRate;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Mime\MimeTypes;

/** Gedeelde toestand tijdens één importrun: id-koppelingen, tellers en meldingen. */
final class Context
{
    public const MAX_ISSUES_PER_ENTITY = 200;

    /** @var array<class-string, array<int, int>> gripp_id → lokaal id */
    private array $maps = [];

    public array $stats = [];

    public array $issues = [];

    public array $files = ['copied' => 0, 'reused' => 0, 'missing' => 0];

    public function __construct(public readonly Snapshot $snapshot, public readonly bool $dryRun) {}

    /** Lokaal id bij een Gripp-verwijzing, of null als het doel (nog) niet bestaat. */
    public function id(string $modelClass, mixed $ref): ?int
    {
        $gid = Gripp::id($ref);
        if ($gid === null) {
            return null;
        }
        $this->maps[$modelClass] ??= $modelClass::query()->withoutGlobalScopes()->whereNotNull('gripp_id')->pluck('id', 'gripp_id')->all();

        return $this->maps[$modelClass][$gid] ?? null;
    }

    /** @return list<int> */
    public function ids(string $modelClass, mixed $refs): array
    {
        return array_values(array_filter(array_map(fn ($r) => $this->id($modelClass, $r), Gripp::ids($refs) ?: [])));
    }

    /**
     * Btw-tarief bij een Gripp-verwijzing ({ id, searchname: 'BTW 21%' }). Gripp levert geen tabel met
     * tarieven; ze worden aangemaakt uit de verwijzingen, met het percentage uit de naam ("nul" = 0%).
     */
    public function vatRate(mixed $ref, string $kind): ?int
    {
        $gid = Gripp::id($ref);
        if ($gid === null) {
            return null;
        }
        if ($id = $this->id(VatRate::class, $gid)) {
            return $id;
        }
        $name = Gripp::label($ref) ?? "Btw {$gid}";
        $pct = preg_match('/(\d+(?:[.,]\d+)?)\s*%/', $name, $m) ? (float) str_replace(',', '.', $m[1]) : 0.0;
        $rate = VatRate::create(['gripp_id' => $gid, 'name' => mb_substr($name, 0, 100), 'percentage' => $pct, 'kind' => $kind, 'active' => true, 'gripp_synced_at' => now()]);
        $this->maps[VatRate::class][$gid] = $rate->id;

        return $rate->id;
    }

    public function remember(Model $model): void
    {
        if (isset($this->maps[$model::class])) {
            $this->maps[$model::class][(int) $model->gripp_id] = $model->getKey();
        }
    }

    public function count(string $entity, string $what, int $n = 1): void
    {
        $this->stats[$entity][$what] = ($this->stats[$entity][$what] ?? 0) + $n;
    }

    public function issue(string $entity, mixed $grippId, string $type, string $message): void
    {
        $this->count($entity, 'issues');
        if (count($this->issues[$entity] ?? []) < self::MAX_ISSUES_PER_ENTITY) {
            $this->issues[$entity][] = ['gripp_id' => $grippId, 'type' => $type, 'message' => mb_substr($message, 0, 500)];
        }
    }

    /**
     * Bestandsrecord voor een Gripp file-id, met inhoud uit het archief (ontdubbeld op SHA-256).
     * Bij een droogloop wordt niets gekopieerd en geen record aangemaakt.
     */
    public function storedFile(int $grippFileId, ?string $title = null): ?StoredFile
    {
        $existing = StoredFile::where('gripp_id', $grippFileId)->first();
        $info = $this->snapshot->file($grippFileId);
        if ($existing && ($existing->path !== null || ! $info)) {
            return $existing;
        }

        return $this->storeFrom($info, $existing ?? new StoredFile(['gripp_id' => $grippFileId]), $title);
    }

    /** Bestand uit het archief vastleggen (zonder Gripp file-id, bv. een factuur-PDF). */
    public function storeArchived(?array $info, string $name): ?StoredFile
    {
        if (! $info) {
            return null;
        }
        if ($info['sha256'] && $found = StoredFile::where('sha256', $info['sha256'])->whereNull('gripp_id')->where('name', $name)->first()) {
            $this->files['reused']++;

            return $found;
        }

        return $this->storeFrom($info, new StoredFile, $name);
    }

    private function storeFrom(?array $info, StoredFile $file, ?string $name): ?StoredFile
    {
        if (! $info) {
            $this->files['missing']++;
        }
        if ($this->dryRun) {
            if ($info) {
                $this->files['copied']++;
            }

            return $file->exists ? $file : null;
        }
        $file->name = mb_substr($name ?: ($info['name'] ?? 'bestand-'.$file->gripp_id), 0, 255);
        if ($info) {
            $stored = StoredFile::storeContent($info['path']);
            $file->fill($stored);
            $file->mime_type = MimeTypes::getDefault()->guessMimeType($info['path']) ?? 'application/octet-stream';
            $this->files['copied']++;
        }
        $file->gripp_synced_at = now();
        $file->save();

        return $file;
    }
}
