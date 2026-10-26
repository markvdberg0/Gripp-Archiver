<?php

namespace App\Support;

use App\Models\Company;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Dubbele relaties samenvoegen. Alle verwijzingen gaan naar de relatie die blijft; de opgeheven relatie wordt
 * zacht verwijderd en onthoudt waarin hij is opgegaan (merged_into_id). De import volgt die verwijzing, zodat
 * Gripp-gegevens van de opgeheven relatie voortaan bij de blijvende relatie terechtkomen.
 */
final class RelatieSamenvoegen
{
    /** tabel => kolom die naar companies verwijst */
    public const VERWIJZINGEN = [
        'contacts' => 'company_id',
        'timeline_entries' => 'company_id',
        'offers' => 'company_id',
        'projects' => 'company_id',
        'tasks' => 'company_id',
        'contracts' => 'company_id',
        'invoices' => 'company_id',
        'products' => 'supplier_id',
        'purchase_orders' => 'supplier_id',
        'purchase_invoices' => 'supplier_id',
    ];

    /** Velden die bij de blijvende relatie worden aangevuld als ze daar leeg zijn. */
    public const AANVULLEN = [
        'legal_name', 'email', 'invoice_email', 'phone', 'mobile', 'website', 'coc_number', 'vat_number', 'iban', 'bic',
        'bank_account_holder', 'visit_street', 'visit_house_number', 'visit_postcode', 'visit_city', 'visit_country',
        'postal_street', 'postal_house_number', 'postal_postcode', 'postal_city', 'postal_country',
        'invoice_street', 'invoice_house_number', 'invoice_postcode', 'invoice_city', 'invoice_country',
        'invoice_company_name', 'invoice_attention', 'payment_term_days', 'supplier_payment_term_days', 'founded_on', 'account_manager_id',
    ];

    /** Aantallen per tabel die bij samenvoegen worden verplaatst. */
    public static function preview(Company $source): array
    {
        $out = [];
        foreach (self::VERWIJZINGEN as $table => $column) {
            $out[$table] = DB::table($table)->where($column, $source->id)->count();
        }
        $out['attachments'] = DB::table('attachments')->where('attachable_type', $source->getMorphClass())->where('attachable_id', $source->id)->count();
        $out['taggables'] = DB::table('taggables')->where('taggable_type', $source->getMorphClass())->where('taggable_id', $source->id)->count();

        return $out;
    }

    /** Velden die bij de blijvende relatie leeg zijn en uit de opgeheven relatie worden overgenomen. */
    public static function aanvulling(Company $source, Company $target): array
    {
        $fill = [];
        foreach (self::AANVULLEN as $field) {
            if (self::empty($target->getRawOriginal($field)) && ! self::empty($source->getRawOriginal($field))) {
                $fill[$field] = $source->getRawOriginal($field);
            }
        }
        foreach (array_keys(Company::ROLLEN) as $role) {
            if ($source->{$role} && ! $target->{$role}) {
                $fill[$role] = true;
            }
        }
        if (! self::empty($source->notes) && ! str_contains((string) $target->notes, (string) $source->notes)) {
            $fill['notes'] = trim($target->notes."\n\n[Uit relatie {$source->number} {$source->name}]\n".$source->notes);
        }

        return $fill;
    }

    /** @return array verplaatste aantallen per tabel */
    public static function merge(Company $source, Company $target, bool $fillEmpty = true): array
    {
        if ($source->is($target)) {
            throw new InvalidArgumentException('Een relatie kan niet met zichzelf worden samengevoegd.');
        }
        if ($source->trashed() || $target->trashed() || $source->merged_into_id || $target->merged_into_id) {
            throw new InvalidArgumentException('Een van beide relaties is al verwijderd of samengevoegd.');
        }

        return DB::transaction(function () use ($source, $target, $fillEmpty) {
            $moved = [];
            // Rechtstreeks in de tabellen: de documenten zelf zijn niet inhoudelijk gewijzigd, dus updated_at blijft
            // staan en een volgende import ziet ze niet als lokaal gewijzigd (de import verwijst nu ook naar $target).
            foreach (self::VERWIJZINGEN as $table => $column) {
                $moved[$table] = DB::table($table)->where($column, $source->id)->update([$column => $target->id]);
            }
            $moved['attachments'] = self::movePivot('attachments', 'attachable', ['file_id', 'role'], $source, $target);
            $moved['taggables'] = self::movePivot('taggables', 'taggable', ['tag_id'], $source, $target);

            if ($fillEmpty && $fill = self::aanvulling($source, $target)) {
                $target->update($fill);
            }
            $source->forceFill(['merged_into_id' => $target->id, 'merged_at' => now(), 'active' => false])->save();
            $source->delete();
            // Ketens inkorten: wat eerder in $source was opgegaan, wijst nu direct naar $target.
            Company::withTrashed()->where('merged_into_id', $source->id)->update(['merged_into_id' => $target->id]);

            Audit::log('relatie_samengevoegd', $target, "{$source->number} {$source->name} → {$target->number} {$target->name}", array_filter($moved));

            return $moved;
        });
    }

    /**
     * Kandidaten voor dubbele relaties: gelijke genormaliseerde naam, KvK-nummer, btw-nummer of IBAN.
     *
     * @return Collection<int, array{reden: string, relaties: Collection<int, Company>}>
     */
    public static function duplicates(int $limit = 200): Collection
    {
        $companies = Company::query()->get(['id', 'number', 'name', 'coc_number', 'vat_number', 'iban', 'visit_city', 'email', 'active', 'is_customer', 'is_supplier']);
        $groups = collect();
        $keys = [
            'naam' => fn (Company $c) => self::normalizeName($c->name),
            'KvK-nummer' => fn (Company $c) => preg_replace('/\D/', '', (string) $c->coc_number),
            'btw-nummer' => fn (Company $c) => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $c->vat_number)),
            'IBAN' => fn (Company $c) => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $c->iban)),
        ];
        $seen = [];
        foreach ($keys as $reason => $key) {
            foreach ($companies->groupBy($key) as $value => $group) {
                if ($value === '' || strlen((string) $value) < 3 || $group->count() < 2) {
                    continue;
                }
                $ids = $group->pluck('id')->sort()->implode(',');
                if (isset($seen[$ids])) {
                    continue;
                }
                $seen[$ids] = true;
                $groups->push(['reden' => $reason, 'relaties' => $group->sortBy('number')->values()]);
            }
        }

        return $groups->take($limit);
    }

    /** "Bakkerij De Vries B.V." en "bakkerij de vries bv" worden gelijk. */
    public static function normalizeName(?string $name): string
    {
        $n = mb_strtolower(trim((string) $name));
        $n = preg_replace('/[.,\-&\'"()]/u', ' ', $n);
        $n = preg_replace('/\b(b\s?v|n\s?v|v\s?o\s?f|vof|holding|beheer|de|het|en)\b/u', ' ', $n);

        return trim(preg_replace('/\s+/u', ' ', $n));
    }

    private static function movePivot(string $table, string $morph, array $keyColumns, Company $source, Company $target): int
    {
        $type = $source->getMorphClass();
        $rows = DB::table($table)->where("{$morph}_type", $type)->where("{$morph}_id", $source->id)->get();
        $moved = 0;
        // Sleutelkolommen + morph identificeren een rij uniek (taggables heeft geen id).
        $where = function (int $ownerId, object $row) use ($table, $morph, $type, $keyColumns) {
            $q = DB::table($table)->where("{$morph}_type", $type)->where("{$morph}_id", $ownerId);
            foreach ($keyColumns as $c) {
                $q->where($c, $row->{$c});
            }

            return $q;
        };
        foreach ($rows as $row) {
            if ($where($target->id, $row)->exists()) {
                $where($source->id, $row)->delete();
            } else {
                $where($source->id, $row)->update(["{$morph}_id" => $target->id]);
                $moved++;
            }
        }

        return $moved;
    }

    private static function empty(mixed $v): bool
    {
        return $v === null || (is_string($v) && trim($v) === '');
    }
}
