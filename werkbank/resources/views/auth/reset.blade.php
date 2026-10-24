@extends('layouts.guest', ['title' => 'Nieuw wachtwoord'])

@section('content')
<div class="card"><div class="card-b">
    <form method="post" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-veld name="email" label="E-mailadres" type="email" :value="$email" required autocomplete="username" />
        <x-veld name="password" label="Nieuw wachtwoord" type="password" required autocomplete="new-password" hint="Minstens 10 tekens, met letters en cijfers." />
        <x-veld name="password_confirmation" label="Nieuw wachtwoord herhalen" type="password" required autocomplete="new-password" />
        <button class="btn btn-primary" type="submit" style="width:100%;justify-content:center">Wachtwoord opslaan</button>
    </form>
</div></div>
@endsection
