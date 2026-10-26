@extends('layouts.app', ['title' => 'Back-ups'])
@php use App\Support\Format; @endphp

@section('content')
<div class="page-head">
    <div><h1>Back-ups</h1><div class="sub">Elke nacht om 02:30 wordt automatisch een back-up gemaakt; de nieuwste {{ $keep }} blijven bewaard.</div></div>
    <div class="actions"><form method="post" action="{{ route('beheer.backups.store') }}">@csrf<button class="btn btn-primary" type="submit">Nu een back-up maken</button></form></div>
</div>
<div class="alert alert-warn">Een back-up bevat alle gegevens en bestanden, ook persoonsgegevens. Bewaar gedownloade back-ups versleuteld en op een veilige plek. Terugzetten gaat via de opdrachtregel: <code>php artisan werkbank:herstel &lt;bestand&gt;</code> (zie de documentatie).</div>
<div class="card"><div class="card-h"><h2>Beschikbare back-ups</h2><span class="muted small"><code>{{ $dir }}</code></span></div><div class="card-b flush">
    @if (! $backups)<div class="empty">Nog geen back-ups.</div>@else
    <table class="list">
        <thead><tr><th>Bestand</th><th>Gemaakt</th><th class="num">Grootte</th><th></th></tr></thead>
        <tbody>
        @foreach ($backups as $b)
            <tr>
                <td>{{ $b['name'] }}</td>
                <td>{{ Format::dateTime(\Illuminate\Support\Carbon::createFromTimestamp($b['time'], config('app.timezone'))) }}</td>
                <td class="num">{{ number_format($b['size'] / 1048576, 1, ',', '.') }} MB</td>
                <td class="right"><a href="{{ route('beheer.backups.download', $b['name']) }}">Downloaden</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div></div>
@endsection
