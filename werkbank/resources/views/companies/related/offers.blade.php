@php use App\Support\Format; @endphp
<div class="card">
    <div class="card-h"><h2>Offertes</h2><a class="small" href="{{ route('offertes.index', ['relatie' => $company->id, 'archief' => 1]) }}">Alle offertes</a></div>
    <div class="card-b flush">
        @if ($items->isEmpty())<div class="empty">Geen offertes.</div>@else
        <table class="list"><tbody>
            @foreach ($items as $o)
                <tr><td><a href="{{ route('offertes.show', $o) }}">{{ $o->number }}</a></td><td>{{ Format::date($o->date) }}</td><td>{{ $o->name }}</td><td>@include('offers.status', ['offer' => $o])</td><td class="num">{{ Format::euro($o->total_excl) }}</td></tr>
            @endforeach
        </tbody></table>
        @endif
    </div>
</div>
