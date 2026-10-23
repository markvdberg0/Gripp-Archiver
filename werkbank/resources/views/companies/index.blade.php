@extends('layouts.app', ['title' => 'Relaties'])

@section('content')
<div class="page-head">
    <div><h1>Relaties</h1><div class="sub">{{ $companies->total() }} {{ $companies->total() === 1 ? 'relatie' : 'relaties' }}</div></div>
    <div class="actions">
        <a class="btn" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}">CSV</a>
        @can('relaties.bewerken')<a class="btn btn-primary" href="{{ route('relaties.create') }}">Nieuwe relatie</a>@endcan
    </div>
</div>

<form class="filters" method="get">
    <div class="field"><label for="q">Zoeken</label><input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Naam, plaats, e-mail of nummer"></div>
    <x-keuze name="rol" label="Rol" :options="\App\Models\Company::ROLLEN" :value="request('rol')" placeholder="Alle rollen" />
    <x-keuze name="actief" label="Status" :options="['1' => 'Actief', '0' => 'Inactief', 'alle' => 'Alle']" :value="request('actief', '1')" />
    <button class="btn" type="submit">Filteren</button>
</form>

<div class="card">
    <div class="card-b flush">
        @if ($companies->isEmpty())
            <div class="empty">Geen relaties gevonden.</div>
        @else
        <table class="list">
            <thead><tr>
                <th><x-sorteer field="number" label="Nr." /></th>
                <th><x-sorteer field="name" label="Naam" :default="true" /></th>
                <th>Rol</th>
                <th><x-sorteer field="city" label="Plaats" /></th>
                <th>Accountmanager</th>
                <th class="num">Contactpersonen</th>
            </tr></thead>
            <tbody>
            @foreach ($companies as $c)
                <tr>
                    <td class="muted">{{ $c->number }}</td>
                    <td><a href="{{ route('relaties.show', $c) }}">{{ $c->name }}</a> @unless ($c->active)<span class="badge">inactief</span>@endunless</td>
                    <td>@foreach ($c->roleLabels() as $r)<span class="badge badge-accent">{{ $r }}</span> @endforeach</td>
                    <td>{{ $c->visit_city }}</td>
                    <td>{{ $c->accountManager?->name }}</td>
                    <td class="num">{{ $c->contacts_count }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        {{ $companies->links() }}
        @endif
    </div>
</div>
@endsection
