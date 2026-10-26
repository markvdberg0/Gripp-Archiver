@extends('layouts.app', ['title' => 'Pakketten'])

@section('content')
<div class="page-head">
    <div><h1>Pakketten</h1><div class="sub">Vaste sets regels die je in één keer aan een offerte toevoegt.</div></div>
    <div class="actions">@can('producten.bewerken')<a class="btn btn-primary" href="{{ route('pakketten.create') }}">Nieuw pakket</a>@endcan</div>
</div>
<div class="card"><div class="card-b flush">
    @if ($packets->isEmpty())<div class="empty">Nog geen pakketten.</div>@else
    <table class="list">
        <thead><tr><th>Nr.</th><th>Naam</th><th class="num">Regels</th></tr></thead>
        <tbody>
        @foreach ($packets as $p)
            <tr><td class="muted">{{ $p->number }}</td><td><a href="{{ route('pakketten.show', $p) }}">{{ $p->name }}</a> @if ($p->archived)<span class="badge">archief</span>@endif</td><td class="num">{{ $p->lines_count }}</td></tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div></div>
@endsection
