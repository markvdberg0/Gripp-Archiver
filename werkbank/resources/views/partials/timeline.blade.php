@php use App\Support\Format; use App\Support\Html; @endphp
@if ($entries->isEmpty())
    <div class="empty">Nog niets in de timeline.</div>
@else
    <ul class="timeline">
        @foreach ($entries as $e)
            <li>
                <div class="meta">
                    <span class="badge {{ in_array($e->type, ['email_ontvangen', 'email_verzonden']) ? 'badge-accent' : '' }}">{{ \App\Models\TimelineEntry::TYPES[$e->type] ?? $e->type }}</span>
                    <span>{{ Format::dateTime($e->occurred_at) }}</span>
                    <span>{{ $e->authorName() }}</span>
                    @if ($e->starred)<span title="Gemarkeerd">★</span>@endif
                </div>
                @if ($e->subject)<div><strong>{{ $e->subject }}</strong></div>@endif
                @if ($e->body)<div class="rich">{{ Html::render($e->body) }}</div>@endif
                @if ($e->files->isNotEmpty())
                    <div class="small" style="margin-top:6px">
                        @foreach ($e->files as $f)<a href="{{ route('bestanden.show', $f) }}">📎 {{ $f->name }}</a> @endforeach
                    </div>
                @endif
            </li>
        @endforeach
    </ul>
    @if (method_exists($entries, 'links')){{ $entries->links() }}@endif
@endif
