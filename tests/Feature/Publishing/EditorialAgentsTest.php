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
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Enums\Lab;
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
    $endpoint = ['provider' => 'openrouter'];

    expect(EditorialAgent::forActivity(editorialActivity(EditorialActivityKind::Interview), $endpoint))->toBeInstanceOf(Interviewer::class)
        ->and(EditorialAgent::forActivity(editorialActivity(EditorialActivityKind::ResearchChallenge), $endpoint))->toBeInstanceOf(Researcher::class)
        ->and(EditorialAgent::forActivity(editorialActivity(EditorialActivityKind::Plan), $endpoint))->toBeInstanceOf(Planner::class)
        ->and(EditorialAgent::forActivity(editorialActivity(EditorialActivityKind::Draft), $endpoint))->toBeInstanceOf(Drafter::class)
        ->and(EditorialAgent::forActivity(editorialActivity(EditorialActivityKind::ReviewFacts), $endpoint))->toBeInstanceOf(FactReviewer::class)
        ->and(EditorialAgent::forActivity(editorialActivity(EditorialActivityKind::ReviewVoice), $endpoint))->toBeInstanceOf(VoiceReviewer::class)
        ->and(EditorialAgent::forActivity(editorialActivity(EditorialActivityKind::ReviewBuyer), $endpoint))->toBeInstanceOf(BuyerReviewer::class)
        ->and(EditorialAgent::forActivity(editorialActivity(EditorialActivityKind::Reconciliation), $endpoint))->toBeInstanceOf(ReviewReconciler::class)
        ->and(EditorialAgent::forActivity(editorialActivity(EditorialActivityKind::Recheck), $endpoint))->toBeInstanceOf(RevisionRechecker::class);
});

test('it prompts the native SDK with frozen context and returns structured output from the fake', function () {
    Planner::fake([[
        'outline' => [['heading' => 'Why now', 'purpose' => 'Open the argument', 'evidence_refs' => []]],
        'argument' => 'The editorial argument.',
        'visualPlan' => [['slot' => 'hero', 'description' => 'Product screenshot']],
    ]]);
    $agent = new Planner(editorialActivity(EditorialActivityKind::Plan), ['provider' => 'openrouter']);

    $response = $agent->prompt($agent->promptText(), provider: Lab::OpenRouter, model: 'openai/gpt-4o-mini');

    expect($response)->toBeInstanceOf(StructuredAgentResponse::class)
        ->and($response['argument'])->toBe('The editorial argument.')
        ->and($agent->promptText())->toContain('Owner supplied brief')
        ->and((string) $agent->instructions())->toContain('untrusted evidence')
        ->and((string) $agent->instructions())->toContain('AskAuthor');
    Planner::assertPrompted(fn ($prompt): bool => $prompt->contains('Owner supplied brief'));
});

test('it exposes meaningful native structured schemas without empty arbitrary objects', function () {
    $schema = (new Researcher(editorialActivity(EditorialActivityKind::ResearchChallenge), []))->schema(new JsonSchemaTypeFactory);
    $claims = $schema['claims']->toArray();
    $sourceReferences = $schema['sourceReferences']->toArray();
    $draftSchema = (new Drafter(editorialActivity(EditorialActivityKind::Draft), []))->schema(new JsonSchemaTypeFactory);
    $reconciliationSchema = (new ReviewReconciler(editorialActivity(EditorialActivityKind::Reconciliation), []))->schema(new JsonSchemaTypeFactory);

    expect(array_keys($schema))->toBe(['claims', 'sourceReferences', 'contradictions', 'gaps'])
        ->and($claims['items']['properties'])->toHaveKeys(['statement', 'supporting_quotations', 'severity'])
        ->and($claims['items']['properties']['supporting_quotations']['items']['properties'])->toHaveKey('quote')
        ->and($sourceReferences['items']['properties'])->toHaveKeys(['url', 'title', 'content'])
        ->and($sourceReferences['maxItems'])->toBe(10)
        ->and($reconciliationSchema['groups']->toArray()['items']['properties'])->toHaveKey('canonical_finding_id')
        ->and($draftSchema['document']->toArray()['type'])->toBe('string');
});

test('it pins one-step execution and OpenRouter Exa plugin routing options', function () {
    $agent = new Researcher(editorialActivity(EditorialActivityKind::ResearchChallenge), [
        'provider' => 'openrouter',
        'max_completion_tokens' => 1234,
        'max_price' => ['prompt' => '0.10', 'completion' => '0.20'],
    ]);

    $options = $agent->providerOptions(Lab::OpenRouter);

    expect($agent->maxSteps())->toBe(1)
        ->and($agent->maxTokens())->toBe(1234)
        ->and($options['provider']['only'])->toBe(['openrouter'])
        ->and($options['provider']['allow_fallbacks'])->toBeFalse()
        ->and($options['provider']['require_parameters'])->toBeTrue()
        ->and($options['provider']['max_price'])->toBe(['prompt' => '0.10', 'completion' => '0.20'])
        ->and($options['max_completion_tokens'])->toBe(1234)
        ->and($options['plugins'])->toBe([['id' => 'web', 'engine' => 'exa', 'mode' => 'auto', 'max_results' => 5]]);
});

test('it routes only interviewers to the author approval tool', function () {
    $interviewerTools = iterator_to_array((new Interviewer(editorialActivity(EditorialActivityKind::Interview), []))->tools());
    $plannerTools = iterator_to_array((new Planner(editorialActivity(EditorialActivityKind::Plan), []))->tools());

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
