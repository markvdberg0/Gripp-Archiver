@php use App\Support\Format; @endphp
@include('pdf.document', [
    'title' => 'Opdrachtbevestiging '.$project->number,
    'doc' => $project,
    'vat' => $vat,
    'recipient' => $project->company,
    'contact' => $project->contact,
    'meta' => [
        'Opdrachtnummer' => $project->number,
        'Datum' => Format::date(now()),
        'Onderwerp' => $project->name,
        'Uw referentie' => $project->client_reference,
        'Startdatum' => Format::date($project->start_date),
        'Leverdatum' => Format::date($project->delivery_date),
        'Contactpersoon' => $project->accountManager?->name,
    ],
    'intro' => $project->description,
    'closing' => null,
    'footer' => null,
])
