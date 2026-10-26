@extends('layouts.app', ['title' => $project->exists ? 'Opdracht '.$project->number.' wijzigen' : 'Nieuwe opdracht'])

@section('content')
<div class="page-head">
    <div><h1>{{ $project->exists ? 'Opdracht '.$project->number : 'Nieuwe opdracht' }}</h1>
        <div class="sub">@unless ($project->exists) Krijgt nummer {{ $nextNumber }} bij opslaan. @endunless @if ($project->gripp_id) Geïmporteerd uit Gripp; na wijzigen neemt een volgende import bronwijzigingen niet meer over. @endif</div>
    </div>
</div>
<form method="post" action="{{ $project->exists ? route('opdrachten.update', $project) : route('opdrachten.store') }}">
    @csrf @if ($project->exists) @method('put') @endif
    <div class="card"><div class="card-b">
        <div class="form-grid">
            <x-veld name="name" label="Omschrijving" :value="$project->name" required wrap="span-2" />
            <x-keuze name="company_id" label="Relatie" :options="$companies" :value="$project->company_id" placeholder="— kies een relatie —" required />
            <x-keuze name="contact_id" label="Contactpersoon" :options="$contacts" :value="$project->contact_id" placeholder="—" />
            <x-keuze name="phase_id" label="Fase" :options="$phases" :value="$project->phase_id" placeholder="—" />
            <x-keuze name="account_manager_id" label="Projectleider / accountmanager" :options="$employees" :value="$project->account_manager_id" placeholder="—" />
            <x-veld name="start_date" label="Startdatum" type="date" :value="$project->start_date" />
            <x-veld name="end_date" label="Einddatum" type="date" :value="$project->end_date" />
            <x-veld name="deadline" label="Deadline" type="date" :value="$project->deadline" />
            <x-veld name="delivery_date" label="Leverdatum" type="date" :value="$project->delivery_date" />
            <x-veld name="client_reference" label="Referentie klant" :value="$project->client_reference" />
            <x-veld name="work_address" label="Werkadres" :value="$project->work_address" />
            <div class="field span-2"><label for="f_employees">Medewerkers</label>
                <select name="employees[]" id="f_employees" multiple size="5">
                    @php $selected = old('employees', $project->exists ? $project->employees->pluck('id')->all() : []); @endphp
                    @foreach ($employees as $id => $name)<option value="{{ $id }}" @selected(in_array($id, $selected))>{{ $name }}</option>@endforeach
                </select>
                <div class="hint">Ctrl/Cmd-klik om meerdere te kiezen.</div>
            </div>
            <x-tekst name="description" label="Omschrijving / afspraken" :value="$project->description" wrap="span-2" rows="4" />
        </div>
    </div></div>
    <div class="card"><div class="card-h"><h2>Regels (begroting)</h2></div><div class="card-b">@include('partials.regels', ['lines' => $project->lines ?? collect(), 'showBuying' => true])</div></div>
    <div class="actions">
        <button class="btn btn-primary" type="submit">Opslaan</button>
        <a class="btn" href="{{ $project->exists ? route('opdrachten.show', $project) : route('opdrachten.index') }}">Annuleren</a>
    </div>
</form>
@endsection
