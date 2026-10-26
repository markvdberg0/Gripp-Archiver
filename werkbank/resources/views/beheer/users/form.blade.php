@extends('layouts.app', ['title' => $user->exists ? 'Gebruiker wijzigen' : 'Nieuwe gebruiker'])

@section('content')
<div class="page-head"><h1>{{ $user->exists ? $user->name : 'Nieuwe gebruiker' }}</h1></div>
<div class="grid grid-side">
    <form method="post" action="{{ $user->exists ? route('beheer.gebruikers.update', $user) : route('beheer.gebruikers.store') }}" class="card">
        @csrf @if ($user->exists) @method('put') @endif
        <div class="card-b">
            <x-veld name="name" label="Naam" :value="$user->name" required />
            <x-veld name="email" label="E-mailadres" type="email" :value="$user->email" required />
            <x-keuze name="role" label="Rol" :options="\App\Support\Rechten::ROLLEN" :value="$user->role" required />
            <x-keuze name="employee_id" label="Gekoppelde medewerker" :options="$employees" :value="$user->employee?->id" placeholder="—" hint="Voor uren, taken en als auteur van notities." />
            <x-vink name="active" label="Actief (mag inloggen)" :checked="$user->active" />
            @unless ($user->exists)<p class="muted small">Na opslaan krijg je een tijdelijk wachtwoord te zien. De gebruiker kiest bij de eerste keer inloggen een eigen wachtwoord.</p>@endunless
            <div class="actions"><button class="btn btn-primary" type="submit">Opslaan</button><a class="btn" href="{{ route('beheer.gebruikers.index') }}">Annuleren</a></div>
        </div>
    </form>
    @if ($user->exists)
    <div class="card">
        <div class="card-h"><h2>Wachtwoord</h2></div>
        <div class="card-b">
            <p class="small">Maakt een nieuw tijdelijk wachtwoord en logt de gebruiker overal uit.</p>
            <form method="post" action="{{ route('beheer.gebruikers.wachtwoord', $user) }}" data-confirm="Nieuw tijdelijk wachtwoord maken?">@csrf<button class="btn" type="submit">Nieuw tijdelijk wachtwoord</button></form>
        </div>
    </div>
    <div class="card">
        <div class="card-h"><h2>Tweestapsverificatie</h2>@if ($user->hasTwoFactor())<span class="badge badge-ok">aan</span>@else<span class="badge">uit</span>@endif</div>
        <div class="card-b">
            @if ($user->hasTwoFactor())
                <p class="small">Telefoon kwijt en geen herstelcodes meer? Zet tweestapsverificatie uit; de gebruiker wordt overal uitgelogd en stelt hem daarna opnieuw in. Controleer eerst of het verzoek echt van deze persoon komt.</p>
                <form method="post" action="{{ route('beheer.gebruikers.tweestaps', $user) }}" data-confirm="Tweestapsverificatie van deze gebruiker uitzetten?">@csrf<button class="btn btn-danger" type="submit">Tweestapsverificatie uitzetten</button></form>
            @else
                <p class="small">De gebruiker zet dit zelf aan onder Account › Tweestapsverificatie.</p>
            @endif
        </div>
    </div>
    @endif
</div>
@endsection
