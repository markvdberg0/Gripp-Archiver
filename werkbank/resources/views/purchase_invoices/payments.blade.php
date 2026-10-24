@extends('layouts.app', ['title' => 'Betaalbestand'])
@php use App\Support\Format; use App\Support\Iban; use App\Support\Sepa; @endphp

@section('content')
<div class="page-head">
    <div><h1>Betaalbestand inkoopfacturen</h1><div class="sub">SEPA-overboekingen (pain.001) om in te lezen bij je bank</div></div>
    <div class="actions"><a class="btn" href="{{ route('inkoopfacturen.index') }}">Naar inkoopfacturen</a></div>
</div>

<p class="small muted">Het bestand betaalt nog niets: je leest het in bij je bank en keurt de betaling daar goed. Facturen worden pas als betaald geregistreerd als je de betaling vastlegt (of via een volgende Gripp-import). Facturen die al in een bestand zaten, staan standaard niet in deze lijst.</p>

@unless ($debtorOk)
    <div class="card"><div class="card-b"><strong>Eerst instellen:</strong> vul een geldig IBAN van het eigen bedrijf in onder Beheer › Instellingen{{ $debtorIban ? ' (nu: '.$debtorIban.')' : '' }}.</div></div>
@endunless

<form class="filters" method="get">
    <x-vink name="ook_verwerkt" label="Ook facturen tonen die al in een betaalbestand zaten" :checked="$showExported" />
    <button class="btn" type="submit">Tonen</button>
</form>

<form method="post" action="{{ route('inkoopfacturen.betalen.store') }}" class="card">
    @csrf
    <div class="card-b flush">
        @if ($invoices->isEmpty())
            <div class="empty">Geen goedgekeurde, openstaande inkoopfacturen.</div>
        @else
        <table class="list">
            <thead><tr><th></th><th>Boeknr.</th><th>Leverancier</th><th>Factuurnr.</th><th>Vervaldatum</th><th>IBAN</th><th class="num">Open</th><th></th></tr></thead>
            <tbody>
            @foreach ($invoices as $i)
                @php $problem = Sepa::problem($i); @endphp
                <tr>
                    <td>@if (! $problem)<input type="checkbox" name="ids[]" value="{{ $i->id }}" @checked(! $i->sepa_exported_at && in_array($i->id, old('ids', []))) aria-label="Selecteren">@endif</td>
                    <td><a href="{{ route('inkoopfacturen.show', $i) }}">{{ $i->booking_number }}</a></td>
                    <td>{{ $i->supplier?->name }}</td>
                    <td>{{ $i->invoice_number }}</td>
                    <td class="nowrap {{ $i->isOverdue() ? 'neg' : '' }}">{{ Format::date($i->due_date) }}</td>
                    <td class="nowrap">{{ $i->supplier?->iban ? Iban::format($i->supplier->iban) : '—' }}</td>
                    <td class="num">{{ Format::euro($i->total_open) }}</td>
                    <td>
                        @if ($problem)<span class="badge badge-warn">{{ $problem }}</span>@endif
                        @if ($i->sepa_exported_at)<span class="badge">in bestand {{ Format::date($i->sepa_exported_at) }}</span>@endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @endif
    </div>
    @if ($invoices->isNotEmpty())
    <div class="card-b filters">
        <x-veld name="execution_date" label="Uitvoeren op" type="date" :value="old('execution_date', now()->toDateString())" required />
        <button class="btn btn-primary" type="submit" @disabled(! $debtorOk)>Betaalbestand maken</button>
        @error('ids')<div class="error-text">{{ $message }}</div>@enderror
    </div>
    @endif
</form>
@endsection
