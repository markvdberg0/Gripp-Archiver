{{--
    Gemeenschappelijke PDF-opmaak voor offertes, opdrachtbevestigingen, facturen en inkoopopdrachten (dompdf).
    Variabelen: $title, $doc (met lines, total_*), $vat, $recipient (Company|null), $contact, $meta (label => waarde), $intro, $footer
--}}
@php
    use App\Models\Setting; use App\Support\Format; use App\Support\Html;
    $s = fn ($k) => Setting::get($k);
@endphp
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<title>{{ $title }}</title>
<style>
    @page { margin: 28mm 18mm 26mm 18mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9.5pt; color: #1d2430; }
    header { position: fixed; top: -20mm; left: 0; right: 0; }
    footer { position: fixed; bottom: -18mm; left: 0; right: 0; font-size: 7.5pt; color: #5d6675; border-top: 1px solid #dfe3ea; padding-top: 4px; }
    .brand { font-size: 14pt; font-weight: bold; color: #1f6f6b; }
    .addr { width: 100%; margin-top: 8mm; }
    .addr td { vertical-align: top; }
    h1 { font-size: 16pt; margin: 10mm 0 2mm; }
    table.meta td { padding: 1px 12px 1px 0; }
    table.lines { width: 100%; border-collapse: collapse; margin-top: 6mm; }
    table.lines th { text-align: left; font-size: 8pt; color: #5d6675; border-bottom: 1px solid #1d2430; padding: 4px 4px; }
    table.lines td { padding: 4px; border-bottom: 1px solid #eceff3; vertical-align: top; }
    table.lines .num { text-align: right; white-space: nowrap; }
    tr.group td { font-weight: bold; background: #f3f5f7; }
    .desc { color: #5d6675; font-size: 8.5pt; }
    table.totals { width: 45%; margin-left: 55%; border-collapse: collapse; margin-top: 4mm; }
    table.totals td { padding: 3px 4px; }
    table.totals tr.grand td { font-weight: bold; border-top: 1px solid #1d2430; }
    .intro { margin-top: 4mm; }
    .intro p { margin: 0 0 4px; }
    .page:after { content: counter(page); }
</style>
</head>
<body>
<header><span class="brand">{{ $s('bedrijf.naam') ?: config('app.name') }}</span></header>
<footer>
    {{ $s('bedrijf.naam') }} · {{ $s('bedrijf.adres') }} {{ $s('bedrijf.postcode') }} {{ $s('bedrijf.plaats') }}
    @if ($s('bedrijf.kvk')) · KvK {{ $s('bedrijf.kvk') }} @endif
    @if ($s('bedrijf.btw')) · Btw {{ $s('bedrijf.btw') }} @endif
    @if ($s('bedrijf.iban')) · IBAN {{ $s('bedrijf.iban') }} @endif
    <span style="float:right">Pagina <span class="page"></span></span>
    @if (! empty($footer))<div>{{ $footer }}</div>@endif
</footer>

<table class="addr"><tr>
    <td style="width:55%">
        @if ($recipient)
            <strong>{{ $recipient->invoice_company_name ?: $recipient->name }}</strong><br>
            @if ($contact)t.a.v. {{ $contact->name }}<br>@elseif ($recipient->invoice_attention)t.a.v. {{ $recipient->invoice_attention }}<br>@endif
            @if (($useInvoiceAddress ?? false) && $recipient->invoice_street)
                {{ $recipient->invoice_street }} {{ $recipient->invoice_house_number }}<br>{{ $recipient->invoice_postcode }} {{ $recipient->invoice_city }}
            @elseif ($recipient->postal_street)
                {{ $recipient->postal_street }} {{ $recipient->postal_house_number }}<br>{{ $recipient->postal_postcode }} {{ $recipient->postal_city }}
            @else
                {{ $recipient->visit_street }} {{ $recipient->visit_house_number }}<br>{{ $recipient->visit_postcode }} {{ $recipient->visit_city }}
            @endif
            @if ($recipient->visit_country && ! in_array($recipient->visit_country, ['Nederland', 'NL']))<br>{{ $recipient->visit_country }}@endif
        @endif
    </td>
    <td>
        {{ $s('bedrijf.naam') }}<br>{{ $s('bedrijf.adres') }}<br>{{ $s('bedrijf.postcode') }} {{ $s('bedrijf.plaats') }}<br>
        @if ($s('bedrijf.telefoon')){{ $s('bedrijf.telefoon') }}<br>@endif
        @if ($s('bedrijf.email')){{ $s('bedrijf.email') }}@endif
    </td>
</tr></table>

<h1>{{ $title }}</h1>
<table class="meta">
    @foreach ($meta as $label => $value)
        @if ($value !== null && $value !== '')<tr><td>{{ $label }}</td><td>{{ $value }}</td></tr>@endif
    @endforeach
</table>

@if (! empty($intro))<div class="intro">{{ Html::render($intro) }}</div>@endif

<table class="lines">
    <thead><tr><th>Omschrijving</th><th class="num">Aantal</th><th>Eenheid</th><th class="num">Prijs</th><th class="num">Korting</th><th class="num">Btw</th><th class="num">Totaal</th></tr></thead>
    <tbody>
    @foreach ($doc->lines as $l)
        @if ($l->row_type === 'groep')
            <tr class="group"><td colspan="7">{{ $l->label() }}</td></tr>
        @elseif ($l->row_type === 'tekst')
            <tr><td colspan="7">{{ $l->label() }}@if ($l->description)<div class="desc">{{ Html::render($l->description) }}</div>@endif</td></tr>
        @else
            <tr>
                <td>{{ $l->label() }}@if ($l->description)<div class="desc">{{ Html::render($l->description) }}</div>@endif</td>
                <td class="num">{{ Format::number($l->quantity, 4) }}</td>
                <td>{{ $l->unit?->name }}</td>
                <td class="num">{{ Format::euro($l->unit_price) }}</td>
                <td class="num">{{ (float) $l->discount ? Format::number($l->discount).'%' : '' }}</td>
                <td class="num">{{ $l->vatRate ? Format::number($l->vatRate->percentage).'%' : '' }}</td>
                <td class="num">{{ Format::euro($l->total_excl) }}</td>
            </tr>
        @endif
    @endforeach
    </tbody>
</table>

<table class="totals">
    <tr><td>Totaal excl. btw</td><td class="num" style="text-align:right">{{ Format::euro($doc->total_excl) }}</td></tr>
    @foreach ($vat as $v)
        <tr><td>{{ $v['name'] }} over {{ Format::euro($v['base']) }}</td><td style="text-align:right">{{ Format::euro($v['vat']) }}</td></tr>
    @endforeach
    <tr class="grand"><td>Totaal incl. btw</td><td style="text-align:right">{{ Format::euro($doc->total_incl) }}</td></tr>
</table>

@if (! empty($closing))<div class="intro" style="margin-top:8mm">{{ Html::render($closing) }}</div>@endif
</body>
</html>
