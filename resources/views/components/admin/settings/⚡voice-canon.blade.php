<?php

use App\Actions\Publishing\ActivateVoiceCanonVersion;
use App\Actions\Publishing\SaveVoiceCanonVersion;
use App\Models\User;
use App\Models\VoiceCanonVersion;
use App\Services\Publishing\VoiceCanon;
use App\Settings\Sections\VoiceCanonSection;
use App\Settings\SettingsSections;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.settings'), Title('Voice canon settings')] class extends Component
{
    private const HISTORY_LIMIT = 50;

    private const CONTENT_LIMIT = 200_000;

    /** The version the editor opened, or null while the environment has only the repo files. */
    #[Locked]
    public ?int $baseVersionId = null;

    /** @var list<string> canon-relative paths of the opened version */
    #[Locked]
    public array $paths = [];

    #[Locked]
    public ?string $path = null;

    public string $content = '';

    public string $note = '';

    public ?string $feedback = null;

    public ?string $saveError = null;

    public function mount(): void
    {
        app(SettingsSections::class)->authorize(VoiceCanonSection::class);

        $this->loadActive();
    }

    public function open(string $path): void
    {
        app(SettingsSections::class)->authorize(VoiceCanonSection::class);
        $this->feedback = null;

        $files = $this->baseFiles();
        if (! array_key_exists($path, $files)) {
            throw ValidationException::withMessages(['path' => 'Choose one of the listed files.']);
        }

        $this->resetValidation();
        $this->path = $path;
        $this->content = $files[$path];
    }

    public function save(): void
    {
        app(SettingsSections::class)->authorize(VoiceCanonSection::class);
        $this->feedback = null;
        $this->saveError = null;

        $this->validate([
            'content' => ['string', 'max:'.self::CONTENT_LIMIT],
            'note' => ['nullable', 'string', 'max:'.SaveVoiceCanonVersion::NOTE_LIMIT],
        ], [
            'content.max' => 'Keep one file under '.number_format(self::CONTENT_LIMIT).' characters.',
            'note.max' => 'Keep the note under '.number_format(SaveVoiceCanonVersion::NOTE_LIMIT).' characters.',
        ]);

        try {
            $files = $this->baseFiles();
            if ($this->path === null || ! array_key_exists($this->path, $files)) {
                throw ValidationException::withMessages(['path' => 'Choose one of the listed files.']);
            }
            $files[$this->path] = $this->content;

            $version = app(SaveVoiceCanonVersion::class)->save($this->user(), $this->baseVersionId, $files, $this->note);
        } catch (QueryException $exception) {
            report($exception);
            $this->saveError = "Couldn't save. Your changes are still here.";

            return;
        } catch (RuntimeException $exception) {
            $this->saveError = $exception->getMessage();

            return;
        }

        $this->baseVersionId = $version->id;
        $this->note = '';
        $this->feedback = "Saved version {$version->shortHash()}. New agent work uses it.";
        $this->dispatch('settings-saved', group: 'edit');
    }

    public function discard(): void
    {
        app(SettingsSections::class)->authorize(VoiceCanonSection::class);
        $this->feedback = null;
        $this->saveError = null;
        $this->resetValidation();

        $this->loadActive();
    }

    public function activate(int $versionId): void
    {
        app(SettingsSections::class)->authorize(VoiceCanonSection::class);
        $this->feedback = null;
        $this->resetValidation();

        $this->saveError = null;

        try {
            $version = app(ActivateVoiceCanonVersion::class)->activate($this->user(), $versionId);
        } catch (RuntimeException $exception) {
            $this->saveError = $exception->getMessage();

            return;
        }

        $this->loadActive();
        $this->feedback = "Version {$version->shortHash()} is active. New agent work uses it.";
    }

    public function with(): array
    {
        $active = VoiceCanonVersion::query()->active()->with('creator:id,name')->first(VoiceCanonVersion::HISTORY_COLUMNS);
        $repoHash = $this->repoHash();
        $errorFields = ['content', 'note', 'path', 'files', 'version'];

        return [
            'active' => $active,
            'repoHash' => $repoHash,
            'baseline' => $this->baseline($active),
            'history' => VoiceCanonVersion::query()
                ->select(VoiceCanonVersion::HISTORY_COLUMNS)
                ->with('creator:id,name')
                ->orderByDesc('id')
                ->limit(self::HISTORY_LIMIT)
                ->get(),
            'historyLimit' => self::HISTORY_LIMIT,
            'editErrorCount' => count(array_filter($errorFields, fn (string $field): bool => $this->getErrorBag()->has($field))),
        ];
    }

    /**
     * Opens the active version, keeping the open file when the version still has it.
     */
    private function loadActive(): void
    {
        $activeId = VoiceCanonVersion::query()->active()->value('id');
        $this->baseVersionId = $activeId === null ? null : (int) $activeId;
        $files = $this->baseFiles();
        $this->paths = array_keys($files);
        sort($this->paths, SORT_STRING);

        $this->path = $this->path !== null && array_key_exists($this->path, $files) ? $this->path : ($this->paths[0] ?? null);
        $this->content = $this->path === null ? '' : $files[$this->path];
        $this->note = '';
    }

    /**
     * @return array<string, string> the opened version's files, or the repo files while no version exists
     */
    private function baseFiles(): array
    {
        if ($this->baseVersionId === null) {
            return app(VoiceCanon::class)->repoSnapshot()->canonFiles();
        }

        return VoiceCanonVersion::query()->findOrFail($this->baseVersionId, ['id', 'files'])->files;
    }

    private function repoHash(): ?string
    {
        try {
            return app(VoiceCanon::class)->repoSnapshot()->version;
        } catch (RuntimeException $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * A repo version a deploy recorded but never activated because Admin edits were active, with the files it changes.
     *
     * @return array{version: VoiceCanonVersion, changed: list<string>, added: list<string>, removed: list<string>}|null
     */
    private function baseline(?VoiceCanonVersion $active): ?array
    {
        if ($active === null) {
            return null;
        }

        $repo = VoiceCanonVersion::query()->where('source', VoiceCanonVersion::SOURCE_REPO)->orderByDesc('id')->first(VoiceCanonVersion::HISTORY_COLUMNS);
        if ($repo === null || $repo->id === $active->id || $repo->activated_at !== null) {
            return null;
        }

        /** @var Collection<int, VoiceCanonVersion> $versions */
        $versions = VoiceCanonVersion::query()->whereKey([$active->id, $repo->id])->get(['id', 'files'])->keyBy('id');
        $current = $versions->get($active->id)?->files ?? [];
        $incoming = $versions->get($repo->id)?->files ?? [];

        $sorted = function (array $paths): array {
            sort($paths, SORT_STRING);

            return $paths;
        };

        return [
            'version' => $repo,
            'changed' => $sorted(array_keys(array_filter(array_intersect_key($incoming, $current), fn (string $content, string $path): bool => $content !== $current[$path], ARRAY_FILTER_USE_BOTH))),
            'added' => $sorted(array_keys(array_diff_key($incoming, $current))),
            'removed' => $sorted(array_keys(array_diff_key($current, $incoming))),
        ];
    }

    private function user(): User
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }
};
?>

<div data-voice-canon-settings class="settings-section">
    @if ($feedback)
        <p role="status" class="settings-feedback mt-5" data-voice-canon-feedback>{{ $feedback }}</p>
    @endif

    @error('version')
        <flux:callout variant="danger" icon="exclamation-triangle" class="mt-5" role="alert" data-voice-canon-error="version">
            <flux:callout.text>{{ $message }}</flux:callout.text>
        </flux:callout>
    @enderror

    @if ($baseline)
        <flux:callout variant="warning" icon="arrow-path" class="mt-5" data-repo-baseline-notice="{{ $baseline['version']->id }}">
            <flux:callout.heading>The repo baseline changed</flux:callout.heading>
            <flux:callout.text>
                A deploy recorded repo version <code>{{ $baseline['version']->shortHash() }}</code> and left your Admin edits active. Activate it to adopt the repo files; your edits stay in the history.
            </flux:callout.text>
            <ul class="settings-list" data-baseline-diff>
                @foreach (['changed' => 'Changed', 'added' => 'Added', 'removed' => 'Removed'] as $kind => $label)
                    @if ($baseline[$kind] !== [])
                        <li data-baseline-{{ $kind }}>{{ $label }}: {{ implode(', ', $baseline[$kind]) }}</li>
                    @endif
                @endforeach
                @if ($baseline['changed'] === [] && $baseline['added'] === [] && $baseline['removed'] === [])
                    <li>Only the archive essays its exemplars come from changed.</li>
                @endif
            </ul>
            <x-slot:actions>
                <flux:button size="sm" wire:click="activate({{ $baseline['version']->id }})" wire:confirm="Activate the repo version? New agent work uses it right away." data-activate-baseline>Activate the repo version</flux:button>
            </x-slot:actions>
        </flux:callout>
    @endif

    <x-admin.settings.group name="active" heading="Active version" description="New agent work freezes this version when it starts. Work already started keeps the version it began with.">
        @if ($active)
            <x-admin.settings.row label="Version" :help="$active->note ?? ($active->source === VoiceCanonVersion::SOURCE_REPO ? 'Recorded from the repo files by a deploy.' : 'Saved in Admin without a note.')" data-active-version="{{ $active->id }}">
                <span class="flex-wrap items-center gap-2">
                    <code>{{ $active->shortHash() }}</code>
                    <flux:badge size="sm" :color="$active->source === VoiceCanonVersion::SOURCE_ADMIN ? 'teal' : 'zinc'">{{ $active->source === VoiceCanonVersion::SOURCE_ADMIN ? 'Admin' : 'Repo' }}</flux:badge>
                    @if ($repoHash !== null && $active->hash === $repoHash)
                        <flux:badge size="sm" color="zinc" data-repo-status="in-repo">In repo</flux:badge>
                    @elseif ($active->source === VoiceCanonVersion::SOURCE_ADMIN)
                        <flux:badge size="sm" color="amber" data-repo-status="not-in-repo">Not in repo</flux:badge>
                    @endif
                </span>
                <x-slot:trailing>
                    <flux:button size="sm" icon="arrow-down-tray" :href="route('admin.settings.voice-canon.download', $active->id)" data-download-version="{{ $active->id }}">Download</flux:button>
                </x-slot:trailing>
            </x-admin.settings.row>
            <x-admin.settings.row label="Activated" :help="$active->creator ? 'Saved by '.$active->creator->name.'.' : null">
                <time datetime="{{ $active->activated_at?->toIso8601String() }}">{{ $active->activated_at?->diffForHumans() }}</time>
            </x-admin.settings.row>
            @if ($active->source === VoiceCanonVersion::SOURCE_ADMIN && $repoHash !== $active->hash)
                <p class="settings-row-state-line is-warning mt-3">Tests and fresh environments still use the repo files. Download this version, unpack it over the repo and commit it to keep them in step.</p>
            @endif
        @else
            <x-admin.settings.row label="Version" help="No version is recorded yet, so agents read the repo files. php artisan publishing:canon-sync records them; your first save does too." data-active-version="repo">
                <span class="flex-wrap items-center gap-2">
                    <code>{{ $repoHash !== null ? substr($repoHash, 0, 12) : 'unreadable' }}</code>
                    <flux:badge size="sm" color="zinc">Repo files</flux:badge>
                </span>
            </x-admin.settings.row>
        @endif
    </x-admin.settings.group>

    <x-admin.settings.group
        name="edit"
        heading="Edit a file"
        description="Saving makes a new version and activates it. An edit that would break an agent task is refused, and nothing is saved."
        :fields="['content', 'note']"
        save="save"
        discard="discard"
        :error-count="$editErrorCount"
        :save-error="$saveError"
    >
        @if ($errors->has('files'))
            <flux:callout variant="danger" icon="exclamation-triangle" class="mt-4" role="alert" data-voice-canon-error="files">
                <flux:callout.heading>This edit can't be saved</flux:callout.heading>
                <ul class="settings-list">
                    @foreach ($errors->get('files') as $problem)
                        <li>{{ $problem }}</li>
                    @endforeach
                </ul>
            </flux:callout>
        @endif

        <x-admin.settings.row label="File" help="Edit one file at a time. Switching files discards unsaved changes." for="voice-canon-file">
            <flux:select
                id="voice-canon-file"
                aria-describedby="voice-canon-file-help"
                x-on:change="if (! $wire.$dirty('content') || confirm('Discard your unsaved changes to this file?')) { $wire.open($event.target.value) } else { $event.target.value = $wire.path }"
            >
                @foreach (collect($paths)->groupBy(fn (string $file): string => str_contains($file, '/') ? Str::before($file, '/') : '')->sortKeys() as $folder => $files)
                    <optgroup label="{{ $folder === '' ? 'Top level' : $folder }}">
                        @foreach ($files as $file)
                            <option value="{{ $file }}" @selected($file === $path)>{{ $file }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </flux:select>
            <flux:error name="path" />
        </x-admin.settings.row>

        <div class="grid gap-2 border-b border-zinc-200 py-6 dark:border-white/10" data-voice-canon-editor="{{ $path }}">
            <label for="voice-canon-content" class="settings-row-label font-mono">{{ $path }}</label>
            <flux:textarea id="voice-canon-content" wire:model="content" rows="24" spellcheck="false" class="font-mono text-[.8125rem] leading-5" />
            <flux:error name="content" />
        </div>

        <x-admin.settings.row label="Note" help="Optional. Say what changed and why; it shows in the version history." for="voice-canon-note">
            <flux:input id="voice-canon-note" wire:model="note" maxlength="{{ SaveVoiceCanonVersion::NOTE_LIMIT }}" aria-describedby="voice-canon-note-help" />
            <flux:error name="note" />
        </x-admin.settings.row>
    </x-admin.settings.group>

    <x-admin.settings.group name="history" heading="Version history" :description="'Activating an earlier version is the rollback. The latest '.$historyLimit.' versions are listed.'">
        @forelse ($history as $version)
            @php
                $isActive = $active !== null && $active->id === $version->id;
                $help = collect([
                    $version->note,
                    ($version->source === VoiceCanonVersion::SOURCE_ADMIN ? 'Saved' : 'Recorded').' '.$version->created_at?->diffForHumans(),
                    $version->creator ? 'by '.$version->creator->name : null,
                ])->filter()->implode(' · ');
            @endphp
            <x-admin.settings.row wire:key="voice-canon-version-{{ $version->id }}" data-version-row="{{ $version->id }}" :label="$version->shortHash()" :help="$help">
                <span class="flex-wrap items-center gap-2">
                    <flux:badge size="sm" :color="$version->source === VoiceCanonVersion::SOURCE_ADMIN ? 'teal' : 'zinc'">{{ $version->source === VoiceCanonVersion::SOURCE_ADMIN ? 'Admin' : 'Repo' }}</flux:badge>
                    @if ($isActive)
                        <flux:badge size="sm" color="green" data-version-active>Active</flux:badge>
                    @endif
                    @if ($version->source === VoiceCanonVersion::SOURCE_ADMIN && $repoHash !== $version->hash)
                        <flux:badge size="sm" color="amber" data-repo-status="not-in-repo">Not in repo</flux:badge>
                    @endif
                </span>
                <x-slot:trailing>
                    <div class="flex items-center gap-2">
                        @unless ($isActive)
                            <flux:button size="sm" wire:click="activate({{ $version->id }})" wire:confirm="Activate version {{ $version->shortHash() }}? New agent work uses it right away." data-activate-version="{{ $version->id }}">Activate</flux:button>
                        @endunless
                        <flux:button size="sm" variant="ghost" icon="arrow-down-tray" :href="route('admin.settings.voice-canon.download', $version->id)" :aria-label="'Download version '.$version->shortHash()" data-download-version="{{ $version->id }}" />
                    </div>
                </x-slot:trailing>
            </x-admin.settings.row>
        @empty
            <p class="settings-row-state-line">No versions yet. The deploy sync or your first save records one.</p>
        @endforelse
    </x-admin.settings.group>
</div>
