@extends('layouts.guest', ['title' => 'Inloggen'])

@section('content')
<div class="card">
    <div class="card-b">
        <form method="post" action="{{ route('login') }}">
            @csrf
            <x-veld name="email" label="E-mailadres" type="email" required autofocus autocomplete="username" />
            <x-veld name="password" label="Wachtwoord" type="password" required autocomplete="current-password" />
            <x-vink name="remember" label="Ingelogd blijven op deze computer" />
            <button class="btn btn-primary" type="submit" style="width:100%;justify-content:center">Inloggen</button>
        </form>
    </div>
</div>
<p class="muted small" style="text-align:center"><a href="{{ route('password.request') }}">Wachtwoord vergeten?</a></p>
@endsection
