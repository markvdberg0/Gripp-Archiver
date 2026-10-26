@extends('layouts.app', ['title' => $invoice->exists ? $invoice->title().' wijzigen' : 'Nieuwe factuur'])

@section('content')
<div class="page-head">
    <div><h1>{{ $invoice->exists ? $invoice->title() : 'Nieuwe factuur' }}</h1>
        <div class="sub">Een concept krijgt pas een nummer als je hem definitief maakt.
            @if ($project && ! $invoice->exists) Voorstel op basis van <a href="{{ route('opdrachten.show', $project) }}">opdracht {{ $project->number }}</a>{{ request('percentage') ? ' (termijn '.request('percentage').'%)' : ': de nog niet gefactureerde regels' }}.@endif
        </div>
    </div>
</div>
@if ($project && isset($invoiced))
    @php $pct = (float) $project->total_excl ? round($invoiced / (float) $project->total_excl * 100, 1) : 0; @endphp
    <div class="alert {{ $invoiced > 0 ? 'alert-warn' : 'alert-ok' }}">
        Opdracht {{ $project->number }}: totaal {{ \App\Support\Format::euro($project->total_excl) }} excl. btw, waarvan al gefactureerd {{ \App\Support\Format::euro($invoiced) }} ({{ \App\Support\Format::number($pct, 1) }}%).
        @if ($invoiced > 0 && ! request('percentage')) Het voorstel hieronder telt alleen facturen mee die per regel aan de opdracht gekoppeld zijn; controleer het tegen eerdere termijnfacturen. @endif
    </div>
@endif
<form method="post" action="{{ $invoice->exists ? route('facturen.update', $invoice) : route('facturen.store') }}">
    @csrf @if ($invoice->exists) @method('put') @endif
    <div class="card"><div class="card-b">
        <div class="form-grid">
            <x-veld name="subject" label="Onderwerp" :value="$invoice->subject" required wrap="span-2" />
            <x-keuze name="company_id" label="Relatie" :options="$companies" :value="$invoice->company_id" placeholder="— kies een relatie —" required />
            <x-keuze name="contact_id" label="Contactpersoon (t.a.v.)" :options="$contacts" :value="$invoice->contact_id" placeholder="—" />
            <x-keuze name="project_id" label="Opdracht" :options="$projects" :value="$invoice->project_id" placeholder="—" />
            <x-veld name="client_reference" label="Referentie klant / ordernummer" :value="$invoice->client_reference" />
            <x-veld name="date" label="Factuurdatum" type="date" :value="$invoice->date" hint="Leeg: de datum van definitief maken." />
            <x-veld name="due_date" label="Vervaldatum" type="date" :value="$invoice->due_date" hint="Leeg: factuurdatum + betaaltermijn van de relatie." />
            <x-keuze name="payment_method" label="Betaalwijze" :options="\App\Models\Company::BETAALWIJZEN" :value="$invoice->payment_method" />
            <x-tekst name="description" label="Toelichting" :value="$invoice->description" wrap="span-2" rows="3" />
        </div>
    </div></div>
    <div class="card"><div class="card-h"><h2>Regels</h2></div><div class="card-b">
        @include('partials.regels', ['lines' => $prefill, 'hiddenFields' => ['project_id', 'project_line_id'], 'showBuying' => false])
    </div></div>
    <div class="actions">
        <button class="btn btn-primary" type="submit">Opslaan als concept</button>
        <a class="btn" href="{{ $invoice->exists ? route('facturen.show', $invoice) : route('facturen.index') }}">Annuleren</a>
    </div>
</form>
@endsection
