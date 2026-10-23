@php use App\Support\Format; @endphp
@if ($myTasks->isNotEmpty())
<div class="card">
    <div class="card-h"><h2>Mijn taken</h2><a class="small" href="{{ route('taken.index') }}">Alle</a></div>
    <div class="card-b flush">
        @foreach ($myTasks as $t)
            <div style="padding:8px 16px;border-bottom:1px solid var(--line)">
                <a href="{{ route('taken.show', $t) }}">{{ $t->title }}</a> @if ($t->isOverdue())<span class="badge badge-danger">te laat</span>@endif
                <div class="muted small">{{ Format::date($t->deadline) }} {{ $t->project ? '· opdracht '.$t->project->number : '' }}</div>
            </div>
        @endforeach
    </div>
</div>
@endif
