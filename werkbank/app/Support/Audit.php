<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Schrijft het logboek (audit_logs). Gevoelige velden worden gemaskeerd; technische velden weggelaten.
 */
final class Audit
{
    /** Tijdens een import worden losse wijzigingen niet gelogd; de importrun zelf wel (met samenvatting). */
    public static bool $muted = false;

    private const WEGLATEN = ['updated_at', 'created_at', 'gripp_hash', 'gripp_synced_at', 'remember_token'];

    private const MASKEREN = ['password', 'iban', 'bic', 'bank_account_holder'];

    public static function log(string $event, ?Model $model = null, ?string $description = null, ?array $changes = null): void
    {
        if (self::$muted && $model !== null) {
            return;
        }
        AuditLog::create([
            'user_id' => Auth::id(),
            'event' => $event,
            'auditable_type' => $model?->getMorphClass(),
            'auditable_id' => $model?->getKey(),
            'description' => $description ? mb_substr($description, 0, 255) : null,
            'changes' => $changes ?: null,
            'ip_address' => app()->runningInConsole() ? null : Request::ip(),
        ]);
    }

    /** @return array<string, array{0: mixed, 1: mixed}> veld => [oud, nieuw] */
    public static function diff(Model $model): array
    {
        $out = [];
        foreach ($model->getChanges() as $field => $new) {
            if (in_array($field, self::WEGLATEN, true)) {
                continue;
            }
            $old = $model->getRawOriginal($field);
            $out[$field] = in_array($field, self::MASKEREN, true) ? ['***', '***'] : [$old, $new];
        }

        return $out;
    }

    public static function snapshot(Model $model): array
    {
        $out = [];
        foreach ($model->getAttributes() as $field => $value) {
            if ($value === null || in_array($field, self::WEGLATEN, true)) {
                continue;
            }
            $out[$field] = [null, in_array($field, self::MASKEREN, true) ? '***' : $value];
        }

        return $out;
    }
}
