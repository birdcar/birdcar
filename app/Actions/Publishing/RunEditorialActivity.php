<?php

namespace App\Actions\Publishing;

use App\Ai\Agents\EditorialAgent;
use App\Models\Article;
use App\Models\ArticleRelease;
use App\Models\ArticleRevision;
use App\Models\ContentIdea;
use App\Models\EditorialActivity;
use App\Models\EditorialApproval;
use App\Models\EditorialFinding;
use App\Models\EvidenceSource;
use App\Models\Publishing\ApprovalKind;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\PublishingAttempt;
use App\Models\User;
use App\Services\Publishing\DocumentBlocks;
use App\Services\Publishing\EditorialOutput;
use App\Services\Publishing\KitDocument;
use App\Services\Publishing\PublicSourceFetcher;
use App\Services\Publishing\PublishingFingerprint;
use App\Settings\PublishingAgentSettings;
use BackedEnum;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\FinishReason;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;
use Throwable;

class RunEditorialActivity implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const OUTPUT_LIMIT_ERROR = 'The agent reached its output limit before finishing its answer, so nothing was applied. This role needs a larger output allowance before the work is rerun.';

    public const MODEL_CONTINUITY_ERROR = 'The model that started this agent conversation cannot be identified, so the approval cannot continue without re-routing. Review the activity and start new work instead.';

    /** Agent work runs on its own queue so it can have a dedicated, long-running worker pool. */
    public const QUEUE = 'publishing-agents';

    /**
     * Above the provider request timeout plus research source fetches. Laravel Cloud Managed Queues honour a job's
     * own timeout, and a database queue's retry window must stay above it.
     */
    public int $timeout = 900;

    /** Activities own their retry policy; a redelivered paid request must never run again silently. */
    public int $tries = 1;

    public function __construct(public int $activityId)
    {
        $this->onQueue(self::QUEUE);
    }

    public function handle(EditorialOutput $prompts, WriteArticle $writer, ?PublicSourceFetcher $fetcher = null): void
    {
        $claim = $this->claimActivity();
        if ($claim === null) {
            return;
        }

        [$activity, $actor] = $claim;
        $continuation = false;
        $invoked = false;
        $responseReceived = false;

        try {
            if (! is_array(data_get($activity->input, 'canon'))) {
                throw new RuntimeException(EditorialActivity::PRE_CANON_PAUSE_REASON);
            }
            $kind = $this->kind($activity);
            $decisions = $this->decisionsFor($activity);
            $continuation = $decisions !== null;
            $configurationProblem = self::configurationProblem($activity);
            if ($configurationProblem !== null) {
                throw new RuntimeException($configurationProblem);
            }
            $agent = $continuation
                ? EditorialAgent::pinnedForActivity($activity, $this->pinnedExecution($activity))
                : EditorialAgent::forActivity($activity, app(PublishingAgentSettings::class)->modelOverrideFor($kind));
            $execution = $continuation ? $this->storedExecution($activity) : $agent->executionSnapshot();
            $conversationId = $this->conversationFor($activity, $actor);
            $agent->continue($conversationId, as: $actor);
            if (! $this->confirmBeforeInvocation($actor, $activity, $execution)) {
                return;
            }
            if ($decisions !== null && collect($activity->tool_decisions)->every(fn (array $decision): bool => $decision['action'] === 'reject')) {
                // The SDK records bare rejections in the conversation without another provider request.
                $agent->prompt($decisions);
                $activity->forceFill([
                    'status' => EditorialActivityStatus::Declined,
                    'pending_tool_approvals' => null,
                    'tool_decisions' => null,
                    'completed_at' => now(),
                ])->save();

                return;
            }
            $invoked = true;
            $agentResponse = $agent->prompt($decisions ?? $agent->promptText());
            $responseReceived = true;
            // The SDK decodes a truncated structured answer as an empty array, and retrying would repeat the truncation.
            if ($agentResponse->steps->last()?->finishReason === FinishReason::Length) {
                $this->pauseActivity($activity, self::OUTPUT_LIMIT_ERROR);

                return;
            }
            $response = $agentResponse->raw?->json() ?? [];
            if (! is_array($response)) {
                throw new RuntimeException('OpenRouter returned a malformed response.');
            }
            $generationId = $this->generationId($response);
            $execution = $this->withReturnedModel($execution, $agentResponse->meta->model);
            if ($agentResponse->hasPendingApprovals()) {
                $this->applyResult($activity, $actor, [], $writer, $generationId, $execution, [], $agentResponse);

                return;
            }
            if (! $agentResponse instanceof StructuredAgentResponse) {
                throw new RuntimeException('Agent response did not include structured output.');
            }
            $payload = $agentResponse->toArray();
            $frozenEvidence = $this->frozenEvidenceTextById($activity);
            $preparedEvidenceSources = [];
            $knownEvidenceIds = array_keys($frozenEvidence);
            $evidenceTexts = $frozenEvidence;
            if ($kind === EditorialActivityKind::ResearchChallenge) {
                $payload = $prompts->validateStructureBeforeRetrieval($kind, $payload, $knownEvidenceIds);
                $payload = $this->attachCitationAnnotations($payload, $response);
                $preparedEvidenceSources = $this->prepareEvidenceSources($payload, $fetcher ?? app(PublicSourceFetcher::class));
                $responseLocalEvidence = $this->responseLocalEvidenceTextByRef($payload, $preparedEvidenceSources);
                $knownEvidenceIds = [...$knownEvidenceIds, ...array_keys($responseLocalEvidence)];
                $evidenceTexts = $evidenceTexts + $responseLocalEvidence;
                // Retrieved page text now lives in the prepared sources; keeping it would push the model's own field past its size limit.
                $payload = $this->withoutRetrievedContent($payload);
            }
            $validated = $prompts->validate($kind, $payload, $knownEvidenceIds, $evidenceTexts, is_array($activity->input) ? $activity->input : []);
            if ($kind === EditorialActivityKind::Recheck) {
                $this->ensureRecheckAssessedEveryFinding($activity, $validated);
            }
            $this->applyResult($activity, $actor, $validated, $writer, $generationId, $execution, $preparedEvidenceSources);
        } catch (Throwable $throwable) {
            $this->handleFailure($activity, $throwable, $continuation, $invoked, $responseReceived);
        }
    }

    /**
     * @return array{EditorialActivity, User}|null
     */
    private function claimActivity(): ?array
    {
        if (app(PublishingAgentSettings::class)->paused) {
            return null;
        }

        /** @var array{EditorialActivity, User}|null $claim */
        $claim = DB::transaction(function (): ?array {
            $activity = EditorialActivity::query()->whereKey($this->activityId)->lockForUpdate()->first();
            if (! $activity instanceof EditorialActivity) {
                return null;
            }

            $status = $this->status($activity);
            if (! in_array($status, [EditorialActivityStatus::Pending, EditorialActivityStatus::Failed], true)) {
                return null;
            }

            $attempt = PublishingAttempt::query()->whereKey($activity->attempt_id)->lockForUpdate()->firstOrFail();
            $article = Article::query()->whereKey($activity->article_id)->lockForUpdate()->firstOrFail();
            $actor = $activity->initiator()->first();

            if (! $this->passesExecutionChecks($activity, $attempt, $article, $actor, 'The article revision changed before the activity could run.')) {
                return null;
            }

            $activity->forceFill([
                'status' => EditorialActivityStatus::Running,
                'started_at' => now(),
                'run_count' => ((int) $activity->run_count) + 1,
                'paused_at' => null,
                'pause_reason' => null,
                'error_reason' => null,
            ])->save();

            return [$activity->refresh(), $actor];
        });

        return $claim;
    }

    /**
     * Recheck ownership, permission, attempt state and frozen inputs immediately before the provider request,
     * then capture the execution choice. No transaction is held across the external call.
     *
     * @param  array<string, mixed>  $execution
     */
    private function confirmBeforeInvocation(User $actor, EditorialActivity $activity, array $execution): bool
    {
        /** @var bool $confirmed */
        $confirmed = DB::transaction(function () use ($actor, $activity, $execution): bool {
            $locked = EditorialActivity::query()->whereKey($activity->id)->lockForUpdate()->first();
            if (! $locked instanceof EditorialActivity || $this->status($locked) !== EditorialActivityStatus::Running) {
                return false;
            }

            $attempt = PublishingAttempt::query()->whereKey($locked->attempt_id)->lockForUpdate()->firstOrFail();
            $article = Article::query()->whereKey($locked->article_id)->lockForUpdate()->firstOrFail();
            $freshActor = User::query()->whereKey($actor->id)->first();

            if (! $this->passesExecutionChecks($locked, $attempt, $article, $freshActor, 'The article revision changed before the provider request.')) {
                return false;
            }

            $locked->forceFill(['model_snapshot' => $execution])->save();

            return true;
        });

        return $confirmed;
    }

    /** @phpstan-assert-if-true User $actor */
    private function passesExecutionChecks(EditorialActivity $activity, PublishingAttempt $attempt, Article $article, ?User $actor, string $revisionChangedReason): bool
    {
        if (! $actor instanceof User || ! $this->actorCanRunKind($actor, $this->kind($activity))) {
            $activity->forceFill([
                'status' => EditorialActivityStatus::Paused,
                'paused_at' => now(),
                'pause_reason' => 'The initiating actor can no longer run this publishing agent work.',
            ])->save();

            return false;
        }

        if ((int) ($article->current_attempt_id ?? 0) !== (int) $attempt->id || $attempt->paused_at !== null || $attempt->parked_at !== null || $attempt->abandoned_at !== null) {
            $activity->forceFill([
                'status' => EditorialActivityStatus::Paused,
                'paused_at' => now(),
                'pause_reason' => 'The publishing attempt is blocked or no longer current.',
            ])->save();

            return false;
        }

        if ($activity->revision_id !== null && (int) ($article->working_revision_id ?? 0) !== (int) $activity->revision_id) {
            $activity->forceFill([
                'status' => EditorialActivityStatus::Stale,
                'completed_at' => now(),
                'error_reason' => $revisionChangedReason,
            ])->save();

            return false;
        }

        $staleReason = $this->staleReason($activity, $attempt, $article);
        if ($staleReason !== null) {
            $activity->forceFill([
                'status' => EditorialActivityStatus::Stale,
                'completed_at' => now(),
                'error_reason' => $staleReason,
            ])->save();

            return false;
        }

        return true;
    }

    /**
     * The stable pause reason when current configuration prevents this activity from running, or null when it can run.
     * Approval continuations keep their pinned model, so only credentials apply to them.
     */
    public static function configurationProblem(EditorialActivity $activity): ?string
    {
        if ((string) config('ai.providers.openrouter.key', '') === '') {
            return EditorialActivity::MISSING_CREDENTIALS_PAUSE_REASON;
        }

        if (is_array($activity->tool_decisions) && $activity->tool_decisions !== []) {
            return null;
        }

        $override = app(PublishingAgentSettings::class)->modelOverrideFor($activity->kind);
        if ($override !== null && ! EditorialAgent::allowsModel($override)) {
            return EditorialActivity::INVALID_OVERRIDE_PAUSE_REASON;
        }

        if (! EditorialAgent::allowsModel(EditorialAgent::forActivity($activity, $override)->model())) {
            return EditorialActivity::UNSUPPORTED_RECOMMENDATION_PAUSE_REASON;
        }

        return null;
    }

    /**
     * @return array{model: string, reasoning_effort: string|null}
     */
    private function pinnedExecution(EditorialActivity $activity): array
    {
        $execution = $this->storedExecution($activity);
        $model = $execution['model'] ?? null;
        if (! is_string($model) || $model === '' || $model === EditorialAgent::AUTO_ROUTER) {
            throw new RuntimeException(self::MODEL_CONTINUITY_ERROR);
        }

        $effort = $execution['reasoning_effort'] ?? null;

        return ['model' => $model, 'reasoning_effort' => is_string($effort) ? $effort : null];
    }

    /**
     * Keep only execution identity from the stored snapshot; budget-era pricing and provider pins are ignored.
     *
     * @return array<string, mixed>
     */
    private function storedExecution(EditorialActivity $activity): array
    {
        $snapshot = is_array($activity->model_snapshot) ? $activity->model_snapshot : [];

        return array_intersect_key($snapshot, array_flip(['requested_model', 'model', 'returned_model', 'reasoning_effort']));
    }

    /**
     * @param  array<string, mixed>  $execution
     * @return array<string, mixed>
     */
    private function withReturnedModel(array $execution, ?string $returnedModel): array
    {
        $returnedModel = is_string($returnedModel) && trim($returnedModel) !== '' ? $returnedModel : null;
        $execution['returned_model'] = $returnedModel ?? ($execution['returned_model'] ?? null);

        if (($execution['model'] ?? null) === EditorialAgent::AUTO_ROUTER && $returnedModel !== null && $returnedModel !== EditorialAgent::AUTO_ROUTER) {
            $execution['model'] = $returnedModel;
        }

        return $execution;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $execution
     * @param  list<array<string, mixed>>  $preparedEvidenceSources
     */
    private function applyResult(EditorialActivity $activity, User $actor, array $payload, WriteArticle $writer, ?string $generationId, array $execution, array $preparedEvidenceSources, ?AgentResponse $agentResponse = null): void
    {
        DB::transaction(function () use ($activity, $actor, $payload, $writer, $generationId, $execution, $preparedEvidenceSources, $agentResponse): void {
            $locked = EditorialActivity::query()->whereKey($activity->id)->lockForUpdate()->firstOrFail();
            if ($this->status($locked) === EditorialActivityStatus::Completed) {
                return;
            }

            $kind = $this->kind($locked);
            $article = Article::query()->whereKey($locked->article_id)->lockForUpdate()->firstOrFail();
            $attempt = PublishingAttempt::query()->whereKey($locked->attempt_id)->lockForUpdate()->firstOrFail();
            $freshActor = $actor->fresh();
            if (! $freshActor instanceof User || ! $this->actorCanRunKind($freshActor, $kind)) {
                $locked->forceFill([
                    'status' => EditorialActivityStatus::Paused,
                    'paused_at' => now(),
                    'pause_reason' => 'The initiating actor can no longer apply publishing agent output.',
                    'generation_id' => $generationId,
                ])->save();

                return;
            }

            if ((int) ($article->current_attempt_id ?? 0) !== (int) $attempt->id || $attempt->paused_at !== null || $attempt->parked_at !== null || $attempt->abandoned_at !== null) {
                $locked->forceFill([
                    'status' => EditorialActivityStatus::Paused,
                    'response' => $payload,
                    'generation_id' => $generationId,
                    'paused_at' => now(),
                    'pause_reason' => 'The agent result returned after the publishing attempt was blocked or replaced.',
                ])->save();

                return;
            }

            if ($locked->revision_id !== null && (int) ($article->working_revision_id ?? 0) !== (int) $locked->revision_id) {
                $locked->forceFill([
                    'status' => EditorialActivityStatus::Stale,
                    'response' => $payload,
                    'generation_id' => $generationId,
                    'completed_at' => now(),
                    'error_reason' => 'The agent result targeted a stale revision.',
                ])->save();

                return;
            }

            $staleReason = $this->staleReason($locked, $attempt, $article);
            if ($staleReason !== null) {
                $locked->forceFill([
                    'status' => EditorialActivityStatus::Stale,
                    'response' => $payload,
                    'generation_id' => $generationId,
                    'completed_at' => now(),
                    'error_reason' => $staleReason,
                ])->save();

                return;
            }

            if ($agentResponse?->hasPendingApprovals()) {
                $pending = $agentResponse->pendingApprovals->map(fn ($approval): array => [
                    'id' => $approval->id,
                    'tool' => $approval->tool,
                    'arguments' => $approval->arguments,
                    'reason' => $approval->reason,
                ])->all();
                Validator::make(['approvals' => $pending], [
                    'approvals' => ['required', 'array', 'max:10'],
                    'approvals.*.id' => ['required', 'string', 'max:255', 'distinct'],
                    'approvals.*.tool' => ['required', 'in:AskAuthor'],
                    'approvals.*.arguments.questions' => ['required', 'array', 'min:1', 'max:10'],
                    'approvals.*.arguments.questions.*' => ['required', 'string', 'max:2000'],
                ])->validate();
                $continuable = is_string($execution['model'] ?? null) && $execution['model'] !== EditorialAgent::AUTO_ROUTER;
                $locked->forceFill([
                    'status' => $continuable ? EditorialActivityStatus::AwaitingApproval : EditorialActivityStatus::Paused,
                    'pending_tool_approvals' => $pending,
                    'tool_decisions' => null,
                    'model_snapshot' => $execution,
                    'generation_id' => $generationId,
                    'paused_at' => $continuable ? null : now(),
                    'pause_reason' => $continuable ? null : self::MODEL_CONTINUITY_ERROR,
                ])->save();

                return;
            }

            if ($kind === EditorialActivityKind::Draft) {
                $this->applyDraft($locked, $freshActor, $article, $payload, $writer);
            }

            if ($kind === EditorialActivityKind::LintRepair) {
                $this->applyLintRepair($locked, $freshActor, $article, $payload, $writer);
            }

            if ($kind === EditorialActivityKind::DraftKit) {
                $this->applyDraftKit($locked, $freshActor, $article, $payload, $writer);
            }

            if ($kind === EditorialActivityKind::ResearchChallenge) {
                $this->storeEvidenceAnnotations($locked, $preparedEvidenceSources);
            }

            if ($kind === EditorialActivityKind::Interview) {
                $this->applyInterview($locked, $attempt, $article, $payload);
            }

            if ($kind === EditorialActivityKind::Plan) {
                $this->applyPlan($locked, $attempt, $payload);
            }

            if ($kind === EditorialActivityKind::Package) {
                $this->applyPackage($locked, $payload);
            }

            if ($kind->isReviewLens() || $kind === EditorialActivityKind::Recheck || $kind === EditorialActivityKind::ReviewKit) {
                $this->storeFindings($locked, $payload, $kind);
            }

            if ($kind === EditorialActivityKind::Reconciliation) {
                $this->applyReconciliation($locked, $payload);
            }

            $locked->forceFill([
                'status' => EditorialActivityStatus::Completed,
                'pending_tool_approvals' => null,
                'tool_decisions' => null,
                'response' => $payload,
                'model_snapshot' => $execution,
                'generation_id' => $generationId,
                'completed_at' => now(),
            ])->save();

            $this->lintThenQueueReviews($locked, $freshActor);
            $this->queuePlanAfterCompletedResearch($locked, $freshActor);
            $this->queueReconciliationAfterCompletedReviewLens($locked, $freshActor);
            $this->queuePackageAfterCompletedRecheck($locked, $freshActor);
        });
    }

    /**
     * The brief is stored with snake_case keys. A null post type keeps the one the owner already picked, and the
     * interviewer's idea title never replaces a name the owner gave the idea, or an archive essay's published title.
     *
     * @param  array<string, mixed>  $payload
     */
    private function applyInterview(EditorialActivity $activity, PublishingAttempt $attempt, Article $article, array $payload): void
    {
        $returned = is_array($payload['brief'] ?? null) ? $payload['brief'] : [];
        $previous = $attempt->brief ?? [];
        $brief = array_diff_key($returned, ['postType' => true, 'targetQuery' => true, 'ideaTitle' => true]);
        $postType = is_string($returned['postType'] ?? null) ? $returned['postType'] : ($previous['post_type'] ?? null);
        $targetQuery = is_string($returned['targetQuery'] ?? null) ? trim($returned['targetQuery']) : '';
        $brief['post_type'] = is_string($postType) ? $postType : null;
        $brief['target_query'] = $targetQuery !== '' ? $targetQuery : null;

        $ideaTitle = is_string($returned['ideaTitle'] ?? null) ? trim($returned['ideaTitle']) : '';
        if ($ideaTitle !== '' && ! ArticleRelease::query()->where('article_id', $article->id)->where('origin', 'import')->exists()) {
            $idea = ContentIdea::query()->whereKey($article->content_idea_id)->lockForUpdate()->first();
            if ($idea instanceof ContentIdea && $idea->summary_title_source !== ContentIdea::SOURCE_OWNER) {
                $idea->forceFill(['summary_title' => $ideaTitle, 'summary_title_source' => ContentIdea::SOURCE_INTERVIEWER])->save();
            }
        }

        $contextValue = $attempt->getAttribute('interview_context');
        $context = is_array($contextValue) ? $contextValue : [];
        $context['latest_interview_activity_id'] = (int) $activity->id;
        $context['questions'] = $payload['questions'];
        $context['angle_options'] = $payload['angleOptions'];
        $answers = collect($activity->tool_decisions ?? [])->pluck('arguments.answers')->filter()->implode("\n\n");
        $context['answers'] = $answers !== '' ? $answers : null;
        $context['answered_at'] = $answers !== '' ? now()->toISOString() : null;
        $context['answered_interview_activity_id'] = $answers !== '' ? (int) $activity->id : null;
        $context['selected_angle_option'] = null;

        $attempt->forceFill([
            'brief' => $brief,
            'interview_context' => $context,
        ])->save();
    }

    /**
     * A re-plan ran with the owner's framework fixed, so that choice is recorded as chosen; a first plan waits for the
     * owner to keep the primary or pick another framework at the plan gate.
     *
     * @param  array<string, mixed>  $payload
     */
    private function applyPlan(EditorialActivity $activity, PublishingAttempt $attempt, array $payload): void
    {
        $framework = is_array($payload['framework'] ?? null) ? $payload['framework'] : [];
        $choice = data_get($activity->input, 'framework_choice');
        $secondary = is_array($framework['secondary'] ?? null) ? $framework['secondary'] : null;

        $attempt->forceFill([
            'plan' => [
                'outline' => $payload['outline'],
                'argument' => $payload['argument'],
                'visualPlan' => $payload['visualPlan'],
                'framework' => [
                    'primary' => $framework['primary'] ?? null,
                    'runner_up' => $framework['runner_up'] ?? null,
                    'reasons' => [
                        'primary' => data_get($framework, 'reasons.primary'),
                        'runner_up' => data_get($framework, 'reasons.runner_up'),
                    ],
                    'secondary' => $secondary === null ? null : [
                        'framework' => $secondary['framework'] ?? null,
                        'section_heading' => $secondary['section_heading'] ?? null,
                    ],
                    'chosen' => is_string($choice) && $choice !== '' ? $choice : null,
                ],
                'source' => 'agent',
                'activity_id' => $activity->id,
                'generated_at' => now()->toISOString(),
            ],
        ])->save();
    }

    /**
     * The package waits on the activity for the owner, as a proposal; nothing reaches the manuscript until accepted.
     *
     * @param  array<string, mixed>  $payload
     */
    private function applyPackage(EditorialActivity $activity, array $payload): void
    {
        $activity->forceFill(['proposal' => [
            'title_options' => array_values(array_map(static fn (mixed $option): string => trim((string) $option), is_array($payload['titleOptions'] ?? null) ? $payload['titleOptions'] : [])),
            'title' => trim((string) ($payload['title'] ?? '')),
            'seo_title' => trim((string) ($payload['seoTitle'] ?? '')),
            'description' => trim((string) ($payload['description'] ?? '')),
            'slug' => trim((string) ($payload['slug'] ?? '')),
            'greatest_hits_tag' => trim((string) ($payload['greatestHitsTag'] ?? '')),
        ]])->save();
    }

    /**
     * Article bodies use heading levels 2 and 3 because the title lives in metadata. A drafted level-1 heading is
     * demoted rather than discarding the whole paid draft over formatting.
     *
     * @param  array<mixed>  $node
     * @return array<mixed>
     */
    private function withArticleHeadingLevels(array $node): array
    {
        if (($node['type'] ?? null) === 'heading' && is_array($node['attrs'] ?? null) && is_int($node['attrs']['level'] ?? null)) {
            $node['attrs']['level'] = min(3, max(2, $node['attrs']['level']));
        }

        if (is_array($node['content'] ?? null)) {
            $node['content'] = array_map(fn (mixed $child): mixed => is_array($child) ? $this->withArticleHeadingLevels($child) : $child, $node['content']);
        }

        return $node;
    }

    /** @param array<string, mixed> $payload */
    private function applyDraft(EditorialActivity $activity, User $actor, Article $article, array $payload, WriteArticle $writer): void
    {
        $document = $this->withArticleHeadingLevels(is_array($payload['document'] ?? null) ? $payload['document'] : []);
        $currentRevision = $article->workingRevision()->first();
        $currentDocumentValue = $currentRevision?->getAttribute('document');
        $currentDocument = is_array($currentDocumentValue) ? $currentDocumentValue : ['version' => 1, 'type' => 'doc', 'content' => []];

        if ($this->isEmptyDocument($currentDocument)) {
            $currentMetadataValue = $currentRevision?->getAttribute('metadata');
            $currentMetadata = is_array($currentMetadataValue) ? $currentMetadataValue : [];
            $revision = $writer->save($actor, $article, $article->working_revision_id, $document, $currentMetadata, 'agent-initial-'.$activity->id, 'agent-initial');
            $activity->forceFill(['proposal' => ['applied_revision_id' => $revision->id]])->save();

            return;
        }

        $activity->forceFill(['proposal' => ['document' => $document, 'reason' => 'Current manuscript was not empty; human acceptance is required.']])->save();
    }

    /**
     * A kit draft becomes the piece's text when nobody has edited the piece since an agent last wrote it. Otherwise it
     * waits as a proposal, so a restart on a new flagship release never replaces the owner's words.
     *
     * @param  array<string, mixed>  $payload
     */
    private function applyDraftKit(EditorialActivity $activity, User $actor, Article $article, array $payload, WriteArticle $writer): void
    {
        $document = KitDocument::document($article->kind, $payload);
        $current = $article->workingRevision()->first();
        if ($current instanceof ArticleRevision && ! in_array((string) $current->origin, ['agent-initial', 'agent-repair'], true)) {
            $activity->forceFill(['proposal' => ['document' => $document, 'reason' => 'The piece has your edits, so this draft from the flagship release waits for you to use it.']])->save();

            return;
        }

        $metadata = $current?->getAttribute('metadata');
        $revision = $writer->save($actor, $article, $article->working_revision_id, $document, is_array($metadata) ? $metadata : [], 'agent-initial-'.$activity->id, 'agent-initial');
        $activity->forceFill(['proposal' => ['applied_revision_id' => $revision->id]])->save();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function attachCitationAnnotations(array $payload, array $response): array
    {
        $references = is_array($payload['sourceReferences'] ?? null) ? $payload['sourceReferences'] : [];
        if ($references === []) {
            return $payload;
        }

        $annotations = data_get($response, 'choices.0.message.annotations', []);
        $byUrl = [];
        if (is_array($annotations)) {
            foreach ($annotations as $annotation) {
                if (! is_array($annotation)) {
                    continue;
                }
                $citation = is_array($annotation['url_citation'] ?? null) ? $annotation['url_citation'] : $annotation;
                $url = is_string($citation['url'] ?? null) ? (string) $citation['url'] : null;
                $content = is_string($citation['content'] ?? null) ? (string) $citation['content'] : null;
                if ($url !== null && $url !== '' && $content !== null && trim($content) !== '') {
                    $byUrl[$url] = $content;
                }
            }
        }

        foreach ($references as $index => $reference) {
            if (! is_array($reference)) {
                continue;
            }
            $url = is_string($reference['url'] ?? null) ? (string) $reference['url'] : null;
            if ($url !== null && isset($byUrl[$url])) {
                $references[$index]['retrieved_content'] = $byUrl[$url];
            }
        }

        $payload['sourceReferences'] = $references;

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function prepareEvidenceSources(array $payload, PublicSourceFetcher $fetcher): array
    {
        $references = is_array($payload['sourceReferences'] ?? null) ? $payload['sourceReferences'] : [];
        $sources = [];

        foreach ($references as $reference) {
            if (! is_array($reference)) {
                continue;
            }

            $url = is_string($reference['url'] ?? null) ? (string) $reference['url'] : null;
            $text = is_string($reference['retrieved_content'] ?? null) ? (string) $reference['retrieved_content'] : null;
            $finalUrl = $url;
            $contentHash = $text === null ? null : hash('sha256', $text);
            $method = 'openrouter-web';
            $unresolved = $text === null ? 'OpenRouter citation did not include retrieved content.' : null;

            if (($text === null || trim($text) === '') && $url !== null && $url !== '') {
                try {
                    $fetched = $fetcher->fetch($url);
                    $text = $fetched['extracted_text'];
                    $finalUrl = $fetched['final_url'];
                    $contentHash = $fetched['content_hash'];
                    $method = 'public-fetch';
                    $unresolved = $fetched['unresolved_reason'];
                } catch (Throwable $throwable) {
                    $text = null;
                    $finalUrl = $url;
                    $contentHash = null;
                    $method = 'public-fetch';
                    $unresolved = 'Public fetch could not retrieve this source: '.$throwable->getMessage();
                }
            }

            $sources[] = [
                'url' => $url,
                'final_url' => $finalUrl,
                'title' => is_string($reference['title'] ?? null) ? (string) $reference['title'] : null,
                'retrieval_method' => $method,
                'extracted_text' => $text,
                'content_hash' => $contentHash,
                'origin_metadata' => $this->sourceOriginMetadata($reference),
                'unresolved_reason' => $unresolved,
            ];
        }

        return $sources;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<array<string, mixed>>  $preparedSources
     * @return array<string, string|null>
     */
    private function responseLocalEvidenceTextByRef(array $payload, array $preparedSources): array
    {
        $references = is_array($payload['sourceReferences'] ?? null) ? array_values($payload['sourceReferences']) : [];
        $texts = [];

        foreach ($references as $index => $reference) {
            if (! is_array($reference)) {
                continue;
            }

            $prepared = $preparedSources[$index] ?? [];
            $text = is_string($prepared['extracted_text'] ?? null) ? (string) $prepared['extracted_text'] : null;
            foreach ($this->responseLocalReferenceKeys($reference, $index) as $key) {
                $texts[$key] = $text;
            }
        }

        return $texts;
    }

    /**
     * @param  array<string, mixed>  $reference
     * @return list<string>
     */
    private function responseLocalReferenceKeys(array $reference, int $index): array
    {
        $keys = ['sourceReferences.'.$index, 'source:'.$index, (string) $index];
        foreach (['id', 'local_id', 'localId', 'ref', 'reference', 'source_ref', 'sourceRef'] as $field) {
            $value = $reference[$field] ?? null;
            if (is_scalar($value) && trim((string) $value) !== '') {
                $keys[] = (string) $value;
            }
        }
        $url = $reference['url'] ?? null;
        if (is_string($url) && $url !== '') {
            $keys[] = $url;
        }

        return array_values(array_unique($keys));
    }

    /**
     * An empty or partial recheck would read as "nothing unresolved", so every frozen finding must be classified,
     * by its ID or by the block it concerns.
     *
     * @param  array<string, mixed>  $payload
     */
    private function ensureRecheckAssessedEveryFinding(EditorialActivity $activity, array $payload): void
    {
        $input = is_array($activity->input) ? $activity->input : [];
        $findings = is_array($input['review_findings'] ?? null) ? $input['review_findings'] : [];
        $assessed = collect([...($payload['resolved'] ?? []), ...($payload['unresolved'] ?? [])])->filter(fn (mixed $item): bool => is_array($item));
        $assessedIds = $assessed->pluck('finding_id')->filter(fn (mixed $id): bool => is_int($id))->all();
        $assessedBlocks = $assessed->pluck('block_id')->filter(fn (mixed $id): bool => is_string($id) && $id !== '')->all();

        foreach ($findings as $finding) {
            if (! is_array($finding)) {
                continue;
            }
            $covered = in_array($finding['id'] ?? null, $assessedIds, true)
                || (is_string($finding['block_id'] ?? null) && in_array($finding['block_id'], $assessedBlocks, true));
            if (! $covered) {
                throw new RuntimeException('The recheck did not assess every review finding, so its result was not applied.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withoutRetrievedContent(array $payload): array
    {
        $references = is_array($payload['sourceReferences'] ?? null) ? $payload['sourceReferences'] : [];
        foreach ($references as $index => $reference) {
            if (is_array($reference)) {
                unset($references[$index]['retrieved_content']);
            }
        }
        $payload['sourceReferences'] = $references;

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $reference
     * @return array<string, mixed>
     */
    private function sourceOriginMetadata(array $reference): array
    {
        unset($reference['content'], $reference['retrieved_content'], $reference['extracted_text']);

        return $reference;
    }

    /**
     * @return array<int, string|null>
     */
    private function frozenEvidenceTextById(EditorialActivity $activity): array
    {
        $input = $activity->getAttribute('input');
        $sources = is_array($input) && is_array($input['evidence_sources'] ?? null) ? $input['evidence_sources'] : [];
        $byId = [];

        foreach ($sources as $source) {
            if (! is_array($source)) {
                continue;
            }
            $id = $source['id'] ?? null;
            if (! is_int($id)) {
                continue;
            }
            $text = $source['extracted_text'] ?? null;
            $byId[$id] = is_string($text) ? $text : null;
        }

        return $byId;
    }

    /** @param list<array<string, mixed>> $preparedSources */
    private function storeEvidenceAnnotations(EditorialActivity $activity, array $preparedSources): void
    {
        foreach ($preparedSources as $source) {
            EvidenceSource::create([
                'article_id' => $activity->article_id,
                'attempt_id' => $activity->attempt_id,
                'activity_id' => $activity->id,
                'source_type' => 'public',
                'url' => $source['url'] ?? null,
                'final_url' => $source['final_url'] ?? null,
                'title' => $source['title'] ?? null,
                'retrieved_at' => now(),
                'retrieval_method' => $source['retrieval_method'] ?? 'openrouter-web',
                'extracted_text' => $source['extracted_text'] ?? null,
                'content_hash' => $source['content_hash'] ?? null,
                'origin_metadata' => $source['origin_metadata'] ?? [],
                'unresolved_reason' => $source['unresolved_reason'] ?? null,
            ]);
        }
    }

    /** @param array<string, mixed> $payload */
    private function storeFindings(EditorialActivity $activity, array $payload, EditorialActivityKind $kind): void
    {
        $findings = is_array($payload['findings'] ?? null) ? $payload['findings'] : (is_array($payload['newBlockingFindings'] ?? null) ? $payload['newBlockingFindings'] : []);
        $limit = (int) config('publishing_agents.limits.max_findings_per_activity', 25);
        foreach (array_slice($findings, 0, $limit) as $finding) {
            if (! is_array($finding)) {
                continue;
            }
            EditorialFinding::create([
                'article_id' => $activity->article_id,
                'attempt_id' => $activity->attempt_id,
                'activity_id' => $activity->id,
                'review_cycle' => $activity->review_cycle,
                'revision_id' => $activity->revision_id,
                'input_hash' => $activity->revision_hash,
                'lens' => $kind->value,
                'kind' => is_string($finding['kind'] ?? null) ? (string) $finding['kind'] : 'editorial',
                'severity' => is_string($finding['severity'] ?? null) ? (string) $finding['severity'] : 'advisory',
                'block_id' => is_string($finding['block_id'] ?? null) ? (string) $finding['block_id'] : null,
                'expected_subtree_hash' => is_string($finding['expected_subtree_hash'] ?? null) ? (string) $finding['expected_subtree_hash'] : null,
                'statement' => (string) ($finding['statement'] ?? 'Editorial finding'),
                'rationale' => is_string($finding['rationale'] ?? null) ? (string) $finding['rationale'] : null,
                'supporting_source_ids' => is_array($finding['supporting_source_ids'] ?? null) ? $finding['supporting_source_ids'] : [],
                'supporting_quotations' => is_array($finding['supporting_quotations'] ?? null) ? $finding['supporting_quotations'] : [],
                'proposed_patch' => is_array($finding['proposed_patch'] ?? null) ? $finding['proposed_patch'] : null,
            ]);
        }
    }

    /** @param array<string, mixed> $payload */
    private function applyReconciliation(EditorialActivity $activity, array $payload): void
    {
        $groups = is_array($payload['groups'] ?? null) ? $payload['groups'] : [];
        foreach ($groups as $index => $group) {
            if (! is_array($group)) {
                continue;
            }

            $ids = $this->findingIdsFromReconciliationItem($group);
            if ($ids === []) {
                continue;
            }

            $canonicalId = $this->canonicalFindingId($group, $ids);
            foreach ($ids as $id) {
                $finding = $this->lockReconciliationFinding($activity, $id);
                if (! $finding instanceof EditorialFinding) {
                    continue;
                }

                $isCanonical = (int) $finding->id === $canonicalId;
                $finding->forceFill([
                    'reconciliation_state' => $isCanonical ? 'representative' : 'duplicate',
                    'reconciliation_group' => 'group-'.($index + 1),
                    'reconciled_into_finding_id' => $isCanonical ? null : $canonicalId,
                    'reconciliation_payload' => $group,
                    'stale_at' => $isCanonical ? $finding->stale_at : ($finding->stale_at ?? now()),
                ])->save();
            }
        }

        $conflicts = is_array($payload['conflicts'] ?? null) ? $payload['conflicts'] : [];
        foreach ($conflicts as $index => $conflict) {
            if (! is_array($conflict)) {
                continue;
            }

            foreach ($this->findingIdsFromReconciliationItem($conflict) as $id) {
                $finding = $this->lockReconciliationFinding($activity, $id);
                if (! $finding instanceof EditorialFinding) {
                    continue;
                }

                $finding->forceFill([
                    'reconciliation_state' => 'conflict',
                    'reconciliation_group' => 'conflict-'.($index + 1),
                    'reconciled_into_finding_id' => null,
                    'reconciliation_payload' => $conflict,
                ])->save();
            }
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<int>
     */
    private function findingIdsFromReconciliationItem(array $item): array
    {
        $raw = $item['finding_ids'] ?? $item['findingIds'] ?? $item['findings'] ?? [];
        if (! is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $value) {
            if (is_array($value)) {
                $value = $value['id'] ?? $value['finding_id'] ?? null;
            }
            if (is_numeric($value) && (int) $value > 0) {
                $ids[] = (int) $value;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<string, mixed>  $group
     * @param  list<int>  $ids
     */
    private function canonicalFindingId(array $group, array $ids): int
    {
        $candidate = $group['canonical_finding_id'] ?? $group['primary_finding_id'] ?? $group['representative_finding_id'] ?? $ids[0];

        return is_numeric($candidate) && in_array((int) $candidate, $ids, true) ? (int) $candidate : $ids[0];
    }

    private function lockReconciliationFinding(EditorialActivity $activity, int $findingId): ?EditorialFinding
    {
        $finding = EditorialFinding::query()->whereKey($findingId)->lockForUpdate()->first();
        if (! $finding instanceof EditorialFinding) {
            return null;
        }

        if ((int) $finding->attempt_id !== (int) $activity->attempt_id
            || (int) $finding->article_id !== (int) $activity->article_id
            || (int) $finding->review_cycle !== (int) $activity->review_cycle
            || (int) ($finding->revision_id ?? 0) !== (int) ($activity->revision_id ?? 0)) {
            return null;
        }

        return $finding;
    }

    private function handleFailure(EditorialActivity $activity, Throwable $throwable, bool $continuation, bool $invoked, bool $responseReceived): void
    {
        if (! $invoked) {
            $this->pauseActivity($activity, $throwable->getMessage());

            return;
        }

        if (! $responseReceived) {
            $status = $this->providerStatus($throwable);
            if ($status === 429 && ! $continuation) {
                $this->failOrPause($activity, 'OpenRouter rate limited the request (HTTP 429) before generating output.');

                return;
            }

            if ($status !== null && in_array($status, [400, 401, 402, 403, 404, 422], true)) {
                $this->rejectActivity($activity, $this->rejectionReason($status));

                return;
            }

            $this->pauseActivity($activity, $continuation
                ? 'The approval continuation did not complete; its provider outcome is uncertain. Review the activity before starting new work.'
                : 'The provider request did not complete (timeout, disconnect or provider error), so its outcome is uncertain. Review the activity before rerunning it.');

            return;
        }

        if ($continuation) {
            $this->pauseActivity($activity, 'The approval continuation returned unusable output: '.$throwable->getMessage().' Review the activity instead of replaying the answered request.');

            return;
        }

        $this->failOrPause($activity, $throwable->getMessage());

        if ($this->kind($activity) === EditorialActivityKind::LintRepair) {
            $this->finishLintAfterUnusableRepair($activity);
        }
    }

    private function providerStatus(Throwable $throwable): ?int
    {
        do {
            if ($throwable instanceof RequestException && $throwable->response !== null) {
                return $throwable->response->status();
            }
            $throwable = $throwable->getPrevious();
        } while ($throwable !== null);

        return null;
    }

    private function rejectionReason(int $status): string
    {
        return match ($status) {
            401, 403 => 'OpenRouter rejected the request (HTTP '.$status.'). Check the OpenRouter API key and its permissions.',
            402 => 'OpenRouter rejected the request (HTTP 402). The OpenRouter key or workspace has no remaining credit or limit.',
            default => 'OpenRouter rejected the request (HTTP '.$status.'). Check that the selected model is available and supports the requested parameters.',
        };
    }

    private function failOrPause(EditorialActivity $activity, string $reason): void
    {
        DB::transaction(function () use ($activity, $reason): void {
            $locked = EditorialActivity::query()->whereKey($activity->id)->lockForUpdate()->first();
            if (! $locked instanceof EditorialActivity || $this->status($locked) === EditorialActivityStatus::Completed) {
                return;
            }
            $runs = (int) $locked->run_count;
            $retryLimit = (int) config('publishing_agents.limits.max_retries', 2);
            $status = $runs <= $retryLimit ? EditorialActivityStatus::Failed : EditorialActivityStatus::Paused;
            $locked->forceFill([
                'status' => $status,
                'paused_at' => $status === EditorialActivityStatus::Paused ? now() : null,
                'pause_reason' => $status === EditorialActivityStatus::Paused ? $reason : null,
                'error_reason' => $reason,
                'available_at' => now()->addMinute(),
            ])->save();
        });
    }

    private function rejectActivity(EditorialActivity $activity, string $reason): void
    {
        EditorialActivity::query()->whereKey($activity->id)->update([
            'status' => EditorialActivityStatus::Paused->value,
            'paused_at' => now(),
            'pause_reason' => $reason,
            'error_reason' => $reason,
        ]);
    }

    private function pauseActivity(EditorialActivity $activity, string $reason): void
    {
        EditorialActivity::query()->whereKey($activity->id)->update([
            'status' => EditorialActivityStatus::Paused->value,
            'paused_at' => now(),
            'pause_reason' => $reason,
        ]);
    }

    private function actorCanRunKind(User $actor, EditorialActivityKind $kind): bool
    {
        if (! $actor->can('publishing.develop')) {
            return false;
        }

        return ! in_array($kind, [EditorialActivityKind::Draft, EditorialActivityKind::DraftKit, EditorialActivityKind::LintRepair], true) || $actor->can('publishing.write');
    }

    /**
     * After a draft lands in an empty manuscript (or a kit draft lands as the piece's text), and after each repair pass,
     * lint the working revision. Repairable hits get another repair while fewer than the cap have run and the last pass
     * changed something; otherwise the remaining hits become findings and review queues on the revision.
     */
    private function lintThenQueueReviews(EditorialActivity $activity, User $actor): void
    {
        $fresh = $activity->fresh();
        if (! $fresh instanceof EditorialActivity || $this->status($fresh) !== EditorialActivityStatus::Completed) {
            return;
        }

        $kind = $this->kind($fresh);
        $proposal = $fresh->getAttribute('proposal');
        $proposal = is_array($proposal) ? $proposal : [];
        if ($kind === EditorialActivityKind::Draft || $kind === EditorialActivityKind::DraftKit) {
            if (! isset($proposal['applied_revision_id'])) {
                return;
            }
            $draftActivityId = (int) $fresh->id;
        } elseif ($kind === EditorialActivityKind::LintRepair) {
            $draftId = data_get($fresh->input, 'draft_activity_id');
            $draftActivityId = is_numeric($draftId) ? (int) $draftId : null;
        } else {
            return;
        }

        $attempt = $fresh->attempt()->first();
        $article = $fresh->article()->first();
        $revision = $article?->workingRevision()->first();
        if (! $attempt instanceof PublishingAttempt || ! $article instanceof Article || ! $revision instanceof ArticleRevision) {
            return;
        }

        $lint = app(LintRevision::class);
        $repairInput = $lint->repairInput($revision, $lint->repairable($lint->hits($article, $revision)));
        $passChangedNothing = $kind === EditorialActivityKind::LintRepair && ! isset($proposal['applied_revision_id']);
        if ($repairInput['hits'] !== [] && ! $passChangedNothing && $lint->completedRepairs($attempt) < LintRevision::REPAIR_CAP) {
            app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::LintRepair, [...$repairInput, 'draft_activity_id' => $draftActivityId], 'lint-repair-'.$attempt->review_cycle.'-'.$revision->id);

            return;
        }

        $lint->finishAndQueueReviews($actor, $attempt, $revision, $draftActivityId);
    }

    /**
     * A repair whose output could not be used still ends the lint step: its revision's hits become findings and review
     * starts. The repair goes stale with its reason, so it is neither retried under the running review nor shown as
     * waiting. A provider failure does not reach here; the owner retries or skips that repair.
     */
    private function finishLintAfterUnusableRepair(EditorialActivity $activity): void
    {
        try {
            DB::transaction(function () use ($activity): void {
                $locked = EditorialActivity::query()->whereKey($activity->id)->lockForUpdate()->first();
                if (! $locked instanceof EditorialActivity || ! in_array($this->status($locked), [EditorialActivityStatus::Failed, EditorialActivityStatus::Paused], true)) {
                    return;
                }

                $attempt = PublishingAttempt::query()->whereKey($locked->attempt_id)->lockForUpdate()->firstOrFail();
                $article = Article::query()->whereKey($locked->article_id)->lockForUpdate()->firstOrFail();
                $revision = $article->workingRevision()->first();
                $actor = $locked->initiator()->first();
                if ((int) ($article->current_attempt_id ?? 0) !== (int) $attempt->id
                    || $attempt->paused_at !== null || $attempt->parked_at !== null || $attempt->abandoned_at !== null
                    || ! $revision instanceof ArticleRevision || (int) $revision->id !== (int) $locked->revision_id
                    || ! $actor instanceof User || ! $this->actorCanRunKind($actor, EditorialActivityKind::LintRepair)) {
                    return;
                }

                $draftId = data_get($locked->input, 'draft_activity_id');
                app(LintRevision::class)->finishAndQueueReviews($actor, $attempt, $revision, is_numeric($draftId) ? (int) $draftId : null);

                $locked->forceFill([
                    'status' => EditorialActivityStatus::Stale,
                    'completed_at' => now(),
                    'paused_at' => null,
                    'pause_reason' => null,
                    'error_reason' => trim(((string) $locked->error_reason).' Review started without this repair.'),
                ])->save();
            });
        } catch (Throwable $throwable) {
            report($throwable);
        }
    }

    /**
     * Applies repair patches only to blocks an agent wrote and nobody protected. A patch whose expected hash no longer
     * matches is skipped and its hit stays; a patch for an owner-written or protected block is kept for the owner.
     *
     * @param  array<string, mixed>  $payload
     */
    private function applyLintRepair(EditorialActivity $activity, User $actor, Article $article, array $payload, WriteArticle $writer): void
    {
        $base = $article->workingRevision()->first();
        if (! $base instanceof ArticleRevision) {
            throw new RuntimeException('Voice-rule repair needs the current revision.');
        }

        $blocks = app(DocumentBlocks::class);
        $fingerprint = app(PublishingFingerprint::class);
        $documentValue = $base->getAttribute('document');
        $metadataValue = $base->getAttribute('metadata');
        $document = is_array($documentValue) ? $documentValue : [];
        $agentWritten = $this->agentWrittenBlockHashes($activity);
        $applied = [];
        $unapplied = [];
        $skipped = [];

        foreach (is_array($payload['patches'] ?? null) ? $payload['patches'] : [] as $patch) {
            if (! is_array($patch) || ! is_string($patch['block_id'] ?? null) || ! is_string($patch['expected_hash'] ?? null) || ! is_array($patch['replacement'] ?? null)) {
                continue;
            }

            $blockId = $patch['block_id'];
            $match = $blocks->find($document, $blockId);
            $currentHash = $match === null ? null : $fingerprint->hash($match['node']);
            if ($match === null || $currentHash === null || ! hash_equals($patch['expected_hash'], $currentHash)) {
                $skipped[] = $blockId;

                continue;
            }

            $record = ['block_id' => $blockId, 'expected_hash' => $currentHash, 'replacement' => $patch['replacement']];
            $agentAuthored = ! $match['protected'] && ($agentWritten[$blockId] ?? null) === $currentHash;
            $overlapsApplied = collect($applied)->contains(fn (array $other): bool => $blocks->overlaps($other['path'], $match['path']));
            if (! $agentAuthored || $overlapsApplied) {
                $unapplied[$blockId] = $record;

                continue;
            }

            $applied[] = ['block_id' => $blockId, 'path' => $match['path'], 'replacement' => $patch['replacement']];
        }

        $appliedRevisionId = null;
        if ($applied !== []) {
            foreach ($applied as $patch) {
                $document = $blocks->replace($document, $patch['path'], $patch['replacement']);
            }
            $revision = $writer->save($actor, $article, (int) $base->id, $document, is_array($metadataValue) ? $metadataValue : [], 'agent-repair-'.$activity->id, 'agent-repair');
            $appliedRevisionId = (int) $revision->id;
        }

        $activity->forceFill(['proposal' => [
            'applied_revision_id' => $appliedRevisionId,
            'applied_block_ids' => array_column($applied, 'block_id'),
            'unapplied_patches' => $unapplied,
            'skipped_block_ids' => $skipped,
        ]])->save();
    }

    /**
     * Each block's subtree hash as an agent last wrote it in this attempt: the whole applied draft, then each block a
     * completed repair changed, later writes replacing earlier ones. A block whose hash differs was written by the
     * owner, including an owner who restored text an agent wrote before its latest pass.
     *
     * @return array<string, string>
     */
    private function agentWrittenBlockHashes(EditorialActivity $repair): array
    {
        $fingerprint = app(PublishingFingerprint::class);
        $blocks = app(DocumentBlocks::class);
        $written = [];
        $record = function (array $node) use (&$record, &$written, $fingerprint): void {
            $id = data_get($node, 'attrs.id');
            if (is_string($id)) {
                $written[$id] = $fingerprint->hash($node);
            }
            foreach (is_array($node['content'] ?? null) ? $node['content'] : [] as $child) {
                if (is_array($child)) {
                    $record($child);
                }
            }
        };

        $activities = EditorialActivity::query()
            ->where('attempt_id', $repair->attempt_id)
            ->whereIn('kind', [EditorialActivityKind::Draft->value, EditorialActivityKind::DraftKit->value, EditorialActivityKind::LintRepair->value])
            ->where('status', EditorialActivityStatus::Completed->value)
            ->orderBy('id')
            ->get(['id', 'kind', 'proposal']);

        foreach ($activities as $activity) {
            $revisionId = data_get($activity->getAttribute('proposal'), 'applied_revision_id');
            $revision = is_numeric($revisionId) ? ArticleRevision::query()->where('article_id', $repair->article_id)->whereKey((int) $revisionId)->first() : null;
            $documentValue = $revision?->getAttribute('document');
            if (! is_array($documentValue)) {
                continue;
            }

            if ($this->kind($activity) !== EditorialActivityKind::LintRepair) {
                foreach (is_array($documentValue['content'] ?? null) ? $documentValue['content'] : [] as $node) {
                    if (is_array($node)) {
                        $record($node);
                    }
                }

                continue;
            }

            $changed = data_get($activity->getAttribute('proposal'), 'applied_block_ids');
            foreach (is_array($changed) ? $changed : [] as $blockId) {
                $match = is_string($blockId) ? $blocks->find($documentValue, $blockId) : null;
                if ($match !== null) {
                    $record($match['node']);
                }
            }
        }

        return $written;
    }

    private function queuePlanAfterCompletedResearch(EditorialActivity $activity, User $actor): void
    {
        $fresh = $activity->fresh();
        if (! $fresh instanceof EditorialActivity || $this->kind($fresh) !== EditorialActivityKind::ResearchChallenge || $this->status($fresh) !== EditorialActivityStatus::Completed) {
            return;
        }

        $attempt = $fresh->attempt()->first();
        if ($attempt instanceof PublishingAttempt) {
            app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::Plan, ['research_activity_id' => $fresh->id], 'plan-'.$attempt->review_cycle);
        }
    }

    private function queueReconciliationAfterCompletedReviewLens(EditorialActivity $activity, User $actor): void
    {
        $fresh = $activity->fresh();
        if (! $fresh instanceof EditorialActivity || ! $this->kind($fresh)->isReviewLens() || $this->status($fresh) !== EditorialActivityStatus::Completed) {
            return;
        }

        $attempt = $fresh->attempt()->first();
        if (! $attempt instanceof PublishingAttempt) {
            return;
        }

        $revisionId = (int) ($fresh->revision_id ?? 0);
        if ($revisionId === 0) {
            return;
        }

        foreach ([EditorialActivityKind::ReviewFacts, EditorialActivityKind::ReviewVoice, EditorialActivityKind::ReviewBuyer] as $kind) {
            $completed = EditorialActivity::query()
                ->where('attempt_id', $attempt->id)
                ->where('review_cycle', (int) $attempt->review_cycle)
                ->where('kind', $kind->value)
                ->where('status', EditorialActivityStatus::Completed->value)
                ->where('revision_id', $revisionId)
                ->exists();
            if (! $completed) {
                return;
            }
        }

        $alreadyScheduled = EditorialActivity::query()
            ->where('attempt_id', $attempt->id)
            ->where('review_cycle', (int) $attempt->review_cycle)
            ->where('kind', EditorialActivityKind::Reconciliation->value)
            ->whereIn('status', [EditorialActivityStatus::Pending->value, EditorialActivityStatus::Running->value, EditorialActivityStatus::Completed->value])
            ->where('revision_id', $revisionId)
            ->exists();
        if ($alreadyScheduled) {
            return;
        }

        app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::Reconciliation, ['expected_revision_id' => $revisionId], 'reconciliation-'.$attempt->review_cycle.'-'.$revisionId);
    }

    /**
     * A completed recheck ends review, so packaging starts on its revision. Any recheck outcome qualifies: unresolved
     * items still block release, and the owner may want the package while fixing them.
     */
    private function queuePackageAfterCompletedRecheck(EditorialActivity $activity, User $actor): void
    {
        $fresh = $activity->fresh();
        if (! $fresh instanceof EditorialActivity || $this->kind($fresh) !== EditorialActivityKind::Recheck || $this->status($fresh) !== EditorialActivityStatus::Completed) {
            return;
        }

        $attempt = $fresh->attempt()->first();
        $article = $fresh->article()->first();
        $revision = $article?->workingRevision()->first();
        if (! $attempt instanceof PublishingAttempt || ! $article instanceof Article || ! $revision instanceof ArticleRevision || ! $actor->can('publishing.develop')) {
            return;
        }

        $input = ['reviewed_revision_id' => (int) data_get($fresh->input, 'reviewed_revision_id', $revision->id), 'expected_revision_id' => (int) $revision->id];
        $starter = app(StartEditorialActivity::class);
        if ($starter->packageEligibilityProblem($attempt, $article, $revision, $input) === null) {
            $starter->start($actor, $attempt, EditorialActivityKind::Package, $input, StartEditorialActivity::packageBatchKey($attempt, (int) $revision->id, afterRecheck: true));
        }
    }

    private function staleReason(EditorialActivity $activity, PublishingAttempt $attempt, Article $article): ?string
    {
        if ((int) ($activity->input_version ?? 0) !== (int) ($attempt->input_version ?? 0)) {
            return 'The activity input version changed before the activity could run.';
        }

        if ((string) $activity->stage !== $this->stageValue($attempt)) {
            return 'The editorial stage changed before the activity could run.';
        }

        $revisionHash = $activity->revision_hash;
        if ($activity->revision_id !== null && $revisionHash !== null) {
            $revision = $article->workingRevision()->first();
            if ($revision === null || ! hash_equals((string) $revision->content_hash, (string) $revisionHash)) {
                return 'The article revision fingerprint changed before the activity could run.';
            }
        }

        $kind = $this->kind($activity);
        $inputValue = $activity->getAttribute('input');
        $input = is_array($inputValue) ? $inputValue : [];
        if ($kind === EditorialActivityKind::Plan) {
            $frozenPlanValue = data_get($input, 'plan', []);
            $frozenPlan = is_array($frozenPlanValue) ? $frozenPlanValue : [];
            $currentPlan = $attempt->plan ?? [];
            $frozenPlanHash = app(PublishingFingerprint::class)->hash($frozenPlan);
            $currentPlanHash = app(PublishingFingerprint::class)->hash($currentPlan);
            if (! hash_equals($frozenPlanHash, $currentPlanHash)) {
                return 'The plan input changed before the plan result could be applied.';
            }
        }

        $flagshipReleaseId = data_get($input, 'flagship.release_id');
        if ($flagshipReleaseId !== null && (int) $flagshipReleaseId !== (int) data_get($attempt->brief, 'flagship_release_id')) {
            return 'The flagship release changed before the activity could run.';
        }

        $approval = StartEditorialActivity::prerequisiteApproval($kind, $input);
        if (! $approval instanceof ApprovalKind) {
            return null;
        }

        $frozenHash = data_get($input, 'approval_hashes.'.$approval->value);
        $currentHash = $this->activeApprovalHash($attempt, $approval);
        if (! is_string($frozenHash) || ! is_string($currentHash) || ! hash_equals($currentHash, $frozenHash)) {
            return 'The prerequisite approval changed before the activity could run.';
        }

        return null;
    }

    private function activeApprovalHash(PublishingAttempt $attempt, ApprovalKind $kind): ?string
    {
        $hash = $this->approvalInputHashFor($attempt, $kind);
        $approval = EditorialApproval::query()
            ->where('attempt_id', $attempt->id)
            ->where('kind', $kind->value)
            ->where('input_hash', $hash)
            ->whereNull('invalidated_at')
            ->first();

        return $approval instanceof EditorialApproval ? (string) $approval->input_hash : null;
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

    private function approvalInputHashFor(PublishingAttempt $attempt, ApprovalKind $kind): string
    {
        $fingerprint = app(PublishingFingerprint::class);

        return match ($kind) {
            ApprovalKind::Angle => $fingerprint->hash([
                'kind' => $kind->value,
                'attempt_id' => $attempt->id,
                'brief' => $attempt->brief ?? [],
                'angle' => $attempt->angle ?? [],
                'owner_context' => $this->approvalOwnerContext($attempt),
                'input_version' => $attempt->input_version,
            ]),
            ApprovalKind::Plan => $fingerprint->hash([
                'kind' => $kind->value,
                'attempt_id' => $attempt->id,
                'brief' => $attempt->brief ?? [],
                'angle' => $attempt->angle ?? [],
                'owner_context' => $this->approvalOwnerContext($attempt),
                'plan' => $attempt->plan ?? [],
                'input_version' => $attempt->input_version,
            ]),
            ApprovalKind::Release => throw new RuntimeException('Release approval does not run agent activity.'),
            ApprovalKind::Kit => throw new RuntimeException('Kit approval does not run agent activity.'),
        };
    }

    private function stageValue(PublishingAttempt $attempt): string
    {
        $stage = $attempt->getAttribute('stage');

        return $stage instanceof BackedEnum ? (string) $stage->value : (string) $stage;
    }

    private function conversationFor(EditorialActivity $activity, User $actor): string
    {
        if ($activity->ai_conversation_id === null) {
            $id = app(ConversationStore::class)->storeConversation(
                Conversation::participantType($actor),
                Conversation::participantKey($actor),
                'Publishing '.$activity->kind->value.' #'.$activity->id,
            );
            $activity->forceFill(['ai_conversation_id' => $id])->save();
        }

        $id = (string) $activity->ai_conversation_id;
        if (! Conversation::query()->whereKey($id)
            ->where('participant_type', Conversation::participantType($actor))
            ->where('participant_id', Conversation::participantKey($actor))->exists()) {
            throw new RuntimeException('The agent conversation does not belong to the initiating author.');
        }

        return $id;
    }

    private function decisionsFor(EditorialActivity $activity): ?Decisions
    {
        if (! is_array($activity->tool_decisions) || $activity->tool_decisions === []) {
            return null;
        }

        $decisions = [];
        foreach ($activity->tool_decisions as $id => $decision) {
            $decisions[$id] = match ($decision['action']) {
                'edit' => Decision::edit($decision['arguments']),
                'reject' => Decision::reject(),
            };
        }

        return Decisions::from($decisions);
    }

    /** @param array<string, mixed> $response */
    private function generationId(array $response): ?string
    {
        $id = data_get($response, 'id') ?? data_get($response, 'usage.generation_id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    /** @param array<string, mixed> $document */
    private function isEmptyDocument(array $document): bool
    {
        return ! $this->documentHasContent($document);
    }

    private function documentHasContent(mixed $node): bool
    {
        if (is_string($node)) {
            return trim($node) !== '';
        }

        if (! is_array($node)) {
            return false;
        }

        $text = $node['text'] ?? null;
        if (is_string($text) && trim($text) !== '') {
            return true;
        }

        $content = $node['content'] ?? null;
        if (is_array($content)) {
            foreach ($content as $child) {
                if ($this->documentHasContent($child)) {
                    return true;
                }
            }
        }

        $type = $node['type'] ?? null;

        return is_string($type)
            && ! in_array($type, ['doc', 'paragraph', 'heading', 'text', 'bulletList', 'orderedList', 'listItem', 'blockquote', 'table', 'tableRow', 'tableCell', 'tableHeader'], true)
            && ($content === null || $content === []);
    }

    private function kind(EditorialActivity $activity): EditorialActivityKind
    {
        $kind = $activity->getAttribute('kind');

        return $kind instanceof EditorialActivityKind ? $kind : EditorialActivityKind::from((string) $kind);
    }

    private function status(EditorialActivity $activity): EditorialActivityStatus
    {
        $status = $activity->getAttribute('status');

        return $status instanceof EditorialActivityStatus ? $status : EditorialActivityStatus::from((string) $status);
    }
}
