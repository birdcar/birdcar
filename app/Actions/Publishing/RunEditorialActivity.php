<?php

namespace App\Actions\Publishing;

use App\Ai\Agents\EditorialAgent;
use App\Models\AgentBudgetReservation;
use App\Models\Article;
use App\Models\EditorialActivity;
use App\Models\EditorialApproval;
use App\Models\EditorialFinding;
use App\Models\EvidenceSource;
use App\Models\Publishing\ApprovalKind;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\PublishingAttempt;
use App\Models\User;
use App\Services\Publishing\AgentBudget;
use App\Services\Publishing\EditorialModelBudget;
use App\Services\Publishing\EditorialOutput;
use App\Services\Publishing\OpenRouterBilling;
use App\Services\Publishing\PublicSourceFetcher;
use App\Services\Publishing\PublishingFingerprint;
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
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;
use Throwable;

class RunEditorialActivity implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $activityId) {}

    public function handle(EditorialModelBudget $client, AgentBudget $budget, EditorialOutput $prompts, WriteArticle $writer, ?PublicSourceFetcher $fetcher = null): void
    {
        $claim = $this->claimActivity();
        if ($claim === null) {
            return;
        }

        [$activity, $actor] = $claim;
        $reservation = null;
        $paidOutcomeResolved = false;
        $completionReceived = false;

        try {
            $kind = $this->kind($activity);
            $route = filled($activity->tool_decisions) && filled($activity->model_snapshot)
                ? $activity->model_snapshot : $this->routeForKind($kind);
            $endpoint = $client->pricedEndpoint($route);
            $agent = EditorialAgent::forActivity($activity, $endpoint);
            $conversationId = $this->conversationFor($activity, $actor);
            $agent->continue($conversationId, as: $actor);
            $decisions = $this->decisionsFor($activity);
            if ($decisions !== null && collect($activity->tool_decisions)->every(fn (array $decision): bool => $decision['action'] === 'reject')) {
                $agent->prompt($decisions, provider: Lab::OpenRouter, model: (string) $endpoint['model']);
                $activity->forceFill([
                    'status' => EditorialActivityStatus::Declined,
                    'pending_tool_approvals' => null,
                    'tool_decisions' => null,
                    'completed_at' => now(),
                ])->save();

                return;
            }
            $pluginNanoUsd = $kind === EditorialActivityKind::ResearchChallenge ? (int) config('publishing_agents.limits.exa_web_search_nano_usd', 7_000_000) : 0;
            $quote = $client->quote($endpoint, [
                'prompt_tokens' => $endpoint['context_tokens'] ?? null,
                'max_completion_tokens' => $endpoint['max_completion_tokens'] ?? null,
                'plugin_nano_usd' => $pluginNanoUsd,
            ]);
            $reservation = $this->reserveAfterFreshActivityCheck($budget, $actor, $activity, $quote);
            if (! $reservation instanceof AgentBudgetReservation) {
                return;
            }
            $agentResponse = $agent->prompt(
                $decisions ?? $agent->promptText(),
                provider: Lab::OpenRouter,
                model: (string) $endpoint['model'],
                timeout: (int) config('publishing_agents.http_timeout', 30),
            );
            $completionReceived = true;
            $response = $agentResponse->raw?->json() ?? [];
            if (! is_array($response)) {
                throw new RuntimeException('OpenRouter returned a malformed response.');
            }
            $generationId = $this->generationId($response);
            $actualNanoUsd = $this->actualCostNanoUsd($response);
            if ($generationId !== null) {
                $reservation->forceFill(['provider_generation_id' => $generationId])->save();
            }

            if ($actualNanoUsd === null && $generationId !== null) {
                $generation = app(OpenRouterBilling::class)->generation($generationId);
                $actualNanoUsd = $this->actualCostNanoUsd($generation);
            }

            if ($actualNanoUsd === null) {
                $budget->retainUnknown($reservation, 'OpenRouter did not return complete cost metadata.', $generationId);
                $paidOutcomeResolved = true;
                $this->pauseActivity($activity, 'Billing outcome is unknown.');

                return;
            }

            $budget->settle($reservation, $actualNanoUsd, $generationId);
            $paidOutcomeResolved = true;
            if ($agentResponse->hasPendingApprovals()) {
                $this->applyResult($activity, $actor, [], $writer, $generationId, $endpoint, [], $agentResponse);

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
            }
            $validated = $prompts->validate($kind, $payload, $knownEvidenceIds, $evidenceTexts);
            $this->applyResult($activity, $actor, $validated, $writer, $generationId, $endpoint, $preparedEvidenceSources);
        } catch (Throwable $throwable) {
            $this->resolveReservationFailure($budget, $reservation, $throwable, $paidOutcomeResolved, $completionReceived);
            if ($reservation instanceof AgentBudgetReservation || $this->isDeterministicPreCallSpendBlocker($throwable)) {
                $this->pauseActivity($activity, $throwable->getMessage());

                return;
            }

            $this->failOrPause($activity, $throwable);
        }
    }

    /**
     * @return array{EditorialActivity, User}|null
     */
    private function claimActivity(): ?array
    {
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

            if (! $actor instanceof User || ! $this->actorCanRunKind($actor, $this->kind($activity))) {
                $activity->forceFill([
                    'status' => EditorialActivityStatus::Paused,
                    'paused_at' => now(),
                    'pause_reason' => 'The initiating actor can no longer run this publishing agent work.',
                ])->save();

                return null;
            }

            if ((int) ($article->current_attempt_id ?? 0) !== (int) $attempt->id || $attempt->paused_at !== null || $attempt->parked_at !== null || $attempt->abandoned_at !== null) {
                $activity->forceFill([
                    'status' => EditorialActivityStatus::Paused,
                    'paused_at' => now(),
                    'pause_reason' => 'The publishing attempt is blocked or no longer current.',
                ])->save();

                return null;
            }

            if ($activity->revision_id !== null && (int) ($article->working_revision_id ?? 0) !== (int) $activity->revision_id) {
                $activity->forceFill([
                    'status' => EditorialActivityStatus::Stale,
                    'completed_at' => now(),
                    'error_reason' => 'The article revision changed before the activity could run.',
                ])->save();

                return null;
            }

            $staleReason = $this->staleReason($activity, $attempt, $article);
            if ($staleReason !== null) {
                $activity->forceFill([
                    'status' => EditorialActivityStatus::Stale,
                    'completed_at' => now(),
                    'error_reason' => $staleReason,
                ])->save();

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
     * @param  array{reserved_nano_usd: int, price_snapshot: array<string, mixed>, request_bound: array<string, mixed>}  $quote
     */
    private function reserveAfterFreshActivityCheck(AgentBudget $budget, User $actor, EditorialActivity $activity, array $quote): ?AgentBudgetReservation
    {
        /** @var AgentBudgetReservation|null $reservation */
        $reservation = DB::transaction(function () use ($budget, $actor, $activity, $quote): ?AgentBudgetReservation {
            $locked = EditorialActivity::query()->whereKey($activity->id)->lockForUpdate()->first();
            if (! $locked instanceof EditorialActivity || $this->status($locked) !== EditorialActivityStatus::Running) {
                return null;
            }

            $attempt = PublishingAttempt::query()->whereKey($locked->attempt_id)->lockForUpdate()->firstOrFail();
            $article = Article::query()->whereKey($locked->article_id)->lockForUpdate()->firstOrFail();
            $freshActor = User::query()->whereKey($actor->id)->first();

            if (! $freshActor instanceof User || ! $this->actorCanRunKind($freshActor, $this->kind($locked))) {
                $locked->forceFill([
                    'status' => EditorialActivityStatus::Paused,
                    'paused_at' => now(),
                    'pause_reason' => 'The initiating actor can no longer run this publishing agent work.',
                ])->save();

                return null;
            }

            if ((int) ($article->current_attempt_id ?? 0) !== (int) $attempt->id || $attempt->paused_at !== null || $attempt->parked_at !== null || $attempt->abandoned_at !== null) {
                $locked->forceFill([
                    'status' => EditorialActivityStatus::Paused,
                    'paused_at' => now(),
                    'pause_reason' => 'The publishing attempt is blocked or no longer current.',
                ])->save();

                return null;
            }

            if ($locked->revision_id !== null && (int) ($article->working_revision_id ?? 0) !== (int) $locked->revision_id) {
                $locked->forceFill([
                    'status' => EditorialActivityStatus::Stale,
                    'completed_at' => now(),
                    'error_reason' => 'The article revision changed before budget could be reserved.',
                ])->save();

                return null;
            }

            $staleReason = $this->staleReason($locked, $attempt, $article);
            if ($staleReason !== null) {
                $locked->forceFill([
                    'status' => EditorialActivityStatus::Stale,
                    'completed_at' => now(),
                    'error_reason' => $staleReason,
                ])->save();

                return null;
            }

            return $budget->reserve($freshActor, $attempt, $locked, $quote['reserved_nano_usd'], $quote['price_snapshot'], $quote['request_bound']);
        });

        return $reservation;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $endpoint
     * @param  list<array<string, mixed>>  $preparedEvidenceSources
     */
    private function applyResult(EditorialActivity $activity, User $actor, array $payload, WriteArticle $writer, ?string $generationId, array $endpoint, array $preparedEvidenceSources, ?AgentResponse $agentResponse = null): void
    {
        DB::transaction(function () use ($activity, $actor, $payload, $writer, $generationId, $endpoint, $preparedEvidenceSources, $agentResponse): void {
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
                    'pause_reason' => 'The paid result returned after the publishing attempt was blocked or replaced.',
                ])->save();

                return;
            }

            if ($locked->revision_id !== null && (int) ($article->working_revision_id ?? 0) !== (int) $locked->revision_id) {
                $locked->forceFill([
                    'status' => EditorialActivityStatus::Stale,
                    'response' => $payload,
                    'generation_id' => $generationId,
                    'completed_at' => now(),
                    'error_reason' => 'The paid result targeted a stale revision.',
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
                $locked->forceFill([
                    'status' => EditorialActivityStatus::AwaitingApproval,
                    'pending_tool_approvals' => $pending,
                    'tool_decisions' => null,
                    'model_snapshot' => $endpoint,
                    'generation_id' => $generationId,
                ])->save();

                return;
            }

            if ($kind === EditorialActivityKind::Draft) {
                $this->applyDraft($locked, $freshActor, $article, $payload, $writer);
            }

            if ($kind === EditorialActivityKind::ResearchChallenge) {
                $this->storeEvidenceAnnotations($locked, $preparedEvidenceSources);
            }

            if ($kind === EditorialActivityKind::Interview) {
                $this->applyInterview($locked, $attempt, $payload);
            }

            if ($kind === EditorialActivityKind::Plan) {
                $this->applyPlan($locked, $attempt, $payload);
            }

            if ($kind->isReviewLens() || $kind === EditorialActivityKind::Recheck) {
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
                'model_snapshot' => $endpoint,
                'generation_id' => $generationId,
                'completed_at' => now(),
            ])->save();

            $this->queueReviewLensesAfterCompletedDraft($locked, $freshActor);
            $this->queuePlanAfterCompletedResearch($locked, $freshActor);
            $this->queueReconciliationAfterCompletedReviewLens($locked, $freshActor);
        });
    }

    /** @param array<string, mixed> $payload */
    private function applyInterview(EditorialActivity $activity, PublishingAttempt $attempt, array $payload): void
    {
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
            'brief' => $payload['brief'],
            'interview_context' => $context,
        ])->save();
    }

    /** @param array<string, mixed> $payload */
    private function applyPlan(EditorialActivity $activity, PublishingAttempt $attempt, array $payload): void
    {
        $attempt->forceFill([
            'plan' => [
                'outline' => $payload['outline'],
                'argument' => $payload['argument'],
                'visualPlan' => $payload['visualPlan'],
                'source' => 'agent',
                'activity_id' => $activity->id,
                'generated_at' => now()->toISOString(),
            ],
        ])->save();
    }

    /** @param array<string, mixed> $payload */
    private function applyDraft(EditorialActivity $activity, User $actor, Article $article, array $payload, WriteArticle $writer): void
    {
        $document = is_array($payload['document'] ?? null) ? $payload['document'] : [];
        $metadata = is_array($payload['metadataProposals'] ?? null) ? $payload['metadataProposals'] : [];
        $currentRevision = $article->workingRevision()->first();
        $currentDocumentValue = $currentRevision?->getAttribute('document');
        $currentDocument = is_array($currentDocumentValue) ? $currentDocumentValue : ['version' => 1, 'type' => 'doc', 'content' => []];

        if ($this->isEmptyDocument($currentDocument)) {
            $currentMetadataValue = $currentRevision?->getAttribute('metadata');
            $currentMetadata = is_array($currentMetadataValue) ? $currentMetadataValue : [];
            $revision = $writer->save($actor, $article, $article->working_revision_id, $document, $currentMetadata, 'agent-initial-'.$activity->id, 'agent-initial');
            $activity->forceFill(['proposal' => ['applied_revision_id' => $revision->id, 'metadata' => $metadata, 'reason' => 'Metadata changes require human acceptance.']])->save();

            return;
        }

        $activity->forceFill(['proposal' => ['document' => $document, 'metadata' => $metadata, 'reason' => 'Current manuscript was not empty; human acceptance is required.']])->save();
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

    /** @return array{voice_sample_ids: list<int>, voice_samples: list<array{id: int, excerpt: string}>} */
    private function reviewVoiceInput(PublishingAttempt $attempt): array
    {
        $context = $attempt->fresh()?->getAttribute('interview_context');
        $context = is_array($context) ? $context : [];
        $ids = is_array($context['voice_sample_ids'] ?? null) ? array_values(array_map('intval', $context['voice_sample_ids'])) : [];
        $samples = [];

        foreach ((is_array($context['voice_samples'] ?? null) ? $context['voice_samples'] : []) as $sample) {
            if (! is_array($sample)) {
                continue;
            }
            $id = $sample['id'] ?? null;
            $excerpt = $sample['excerpt'] ?? null;
            if (is_numeric($id) && is_string($excerpt) && trim($excerpt) !== '') {
                $samples[] = ['id' => (int) $id, 'excerpt' => mb_substr($excerpt, 0, 1200)];
            }
        }

        return [
            'voice_sample_ids' => $ids,
            'voice_samples' => $samples,
        ];
    }

    private function isDeterministicPreCallSpendBlocker(Throwable $throwable): bool
    {
        $message = $throwable->getMessage();

        foreach ([
            'Publishing agents are disabled.',
            'OpenRouter credentials are not configured.',
            'Publishing agent route is not configured.',
            'Publishing agent model and provider must be explicit.',
            'Publishing agent pricing is missing.',
            'Publishing agent context and output limits must be configured.',
            'A conservative token bound is required before spending.',
            'Endpoint pricing is missing.',
            'Endpoint pricing must be a non-negative decimal string.',
            'This user is not allowed to spend publishing agent budget.',
            'Reservations must be positive.',
            'Blocked publishing attempts cannot spend agent budget.',
            'Budget reservations cannot cross attempts.',
            'The publishing agent budget allowance would be exceeded.',
        ] as $blocker) {
            if (str_contains($message, $blocker)) {
                return true;
            }
        }

        return false;
    }

    private function failOrPause(EditorialActivity $activity, Throwable $throwable): void
    {
        DB::transaction(function () use ($activity, $throwable): void {
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
                'pause_reason' => $status === EditorialActivityStatus::Paused ? $throwable->getMessage() : null,
                'error_reason' => $throwable->getMessage(),
                'available_at' => now()->addMinute(),
            ])->save();
        });
    }

    private function pauseActivity(EditorialActivity $activity, string $reason): void
    {
        EditorialActivity::query()->whereKey($activity->id)->update([
            'status' => EditorialActivityStatus::Paused->value,
            'paused_at' => now(),
            'pause_reason' => $reason,
        ]);
    }

    private function resolveReservationFailure(AgentBudget $budget, ?AgentBudgetReservation $reservation, Throwable $throwable, bool $paidOutcomeResolved, bool $completionReceived): void
    {
        if (! $reservation instanceof AgentBudgetReservation || $paidOutcomeResolved) {
            return;
        }

        if (! $completionReceived && $this->isKnownNonBillableFailure($throwable)) {
            $budget->release($reservation, $throwable->getMessage());

            return;
        }

        $budget->retainUnknown($reservation, 'Exception after reserving budget: '.$throwable->getMessage(), $reservation->provider_generation_id);
    }

    private function isKnownNonBillableFailure(Throwable $throwable): bool
    {
        do {
            if ($throwable instanceof RequestException && $throwable->response !== null) {
                return in_array($throwable->response->status(), [400, 401, 402, 403, 404, 422], true);
            }
            $throwable = $throwable->getPrevious();
        } while ($throwable !== null);

        return false;
    }

    private function actorCanRunKind(User $actor, EditorialActivityKind $kind): bool
    {
        if (! $actor->can('publishing.develop')) {
            return false;
        }

        return $kind !== EditorialActivityKind::Draft || $actor->can('publishing.write');
    }

    private function queueReviewLensesAfterCompletedDraft(EditorialActivity $activity, User $actor): void
    {
        $fresh = $activity->fresh();
        if (! $fresh instanceof EditorialActivity || $this->kind($fresh) !== EditorialActivityKind::Draft || $this->status($fresh) !== EditorialActivityStatus::Completed) {
            return;
        }

        $proposal = $fresh->getAttribute('proposal');
        if (! is_array($proposal) || ! isset($proposal['applied_revision_id'])) {
            return;
        }

        $attempt = $fresh->attempt()->first();
        if (! $attempt instanceof PublishingAttempt) {
            return;
        }

        $batchKey = 'review-'.$attempt->review_cycle.'-'.$proposal['applied_revision_id'];
        $voiceInput = $this->reviewVoiceInput($attempt);
        foreach ([EditorialActivityKind::ReviewFacts, EditorialActivityKind::ReviewVoice, EditorialActivityKind::ReviewBuyer] as $kind) {
            app(StartEditorialActivity::class)->start($actor, $attempt, $kind, array_merge(['draft_activity_id' => $fresh->id], $voiceInput), $batchKey);
        }
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
                return 'The plan input changed before the paid plan result could be applied.';
            }
        }

        $approval = $this->prerequisiteApprovalFor($kind);
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

    private function prerequisiteApprovalFor(EditorialActivityKind $kind): ?ApprovalKind
    {
        return match ($kind) {
            EditorialActivityKind::ResearchChallenge, EditorialActivityKind::Plan => ApprovalKind::Angle,
            EditorialActivityKind::Draft, EditorialActivityKind::ReviewFacts, EditorialActivityKind::ReviewVoice, EditorialActivityKind::ReviewBuyer, EditorialActivityKind::Reconciliation, EditorialActivityKind::Recheck => ApprovalKind::Plan,
            EditorialActivityKind::Interview => null,
        };
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
        };
    }

    private function stageValue(PublishingAttempt $attempt): string
    {
        $stage = $attempt->getAttribute('stage');

        return $stage instanceof BackedEnum ? (string) $stage->value : (string) $stage;
    }

    /** @return array<string, mixed> */
    private function routeForKind(EditorialActivityKind $kind): array
    {
        $routeName = 'default';
        if ($kind === EditorialActivityKind::ReviewFacts) {
            $candidate = (string) config('publishing_agents.role_routes.review_facts', 'premium');
            $premium = config('publishing_agents.routes.'.$candidate);
            $routeName = is_array($premium) && is_string($premium['model'] ?? null) && $premium['model'] !== '' ? $candidate : 'default';
        }

        $route = config('publishing_agents.routes.'.$routeName);
        if (! is_array($route)) {
            throw new RuntimeException('Publishing agent route is not configured.');
        }

        return $route;
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
    private function actualCostNanoUsd(array $response): ?int
    {
        $value = data_get($response, 'data.total_cost') ?? data_get($response, 'total_cost') ?? data_get($response, 'usage.cost') ?? data_get($response, 'usage.total_cost');
        if (is_int($value)) {
            return $value * 1_000_000_000;
        }
        if (is_float($value)) {
            return (int) ceil($value * 1_000_000_000);
        }
        if (is_string($value) && preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            return $this->decimalUsdToNanoUsd($value);
        }

        return null;
    }

    private function decimalUsdToNanoUsd(string $decimalUsd): int
    {
        [$whole, $fraction] = array_pad(explode('.', $decimalUsd, 2), 2, '');
        $fraction = str_pad($fraction, 10, '0');
        $nano = ((int) $whole * 1_000_000_000) + (int) substr($fraction, 0, 9);
        if (preg_match('/[1-9]/', substr($fraction, 9)) === 1) {
            $nano++;
        }

        return $nano;
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
