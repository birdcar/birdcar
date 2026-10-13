<?php

namespace App\Actions\Publishing;

use App\Models\VoiceCanonVersion;
use App\Services\Publishing\VoiceCanon;
use Illuminate\Support\Facades\DB;

/**
 * Keeps an environment's canon versions in step with the repo files at deploy. A repo hash that any version already
 * has changes nothing, including an Admin version the owner downloaded and committed. A new repo hash becomes a repo
 * version, which is activated only when nothing is active or the active version is the previous repo version, so a
 * deploy never replaces the owner's Admin edits.
 */
final class SyncVoiceCanonFromRepo
{
    public const NOOP = 'noop';

    public const CREATED_ACTIVE = 'created-active';

    public const CREATED_INACTIVE = 'created-inactive';

    public function __construct(private readonly VoiceCanon $canon) {}

    /** @return 'noop'|'created-active'|'created-inactive' */
    public function sync(): string
    {
        $repo = $this->canon->repoSnapshot();

        /** @var 'noop'|'created-active'|'created-inactive' $outcome */
        $outcome = DB::transaction(function () use ($repo): string {
            VoiceCanonVersion::lockForChange();

            if (VoiceCanonVersion::query()->where('hash', $repo->version)->exists()) {
                return self::NOOP;
            }

            $active = VoiceCanonVersion::query()->active()->first(['id', 'source']);
            $previousRepoId = VoiceCanonVersion::query()->where('source', VoiceCanonVersion::SOURCE_REPO)->orderByDesc('id')->value('id');
            $previousRepoId = $previousRepoId === null ? null : (int) $previousRepoId;

            $version = VoiceCanonVersion::create([
                'hash' => $repo->version,
                'source' => VoiceCanonVersion::SOURCE_REPO,
                'parent_version_id' => $previousRepoId,
                'files' => $repo->canonFiles(),
                'external_hashes' => $repo->externalHashes(),
            ]);

            if ($active !== null && ($active->source !== VoiceCanonVersion::SOURCE_REPO || $active->id !== $previousRepoId)) {
                return self::CREATED_INACTIVE;
            }

            $version->markActive();

            return self::CREATED_ACTIVE;
        });

        $this->canon->forget();

        return $outcome;
    }
}
