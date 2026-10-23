@php use App\Support\Format; use App\Models\Setting; @endphp
@include('pdf.document', [
    'title' => 'Offerte '.$offer->number,
    'doc' => $offer,
    'vat' => $vat,
    'recipient' => $offer->company,
    'contact' => $offer->contact,
    'meta' => [
        'Offertenummer' => $offer->number,
        'Datum' => Format::date($offer->date),
        'Geldig tot' => Format::date($offer->valid_until),
        'Onderwerp' => $offer->name,
        'Uw referentie' => $offer->client_reference,
        'Contactpersoon' => $offer->accountManager?->name,
    ],
    'intro' => $offer->description,
    'closing' => Setting::get('offerte.voettekst'),
    'footer' => null,
])
