@extends('layouts.app', ['title' => 'Bank'])
@php
    use App\Support\Format;
    use App\Support\Iban;
    use App\Models\BankTransaction;
    use App\Models\Invoice;
    $tabs = ['open' => 'Te verwerken'] + BankTransaction::STATUSSEN;
@endphp

@section('content')
<div class="page-head">
    <div><h1>Bank</h1><div class="sub">Transacties uit Ponto met koppelvoorstellen · alleen lezen: er wordt pas geboekt als jij bevestigt</div></div>
    <div class="actions">
        @if ($certain)
            <form method="post" action="{{ route('bank.zeker') }}" class="inline" data-confirm="{{ $certain }} zekere voorstel(len) boeken als betaling?">@csrf<button class="btn" type="submit">Zekere voorstellen boeken ({{ $certain }})</button></form>
        @endif
        @if ($configured)
            <form method="post" action="{{ route('bank.ophalen') }}" class="inline">@csrf<button class="btn btn-primary" type="submit" title="Leest de gegevens die Ponto al heeft; Ponto werkt ze zelf vier keer per dag bij">Nu ophalen</button></form>
        @endif
    </div>
</div>

@unless ($configured)
    <div class="card"><div class="card-b"><strong>Niet ingesteld.</strong> Vul <code>PONTO_CLIENT_ID</code> en <code>PONTO_CLIENT_SECRET</code> in <code>.env</code> in (een custom integration in het Ponto-dashboard, alleen AIS). Zie <code>docs/bankkoppeling-ponto.md</code>.</div></div>
@endunless
@error('bank')<div class="card"><div class="card-b error-text">{{ $message }}</div></div>@enderror

@if ($accounts->isNotEmpty())
<div class="tiles">
    @foreach ($accounts as $a)
        <div class="tile">
            <div class="k">{{ $a->label() ?: 'Rekening' }}</div>
            <div class="v">{{ $a->current_balance !== null ? Format::euro($a->current_balance) : '—' }}</div>
            <div class="small muted">Bank bijgewerkt {{ Format::dateTime($a->synchronized_at) ?: '—' }}</div>
            @if ($a->availability === 'readonly')<div class="small"><span class="badge badge-warn">bank in onderhoud</span></div>@endif
            @if ($a->authorizationExpiresSoon())<div class="small"><span class="badge badge-danger">toestemming verloopt {{ Format::date($a->authorization_expires_at) }}</span> vernieuwen in Ponto</div>@endif
        </div>
    @endforeach
</div>
@endif

<div class="actions" style="margin-bottom:12px">
    @foreach ($tabs as $k => $label)
        @php $n = $k === 'open' ? ($counts['nieuw'] ?? 0) + ($counts['voorstel'] ?? 0) : ($counts[$k] ?? 0); @endphp
        <a class="btn {{ $status === $k ? 'btn-primary' : '' }}" href="{{ route('bank.index', ['status' => $k]) }}">{{ $label }} ({{ $n }})</a>
    @endforeach
</div>

<div class="card">
    <div class="card-b flush">
        @if ($transactions->isEmpty())
            <div class="empty">Geen transacties.</div>
        @else
        <table class="list">
            <thead><tr><th>Datum</th><th>Tegenpartij</th><th>Omschrijving</th><th class="num">Bedrag</th><th>Koppeling</th><th></th></tr></thead>
            <tbody>
            @foreach ($transactions as $t)
                @php $doc = $t->proposal; @endphp
                <tr>
                    <td class="nowrap">{{ Format::date($t->date) }}</td>
                    <td>{{ $t->counterpart_name ?: '—' }}@if ($t->counterpart_iban)<div class="muted small nowrap">{{ Iban::format($t->counterpart_iban) }}</div>@endif</td>
                    <td class="small">{{ \Illuminate\Support\Str::limit($t->remittance ?: $t->description, 120) }}@if ($t->end_to_end_id)<div class="muted">{{ $t->end_to_end_id }}</div>@endif</td>
                    <td class="num nowrap {{ (float) $t->amount < 0 ? 'neg' : '' }}">{{ Format::euro($t->amount) }}</td>
                    <td class="small">
                        @if ($doc)
                            @if ($doc instanceof Invoice)
                                <a href="{{ route('facturen.show', $doc) }}">Factuur {{ $doc->number }}</a> · {{ $doc->company?->name }}
                            @else
                                <a href="{{ route('inkoopfacturen.show', $doc) }}">Inkoopfactuur {{ $doc->booking_number }}</a> · {{ $doc->supplier?->name }}
                            @endif
                            <div class="muted">open {{ Format::euro($doc->total_open) }}</div>
                            @if ($t->isOpen())<div>@if ($t->proposal_certain)<span class="badge badge-ok">zeker</span>@else<span class="badge badge-warn">controleren</span>@endif {{ $t->proposal_reason }}</div>@endif
                        @endif
                        @if ($t->status === 'gekoppeld')<span class="badge badge-ok">geboekt</span> <span class="muted">{{ $t->handler?->name }}, {{ Format::dateTime($t->handled_at) }}</span>@endif
                        @if ($t->status === 'genegeerd')<span class="badge">genegeerd</span>@endif
                    </td>
                    <td class="nowrap">
                        @if ($t->isOpen())
                            @if ($doc)<form method="post" action="{{ route('bank.boeken', $t) }}" class="inline">@csrf<button class="btn btn-primary" type="submit">Boeken</button></form>@endif
                            <details class="inline-details">
                                <summary class="btn">Andere…</summary>
                                <form method="post" action="{{ route('bank.boeken', $t) }}" class="filters" style="margin-top:6px">
                                    @csrf
                                    <select name="soort" aria-label="Soort document">
                                        <option value="factuur" @selected((float) $t->amount > 0)>Verkoopfactuur nr.</option>
                                        <option value="inkoopfactuur" @selected((float) $t->amount < 0)>Inkoopfactuur boeknr.</option>
                                    </select>
                                    <input name="nummer" inputmode="numeric" size="9" aria-label="Nummer" required>
                                    <button class="btn" type="submit">Boeken</button>
                                </form>
                            </details>
                            <form method="post" action="{{ route('bank.negeren', $t) }}" class="inline">@csrf<button class="btn" type="submit" title="Bijvoorbeeld bankkosten of salaris">Negeren</button></form>
                        @elseif ($t->status === 'genegeerd')
                            <form method="post" action="{{ route('bank.heropenen', $t) }}" class="inline">@csrf<button class="btn" type="submit">Terugzetten</button></form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        {{ $transactions->links() }}
        @endif
    </div>
</div>
@error('nummer')<p class="error-text">{{ $message }}</p>@enderror
@endsection
