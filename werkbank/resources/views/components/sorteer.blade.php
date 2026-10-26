@props(['field', 'label', 'default' => false, 'defaultDir' => 'asc'])
@php
    $active = request('sort') === $field || (! request('sort') && $default);
    $currentDir = request('sort') ? (request('dir') === 'desc' ? 'desc' : 'asc') : $defaultDir;
    $nextDir = $active && $currentDir === 'asc' ? 'desc' : 'asc';
    $arrow = $active ? ($currentDir === 'desc' ? ' ↓' : ' ↑') : '';
@endphp
<a href="{{ request()->fullUrlWithQuery(['sort' => $field, 'dir' => $nextDir, 'page' => null]) }}">{{ $label }}{{ $arrow }}</a>
