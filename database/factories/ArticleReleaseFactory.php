<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\ArticleRelease;
use App\Models\ArticleRevision;
use App\Models\PublishingAttempt;
use App\Services\Publishing\PublishingFingerprint;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * @extends Factory<ArticleRelease>
 */
class ArticleReleaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $document = [
            'version' => 1,
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'text' => fake()->sentence()],
            ],
        ];
        $metadata = [
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'tags' => [],
        ];
        $payload = $this->payloadFor($document, $metadata, fake()->slug());

        return [
            'article_id' => null,
            'attempt_id' => null,
            'revision_id' => null,
            'origin' => 'editorial',
            'payload' => $payload,
            'release_hash' => (new PublishingFingerprint)->hash($payload),
            'status' => 'prepared',
            'scheduled_at' => null,
            'published_by' => null,
            'published_at' => null,
            'withdrawn_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (ArticleRelease $release): void {
            $this->syncPayloadFromRelatedRecords($release);
        });
    }

    /**
     * @param  (callable(array<string, mixed>): array<string, mixed>)|array<string, mixed>  $attributes
     */
    public function create($attributes = [], ?Model $parent = null)
    {
        if ($attributes !== []) {
            return $this->state($attributes)->create([], $parent);
        }

        if ($this->count !== null) {
            $factory = $this->count(null);
            $releases = new EloquentCollection;

            foreach (range(1, (int) $this->count) as $_) {
                $releases->push($factory->create([], $parent));
            }

            return $releases;
        }

        /** @var ArticleRelease $release */
        $release = $this->make([], $parent);
        $this->populateMissingRelatedRecords($release);
        $this->syncPayloadFromRelatedRecords($release);
        $release->save();
        $this->callAfterCreating(new EloquentCollection([$release]), $parent);

        return $release;
    }

    public function imported(): static
    {
        return $this->state(fn (array $attributes): array => [
            'attempt_id' => null,
            'origin' => 'import',
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    private function populateMissingRelatedRecords(ArticleRelease $release): void
    {
        $articleId = $this->modelKey($release->getAttribute('article_id'));
        $revisionId = $this->modelKey($release->getAttribute('revision_id'));
        $attemptId = $this->modelKey($release->getAttribute('attempt_id'));
        $origin = $release->getAttribute('origin');

        $article = $articleId === null
            ? Article::factory()->createOne()
            : Article::query()->findOrFail($articleId);

        $revision = $revisionId === null
            ? ArticleRevision::factory()->createOne(['article_id' => $article->id])
            : ArticleRevision::query()->findOrFail($revisionId);

        if ((int) $revision->article_id !== (int) $article->id) {
            throw new RuntimeException('Release factory revisions must belong to the release article.');
        }

        $release->setAttribute('article_id', $article->id);
        $release->setAttribute('revision_id', $revision->id);

        if ($origin === 'editorial' && $attemptId === null) {
            $attempt = PublishingAttempt::factory()->createOne([
                'article_id' => $article->id,
                'input_version' => $revision->id,
            ]);

            $release->setAttribute('attempt_id', $attempt->id);
        }
    }

    private function syncPayloadFromRelatedRecords(ArticleRelease $release): void
    {
        $articleId = $this->modelKey($release->getAttribute('article_id'));
        $revisionId = $this->modelKey($release->getAttribute('revision_id'));

        if ($articleId === null || $revisionId === null) {
            $release->setAttribute('release_hash', (new PublishingFingerprint)->hash($this->payloadAttribute($release)));

            return;
        }

        $article = Article::query()->find($articleId);
        $revision = ArticleRevision::query()->find($revisionId);

        if ($article === null || $revision === null) {
            $release->setAttribute('release_hash', (new PublishingFingerprint)->hash($this->payloadAttribute($release)));

            return;
        }

        $payload = $this->payloadAttribute($release);
        $deliveryIntent = $payload['delivery_intent'] ?? [];
        $releasePayload = $this->payloadFor(
            $this->arrayAttribute($revision->getAttribute('document')),
            $this->arrayAttribute($revision->getAttribute('metadata')),
            $article->slug,
            is_array($deliveryIntent) ? $deliveryIntent : [],
            $this->scheduledAtIsoString($release),
        );

        $release->setAttribute('payload', $releasePayload);
        $release->setAttribute('release_hash', (new PublishingFingerprint)->hash($releasePayload));
    }

    private function modelKey(mixed $value): int|string|null
    {
        return is_int($value) || is_string($value) ? $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadAttribute(ArticleRelease $release): array
    {
        return $this->arrayAttribute($release->getAttribute('payload'));
    }

    /**
     * @return array<string, mixed>
     */
    private function arrayAttribute(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function scheduledAtIsoString(ArticleRelease $release): ?string
    {
        $scheduledAt = $release->getAttribute('scheduled_at');

        if ($scheduledAt instanceof CarbonInterface) {
            return $scheduledAt->toISOString();
        }

        return is_string($scheduledAt) ? $scheduledAt : null;
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $deliveryIntent
     * @return array<string, mixed>
     */
    private function payloadFor(array $document, array $metadata, string $slug, array $deliveryIntent = [], ?string $scheduledAt = null): array
    {
        return [
            'document' => $document,
            'metadata' => $metadata,
            'rendered_content_version' => 1,
            'canonical_slug' => $slug,
            'supporting_evidence_manifest' => [],
            'review_manifest' => [],
            'delivery_intent' => $deliveryIntent,
            'scheduled_at' => $scheduledAt,
        ];
    }
}
