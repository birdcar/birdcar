@props(['title' => null, 'summary'])

<flux:heading size="sm" class="wrap-anywhere">
    @if (filled($title))
        {{ $title }}
    @else
        <x-admin.publishing.idea-label :summary="$summary" />
    @endif
</flux:heading>
@if (filled($title))
    <x-admin.publishing.idea-label :summary="$summary" class="text-sm text-zinc-600 dark:text-zinc-300" />
@endif
