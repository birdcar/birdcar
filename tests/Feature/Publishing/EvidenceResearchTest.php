<?php

use App\Actions\Publishing\AdvancePublishingAttempt;
use App\Actions\Publishing\ApprovePublishingStage;
use App\Actions\Publishing\RunEditorialActivity;
use App\Actions\Publishing\StartEditorialActivity;
use App\Actions\Publishing\WriteArticle;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Models\EvidenceSource;
use App\Models\Publishing\ApprovalKind;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\PublishingAttempt;
use App\Models\User;
use App\Services\Publishing\EditorialOutput;
use App\Services\Publishing\PublicSourceFetcher;
use App\Settings\PublishingAgentSettings;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
});

test('research stores retrieved citation text and fetches pointers without content', function (): void {
    setPublishingAgentsPaused(false);
    config()->set('ai.providers.openrouter.key', 'test-key');

    Http::preventStrayRequests();
    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => Http::response([
            'id' => 'gen-research',
            'choices' => [['message' => ['content' => json_encode([
                'claims' => [],
                'sourceReferences' => [
                    ['url' => 'https://example.com/a', 'title' => 'A', 'content' => 'Model-authored text must not become evidence.'],
                    ['url' => 'https://example.com/b', 'title' => 'B'],
                ],
                'contradictions' => [],
                'gaps' => [],
            ]), 'annotations' => [[
                'type' => 'url_citation',
                'url_citation' => ['url' => 'https://example.com/a', 'content' => 'A supporting passage.'],
            ]]]]],
        ]),
        'https://example.com/b' => Http::response('<p>Fetched public passage.</p>', 200, ['Content-Type' => 'text/html']),
    ]);

    $actor = evidenceAuthor();
    $attempt = evidenceAttempt($actor);
    $fetcher = app(PublicSourceFetcher::class);
    $fetcher->useResolver(fn (string $host): array => ['93.184.216.34']);
    $fetcher->useTransport(fn (string $url, array $options): array => ['status' => 200, 'headers' => ['Content-Type' => 'text/html'], 'body' => '<p>Fetched public passage.</p>']);

    $activity = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::ResearchChallenge, [], 'research-test');
    app(RunEditorialActivity::class, ['activityId' => $activity->id])->handle(app(EditorialOutput::class), app(WriteArticle::class), $fetcher);

    $openRouterText = EvidenceSource::query()->where('retrieval_method', 'openrouter-web')->first()?->extracted_text;

    $fetchedText = EvidenceSource::query()->where('retrieval_method', 'public-fetch')->first()?->extracted_text;

    expect(EvidenceSource::query()->where('activity_id', $activity->id)->count())->toBe(2)
        ->and(str_contains((string) $openRouterText, 'supporting passage'))->toBeTrue()
        ->and(str_contains((string) $openRouterText, 'Model-authored'))->toBeFalse()
        ->and(str_contains((string) $fetchedText, 'Fetched public passage'))->toBeTrue();
});

test('research records unresolved source when public fetch is denied', function (): void {
    setPublishingAgentsPaused(false);
    config()->set('ai.providers.openrouter.key', 'test-key');

    Http::preventStrayRequests();
    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => Http::response([
            'id' => 'gen-research-denied',
            'choices' => [['message' => ['content' => json_encode([
                'claims' => [],
                'sourceReferences' => [
                    ['url' => 'http://127.0.0.1/latest', 'title' => 'Denied'],
                ],
                'contradictions' => [],
                'gaps' => [],
            ])]]],
        ]),
    ]);

    $actor = evidenceAuthor();
    $attempt = evidenceAttempt($actor);
    $activity = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::ResearchChallenge, [], 'research-denied-source');

    app(RunEditorialActivity::class, ['activityId' => $activity->id])->handle(app(EditorialOutput::class), app(WriteArticle::class), app(PublicSourceFetcher::class));

    $source = EvidenceSource::query()->where('activity_id', $activity->id)->first();
    expect($activity->fresh()?->status->value)->toBe('completed')
        ->and($source?->extracted_text)->toBeNull()
        ->and($source?->unresolved_reason)->toContain('Public fetch could not retrieve this source');
});

test('public source fetcher rejects private redirects and pins validated hosts', function (): void {
    $fetcher = app(PublicSourceFetcher::class);
    $lookups = ['example.com' => ['93.184.216.34'], 'metadata.test' => ['169.254.169.254']];
    $fetcher->useResolver(fn (string $host): array => $lookups[$host] ?? []);
    $fetcher->useTransport(function (string $url, array $options): array {
        expect($options['ip'])->toBe('93.184.216.34');

        return ['status' => 302, 'headers' => ['Location' => 'http://metadata.test/latest'], 'body' => ''];
    });

    expect(fn () => $fetcher->fetch('https://example.com/source'))->toThrow(InvalidArgumentException::class, 'non-public');
});

test('invented and non-integer evidence references are rejected', function (): void {
    $prompts = app(EditorialOutput::class);

    expect(fn () => $prompts->validate(EditorialActivityKind::ReviewFacts, ['findings' => [[
        'statement' => 'Unsupported claim.',
        'supporting_source_ids' => [123],
    ]]], []))->toThrow(InvalidArgumentException::class, 'unknown evidence')
        ->and(fn () => $prompts->validate(EditorialActivityKind::ReviewFacts, ['findings' => [[
            'statement' => 'Unsupported claim.',
            'supporting_source_ids' => ['123'],
        ]]], [123]))->toThrow(InvalidArgumentException::class, 'integer source IDs');
});

test('supporting quotations must be grounded in eligible retained source text', function (): void {
    $prompts = app(EditorialOutput::class);
    $eligibleText = 'The retained passage says customers reduced review time by 42 percent.';

    $validFinding = [
        'statement' => 'Review time dropped.',
        'supporting_source_ids' => [10],
        'supporting_quotations' => [['source_id' => 10, 'quote' => 'customers reduced review time by 42 percent']],
    ];

    expect($prompts->validate(EditorialActivityKind::ReviewFacts, ['findings' => [$validFinding]], [10], [10 => $eligibleText]))->toBeArray()
        ->and($prompts->validate(EditorialActivityKind::ResearchChallenge, [
            'claims' => [$validFinding],
            'sourceReferences' => [],
            'contradictions' => [],
            'gaps' => [],
        ], [10], [10 => $eligibleText]))->toBeArray()
        ->and(fn () => $prompts->validate(EditorialActivityKind::ReviewFacts, ['findings' => [[
            'statement' => 'Invented support.',
            'supporting_source_ids' => [10],
            'supporting_quotations' => [['source_id' => 10, 'quote' => 'this quotation is not in the source']],
        ]]], [10], [10 => $eligibleText]))->toThrow(InvalidArgumentException::class, 'not found')
        ->and(fn () => $prompts->validate(EditorialActivityKind::ReviewFacts, ['findings' => [[
            'statement' => 'Missing source text.',
            'supporting_source_ids' => [10],
            'supporting_quotations' => [['source_id' => 10, 'quote' => 'customers reduced review time']],
        ]]], [10], [10 => null]))->toThrow(InvalidArgumentException::class, 'source text is missing')
        ->and(fn () => $prompts->validate(EditorialActivityKind::ReviewFacts, ['findings' => [[
            'statement' => 'Cross attempt source.',
            'supporting_source_ids' => [11],
            'supporting_quotations' => [['source_id' => 11, 'quote' => 'customers reduced review time']],
        ]]], [10], [10 => $eligibleText]))->toThrow(InvalidArgumentException::class, 'unknown evidence')
        ->and(fn () => $prompts->validate(EditorialActivityKind::Recheck, [
            'resolved' => [],
            'unresolved' => [],
            'newBlockingFindings' => [[
                'statement' => 'Contradiction was downgraded.',
                'severity' => 'advisory',
                'supporting_source_ids' => [10],
                'supporting_quotations' => [['source_id' => 10, 'quote' => 'customers reduced review time', 'relationship' => 'contradicts']],
            ]],
        ], [10], [10 => $eligibleText]))->toThrow(InvalidArgumentException::class, 'Contradictory evidence');
});

test('activity inputs freeze eligible source passages and exclude restricted material without consent', function (): void {
    $actor = evidenceAuthor();
    $attempt = evidenceAttempt($actor);
    $public = EvidenceSource::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'source_type' => 'owner',
        'extracted_text' => 'Original retained owner source text.',
        'content_hash' => hash('sha256', 'Original retained owner source text.'),
    ]);
    $attempt->forceFill(['interview_context' => ['selected_evidence_source_ids' => [$public->id]]])->save();
    EvidenceSource::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'source_type' => 'restricted',
        'extracted_text' => 'Restricted source without processing consent.',
        'restricted_processing_consent' => false,
    ]);

    $activity = app(StartEditorialActivity::class)->start($actor, $attempt, EditorialActivityKind::ResearchChallenge, [], 'freeze-evidence-inputs');
    $public->forceFill(['extracted_text' => 'Changed after dispatch.'])->save();

    $sources = $activity->fresh()?->input['evidence_sources'] ?? [];

    expect($sources)->toHaveCount(1)
        ->and($sources[0]['id'])->toBe($public->id)
        ->and($sources[0]['extracted_text'])->toBe('Original retained owner source text.');
});

test('restricted supplied material separates ai consent from publication permission', function (): void {
    $actor = evidenceAuthor();
    $attempt = evidenceAttempt($actor);
    $source = EvidenceSource::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'source_type' => 'restricted',
        'extracted_text' => 'Private customer quote.',
        'content_hash' => hash('sha256', 'Private customer quote.'),
        'restricted_processing_consent' => true,
        'publication_permission' => false,
        'consent_actor_id' => $actor->id,
        'consented_at' => now(),
    ]);

    expect($source->restricted_processing_consent)->toBeTrue()
        ->and($source->publication_permission)->toBeFalse();
});

function evidenceAuthor(): User
{
    $user = User::factory()->create();
    $user->assignRole(PublishingRole::Author->value);

    return $user;
}

function evidenceAttempt(User $actor): PublishingAttempt
{
    $writer = app(WriteArticle::class);
    $article = $writer->capture($actor, 'Evidence agent work.', 'evidence-agent-work-'.uniqid());
    $revision = $writer->save($actor, $article, null, ['version' => 1, 'type' => 'doc', 'content' => []], ['title' => 'Evidence'], 'evidence-draft-'.uniqid());

    $attempt = app(AdvancePublishingAttempt::class)->develop($actor, $article, $revision->id, ['goal' => 'Evidence']);
    $paused = app(PublishingAgentSettings::class)->paused;
    setPublishingAgentsPaused(true);
    $approve = app(ApprovePublishingStage::class);
    $approve->approve($actor, $attempt, ApprovalKind::Angle, $approve->inputHashFor($attempt, ApprovalKind::Angle));
    setPublishingAgentsPaused($paused);

    return $attempt->fresh();
}
