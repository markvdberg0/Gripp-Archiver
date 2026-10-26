@extends('layouts.app', ['title' => 'Account'])

@section('content')
<div class="page-head"><div><h1>Account</h1><div class="sub">{{ $user->name }} · {{ $user->email }}</div></div></div>
<div class="grid grid-2">
    <div class="card">
        <div class="card-h"><h2>Wachtwoord wijzigen</h2></div>
        <div class="card-b">
            <form method="post" action="{{ route('account.update') }}">
                @csrf @method('put')
                <x-veld name="current_password" label="Huidig wachtwoord" type="password" required autocomplete="current-password" />
                <x-veld name="password" label="Nieuw wachtwoord" type="password" required autocomplete="new-password" hint="Minstens 10 tekens, met letters en cijfers." />
                <x-veld name="password_confirmation" label="Nieuw wachtwoord herhalen" type="password" required autocomplete="new-password" />
                <button class="btn btn-primary" type="submit">Wachtwoord opslaan</button>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-h"><h2>Tweestapsverificatie</h2>@if ($user->hasTwoFactor())<span class="badge badge-ok">aan</span>@else<span class="badge">uit</span>@endif</div>
        <div class="card-b">
            <p class="small">Naast je wachtwoord een code uit een app op je telefoon. Zo kan niemand inloggen met alleen een gestolen wachtwoord.</p>
            <a class="btn" href="{{ route('account.tweestaps') }}">{{ $user->hasTwoFactor() ? 'Beheren' : 'Instellen' }}</a>
        </div>
    </div>
    <div class="card">
        <div class="card-h"><h2>Rol en rechten</h2><span class="badge badge-accent">{{ $user->roleLabel() }}</span></div>
        <div class="card-b">
            <ul class="small" style="margin:0;padding-left:18px">
                @foreach ($rechten as $r)<li>{{ \App\Support\Rechten::RECHTEN[$r] }}</li>@endforeach
            </ul>
        </div>
    </div>
</div>
@endsection
