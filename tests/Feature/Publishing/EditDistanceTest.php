<?php

use App\Actions\Publishing\WriteArticle;
use App\Authorization\Admin\Role as AdminRole;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Jobs\RecordEditDistance;
use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\ContentIdea;
use App\Models\Publishing\PieceKind;
use App\Models\User;
use App\Services\Publishing\EditDistance;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
});

function editDistanceAuthor(): User
{
    $user = User::factory()->create();
    $user->assignRole([AdminRole::Access->value, PublishingRole::Author->value]);

    return $user;
}

/**
 * One paragraph per entry; a list entry is a paragraph whose text nodes are joined as written, so a word can be split
 * across marks.
 *
 * @param  list<string|list<array{0: string, 1?: string}>>  $paragraphs
 * @return array<string, mixed>
 */
function editDistanceDocument(array $paragraphs): array
{
    return ['version' => 1, 'type' => 'doc', 'content' => array_map(function (string|array $paragraph): array {
        $nodes = is_string($paragraph) ? [[$paragraph]] : $paragraph;

        return ['type' => 'paragraph', 'content' => array_map(
            fn (array $node): array => isset($node[1]) ? ['type' => 'text', 'text' => $node[0], 'marks' => [['type' => $node[1]]]] : ['type' => 'text', 'text' => $node[0]],
            $nodes,
        )];
    }, $paragraphs)];
}

/** @return array<string, mixed> */
function editDistanceWords(string $prefix, int $count): array
{
    return editDistanceDocument([implode(' ', array_map(fn (int $n): string => $prefix.$n, range(1, $count)))]);
}

/** A blog post whose first revision an agent wrote; returns the piece and that revision. */
function editDistanceAgentDraft(User $actor, string $text = 'The invoice run takes a day.', string $origin = 'agent-initial'): ArticleRevision
{
    $writer = app(WriteArticle::class);
    $piece = $writer->captureIdea($actor, 'Owners chase every invoice by hand');

    return $writer->save($actor, $piece, null, editDistanceDocument([$text]), [], 'edit-distance-agent', $origin);
}

function editDistanceSaveAfter(User $actor, ArticleRevision $parent, string $text, string $origin = 'human', ?string $mutationId = null): ArticleRevision
{
    return app(WriteArticle::class)->save($actor, (int) $parent->article_id, (int) $parent->id, editDistanceDocument([$text]), [], $mutationId ?? 'edit-distance-'.$parent->id, $origin);
}

test('the edit distance counts inserted, deleted and replaced words', function (array $baseline, array $revised, int $expected) {
    $distance = app(EditDistance::class);

    expect($distance->between($baseline, $revised))->toBe($expected)
        ->and($distance->between($revised, $baseline))->toBe($expected);
})->with([
    'identical documents' => [editDistanceDocument(['The invoice run takes a day.']), editDistanceDocument(['The invoice run takes a day.']), 0],
    'the last two words replaced' => [editDistanceDocument(['The invoice run takes a day.']), editDistanceDocument(['The invoice run takes an hour.']), 2],
    'one word changed in the middle' => [editDistanceDocument(['The invoice run takes a day.']), editDistanceDocument(['The payroll run takes a day.']), 1],
    'a paragraph inserted' => [editDistanceDocument(['First line.', 'Last line.']), editDistanceDocument(['First line.', 'Five more words go here.', 'Last line.']), 5],
    'an empty document against words' => [editDistanceDocument([]), editDistanceDocument(['Seven words sit in this short paragraph.']), 7],
    'case, punctuation and curly apostrophes only' => [editDistanceDocument(["It's done, really."]), editDistanceDocument(['IT’S DONE really!']), 0],
    'a word split across marks' => [editDistanceDocument([[['Book'], ['keeping', 'bold'], [' matters.']]]), editDistanceDocument(['Bookkeeping matters.']), 0],
    'words in neighbouring blocks never fuse' => [editDistanceDocument(['One', 'two']), editDistanceDocument(['One two']), 0],
    'a malformed document reads as no words' => [['type' => 'doc', 'content' => 'not a list'], editDistanceDocument(['Three words here.']), 3],
]);

test('the edit distance reads hard breaks, note titles and figure captions as text', function () {
    $document = fn (string $title, string $caption): array => ['version' => 1, 'type' => 'doc', 'content' => [
        ['type' => 'note', 'attrs' => ['title' => $title], 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Before'], ['type' => 'hardBreak'], ['type' => 'text', 'text' => 'after']]]]],
        ['type' => 'chart', 'attrs' => ['caption' => $caption]],
    ]];

    $distance = app(EditDistance::class)->between($document('Read this first', 'Loops by kind'), $document('Read this last', 'Bug loops by kind'));

    expect($distance)->toBe(2)
        ->and(app(EditDistance::class)->between($document('A', 'B'), editDistanceDocument(['A Before after B'])))->toBe(0);
});

test('a full rewrite of a 4,000-word document is measured in two rows of memory', function () {
    $baseline = editDistanceWords('old', 4000);
    $revised = editDistanceWords('new', 4000);
    memory_reset_peak_usage();
    $before = memory_get_peak_usage();

    $distance = app(EditDistance::class)->between($baseline, $revised);

    expect($distance)->toBe(4000)
        ->and(memory_get_peak_usage() - $before)->toBeLessThan(16 * 1024 * 1024);
});

test('an owner save straight after an agent-authored revision queues one measurement for that save', function (string $parentOrigin) {
    Queue::fake([RecordEditDistance::class]);
    $actor = editDistanceAuthor();
    $agentRevision = editDistanceAgentDraft($actor, origin: $parentOrigin);

    $ownerRevision = editDistanceSaveAfter($actor, $agentRevision, 'The invoice run takes an hour.');
    editDistanceSaveAfter($actor, $agentRevision, 'The invoice run takes an hour.');

    Queue::assertPushedTimes(RecordEditDistance::class, 1);
    Queue::assertPushed(RecordEditDistance::class, fn (RecordEditDistance $job): bool => $job->revisionId === $ownerRevision->id);
})->with(['agent-initial', 'agent-repair', 'agent-accepted']);

test('no measurement is queued for a first save, an owner save after an owner save, or an agent save', function () {
    Queue::fake([RecordEditDistance::class]);
    $actor = editDistanceAuthor();
    $writer = app(WriteArticle::class);
    $piece = $writer->captureIdea($actor, 'Owners chase every invoice by hand');

    $first = $writer->save($actor, $piece, null, editDistanceDocument(['The owner wrote this.']), [], 'edit-distance-first');
    $second = editDistanceSaveAfter($actor, $first, 'The owner wrote this again.');
    editDistanceSaveAfter($actor, $second, 'An agent rewrote it.', 'agent-accepted');

    Queue::assertNotPushed(RecordEditDistance::class);
});

test('the queued measurement records the distance and its agent baseline on the owner revision at commit', function () {
    $actor = editDistanceAuthor();
    $agentRevision = editDistanceAgentDraft($actor);

    $ownerRevision = editDistanceSaveAfter($actor, $agentRevision, 'The payroll run takes an hour now.');

    $measured = ArticleRevision::query()->findOrFail($ownerRevision->id);
    $baseline = ArticleRevision::query()->findOrFail($agentRevision->id);
    expect($measured->agent_edit_distance)->toBe(4)
        ->and($measured->agent_baseline_revision_id)->toBe($agentRevision->id)
        ->and($baseline->agent_edit_distance)->toBeNull()
        ->and($baseline->agent_baseline_revision_id)->toBeNull();
});

test('the measurement writes only the revision it was queued for and never overwrites a recorded distance', function () {
    Queue::fake([RecordEditDistance::class]);
    $actor = editDistanceAuthor();
    $agentRevision = editDistanceAgentDraft($actor);
    $ownerRevision = editDistanceSaveAfter($actor, $agentRevision, 'The payroll run takes a day.');
    $laterRevision = editDistanceSaveAfter($actor, $ownerRevision, 'Something else entirely, rewritten later.');
    $run = fn (int $revisionId) => (new RecordEditDistance($revisionId))->handle(app(EditDistance::class));

    $run($ownerRevision->id);
    $run($laterRevision->id);
    $run(PHP_INT_MAX);
    DB::table('article_revisions')->where('id', $ownerRevision->id)->update(['agent_edit_distance' => 99]);
    $run($ownerRevision->id);

    expect(ArticleRevision::query()->findOrFail($ownerRevision->id)->agent_edit_distance)->toBe(99)
        ->and(ArticleRevision::query()->findOrFail($laterRevision->id)->agent_edit_distance)->toBeNull();
});

test('a recorded distance is set once from null and is then as immutable as the revision', function () {
    $actor = editDistanceAuthor();
    $agentRevision = editDistanceAgentDraft($actor);
    $ownerRevision = app(WriteArticle::class)->save($actor, (int) $agentRevision->article_id, (int) $agentRevision->id, editDistanceDocument(['Owner text.']), [], 'edit-distance-owner', 'agent-accepted');
    $partial = ArticleRevision::query()->select(['id', 'origin'])->findOrFail($ownerRevision->id);

    expect(fn () => $partial->forceFill(['agent_edit_distance' => 3])->save())->toThrow(RuntimeException::class, 'recorded once');

    $loaded = ArticleRevision::query()->findOrFail($ownerRevision->id);
    $loaded->forceFill(['agent_edit_distance' => 3, 'agent_baseline_revision_id' => $agentRevision->id])->save();

    $recorded = ArticleRevision::query()->findOrFail($ownerRevision->id);
    expect($recorded->agent_edit_distance)->toBe(3)
        ->and(fn () => $recorded->forceFill(['agent_edit_distance' => 1])->save())->toThrow(RuntimeException::class, 'recorded once')
        ->and(fn () => $recorded->forceFill(['agent_baseline_revision_id' => null])->save())->toThrow(RuntimeException::class, 'recorded once')
        ->and(fn () => $recorded->forceFill(['document' => editDistanceDocument(['Changed.'])])->save())->toThrow(RuntimeException::class, 'append-only');
});

test('the command prints every piece of an idea with its kind and recorded distances as JSON', function () {
    $actor = editDistanceAuthor();
    $writer = app(WriteArticle::class);
    $flagshipDraft = editDistanceAgentDraft($actor);
    $flagship = Article::query()->findOrFail($flagshipDraft->article_id);
    $idea = ContentIdea::query()->findOrFail($flagship->content_idea_id);
    editDistanceSaveAfter($actor, $flagshipDraft, 'The invoice run takes an hour.');
    $expected = [['article_id' => $flagship->id, 'kind' => 'blog_post', 'edit_distance' => 2]];
    foreach ([PieceKind::LinkedinPost, PieceKind::BlueskyThread, PieceKind::EmailBlurb] as $offset => $kind) {
        $piece = $writer->addPiece($actor, $idea, $kind, $flagship);
        $kitDraft = $writer->save($actor, $piece, null, editDistanceDocument(['Kit text for the post.']), [], 'edit-distance-kit', 'agent-initial');
        editDistanceSaveAfter($actor, $kitDraft, 'Kit text for the post.'.str_repeat(' added', $offset + 1));
        $expected[] = ['article_id' => $piece->id, 'kind' => $kind->value, 'edit_distance' => $offset + 1];
    }

    $exitCode = Artisan::call('publishing:edit-distances', ['idea' => (string) $idea->id]);

    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    $pieces = is_array($report['pieces'] ?? null) ? $report['pieces'] : [];
    expect($exitCode)->toBe(0)
        ->and($report['idea_id'] ?? null)->toBe($idea->id)
        ->and($report['summary_title'] ?? null)->toBe($idea->summary_title)
        ->and(array_map(fn (array $piece): array => array_intersect_key($piece, array_flip(['article_id', 'kind', 'edit_distance'])), $pieces))->toBe($expected)
        ->and(array_column($pieces[0]['distances'] ?? [], 'baseline_revision_id'))->toBe([$flagshipDraft->id]);
});

test('the command reports the latest of several distances, and null for a piece with none recorded', function () {
    $actor = editDistanceAuthor();
    $writer = app(WriteArticle::class);
    $agentRevision = editDistanceAgentDraft($actor);
    $firstEdit = editDistanceSaveAfter($actor, $agentRevision, 'The payroll run takes a day.');
    $accepted = editDistanceSaveAfter($actor, $firstEdit, 'An accepted proposal rewrote the line.', 'agent-accepted');
    editDistanceSaveAfter($actor, $accepted, 'An accepted proposal rewrote this line twice over.');
    $flagship = Article::query()->findOrFail($agentRevision->article_id);
    $idea = ContentIdea::query()->findOrFail($flagship->content_idea_id);
    $sibling = $writer->addPiece($actor, $idea, PieceKind::BlogPost);
    $writer->save($actor, $sibling, null, editDistanceDocument(['Owner-only text.']), [], 'edit-distance-sibling');

    Artisan::call('publishing:edit-distances', ['idea' => (string) $idea->id]);

    $pieces = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['pieces'] ?? [];
    expect($pieces[0]['edit_distance'] ?? null)->toBe(3)
        ->and(array_column($pieces[0]['distances'] ?? [], 'edit_distance'))->toBe([1, 3])
        ->and($pieces[1] ?? null)->toMatchArray(['article_id' => $sibling->id, 'edit_distance' => null, 'distances' => []]);
});

test('the command fails without changing anything when the idea does not exist or is not an id', function (string $idea) {
    $this->artisan('publishing:edit-distances', ['idea' => $idea])->assertFailed();
})->with(['missing idea' => '999999', 'not an id' => 'flagship']);
