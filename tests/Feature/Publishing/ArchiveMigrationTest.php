<?php

use App\Actions\Publishing\ImportWritingArchive;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Models\Article;
use App\Models\ArticleRelease;
use App\Models\ArticleRevision;
use App\Models\User;
use App\Services\Publishing\ArchiveMarkdownImporter;
use App\Services\Publishing\ArticleDocument;
use App\Services\Publishing\PublishingFingerprint;
use Illuminate\Support\Facades\File;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
});

test('fixture archive converts directives into canonical document and parity manifests', function (): void {
    $directory = archiveFixtureDirectory(['minimal.md'], ['minimal-chart.json']);
    $entries = app(ArchiveMarkdownImporter::class)->parseDirectory($directory);

    expect($entries)->toHaveCount(1)
        ->and($entries[0]['slug'])->toBe('minimal')
        ->and($entries[0]['document']['content'])->toHaveCount(7)
        ->and($entries[0]['source_manifest']['source']['sha256'])->not->toBe('')
        ->and($entries[0]['parity_manifest']['directive_counts']['figures'])->toBe(2)
        ->and($entries[0]['parity_manifest']['source']['links'])->toContain('/authors/birdcar')
        ->and($entries[0]['parity_manifest']['source']['note_titles'])->toBe(['About this fixture'])
        ->and($entries[0]['parity_manifest']['source']['charts'][0]['data'][0])->toBe(['category' => 'first', 'value' => 0])
        ->and($entries[0]['parity_manifest']['source']['diagrams'][0])->toMatchArray(['sourceType' => 'preset', 'name' => 'walkthrough'])
        ->and($entries[0]['parity_manifest']['imported_document']['charts'][0]['data'][0])->toBe(['category' => 'first', 'value' => 0]);
});

test('malformed directives and missing chart data fail preflight before writes', function (string $fixture): void {
    $directory = archiveFixtureDirectory([$fixture], []);

    expect(fn () => app(ImportWritingArchive::class)->dryRun($directory, 'baseline'))
        ->toThrow(RuntimeException::class);

    expect(Article::query()->count())->toBe(0)
        ->and(ArticleRevision::query()->count())->toBe(0)
        ->and(ArticleRelease::query()->count())->toBe(0);
})->with(['malformed-unknown-directive.md', 'malformed-missing-chart.md']);

test('dry run parses every real baseline essay with independent parity and referenced data without database writes', function (): void {
    $before = [Article::query()->count(), ArticleRevision::query()->count(), ArticleRelease::query()->count()];
    $report = app(ImportWritingArchive::class)->dryRun('resources/writing', '72f7d8ad8521573cb224022c902447f9ca4c4351');
    $expectedSlugs = collect(glob(resource_path('writing/*.md')) ?: [])
        ->map(fn (string $path): string => pathinfo($path, PATHINFO_FILENAME))
        ->sort()
        ->values()
        ->all();

    expect($report['counts']['published'])->toBe(10)
        ->and($report['counts']['excluded'])->toBe(0)
        ->and(array_column($report['sources'], 'slug'))->toBe($expectedSlugs)
        ->and($report['write'])->toBeFalse()
        ->and([Article::query()->count(), ArticleRevision::query()->count(), ArticleRelease::query()->count()])->toBe($before);

    foreach ($report['sources'] as $source) {
        expect($source['source_sha256'])->toBe(hash_file('sha256', resource_path('writing/'.$source['slug'].'.md')))
            ->and($source['parity']['matches'])->toBeTrue()
            ->and($source['parity']['legacy_reader'])->not->toBeNull()
            ->and($source['parity']['source']['links'])->toBe($source['parity']['imported_document']['links'])
            ->and($source['parity']['source']['note_titles'])->toBe($source['parity']['imported_document']['note_titles'])
            ->and($source['parity']['source']['charts'])->toHaveCount(count($source['parity']['imported_document']['charts']))
            ->and($source['parity']['source']['diagrams'])->toBe($source['parity']['imported_document']['diagrams']);
    }

    $prompts = archiveSource($report, 'six-months-talking-to-a-machine');
    $bugs = archiveSource($report, 'your-ai-wrote-a-bug');

    expect($prompts['source_manifest']['source']['data'][0]['sha256'])->toBe(hash_file('sha256', resource_path('writing/data/six-months-prompts.json')))
        ->and($prompts['parity']['source']['charts'][0]['data'])->toBe(json_decode(File::get(resource_path('writing/data/six-months-prompts.json')), true, flags: JSON_THROW_ON_ERROR)['data'])
        ->and($bugs['source_manifest']['source']['data'][0]['sha256'])->toBe(hash_file('sha256', resource_path('writing/data/bug-fix-loops.json')))
        ->and($bugs['parity']['source']['charts'][0]['data'])->toBe(json_decode(File::get(resource_path('writing/data/bug-fix-loops.json')), true, flags: JSON_THROW_ON_ERROR)['data']);
});

test('imported releases are visible through the database public reader after cutover', function (): void {
    config(['publishing.public_reader' => 'database', 'marketing.url' => 'https://birdcar.dev']);
    $directory = archiveFixtureDirectory(['minimal.md'], ['minimal-chart.json']);

    app(ImportWritingArchive::class)->write($directory, 'baseline', archiveAuthor());

    $this->get('/writing/')->assertOk()
        ->assertSee('Minimal archive')
        ->assertSee('minimal');
    $this->get('/writing/minimal/')->assertOk()
        ->assertSee('A fixture')
        ->assertSee('About this fixture')
        ->assertDontSee('@figure');
    $this->get('/rss.xml')->assertOk()->assertSee('Minimal archive');
    $this->get('/sitemap.xml')->assertOk()->assertSee('https://birdcar.dev/writing/minimal/');
});

test('write creates import revisions and releases once without clobbering repeats', function (): void {
    $directory = archiveFixtureDirectory(['minimal.md'], ['minimal-chart.json']);
    $actor = archiveAuthor();
    $action = app(ImportWritingArchive::class);

    $first = $action->write($directory, 'baseline', $actor);
    $second = $action->write($directory, 'baseline', $actor);
    $article = Article::query()->where('slug', 'minimal')->firstOrFail();
    $release = ArticleRelease::query()->where('article_id', $article->id)->firstOrFail();

    expect(fn () => $action->write($directory, 'changed-baseline', $actor))
        ->toThrow(RuntimeException::class, 'clobber');

    expect($first['counts']['created'])->toBe(1)
        ->and($second['counts']['already_imported'])->toBe(1)
        ->and(Article::query()->count())->toBe(1)
        ->and(ArticleRevision::query()->count())->toBe(1)
        ->and(ArticleRelease::query()->count())->toBe(1)
        ->and($release->origin)->toBe('import')
        ->and($release->attempt_id)->toBeNull()
        ->and($release->payload['archive']['baseline'])->toBe('baseline')
        ->and($article->published_release_id)->toBe($release->id);
});

test('write refuses unrelated slug collisions and command requires an actor', function (): void {
    $directory = archiveFixtureDirectory(['minimal.md'], ['minimal-chart.json']);
    Article::factory()->create(['slug' => 'minimal']);

    expect(fn () => app(ImportWritingArchive::class)->write($directory, 'baseline', archiveAuthor()))
        ->toThrow(RuntimeException::class, 'clobber');

    $this->artisan('publishing:import-archive', ['--write' => true, '--source' => $directory, '--baseline' => 'baseline'])
        ->assertFailed();
});

test('excluded draft and future entries are reported without being written', function (): void {
    $directory = archiveFixtureDirectory(['minimal.md'], ['minimal-chart.json']);
    File::put($directory.'/draft.md', "---\ntitle: Draft\ndate: 2024-01-01\ndraft: true\n---\n\nDraft body.\n");
    File::put($directory.'/future.md', "---\ntitle: Future\ndate: 2999-01-01\n---\n\nFuture body.\n");

    $report = app(ImportWritingArchive::class)->dryRun($directory, 'baseline');

    expect($report['counts']['published'])->toBe(1)
        ->and($report['counts']['excluded'])->toBe(2)
        ->and($report['excluded'])->toContain([
            'slug' => 'draft',
            'title' => 'Draft',
            'reason' => 'draft',
            'source_sha256' => hash_file('sha256', $directory.'/draft.md'),
        ])->and($report['excluded'])->toContain([
            'slug' => 'future',
            'title' => 'Future',
            'reason' => 'future_date',
            'source_sha256' => hash_file('sha256', $directory.'/future.md'),
        ]);

    $write = app(ImportWritingArchive::class)->write($directory, 'baseline', archiveAuthor());

    expect($write['counts']['created'])->toBe(1)
        ->and($write['counts']['excluded'])->toBe(2)
        ->and(Article::query()->pluck('slug')->all())->toBe(['minimal']);
});

test('transaction failures roll back created archive records', function (): void {
    $directory = archiveFixtureDirectory(['minimal.md'], ['minimal-chart.json']);
    $action = new ImportWritingArchive(
        app(ArchiveMarkdownImporter::class),
        app(ArticleDocument::class),
        new class extends PublishingFingerprint
        {
            public function hash(mixed $value): string
            {
                if (is_array($value) && ($value['schema_version'] ?? null) === 1) {
                    throw new RuntimeException('forced release failure');
                }

                return parent::hash($value);
            }
        },
    );

    expect(fn () => $action->write($directory, 'baseline', archiveAuthor()))
        ->toThrow(RuntimeException::class, 'forced release failure');

    expect(Article::query()->count())->toBe(0)
        ->and(ArticleRevision::query()->count())->toBe(0)
        ->and(ArticleRelease::query()->count())->toBe(0);
});

test('production command writes require explicit confirmation', function (): void {
    $directory = archiveFixtureDirectory(['minimal.md'], ['minimal-chart.json']);
    $actor = archiveAuthor();
    $this->app->detectEnvironment(fn (): string => 'production');

    $this->artisan('publishing:import-archive', ['--write' => true, '--source' => $directory, '--baseline' => 'baseline', '--actor' => (string) $actor->id])
        ->assertFailed();

    $this->artisan('publishing:import-archive', ['--write' => true, '--source' => $directory, '--baseline' => 'baseline', '--actor' => (string) $actor->id, '--confirm-production-write' => true])
        ->assertSuccessful();
});

/**
 * @param  array<string, mixed>  $report
 * @return array<string, mixed>
 */
function archiveSource(array $report, string $slug): array
{
    foreach ($report['sources'] as $source) {
        if (($source['slug'] ?? null) === $slug) {
            return $source;
        }
    }

    throw new RuntimeException('Missing archive source '.$slug);
}

function archiveAuthor(): User
{
    $user = User::factory()->create();
    $user->assignRole(PublishingRole::Author->value);

    return $user;
}

/**
 * @param  list<string>  $markdownFiles
 * @param  list<string>  $dataFiles
 */
function archiveFixtureDirectory(array $markdownFiles, array $dataFiles): string
{
    $source = base_path('tests/Fixtures/Publishing/archive');
    $target = storage_path('framework/testing/archive-'.bin2hex(random_bytes(4)));
    File::ensureDirectoryExists($target.'/data');

    foreach ($markdownFiles as $file) {
        File::copy($source.'/'.$file, $target.'/'.$file);
    }

    foreach ($dataFiles as $file) {
        File::copy($source.'/data/'.$file, $target.'/data/'.$file);
    }

    return $target;
}
