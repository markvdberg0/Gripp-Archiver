<?php

namespace App\Import\Mappers;

use App\Import\Context;
use App\Import\Gripp;
use App\Import\Mapper;
use App\Models\Company;
use App\Models\Contact;
use App\Models\PurchaseInvoice;
use Illuminate\Database\Eloquent\Model;

/**
 * Inkoopfacturen. Fasen: Concept/Controle/Akkoord/Verwerkt; "On-hold" wordt fase controle met on_hold.
 * "unprocessed" (nog in de Gripp-inbox) wordt inbox. Totalen, betaald en openstaand zoals Gripp ze heeft.
 */
class PurchaseInvoiceMapper extends Mapper
{
    /** @var array<int, int> boeknummer → gripp_id, om dubbele boeknummers te herkennen */
    private array $bookingNumbers = [];

    public function entity(): string
    {
        return 'purchaseinvoice';
    }

    public function model(): string
    {
        return PurchaseInvoice::class;
    }

    public function map(array $row, Context $ctx): ?array
    {
        $onHold = Gripp::is($row['phase'] ?? null, 'on-hold', 'on hold', 'onhold');
        $booking = is_numeric($row['bookingnumber'] ?? null) ? (int) $row['bookingnumber'] : null;
        if ($booking !== null && isset($this->bookingNumbers[$booking]) && $this->bookingNumbers[$booking] !== (int) $row['id']) {
            $ctx->issue($this->key(), $row['id'], 'dubbel', "Boeknummer {$booking} komt vaker voor; dit record is zonder boeknummer geïmporteerd.");
            $booking = null;
        }
        if ($booking !== null) {
            $this->bookingNumbers[$booking] = (int) $row['id'];
        }
        $incl = Gripp::money($row['totalinclvat'] ?? 0);

        return [
            'booking_number' => $booking,
            'invoice_number' => Gripp::str($row['number'] ?? null, 100),
            'supplier_id' => $ctx->id(Company::class, $row['company'] ?? null),
            'contact_id' => $ctx->id(Contact::class, $row['contact'] ?? null),
            'subject' => Gripp::str($row['subject'] ?? null),
            'description' => Gripp::text($row['description'] ?? null),
            'date' => Gripp::day($row['date'] ?? null),
            'due_date' => Gripp::day($row['expirydate'] ?? null),
            'phase' => match (true) {
                $onHold => 'controle',
                Gripp::is($row['phase'] ?? null, 'verwerkt', 'processed') => 'verwerkt',
                Gripp::is($row['phase'] ?? null, 'akkoord', 'agreed') => 'akkoord',
                Gripp::is($row['phase'] ?? null, 'controle', 'check') => 'controle',
                default => 'concept',
            },
            'on_hold' => $onHold,
            'hold_reason' => $onHold ? 'On hold in Gripp' : null,
            'inbox' => Gripp::bool($row['unprocessed'] ?? false),
            'payment_method' => CompanyMapper::paymentMethod($row['paymentmethod'] ?? null),
            'total_incl' => $incl,
            'total_paid' => Gripp::money($row['totalpayed'] ?? 0),
            'total_open' => Gripp::money($row['totalopeninclvat'] ?? 0),
        ];
    }

    public function after(Model $model, array $row, Context $ctx): void
    {
        $this->syncTags($model, $row, $ctx);
        $this->attachFiles($model, $row, $ctx);
    }

    public function checks(Context $ctx): array
    {
        $rows = $ctx->snapshot->rows('purchaseinvoice');
        $fmt = fn ($n) => number_format($n, 2, ',', '.');
        $out = parent::checks($ctx);
        foreach (['totalinclvat' => ['total_incl', 'som totaal incl. btw'], 'totalopeninclvat' => ['total_open', 'som openstaand incl. btw']] as $src => [$col, $label]) {
            $s = array_sum(array_map(fn ($r) => (float) ($r[$src] ?? 0), $rows));
            $l = (float) PurchaseInvoice::withTrashed()->whereNotNull('gripp_id')->sum($col);
            $out[] = ['label' => "purchaseinvoice: {$label}", 'source' => $fmt($s), 'local' => $fmt($l), 'ok' => abs($s - $l) < 0.01];
        }

        return $out;
    }
}
