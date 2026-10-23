@extends('layouts.app', ['title' => 'Zoeken'])

@section('content')
<div class="page-head"><div><h1>Zoeken</h1>@if ($q !== '')<div class="sub">Resultaten voor “{{ $q }}”</div>@endif</div></div>
@if (mb_strlen($q) < 2)
    <div class="card"><div class="empty">Typ minstens twee tekens in het zoekveld bovenaan.</div></div>
@elseif (! $results)
    <div class="card"><div class="empty">Niets gevonden.</div></div>
@else
    <div class="grid grid-2">
        @foreach ($results as $label => $items)
            <div class="card">
                <div class="card-h"><h2>{{ $label }}</h2><span class="muted small">{{ $items->count() >= 20 ? '20+' : $items->count() }}</span></div>
                <div class="card-b flush">
                    @foreach ($items as $i)
                        <div style="padding:8px 16px;border-bottom:1px solid var(--line)"><a href="{{ $i['url'] }}">{{ $i['title'] }}</a> <span class="muted small">{{ $i['sub'] }}</span></div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection
