@extends('layouts.app', ['title' => 'Contactpersonen'])

@section('content')
<div class="page-head">
    <div><h1>Contactpersonen</h1><div class="sub">{{ $contacts->total() }} contactpersonen</div></div>
    <div class="actions">@can('relaties.bewerken')<a class="btn btn-primary" href="{{ route('contactpersonen.create') }}">Nieuwe contactpersoon</a>@endcan</div>
</div>
<form class="filters" method="get">
    <div class="field"><label for="q">Zoeken</label><input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Naam of e-mail"></div>
    <x-keuze name="actief" label="Status" :options="['1' => 'Actief', '0' => 'Inactief', 'alle' => 'Alle']" :value="request('actief', '1')" />
    <button class="btn" type="submit">Filteren</button>
</form>
<div class="card"><div class="card-b flush">
    @if ($contacts->isEmpty())
        <div class="empty">Geen contactpersonen gevonden.</div>
    @else
    <table class="list">
        <thead><tr><th>Naam</th><th>Relatie</th><th>Functie</th><th>E-mail</th><th>Telefoon</th></tr></thead>
        <tbody>
        @foreach ($contacts as $c)
            <tr>
                <td><a href="{{ route('contactpersonen.show', $c) }}">{{ $c->name }}</a> @unless ($c->active)<span class="badge">inactief</span>@endunless</td>
                <td>@if ($c->company)<a href="{{ route('relaties.show', $c->company) }}">{{ $c->company->name }}</a>@endif</td>
                <td>{{ $c->function }}</td>
                <td>{{ $c->email }}</td>
                <td class="nowrap">{{ $c->phone ?: $c->mobile }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    {{ $contacts->links() }}
    @endif
</div></div>
@endsection
