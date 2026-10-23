@extends('layouts.app', ['title' => 'Medewerkers'])
@php use App\Support\Format; @endphp

@section('content')
<div class="page-head">
    <div><h1>Medewerkers</h1><div class="sub">Accountmanagers, uitvoerders en urenschrijvers. Inloggen gaat via een gebruiker.</div></div>
    <div class="actions"><a class="btn btn-primary" href="{{ route('beheer.medewerkers.create') }}">Nieuwe medewerker</a></div>
</div>
<div class="card"><div class="card-b flush">
    @if ($employees->isEmpty())<div class="empty">Nog geen medewerkers.</div>@else
    <table class="list">
        <thead><tr><th>Nr.</th><th>Naam</th><th>Functie</th><th>E-mail</th><th>In dienst sinds</th><th>Gebruiker</th><th>Status</th></tr></thead>
        <tbody>
        @foreach ($employees as $e)
            <tr>
                <td class="muted">{{ $e->number }}</td>
                <td><a href="{{ route('beheer.medewerkers.edit', $e) }}">{{ $e->name }}</a></td>
                <td>{{ $e->function }}</td>
                <td>{{ $e->email }}</td>
                <td>{{ Format::date($e->employed_since) }}</td>
                <td>{{ $e->user?->email }}</td>
                <td>@if ($e->active)<span class="badge badge-ok">actief</span>@else<span class="badge">inactief</span>@endif</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div></div>
@endsection
