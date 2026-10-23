@props(['field', 'label', 'default' => false])
@php
    $current = request('sort', $default ? $field : null);
    $dir = $current === $field && request('dir') !== 'desc' ? 'desc' : 'asc';
    $arrow = $current === $field ? (request('dir') === 'desc' ? ' ↓' : ' ↑') : '';
@endphp
<a href="{{ request()->fullUrlWithQuery(['sort' => $field, 'dir' => $dir, 'page' => null]) }}">{{ $label }}{{ $arrow }}</a>
