# Implementation Spec: Consistent Voice Publishing - Phase 8

**Contract**: ./contract.md
**Estimated Effort**: M

## Technical Approach

This phase lets the owner change the canon in Admin without a deploy. Until now (Phases 2-7) the canon was the repo files, frozen by manifest hash.

A `voice_canon_versions` table stores immutable versions. Each row holds:

- the canon directory's files, as relative path to content;
- the hashes of the external sources it references (the archive exemplar essays, which stay in `resources/writing/` and are not editable here);
- its `hash`, computed exactly as Phase 2's manifest hash, so a version seeded from the repo has the same hash activities already froze;
- its `source` (`repo` or `admin`), its parent, its author and an optional note.

The active version is the row with the most recent `activated_at`. Saving an edit creates a new `admin` version and activates it. Re-activating an older version is the rollback. `VoiceCanon::snapshot()` now reads the active version, and falls back to the repo files only when the table is empty.

A deploy-time `php artisan publishing:canon-sync` keeps environments in step with the repo. It computes the repo files' hash:

- If a version with that hash already exists, it does nothing. That includes the case where the owner downloaded an Admin version and committed it.
- Otherwise it creates a `repo` version. It activates that version only when the currently active version is the previous `repo` version (the owner made no Admin edits since) or when nothing is active.
- When Admin edits exist, the new repo version stays inactive, and the editor shows "The repo baseline changed" with a diff summary (files changed) and an activate button.

The owner adds this command to the Laravel Cloud deploy commands.

The editor is a new registered Settings section, following the project's single settings shell rule. Saves are rejected when they would break the pipeline:

- `roles.json` must parse and cover every `EditorialActivityKind`.
- Every referenced file must exist.
- `frameworks/catalog.json` must parse.
- Every registered lint rule id (`VoiceLinter::ruleIds()`) must still be stated in the Drafter rubric's files.

A download action returns the active version as a zip of the `resources/publishing/canon/` tree, ready to unpack over the repo and commit.

## Decisions Considered and Rejected

_Carried from the contract; consult before making gap decisions._

- **Decompose bat-kol into repo-owned rubric files as version 1; Admin-edited database versions win once the editor exists; a download of the active version is committed back to the repo**: rejected syncing from bat-kol by heading manifest, the repo always winning, or Admin-only storage after import.
- **Database versions, the Admin editor and a deploy-time sync arrive here, after every rubric exists; sync deduplicates by hash and auto-activates a repo version only when the active version is the previous repo-sourced one**: rejected seeding versions in Phase 2.
- **Save creates and activates; re-activating is rollback; the export's consumer is the repo baseline for fresh environments and tests**: from the over-engineering critic's simplification of a separate activation step.
- **New Admin configuration is a registered Settings section** (`.ai/rules/settings.md`).

## Feedback Strategy

**Inner-loop command**: `php artisan test --compact tests/Feature/Publishing/CanonEditorTest.php`

**Playground**: Pest with `Livewire::test('admin.settings.voice-canon')` and artisan command tests.

**Why this approach**: Versioning, sync rules and save validation are pure state logic; the Livewire test covers authorization and the form.

## File Changes

### New Files

| File Path | Purpose |
| --- | --- |
| `database/migrations/2026_10_02_400000_create_voice_canon_versions_table.php` | Versions table |
| `app/Models/VoiceCanonVersion.php` | Immutable model (`updating` and `deleting` throw), `files` and `external_hashes` cast to array, scope `active()` |
| `app/Actions/Publishing/SaveVoiceCanonVersion.php` | Validate and create-and-activate an `admin` version from an edited file set |
| `app/Actions/Publishing/ActivateVoiceCanonVersion.php` | Re-activate an existing version (rollback) |
| `app/Actions/Publishing/SyncVoiceCanonFromRepo.php` | Sync rules above; returns `noop`, `created-active` or `created-inactive` |
| `app/Console/Commands/PublishingCanonSync.php` | `php artisan publishing:canon-sync`; prints the outcome; exit 0 for every outcome |
| `app/Http/Controllers/Admin/DownloadVoiceCanonController.php` | Streams a zip of a version as `resources/publishing/canon/**` |
| `app/Settings/Sections/VoiceCanonSection.php` | Section `voice-canon`, group Application, label "Voice canon", permission `publishing.configure-agents`, component `admin.settings.voice-canon` |
| `resources/views/components/admin/settings/⚡voice-canon.blade.php` | Editor: active version summary, file list, edit one file at a time, note, save; version history with activate and download; repo-baseline-changed notice |
| `tests/Feature/Publishing/CanonEditorTest.php` | Versioning, sync, validation, rollback, download, authorization |

### Modified Files

| File Path | Changes |
| --- | --- |
| `app/Services/Publishing/VoiceCanon.php` | `snapshot()` reads the active version (files from the row, archive exemplar texts from `resources/writing/` verified against `external_hashes`); repo files only when no version exists; `repoSnapshot()` stays available for sync |
| `config/admin.php` | Add `VoiceCanonSection::class` after `PublishingSection::class` |
| `routes/admin.php` | `GET /settings/voice-canon/versions/{version}/download` named `admin.settings.voice-canon.download`, behind `publishing.configure-agents` |
| `resources/views/components/admin/publishing/partials/develop.blade.php` | The "Voice canon {short version}" line links to the editor for users with the permission |

## Implementation Details

### Versions and snapshot

```php
final class VoiceCanonVersion extends Model
{
    // hash (unique), source (repo|admin), parent_version_id, files (array<string,string>),
    // external_hashes (array<string,string>), created_by, note, activated_at
}
```

**Key decisions**:

- The hash is computed by the same code path for repo files and stored versions, so equal content always gives an equal hash. Add a test that a version seeded from the repo has `hash === app(VoiceCanon::class)->repoSnapshot()->version`.
- When an archive exemplar's source on disk no longer matches `external_hashes`, `snapshot()` throws a clear error naming the file. The sync then creates a new repo version, because the repo hash changed.
- Strict Eloquent applies: select the columns you read; `files` can be large, so the history list selects only `id, hash, source, note, created_by, activated_at, created_at`.

### Save validation

1. Parse `roles.json`. Every `EditorialActivityKind::cases()` value has an entry, and every listed file exists in the edited set.
2. Parse `frameworks/catalog.json`.
3. Every id in `VoiceLinter::ruleIds()` appears as `[rule:<id>]` in at least one file of the `draft` role's rubric (and kit ids in the `draft_kit` rubric).
4. No file contains `~/.config`, `/Users/` or the phrase "voice sample".
5. The content differs from the active version (no empty versions).

Failures return field errors naming the rule and the file. Nothing is written.

### Sync

```php
final class SyncVoiceCanonFromRepo
{
    /** @return 'noop'|'created-active'|'created-inactive' */
    public function sync(): string;
}
```

**Implementation steps**:

1. Migration, model, immutability guards.
2. Update `VoiceCanon::snapshot()`, and add `repoSnapshot()`.
3. Sync action and command. Tests for all three outcomes and the "committed Admin export" no-op.
4. Save and activate actions with validation.
5. Settings section, registry entry, SFC (authorize in `mount` and every mutation with `app(SettingsSections::class)->authorize(VoiceCanonSection::class)`), download controller and route.

**Feedback loop**:

- **Playground**: `tests/Feature/Publishing/CanonEditorTest.php`
- **Experiment**:
  - Empty table: sync creates an active repo version with the repo hash.
  - A second sync is a no-op.
  - An Admin edit then a repo change: sync creates an inactive version, and the active version is unchanged.
  - Download the Admin version, write its files over a temporary copy of the canon directory, then sync against that copy: no-op.
  - Save an edit that removes `[rule:em-dash]` from `voice/mechanical-rules.md`: rejected.
  - Start an activity, save a new version, then re-run the activity: it keeps its frozen canon.
- **Check command**: `php artisan test --compact tests/Feature/Publishing/CanonEditorTest.php`

## Data Model

```sql
CREATE TABLE voice_canon_versions (
  id BIGSERIAL PRIMARY KEY,
  hash CHAR(64) NOT NULL UNIQUE,
  source VARCHAR(10) NOT NULL,          -- repo | admin
  parent_version_id BIGINT NULL REFERENCES voice_canon_versions(id) ON DELETE SET NULL,
  files JSONB NOT NULL,                 -- relative path => content (canon directory only)
  external_hashes JSONB NOT NULL,       -- referenced archive source path => sha256
  created_by BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
  note TEXT NULL,
  activated_at TIMESTAMP NULL,
  created_at TIMESTAMP, updated_at TIMESTAMP
);
CREATE INDEX voice_canon_versions_activated_at_index ON voice_canon_versions(activated_at);
```

## Testing Requirements

| Test File | Coverage |
| --- | --- |
| `tests/Feature/Publishing/CanonEditorTest.php` | Immutability; save creates and activates; rollback; frozen activities unaffected; sync outcomes; dedupe by hash; validation failures; download zip contains every file at `resources/publishing/canon/...` with identical bytes; only `publishing.configure-agents` can view, save, activate or download |
| `tests/Feature/Publishing/VoiceCanonTest.php` | Updated: snapshot reads the active version when present and repo files when empty |
| `tests/Feature/Publishing/EditorialInputContractTest.php` | Still green; add a row asserting `input.canon.version` equals the active version hash after an Admin save |

## Error Handling

| Error Scenario | Handling Strategy |
| --- | --- |
| Invalid edit | Field errors; no version written |
| Concurrent saves from two tabs | Each save is based on an expected active version id; a stale base is rejected with "The canon changed since you opened it" |
| Archive exemplar source changed on disk | Snapshot throws with the file name; the next deploy's sync creates a new repo version |
| Sync not added to the Cloud deploy commands | Production keeps the last synced version and new rubric files never reach it; the acceptance gate lists adding the command |

## Failure Modes

| Component | Failure Mode | Trigger | Impact | Mitigation |
| --- | --- | --- | --- | --- |
| Sync | Repo change silently overrides Admin edits | Wrong activation rule | Owner's edits lost | Activate only when the active version is the previous repo version; test |
| Sync | Admin edits never reach tests or fresh environments | Owner forgets to commit the download | Drift between production and repo | Editor shows "not in repo" on Admin versions whose hash differs from the repo hash |
| Editor | Owner deletes a role's rubric file | Editing `roles.json` | Agents fail | Save validation covers every kind |
| Size | Large `files` payloads in lists | Selecting `*` | Slow settings page | Select only metadata columns for history |

## Validation Commands

```bash
php artisan test --compact tests/Feature/Publishing/CanonEditorTest.php tests/Feature/Publishing/VoiceCanonTest.php tests/Feature/Publishing/EditorialInputContractTest.php
php artisan test --compact tests/Feature/Publishing
vendor/bin/pint --dirty --format agent
env -u AI_AGENT -u CLAUDECODE vendor/bin/phpstan analyse --no-progress
composer test
```

## Rollout Considerations

- **Owner tasks**: add `php artisan publishing:canon-sync` to the Laravel Cloud deploy commands (Cloud UI, after migrations). Run it once locally.
- **Rollback plan**: revert; `VoiceCanon` falls back to repo files when the table is absent.
- **Rules in force**: `.ai/rules/settings.md` (registered section, authorize in mount and every mutation, scoped settings), `.ai/rules/app.md`, `.ai/rules/tests.md`.

## Open Items

- [ ] None.
