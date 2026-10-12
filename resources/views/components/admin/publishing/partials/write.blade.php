<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <flux:heading level="2" size="lg">Manuscript</flux:heading>
    <div class="flex flex-wrap gap-2" aria-label="Writing context">
        @foreach (['reviews' => 'Feedback', 'sources' => 'Sources', 'brief' => 'Brief'] as $contextKey => $contextLabel)
            <flux:button size="sm" variant="ghost" @click="selectContext('{{ $contextKey }}'); setContextOpen(true)" x-bind:aria-pressed="contextOpen && context === '{{ $contextKey }}'">{{ $contextLabel }}</flux:button>
        @endforeach
    </div>
</div>
<div class="publishing-writing" :class="{ 'has-context': contextOpen }">
    <section class="admin-editor publishing-manuscript" data-admin-editor-shell>
        @can('update', $article)
            <div class="publishing-block-tools" aria-label="Semantic manuscript blocks">
                <flux:dropdown>
                    <flux:button size="sm" variant="ghost" icon="plus" icon:trailing="chevron-down">Insert</flux:button>
                    <flux:menu>
                        <flux:menu.item data-editor-command="note">Insert note</flux:menu.item>
                        <flux:menu.item data-editor-command="callout">Insert callout</flux:menu.item>
                        <flux:menu.item data-editor-command="chart">Insert chart</flux:menu.item>
                        <flux:menu.item data-editor-command="diagram">Insert diagram</flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
                <flux:dropdown>
                    <flux:button size="sm" variant="ghost" icon="lock-closed" icon:trailing="chevron-down">Passage</flux:button>
                    <flux:menu>
                        <flux:menu.item data-editor-command="protect">Protect selection</flux:menu.item>
                        <flux:menu.item data-editor-command="unprotect">Unprotect selection</flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
                <span class="ml-auto text-xs text-zinc-600 dark:text-zinc-300">Markdown shortcuts work here</span>
            </div>
        @endcan
        <div wire:ignore>
            <flux:editor
                variant="borderless"
                class="publishing-editor"
                data-admin-editor
                data-article-id="{{ $article->id }}"
                data-user-id="{{ auth()->id() }}"
                data-current-revision="{{ $currentRevisionId }}"
                :data-document="json_encode($document)"
                :data-metadata="json_encode($metadata)"
                :disabled="! auth()->user()->can('update', $article)"
                toolbar="heading | bold italic strike | bullet ordered blockquote | link"
            />
        </div>
        <div class="publishing-manuscript-footer"><span>Your words are saved as you work.</span><span>{{ count($protectedBlocks) }} protected {{ str('passage')->plural(count($protectedBlocks)) }}</span></div>
    </section>

    <aside class="publishing-writing-context" data-session-context x-show="contextOpen" x-cloak>
        <div class="mb-6 flex items-center justify-between gap-3"><flux:heading level="2" x-text="context === 'reviews' ? 'Reviews and proposals' : (context === 'sources' ? 'Evidence sources' : 'The living brief')">Reviews and proposals</flux:heading><flux:button size="sm" variant="ghost" icon="x-mark" aria-label="Return to manuscript" @click="setContextOpen(false)" /></div>
        <div x-show="context === 'reviews'">
            <p class="mb-5 text-sm leading-relaxed text-zinc-600 dark:text-zinc-300">A second set of eyes, not a second author. Every change is yours to accept.</p>
            <div class="mb-6 flex flex-wrap gap-2">
                @can('develop', $article)<flux:button size="sm" wire:click="startReviews" data-publishing-editor-action :disabled="$working || ! $planApproved">Start three-lens review</flux:button>@endcan
                @can('approve', $article)<flux:button size="sm" variant="primary" wire:click="finishReview" data-publishing-editor-action :disabled="$working || ! $planApproved">Finish review</flux:button>@endcan
                @if ($voiceRuleRepairWaiting)
                    @can('develop', $article)<flux:button size="sm" variant="ghost" wire:click="skipVoiceRuleRepair" data-publishing-editor-action data-skip-voice-rule-repair>Skip repair and review</flux:button>@endcan
                @endif
            </div>
            @if (! $planApproved)<p class="mb-5 text-sm text-zinc-600 dark:text-zinc-300">Review becomes available after an approved plan and a saved draft.</p>@endif
            @if ($voiceRuleFindings->isNotEmpty())
                <section class="mb-8" data-voice-rules aria-labelledby="voice-rules-heading">
                    <flux:heading level="3" id="voice-rules-heading">Voice rules</flux:heading>
                    <p class="mt-2 mb-5 text-sm leading-relaxed text-zinc-600 dark:text-zinc-300">Mechanical rules from your voice canon. Each one holds the release until the passage is fixed or you override the rule with a reason.</p>
                    <div class="space-y-6">
                        @foreach ($voiceRuleFindings as $finding)
                            <article wire:key="voice-rule-{{ $finding->id }}" class="publishing-finding" data-voice-rule-finding="{{ $finding->id }}">
                                <div class="mb-3 flex flex-wrap items-center gap-2">
                                    <flux:badge size="sm" :color="$finding->disposition === 'false_positive' ? 'zinc' : 'amber'">{{ $finding->disposition === 'false_positive' ? 'Overridden' : 'Resolve before release' }}</flux:badge>
                                    <span class="text-xs text-zinc-600 dark:text-zinc-300">{{ str($finding->kind)->after('lint:')->replace('-', ' ')->ucfirst() }}</span>
                                </div>
                                <p class="text-sm font-medium leading-relaxed">{{ $finding->statement }}</p>
                                @if ($finding->block_id)<p class="mt-2 text-xs text-zinc-600 dark:text-zinc-300">Passage <code>{{ $finding->block_id }}</code></p>@endif
                                @if ($finding->proposed_patch)
                                    <details class="publishing-disclosure mt-4"><summary>Suggested fix</summary><p class="mt-3 whitespace-pre-wrap text-sm leading-relaxed">{{ $proposalTexts[$finding->id] ?? '' }}</p></details>
                                @endif
                                @if ($finding->disposition === 'false_positive')
                                    <p class="mt-4 text-sm text-zinc-600 dark:text-zinc-300">Your reason: {{ $finding->disposition_reason }}</p>
                                @elseif ($finding->disposition === null)
                                    <div class="mt-4 space-y-3">
                                        @if ($finding->proposed_patch)
                                            @can('update', $article)<flux:button size="sm" wire:click="applyProposal({{ $finding->id }})" data-publishing-editor-action>Accept fix</flux:button>@endcan
                                        @endif
                                        @can('approve', $article)
                                            <details class="publishing-disclosure"><summary>Override with reason</summary><div class="mt-3 space-y-3"><flux:textarea wire:model="findingReasons.{{ $finding->id }}" label="Why does this rule not apply here?" rows="2" /><flux:error name="findingReasons.{{ $finding->id }}" /><flux:button size="sm" wire:click="overrideVoiceRule({{ $finding->id }})">Record override</flux:button></div></details>
                                        @endcan
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif
            <div class="space-y-6">
                @forelse ($editorialFindings as $finding)
                    @php($pendingDisposition = $findingDispositions[$finding->id]['disposition'] ?? null)
                    <article wire:key="finding-{{ $finding->id }}" class="publishing-finding" data-finding-id="{{ $finding->id }}">
                        <div class="mb-3 flex flex-wrap items-center gap-2"><flux:badge size="sm" :color="$finding->severity === 'blocking' ? 'amber' : 'zinc'">{{ $finding->severity === 'blocking' ? 'Resolve before release' : 'Suggestion' }}</flux:badge><span class="text-xs text-zinc-600 dark:text-zinc-300">{{ str($finding->lens)->replace('_', ' ')->ucfirst() }}</span></div>
                        <p class="text-sm font-medium leading-relaxed">{{ $finding->statement }}</p>
                        @if ($finding->rationale)<p class="mt-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-300">{{ $finding->rationale }}</p>@endif
                        @if ($finding->block_id)<p class="mt-2 text-xs text-zinc-600 dark:text-zinc-300">Passage <code>{{ $finding->block_id }}</code></p>@endif
                        @if ($finding->proposed_patch)
                            <details class="publishing-disclosure mt-4"><summary>Suggested replacement</summary><p class="mt-3 whitespace-pre-wrap text-sm leading-relaxed">{{ $proposalTexts[$finding->id] ?? '' }}</p></details>
                        @endif
                        @if ($finding->supporting_quotations)
                            <details class="publishing-disclosure mt-4"><summary>Supporting quotations</summary><div class="mt-3 space-y-3">@foreach ($finding->supporting_quotations as $quotation)@if (is_array($quotation))<blockquote class="text-sm leading-relaxed">“{{ $quotation['quote'] ?? '' }}”<span class="mt-1 block text-xs text-zinc-600 dark:text-zinc-300">Source #{{ $quotation['source_id'] ?? 'unknown' }}</span></blockquote>@endif @endforeach</div></details>
                        @endif
                        @if ($finding->disposition)
                            <p class="mt-4 text-sm font-medium text-accent-content">{{ str($finding->disposition)->replace('_', ' ')->ucfirst() }}</p>
                        @else
                            <div class="mt-4 space-y-3">
                                @if ($pendingDisposition)<p class="text-sm text-accent-content">{{ str($pendingDisposition)->replace('_', ' ')->ucfirst() }} · saved when you finish review</p>@endif
                                <div class="flex flex-wrap gap-2">
                                    @if ($finding->proposed_patch)
                                        @can('update', $article)<flux:button size="sm" wire:click="applyProposal({{ $finding->id }})" data-publishing-editor-action>Accept change</flux:button>@endcan
                                    @endif
                                    @can('approve', $article)<flux:button size="sm" variant="ghost" wire:click="decideFinding({{ $finding->id }}, 'rejected')">Keep my version</flux:button>@endcan
                                </div>
                                @can('approve', $article)
                                    <details class="publishing-disclosure"><summary>This finding does not apply</summary><div class="mt-3 space-y-3"><flux:textarea wire:model="findingReasons.{{ $finding->id }}" label="Why is this a false positive?" rows="2" /><flux:error name="findingReasons.{{ $finding->id }}" /><flux:button size="sm" wire:click="decideFinding({{ $finding->id }}, 'false_positive')">Record false positive</flux:button></div></details>
                                @endcan
                            </div>
                        @endif
                    </article>
                @empty
                    @if ($voiceRuleFindings->isEmpty())<div class="publishing-feedback-empty"><flux:icon.chat-bubble-left-right variant="outline" class="mb-4 size-7 text-accent-content" /><flux:heading level="3">Room for another perspective.</flux:heading><flux:text class="mt-2">No findings have been recorded for this revision. Reviews will check facts, voice, and the reader’s experience.</flux:text></div>@endif
                @endforelse
            </div>
            @if ($packageActivity)
                <section class="mt-8 space-y-5" data-package-panel="{{ $packageActivity->id }}" aria-labelledby="package-heading">
                    <flux:heading level="3" id="package-heading">Titles and search</flux:heading>
                    <p class="text-sm leading-relaxed text-zinc-600 dark:text-zinc-300">Review is done, so packaging proposed {{ $packageTitleOptionCount }} titles and the search details. Pick a title, edit anything, then save. Nothing changes in your article until you save.</p>
                    <div>
                        <p class="mb-2 text-sm font-medium">Title options</p>
                        <ol class="list-decimal space-y-2 pl-5 text-sm">
                            @foreach ($packageActivity->proposal['title_options'] ?? [] as $index => $option)
                                <li wire:key="title-option-{{ $index }}"><button type="button" class="text-left underline-offset-2 hover:underline" wire:click="$set('package.title', {{ \Illuminate\Support\Js::from((string) $option) }})">{{ $option }}</button></li>
                            @endforeach
                        </ol>
                    </div>
                    <div class="space-y-4">
                        <div><flux:input wire:model.live.debounce.400ms="package.title" label="Title" maxlength="240" /><p class="mt-1 text-xs text-zinc-600 dark:text-zinc-300">Sentence case. Shown as the page heading.</p></div>
                        <div><flux:input wire:model.live.debounce.400ms="package.seo_title" label="SEO title" maxlength="240" /><p class="mt-1 text-xs text-zinc-600 dark:text-zinc-300" data-character-count="seo_title"><span x-text="[...($wire.package.seo_title ?? '')].length">{{ mb_strlen($package['seo_title']) }}</span> characters; aim for 50 to 60.</p></div>
                        <div><flux:textarea wire:model.live.debounce.400ms="package.description" label="Meta description" rows="3" maxlength="1000" /><p class="mt-1 text-xs text-zinc-600 dark:text-zinc-300" data-character-count="description"><span x-text="[...($wire.package.description ?? '')].length">{{ mb_strlen($package['description']) }}</span> characters; aim for 150 to 160.</p></div>
                        <div><flux:input wire:model.live.debounce.400ms="package.slug" label="Slug" maxlength="240" /><p class="mt-1 text-xs text-zinc-600 dark:text-zinc-300">Four to six lowercase words joined by hyphens. Never an archive slug.</p></div>
                        <div><flux:input wire:model="package.greatest_hits_tag" label="Greatest-hits tag" maxlength="80" /><p class="mt-1 text-xs text-zinc-600 dark:text-zinc-300">Your label for this piece. Never shown publicly.</p></div>
                    </div>
                    @if ($packageLintMessages !== [])
                        <ul class="space-y-1 text-sm text-amber-800 dark:text-amber-200" data-package-lint>@foreach ($packageLintMessages as $message)<li>{{ $message }}</li>@endforeach</ul>
                    @endif
                    @can('approve', $article)<flux:button size="sm" variant="primary" wire:click="acceptPackageProposal({{ $packageActivity->id }})" data-publishing-editor-action>Save titles and search details</flux:button>@endcan
                    <p class="text-xs leading-relaxed text-zinc-600 dark:text-zinc-300">Saving creates a new revision with the same manuscript, so your review still counts. The release check runs these rules again.</p>
                </section>
            @endif
            @if ($planApproved)
                @can('approve', $article)<details class="publishing-disclosure mt-8"><summary>Start another review cycle</summary><p class="my-3 text-sm text-zinc-600 dark:text-zinc-300">The default is one review batch and one focused recheck. A new cycle is a deliberate restart.</p><flux:button size="sm" wire:click="restartReview" data-publishing-editor-action>Restart cycle</flux:button></details>@endcan
            @endif
            @if ($protectedBlocks !== [])
                <details class="publishing-disclosure mt-8"><summary>Protected passages ({{ count($protectedBlocks) }})</summary><div class="mt-4 space-y-3">@foreach ($protectedBlocks as $block)<div wire:key="protected-{{ $block['id'] }}" class="flex flex-wrap items-center justify-between gap-2"><code class="text-xs">{{ $block['id'] }}</code>@can('update', $article)<flux:button size="sm" variant="ghost" wire:click="unprotectBlock('{{ $block['id'] }}')" data-publishing-editor-action>Remove protection</flux:button>@endcan</div>@endforeach</div></details>
            @endif
        </div>
        <div x-show="context === 'sources'" class="space-y-6">
            @forelse ($evidenceSources as $source)
                <article wire:key="evidence-{{ $source->id }}" class="publishing-finding"><p class="text-sm font-medium">{{ $source->title ?? $source->url ?? 'Source '.$source->id }}</p><p class="mt-1 break-all text-xs text-zinc-600 dark:text-zinc-300">{{ $source->url }}</p>@if ($source->unresolved_reason)<p class="mt-3 text-sm text-amber-800 dark:text-amber-200">{{ $source->unresolved_reason }}</p>@endif</article>
            @empty<flux:text>Your research sources will appear here. Missing or restricted evidence is never treated as verified.</flux:text>@endforelse
        </div>
        <div x-show="context === 'brief'" class="space-y-6"><x-admin.publishing.partials.editorial-content :value="$displayBrief" /><div><flux:heading level="3">The argument</flux:heading><x-admin.publishing.partials.editorial-content class="mt-3" :value="$displayAngle" /></div><div><flux:heading level="3">The outline</flux:heading><x-admin.publishing.partials.editorial-content class="mt-3" :value="$displayPlan['outline'] ?? []" /></div></div>
    </aside>
</div>
