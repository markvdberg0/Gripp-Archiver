@props(['name', 'label' => null, 'value' => null, 'required' => false, 'hint' => null, 'wrap' => ''])
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id = 'f_'.str_replace('.', '_', $key);
@endphp
<div class="field {{ $errors->has($key) ? 'has-error' : '' }} {{ $wrap }}">
    @if ($label)<label for="{{ $id }}">{{ $label }}@if ($required) <span aria-hidden="true">*</span>@endif</label>@endif
    <textarea name="{{ $name }}" id="{{ $id }}" @if ($required) required @endif {{ $attributes }}>{{ old($key, $value) }}</textarea>
    @if ($hint)<div class="hint">{{ $hint }}</div>@endif
    @error($key)<div class="error-text">{{ $message }}</div>@enderror
</div>
