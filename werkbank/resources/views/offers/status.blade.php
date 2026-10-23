@if ($offer->is_template)
<span class="badge">Sjabloon</span>
@else
<span class="badge {{ ['geaccepteerd' => 'badge-ok', 'afgewezen' => 'badge-danger', 'verzonden' => 'badge-accent', 'concept' => ''][$offer->state()] ?? '' }}">{{ $offer->stateLabel() }}</span>
@endif
