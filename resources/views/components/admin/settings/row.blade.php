@props([
    'label',
    'help' => null,
    'for' => null,
    'badge' => null,
])

<div {{ $attributes->class('settings-row') }} data-settings-row>
    <div class="settings-row-text">
        @if ($for)
            <label for="{{ $for }}" class="settings-row-label">{{ $label }}@if ($badge) <flux:badge size="sm" class="settings-row-badge">{{ $badge }}</flux:badge>@endif</label>
        @else
            <p class="settings-row-label">{{ $label }}@if ($badge) <flux:badge size="sm" class="settings-row-badge">{{ $badge }}</flux:badge>@endif</p>
        @endif
        @if ($help)
            <p class="settings-row-help" @if ($for) id="{{ $for }}-help" @endif>{{ $help }}</p>
        @endif
    </div>

    <div class="settings-row-control">
        <div class="settings-row-value">{{ $slot }}</div>
        @isset($trailing)
            <div class="settings-row-trailing">{{ $trailing }}</div>
        @endisset
    </div>
</div>
