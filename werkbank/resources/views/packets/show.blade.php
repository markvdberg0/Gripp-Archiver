@extends('layouts.app', ['title' => $packet->name])
@php use App\Support\Html; @endphp

@section('content')
<div class="page-head">
    <div><h1>{{ $packet->name }}</h1><div class="sub">Pakket {{ $packet->number }} @if ($packet->archived)<span class="badge">gearchiveerd</span>@endif</div></div>
    <div class="actions">@can('producten.bewerken')<a class="btn btn-primary" href="{{ route('pakketten.edit', $packet) }}">Wijzigen</a>@endcan</div>
</div>
@if ($packet->description)<div class="card"><div class="card-b">{{ Html::render($packet->description) }}</div></div>@endif
<div class="card"><div class="card-h"><h2>Regels</h2></div><div class="card-b flush">@include('partials.regels-tabel', ['doc' => $packet, 'vat' => $vat, 'showBuying' => true])</div></div>
@endsection
