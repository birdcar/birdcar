<?php

use App\Actions\Publishing\ApprovePublishingStage;
use App\Actions\Publishing\ManageArticleRelease;
use App\Actions\Publishing\WriteArticle;
use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\Publishing\ApprovalKind;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.admin')] class extends Component
{
    public Article $article;
    public ?int $currentRevisionId = null;
    public string $saveState = 'saved';
    public ?string $saveError = null;
    public ?string $conflictMessage = null;
    public ?string $angleInputHash = null;
    public ?string $planInputHash = null;
    public ?string $releaseInputHash = null;

    /** @var array<string, mixed> */
    public array $document = ['version' => 1, 'type' => 'doc', 'content' => []];

    /** @var array<string, mixed> */
    public array $metadata = [];

    public function mount(Article $article): void
    {
        Gate::authorize('view', $article);
        $this->article = $article->load(['workingRevision', 'currentAttempt.approvals', 'currentAttempt.releases', 'publishedRelease']);
        $this->currentRevisionId = $article->working_revision_id;
        $revision = $article->workingRevision;
        if ($revision instanceof ArticleRevision) {
            $this->document = $revision->document ?? $this->document;
            $this->metadata = $revision->metadata ?? [];
        }
        $this->refreshApprovalInputs();
    }

    /**
     * @param array<string, mixed> $document
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    public function saveDocument(mixed $articleIdOrExpectedRevisionId, mixed $expectedRevisionIdOrMutationId, mixed $clientMutationIdOrDocument, mixed $documentOrMetadata, mixed $metadata = [], ?WriteArticle $writer = null): array
    {
        Gate::authorize('update', $this->article);
        $writer ??= app(WriteArticle::class);

        if (is_string($expectedRevisionIdOrMutationId)) {
            $articleId = (int) $this->article->id;
            $expectedRevisionId = $articleIdOrExpectedRevisionId;
            $clientMutationId = $expectedRevisionIdOrMutationId;
            $document = $clientMutationIdOrDocument;
            $metadata = $documentOrMetadata;
        } else {
            $articleId = (int) $articleIdOrExpectedRevisionId;
            $expectedRevisionId = $expectedRevisionIdOrMutationId;
            $clientMutationId = $clientMutationIdOrDocument;
            $document = $documentOrMetadata;
        }

        abort_unless((int) $this->article->id === $articleId, 404);

        $this->saveError = null;
        $this->conflictMessage = null;
        $this->saveState = 'saving';

        try {
            $revision = $writer->save(auth()->user(), $this->article, $expectedRevisionId === null ? null : (int) $expectedRevisionId, is_array($document) ? $document : [], is_array($metadata) ? $metadata : [], (string) $clientMutationId, 'human');
            $this->currentRevisionId = (int) $revision->id;
            $this->document = $revision->document ?? [];
            $this->metadata = $revision->metadata ?? [];
            $this->article = $this->article->fresh(['workingRevision', 'currentAttempt.approvals', 'currentAttempt.releases', 'publishedRelease']);
            $this->refreshApprovalInputs();
            $this->saveState = 'saved';

            return [
                'ok' => true,
                'revisionId' => $this->currentRevisionId,
                'document' => $this->document,
                'metadata' => $this->metadata,
            ];
        } catch (Throwable $exception) {
            $this->saveState = str_contains($exception->getMessage(), 'changed since') || str_contains($exception->getMessage(), 'mutation key') ? 'conflict' : 'error';
            $this->conflictMessage = $this->saveState === 'conflict' ? $exception->getMessage() : null;
            $this->saveError = $this->saveState === 'error' ? $exception->getMessage() : null;

            return [
                'ok' => false,
                'conflict' => $this->conflictMessage,
                'error' => $this->saveError,
                'revisionId' => $this->currentRevisionId,
            ];
        }
    }

    public function approveAngle(string $expectedInputHash = '', ?ApprovePublishingStage $approver = null): void
    {
        Gate::authorize('approve', $this->article);
        $approver ??= app(ApprovePublishingStage::class);
        $attempt = $this->article->currentAttempt;
        if ($attempt === null) {
            $this->saveError = 'No active publishing attempt exists.';
            return;
        }

        try {
            $approver->approve(auth()->user(), $attempt, ApprovalKind::Angle, $expectedInputHash !== '' ? $expectedInputHash : (string) $this->angleInputHash);
            $this->article = $this->article->fresh(['workingRevision', 'currentAttempt.approvals', 'currentAttempt.releases', 'publishedRelease']);
            $this->refreshApprovalInputs();
            session()->flash('status', 'Angle approved.');
        } catch (Throwable $exception) {
            $this->saveError = $exception->getMessage();
        }
    }

    public function approvePlan(string $expectedInputHash = '', ?ApprovePublishingStage $approver = null): void
    {
        Gate::authorize('approve', $this->article);
        $approver ??= app(ApprovePublishingStage::class);
        $attempt = $this->article->currentAttempt;
        if ($attempt === null) {
            $this->saveError = 'No active publishing attempt exists.';
            return;
        }

        try {
            $approver->approve(auth()->user(), $attempt, ApprovalKind::Plan, $expectedInputHash !== '' ? $expectedInputHash : (string) $this->planInputHash);
            $this->article = $this->article->fresh(['workingRevision', 'currentAttempt.approvals', 'currentAttempt.releases', 'publishedRelease']);
            $this->refreshApprovalInputs();
            session()->flash('status', 'Plan approved.');
        } catch (Throwable $exception) {
            $this->saveError = $exception->getMessage();
        }
    }

    public function approveRelease(string $expectedReleaseHash = '', ?ManageArticleRelease $releases = null): void
    {
        Gate::authorize('approve', $this->article);
        $releases ??= app(ManageArticleRelease::class);
        $release = $this->currentReleasePackage();

        if ($release === null) {
            $this->saveError = 'Prepare an exact release package for the current revision before approval.';
            return;
        }

        try {
            $releases->approve(auth()->user(), $release, $expectedReleaseHash !== '' ? $expectedReleaseHash : (string) $this->releaseInputHash);
            $this->article = $this->article->fresh(['workingRevision', 'currentAttempt.approvals', 'currentAttempt.releases', 'publishedRelease']);
            $this->refreshApprovalInputs();
            session()->flash('status', 'Exact release package approved.');
        } catch (Throwable $exception) {
            $this->saveError = $exception->getMessage();
        }
    }

    public function protectFirstBlock(): void
    {
        Gate::authorize('update', $this->article);
        $blockId = $this->firstProtectableBlockId($this->document['content'] ?? []);
        if ($blockId === null) {
            $this->saveError = 'No protectable manuscript block exists yet.';
            return;
        }

        $this->setBlockProtection($blockId, true);
    }

    public function unprotectBlock(string $blockId): void
    {
        Gate::authorize('update', $this->article);
        $this->setBlockProtection($blockId, false);
    }

    /**
     * @param array<int, mixed> $nodes
     */
    private function firstProtectableBlockId(array $nodes): ?string
    {
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }
            $id = data_get($node, 'attrs.id');
            if (is_string($id) && ($node['type'] ?? null) !== 'text') {
                return $id;
            }
            $nested = $this->firstProtectableBlockId(is_array($node['content'] ?? null) ? $node['content'] : []);
            if ($nested !== null) {
                return $nested;
            }
        }

        return null;
    }

    private function setBlockProtection(string $blockId, bool $protected): void
    {
        $document = $this->document;
        $changed = false;
        $apply = function (array $nodes) use (&$apply, $blockId, $protected, &$changed): array {
            foreach ($nodes as &$node) {
                if (! is_array($node)) {
                    continue;
                }
                if (data_get($node, 'attrs.id') === $blockId) {
                    $node['attrs'] = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
                    $node['attrs']['protected'] = $protected;
                    $changed = true;
                }
                if (is_array($node['content'] ?? null)) {
                    $node['content'] = $apply($node['content']);
                }
            }

            return $nodes;
        };
        $document['content'] = $apply(is_array($document['content'] ?? null) ? $document['content'] : []);

        if (! $changed) {
            $this->saveError = 'That protected passage was not found in the canonical document.';
            return;
        }

        try {
            $revision = app(WriteArticle::class)->save(
                auth()->user(),
                $this->article,
                $this->currentRevisionId,
                $document,
                $this->metadata,
                'protected-block-'.uniqid('', true),
                'human',
            );
            $this->currentRevisionId = (int) $revision->id;
            $this->document = $revision->document ?? $document;
            $this->metadata = $revision->metadata ?? $this->metadata;
            $this->article = $this->article->fresh(['workingRevision', 'currentAttempt.approvals', 'currentAttempt.releases', 'publishedRelease']);
            $this->refreshApprovalInputs();
            $this->saveState = 'saved';
            $this->saveError = null;
        } catch (Throwable $exception) {
            $this->document = $document;
            $this->saveState = 'error';
            $this->saveError = $exception->getMessage();
        }
    }

    /**
     * @return array<int, array{id: string, type: string}>
     */
    private function protectedBlocks(): array
    {
        $blocks = [];
        $walk = function (array $nodes) use (&$walk, &$blocks): void {
            foreach ($nodes as $node) {
                if (! is_array($node)) {
                    continue;
                }
                $id = data_get($node, 'attrs.id');
                if (is_string($id) && data_get($node, 'attrs.protected') === true) {
                    $blocks[] = ['id' => $id, 'type' => (string) ($node['type'] ?? 'block')];
                }
                if (is_array($node['content'] ?? null)) {
                    $walk($node['content']);
                }
            }
        };
        $walk(is_array($this->document['content'] ?? null) ? $this->document['content'] : []);

        return $blocks;
    }

    private function refreshApprovalInputs(): void
    {
        $attempt = $this->article->currentAttempt;
        if ($attempt === null) {
            $this->angleInputHash = null;
            $this->planInputHash = null;
            $this->releaseInputHash = null;
            return;
        }

        $approver = app(ApprovePublishingStage::class);
        $this->angleInputHash = $approver->inputHashFor($attempt, ApprovalKind::Angle);
        $this->planInputHash = $approver->inputHashFor($attempt, ApprovalKind::Plan);
        $this->releaseInputHash = $this->currentReleasePackage()?->release_hash;
    }

    private function currentReleasePackage(): ?\App\Models\ArticleRelease
    {
        return $this->article->currentAttempt?->releases()
            ->where('revision_id', $this->currentRevisionId)
            ->whereIn('status', ['prepared', 'approved', 'scheduled'])
            ->latest()
            ->first();
    }

    public function with(): array
    {
        $previewUrl = $this->currentRevisionId === null ? null : route('admin.publishing.articles.preview', ['article' => $this->article, 'revision' => $this->currentRevisionId]);
        $attempt = $this->article->currentAttempt;
        $release = $this->currentReleasePackage();
        $release ??= $attempt === null ? $this->article->publishedRelease : null;

        return [
            'attempt' => $attempt,
            'previewUrl' => $previewUrl,
            'release' => $release,
            'protectedBlocks' => $this->protectedBlocks(),
        ];
    }
};
?>

<section class="space-y-8" data-article-id="{{ $article->id }}" data-current-revision="{{ $currentRevisionId }}" data-user-id="{{ auth()->id() }}">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-sm text-zinc-400">Article workspace</p>
            <h1 class="text-3xl font-semibold">{{ $article->idea }}</h1>
        </div>
        <x-admin.publishing.partials.save-badge :state="$saveState" />
    </div>

    @if ($conflictMessage)
        <x-admin.publishing.partials.conflict-banner :message="$conflictMessage" />
    @endif
    @if ($saveError)
        <div class="rounded border border-red-400/30 bg-red-400/10 p-4 text-sm text-red-100">{{ $saveError }}</div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[1fr_22rem]">
        <div class="space-y-6">
            <section class="rounded-xl border border-white/10 bg-white/5 p-6">
                <h2 class="text-xl font-semibold">Brief, angle, and plan</h2>
                <dl class="mt-4 grid gap-3 text-sm text-zinc-300">
                    <div><dt class="font-medium text-zinc-100">Stage</dt><dd>{{ $attempt?->stage?->value ?? 'Idea saved for later' }}</dd></div>
                    <div><dt class="font-medium text-zinc-100">Agent status</dt><dd>Not wired yet. Future phases will show proposals here.</dd></div>
                </dl>
                <div class="mt-4 flex gap-3">
                    @can(PublishingPermission::Approve->value)
                        <button wire:click="approveAngle('{{ $angleInputHash }}')" class="rounded border border-white/15 px-3 py-2 text-sm">Approve angle</button>
                        <button wire:click="approvePlan('{{ $planInputHash }}')" class="rounded border border-white/15 px-3 py-2 text-sm">Approve plan</button>
                    @endcan
                </div>
            </section>

            <section class="admin-editor rounded-xl border border-white/10 bg-white/5 p-6" data-admin-editor-shell>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-semibold">Manuscript</h2>
                        <p class="mt-1 text-sm text-zinc-400">Protected passages are stored in the canonical JSON as block attributes and require proposal review before replacement.</p>
                    </div>
                    @can(PublishingPermission::Write->value)
                        <button wire:click="protectFirstBlock" class="rounded border border-white/15 px-3 py-2 text-sm">Protect first passage</button>
                    @endcan
                </div>
                @can(PublishingPermission::Write->value)
                    <div class="mt-4 flex flex-wrap gap-2" aria-label="Semantic manuscript blocks">
                        <button type="button" data-editor-command="note" class="rounded border border-white/15 px-3 py-1.5 text-xs">Insert note</button>
                        <button type="button" data-editor-command="callout" class="rounded border border-white/15 px-3 py-1.5 text-xs">Insert callout</button>
                        <button type="button" data-editor-command="chart" class="rounded border border-white/15 px-3 py-1.5 text-xs">Insert chart</button>
                        <button type="button" data-editor-command="diagram" class="rounded border border-white/15 px-3 py-1.5 text-xs">Insert diagram</button>
                        <button type="button" data-editor-command="protect" class="rounded border border-amber-300/30 px-3 py-1.5 text-xs text-amber-100">Protect selection</button>
                        <button type="button" data-editor-command="unprotect" class="rounded border border-white/15 px-3 py-1.5 text-xs">Unprotect selection</button>
                    </div>
                @endcan
                <div wire:ignore class="mt-4">
                    <flux:editor
                        class="min-h-64 rounded bg-zinc-950"
                        data-admin-editor
                        data-article-id="{{ $article->id }}"
                        data-user-id="{{ auth()->id() }}"
                        data-current-revision="{{ $currentRevisionId }}"
                        data-document='@json($document)'
                        data-metadata='@json($metadata)'
                        toolbar="heading | bold italic strike | bullet ordered blockquote | link"
                    />
                </div>
                <p class="mt-3 text-xs text-zinc-500">Autosaves canonical JSON with expected revision and tab-scoped recovery. Conflicts keep local text intact.</p>
            </section>

            <section class="rounded-xl border border-white/10 bg-white/5 p-6">
                <h2 class="text-xl font-semibold">Reviews and proposals</h2>
                <p class="mt-2 text-sm text-zinc-400">No agent proposals are available yet. Later phases will place suggested edits here for human accept/reject decisions; they will not overwrite protected prose automatically.</p>

                <div class="mt-5 rounded-lg border border-white/10 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="font-medium">Protected passage controls</h3>
                            <p class="mt-1 text-sm text-zinc-400">These controls read and update the canonical document JSON, not rendered HTML.</p>
                        </div>
                        @can(PublishingPermission::Write->value)
                            <button wire:click="protectFirstBlock" class="rounded border border-white/15 px-3 py-2 text-sm">Mark first block protected</button>
                        @endcan
                    </div>

                    @if ($protectedBlocks === [])
                        <p class="mt-4 text-sm text-zinc-400">No protected passages are marked in this revision.</p>
                    @else
                        <ul class="mt-4 space-y-2 text-sm text-zinc-300">
                            @foreach ($protectedBlocks as $block)
                                <li class="flex flex-wrap items-center justify-between gap-3 rounded border border-white/10 p-3">
                                    <span>{{ $block['type'] }} <code class="text-xs text-zinc-500">{{ $block['id'] }}</code></span>
                                    @can(PublishingPermission::Write->value)
                                        <button wire:click="unprotectBlock('{{ $block['id'] }}')" class="rounded border border-white/15 px-3 py-1 text-xs">Remove protection</button>
                                    @endcan
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>
        </div>

        <aside class="space-y-6">
            <section id="preview" class="rounded-xl border border-white/10 bg-white/5 p-6">
                <h2 class="font-semibold">Preview</h2>
                @if ($previewUrl)
                    <iframe class="mt-4 h-96 w-full rounded bg-white" src="{{ $previewUrl }}" sandbox="allow-same-origin" title="Article preview"></iframe>
                @else
                    <p class="mt-3 text-sm text-zinc-400">Save a revision before previewing.</p>
                @endif
            </section>
            <x-admin.publishing.partials.release-checklist :article="$article" :attempt="$attempt" :release="$release" />
        </aside>
    </div>
</section>
