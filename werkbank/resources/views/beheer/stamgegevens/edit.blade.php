@extends('layouts.app', ['title' => $title.' wijzigen'])

@section('content')
<div class="page-head"><div><h1>{{ $title }}: {{ $item->name ?? $item->id }}</h1><div class="sub"><a href="{{ route('beheer.stamgegevens.show', $soort) }}">Terug naar {{ mb_strtolower($title) }}</a></div></div></div>
<form method="post" action="{{ route('beheer.stamgegevens.update', [$soort, $item->id]) }}" class="card" style="max-width:560px">
    @csrf @method('put')
    <div class="card-b">
        @include('beheer.stamgegevens.fields')
        <button class="btn btn-primary" type="submit">Opslaan</button>
    </div>
</form>
@endsection
