@props(['name', 'label', 'checked' => false, 'hint' => null])
@php $key = str_replace(['[', ']'], ['.', ''], $name); @endphp
<div class="field">
    <input type="hidden" name="{{ $name }}" value="0">
    <label class="check"><input type="checkbox" name="{{ $name }}" value="1" @checked(old($key, $checked)) {{ $attributes }}> {{ $label }}</label>
    @if ($hint)<div class="hint">{{ $hint }}</div>@endif
    @error($key)<div class="error-text">{{ $message }}</div>@enderror
</div>
