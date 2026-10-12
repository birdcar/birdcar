<?php

use App\Actions\Publishing\AdvancePublishingAttempt;
use App\Actions\Publishing\ApprovePublishingStage;
use App\Actions\Publishing\CreateSiblingPiece;
use App\Actions\Publishing\RunEditorialActivity;
use App\Actions\Publishing\WriteArticle;
use App\Authorization\Admin\Role as AdminRole;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Models\Article;
use App\Models\EditorialActivity;
use App\Models\Publishing\ApprovalKind;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\Publishing\PieceKind;
use App\Models\User;
use App\Services\Publishing\EditorialOutput;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

const SIBLING_PIECE_IDEA = 'Why another admin hire will not fix the Friday report';

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
});

function siblingPieceAuthor(): User
{
    $user = User::factory()->create();
    $user->assignRole([AdminRole::Access->value, PublishingRole::Author->value]);

    return $user;
}

/** A flagship with a saved title and a chosen angle, the way an idea looks once its first post is under way. */
function siblingPieceFlagship(User $actor): Article
{
    $write = app(WriteArticle::class);
    $flagship = $write->captureIdea($actor, SIBLING_PIECE_IDEA);
    $revision = $write->save($actor, $flagship, null, ['version' => 1, 'type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'attrs' => ['id' => 'blk_000000000000e0a1'], 'content' => [['type' => 'text', 'text' => 'A new hire often inherits the same chores.']]],
    ]], ['title' => 'Another admin hire will not fix it'], 'sibling-flagship');
    $attempt = app(AdvancePublishingAttempt::class)->develop($actor, $flagship, $revision->id, ['idea' => SIBLING_PIECE_IDEA]);
    $attempt->forceFill(['angle' => ['title' => 'Check the chores first', 'thesis' => 'A hire adds a person to the same chores.']])->save();

    return $flagship->fresh() ?? $flagship;
}

function siblingPieceInterview(Article $piece): EditorialActivity
{
    return EditorialActivity::query()->where('article_id', $piece->id)->where('kind', EditorialActivityKind::Interview->value)->sole();
}

test('a sibling is a parentless blog post on the same idea with its interview queued', function () {
    $actor = siblingPieceAuthor();
    $flagship = siblingPieceFlagship($actor);

    $sibling = app(CreateSiblingPiece::class)->create($actor, $flagship->contentIdea, '  Write it as a playbook this time.  ');

    $interview = siblingPieceInterview($sibling);
    expect($sibling->kind)->toBe(PieceKind::BlogPost)
        ->and($sibling->parent_article_id)->toBeNull()
        ->and($sibling->content_idea_id)->toBe($flagship->content_idea_id)
        ->and($sibling->id)->not->toBe($flagship->id)
        ->and($interview->status)->toBe(EditorialActivityStatus::Pending)
        ->and($interview->input['idea'] ?? null)->toBe(SIBLING_PIECE_IDEA)
        ->and($interview->input[CreateSiblingPiece::NOTE_KEY] ?? null)->toBe('Write it as a playbook this time.')
        ->and($interview->input['brief'] ?? null)->toBe(['idea' => SIBLING_PIECE_IDEA]);
});

test('the sibling interview carries every other post title and angle thesis, never the idea summary title', function () {
    $actor = siblingPieceAuthor();
    $flagship = siblingPieceFlagship($actor);
    $untitled = app(WriteArticle::class)->addPiece($actor, $flagship->contentIdea, PieceKind::BlogPost);
    app(WriteArticle::class)->addPiece($actor, $flagship->contentIdea, PieceKind::LinkedinPost, $flagship);

    $sibling = app(CreateSiblingPiece::class)->create($actor, $flagship->contentIdea);

    $context = siblingPieceInterview($sibling)->input['sibling_context'] ?? null;
    expect($context)->toEqual([
        ['article_id' => $flagship->id, 'title' => 'Another admin hire will not fix it', 'angle_thesis' => 'A hire adds a person to the same chores.'],
        ['article_id' => $untitled->id, 'title' => null, 'angle_thesis' => null],
    ])
        ->and(json_encode($context))->not->toContain($flagship->contentIdea->summary_title)
        ->and(siblingPieceInterview($sibling)->input)->not->toHaveKey(CreateSiblingPiece::NOTE_KEY);
});

test('a post without a saved title is listed by its angle title', function () {
    $actor = siblingPieceAuthor();
    $flagship = siblingPieceFlagship($actor);
    $first = app(CreateSiblingPiece::class)->create($actor, $flagship->contentIdea);
    $first->currentAttempt()->firstOrFail()->forceFill(['angle' => ['title' => 'The Friday report is a memory problem', 'thesis' => 'The report only happens if someone remembers it.']])->save();

    $second = app(CreateSiblingPiece::class)->create($actor, $flagship->contentIdea);

    expect(siblingPieceInterview($second)->input['sibling_context'][1] ?? null)->toEqual([
        'article_id' => $first->id,
        'title' => 'The Friday report is a memory problem',
        'angle_thesis' => 'The report only happens if someone remembers it.',
    ]);
});

test('the sibling runs the full post path, so its plan cannot be approved without its own angle approval', function () {
    $actor = siblingPieceAuthor();
    $flagship = siblingPieceFlagship($actor);
    $approve = app(ApprovePublishingStage::class);
    $flagshipAttempt = $flagship->currentAttempt()->firstOrFail();
    $approve->approve($actor, $flagshipAttempt, ApprovalKind::Angle, $approve->inputHashFor($flagshipAttempt, ApprovalKind::Angle));

    $attempt = app(CreateSiblingPiece::class)->create($actor, $flagship->contentIdea)->currentAttempt()->firstOrFail();

    expect(fn () => $approve->approve($actor, $attempt, ApprovalKind::Plan, $approve->inputHashFor($attempt, ApprovalKind::Plan)))
        ->toThrow(RuntimeException::class, 'Plan approval requires an active angle approval.');
});

test('the interviewer is told to propose an angle distinct from every listed post and to say how it differs', function () {
    $actor = siblingPieceAuthor();
    $flagship = siblingPieceFlagship($actor);
    $interview = siblingPieceInterview(app(CreateSiblingPiece::class)->create($actor, $flagship->contentIdea));
    setPublishingAgentsPaused(false);
    config()->set('ai.providers.openrouter.key', 'test-key');
    Queue::fake([RunEditorialActivity::class]);
    Http::preventStrayRequests();
    Http::fake(['https://openrouter.ai/api/v1/chat/completions' => Http::response(['id' => 'gen-sibling', 'choices' => [['finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => json_encode([
        'questions' => ['What would an owner do first?'],
        'brief' => ['summary' => 'A playbook for the admin hire question.', 'missingAnswers' => []],
        'angleOptions' => [['title' => 'Five chores to check before you hire', 'thesis' => 'Most of the new hire\'s first month is chores you could stop.', 'rationale' => 'A playbook where the flagship is an essay: steps instead of the argument.']],
    ], JSON_THROW_ON_ERROR)]]]])]);

    app(RunEditorialActivity::class, ['activityId' => $interview->id])->handle(app(EditorialOutput::class), app(WriteArticle::class));

    $body = Http::recorded()->sole()[0]->data();
    $prompt = json_decode((string) data_get($body, 'messages.1.content'), true, flags: JSON_THROW_ON_ERROR);
    expect((string) data_get($body, 'messages.0.content'))->toContain("Each angle's rationale states how it differs from every listed post.")
        ->and(data_get($prompt, 'input.sibling_context.0.angle_thesis'))->toBe('A hire adds a person to the same chores.')
        ->and($interview->fresh()?->status)->toBe(EditorialActivityStatus::Completed);
});

test('the workspace starts a new post from the idea and opens it', function () {
    $actor = siblingPieceAuthor();
    $flagship = siblingPieceFlagship($actor);

    Livewire::actingAs($actor)
        ->test('admin.publishing.article-workspace', ['article' => $flagship])
        ->assertSee('New post from this idea')
        ->set('siblingNote', 'A teardown of one Friday report.')
        ->call('createSibling')
        ->assertHasNoErrors()
        ->assertRedirect();

    $sibling = Article::query()->where('content_idea_id', $flagship->content_idea_id)->whereKeyNot($flagship->id)->sole();
    expect(siblingPieceInterview($sibling)->input[CreateSiblingPiece::NOTE_KEY] ?? null)->toBe('A teardown of one Friday report.');
});

test('starting a sibling needs the develop permission', function () {
    $actor = siblingPieceAuthor();
    $flagship = siblingPieceFlagship($actor);
    $viewer = User::factory()->create();
    $viewer->assignRole(AdminRole::Access->value);

    expect(fn () => app(CreateSiblingPiece::class)->create($viewer, $flagship->contentIdea))->toThrow(AuthorizationException::class)
        ->and(Article::query()->where('content_idea_id', $flagship->content_idea_id)->count())->toBe(1);
});
