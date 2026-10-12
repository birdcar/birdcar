@php($kitStage = $stage?->value)
<div class="publishing-kit grid gap-8 lg:grid-cols-[minmax(0,1fr)_22rem]" data-kit-panel="{{ $article->kind->value }}">
    <div class="min-w-0 space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <flux:heading level="2" size="lg">{{ $article->kind->label() }}</flux:heading>
            <flux:badge size="sm" :color="$kitStage === 'approved' ? 'lime' : ($kitStage === 'published' ? 'zinc' : 'amber')" data-kit-status>{{ match ($kitStage) { 'approved' => 'Ready to post', 'published' => 'Posted', default => 'In review' } }}</flux:badge>
        </div>
        @if ($kitFlagship)
            <p class="text-sm text-zinc-600 dark:text-zinc-300">Cut from <flux:link :href="route('admin.publishing.articles.show', $kitFlagship)" wire:navigate>{{ $kitFlagshipTitle }}</flux:link>. The app never posts it; copy each part out and post it yourself.</p>
        @endif
        @if ($kitFlagshipChanged)
            <flux:callout variant="warning" heading="The flagship changed since approval" text="The post has a newer approved release than the one this piece was cut from. Check the piece still matches the post before you post it." data-kit-flagship-changed />
        @elseif (! $kitFlagshipStands && $kitStage === 'in_review')
            <flux:callout variant="warning" heading="The flagship release no longer stands" text="The post was edited or its release withdrawn after approval. Approve the post again to draft this piece from the new release." />
        @endif
        @if (in_array($kitStage, ['approved', 'published'], true) && ! $kitFlagshipLive)
            <flux:callout variant="warning" heading="The flagship post is not published" text="Keep this piece until the post is live: a social post must never link to a post before it is published." data-kit-flagship-unpublished />
        @endif

        @if ($kitWaitingDraft)
            <section class="publishing-finding space-y-3" data-kit-waiting-draft="{{ $kitWaitingDraft->id }}">
                <flux:heading level="3">A new draft is waiting</flux:heading>
                <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ data_get($kitWaitingDraft->proposal, 'reason') }}</p>
                @foreach ($kitWaitingDraftFields as $field => $value)
                    <p class="whitespace-pre-wrap text-sm"><span class="font-medium">{{ str($field)->replace('_', ' ')->ucfirst() }}:</span> {{ is_array($value) ? implode("\n\n", $value) : $value }}</p>
                @endforeach
                @can('update', $article)<flux:button size="sm" wire:click="useKitDraft({{ $kitWaitingDraft->id }})">Replace my text with this draft</flux:button>@endcan
            </section>
        @endif

        @forelse ($kitFieldBlocks as $field)
            <section wire:key="kit-field-{{ $field['role'] }}" class="publishing-finding" data-kit-field="{{ $field['role'] }}" x-data="{ copied: false }">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <flux:heading level="3">{{ $field['label'] }}</flux:heading>
                    <div class="flex items-center gap-3">
                        <span class="text-xs text-zinc-600 dark:text-zinc-300">{{ $field['length'] }} characters</span>
                        <flux:button size="xs" icon="clipboard-document" x-on:click="navigator.clipboard.writeText({{ \Illuminate\Support\Js::from($field['text']) }}).then(() => { copied = true; setTimeout(() => copied = false, 2000) })" data-copy-field="{{ $field['role'] }}"><span x-text="copied ? 'Copied' : 'Copy'">Copy</span></flux:button>
                    </div>
                </div>
                <p class="whitespace-pre-wrap text-sm leading-relaxed" data-kit-text="{{ $field['role'] }}">{{ $field['text'] }}</p>
            </section>
        @empty
            <div class="publishing-empty">
                <flux:heading level="3">{{ $kitDrafting ? 'Drafting from the approved post.' : 'No draft yet.' }}</flux:heading>
                <flux:text class="mt-3">{{ $kitDrafting ? ($agentsPaused ? 'The draft is queued and runs when publishing agents resume.' : 'The draft appears here when it is ready.') : 'Draft this piece from the flagship post\'s approved release.' }}</flux:text>
                @if (! $kitDrafting && $kitStage === 'in_review')
                    @can('develop', $article)<flux:button class="mt-6" size="sm" wire:click="startKitDraft" data-start-kit-draft>Draft this piece</flux:button>@endcan
                @endif
            </div>
        @endforelse

        @if ($kitFieldBlocks !== [] && $kitStage !== 'published')
            @can('update', $article)
                <details class="publishing-disclosure" data-kit-edit>
                    <summary>Edit the text</summary>
                    <form wire:submit="saveKitFields" class="mt-4 space-y-4">
                        @if (array_key_exists('posts', $kitFields))
                            @foreach ($kitFields['posts'] as $index => $post)
                                <div wire:key="kit-post-{{ $index }}" class="space-y-2">
                                    <flux:textarea wire:model="kitFields.posts.{{ $index }}" label="Post {{ $index + 1 }}" rows="3" />
                                    <flux:error name="kitFields.posts.{{ $index }}" />
                                    @if (count($kitFields['posts']) > 1)<flux:button type="button" size="xs" variant="ghost" wire:click="removeKitPost({{ $index }})">Remove post {{ $index + 1 }}</flux:button>@endif
                                </div>
                            @endforeach
                            <flux:button type="button" size="sm" variant="ghost" icon="plus" wire:click="addKitPost">Add a post</flux:button>
                        @else
                            @foreach ($kitFields as $field => $value)
                                <div wire:key="kit-input-{{ $field }}">
                                    <flux:textarea wire:model="kitFields.{{ $field }}" :label="\App\Services\Publishing\KitDocument::label($article->kind, $field)" :rows="in_array($field, ['subject', 'preview'], true) ? 1 : 5" />
                                    <flux:error name="kitFields.{{ $field }}" />
                                </div>
                            @endforeach
                        @endif
                        <flux:button type="submit" size="sm" variant="primary">Save the text</flux:button>
                        <p class="text-xs text-zinc-600 dark:text-zinc-300">Saving re-checks the voice rules. Saving after approval needs a new approval.</p>
                    </form>
                </details>
            @endcan
        @endif
    </div>

    <aside class="space-y-8">
        @include('components.admin.publishing.partials.voice-rules')

        <section class="space-y-4" data-kit-review aria-labelledby="kit-review-heading">
            <flux:heading level="3" id="kit-review-heading">Kit review</flux:heading>
            @forelse ($kitReviewFindings as $finding)
                <article wire:key="kit-finding-{{ $finding->id }}" class="publishing-finding" data-kit-finding="{{ $finding->id }}">
                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <flux:badge size="sm" :color="$finding->severity === 'blocking' ? 'amber' : 'zinc'">{{ $finding->severity === 'blocking' ? 'Decide before approval' : 'Suggestion' }}</flux:badge>
                        <span class="text-xs text-zinc-600 dark:text-zinc-300">{{ match ($finding->kind) { 'claim_outside_manifest' => 'Claim outside the post', 'register' => 'Voice', 'channel' => 'Channel', default => 'Review' } }}</span>
                    </div>
                    <p class="text-sm font-medium leading-relaxed">{{ $finding->statement }}</p>
                    @if ($finding->rationale)<p class="mt-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-300">{{ $finding->rationale }}</p>@endif
                    @if ($finding->disposition)
                        <p class="mt-4 text-sm font-medium text-accent-content">{{ match ($finding->disposition) { 'accepted' => 'Fixed', 'rejected' => 'Kept as written', 'false_positive' => 'Does not apply', default => str($finding->disposition)->replace('_', ' ')->ucfirst() } }}</p>
                        @if ($finding->disposition_reason)<p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">Your reason: {{ $finding->disposition_reason }}</p>@endif
                    @else
                        @can('approve', $article)
                            <div class="mt-4 space-y-3">
                                <flux:button size="sm" wire:click="decideKitFinding({{ $finding->id }}, 'accepted')">I fixed it</flux:button>
                                <details class="publishing-disclosure"><summary>Keep it as written</summary><div class="mt-3 space-y-3"><flux:textarea wire:model="findingReasons.{{ $finding->id }}" label="Why does the piece keep this?" rows="2" /><flux:error name="findingReasons.{{ $finding->id }}" /><div class="flex flex-wrap gap-2"><flux:button size="sm" wire:click="decideKitFinding({{ $finding->id }}, 'rejected')">Keep as written</flux:button><flux:button size="sm" variant="ghost" wire:click="decideKitFinding({{ $finding->id }}, 'false_positive')">This does not apply</flux:button></div></div></details>
                            </div>
                        @endcan
                    @endif
                </article>
            @empty
                <flux:text>{{ $agentActivities->contains(fn ($activity) => $activity->kind === \App\Models\Publishing\EditorialActivityKind::ReviewKit && $activity->status === \App\Models\Publishing\EditorialActivityStatus::Completed) ? 'The kit review found nothing to decide.' : 'The kit review runs after the draft and its voice-rule repair.' }}</flux:text>
            @endforelse
        </section>

        @if ($kitStage === 'in_review')
            <section class="space-y-3" data-kit-approve>
                <flux:heading level="3">Approve as written</flux:heading>
                @if ($kitApprovalProblems !== [])
                    <ul class="space-y-1 text-sm text-amber-800 dark:text-amber-200" data-kit-approval-problems>@foreach ($kitApprovalProblems as $problem)<li>{{ $problem }}</li>@endforeach</ul>
                @endif
                @can('approve', $article)<flux:button size="sm" variant="primary" wire:click="approveKit" :disabled="$kitApprovalProblems !== []" data-approve-kit>Approve this piece</flux:button>@endcan
            </section>
        @endif

        @if ($kitStage === 'approved')
            <section class="space-y-3" data-kit-mark-posted>
                <flux:heading level="3">After you post it</flux:heading>
                @if ($kitPostedProblem)<p class="text-sm text-amber-800 dark:text-amber-200" data-kit-posted-problem>{{ $kitPostedProblem }}</p>@endif
                @can('publish', $article)
                    <form wire:submit="markPosted" class="space-y-3">
                        <flux:input wire:model="postedUrl" type="url" label="Link to the post (optional)" placeholder="https://" maxlength="{{ \App\Actions\Publishing\MarkKitPiecePosted::URL_LIMIT }}" />
                        <flux:error name="postedUrl" />
                        <flux:button type="submit" size="sm" variant="primary" :disabled="$kitPostedProblem !== null">Mark posted</flux:button>
                    </form>
                @endcan
            </section>
        @endif

        @if ($kitStage === 'published')
            <section class="space-y-2" data-kit-posted>
                <flux:heading level="3">Posted</flux:heading>
                <p class="text-sm text-zinc-600 dark:text-zinc-300">Marked posted {{ $article->posted_at?->toFormattedDayDateString() }}.</p>
                @if ($article->posted_url)<flux:link :href="$article->posted_url" target="_blank" rel="noopener" class="break-all text-sm">{{ $article->posted_url }}</flux:link>@endif
            </section>
        @endif
    </aside>
</div>
