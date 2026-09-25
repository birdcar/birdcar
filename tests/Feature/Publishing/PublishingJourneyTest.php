<?php

use App\Actions\Publishing\AdvancePublishingAttempt;
use App\Actions\Publishing\ApprovePublishingStage;
use App\Actions\Publishing\RunEditorialActivity;
use App\Actions\Publishing\WriteArticle;
use App\Authorization\Admin\Role as AdminRole;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Models\ArticleRelease;
use App\Models\EditorialActivity;
use App\Models\EditorialFinding;
use App\Models\EvidenceSource;
use App\Models\Publishing\ApprovalKind;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\User;
use App\Services\Publishing\EditorialOutput;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
});

test('displayed interview to recheck journey uses real workspace actions and frozen evidence', function (): void {
    journeyConfigureAgents();

    Http::preventStrayRequests();
    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => function (Request $request) {
            $prompt = json_decode((string) data_get($request->data(), 'messages.1.content'), true, flags: JSON_THROW_ON_ERROR);
            $kind = data_get($prompt, 'kind');

            return Http::response(match ($kind) {
                EditorialActivityKind::Interview->value => journeyOpenRouterResponse('journey-interview', [
                    'questions' => ['What proof should the owner include before approving this angle?'],
                    'brief' => ['goal' => 'Explain the publishing control loop', 'audience' => 'Founder editing a launch article'],
                    'angleOptions' => [[
                        'title' => 'Control loop angle',
                        'thesis' => 'Human approvals and bounded agents make launch writing safer.',
                    ]],
                ]),
                EditorialActivityKind::ResearchChallenge->value => journeyOpenRouterResponse('journey-research', [
                    'claims' => [['statement' => 'Grounded sources improve review quality.', 'supporting_source_ids' => [], 'supporting_source_refs' => ['grounded'], 'supporting_quotations' => [['source_ref' => 'grounded', 'quote' => 'bounded review catches unsupported launch claims']]]],
                    'sourceReferences' => [['local_id' => 'grounded', 'url' => 'https://example.com/grounded', 'title' => 'Grounded source', 'content' => 'Model-written evidence must be ignored.']],
                    'contradictions' => [],
                    'gaps' => [],
                ], [['type' => 'url_citation', 'url_citation' => ['url' => 'https://example.com/grounded', 'content' => 'Retrieved annotation says bounded review catches unsupported launch claims.']]]),
                EditorialActivityKind::Plan->value => journeyOpenRouterResponse('journey-plan', [
                    'outline' => [['heading' => 'Show the gates'], ['heading' => 'Show evidence and reviews']],
                    'argument' => 'The displayed plan uses the approved control loop angle and grounded research.',
                    'visualPlan' => [['slot' => 'diagram', 'description' => 'Approval and review loop']],
                ]),
                EditorialActivityKind::Draft->value => journeyOpenRouterResponse('journey-draft', [
                    'document' => ['version' => 1, 'type' => 'doc', 'content' => [[
                        'type' => 'paragraph',
                        'attrs' => ['id' => 'blk_0000000000000002'],
                        'content' => [['type' => 'text', 'text' => 'Agent draft says the loop needs one clearer sentence.']],
                    ]]],
                    'metadataProposals' => [],
                ]),
                EditorialActivityKind::ReviewFacts->value => journeyOpenRouterResponse('journey-review-facts', [
                    'findings' => [[
                        'statement' => 'Clarify the control-loop sentence with grounded support.',
                        'kind' => 'claim',
                        'severity' => 'advisory',
                        'block_id' => 'blk_0000000000000002',
                        'supporting_source_ids' => [journeyFirstEvidenceId()],
                        'supporting_quotations' => [[
                            'source_id' => journeyFirstEvidenceId(),
                            'quote' => 'bounded review catches unsupported launch claims',
                        ]],
                        'proposed_patch' => [
                            'block_id' => 'blk_0000000000000002',
                            'replacement' => ['type' => 'paragraph', 'attrs' => ['id' => 'blk_0000000000000002'], 'content' => [['type' => 'text', 'text' => 'Agent draft says bounded review catches unsupported launch claims before release.']]],
                        ],
                    ]],
                ]),
                EditorialActivityKind::ReviewVoice->value, EditorialActivityKind::ReviewBuyer->value => journeyOpenRouterResponse('journey-'.$kind, ['findings' => []]),
                EditorialActivityKind::Reconciliation->value => journeyOpenRouterResponse('journey-reconciliation', ['groups' => [], 'conflicts' => []]),
                EditorialActivityKind::Recheck->value => journeyOpenRouterResponse('journey-recheck', ['resolved' => [['block_id' => 'blk_0000000000000002', 'status' => 'resolved']], 'unresolved' => [], 'newBlockingFindings' => []]),
                default => throw new RuntimeException('Unexpected journey kind '.$kind),
            });
        },
    ]);

    $actor = journeyUser();
    $write = app(WriteArticle::class);
    $archive = $write->capture($actor, 'Published archive voice', 'published-archive-voice');
    $publishedArchive = $write->save($actor, $archive, null, journeyDocument('Archive voice sample with direct, concrete editorial language.', 'archive-voice'), ['title' => 'Archive voice sample'], 'journey-archive');
    $publishedRelease = ArticleRelease::factory()->imported()->create([
        'article_id' => $archive->id,
        'attempt_id' => null,
        'revision_id' => $publishedArchive->id,
    ]);
    $archive->forceFill(['published_release_id' => $publishedRelease->id, 'first_published_at' => $publishedRelease->published_at])->save();
    $write->save($actor, $archive, $publishedArchive->id, journeyDocument('Private working draft voice must not enter prompts.', 'archive-private'), ['title' => 'Private archive draft'], 'journey-archive-private');

    $article = $write->capture($actor, 'Journey article', 'journey-article');
    $seed = $write->save($actor, $article, null, ['version' => 1, 'type' => 'doc', 'content' => []], ['title' => 'Journey article'], 'journey-seed');
    $attempt = app(AdvancePublishingAttempt::class)->develop($actor, $article, $seed->id, ['goal' => 'Initial owner brief']);
    $ownerSource = EvidenceSource::create([
        'article_id' => $article->id,
        'attempt_id' => $attempt->id,
        'source_type' => 'owner',
        'title' => 'Owner retained proof',
        'extracted_text' => 'Owner retained proof says approvals stay bounded.',
        'content_hash' => hash('sha256', 'Owner retained proof says approvals stay bounded.'),
    ]);

    $this->actingAs($actor)->get('http://admin.birdcar.test/publishing')->assertOk()->assertSee('Publishing workspace');

    Livewire\Livewire::actingAs($actor)
        ->test('admin.publishing.article-workspace', ['article' => $article->fresh()])
        ->set('selectedVoiceSampleArticleIds', [$archive->id])
        ->set('selectedEvidenceSourceIds', [$ownerSource->id])
        ->call('startInterview')
        ->assertSet('saveError', null);

    journeyDrainEditorialActivities(1);

    Livewire\Livewire::actingAs($actor)
        ->test('admin.publishing.article-workspace', ['article' => $article->fresh()])
        ->assertSee('What proof should the owner include before approving this angle?')
        ->assertSee('Control loop angle')
        ->call('selectAngleOption', '0')
        ->call('approveAngle', app(ApprovePublishingStage::class)->inputHashFor($attempt->fresh(), ApprovalKind::Angle))
        ->assertSet('saveError', 'Angle approval requires answers to the latest interview questions.')
        ->set('interviewAnswers', 'Use the retained owner proof and the retrieved source.')
        ->call('submitInterviewAnswers')
        ->call('approveAngle')
        ->assertSet('saveError', null);

    journeyDrainEditorialActivities(2);

    Livewire\Livewire::actingAs($actor)
        ->test('admin.publishing.article-workspace', ['article' => $article->fresh()])
        ->assertSee('A plan for the piece')
        ->assertSee('grounded research')
        ->call('approvePlan')
        ->assertSet('saveError', null);

    journeyDrainEditorialActivities(5);

    $draftRevision = $article->fresh()->workingRevision()->firstOrFail();
    $finding = EditorialFinding::query()->where('attempt_id', $attempt->id)->whereNotNull('proposed_patch')->firstOrFail();

    Livewire\Livewire::actingAs($actor)
        ->test('admin.publishing.article-workspace', ['article' => $article->fresh()])
        ->assertSee('Clarify the control-loop sentence')
        ->call('applyProposal', $finding->id)
        ->assertSet('saveError', null)
        ->call('finishReview')
        ->assertSet('saveError', null);

    journeyDrainEditorialActivities(1);

    $recheck = EditorialActivity::query()->where('attempt_id', $attempt->id)->where('kind', EditorialActivityKind::Recheck->value)->firstOrFail();
    $targetRevision = $article->fresh()->workingRevision()->firstOrFail();

    expect($targetRevision->id)->not->toBe($draftRevision->id)
        ->and($recheck->status)->toBe(EditorialActivityStatus::Completed)
        ->and($recheck->input['reviewed_revision_id'])->toBe($draftRevision->id)
        ->and($recheck->input['expected_revision_id'])->toBe($targetRevision->id)
        ->and(EvidenceSource::query()->where('attempt_id', $attempt->id)->where('extracted_text', 'Retrieved annotation says bounded review catches unsupported launch claims.')->exists())->toBeTrue();

    Http::assertSent(function (Request $request): bool {
        $prompt = json_decode((string) data_get($request->data(), 'messages.1.content'), true, flags: JSON_THROW_ON_ERROR);

        return data_get($prompt, 'kind') === EditorialActivityKind::Plan->value
            && str_contains(json_encode($prompt, JSON_THROW_ON_ERROR), 'Retrieved annotation says bounded review catches unsupported launch claims.')
            && str_contains(json_encode($prompt, JSON_THROW_ON_ERROR), 'Owner retained proof says approvals stay bounded.');
    });

    Http::assertSent(function (Request $request): bool {
        $prompt = json_decode((string) data_get($request->data(), 'messages.1.content'), true, flags: JSON_THROW_ON_ERROR);

        $encoded = json_encode($prompt, JSON_THROW_ON_ERROR);

        return data_get($prompt, 'kind') === EditorialActivityKind::ReviewFacts->value
            && str_contains($encoded, 'Archive voice sample with direct, concrete editorial language.')
            && ! str_contains($encoded, 'Private working draft voice must not enter prompts.');
    });
});

function journeyConfigureAgents(): void
{
    setPublishingAgentsPaused(false);
    config()->set('ai.providers.openrouter.key', 'test-key');
}

/**
 * @param  array<string, mixed>  $payload
 * @param  list<array<string, mixed>>  $annotations
 * @return array<string, mixed>
 */
function journeyOpenRouterResponse(string $id, array $payload, array $annotations = []): array
{
    return [
        'id' => $id,
        'choices' => [['message' => ['content' => json_encode($payload, JSON_THROW_ON_ERROR), 'annotations' => $annotations]]],
    ];
}

function journeyUser(): User
{
    $user = User::factory()->create();
    $user->assignRole(AdminRole::Access->value);
    $user->assignRole(PublishingRole::Author->value);

    return $user;
}

function journeyDrainEditorialActivities(int $limit = 10): void
{
    for ($i = 0; $i < $limit; $i++) {
        $activity = EditorialActivity::query()
            ->where('status', EditorialActivityStatus::Pending->value)
            ->oldest('id')
            ->first();

        if (! $activity instanceof EditorialActivity) {
            return;
        }

        app(RunEditorialActivity::class, ['activityId' => $activity->id])->handle(app(EditorialOutput::class), app(WriteArticle::class));
    }
}

function journeyFirstEvidenceId(): int
{
    return (int) EvidenceSource::query()
        ->where('extracted_text', 'Retrieved annotation says bounded review catches unsupported launch claims.')
        ->value('id');
}

function journeyDocument(string $text, string $id): array
{
    return ['version' => 1, 'type' => 'doc', 'content' => [[
        'type' => 'paragraph',
        'attrs' => ['id' => $id],
        'content' => [['type' => 'text', 'text' => $text]],
    ]]];
}
