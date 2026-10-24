@extends('layouts.guest', ['title' => 'Wachtwoord vergeten'])

@section('content')
<div class="card"><div class="card-b">
    <p class="small">Vul je e-mailadres in. Je krijgt een link om een nieuw wachtwoord te kiezen.</p>
    <form method="post" action="{{ route('password.email') }}">
        @csrf
        <x-veld name="email" label="E-mailadres" type="email" required autofocus autocomplete="username" />
        <button class="btn btn-primary" type="submit" style="width:100%;justify-content:center">Link versturen</button>
    </form>
</div></div>
<p class="small" style="text-align:center"><a href="{{ route('login') }}">Terug naar inloggen</a></p>
@endsection
