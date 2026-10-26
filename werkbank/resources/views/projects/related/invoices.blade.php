@php use App\Support\Format; @endphp
<div class="card">
    <div class="card-h"><h2>Facturen</h2><span class="muted small">{{ Format::euro($items->where('status', '!=', 'concept')->sum('total_excl')) }} excl. gefactureerd</span></div>
    <div class="card-b flush">
        @if ($items->isEmpty())<div class="empty">Nog niet gefactureerd.</div>@else
        <table class="list"><tbody>
            @foreach ($items as $i)
                <tr><td><a href="{{ route('facturen.show', $i) }}">{{ $i->number ?? 'concept' }}</a></td><td>{{ Format::date($i->date) }}</td><td>@include('invoices.status', ['invoice' => $i])</td><td class="num">{{ Format::euro($i->total_excl) }}</td></tr>
            @endforeach
        </tbody></table>
        @endif
    </div>
</div>
