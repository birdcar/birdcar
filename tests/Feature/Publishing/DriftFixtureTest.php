<?php

use App\Actions\Publishing\WriteArticle;
use App\Authorization\Admin\Role as AdminRole;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Models\Publishing\PieceKind;
use App\Models\User;
use App\Services\Publishing\Lint\LintHit;
use App\Services\Publishing\Lint\VoiceLinter;
use App\Services\Publishing\VoiceCanon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Fixtures\Publishing\DriftGoldenSet;

const DRIFT_FIXTURE_SITE_COPY = 'exemplars/site-copy-2026-10-02.md';

/**
 * Dataset rows keyed by passage id, so a failure names the passage.
 *
 * @return array<string, array{string}>
 */
function driftFixtureIds(string $variant): array
{
    $ids = array_column(DriftGoldenSet::load()[$variant], 'id');

    return array_combine($ids, array_map(fn (string $id): array => [$id], $ids));
}

/** @return array{id: string, real_id: string, blocks: list<string>, injected: list<array{block_index: int, tell: string, mechanical: bool}>} */
function driftFixtureSlopped(string $id): array
{
    foreach (DriftGoldenSet::load()['slopped'] as $passage) {
        if ($passage['id'] === $id) {
            return $passage;
        }
    }

    throw new RuntimeException("No slopped passage has the id {$id}.");
}

function driftFixtureCollapse(string $text): string
{
    return trim((string) preg_replace('/\s+/u', ' ', $text));
}

/**
 * @param  list<LintHit>  $hits
 * @return list<string> "rule_id@block_id" for each hit
 */
function driftFixtureHitLocations(array $hits): array
{
    return array_map(fn (LintHit $hit): string => $hit->ruleId.'@'.$hit->blockId, $hits);
}

test('the golden set pairs ten real passages with exactly one slopped rewrite each', function () {
    $set = DriftGoldenSet::load();
    $realIds = array_column($set['real'], 'id');
    $pairedRealIds = array_column($set['slopped'], 'real_id');

    expect($realIds)->toHaveCount(10)
        ->and($set['slopped'])->toHaveCount(10)
        ->and(array_unique([...$realIds, ...array_column($set['slopped'], 'id')]))->toHaveCount(20)
        ->and($pairedRealIds)->toEqualCanonicalizing($realIds);
});

test('real passages quote one to three paragraphs verbatim, seven from the canon essays and three from the site copy', function () {
    $exemplars = array_column(app(VoiceCanon::class)->snapshot()->exemplars(), 'text', 'id');
    $real = DriftGoldenSet::load()['real'];
    $notQuoted = [];
    foreach ($real as $passage) {
        $source = driftFixtureCollapse($exemplars[$passage['source']] ?? '');
        foreach ($passage['blocks'] as $index => $block) {
            if (trim($block) === '' || ! str_contains($source, driftFixtureCollapse($block))) {
                $notQuoted[] = $passage['id'].' block '.$index;
            }
        }
    }
    $sources = array_count_values(array_map(fn (array $passage): string => $passage['source'] === DRIFT_FIXTURE_SITE_COPY ? 'site copy' : 'essay', $real));

    expect($notQuoted)->toBe([])
        ->and(array_filter($real, fn (array $passage): bool => count($passage['blocks']) < 1 || count($passage['blocks']) > 3))->toBe([])
        ->and(array_diff(array_column($real, 'source'), array_keys($exemplars)))->toBe([])
        ->and($sources)->toEqual(['essay' => 7, 'site copy' => 3]);
});

test('a slopped passage records two or three named tells on blocks it has, at least one that no lint rule sees', function (string $id) {
    $passage = driftFixtureSlopped($id);
    $ruleIds = array_values(array_diff(app(VoiceLinter::class)->ruleIdsFor(PieceKind::BlogPost), DriftGoldenSet::EXCERPT_EXEMPT_RULES));
    $mechanical = array_filter($passage['injected'], fn (array $tell): bool => $tell['mechanical']);
    $nonMechanical = array_filter($passage['injected'], fn (array $tell): bool => ! $tell['mechanical']);

    expect(count($passage['blocks']))->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(3)
        ->and(array_filter($passage['blocks'], fn (string $block): bool => trim($block) === ''))->toBe([])
        ->and(count($passage['injected']))->toBeGreaterThanOrEqual(2)->toBeLessThanOrEqual(3)
        ->and(array_filter(array_column($passage['injected'], 'block_index'), fn (int $index): bool => ! array_key_exists($index, $passage['blocks'])))->toBe([])
        ->and($nonMechanical)->not->toBeEmpty()
        ->and(array_diff(array_column($nonMechanical, 'tell'), DriftGoldenSet::NON_MECHANICAL_TELLS))->toBe([])
        ->and(array_diff(array_column($mechanical, 'tell'), $ruleIds))->toBe([]);
})->with(fn (): array => driftFixtureIds('slopped'));

test('a real passage is clean under the public-register lint', function (string $id) {
    $passage = collect(DriftGoldenSet::load()['real'])->firstWhere('id', $id);
    $blocks = is_array($passage) ? $passage['blocks'] : [];

    $hits = DriftGoldenSet::lint(DriftGoldenSet::document($id, $blocks));

    expect($blocks)->not->toBeEmpty()
        ->and(driftFixtureHitLocations($hits))->toBe([]);
})->with(fn (): array => driftFixtureIds('real'));

test('lint flags a slopped passage exactly where its mechanical tells were injected', function (string $id) {
    $passage = driftFixtureSlopped($id);
    $expected = [];
    foreach ($passage['injected'] as $tell) {
        if ($tell['mechanical']) {
            $expected[] = $tell['tell'].'@'.DriftGoldenSet::blockId($id, $tell['block_index']);
        }
    }

    $hits = DriftGoldenSet::lint(DriftGoldenSet::document($id, $passage['blocks']));

    expect(array_values(array_unique(driftFixtureHitLocations($hits))))->toEqualCanonicalizing($expected);
})->with(fn (): array => driftFixtureIds('slopped'));

test('a saved passage keeps its block ids, so an injected block index still names the right block', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
    $owner = User::factory()->create();
    $owner->assignRole([AdminRole::Access->value, PublishingRole::Author->value]);
    $writer = app(WriteArticle::class);
    $piece = $writer->captureIdea($owner, 'A short passage about building software');

    $revision = $writer->save($owner, $piece, null, DriftGoldenSet::document('slop-01', ['First.', 'Second.']), [], 'drift-fixture-ids');

    $content = is_array($revision->document['content'] ?? null) ? $revision->document['content'] : [];
    expect(array_map(fn (array $block): mixed => $block['attrs']['id'] ?? null, $content))->toBe([DriftGoldenSet::blockId('slop-01', 0), DriftGoldenSet::blockId('slop-01', 1)])
        ->and(DriftGoldenSet::blockId('slop-01', 0))->not->toBe(DriftGoldenSet::blockId('slop-02', 0));
});
