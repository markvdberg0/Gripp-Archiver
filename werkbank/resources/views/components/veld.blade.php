@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'required' => false, 'hint' => null, 'wrap' => ''])
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id = 'f_'.str_replace('.', '_', $key);
    $val = $type === 'password' ? '' : old($key, $value instanceof \DateTimeInterface ? $value->format($type === 'date' ? 'Y-m-d' : ($type === 'time' ? 'H:i' : 'Y-m-d H:i')) : $value);
@endphp
<div class="field {{ $errors->has($key) ? 'has-error' : '' }} {{ $wrap }}">
    @if ($label)<label for="{{ $id }}">{{ $label }}@if ($required) <span aria-hidden="true">*</span>@endif</label>@endif
    <input type="{{ $type }}" name="{{ $name }}" id="{{ $id }}" value="{{ $val }}" @if ($required) required @endif
        @if ($errors->has($key)) aria-invalid="true" aria-describedby="{{ $id }}_err" @endif {{ $attributes }}>
    @if ($hint)<div class="hint">{{ $hint }}</div>@endif
    @error($key)<div class="error-text" id="{{ $id }}_err">{{ $message }}</div>@enderror
</div>
