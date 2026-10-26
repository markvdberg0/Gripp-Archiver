@php use App\Support\Format; @endphp
<div class="card">
    <div class="card-h"><h2>Opdrachten</h2><a class="small" href="{{ route('opdrachten.index', ['relatie' => $company->id, 'archief' => 1]) }}">Alle opdrachten</a></div>
    <div class="card-b flush">
        @if ($items->isEmpty())<div class="empty">Geen opdrachten.</div>@else
        <table class="list"><tbody>
            @foreach ($items as $p)
                <tr><td><a href="{{ route('opdrachten.show', $p) }}">{{ $p->number }}</a></td><td>{{ $p->name }}</td><td>{{ $p->archived ? 'archief' : '' }}</td><td class="num">{{ Format::euro($p->total_excl) }}</td></tr>
            @endforeach
        </tbody></table>
        @endif
    </div>
</div>
