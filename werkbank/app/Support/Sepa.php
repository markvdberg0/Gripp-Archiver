<?php

namespace App\Support;

use App\Models\PurchaseInvoice;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use XMLWriter;

/**
 * SEPA-overboekingsbestand (pain.001.001.09) voor het betalen van inkoopfacturen. In te lezen bij de bank;
 * de bank voert pas uit na jouw akkoord daar. Getoetst op structuur, niet bij elke bank uitgeprobeerd.
 */
final class Sepa
{
    public const NS = 'urn:iso:std:iso:20022:tech:xsd:pain.001.001.09';

    /**
     * @param  Collection<int, PurchaseInvoice>  $invoices  met supplier geladen; bedrag = total_open
     * @return array{xml: string, batch: string, count: int, total: string}
     */
    public static function creditTransfer(Collection $invoices, CarbonInterface $executionDate, string $debtorName, string $debtorIban, ?string $debtorBic): array
    {
        $batch = 'WB'.now()->format('YmdHis').strtoupper(Str::random(4));
        $total = round($invoices->sum(fn ($i) => (float) $i->total_open), 2);
        $count = $invoices->count();

        $w = new XMLWriter;
        $w->openMemory();
        $w->setIndent(true);
        $w->startDocument('1.0', 'UTF-8');
        $w->startElement('Document');
        $w->writeAttribute('xmlns', self::NS);
        $w->startElement('CstmrCdtTrfInitn');

        $w->startElement('GrpHdr');
        $w->writeElement('MsgId', $batch);
        $w->writeElement('CreDtTm', now()->format('Y-m-d\TH:i:s'));
        $w->writeElement('NbOfTxs', (string) $count);
        $w->writeElement('CtrlSum', self::amount($total));
        $w->startElement('InitgPty');
        $w->writeElement('Nm', self::text($debtorName, 70));
        $w->endElement();
        $w->endElement();

        $w->startElement('PmtInf');
        $w->writeElement('PmtInfId', $batch.'-1');
        $w->writeElement('PmtMtd', 'TRF');
        $w->writeElement('BtchBookg', 'true');
        $w->writeElement('NbOfTxs', (string) $count);
        $w->writeElement('CtrlSum', self::amount($total));
        $w->startElement('PmtTpInf');
        $w->startElement('SvcLvl');
        $w->writeElement('Cd', 'SEPA');
        $w->endElement();
        $w->endElement();
        $w->startElement('ReqdExctnDt');
        $w->writeElement('Dt', $executionDate->format('Y-m-d'));
        $w->endElement();
        $w->startElement('Dbtr');
        $w->writeElement('Nm', self::text($debtorName, 70));
        $w->endElement();
        $w->startElement('DbtrAcct');
        $w->startElement('Id');
        $w->writeElement('IBAN', Iban::normalize($debtorIban));
        $w->endElement();
        $w->endElement();
        $w->startElement('DbtrAgt');
        $w->startElement('FinInstnId');
        if ($debtorBic) {
            $w->writeElement('BICFI', strtoupper(preg_replace('/\s+/', '', $debtorBic)));
        } else {
            $w->startElement('Othr');
            $w->writeElement('Id', 'NOTPROVIDED');
            $w->endElement();
        }
        $w->endElement();
        $w->endElement();
        $w->writeElement('ChrgBr', 'SLEV');

        foreach ($invoices as $invoice) {
            $supplier = $invoice->supplier;
            $w->startElement('CdtTrfTxInf');
            $w->startElement('PmtId');
            $w->writeElement('EndToEndId', self::text('WB-'.($invoice->booking_number ?? $invoice->id), 35));
            $w->endElement();
            $w->startElement('Amt');
            $w->startElement('InstdAmt');
            $w->writeAttribute('Ccy', 'EUR');
            $w->text(self::amount((float) $invoice->total_open));
            $w->endElement();
            $w->endElement();
            $w->startElement('Cdtr');
            $w->writeElement('Nm', self::text($supplier->bank_account_holder ?: $supplier->name, 70));
            $w->endElement();
            $w->startElement('CdtrAcct');
            $w->startElement('Id');
            $w->writeElement('IBAN', Iban::normalize((string) $supplier->iban));
            $w->endElement();
            $w->endElement();
            $w->startElement('RmtInf');
            $w->writeElement('Ustrd', self::text('Factuur '.($invoice->invoice_number ?: $invoice->booking_number), 140));
            $w->endElement();
            $w->endElement();
        }

        $w->endElement(); // PmtInf
        $w->endElement(); // CstmrCdtTrfInitn
        $w->endElement(); // Document
        $w->endDocument();

        return ['xml' => $w->outputMemory(), 'batch' => $batch, 'count' => $count, 'total' => self::amount($total)];
    }

    /** Waarom deze factuur niet in een betaalbestand kan, of null. */
    public static function problem(PurchaseInvoice $invoice): ?string
    {
        return match (true) {
            ! in_array($invoice->phase, ['akkoord', 'verwerkt'], true) => 'nog niet goedgekeurd',
            $invoice->on_hold => 'staat on hold',
            $invoice->payment_method === 'incasso' => 'incasso door leverancier',
            (float) $invoice->total_open <= 0.005 => 'niets open',
            ! $invoice->supplier => 'geen leverancier',
            ! Iban::valid((string) $invoice->supplier->iban) => 'geen geldig IBAN',
            default => null,
        };
    }

    private static function amount(float $n): string
    {
        return number_format(round($n, 2), 2, '.', '');
    }

    /** SEPA-tekenset: Latijnse letters, cijfers en / - ? : ( ) . , ' + en spatie. */
    private static function text(string $s, int $max): string
    {
        $s = Str::ascii($s);
        $s = preg_replace("/[^A-Za-z0-9\/\-?:().,'+ ]/", ' ', $s);
        $s = trim(preg_replace('/\s+/', ' ', $s));

        return mb_substr($s === '' ? '-' : $s, 0, $max);
    }
}
