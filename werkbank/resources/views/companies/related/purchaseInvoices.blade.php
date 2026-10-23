@php use App\Support\Format; @endphp
<div class="card">
    <div class="card-h"><h2>Inkoopfacturen</h2><a class="small" href="{{ route('inkoopfacturen.index', ['leverancier' => $company->id]) }}">Alle</a></div>
    <div class="card-b flush">
        @if ($items->isEmpty())<div class="empty">Geen inkoopfacturen.</div>@else
        <table class="list"><tbody>@foreach ($items as $i)<tr><td><a href="{{ route('inkoopfacturen.show', $i) }}">{{ $i->booking_number }}</a></td><td>{{ Format::date($i->date) }}</td><td>{{ $i->invoice_number }}</td><td><span class="badge">{{ $i->stateLabel() }}</span></td><td class="num">{{ Format::euro($i->total_incl) }}</td></tr>@endforeach</tbody></table>
        @endif
    </div>
</div>
