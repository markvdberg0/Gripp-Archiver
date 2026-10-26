@extends('layouts.app', ['title' => 'Gebruikers'])
@php use App\Support\Format; @endphp

@section('content')
<div class="page-head">
    <div><h1>Gebruikers</h1><div class="sub">Wie mag inloggen, en met welke rol.</div></div>
    <div class="actions"><a class="btn btn-primary" href="{{ route('beheer.gebruikers.create') }}">Nieuwe gebruiker</a></div>
</div>
<div class="card"><div class="card-b flush">
    <table class="list">
        <thead><tr><th>Naam</th><th>E-mail</th><th>Rol</th><th>Medewerker</th><th>Laatst ingelogd</th><th>Status</th></tr></thead>
        <tbody>
        @foreach ($users as $u)
            <tr>
                <td><a href="{{ route('beheer.gebruikers.edit', $u) }}">{{ $u->name }}</a></td>
                <td>{{ $u->email }}</td>
                <td>{{ $u->roleLabel() }}</td>
                <td>{{ $u->employee?->name }}</td>
                <td>{{ Format::dateTime($u->last_login_at) ?: '—' }}</td>
                <td>@if ($u->active)<span class="badge badge-ok">actief</span>@else<span class="badge">inactief</span>@endif @if ($u->must_change_password)<span class="badge badge-warn">tijdelijk wachtwoord</span>@endif @if ($u->hasTwoFactor())<span class="badge">2FA</span>@endif</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div></div>
<div class="card">
    <div class="card-h"><h2>Rollen</h2></div>
    <div class="card-b">
        <table class="list">
            <thead><tr><th>Recht</th>@foreach (\App\Support\Rechten::ROLLEN as $label)<th class="num">{{ $label }}</th>@endforeach</tr></thead>
            <tbody>
            @foreach (\App\Support\Rechten::RECHTEN as $recht => $omschrijving)
                <tr><td>{{ $omschrijving }}</td>@foreach (\App\Support\Rechten::ROLLEN as $rol => $label)<td class="num">{{ \App\Support\Rechten::heeft($rol, $recht) ? '✓' : '' }}</td>@endforeach</tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
