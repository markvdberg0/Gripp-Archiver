<?php

namespace App\Import;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Model;

/**
 * Zet één Gripp-entiteit om naar één Werkbank-model. Verhoog VERSION als de omzetting verandert:
 * de versie zit in de hash, zodat een volgende import de records opnieuw verwerkt.
 */
abstract class Mapper
{
    public const VERSION = 1;

    /** Naam van de Gripp-entiteit (bestandsnaam in raw/). */
    abstract public function entity(): string;

    /** @return class-string<Model> */
    abstract public function model(): string;

    /**
     * Attributen voor het model, of null om het record over te slaan.
     * Gooi een ImportSkip om over te slaan met een reden.
     */
    abstract public function map(array $row, Context $ctx): ?array;

    /** Na opslaan: koppelingen (labels, bijlagen, …). */
    public function after(Model $model, array $row, Context $ctx): void {}

    /** Extra invoer voor de hash (bv. of een bijlage inmiddels beschikbaar is). */
    public function hashExtra(array $row, Context $ctx): string
    {
        return '';
    }

    /** Het deel van het bronrecord dat in de hash gaat. */
    public function hashRow(array $row): array
    {
        return $row;
    }

    /** Records zonder id kunnen niet idempotent worden geïmporteerd. */
    public function grippId(array $row): ?int
    {
        return Gripp::id($row['id'] ?? null);
    }

    /**
     * Controles na de import (bron tegenover Werkbank). Standaard: aantal records.
     *
     * @return list<array{label: string, source: string|int, local: string|int, ok: bool}>
     */
    public function checks(Context $ctx): array
    {
        $source = count($ctx->snapshot->rows($this->entity()));
        $class = $this->model();
        $q = $class::query()->whereNotNull('gripp_id');
        if (method_exists($class, 'bootSoftDeletes')) {
            $q->withTrashed();
        }
        $local = $q->count();

        return [['label' => $this->entity().': aantal records', 'source' => $source, 'local' => $local, 'ok' => $source === $local]];
    }

    /** Bijlagen (veld files e.d.) koppelen aan het model. */
    protected function attachFiles(Model $model, array $row, Context $ctx, array $fields = ['files']): void
    {
        if ($ctx->dryRun || ! method_exists($model, 'files')) {
            return;
        }
        $ids = [];
        foreach ($fields as $f) {
            foreach (Gripp::ids($row[$f] ?? null) as $gid) {
                $label = collect((array) ($row[$f] ?? []))->first(fn ($v) => is_array($v) && Gripp::id($v) === $gid);
                if ($file = $ctx->storedFile($gid, is_array($label) ? Gripp::label($label) : null)) {
                    $ids[$file->id] = ['role' => 'bijlage', 'created_at' => now()];
                }
            }
        }
        $model->files()->wherePivot('role', 'bijlage')->syncWithoutDetaching($ids);
    }

    protected function syncTags(Model $model, array $row, Context $ctx): void
    {
        if (! $ctx->dryRun && method_exists($model, 'tags')) {
            $model->tags()->sync($ctx->ids(Tag::class, $row['tags'] ?? []));
        }
    }
}
