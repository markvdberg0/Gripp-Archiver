@extends('layouts.app', ['title' => 'Stamgegevens'])

@section('content')
<div class="page-head"><div><h1>Stamgegevens</h1><div class="sub">Keuzelijsten die in relaties, producten en documenten worden gebruikt.</div></div></div>
<div class="tiles">
    @foreach ($types as $soort => $t)
        <a class="tile" href="{{ route('beheer.stamgegevens.show', $soort) }}" style="color:inherit;text-decoration:none"><div class="k">{{ $t['title'] }}</div><div class="v">{{ $t['count'] }}</div></a>
    @endforeach
</div>
@endsection
