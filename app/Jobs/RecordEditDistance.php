<?php

namespace App\Jobs;

use App\Models\ArticleRevision;
use App\Services\Publishing\EditDistance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Records how many words the owner changed in their save straight after an agent-authored revision, on that owner
 * revision only, even when later revisions exist. It is the only writer of the two columns and writes them once: a
 * missing revision, a parent an agent did not write, or a distance already recorded leaves everything as it is. A
 * measurement never fails a save, so an unexpected error is reported and the revision stays unmeasured.
 */
class RecordEditDistance implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public int $revisionId) {}

    public function handle(EditDistance $editDistance): void
    {
        try {
            $revision = ArticleRevision::query()
                ->select(['id', 'parent_revision_id', 'origin', 'document', 'agent_edit_distance'])
                ->find($this->revisionId);
            if (! $revision instanceof ArticleRevision || $revision->origin !== 'human' || $revision->parent_revision_id === null || $revision->agent_edit_distance !== null) {
                return;
            }

            $baseline = ArticleRevision::query()->select(['id', 'origin', 'document'])->find($revision->parent_revision_id);
            if (! $baseline instanceof ArticleRevision || ! in_array($baseline->origin, ArticleRevision::AGENT_ORIGINS, true)) {
                return;
            }

            $distance = $editDistance->between($baseline->document, $revision->document);

            // The null check makes a second run of the same job a no-op, even when both read the column before either wrote.
            ArticleRevision::query()
                ->whereKey($revision->id)
                ->whereNull('agent_edit_distance')
                ->update(['agent_edit_distance' => $distance, 'agent_baseline_revision_id' => $baseline->id]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
