@props(['summary'])

<span {{ $attributes->class('inline-flex min-w-0 items-center gap-2') }} data-idea-label>
    <flux:badge size="sm" color="zinc" class="shrink-0">Idea</flux:badge>
    <span class="min-w-0 wrap-anywhere">{{ $summary }}</span>
</span>
