<?php

namespace App\Actions\Publishing;

use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Models\Article;
use App\Models\ArticleRelease;
use App\Models\ArticleRevision;
use App\Models\EditorialActivity;
use App\Models\EditorialFinding;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\Publishing\PieceKind;
use App\Models\PublishingAttempt;
use App\Models\User;
use App\Services\Publishing\DocumentBlocks;
use App\Services\Publishing\Lint\LintHit;
use App\Services\Publishing\Lint\LintTarget;
use App\Services\Publishing\Lint\VoiceLinter;
use App\Services\Publishing\PublishingFingerprint;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Lints a revision against the mechanical voice rules and keeps its lint findings in step: one blocking finding per
 * hit, vanished hits and earlier revisions' findings marked stale. Overrides travel by hit hash across revisions.
 */
class LintRevision
{
    public const LENS = 'lint';

    /** Self-repair passes allowed per review cycle before the remaining hits go to the owner. */
    public const REPAIR_CAP = 2;

    /** Markers stand for proof or an anecdote only Nick can supply; a repair would have to invent it. */
    private const UNREPAIRABLE_RULES = ['unresolved-markers'];

    public function __construct(
        private readonly VoiceLinter $linter,
        private readonly PublishingFingerprint $fingerprint,
        private readonly DocumentBlocks $blocks,
        private readonly StartEditorialActivity $activities,
    ) {}

    /**
     * Archive essays stay exactly as published, so the voice canon never lints them. A kit piece is linted against the
     * flagship URL and target phrase its attempt froze, so every lint of one revision sees the same context.
     *
     * @return list<LintHit>
     */
    public function hits(Article $article, ArticleRevision $revision): array
    {
        if ($this->isArchiveImport($article)) {
            return [];
        }

        $kind = $article->getAttribute('kind');
        $kind = $kind instanceof PieceKind ? $kind : PieceKind::BlogPost;

        return $this->linter->lint(LintTarget::fromRevision($revision, $kind, $kind->isKit() ? $this->kitContext($article) : []));
    }

    /**
     * The flagship URL and target phrase frozen into a kit piece's attempt when it was spawned or restarted.
     *
     * @return array{canonical_url: string|null, target_query: string|null}
     */
    public function kitContext(Article $article): array
    {
        $brief = $article->current_attempt_id === null ? null : PublishingAttempt::query()->select(['id', 'brief'])->find($article->current_attempt_id)?->brief;
        $canonicalUrl = is_array($brief) ? ($brief['canonical_url'] ?? null) : null;
        $targetQuery = is_array($brief) ? ($brief['target_query'] ?? null) : null;

        return [
            'canonical_url' => is_string($canonicalUrl) ? $canonicalUrl : null,
            'target_query' => is_string($targetQuery) ? $targetQuery : null,
        ];
    }

    /**
     * Hits a repair agent may patch: they sit in a block and fixing them needs no new facts.
     *
     * @param  list<LintHit>  $hits
     * @return list<LintHit>
     */
    public function repairable(array $hits): array
    {
        return array_values(array_filter($hits, static fn (LintHit $hit): bool => $hit->blockId !== null && ! in_array($hit->ruleId, self::UNREPAIRABLE_RULES, true)));
    }

    public function completedRepairs(PublishingAttempt $attempt): int
    {
        return EditorialActivity::query()
            ->where('attempt_id', $attempt->id)
            ->where('review_cycle', (int) $attempt->review_cycle)
            ->where('kind', EditorialActivityKind::LintRepair->value)
            ->where('status', EditorialActivityStatus::Completed->value)
            ->count();
    }

    /**
     * The frozen input for a repair: each hit, and each block it touches with that block's subtree hash, so the agent
     * judges exactly the text it is asked to fix and echoes the hash it was given.
     *
     * @param  list<LintHit>  $hits
     * @return array{hits: list<array{hit_id: string, rule_id: string, block_id: string, excerpt: string, message: string}>, blocks: list<array{block_id: string, hash: string, node: array<string, mixed>, hit_ids: list<string>}>, expected_revision_id: int}
     */
    public function repairInput(ArticleRevision $revision, array $hits): array
    {
        $document = $this->document($revision);
        $rows = [];
        $blocks = [];

        foreach ($hits as $index => $hit) {
            $match = $hit->blockId === null ? null : $this->blocks->find($document, $hit->blockId);
            if ($hit->blockId === null || $match === null) {
                continue;
            }

            $hitId = 'hit-'.($index + 1);
            $rows[] = ['hit_id' => $hitId, 'rule_id' => $hit->ruleId, 'block_id' => $hit->blockId, 'excerpt' => $hit->excerpt, 'message' => $hit->message];
            $blocks[$hit->blockId] ??= ['block_id' => $hit->blockId, 'hash' => $this->fingerprint->hash($match['node']), 'node' => $match['node'], 'hit_ids' => []];
            $blocks[$hit->blockId]['hit_ids'][] = $hitId;
        }

        return ['hits' => $rows, 'blocks' => array_values($blocks), 'expected_revision_id' => (int) $revision->id];
    }

    /**
     * Re-lints the working revision after an owner save. Articles without a current attempt have nowhere to keep findings.
     */
    public function refresh(Article $article): void
    {
        $article = Article::query()->find($article->id);
        $attempt = $article?->currentAttempt()->first();
        $revision = $article?->workingRevision()->first();
        if (! $article instanceof Article || ! $attempt instanceof PublishingAttempt || ! $revision instanceof ArticleRevision) {
            return;
        }

        DB::transaction(fn () => $this->sync($attempt, $revision, $this->hits($article, $revision)));
    }

    /**
     * @param  list<LintHit>  $hits
     * @param  array<string, array<string, mixed>>  $patchesByBlock  unapplied repair patches keyed by block id
     */
    public function sync(PublishingAttempt $attempt, ArticleRevision $revision, array $hits, array $patchesByBlock = []): void
    {
        $document = $this->document($revision);
        $cycle = (int) $attempt->review_cycle;
        $hashes = array_map(static fn (LintHit $hit): string => $hit->inputHash(), $hits);

        $vanished = EditorialFinding::query()
            ->where('attempt_id', $attempt->id)
            ->where('lens', self::LENS)
            ->whereNull('stale_at');
        if ($hashes !== []) {
            $vanished->where(fn ($query) => $query
                ->where('revision_id', '!=', $revision->id)
                ->orWhereNull('revision_id')
                ->orWhere('review_cycle', '!=', $cycle)
                ->orWhereNotIn('input_hash', $hashes));
        }
        $vanished->update(['stale_at' => now()]);

        $current = EditorialFinding::query()
            ->where('attempt_id', $attempt->id)
            ->where('lens', self::LENS)
            ->where('revision_id', $revision->id)
            ->where('review_cycle', $cycle)
            ->whereNull('stale_at')
            ->get()
            ->keyBy('input_hash');
        $overrides = $this->overrides((int) $attempt->article_id);

        foreach ($hits as $hit) {
            $hash = $hit->inputHash();
            $match = $hit->blockId === null ? null : $this->blocks->find($document, $hit->blockId);
            $node = $match['node'] ?? null;
            $subtreeHash = $node === null ? null : $this->fingerprint->hash($node);
            $patch = $this->currentPatch($patchesByBlock[$hit->blockId ?? ''] ?? null, $hit, $node)
                ?? $this->earlierPatch($attempt, $hit, $node);

            $existing = $current->get($hash);
            if ($existing instanceof EditorialFinding) {
                if ($existing->proposed_patch === null && $patch !== null) {
                    $existing->forceFill(['proposed_patch' => $patch])->save();
                }

                continue;
            }

            $override = $overrides[$hash] ?? null;
            EditorialFinding::create([
                'article_id' => $attempt->article_id,
                'attempt_id' => $attempt->id,
                'activity_id' => null,
                'review_cycle' => $cycle,
                'revision_id' => $revision->id,
                'input_hash' => $hash,
                'lens' => self::LENS,
                'kind' => 'lint:'.$hit->ruleId,
                'severity' => 'blocking',
                'block_id' => $hit->blockId,
                'expected_subtree_hash' => $subtreeHash,
                'statement' => $hit->message,
                'supporting_source_ids' => [],
                'supporting_quotations' => [],
                'proposed_patch' => $patch,
                'disposition' => $override === null ? null : 'false_positive',
                'disposition_reason' => $override['reason'] ?? null,
                'disposition_actor_id' => $override['actor_id'] ?? null,
                'disposed_at' => $override === null ? null : now(),
            ]);
        }
    }

    /**
     * Ends the post-draft lint step: stores the revision's remaining hits as findings, with any unapplied repair patch,
     * then queues review on that revision: the three lenses for a blog post, the kit reviewer for a kit piece.
     */
    public function finishAndQueueReviews(User $actor, PublishingAttempt $attempt, ArticleRevision $revision, ?int $draftActivityId = null): void
    {
        $article = Article::query()->findOrFail($attempt->article_id);
        $this->sync($attempt, $revision, $this->hits($article, $revision), $this->unappliedRepairPatches($attempt));

        $input = $draftActivityId === null ? [] : ['draft_activity_id' => $draftActivityId];
        if ($article->isKit()) {
            $this->activities->start($actor, $attempt, EditorialActivityKind::ReviewKit, $input, 'review-kit-'.$attempt->review_cycle.'-'.$revision->id);

            return;
        }

        $batchKey = 'review-'.$attempt->review_cycle.'-'.$revision->id;
        foreach ([EditorialActivityKind::ReviewFacts, EditorialActivityKind::ReviewVoice, EditorialActivityKind::ReviewBuyer] as $kind) {
            $this->activities->start($actor, $attempt, $kind, $input, $batchKey);
        }
    }

    /**
     * The owner's way past a stuck repair: open repairs in this cycle go stale, and review starts on the current revision.
     */
    public function skipRepair(User $actor, PublishingAttempt $attempt): void
    {
        if (! $actor->can(PublishingPermission::Develop->value)) {
            throw new AuthorizationException('This user is not allowed to start publishing agent work.');
        }

        DB::transaction(function () use ($actor, $attempt): void {
            $locked = PublishingAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $article = Article::query()->whereKey($locked->article_id)->lockForUpdate()->firstOrFail();
            $revision = $article->workingRevision()->first();
            if ((int) ($article->current_attempt_id ?? 0) !== (int) $locked->id || ! $revision instanceof ArticleRevision) {
                throw new RuntimeException('Only the current publishing attempt can skip voice-rule repair.');
            }

            $repairs = EditorialActivity::query()
                ->where('attempt_id', $locked->id)
                ->where('review_cycle', (int) $locked->review_cycle)
                ->where('kind', EditorialActivityKind::LintRepair->value);
            if ((clone $repairs)->where('status', EditorialActivityStatus::Running->value)->exists()) {
                throw new RuntimeException('Voice-rule repair is running. Wait for it to finish before skipping it.');
            }

            $repairs->whereIn('status', [EditorialActivityStatus::Pending->value, EditorialActivityStatus::Paused->value, EditorialActivityStatus::Failed->value])
                ->update([
                    'status' => EditorialActivityStatus::Stale->value,
                    'completed_at' => now(),
                    'error_reason' => 'Skipped by the owner, who started review instead.',
                ]);

            $draftId = EditorialActivity::query()
                ->where('attempt_id', $locked->id)
                ->whereIn('kind', [EditorialActivityKind::Draft->value, EditorialActivityKind::DraftKit->value])
                ->where('status', EditorialActivityStatus::Completed->value)
                ->latest('id')
                ->value('id');

            $this->finishAndQueueReviews($actor, $locked, $revision, $draftId === null ? null : (int) $draftId);
        });
    }

    /**
     * Records that a voice rule does not apply to this hit. Release accepts the override for any later revision whose
     * hit has the same hash, so it survives unrelated edits but not a change to the flagged text.
     */
    public function override(User $actor, EditorialFinding $finding, string $reason): void
    {
        if (! $actor->can(PublishingPermission::Approve->value)) {
            throw new AuthorizationException('This user is not allowed to override voice rules.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Explain why this voice rule does not apply here.']);
        }

        if ($finding->lens !== self::LENS) {
            throw new RuntimeException('Only voice-rule findings can be overridden.');
        }

        $finding->forceFill([
            'disposition' => 'false_positive',
            'disposition_reason' => $reason,
            'disposition_actor_id' => $actor->id,
            'disposed_at' => now(),
        ])->save();
    }

    /**
     * Reasoned overrides recorded anywhere on the article, keyed by hit hash. Stale findings count: each save stales
     * the previous revision's findings, and their overrides must still clear an unchanged hit.
     *
     * @return array<string, array{reason: string, actor_id: int|null}>
     */
    public function overrides(int $articleId): array
    {
        $overrides = [];
        EditorialFinding::query()
            ->where('article_id', $articleId)
            ->where('lens', self::LENS)
            ->where('disposition', 'false_positive')
            ->whereNotNull('disposition_reason')
            ->orderBy('id')
            ->get(['input_hash', 'disposition_reason', 'disposition_actor_id'])
            ->each(function (EditorialFinding $finding) use (&$overrides): void {
                $reason = trim((string) $finding->disposition_reason);
                if ($reason !== '' && is_string($finding->input_hash)) {
                    $overrides[$finding->input_hash] = ['reason' => $reason, 'actor_id' => $finding->disposition_actor_id === null ? null : (int) $finding->disposition_actor_id];
                }
            });

        return $overrides;
    }

    /**
     * Patches a repair returned for blocks it was not allowed to change, latest first.
     *
     * @return array<string, array<string, mixed>>
     */
    private function unappliedRepairPatches(PublishingAttempt $attempt): array
    {
        $patches = [];
        EditorialActivity::query()
            ->where('attempt_id', $attempt->id)
            ->where('review_cycle', (int) $attempt->review_cycle)
            ->where('kind', EditorialActivityKind::LintRepair->value)
            ->where('status', EditorialActivityStatus::Completed->value)
            ->orderBy('id')
            ->get(['id', 'proposal'])
            ->each(function (EditorialActivity $activity) use (&$patches): void {
                $unapplied = data_get($activity->getAttribute('proposal'), 'unapplied_patches');
                foreach (is_array($unapplied) ? $unapplied : [] as $blockId => $patch) {
                    if (is_string($blockId) && is_array($patch)) {
                        $patches[$blockId] = $patch;
                    }
                }
            });

        return $patches;
    }

    /**
     * A patch still fits a block that is unchanged, or changed only in its own lock: the owner unlocks a protected
     * passage to accept its fix, and that save must not lose the fix. The patch is rebased onto the block as it is
     * now, so accepting it neither fails the expected hash nor locks the passage again.
     *
     * @param  array<string, mixed>|null  $patch
     * @param  array<string, mixed>|null  $node
     * @return array{block_id: string, expected_hash: string, replacement: array<mixed>}|null
     */
    private function currentPatch(?array $patch, LintHit $hit, ?array $node): ?array
    {
        if ($patch === null || $node === null || $hit->blockId === null) {
            return null;
        }

        $expectedHash = $patch['expected_hash'] ?? null;
        $replacement = $patch['replacement'] ?? null;
        if (($patch['block_id'] ?? null) !== $hit->blockId || ! is_string($expectedHash) || ! is_array($replacement) || ! $this->matchesIgnoringLock($expectedHash, $node)) {
            return null;
        }

        return ['block_id' => $hit->blockId, 'expected_hash' => $this->fingerprint->hash($node), 'replacement' => $this->withLockOf($replacement, $node)];
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function matchesIgnoringLock(string $expectedHash, array $node): bool
    {
        if (hash_equals($expectedHash, $this->fingerprint->hash($node))) {
            return true;
        }

        $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
        unset($attrs['protected']);
        foreach ([$attrs, [...$attrs, 'protected' => true], [...$attrs, 'protected' => false]] as $variant) {
            if (hash_equals($expectedHash, $this->fingerprint->hash([...$node, 'attrs' => $variant]))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<mixed>  $replacement
     * @param  array<string, mixed>  $node
     * @return array<mixed>
     */
    private function withLockOf(array $replacement, array $node): array
    {
        $nodeAttrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
        $attrs = is_array($replacement['attrs'] ?? null) ? $replacement['attrs'] : [];
        unset($attrs['protected']);
        if (array_key_exists('protected', $nodeAttrs)) {
            $attrs['protected'] = $nodeAttrs['protected'];
        }
        $replacement['attrs'] = $attrs;

        return $replacement;
    }

    /**
     * Keeps "Accept fix" available across saves that leave the flagged block untouched apart from its lock.
     *
     * @param  array<string, mixed>|null  $node
     * @return array{block_id: string, expected_hash: string, replacement: array<mixed>}|null
     */
    private function earlierPatch(PublishingAttempt $attempt, LintHit $hit, ?array $node): ?array
    {
        $earlier = EditorialFinding::query()
            ->where('attempt_id', $attempt->id)
            ->where('lens', self::LENS)
            ->where('input_hash', $hit->inputHash())
            ->whereNotNull('proposed_patch')
            ->latest('id')
            ->first();
        $patch = $earlier?->getAttribute('proposed_patch');

        return $this->currentPatch(is_array($patch) ? $patch : null, $hit, $node);
    }

    public function isArchiveImport(Article $article): bool
    {
        return ArticleRelease::query()->where('article_id', $article->id)->where('origin', 'import')->exists();
    }

    /** @return array<array-key, mixed> */
    private function document(ArticleRevision $revision): array
    {
        $document = $revision->getAttribute('document');

        return is_array($document) ? $document : [];
    }
}
