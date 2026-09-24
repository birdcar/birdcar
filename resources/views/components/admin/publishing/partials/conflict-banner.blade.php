@props(['message', 'latest' => null])
<flux:callout variant="warning" role="alert" data-conflict-banner>
    <flux:callout.heading>Save conflict</flux:callout.heading>
    <flux:callout.text>{{ $message }} Your local text was not overwritten.</flux:callout.text>

    @if (is_array($latest))
        <div class="rounded-lg border border-amber-200 bg-white/60 p-3 text-sm dark:border-amber-200/20 dark:bg-black/10" data-latest-revision="{{ $latest['id'] }}">
            <p class="text-xs font-medium uppercase tracking-wide text-amber-700 dark:text-amber-200/80">Latest saved revision #{{ $latest['number'] }}</p>
            <p class="mt-1 text-xs text-amber-900 dark:text-amber-50/90">{{ $latest['excerpt'] }}</p>
        </div>
    @endif

    <x-slot:actions>
        <flux:button type="button" size="xs" data-conflict-action="recover-local">Recover local draft</flux:button>
        <flux:button type="button" size="xs" data-conflict-action="copy-local">Copy local text</flux:button>
        <flux:button size="xs" href="{{ is_array($latest) ? $latest['preview_url'] : '#preview' }}" target="{{ is_array($latest) ? '_blank' : '_self' }}" data-conflict-action="compare-latest">Compare preview</flux:button>
        <flux:button type="button" size="xs" variant="danger" data-conflict-action="restart-from-server">Restart from server</flux:button>
    </x-slot:actions>
</flux:callout>
