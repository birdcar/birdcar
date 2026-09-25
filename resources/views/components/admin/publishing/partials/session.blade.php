<section class="publishing-studio" data-publishing-studio data-publishing-arrival="{{ session('publishing-arrival') ? 'true' : 'false' }}" data-article-id="{{ $article->id }}" data-current-revision="{{ $currentRevisionId }}" data-user-id="{{ auth()->id() }}" x-data="publishingSession(@js($sessionMode))" x-init="contextOpen = window.matchMedia('(min-width: 1024px)').matches" @publishing-editor-action-blocked.window="if ($event.detail.articleId === Number($el.dataset.articleId)) editorActionMessage = $event.detail.status === 'saved' ? 'Manuscript saved. Choose the action again.' : 'Finish saving or resolve the manuscript conflict before continuing.'" @publishing-editor-state.window="if ($event.detail.articleId === Number($el.dataset.articleId)) editorState = $event.detail.status">
    <header class="publishing-session-header" data-publishing-enter>
        <div class="flex min-w-0 flex-1 flex-col gap-3">
            <flux:link :href="route('admin.publishing.dashboard')" class="w-fit text-sm" icon="arrow-left">All writing</flux:link>
            <flux:heading level="1" size="xl" class="max-w-4xl break-words text-balance">{{ $articleTitle }}</flux:heading>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-zinc-600 dark:text-zinc-300">
                <span>{{ match ($stage?->value) { 'developing' => 'Finding the argument', 'drafting' => 'Building the plan', 'in_review' => 'Shaping the manuscript', 'approved' => 'Approved for release', 'scheduled' => 'Scheduled', 'published' => 'Published', 'abandoned' => 'Set aside', default => $article->published_release_id ? 'Published' : 'An idea for later' } }}</span>
                @if ($article->published_release_id && $article->working_revision_id !== $article->publishedRelease?->revision_id)
                    <flux:badge size="sm">Unpublished changes</flux:badge>
                @endif
            </div>
        </div>
        <div class="flex shrink-0 items-center gap-3">
            <x-admin.publishing.partials.save-badge :state="$saveState" />
            @if ($previewUrl)
                <flux:button :href="$previewUrl" target="_blank" rel="noopener" size="sm" icon="arrow-top-right-on-square">Preview</flux:button>
            @endif
        </div>
    </header>

    <p x-show="editorActionMessage" x-text="editorActionMessage" x-cloak role="status" class="text-sm text-amber-800 dark:text-amber-200"></p>
    @if ($conflictMessage)
        <x-admin.publishing.partials.conflict-banner :message="$conflictMessage" :latest="$conflictLatestRevision" />
    @endif
    @if ($saveError)
        <flux:callout variant="danger" heading="This needs a look" :text="$saveError" role="alert" />
    @endif

    <div class="publishing-session-tabs" role="tablist" aria-label="Article working mode" @keydown.right.prevent="moveMode(1)" @keydown.left.prevent="moveMode(-1)" @keydown.home.prevent="focusMode('develop')" @keydown.end.prevent="focusMode('release')">
        @foreach (['develop' => 'Develop', 'write' => 'Write & review', 'release' => 'Release'] as $modeKey => $modeLabel)
            <button type="button" role="tab" id="session-tab-{{ $modeKey }}" aria-controls="session-panel-{{ $modeKey }}" :aria-selected="mode === '{{ $modeKey }}'" :tabindex="mode === '{{ $modeKey }}' ? 0 : -1" @click="selectMode('{{ $modeKey }}')" data-session-tab="{{ $modeKey }}" class="publishing-session-tab">
                {{ $modeLabel }}
            </button>
        @endforeach
    </div>

    <div class="publishing-agent-status" @if ($working) wire:poll.5s.visible="refreshAgentWork" @endif>
        @if ($attempt?->paused_at || $attempt?->parked_at || $attempt?->abandoned_at)
            <flux:callout variant="warning" heading="Work is paused" :text="$attempt->pause_reason ?? $attempt->parked_reason ?? $attempt->abandoned_reason ?? 'This attempt has been set aside.'" />
        @elseif ($working)
            <div class="publishing-work-status" role="status">
                <span class="publishing-activity-mark" data-publishing-activity aria-hidden="true"><span></span><span></span><span></span></span>
                <div>
                    <p class="font-medium">{{ $agentsPaused ? 'Agent work is queued; publishing agents are paused' : ($currentActivity?->status === \App\Models\Publishing\EditorialActivityStatus::Running ? 'Your editorial partner is working' : 'Ready for the next available worker') }}</p>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ match ($currentActivity?->kind?->value) { 'interview' => 'Making room for your perspective.', 'research_challenge' => 'Checking the evidence behind the argument.', 'plan' => 'Organizing the argument into a plan for your approval.', 'draft' => 'Drafting from your approved plan.', 'review_facts', 'review_voice', 'review_buyer', 'reconciliation', 'recheck' => 'Reading carefully. Your manuscript remains yours.', default => 'You can leave this page and return to the same work.' } }}</p>
                </div>
            </div>
        @elseif (in_array($currentActivity?->status?->value, ['failed', 'paused'], true))
            <flux:callout variant="warning" heading="Agent work needs attention" :text="$currentActivity->pause_reason ?? $currentActivity->error_reason ?? 'Open activity below for details. Your saved work is safe.'">
                @if ($currentActivity->status === \App\Models\Publishing\EditorialActivityStatus::Failed)
                    <x-slot:actions>
                        <flux:button size="sm" icon="arrow-path" wire:click="retryAgent({{ $currentActivity->id }})" wire:loading.attr="disabled" data-retry-agent="{{ $currentActivity->id }}">Try again</flux:button>
                    </x-slot:actions>
                @endif
            </flux:callout>
        @endif
    </div>

    <section id="session-panel-develop" role="tabpanel" aria-labelledby="session-tab-develop" tabindex="0" data-session-panel="develop" x-show="mode === 'develop'" x-cloak>
        @include('components.admin.publishing.partials.develop')
    </section>
    <section id="session-panel-write" role="tabpanel" aria-labelledby="session-tab-write" tabindex="0" data-session-panel="write" x-show="mode === 'write'" x-cloak>
        @include('components.admin.publishing.partials.write')
    </section>
    <section id="session-panel-release" role="tabpanel" aria-labelledby="session-tab-release" tabindex="0" data-session-panel="release" x-show="mode === 'release'" x-cloak>
        <div class="publishing-release-grid">
            <div class="min-w-0">
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <flux:heading level="2" size="lg">{{ $release ? 'This exact release' : 'Before it goes out' }}</flux:heading>
                    @if ($release)<flux:badge size="sm">Release #{{ $release->id }}</flux:badge>@endif
                </div>
                @if ($releasePreviewUrl || $previewUrl)
                    <p class="mb-4 text-sm text-zinc-600 dark:text-zinc-300">{{ $release ? 'A frozen preview of the release below—not the latest draft.' : 'Working preview. Prepare a release to freeze the version you will approve.' }}</p>
                    <iframe class="publishing-preview" src="{{ $releasePreviewUrl ?? $previewUrl }}" sandbox="allow-same-origin" loading="lazy" title="{{ $release ? 'Exact release preview' : 'Working article preview' }}"></iframe>
                @else
                    <div class="publishing-empty"><flux:heading level="3">An article worth sending.</flux:heading><flux:text class="mt-3">Your preview will appear here once there is a saved manuscript.</flux:text><flux:button class="mt-6" @click="selectMode('write')">Open the manuscript</flux:button></div>
                @endif
            </div>
            <aside class="space-y-8">
                @include('components.admin.publishing.partials.details')
                <x-admin.publishing.partials.release-checklist :article="$article" :attempt="$attempt" :release="$release" />
            </aside>
        </div>
    </section>

    @include('components.admin.publishing.partials.activity')
    <noscript><p>Enable JavaScript to interview, edit, and approve work. Your saved articles remain unchanged.</p></noscript>
</section>
