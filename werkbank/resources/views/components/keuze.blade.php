@props(['name', 'label' => null, 'options' => [], 'value' => null, 'required' => false, 'placeholder' => null, 'hint' => null, 'wrap' => ''])
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id = 'f_'.str_replace('.', '_', $key);
    $val = (string) old($key, $value);
@endphp
<div class="field {{ $errors->has($key) ? 'has-error' : '' }} {{ $wrap }}">
    @if ($label)<label for="{{ $id }}">{{ $label }}@if ($required) <span aria-hidden="true">*</span>@endif</label>@endif
    <select name="{{ $name }}" id="{{ $id }}" @if ($required) required @endif {{ $attributes }}>
        @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $k => $v)
            <option value="{{ $k }}" @selected($val !== '' && (string) $k === $val)>{{ $v }}</option>
        @endforeach
    </select>
    @if ($hint)<div class="hint">{{ $hint }}</div>@endif
    @error($key)<div class="error-text">{{ $message }}</div>@enderror
</div>
