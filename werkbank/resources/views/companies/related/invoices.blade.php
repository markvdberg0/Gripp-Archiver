@php use App\Support\Format; @endphp
<div class="card">
    <div class="card-h"><h2>Verkoopfacturen</h2><a class="small" href="{{ route('facturen.index', ['relatie' => $company->id]) }}">Alle facturen</a></div>
    <div class="card-b flush">
        @if ($items->isEmpty())<div class="empty">Geen facturen.</div>@else
        <table class="list"><tbody>
            @foreach ($items as $i)
                <tr><td><a href="{{ route('facturen.show', $i) }}">{{ $i->number ?? 'concept' }}</a></td><td>{{ Format::date($i->date) }}</td><td>@include('invoices.status', ['invoice' => $i])</td><td class="num">{{ Format::euro($i->total_incl) }}</td></tr>
            @endforeach
        </tbody></table>
        @endif
    </div>
</div>
