<?php

use App\Actions\Publishing\WriteArticle;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Models\ArticleRevision;
use App\Models\User;
use App\Services\Publishing\ArticleDocument;
use App\Services\Publishing\ArticleDocumentValidationException;
use App\Services\Publishing\PublishingFingerprint;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
});

test('supported tiptap nodes render escaped static html without reparsing text as markdown', function (): void {
    $document = drtDocument();
    $service = app(ArticleDocument::class);

    $canonical = $service->canonicalize($document);
    $html = $service->renderHtml($canonical);

    expect($canonical)->toBe($document)
        ->and($html)->toContain('Literal *stars* &lt;angle&gt;')
        ->and($html)->toContain('<strong>bold</strong>')
        ->and($html)->toContain('href="/writing/example/"')
        ->and($html)->toContain('<hr />')
        ->and($html)->toContain('View chart data')
        ->and($html)->toContain('Key takeaway')
        ->and($html)->not->toContain('<script', 'javascript:');
});

test('documents reject duplicate ids unsafe links string chart numbers and active svg', function (): void {
    $service = app(ArticleDocument::class);
    $duplicate = drtDocument();
    $duplicate['content'][1]['attrs']['id'] = 'blk_0123456789abcdef';

    expect(fn () => $service->validate($duplicate))->toThrow(ArticleDocumentValidationException::class, 'unique');

    $unsafeLink = drtDocument();
    $unsafeLink['content'][0]['content'][1]['marks'][1]['attrs']['href'] = 'javascript:alert(1)';
    expect(fn () => $service->validate($unsafeLink))->toThrow(ArticleDocumentValidationException::class, 'allowed URL');

    $stringChart = drtDocument();
    $stringChart['content'][5]['attrs']['data'][0]['value'] = '7';
    expect(fn () => $service->validate($stringChart))->toThrow(ArticleDocumentValidationException::class, 'finite nonnegative number');

    $activeSvg = drtDocument();
    $activeSvg['content'][6]['attrs'] = [
        'id' => 'blk_7777777777777777',
        'sourceType' => 'svg',
        'caption' => 'Bad.',
        'source' => '<!DOCTYPE svg [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg"><text>&xxe;</text></svg>',
    ];
    expect(fn () => $service->validate($activeSvg))->toThrow(ArticleDocumentValidationException::class, 'active or external');

    $titleOnlySvg = drtDocument();
    $titleOnlySvg['content'][6]['attrs'] = [
        'id' => 'blk_aaaaaaaaaaaaaaaa',
        'sourceType' => 'svg',
        'caption' => 'Generated description.',
        'source' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><title>Provided title</title><circle cx="5" cy="5" r="4" fill="none" /></svg>',
    ];
    $safeSvg = $service->canonicalize($titleOnlySvg)['content'][6]['attrs']['safeSvg'];
    expect($safeSvg)
        ->toContain('<title>Provided title</title>')
        ->toContain('<desc>Generated description.</desc>');

    $blankSvg = drtDocument();
    $blankSvg['content'][6]['attrs'] = [
        'id' => 'blk_bbbbbbbbbbbbbbbb',
        'sourceType' => 'svg',
        'caption' => 'Caption fallback.',
        'source' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><title>   </title><desc>&#x20;</desc><circle cx="5" cy="5" r="4" fill="none" /></svg>',
    ];
    $blankSafeSvg = $service->canonicalize($blankSvg)['content'][6]['attrs']['safeSvg'];
    expect($blankSafeSvg)
        ->toContain('<title>Caption fallback.</title>')
        ->toContain('<desc>Caption fallback.</desc>');
});

test('saves require stable ids and persist canonical documents with matching hashes', function (): void {
    $actor = drtAuthor();
    $write = app(WriteArticle::class);
    $article = $write->capture($actor, 'Canonical save.', 'canonical-save');
    $document = drtDocument();
    $document['content'][1]['attrs']['protected'] = null;

    $revision = $write->save($actor, $article, null, $document, ['title' => 'Canonical'], 'canonical');
    $stored = $revision->fresh()?->document;

    expect($stored['content'][1]['attrs'])->not->toHaveKey('protected')
        ->and($revision->content_hash)->toBe(app(PublishingFingerprint::class)->hash(['document' => $stored, 'metadata' => ['title' => 'Canonical']]));

    $missingId = drtDocument();
    unset($missingId['content'][0]['attrs']['id'], $missingId['content'][2]['content'][0]['attrs']['id']);

    $withGeneratedIds = $write->save($actor, $article, $revision->id, $missingId, ['title' => 'Missing id'], 'missing-id');
    expect($withGeneratedIds->document['content'][0]['attrs']['id'])->toMatch('/^blk_[0-9a-f]{16}$/')
        ->and($withGeneratedIds->document['content'][2]['content'][0]['attrs']['id'])->toMatch('/^blk_[0-9a-f]{16}$/');

    $legacyTextWithoutId = drtDocument();
    $legacyTextWithoutId['content'][0] = ['type' => 'paragraph', 'attrs' => [], 'text' => 'Legacy text still needs an id.'];

    $legacyWithGeneratedId = $write->save($actor, $article, $withGeneratedIds->id, $legacyTextWithoutId, ['title' => 'Legacy missing id'], 'legacy-missing-id');
    expect($legacyWithGeneratedId->document['content'][0]['attrs']['id'])->toMatch('/^blk_[0-9a-f]{16}$/')
        ->and($legacyWithGeneratedId->document['content'][0]['content'][0]['text'])->toBe('Legacy text still needs an id.');
});

test('failed saves and protected agent edits do not mutate the working revision', function (): void {
    $actor = drtAuthor();
    $write = app(WriteArticle::class);
    $article = $write->capture($actor, 'Protected blocks.', 'protected-blocks');
    $first = $write->save($actor, $article, null, drtDocument(), ['title' => 'Protected'], 'first');

    $invalid = drtDocument();
    $invalid['content'][0]['content'][1]['marks'][1]['attrs']['href'] = 'ftp://example.com/file';
    expect(fn () => $write->save($actor, $article, $first->id, $invalid, ['title' => 'Invalid'], 'invalid'))
        ->toThrow(ArticleDocumentValidationException::class);

    $changedProtected = drtDocument();
    $changedProtected['content'][0]['content'][0]['text'] = 'Changed';
    expect(fn () => $write->save($actor, $article, $first->id, $changedProtected, ['title' => 'Agent'], 'agent-change', 'agent'))
        ->toThrow(RuntimeException::class, 'protected');

    $humanChanged = $write->save($actor, $article, $first->id, $changedProtected, ['title' => 'Human'], 'human-change', 'human');

    expect(ArticleRevision::query()->where('article_id', $article->id)->count())->toBe(2)
        ->and($article->fresh()?->working_revision_id)->toBe($humanChanged->id);
});

function drtAuthor(): User
{
    $user = User::factory()->create();
    $user->assignRole(PublishingRole::Author->value);

    return $user;
}

/** @return array<string, mixed> */
function drtDocument(): array
{
    return [
        'version' => 1,
        'type' => 'doc',
        'content' => [
            [
                'type' => 'paragraph',
                'attrs' => ['id' => 'blk_0123456789abcdef', 'protected' => true],
                'content' => [
                    ['type' => 'text', 'text' => 'Literal *stars* <angle> and '],
                    ['type' => 'text', 'text' => 'bold', 'marks' => [['type' => 'bold'], ['type' => 'link', 'attrs' => ['href' => '/writing/example/']]]],
                ],
            ],
            ['type' => 'heading', 'attrs' => ['id' => 'blk_1111111111111111', 'level' => 2], 'content' => [['type' => 'text', 'text' => 'Heading']]],
            ['type' => 'bulletList', 'attrs' => ['id' => 'blk_2222222222222222'], 'content' => [['type' => 'listItem', 'attrs' => ['id' => 'blk_3333333333333333'], 'content' => [['type' => 'paragraph', 'attrs' => ['id' => 'blk_4444444444444444'], 'content' => [['type' => 'text', 'text' => 'Item']]]]]]],
            ['type' => 'horizontalRule', 'attrs' => ['id' => 'blk_5555555555555555']],
            ['type' => 'callout', 'attrs' => ['id' => 'blk_6666666666666666', 'title' => 'Key takeaway', 'style' => 'key'], 'content' => [['type' => 'paragraph', 'attrs' => ['id' => 'blk_7777777777777777'], 'content' => [['type' => 'text', 'text' => 'Keep the meaning.']]]]],
            ['type' => 'chart', 'attrs' => ['id' => 'blk_8888888888888888', 'chartType' => 'line', 'x' => 'month', 'series' => [['key' => 'value', 'label' => 'Value']], 'data' => [['month' => 'Only', 'value' => 0]], 'caption' => 'Zero chart.', 'width' => 'wide']],
            ['type' => 'diagram', 'attrs' => ['id' => 'blk_9999999999999999', 'sourceType' => 'preset', 'name' => 'walkthrough', 'caption' => 'Walkthrough.']],
        ],
    ];
}
