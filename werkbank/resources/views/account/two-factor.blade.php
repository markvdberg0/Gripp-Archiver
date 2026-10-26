@extends('layouts.app', ['title' => 'Tweestapsverificatie'])

@section('content')
<div class="page-head">
    <div><h1>Tweestapsverificatie</h1><div class="sub">{{ $user->email }} · @if ($user->hasTwoFactor())<span class="badge badge-ok">aan</span>@else<span class="badge">uit</span>@endif</div></div>
    <div class="actions"><a class="btn" href="{{ route('account.edit') }}">Terug naar account</a></div>
</div>

@if ($codes)
<div class="card">
    <div class="card-h"><h2>Herstelcodes</h2></div>
    <div class="card-b">
        <p class="small">Met een herstelcode log je in als je telefoon kwijt is. Elke code werkt één keer. <strong>Ze worden maar één keer getoond</strong>: bewaar ze in je wachtwoordkluis of print ze.</p>
        <pre class="codes">@foreach ($codes as $c){{ $c }}
@endforeach</pre>
    </div>
</div>
@endif

@if (! $user->hasTwoFactor())
<div class="card">
    <div class="card-h"><h2>Instellen</h2></div>
    <div class="card-b">
        <ol class="small">
            <li>Installeer een authenticator-app op je telefoon (bijvoorbeeld een opensource-app als Aegis of FreeOTP, of die van je wachtwoordkluis).</li>
            <li>Scan de QR-code, of voer de sleutel handmatig in.</li>
            <li>Vul de code van zes cijfers in die de app toont.</li>
        </ol>
        <div class="qr">{!! $qr !!}</div>
        <p class="small">Sleutel: <code>{{ $secret }}</code></p>
        <form method="post" action="{{ route('account.tweestaps.store') }}">
            @csrf
            <x-veld name="code" label="Code uit de app" inputmode="numeric" autocomplete="one-time-code" required autofocus />
            <button class="btn btn-primary" type="submit">Bevestigen en aanzetten</button>
        </form>
    </div>
</div>
@else
<div class="grid grid-2">
    <div class="card">
        <div class="card-h"><h2>Herstelcodes vernieuwen</h2></div>
        <div class="card-b">
            <p class="small">Nog {{ $remaining }} ongebruikte {{ $remaining === 1 ? 'herstelcode' : 'herstelcodes' }}. Nieuwe codes maken maakt de oude ongeldig.</p>
            <form method="post" action="{{ route('account.tweestaps.codes') }}">
                @csrf
                <x-veld name="current_password" label="Huidig wachtwoord" type="password" required autocomplete="current-password" />
                <button class="btn" type="submit">Nieuwe herstelcodes</button>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-h"><h2>Uitzetten</h2></div>
        <div class="card-b">
            <p class="small">Daarna is alleen je wachtwoord nog nodig om in te loggen.</p>
            <form method="post" action="{{ route('account.tweestaps.destroy') }}" data-confirm="Tweestapsverificatie uitzetten?">
                @csrf @method('delete')
                <x-veld name="wachtwoord_uit" label="Huidig wachtwoord" type="password" required autocomplete="current-password" />
                <button class="btn btn-danger" type="submit">Uitzetten</button>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
