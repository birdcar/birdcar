<?php

use App\Actions\Publishing\AdvancePublishingAttempt;
use App\Actions\Publishing\ApprovePublishingStage;
use App\Actions\Publishing\CheckArticleRelease;
use App\Actions\Publishing\ManageArticleRelease;
use App\Actions\Publishing\WriteArticle;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\EditorialActivity;
use App\Models\EditorialFinding;
use App\Models\EvidenceSource;
use App\Models\Publishing\ApprovalKind;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\PublishingAttempt;
use App\Models\User;
use App\Services\Publishing\PublishingFingerprint;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
});

test('release readiness reports blocking package completeness findings before preparation', function (): void {
    $actor = readinessAuthor();
    $write = app(WriteArticle::class);
    $article = $write->capture($actor, 'Missing metadata.', 'missing-metadata');
    $revision = $write->save($actor, $article, null, readinessDocument('Body with enough public text.'), ['title' => 'Missing metadata'], 'missing-1');
    $attempt = readinessPrerequisites($actor, app(AdvancePublishingAttempt::class)->develop($actor, $article, $revision->id));

    $check = app(CheckArticleRelease::class)->check($actor, $attempt, $revision->id, 'missing-metadata');

    expect($check['blocking'])->toBeTrue()
        ->and(array_column($check['findings'], 'code'))->toContain('metadata.description.required', 'metadata.date.required');

    expect(fn () => app(ManageArticleRelease::class)->prepare($actor, $attempt, $revision->id, 'missing-metadata'))
        ->toThrow(RuntimeException::class, 'Release readiness has blocking findings');
});

test('safe article packages include a current readiness hash and stale findings stop approval', function (): void {
    $actor = readinessAuthor();
    $write = app(WriteArticle::class);
    $article = $write->capture($actor, 'Stale readiness.', 'stale-readiness');
    $revision = $write->save($actor, $article, null, readinessDocument('Supported body.'), readinessMetadata('Stale Readiness'), 'stale-1');
    $attempt = readinessPrerequisites($actor, app(AdvancePublishingAttempt::class)->develop($actor, $article, $revision->id));
    $release = app(ManageArticleRelease::class)->prepare($actor, $attempt, $revision->id, 'stale-readiness');

    EditorialFinding::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'review_cycle' => $attempt->review_cycle,
        'revision_id' => $revision->id,
        'input_hash' => $revision->content_hash,
        'lens' => 'review_facts',
        'kind' => 'style',
        'severity' => 'advisory',
        'statement' => 'A new advisory finding appeared after readiness.',
        'supporting_source_ids' => [],
    ]);

    expect($release->payload['readiness_check']['input_hash'])->toBeString()->not->toBe('');

    expect(fn () => app(ManageArticleRelease::class)->approve($actor, $release, $release->release_hash))
        ->toThrow(RuntimeException::class, 'readiness check is stale');
});

test('release freshness hashes evidence contents', function (): void {
    $actor = readinessAuthor();
    $write = app(WriteArticle::class);
    $article = $write->capture($actor, 'Evidence freshness.', 'evidence-freshness');
    $revision = $write->save($actor, $article, null, readinessDocument('Supported body.'), readinessMetadata('Evidence Freshness'), 'evidence-freshness-1');
    $attempt = readinessPrerequisites($actor, app(AdvancePublishingAttempt::class)->develop($actor, $article, $revision->id));
    $source = EvidenceSource::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'source_type' => 'url',
        'url' => 'https://example.com/source',
        'final_url' => 'https://example.com/source',
        'title' => 'Original source',
        'retrieval_method' => 'fixture',
        'extracted_text' => 'Original supporting text.',
        'content_hash' => hash('sha256', 'source-v1'),
        'origin_metadata' => ['provider' => 'fixture'],
        'publication_permission' => true,
        'retrieved_at' => now(),
    ]);
    $releases = app(ManageArticleRelease::class);
    $release = $releases->prepare($actor, $attempt, $revision->id, 'evidence-freshness');

    $approval = $releases->approve($actor, $release, $release->release_hash);
    expect($approval->release_id)->toBe($release->id);

    $source->forceFill([
        'extracted_text' => 'Changed supporting text.',
        'content_hash' => hash('sha256', 'source-v2'),
        'publication_permission' => true,
    ])->save();
    $freshRelease = $releases->prepare($actor, $attempt->fresh(), $revision->id, 'evidence-freshness');
    $source->forceFill([
        'extracted_text' => 'Changed supporting text again.',
        'content_hash' => hash('sha256', 'source-v3'),
        'publication_permission' => true,
    ])->save();

    expect(fn () => $releases->approve($actor, $freshRelease, $freshRelease->release_hash))
        ->toThrow(RuntimeException::class, 'readiness check is stale');
});

test('release freshness hashes evidence with blank resolved restrictions', function (): void {
    $actor = readinessAuthor();
    $write = app(WriteArticle::class);
    $article = $write->capture($actor, 'Blank restriction freshness.', 'blank-restriction-freshness');
    $revision = $write->save($actor, $article, null, readinessDocument('Supported body.'), readinessMetadata('Blank Restriction Freshness'), 'blank-restriction-freshness-1');
    $attempt = readinessPrerequisites($actor, app(AdvancePublishingAttempt::class)->develop($actor, $article, $revision->id));
    $source = EvidenceSource::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'source_type' => 'url',
        'url' => 'https://example.com/blank-restriction',
        'title' => 'Blank restriction source',
        'retrieval_method' => 'fixture',
        'extracted_text' => 'Resolved supporting text.',
        'content_hash' => hash('sha256', 'blank-restriction-v1'),
        'publication_permission' => true,
        'unresolved_reason' => '   ',
        'retrieved_at' => now(),
    ]);

    $release = app(ManageArticleRelease::class)->prepare($actor, $attempt, $revision->id, 'blank-restriction-freshness');

    expect($release->payload['supporting_evidence_manifest'][0]['id'])->toBe($source->id)
        ->and($release->payload['supporting_evidence_manifest'][0]['unresolved_reason'])->toBeNull();

    $source->forceFill([
        'extracted_text' => 'Changed resolved supporting text.',
        'content_hash' => hash('sha256', 'blank-restriction-v2'),
    ])->save();

    expect(fn () => app(ManageArticleRelease::class)->approve($actor, $release, $release->release_hash))
        ->toThrow(RuntimeException::class, 'readiness check is stale');
});

test('release readiness blocks evidence without publication rights or unresolved restrictions', function (): void {
    $actor = readinessAuthor();
    $write = app(WriteArticle::class);
    $article = $write->capture($actor, 'Evidence rights.', 'evidence-rights');
    $revision = $write->save($actor, $article, null, readinessDocument('Supported body.'), readinessMetadata('Evidence Rights'), 'evidence-rights-1');
    $attempt = readinessPrerequisites($actor, app(AdvancePublishingAttempt::class)->develop($actor, $article, $revision->id));
    EvidenceSource::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'source_type' => 'restricted',
        'title' => 'Private source',
        'extracted_text' => 'Private supporting text.',
        'content_hash' => hash('sha256', 'private-source'),
        'publication_permission' => false,
        'restricted_processing_consent' => false,
        'unresolved_reason' => 'Owner has not cleared publication rights.',
    ]);

    $check = app(CheckArticleRelease::class)->check($actor, $attempt, $revision->id, 'evidence-rights');

    expect($check['blocking'])->toBeTrue()
        ->and(array_column($check['findings'], 'code'))->toContain(
            'evidence.publication_permission.required',
            'evidence.rights.unresolved',
            'evidence.restricted_processing_consent.required',
        );
    expect(fn () => app(ManageArticleRelease::class)->prepare($actor, $attempt, $revision->id, 'evidence-rights'))
        ->toThrow(RuntimeException::class, 'evidence.publication_permission.required');
});

test('release freshness hashes in-place finding dispositions and reasons', function (): void {
    $actor = readinessAuthor();
    $write = app(WriteArticle::class);
    $article = $write->capture($actor, 'Finding freshness.', 'finding-freshness');
    $revision = $write->save($actor, $article, null, readinessDocument('Supported body.'), readinessMetadata('Finding Freshness'), 'finding-freshness-1');
    $attempt = readinessPrerequisites($actor, app(AdvancePublishingAttempt::class)->develop($actor, $article, $revision->id));
    $finding = readinessFinding($attempt, $revision, [
        'disposition' => 'false_positive',
        'disposition_reason' => 'The sentence is clearly framed as a hypothetical.',
        'disposition_actor_id' => $actor->id,
        'disposed_at' => now(),
    ]);

    $release = app(ManageArticleRelease::class)->prepare($actor, $attempt, $revision->id, 'finding-freshness');
    $finding->forceFill(['disposition_reason' => 'Changed after preparation.'])->save();

    expect(fn () => app(ManageArticleRelease::class)->approve($actor, $release, $release->release_hash))
        ->toThrow(RuntimeException::class, 'readiness check is stale');
});

test('incomplete same-revision reviews are structured blocking readiness findings', function (): void {
    $actor = readinessAuthor();
    $write = app(WriteArticle::class);
    $article = $write->capture($actor, 'Incomplete reviews.', 'incomplete-reviews');
    $revision = $write->save($actor, $article, null, readinessDocument('Review prerequisites body.'), readinessMetadata('Incomplete Reviews'), 'incomplete-review-1');
    $attempt = app(AdvancePublishingAttempt::class)->develop($actor, $article, $revision->id);

    $check = app(CheckArticleRelease::class)->check($actor, $attempt, $revision->id, 'incomplete-reviews');

    expect($check['blocking'])->toBeTrue()
        ->and(array_column($check['findings'], 'code'))->toContain('review.prerequisite.incomplete');
});

test('completed review lineage is blocked by active same-revision review work', function (): void {
    $actor = readinessAuthor();
    $write = app(WriteArticle::class);
    $article = $write->capture($actor, 'Active review work.', 'active-review-work');
    $revision = $write->save($actor, $article, null, readinessDocument('Review work body.'), readinessMetadata('Active Review Work'), 'active-review-work-1');
    $attempt = readinessPrerequisites($actor, app(AdvancePublishingAttempt::class)->develop($actor, $article, $revision->id));

    foreach ([EditorialActivityKind::ReviewFacts->value => EditorialActivityStatus::Running, EditorialActivityKind::Reconciliation->value => EditorialActivityStatus::Failed] as $kind => $status) {
        EditorialActivity::create([
            'article_id' => $attempt->article_id,
            'attempt_id' => $attempt->id,
            'initiating_user_id' => $actor->id,
            'kind' => $kind,
            'status' => $status,
            'stage' => 'in_review',
            'input_version' => $attempt->input_version,
            'revision_id' => $revision->id,
            'revision_hash' => $revision->content_hash,
            'review_cycle' => $attempt->review_cycle,
            'batch_key' => 'active-review-'.$attempt->id.'-'.$kind,
            'idempotency_key' => 'active-review-'.$attempt->id.'-'.$kind,
            'prompt_version' => 1,
            'prompt_hash' => hash('sha256', 'active-review-'.$attempt->id.$kind),
            'input' => [],
            'model_snapshot' => [],
            'response' => [],
        ]);
    }

    $check = app(CheckArticleRelease::class)->check($actor, $attempt->fresh(), $revision->id, 'active-review-work');

    expect($check['blocking'])->toBeTrue()
        ->and(array_column($check['findings'], 'code'))->toContain('review.prerequisite.running', 'review.prerequisite.stale');
    expect(fn () => app(ManageArticleRelease::class)->prepare($actor, $attempt->fresh(), $revision->id, 'active-review-work'))
        ->toThrow(RuntimeException::class, 'review.prerequisite.running');
});

test('scheduled release preparation rejects past schedule instants', function (): void {
    $actor = readinessAuthor();
    $write = app(WriteArticle::class);
    $article = $write->capture($actor, 'Past schedule.', 'past-schedule');
    $revision = $write->save($actor, $article, null, readinessDocument('Past schedule body.'), readinessMetadata('Past Schedule'), 'past-schedule-1');
    $attempt = readinessPrerequisites($actor, app(AdvancePublishingAttempt::class)->develop($actor, $article, $revision->id));

    $scheduledAt = now()->subSecond()->utc()->startOfSecond();

    expect(fn () => app(ManageArticleRelease::class)->prepare($actor, $attempt, $revision->id, 'past-schedule', $scheduledAt, [
        'selected_timezone' => 'UTC',
        'scheduled_wall_time' => $scheduledAt->copy()->format('Y-m-d H:i:s'),
    ]))->toThrow(RuntimeException::class, 'future scheduled_at');
});

test('malformed public metadata dates are structured blocking readiness findings', function (): void {
    $actor = readinessAuthor();
    $write = app(WriteArticle::class);
    $article = $write->capture($actor, 'Invalid date.', 'invalid-date');
    $metadata = readinessMetadata('Invalid Date');
    $metadata['date'] = 'not a real date';
    $revision = $write->save($actor, $article, null, readinessDocument('Invalid date body.'), $metadata, 'invalid-date-1');
    $attempt = readinessPrerequisites($actor, app(AdvancePublishingAttempt::class)->develop($actor, $article, $revision->id));

    $check = app(CheckArticleRelease::class)->check($actor, $attempt, $revision->id, 'invalid-date');

    expect($check['blocking'])->toBeTrue()
        ->and(array_column($check['findings'], 'code'))->toContain('metadata.date.invalid')
        ->not->toContain('metadata.date.required');
});

test('first publication packages and delivery use the validated metadata public date', function (): void {
    $actor = readinessAuthor();
    $write = app(WriteArticle::class);
    $article = $write->capture($actor, 'First public date.', 'first-public-date');
    $revision = $write->save($actor, $article, null, readinessDocument('First public body.'), readinessMetadata('First Public Date'), 'first-public-date-1');
    $attempt = readinessPrerequisites($actor, app(AdvancePublishingAttempt::class)->develop($actor, $article, $revision->id));

    $release = app(ManageArticleRelease::class)->prepare($actor, $attempt, $revision->id, 'first-public-date');
    app(ManageArticleRelease::class)->approve($actor, $release, $release->release_hash);
    app(ManageArticleRelease::class)->deliver($actor, $release, null);

    expect($release->payload['original_public_date'])->toBe('2024-01-01T00:00:00.000000Z')
        ->and($article->fresh()?->first_published_at?->toISOString())->toBe('2024-01-01T00:00:00.000000Z');
});

test('safe chart and svg assets can be packaged while invalid assets block readiness', function (): void {
    $actor = readinessAuthor();
    $write = app(WriteArticle::class);
    $article = $write->capture($actor, 'Safe assets.', 'safe-assets');
    $revision = $write->save($actor, $article, null, readinessAssetDocument(), readinessMetadata('Safe Assets'), 'safe-assets-1');
    $attempt = readinessPrerequisites($actor, app(AdvancePublishingAttempt::class)->develop($actor, $article, $revision->id));

    $release = app(ManageArticleRelease::class)->prepare($actor, $attempt, $revision->id, 'safe-assets');
    expect($release->payload['rendered_document']['html'])->toContain('View chart data', 'Safe diagram.');

    $invalid = readinessRevisionBypassingSave($actor, 'invalid-assets', readinessInvalidAssetDocument(), readinessMetadata('Invalid Assets'));
    $invalidAttempt = readinessPrerequisites($actor, app(AdvancePublishingAttempt::class)->develop($actor, $invalid->article, $invalid->id));
    $check = app(CheckArticleRelease::class)->check($actor, $invalidAttempt, $invalid->id, 'invalid-assets');

    expect($check['blocking'])->toBeTrue()
        ->and(array_column($check['findings'], 'code'))->toContain('document.schema');
});

test('reasoned false-positive dispositions clear blockers but bare false positives and accepted blockers do not', function (): void {
    $actor = readinessAuthor();
    $write = app(WriteArticle::class);
    $article = $write->capture($actor, 'False positive blockers.', 'false-positive-blockers');
    $revision = $write->save($actor, $article, null, readinessDocument('Supported body.'), readinessMetadata('False Positive Blockers'), 'false-positive-1');
    $attempt = readinessPrerequisites($actor, app(AdvancePublishingAttempt::class)->develop($actor, $article, $revision->id));
    $finding = readinessFinding($attempt, $revision, ['disposition' => 'false_positive']);

    $withoutReason = app(CheckArticleRelease::class)->check($actor, $attempt, $revision->id, 'false-positive-blockers');
    $finding->forceFill(['disposition_reason' => 'The cited sentence describes a hypothetical, not a factual claim.'])->save();
    $withReason = app(CheckArticleRelease::class)->check($actor, $attempt->fresh(), $revision->id, 'false-positive-blockers');
    $finding->forceFill(['disposition' => 'accepted', 'disposition_reason' => 'Publish anyway.'])->save();
    $accepted = app(CheckArticleRelease::class)->check($actor, $attempt->fresh(), $revision->id, 'false-positive-blockers');

    expect($withoutReason['blocking'])->toBeTrue()
        ->and($withReason['blocking'])->toBeFalse()
        ->and($accepted['blocking'])->toBeTrue();
});

test('advisory findings do not block exact package approval', function (): void {
    $actor = readinessAuthor();
    $write = app(WriteArticle::class);
    $article = $write->capture($actor, 'Advisory only.', 'advisory-only');
    $revision = $write->save($actor, $article, null, readinessDocument('Publishable body.'), readinessMetadata('Advisory Only'), 'advisory-1');
    $attempt = readinessPrerequisites($actor, app(AdvancePublishingAttempt::class)->develop($actor, $article, $revision->id));
    EditorialFinding::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'review_cycle' => $attempt->review_cycle,
        'revision_id' => $revision->id,
        'input_hash' => $revision->content_hash,
        'lens' => 'review_voice',
        'kind' => 'style',
        'severity' => 'advisory',
        'statement' => 'Consider a shorter sentence.',
        'supporting_source_ids' => [],
    ]);

    $release = app(ManageArticleRelease::class)->prepare($actor, $attempt, $revision->id, 'advisory-only');
    $approval = app(ManageArticleRelease::class)->approve($actor, $release, $release->release_hash);

    expect($approval->release_id)->toBe($release->id);
});

function readinessAuthor(): User
{
    $user = User::factory()->create();
    $user->assignRole(PublishingRole::Author->value);

    return $user;
}

function readinessPrerequisites(User $actor, PublishingAttempt $attempt): PublishingAttempt
{
    $approve = app(ApprovePublishingStage::class);
    $approve->approve($actor, $attempt, ApprovalKind::Angle, $approve->inputHashFor($attempt, ApprovalKind::Angle));
    EditorialActivity::create([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'initiating_user_id' => $actor->id,
        'kind' => EditorialActivityKind::ResearchChallenge,
        'status' => EditorialActivityStatus::Completed,
        'stage' => 'drafting',
        'input_version' => $attempt->input_version,
        'revision_id' => null,
        'revision_hash' => null,
        'review_cycle' => $attempt->review_cycle,
        'batch_key' => 'readiness-research-'.$attempt->id,
        'idempotency_key' => 'readiness-research-'.$attempt->id,
        'prompt_version' => 1,
        'prompt_hash' => hash('sha256', 'readiness-research-'.$attempt->id),
        'input' => ['approval_hashes' => [ApprovalKind::Angle->value => app(ApprovePublishingStage::class)->inputHashFor($attempt, ApprovalKind::Angle)]],
        'model_snapshot' => [],
        'response' => [],
        'completed_at' => now(),
    ]);
    $attempt = app(AdvancePublishingAttempt::class)->rethink($actor, $attempt->fresh(), plan: ['outline' => ['intro'], 'visualPlan' => ['none']]);
    $approve->approve($actor, $attempt, ApprovalKind::Plan, $approve->inputHashFor($attempt, ApprovalKind::Plan));
    $attempt = $attempt->fresh();

    foreach ([EditorialActivityKind::ReviewFacts, EditorialActivityKind::ReviewVoice, EditorialActivityKind::ReviewBuyer, EditorialActivityKind::Reconciliation] as $kind) {
        EditorialActivity::create([
            'article_id' => $attempt->article_id,
            'attempt_id' => $attempt->id,
            'initiating_user_id' => $actor->id,
            'kind' => $kind,
            'status' => EditorialActivityStatus::Completed,
            'stage' => 'in_review',
            'input_version' => $attempt->input_version,
            'revision_id' => $attempt->article()->firstOrFail()->working_revision_id,
            'revision_hash' => $attempt->article()->firstOrFail()->workingRevision()->firstOrFail()->content_hash,
            'review_cycle' => $attempt->review_cycle,
            'batch_key' => 'readiness-review-'.$attempt->id.'-'.$kind->value,
            'idempotency_key' => 'readiness-review-'.$attempt->id.'-'.$kind->value,
            'prompt_version' => 1,
            'prompt_hash' => hash('sha256', 'readiness-review-'.$attempt->id.$kind->value),
            'input' => [],
            'model_snapshot' => [],
            'response' => [],
            'completed_at' => now(),
        ]);
    }

    return $attempt->fresh();
}

/** @return array<string, mixed> */
function readinessAssetDocument(): array
{
    return [
        'version' => 1,
        'type' => 'doc',
        'content' => [
            ['type' => 'paragraph', 'text' => 'Asset body.'],
            ['type' => 'chart', 'attrs' => [
                'chartType' => 'bar',
                'caption' => 'Release chart.',
                'x' => 'category',
                'series' => [['key' => 'loops', 'label' => 'Loops']],
                'data' => [['category' => 'spec', 'loops' => 2]],
            ]],
            ['type' => 'diagram', 'attrs' => [
                'sourceType' => 'svg',
                'caption' => 'Safe diagram.',
                'source' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><title>Safe</title><desc>Safe</desc><circle cx="5" cy="5" r="4" fill="none" stroke="currentColor" /></svg>',
            ]],
        ],
    ];
}

/** @return array<string, mixed> */
function readinessInvalidAssetDocument(): array
{
    return [
        'version' => 1,
        'type' => 'doc',
        'content' => [
            ['type' => 'paragraph', 'attrs' => ['id' => 'blk_1111111111111111'], 'text' => 'Invalid assets body.'],
            ['type' => 'chart', 'attrs' => [
                'id' => 'blk_2222222222222222',
                'chartType' => 'pie',
                'caption' => '',
                'x' => 'category',
                'series' => [['key' => 'loops', 'label' => 'Loops']],
                'data' => [],
            ]],
            ['type' => 'diagram', 'attrs' => [
                'id' => 'blk_3333333333333333',
                'sourceType' => 'svg',
                'caption' => 'Unsafe diagram.',
                'source' => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
            ]],
        ],
    ];
}

/** @param array<string, mixed> $metadata */
function readinessRevisionBypassingSave(User $actor, string $slug, array $document, array $metadata): ArticleRevision
{
    $article = Article::factory()->create(['author_id' => $actor->id, 'slug' => $slug, 'idea' => $metadata['title'] ?? $slug]);
    $revision = ArticleRevision::factory()->create([
        'article_id' => $article->id,
        'created_by' => $actor->id,
        'document' => $document,
        'metadata' => $metadata,
        'content_hash' => app(PublishingFingerprint::class)->hash(['document' => $document, 'metadata' => $metadata]),
    ]);
    $article->forceFill(['working_revision_id' => $revision->id])->save();

    return $revision->refresh();
}

/** @param array<string, mixed> $overrides */
function readinessFinding(PublishingAttempt $attempt, ArticleRevision $revision, array $overrides = []): EditorialFinding
{
    return EditorialFinding::create(array_merge([
        'article_id' => $attempt->article_id,
        'attempt_id' => $attempt->id,
        'review_cycle' => $attempt->review_cycle,
        'revision_id' => $revision->id,
        'input_hash' => $revision->content_hash,
        'lens' => 'review_facts',
        'kind' => 'claim',
        'severity' => 'blocking',
        'statement' => 'The material claim needs support.',
        'supporting_source_ids' => [],
    ], $overrides));
}

/** @return array<string, mixed> */
function readinessMetadata(string $title): array
{
    return [
        'title' => $title,
        'description' => $title.' description.',
        'date' => '2024-01-01',
        'tags' => ['testing'],
    ];
}

/** @return array<string, mixed> */
function readinessDocument(string $text): array
{
    return [
        'version' => 1,
        'type' => 'doc',
        'content' => [
            ['type' => 'paragraph', 'text' => $text],
        ],
    ];
}
