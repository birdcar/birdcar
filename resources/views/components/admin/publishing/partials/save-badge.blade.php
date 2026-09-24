@props(['state' => 'saved'])
@php
    $color = match ($state) {
        'saved' => 'green',
        'saving' => 'sky',
        'conflict' => 'amber',
        'error' => 'red',
        default => 'zinc',
    };
@endphp
<flux:badge :color="$color" rounded data-save-state="{{ $state }}">{{ ucfirst($state) }}</flux:badge>
