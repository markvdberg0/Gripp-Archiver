@extends('layouts.app', ['title' => $contact->name])

@section('content')
<div class="page-head">
    <div><h1>{{ $contact->name }}</h1>
        <div class="sub">{{ $contact->function }} @if ($contact->company) bij <a href="{{ route('relaties.show', $contact->company) }}">{{ $contact->company->name }}</a>@endif
            @unless ($contact->active)<span class="badge">inactief</span>@endunless
            @foreach ($contact->tags as $tag)<span class="badge">{{ $tag->name }}</span> @endforeach
        </div>
    </div>
    <div class="actions">
        @can('relaties.bewerken')
            <a class="btn btn-primary" href="{{ route('contactpersonen.edit', $contact) }}">Wijzigen</a>
            <form method="post" action="{{ route('contactpersonen.destroy', $contact) }}" class="inline" data-confirm="Contactpersoon verwijderen?">@csrf @method('delete')<button class="btn btn-danger" type="submit">Verwijderen</button></form>
        @endcan
    </div>
</div>
<div class="grid grid-side">
    <div class="card"><div class="card-h"><h2>Timeline</h2></div>@include('partials.timeline', ['entries' => $timeline])</div>
    <div class="card"><div class="card-h"><h2>Gegevens</h2></div><div class="card-b">
        <dl class="props">
            <dt>Aanhef</dt><dd>{{ \App\Models\Contact::AANHEF[$contact->salutation] ?? '—' }}</dd>
            <dt>E-mail</dt><dd>@if ($contact->email)<a href="mailto:{{ $contact->email }}">{{ $contact->email }}</a>@else — @endif</dd>
            <dt>Telefoon</dt><dd>{{ $contact->phone ?: '—' }}</dd>
            <dt>Mobiel</dt><dd>{{ $contact->mobile ?: '—' }}</dd>
            <dt>Afdeling</dt><dd>{{ $contact->department ?: '—' }}</dd>
            @if ($contact->notes)<dt>Notities</dt><dd>{!! nl2br(e($contact->notes)) !!}</dd>@endif
        </dl>
    </div></div>
</div>
@endsection
