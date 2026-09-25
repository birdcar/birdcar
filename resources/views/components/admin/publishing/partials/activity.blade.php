<details class="publishing-disclosure publishing-activity-details" @if ($attempt?->paused_at) open @endif>
    <summary class="flex flex-wrap items-center justify-between gap-3"><span>Activity</span></summary>
    <div class="mt-6">
        <section>
            <div class="mb-4 flex items-center justify-between gap-3"><flux:heading level="2">Activity log</flux:heading><flux:button size="sm" variant="ghost" icon="arrow-path" wire:click="refreshAgentWork">Refresh</flux:button></div>
            <ol class="space-y-4">
                @forelse ($agentActivities as $activity)
                    <li wire:key="activity-{{ $activity->id }}" class="text-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2"><span class="font-medium">{{ str($activity->kind->value)->replace('_', ' ')->ucfirst() }}</span><span class="text-zinc-600 dark:text-zinc-300">{{ str($activity->status->value)->replace('_', ' ')->ucfirst() }}</span></div>
                        @if ($activity->pause_reason || $activity->error_reason)<p class="mt-2 text-amber-800 dark:text-amber-200">{{ $activity->pause_reason ?? $activity->error_reason }}</p>@endif
                    </li>
                @empty<flux:text>No agent work has started for this article.</flux:text>@endforelse
            </ol>
        </section>
    </div>
</details>
