{{-- Alleen-lezen weergave van documentregels met totalen. $doc (met lines), $vat (vatBreakdown), $showBuying optioneel --}}
@php use App\Support\Format; use App\Support\Html; $showBuying = $showBuying ?? false; @endphp
<table class="list">
    <thead><tr><th>Omschrijving</th><th class="num">Aantal</th><th>Eenheid</th><th class="num">Prijs</th>@if ($showBuying)<th class="num">Inkoop</th>@endif<th class="num">Korting</th><th>Btw</th><th class="num">Totaal</th></tr></thead>
    <tbody>
    @forelse ($doc->lines as $l)
        @if ($l->row_type === 'groep')
            <tr class="group"><td colspan="{{ $showBuying ? 8 : 7 }}">{{ $l->label() }}</td></tr>
        @elseif ($l->row_type === 'tekst')
            <tr><td colspan="{{ $showBuying ? 8 : 7 }}" class="muted">{{ $l->label() }} @if ($l->description)<div>{{ Html::render($l->description) }}</div>@endif</td></tr>
        @else
            <tr>
                <td>{{ $l->label() }}@if ($l->description)<div class="muted small">{{ Html::render($l->description) }}</div>@endif
                    @if (isset($extraLine)) {{ $extraLine($l) }} @endif</td>
                <td class="num">{{ Format::number($l->quantity, 4) }}</td>
                <td>{{ $l->unit?->name }}</td>
                <td class="num">{{ Format::euro($l->unit_price) }}</td>
                @if ($showBuying)<td class="num muted">{{ (float) $l->buying_price ? Format::euro($l->buying_price) : '' }}</td>@endif
                <td class="num">{{ (float) $l->discount ? Format::number($l->discount).'%' : '' }}</td>
                <td class="small">{{ $l->vatRate?->name }}</td>
                <td class="num">{{ Format::euro($l->total_excl) }}</td>
            </tr>
        @endif
    @empty
        <tr><td colspan="8" class="empty">Geen regels.</td></tr>
    @endforelse
    </tbody>
    <tfoot>
        <tr><td colspan="{{ $showBuying ? 7 : 6 }}" class="right">Totaal excl. btw</td><td class="num">{{ Format::euro($doc->total_excl) }}</td></tr>
        @foreach ($vat as $v)
            <tr><td colspan="{{ $showBuying ? 7 : 6 }}" class="right muted">{{ $v['name'] }} over {{ Format::euro($v['base']) }}</td><td class="num">{{ Format::euro($v['vat']) }}</td></tr>
        @endforeach
        <tr><td colspan="{{ $showBuying ? 7 : 6 }}" class="right">Totaal incl. btw</td><td class="num">{{ Format::euro($doc->total_incl) }}</td></tr>
    </tfoot>
</table>
