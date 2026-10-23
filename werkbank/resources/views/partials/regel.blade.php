@php use App\Support\Bedragen; $n = "lines[$i]"; @endphp
<tr data-line>
    <td class="nowrap"><a href="#" data-up title="Omhoog" aria-label="Omhoog">▲</a> <a href="#" data-down title="Omlaag" aria-label="Omlaag">▼</a>
        @if (! empty($r['id']))<input type="hidden" name="{{ $n }}[id]" value="{{ $r['id'] }}">@endif
        @foreach ($hiddenFields ?? [] as $hf)@if (! empty($r[$hf]))<input type="hidden" name="{{ $n }}[{{ $hf }}]" value="{{ $r[$hf] }}">@endif @endforeach</td>
    <td><select name="{{ $n }}[row_type]" data-f="row_type" aria-label="Soort regel">
        @foreach (\App\Models\DocumentLine::ROW_TYPES as $k => $v)<option value="{{ $k }}" @selected(($r['row_type'] ?? 'normaal') === $k)>{{ $v }}</option>@endforeach
    </select></td>
    <td><select name="{{ $n }}[product_id]" data-f="product_id" aria-label="Product" style="max-width:180px">
        <option value="">—</option>
        @foreach ($products as $p)
            <option value="{{ $p->id }}" data-name="{{ $p->name }}" data-price="{{ Bedragen::input($p->selling_price) }}" data-unit="{{ $p->unit_id }}" data-vat="{{ $p->vat_rate_id }}" @selected((string) ($r['product_id'] ?? '') === (string) $p->id)>{{ $p->number ? $p->number.' · ' : '' }}{{ $p->name }}</option>
        @endforeach
    </select></td>
    <td><input type="text" name="{{ $n }}[title]" value="{{ $r['title'] ?? '' }}" data-f="description" aria-label="Omschrijving">
        <textarea name="{{ $n }}[description]" rows="1" placeholder="Toelichting" aria-label="Toelichting" style="min-height:32px;margin-top:3px">{{ $r['description'] ?? '' }}</textarea></td>
    <td><input type="text" inputmode="decimal" class="w-sm" name="{{ $n }}[quantity]" value="{{ Bedragen::input($r['quantity'] ?? null, 0) }}" data-f="quantity" aria-label="Aantal"></td>
    <td><select name="{{ $n }}[unit_id]" data-f="unit" aria-label="Eenheid"><option value="">—</option>@foreach ($units as $id => $name)<option value="{{ $id }}" @selected((string) ($r['unit_id'] ?? '') === (string) $id)>{{ $name }}</option>@endforeach</select></td>
    <td><input type="text" inputmode="decimal" class="w-num" name="{{ $n }}[unit_price]" value="{{ Bedragen::input($r['unit_price'] ?? null) }}" data-f="unit_price" aria-label="Prijs"></td>
    @if ($showBuying ?? false)
        <td><input type="text" inputmode="decimal" class="w-num" name="{{ $n }}[buying_price]" value="{{ Bedragen::input($r['buying_price'] ?? null) }}" aria-label="Inkoopprijs"></td>
    @endif
    <td><input type="text" inputmode="decimal" class="w-sm" name="{{ $n }}[discount]" value="{{ Bedragen::input($r['discount'] ?? 0, 0) }}" data-f="discount" aria-label="Korting"></td>
    <td><select name="{{ $n }}[vat_rate_id]" data-f="vat_rate_id" aria-label="Btw-tarief"><option value="" data-pct="0">—</option>
        @foreach ($vatRates as $v)<option value="{{ $v->id }}" data-pct="{{ $v->percentage }}" data-rc="{{ $v->reverse_charge ? 1 : 0 }}" @selected((string) ($r['vat_rate_id'] ?? '') === (string) $v->id)>{{ $v->name }}</option>@endforeach
    </select></td>
    @foreach ($extra ?? [] as $field => [$label, $options])
        <td><select name="{{ $n }}[{{ $field }}]" aria-label="{{ $label }}" style="max-width:160px"><option value="">—</option>@foreach ($options as $id => $name)<option value="{{ $id }}" @selected((string) ($r[$field] ?? '') === (string) $id)>{{ $name }}</option>@endforeach</select></td>
    @endforeach
    <td class="num" data-total></td>
    <td><a href="#" data-remove class="neg" title="Regel verwijderen" aria-label="Regel verwijderen">✕</a></td>
</tr>
