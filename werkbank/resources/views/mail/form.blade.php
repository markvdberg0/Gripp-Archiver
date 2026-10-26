@extends('layouts.app', ['title' => 'Versturen per e-mail'])

@section('content')
<div class="page-head">
    <div><h1>Versturen per e-mail</h1>
        <div class="sub">{{ ['offerte' => 'Offerte', 'factuur' => 'Factuur', 'herinnering' => 'Herinnering bij factuur', 'opdracht' => 'Opdrachtbevestiging', 'inkoopopdracht' => 'Inkoopopdracht'][$soort] }} {{ $doc->number }} · de PDF gaat als bijlage mee.</div></div>
    <div class="actions"><a class="btn" href="{{ $back }}">Annuleren</a></div>
</div>
@if ($post)<div class="alert alert-warn">Deze relatie wil facturen per post ontvangen (zie de relatie). Je kunt de factuur ook downloaden en printen.</div>@endif
@if ($soort === 'herinnering')<div class="alert alert-warn">Bij versturen wordt de volgende herinneringsfase vastgelegd.</div>@endif
@if ($soort === 'offerte' && $doc->status === 'concept')<div class="alert alert-ok">De offerte krijgt bij versturen de status "verzonden".</div>@endif
<form method="post" action="{{ route('mail.send', [$soort, $doc->id]) }}" class="card" style="max-width:820px">
    @csrf
    <div class="card-b">
        <x-veld name="to" label="Aan" :value="$to" required hint="Meerdere adressen scheiden met een komma." />
        <x-veld name="cc" label="Cc" />
        <x-veld name="subject" label="Onderwerp" :value="$subject" required />
        <x-tekst name="body" label="Tekst" :value="$body" required rows="12" />
        <x-vink name="bcc_self" label="Stuur mij een kopie ({{ auth()->user()->email }})" :checked="true" />
        @if (in_array($soort, ['factuur', 'herinnering'], true))
            <x-vink name="ubl" label="UBL-bestand (e-factuur) meesturen" :checked="false" hint="Voor ontvangers die facturen automatisch inlezen in hun boekhouding." />
        @endif
        <p class="muted small">Antwoorden komen bij jou binnen (reply-to). De verzonden tekst en PDF worden in de timeline bewaard.</p>
        <div class="actions"><button class="btn btn-primary" type="submit">Versturen</button><a class="btn" href="{{ $back }}">Annuleren</a></div>
    </div>
</form>
@endsection
