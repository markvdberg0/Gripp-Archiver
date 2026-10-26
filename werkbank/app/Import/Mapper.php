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

    /** Naam in rapport en bij --alleen. Standaard de Gripp-entiteit; anders als één bronbestand over meerdere modellen gaat. */
    public function key(): string
    {
        return $this->entity();
    }

    /** Hoort dit bronrecord bij deze mapper? (bv. offerteregels vs. opdrachtregels in hetzelfde bestand) */
    public function accepts(array $row, Context $ctx): bool
    {
        return true;
    }

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

    /**
     * Reden om dit record niet bij te werken omdat het bovenliggende document in Werkbank is gewijzigd
     * of verwijderd (voor regels en betalingen), of null.
     */
    public function parentConflict(array $row, Context $ctx): ?string
    {
        return null;
    }

    /** @var array<string, ?string> cache: klasse#id → reden of null */
    private array $parentState = [];

    /** Hulp voor parentConflict(): controleert het document met dit lokale id. */
    protected function parentState(string $class, ?int $id): ?string
    {
        if ($id === null) {
            return null;
        }

        return $this->parentState[$class.'#'.$id] ??= (function () use ($class, $id) {
            $query = $class::query();
            if (method_exists($class, 'bootSoftDeletes')) {
                $query->withTrashed();
            }
            $parent = $query->find($id);
            if (! $parent || (method_exists($parent, 'trashed') && $parent->trashed())) {
                return 'Het document is in Werkbank verwijderd; regel/betaling niet bijgewerkt.';
            }

            return $parent->isLocallyModified() ? "Het document ({$parent->auditLabel()}) is in Werkbank gewijzigd; regel/betaling uit Gripp niet overgenomen." : null;
        })() ?? '';
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
        $source = count(array_filter($ctx->snapshot->rows($this->entity()), fn ($r) => $this->accepts($r, $ctx)));
        $class = $this->model();
        $q = $class::query()->whereNotNull('gripp_id');
        if (method_exists($class, 'bootSoftDeletes')) {
            $q->withTrashed();
        }
        $local = $q->count();

        return [['label' => $this->key().': aantal records', 'source' => $source, 'local' => $local, 'ok' => $source === $local]];
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

    /** Een document-PDF uit het archief (offerte, opdracht, factuur) koppelen met rol "document". */
    protected function attachDocument(Model $model, ?array $info, string $name, Context $ctx): void
    {
        if ($ctx->dryRun || ! $info) {
            return;
        }
        if ($file = $ctx->storeArchived($info, $name)) {
            $model->files()->wherePivot('role', 'document')->syncWithoutDetaching([$file->id => ['role' => 'document', 'created_at' => now()]]);
        }
    }

    protected function syncTags(Model $model, array $row, Context $ctx): void
    {
        if (! $ctx->dryRun && method_exists($model, 'tags')) {
            $model->tags()->sync($ctx->ids(Tag::class, $row['tags'] ?? []));
        }
    }
}
