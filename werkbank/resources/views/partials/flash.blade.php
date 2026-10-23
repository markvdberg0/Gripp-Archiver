@if (session('status'))
    <div class="alert alert-ok" role="status">{{ session('status') }}</div>
@endif
@if (session('warning'))
    <div class="alert alert-warn" role="status">{{ session('warning') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-error" role="alert">
        @if ($errors->count() === 1)
            {{ $errors->first() }}
        @else
            Controleer het formulier: er {{ $errors->count() === 1 ? 'is 1 fout' : 'zijn '.$errors->count().' fouten' }}.
        @endif
    </div>
@endif
