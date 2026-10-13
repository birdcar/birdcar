<?php

use App\Actions\Publishing\SaveVoiceCanonVersion;
use App\Actions\Publishing\SyncVoiceCanonFromRepo;
use App\Authorization\Admin\Role as AdminRole;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\User;
use App\Models\VoiceCanonVersion;
use App\Services\Publishing\CanonSnapshot;
use App\Services\Publishing\VoiceCanon;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

const VOICE_CANON_RULE_IDS = [
    'ai-vocabulary', 'bluesky-numbering', 'bluesky-post-length', 'bold-limit', 'chatbot-artifacts', 'corporate-vocabulary',
    'corporate-we', 'curly-quotes', 'em-dash', 'email-blurb-length', 'email-subject-length', 'emoji', 'exclamation-limit',
    'excluded-names', 'experience-claim', 'filler-phrases', 'heading-order', 'inline-header-list', 'kit-links-post',
    'kit-no-walkthrough-cta', 'label-headings', 'linkedin-hashtags', 'linkedin-length', 'linkedin-link-in-comment',
    'meta-description-length', 'negative-parallelism', 'public-vocabulary', 'sentence-case-headings', 'seo-title-length',
    'slug-archive-reuse', 'slug-shape', 'stock-openers', 'title-sentence-case', 'unresolved-markers', 'walkthrough-link',
];

afterEach(function (): void {
    (new Filesystem)->deleteDirectory(storage_path('framework/testing/voice-canon'));
});

function voiceCanonSnapshot(): CanonSnapshot
{
    return (new VoiceCanon(new Filesystem))->snapshot();
}

/**
 * Copies the real canon and archive into a temporary directory and points the configuration at the copies, so a test
 * can change them without touching the repository files.
 *
 * @return array{canon: string, archive: string}
 */
function voiceCanonTemporaryCopy(): array
{
    $files = new Filesystem;
    $root = storage_path('framework/testing/voice-canon/'.Str::random(12));
    $paths = ['canon' => $root.'/canon', 'archive' => $root.'/archive'];
    $files->copyDirectory(resource_path('publishing/canon'), $paths['canon']);
    $files->copyDirectory(resource_path('writing'), $paths['archive']);
    config()->set('publishing_agents.canon.path', $paths['canon']);
    config()->set('publishing_agents.canon.archive_path', $paths['archive']);

    return $paths;
}

/** @return array<string, string> canon-relative path => content */
function voiceCanonFiles(): array
{
    $files = [];
    foreach ((new Filesystem)->allFiles(resource_path('publishing/canon')) as $file) {
        $files[$file->getRelativePathname()] = $file->getContents();
    }

    return $files;
}

test('the canon version is stable across snapshots and independent of where the files sit', function () {
    $version = voiceCanonSnapshot()->version;

    expect(voiceCanonSnapshot()->version)->toBe($version)
        ->and($version)->toMatch('/^[0-9a-f]{64}$/');

    voiceCanonTemporaryCopy();

    expect(voiceCanonSnapshot()->version)->toBe($version);
});

test('the canon version changes when a canon file changes', function () {
    $version = voiceCanonSnapshot()->version;
    $paths = voiceCanonTemporaryCopy();

    file_put_contents($paths['canon'].'/roles/drafter.md', "\nOne more drafting rule.\n", FILE_APPEND);

    expect(voiceCanonSnapshot()->version)->not->toBe($version);
});

test('the canon version changes when a referenced archive essay changes', function () {
    $version = voiceCanonSnapshot()->version;
    $paths = voiceCanonTemporaryCopy();

    file_put_contents($paths['archive'].'/just-build-it-twice.md', "\nAn edited closing line.\n", FILE_APPEND);

    expect(voiceCanonSnapshot()->version)->not->toBe($version);
});

test('hidden files and archive essays outside the exemplar canon never change the version', function () {
    $version = voiceCanonSnapshot()->version;
    $paths = voiceCanonTemporaryCopy();

    file_put_contents($paths['canon'].'/.DS_Store', 'Finder metadata');
    file_put_contents($paths['archive'].'/your-metrics-are-bullshit.md', "\nNot an exemplar.\n", FILE_APPEND);

    expect(voiceCanonSnapshot()->version)->toBe($version);
});

test('the manifest records repo-relative paths for canon files and referenced archive sources only', function () {
    $snapshot = voiceCanonSnapshot();

    expect($snapshot->hashes)->toHaveKeys([
        'resources/publishing/canon/goals.md',
        'resources/publishing/canon/roles.json',
        'resources/publishing/canon/frameworks/the-false-start.md',
        'resources/writing/just-build-it-twice.md',
        'resources/writing/the-tools-that-build-the-tools.md',
    ])
        ->and($snapshot->hashes['resources/publishing/canon/goals.md'])->toBe(hash_file('sha256', resource_path('publishing/canon/goals.md')))
        ->and(array_keys($snapshot->hashes))->not->toContain('resources/writing/your-metrics-are-bullshit.md');
});

test('no canon file carries a local path, a bat-kol framework alias or the retired voice selection wording', function (string $forbidden) {
    foreach (voiceCanonFiles() as $path => $content) {
        expect(str_contains(Str::lower($content), Str::lower($forbidden)))->toBeFalse("{$path} contains {$forbidden}");
    }
})->with(['~/.config', '/Users/', 'FW/', 'voice sample']);

test('every markdown path a canon file mentions resolves inside the canon', function () {
    foreach (voiceCanonFiles() as $path => $content) {
        preg_match_all('/(?<![A-Za-z0-9\/_.-])([a-z0-9_-]+(?:\/[a-z0-9_-]+)*\.md)\b/', $content, $matches);

        foreach ($matches[1] as $mentioned) {
            expect(resource_path('publishing/canon/'.$mentioned))->toBeFile("{$path} mentions {$mentioned}");
        }
    }
});

test('every editorial activity kind has a role rubric in roles.json', function (EditorialActivityKind $kind) {
    $snapshot = voiceCanonSnapshot();
    $rubric = $snapshot->rubricFor($kind);

    expect($snapshot->roles)->toHaveKey($kind->value)
        ->and($rubric['files'])->not->toBeEmpty()
        ->and($rubric['text'])->toStartWith('# ')
        ->and($rubric['text'])->not->toContain('# Business goals');
})->with(EditorialActivityKind::cases());

test('a rubric for a kind missing from roles.json fails loudly', function () {
    $paths = voiceCanonTemporaryCopy();
    $roles = json_decode((string) file_get_contents($paths['canon'].'/roles.json'), true, flags: JSON_THROW_ON_ERROR);
    if (! is_array($roles)) {
        throw new RuntimeException('roles.json did not decode to an array.');
    }
    unset($roles['interview']);
    file_put_contents($paths['canon'].'/roles.json', json_encode($roles, JSON_THROW_ON_ERROR));

    expect(fn () => voiceCanonSnapshot()->rubricFor(EditorialActivityKind::Interview))
        ->toThrow(RuntimeException::class, 'The voice canon has no rubric for the interview activity kind.');
});

test('a missing rubric file or archive exemplar fails the snapshot with its name', function () {
    $paths = voiceCanonTemporaryCopy();
    unlink($paths['canon'].'/roles/rechecker.md');

    expect(fn () => voiceCanonSnapshot())->toThrow(RuntimeException::class, 'The voice canon file roles/rechecker.md is missing.');

    $paths = voiceCanonTemporaryCopy();
    unlink($paths['archive'].'/your-ai-wrote-a-bug.md');

    expect(fn () => voiceCanonSnapshot())->toThrow(RuntimeException::class, 'The voice canon exemplar your-ai-wrote-a-bug has no archive source file.');
});

test('exemplars are the four archive essays and the site copy without frontmatter or directive blocks', function () {
    $exemplars = voiceCanonSnapshot()->exemplars();

    expect(array_column($exemplars, 'id'))->toBe([
        'just-build-it-twice',
        'your-ai-wrote-a-bug',
        'six-months-talking-to-a-machine',
        'the-tools-that-build-the-tools',
        'exemplars/site-copy-2026-10-02.md',
    ])
        ->and(array_column($exemplars, 'title'))->toBe([
            'Just build it twice',
            'Your AI wrote a bug',
            'Six months of talking to a machine',
            'The tools that build the tools',
            'Approved site copy, homepage and Walkthrough (2026-10-02)',
        ]);

    foreach ($exemplars as $exemplar) {
        expect($exemplar['text'])->not->toStartWith('---')
            ->and($exemplar['text'])->not->toMatch('/^(title|draft):/m')
            ->and($exemplar['text'])->not->toMatch('/^@(figure|aside|callout|end)/m');
    }

    expect($exemplars[4]['text'])->toContain('Better work, faster. Without another hire.')
        ->and($exemplars[4]['text'])->toContain('fifteen years');
});

test('each mechanical rule id is tagged exactly once and archive essays add no rule ids', function () {
    preg_match_all('/\[rule:([a-z0-9-]+)\]/', (string) file_get_contents(resource_path('publishing/canon/voice/mechanical-rules.md')), $tags);
    preg_match_all('/\[rule:([a-z0-9-]+)\]/', (string) file_get_contents(resource_path('publishing/canon/voice/metadata-rules.md')), $metadataTags);
    preg_match_all('/\[rule:([a-z0-9-]+)\]/', (string) file_get_contents(resource_path('publishing/canon/voice/kit-rules.md')), $kitTags);

    expect(voiceCanonSnapshot()->ruleIds())->toBe(VOICE_CANON_RULE_IDS)
        ->and($tags[1])->toHaveCount(21)
        ->and(array_unique($tags[1]))->toHaveCount(21)
        ->and($metadataTags[1])->toBe(['seo-title-length', 'meta-description-length', 'slug-shape', 'slug-archive-reuse', 'title-sentence-case'])
        ->and($kitTags[1])->toBe(['linkedin-length', 'linkedin-hashtags', 'linkedin-link-in-comment', 'bluesky-post-length', 'bluesky-numbering', 'email-subject-length', 'email-blurb-length', 'kit-no-walkthrough-cta', 'kit-links-post'])
        ->and(array_intersect($tags[1], $metadataTags[1]))->toBe([])
        ->and(array_intersect([...$tags[1], ...$metadataTags[1]], $kitTags[1]))->toBe([]);

    $paths = voiceCanonTemporaryCopy();
    file_put_contents($paths['archive'].'/six-months-talking-to-a-machine.md', "\nAn essay that mentions [rule:archive-only].\n", FILE_APPEND);

    expect(voiceCanonSnapshot()->ruleIds())->toBe(VOICE_CANON_RULE_IDS);
});

test('the canon hash command prints exactly the snapshot version', function () {
    $this->artisan('publishing:canon-hash')
        ->expectsOutput(voiceCanonSnapshot()->version)
        ->assertSuccessful();
});

test('the snapshot reads the repo files while no canon version exists', function () {
    expect(VoiceCanonVersion::query()->exists())->toBeFalse()
        ->and(voiceCanonSnapshot()->version)->toBe((new VoiceCanon(new Filesystem))->repoSnapshot()->version)
        ->and(voiceCanonSnapshot()->canonFiles())->toEqual(voiceCanonFiles());
});

test('the snapshot reads the active canon version instead of the repo files once one exists', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
    $owner = User::factory()->create();
    $owner->assignRole([AdminRole::Access->value, PublishingRole::Author->value]);
    app(SyncVoiceCanonFromRepo::class)->sync();
    $repoVersion = voiceCanonSnapshot()->version;
    $files = voiceCanonFiles();
    $files['roles/drafter.md'] .= "\nAn Admin-only drafting rule.\n";

    $version = app(SaveVoiceCanonVersion::class)->save($owner, (int) VoiceCanonVersion::query()->active()->value('id'), $files);

    $snapshot = voiceCanonSnapshot();
    expect($snapshot->version)->toBe($version->hash)
        ->and($snapshot->version)->not->toBe($repoVersion)
        ->and($snapshot->rubricFor(EditorialActivityKind::Draft)['text'])->toContain('An Admin-only drafting rule.')
        ->and((new VoiceCanon(new Filesystem))->repoSnapshot()->version)->toBe($repoVersion)
        ->and(file_get_contents(resource_path('publishing/canon/roles/drafter.md')))->not->toContain('An Admin-only drafting rule.');
});
