<?php

use App\Actions\Admin\ReadAdminHome;
use App\Authorization\Admin\Permission as AdminPermission;
use App\Authorization\Publishing\Permission as PublishingPermission;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.admin'), Title('Home')] class extends Component
{
    public function with(): array
    {
        Gate::authorize(AdminPermission::View->value);
        $canViewPublishing = auth()->user()->can(PublishingPermission::View->value);
        $items = ['blocked' => [], 'decisions' => [], 'continuing' => []];
        $unavailable = false;

        if ($canViewPublishing) {
            try {
                $items = app(ReadAdminHome::class)->forUser(auth()->user());
            } catch (QueryException $exception) {
                report($exception);
                $unavailable = true;
            }
        }

        return [...$items, 'canViewPublishing' => $canViewPublishing, 'unavailable' => $unavailable];
    }
};
?>

<section class="space-y-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading level="1" size="xl">Needs attention</flux:heading>
            <flux:text class="mt-2">Decisions and blocked work, before the rest.</flux:text>
        </div>
        @if ($canViewPublishing)
            <div class="flex items-center gap-3">
                <span wire:loading role="status" class="text-sm text-zinc-500 dark:text-zinc-400">Refreshing…</span>
                <flux:button wire:click="$refresh" icon="arrow-path" size="sm">Refresh</flux:button>
            </div>
        @endif
    </div>

    @if (! $canViewPublishing)
        <flux:callout icon="lock-closed">
            <flux:callout.heading>Your workspace is ready</flux:callout.heading>
            <flux:callout.text>You have Admin access. Work appears here when you have access to a business module.</flux:callout.text>
        </flux:callout>
    @elseif ($unavailable)
        <flux:callout variant="warning" icon="exclamation-triangle" role="alert">
            <flux:callout.heading>Publishing work couldn’t be loaded</flux:callout.heading>
            <flux:callout.text>Your attention list is unavailable, not empty. Refresh to try again, or open Publishing.</flux:callout.text>
            <x-slot:actions>
                <flux:button :href="route('admin.publishing.dashboard')">Open Publishing</flux:button>
            </x-slot:actions>
        </flux:callout>
    @else
        <div class="grid min-w-0 gap-10 xl:grid-cols-[minmax(0,1fr)_19rem]">
            <div class="min-w-0 space-y-8">
                @if ($blocked === [] && $decisions === [])
                    <section class="border-y border-zinc-200 py-10 dark:border-zinc-700" aria-labelledby="attention-empty">
                        <flux:icon.check-circle class="mb-4 size-6 text-accent-content" />
                        <flux:heading id="attention-empty" level="2" size="lg">Nothing needs your attention</flux:heading>
                        <flux:text class="mt-2 max-w-prose">Your current publishing work has no flagged blockers or decisions. Pick up an active piece, or open Publishing to start something new.</flux:text>
                        <flux:button :href="route('admin.publishing.dashboard')" variant="primary" class="mt-5">Open Publishing</flux:button>
                    </section>
                @endif

                @foreach (['blocked' => ['heading' => 'Blocked', 'items' => $blocked, 'color' => 'amber'], 'decisions' => ['heading' => 'Your decision', 'items' => $decisions, 'color' => 'cyan']] as $section => $group)
                    @if ($group['items'] !== [])
                        <section wire:key="home-{{ $section }}" aria-labelledby="home-{{ $section }}">
                            <flux:heading id="home-{{ $section }}" level="2" size="lg" class="mb-3">{{ $group['heading'] }}</flux:heading>
                            <ul class="divide-y divide-zinc-200 border-y border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                                @foreach ($group['items'] as $item)
                                    <li wire:key="home-{{ $section }}-{{ $item['article']->id }}" class="py-5">
                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                                            <flux:badge size="sm" :color="$group['color']">Publishing</flux:badge>
                                            <time datetime="{{ $item['updated_at']->toIso8601String() }}" title="{{ $item['updated_at']->toIso8601String() }}" class="text-xs text-zinc-500 dark:text-zinc-400">Updated {{ $item['updated_at']->diffForHumans() }}</time>
                                        </div>
                                        <div class="mt-3 flex flex-col items-start gap-4 sm:flex-row sm:justify-between sm:gap-6">
                                            <div class="min-w-0 space-y-1">
                                                <flux:heading level="3" class="wrap-anywhere">{{ data_get($item['article']->workingRevision?->metadata, 'title') ?: $item['article']->idea }}</flux:heading>
                                                <flux:text>{{ $item['reason'] }}</flux:text>
                                            </div>
                                            <flux:button :href="route('admin.publishing.articles.show', $item['article'])" size="sm" icon:trailing="arrow-up-right" class="shrink-0" :aria-label="$item['action'].': '.$item['article']->idea">{{ $item['action'] }}</flux:button>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                @endforeach

                @if ($blocked !== [] || $decisions !== [])
                    <flux:text size="sm">Up to five items per section. <flux:link :href="route('admin.publishing.dashboard')">Open Publishing for all active writing.</flux:link></flux:text>
                @endif
            </div>

            <aside class="min-w-0" aria-labelledby="continue-heading">
                <flux:heading id="continue-heading" level="2" size="lg">Continue working</flux:heading>
                <flux:text class="mt-2" size="sm">Active work outside your attention list.</flux:text>
                <ul class="mt-3 divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($continuing as $item)
                        <li wire:key="home-continue-{{ $item['article']->id }}" class="py-4">
                            <flux:link :href="route('admin.publishing.articles.show', $item['article'])" class="block font-medium wrap-anywhere">{{ data_get($item['article']->workingRevision?->metadata, 'title') ?: $item['article']->idea }}</flux:link>
                            <flux:text class="mt-1" size="sm">{{ $item['reason'] }}</flux:text>
                            <flux:text class="mt-2" size="sm">Publishing · <time datetime="{{ $item['updated_at']->toIso8601String() }}">{{ $item['updated_at']->diffForHumans() }}</time></flux:text>
                        </li>
                    @empty
                        <li class="py-4"><flux:text size="sm">No other active work. Your saved ideas are in Publishing.</flux:text></li>
                    @endforelse
                </ul>
                <flux:button :href="route('admin.publishing.dashboard')" variant="ghost" icon:trailing="arrow-right" class="mt-3">Open Publishing</flux:button>
            </aside>
        </div>
    @endif
</section>
