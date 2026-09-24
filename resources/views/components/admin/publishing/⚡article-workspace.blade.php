<?php

use App\Actions\Publishing\AdvancePublishingAttempt;
use App\Actions\Publishing\ApplyEditorialProposal;
use App\Actions\Publishing\ApprovePublishingStage;
use App\Actions\Publishing\FinishEditorialReview;
use App\Actions\Publishing\ManageArticleRelease;
use App\Actions\Publishing\ResumeEditorialActivity;
use App\Actions\Publishing\StartEditorialActivity;
use App\Actions\Publishing\WriteArticle;
use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\EditorialActivity;
use App\Models\EditorialFinding;
use App\Models\EvidenceSource;
use App\Models\Publishing\ApprovalKind;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
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

    /** @var array<int, string> */
    public array $agentAnswers = [];
    public string $selectedAngleOptionKey = '';
    public string $releaseSlug = '';
    public string $releaseScheduledAt = '';
    public string $releaseScheduledTimezone = '';

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
        $this->releaseSlug = (string) $article->slug;
        $this->releaseScheduledTimezone = (string) config('app.timezone', 'UTC');
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

    public function prepareRelease(?ManageArticleRelease $releases = null): void
    {
        Gate::authorize('publish', $this->article);
        $releases ??= app(ManageArticleRelease::class);
        $attempt = $this->article->currentAttempt;

        if ($attempt === null || $this->currentRevisionId === null) {
            $this->saveError = 'No active publishing attempt and current revision exist.';
            return;
        }

        try {
            $this->saveError = null;
            $schedule = trim($this->releaseScheduledAt) === ''
                ? ['scheduled_at' => null, 'delivery_intent' => ['channel' => 'manual']]
                : $releases->resolveSchedule($this->releaseScheduledAt, $this->releaseScheduledTimezone);
            $releases->prepare(auth()->user(), $attempt, $this->currentRevisionId, $this->releaseSlug !== '' ? $this->releaseSlug : $this->article->slug, $schedule['scheduled_at'], $schedule['delivery_intent']);
            $this->article = $this->article->fresh(['workingRevision', 'currentAttempt.approvals', 'currentAttempt.releases', 'publishedRelease']);
            $this->refreshApprovalInputs();
            session()->flash('status', $schedule['scheduled_at'] === null ? 'Release package prepared.' : 'Scheduled release package prepared.');
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

    public function deliverRelease(?ManageArticleRelease $releases = null): void
    {
        Gate::authorize('publish', $this->article);
        $releases ??= app(ManageArticleRelease::class);
        $release = $this->currentReleasePackage();

        if ($release === null) {
            $this->saveError = 'No approved release package is available to deliver.';
            return;
        }

        try {
            $this->saveError = null;
            $releases->deliver(auth()->user(), $release, $this->article->published_release_id);
            $this->article = $this->article->fresh(['workingRevision', 'currentAttempt.approvals', 'currentAttempt.releases', 'publishedRelease']);
            $this->refreshApprovalInputs();
            session()->flash('status', 'Release delivered to the public reader.');
        } catch (Throwable $exception) {
            $this->saveError = $exception->getMessage();
        }
    }

    public function withdrawRelease(?ManageArticleRelease $releases = null): void
    {
        Gate::authorize('publish', $this->article);
        $attempt = $this->article->currentAttempt;

        if ($attempt === null) {
            $this->saveError = 'No active publishing attempt exists.';
            return;
        }

        try {
            $this->saveError = null;
            ($releases ?? app(ManageArticleRelease::class))->withdrawScheduledReleasesForAttemptId((int) $attempt->id);
            $this->article = $this->article->fresh(['workingRevision', 'currentAttempt.approvals', 'currentAttempt.releases', 'publishedRelease']);
            $this->refreshApprovalInputs();
            session()->flash('status', 'Scheduled release withdrawn.');
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

    public function refreshAgentWork(): void
    {
        Gate::authorize('view', $this->article);
        $this->article->refresh()->load(['workingRevision', 'currentAttempt.approvals', 'currentAttempt.releases', 'publishedRelease']);
        $this->refreshApprovalInputs();
    }

    public function answerAgent(int $activityId, string $expectedHash, bool $reject = false): void
    {
        Gate::authorize('develop', $this->article);
        $activity = EditorialActivity::query()->where('article_id', $this->article->id)->findOrFail($activityId);

        try {
            app(ResumeEditorialActivity::class)->handle(auth()->user(), $activity, $expectedHash, $reject ? null : ($this->agentAnswers[$activityId] ?? ''));
            unset($this->agentAnswers[$activityId]);
            $this->saveError = null;
            $this->refreshAgentWork();
            session()->flash('status', $reject ? 'Agent request declined.' : 'Your answers were queued for the agent.');
        } catch (Throwable $exception) {
            $this->saveError = $exception->getMessage();
        }
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
        Gate::authorize('view', $this->article);
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
            'pendingAgentRequests' => $attempt === null ? collect() : $attempt->editorialActivities()
                ->where('status', EditorialActivityStatus::AwaitingApproval)
                ->where('initiating_user_id', auth()->id())
                ->where('stage', $attempt->stage->value)
                ->where('review_cycle', $attempt->review_cycle)
                ->where('revision_id', $this->article->working_revision_id)
                ->oldest('id')->get(),
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
        <div class="min-w-0">
            <flux:text>Article workspace</flux:text>
            <flux:heading level="1" size="xl" class="mt-1 break-words">{{ $article->idea }}</flux:heading>
        </div>
        <x-admin.publishing.partials.save-badge :state="$saveState" />
    </div>

    @if ($conflictMessage)
        <x-admin.publishing.partials.conflict-banner :message="$conflictMessage" :latest="$conflictLatestRevision" />
    @endif
    @if ($saveError)
        <flux:callout variant="danger" heading="Workspace error" text="{{ $saveError }}" />
    @endif

    <div class="space-y-4" @if ($agentActivities->contains(fn ($activity) => in_array($activity->status, [EditorialActivityStatus::Pending, EditorialActivityStatus::Running], true))) wire:poll.5s.visible="refreshAgentWork" @endif>
        @foreach ($pendingAgentRequests as $request)
            <flux:callout wire:key="agent-request-{{ $request->id }}" heading="The agent needs your input" data-agent-approval="{{ $request->id }}">
                <flux:callout.text>The interview is paused. Answer these questions to resume the same conversation, or decline to stop it. This does not approve an angle, plan, or release.</flux:callout.text>
                <ol class="my-4 list-decimal space-y-2 pl-5">
                    @foreach ($request->pending_tool_approvals ?? [] as $approval)
                        @foreach ($approval['arguments']['questions'] ?? [] as $question)
                            <li>{{ $question }}</li>
                        @endforeach
                    @endforeach
                </ol>
                @can('develop', $article)
                    <flux:textarea wire:model="agentAnswers.{{ $request->id }}" label="Your answers" maxlength="4000" rows="3" />
                    <div class="mt-4 flex flex-wrap gap-2">
                        <flux:button variant="primary" wire:click="answerAgent({{ $request->id }}, '{{ $request->pendingApprovalHash() }}')">Send answers and continue</flux:button>
                        <flux:button wire:click="answerAgent({{ $request->id }}, '{{ $request->pendingApprovalHash() }}', true)">Decline request</flux:button>
                    </div>
                @endcan
            </flux:callout>
        @endforeach
        <flux:button size="sm" icon="arrow-path" wire:click="refreshAgentWork">Refresh agent work</flux:button>
    </div>

    <div class="grid min-w-0 gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <flux:tab.group class="min-w-0">
            <flux:tabs scrollable>
                <flux:tab name="brief" selected>Brief & plan</flux:tab>
                <flux:tab name="manuscript">Manuscript</flux:tab>
                <flux:tab name="reviews">Reviews</flux:tab>
            </flux:tabs>

            <flux:tab.panel name="brief" selected>
            <section class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-white/10 dark:bg-white/5">
                <flux:heading size="lg">Brief, angle, and plan</flux:heading>
                <dl class="mt-4 grid gap-3 text-sm text-zinc-700 dark:text-zinc-300">
                    <div><dt class="font-medium text-zinc-900 dark:text-zinc-100">Stage</dt><dd>{{ $attempt?->stage?->value ?? 'Idea saved for later' }}</dd></div>
                    <div><dt class="font-medium text-zinc-900 dark:text-zinc-100">Agent budget</dt><dd>{{ $agentBudget ? number_format($agentBudget['available'] / 1_000_000_000, 3) : '0.000' }} USD available</dd></div>
                    <div><dt class="font-medium text-zinc-900 dark:text-zinc-100">Latest activity</dt><dd>{{ $agentActivities->first()?->kind?->value ?? 'Ready for an agent activity' }} {{ $agentActivities->first()?->status?->value ? '· '.$agentActivities->first()?->status?->value : '' }}</dd></div>
                </dl>
                @php($displayBrief = is_array($attempt?->brief) ? $attempt->brief : [])
                @php($displayAngle = is_array($attempt?->angle) ? $attempt->angle : [])
                @php($interviewQuestions = is_array($interviewContext['questions'] ?? null) ? $interviewContext['questions'] : [])
                @php($angleOptions = is_array($interviewContext['angle_options'] ?? null) ? $interviewContext['angle_options'] : [])
                @if ($interviewQuestions !== [] || $displayBrief !== [] || $displayAngle !== [])
                    <div class="mt-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-800 dark:border-white/10 dark:bg-zinc-950/60 dark:text-zinc-200" data-interview-and-angle>
                        <h3 class="font-medium text-zinc-900 dark:text-zinc-100">Interview and angle for approval</h3>
                        @if ($interviewQuestions !== [])
                            <div class="mt-3">
                                <p class="font-medium text-zinc-900 dark:text-zinc-100">Latest interview questions</p>
                                <ol class="mt-2 list-decimal space-y-1 pl-5 text-zinc-700 dark:text-zinc-300">
                                    @foreach ($interviewQuestions as $question)
                                        <li>{{ is_array($question) ? ($question['question'] ?? $question['text'] ?? json_encode($question)) : $question }}</li>
                                    @endforeach
                                </ol>
                            </div>
                        @endif
                        @if ($displayBrief !== [])
                            <div class="mt-3">
                                <p class="font-medium text-zinc-900 dark:text-zinc-100">Brief to approve</p>
                                <pre class="mt-1 whitespace-pre-wrap text-xs text-zinc-700 dark:text-zinc-300">{{ json_encode($displayBrief, JSON_PRETTY_PRINT) }}</pre>
                            </div>
                        @endif
                        @if ($angleOptions !== [])
                            <div class="mt-3 space-y-2">
                                <p class="font-medium text-zinc-900 dark:text-zinc-100">Angle options</p>
                                @foreach ($angleOptions as $key => $option)
                                    <flux:button type="button" wire:click="selectAngleOption(@js((string) $key))" class="h-auto w-full justify-start whitespace-normal py-3 {{ (string) ($interviewContext['selected_angle_option'] ?? '') === (string) $key ? 'ring-2 ring-sky-500' : '' }}">
                                        {{ is_array($option) ? ($option['title'] ?? $option['thesis'] ?? json_encode($option)) : $option }}
                                    </flux:button>
                                @endforeach
                            </div>
                        @endif
                        @if ($displayAngle !== [])
                            <div class="mt-3">
                                <p class="font-medium text-zinc-900 dark:text-zinc-100">Selected angle</p>
                                <pre class="mt-1 whitespace-pre-wrap text-xs text-zinc-700 dark:text-zinc-300">{{ json_encode($displayAngle, JSON_PRETTY_PRINT) }}</pre>
                            </div>
                        @endif
                    </div>
                @endif
                @php($displayPlan = is_array($attempt?->plan) ? $attempt->plan : [])
                @if ($displayPlan !== [])
                    <div class="mt-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-800 dark:border-white/10 dark:bg-zinc-950/60 dark:text-zinc-200" data-generated-plan-digest>
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="font-medium text-zinc-900 dark:text-zinc-100">Plan digest for approval</h3>
                            @if (($displayPlan['source'] ?? null) === 'agent')
                                <span class="rounded bg-sky-50 px-2 py-1 text-xs text-sky-700 dark:bg-sky-400/10 dark:text-sky-100">Generated by agent</span>
                            @endif
                        </div>
                        @if (isset($displayPlan['argument']))
                            <p class="mt-3 text-zinc-700 dark:text-zinc-300">{{ is_string($displayPlan['argument']) ? $displayPlan['argument'] : json_encode($displayPlan['argument']) }}</p>
                        @endif
                        <div class="mt-3 grid gap-3 md:grid-cols-2">
                            <div>
                                <p class="font-medium text-zinc-900 dark:text-zinc-100">Outline</p>
                                <pre class="mt-1 whitespace-pre-wrap text-xs text-zinc-700 dark:text-zinc-300">{{ json_encode($displayPlan['outline'] ?? [], JSON_PRETTY_PRINT) }}</pre>
                            </div>
                            <div>
                                <p class="font-medium text-zinc-900 dark:text-zinc-100">Visual plan</p>
                                <pre class="mt-1 whitespace-pre-wrap text-xs text-zinc-700 dark:text-zinc-300">{{ json_encode($displayPlan['visualPlan'] ?? [], JSON_PRETTY_PRINT) }}</pre>
                            </div>
                        </div>
                    </div>
                @endif
                <div class="mt-4 flex flex-wrap gap-3">
                    @can(PublishingPermission::Develop->value)
                        <flux:button wire:click="startInterview">Start interview</flux:button>
                        <flux:button wire:click="submitInterviewAnswers">Save interview answers</flux:button>
                        <flux:button wire:click="startResearch">Research sources</flux:button>
                    @endcan
                    @can(PublishingPermission::Approve->value)
                        <flux:button wire:click="approveAngle('{{ $angleInputHash }}')">Approve angle</flux:button>
                        <flux:button wire:click="approvePlan('{{ $planInputHash }}')">Approve plan</flux:button>
                    @endcan
                </div>
                <div class="mt-4 grid min-w-0 gap-3 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>Source URL</flux:label>
                        <flux:input wire:model="sourceUrl" placeholder="https://example.com/source" />
                        <flux:error name="sourceUrl" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Interview answers</flux:label>
                        <flux:textarea wire:model="interviewAnswers" rows="3" placeholder="Answer the latest interview questions before angle approval" />
                        <flux:error name="interviewAnswers" />
                    </flux:field>
                    <div class="text-sm text-zinc-700 dark:text-zinc-300 sm:col-span-2">
                        <p class="font-medium text-zinc-900 dark:text-zinc-100">Voice samples from published archive</p>
                        <div class="mt-2 grid gap-2">
                            @forelse ($voiceSampleOptions as $sampleArticle)
                                <flux:checkbox wire:model="selectedVoiceSampleArticleIds" value="{{ $sampleArticle->id }}" label="{{ $sampleArticle->publishedRelease?->payload['metadata']['title'] ?? $sampleArticle->idea ?? 'Published article #'.$sampleArticle->id }}" />
                            @empty
                                <p class="text-zinc-600 dark:text-zinc-400">Publish or import an archive article before selecting voice samples.</p>
                            @endforelse
                        </div>
                    </div>
                    <div class="text-sm text-zinc-700 dark:text-zinc-300 sm:col-span-2">
                        <p class="font-medium text-zinc-900 dark:text-zinc-100">Retained owner sources</p>
                        <div class="mt-2 grid gap-2">
                            @forelse ($evidenceSources->whereIn('source_type', ['owner', 'restricted']) as $source)
                                <flux:checkbox wire:model="selectedEvidenceSourceIds" value="{{ $source->id }}" label="{{ ($source->title ?? $source->url ?? 'Source #'.$source->id).($source->unresolved_reason ? ' — '.$source->unresolved_reason : '') }}" />
                            @empty
                                <p class="text-zinc-600 dark:text-zinc-400">No retained owner sources are available for selection.</p>
                            @endforelse
                        </div>
                    </div>
                    <flux:field class="sm:col-span-2">
                        <flux:label>Voice sample notes</flux:label>
                        <flux:input wire:model="voiceSample" placeholder="Select published archive samples above for new dispatches; pasted IDs/excerpts are rejected" />
                        <flux:error name="voiceSample" />
                    </flux:field>
                </div>
            </section>
            </flux:tab.panel>

            <flux:tab.panel name="manuscript">
            <section class="admin-editor rounded-xl border border-zinc-200 bg-white p-6 dark:border-white/10 dark:bg-white/5" data-admin-editor-shell>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <flux:heading size="lg">Manuscript</flux:heading>
                        <flux:text class="mt-1">Protected passages are stored in the canonical JSON as block attributes and require proposal review before replacement.</flux:text>
                    </div>
                    @can(PublishingPermission::Write->value)
                        <flux:button wire:click="protectFirstBlock">Protect first passage</flux:button>
                    @endcan
                </div>
                @can(PublishingPermission::Write->value)
                    <div class="mt-4 flex flex-wrap gap-2" aria-label="Semantic manuscript blocks">
                        <flux:button type="button" size="xs" data-editor-command="note">Insert note</flux:button>
                        <flux:button type="button" size="xs" data-editor-command="callout">Insert callout</flux:button>
                        <flux:button type="button" size="xs" data-editor-command="chart">Insert chart</flux:button>
                        <flux:button type="button" size="xs" data-editor-command="diagram">Insert diagram</flux:button>
                        <flux:button type="button" size="xs" color="amber" data-editor-command="protect">Protect selection</flux:button>
                        <flux:button type="button" size="xs" data-editor-command="unprotect">Unprotect selection</flux:button>
                    </div>
                @endcan
                <div wire:ignore class="mt-4">
                    <flux:editor
                        class="min-h-64 rounded border border-zinc-200 bg-white dark:border-white/10 dark:bg-zinc-950"
                        data-admin-editor
                        data-article-id="{{ $article->id }}"
                        data-user-id="{{ auth()->id() }}"
                        data-current-revision="{{ $currentRevisionId }}"
                        :data-document="json_encode($document)"
                        :data-metadata="json_encode($metadata)"
                        toolbar="heading | bold italic strike | bullet ordered blockquote | link"
                    />
                </div>
                <flux:text class="mt-3 text-xs">Autosaves canonical JSON with expected revision and tab-scoped recovery. Conflicts keep local text intact.</flux:text>
            </section>
            </flux:tab.panel>

            <flux:tab.panel name="reviews">
            <section class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-white/10 dark:bg-white/5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <flux:heading size="lg">Reviews and proposals</flux:heading>
                        <flux:text class="mt-2">Agent findings are anchored to one revision. Human decisions apply proposals; protected prose is never overwritten automatically.</flux:text>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @can(PublishingPermission::Develop->value)
                            <flux:button wire:click="startReviews">Start three-lens review</flux:button>
                        @endcan
                        @can(PublishingPermission::Approve->value)
                            <flux:button wire:click="finishReview">Finish review</flux:button>
                            <flux:button wire:click="restartReview">Restart cycle</flux:button>
                        @endcan
                    </div>
                </div>

                <div class="mt-5 grid gap-4 lg:grid-cols-2">
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-white/10">
                        <h3 class="font-medium">Activity log</h3>
                        <ul class="mt-3 space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
                            @forelse ($agentActivities as $activity)
                                <li class="rounded bg-zinc-50 p-3 dark:bg-zinc-950">{{ $activity->kind->value }} · {{ $activity->status->value }} @if($activity->pause_reason)<span class="text-amber-700 dark:text-amber-200">— {{ $activity->pause_reason }}</span>@endif</li>
                            @empty
                                <li class="text-zinc-600 dark:text-zinc-400">No agent activity has been started for this attempt.</li>
                            @endforelse
                        </ul>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-white/10">
                        <h3 class="font-medium">Evidence sources</h3>
                        <ul class="mt-3 space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
                            @forelse ($evidenceSources as $source)
                                <li class="rounded bg-zinc-50 p-3 dark:bg-zinc-950">{{ $source->title ?? $source->url ?? 'Source' }} @if($source->unresolved_reason)<span class="text-amber-700 dark:text-amber-200">— {{ $source->unresolved_reason }}</span>@endif</li>
                            @empty
                                <li class="text-zinc-600 dark:text-zinc-400">No evidence sources recorded yet.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>

                <div class="mt-5 rounded-lg border border-zinc-200 p-4 dark:border-white/10">
                    <h3 class="font-medium">Findings and proposals</h3>
                    <ul class="mt-3 space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
                        @forelse ($editorialFindings as $finding)
                            <li class="rounded bg-zinc-50 p-3 dark:bg-zinc-950">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div><span class="text-xs uppercase text-zinc-500">{{ $finding->lens }} · {{ $finding->severity }}</span><p class="mt-1">{{ $finding->statement }}</p></div>
                                    @if($finding->proposed_patch && !$finding->disposition)
                                        @can(PublishingPermission::Write->value)
                                            <flux:button size="xs" wire:click="applyProposal({{ $finding->id }})">Accept patch</flux:button>
                                        @endcan
                                    @endif
                                    @if(!$finding->disposition)
                                        @can(PublishingPermission::Approve->value)
                                            <flux:button size="xs" wire:click="decideFinding({{ $finding->id }}, 'rejected')">Reject advice</flux:button>
                                            <flux:button size="xs" wire:click="decideFinding({{ $finding->id }}, 'false_positive')">False positive</flux:button>
                                        @endcan
                                    @endif
                                </div>
                                @if(is_array($finding->supporting_quotations) && count($finding->supporting_quotations) > 0)
                                    <div class="mt-2 space-y-1 text-xs text-zinc-600 dark:text-zinc-400">
                                        <p class="font-medium text-zinc-700 dark:text-zinc-300">Supporting quotations</p>
                                        @foreach($finding->supporting_quotations as $quotation)
                                            @if(is_array($quotation))
                                                <blockquote class="border-l border-zinc-300 pl-2 dark:border-white/15">Source #{{ $quotation['source_id'] ?? 'unknown' }}: “{{ $quotation['quote'] ?? '' }}”</blockquote>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                                @if($finding->disposition)<p class="mt-2 text-xs text-zinc-500">Disposition: {{ $finding->disposition }}</p>@endif
                            </li>
                        @empty
                            <li class="text-zinc-600 dark:text-zinc-400">No findings have been recorded for this revision.</li>
                        @endforelse
                    </ul>
                </div>

                <div class="mt-5 rounded-lg border border-zinc-200 p-4 dark:border-white/10">
                    <h3 class="font-medium">Budget top-up</h3>
                    <div class="mt-3 grid gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
                        <flux:input wire:model="budgetTopUpNanoUsd" type="number" min="1" aria-label="Nano USD top-up amount" />
                        <flux:input wire:model="budgetMutationKey" aria-label="Budget mutation key" placeholder="mutation key" />
                        @can(PublishingPermission::Budget->value)
                            <flux:button wire:click="topUpBudget">Top up budget</flux:button>
                        @endcan
                    </div>
                </div>

                <div class="mt-5 rounded-lg border border-zinc-200 p-4 dark:border-white/10">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="font-medium">Protected passage controls</h3>
                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">These controls read and update the canonical document JSON, not rendered HTML.</p>
                        </div>
                        @can(PublishingPermission::Write->value)
                            <flux:button wire:click="protectFirstBlock">Mark first block protected</flux:button>
                        @endcan
                    </div>

                    @if ($protectedBlocks === [])
                        <p class="mt-4 text-sm text-zinc-600 dark:text-zinc-400">No protected passages are marked in this revision.</p>
                    @else
                        <ul class="mt-4 space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
                            @foreach ($protectedBlocks as $block)
                                <li class="flex flex-wrap items-center justify-between gap-3 rounded border border-zinc-200 p-3 dark:border-white/10">
                                    <span>{{ $block['type'] }} <code class="text-xs text-zinc-500">{{ $block['id'] }}</code></span>
                                    @can(PublishingPermission::Write->value)
                                        <flux:button size="xs" wire:click="unprotectBlock('{{ $block['id'] }}')">Remove protection</flux:button>
                                    @endcan
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>
            </flux:tab.panel>
        </flux:tab.group>

        <aside class="space-y-6">
            <section id="preview" class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-white/10 dark:bg-white/5">
                <flux:heading size="lg">Preview</flux:heading>
                @if ($previewUrl)
                    <iframe class="mt-4 h-96 w-full rounded bg-white" src="{{ $previewUrl }}" sandbox="allow-same-origin" title="Article preview"></iframe>
                @else
                    <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">Save a revision before previewing.</p>
                @endif
            </section>
            <x-admin.publishing.partials.release-checklist :article="$article" :attempt="$attempt" :release="$release" />
        </aside>
    </div>
</section>
