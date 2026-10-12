<?php

use App\Ai\Agents\Drafter;
use App\Models\EditorialActivity;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\PieceKind;
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

    $hits = app(VoiceLinter::class)->lint(voiceLintDocument($content, ['title' => 'Why another admin hire will not fix the Friday report', 'description' => 'What I would check first when the team is full.']));

    expect(voiceLintRegisterExamples())->not->toBeEmpty()
        ->and(array_map(fn (LintHit $hit): string => $hit->ruleId.': '.$hit->message, $hits))->toBe([]);
});

test('metadata gets the character rules but never sentence case', function () {
    $target = voiceLintDocument([voiceLintParagraph('A clean paragraph.'), voiceLintWalkthrough()], [
        'title' => 'Agent Plan Pipeline — Explained',
        'description' => 'The Friday report, finally fixed!',
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

test('only blog posts are linted in this phase', function () {
    $target = LintTarget::fromDocument(['version' => 1, 'type' => 'doc', 'content' => [voiceLintParagraph('We leverage — crucially — everything.')]], [], PieceKind::LinkedinPost);

    expect(app(VoiceLinter::class)->lint($target))->toBe([]);
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

    expect($statements[0])->toHaveCount(21);
    foreach ($statements[0] as $statement) {
        expect($system)->toContain($statement);
    }
    foreach (app(VoiceLinter::class)->ruleIds() as $ruleId) {
        expect($documentDescription)->toContain($ruleId);
    }
});
