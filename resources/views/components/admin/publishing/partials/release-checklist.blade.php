@props(['article', 'attempt', 'release' => null])
@php
    $historicalPublishedRelease = $release !== null && (int) $article->published_release_id === (int) $release->id && $article->current_attempt_id === null;
    $currentRevisionReady = $article->working_revision_id !== null || $historicalPublishedRelease;
    $releaseTargetsCurrent = $historicalPublishedRelease || ($release !== null && (int) $release->revision_id === (int) $article->working_revision_id);
    $approval = $attempt?->approvals
        ?->where('kind', \App\Models\Publishing\ApprovalKind::Release)
        ->where('release_id', $release?->id)
        ->where('input_hash', $release?->release_hash)
        ->whereNull('invalidated_at')
        ->first();
    $exactApproved = $approval !== null || ($release?->origin === 'import' && $article->published_release_id === $release?->id);
    $blocked = $attempt?->paused_at || $attempt?->parked_at || $attempt?->abandoned_at;
    $eligible = $releaseTargetsCurrent && ! $exactApproved && ! $blocked && in_array($release?->status, ['prepared', 'approved', 'scheduled'], true);
    $deliveryReady = $exactApproved && $releaseTargetsCurrent && ! $blocked && $release?->published_at === null && in_array($release?->status, ['approved', 'scheduled'], true);
    $payload = is_array($release?->payload) ? $release->payload : [];
    $deliveryIntent = is_array($payload['delivery_intent'] ?? null) ? $payload['delivery_intent'] : [];
    $scheduleConfirmation = $release?->scheduled_at
        ? (($deliveryIntent['scheduled_wall_time'] ?? 'scheduled time').' '.($deliveryIntent['selected_timezone'] ?? 'UTC').' ('.($deliveryIntent['utc_offset'] ?? '+00:00').'; UTC '.($deliveryIntent['scheduled_utc'] ?? $release->scheduled_at?->toISOString()).')')
        : null;
@endphp
<section class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-white/10 dark:bg-white/5">
    <flux:heading size="lg">Release checklist</flux:heading>
    <dl class="mt-4 grid gap-2 text-sm">
        <div class="flex flex-wrap items-center justify-between gap-2"><dt class="text-zinc-600 dark:text-zinc-400">Working revision</dt><dd><flux:badge :color="$currentRevisionReady ? 'green' : 'amber'">{{ $currentRevisionReady ? 'Ready' : 'Missing' }}</flux:badge></dd></div>
        <div class="flex flex-wrap items-center justify-between gap-2"><dt class="text-zinc-600 dark:text-zinc-400">Release package</dt><dd class="text-right text-zinc-900 dark:text-zinc-100">{{ $release ? ($historicalPublishedRelease ? 'historical '.$release->status.' #'.$release->id : $release->status.' #'.$release->id) : 'not prepared' }}</dd></div>
        <div class="flex flex-wrap items-center justify-between gap-2"><dt class="text-zinc-600 dark:text-zinc-400">Exact content match</dt><dd class="text-right text-zinc-900 dark:text-zinc-100">{{ $historicalPublishedRelease ? 'historical published revision' : ($releaseTargetsCurrent ? 'targets current revision' : 'needs current package') }}</dd></div>
        <div class="flex flex-wrap items-center justify-between gap-2"><dt class="text-zinc-600 dark:text-zinc-400">Exact-release approval</dt><dd><flux:badge :color="$exactApproved ? 'green' : 'zinc'">{{ $exactApproved ? 'Approved' : 'Not approved' }}</flux:badge></dd></div>
        <div class="flex flex-wrap items-center justify-between gap-2"><dt class="text-zinc-600 dark:text-zinc-400">Attempt blockers</dt><dd><flux:badge :color="$blocked ? 'amber' : 'green'">{{ $blocked ? 'Blocked' : 'Clear' }}</flux:badge></dd></div>
        <div class="flex flex-wrap items-center justify-between gap-2"><dt class="text-zinc-600 dark:text-zinc-400">Delivery</dt><dd class="text-right text-zinc-900 dark:text-zinc-100">{{ $scheduleConfirmation ?? 'Manual delivery ready after approval' }}</dd></div>
    </dl>

    @can(\App\Authorization\Publishing\Permission::Publish->value)
        <flux:accordion class="mt-4">
            <flux:accordion.item heading="Release settings" expanded>
                <div class="space-y-4">
                    <flux:field>
                        <flux:label>Public slug</flux:label>
                        <flux:input wire:model="releaseSlug" placeholder="{{ $article->slug }}" />
                        <flux:error name="releaseSlug" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Schedule local time (optional)</flux:label>
                        <flux:input wire:model="releaseScheduledAt" placeholder="2026-09-23 14:00" />
                        <flux:error name="releaseScheduledAt" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Schedule timezone</flux:label>
                        <flux:input wire:model="releaseScheduledTimezone" placeholder="America/New_York" />
                        <flux:error name="releaseScheduledTimezone" />
                    </flux:field>
                    <flux:text class="text-xs">Scheduled releases are frozen as an exact wall time, IANA timezone, UTC instant and offset; DST gaps or ambiguous times are rejected.</flux:text>
                    <div class="flex flex-wrap gap-2">
                        <flux:button size="sm" wire:click="prepareRelease">Prepare/check package</flux:button>
                        @if ($deliveryReady)
                            <flux:button size="sm" variant="primary" wire:click="deliverRelease">Deliver now</flux:button>
                        @endif
                        @if ($release?->status === 'scheduled')
                            <flux:button size="sm" color="amber" wire:click="withdrawRelease">Withdraw schedule</flux:button>
                        @endif
                    </div>
                </div>
            </flux:accordion.item>
        </flux:accordion>
    @endcan

    @if ($eligible)
        @can(\App\Authorization\Publishing\Permission::Approve->value)
            <flux:button class="mt-4" variant="primary" wire:click="approveRelease('{{ $release?->release_hash }}')">
                Approve exact release package
            </flux:button>
        @endcan
    @elseif ($exactApproved)
        <flux:callout class="mt-4" variant="success" text="{{ $historicalPublishedRelease ? 'This imported article is already published from the historical release package.' : 'This exact release package is approved.' }}" />
    @else
        <flux:text class="mt-4">Prepare a current release package after plan approval before exact-release approval.</flux:text>
    @endif
</section>
