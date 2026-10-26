@extends('layouts.app', ['title' => 'Verlof aanvragen'])

@section('content')
<div class="page-head"><div><h1>Verlof aanvragen</h1></div><div class="actions"><a class="btn" href="{{ route('verlof.index') }}">Annuleren</a></div></div>

<form method="post" action="{{ route('verlof.store') }}" class="card">
    @csrf
    <div class="card-b">
        @if ($employees)
            <x-keuze name="employee_id" label="Medewerker" :options="$employees" :value="$employee?->id" placeholder="Ikzelf" />
        @endif
        <x-keuze name="type" label="Soort" :options="collect(\App\Models\LeaveRequest::TYPES)->map(fn ($t) => $t[0])->all()" value="vakantie" required />
        <div class="grid grid-3">
            <x-veld name="from" label="Eerste dag" type="date" required />
            <x-veld name="to" label="Laatste dag" type="date" required />
            <x-veld name="hours_per_day" label="Uren per dag" type="number" step="0.5" value="8" required />
        </div>
        <p class="muted small">Zaterdagen en zondagen worden overgeslagen. Feestdagen niet: haal die zo nodig uit de periode of vraag ze apart aan.</p>
        <x-veld name="description" label="Omschrijving" />
        <x-tekst name="comment" label="Toelichting voor de goedkeurder" rows="3" />
    </div>
    <div class="card-b actions"><button class="btn btn-primary" type="submit">Aanvragen</button></div>
</form>
@endsection
