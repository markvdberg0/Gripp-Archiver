@php use App\Support\Format; @endphp
<div class="card">
    <div class="card-h"><h2>Bestanden</h2><span class="muted small">{{ $model->files->count() }}</span></div>
    <div class="card-b flush">
        @forelse ($model->files as $f)
            <div style="padding:8px 16px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;gap:8px">
                <span>
                    @if ($f->path)<a href="{{ route('bestanden.show', $f) }}">{{ $f->name }}</a>@else<span class="muted" title="Inhoud ontbreekt in de back-up">{{ $f->name }}</span>@endif
                    @if ($f->pivot->role !== 'bijlage')<span class="badge">{{ $f->pivot->role }}</span>@endif
                </span>
                <span class="muted small nowrap">{{ $f->path ? $f->sizeLabel() : 'geen inhoud' }}</span>
            </div>
        @empty
            <div class="empty">Geen bestanden.</div>
        @endforelse
    </div>
    @if ($canUpload ?? false)
        <div class="card-b">
            <form method="post" action="{{ route('bestanden.store') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="type" value="{{ $type }}">
                <input type="hidden" name="id" value="{{ $model->getKey() }}">
                <div class="field"><input type="file" name="bestand" required aria-label="Bestand kiezen">@error('bestand')<div class="error-text">{{ $message }}</div>@enderror</div>
                <button class="btn btn-sm" type="submit">Uploaden</button>
            </form>
        </div>
    @endif
</div>
