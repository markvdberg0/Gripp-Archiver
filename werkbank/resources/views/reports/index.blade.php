@extends('layouts.app', ['title' => 'Overzichten'])

@section('content')
<div class="page-head"><div><h1>Overzichten</h1><div class="sub">Elk overzicht is ook als CSV te downloaden (opent in Excel).</div></div></div>
<div class="grid grid-2">
    @can('facturen.bekijken')
    <a class="card" href="{{ route('rapporten.open', 'debiteuren') }}" style="color:inherit;text-decoration:none"><div class="card-b"><h2>Openstaande debiteuren</h2><p class="muted">Openstaande verkoopfacturen op een peildatum, per klant en naar ouderdom.</p></div></a>
    <a class="card" href="{{ route('rapporten.omzet') }}" style="color:inherit;text-decoration:none"><div class="card-b"><h2>Omzet en inkoop per maand</h2><p class="muted">Gefactureerde omzet en geboekte inkoop per maand, excl. btw.</p></div></a>
    @endcan
    @can('inkoop.bekijken')
    <a class="card" href="{{ route('rapporten.open', 'crediteuren') }}" style="color:inherit;text-decoration:none"><div class="card-b"><h2>Openstaande crediteuren</h2><p class="muted">Openstaande inkoopfacturen op een peildatum, per leverancier en naar ouderdom.</p></div></a>
    @endcan
    @can('offertes.bekijken')
    <a class="card" href="{{ route('rapporten.pijplijn') }}" style="color:inherit;text-decoration:none"><div class="card-b"><h2>Offertepijplijn</h2><p class="muted">Open offertes per fase met totaal en gewogen waarde (bedrag × kans).</p></div></a>
    @endcan
    @can('uren.fiatteren')
    <a class="card" href="{{ route('rapporten.uren') }}" style="color:inherit;text-decoration:none"><div class="card-b"><h2>Uren per medewerker</h2><p class="muted">Geschreven en gefiatteerde uren per maand.</p></div></a>
    @endcan
</div>
<p class="muted small">Winst-en-verliesrekening, balans en btw-aangifte zitten niet in Werkbank: die horen in het boekhoudpakket (zie de analyse).</p>
@endsection
