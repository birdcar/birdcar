<?php

use App\Ai\Agents\BuyerReviewer;
use App\Ai\Agents\Drafter;
use App\Ai\Agents\EditorialAgent;
use App\Ai\Agents\FactReviewer;
use App\Ai\Agents\Interviewer;
use App\Ai\Agents\Planner;
use App\Ai\Agents\Researcher;
use App\Ai\Agents\ReviewReconciler;
use App\Ai\Agents\RevisionRechecker;
use App\Ai\Agents\VoiceReviewer;
use App\Ai\Tools\AskAuthor;
use App\Models\EditorialActivity;
use App\Models\Publishing\EditorialActivityKind;
use App\Services\Publishing\EditorialOutput;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Laravel\Ai\Tools\Request;

function editorialActivity(EditorialActivityKind $kind, array $input = []): EditorialActivity
{
    return new EditorialActivity([
        'kind' => $kind,
        'input' => $input ?: ['frozen' => ['title' => 'Owner supplied brief', 'sources' => ['untrusted excerpt']]],
    ]);
}

test('it selects the role-specific SDK agent for each editorial activity kind', function () {
    expect(EditorialAgent::forActivity(editorialActivity(EditorialActivityKind::Interview)))->toBeInstanceOf(Interviewer::class)
        ->and(EditorialAgent::forActivity(editorialActivity(EditorialActivityKind::ResearchChallenge)))->toBeInstanceOf(Researcher::class)
        ->and(EditorialAgent::forActivity(editorialActivity(EditorialActivityKind::Plan)))->toBeInstanceOf(Planner::class)
        ->and(EditorialAgent::forActivity(editorialActivity(EditorialActivityKind::Draft)))->toBeInstanceOf(Drafter::class)
        ->and(EditorialAgent::forActivity(editorialActivity(EditorialActivityKind::ReviewFacts)))->toBeInstanceOf(FactReviewer::class)
        ->and(EditorialAgent::forActivity(editorialActivity(EditorialActivityKind::ReviewVoice)))->toBeInstanceOf(VoiceReviewer::class)
        ->and(EditorialAgent::forActivity(editorialActivity(EditorialActivityKind::ReviewBuyer)))->toBeInstanceOf(BuyerReviewer::class)
        ->and(EditorialAgent::forActivity(editorialActivity(EditorialActivityKind::Reconciliation)))->toBeInstanceOf(ReviewReconciler::class)
        ->and(EditorialAgent::forActivity(editorialActivity(EditorialActivityKind::Recheck)))->toBeInstanceOf(RevisionRechecker::class);
});

test('it prompts the native SDK with frozen context and returns structured output from the fake', function () {
    Planner::fake([[
        'outline' => [['heading' => 'Why now', 'purpose' => 'Open the argument', 'evidence_refs' => []]],
        'argument' => 'The editorial argument.',
        'visualPlan' => [['slot' => 'hero', 'description' => 'Product screenshot']],
    ]]);
    $agent = new Planner(editorialActivity(EditorialActivityKind::Plan));

    $response = $agent->prompt($agent->promptText());

    expect($response)->toBeInstanceOf(StructuredAgentResponse::class)
        ->and($response['argument'])->toBe('The editorial argument.')
        ->and($agent->promptText())->toContain('Owner supplied brief')
        ->and((string) $agent->instructions())->toContain('untrusted evidence')
        ->and((string) $agent->instructions())->toContain('AskAuthor');
    Planner::assertPrompted(fn ($prompt): bool => $prompt->contains('Owner supplied brief'));
});

test('it exposes meaningful native structured schemas without empty arbitrary objects', function () {
    $schema = (new Researcher(editorialActivity(EditorialActivityKind::ResearchChallenge)))->schema(new JsonSchemaTypeFactory);
    $claims = $schema['claims']->toArray();
    $sourceReferences = $schema['sourceReferences']->toArray();
    $draftSchema = (new Drafter(editorialActivity(EditorialActivityKind::Draft)))->schema(new JsonSchemaTypeFactory);
    $reconciliationSchema = (new ReviewReconciler(editorialActivity(EditorialActivityKind::Reconciliation)))->schema(new JsonSchemaTypeFactory);

    expect(array_keys($schema))->toBe(['claims', 'sourceReferences', 'contradictions', 'gaps'])
        ->and($claims['items']['properties'])->toHaveKeys(['statement', 'supporting_quotations', 'severity'])
        ->and($claims['items']['properties']['supporting_quotations']['items']['properties'])->toHaveKey('quote')
        ->and($sourceReferences['items']['properties'])->toHaveKeys(['url', 'title', 'content'])
        ->and($sourceReferences['maxItems'])->toBe(10)
        ->and($reconciliationSchema['groups']->toArray()['items']['properties'])->toHaveKey('canonical_finding_id')
        ->and($draftSchema['document']->toArray()['type'])->toBe('string');
});

/** @return array<string, mixed> */
function sentEditorialAgentRequest(EditorialAgent $agent): array
{
    config()->set('ai.providers.openrouter.key', 'test-key');
    Http::preventStrayRequests();
    Http::fake(['https://openrouter.ai/api/v1/chat/completions' => Http::response([
        'id' => 'gen-config', 'model' => $agent->model(),
        'choices' => [['finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => '{}']]],
    ])]);

    $agent->prompt($agent->promptText());

    return Http::recorded()->sole()[0]->data();
}

test('each role sends its explicit native recommendation without price or provider pins', function (EditorialActivityKind $kind, string $model, string $effort, int $maxTokens) {
    $agent = EditorialAgent::forActivity(editorialActivity($kind));

    $body = sentEditorialAgentRequest($agent);

    expect(EditorialAgent::recommendedModelFor($kind))->toBe($model)
        ->and(EditorialAgent::allowsModel($model))->toBeTrue()
        ->and($agent->maxSteps())->toBe(1)
        ->and($agent->timeout())->toBeLessThan(60)
        ->and($body['model'])->toBe($model)
        ->and($body['max_tokens'])->toBe($maxTokens)
        ->and($body['reasoning'])->toBe(['effort' => $effort])
        ->and($body['provider'])->toBe(['require_parameters' => true])
        ->and($body)->not->toHaveKeys(['max_price', 'max_completion_tokens']);
})->with([
    'interviewer' => [EditorialActivityKind::Interview, 'google/gemini-3.8-flash', 'low', 4000],
    'researcher' => [EditorialActivityKind::ResearchChallenge, 'google/gemini-3.8-flash', 'medium', 8000],
    'planner' => [EditorialActivityKind::Plan, 'deepseek/deepseek-v4-pro-0813', 'high', 12000],
    'drafter' => [EditorialActivityKind::Draft, 'google/gemini-3.8-flash', 'medium', 16000],
    'fact reviewer' => [EditorialActivityKind::ReviewFacts, 'deepseek/deepseek-v4-pro-0813', 'high', 12000],
    'voice reviewer' => [EditorialActivityKind::ReviewVoice, 'google/gemini-3.8-flash', 'medium', 8000],
    'buyer reviewer' => [EditorialActivityKind::ReviewBuyer, 'deepseek/deepseek-v4.1-flash', 'low', 6000],
    'reconciler' => [EditorialActivityKind::Reconciliation, 'deepseek/deepseek-v4.1-flash', 'low', 6000],
    'rechecker' => [EditorialActivityKind::Recheck, 'deepseek/deepseek-v4-pro-0813', 'high', 12000],
]);

test('allowlisted overrides replace only the model and keep role reasoning where supported', function (string $override) {
    $body = sentEditorialAgentRequest(EditorialAgent::forActivity(editorialActivity(EditorialActivityKind::Plan), $override));

    expect($body['model'])->toBe($override)
        ->and($body['reasoning'])->toBe(['effort' => 'high'])
        ->and($body['provider'])->toBe(['require_parameters' => true]);
})->with(['google/gemini-3.8-flash', 'deepseek/deepseek-v4-pro-0813', 'deepseek/deepseek-v4.1-flash']);

test('auto router requests omit forced reasoning while research keeps Exa search', function () {
    $research = sentEditorialAgentRequest(EditorialAgent::forActivity(editorialActivity(EditorialActivityKind::ResearchChallenge), EditorialAgent::AUTO_ROUTER));

    expect($research['model'])->toBe('openrouter/auto')
        ->and($research)->not->toHaveKey('reasoning')
        ->and($research['provider'])->toBe(['require_parameters' => true])
        ->and($research['plugins'])->toBe([['id' => 'web', 'engine' => 'exa', 'mode' => 'auto', 'max_results' => 5]]);
});

test('only research requests carry the Exa web plugin', function () {
    expect(sentEditorialAgentRequest(EditorialAgent::forActivity(editorialActivity(EditorialActivityKind::Draft))))->not->toHaveKey('plugins')
        ->and(sentEditorialAgentRequest(EditorialAgent::forActivity(editorialActivity(EditorialActivityKind::ResearchChallenge)))['plugins'][0]['engine'])->toBe('exa');
});

test('the allowlist rejects unknown identifiers and marks auto as reasoning-incompatible', function () {
    expect(EditorialAgent::allowsModel('openai/gpt-unknown'))->toBeFalse()
        ->and(EditorialAgent::allowsModel(''))->toBeFalse()
        ->and(EditorialAgent::modelSupportsReasoning(EditorialAgent::AUTO_ROUTER))->toBeFalse()
        ->and(config('publishing_agents'))->not->toHaveKeys(['routes', 'role_routes', 'enabled'])
        ->and(json_encode(config('publishing_agents')))->not->toContain('price');
});

test('it routes only interviewers to the author approval tool', function () {
    $interviewerTools = iterator_to_array((new Interviewer(editorialActivity(EditorialActivityKind::Interview)))->tools());
    $plannerTools = iterator_to_array((new Planner(editorialActivity(EditorialActivityKind::Plan)))->tools());

    expect($interviewerTools)->toHaveCount(1)
        ->and($interviewerTools[0])->toBeInstanceOf(AskAuthor::class)
        ->and($plannerTools)->toBe([]);
});

test('it requires approval and human answers before returning author context', function () {
    $tool = new AskAuthor;

    expect($tool->shouldRequestApproval(new Request(['questions' => ['What is the owner goal?']])))->toBeInstanceOf(Approval::class)
        ->and(fn () => $tool->handle(new Request(['questions' => ['What is the owner goal?'], 'answers' => null])))->toThrow(InvalidArgumentException::class)
        ->and($tool->handle(new Request(['questions' => ['What is the owner goal?'], 'answers' => 'Build buyer trust.'])))->toContain('Build buyer trust.');
});

test('it keeps historical array outputs valid while decoding SDK JSON strings before validation', function () {
    $output = new EditorialOutput;

    $validated = $output->validate(EditorialActivityKind::Draft, [
        'document' => json_encode(['type' => 'doc', 'content' => []], JSON_THROW_ON_ERROR),
        'metadataProposals' => ['title' => 'Known array metadata'],
    ]);

    expect($validated['document'])->toBe(['type' => 'doc', 'content' => []])
        ->and($output->validate(EditorialActivityKind::Interview, [
            'questions' => ['Who is this for?'],
            'brief' => ['summary' => 'Stored array result'],
            'angleOptions' => [['angle' => 'Practical guide']],
        ])['brief']['summary'])->toBe('Stored array result');
});
