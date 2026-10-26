@php
    use App\Support\Format; use App\Models\Setting; use App\Models\Invoice;
    $heading = $invoice->reminder_level > 0 ? Invoice::REMINDERS[$invoice->reminder_level].' · ' : '';
    $closing = $invoice->reminder_level > 0
        ? 'Volgens onze administratie staat van deze factuur nog '.Format::euro($invoice->total_open).' open. De vervaldatum was '.Format::date($invoice->due_date).'. Wij verzoeken u het bedrag per omgaande over te maken onder vermelding van het factuurnummer. Is de betaling inmiddels onderweg, dan kunt u dit bericht als niet verzonden beschouwen.'
        : Setting::get('factuur.voettekst');
@endphp
@include('pdf.document', [
    'title' => $heading.($invoice->status === 'concept' ? 'CONCEPT ' : '').$invoice->title(),
    'doc' => $invoice,
    'vat' => $vat,
    'recipient' => $invoice->company,
    'contact' => $invoice->contact,
    'useInvoiceAddress' => true,
    'meta' => [
        'Factuurnummer' => $invoice->number ?? 'concept',
        'Factuurdatum' => Format::date($invoice->date),
        'Vervaldatum' => Format::date($invoice->due_date),
        'Onderwerp' => $invoice->subject,
        'Uw referentie' => $invoice->client_reference,
        'Creditering van factuur' => $invoice->creditOf?->number,
        'Btw-nummer klant' => $invoice->company?->vat_number,
        'Openstaand' => $invoice->reminder_level > 0 ? Format::euro($invoice->total_open) : null,
    ],
    'intro' => $invoice->description,
    'closing' => $closing.($invoice->company?->vat_shifted ? "\n\nBtw verlegd." : ''),
    'footer' => null,
])
