@props([
    'name',
    'heading',
    'description' => null,
    'fields' => [],
    'save' => null,
    'discard' => null,
    'errorCount' => 0,
    'saveError' => null,
])

@php
    $headingId = 'settings-group-'.$name.'-heading';
    $saves = $save !== null && $fields !== [];
@endphp

<section {{ $attributes->class('settings-group') }} aria-labelledby="{{ $headingId }}" data-settings-group="{{ $name }}">
    <div class="settings-group-header">
        <h2 id="{{ $headingId }}">{{ $heading }}</h2>
        @if ($description)
            <p>{{ $description }}</p>
        @endif
    </div>

    @if ($saves)
        <form wire:submit="{{ $save }}" x-data="settingsGroup(@js(array_values($fields)), @js($name))" class="settings-rows" novalidate>
            {{ $slot }}

            @teleport('#settings-save-bars')
                <div
                    class="settings-save-bar"
                    x-show="dirty || saved"
                    x-cloak
                    x-transition:enter="settings-save-bar-enter"
                    x-transition:enter-start="settings-save-bar-from"
                    x-transition:leave="settings-save-bar-leave"
                    x-transition:leave-end="settings-save-bar-from"
                    :data-dirty="dirty ? 'true' : 'false'"
                    data-settings-save-bar="{{ $name }}"
                    role="region"
                    aria-label="{{ $heading }} changes"
                >
                    <p class="settings-save-status" role="status">
                        <span class="settings-save-group">{{ $heading }}<span aria-hidden="true"> · </span></span>
                        @if ($saveError)
                            <span class="settings-save-status-error" x-show="dirty">{{ $saveError }}</span>
                        @elseif ($errorCount > 0)
                            <span class="settings-save-status-error" x-show="dirty">Fix {{ $errorCount }} {{ Str::plural('field', $errorCount) }} to save</span>
                        @endif
                        @unless ($saveError || $errorCount > 0)
                            <span x-show="dirty" x-text="count === 1 ? '1 unsaved change' : `${count} unsaved changes`"></span>
                        @endunless
                        <span x-show="saved && ! dirty" class="settings-save-status-saved">
                            <flux:icon.check-circle variant="mini" />
                            Saved
                        </span>
                    </p>
                    <div class="settings-save-actions" x-show="dirty">
                        <flux:button type="button" wire:click="{{ $discard }}" wire:loading.attr="disabled" wire:target="{{ $save }},{{ $discard }}">Discard</flux:button>
                        <flux:button type="button" variant="primary" wire:click="{{ $save }}" wire:loading.attr="disabled" wire:target="{{ $save }},{{ $discard }}">
                            <span wire:loading.remove wire:target="{{ $save }}">Save changes</span>
                            <span wire:loading wire:target="{{ $save }}">Saving…</span>
                        </flux:button>
                    </div>
                </div>
            @endteleport
        </form>
    @else
        <div class="settings-rows">
            {{ $slot }}
        </div>
    @endif
</section>
