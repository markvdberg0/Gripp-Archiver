@extends('layouts.app', ['title' => 'Overzicht'])
@php use App\Support\Format; use App\Support\Html; @endphp

@section('content')
<div class="page-head"><div><h1>Goedendag, {{ explode(' ', auth()->user()->name)[0] }}</h1><div class="sub">{{ now()->translatedFormat('l j F Y') }}</div></div></div>

@if ($tiles)
<div class="tiles">
    @foreach ($tiles as [$label, $value, $url])
        <a class="tile" href="{{ $url }}" style="color:inherit;text-decoration:none"><div class="k">{{ $label }}</div><div class="v">{{ $value }}</div></a>
    @endforeach
</div>
@endif

<div class="grid grid-side">
    <div class="card">
        <div class="card-h"><h2>Recente notities en e-mails</h2></div>
        <div class="card-b flush">
            @forelse ($recent as $e)
                <div style="padding:10px 16px;border-bottom:1px solid var(--line)">
                    <div class="muted small">{{ Format::dateTime($e->occurred_at) }} · {{ \App\Models\TimelineEntry::TYPES[$e->type] ?? $e->type }} · {{ $e->authorName() }}
                        @if ($e->company) · <a href="{{ route('relaties.show', $e->company) }}">{{ $e->company->name }}</a>@endif</div>
                    <div>{{ $e->subject ?: Html::plain($e->body, 140) }}</div>
                </div>
            @empty
                <div class="empty">Nog geen notities.</div>
            @endforelse
        </div>
    </div>
    <div>
        @can('beheer.import')
        <div class="card">
            <div class="card-h"><h2>Laatste import</h2></div>
            <div class="card-b">
                @if ($lastImport)
                    <p>{{ Format::dateTime($lastImport->finished_at ?? $lastImport->created_at) }} · <span class="badge {{ $lastImport->status === 'geslaagd' ? 'badge-ok' : ($lastImport->status === 'mislukt' ? 'badge-danger' : 'badge-warn') }}">{{ $lastImport->status }}</span></p>
                    <a href="{{ route('beheer.import.show', $lastImport) }}">Rapport bekijken</a>
                @else
                    <p class="muted">Er is nog niets geïmporteerd.</p>
                    <a class="btn btn-sm" href="{{ route('beheer.import.index') }}">Naar import</a>
                @endif
            </div>
        </div>
        @endcan
        @include('dashboard.extra')
    </div>
</div>
@endsection
