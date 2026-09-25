@props(['article', 'attempt', 'release' => null])
@php
    $isLive = $release !== null && (int) $article->published_release_id === (int) $release->id && $release->published_at !== null;
    $releaseTargetsCurrent = $release !== null && (int) $release->revision_id === (int) $article->working_revision_id;
    $approval = $attempt?->approvals?->where('kind', \App\Models\Publishing\ApprovalKind::Release)->where('release_id', $release?->id)->where('input_hash', $release?->release_hash)->whereNull('invalidated_at')->first();
    $exactApproved = $approval !== null || ($release?->origin === 'import' && $isLive);
    $blocked = $attempt?->paused_at || $attempt?->parked_at || $attempt?->abandoned_at;
    $eligible = $releaseTargetsCurrent && ! $exactApproved && ! $blocked && in_array($release?->status, ['prepared', 'approved', 'scheduled'], true);
    $deliveryReady = $exactApproved && $releaseTargetsCurrent && ! $blocked && ! $isLive && $release?->status === 'approved';
    $payload = is_array($release?->payload) ? $release->payload : [];
    $deliveryIntent = is_array($payload['delivery_intent'] ?? null) ? $payload['delivery_intent'] : [];
    $scheduleConfirmation = $release?->scheduled_at ? (($deliveryIntent['scheduled_wall_time'] ?? 'Scheduled time').' '.($deliveryIntent['selected_timezone'] ?? 'UTC').' ('.($deliveryIntent['utc_offset'] ?? '+00:00').'; UTC '.($deliveryIntent['scheduled_utc'] ?? $release->scheduled_at?->toISOString()).')') : null;
@endphp
<section class="publishing-release-checklist space-y-5">
    <flux:heading level="2" size="lg">Release checklist</flux:heading>
    @if ($isLive)
        <flux:callout variant="success" heading="Out in the world" text="This release is published. Your working draft never replaces it automatically." />
        <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ $release->origin === 'import' ? 'Original archive release' : 'Published release' }} #{{ $release->id }}</p>
        @can('develop', $article)<flux:button wire:click="startDevelopment" data-publishing-editor-action>Start a new revision</flux:button>@endcan
    @else
        <dl class="space-y-3 text-sm">
            <div class="flex items-center justify-between gap-3"><dt class="text-zinc-600 dark:text-zinc-300">Saved manuscript</dt><dd>{{ $article->working_revision_id ? 'Ready' : 'Not yet' }}</dd></div>
            <div class="flex items-center justify-between gap-3"><dt class="text-zinc-600 dark:text-zinc-300">Frozen release</dt><dd>{{ $release ? '#'.$release->id : 'Not prepared' }}</dd></div>
            <div class="flex items-center justify-between gap-3"><dt class="text-zinc-600 dark:text-zinc-300">Your approval</dt><dd>{{ $exactApproved ? 'Approved' : 'Not approved' }}</dd></div>
            @if ($blocked)<div class="flex items-center justify-between gap-3"><dt>Agent work</dt><dd class="text-amber-800 dark:text-amber-200">Paused</dd></div>@endif
        </dl>
        @if ($scheduleConfirmation)
            <div class="publishing-release-intent"><p class="text-sm font-medium">{{ $release->status === 'scheduled' ? 'Scheduled, not yet published' : 'Schedule to approve' }}</p><p class="mt-2 break-words text-sm leading-relaxed">{{ $scheduleConfirmation }}</p></div>
        @elseif ($release)
            <div class="publishing-release-intent"><p class="text-sm font-medium">Publish manually after approval</p><p class="mt-2 text-sm leading-relaxed">{{ $payload['metadata']['title'] ?? $article->idea }}</p><p class="mt-1 break-all text-xs">/writing/{{ $payload['canonical_slug'] ?? $article->slug }}/</p></div>
        @endif
        @if ($eligible)
            @can('approve', $article)<flux:button class="w-full" variant="primary" wire:click="approveRelease('{{ $release?->release_hash }}')" data-publishing-editor-action>{{ $scheduleConfirmation ? 'Approve and schedule this release' : 'Approve this exact release' }}</flux:button>@endcan
        @elseif ($deliveryReady)
            @can('publish', $article)<flux:button class="w-full" variant="primary" wire:click="deliverRelease" wire:confirm="Publish this approved release to your public Writing site?" data-publishing-editor-action>Publish now</flux:button>@endcan
        @endif
        @if ($release?->status === 'scheduled')
            @can('publish', $article)<flux:button wire:click="withdrawRelease">Withdraw schedule</flux:button>@endcan
        @endif
        @can('publish', $article)
            <details class="publishing-disclosure" @if (! $release) open @endif>
                <summary>{{ $release ? 'Prepare a different release' : 'Prepare the release' }}</summary>
                <div class="mt-5 space-y-4">
                    <flux:input wire:model="releaseSlug" label="Public slug" :disabled="$article->published_release_id !== null" />
                    <flux:input wire:model="releaseScheduledAt" label="Schedule date and time (optional)" type="datetime-local" />
                    <flux:input wire:model="releaseScheduledTimezone" label="Schedule timezone" placeholder="America/Chicago" />
                    <p class="text-xs leading-relaxed text-zinc-600 dark:text-zinc-300">Leave the time empty to publish manually. Scheduling uses this timezone; ambiguous or nonexistent daylight-saving times need correction.</p>
                    <flux:button wire:click="prepareRelease" data-publishing-editor-action>Check and prepare release</flux:button>
                    <p class="text-xs leading-relaxed text-zinc-600 dark:text-zinc-300">Checks the saved manuscript, evidence, and reviews. Changing content or timing requires fresh approval.</p>
                </div>
            </details>
        @endcan
    @endif
</section>
