<?php

namespace App\Services\Publishing;

use Laravel\Ai\Embeddings;
use RuntimeException;

/**
 * Embeds exemplar text through the dedicated OpenRouter entry. Every vector query receives these precomputed floats, so
 * nothing ever embeds a raw string through whatever provider happens to be the default.
 */
class ExemplarEmbeddings
{
    public const PROVIDER = 'openrouter-embeddings';

    public const MODEL = 'openai/text-embedding-3-small';

    /** Pinned by the exemplar_sections.embedding column; a model change needs a re-embed migration. */
    public const DIMENSIONS = 1536;

    /**
     * @param  list<string>  $texts  non-empty, with no blank string; the SDK refuses either before any request
     * @return list<list<float>> one vector per text, in order
     */
    public function embed(array $texts, int $timeout = 60): array
    {
        $key = config('ai.providers.'.self::PROVIDER.'.key');
        if (! is_string($key) || trim($key) === '') {
            throw new RuntimeException('The '.self::PROVIDER.' provider has no API key.');
        }

        $response = Embeddings::for($texts)
            ->dimensions(self::DIMENSIONS)
            ->timeout($timeout)
            ->generate(provider: self::PROVIDER, model: self::MODEL);

        if (count($response->embeddings) !== count($texts)) {
            throw new RuntimeException('The embeddings provider returned '.count($response->embeddings).' vectors for '.count($texts).' inputs.');
        }

        $vectors = [];
        foreach ($response->embeddings as $vector) {
            if (count($vector) !== self::DIMENSIONS) {
                throw new RuntimeException('The embeddings provider returned a vector without exactly '.self::DIMENSIONS.' dimensions.');
            }

            $vectors[] = array_map(floatval(...), array_values($vector));
        }

        return $vectors;
    }
}
