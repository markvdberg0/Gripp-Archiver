@extends('layouts.app', ['title' => 'Importrapport #'.$run->id])
@php use App\Support\Format; $t = $run->totals(); $f = $run->summary['files'] ?? []; @endphp

@section('content')
<div class="page-head">
    <div><h1>{{ $run->dry_run ? 'Droogloop' : 'Import' }} #{{ $run->id }}</h1>
        <div class="sub">@include('beheer.import.status', ['status' => $run->status]) · gestart {{ Format::dateTime($run->started_at ?? $run->created_at) }} door {{ $run->user?->name ?? 'opdrachtregel' }}
            @if ($run->summary['seconds'] ?? null) · {{ $run->summary['seconds'] }} s @endif</div>
    </div>
    <div class="actions"><a class="btn" href="{{ route('beheer.import.index') }}">Terug naar import</a></div>
</div>

@if (in_array($run->status, ['wachtrij', 'bezig']))
    <div class="alert alert-warn">De import loopt nog. Ververs deze pagina over enkele ogenblikken. Staat hij lang op “wachtrij”, controleer dan of de queue-worker draait (zie de installatiehandleiding).</div>
@endif
@if ($run->error)<div class="alert alert-error">Mislukt: {{ $run->error }} Er is niets gewijzigd.</div>@endif
@if ($run->dry_run && $run->status === 'geslaagd')<div class="alert alert-ok">Dit was een droogloop: er is niets opgeslagen. Hieronder staat wat een echte import zou doen.</div>@endif

<div class="tiles">
    <div class="tile"><div class="k">Nieuw</div><div class="v">{{ $t['created'] }}</div></div>
    <div class="tile"><div class="k">Gewijzigd</div><div class="v">{{ $t['updated'] }}</div></div>
    <div class="tile"><div class="k">Ongewijzigd</div><div class="v">{{ $t['unchanged'] }}</div></div>
    <div class="tile"><div class="k">Conflicten</div><div class="v">{{ $t['conflicts'] }}</div></div>
    <div class="tile"><div class="k">Fouten</div><div class="v">{{ $t['errors'] }}</div></div>
    <div class="tile"><div class="k">Bestanden zonder inhoud</div><div class="v">{{ $f['missing'] ?? 0 }}</div></div>
</div>

<div class="card">
    <div class="card-h"><h2>Per onderdeel</h2><span class="muted small">Snapshot {{ $run->summary['batch'] ?? '—' }} uit <code>{{ $run->source_path }}</code></span></div>
    <div class="card-b flush">
        <table class="list">
            <thead><tr><th>Onderdeel</th><th class="num">In bron</th><th class="num">Nieuw</th><th class="num">Gewijzigd</th><th class="num">Ongewijzigd</th><th class="num">Conflicten</th><th class="num">Overgeslagen</th><th class="num">Fouten</th><th class="num">Niet meer in bron</th></tr></thead>
            <tbody>
            @foreach ($run->summary['entities'] ?? [] as $entity => $s)
                <tr>
                    <td>{{ $entity }}</td>
                    <td class="num">{{ $s['source'] ?? 0 }}</td><td class="num">{{ $s['created'] ?? 0 }}</td><td class="num">{{ $s['updated'] ?? 0 }}</td>
                    <td class="num">{{ $s['unchanged'] ?? 0 }}</td><td class="num">{{ $s['conflicts'] ?? 0 }}</td><td class="num">{{ $s['skipped'] ?? 0 }}</td>
                    <td class="num">{{ $s['errors'] ?? 0 }}</td><td class="num">{{ $s['missing_in_source'] ?? '' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

@if (! empty($run->summary['checks']))
<div class="card">
    <div class="card-h"><h2>Controletotalen</h2><span class="muted small">Bron (back-up) tegenover Werkbank na import</span></div>
    <div class="card-b flush">
        <table class="list">
            <thead><tr><th>Controle</th><th class="num">Bron</th><th class="num">Werkbank</th><th></th></tr></thead>
            <tbody>
            @foreach ($run->summary['checks'] as $c)
                <tr><td>{{ $c['label'] }}</td><td class="num">{{ $c['source'] }}</td><td class="num">{{ $c['local'] }}</td><td>@if ($c['ok'])<span class="badge badge-ok">klopt</span>@else<span class="badge badge-danger">verschil</span>@endif</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@if ($run->issues)
<div class="card">
    <div class="card-h"><h2>Meldingen</h2><span class="muted small">Maximaal 200 per onderdeel</span></div>
    <div class="card-b flush">
        <table class="list">
            <thead><tr><th>Onderdeel</th><th>Gripp-id</th><th>Soort</th><th>Melding</th></tr></thead>
            <tbody>
            @foreach ($run->issues as $entity => $issues)
                @foreach ($issues as $i)
                    <tr><td>{{ $entity }}</td><td>{{ $i['gripp_id'] }}</td><td><span class="badge {{ $i['type'] === 'fout' ? 'badge-danger' : ($i['type'] === 'conflict' ? 'badge-warn' : '') }}">{{ $i['type'] }}</span></td><td>{{ $i['message'] }}</td></tr>
                @endforeach
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
