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
@endphp
<section class="rounded-xl border border-white/10 bg-white/5 p-6">
    <h2 class="font-semibold">Release checklist</h2>
    <ul class="mt-4 space-y-2 text-sm text-zinc-300">
        <li>Working revision: <span class="text-zinc-100">{{ $currentRevisionReady ? 'ready' : 'missing' }}</span></li>
        <li>Release package: <span class="text-zinc-100">{{ $release ? ($historicalPublishedRelease ? 'historical '.$release->status.' #'.$release->id : $release->status.' #'.$release->id) : 'not prepared' }}</span></li>
        <li>Exact content match: <span class="text-zinc-100">{{ $historicalPublishedRelease ? 'historical published revision' : ($releaseTargetsCurrent ? 'targets current revision' : 'needs current package') }}</span></li>
        <li>Exact-release approval: <span class="text-zinc-100">{{ $exactApproved ? 'approved' : 'not approved' }}</span></li>
        <li>Attempt blockers: <span class="text-zinc-100">{{ $blocked ? 'blocked' : 'clear' }}</span></li>
        <li>Delivery: <span class="text-zinc-100">{{ $release?->scheduled_at ? 'schedule intent recorded' : 'manual delivery pending later phase' }}</span></li>
    </ul>

    @if ($eligible)
        @can(\App\Authorization\Publishing\Permission::Approve->value)
            <button wire:click="approveRelease('{{ $release?->release_hash }}')" class="mt-4 rounded bg-white px-4 py-2 text-sm font-medium text-zinc-950">
                Approve exact release package
            </button>
        @endcan
    @elseif ($exactApproved)
        <p class="mt-4 rounded border border-emerald-400/30 bg-emerald-400/10 p-3 text-sm text-emerald-100">{{ $historicalPublishedRelease ? 'This imported article is already published from the historical release package.' : 'This exact release package is approved.' }}</p>
    @else
        <p class="mt-4 text-sm text-zinc-400">Prepare a current release package after plan approval before exact-release approval.</p>
    @endif
</section>
