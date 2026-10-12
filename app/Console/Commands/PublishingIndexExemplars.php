<?php

namespace App\Console\Commands;

use App\Actions\Publishing\IndexExemplarSections;
use App\Models\Article;
use App\Models\ExemplarSection;
use App\Models\Publishing\PieceKind;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class PublishingIndexExemplars extends Command
{
    protected $signature = 'publishing:index-exemplars
        {--article= : Index only the article with this slug}';

    protected $description = 'Index published blog posts into exemplar sections and embed every section that has no vector yet. Paid: it calls the OpenRouter embeddings model. Owner-run only; never scheduled. Changing the embeddings model or its dimensions needs a migration that re-embeds every section.';

    public function handle(IndexExemplarSections $index): int
    {
        $slug = $this->option('article');
        $articles = Article::query()
            ->select(['id', 'slug'])
            ->when(
                is_string($slug) && $slug !== '',
                fn (Builder $query) => $query->where('slug', $slug),
                // Every eligible post, plus any post that still has rows but is no longer eligible, so they get pruned.
                fn (Builder $query) => $query
                    ->where(fn (Builder $query) => $query->where('kind', PieceKind::BlogPost->value)->whereNotNull('published_release_id'))
                    ->orWhereIn('id', ExemplarSection::query()->select('article_id')),
            )
            ->orderBy('id')
            ->get();

        if (is_string($slug) && $slug !== '' && $articles->isEmpty()) {
            $this->error("No article has the slug {$slug}.");

            return self::FAILURE;
        }

        $totals = ['created' => 0, 'pruned' => 0, 'embedded' => 0];
        $failures = 0;

        foreach ($articles as $article) {
            $counts = ['created' => 0, 'pruned' => 0, 'embedded' => 0];

            try {
                $counts = [...$index->sync($article), 'embedded' => 0];
                $counts['embedded'] = $index->embedMissing($article);
                $this->line("{$article->slug}: {$counts['created']} created, {$counts['pruned']} pruned, {$counts['embedded']} embedded");
            } catch (Throwable $exception) {
                $failures++;
                $this->error("{$article->slug}: {$counts['created']} created, {$counts['pruned']} pruned, failed: {$exception->getMessage()}");
            }

            foreach ($totals as $key => $total) {
                $totals[$key] = $total + $counts[$key];
            }
        }

        $this->info("Total: {$totals['created']} created, {$totals['pruned']} pruned, {$totals['embedded']} embedded.");

        if ($failures > 0) {
            $this->error("{$failures} article(s) failed. Rerun the command to embed only the sections still missing a vector.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
