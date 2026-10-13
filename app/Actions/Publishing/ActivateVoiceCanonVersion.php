<?php

namespace App\Actions\Publishing;

use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Models\User;
use App\Models\VoiceCanonVersion;
use App\Services\Publishing\VoiceCanon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Makes an existing canon version active again: the rollback, and how the owner adopts a repo baseline that a deploy
 * left inactive. Work already started keeps the version it froze.
 */
final class ActivateVoiceCanonVersion
{
    public function __construct(private readonly VoiceCanon $canon) {}

    /**
     * @throws ValidationException when the version is already active or can no longer be read
     */
    public function activate(User $actor, int $versionId): VoiceCanonVersion
    {
        if (! $actor->can(PublishingPermission::ConfigureAgents->value)) {
            throw new AuthorizationException('This user is not allowed to change the active voice canon.');
        }

        /** @var VoiceCanonVersion $version */
        $version = DB::transaction(function () use ($versionId): VoiceCanonVersion {
            VoiceCanonVersion::lockForChange();

            $version = VoiceCanonVersion::query()->find($versionId, ['id', 'hash', 'files', 'external_hashes']);
            if ($version === null) {
                throw ValidationException::withMessages(['version' => 'That canon version no longer exists.']);
            }

            $activeId = VoiceCanonVersion::query()->active()->value('id');
            if ($activeId !== null && (int) $activeId === $version->id) {
                throw ValidationException::withMessages(['version' => "Version {$version->shortHash()} is already active."]);
            }

            try {
                $this->canon->snapshotOf($version->files, $version->external_hashes);
            } catch (RuntimeException $exception) {
                throw ValidationException::withMessages(['version' => "Version {$version->shortHash()} can't be activated: {$exception->getMessage()}"]);
            }

            $version->markActive();

            return $version;
        });

        $this->canon->forget();

        return $version;
    }
}
