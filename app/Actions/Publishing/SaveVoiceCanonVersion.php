<?php

namespace App\Actions\Publishing;

use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\PieceKind;
use App\Models\User;
use App\Models\VoiceCanonVersion;
use App\Services\Publishing\CanonSnapshot;
use App\Services\Publishing\FrameworkCatalog;
use App\Services\Publishing\Lint\VoiceLinter;
use App\Services\Publishing\VoiceCanon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Saves an Admin edit of the canon as a new version and activates it. An edit that would break the pipeline is
 * refused with every reason, naming the rule and the file, and nothing is written. A save is based on the version the
 * owner opened, so a second tab cannot silently overwrite a newer save.
 */
final class SaveVoiceCanonVersion
{
    public const NOTE_LIMIT = 1000;

    public const STALE = 'The canon changed since you opened it. Copy your edit, then choose Discard to load the current version.';

    /** Local paths that must never reach an agent. */
    private const FORBIDDEN = ['~/.config', '/Users/'];

    private const PATH_PATTERN = '/\A(?:[A-Za-z0-9_-]+\/)*[A-Za-z0-9_-][A-Za-z0-9_.-]*\.(?:md|json)\z/';

    private const ROLES = 'roles.json';

    public function __construct(
        private readonly VoiceCanon $canon,
        private readonly VoiceLinter $linter,
        private readonly SyncVoiceCanonFromRepo $sync,
    ) {}

    /**
     * @param  array<string, string>  $files  the complete edited canon: canon-relative path => content
     *
     * @throws ValidationException
     */
    public function save(User $actor, ?int $expectedVersionId, array $files, ?string $note = null): VoiceCanonVersion
    {
        if (! $actor->can(PublishingPermission::ConfigureAgents->value)) {
            throw new AuthorizationException('This user is not allowed to edit the voice canon.');
        }

        $note = trim((string) $note);
        if (mb_strlen($note) > self::NOTE_LIMIT) {
            throw ValidationException::withMessages(['note' => 'Keep the note under '.self::NOTE_LIMIT.' characters.']);
        }

        $snapshot = $this->validated($files);

        /** @var VoiceCanonVersion $version */
        $version = DB::transaction(function () use ($actor, $expectedVersionId, $snapshot, $note): VoiceCanonVersion {
            VoiceCanonVersion::lockForChange();

            $active = VoiceCanonVersion::query()->active()->first(['id', 'hash']);
            if ($active?->id !== $expectedVersionId) {
                throw ValidationException::withMessages(['version' => self::STALE]);
            }

            // The first save in an environment the deploy sync never reached records the repo files it edited.
            if ($active === null) {
                $this->sync->sync();
                $active = VoiceCanonVersion::query()->active()->firstOrFail(['id', 'hash']);
            }

            if ($active->hash === $snapshot->version) {
                throw ValidationException::withMessages(['files' => 'Nothing changed. Edit a file before saving.']);
            }

            $earlier = VoiceCanonVersion::query()->where('hash', $snapshot->version)->first(['id', 'hash']);
            if ($earlier !== null) {
                throw ValidationException::withMessages(['files' => "This edit matches version {$earlier->shortHash()}. Activate it from the version history instead."]);
            }

            $version = VoiceCanonVersion::create([
                'hash' => $snapshot->version,
                'source' => VoiceCanonVersion::SOURCE_ADMIN,
                'parent_version_id' => $active->id,
                'files' => $snapshot->canonFiles(),
                'external_hashes' => $snapshot->externalHashes(),
                'created_by' => $actor->id,
                'note' => $note === '' ? null : $note,
            ]);
            $version->markActive();

            return $version;
        });

        $this->canon->forget();

        return $version;
    }

    /**
     * The snapshot of an edited file set that every agent task can still use.
     *
     * @param  array<string, string>  $files
     *
     * @throws ValidationException listing every problem under `files`
     */
    private function validated(array $files): CanonSnapshot
    {
        $problems = $this->problems($files);

        if ($problems === []) {
            try {
                return $this->canon->snapshotOf($files);
            } catch (RuntimeException $exception) {
                $problems[] = $exception->getMessage();
            }
        }

        throw ValidationException::withMessages(['files' => $problems]);
    }

    /**
     * @param  array<string, string>  $files
     * @return list<string>
     */
    private function problems(array $files): array
    {
        $problems = [];
        foreach ($files as $path => $content) {
            if (preg_match(self::PATH_PATTERN, $path) !== 1 || str_contains($path, '..')) {
                $problems[] = "{$path} is not a canon file path. Use a relative path ending in .md or .json.";

                continue;
            }

            foreach (self::FORBIDDEN as $forbidden) {
                if (str_contains(Str::lower($content), Str::lower($forbidden))) {
                    $problems[] = "{$path} contains “{$forbidden}”. Remove it; agents must never see it.";
                }
            }
        }

        [$roles, $roleProblems] = $this->roleFiles($files);

        return [...$problems, ...$roleProblems, ...$this->catalogProblems($files), ...$this->ruleProblems($files, $roles)];
    }

    /**
     * Each activity kind's rubric files, checking that every kind has a rubric and every listed file exists.
     *
     * @param  array<string, string>  $files
     * @return array{0: array<string, list<string>>, 1: list<string>}
     */
    private function roleFiles(array $files): array
    {
        $decoded = json_decode($files[self::ROLES] ?? '', true);
        if (! is_array($decoded)) {
            return [[], [self::ROLES.' is missing or is not valid JSON.']];
        }

        $roles = [];
        $problems = [];
        foreach (EditorialActivityKind::cases() as $kind) {
            $listed = $decoded[$kind->value]['files'] ?? null;
            if (! is_array($listed) || $listed === []) {
                $problems[] = self::ROLES." has no rubric files for the {$kind->value} role. Every agent task needs one.";

                continue;
            }

            $roles[$kind->value] = [];
            foreach ($listed as $file) {
                if (! is_string($file) || ! array_key_exists($file, $files)) {
                    $problems[] = self::ROLES.' lists '.(is_string($file) ? $file : 'a non-path entry')." for the {$kind->value} role, but that file is not in the canon.";

                    continue;
                }
                $roles[$kind->value][] = $file;
            }
        }

        return [$roles, $problems];
    }

    /**
     * @param  array<string, string>  $files
     * @return list<string>
     */
    private function catalogProblems(array $files): array
    {
        $decoded = json_decode($files[FrameworkCatalog::PATH] ?? '', true);
        if (! is_array($decoded)) {
            return [FrameworkCatalog::PATH.' is missing or is not valid JSON.'];
        }

        try {
            $catalog = FrameworkCatalog::fromArray($decoded);
        } catch (RuntimeException $exception) {
            return [FrameworkCatalog::PATH.': '.$exception->getMessage()];
        }

        $problems = [];
        foreach ($catalog->postTypeSlugs() as $postType) {
            $template = $catalog->templatePath($postType);
            if ($template !== null && ! array_key_exists($template, $files)) {
                $problems[] = FrameworkCatalog::PATH." names {$template} as the {$postType} template, but that file is not in the canon.";
            }
        }
        foreach (array_keys($catalog->frameworks) as $framework) {
            $file = $catalog->frameworkPath($framework);
            if ($file !== null && ! array_key_exists($file, $files)) {
                $problems[] = FrameworkCatalog::PATH." names {$file} for the {$framework} framework, but that file is not in the canon.";
            }
        }

        return $problems;
    }

    /**
     * Every registered lint rule stays stated in the rubric of the drafter that must pass it: blog rules in the
     * draft rubric and distribution kit rules in the draft_kit rubric.
     *
     * @param  array<string, string>  $files
     * @param  array<string, list<string>>  $roles
     * @return list<string>
     */
    private function ruleProblems(array $files, array $roles): array
    {
        $blogRules = $this->linter->ruleIdsFor(PieceKind::BlogPost);
        $kitRules = [];
        foreach (PieceKind::cases() as $kind) {
            if ($kind->isKit()) {
                array_push($kitRules, ...$this->linter->ruleIdsFor($kind));
            }
        }

        $problems = [];
        foreach ([EditorialActivityKind::Draft->value => $blogRules, EditorialActivityKind::DraftKit->value => $kitRules] as $role => $ruleIds) {
            $rubric = $roles[$role] ?? null;
            if ($rubric === null) {
                continue;
            }

            $text = implode("\n", array_map(static fn (string $file): string => $files[$file], $rubric));
            foreach ($this->linter->ruleIds() as $ruleId) {
                if (in_array($ruleId, $ruleIds, true) && ! str_contains($text, '[rule:'.$ruleId.']')) {
                    $problems[] = "The {$role} rubric no longer states [rule:{$ruleId}]. Tag it in one of its files: ".implode(', ', $rubric).'.';
                }
            }
        }

        return $problems;
    }
}
