@extends('layouts.app', ['title' => 'Dubbele relaties'])

@section('content')
<div class="page-head">
    <div><h1>Dubbele relaties</h1><div class="sub">{{ $groups->count() }} {{ $groups->count() === 1 ? 'mogelijke dubbeling' : 'mogelijke dubbelingen' }} op naam, KvK-nummer, btw-nummer of IBAN</div></div>
    <div class="actions"><a class="btn" href="{{ route('relaties.index') }}">Terug naar relaties</a></div>
</div>

<p class="muted small">Dit zijn kandidaten, geen zekerheden: controleer of het echt om dezelfde relatie gaat. Kies bij een dubbeling de relatie die moet <strong>verdwijnen</strong> en voeg die samen met de relatie die blijft.</p>

<div class="card">
    <div class="card-b flush">
        @if ($groups->isEmpty())
            <div class="empty">Geen mogelijke dubbele relaties gevonden.</div>
        @else
        <table class="list">
            <thead><tr><th>Gelijk op</th><th>Nr.</th><th>Naam</th><th>Plaats</th><th>E-mail</th><th></th></tr></thead>
            <tbody>
            @foreach ($groups as $g)
                @foreach ($g['relaties'] as $i => $c)
                <tr @if ($i === 0) style="border-top:2px solid var(--line)" @endif>
                    <td class="muted">{{ $i === 0 ? $g['reden'] : '' }}</td>
                    <td class="muted">{{ $c->number }}</td>
                    <td><a href="{{ route('relaties.show', $c) }}">{{ $c->name }}</a> @unless ($c->active)<span class="badge">inactief</span>@endunless</td>
                    <td>{{ $c->visit_city }}</td>
                    <td>{{ $c->email }}</td>
                    <td class="num">
                        @php $other = $g['relaties']->firstWhere('id', '!=', $c->id); @endphp
                        <a class="btn" href="{{ route('relaties.samenvoegen', [$c, 'doel' => $other?->id]) }}">Deze opheffen…</a>
                    </td>
                </tr>
                @endforeach
            @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>
@endsection
