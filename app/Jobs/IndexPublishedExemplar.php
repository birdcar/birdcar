<?php

namespace App\Jobs;

use App\Actions\Publishing\IndexExemplarSections;
use App\Models\Article;
use App\Settings\PublishingAgentSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Re-indexes one blog post after it is published. While publishing agents are paused the sections are synced but not
 * embedded, since the pause stops automatic paid calls; `publishing:index-exemplars` embeds them later. Indexing
 * never fails publishing: a provider error is reported and the rows wait for the next run.
 */
class IndexPublishedExemplar implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public int $articleId) {}

    public function handle(IndexExemplarSections $index, PublishingAgentSettings $settings): void
    {
        $article = Article::query()->select(['id'])->find($this->articleId);
        if (! $article instanceof Article) {
            return;
        }

        try {
            $settings->paused ? $index->sync($article) : $index->index($article);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
