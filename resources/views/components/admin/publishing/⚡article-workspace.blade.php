<?php

use App\Actions\Publishing\AcceptPackageProposal;
use App\Actions\Publishing\AdvancePublishingAttempt;
use App\Actions\Publishing\ApplyEditorialProposal;
use App\Actions\Publishing\ApprovePublishingStage;
use App\Actions\Publishing\FinishEditorialReview;
use App\Actions\Publishing\LintRevision;
use App\Actions\Publishing\ManageArticleRelease;
use App\Actions\Publishing\ResumeEditorialActivity;
use App\Actions\Publishing\RetryEditorialActivity;
use App\Actions\Publishing\StartEditorialActivity;
use App\Actions\Publishing\WriteArticle;
use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\ContentIdea;
use App\Models\EditorialActivity;
use App\Models\EditorialFinding;
use App\Models\EvidenceSource;
use App\Models\Publishing\ApprovalKind;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\Publishing\EditorialStage;
use App\Models\PublishingAttempt;
use App\Models\Publishing\PieceKind;
use App\Services\Publishing\EditorialOutput;
use App\Services\Publishing\FrameworkCatalog;
use App\Services\Publishing\Lint\LintHit;
use App\Services\Publishing\Lint\LintTarget;
use App\Services\Publishing\Lint\VoiceLinter;
use App\Services\Publishing\VoiceCanon;
use App\Settings\PublishingAgentSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
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
    public string $sourceUrl = '';
    public string $interviewAnswers = '';
    public string $ideaTitle = '';

    /** @var array<int, string> */
    public array $agentAnswers = [];
    public string $selectedAngleOptionKey = '';
    public string $releaseSlug = '';
    public string $releaseScheduledAt = '';
    public string $releaseScheduledTimezone = '';
    public string $postType = '';
    public string $frameworkChoice = '';

    /** @var array{title: string, seo_title: string, description: string, slug: string, greatest_hits_tag: string} */
    public array $package = ['title' => '', 'seo_title' => '', 'description' => '', 'slug' => '', 'greatest_hits_tag' => ''];

    /** @var list<int> */
    public array $selectedEvidenceSourceIds = [];

    /** @var array<int, array{disposition: string, reason?: string}> */
    public array $findingDispositions = [];

    /** @var array<int, string> */
    public array $findingReasons = [];

    /** @var array{title: string, description: string, date: string} */
    public array $details = ['title' => '', 'description' => '', 'date' => ''];

    /** @var array<string, mixed> */
    public array $document = ['version' => 1, 'type' => 'doc', 'content' => []];

    /** @var array<string, mixed> */
    public array $metadata = [];

    public function mount(Article $article): void
    {
        Gate::authorize('view', $article);
        $this->article = $article->load(['contentIdea', 'workingRevision', 'currentAttempt.approvals', 'currentAttempt.releases', 'publishedRelease']);
        $this->ideaTitle = $article->contentIdea->summary_title;
        $this->currentRevisionId = $article->working_revision_id;
        $this->releaseSlug = (string) ($article->workingRevision?->metadata['slug'] ?? $article->slug);
        $this->releaseScheduledTimezone = (string) (auth()->user()?->timezone ?? config('app.timezone', 'UTC'));
        $revision = $article->workingRevision;
        if ($revision instanceof ArticleRevision) {
            $this->document = $revision->document ?? $this->document;
            $this->metadata = $revision->metadata ?? [];
        }
        $this->details = ['title' => (string) ($this->metadata['title'] ?? ''), 'description' => (string) ($this->metadata['description'] ?? ''), 'date' => (string) ($this->metadata['date'] ?? $article->first_published_at?->toDateString() ?? now()->toDateString())];
        $this->hydrateAgentSelectionsFromAttempt();
        $this->hydratePackageFromLatestProposal();
        $this->refreshApprovalInputs();
    }

    public function startDevelopment(): void
    {
        Gate::authorize('develop', $this->article);

        try {
            DB::transaction(function (): void {
                $attempt = app(AdvancePublishingAttempt::class)->develop(auth()->user(), $this->article, $this->currentRevisionId, ['idea' => $this->article->contentIdea->body]);
                app(StartEditorialActivity::class)->start(auth()->user(), $attempt, EditorialActivityKind::Interview, [], 'interview-'.$attempt->review_cycle);
            });
            $this->saveError = null;
            $this->refreshAgentWork();
        } catch (Throwable $exception) {
            $this->saveError = $exception->getMessage();
        }
    }

    public function saveDetails(): void
    {
        Gate::authorize('update', $this->article);
        $validated = $this->validate([
            'details.title' => ['required', 'string', 'max:240'],
            'details.description' => ['nullable', 'string', 'max:1000'],
            'details.date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);
        $baseRevisionId = $this->currentRevisionId;
        $result = $this->saveDocument($baseRevisionId, 'details-'.Str::uuid(), $this->document, array_replace($this->metadata, $validated['details']));

        if ($result['ok']) {
            $this->notifyEditor($baseRevisionId);
        }
    }

    public function renameIdea(WriteArticle $articles): void
    {
        Gate::authorize('update', $this->article);
        $validated = $this->validate(['ideaTitle' => ['required', 'string', 'max:'.ContentIdea::TITLE_LIMIT]]);
        $articles->renameIdea(auth()->user(), $this->article->contentIdea, $validated['ideaTitle']);
        $this->ideaTitle = $this->article->contentIdea->summary_title;
        $this->dispatch('idea-renamed');
    }

    #[On('publishing-editor-conflict')]
    public function reportEditorConflict(int $articleId): void
    {
        Gate::authorize('view', $this->article);
        abort_unless($articleId === (int) $this->article->id, 404);
        $this->saveState = 'conflict';
        $this->conflictMessage = 'The saved manuscript changed while this tab had local edits. Your local text is intact. Copy it before loading the saved version.';
        $this->conflictLatestRevision = $this->latestSavedRevisionPayload();
    }

    private function notifyEditor(?int $baseRevisionId): void
    {
        $this->dispatch('publishing-document-updated', articleId: (int) $this->article->id, baseRevisionId: $baseRevisionId, revisionId: $this->currentRevisionId, document: $this->document, metadata: $this->metadata);
    }

    private function hydrateAgentSelectionsFromAttempt(): void
    {
        $attempt = $this->article->currentAttempt;
        $context = $attempt instanceof PublishingAttempt && is_array($attempt->interview_context) ? $attempt->interview_context : [];
        $this->selectedEvidenceSourceIds = is_array($context['selected_evidence_source_ids'] ?? null) ? array_values(array_map('intval', $context['selected_evidence_source_ids'])) : [];
        $selectedAngle = $context['selected_angle_option'] ?? '';
        $this->selectedAngleOptionKey = is_scalar($selectedAngle) ? (string) $selectedAngle : '';
        $this->hydrateStructureChoices();
    }

    /** The post type and framework the attempt holds now, for example after an interview or a plan finished. */
    private function hydrateStructureChoices(): void
    {
        $attempt = $this->article->currentAttempt;
        $postType = $attempt instanceof PublishingAttempt ? data_get($attempt->brief, 'post_type') : null;
        $this->postType = is_string($postType) ? $postType : '';
        $framework = $attempt instanceof PublishingAttempt ? data_get($attempt->plan, 'framework') : null;
        $choice = is_array($framework) ? ($framework['chosen'] ?? $framework['primary'] ?? null) : null;
        $this->frameworkChoice = is_string($choice) ? $choice : '';
    }

    /** The package panel starts from the latest completed package of this review cycle. */
    private function hydratePackageFromLatestProposal(): void
    {
        $proposal = $this->latestPackage()?->proposal;
        if (! is_array($proposal)) {
            return;
        }

        foreach (array_keys($this->package) as $field) {
            $this->package[$field] = is_string($proposal[$field] ?? null) ? $proposal[$field] : '';
        }
    }

    private function latestPackage(): ?EditorialActivity
    {
        $attempt = $this->article->currentAttempt;

        return $attempt === null ? null : $attempt->editorialActivities()
            ->where('review_cycle', $attempt->review_cycle)
            ->where('kind', EditorialActivityKind::Package->value)
            ->where('status', EditorialActivityStatus::Completed->value)
            ->latest('id')
            ->first();
    }

    public function choosePostType(?AdvancePublishingAttempt $advance = null): void
    {
        Gate::authorize('develop', $this->article);
        $attempt = $this->article->currentAttempt;
        if ($attempt === null) {
            $this->saveError = 'No active publishing attempt exists.';
            return;
        }

        try {
            $this->saveError = null;
            ($advance ?? app(AdvancePublishingAttempt::class))->choosePostType(auth()->user(), $attempt, $this->postType);
            $this->article = $this->article->fresh(['contentIdea', 'workingRevision', 'currentAttempt.approvals', 'currentAttempt.releases', 'publishedRelease']);
            $this->refreshApprovalInputs();
            session()->flash('status', 'Post type saved.');
        } catch (Throwable $exception) {
            $this->saveError = $exception->getMessage();
        }
    }

    public function chooseFramework(string $slug = '', ?AdvancePublishingAttempt $advance = null): void
    {
        Gate::authorize('develop', $this->article);
        $attempt = $this->article->currentAttempt;
        if ($attempt === null) {
            $this->saveError = 'No active publishing attempt exists.';
            return;
        }

        $slug = $slug !== '' ? $slug : $this->frameworkChoice;

        try {
            $this->saveError = null;
            $replan = ($advance ?? app(AdvancePublishingAttempt::class))->chooseFramework(auth()->user(), $attempt, $slug);
            $this->frameworkChoice = $slug;
            $this->article = $this->article->fresh(['contentIdea', 'workingRevision', 'currentAttempt.approvals', 'currentAttempt.releases', 'currentAttempt.editorialActivities', 'publishedRelease']);
            $this->refreshApprovalInputs();
            session()->flash('status', $replan === null ? 'Framework chosen. The plan is ready for your approval.' : 'Re-planning with the framework you chose.');
        } catch (Throwable $exception) {
            $this->saveError = $exception->getMessage();
        }
    }

    public function acceptPackageProposal(int $activityId, ?AcceptPackageProposal $packages = null): void
    {
        Gate::authorize('approve', $this->article);
        Gate::authorize('update', $this->article);
        $limits = AcceptPackageProposal::LIMITS;
        $validated = $this->validate([
            'package.title' => ['required', 'string', 'max:'.$limits['title']],
            'package.seo_title' => ['required', 'string', 'max:'.$limits['seo_title']],
            'package.description' => ['required', 'string', 'max:'.$limits['description']],
            'package.slug' => ['required', 'string', 'max:'.$limits['slug']],
            'package.greatest_hits_tag' => ['required', 'string', 'max:'.$limits['greatest_hits_tag']],
        ]);
        $package = EditorialActivity::query()->where('article_id', $this->article->id)->where('kind', EditorialActivityKind::Package->value)->findOrFail($activityId);

        try {
            $this->saveError = null;
            $baseRevisionId = $this->currentRevisionId;
            $revision = ($packages ?? app(AcceptPackageProposal::class))->accept(auth()->user(), $package, $validated['package'], (int) $baseRevisionId);
            $this->currentRevisionId = (int) $revision->id;
            $this->document = $revision->document ?? $this->document;
            $this->metadata = $revision->metadata ?? $this->metadata;
            $this->details['title'] = (string) ($this->metadata['title'] ?? '');
            $this->details['description'] = (string) ($this->metadata['description'] ?? '');
            $this->releaseSlug = (string) ($this->metadata['slug'] ?? $this->releaseSlug);
            $this->article = $this->article->fresh(['contentIdea', 'workingRevision', 'currentAttempt.approvals', 'currentAttempt.releases', 'currentAttempt.editorialActivities', 'publishedRelease']);
            $this->refreshApprovalInputs();
            $this->notifyEditor($baseRevisionId);
            session()->flash('status', 'Titles and search details saved to your article.');
        } catch (Throwable $exception) {
            $this->saveError = $exception->getMessage();
        }
    }

    /**
     * The metadata rules and character rules the package fields break as typed, so the owner sees them before saving.
     *
     * @return list<string>
     */
    private function packageLintMessages(): array
    {
        $target = LintTarget::fromDocument([], [
            'title' => $this->package['title'],
            'seo_title' => $this->package['seo_title'],
            'description' => $this->package['description'],
            'slug' => $this->package['slug'],
        ], PieceKind::BlogPost, (int) $this->article->id);

        return array_map(static fn (LintHit $hit): string => $hit->message, app(VoiceLinter::class)->lint($target));
    }

    /**
     * The frameworks the owner may pick at the plan gate: the plan's primary and runner-up with their reasons first,
     * then every other framework the post type allows.
     *
     * @return list<array{slug: string, name: string, role: string, reason: string|null}>
     */
    private function frameworkOptions(FrameworkCatalog $catalog, ?PublishingAttempt $attempt): array
    {
        $framework = $attempt === null ? null : data_get($attempt->plan, 'framework');
        $postType = $attempt === null ? null : data_get($attempt->brief, 'post_type');
        if (! is_array($framework) || ! is_string($postType)) {
            return [];
        }

        $options = [];
        foreach (['primary' => 'Recommended', 'runner_up' => 'Runner-up'] as $key => $role) {
            $slug = $framework[$key] ?? null;
            if (is_string($slug) && $slug !== '') {
                $reason = data_get($framework, 'reasons.'.$key);
                $options[$slug] = ['slug' => $slug, 'name' => $catalog->frameworkName($slug), 'role' => $role, 'reason' => is_string($reason) ? $reason : null];
            }
        }

        foreach ($catalog->allowedFor($postType) as $slug) {
            $options[$slug] ??= ['slug' => $slug, 'name' => $catalog->frameworkName($slug), 'role' => 'Also allowed', 'reason' => null];
        }

        return array_values($options);
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
            $this->refreshVoiceRuleFindings();
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
            $this->dispatch('publishing-milestone', kind: 'angle', articleId: (int) $this->article->id);
            session()->flash('status', 'Angle approved. Research and planning can begin.');
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
            $this->dispatch('publishing-milestone', kind: 'plan', articleId: (int) $this->article->id);
            session()->flash('status', 'Plan approved. Your draft is next.');
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
            $scheduled = $release->fresh()->status === 'scheduled';
            $this->dispatch('publishing-milestone', kind: $scheduled ? 'scheduled' : 'release', articleId: (int) $this->article->id);
            session()->flash('status', $scheduled ? 'This exact release is scheduled.' : 'This exact release is approved. Publish when you are ready.');
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
            if ($this->article->published_release_id === $release->id && $release->fresh()->published_at !== null) {
                $this->dispatch('publishing-milestone', kind: 'published', articleId: (int) $this->article->id);
            }
            session()->flash('status', 'Your article is published.');
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

    public function refreshAgentWork(): void
    {
        Gate::authorize('view', $this->article);
        $baseRevisionId = $this->currentRevisionId;
        $this->article->refresh()->load(['contentIdea', 'workingRevision', 'currentAttempt.approvals', 'currentAttempt.releases', 'publishedRelease']);
        if ($this->article->working_revision_id !== $baseRevisionId) {
            $previousMetadata = $this->metadata;
            $this->currentRevisionId = $this->article->working_revision_id;
            $this->document = $this->article->workingRevision?->document ?? $this->document;
            $this->metadata = $this->article->workingRevision?->metadata ?? $this->metadata;
            foreach (['title', 'description', 'date'] as $field) {
                $fallback = $field === 'date' ? ($this->article->first_published_at?->toDateString() ?? now()->toDateString()) : '';
                if ($this->details[$field] === (string) ($previousMetadata[$field] ?? $fallback)) {
                    $this->details[$field] = (string) ($this->metadata[$field] ?? $fallback);
                }
            }
            $this->notifyEditor($baseRevisionId);
        }
        $this->hydrateStructureChoices();
        if (array_filter($this->package) === []) {
            $this->hydratePackageFromLatestProposal();
        }
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

    public function retryAgent(int $activityId): void
    {
        Gate::authorize('develop', $this->article);
        $activity = EditorialActivity::query()->where('article_id', $this->article->id)->findOrFail($activityId);

        try {
            app(RetryEditorialActivity::class)->handle(auth()->user(), $activity);
            $this->saveError = null;
            $this->refreshAgentWork();
            session()->flash('status', 'Agent work queued to run again.');
        } catch (Throwable $exception) {
            $this->saveError = $exception->getMessage();
        }
    }

    public function startInterview(?StartEditorialActivity $activities = null): void
    {
        $this->startActivity(EditorialActivityKind::Interview, $activities, [
            'selected_evidence_source_ids' => $this->selectedEvidenceSourceIds(),
        ]);
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
            foreach ([EditorialActivityKind::ReviewFacts, EditorialActivityKind::ReviewVoice, EditorialActivityKind::ReviewBuyer] as $kind) {
                $activities->start(auth()->user(), $attempt, $kind, [], 'review-'.$attempt->review_cycle);
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

        EditorialFinding::query()->where('article_id', $this->article->id)->where('attempt_id', $this->article->current_attempt_id)->findOrFail($findingId);
        $reason = trim($reason !== '' ? $reason : ($this->findingReasons[$findingId] ?? ''));
        if ($disposition === 'false_positive' && $reason === '') {
            $this->addError('findingReasons.'.$findingId, 'Explain why this finding does not apply.');
            return;
        }
        $this->resetErrorBag('findingReasons.'.$findingId);
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
            $baseRevisionId = $this->currentRevisionId;
            $revision = $proposals->apply(auth()->user(), $attempt, [$findingId], $baseRevisionId);
            $this->refreshVoiceRuleFindings();
            $this->currentRevisionId = (int) $revision->id;
            $this->document = $revision->document ?? $this->document;
            $this->metadata = $revision->metadata ?? $this->metadata;
            $this->article = $this->article->fresh(['workingRevision', 'currentAttempt.approvals', 'currentAttempt.releases', 'currentAttempt.editorialActivities', 'publishedRelease']);
            $this->refreshApprovalInputs();
            $this->notifyEditor($baseRevisionId);
            $this->dispatch('publishing-milestone', kind: 'proposal', articleId: (int) $this->article->id);
            session()->flash('status', 'Suggested change applied to your manuscript.');
        } catch (Throwable $exception) {
            $this->saveError = $exception->getMessage();
        }
    }

    public function overrideVoiceRule(int $findingId, ?LintRevision $lint = null): void
    {
        Gate::authorize('approve', $this->article);
        $lint ??= app(LintRevision::class);
        $finding = EditorialFinding::query()
            ->where('article_id', $this->article->id)
            ->where('attempt_id', $this->article->current_attempt_id)
            ->where('lens', LintRevision::LENS)
            ->findOrFail($findingId);
        $reason = trim($this->findingReasons[$findingId] ?? '');
        if ($reason === '') {
            $this->addError('findingReasons.'.$findingId, 'Explain why this voice rule does not apply here.');
            return;
        }

        try {
            $lint->override(auth()->user(), $finding, $reason);
            $this->resetErrorBag('findingReasons.'.$findingId);
            unset($this->findingReasons[$findingId]);
            $this->saveError = null;
            session()->flash('status', 'Voice rule overridden. Your reason is recorded with the release.');
        } catch (Throwable $exception) {
            $this->saveError = $exception->getMessage();
        }
    }

    public function skipVoiceRuleRepair(?LintRevision $lint = null): void
    {
        Gate::authorize('develop', $this->article);
        $lint ??= app(LintRevision::class);
        $attempt = $this->article->currentAttempt;
        if ($attempt === null) {
            $this->saveError = 'No active publishing attempt exists.';
            return;
        }

        try {
            $lint->skipRepair(auth()->user(), $attempt);
            $this->saveError = null;
            $this->article = $this->article->fresh(['workingRevision', 'currentAttempt.approvals', 'currentAttempt.releases', 'currentAttempt.editorialActivities', 'publishedRelease']);
            session()->flash('status', 'Voice-rule repair skipped. Review has started.');
        } catch (Throwable $exception) {
            $this->saveError = $exception->getMessage();
        }
    }

    /**
     * Saves never fail over lint: a problem refreshing voice-rule findings is reported, and release re-lints anyway.
     */
    private function refreshVoiceRuleFindings(): void
    {
        try {
            app(LintRevision::class)->refresh($this->article);
        } catch (Throwable $exception) {
            report($exception);
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
            $baseRevisionId = $this->currentRevisionId;
            $revision = app(WriteArticle::class)->save(
                auth()->user(),
                $this->article,
                $this->currentRevisionId,
                $document,
                $this->metadata,
                'protected-block-'.uniqid('', true),
                'human',
            );
            $this->refreshVoiceRuleFindings();
            $this->currentRevisionId = (int) $revision->id;
            $this->document = $revision->document ?? $document;
            $this->metadata = $revision->metadata ?? $this->metadata;
            $this->article = $this->article->fresh(['workingRevision', 'currentAttempt.approvals', 'currentAttempt.releases', 'publishedRelease']);
            $this->refreshApprovalInputs();
            $this->saveState = 'saved';
            $this->saveError = null;
            $this->notifyEditor($baseRevisionId);
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
    private function documentExcerpt(array $document, ?int $limit = 220): string
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

        return $excerpt === '' ? 'No text in this passage.' : ($limit === null ? $excerpt : str($excerpt)->limit($limit)->toString());
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
        $release ??= $attempt === null || $attempt->stage === EditorialStage::Published ? $this->article->publishedRelease : null;

        $activities = $attempt === null ? collect() : $attempt->editorialActivities()->where('review_cycle', $attempt->review_cycle)->latest()->get();
        $findings = $attempt === null ? collect() : EditorialFinding::query()
            ->where('attempt_id', $attempt->id)
            ->whereNull('stale_at')
            ->where('review_cycle', $attempt->review_cycle)
            ->latest()
            ->get();
        $sources = $attempt === null ? collect() : EvidenceSource::query()
            ->where('attempt_id', $attempt->id)
            ->latest()
            ->get();
        $interviewContext = is_array($attempt?->interview_context) ? $attempt->interview_context : [];
        $stage = $attempt?->stage;
        $currentActivity = $activities->first(fn ($activity) => $activity->stage === $stage?->value && $activity->status !== EditorialActivityStatus::Stale);
        $working = $activities->contains(fn ($activity) => in_array($activity->status, [EditorialActivityStatus::Pending, EditorialActivityStatus::Running], true));
        $angleApproved = $attempt?->approvals->contains(fn ($approval) => $approval->kind === ApprovalKind::Angle && $approval->invalidated_at === null) ?? false;
        $planApproved = $attempt?->approvals->contains(fn ($approval) => $approval->kind === ApprovalKind::Plan && $approval->invalidated_at === null) ?? false;
        $catalog = FrameworkCatalog::fromSnapshot(app(VoiceCanon::class)->snapshot());
        $packageActivity = $this->latestPackage();
        $sessionMode = match ($stage) {
            EditorialStage::InReview => 'write',
            EditorialStage::Approved, EditorialStage::Scheduled, EditorialStage::Published => 'release',
            default => $attempt === null && $this->article->published_release_id !== null ? 'release' : 'develop',
        };

        return [
            'sessionMode' => $sessionMode,
            'articleTitle' => trim((string) ($this->metadata['title'] ?? '')) ?: null,
            'stage' => $stage,
            'working' => $working,
            'currentActivity' => $currentActivity,
            'angleApproved' => $angleApproved,
            'planApproved' => $planApproved,
            'displayBrief' => is_array($attempt?->brief) ? $attempt->brief : [],
            'displayAngle' => is_array($attempt?->angle) ? $attempt->angle : [],
            'displayPlan' => is_array($attempt?->plan) ? $attempt->plan : [],
            'angleOptions' => is_array($interviewContext['angle_options'] ?? null) ? $interviewContext['angle_options'] : [],
            'interviewQuestions' => is_array($interviewContext['questions'] ?? null) ? $interviewContext['questions'] : [],
            'releasePreviewUrl' => $release === null ? null : route('admin.publishing.articles.preview', ['article' => $this->article, 'release' => $release->id]),
            'proposalTexts' => $findings->mapWithKeys(fn ($finding) => [$finding->id => $this->documentExcerpt(['content' => [$finding->proposed_patch['replacement'] ?? []]], null)])->all(),
            'voiceRuleFindings' => $findings->where('lens', LintRevision::LENS)->sortBy('id')->values(),
            'voiceRuleRepairWaiting' => $activities->contains(fn ($activity) => $activity->kind === EditorialActivityKind::LintRepair && in_array($activity->status, [EditorialActivityStatus::Pending, EditorialActivityStatus::Paused, EditorialActivityStatus::Failed], true)),
            'attempt' => $attempt,
            'previewUrl' => $previewUrl,
            'release' => $release,
            'protectedBlocks' => $this->protectedBlocks(),
            'agentsPaused' => app(PublishingAgentSettings::class)->paused,
            'agentActivities' => $activities,
            'pendingAgentRequests' => $attempt === null ? collect() : $attempt->editorialActivities()
                ->where('status', EditorialActivityStatus::AwaitingApproval)
                ->where('initiating_user_id', auth()->id())
                ->where('stage', $attempt->stage->value)
                ->where('review_cycle', $attempt->review_cycle)
                ->where('revision_id', $this->article->working_revision_id)
                ->oldest('id')->get(),
            'editorialFindings' => $findings->where('lens', '!=', LintRevision::LENS)->values(),
            'evidenceSources' => $sources,
            'voiceCanonVersion' => Str::substr(app(VoiceCanon::class)->snapshot()->version, 0, 12),
            'interviewContext' => $interviewContext,
            'postTypeOptions' => collect($catalog->postTypeSlugs())->mapWithKeys(fn (string $slug): array => [$slug => $catalog->postTypeLabel($slug)])->all(),
            'postTypeLabel' => is_string(data_get($attempt?->brief, 'post_type')) ? $catalog->postTypeLabel((string) data_get($attempt?->brief, 'post_type')) : null,
            'frameworkOptions' => $this->frameworkOptions($catalog, $attempt),
            'frameworkChosen' => is_string(data_get($attempt?->plan, 'framework.chosen')) ? $catalog->frameworkName((string) data_get($attempt?->plan, 'framework.chosen')) : null,
            'planning' => $activities->contains(fn ($activity) => $activity->kind === EditorialActivityKind::Plan && in_array($activity->status, [EditorialActivityStatus::Pending, EditorialActivityStatus::Running], true)),
            'packageActivity' => $packageActivity,
            'packageLintMessages' => $packageActivity === null ? [] : $this->packageLintMessages(),
            'packageTitleOptionCount' => EditorialOutput::PACKAGE_TITLE_OPTIONS,
        ];
    }
};
?>

@include('components.admin.publishing.partials.session')
