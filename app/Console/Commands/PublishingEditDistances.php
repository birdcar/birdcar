<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\ContentIdea;
use Illuminate\Console\Command;

class PublishingEditDistances extends Command
{
    protected $signature = 'publishing:edit-distances
        {idea : The id of the content idea}';

    protected $description = 'Print, as JSON, every piece of an idea with the word-level edit distances recorded between its agent-authored revisions and the owner\'s next save. edit_distance is the latest recorded distance, or null when none was recorded. Read-only.';

    public function handle(): int
    {
        $ideaId = $this->argument('idea');
        if (! ctype_digit($ideaId)) {
            $this->error('The idea must be a numeric id.');

            return self::FAILURE;
        }

        $idea = ContentIdea::query()->select(['id', 'summary_title'])->find((int) $ideaId);
        if (! $idea instanceof ContentIdea) {
            $this->error("No idea has the id {$ideaId}.");

            return self::FAILURE;
        }

        $pieces = Article::query()
            ->select(['id', 'kind', 'content_idea_id'])
            ->where('content_idea_id', $idea->id)
            ->orderBy('id')
            ->get();
        $recorded = ArticleRevision::query()
            ->select(['id', 'article_id', 'agent_baseline_revision_id', 'agent_edit_distance'])
            ->whereIn('article_id', $pieces->modelKeys())
            ->whereNotNull('agent_edit_distance')
            ->orderBy('id')
            ->get()
            ->groupBy('article_id');

        $report = [
            'idea_id' => $idea->id,
            'summary_title' => $idea->summary_title,
            'pieces' => $pieces->map(function (Article $piece) use ($recorded): array {
                $distances = $recorded->get($piece->id, collect())->map(fn (ArticleRevision $revision): array => [
                    'revision_id' => $revision->id,
                    'baseline_revision_id' => $revision->agent_baseline_revision_id,
                    'edit_distance' => $revision->agent_edit_distance,
                ])->values()->all();

                return [
                    'article_id' => $piece->id,
                    'kind' => $piece->kind->value,
                    'edit_distance' => $distances === [] ? null : $distances[array_key_last($distances)]['edit_distance'],
                    'distances' => $distances,
                ];
            })->values()->all(),
        ];

        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
