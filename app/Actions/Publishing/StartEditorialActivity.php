<?php

namespace App\Actions\Publishing;

use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Models\Article;
use App\Models\ArticleRelease;
use App\Models\ArticleRevision;
use App\Models\EditorialActivity;
use App\Models\EditorialApproval;
use App\Models\EditorialFinding;
use App\Models\EvidenceSource;
use App\Models\Publishing\ApprovalKind;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\Publishing\EditorialStage;
use App\Models\Publishing\PieceKind;
use App\Models\PublishingAttempt;
use App\Models\User;
use App\Services\Publishing\CanonSnapshot;
use App\Services\Publishing\EditorialOutput;
use App\Services\Publishing\ExemplarRetriever;
use App\Services\Publishing\FrameworkCatalog;
use App\Services\Publishing\Lint\LintTarget;
use App\Services\Publishing\PublishingFingerprint;
use App\Services\Publishing\VoiceCanon;
use App\Settings\PublishingAgentSettings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StartEditorialActivity
{
    public function __construct(
        private readonly PublishingFingerprint $fingerprint,
        private readonly VoiceCanon $canon,
        private readonly ExemplarRetriever $exemplars,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function start(User $actor, PublishingAttempt|int $attempt, EditorialActivityKind $kind, array $input = [], ?string $batchKey = null): EditorialActivity
    {
        if (! $actor->can(PublishingPermission::Develop->value)) {
            throw new AuthorizationException('This user is not allowed to start publishing agent work.');
        }

        /** @var EditorialActivity $activity */
        $activity = DB::transaction(function () use ($actor, $attempt, $kind, $input, $batchKey): EditorialActivity {
            $lockedAttempt = $this->lockedAttempt($attempt);
            $article = Article::query()->whereKey($lockedAttempt->article_id)->lockForUpdate()->firstOrFail();

            if ((int) ($article->current_attempt_id ?? 0) !== (int) $lockedAttempt->id) {
                throw new RuntimeException('Only the current publishing attempt can start agent work.');
            }

            if ($lockedAttempt->paused_at !== null || $lockedAttempt->parked_at !== null || $lockedAttempt->abandoned_at !== null) {
                throw new RuntimeException('Blocked publishing attempts cannot start agent work.');
            }

            $revision = $article->working_revision_id === null ? null : ArticleRevision::query()->find($article->working_revision_id);
            $stageValue = $this->stageValue($lockedAttempt);
            $this->ensureEligible($lockedAttempt, $article, $kind, $stageValue, $revision, $input);
            $this->persistSelectedEvidenceContext($lockedAttempt, $input);
            $lockedAttempt->refresh();
            $approvalHashes = [
                ApprovalKind::Angle->value => $this->activeApprovalHash($lockedAttempt, ApprovalKind::Angle),
                ApprovalKind::Plan->value => $this->activeApprovalHash($lockedAttempt, ApprovalKind::Plan),
            ];
            $frozenInput = array_merge($input, [
                'article_id' => $article->id,
                'attempt_id' => $lockedAttempt->id,
                'stage' => $stageValue,
                'brief' => $lockedAttempt->brief ?? [],
                'angle' => $lockedAttempt->angle ?? [],
                'plan' => $lockedAttempt->plan ?? [],
                'owner_context' => $this->ownerContextForPrompt($lockedAttempt),
                'approval_hashes' => $approvalHashes,
                'revision_id' => $revision?->id,
                'revision_hash' => $revision?->content_hash,
                'manuscript' => $revision instanceof ArticleRevision ? [
                    'revision_id' => $revision->id,
                    'content_hash' => $revision->content_hash,
                    'document' => $revision->document ?? [],
                    'metadata' => $revision->metadata ?? [],
                ] : null,
                'completed_research' => $this->completedResearchSnapshots($lockedAttempt),
                'evidence_sources' => $this->eligibleEvidenceSources($lockedAttempt),
                'canon' => $this->frozenCanon($lockedAttempt, $article, $kind, $input),
            ], $this->reviewContext($lockedAttempt, $article, $kind, $revision, $input), $this->pieceContext($lockedAttempt, $article, $kind));
            $promptHash = $this->fingerprint->hash([
                'version' => EditorialOutput::VERSION,
                'kind' => $kind->value,
                'input' => $frozenInput,
            ]);
            $idempotencyKey = $this->fingerprint->hash($this->idempotencyPayload($lockedAttempt, $kind, $revision, $batchKey, $promptHash));

            $existing = EditorialActivity::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing instanceof EditorialActivity) {
                return $existing;
            }

            return EditorialActivity::create([
                'article_id' => $article->id,
                'attempt_id' => $lockedAttempt->id,
                'initiating_user_id' => $actor->id,
                'kind' => $kind,
                'status' => EditorialActivityStatus::Pending,
                'stage' => $stageValue,
                'input_version' => $lockedAttempt->input_version,
                'revision_id' => $revision?->id,
                'revision_hash' => $revision?->content_hash,
                'review_cycle' => (int) $lockedAttempt->review_cycle,
                'batch_key' => $batchKey,
                'idempotency_key' => $idempotencyKey,
                'prompt_version' => EditorialOutput::VERSION,
                'prompt_hash' => $promptHash,
                'input' => $frozenInput,
                'model_snapshot' => [],
                'run_count' => 0,
                'available_at' => now(),
            ]);
        });

        if (! app(PublishingAgentSettings::class)->paused) {
            RunEditorialActivity::dispatch((int) $activity->id)->afterCommit();
        }

        return $activity;
    }

    /** One package per review cycle and revision; the one after a recheck gets its own key, so it never reuses an earlier package. */
    public static function packageBatchKey(PublishingAttempt $attempt, int $revisionId, bool $afterRecheck = false): string
    {
        return 'package-'.$attempt->review_cycle.'-'.$revisionId.($afterRecheck ? '-after-recheck' : '');
    }

    /**
     * The canon block this activity freezes. The Interviewer and Planner also carry the framework catalog their output
     * is validated against. A blog-post Draft and VoiceReviewer carry the sections the plan approval retrieved, under
     * the same voice-only exemplar label, and the post-type template and chosen framework in full.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function frozenCanon(PublishingAttempt $attempt, Article $article, EditorialActivityKind $kind, array $input): array
    {
        $snapshot = $this->canon->snapshot();
        $canon = $snapshot->frozenFor($kind);

        if (in_array($kind, [EditorialActivityKind::Interview, EditorialActivityKind::Plan], true)) {
            $canon['catalog'] = FrameworkCatalog::fromSnapshot($snapshot)->toArray();
        }

        if (! in_array($kind, [EditorialActivityKind::Draft, EditorialActivityKind::ReviewVoice], true) || $article->kind !== PieceKind::BlogPost) {
            return $canon;
        }

        $draftCanon = $kind === EditorialActivityKind::ReviewVoice ? $this->draftCanon($attempt, $input) : null;

        return [...$canon, ...$this->retrievedExemplars($attempt, $draftCanon), ...$this->structureCanon($attempt, $draftCanon)];
    }

    /**
     * The post-type template and the framework the owner chose at the plan gate, read from the canon snapshot. A voice
     * review copies exactly what its Draft froze; a plan approved before frameworks existed freezes neither key.
     *
     * @param  array<string, mixed>|null  $draftCanon
     * @return array<string, mixed>
     */
    private function structureCanon(PublishingAttempt $attempt, ?array $draftCanon): array
    {
        if ($draftCanon !== null) {
            return array_intersect_key($draftCanon, ['post_type' => true, 'framework' => true]);
        }

        $snapshot = $this->canon->snapshot();
        $catalog = FrameworkCatalog::fromSnapshot($snapshot);
        $text = fn (?string $relative): ?string => $relative === null ? null : (isset($snapshot->files[CanonSnapshot::CANON_PREFIX.$relative]) ? trim($snapshot->files[CanonSnapshot::CANON_PREFIX.$relative]) : null);
        $structure = [];

        $postType = data_get($attempt->brief, 'post_type');
        $template = is_string($postType) ? $text($catalog->templatePath($postType)) : null;
        if (is_string($postType) && $template !== null) {
            $structure['post_type'] = ['slug' => $postType, 'text' => $template];
        }

        $chosen = data_get($attempt->plan, 'framework.chosen');
        $frameworkText = is_string($chosen) ? $text($catalog->frameworkPath($chosen)) : null;
        if (is_string($chosen) && $frameworkText !== null) {
            $framework = ['slug' => $chosen, 'text' => $frameworkText];
            $secondary = data_get($attempt->plan, 'framework.secondary');
            $secondarySlug = is_array($secondary) ? ($secondary['framework'] ?? null) : null;
            $secondaryText = is_string($secondarySlug) ? $text($catalog->frameworkPath($secondarySlug)) : null;
            if (is_array($secondary) && is_string($secondarySlug) && $secondaryText !== null) {
                $framework['secondary'] = [
                    'slug' => $secondarySlug,
                    'text' => $secondaryText,
                    'section_heading' => is_string($secondary['section_heading'] ?? null) ? $secondary['section_heading'] : '',
                ];
            }
            $structure['framework'] = $framework;
        }

        return $structure;
    }

    /**
     * A voice review judges the draft against exactly the sections that draft saw, so it copies them from the Draft it
     * follows. Otherwise the attempt's stored context is used; one whose query no longer matches the brief, angle and
     * plan (after a rethink) is `stale`, and an attempt that never retrieved is `none`.
     *
     * @param  array<string, mixed>|null  $draftCanon
     * @return array{retrieved_exemplars: list<mixed>, retrieval_status: string}
     */
    private function retrievedExemplars(PublishingAttempt $attempt, ?array $draftCanon): array
    {
        if ($draftCanon !== null && is_array($draftCanon['retrieved_exemplars'] ?? null) && is_string($draftCanon['retrieval_status'] ?? null)) {
            return ['retrieved_exemplars' => array_values($draftCanon['retrieved_exemplars']), 'retrieval_status' => $draftCanon['retrieval_status']];
        }

        $context = $attempt->getAttribute('exemplar_context');
        if (! is_array($context)) {
            return ['retrieved_exemplars' => [], 'retrieval_status' => 'none'];
        }

        $storedHash = $context['query_hash'] ?? null;
        if (! is_string($storedHash) || ! hash_equals($storedHash, hash('sha256', $this->exemplars->queryText($attempt)))) {
            return ['retrieved_exemplars' => [], 'retrieval_status' => 'stale'];
        }

        return [
            'retrieved_exemplars' => is_array($context['sections'] ?? null) ? array_values($context['sections']) : [],
            'retrieval_status' => is_string($context['status'] ?? null) ? $context['status'] : 'none',
        ];
    }

    /**
     * The canon frozen into the Draft this review follows: the given `draft_activity_id` when it names a Draft of this
     * attempt, otherwise the latest Draft of the current review cycle.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>|null
     */
    private function draftCanon(PublishingAttempt $attempt, array $input): ?array
    {
        $draftId = $input['draft_activity_id'] ?? null;
        $draft = EditorialActivity::query()
            ->select(['id', 'input'])
            ->where('attempt_id', $attempt->id)
            ->where('kind', EditorialActivityKind::Draft->value)
            ->when(
                is_numeric($draftId),
                fn ($query) => $query->whereKey((int) $draftId),
                fn ($query) => $query->where('review_cycle', (int) $attempt->review_cycle)->latest('id'),
            )
            ->first();

        $canon = $draft?->input['canon'] ?? null;

        return is_array($canon) ? $canon : null;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function ensureEligible(PublishingAttempt $attempt, Article $article, EditorialActivityKind $kind, string $stageValue, ?ArticleRevision $revision, array $input): void
    {
        match ($kind) {
            EditorialActivityKind::Interview => $this->ensureStage($stageValue, [EditorialStage::Developing], 'Interview work can only start while developing.'),
            EditorialActivityKind::ResearchChallenge => $this->ensureStageAndApproval($attempt, $stageValue, [EditorialStage::Drafting], ApprovalKind::Angle, 'Research requires an approved angle and drafting stage.'),
            EditorialActivityKind::Plan => $this->ensurePlanEligible($attempt, $stageValue),
            EditorialActivityKind::Draft => $this->ensureDraftEligible($attempt, $stageValue),
            EditorialActivityKind::LintRepair => $this->ensureLintRepairEligible($attempt, $article, $stageValue, $revision, $input),
            EditorialActivityKind::ReviewFacts, EditorialActivityKind::ReviewVoice, EditorialActivityKind::ReviewBuyer => $this->ensureReviewEligible($attempt, $article, $stageValue, $revision),
            EditorialActivityKind::Reconciliation => $this->ensureReconciliationEligible($attempt, $article, $stageValue),
            EditorialActivityKind::Recheck => $this->ensureRecheckEligible($attempt, $article, $stageValue, $input),
            EditorialActivityKind::Package => $this->ensurePackageEligible($attempt, $article, $revision, $input),
            EditorialActivityKind::DraftKit => $this->ensureDraftKitEligible($attempt, $article, $stageValue),
            EditorialActivityKind::ReviewKit => $this->ensureReviewKitEligible($attempt, $article, $stageValue, $revision),
        };
    }

    /**
     * What a piece's kind adds to every input, so no caller can forge it: the piece's own kind, which decides whether
     * approval checks apply, and for a kit piece's draft and review the approved flagship release it is cut from; for a
     * blog post's interview, the idea's other posts.
     *
     * @return array<string, mixed>
     */
    private function pieceContext(PublishingAttempt $attempt, Article $article, EditorialActivityKind $kind): array
    {
        $context = ['piece_kind' => $article->kind->value];

        if ($article->isKit()) {
            if (in_array($kind, [EditorialActivityKind::DraftKit, EditorialActivityKind::ReviewKit], true)) {
                $context['flagship'] = $this->flagshipContext($attempt, $article);
            }

            return $context;
        }

        if ($kind !== EditorialActivityKind::Interview) {
            return $context;
        }

        $siblings = $this->siblingContext($article);

        return $siblings === [] ? $context : $context + ['sibling_context' => $siblings];
    }

    /**
     * The approved flagship release a kit piece is cut from: its text with headings marked, title, public URL, target
     * phrase and the claim manifest that release approval froze.
     *
     * @return array{article_id: int, release_id: int, title: string|null, text: string, canonical_url: string|null, target_query: string|null, claim_manifest: list<mixed>}
     */
    private function flagshipContext(PublishingAttempt $attempt, Article $article): array
    {
        $release = $this->flagshipRelease($attempt, $article);
        $payload = $release->payload;
        $document = is_array($payload['document'] ?? null) ? $payload['document'] : [];
        $title = data_get($payload, 'metadata.title');
        $paragraphs = array_map(
            static fn (array $block): string => ($block['type'] === 'heading' ? '## ' : '').trim($block['text']),
            LintTarget::fromDocument($document, [], PieceKind::BlogPost)->blocks,
        );
        $canonicalUrl = data_get($attempt->brief, 'canonical_url');
        $targetQuery = data_get($attempt->brief, 'target_query');

        return [
            'article_id' => (int) $release->article_id,
            'release_id' => (int) $release->id,
            'title' => is_string($title) ? $title : null,
            'text' => implode("\n\n", array_filter($paragraphs, static fn (string $paragraph): bool => trim($paragraph, '# ') !== '')),
            'canonical_url' => is_string($canonicalUrl) ? $canonicalUrl : null,
            'target_query' => is_string($targetQuery) ? $targetQuery : null,
            'claim_manifest' => is_array($payload['claim_manifest'] ?? null) ? array_values($payload['claim_manifest']) : [],
        ];
    }

    /** The flagship release this kit attempt was spawned or restarted from. */
    private function flagshipRelease(PublishingAttempt $attempt, Article $article): ArticleRelease
    {
        $releaseId = data_get($attempt->brief, 'flagship_release_id');
        $release = is_numeric($releaseId) && $article->parent_article_id !== null
            ? ArticleRelease::query()->whereKey((int) $releaseId)->where('article_id', $article->parent_article_id)->first()
            : null;
        if (! $release instanceof ArticleRelease) {
            throw new RuntimeException('This kit piece has no flagship release to draft from.');
        }

        return $release;
    }

    /**
     * The idea's other blog posts, so an interview proposes a distinct angle: each one's title (its own metadata title,
     * else its angle's working title) and angle thesis. The idea's summary title names the idea, never a post.
     *
     * @return list<array{article_id: int, title: string|null, angle_thesis: string|null}>
     */
    private function siblingContext(Article $article): array
    {
        return array_values(Article::query()
            ->with(['workingRevision:id,metadata', 'currentAttempt:id,angle'])
            ->where('content_idea_id', $article->content_idea_id)
            ->where('kind', PieceKind::BlogPost->value)
            ->whereKeyNot($article->id)
            ->orderBy('id')
            ->get(['id', 'working_revision_id', 'current_attempt_id'])
            ->map(function (Article $sibling): array {
                $metadataTitle = data_get($sibling->workingRevision?->metadata, 'title');
                $angleTitle = data_get($sibling->currentAttempt?->angle, 'title');
                $thesis = data_get($sibling->currentAttempt?->angle, 'thesis');
                $title = is_string($metadataTitle) && trim($metadataTitle) !== '' ? trim($metadataTitle) : (is_string($angleTitle) && trim($angleTitle) !== '' ? trim($angleTitle) : null);

                return [
                    'article_id' => (int) $sibling->id,
                    'title' => $title,
                    'angle_thesis' => is_string($thesis) && trim($thesis) !== '' ? trim($thesis) : null,
                ];
            })
            ->all());
    }

    private function ensureDraftKitEligible(PublishingAttempt $attempt, Article $article, string $stageValue): void
    {
        if (! $article->isKit()) {
            throw new RuntimeException('Kit drafting is only for distribution kit pieces.');
        }

        $this->ensureStage($stageValue, [EditorialStage::InReview], 'Kit drafting requires a kit piece that has not been approved.');
        if (! $this->flagshipRelease($attempt, $article)->standsApproved()) {
            throw new RuntimeException('Kit drafting requires the flagship post\'s approved release; it was withdrawn or edited since approval.');
        }
    }

    private function ensureReviewKitEligible(PublishingAttempt $attempt, Article $article, string $stageValue, ?ArticleRevision $revision): void
    {
        if (! $article->isKit()) {
            throw new RuntimeException('Kit review is only for distribution kit pieces.');
        }

        $this->ensureStage($stageValue, [EditorialStage::InReview], 'Kit review requires a kit piece that has not been approved.');
        if (! $revision instanceof ArticleRevision || (int) ($article->working_revision_id ?? 0) !== (int) $revision->id) {
            throw new RuntimeException('Kit review requires the current drafted revision.');
        }

        $produced = EditorialActivity::query()
            ->where('attempt_id', $attempt->id)
            ->where('review_cycle', (int) $attempt->review_cycle)
            ->whereIn('kind', [EditorialActivityKind::DraftKit->value, EditorialActivityKind::LintRepair->value])
            ->where('status', EditorialActivityStatus::Completed->value)
            ->get(['id', 'proposal'])
            ->contains(fn (EditorialActivity $activity): bool => (int) data_get($activity->getAttribute('proposal'), 'applied_revision_id', 0) === (int) $revision->id);
        if (! $produced) {
            throw new RuntimeException('Kit review can only start after the kit draft has completed for the current revision.');
        }
    }

    /**
     * Review has finished when the reviewed revision has a completed reconciliation and no blocking or conflicting
     * finding (outside lint) waits for a disposition. When the one recheck was used, it must have completed on the
     * current revision; otherwise the reviewed revision is the current one. Null means packaging may start.
     *
     * @param  array<string, mixed>  $input
     */
    public function packageEligibilityProblem(PublishingAttempt $attempt, Article $article, ?ArticleRevision $revision, array $input): ?string
    {
        if ($this->stageValue($attempt) !== EditorialStage::InReview->value || ! $this->hasCurrentActiveApproval($attempt, ApprovalKind::Plan)) {
            return 'Packaging requires an approved plan and review stage.';
        }

        if (! $revision instanceof ArticleRevision || (int) ($article->working_revision_id ?? 0) !== (int) $revision->id) {
            return 'Packaging must target the current revision.';
        }

        $reviewedRevisionId = (int) ($input['reviewed_revision_id'] ?? $revision->id);
        if (! $this->completedActivityExists($attempt, EditorialActivityKind::Reconciliation, $reviewedRevisionId)) {
            return 'Packaging starts after review is reconciled.';
        }

        $openFindings = EditorialFinding::query()
            ->where('attempt_id', $attempt->id)
            ->where('review_cycle', (int) $attempt->review_cycle)
            ->where('revision_id', $reviewedRevisionId)
            ->where('lens', '!=', LintRevision::LENS)
            ->whereNull('stale_at')
            ->whereNull('disposition')
            ->where(fn ($query) => $query->where('severity', 'blocking')->orWhere('reconciliation_state', 'conflict'))
            ->exists();
        if ($openFindings) {
            return 'Packaging starts after every blocking or conflicting finding has a disposition.';
        }

        $rechecks = EditorialActivity::query()
            ->where('attempt_id', $attempt->id)
            ->where('review_cycle', (int) $attempt->review_cycle)
            ->where('kind', EditorialActivityKind::Recheck->value);
        if ((clone $rechecks)->whereIn('status', [EditorialActivityStatus::Pending->value, EditorialActivityStatus::Running->value])->exists()) {
            return 'Packaging starts after the recheck completes.';
        }

        if ((bool) $attempt->recheck_used) {
            $completed = (clone $rechecks)->where('status', EditorialActivityStatus::Completed->value)->where('revision_id', $revision->id)->exists();

            return $completed ? null : 'Packaging starts after the recheck completes on the current revision.';
        }

        return $reviewedRevisionId === (int) $revision->id ? null : 'Packaging must follow review of the current revision.';
    }

    /** @param array<string, mixed> $input */
    private function ensurePackageEligible(PublishingAttempt $attempt, Article $article, ?ArticleRevision $revision, array $input): void
    {
        $problem = $this->packageEligibilityProblem($attempt, $article, $revision, $input);
        if ($problem !== null) {
            throw new RuntimeException($problem);
        }
    }

    /** @param list<EditorialStage> $stages */
    private function ensureStage(string $stageValue, array $stages, string $message): void
    {
        $allowed = array_map(static fn (EditorialStage $stage): string => $stage->value, $stages);
        if (! in_array($stageValue, $allowed, true)) {
            throw new RuntimeException($message);
        }
    }

    /** @param list<EditorialStage> $stages */
    private function ensureStageAndApproval(PublishingAttempt $attempt, string $stageValue, array $stages, ApprovalKind $approval, string $message): void
    {
        $this->ensureStage($stageValue, $stages, $message);
        if (! $this->hasCurrentActiveApproval($attempt, $approval)) {
            throw new RuntimeException($message);
        }
    }

    private function ensurePlanEligible(PublishingAttempt $attempt, string $stageValue): void
    {
        $this->ensureStageAndApproval($attempt, $stageValue, [EditorialStage::Drafting], ApprovalKind::Angle, 'Planning requires an approved angle and drafting stage.');
        if (! $this->completedActivityExists($attempt, EditorialActivityKind::ResearchChallenge)) {
            throw new RuntimeException('Planning requires completed research/challenge work.');
        }
    }

    private function ensureDraftEligible(PublishingAttempt $attempt, string $stageValue): void
    {
        $this->ensureStageAndApproval($attempt, $stageValue, [EditorialStage::InReview], ApprovalKind::Plan, 'Drafting requires an approved plan and review stage.');
    }

    /** @param array<string, mixed> $input */
    private function ensureLintRepairEligible(PublishingAttempt $attempt, Article $article, string $stageValue, ?ArticleRevision $revision, array $input): void
    {
        if ($article->isKit()) {
            $this->ensureStage($stageValue, [EditorialStage::InReview], 'Voice-rule repair requires a kit piece that has not been approved.');
        } else {
            $this->ensureStageAndApproval($attempt, $stageValue, [EditorialStage::InReview], ApprovalKind::Plan, 'Voice-rule repair requires an approved plan and review stage.');
        }
        if (! $revision instanceof ArticleRevision
            || (int) ($article->working_revision_id ?? 0) !== (int) $revision->id
            || (int) ($input['expected_revision_id'] ?? 0) !== (int) $revision->id) {
            throw new RuntimeException('Voice-rule repair must target the current revision.');
        }
        if (! is_array($input['hits'] ?? null) || $input['hits'] === []) {
            throw new RuntimeException('Voice-rule repair needs at least one hit to fix.');
        }
        $completedRepairs = EditorialActivity::query()
            ->where('attempt_id', $attempt->id)
            ->where('review_cycle', (int) $attempt->review_cycle)
            ->where('kind', EditorialActivityKind::LintRepair->value)
            ->where('status', EditorialActivityStatus::Completed->value)
            ->count();
        if ($completedRepairs >= LintRevision::REPAIR_CAP) {
            throw new RuntimeException('Voice-rule repair already ran '.LintRevision::REPAIR_CAP.' times in this review cycle.');
        }
    }

    private function ensureReviewEligible(PublishingAttempt $attempt, Article $article, string $stageValue, ?ArticleRevision $revision): void
    {
        $this->ensureStageAndApproval($attempt, $stageValue, [EditorialStage::InReview], ApprovalKind::Plan, 'Review lenses require an approved plan and review stage.');
        if (! $revision instanceof ArticleRevision || (int) ($article->working_revision_id ?? 0) !== (int) $revision->id) {
            throw new RuntimeException('Review lenses require the current drafted revision.');
        }
        if (! $this->completedDraftExistsForRevision($attempt, (int) $revision->id)) {
            throw new RuntimeException('Review lenses can only start after the draft has completed for the current revision.');
        }
    }

    private function ensureReconciliationEligible(PublishingAttempt $attempt, Article $article, string $stageValue): void
    {
        $this->ensureStageAndApproval($attempt, $stageValue, [EditorialStage::InReview], ApprovalKind::Plan, 'Reconciliation requires an approved plan and review stage.');
        foreach ([EditorialActivityKind::ReviewFacts, EditorialActivityKind::ReviewVoice, EditorialActivityKind::ReviewBuyer] as $kind) {
            if (! $this->completedActivityExists($attempt, $kind, (int) ($article->working_revision_id ?? 0))) {
                throw new RuntimeException('Reconciliation requires all three review lenses on the current revision.');
            }
        }
    }

    /** @param array<string, mixed> $input */
    private function ensureRecheckEligible(PublishingAttempt $attempt, Article $article, string $stageValue, array $input): void
    {
        $this->ensureStageAndApproval($attempt, $stageValue, [EditorialStage::InReview], ApprovalKind::Plan, 'Recheck requires an approved plan and review stage.');
        if (! (bool) $attempt->recheck_used) {
            throw new RuntimeException('Recheck requires an explicit finished review decision.');
        }
        $targetRevisionId = (int) ($input['expected_revision_id'] ?? 0);
        if ($targetRevisionId !== 0 && $targetRevisionId !== (int) ($article->working_revision_id ?? 0)) {
            throw new RuntimeException('Recheck must target the current review revision.');
        }

        $reviewedRevisionId = (int) ($input['reviewed_revision_id'] ?? $targetRevisionId);
        foreach ([EditorialActivityKind::ReviewFacts, EditorialActivityKind::ReviewVoice, EditorialActivityKind::ReviewBuyer, EditorialActivityKind::Reconciliation] as $kind) {
            if (! $this->completedActivityExists($attempt, $kind, $reviewedRevisionId)) {
                throw new RuntimeException('Recheck requires a complete reviewed base batch.');
            }
        }
    }

    /**
     * Reconciliation and recheck judge earlier findings, so freeze the findings (and the reviewed text) they need.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function reviewContext(PublishingAttempt $attempt, Article $article, EditorialActivityKind $kind, ?ArticleRevision $revision, array $input): array
    {
        if ($kind === EditorialActivityKind::Reconciliation) {
            return ['review_findings' => $this->reviewFindings($attempt, (int) ($revision->id ?? 0), includeResolved: false)];
        }

        if ($kind !== EditorialActivityKind::Recheck) {
            return [];
        }

        $reviewedRevisionId = (int) ($input['reviewed_revision_id'] ?? 0);
        $reviewed = ArticleRevision::query()->where('article_id', $article->id)->whereKey($reviewedRevisionId)->first();

        return [
            'review_findings' => $this->reviewFindings($attempt, $reviewedRevisionId, includeResolved: true),
            'reviewed_manuscript' => $reviewed instanceof ArticleRevision ? [
                'revision_id' => $reviewed->id,
                'content_hash' => $reviewed->content_hash,
                'document' => $reviewed->document ?? [],
            ] : null,
        ];
    }

    /**
     * Current-cycle lens findings for one revision. Reconciliation sees open findings; recheck also sees superseded
     * ones, but not reconciled duplicates or findings the owner dismissed. Lint findings are deterministic and
     * re-checked at release, so neither agent judges them.
     *
     * @return list<array<string, mixed>>
     */
    private function reviewFindings(PublishingAttempt $attempt, int $revisionId, bool $includeResolved): array
    {
        return array_values(EditorialFinding::query()
            ->where('attempt_id', $attempt->id)
            ->where('review_cycle', (int) $attempt->review_cycle)
            ->where('revision_id', $revisionId)
            ->where('lens', '!=', LintRevision::LENS)
            ->when(! $includeResolved, fn ($query) => $query->whereNull('stale_at'))
            ->when($includeResolved, fn ($query) => $query
                ->whereNull('reconciled_into_finding_id')
                ->where(fn ($query) => $query->whereNull('disposition')->orWhereNotIn('disposition', ['rejected', 'false_positive'])))
            ->orderBy('id')
            ->get()
            ->map(fn (EditorialFinding $finding): array => array_filter([
                'id' => (int) $finding->id,
                'lens' => $finding->lens,
                'kind' => $finding->kind,
                'severity' => $finding->severity,
                'block_id' => $finding->block_id,
                'statement' => $finding->statement,
                'rationale' => $finding->rationale,
                'supporting_source_ids' => $finding->supporting_source_ids,
                'supporting_quotations' => $finding->supporting_quotations,
                'reconciliation_state' => $includeResolved ? $finding->reconciliation_state : null,
                'disposition' => $includeResolved ? $finding->disposition : null,
                'disposition_reason' => $includeResolved ? $finding->disposition_reason : null,
            ], fn (mixed $value): bool => $value !== null))
            ->all());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function completedResearchSnapshots(PublishingAttempt $attempt): array
    {
        $snapshots = [];
        $activities = EditorialActivity::query()
            ->where('attempt_id', $attempt->id)
            ->where('review_cycle', (int) $attempt->review_cycle)
            ->where('kind', EditorialActivityKind::ResearchChallenge->value)
            ->where('status', EditorialActivityStatus::Completed->value)
            ->get();

        foreach ($activities as $activity) {
            if (! $this->activityMatchesCurrentApproval($activity, $attempt, EditorialActivityKind::ResearchChallenge)) {
                continue;
            }

            $response = $activity->getAttribute('response');
            $completedAt = $activity->getAttribute('completed_at');
            $snapshots[] = [
                'activity_id' => (int) $activity->id,
                'input_version' => (int) ($activity->input_version ?? 0),
                'response' => is_array($response) ? $this->sanitizeResearchResponse($response) : [],
                'completed_at' => $completedAt instanceof \DateTimeInterface ? $completedAt->format(DATE_ATOM) : null,
            ];
        }

        return $snapshots;
    }

    /**
     * @return list<array{id: int, source_type: string, title: string|null, url: string|null, final_url: string|null, extracted_text: string|null, content_hash: string|null, activity_id: int|null}>
     */
    private function eligibleEvidenceSources(PublishingAttempt $attempt): array
    {
        $sources = [];
        $records = EvidenceSource::query()
            ->where('attempt_id', $attempt->id)
            ->get();

        $selectedIds = $this->selectedEvidenceSourceIds($attempt);
        foreach ($records as $source) {
            $sourceType = (string) $source->source_type;
            $selected = in_array((int) $source->id, $selectedIds, true);
            $currentResearch = $source->activity_id !== null
                && $sourceType === 'public'
                && $this->sourceActivityMatchesCurrentApproval($source, $attempt);

            $selectedOwnerOrRestricted = $selected && in_array($sourceType, ['owner', 'restricted'], true);

            if (! $currentResearch && ! $selectedOwnerOrRestricted) {
                continue;
            }

            if ($sourceType === 'restricted' && (! $selectedOwnerOrRestricted || ! (bool) $source->restricted_processing_consent)) {
                continue;
            }

            $sources[] = [
                'id' => (int) $source->id,
                'source_type' => $sourceType,
                'title' => is_string($source->title) ? $source->title : null,
                'url' => is_string($source->url) ? $source->url : null,
                'final_url' => is_string($source->final_url) ? $source->final_url : null,
                'extracted_text' => is_string($source->extracted_text) ? $source->extracted_text : null,
                'content_hash' => is_string($source->content_hash) ? $source->content_hash : null,
                'activity_id' => $source->activity_id === null ? null : (int) $source->activity_id,
            ];
        }

        return $sources;
    }

    /** @return array<string, mixed> */
    private function ownerContextForPrompt(PublishingAttempt $attempt): array
    {
        $context = $attempt->getAttribute('interview_context');
        $context = is_array($context) ? $context : [];

        $angle = $attempt->getAttribute('angle');

        return array_filter([
            'latest_interview_activity_id' => is_numeric($context['latest_interview_activity_id'] ?? null) ? (int) $context['latest_interview_activity_id'] : null,
            'questions' => is_array($context['questions'] ?? null) ? $context['questions'] : [],
            'answers' => $this->boundedAnswerContext($context['answers'] ?? null),
            'answered_interview_activity_id' => is_numeric($context['answered_interview_activity_id'] ?? null) ? (int) $context['answered_interview_activity_id'] : null,
            'answered_at' => is_string($context['answered_at'] ?? null) ? $context['answered_at'] : null,
            'selected_angle_option' => is_scalar($context['selected_angle_option'] ?? null) ? (string) $context['selected_angle_option'] : null,
            'selected_angle' => is_array($angle) ? $angle : [],
        ], static fn (mixed $value): bool => $value !== null);
    }

    /** @return array<string, mixed> */
    private function approvalOwnerContext(PublishingAttempt $attempt): array
    {
        $context = $this->ownerContextForPrompt($attempt);

        return [
            'latest_interview_activity_id' => $context['latest_interview_activity_id'] ?? null,
            'answers' => $context['answers'] ?? null,
            'answered_interview_activity_id' => $context['answered_interview_activity_id'] ?? null,
            'selected_angle_option' => $context['selected_angle_option'] ?? null,
        ];
    }

    private function boundedAnswerContext(mixed $answers): mixed
    {
        if (is_string($answers)) {
            return mb_substr($answers, 0, 4000);
        }

        if (! is_array($answers)) {
            return null;
        }

        $bounded = [];
        foreach ($answers as $key => $answer) {
            if (is_string($answer)) {
                $bounded[(string) $key] = mb_substr($answer, 0, 2000);
            }
        }

        return $bounded;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function sanitizeResearchResponse(array $response): array
    {
        if (is_array($response['sourceReferences'] ?? null)) {
            $response['sourceReferences'] = array_map(function (mixed $reference): mixed {
                if (! is_array($reference)) {
                    return $reference;
                }

                unset($reference['content'], $reference['retrieved_content'], $reference['extracted_text']);

                return $reference;
            }, $response['sourceReferences']);
        }

        return $response;
    }

    /** @return list<int> */
    private function selectedEvidenceSourceIds(PublishingAttempt $attempt): array
    {
        $context = $attempt->getAttribute('interview_context');
        $context = is_array($context) ? $context : [];
        $ids = $context['selected_evidence_source_ids'] ?? [];
        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id): bool => $id > 0)));
    }

    private function sourceActivityMatchesCurrentApproval(EvidenceSource $source, PublishingAttempt $attempt): bool
    {
        $activityId = $source->getAttribute('activity_id');
        if (! is_numeric($activityId)) {
            return false;
        }

        $activity = EditorialActivity::query()->whereKey((int) $activityId)->first();
        if (! $activity instanceof EditorialActivity) {
            return false;
        }

        $kind = $activity->getAttribute('kind');
        $status = $activity->getAttribute('status');

        return (int) $activity->attempt_id === (int) $attempt->id
            && (int) $activity->review_cycle === (int) $attempt->review_cycle
            && (int) ($activity->input_version ?? 0) === (int) ($attempt->input_version ?? 0)
            && ($kind instanceof EditorialActivityKind ? $kind->value : (string) $kind) === EditorialActivityKind::ResearchChallenge->value
            && ($status instanceof EditorialActivityStatus ? $status->value : (string) $status) === EditorialActivityStatus::Completed->value
            && $this->activityMatchesCurrentApproval($activity, $attempt, EditorialActivityKind::ResearchChallenge);
    }

    /** @param array<string, mixed> $input */
    private function persistSelectedEvidenceContext(PublishingAttempt $attempt, array $input): void
    {
        if (! array_key_exists('selected_evidence_source_ids', $input)) {
            return;
        }

        $ids = is_array($input['selected_evidence_source_ids']) ? $input['selected_evidence_source_ids'] : [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id): bool => $id > 0)));

        $contextValue = $attempt->getAttribute('interview_context');
        $context = is_array($contextValue) ? $contextValue : [];
        $context['selected_evidence_source_ids'] = $ids;

        $attempt->forceFill(['interview_context' => $context])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function idempotencyPayload(PublishingAttempt $attempt, EditorialActivityKind $kind, ?ArticleRevision $revision, ?string $batchKey, string $promptHash): array
    {
        $payload = [
            'attempt_id' => $attempt->id,
            'kind' => $kind->value,
            'review_cycle' => (int) $attempt->review_cycle,
            'batch_key' => $batchKey,
        ];

        if (in_array($kind, [
            EditorialActivityKind::ReviewFacts,
            EditorialActivityKind::ReviewVoice,
            EditorialActivityKind::ReviewBuyer,
            EditorialActivityKind::Reconciliation,
            EditorialActivityKind::Recheck,
            EditorialActivityKind::Package,
            EditorialActivityKind::ReviewKit,
        ], true)) {
            $payload['revision_id'] = $revision?->id;
            $payload['revision_hash'] = $revision?->content_hash;

            return $payload;
        }

        $payload['prompt_hash'] = $promptHash;

        return $payload;
    }

    /**
     * The drafted revision, or the revision a same-cycle voice-rule repair produced from it.
     */
    private function completedDraftExistsForRevision(PublishingAttempt $attempt, int $revisionId): bool
    {
        return $this->completedDraftTargetsRevision($attempt, $revisionId) || $this->completedLintRepairProducedRevision($attempt, $revisionId);
    }

    private function completedLintRepairProducedRevision(PublishingAttempt $attempt, int $revisionId): bool
    {
        return EditorialActivity::query()
            ->where('attempt_id', $attempt->id)
            ->where('review_cycle', (int) $attempt->review_cycle)
            ->where('kind', EditorialActivityKind::LintRepair->value)
            ->where('status', EditorialActivityStatus::Completed->value)
            ->get()
            ->contains(fn (EditorialActivity $activity): bool => (int) data_get($activity->getAttribute('proposal'), 'applied_revision_id', 0) === $revisionId
                && $this->activityMatchesCurrentApproval($activity, $attempt, EditorialActivityKind::LintRepair));
    }

    private function completedDraftTargetsRevision(PublishingAttempt $attempt, int $revisionId): bool
    {
        return EditorialActivity::query()
            ->where('attempt_id', $attempt->id)
            ->where('kind', EditorialActivityKind::Draft->value)
            ->where('status', EditorialActivityStatus::Completed->value)
            ->get()
            ->contains(function (EditorialActivity $activity) use ($attempt, $revisionId): bool {
                $proposal = $activity->getAttribute('proposal');
                $targetsRevision = (int) ($activity->revision_id ?? 0) === $revisionId
                    || (is_array($proposal) && (int) ($proposal['applied_revision_id'] ?? 0) === $revisionId);

                if (! $targetsRevision || ! $this->activityMatchesCurrentApproval($activity, $attempt, EditorialActivityKind::Draft)) {
                    return false;
                }

                return (int) ($activity->review_cycle ?? 0) === (int) $attempt->review_cycle
                    || $this->isExplicitReviewRestartDraft($attempt, $activity, $revisionId);
            });
    }

    private function isExplicitReviewRestartDraft(PublishingAttempt $attempt, EditorialActivity $draft, int $revisionId): bool
    {
        if ((int) ($draft->review_cycle ?? 0) >= (int) $attempt->review_cycle) {
            return false;
        }

        return EditorialActivity::query()
            ->where('attempt_id', $attempt->id)
            ->where('review_cycle', (int) $draft->review_cycle)
            ->where('kind', EditorialActivityKind::Reconciliation->value)
            ->where('status', EditorialActivityStatus::Completed->value)
            ->where('revision_id', $revisionId)
            ->exists();
    }

    private function completedActivityExists(PublishingAttempt $attempt, EditorialActivityKind $kind, ?int $revisionId = null): bool
    {
        $query = EditorialActivity::query()
            ->where('attempt_id', $attempt->id)
            ->where('review_cycle', (int) $attempt->review_cycle)
            ->where('kind', $kind->value)
            ->where('status', EditorialActivityStatus::Completed->value);

        if ($revisionId !== null) {
            $query->where('revision_id', $revisionId);
        }

        return $query->get()->contains(fn (EditorialActivity $activity): bool => $this->activityMatchesCurrentApproval($activity, $attempt, $kind));
    }

    private function hasCurrentActiveApproval(PublishingAttempt $attempt, ApprovalKind $kind): bool
    {
        return $this->activeApprovalHash($attempt, $kind) !== null;
    }

    private function activeApprovalHash(PublishingAttempt $attempt, ApprovalKind $kind): ?string
    {
        $inputHash = $this->approvalInputHashFor($attempt, $kind);

        $approval = EditorialApproval::query()
            ->where('attempt_id', $attempt->id)
            ->where('kind', $kind->value)
            ->where('input_hash', $inputHash)
            ->whereNull('invalidated_at')
            ->first();

        return $approval instanceof EditorialApproval ? (string) $approval->input_hash : null;
    }

    private function activityMatchesCurrentApproval(EditorialActivity $activity, PublishingAttempt $attempt, EditorialActivityKind $kind): bool
    {
        $approval = self::prerequisiteApproval($kind, is_array($activity->input) ? $activity->input : []);

        if (! $approval instanceof ApprovalKind) {
            return true;
        }

        $input = $activity->getAttribute('input');
        $hashes = is_array($input) && is_array(data_get($input, 'approval_hashes')) ? data_get($input, 'approval_hashes') : [];
        $activityHash = $hashes[$approval->value] ?? null;
        $currentHash = $this->activeApprovalHash($attempt, $approval);

        return is_string($activityHash) && is_string($currentHash) && hash_equals($currentHash, $activityHash);
    }

    /**
     * The approval an activity's input must still match. Distribution kit work answers to the flagship release it was cut
     * from, which RunEditorialActivity checks, so it carries no approval of its own attempt.
     *
     * @param  array<array-key, mixed>  $input
     */
    public static function prerequisiteApproval(EditorialActivityKind $kind, array $input): ?ApprovalKind
    {
        $piece = PieceKind::tryFrom(is_string($input['piece_kind'] ?? null) ? $input['piece_kind'] : '');
        if ($piece?->isKit()) {
            return null;
        }

        return match ($kind) {
            EditorialActivityKind::ResearchChallenge, EditorialActivityKind::Plan => ApprovalKind::Angle,
            EditorialActivityKind::Draft, EditorialActivityKind::LintRepair, EditorialActivityKind::ReviewFacts, EditorialActivityKind::ReviewVoice, EditorialActivityKind::ReviewBuyer, EditorialActivityKind::Reconciliation, EditorialActivityKind::Recheck, EditorialActivityKind::Package => ApprovalKind::Plan,
            EditorialActivityKind::Interview, EditorialActivityKind::DraftKit, EditorialActivityKind::ReviewKit => null,
        };
    }

    private function approvalInputHashFor(PublishingAttempt $attempt, ApprovalKind $kind): string
    {
        return match ($kind) {
            ApprovalKind::Angle => $this->fingerprint->hash([
                'kind' => $kind->value,
                'attempt_id' => $attempt->id,
                'brief' => $attempt->brief ?? [],
                'angle' => $attempt->angle ?? [],
                'owner_context' => $this->approvalOwnerContext($attempt),
                'input_version' => $attempt->input_version,
            ]),
            ApprovalKind::Plan => $this->fingerprint->hash([
                'kind' => $kind->value,
                'attempt_id' => $attempt->id,
                'brief' => $attempt->brief ?? [],
                'angle' => $attempt->angle ?? [],
                'owner_context' => $this->approvalOwnerContext($attempt),
                'plan' => $attempt->plan ?? [],
                'input_version' => $attempt->input_version,
            ]),
            ApprovalKind::Release => throw new RuntimeException('Release approval does not start agent activity.'),
            ApprovalKind::Kit => throw new RuntimeException('Kit approval does not start agent activity.'),
        };
    }

    private function stageValue(PublishingAttempt $attempt): string
    {
        $stage = $attempt->getAttribute('stage');

        return $stage instanceof EditorialStage ? $stage->value : (string) $stage;
    }

    private function lockedAttempt(PublishingAttempt|int $attempt): PublishingAttempt
    {
        $attemptId = $attempt instanceof PublishingAttempt ? $attempt->getKey() : $attempt;

        return PublishingAttempt::query()->whereKey($attemptId)->lockForUpdate()->firstOrFail();
    }
}
