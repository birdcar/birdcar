<?php

use App\Actions\Publishing\AdvancePublishingAttempt;
use App\Actions\Publishing\ApplyEditorialProposal;
use App\Actions\Publishing\ApprovePublishingStage;
use App\Actions\Publishing\FinishEditorialReview;
use App\Actions\Publishing\ManageArticleRelease;
use App\Actions\Publishing\StartEditorialActivity;
use App\Actions\Publishing\WriteArticle;
use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\EditorialFinding;
use App\Models\EvidenceSource;
use App\Models\Publishing\ApprovalKind;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\PublishingAttempt;
use App\Services\Publishing\AgentBudget;
use Illuminate\Support\Facades\DB;
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

    /** @var array{id: int, number: int, content_hash: string, preview_url: string, excerpt: string, metadata: array<string, mixed>}|null */
    public ?array $conflictLatestRevision = null;

    public ?string $angleInputHash = null;
    public ?string $planInputHash = null;
    public ?string $releaseInputHash = null;
    public int $budgetTopUpNanoUsd = 1_000_000_000;
    public string $budgetMutationKey = '';
    public string $sourceUrl = '';
    public string $voiceSample = '';
    public string $interviewAnswers = '';
    public string $selectedAngleOptionKey = '';

    /** @var list<int> */
    public array $selectedVoiceSampleArticleIds = [];

    /** @var list<int> */
    public array $selectedEvidenceSourceIds = [];

    /** @var array<int, array{disposition: string, reason?: string}> */
    public array $findingDispositions = [];

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
        $this->hydrateAgentSelectionsFromAttempt();
        $this->refreshApprovalInputs();
    }

    private function hydrateAgentSelectionsFromAttempt(): void
    {
        $attempt = $this->article->currentAttempt;
        $context = $attempt instanceof PublishingAttempt && is_array($attempt->interview_context) ? $attempt->interview_context : [];
        $this->selectedEvidenceSourceIds = is_array($context['selected_evidence_source_ids'] ?? null) ? array_values(array_map('intval', $context['selected_evidence_source_ids'])) : [];
        $this->selectedVoiceSampleArticleIds = is_array($context['voice_sample_ids'] ?? null) ? array_values(array_map('intval', $context['voice_sample_ids'])) : [];
        $selectedAngle = $context['selected_angle_option'] ?? '';
        $this->selectedAngleOptionKey = is_scalar($selectedAngle) ? (string) $selectedAngle : '';
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
        $this->conflictLatestRevision = null;
        $this->saveState = 'saving';

        try {
            $revision = $writer->save(auth()->user(), $this->article, $expectedRevisionId === null ? null : (int) $expectedRevisionId, is_array($document) ? $document : [], is_array($metadata) ? $metadata : [], (string) $clientMutationId, 'human');
            $this->currentRevisionId = (int) $revision->id;
            $this->document = $revision->document ?? [];
            $this->metadata = $revision->metadata ?? [];
            $this->article = $this->article->fresh(['workingRevision', 'currentAttempt.approvals', 'currentAttempt.releases', 'publishedRelease']);
            $this->refreshApprovalInputs();
            $this->saveState = 'saved';
            $this->conflictLatestRevision = null;

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
            $this->conflictLatestRevision = $this->saveState === 'conflict' ? $this->latestSavedRevisionPayload() : null;

            return [
                'ok' => false,
                'conflict' => $this->conflictMessage,
                'error' => $this->saveError,
                'revisionId' => $this->currentRevisionId,
                'latestRevision' => $this->conflictLatestRevision,
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
            $this->saveError = null;
            if (auth()->user()?->can(PublishingPermission::Develop->value)) {
                $this->persistSelectedEvidenceSources($attempt);
            }
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
            $this->saveError = null;
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

    /** @return array{voice_sample_ids: list<int>, voice_samples: list<array{id: int, excerpt: string|null}>} */
    private function voiceActivityInput(): array
    {
        $ids = $this->selectedVoiceSampleIds();
        if ($ids === [] && trim($this->voiceSample) !== '') {
            throw new RuntimeException('Select voice samples from the published archive; pasted legacy IDs or excerpts are not accepted for new dispatches.');
        }
        $excerpts = $this->selectedVoiceSampleExcerpts($ids);
        if ($ids !== [] && count($excerpts) < count($ids)) {
            throw new RuntimeException('Selected voice samples must be published archive articles with readable text.');
        }

        return [
            'voice_sample_ids' => $ids,
            'voice_samples' => array_map(fn (int $id): array => [
                'id' => $id,
                'excerpt' => $excerpts[$id] ?? null,
            ], $ids),
        ];
    }

    /** @return list<int> */
    private function selectedVoiceSampleIds(): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $this->selectedVoiceSampleArticleIds), fn (int $id): bool => $id > 0)));
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function selectedVoiceSampleExcerpts(array $ids): array
    {
        $excerpts = [];
        if ($ids !== []) {
            Article::query()
                ->whereIn('id', $ids)
                ->where('author_id', auth()->id())
                ->whereNotNull('published_release_id')
                ->with('publishedRelease')
                ->get()
                ->each(function (Article $article) use (&$excerpts): void {
                    $release = $article->publishedRelease;
                    $payload = is_array($release?->payload) ? $release->payload : [];
                    $document = is_array($payload['document'] ?? null) ? $payload['document'] : [];
                    $excerpt = $this->documentExcerpt($document);
                    if ($excerpt !== '') {
                        $excerpts[(int) $article->id] = mb_substr($excerpt, 0, 1200);
                    }
                });
        }

        return $excerpts;
    }

    public function startInterview(?StartEditorialActivity $activities = null): void
    {
        $this->startActivity(EditorialActivityKind::Interview, $activities, array_merge($this->voiceActivityInput(), [
            'selected_evidence_source_ids' => $this->selectedEvidenceSourceIds(),
        ]));
    }

    /** @return list<int> */
    private function selectedEvidenceSourceIds(): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $this->selectedEvidenceSourceIds), fn (int $id): bool => $id > 0)));
    }

    private function persistSelectedEvidenceSources(PublishingAttempt $attempt): void
    {
        DB::transaction(function () use ($attempt): void {
            $locked = $attempt->newQuery()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $article = Article::query()->whereKey($locked->article_id)->lockForUpdate()->firstOrFail();
            if ((int) ($article->current_attempt_id ?? 0) !== (int) $locked->id) {
                throw new RuntimeException('Only the current publishing attempt can save selected sources.');
            }
            if ($locked->paused_at !== null || $locked->parked_at !== null || $locked->abandoned_at !== null) {
                throw new RuntimeException('Blocked publishing attempts cannot save selected sources.');
            }

            $selected = $this->selectedEvidenceSourceIds();
            $context = is_array($locked->interview_context) ? $locked->interview_context : [];
            $context['selected_evidence_source_ids'] = $selected;
            $locked->forceFill(['interview_context' => $context])->save();
        });
    }

    public function startResearch(?StartEditorialActivity $activities = null): void
    {
        $this->startActivity(EditorialActivityKind::ResearchChallenge, $activities, [
            'source_url' => $this->sourceUrl,
            'selected_evidence_source_ids' => $this->selectedEvidenceSourceIds(),
        ], true);
    }

    public function selectAngleOption(string $optionKey): void
    {
        Gate::authorize(PublishingPermission::Develop->value);
        $attempt = $this->article->currentAttempt;
        if ($attempt === null) {
            $this->saveError = 'No active publishing attempt exists.';
            return;
        }

        try {
            app(AdvancePublishingAttempt::class)->selectAngleOption(auth()->user(), $attempt, $optionKey);
            $this->selectedAngleOptionKey = $optionKey;
            $this->article = $this->article->fresh(['workingRevision', 'currentAttempt.approvals', 'currentAttempt.releases', 'currentAttempt.editorialActivities', 'publishedRelease']);
            $this->refreshApprovalInputs();
            session()->flash('status', 'Angle option selected.');
        } catch (Throwable $exception) {
            $this->saveError = $exception->getMessage();
        }
    }

    public function submitInterviewAnswers(): void
    {
        Gate::authorize(PublishingPermission::Develop->value);
        $attempt = $this->article->currentAttempt;
        if ($attempt === null) {
            $this->saveError = 'No active publishing attempt exists.';
            return;
        }

        try {
            app(AdvancePublishingAttempt::class)->submitInterviewAnswers(auth()->user(), $attempt, $this->interviewAnswers);
            $this->article = $this->article->fresh(['workingRevision', 'currentAttempt.approvals', 'currentAttempt.releases', 'currentAttempt.editorialActivities', 'publishedRelease']);
            $this->refreshApprovalInputs();
            session()->flash('status', 'Interview answers saved.');
        } catch (Throwable $exception) {
            $this->saveError = $exception->getMessage();
        }
    }

    public function startReviews(?StartEditorialActivity $activities = null): void
    {
        $activities ??= app(StartEditorialActivity::class);
        $attempt = $this->article->currentAttempt;
        if ($attempt === null) {
            $this->saveError = 'No active publishing attempt exists.';
            return;
        }

        try {
            $voiceInput = $this->voiceActivityInput();
            foreach ([EditorialActivityKind::ReviewFacts, EditorialActivityKind::ReviewVoice, EditorialActivityKind::ReviewBuyer] as $kind) {
                $activities->start(auth()->user(), $attempt, $kind, $voiceInput, 'review-'.$attempt->review_cycle);
            }
            $this->article = $this->article->fresh(['workingRevision', 'currentAttempt.approvals', 'currentAttempt.releases', 'currentAttempt.editorialActivities', 'publishedRelease']);
            session()->flash('status', 'Review activities started.');
        } catch (Throwable $exception) {
            $this->saveError = $exception->getMessage();
        }
    }

    public function decideFinding(int $findingId, string $disposition, string $reason = ''): void
    {
        Gate::authorize('approve', $this->article);
        if (! in_array($disposition, ['accepted', 'rejected', 'false_positive', 'deferred'], true)) {
            $this->saveError = 'Unknown review disposition.';
            return;
        }

        $this->findingDispositions[$findingId] = [
            'disposition' => $disposition,
            'reason' => $reason,
        ];
    }

    public function applyProposal(int $findingId, ?ApplyEditorialProposal $proposals = null): void
    {
        Gate::authorize('update', $this->article);
        $proposals ??= app(ApplyEditorialProposal::class);
        $attempt = $this->article->currentAttempt;
        if ($attempt === null || $this->currentRevisionId === null) {
            $this->saveError = 'No active revision exists for proposal acceptance.';
            return;
        }

        try {
            $revision = $proposals->apply(auth()->user(), $attempt, [$findingId], $this->currentRevisionId);
            $this->currentRevisionId = (int) $revision->id;
            $this->document = $revision->document ?? $this->document;
            $this->metadata = $revision->metadata ?? $this->metadata;
            $this->article = $this->article->fresh(['workingRevision', 'currentAttempt.approvals', 'currentAttempt.releases', 'currentAttempt.editorialActivities', 'publishedRelease']);
            $this->refreshApprovalInputs();
            session()->flash('status', 'Editorial proposal accepted.');
        } catch (Throwable $exception) {
            $this->saveError = $exception->getMessage();
        }
    }

    public function finishReview(?FinishEditorialReview $review = null): void
    {
        Gate::authorize('approve', $this->article);
        $review ??= app(FinishEditorialReview::class);
        $attempt = $this->article->currentAttempt;
        if ($attempt === null || $this->currentRevisionId === null) {
            $this->saveError = 'No active review exists.';
            return;
        }

        try {
            $review->finish(auth()->user(), $attempt, $this->latestReviewableRevisionId($attempt) ?? $this->currentRevisionId, $this->findingDispositions);
            $this->findingDispositions = [];
            $this->article = $this->article->fresh(['workingRevision', 'currentAttempt.approvals', 'currentAttempt.releases', 'currentAttempt.editorialActivities', 'publishedRelease']);
            session()->flash('status', 'Review finished; affected-area recheck queued if needed.');
        } catch (Throwable $exception) {
            $this->saveError = $exception->getMessage();
        }
    }

    public function restartReview(?FinishEditorialReview $review = null): void
    {
        Gate::authorize('approve', $this->article);
        $review ??= app(FinishEditorialReview::class);
        $attempt = $this->article->currentAttempt;
        if ($attempt === null) {
            $this->saveError = 'No active review exists.';
            return;
        }

        try {
            $review->restart(auth()->user(), $attempt);
            $this->article = $this->article->fresh(['workingRevision', 'currentAttempt.approvals', 'currentAttempt.releases', 'currentAttempt.editorialActivities', 'publishedRelease']);
            session()->flash('status', 'Review cycle restarted.');
        } catch (Throwable $exception) {
            $this->saveError = $exception->getMessage();
        }
    }

    private function latestReviewableRevisionId(PublishingAttempt $attempt): ?int
    {
        $revisionId = $attempt->editorialActivities()
            ->where('review_cycle', $attempt->review_cycle)
            ->where('kind', EditorialActivityKind::Reconciliation->value)
            ->where('status', 'completed')
            ->latest('id')
            ->value('revision_id');

        return $revisionId === null ? null : (int) $revisionId;
    }

    public function topUpBudget(?AgentBudget $budget = null): void
    {
        Gate::authorize(PublishingPermission::Budget->value);
        $budget ??= app(AgentBudget::class);
        $attempt = $this->article->currentAttempt;
        if ($attempt === null) {
            $this->saveError = 'No active publishing attempt exists.';
            return;
        }

        try {
            $budget->increaseAllowance(auth()->user(), $attempt, $this->budgetTopUpNanoUsd, $this->budgetMutationKey !== '' ? $this->budgetMutationKey : 'ui-topup-'.uniqid());
            $this->article = $this->article->fresh(['workingRevision', 'currentAttempt.approvals', 'currentAttempt.releases', 'currentAttempt.editorialActivities', 'publishedRelease']);
            session()->flash('status', 'Agent budget increased.');
        } catch (Throwable $exception) {
            $this->saveError = $exception->getMessage();
        }
    }

    private function startActivity(EditorialActivityKind $kind, ?StartEditorialActivity $activities, array $input = [], bool $persistSelectedEvidence = false): void
    {
        Gate::authorize(PublishingPermission::Develop->value);
        $activities ??= app(StartEditorialActivity::class);
        $attempt = $this->article->currentAttempt;
        if ($attempt === null) {
            $this->saveError = 'No active publishing attempt exists.';
            return;
        }

        try {
            $activities->start(auth()->user(), $attempt, $kind, $input, $kind->value.'-'.$attempt->review_cycle);
            $this->article = $this->article->fresh(['workingRevision', 'currentAttempt.approvals', 'currentAttempt.releases', 'currentAttempt.editorialActivities', 'publishedRelease']);
            session()->flash('status', 'Agent activity started: '.$kind->value.'.');
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

    /**
     * @return array{id: int, number: int, content_hash: string, preview_url: string, excerpt: string, metadata: array<string, mixed>}|null
     */
    private function latestSavedRevisionPayload(): ?array
    {
        $freshArticle = Article::query()->whereKey($this->article->id)->with('workingRevision')->first();
        $revision = $freshArticle?->workingRevision;
        if (! $revision instanceof ArticleRevision) {
            return null;
        }

        $document = $revision->document ?? [];
        $metadata = $revision->metadata ?? [];

        return [
            'id' => (int) $revision->id,
            'number' => (int) $revision->number,
            'content_hash' => (string) $revision->content_hash,
            'preview_url' => route('admin.publishing.articles.preview', ['article' => $this->article, 'revision' => $revision->id]),
            'excerpt' => $this->documentExcerpt(is_array($document) ? $document : []),
            'metadata' => is_array($metadata) ? $metadata : [],
        ];
    }

    /** @param array<string, mixed> $document */
    private function documentExcerpt(array $document): string
    {
        $text = [];
        $walk = function (array $nodes) use (&$walk, &$text): void {
            foreach ($nodes as $node) {
                if (! is_array($node)) {
                    continue;
                }
                if (is_string($node['text'] ?? null)) {
                    $text[] = $node['text'];
                }
                if (is_array($node['content'] ?? null)) {
                    $walk($node['content']);
                }
            }
        };
        $walk(is_array($document['content'] ?? null) ? $document['content'] : []);

        $excerpt = trim(implode(' ', $text));

        return $excerpt === '' ? 'Latest saved revision has no text content.' : str($excerpt)->limit(220)->toString();
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

        $budget = $attempt === null ? null : app(AgentBudget::class)->available($attempt);
        $activities = $attempt === null ? collect() : $attempt->editorialActivities()->latest()->limit(12)->get();
        $findings = $attempt === null ? collect() : EditorialFinding::query()
            ->where('attempt_id', $attempt->id)
            ->whereNull('stale_at')
            ->latest()
            ->limit(20)
            ->get();
        $sources = $attempt === null ? collect() : EvidenceSource::query()
            ->where('attempt_id', $attempt->id)
            ->latest()
            ->limit(10)
            ->get();
        $voiceSampleOptions = Article::query()
            ->where('author_id', auth()->id())
            ->whereNotNull('published_release_id')
            ->whereHas('publishedRelease')
            ->with('publishedRelease')
            ->latest('updated_at')
            ->limit(20)
            ->get();
        $interviewContext = is_array($attempt?->interview_context) ? $attempt->interview_context : [];

        return [
            'attempt' => $attempt,
            'previewUrl' => $previewUrl,
            'release' => $release,
            'protectedBlocks' => $this->protectedBlocks(),
            'agentBudget' => $budget,
            'agentActivities' => $activities,
            'editorialFindings' => $findings,
            'evidenceSources' => $sources,
            'voiceSampleOptions' => $voiceSampleOptions,
            'interviewContext' => $interviewContext,
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
        <x-admin.publishing.partials.conflict-banner :message="$conflictMessage" :latest="$conflictLatestRevision" />
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
                    <div><dt class="font-medium text-zinc-100">Agent budget</dt><dd>{{ $agentBudget ? number_format($agentBudget['available'] / 1_000_000_000, 3) : '0.000' }} USD available</dd></div>
                    <div><dt class="font-medium text-zinc-100">Latest activity</dt><dd>{{ $agentActivities->first()?->kind?->value ?? 'Ready for an agent activity' }} {{ $agentActivities->first()?->status?->value ? '· '.$agentActivities->first()?->status?->value : '' }}</dd></div>
                </dl>
                @php($displayBrief = is_array($attempt?->brief) ? $attempt->brief : [])
                @php($displayAngle = is_array($attempt?->angle) ? $attempt->angle : [])
                @php($interviewQuestions = is_array($interviewContext['questions'] ?? null) ? $interviewContext['questions'] : [])
                @php($angleOptions = is_array($interviewContext['angle_options'] ?? null) ? $interviewContext['angle_options'] : [])
                @if ($interviewQuestions !== [] || $displayBrief !== [] || $displayAngle !== [])
                    <div class="mt-4 rounded-lg border border-white/10 bg-zinc-950/60 p-4 text-sm text-zinc-200" data-interview-and-angle>
                        <h3 class="font-medium text-zinc-100">Interview and angle for approval</h3>
                        @if ($interviewQuestions !== [])
                            <div class="mt-3">
                                <p class="font-medium text-zinc-100">Latest interview questions</p>
                                <ol class="mt-2 list-decimal space-y-1 pl-5 text-zinc-300">
                                    @foreach ($interviewQuestions as $question)
                                        <li>{{ is_array($question) ? ($question['question'] ?? $question['text'] ?? json_encode($question)) : $question }}</li>
                                    @endforeach
                                </ol>
                            </div>
                        @endif
                        @if ($displayBrief !== [])
                            <div class="mt-3">
                                <p class="font-medium text-zinc-100">Brief to approve</p>
                                <pre class="mt-1 whitespace-pre-wrap text-xs text-zinc-300">{{ json_encode($displayBrief, JSON_PRETTY_PRINT) }}</pre>
                            </div>
                        @endif
                        @if ($angleOptions !== [])
                            <div class="mt-3 space-y-2">
                                <p class="font-medium text-zinc-100">Angle options</p>
                                @foreach ($angleOptions as $key => $option)
                                    <button type="button" wire:click="selectAngleOption('{{ $key }}')" class="block w-full rounded border border-white/15 p-3 text-left text-sm {{ (string) ($interviewContext['selected_angle_option'] ?? '') === (string) $key ? 'bg-sky-400/10 text-sky-100' : 'text-zinc-300' }}">
                                        {{ is_array($option) ? ($option['title'] ?? $option['thesis'] ?? json_encode($option)) : $option }}
                                    </button>
                                @endforeach
                            </div>
                        @endif
                        @if ($displayAngle !== [])
                            <div class="mt-3">
                                <p class="font-medium text-zinc-100">Selected angle</p>
                                <pre class="mt-1 whitespace-pre-wrap text-xs text-zinc-300">{{ json_encode($displayAngle, JSON_PRETTY_PRINT) }}</pre>
                            </div>
                        @endif
                    </div>
                @endif
                @php($displayPlan = is_array($attempt?->plan) ? $attempt->plan : [])
                @if ($displayPlan !== [])
                    <div class="mt-4 rounded-lg border border-white/10 bg-zinc-950/60 p-4 text-sm text-zinc-200" data-generated-plan-digest>
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="font-medium text-zinc-100">Plan digest for approval</h3>
                            @if (($displayPlan['source'] ?? null) === 'agent')
                                <span class="rounded bg-sky-400/10 px-2 py-1 text-xs text-sky-100">Generated by agent</span>
                            @endif
                        </div>
                        @if (isset($displayPlan['argument']))
                            <p class="mt-3 text-zinc-300">{{ is_string($displayPlan['argument']) ? $displayPlan['argument'] : json_encode($displayPlan['argument']) }}</p>
                        @endif
                        <div class="mt-3 grid gap-3 md:grid-cols-2">
                            <div>
                                <p class="font-medium text-zinc-100">Outline</p>
                                <pre class="mt-1 whitespace-pre-wrap text-xs text-zinc-300">{{ json_encode($displayPlan['outline'] ?? [], JSON_PRETTY_PRINT) }}</pre>
                            </div>
                            <div>
                                <p class="font-medium text-zinc-100">Visual plan</p>
                                <pre class="mt-1 whitespace-pre-wrap text-xs text-zinc-300">{{ json_encode($displayPlan['visualPlan'] ?? [], JSON_PRETTY_PRINT) }}</pre>
                            </div>
                        </div>
                    </div>
                @endif
                <div class="mt-4 flex flex-wrap gap-3">
                    @can(PublishingPermission::Develop->value)
                        <button wire:click="startInterview" class="rounded border border-white/15 px-3 py-2 text-sm">Start interview</button>
                        <button wire:click="submitInterviewAnswers" class="rounded border border-white/15 px-3 py-2 text-sm">Save interview answers</button>
                        <button wire:click="startResearch" class="rounded border border-white/15 px-3 py-2 text-sm">Research sources</button>
                    @endcan
                    @can(PublishingPermission::Approve->value)
                        <button wire:click="approveAngle('{{ $angleInputHash }}')" class="rounded border border-white/15 px-3 py-2 text-sm">Approve angle</button>
                        <button wire:click="approvePlan('{{ $planInputHash }}')" class="rounded border border-white/15 px-3 py-2 text-sm">Approve plan</button>
                    @endcan
                </div>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <label class="text-sm text-zinc-300">Source URL <input wire:model="sourceUrl" class="mt-1 w-full rounded bg-zinc-950 px-3 py-2" placeholder="https://example.com/source"></label>
                    <label class="text-sm text-zinc-300">Interview answers <textarea wire:model="interviewAnswers" class="mt-1 w-full rounded bg-zinc-950 px-3 py-2" rows="3" placeholder="Answer the latest interview questions before angle approval"></textarea></label>
                    <div class="text-sm text-zinc-300 sm:col-span-2">
                        <p class="font-medium text-zinc-100">Voice samples from published archive</p>
                        <div class="mt-2 grid gap-2">
                            @forelse ($voiceSampleOptions as $sampleArticle)
                                <label class="flex items-center gap-2 rounded border border-white/10 bg-zinc-950 p-2">
                                    <input type="checkbox" wire:model="selectedVoiceSampleArticleIds" value="{{ $sampleArticle->id }}">
                                    <span>{{ $sampleArticle->publishedRelease?->payload['metadata']['title'] ?? $sampleArticle->idea ?? 'Published article #'.$sampleArticle->id }}</span>
                                </label>
                            @empty
                                <p class="text-zinc-400">Publish or import an archive article before selecting voice samples.</p>
                            @endforelse
                        </div>
                    </div>
                    <div class="text-sm text-zinc-300 sm:col-span-2">
                        <p class="font-medium text-zinc-100">Retained owner sources</p>
                        <div class="mt-2 grid gap-2">
                            @forelse ($evidenceSources->whereIn('source_type', ['owner', 'restricted']) as $source)
                                <label class="flex items-center gap-2 rounded border border-white/10 bg-zinc-950 p-2">
                                    <input type="checkbox" wire:model="selectedEvidenceSourceIds" value="{{ $source->id }}">
                                    <span>{{ $source->title ?? $source->url ?? 'Source #'.$source->id }} @if($source->unresolved_reason)<span class="text-amber-200">— {{ $source->unresolved_reason }}</span>@endif</span>
                                </label>
                            @empty
                                <p class="text-zinc-400">No retained owner sources are available for selection.</p>
                            @endforelse
                        </div>
                    </div>
                    <label class="text-sm text-zinc-300 sm:col-span-2">Voice sample notes <input wire:model="voiceSample" class="mt-1 w-full rounded bg-zinc-950 px-3 py-2" placeholder="New dispatches require selecting published archive samples above; pasted IDs/excerpts are rejected"></label>
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
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-semibold">Reviews and proposals</h2>
                        <p class="mt-2 text-sm text-zinc-400">Agent findings are anchored to one revision. Human decisions apply proposals; protected prose is never overwritten automatically.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @can(PublishingPermission::Develop->value)
                            <button wire:click="startReviews" class="rounded border border-white/15 px-3 py-2 text-sm">Start three-lens review</button>
                        @endcan
                        @can(PublishingPermission::Approve->value)
                            <button wire:click="finishReview" class="rounded border border-white/15 px-3 py-2 text-sm">Finish review</button>
                            <button wire:click="restartReview" class="rounded border border-white/15 px-3 py-2 text-sm">Restart cycle</button>
                        @endcan
                    </div>
                </div>

                <div class="mt-5 grid gap-4 lg:grid-cols-2">
                    <div class="rounded-lg border border-white/10 p-4">
                        <h3 class="font-medium">Activity log</h3>
                        <ul class="mt-3 space-y-2 text-sm text-zinc-300">
                            @forelse ($agentActivities as $activity)
                                <li class="rounded bg-zinc-950 p-3">{{ $activity->kind->value }} · {{ $activity->status->value }} @if($activity->pause_reason)<span class="text-amber-200">— {{ $activity->pause_reason }}</span>@endif</li>
                            @empty
                                <li class="text-zinc-400">No agent activity has been started for this attempt.</li>
                            @endforelse
                        </ul>
                    </div>
                    <div class="rounded-lg border border-white/10 p-4">
                        <h3 class="font-medium">Evidence sources</h3>
                        <ul class="mt-3 space-y-2 text-sm text-zinc-300">
                            @forelse ($evidenceSources as $source)
                                <li class="rounded bg-zinc-950 p-3">{{ $source->title ?? $source->url ?? 'Source' }} @if($source->unresolved_reason)<span class="text-amber-200">— {{ $source->unresolved_reason }}</span>@endif</li>
                            @empty
                                <li class="text-zinc-400">No evidence sources recorded yet.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>

                <div class="mt-5 rounded-lg border border-white/10 p-4">
                    <h3 class="font-medium">Findings and proposals</h3>
                    <ul class="mt-3 space-y-2 text-sm text-zinc-300">
                        @forelse ($editorialFindings as $finding)
                            <li class="rounded bg-zinc-950 p-3">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div><span class="text-xs uppercase text-zinc-500">{{ $finding->lens }} · {{ $finding->severity }}</span><p class="mt-1">{{ $finding->statement }}</p></div>
                                    @if($finding->proposed_patch && !$finding->disposition)
                                        @can(PublishingPermission::Write->value)
                                            <button wire:click="applyProposal({{ $finding->id }})" class="rounded border border-white/15 px-3 py-1 text-xs">Accept patch</button>
                                        @endcan
                                    @endif
                                    @if(!$finding->disposition)
                                        @can(PublishingPermission::Approve->value)
                                            <button wire:click="decideFinding({{ $finding->id }}, 'rejected')" class="rounded border border-white/15 px-3 py-1 text-xs">Reject advice</button>
                                            <button wire:click="decideFinding({{ $finding->id }}, 'false_positive')" class="rounded border border-white/15 px-3 py-1 text-xs">False positive</button>
                                        @endcan
                                    @endif
                                </div>
                                @if(is_array($finding->supporting_quotations) && count($finding->supporting_quotations) > 0)
                                    <div class="mt-2 space-y-1 text-xs text-zinc-400">
                                        <p class="font-medium text-zinc-300">Supporting quotations</p>
                                        @foreach($finding->supporting_quotations as $quotation)
                                            @if(is_array($quotation))
                                                <blockquote class="border-l border-white/15 pl-2">Source #{{ $quotation['source_id'] ?? 'unknown' }}: “{{ $quotation['quote'] ?? '' }}”</blockquote>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                                @if($finding->disposition)<p class="mt-2 text-xs text-zinc-500">Disposition: {{ $finding->disposition }}</p>@endif
                            </li>
                        @empty
                            <li class="text-zinc-400">No findings have been recorded for this revision.</li>
                        @endforelse
                    </ul>
                </div>

                <div class="mt-5 rounded-lg border border-white/10 p-4">
                    <h3 class="font-medium">Budget top-up</h3>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <input wire:model="budgetTopUpNanoUsd" type="number" min="1" class="rounded bg-zinc-950 px-3 py-2 text-sm" aria-label="Nano USD top-up amount">
                        <input wire:model="budgetMutationKey" class="rounded bg-zinc-950 px-3 py-2 text-sm" placeholder="mutation key">
                        @can(PublishingPermission::Budget->value)
                            <button wire:click="topUpBudget" class="rounded border border-white/15 px-3 py-2 text-sm">Top up budget</button>
                        @endcan
                    </div>
                </div>

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
