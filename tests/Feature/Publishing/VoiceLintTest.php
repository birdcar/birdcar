<?php

use App\Ai\Agents\Drafter;
use App\Ai\Agents\KitDrafter;
use App\Models\Article;
use App\Models\EditorialActivity;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\PieceKind;
use App\Services\Publishing\KitDocument;
use App\Services\Publishing\Lint\LintHit;
use App\Services\Publishing\Lint\LintTarget;
use App\Services\Publishing\Lint\VoiceLinter;
use App\Services\Publishing\VoiceCanon;
use Illuminate\Support\Facades\Http;

/** @return array<string, mixed> */
function voiceLintParagraph(string $text, string $id = 'blk_00000000000000a1'): array
{
    return ['type' => 'paragraph', 'attrs' => ['id' => $id], 'content' => [['type' => 'text', 'text' => $text]]];
}

/** @return array<string, mixed> */
function voiceLintHeading(string $text, int $level = 2, string $id = 'blk_00000000000000b1'): array
{
    return ['type' => 'heading', 'attrs' => ['id' => $id, 'level' => $level], 'content' => [['type' => 'text', 'text' => $text]]];
}

/**
 * @param  list<array<string, mixed>>  $content
 * @return array<string, mixed>
 */
function voiceLintMarked(array $content, string $id = 'blk_00000000000000c1'): array
{
    return ['type' => 'paragraph', 'attrs' => ['id' => $id], 'content' => $content];
}

/** @return array<string, mixed> */
function voiceLintWalkthrough(string $id = 'blk_00000000000000f1', string $href = '/walkthrough'): array
{
    return voiceLintMarked([
        ['type' => 'text', 'text' => 'If one of these sounds like your week, bring it to '],
        ['type' => 'text', 'text' => 'a Walkthrough', 'marks' => [['type' => 'link', 'attrs' => ['href' => $href]]]],
        ['type' => 'text', 'text' => '.'],
    ], $id);
}

/**
 * @param  list<array<string, mixed>>  $content
 * @param  array<string, mixed>  $metadata
 */
function voiceLintDocument(array $content, array $metadata = []): LintTarget
{
    return LintTarget::fromDocument(['version' => 1, 'type' => 'doc', 'content' => $content], $metadata, PieceKind::BlogPost);
}

const VOICE_LINT_POST_URL = 'https://birdcar.dev/writing/another-admin-hire/';

/**
 * A distribution kit piece built from its fields, linted against the flagship URL and target phrase its attempt froze.
 *
 * @param  array<string, mixed>  $fields
 */
function voiceLintKit(PieceKind $kind, array $fields): LintTarget
{
    return LintTarget::fromDocument(KitDocument::document($kind, $fields), [], $kind, null, ['canonical_url' => VOICE_LINT_POST_URL, 'target_query' => 'do I need another admin hire']);
}

/** @return array{text: string, first_comment: string} */
function voiceLintLinkedin(string $text = 'Do I need another admin hire? It is the question I hear most from owners whose team is full.', string $comment = 'The full post: '.VOICE_LINT_POST_URL): array
{
    return ['text' => $text, 'first_comment' => $comment];
}

/** @return array{subject: string, preview: string, body: string} */
function voiceLintEmail(string $body = 'A new hire often inherits the same chores. Check them first. Read the post: '.VOICE_LINT_POST_URL, string $subject = 'Another admin hire will not fix it'): array
{
    return ['subject' => $subject, 'preview' => 'Check the chores before you post the job.', 'body' => $body];
}

const VOICE_LINT_OWNER_DESCRIPTION = 'When the team is full and the work keeps coming back to you, a new hire often adds a person to the same chores. Here is what to check before you post the job.';

/** A post whose slug repeats an archive essay's slug, with that essay in the database. */
function voiceLintArchiveSlugTarget(): LintTarget
{
    Article::factory()->create(['slug' => 'just-build-it-twice']);

    return voiceLintDocument([], ['slug' => 'just-build-it-twice']);
}

/** @return list<LintHit> */
function voiceLintHitsFor(string $ruleId, LintTarget $target): array
{
    return array_values(array_filter(app(VoiceLinter::class)->lint($target), fn (LintHit $hit): bool => $hit->ruleId === $ruleId));
}

/**
 * The register examples the canon gives for the public voice, as plain paragraphs.
 *
 * @return list<string>
 */
function voiceLintRegisterExamples(): array
{
    $core = (string) file_get_contents(resource_path('publishing/canon/voice/core.md'));
    $examples = substr($core, (int) strpos($core, '## Examples'));
    preg_match_all('/^> (.+)$/m', $examples, $lines);

    return $lines[1];
}

test('each voice rule fires on its seeded violation', function (string $ruleId, LintTarget $target, int $hits) {
    expect(voiceLintHitsFor($ruleId, $target))->toHaveCount($hits);
})->with([
    'em-dash' => ['em-dash', voiceLintDocument([voiceLintParagraph('The report takes an afternoon — every single week.')]), 1],
    'curly-quotes' => ['curly-quotes', voiceLintDocument([voiceLintParagraph('It’s a memory problem.')]), 1],
    'ai-vocabulary' => ['ai-vocabulary', voiceLintDocument([voiceLintParagraph('The Friday report is crucial to the team.')]), 1],
    'corporate-vocabulary' => ['corporate-vocabulary', voiceLintDocument([voiceLintParagraph('I would leverage the CRM for the follow-up.')]), 1],
    'public-vocabulary' => ['public-vocabulary', voiceLintDocument([voiceLintParagraph('The new solution saves an afternoon.')]), 1],
    'filler-phrases' => ['filler-phrases', voiceLintDocument([voiceLintParagraph('I check the inbox in order to find the request.')]), 1],
    'negative-parallelism' => ['negative-parallelism', voiceLintDocument([voiceLintParagraph("It's not just a reporting problem, it's a memory problem.")]), 1],
    'chatbot-artifacts' => ['chatbot-artifacts', voiceLintDocument([voiceLintParagraph('Great question, and the answer starts in the inbox.')]), 1],
    'stock-openers' => ['stock-openers', voiceLintDocument([voiceLintHeading('Where the afternoon goes'), voiceLintParagraph("Let's dive in to the Friday report.")]), 1],
    'sentence-case-headings' => ['sentence-case-headings', voiceLintDocument([voiceLintHeading("Why Another Hire Won't Fix It")]), 1],
    'label-headings' => ['label-headings', voiceLintDocument([voiceLintHeading('Conclusion')]), 1],
    'heading-order' => ['heading-order', voiceLintDocument([voiceLintHeading('A section with no parent', 3)]), 1],
    'inline-header-list' => ['inline-header-list', voiceLintDocument([['type' => 'bulletList', 'attrs' => ['id' => 'blk_00000000000000d1'], 'content' => [
        ['type' => 'listItem', 'attrs' => ['id' => 'blk_00000000000000d2'], 'content' => [voiceLintMarked([
            ['type' => 'text', 'text' => 'Performance:', 'marks' => [['type' => 'bold']]],
            ['type' => 'text', 'text' => ' the report takes an afternoon.'],
        ])]],
    ]]]), 1],
    'bold-limit' => ['bold-limit', voiceLintDocument([voiceLintMarked([
        ['type' => 'text', 'text' => 'The report is '],
        ['type' => 'text', 'text' => 'late', 'marks' => [['type' => 'bold']]],
        ['type' => 'text', 'text' => ' and '],
        ['type' => 'text', 'text' => 'wrong', 'marks' => [['type' => 'bold']]],
        ['type' => 'text', 'text' => '.'],
    ])]), 1],
    'exclamation-limit' => ['exclamation-limit', voiceLintDocument([voiceLintParagraph('The report is done!'), voiceLintParagraph('And it is right!', 'blk_00000000000000a2')]), 1],
    'emoji' => ['emoji', voiceLintDocument([voiceLintParagraph('The report is done 🎉')]), 1],
    'corporate-we' => ['corporate-we', voiceLintDocument([voiceLintParagraph('We built the report together.')]), 1],
    'excluded-names' => ['excluded-names', voiceLintDocument([voiceLintParagraph('I learned this at WorkOS.')]), 1],
    'experience-claim' => ['experience-claim', voiceLintDocument([voiceLintParagraph('I have more than fifteen years of practice.')]), 1],
    'walkthrough-link' => ['walkthrough-link', voiceLintDocument([voiceLintParagraph('Bring one example of work that takes more than it should.')]), 1],
    'unresolved-markers' => ['unresolved-markers', voiceLintDocument([voiceLintParagraph('The report saves [NEED: proof] hours.')]), 1],
    'seo-title-length' => ['seo-title-length', voiceLintDocument([], ['seo_title' => 'Do you need another admin hire']), 1],
    'meta-description-length' => ['meta-description-length', voiceLintDocument([], ['description' => 'What I would check first when the team is full.']), 1],
    'slug-shape' => ['slug-shape', voiceLintDocument([], ['slug' => 'Another_Admin_Hire']), 1],
    'slug-archive-reuse' => ['slug-archive-reuse', fn (): LintTarget => voiceLintArchiveSlugTarget(), 1],
    'title-sentence-case' => ['title-sentence-case', voiceLintDocument([], ['title' => "Why Another Hire Won't Fix It"]), 1],
    'linkedin-length' => ['linkedin-length', voiceLintKit(PieceKind::LinkedinPost, voiceLintLinkedin('Do I need another admin hire? '.str_repeat('The report waits for Friday. ', 110))), 1],
    'linkedin-length (target phrase)' => ['linkedin-length', voiceLintKit(PieceKind::LinkedinPost, voiceLintLinkedin('Every Friday someone opens five tabs. '.str_repeat('They paste and reconcile. ', 7).'Do I need another admin hire?')), 1],
    'linkedin-hashtags' => ['linkedin-hashtags', voiceLintKit(PieceKind::LinkedinPost, voiceLintLinkedin('Do I need another admin hire? #hiring Check the chores first. #owners #admin #operations')), 2],
    'linkedin-link-in-comment' => ['linkedin-link-in-comment', voiceLintKit(PieceKind::LinkedinPost, voiceLintLinkedin('Do I need another admin hire? Read www.birdcar.dev/writing/another-admin-hire first.', 'More on this soon.')), 2],
    'bluesky-post-length' => ['bluesky-post-length', voiceLintKit(PieceKind::BlueskyThread, ['posts' => ['1/2 A new hire often inherits the same chores.', '2/2 '.trim(str_repeat('The report waits for Friday. ', 11)).' '.VOICE_LINT_POST_URL]]), 1],
    'bluesky-numbering' => ['bluesky-numbering', voiceLintKit(PieceKind::BlueskyThread, ['posts' => ['1/3 A new hire often inherits the same chores.', '2/2 Check the chores first.', 'The full post: '.VOICE_LINT_POST_URL]]), 2],
    'email-subject-length' => ['email-subject-length', voiceLintKit(PieceKind::EmailBlurb, voiceLintEmail(subject: 'Another admin hire will not fix the work on your desk')), 1],
    'email-blurb-length' => ['email-blurb-length', voiceLintKit(PieceKind::EmailBlurb, voiceLintEmail('Read the post: '.VOICE_LINT_POST_URL)), 1],
    'kit-no-walkthrough-cta' => ['kit-no-walkthrough-cta', voiceLintKit(PieceKind::LinkedinPost, voiceLintLinkedin(comment: 'The full post: '.VOICE_LINT_POST_URL.' and a Walkthrough at birdcar.dev/walkthrough')), 1],
    'kit-links-post' => ['kit-links-post', voiceLintKit(PieceKind::EmailBlurb, voiceLintEmail('A new hire often inherits the same chores. Check them first.')), 1],
]);

test('each voice rule stays quiet on clean owner prose and near misses', function (string $ruleId, LintTarget $target) {
    expect(voiceLintHitsFor($ruleId, $target))->toBe([]);
})->with([
    'em-dash' => ['em-dash', voiceLintDocument([voiceLintParagraph('The report takes an afternoon, every single week. The range is 3–4 hours.')])],
    'curly-quotes' => ['curly-quotes', voiceLintDocument([voiceLintParagraph('It\'s a memory problem, and "the same four chores, faster" is the usual answer.')])],
    'ai-vocabulary' => ['ai-vocabulary', voiceLintDocument([voiceLintParagraph('The landscaper sent the invoice, and the report is useful.')])],
    'corporate-vocabulary' => ['corporate-vocabulary', voiceLintDocument([voiceLintParagraph('The aligned columns make the report easier to read.')])],
    'public-vocabulary' => ['public-vocabulary', voiceLintDocument([voiceLintParagraph('One sentence on what this will not fix earns more trust than a stack of promises.')])],
    'filler-phrases' => ['filler-phrases', voiceLintDocument([voiceLintParagraph("It's also worth asking what the new person would spend their first month doing.")])],
    'negative-parallelism' => ['negative-parallelism', voiceLintDocument([voiceLintParagraph("A missed follow-up is almost never a people problem. It's a memory problem.")])],
    'chatbot-artifacts' => ['chatbot-artifacts', voiceLintDocument([voiceLintParagraph('The question an owner asks first is who sends the report.')])],
    'stock-openers' => ['stock-openers', voiceLintDocument([voiceLintParagraph('Every Friday, someone opens five tabs.'), voiceLintParagraph('In conclusion, the report is late again.', 'blk_00000000000000a2')])],
    'sentence-case-headings' => ['sentence-case-headings', voiceLintDocument([
        voiceLintHeading('What a Walkthrough report covers'),
        voiceLintHeading('Who owns the Friday report when its author is out?', 2, 'blk_00000000000000b2'),
        voiceLintHeading('How I use AI with GitHub and Craft & Communicate', 2, 'blk_00000000000000b3'),
    ])],
    'label-headings' => ['label-headings', voiceLintDocument([voiceLintHeading('What the summary should say')])],
    'heading-order' => ['heading-order', voiceLintDocument([voiceLintHeading('Where the time goes'), voiceLintHeading('The Friday report', 3, 'blk_00000000000000b2')])],
    'inline-header-list' => ['inline-header-list', voiceLintDocument([voiceLintMarked([
        ['type' => 'text', 'text' => 'Performance', 'marks' => [['type' => 'bold']]],
        ['type' => 'text', 'text' => ' is the thing owners notice first: slow reports.'],
    ])])],
    'bold-limit' => ['bold-limit', voiceLintDocument([
        voiceLintMarked([['type' => 'text', 'text' => 'late', 'marks' => [['type' => 'bold']]], ['type' => 'text', 'text' => ' is the problem.']]),
        voiceLintHeading('Where the time goes'),
        voiceLintMarked([['type' => 'text', 'text' => 'wrong', 'marks' => [['type' => 'bold'], ['type' => 'italic']]], ['type' => 'text', 'text' => ' is worse, and ', 'marks' => [['type' => 'bold']]]], 'blk_00000000000000c2'),
    ])],
    'exclamation-limit' => ['exclamation-limit', voiceLintDocument([voiceLintParagraph('The report is done!'), voiceLintMarked([['type' => 'text', 'text' => 'Run !important', 'marks' => [['type' => 'code']]]])])],
    'emoji' => ['emoji', voiceLintDocument([voiceLintParagraph('Copyright © Birdcar™, and the arrow → points at the next step.')])],
    'corporate-we' => ['corporate-we', voiceLintDocument([
        ['type' => 'blockquote', 'attrs' => ['id' => 'blk_00000000000000e1'], 'content' => [voiceLintParagraph('We ship on Fridays.')]],
        voiceLintParagraph('"We ship on Fridays," the owner said, and the US office agreed.', 'blk_00000000000000a2'),
    ])],
    'excluded-names' => ['excluded-names', voiceLintDocument([voiceLintParagraph('I learned this at GitHub, Heroku and Zapier.')])],
    'experience-claim' => ['experience-claim', voiceLintDocument([voiceLintParagraph('I have fifteen years of practice, and more than 12 years with owners.')])],
    'walkthrough-link' => ['walkthrough-link', voiceLintDocument([voiceLintParagraph('Bring one example of work that takes more than it should.'), voiceLintWalkthrough()])],
    'unresolved-markers' => ['unresolved-markers', voiceLintDocument([voiceLintParagraph('The report saves an afternoon every week.')])],
    'seo-title-length' => ['seo-title-length', voiceLintDocument([], ['seo_title' => 'Do you need another admin hire? What to check first'])],
    'meta-description-length' => ['meta-description-length', voiceLintDocument([], ['description' => VOICE_LINT_OWNER_DESCRIPTION])],
    'slug-shape' => ['slug-shape', voiceLintDocument([], ['slug' => 'another-admin-hire-what-to-check'])],
    'slug-archive-reuse' => ['slug-archive-reuse', voiceLintDocument([], ['slug' => 'another-admin-hire-what-to-check'])],
    'title-sentence-case' => ['title-sentence-case', voiceLintDocument([], ['title' => "Another admin hire won't fix it", 'seo_title' => 'Do You Need Another Admin Hire? What To Check First'])],
    'linkedin-length' => ['linkedin-length', voiceLintKit(PieceKind::LinkedinPost, voiceLintLinkedin('Owners ask me one question more than any other: do I need another admin-hire? '.str_repeat('The report waits for Friday. ', 90)))],
    'linkedin-hashtags' => ['linkedin-hashtags', voiceLintKit(PieceKind::LinkedinPost, voiceLintLinkedin("Do I need another admin hire? Point 1 is #1 for a reason, and it starts at /walkthrough#choose-a-time.\n\n#hiring #owners #operations"))],
    'linkedin-link-in-comment' => ['linkedin-link-in-comment', voiceLintKit(PieceKind::LinkedinPost, voiceLintLinkedin(comment: 'The full post: http://www.birdcar.dev/writing/another-admin-hire'))],
    'bluesky-post-length' => ['bluesky-post-length', voiceLintKit(PieceKind::BlueskyThread, ['posts' => ['1/1 '.trim(str_repeat('The report waits for Friday. ', 10))]])],
    'bluesky-numbering' => ['bluesky-numbering', voiceLintKit(PieceKind::BlueskyThread, ['posts' => ['1/2 A new hire often inherits the same chores.', 'The full post: '.VOICE_LINT_POST_URL.' (2/2)']])],
    'email-subject-length' => ['email-subject-length', voiceLintKit(PieceKind::EmailBlurb, voiceLintEmail(subject: 'Another admin hire will not fix the work you do'))],
    'email-blurb-length' => ['email-blurb-length', voiceLintKit(PieceKind::EmailBlurb, voiceLintEmail('A new hire often inherits 3.5 hours of the same chores. Check them first, in the order on '.VOICE_LINT_POST_URL.'. Then decide.'))],
    'kit-no-walkthrough-cta' => ['kit-no-walkthrough-cta', voiceLintKit(PieceKind::EmailBlurb, voiceLintEmail('A new hire often inherits the same chores, and a Walkthrough is where I look at them. Read the post: '.VOICE_LINT_POST_URL))],
    'kit-links-post' => ['kit-links-post', voiceLintKit(PieceKind::BlueskyThread, ['posts' => ['1/2 A new hire often inherits the same chores.', '2/2 The full post: birdcar.dev/writing/another-admin-hire/?utm_source=bluesky']])],
]);

test('the walkthrough link fails without exactly one link in the closing section', function (LintTarget $target) {
    expect(voiceLintHitsFor('walkthrough-link', $target))->not->toBe([]);
})->with([
    'no link' => [voiceLintDocument([voiceLintHeading('Where the time goes'), voiceLintParagraph('Bring one example.')])],
    'two links' => [voiceLintDocument([voiceLintHeading('Where the time goes'), voiceLintWalkthrough('blk_00000000000000f2'), voiceLintWalkthrough('blk_00000000000000f3', 'https://www.birdcar.dev/walkthrough#choose-a-time')])],
    'one link in the opening section' => [voiceLintDocument([
        voiceLintWalkthrough('blk_00000000000000f2'),
        voiceLintHeading('Where the time goes'),
        voiceLintParagraph('The report takes an afternoon.'),
        voiceLintHeading('What to change first', 2, 'blk_00000000000000b2'),
        voiceLintParagraph('Start with the inbox.', 'blk_00000000000000a2'),
    ])],
    'a link to another page' => [voiceLintDocument([voiceLintParagraph('Bring one example.'), voiceLintWalkthrough('blk_00000000000000f2', '/assessment')])],
]);

test('the register examples in the voice canon pass every rule', function () {
    $content = array_map(fn (string $text, int $index): array => voiceLintParagraph($text, sprintf('blk_%016x', $index + 1)), voiceLintRegisterExamples(), array_keys(voiceLintRegisterExamples()));
    $content[] = voiceLintWalkthrough();

    $hits = app(VoiceLinter::class)->lint(voiceLintDocument($content, ['title' => 'Why another admin hire will not fix the Friday report', 'description' => VOICE_LINT_OWNER_DESCRIPTION]));

    expect(voiceLintRegisterExamples())->not->toBeEmpty()
        ->and(array_map(fn (LintHit $hit): string => $hit->ruleId.': '.$hit->message, $hits))->toBe([]);
});

test('metadata gets the character rules, and only the title gets sentence case', function () {
    $target = voiceLintDocument([voiceLintParagraph('A clean paragraph.'), voiceLintWalkthrough()], [
        'title' => 'Agent plan pipeline — explained',
        'seo_title' => 'Do You Need Another Admin Hire? What To Check First',
        'description' => substr(VOICE_LINT_OWNER_DESCRIPTION, 0, -1).'!',
    ]);

    $hits = app(VoiceLinter::class)->lint($target);

    expect(array_map(fn (LintHit $hit): string => $hit->ruleId.'@'.$hit->location(), $hits))->toBe([
        'em-dash@$.metadata.title',
        'exclamation-limit@$.metadata.description',
    ]);
});

test('hits skip code, read protection and blockquotes from ancestors, and follow document order', function () {
    $target = voiceLintDocument([
        ['type' => 'blockquote', 'attrs' => ['id' => 'blk_00000000000000e1', 'protected' => true], 'content' => [voiceLintParagraph('A quoted crucial line.', 'blk_00000000000000e2')]],
        voiceLintMarked([
            ['type' => 'text', 'text' => 'Run '],
            ['type' => 'text', 'text' => 'leverage --now', 'marks' => [['type' => 'code']]],
            ['type' => 'text', 'text' => ' and then leverage it.'],
        ]),
        ['type' => 'codeBlock', 'attrs' => ['id' => 'blk_00000000000000c9'], 'content' => [['type' => 'text', 'text' => 'leverage — crucial']]],
    ]);

    $hits = app(VoiceLinter::class)->lint($target);

    expect($target->blocks[0])->toMatchArray(['id' => 'blk_00000000000000e2', 'in_blockquote' => true, 'protected' => true])
        ->and($target->blocks[1]['text'])->toBe('Run   and then leverage it.')
        ->and($target->blocks)->toHaveCount(2)
        ->and(array_map(fn (LintHit $hit): string => $hit->ruleId.'@'.$hit->location(), array_values(array_filter($hits, fn (LintHit $hit): bool => $hit->ruleId !== 'walkthrough-link'))))->toBe([
            'ai-vocabulary@blk_00000000000000e2',
            'corporate-vocabulary@blk_00000000000000c1',
        ]);
});

test('bold and link marks split across text nodes count once', function () {
    $target = voiceLintDocument([voiceLintMarked([
        ['type' => 'text', 'text' => 'Late', 'marks' => [['type' => 'bold']]],
        ['type' => 'text', 'text' => ' and wrong', 'marks' => [['type' => 'bold'], ['type' => 'italic']]],
        ['type' => 'text', 'text' => ' is the problem. Bring it to '],
        ['type' => 'text', 'text' => 'a ', 'marks' => [['type' => 'link', 'attrs' => ['href' => '/walkthrough']]]],
        ['type' => 'text', 'text' => 'Walkthrough', 'marks' => [['type' => 'link', 'attrs' => ['href' => '/walkthrough']], ['type' => 'bold']]],
    ])]);

    expect($target->blocks[0]['bold_spans'])->toBe([['text' => 'Late and wrong', 'offset' => 0], ['text' => 'Walkthrough', 'offset' => 45]])
        ->and($target->blocks[0]['links'])->toBe([['href' => '/walkthrough', 'text' => 'a Walkthrough']])
        ->and(voiceLintHitsFor('walkthrough-link', $target))->toBe([]);
});

test('a hit hash changes with its text but not with unrelated edits in the same block', function () {
    $before = voiceLintHitsFor('corporate-vocabulary', voiceLintDocument([voiceLintParagraph('I would leverage the CRM.')]));
    $unrelated = voiceLintHitsFor('corporate-vocabulary', voiceLintDocument([voiceLintParagraph('I would leverage the CRM on Fridays.')]));
    $changed = voiceLintHitsFor('corporate-vocabulary', voiceLintDocument([voiceLintParagraph('I would align the CRM.')]));

    expect($unrelated[0]->inputHash())->toBe($before[0]->inputHash())
        ->and($changed[0]->inputHash())->not->toBe($before[0]->inputHash());
});

test('a repeated word in one block is a hit per occurrence, so an override on the first never covers a second', function () {
    $once = voiceLintHitsFor('corporate-vocabulary', voiceLintDocument([voiceLintParagraph('I would leverage the CRM.')]));
    $twice = voiceLintHitsFor('corporate-vocabulary', voiceLintDocument([voiceLintParagraph('I would leverage the CRM and leverage the inbox.')]));

    expect($twice)->toHaveCount(2)
        ->and(array_map(fn (LintHit $hit): string => $hit->excerpt, $twice))->toBe(['leverage', 'leverage'])
        ->and($twice[0]->inputHash())->toBe($once[0]->inputHash())
        ->and($twice[1]->inputHash())->not->toBe($once[0]->inputHash());
});

test('body rules lint kit pieces, while heading, walkthrough and metadata rules stay with blog posts', function () {
    $target = LintTarget::fromDocument(['version' => 1, 'type' => 'doc', 'content' => [
        voiceLintHeading("Why Another Hire Won't Fix It"),
        voiceLintParagraph('We leverage — crucially — everything!'),
    ]], ['title' => "Why Another Hire Won't Fix It", 'seo_title' => 'Too short', 'description' => 'Too short.', 'slug' => 'Bad_Slug'], PieceKind::LinkedinPost);

    $ids = array_values(array_unique(array_map(fn (LintHit $hit): string => $hit->ruleId, app(VoiceLinter::class)->lint($target))));
    $blogOnly = ['heading-order', 'label-headings', 'sentence-case-headings', 'walkthrough-link', 'seo-title-length', 'meta-description-length', 'slug-shape', 'slug-archive-reuse', 'title-sentence-case'];

    expect($ids)->toContain('em-dash', 'corporate-vocabulary', 'corporate-we', 'exclamation-limit')
        ->and(array_intersect($ids, $blogOnly))->toBe([])
        ->and(array_intersect(app(VoiceLinter::class)->ruleIdsFor(PieceKind::EmailBlurb), $blogOnly))->toBe([])
        ->and(app(VoiceLinter::class)->ruleIdsFor(PieceKind::BlogPost))->not->toContain('linkedin-length', 'kit-links-post');
});

test('a kit piece takes no exclamation point at all, where a blog post may use one', function () {
    $paragraph = [voiceLintParagraph('The report is done!')];

    expect(voiceLintHitsFor('exclamation-limit', voiceLintDocument($paragraph)))->toBe([])
        ->and(voiceLintHitsFor('exclamation-limit', LintTarget::fromDocument(['content' => $paragraph], [], PieceKind::BlueskyThread))[0]->message ?? null)
        ->toBe('Remove the exclamation point from "report is done!"; a distribution piece takes none.');
});

test('kit link rules name the post URL, so a single-passage fix can clear them', function () {
    $linkedin = voiceLintHitsFor('linkedin-link-in-comment', voiceLintKit(PieceKind::LinkedinPost, voiceLintLinkedin(comment: 'More soon.')));
    $twice = voiceLintHitsFor('kit-links-post', voiceLintKit(PieceKind::BlueskyThread, ['posts' => ['1/2 Read '.VOICE_LINT_POST_URL, '2/2 The full post: '.VOICE_LINT_POST_URL]]));

    expect($linkedin[0]->message ?? null)->toBe('Put the link to the post, '.VOICE_LINT_POST_URL.', in the first comment.')
        ->and($linkedin[0]->blockId ?? null)->toBe(KitDocument::blockId(PieceKind::LinkedinPost, 'first_comment'))
        ->and(array_map(fn (LintHit $hit): string => $hit->message, $twice))->toBe(['Remove the link to the post here; it belongs once, in the last post.'])
        ->and($twice[0]->blockId)->toBe(KitDocument::blockId(PieceKind::BlueskyThread, 'post:1'));
});

test('the registered rule ids are exactly the rule ids tagged in the voice canon', function () {
    expect(app(VoiceLinter::class)->ruleIds())->toBe(app(VoiceCanon::class)->snapshot()->ruleIds());
});

test('the drafter is sent every mechanical rule statement and every rule id', function () {
    $activity = new EditorialActivity(['kind' => EditorialActivityKind::Draft, 'input' => ['canon' => app(VoiceCanon::class)->snapshot()->frozenFor(EditorialActivityKind::Draft)]]);
    $agent = new Drafter($activity);
    config()->set('ai.providers.openrouter.key', 'test-key');
    Http::preventStrayRequests();
    Http::fake(['https://openrouter.ai/api/v1/chat/completions' => Http::response([
        'id' => 'gen-voice-lint',
        'choices' => [['finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => '{}']]],
    ])]);

    $agent->prompt($agent->promptText());

    $body = Http::recorded()->sole()[0]->data();
    $system = (string) data_get($body, 'messages.0.content');
    $documentDescription = (string) data_get($body, 'response_format.json_schema.schema.properties.document.description');
    preg_match_all('/^.*\[rule:[a-z0-9-]+\].*$/m', (string) file_get_contents(resource_path('publishing/canon/voice/mechanical-rules.md')), $statements);

    preg_match_all('/^.*\[rule:[a-z0-9-]+\].*$/m', (string) file_get_contents(resource_path('publishing/canon/voice/metadata-rules.md')), $metadataStatements);

    expect($statements[0])->toHaveCount(21)
        ->and($metadataStatements[0])->toHaveCount(5);
    foreach ([...$statements[0], ...$metadataStatements[0]] as $statement) {
        expect($system)->toContain($statement);
    }
    foreach (app(VoiceLinter::class)->ruleIdsFor(PieceKind::BlogPost) as $ruleId) {
        expect($documentDescription)->toContain($ruleId);
    }
    expect($documentDescription)->not->toContain('linkedin-length')
        ->and($documentDescription)->not->toContain('kit-links-post');
});

test('the kit drafter is sent every kit rule statement and the id of every rule its piece must pass', function (PieceKind $kind) {
    $activity = new EditorialActivity(['kind' => EditorialActivityKind::DraftKit, 'input' => ['piece_kind' => $kind->value, 'canon' => app(VoiceCanon::class)->snapshot()->frozenFor(EditorialActivityKind::DraftKit)]]);
    $agent = new KitDrafter($activity);
    config()->set('ai.providers.openrouter.key', 'test-key');
    Http::preventStrayRequests();
    Http::fake(['https://openrouter.ai/api/v1/chat/completions' => Http::response([
        'id' => 'gen-kit-lint',
        'choices' => [['finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => '{}']]],
    ])]);

    $agent->prompt($agent->promptText());

    $body = Http::recorded()->sole()[0]->data();
    $system = (string) data_get($body, 'messages.0.content');
    $schema = json_encode(data_get($body, 'response_format.json_schema.schema'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    preg_match_all('/^.*\[rule:[a-z0-9-]+\].*$/m', (string) file_get_contents(resource_path('publishing/canon/voice/kit-rules.md')), $statements);

    expect($statements[0])->toHaveCount(9)
        ->and(array_keys((array) data_get($body, 'response_format.json_schema.schema.properties')))->toBe(KitDocument::fieldsFor($kind));
    foreach ($statements[0] as $statement) {
        expect($system)->toContain($statement);
    }
    foreach (app(VoiceLinter::class)->ruleIdsFor($kind) as $ruleId) {
        expect($schema)->toContain($ruleId);
    }
})->with([PieceKind::LinkedinPost, PieceKind::BlueskyThread, PieceKind::EmailBlurb]);
