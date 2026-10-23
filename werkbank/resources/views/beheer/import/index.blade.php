@extends('layouts.app', ['title' => 'Import'])
@php use App\Support\Format; @endphp

@section('content')
<div class="page-head"><div><h1>Import uit de Gripp Back-up</h1><div class="sub">Gegevens uit een back-up overnemen. Herhalen is veilig: er ontstaan geen dubbele records.</div></div></div>

<div class="grid grid-side">
    <div class="card">
        <div class="card-h"><h2>Nieuwe import</h2></div>
        <div class="card-b">
            @if (! $path)
                <div class="alert alert-warn">Er is geen back-upmap ingesteld. Zet <code>WERKBANK_IMPORT_PATH</code> in <code>.env</code> (in Docker: koppel de back-upmap aan <code>/import</code>).</div>
            @elseif ($problem)
                <div class="alert alert-error">{{ $problem }}</div>
            @else
                <dl class="props" style="margin-bottom:14px">
                    <dt>Back-upmap</dt><dd><code>{{ $path }}</code></dd>
                    <dt>Snapshot</dt><dd>{{ $snapshot['batch'] }} @if ($snapshot['status'])<span class="badge {{ $snapshot['status'] === 'complete' ? 'badge-ok' : 'badge-warn' }}">{{ $snapshot['status'] === 'complete' ? 'volledig' : $snapshot['status'] }}</span>@endif</dd>
                </dl>
                <form method="post" action="{{ route('beheer.import.store') }}">
                    @csrf
                    <details style="margin-bottom:14px"><summary>Alleen bepaalde onderdelen (standaard: alles)</summary>
                        <div class="actions" style="margin-top:8px">
                            @foreach ($entities as $e)<label class="check"><input type="checkbox" name="only[]" value="{{ $e }}"> {{ $e }}</label>@endforeach
                        </div>
                    </details>
                    @if ($running)<div class="alert alert-warn">Er loopt al een import.</div>@endif
                    <div class="actions">
                        <button class="btn" name="mode" value="droogloop" type="submit" @disabled($running)>Droogloop (alleen controleren)</button>
                        <button class="btn btn-primary" name="mode" value="import" type="submit" @disabled($running) data-confirm="Import uitvoeren? Bestaande, lokaal gewijzigde records worden niet overschreven.">Importeren</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
    <div class="card"><div class="card-h"><h2>Zo werkt het</h2></div><div class="card-b small stack">
        <p>Werkbank leest de laatste snapshot (<code>data/current</code>) en de bijlagen (<code>archive/</code>) uit de back-upmap. De back-upmap zelf wordt nooit gewijzigd.</p>
        <p>Begin met een <b>droogloop</b>: die toont wat er nieuw, gewijzigd of in conflict is, zonder iets op te slaan.</p>
        <p>Records die je in Werkbank hebt gewijzigd of verwijderd, worden niet overschreven; ze verschijnen als conflict in het rapport. Records die uit Gripp verdwenen zijn, blijven bestaan.</p>
        <p>Een import is één geheel: mislukt hij halverwege, dan verandert er niets.</p>
    </div></div>
</div>

<div class="card">
    <div class="card-h"><h2>Eerdere runs</h2></div>
    <div class="card-b flush">
        @if ($runs->isEmpty())<div class="empty">Nog geen runs.</div>@else
        <table class="list">
            <thead><tr><th>#</th><th>Gestart</th><th>Soort</th><th>Door</th><th>Status</th><th class="num">Nieuw</th><th class="num">Gewijzigd</th><th class="num">Conflicten</th><th class="num">Fouten</th></tr></thead>
            <tbody>
            @foreach ($runs as $r)
                @php $t = $r->totals(); @endphp
                <tr>
                    <td><a href="{{ route('beheer.import.show', $r) }}">{{ $r->id }}</a></td>
                    <td>{{ Format::dateTime($r->started_at ?? $r->created_at) }}</td>
                    <td>{{ $r->dry_run ? 'Droogloop' : 'Import' }}{{ $r->only ? ' (deels)' : '' }}</td>
                    <td>{{ $r->user?->name ?? 'opdrachtregel' }}</td>
                    <td>@include('beheer.import.status', ['status' => $r->status])</td>
                    <td class="num">{{ $t['created'] }}</td><td class="num">{{ $t['updated'] }}</td><td class="num">{{ $t['conflicts'] }}</td><td class="num">{{ $t['errors'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>
@endsection
