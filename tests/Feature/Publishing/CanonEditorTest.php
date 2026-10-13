<?php

use App\Actions\Publishing\ActivateVoiceCanonVersion;
use App\Actions\Publishing\AdvancePublishingAttempt;
use App\Actions\Publishing\RunEditorialActivity;
use App\Actions\Publishing\SaveVoiceCanonVersion;
use App\Actions\Publishing\StartEditorialActivity;
use App\Actions\Publishing\SyncVoiceCanonFromRepo;
use App\Actions\Publishing\WriteArticle;
use App\Authorization\Admin\Role as AdminRole;
use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Authorization\Publishing\Role as PublishingRole;
use App\Models\EditorialActivity;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\User;
use App\Models\VoiceCanonVersion;
use App\Services\Publishing\EditorialOutput;
use App\Services\Publishing\VoiceCanon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Assert;
use Spatie\Permission\PermissionRegistrar;

const CANON_EDITOR_PAGE = 'http://admin.birdcar.test/settings/voice-canon';

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->artisan('authorization:sync')->assertSuccessful();
});

afterEach(function (): void {
    (new Filesystem)->deleteDirectory(storage_path('framework/testing/canon-editor'));
});

function canonEditorOwner(): User
{
    $owner = User::factory()->create();
    $owner->assignRole([AdminRole::Access->value, PublishingRole::Author->value]);

    return $owner;
}

function canonEditorWithoutConfiguration(): User
{
    $user = User::factory()->create();
    $user->assignRole(AdminRole::Access->value);
    $user->givePermissionTo(PublishingPermission::View->value, PublishingPermission::Write->value, PublishingPermission::Develop->value);

    return $user;
}

/**
 * Copies the repo canon and archive into a temporary directory and points the configuration at them, so a test can
 * change "the repo" without touching the real files.
 *
 * @return array{canon: string, archive: string}
 */
function canonEditorRepoCopy(): array
{
    $files = new Filesystem;
    $root = storage_path('framework/testing/canon-editor/'.Str::random(12));
    $paths = ['canon' => $root.'/canon', 'archive' => $root.'/archive'];
    $files->copyDirectory(resource_path('publishing/canon'), $paths['canon']);
    $files->copyDirectory(resource_path('writing'), $paths['archive']);
    config()->set('publishing_agents.canon.path', $paths['canon']);
    config()->set('publishing_agents.canon.archive_path', $paths['archive']);

    return $paths;
}

/** @return array<string, string> canon-relative path => content of the real repo canon */
function canonEditorRepoFiles(): array
{
    $files = [];
    foreach ((new Filesystem)->allFiles(resource_path('publishing/canon')) as $file) {
        $files[str_replace(DIRECTORY_SEPARATOR, '/', $file->getRelativePathname())] = $file->getContents();
    }
    ksort($files, SORT_STRING);

    return $files;
}

/**
 * The repo canon with one file's content changed.
 *
 * @return array<string, string>
 */
function canonEditorEdit(string $path, string $content): array
{
    return [...canonEditorRepoFiles(), $path => $content];
}

function canonEditorActive(): VoiceCanonVersion
{
    return VoiceCanonVersion::query()->active()->first(['id', 'hash', 'source']) ?? Assert::fail('No canon version is active.');
}

/** Saves an Admin edit on top of the active version. */
function canonEditorSave(User $owner, string $path, string $content, ?string $note = null): VoiceCanonVersion
{
    $active = VoiceCanonVersion::query()->active()->value('id');

    return app(SaveVoiceCanonVersion::class)->save($owner, $active === null ? null : (int) $active, canonEditorEdit($path, $content), $note);
}

/** @return array<string, string> archive entry name => bytes */
function canonEditorZipEntries(string $bytes): array
{
    $path = tempnam(sys_get_temp_dir(), 'canon-editor-');
    if ($path === false) {
        Assert::fail('Could not create a temporary file.');
    }
    file_put_contents($path, $bytes);

    $zip = new ZipArchive;
    if ($zip->open($path) !== true) {
        Assert::fail('The download is not a zip archive.');
    }

    $entries = [];
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $name = $zip->getNameIndex($index);
        $content = $zip->getFromIndex($index);
        if (! is_string($name) || ! is_string($content)) {
            Assert::fail("The zip entry {$index} is unreadable.");
        }
        $entries[$name] = $content;
    }
    $zip->close();
    unlink($path);
    ksort($entries, SORT_STRING);

    return $entries;
}

function canonEditorInterview(User $owner): EditorialActivity
{
    $write = app(WriteArticle::class);
    $article = $write->captureIdea($owner, 'Why another admin hire will not fix the Friday report');
    $revision = $write->save($owner, $article, null, ['version' => 1, 'type' => 'doc', 'content' => []], ['title' => 'The Friday report'], 'canon-editor-'.Str::random(8));
    $attempt = app(AdvancePublishingAttempt::class)->develop($owner, $article, $revision->id, ['goal' => 'Show owners what to check first', 'post_type' => 'essay']);

    return app(StartEditorialActivity::class)->start($owner, $attempt, EditorialActivityKind::Interview, [], 'canon-editor');
}

/** @return array{system: string, input: array<string, mixed>} */
function canonEditorRun(EditorialActivity $activity): array
{
    setPublishingAgentsPaused(false);
    config()->set('ai.providers.openrouter.key', 'test-key');
    Queue::fake();
    Http::preventStrayRequests();
    Http::fake(['https://openrouter.ai/api/v1/chat/completions' => Http::response([
        'id' => 'gen-canon-editor',
        'choices' => [['finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => json_encode([
            'questions' => ['What happened last Friday?'],
            'brief' => ['summary' => 'A brief.', 'missingAnswers' => []],
            'angleOptions' => [['title' => 'Check the chores first', 'thesis' => 'A hire adds a person to the same chores.']],
        ], JSON_THROW_ON_ERROR)]]],
    ])]);

    app(RunEditorialActivity::class, ['activityId' => $activity->id])->handle(app(EditorialOutput::class), app(WriteArticle::class));

    $body = Http::recorded()->sole()[0]->data();
    $prompt = json_decode((string) data_get($body, 'messages.1.content'), true, flags: JSON_THROW_ON_ERROR);
    $input = is_array($prompt) && is_array($prompt['input'] ?? null) ? $prompt['input'] : Assert::fail('The user message carried no input.');

    return ['system' => (string) data_get($body, 'messages.0.content'), 'input' => $input];
}

test('the first sync records the repo files as the active repo version with the repo hash', function () {
    $this->artisan('publishing:canon-sync')
        ->expectsOutputToContain('created-active')
        ->assertSuccessful();

    $version = VoiceCanonVersion::query()->sole();

    expect($version->hash)->toBe(app(VoiceCanon::class)->repoSnapshot()->version)
        ->and($version->source)->toBe(VoiceCanonVersion::SOURCE_REPO)
        ->and($version->activated_at)->not->toBeNull()
        ->and($version->files)->toEqual(canonEditorRepoFiles())
        ->and($version->external_hashes)->toEqual([
            'resources/writing/just-build-it-twice.md' => hash_file('sha256', resource_path('writing/just-build-it-twice.md')),
            'resources/writing/six-months-talking-to-a-machine.md' => hash_file('sha256', resource_path('writing/six-months-talking-to-a-machine.md')),
            'resources/writing/the-tools-that-build-the-tools.md' => hash_file('sha256', resource_path('writing/the-tools-that-build-the-tools.md')),
            'resources/writing/your-ai-wrote-a-bug.md' => hash_file('sha256', resource_path('writing/your-ai-wrote-a-bug.md')),
        ])
        ->and(app(VoiceCanon::class)->snapshot()->version)->toBe($version->hash);
});

test('a sync whose repo hash already has a version changes nothing', function () {
    app(SyncVoiceCanonFromRepo::class)->sync();

    $this->artisan('publishing:canon-sync')
        ->expectsOutputToContain('noop')
        ->assertSuccessful();

    expect(VoiceCanonVersion::query()->count())->toBe(1);
});

test('a repo change activates the new repo version while the previous repo version is active', function () {
    $sync = app(SyncVoiceCanonFromRepo::class);
    $sync->sync();
    $first = canonEditorActive();
    $paths = canonEditorRepoCopy();
    file_put_contents($paths['canon'].'/roles/rechecker.md', "\nA rule that arrived in a deploy.\n", FILE_APPEND);

    expect($sync->sync())->toBe(SyncVoiceCanonFromRepo::CREATED_ACTIVE);

    $active = VoiceCanonVersion::query()->active()->firstOrFail(['id', 'hash', 'parent_version_id']);
    expect($active->hash)->toBe(app(VoiceCanon::class)->repoSnapshot()->version)
        ->and($active->parent_version_id)->toBe($first->id)
        ->and(app(VoiceCanon::class)->snapshot()->files['resources/publishing/canon/roles/rechecker.md'])->toContain('A rule that arrived in a deploy.');
});

test('a repo change never replaces active Admin edits', function () {
    $owner = canonEditorOwner();
    app(SyncVoiceCanonFromRepo::class)->sync();
    $edit = canonEditorSave($owner, 'roles/drafter.md', canonEditorRepoFiles()['roles/drafter.md']."\nAn owner rule.\n");
    $paths = canonEditorRepoCopy();
    file_put_contents($paths['canon'].'/roles/rechecker.md', "\nA rule that arrived in a deploy.\n", FILE_APPEND);

    $this->artisan('publishing:canon-sync')
        ->expectsOutputToContain('created-inactive')
        ->assertSuccessful();

    $repo = VoiceCanonVersion::query()->where('source', VoiceCanonVersion::SOURCE_REPO)->orderByDesc('id')->firstOrFail(['id', 'hash', 'activated_at']);
    expect(canonEditorActive()->id)->toBe($edit->id)
        ->and($repo->hash)->toBe(app(VoiceCanon::class)->repoSnapshot()->version)
        ->and($repo->activated_at)->toBeNull()
        ->and(app(VoiceCanon::class)->snapshot()->version)->toBe($edit->hash);
});

test('a committed Admin download is a no-op for the next sync', function () {
    $owner = canonEditorOwner();
    $edit = canonEditorSave($owner, 'voice/core.md', canonEditorRepoFiles()['voice/core.md']."\nA sentence the owner added in Admin.\n");

    $bytes = $this->actingAs($owner)->get('http://admin.birdcar.test/settings/voice-canon/versions/'.$edit->id.'/download')->assertOk()->streamedContent();
    $paths = canonEditorRepoCopy();
    foreach (canonEditorZipEntries($bytes) as $name => $content) {
        file_put_contents($paths['canon'].'/'.Str::after($name, 'resources/publishing/canon/'), $content);
    }

    expect(app(VoiceCanon::class)->repoSnapshot()->version)->toBe($edit->hash)
        ->and(app(SyncVoiceCanonFromRepo::class)->sync())->toBe(SyncVoiceCanonFromRepo::NOOP)
        ->and(VoiceCanonVersion::query()->count())->toBe(2)
        ->and(canonEditorActive()->id)->toBe($edit->id);
});

test('the download holds every file of the version under resources/publishing/canon with identical bytes', function () {
    app(SyncVoiceCanonFromRepo::class)->sync();
    $version = canonEditorActive();

    $response = $this->actingAs(canonEditorOwner())
        ->get('http://admin.birdcar.test/settings/voice-canon/versions/'.$version->id.'/download')
        ->assertOk()
        ->assertDownload('voice-canon-'.$version->shortHash().'.zip')
        ->assertHeader('Cache-Control', 'max-age=0, no-store, private');

    $expected = [];
    foreach (canonEditorRepoFiles() as $path => $content) {
        $expected['resources/publishing/canon/'.$path] = $content;
    }

    expect(canonEditorZipEntries($response->streamedContent()))->toBe($expected);
});

test('saving an edit creates an Admin version on top of the active one and activates it', function () {
    $owner = canonEditorOwner();
    app(SyncVoiceCanonFromRepo::class)->sync();
    $repo = canonEditorActive();
    $content = canonEditorRepoFiles()['roles/drafter.md']."\nOpen with the reader's Friday, not the tool.\n";

    $version = canonEditorSave($owner, 'roles/drafter.md', $content, '  Sharper opening rule  ');

    $saved = VoiceCanonVersion::query()->findOrFail($version->id);
    expect($saved->source)->toBe(VoiceCanonVersion::SOURCE_ADMIN)
        ->and($saved->parent_version_id)->toBe($repo->id)
        ->and($saved->created_by)->toBe($owner->id)
        ->and($saved->note)->toBe('Sharper opening rule')
        ->and($saved->files['roles/drafter.md'])->toBe($content)
        ->and(canonEditorActive()->id)->toBe($saved->id)
        ->and(app(VoiceCanon::class)->snapshot()->version)->toBe($saved->hash)
        ->and(app(VoiceCanon::class)->snapshot()->rubricFor(EditorialActivityKind::Draft)['text'])->toContain("Open with the reader's Friday, not the tool.");
});

test('the first save in an environment the sync never reached records the repo baseline first', function () {
    $version = canonEditorSave(canonEditorOwner(), 'roles/drafter.md', canonEditorRepoFiles()['roles/drafter.md']."\nAn owner rule.\n");

    $repo = VoiceCanonVersion::query()->where('source', VoiceCanonVersion::SOURCE_REPO)->sole();
    expect($repo->hash)->toBe(app(VoiceCanon::class)->repoSnapshot()->version)
        ->and($version->parent_version_id)->toBe($repo->id)
        ->and(canonEditorActive()->id)->toBe($version->id);
});

test('re-activating an earlier version rolls the canon back', function () {
    $owner = canonEditorOwner();
    app(SyncVoiceCanonFromRepo::class)->sync();
    $repo = canonEditorActive();
    canonEditorSave($owner, 'roles/drafter.md', canonEditorRepoFiles()['roles/drafter.md']."\nAn owner rule.\n");

    app(ActivateVoiceCanonVersion::class)->activate($owner, $repo->id);

    expect(canonEditorActive()->id)->toBe($repo->id)
        ->and(app(VoiceCanon::class)->snapshot()->version)->toBe($repo->hash)
        ->and(VoiceCanonVersion::query()->count())->toBe(2);
});

test('activating the version that is already active is refused', function () {
    app(SyncVoiceCanonFromRepo::class)->sync();
    $active = canonEditorActive();

    expect(fn () => app(ActivateVoiceCanonVersion::class)->activate(canonEditorOwner(), $active->id))
        ->toThrow(ValidationException::class, "Version {$active->shortHash()} is already active.");
});

test('a version cannot change anything but its activation or be deleted', function () {
    app(SyncVoiceCanonFromRepo::class)->sync();
    $version = VoiceCanonVersion::query()->sole();

    expect(fn () => $version->forceFill(['note' => 'Rewritten history'])->save())
        ->toThrow(RuntimeException::class, 'Voice canon versions are immutable; only activation changes a version.')
        ->and(fn () => VoiceCanonVersion::query()->sole()->delete())
        ->toThrow(RuntimeException::class, 'Voice canon versions are immutable and cannot be deleted.')
        ->and(VoiceCanonVersion::query()->sole()->note)->toBeNull();
});

test('an activity started before a save keeps its frozen canon when it runs', function () {
    $owner = canonEditorOwner();
    app(SyncVoiceCanonFromRepo::class)->sync();
    $activity = canonEditorInterview($owner);
    $saved = canonEditorSave($owner, 'roles/interviewer.md', "# Rewritten interviewer rubric\n\nA live rule added after freezing.\n");

    $sent = canonEditorRun($activity);

    expect(app(VoiceCanon::class)->snapshot()->version)->toBe($saved->hash)
        ->and($sent['input']['canon']['version'] ?? null)->toBe($activity->input['canon']['version'] ?? null)
        ->and($sent['input']['canon']['version'] ?? null)->not->toBe($saved->hash)
        ->and($sent['system'])->toContain("## Your rubric\n\n# Interviewer rubric")
        ->and($sent['system'])->not->toContain('A live rule added after freezing.');
});

test('an edit that would break an agent task is refused with the rule and the file, and nothing is written', function (string $path, Closure $edit, string $problem) {
    $owner = canonEditorOwner();
    app(SyncVoiceCanonFromRepo::class)->sync();
    $files = canonEditorRepoFiles();
    $content = $edit($files[$path] ?? '');

    try {
        canonEditorSave($owner, $path, $content);
        Assert::fail('The edit was saved.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['files'] ?? [])->toContain($problem);
    }

    expect(VoiceCanonVersion::query()->count())->toBe(1);
})->with([
    'a blog rule removed from the draft rubric' => [
        'voice/mechanical-rules.md',
        fn (string $content): string => str_replace(' [rule:em-dash]', '', $content),
        'The draft rubric no longer states [rule:em-dash]. Tag it in one of its files: voice/core.md, voice/mechanical-rules.md, voice/metadata-rules.md, channels/blog.md, roles/drafter.md.',
    ],
    'a kit rule removed from the draft_kit rubric' => [
        'voice/kit-rules.md',
        fn (string $content): string => str_replace(' [rule:linkedin-length]', '', $content),
        'The draft_kit rubric no longer states [rule:linkedin-length]. Tag it in one of its files: voice/core.md, voice/mechanical-rules.md, voice/kit-rules.md, channels/kit.md, exemplars/short-form.md, roles/kit-drafter.md.',
    ],
    'an activity kind without a rubric' => [
        'roles.json',
        function (string $content): string {
            $roles = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($roles)) {
                Assert::fail('roles.json did not decode to an array.');
            }
            unset($roles['recheck']);

            return json_encode($roles, JSON_THROW_ON_ERROR);
        },
        'roles.json has no rubric files for the recheck role. Every agent task needs one.',
    ],
    'a rubric file that does not exist' => [
        'roles.json',
        fn (string $content): string => str_replace('"roles/interviewer.md"', '"roles/missing.md"', $content),
        'roles.json lists roles/missing.md for the interview role, but that file is not in the canon.',
    ],
    'a framework catalog that does not parse' => [
        'frameworks/catalog.json',
        fn (string $content): string => rtrim($content).',',
        'frameworks/catalog.json is missing or is not valid JSON.',
    ],
    'a local path' => [
        'roles/drafter.md',
        fn (string $content): string => $content."\nSee /Users/nick/notes.md.\n",
        'roles/drafter.md contains “/Users/”. Remove it; agents must never see it.',
    ],
    'the retired voice selection wording' => [
        'voice/core.md',
        fn (string $content): string => $content."\nMatch the Voice Sample.\n",
        'voice/core.md contains “voice sample”. Remove it; agents must never see it.',
    ],
    'no change at all' => [
        'voice/core.md',
        fn (string $content): string => $content,
        'Nothing changed. Edit a file before saving.',
    ],
]);

test('a save based on a version that is no longer active is refused', function () {
    $owner = canonEditorOwner();
    app(SyncVoiceCanonFromRepo::class)->sync();
    $opened = canonEditorActive();
    canonEditorSave($owner, 'roles/drafter.md', canonEditorRepoFiles()['roles/drafter.md']."\nFirst tab.\n");

    expect(fn () => app(SaveVoiceCanonVersion::class)->save($owner, $opened->id, canonEditorEdit('roles/planner.md', "# Planner rubric\n\nSecond tab.\n")))
        ->toThrow(ValidationException::class, SaveVoiceCanonVersion::STALE)
        ->and(VoiceCanonVersion::query()->count())->toBe(2);
});

test('an edit that matches an earlier version points to the history instead of duplicating it', function () {
    $owner = canonEditorOwner();
    app(SyncVoiceCanonFromRepo::class)->sync();
    $repo = canonEditorActive();
    canonEditorSave($owner, 'roles/drafter.md', canonEditorRepoFiles()['roles/drafter.md']."\nAn owner rule.\n");

    expect(fn () => canonEditorSave($owner, 'roles/drafter.md', canonEditorRepoFiles()['roles/drafter.md']))
        ->toThrow(ValidationException::class, "This edit matches version {$repo->shortHash()}. Activate it from the version history instead.");
});

test('an archive exemplar that changed on disk fails the snapshot by name until the next sync records it', function () {
    $paths = canonEditorRepoCopy();
    app(SyncVoiceCanonFromRepo::class)->sync();
    file_put_contents($paths['archive'].'/your-ai-wrote-a-bug.md', "\nAn edited closing line.\n", FILE_APPEND);
    app(VoiceCanon::class)->forget();

    expect(fn () => app(VoiceCanon::class)->snapshot())
        ->toThrow(RuntimeException::class, 'The voice canon exemplar source resources/writing/your-ai-wrote-a-bug.md changed since this canon version was saved.')
        ->and(app(SyncVoiceCanonFromRepo::class)->sync())->toBe(SyncVoiceCanonFromRepo::CREATED_ACTIVE)
        ->and(app(VoiceCanon::class)->snapshot()->version)->toBe(app(VoiceCanon::class)->repoSnapshot()->version);
});

test('the editor saves an edited file as the active version', function () {
    $owner = canonEditorOwner();
    app(SyncVoiceCanonFromRepo::class)->sync();
    $content = canonEditorRepoFiles()['roles/planner.md']."\nPrefer the owner's own example.\n";

    Livewire::actingAs($owner)
        ->test('admin.settings.voice-canon')
        ->call('open', 'roles/planner.md')
        ->assertSet('content', canonEditorRepoFiles()['roles/planner.md'])
        ->set('content', $content)
        ->set('note', 'Planner example rule')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('note', '')
        ->assertSee('New agent work uses it.');

    $active = VoiceCanonVersion::query()->active()->firstOrFail(['id', 'source', 'note', 'files']);
    expect($active->source)->toBe(VoiceCanonVersion::SOURCE_ADMIN)
        ->and($active->note)->toBe('Planner example rule')
        ->and($active->files['roles/planner.md'])->toBe($content);
});

test('the editor shows why an edit was refused and keeps the edit', function () {
    app(SyncVoiceCanonFromRepo::class)->sync();
    $content = str_replace(' [rule:em-dash]', '', canonEditorRepoFiles()['voice/mechanical-rules.md']);

    Livewire::actingAs(canonEditorOwner())
        ->test('admin.settings.voice-canon')
        ->call('open', 'voice/mechanical-rules.md')
        ->set('content', $content)
        ->call('save')
        ->assertHasErrors('files')
        ->assertSet('content', $content)
        ->assertSee('The draft rubric no longer states [rule:em-dash].');

    expect(VoiceCanonVersion::query()->count())->toBe(1);
});

test('the editor reports a canon it cannot read as an inline error and writes nothing', function () {
    $paths = canonEditorRepoCopy();
    $page = Livewire::actingAs(canonEditorOwner())
        ->test('admin.settings.voice-canon')
        ->call('open', 'roles/planner.md')
        ->set('content', canonEditorRepoFiles()['roles/planner.md']."\nOne more rule.\n");

    unlink($paths['archive'].'/your-ai-wrote-a-bug.md');
    app(VoiceCanon::class)->forget();

    $page->call('save')->assertOk();

    expect($page->get('saveError'))->toBeString()->toContain('your-ai-wrote-a-bug')
        ->and(VoiceCanonVersion::query()->count())->toBe(0);
});

test('the editor refuses a save from a tab opened before another save', function () {
    $owner = canonEditorOwner();
    app(SyncVoiceCanonFromRepo::class)->sync();
    $stale = Livewire::actingAs($owner)->test('admin.settings.voice-canon')->call('open', 'roles/planner.md');
    canonEditorSave($owner, 'roles/drafter.md', canonEditorRepoFiles()['roles/drafter.md']."\nFirst tab.\n");

    $stale->set('content', "# Planner rubric\n\nSecond tab.\n")
        ->call('save')
        ->assertHasErrors(['version' => SaveVoiceCanonVersion::STALE])
        ->assertSee('The canon changed since you opened it.');

    expect(VoiceCanonVersion::query()->count())->toBe(2);
});

test('the editor rolls back to an earlier version from the history', function () {
    $owner = canonEditorOwner();
    app(SyncVoiceCanonFromRepo::class)->sync();
    $repo = canonEditorActive();
    canonEditorSave($owner, 'roles/drafter.md', canonEditorRepoFiles()['roles/drafter.md']."\nAn owner rule.\n");

    Livewire::actingAs($owner)
        ->test('admin.settings.voice-canon')
        ->call('activate', $repo->id)
        ->assertHasNoErrors()
        ->assertSet('baseVersionId', $repo->id)
        ->assertSee("Version {$repo->shortHash()} is active.");

    expect(canonEditorActive()->id)->toBe($repo->id);
});

test('the editor flags a changed repo baseline with the files it changes and adopts it on request', function () {
    $owner = canonEditorOwner();
    app(SyncVoiceCanonFromRepo::class)->sync();
    $edit = canonEditorSave($owner, 'roles/drafter.md', canonEditorRepoFiles()['roles/drafter.md']."\nAn owner rule.\n");
    $paths = canonEditorRepoCopy();
    file_put_contents($paths['canon'].'/roles/rechecker.md', "\nA rule that arrived in a deploy.\n", FILE_APPEND);
    app(SyncVoiceCanonFromRepo::class)->sync();
    $baseline = VoiceCanonVersion::query()->where('source', VoiceCanonVersion::SOURCE_REPO)->orderByDesc('id')->firstOrFail(['id', 'hash']);

    $page = Livewire::actingAs($owner)
        ->test('admin.settings.voice-canon')
        ->assertSee('The repo baseline changed')
        ->assertSeeHtml('data-repo-baseline-notice="'.$baseline->id.'"')
        ->assertSee('Changed: roles/drafter.md, roles/rechecker.md')
        ->assertSeeHtml('data-repo-status="not-in-repo"');

    $page->call('activate', $baseline->id)
        ->assertHasNoErrors()
        ->assertDontSee('The repo baseline changed');

    expect(canonEditorActive()->id)->toBe($baseline->id)
        ->and(canonEditorActive()->id)->not->toBe($edit->id);
});

test('canon configurers reach the editor with voice canon current in the settings rail', function () {
    app(SyncVoiceCanonFromRepo::class)->sync();

    $this->actingAs(canonEditorOwner())
        ->get(CANON_EDITOR_PAGE)
        ->assertOk()
        ->assertSee('Voice canon settings')
        ->assertSee('Active version')
        ->assertSee('Version history')
        ->assertSeeHtml('aria-current="page"');
});

test('guests are sent to sign in before the editor and its downloads', function () {
    app(SyncVoiceCanonFromRepo::class)->sync();

    $this->get(CANON_EDITOR_PAGE)->assertRedirect('/login');
    $this->get(CANON_EDITOR_PAGE.'/versions/'.canonEditorActive()->id.'/download')->assertRedirect('/login');
});

test('the editor, its actions and its downloads are forbidden without the agent configuration capability', function (Closure $user) {
    $intruder = $user();
    app(SyncVoiceCanonFromRepo::class)->sync();
    $owner = canonEditorOwner();
    $repo = canonEditorActive();
    canonEditorSave($owner, 'roles/drafter.md', canonEditorRepoFiles()['roles/drafter.md']."\nAn owner rule.\n");
    $savePage = Livewire::actingAs($owner)->test('admin.settings.voice-canon')->call('open', 'roles/planner.md')->set('content', "# Planner rubric\n\nForged.\n");
    $activatePage = Livewire::actingAs($owner)->test('admin.settings.voice-canon');

    $this->actingAs($intruder)->get(CANON_EDITOR_PAGE)->assertForbidden();
    $this->actingAs($intruder)->get(CANON_EDITOR_PAGE.'/versions/'.$repo->id.'/download')->assertForbidden();
    Livewire::actingAs($intruder)->test('admin.settings.voice-canon')->assertForbidden();
    $savePage->call('save')->assertForbidden();
    $activatePage->call('activate', $repo->id)->assertForbidden();

    expect(VoiceCanonVersion::query()->count())->toBe(2)
        ->and(canonEditorActive()->id)->not->toBe($repo->id)
        ->and(fn () => app(SaveVoiceCanonVersion::class)->save($intruder, canonEditorActive()->id, canonEditorEdit('roles/planner.md', "# Planner rubric\n\nForged.\n")))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ActivateVoiceCanonVersion::class)->activate($intruder, $repo->id))->toThrow(AuthorizationException::class);
})->with([
    'admin access only' => fn (): User => tap(User::factory()->create())->assignRole(AdminRole::Access->value),
    'publishing access without configuration' => fn (): User => canonEditorWithoutConfiguration(),
]);

test('the workspace links the voice canon line to the editor only for canon configurers', function () {
    $owner = canonEditorOwner();
    $writer = canonEditorWithoutConfiguration();
    $write = app(WriteArticle::class);

    Livewire::actingAs($owner)
        ->test('admin.publishing.article-workspace', ['article' => $write->captureIdea($owner, 'Why another admin hire will not fix the Friday report')])
        ->assertSeeHtml('data-voice-canon-link')
        ->assertSeeHtml('href="'.route('admin.settings.voice-canon').'"');

    Livewire::actingAs($writer)
        ->test('admin.publishing.article-workspace', ['article' => $write->captureIdea($writer, 'Owners keep rebuilding the Friday report by hand.')])
        ->assertSee('Voice canon')
        ->assertDontSeeHtml('data-voice-canon-link');
});
