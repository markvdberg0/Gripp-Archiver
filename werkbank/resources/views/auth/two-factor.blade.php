@extends('layouts.guest', ['title' => 'Tweestapsverificatie'])

@section('content')
<div class="card"><div class="card-b">
    <p class="small">Vul de code van zes cijfers uit je authenticator-app in. Telefoon kwijt? Gebruik een van je herstelcodes.</p>
    <form method="post" action="{{ route('login.tweestaps') }}">
        @csrf
        <x-veld name="code" label="Code" inputmode="numeric" autocomplete="one-time-code" required autofocus />
        <button class="btn btn-primary" type="submit" style="width:100%;justify-content:center">Inloggen</button>
    </form>
</div></div>
<p class="small" style="text-align:center"><a href="{{ route('login') }}">Opnieuw beginnen</a></p>
@endsection
