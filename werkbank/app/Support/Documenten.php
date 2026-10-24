<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\Offer;
use App\Models\Project;
use App\Models\PurchaseOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * PDF's van documenten op één plek: voor downloaden, mailen en archiveren in de timeline.
 */
final class Documenten
{
    /** @return array{bytes: string, filename: string} */
    public static function pdf(Model $doc): array
    {
        [$view, $data, $filename] = match (true) {
            $doc instanceof Offer => ['pdf.offer', ['offer' => $doc->load(['company', 'contact', 'accountManager', 'lines.unit', 'lines.vatRate', 'lines.product'])], "Offerte {$doc->number}.pdf"],
            $doc instanceof Invoice => ['pdf.invoice', ['invoice' => $doc->load(['company', 'contact', 'creditOf', 'lines.unit', 'lines.vatRate', 'lines.product'])], ($doc->reminder_level ? 'Herinnering ' : '').$doc->title().'.pdf'],
            $doc instanceof Project => ['pdf.project', ['project' => $doc->load(['company', 'contact', 'accountManager', 'lines.unit', 'lines.vatRate', 'lines.product'])], "Opdrachtbevestiging {$doc->number}.pdf"],
            $doc instanceof PurchaseOrder => ['pdf.purchase_order', ['order' => $doc->load(['supplier', 'contact', 'employee', 'lines.unit', 'lines.vatRate', 'lines.product'])], "Inkoopopdracht {$doc->number}.pdf"],
            default => throw new InvalidArgumentException('Geen PDF voor '.$doc::class),
        };

        return ['bytes' => Pdf::loadView($view, $data + ['vat' => $doc->vatBreakdown()])->setPaper('a4')->output(), 'filename' => $filename];
    }
}
