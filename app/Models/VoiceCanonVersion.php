<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * One immutable version of the voice canon: the canon directory's files and the hashes of the archive essays it reads
 * its exemplars from. Its hash is the canon version activities freeze. Only activation ever changes a row, and the
 * active version is the one activated most recently.
 */
#[Fillable(['hash', 'source', 'parent_version_id', 'files', 'external_hashes', 'created_by', 'note'])]
class VoiceCanonVersion extends Model
{
    public const SOURCE_REPO = 'repo';

    public const SOURCE_ADMIN = 'admin';

    /** The columns lists read. A version's files can be large, so a list never selects them. */
    public const HISTORY_COLUMNS = ['id', 'hash', 'source', 'note', 'created_by', 'activated_at', 'created_at'];

    /** Serializes saves, activations and the deploy sync, including the first sync into an empty table. */
    private const CHANGE_LOCK = 4_202_610_028;

    /** @var list<string> */
    private const ACTIVATION_ATTRIBUTES = ['activated_at', 'updated_at'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected static function booted(): void
    {
        static::updating(function (VoiceCanonVersion $version): void {
            if (array_diff(array_keys($version->getDirty()), self::ACTIVATION_ATTRIBUTES) !== []) {
                throw new RuntimeException('Voice canon versions are immutable; only activation changes a version.');
            }
        });

        static::deleting(function (): void {
            throw new RuntimeException('Voice canon versions are immutable and cannot be deleted.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'files' => 'array',
            'external_hashes' => 'array',
            'parent_version_id' => 'integer',
            'created_by' => 'integer',
            'activated_at' => 'datetime',
        ];
    }

    /**
     * The version activated most recently.
     *
     * @param  Builder<VoiceCanonVersion>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNotNull('activated_at')->orderByDesc('activated_at')->orderByDesc('id')->limit(1);
    }

    /**
     * Takes the change lock until the surrounding transaction ends.
     */
    public static function lockForChange(): void
    {
        DB::statement('select pg_advisory_xact_lock(?)', [self::CHANGE_LOCK]);
    }

    /**
     * Makes this version the active one. Its activation time is always later than every earlier activation, so two
     * activations in the same instant still order the way they happened. Call it while holding the change lock.
     */
    public function markActive(): void
    {
        $at = CarbonImmutable::now();
        $latest = static::query()->max('activated_at');
        if (is_string($latest) && $latest !== '') {
            $previous = CarbonImmutable::parse($latest);
            if ($at->lessThanOrEqualTo($previous)) {
                $at = $previous->addMicrosecond();
            }
        }

        $this->forceFill(['activated_at' => $at])->save();
    }

    public function shortHash(): string
    {
        return substr($this->hash, 0, 12);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<VoiceCanonVersion, $this>
     */
    public function parentVersion(): BelongsTo
    {
        return $this->belongsTo(VoiceCanonVersion::class, 'parent_version_id');
    }
}
