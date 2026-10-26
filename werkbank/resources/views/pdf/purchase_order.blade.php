@php use App\Support\Format; @endphp
@include('pdf.document', [
    'title' => 'Inkoopopdracht '.$order->number,
    'doc' => $order,
    'vat' => $vat,
    'recipient' => $order->supplier,
    'contact' => $order->contact,
    'meta' => [
        'Inkoopnummer' => $order->number,
        'Datum' => Format::date($order->date),
        'Onderwerp' => $order->name,
        'Uw referentie' => $order->reference,
        'Gewenste leverdatum' => Format::date($order->delivery_date),
        'Afleveradres' => $order->delivery_address,
        'Besteld door' => $order->employee?->name,
    ],
    'intro' => $order->description,
    'closing' => 'Vermeld ons inkoopnummer '.$order->number.' op de pakbon en de factuur.',
    'footer' => null,
])
