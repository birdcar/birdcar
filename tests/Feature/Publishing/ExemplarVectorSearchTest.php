<?php

use App\Models\ExemplarSection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A unit vector along one axis, scaled by the given weights on further axes. Never all zeros: cosine distance to a
 * zero vector is NaN.
 *
 * @param  array<int, float>  $weights  axis => weight
 * @return list<float>
 */
function exemplarVectorAlong(array $weights): array
{
    $vector = array_fill(0, 1536, 0.0);
    foreach ($weights as $axis => $weight) {
        $vector[$axis] = $weight;
    }

    return array_values($vector);
}

test('the embedding column is a pgvector column with an HNSW cosine index', function () {
    $index = (array) DB::selectOne("select indexname, indexdef from pg_indexes where tablename = 'exemplar_sections' and indexdef ilike '%using hnsw%'");

    expect(Schema::getColumnType('exemplar_sections', 'embedding'))->toBe('vector')
        ->and($index['indexname'] ?? null)->toBe('exemplar_sections_embedding_hnsw')
        ->and($index['indexdef'] ?? null)->toContain('vector_cosine_ops');
});

test('the nearest section comes first and an orthogonal section falls below the similarity cut', function () {
    $nearest = ExemplarSection::factory()->embedded(exemplarVectorAlong([0 => 1.0]))->create();
    $partial = ExemplarSection::factory()->embedded(exemplarVectorAlong([1 => 1.0]))->create();
    ExemplarSection::factory()->embedded(exemplarVectorAlong([2 => 1.0]))->create();
    $query = exemplarVectorAlong([0 => 0.9, 1 => 0.4]);

    $matches = ExemplarSection::query()
        ->select(['id'])
        ->selectVectorDistance('embedding', $query, as: 'distance')
        ->whereVectorSimilarTo('embedding', $query, minSimilarity: 0.2)
        ->get();

    expect($matches->pluck('id')->all())->toBe([$nearest->id, $partial->id])
        ->and(1 - (float) $matches->first()?->getAttribute('distance'))->toEqualWithDelta(0.9 / sqrt(0.97), 0.0001)
        ->and($nearest->fresh()?->embedding)->toEqual(exemplarVectorAlong([0 => 1.0]));
});
