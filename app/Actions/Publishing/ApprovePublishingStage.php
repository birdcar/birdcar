<?php

namespace App\Actions\Publishing;

use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Models\Article;
use App\Models\ArticleRelease;
use App\Models\EditorialActivity;
use App\Models\EditorialApproval;
use App\Models\EditorialFinding;
use App\Models\Publishing\ApprovalKind;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\Publishing\EditorialStage;
use App\Models\Publishing\PieceKind;
use App\Models\PublishingAttempt;
use App\Models\User;
use App\Services\Publishing\ExemplarRetriever;
use App\Services\Publishing\Lint\LintHit;
use App\Services\Publishing\PublishingFingerprint;
use App\Settings\PublishingAgentSettings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ApprovePublishingStage
{
    public function __construct(private PublishingFingerprint $fingerprint, private ExemplarRetriever $exemplars) {}

    public function approve(
        User $actor,
        PublishingAttempt|int $attempt,
        ApprovalKind $kind,
        string $expectedInputHash,
        ?int $revisionId = null,
        ?int $releaseId = null,
    ): EditorialApproval {
        $this->authorize($actor, PublishingPermission::Approve->value);

        if ($kind === ApprovalKind::Release) {
            throw new RuntimeException('Release approvals must be managed through release packages.');
        }

        // The Draft starts inside the transaction below, so the network call happens first and never holds row locks.
        $exemplarContext = $kind === ApprovalKind::Plan ? $this->exemplarContextFor($attempt) : null;

        /** @var EditorialApproval $approval */
        $approval = DB::transaction(function () use ($actor, $attempt, $kind, $expectedInputHash, $revisionId, $releaseId, $exemplarContext): EditorialApproval {
            $lockedAttempt = $this->lockedAttempt($attempt);
            $actualHash = $this->inputHashFor($lockedAttempt, $kind, $revisionId, $releaseId);

            if (! hash_equals($actualHash, $expectedInputHash)) {
                throw new RuntimeException('The approval input is stale.');
            }

            $this->ensureApprovalIsAllowed($lockedAttempt, $kind);

            EditorialApproval::query()
                ->where('attempt_id', $lockedAttempt->id)
                ->where('kind', $kind->value)
                ->whereNull('invalidated_at')
                ->update(['invalidated_at' => now()]);

            $approval = EditorialApproval::create([
                'attempt_id' => $lockedAttempt->id,
                'kind' => $kind,
                'input_hash' => $actualHash,
                'revision_id' => $kind === ApprovalKind::Kit ? Article::query()->whereKey($lockedAttempt->article_id)->value('working_revision_id') : $revisionId,
                'release_id' => $releaseId,
                'user_id' => $actor->id,
                'approved_at' => now(),
            ]);

            $changes = ['stage' => $this->nextStage($kind)];
            if ($exemplarContext !== null) {
                $changes['exemplar_context'] = $exemplarContext;
            }
            $lockedAttempt->forceFill($changes)->save();
            $lockedAttempt->refresh();

            if (! app(PublishingAgentSettings::class)->paused) {
                if ($kind === ApprovalKind::Angle) {
                    app(StartEditorialActivity::class)->start($actor, $lockedAttempt, EditorialActivityKind::ResearchChallenge, [], 'research-'.$lockedAttempt->review_cycle);
                }
                if ($kind === ApprovalKind::Plan) {
                    app(StartEditorialActivity::class)->start($actor, $lockedAttempt, EditorialActivityKind::Draft, [], 'draft-'.$lockedAttempt->review_cycle);
                }
            }

            return $approval;
        });

        return $approval;
    }

    /**
     * Blog-post exemplars for the Draft a plan approval starts. A paused approval starts no Draft, so it spends nothing;
     * kit pieces never retrieve.
     *
     * @return array<string, mixed>|null
     */
    private function exemplarContextFor(PublishingAttempt|int $attempt): ?array
    {
        if (app(PublishingAgentSettings::class)->paused) {
            return null;
        }

        $fresh = PublishingAttempt::query()->find($attempt instanceof PublishingAttempt ? $attempt->getKey() : $attempt);
        if (! $fresh instanceof PublishingAttempt) {
            return null;
        }

        $article = Article::query()->select(['id', 'kind'])->find($fresh->article_id);
        if ($article?->kind !== PieceKind::BlogPost) {
            return null;
        }

        return $this->exemplars->forAttempt($fresh);
    }

    public function inputHashFor(PublishingAttempt $attempt, ApprovalKind $kind, ?int $revisionId = null, ?int $releaseId = null): string
    {
        return $this->snapshotInputHashFor($attempt->fresh() ?? $attempt, $kind, $revisionId, $releaseId);
    }

    public function snapshotInputHashFor(PublishingAttempt $attempt, ApprovalKind $kind, ?int $revisionId = null, ?int $releaseId = null): string
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
            ApprovalKind::Release => $this->releaseHash($attempt, $revisionId, $releaseId),
            ApprovalKind::Kit => $this->kitHash($attempt),
        };
    }

    /**
     * A kit approval covers the exact text, the flagship release it was cut from, every voice-rule hit with its override
     * reason, and every kit review finding with its decision, so a change to any of them needs a new approval.
     */
    private function kitHash(PublishingAttempt $attempt): string
    {
        $article = Article::query()->findOrFail($attempt->article_id);
        $revision = $article->workingRevision()->first();
        $lint = app(LintRevision::class);
        $overrides = $lint->overrides((int) $article->id);
        $hits = $revision === null ? [] : $lint->hits($article, $revision);

        return $this->fingerprint->hash([
            'kind' => ApprovalKind::Kit->value,
            'attempt_id' => $attempt->id,
            'flagship_release_id' => data_get($attempt->brief, 'flagship_release_id'),
            'revision_id' => $revision?->id,
            'content_hash' => $revision?->content_hash,
            'lint' => array_map(static fn (LintHit $hit): array => ['hash' => $hit->inputHash(), 'override' => $overrides[$hit->inputHash()]['reason'] ?? null], $hits),
            'findings' => $this->kitFindings($attempt)
                ->map(static fn (EditorialFinding $finding): array => ['id' => (int) $finding->id, 'severity' => $finding->severity, 'disposition' => $finding->disposition, 'reason' => $finding->disposition_reason])
                ->all(),
        ]);
    }

    /**
     * Why a kit piece cannot be approved yet, in the order the owner fixes them; empty when it can.
     *
     * @return list<string>
     */
    public function kitApprovalProblems(PublishingAttempt $attempt): array
    {
        $article = Article::query()->find($attempt->article_id);
        if (! $article instanceof Article || ! $article->isKit()) {
            return ['Kit approval is only for distribution kit pieces.'];
        }

        if ($this->stageValue($attempt) !== EditorialStage::InReview->value) {
            return ['Kit approval requires a kit piece that is still in review.'];
        }

        $revision = $article->workingRevision()->first();
        if ($revision === null) {
            return ['Kit approval needs a drafted piece.'];
        }

        $problems = [];
        $reviewed = EditorialActivity::query()
            ->where('attempt_id', $attempt->id)
            ->where('review_cycle', (int) $attempt->review_cycle)
            ->where('kind', EditorialActivityKind::ReviewKit->value)
            ->where('status', EditorialActivityStatus::Completed->value)
            ->exists();
        if (! $reviewed) {
            $problems[] = 'Kit approval needs a completed kit review.';
        }

        $lint = app(LintRevision::class);
        $overrides = $lint->overrides((int) $article->id);
        $open = array_filter($lint->hits($article, $revision), static fn (LintHit $hit): bool => ! isset($overrides[$hit->inputHash()]));
        if ($open !== []) {
            $problems[] = count($open).' voice-rule '.(count($open) === 1 ? 'hit needs' : 'hits need').' a fix or an override with a reason.';
        }

        if ($this->kitFindings($attempt)->contains(static fn (EditorialFinding $finding): bool => $finding->severity === 'blocking' && $finding->disposition === null)) {
            $problems[] = 'Decide every blocking kit review finding before approving.';
        }

        return $problems;
    }

    /**
     * Records the owner's decision on a kit review finding at once; kit review has no finish step to buffer it for.
     * Keeping the piece as written, or calling the finding a false positive, needs a reason.
     */
    public function decideKitFinding(User $actor, EditorialFinding $finding, string $disposition, string $reason = ''): void
    {
        $this->authorize($actor, PublishingPermission::Approve->value);

        if ($finding->lens !== EditorialActivityKind::ReviewKit->value) {
            throw new RuntimeException('Only kit review findings are decided here.');
        }

        if (! in_array($disposition, ['accepted', 'rejected', 'false_positive'], true)) {
            throw new RuntimeException('Unknown kit review decision.');
        }

        $reason = trim($reason);
        if ($disposition !== 'accepted' && $reason === '') {
            throw ValidationException::withMessages(['reason' => 'Explain why the piece keeps this as written.']);
        }

        $finding->forceFill([
            'disposition' => $disposition,
            'disposition_reason' => $reason === '' ? null : $reason,
            'disposition_actor_id' => $actor->id,
            'disposed_at' => now(),
        ])->save();
    }

    /**
     * The current kit review cycle's live findings.
     *
     * @return Collection<int, EditorialFinding>
     */
    private function kitFindings(PublishingAttempt $attempt): Collection
    {
        return EditorialFinding::query()
            ->where('attempt_id', $attempt->id)
            ->where('lens', EditorialActivityKind::ReviewKit->value)
            ->where('review_cycle', (int) $attempt->review_cycle)
            ->whereNull('stale_at')
            ->orderBy('id')
            ->get();
    }

    /** @return array<string, mixed> */
    private function approvalOwnerContext(PublishingAttempt $attempt): array
    {
        $context = $attempt->getAttribute('interview_context');
        $context = is_array($context) ? $context : [];
        $answers = $context['answers'] ?? null;
        if (is_string($answers)) {
            $answers = mb_substr($answers, 0, 4000);
        } elseif (is_array($answers)) {
            $answers = array_map(static fn (mixed $answer): mixed => is_string($answer) ? mb_substr($answer, 0, 2000) : null, $answers);
        } else {
            $answers = null;
        }

        return [
            'latest_interview_activity_id' => is_numeric($context['latest_interview_activity_id'] ?? null) ? (int) $context['latest_interview_activity_id'] : null,
            'answers' => $answers,
            'answered_interview_activity_id' => is_numeric($context['answered_interview_activity_id'] ?? null) ? (int) $context['answered_interview_activity_id'] : null,
            'selected_angle_option' => is_scalar($context['selected_angle_option'] ?? null) ? (string) $context['selected_angle_option'] : null,
        ];
    }

    private function releaseHash(PublishingAttempt $attempt, ?int $revisionId, ?int $releaseId): string
    {
        if ($releaseId === null) {
            throw new RuntimeException('Release approvals require a prepared release package.');
        }

        $release = ArticleRelease::query()->findOrFail($releaseId);

        if ((int) $release->attempt_id !== (int) $attempt->id) {
            throw new RuntimeException('Release approval cannot cross publishing attempts.');
        }

        if ((int) $release->article_id !== (int) $attempt->article_id) {
            throw new RuntimeException('Release approval cannot cross articles.');
        }

        if ($revisionId !== null && (int) $release->revision_id !== (int) $revisionId) {
            throw new RuntimeException('Release approval must match the prepared package revision.');
        }

        return (string) $release->release_hash;
    }

    private function nextStage(ApprovalKind $kind): EditorialStage
    {
        return match ($kind) {
            ApprovalKind::Angle => EditorialStage::Drafting,
            ApprovalKind::Plan => EditorialStage::InReview,
            ApprovalKind::Release, ApprovalKind::Kit => EditorialStage::Approved,
        };
    }

    private function ensureApprovalIsAllowed(PublishingAttempt $attempt, ApprovalKind $kind): void
    {
        if ($attempt->paused_at !== null || $attempt->parked_at !== null || $attempt->abandoned_at !== null) {
            throw new RuntimeException('Blocked publishing attempts cannot receive approvals.');
        }

        match ($kind) {
            ApprovalKind::Angle => $this->ensureAngleApprovalRequirements($attempt),
            ApprovalKind::Plan => $this->ensurePlanApprovalRequirements($attempt),
            ApprovalKind::Release => $this->ensurePrerequisiteApproval($attempt, [EditorialStage::InReview, EditorialStage::Approved, EditorialStage::Scheduled], ApprovalKind::Plan, 'Release approval requires an active plan approval.'),
            ApprovalKind::Kit => $this->ensureKitApprovalRequirements($attempt),
        };
    }

    private function ensureKitApprovalRequirements(PublishingAttempt $attempt): void
    {
        $problems = $this->kitApprovalProblems($attempt);
        if ($problems !== []) {
            throw new RuntimeException($problems[0]);
        }
    }

    private function ensureAngleApprovalRequirements(PublishingAttempt $attempt): void
    {
        $this->ensureStage($attempt, EditorialStage::Developing, 'Angle approval requires a developing attempt.');

        $context = $attempt->getAttribute('interview_context');
        $context = is_array($context) ? $context : [];
        $questions = is_array($context['questions'] ?? null) ? $context['questions'] : [];
        if ($questions !== []) {
            $answers = $context['answers'] ?? null;
            $answered = false;
            if (is_string($answers)) {
                $answered = trim($answers) !== '';
            } elseif (is_array($answers)) {
                $answered = collect($questions)->every(function (mixed $question, int|string $key) use ($answers): bool {
                    $answer = $answers[$key] ?? (is_array($question) ? ($answers[$question['id'] ?? ''] ?? null) : null);

                    return is_string($answer) && trim($answer) !== '';
                });
            }

            if (! $answered) {
                throw new RuntimeException('Angle approval requires answers to the latest interview questions.');
            }
        }

        $this->ensureCurrentAngleSelected($attempt, $context);
    }

    /** @param array<string, mixed> $context */
    private function ensureCurrentAngleSelected(PublishingAttempt $attempt, array $context): void
    {
        $angleValue = $attempt->getAttribute('angle');
        $angle = is_array($angleValue) ? $angleValue : [];
        $options = is_array($context['angle_options'] ?? null) ? $context['angle_options'] : [];
        if ($angle === [] && $options !== []) {
            throw new RuntimeException('Angle approval requires a selected angle option or explicit human angle.');
        }

        if ($options === []) {
            if (($angle['source'] ?? null) === 'human' || ($angle['source'] ?? null) === 'owner' || ($angle['explicit_human_angle'] ?? false) === true) {
                return;
            }

            $briefValue = $attempt->getAttribute('brief');
            $brief = is_array($briefValue) ? $briefValue : [];
            if (! array_key_exists('latest_interview_activity_id', $context) && ($context === [] || $brief !== [])) {
                return;
            }

            throw new RuntimeException('Angle approval requires a selected angle option or explicit human angle.');
        }

        $selectedKey = $context['selected_angle_option'] ?? null;
        if (! is_scalar($selectedKey) || ! array_key_exists((string) $selectedKey, $options)) {
            throw new RuntimeException('Angle approval requires a current selected angle option.');
        }

        $selected = $options[(string) $selectedKey];
        if (! is_array($selected) || $selected !== $angle) {
            throw new RuntimeException('Angle approval requires the current selected angle option.');
        }
    }

    private function ensurePlanApprovalRequirements(PublishingAttempt $attempt): void
    {
        $this->ensurePrerequisiteApproval($attempt, [EditorialStage::Drafting], ApprovalKind::Angle, 'Plan approval requires an active angle approval.');

        if (! $this->completedCurrentCycleActivityExists($attempt, EditorialActivityKind::ResearchChallenge)) {
            throw new RuntimeException('Plan approval requires completed research/challenge work for the current review cycle.');
        }

        if (! $this->hasReviewedPlanForCurrentInput($attempt)) {
            throw new RuntimeException('Plan approval requires a reviewed outline and visual plan for the current input.');
        }

        $this->ensureFrameworkChosen($attempt);
    }

    /**
     * An agent plan is approved only with the framework the owner chose at the plan gate, stored inside the plan so the
     * plan approval hash covers it. A plan the owner wrote by hand carries its own structure and needs no choice.
     */
    private function ensureFrameworkChosen(PublishingAttempt $attempt): void
    {
        $plan = $attempt->plan ?? [];
        if (($plan['source'] ?? 'human') !== 'agent') {
            return;
        }

        $chosen = data_get($plan, 'framework.chosen');
        if (! is_string($chosen) || $chosen === '' || $chosen !== data_get($plan, 'framework.primary')) {
            throw new RuntimeException('Plan approval requires a chosen narrative framework: keep the primary or pick another framework first.');
        }
    }

    private function ensureStage(PublishingAttempt $attempt, EditorialStage $stage, string $message): void
    {
        if ($this->stageValue($attempt) !== $stage->value) {
            throw new RuntimeException($message);
        }
    }

    /**
     * @param  list<EditorialStage>  $stages
     */
    private function ensurePrerequisiteApproval(PublishingAttempt $attempt, array $stages, ApprovalKind $prerequisite, string $message): void
    {
        $allowedStageValues = array_map(static fn (EditorialStage $stage): string => $stage->value, $stages);

        if (! in_array($this->stageValue($attempt), $allowedStageValues, true)) {
            throw new RuntimeException($message);
        }

        $inputHash = $this->inputHashFor($attempt, $prerequisite);

        $approved = EditorialApproval::query()
            ->where('attempt_id', $attempt->id)
            ->where('kind', $prerequisite->value)
            ->where('input_hash', $inputHash)
            ->whereNull('invalidated_at')
            ->exists();

        if (! $approved) {
            throw new RuntimeException($message);
        }
    }

    private function completedCurrentCycleActivityExists(PublishingAttempt $attempt, EditorialActivityKind $kind): bool
    {
        return EditorialActivity::query()
            ->where('attempt_id', $attempt->id)
            ->where('review_cycle', (int) $attempt->review_cycle)
            ->where('kind', $kind->value)
            ->where('status', EditorialActivityStatus::Completed->value)
            ->where('input_version', $attempt->input_version)
            ->get()
            ->contains(fn (EditorialActivity $activity): bool => $this->activityMatchesCurrentApproval($activity, $attempt, $kind));
    }

    private function hasReviewedPlanForCurrentInput(PublishingAttempt $attempt): bool
    {
        $planValue = $attempt->getAttribute('plan');
        $plan = is_array($planValue) ? $planValue : [];
        $outline = array_key_exists('outline', $plan) ? $plan['outline'] : null;
        $visualPlan = array_key_exists('visualPlan', $plan) ? $plan['visualPlan'] : null;

        if (! $this->isFilledListOrText($outline) || ! $this->isFilledListOrText($visualPlan)) {
            return false;
        }

        $source = array_key_exists('source', $plan) ? $plan['source'] : 'human';
        if ($source === 'agent') {
            return EditorialActivity::query()
                ->where('attempt_id', $attempt->id)
                ->where('review_cycle', (int) $attempt->review_cycle)
                ->where('kind', EditorialActivityKind::Plan->value)
                ->where('status', EditorialActivityStatus::Completed->value)
                ->where('input_version', $attempt->input_version)
                ->get()
                ->contains(fn (EditorialActivity $activity): bool => $this->activityMatchesCurrentApproval($activity, $attempt, EditorialActivityKind::Plan));
        }

        return true;
    }

    private function activityMatchesCurrentApproval(EditorialActivity $activity, PublishingAttempt $attempt, EditorialActivityKind $kind): bool
    {
        $approval = StartEditorialActivity::prerequisiteApproval($kind, is_array($activity->input) ? $activity->input : []);

        if (! $approval instanceof ApprovalKind) {
            return true;
        }

        $input = $activity->getAttribute('input');
        $hashes = is_array($input) && is_array(data_get($input, 'approval_hashes')) ? data_get($input, 'approval_hashes') : [];
        $activityHash = $hashes[$approval->value] ?? null;
        $currentHash = $this->activeApprovalHash($attempt, $approval);

        return is_string($activityHash) && is_string($currentHash) && hash_equals($currentHash, $activityHash);
    }

    private function activeApprovalHash(PublishingAttempt $attempt, ApprovalKind $kind): ?string
    {
        $inputHash = $this->inputHashFor($attempt, $kind);

        $approval = EditorialApproval::query()
            ->where('attempt_id', $attempt->id)
            ->where('kind', $kind->value)
            ->where('input_hash', $inputHash)
            ->whereNull('invalidated_at')
            ->first();

        return $approval instanceof EditorialApproval ? (string) $approval->input_hash : null;
    }

    private function isFilledListOrText(mixed $value): bool
    {
        if (is_string($value)) {
            return trim($value) !== '';
        }

        return is_array($value) && $value !== [];
    }

    private function stageValue(PublishingAttempt $attempt): string
    {
        $stage = $attempt->getAttribute('stage');

        return $stage instanceof EditorialStage ? $stage->value : (string) $stage;
    }

    private function lockedAttempt(PublishingAttempt|int $attempt): PublishingAttempt
    {
        $attemptId = $attempt instanceof PublishingAttempt ? $attempt->getKey() : $attempt;

        return PublishingAttempt::query()
            ->whereKey($attemptId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $actor->can($permission)) {
            throw new AuthorizationException('This user is not allowed to approve publishing work.');
        }
    }
}
