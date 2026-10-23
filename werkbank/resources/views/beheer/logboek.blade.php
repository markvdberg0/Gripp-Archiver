@extends('layouts.app', ['title' => 'Logboek'])
@php use App\Support\Format; @endphp

@section('content')
<div class="page-head"><div><h1>Logboek</h1><div class="sub">Wie heeft wat gedaan. Gevoelige waarden (wachtwoorden, bankgegevens) worden gemaskeerd.</div></div></div>
<form class="filters" method="get">
    <x-keuze name="gebruiker" label="Gebruiker" :options="$users" :value="request('gebruiker')" placeholder="Iedereen" />
    <x-keuze name="gebeurtenis" label="Gebeurtenis" :options="$events" :value="request('gebeurtenis')" placeholder="Alle" />
    <button class="btn" type="submit">Filteren</button>
</form>
<div class="card"><div class="card-b flush">
    @if ($logs->isEmpty())<div class="empty">Geen regels.</div>@else
    <table class="list">
        <thead><tr><th>Tijdstip</th><th>Gebruiker</th><th>Gebeurtenis</th><th>Onderwerp</th><th>Wijzigingen</th><th>IP</th></tr></thead>
        <tbody>
        @foreach ($logs as $l)
            <tr>
                <td class="nowrap">{{ Format::dateTime($l->created_at) }}</td>
                <td>{{ $l->user?->name ?? 'systeem' }}</td>
                <td><span class="badge">{{ $l->event }}</span></td>
                <td>{{ $l->description }} @if ($l->auditable_type)<span class="muted small">({{ $l->auditable_type }} {{ $l->auditable_id }})</span>@endif</td>
                <td class="small">
                    @if (is_array($l->changes))
                        @foreach (array_slice($l->changes, 0, 8, true) as $field => $change)
                            @if (is_array($change) && count($change) === 2)
                                <div><span class="muted">{{ $field }}:</span> {{ \Illuminate\Support\Str::limit(is_scalar($change[0]) ? (string) $change[0] : json_encode($change[0]), 40) }} → {{ \Illuminate\Support\Str::limit(is_scalar($change[1]) ? (string) $change[1] : json_encode($change[1]), 40) }}</div>
                            @else
                                <div><span class="muted">{{ $field }}:</span> {{ is_scalar($change) ? $change : json_encode($change) }}</div>
                            @endif
                        @endforeach
                        @if (count($l->changes) > 8)<div class="muted">… en {{ count($l->changes) - 8 }} meer</div>@endif
                    @endif
                </td>
                <td class="muted small">{{ $l->ip_address }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    {{ $logs->links() }}
    @endif
</div></div>
@endsection
