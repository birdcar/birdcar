<details class="publishing-disclosure publishing-activity-details" @if ($attempt?->paused_at) open @endif>
    <summary class="flex flex-wrap items-center justify-between gap-3"><span>Activity & allowance</span>@if ($agentBudget)<span class="text-sm font-normal tabular-nums text-zinc-600 dark:text-zinc-300">${{ number_format($agentBudget['available'] / 1_000_000_000, 2) }} available</span>@endif</summary>
    <div class="mt-6 grid gap-8 lg:grid-cols-2">
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
        <section class="space-y-4">
            <flux:heading level="2">Agent budget</flux:heading>
            @if ($agentBudget)
                <dl class="grid grid-cols-3 gap-4 text-sm"><div><dt class="text-zinc-600 dark:text-zinc-300">Allowance</dt><dd class="mt-1 font-medium tabular-nums">${{ number_format($agentBudget['allowance'] / 1_000_000_000, 2) }}</dd></div><div><dt class="text-zinc-600 dark:text-zinc-300">Spent</dt><dd class="mt-1 font-medium tabular-nums">${{ number_format($agentBudget['spent'] / 1_000_000_000, 3) }}</dd></div><div><dt class="text-zinc-600 dark:text-zinc-300">Reserved</dt><dd class="mt-1 font-medium tabular-nums">${{ number_format($agentBudget['held'] / 1_000_000_000, 3) }}</dd></div></dl>
                @can(\App\Authorization\Publishing\Permission::Budget->value)
                    <form wire:submit="topUpBudget" class="flex flex-wrap items-end gap-3">
                        <flux:select wire:model="budgetTopUpNanoUsd" label="Add to allowance" class="max-w-40"><flux:select.option value="1000000000">$1.00</flux:select.option><flux:select.option value="5000000000">$5.00</flux:select.option><flux:select.option value="10000000000">$10.00</flux:select.option></flux:select>
                        <flux:button type="submit">Add allowance</flux:button>
                    </form>
                @endcan
                <p class="text-xs leading-relaxed text-zinc-600 dark:text-zinc-300">Reserved spending may include an unresolved provider outcome. Adding allowance does not automatically retry uncertain work.</p>
            @else<flux:text>Developing an idea starts a $5 allowance. Saving it for later does not start agent work.</flux:text>@endif
        </section>
    </div>
</details>
