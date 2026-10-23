@extends('layouts.app', ['title' => $title])
@php use App\Support\Format; @endphp

@section('content')
<div class="page-head"><div><h1>{{ $title }}</h1><div class="sub"><a href="{{ route('beheer.stamgegevens.index') }}">Stamgegevens</a></div></div></div>
<div class="grid grid-side">
    <div class="card"><div class="card-b flush">
        @if ($items->isEmpty())<div class="empty">Nog niets ingevoerd. Na een import uit Gripp staan hier de gebruikte waarden.</div>@else
        <table class="list">
            <thead><tr>@foreach ($fields as [$label])<th>{{ $label }}</th>@endforeach<th></th></tr></thead>
            <tbody>
            @foreach ($items as $item)
                <tr>
                    @foreach ($fields as $name => $f)
                        <td>
                            @if ($f[1] === 'bool'){{ $item->{$name} ? 'ja' : 'nee' }}
                            @elseif ($f[1] === 'select'){{ $f[3][$item->{$name}] ?? $item->{$name} }}
                            @elseif ($f[1] === 'decimal'){{ Format::number($item->{$name}) }}
                            @else{{ $item->{$name} }}@endif
                        </td>
                    @endforeach
                    <td class="right"><a href="{{ route('beheer.stamgegevens.edit', [$soort, $item->id]) }}">Wijzigen</a> @if ($item->gripp_id)<span class="muted small" title="Uit Gripp">G</span>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @endif
    </div></div>
    <div class="card"><div class="card-h"><h2>Toevoegen</h2></div><div class="card-b">
        <form method="post" action="{{ route('beheer.stamgegevens.store', $soort) }}">
            @csrf
            @include('beheer.stamgegevens.fields')
            <button class="btn btn-primary" type="submit">Toevoegen</button>
        </form>
    </div></div>
</div>
@endsection
