<?php

namespace App\Actions\Publishing;

use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\EditorialActivity;
use App\Models\Publishing\EditorialActivityKind;
use App\Models\Publishing\EditorialActivityStatus;
use App\Models\User;
use App\Services\Publishing\EditorialOutput;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * The owner accepts a package, as proposed or edited, as a metadata-only revision. The document is saved unchanged, so
 * the reviewed lineage carries over; the values are linted at release like any other metadata.
 */
class AcceptPackageProposal
{
    /** The same limits the article details form applies. */
    public const LIMITS = ['title' => 240, 'seo_title' => 240, 'description' => 1000, 'slug' => 240, 'greatest_hits_tag' => EditorialOutput::GREATEST_HITS_TAG_LIMIT];

    public function __construct(private readonly WriteArticle $writer, private readonly LintRevision $lint) {}

    /**
     * @param  array{title: string, seo_title: string, description: string, slug: string, greatest_hits_tag: string}  $metadata
     */
    public function accept(User $actor, EditorialActivity $package, array $metadata, int $expectedRevisionId): ArticleRevision
    {
        if (! $actor->can(PublishingPermission::Approve->value)) {
            throw new AuthorizationException('This user is not allowed to accept a package.');
        }

        $values = $this->values($metadata);

        /** @var ArticleRevision $revision */
        $revision = DB::transaction(function () use ($actor, $package, $values, $expectedRevisionId): ArticleRevision {
            $locked = EditorialActivity::query()->whereKey($package->id)->lockForUpdate()->firstOrFail();
            if ($locked->kind !== EditorialActivityKind::Package || $locked->status !== EditorialActivityStatus::Completed) {
                throw new RuntimeException('Only a completed package can be accepted.');
            }

            $article = Article::query()->whereKey($locked->article_id)->lockForUpdate()->firstOrFail();
            if ((int) ($article->current_attempt_id ?? 0) !== (int) $locked->attempt_id) {
                throw new RuntimeException('The package belongs to an earlier publishing attempt.');
            }

            $mutationId = 'package-'.$locked->id.'-'.substr(hash('sha256', json_encode($values, JSON_THROW_ON_ERROR)), 0, 24);
            $accepted = ArticleRevision::query()->where('article_id', $article->id)->where('client_mutation_id', $mutationId)->first();
            if ($accepted instanceof ArticleRevision) {
                return $accepted;
            }

            $newerRecheck = EditorialActivity::query()
                ->where('attempt_id', $locked->attempt_id)
                ->where('review_cycle', (int) $locked->review_cycle)
                ->where('kind', EditorialActivityKind::Recheck->value)
                ->where('id', '>', $locked->id)
                ->exists();
            if ($newerRecheck) {
                throw new RuntimeException('A recheck started after this package; accept the package that follows the recheck.');
            }

            if ((int) ($article->working_revision_id ?? 0) !== $expectedRevisionId) {
                throw new RuntimeException('The article revision changed before the package was accepted.');
            }

            $base = ArticleRevision::query()->whereKey($expectedRevisionId)->firstOrFail();
            $document = $base->getAttribute('document');
            $current = $base->getAttribute('metadata');

            return $this->writer->save(
                $actor,
                $article,
                $expectedRevisionId,
                is_array($document) ? $document : [],
                array_replace(is_array($current) ? $current : [], $values),
                $mutationId,
                'agent-accepted',
            );
        });

        try {
            $this->lint->refresh($revision->article()->firstOrFail());
        } catch (Throwable $exception) {
            report($exception);
        }

        return $revision;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{title: string, seo_title: string, description: string, slug: string, greatest_hits_tag: string}
     */
    private function values(array $metadata): array
    {
        $values = [];
        foreach (self::LIMITS as $field => $limit) {
            $value = $metadata[$field] ?? null;
            if (! is_string($value) || trim($value) === '') {
                throw new InvalidArgumentException('The package needs a '.str_replace('_', ' ', $field).'.');
            }

            $value = trim($value);
            if (mb_strlen($value) > $limit) {
                throw new InvalidArgumentException('The package '.str_replace('_', ' ', $field).' must be at most '.$limit.' characters.');
            }
            $values[$field] = $value;
        }

        return [
            'title' => $values['title'],
            'seo_title' => $values['seo_title'],
            'description' => $values['description'],
            'slug' => $values['slug'],
            'greatest_hits_tag' => $values['greatest_hits_tag'],
        ];
    }
}
