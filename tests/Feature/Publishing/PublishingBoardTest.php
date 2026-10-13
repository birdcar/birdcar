<?php

use App\Actions\Publishing\MovePublishingPiece;
use App\Actions\Publishing\WriteArticle;
use App\Authorization\Admin\Role as AdminRole;
use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Models\Article;
use App\Models\ArticleRelease;
use App\Models\ArticleRevision;
use App\Models\EditorialActivity;
use App\Models\EditorialFinding;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\Publishing\EditorialStage;
use App\Models\Publishing\PieceKind;
use App\Models\PublishingAttempt;
use App\Models\User;
use App\Services\Publishing\BoardColumn;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
});

function publishingBoardAuthor(): User
{
    $user = User::factory()->create();
    $user->assignRole([AdminRole::Access->value, PublishingRole::Author->value]);

    return $user;
}

function publishingBoardWriter(): User
{
    $user = User::factory()->create();
    $user->assignRole(AdminRole::Access->value);
    $user->givePermissionTo(PublishingPermission::View->value, PublishingPermission::Write->value);

    return $user;
}

/**
 * @param  array<string, mixed>  $attempt
 */
function publishingBoardPiece(User $user, string $idea, ?EditorialStage $stage = null, array $attempt = []): Article
{
    $piece = Article::factory()->idea($idea)->create(['author_id' => $user->id]);

    if ($stage instanceof EditorialStage) {
        $created = PublishingAttempt::factory()->create([...$attempt, 'article_id' => $piece->id, 'user_id' => $user->id, 'stage' => $stage]);
        $piece->forceFill(['current_attempt_id' => $created->id])->save();
    }

    return $piece->fresh();
}

/**
 * @param  array<string, mixed>  $attributes
 */
function publishingBoardActivity(Article $piece, EditorialActivityKind $kind, EditorialActivityStatus $status, array $attributes = []): EditorialActivity
{
    return EditorialActivity::factory()->create([
        'article_id' => $piece->id,
        'attempt_id' => $piece->current_attempt_id,
        'initiating_user_id' => $piece->author_id,
        'kind' => $kind,
        'status' => $status,
        ...$attributes,
    ]);
}

function publishingBoardRevision(Article $piece, string $title = 'Board working title'): ArticleRevision
{
    $revision = ArticleRevision::factory()->create([
        'article_id' => $piece->id,
        'created_by' => $piece->author_id,
        'metadata' => ['title' => $title, 'description' => 'Description', 'tags' => []],
    ]);
    $piece->forceFill(['working_revision_id' => $revision->id])->save();

    return $revision;
}

function publishingBoardPublish(Article $piece): Article
{
    $revision = publishingBoardRevision($piece, 'Board live title');
    $release = ArticleRelease::factory()->imported()->create(['article_id' => $piece->id, 'revision_id' => $revision->id]);
    $piece->forceFill(['published_release_id' => $release->id, 'first_published_at' => now()->subDay()])->save();

    return $piece->fresh();
}

/**
 * A piece in review whose draft has completed.
 */
function publishingBoardDrafted(User $user, string $idea = 'Drafted board piece'): Article
{
    $piece = publishingBoardPiece($user, $idea, EditorialStage::InReview);
    publishingBoardActivity($piece, EditorialActivityKind::Draft, EditorialActivityStatus::Completed, ['stage' => EditorialStage::InReview->value]);

    return $piece;
}

function publishingBoardKit(User $user, Article $flagship, PieceKind $kind, EditorialStage $stage): Article
{
    $kit = app(WriteArticle::class)->addPiece($user, $flagship->contentIdea, $kind, $flagship);
    $attempt = PublishingAttempt::factory()->create(['article_id' => $kit->id, 'user_id' => $user->id, 'stage' => $stage]);
    $kit->forceFill(['current_attempt_id' => $attempt->id])->save();

    return $kit->fresh();
}

function publishingBoardScenario(User $user, string $scenario): Article
{
    return match ($scenario) {
        'an idea' => publishingBoardPiece($user, 'Board idea'),
        'an archive import' => publishingBoardPublish(publishingBoardPiece($user, 'Board import')),
        'developing' => publishingBoardPiece($user, 'Board piece', EditorialStage::Developing),
        'drafting' => publishingBoardPiece($user, 'Board piece', EditorialStage::Drafting),
        'in review before any draft' => publishingBoardPiece($user, 'Board piece', EditorialStage::InReview),
        'in review while a repair runs' => tap(publishingBoardDrafted($user), fn (Article $piece): EditorialActivity => publishingBoardActivity($piece, EditorialActivityKind::LintRepair, EditorialActivityStatus::Running)),
        'in review after the draft' => publishingBoardDrafted($user),
        'approved' => publishingBoardPiece($user, 'Board piece', EditorialStage::Approved),
        'scheduled' => publishingBoardPiece($user, 'Board piece', EditorialStage::Scheduled),
        'published' => publishingBoardPublish(publishingBoardPiece($user, 'Board piece', EditorialStage::Published)),
        'published with a new attempt' => publishingBoardPublish(publishingBoardPiece($user, 'Board piece', EditorialStage::Drafting)),
        'parked' => publishingBoardPiece($user, 'Board piece', EditorialStage::Drafting, ['parked_at' => now(), 'parked_reason' => 'Waiting on numbers']),
        'killed' => publishingBoardPiece($user, 'Board piece', EditorialStage::Abandoned, ['abandoned_at' => now(), 'abandoned_reason' => 'Off strategy']),
        'abandoned without the abandoned stage' => publishingBoardPiece($user, 'Board piece', EditorialStage::InReview, ['abandoned_at' => now()]),
        'parked and then killed' => publishingBoardPiece($user, 'Board piece', EditorialStage::Abandoned, ['parked_at' => now(), 'abandoned_at' => now()]),
        default => throw new InvalidArgumentException("Unknown board scenario [{$scenario}]."),
    };
}

function publishingBoardXpath(string $html): DOMXPath
{
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8"?>'.$html);

    return new DOMXPath($document);
}

/**
 * @return DOMNodeList<DOMNameSpaceNode|DOMNode>
 */
function publishingBoardNodes(string $html, string $expression): DOMNodeList
{
    $nodes = publishingBoardXpath($html)->query($expression);

    if ($nodes === false) {
        throw new RuntimeException("Invalid XPath expression: {$expression}");
    }

    return $nodes;
}

/**
 * @return list<string>
 */
function publishingBoardColumnsOf(string $html, Article $piece): array
{
    $columns = [];
    foreach (publishingBoardNodes($html, sprintf('//*[@data-board-column][.//*[@data-board-card="%d"]]', $piece->id)) as $column) {
        if ($column instanceof DOMElement) {
            $columns[] = $column->getAttribute('data-board-column');
        }
    }

    return $columns;
}

function publishingBoardCardText(string $html, Article $piece): string
{
    $card = publishingBoardNodes($html, sprintf('//*[@data-board-card="%d"]', $piece->id))->item(0);

    if (! $card instanceof DOMElement) {
        throw new RuntimeException("No board card for piece [{$piece->id}].");
    }

    return trim((string) preg_replace('/\s+/u', ' ', $card->textContent));
}

function publishingBoardHtml(User $user): string
{
    return Livewire::actingAs($user)->test('admin.publishing.board')->html();
}

/**
 * One saved idea, one flagship in review with its three kit pieces, and one archive import.
 */
function publishingBoardShape(User $user, int $index): void
{
    publishingBoardPiece($user, "Shape idea {$index}");
    $flagship = publishingBoardDrafted($user, "Shape flagship {$index}");
    foreach ([PieceKind::LinkedinPost, PieceKind::BlueskyThread, PieceKind::EmailBlurb] as $kind) {
        $kit = publishingBoardKit($user, $flagship, $kind, EditorialStage::InReview);
        publishingBoardActivity($kit, EditorialActivityKind::DraftKit, EditorialActivityStatus::Completed);
    }
    publishingBoardPublish(publishingBoardPiece($user, "Shape import {$index}"));
}

test('each piece appears in exactly the column its stage and flags derive', function (string $scenario, BoardColumn $expected): void {
    $user = publishingBoardAuthor();
    $piece = publishingBoardScenario($user, $scenario);

    $html = publishingBoardHtml($user);

    expect(publishingBoardColumnsOf($html, $piece))->toBe([$expected->value]);
})->with([
    'an idea' => ['an idea', BoardColumn::Ideas],
    'an archive import' => ['an archive import', BoardColumn::Published],
    'developing' => ['developing', BoardColumn::Shaping],
    'drafting' => ['drafting', BoardColumn::Planning],
    'in review before any draft' => ['in review before any draft', BoardColumn::Writing],
    'in review while a repair runs' => ['in review while a repair runs', BoardColumn::Writing],
    'in review after the draft' => ['in review after the draft', BoardColumn::Review],
    'approved' => ['approved', BoardColumn::Ready],
    'scheduled' => ['scheduled', BoardColumn::Scheduled],
    'published' => ['published', BoardColumn::Published],
    'published with a new attempt' => ['published with a new attempt', BoardColumn::Planning],
    'parked' => ['parked', BoardColumn::Parked],
    'killed' => ['killed', BoardColumn::Killed],
    'abandoned without the abandoned stage' => ['abandoned without the abandoned stage', BoardColumn::Killed],
    'parked and then killed' => ['parked and then killed', BoardColumn::Killed],
]);

test('owner edits after the draft keep a piece in review', function (): void {
    $user = publishingBoardAuthor();
    $piece = publishingBoardDrafted($user);
    publishingBoardRevision($piece, 'Edited after the draft');

    $html = publishingBoardHtml($user);

    expect(publishingBoardColumnsOf($html, $piece))->toBe([BoardColumn::Review->value]);
});

test('the board shows only the viewer’s own pieces', function (): void {
    $user = publishingBoardAuthor();
    $other = publishingBoardAuthor();
    $own = publishingBoardPiece($user, 'My board idea');
    $theirs = publishingBoardPiece($other, 'Their board idea');

    $html = publishingBoardHtml($user);

    expect(publishingBoardColumnsOf($html, $own))->toBe([BoardColumn::Ideas->value])
        ->and(publishingBoardColumnsOf($html, $theirs))->toBe([]);
});

test('kit pieces nest in the flagship card with their own stage chips', function (): void {
    $user = publishingBoardAuthor();
    $flagship = publishingBoardDrafted($user, 'Flagship with a kit');
    $linkedin = publishingBoardKit($user, $flagship, PieceKind::LinkedinPost, EditorialStage::InReview);
    publishingBoardActivity($linkedin, EditorialActivityKind::DraftKit, EditorialActivityStatus::Completed);
    publishingBoardActivity($linkedin, EditorialActivityKind::ReviewKit, EditorialActivityStatus::Completed);
    $bluesky = publishingBoardKit($user, $flagship, PieceKind::BlueskyThread, EditorialStage::Approved);
    $email = publishingBoardKit($user, $flagship, PieceKind::EmailBlurb, EditorialStage::Published);

    $html = publishingBoardHtml($user);

    expect(publishingBoardNodes($html, '//*[@data-board-card]')->length)->toBe(1)
        ->and(publishingBoardColumnsOf($html, $flagship))->toBe([BoardColumn::Review->value])
        ->and(publishingBoardNodes($html, sprintf('//*[@data-board-card="%d"]//*[@data-board-kit]', $flagship->id))->length)->toBe(3);
    expect(publishingBoardCardText($html, $flagship))
        ->toContain('LinkedIn post In review Waiting on you')
        ->toContain('Bluesky thread Ready to post')
        ->toContain('Email blurb Posted');
    foreach ([$linkedin, $bluesky, $email] as $kit) {
        expect(publishingBoardColumnsOf($html, $kit))->toBe([]);
    }
});

test('status chips show running, queued, waiting and failed work with the reason', function (string $scenario, string $state, string $text): void {
    $user = publishingBoardAuthor();
    $piece = match ($scenario) {
        'running' => tap(publishingBoardPiece($user, 'Status piece', EditorialStage::InReview), fn (Article $piece): EditorialActivity => publishingBoardActivity($piece, EditorialActivityKind::Draft, EditorialActivityStatus::Running)),
        'queued while agents are paused' => tap(publishingBoardPiece($user, 'Status piece', EditorialStage::Developing), fn (Article $piece): EditorialActivity => publishingBoardActivity($piece, EditorialActivityKind::Interview, EditorialActivityStatus::Pending)),
        'failed' => tap(publishingBoardPiece($user, 'Status piece', EditorialStage::Drafting), fn (Article $piece): EditorialActivity => publishingBoardActivity($piece, EditorialActivityKind::Plan, EditorialActivityStatus::Failed, ['error_reason' => 'The provider timed out.'])),
        'interview answers' => tap(publishingBoardPiece($user, 'Status piece', EditorialStage::Developing), fn (Article $piece): EditorialActivity => publishingBoardActivity($piece, EditorialActivityKind::Interview, EditorialActivityStatus::AwaitingApproval)),
        'angle options' => publishingBoardPiece($user, 'Status piece', EditorialStage::Developing, ['interview_context' => ['angle_options' => ['a' => ['thesis' => 'One thesis']]]]),
        'plan ready' => publishingBoardPiece($user, 'Status piece', EditorialStage::Drafting, ['plan' => ['outline' => ['One section'], 'visualPlan' => ['One figure'], 'source' => 'owner']]),
        'open findings' => tap(publishingBoardDrafted($user), function (Article $piece): void {
            $revision = publishingBoardRevision($piece);
            EditorialFinding::factory()->create(['article_id' => $piece->id, 'attempt_id' => $piece->current_attempt_id, 'activity_id' => null, 'revision_id' => $revision->id, 'input_hash' => $revision->content_hash, 'review_cycle' => 1, 'reconciliation_state' => null]);
        }),
        default => throw new InvalidArgumentException("Unknown status scenario [{$scenario}]."),
    };

    $html = publishingBoardHtml($user);

    expect(publishingBoardNodes($html, sprintf('//*[@data-board-card="%d"]//*[@data-piece-status="%s"]', $piece->id, $state))->length)->toBe(1)
        ->and(publishingBoardCardText($html, $piece))->toContain($text);
})->with([
    'running' => ['running', 'running', 'Running Draft'],
    'queued while agents are paused' => ['queued while agents are paused', 'running', 'Queued; agents paused Interview'],
    'failed' => ['failed', 'failed', 'Plan failed The provider timed out.'],
    'interview answers' => ['interview answers', 'waiting', 'The interview is waiting for your answers.'],
    'angle options' => ['angle options', 'waiting', 'Choose and approve the angle.'],
    'plan ready' => ['plan ready', 'waiting', 'Review and approve the plan.'],
    'open findings' => ['open findings', 'waiting', 'Decide the open findings.'],
]);

test('an accepted package no longer waits on the owner', function (): void {
    $user = publishingBoardAuthor();
    $piece = publishingBoardDrafted($user);
    $package = publishingBoardActivity($piece, EditorialActivityKind::Package, EditorialActivityStatus::Completed);

    $waiting = publishingBoardHtml($user);
    ArticleRevision::factory()->create(['article_id' => $piece->id, 'created_by' => $user->id, 'origin' => 'agent-accepted', 'client_mutation_id' => "package-{$package->id}-0123456789abcdef01234567"]);
    $accepted = publishingBoardHtml($user);

    expect(publishingBoardCardText($waiting, $piece))->toContain('Accept or edit the package.')
        ->and(publishingBoardNodes($accepted, sprintf('//*[@data-board-card="%d"]//*[@data-piece-status]', $piece->id))->length)->toBe(0);
});

test('a prepared release on the working revision waits on the owner', function (): void {
    $user = publishingBoardAuthor();
    $piece = publishingBoardDrafted($user);
    $revision = publishingBoardRevision($piece);
    ArticleRelease::factory()->create(['article_id' => $piece->id, 'attempt_id' => $piece->current_attempt_id, 'revision_id' => $revision->id, 'status' => 'prepared']);

    $html = publishingBoardHtml($user);

    expect(publishingBoardCardText($html, $piece))->toContain('Approve the prepared release.');
});

test('dragging an idea to shaping starts development and queues the interview', function (): void {
    $user = publishingBoardAuthor();
    $piece = publishingBoardPiece($user, 'Develop me from the board');

    Livewire::actingAs($user)
        ->test('admin.publishing.board')
        ->call('moveCard', "{$piece->id}:ideas", 0, 'shaping')
        ->assertSet('moveRefused', false)
        ->assertSee('Development started. The interview has been queued.');

    $piece->refresh();
    $interview = EditorialActivity::query()->where('attempt_id', $piece->current_attempt_id)->sole();
    expect($piece->currentAttempt?->stage)->toBe(EditorialStage::Developing)
        ->and($interview->kind)->toBe(EditorialActivityKind::Interview)
        ->and($interview->status)->toBe(EditorialActivityStatus::Pending)
        ->and($interview->input['idea'] ?? null)->toBe('Develop me from the board');
});

test('a viewer without the develop permission cannot drag an idea to shaping', function (): void {
    $user = publishingBoardWriter();
    $piece = publishingBoardPiece($user, 'Writer idea');

    Livewire::actingAs($user)
        ->test('admin.publishing.board')
        ->assertDontSeeHtml('data-board-move="shaping"')
        ->call('moveCard', "{$piece->id}:ideas", 0, 'shaping')
        ->assertSet('moveRefused', true)
        ->assertSee('You don’t have permission to make that move.');

    expect($piece->fresh()->current_attempt_id)->toBeNull()
        ->and(EditorialActivity::query()->where('article_id', $piece->id)->exists())->toBeFalse();
});

test('moves across a gate are refused with the gate to cross and change nothing', function (string $scenario, BoardColumn $from, BoardColumn $to, string $message): void {
    $user = publishingBoardAuthor();
    $piece = publishingBoardScenario($user, $scenario);
    $stage = $piece->currentAttempt?->stage;
    $activities = EditorialActivity::query()->where('article_id', $piece->id)->count();

    Livewire::actingAs($user)
        ->test('admin.publishing.board')
        ->call('moveCard', "{$piece->id}:{$from->value}", 0, $to->value)
        ->assertSet('moveRefused', true)
        ->assertSee($message);

    expect($piece->fresh()->currentAttempt?->stage)->toBe($stage)
        ->and(EditorialActivity::query()->where('article_id', $piece->id)->count())->toBe($activities);
})->with([
    'shaping to planning' => ['developing', BoardColumn::Shaping, BoardColumn::Planning, 'Open the workspace to approve the angle.'],
    'planning to writing' => ['drafting', BoardColumn::Planning, BoardColumn::Writing, 'Open the workspace to approve the plan.'],
    'writing to review' => ['in review before any draft', BoardColumn::Writing, BoardColumn::Review, 'The piece moves to Review on its own when the draft finishes.'],
    'review to ready' => ['in review after the draft', BoardColumn::Review, BoardColumn::Ready, 'Open the workspace to approve the exact release.'],
    'ready to published' => ['approved', BoardColumn::Ready, BoardColumn::Published, 'Publish from the workspace.'],
    'planning back to shaping' => ['drafting', BoardColumn::Planning, BoardColumn::Shaping, 'Pieces don’t move backward on the board.'],
    'an idea to planning' => ['an idea', BoardColumn::Ideas, BoardColumn::Planning, 'Drag the idea to Shaping to start developing it.'],
    'an idea to parked' => ['an idea', BoardColumn::Ideas, BoardColumn::Parked, 'Ideas are already off the clock'],
    'published to killed' => ['published', BoardColumn::Published, BoardColumn::Killed, 'Published work is already live, so it can’t be parked or killed.'],
    'killed back to review' => ['killed', BoardColumn::Killed, BoardColumn::Review, 'Killed pieces stay where they are.'],
]);

test('ready to scheduled opens the workspace release checklist', function (): void {
    $user = publishingBoardAuthor();
    $piece = publishingBoardPiece($user, 'Ready piece', EditorialStage::Approved);

    Livewire::actingAs($user)
        ->test('admin.publishing.board')
        ->call('moveCard', "{$piece->id}:ready", 0, 'scheduled')
        ->assertRedirect(route('admin.publishing.articles.show', $piece));

    expect($piece->fresh()->currentAttempt?->stage)->toBe(EditorialStage::Approved);
});

test('reordering inside a column changes nothing and says nothing', function (): void {
    $user = publishingBoardAuthor();
    $piece = publishingBoardPiece($user, 'Reordered idea');

    Livewire::actingAs($user)
        ->test('admin.publishing.board')
        ->call('moveCard', "{$piece->id}:ideas", 3, 'ideas')
        ->assertSet('moveMessage', null);

    expect($piece->fresh()->current_attempt_id)->toBeNull();
});

test('parking asks for a reason and refuses a blank or overlong one', function (string $reason, string $message): void {
    $user = publishingBoardAuthor();
    $piece = publishingBoardPiece($user, 'Park me', EditorialStage::Drafting);

    Livewire::actingAs($user)
        ->test('admin.publishing.board')
        ->call('moveCard', "{$piece->id}:planning", 0, 'parked')
        ->assertSet('confirmingMove', true)
        ->set('moveReason', $reason)
        ->call('confirmMove')
        ->assertHasErrors('moveReason')
        ->assertSee($message);

    expect($piece->fresh()->currentAttempt?->parked_at)->toBeNull();
})->with([
    'blank' => ['   ', 'Say why you’re parking this piece.'],
    'too long' => [str_repeat('a', 1001), 'must not be greater than 1000 characters'],
]);

test('the move action itself refuses a blank reason', function (): void {
    $user = publishingBoardAuthor();
    $piece = publishingBoardPiece($user, 'Park me directly', EditorialStage::Drafting);

    expect(fn () => app(MovePublishingPiece::class)->move($user, $piece, BoardColumn::Planning, BoardColumn::Parked, '  '))
        ->toThrow(ValidationException::class);
    expect($piece->fresh()->currentAttempt?->parked_at)->toBeNull();
});

test('parking with a reason parks the piece and keeps its stage', function (): void {
    $user = publishingBoardAuthor();
    $piece = publishingBoardPiece($user, 'Park me', EditorialStage::Drafting);

    $component = Livewire::actingAs($user)
        ->test('admin.publishing.board')
        ->call('moveCard', "{$piece->id}:planning", 0, 'parked')
        ->set('moveReason', '  Waiting on client numbers  ')
        ->call('confirmMove')
        ->assertHasNoErrors()
        ->assertSet('confirmingMove', false)
        ->assertSee('Parked.');

    $attempt = $piece->fresh()->currentAttempt;
    expect($attempt?->parked_at)->not->toBeNull()
        ->and($attempt?->parked_reason)->toBe('Waiting on client numbers')
        ->and($attempt?->stage)->toBe(EditorialStage::Drafting)
        ->and(publishingBoardColumnsOf($component->html(), $piece))->toBe([BoardColumn::Parked->value]);
});

test('cancelling the reason dialog leaves the piece where it was', function (): void {
    $user = publishingBoardAuthor();
    $piece = publishingBoardPiece($user, 'Keep me', EditorialStage::Drafting);

    $component = Livewire::actingAs($user)
        ->test('admin.publishing.board')
        ->call('moveCard', "{$piece->id}:planning", 0, 'killed')
        ->call('cancelMove')
        ->assertSet('confirmingMove', false)
        ->assertSet('movePieceId', null);

    expect($piece->fresh()->currentAttempt?->abandoned_at)->toBeNull()
        ->and(publishingBoardColumnsOf($component->html(), $piece))->toBe([BoardColumn::Planning->value]);
});

test('killing a scheduled piece abandons it and withdraws the scheduled release', function (): void {
    $user = publishingBoardAuthor();
    $piece = publishingBoardPiece($user, 'Kill me', EditorialStage::Scheduled);
    $revision = publishingBoardRevision($piece);
    $release = ArticleRelease::factory()->create(['article_id' => $piece->id, 'attempt_id' => $piece->current_attempt_id, 'revision_id' => $revision->id, 'status' => 'scheduled', 'scheduled_at' => now()->addDay()]);

    Livewire::actingAs($user)
        ->test('admin.publishing.board')
        ->call('moveCard', "{$piece->id}:scheduled", 0, 'killed')
        ->set('moveReason', 'The client cancelled the launch')
        ->call('confirmMove')
        ->assertHasNoErrors()
        ->assertSee('Killed. Its scheduled release was withdrawn.');

    $attempt = $piece->fresh()->currentAttempt;
    expect($attempt?->stage)->toBe(EditorialStage::Abandoned)
        ->and($attempt?->abandoned_reason)->toBe('The client cancelled the launch');
    expect($release->fresh()?->status)->toBe('withdrawn');
});

test('a parked piece can be killed', function (): void {
    $user = publishingBoardAuthor();
    $piece = publishingBoardScenario($user, 'parked');

    Livewire::actingAs($user)
        ->test('admin.publishing.board')
        ->call('moveCard', "{$piece->id}:parked", 0, 'killed')
        ->set('moveReason', 'Not coming back')
        ->call('confirmMove')
        ->assertHasNoErrors();

    expect($piece->fresh()->currentAttempt?->stage)->toBe(EditorialStage::Abandoned);
});

test('dropping a parked piece on its stage column unparks it and requeues work a worker paused', function (): void {
    $user = publishingBoardAuthor();
    $piece = publishingBoardPiece($user, 'Unpark me', EditorialStage::InReview, ['parked_at' => now(), 'parked_reason' => 'Waiting']);
    $blocked = publishingBoardActivity($piece, EditorialActivityKind::Draft, EditorialActivityStatus::Paused, ['pause_reason' => EditorialActivity::BLOCKED_ATTEMPT_PAUSE_REASON, 'paused_at' => now()]);
    $owner = publishingBoardActivity($piece, EditorialActivityKind::ReviewFacts, EditorialActivityStatus::Paused, ['pause_reason' => 'The initiating actor can no longer run this publishing agent work.', 'paused_at' => now()]);

    Livewire::actingAs($user)
        ->test('admin.publishing.board')
        ->call('moveCard', "{$piece->id}:parked", 0, 'writing')
        ->assertSet('moveRefused', false)
        ->assertSee('Unparked.');

    expect($piece->fresh()->currentAttempt?->parked_at)->toBeNull();
    expect($blocked->fresh()?->status)->toBe(EditorialActivityStatus::Pending);
    expect($owner->fresh()?->status)->toBe(EditorialActivityStatus::Paused);
});

test('unparking requeues only the work this park blocked', function (): void {
    $user = publishingBoardAuthor();
    $piece = publishingBoardPiece($user, 'Unpark selectively', EditorialStage::InReview, ['parked_at' => now(), 'parked_reason' => 'Waiting']);
    $blockedReason = ['pause_reason' => EditorialActivity::BLOCKED_ATTEMPT_PAUSE_REASON];
    $older = publishingBoardActivity($piece, EditorialActivityKind::Draft, EditorialActivityStatus::Paused, [...$blockedReason, 'paused_at' => now()->subDay()]);
    $superseded = publishingBoardActivity($piece, EditorialActivityKind::ReviewFacts, EditorialActivityStatus::Paused, [...$blockedReason, 'paused_at' => now()->addSecond(), 'batch_key' => 'cycle-1']);
    $newest = publishingBoardActivity($piece, EditorialActivityKind::ReviewFacts, EditorialActivityStatus::Paused, [...$blockedReason, 'paused_at' => now()->addSeconds(2), 'batch_key' => 'cycle-1']);
    $otherBatch = publishingBoardActivity($piece, EditorialActivityKind::ReviewFacts, EditorialActivityStatus::Paused, [...$blockedReason, 'paused_at' => now()->addSeconds(2), 'batch_key' => 'cycle-2']);

    Livewire::actingAs($user)
        ->test('admin.publishing.board')
        ->call('moveCard', "{$piece->id}:parked", 0, 'writing')
        ->assertSet('moveRefused', false);

    expect($older->fresh()?->status)->toBe(EditorialActivityStatus::Paused)
        ->and($superseded->fresh()?->status)->toBe(EditorialActivityStatus::Stale)
        ->and($newest->fresh()?->status)->toBe(EditorialActivityStatus::Pending)
        ->and($otherBatch->fresh()?->status)->toBe(EditorialActivityStatus::Pending);
});

test('parking a scheduled piece then unparking it leaves it approved with nothing scheduled', function (): void {
    $user = publishingBoardAuthor();
    $piece = publishingBoardPiece($user, 'Park scheduled', EditorialStage::Scheduled);
    $revision = publishingBoardRevision($piece);
    $release = ArticleRelease::factory()->create(['article_id' => $piece->id, 'attempt_id' => $piece->current_attempt_id, 'revision_id' => $revision->id, 'status' => 'scheduled', 'scheduled_at' => now()->addDay()]);

    Livewire::actingAs($user)
        ->test('admin.publishing.board')
        ->call('moveCard', "{$piece->id}:scheduled", 0, 'parked')
        ->set('moveReason', 'Holding the launch')
        ->call('confirmMove')
        ->assertHasNoErrors()
        ->assertSee('Parked. Its scheduled release was withdrawn.');

    expect($release->fresh()?->status)->toBe('withdrawn')
        ->and($piece->fresh()->currentAttempt?->stage)->toBe(EditorialStage::Approved);

    $component = Livewire::actingAs($user)
        ->test('admin.publishing.board')
        ->call('moveCard', "{$piece->id}:parked", 0, 'ready')
        ->assertSet('moveRefused', false);

    expect(publishingBoardColumnsOf($component->html(), $piece))->toBe([BoardColumn::Ready->value])
        ->and(ArticleRelease::query()->where('attempt_id', $piece->current_attempt_id)->where('status', 'scheduled')->exists())->toBeFalse();
});

test('a parked piece only unparks onto its own stage column', function (): void {
    $user = publishingBoardAuthor();
    $piece = publishingBoardScenario($user, 'parked');

    Livewire::actingAs($user)
        ->test('admin.publishing.board')
        ->call('moveCard', "{$piece->id}:parked", 0, 'review')
        ->assertSet('moveRefused', true)
        ->assertSee('Drop this piece on Planning to unpark it.');

    expect($piece->fresh()->currentAttempt?->parked_at)->not->toBeNull();
});

test('a move made on a stale board is refused', function (): void {
    $user = publishingBoardAuthor();
    $piece = publishingBoardPiece($user, 'Advanced by an agent', EditorialStage::Developing);
    $piece->currentAttempt?->forceFill(['stage' => EditorialStage::Drafting])->save();

    $result = app(MovePublishingPiece::class)->move($user, $piece, BoardColumn::Shaping, BoardColumn::Parked, 'Waiting on a client');

    expect($result->wasRefused())->toBeTrue()
        ->and($result->message)->toBe(MovePublishingPiece::MOVED_ELSEWHERE);
    expect($piece->fresh()->currentAttempt?->parked_at)->toBeNull();
});

test('another author’s card id is refused without revealing the piece', function (): void {
    $user = publishingBoardAuthor();
    $other = publishingBoardAuthor();
    $theirs = publishingBoardPiece($other, 'Their secret idea');

    Livewire::actingAs($user)
        ->test('admin.publishing.board')
        ->call('moveCard', "{$theirs->id}:ideas", 0, 'shaping')
        ->assertSet('moveRefused', true)
        ->assertSee('That piece isn’t on your board.')
        ->assertDontSee('Their secret idea');

    expect($theirs->fresh()->current_attempt_id)->toBeNull();
});

test('the card menu offers the same single moves as a drag', function (): void {
    $user = publishingBoardAuthor();
    $parked = publishingBoardScenario($user, 'parked');
    $ready = publishingBoardScenario($user, 'approved');

    $html = publishingBoardHtml($user);

    $moves = fn (Article $piece): array => array_map(
        fn (DOMNameSpaceNode|DOMNode $node): string => $node instanceof DOMElement ? $node->getAttribute('data-board-move') : '',
        iterator_to_array(publishingBoardNodes($html, sprintf('//*[@data-board-card="%d"]//*[@data-board-move]', $piece->id))),
    );
    expect($moves($parked))->toBe(['planning', 'killed'])
        ->and($moves($ready))->toBe(['scheduled', 'parked', 'killed']);
});

test('a user who loses publishing access is refused on the next refresh', function (): void {
    $user = publishingBoardAuthor();
    $component = Livewire::actingAs($user)->test('admin.publishing.board');

    $user->removeRole(PublishingRole::Author->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $component->call('$refresh')->assertForbidden();
});

test('the board polls its cards every ten seconds', function (): void {
    Livewire::actingAs(publishingBoardAuthor())
        ->test('admin.publishing.board')
        ->assertSeeHtml('wire:poll.10s');
});

test('the board loads thirty pieces in no more queries than three', function (): void {
    $user = publishingBoardAuthor();
    publishingBoardShape($user, 1);
    $component = Livewire::actingAs($user)->test('admin.publishing.board');

    DB::enableQueryLog();
    DB::flushQueryLog();
    $component->call('$refresh');
    $three = count(DB::getQueryLog());
    foreach (range(2, 10) as $index) {
        publishingBoardShape($user, $index);
    }
    DB::flushQueryLog();
    $component->call('$refresh');
    $thirty = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect(publishingBoardNodes($component->html(), '//*[@data-board-card]')->length)->toBe(30)
        ->and($thirty)->toBe($three)
        ->and($thirty)->toBeLessThanOrEqual(30);
});
