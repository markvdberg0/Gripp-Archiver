<?php

namespace App\Import;

use App\Models\ImportRun;
use App\Support\Audit;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Importeert een snapshot uit de Gripp Back-up in Werkbank.
 *
 * - Idempotent: elk record wordt gevonden op gripp_id; een herhaalde import maakt geen duplicaten.
 * - Onveranderde records (zelfde hash van het bronrecord) worden overgeslagen.
 * - Lokaal gewijzigde of verwijderde records worden nooit overschreven: dat wordt als conflict gemeld.
 * - Records die niet meer in de bron staan, worden niet verwijderd (alleen gemeld).
 * - De hele run is één databasetransactie: lukt hij niet, dan verandert er niets. Een droogloop
 *   wordt altijd teruggedraaid en kopieert geen bestanden.
 */
final class Importer
{
    /** Volgorde = afhankelijkheden: eerst waar later naar verwezen wordt. */
    public const MAPPERS = [
        Mappers\TagMapper::class,
        Mappers\EmployeeMapper::class,
        Mappers\FileMapper::class,
        Mappers\CompanyMapper::class,
        Mappers\ContactMapper::class,
        Mappers\UnitMapper::class,
        Mappers\LedgerMapper::class,
        Mappers\CostCenterMapper::class,
        Mappers\ProductMapper::class,
        Mappers\PacketMapper::class,
        Mappers\PacketLineMapper::class,
        Mappers\OfferPhaseMapper::class,
        Mappers\OfferMapper::class,
        Mappers\OfferLineMapper::class,
        Mappers\ProjectPhaseMapper::class,
        Mappers\ProjectMapper::class,
        Mappers\ProjectLineMapper::class,
        Mappers\TaskTypeMapper::class,
        Mappers\TaskPhaseMapper::class,
        Mappers\TaskMapper::class,
        Mappers\CalendarItemMapper::class,
        Mappers\HourMapper::class,
        Mappers\ContractMapper::class,
        Mappers\ContractLineMapper::class,
        Mappers\InvoiceMapper::class,
        Mappers\InvoiceLineMapper::class,
        Mappers\PaymentMapper::class,
        Mappers\TimelineMapper::class,
    ];

    /** @return list<Mapper> */
    public static function mappers(?array $only = null): array
    {
        $all = array_map(fn ($c) => new $c, self::MAPPERS);

        return $only ? array_values(array_filter($all, fn (Mapper $m) => in_array($m->key(), $only, true))) : $all;
    }

    public static function entities(): array
    {
        return array_map(fn (Mapper $m) => $m->key(), self::mappers());
    }

    public function run(ImportRun $run, ?callable $progress = null): ImportRun
    {
        $only = $run->only ? array_values(array_filter(array_map('trim', explode(',', $run->only)))) : null;
        $run->update(['status' => 'bezig', 'started_at' => now(), 'error' => null]);
        $started = microtime(true);
        $ctx = null;
        $checks = [];

        try {
            if ($only && array_diff($only, self::entities())) {
                throw new \InvalidArgumentException('Onbekende entiteit in "alleen": '.implode(', ', array_diff($only, self::entities())).'. Geldig: '.implode(', ', self::entities()));
            }
            $snapshot = new Snapshot($run->source_path, $run->snapshot ?: 'current');
            $ctx = new Context($snapshot, $run->dry_run);
            Audit::$muted = true;
            DB::beginTransaction();
            try {
                foreach (self::mappers($only) as $mapper) {
                    $progress && $progress("{$mapper->key()}: bezig");
                    $this->importEntity($mapper, $ctx);
                    $progress && $progress($this->line($mapper->key(), $ctx->stats[$mapper->key()] ?? []));
                }
                foreach (self::mappers($only) as $mapper) {
                    $checks = array_merge($checks, $mapper->checks($ctx));
                    $snapshot->forget($mapper->entity());
                }
                $run->dry_run ? DB::rollBack() : DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            }
            $run->status = 'geslaagd';
        } catch (Throwable $e) {
            $run->status = 'mislukt';
            $run->error = mb_substr($e->getMessage(), 0, 2000);
            Log::error('Import mislukt', ['run' => $run->id, 'exception' => $e]);
        } finally {
            Audit::$muted = false;
        }

        $run->fill([
            'snapshot' => $run->snapshot ?: 'current',
            'summary' => [
                'batch' => isset($snapshot) ? $snapshot->batch() : null,
                'snapshot_status' => isset($snapshot) ? $snapshot->status() : null,
                'entities' => $ctx?->stats ?? [],
                'files' => $ctx?->files ?? [],
                'checks' => $checks,
                'seconds' => round(microtime(true) - $started, 1),
            ],
            'issues' => $ctx?->issues ?? [],
            'finished_at' => now(),
        ])->save();
        Audit::log('import', null, sprintf('Import %s %s: %s', $run->dry_run ? '(droogloop)' : '', $run->summary['batch'] ?? '', $run->status), $run->totals());

        return $run;
    }

    private function importEntity(Mapper $mapper, Context $ctx): void
    {
        $entity = $mapper->key();
        $class = $mapper->model();
        $softDeletes = in_array(SoftDeletes::class, class_uses_recursive($class), true);
        $rows = array_values(array_filter($ctx->snapshot->rows($mapper->entity()), fn ($r) => $mapper->accepts($r, $ctx)));
        $ctx->stats[$entity] = ['source' => count($rows), 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'conflicts' => 0, 'skipped' => 0, 'errors' => 0];
        if (! $ctx->snapshot->has($mapper->entity())) {
            $ctx->issue($entity, null, 'ontbreekt', "Tabel {$mapper->entity()} staat niet in deze snapshot (geen leesrechten in Gripp?).");
        }
        $existing = ($softDeletes ? $class::withTrashed() : $class::query())->whereNotNull('gripp_id')->get()->keyBy('gripp_id');
        $seen = [];

        foreach ($rows as $row) {
            $gid = $mapper->grippId($row);
            if ($gid === null) {
                $ctx->count($entity, 'skipped');
                $ctx->issue($entity, null, 'geen id', 'Record zonder id overgeslagen.');

                continue;
            }
            $seen[$gid] = true;
            $hash = sha1($mapper::VERSION.'|'.json_encode($mapper->hashRow($row)).'|'.$mapper->hashExtra($row, $ctx));
            $model = $existing->get($gid);
            if ($model && $model->gripp_hash === $hash) {
                $ctx->count($entity, 'unchanged');

                continue;
            }
            if ($model && $softDeletes && $model->trashed()) {
                $ctx->count($entity, 'conflicts');
                $ctx->issue($entity, $gid, 'conflict', 'In Werkbank verwijderd; niet hersteld.');

                continue;
            }
            if ($model && $model->isLocallyModified()) {
                $ctx->count($entity, 'conflicts');
                $ctx->issue($entity, $gid, 'conflict', 'In Werkbank gewijzigd sinds de vorige import; bronwijziging niet overgenomen.');

                continue;
            }

            try {
                DB::transaction(function () use ($mapper, $row, $ctx, $class, $model, $gid, $hash, $entity) {
                    $attrs = $mapper->map($row, $ctx);
                    if ($attrs === null) {
                        $ctx->count($entity, 'skipped');

                        return;
                    }
                    $isNew = $model === null;
                    $model ??= new $class;
                    $now = now();
                    $model->forceFill($attrs + ['gripp_id' => $gid, 'gripp_hash' => $hash, 'gripp_synced_at' => $now]);
                    $model->timestamps = false;
                    $model->updated_at = $now;
                    if ($isNew) {
                        $model->created_at = Gripp::date($row['createdon'] ?? null) ?? $now;
                    }
                    $model->save();
                    $model->timestamps = true;
                    $ctx->remember($model);
                    $mapper->after($model, $row, $ctx);
                    $ctx->count($entity, $isNew ? 'created' : 'updated');
                });
            } catch (ImportSkip $e) {
                $ctx->count($entity, 'skipped');
                $ctx->issue($entity, $gid, 'overgeslagen', $e->getMessage());
            } catch (Throwable $e) {
                $ctx->count($entity, 'errors');
                $ctx->issue($entity, $gid, 'fout', $e->getMessage());
            }
        }

        $missing = $existing->keys()->reject(fn ($id) => isset($seen[$id]))->count();
        if ($missing && $ctx->snapshot->has($mapper->entity())) {
            $ctx->stats[$entity]['missing_in_source'] = $missing;
            $ctx->issue($entity, null, 'niet in bron', "{$missing} eerder geïmporteerde record(s) staan niet meer in de bron. Ze zijn niet verwijderd.");
        }
    }

    public function line(string $entity, array $s): string
    {
        return sprintf('%-20s bron %5d · nieuw %5d · gewijzigd %5d · ongewijzigd %5d · conflict %d · overgeslagen %d · fout %d',
            $entity, $s['source'] ?? 0, $s['created'] ?? 0, $s['updated'] ?? 0, $s['unchanged'] ?? 0, $s['conflicts'] ?? 0, $s['skipped'] ?? 0, $s['errors'] ?? 0);
    }
}
