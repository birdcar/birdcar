@if ($distributionKit->isNotEmpty() || $canCreateKit)
    <section class="space-y-4" data-distribution-kit aria-labelledby="distribution-kit-heading">
        <flux:heading level="3" id="distribution-kit-heading">Distribution kit</flux:heading>
        <p class="text-sm leading-relaxed text-zinc-600 dark:text-zinc-300">Short pieces cut from this post's approved release. You copy each one out and post it yourself after the post is live.</p>
        @if ($distributionKit->isNotEmpty())
            <ul class="space-y-2 text-sm">
                @foreach ($distributionKit as $piece)
                    <li wire:key="kit-piece-{{ $piece->id }}" class="flex flex-wrap items-center justify-between gap-2" data-kit-piece="{{ $piece->kind->value }}">
                        <flux:link :href="route('admin.publishing.articles.show', $piece)" wire:navigate>{{ $piece->kind->label() }}</flux:link>
                        <span class="text-zinc-600 dark:text-zinc-300">{{ match ($piece->currentAttempt?->stage?->value) { 'approved' => 'Ready to post', 'published' => 'Posted', default => 'In review' } }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
        @if ($canCreateKit)
            @can('approve', $article)<flux:button size="sm" wire:click="createDistributionKit" data-create-distribution-kit>Create distribution kit</flux:button>@endcan
        @endif
    </section>
@endif
