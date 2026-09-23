<?php

namespace App\Console\Commands;

use App\Actions\Publishing\ManageArticleRelease;
use App\Models\ArticleRelease;
use App\Models\EditorialApproval;
use App\Models\Publishing\ApprovalKind;
use App\Models\User;
use Illuminate\Console\Command;
use Throwable;

class PublishDueArticles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'publishing:publish-due {--limit=25 : Maximum due releases to inspect in one run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish approved article releases whose scheduled time is due';

    public function handle(ManageArticleRelease $releases): int
    {
        $limit = max(1, min(100, (int) $this->option('limit')));
        $published = 0;
        $failed = 0;

        ArticleRelease::query()
            ->where('status', 'scheduled')
            ->whereNull('published_at')
            ->whereNull('withdrawn_at')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->oldest('scheduled_at')
            ->limit($limit)
            ->get()
            ->each(function (ArticleRelease $release) use ($releases, &$published, &$failed): void {
                try {
                    $actor = $this->storedApprovalActor($release);
                    $payloadValue = $release->getAttribute('payload');
                    $payload = is_array($payloadValue) ? $payloadValue : [];
                    $expectedPrevious = $payload['expected_previous_live_release_id'] ?? null;
                    $releases->deliver($actor, $release, is_numeric($expectedPrevious) ? (int) $expectedPrevious : null);
                    $published++;
                } catch (Throwable $exception) {
                    $failed++;
                    $this->warn('Release #'.$release->id.' was not published: '.$exception->getMessage());
                }
            });

        $this->info('Published '.$published.' due release(s).');

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function storedApprovalActor(ArticleRelease $release): User
    {
        $approval = EditorialApproval::query()
            ->where('release_id', $release->id)
            ->where('kind', ApprovalKind::Release->value)
            ->where('input_hash', $release->release_hash)
            ->whereNull('invalidated_at')
            ->latest('approved_at')
            ->first();

        if (! $approval instanceof EditorialApproval || $approval->user_id === null) {
            throw new \RuntimeException('The scheduled release has no stored approving actor.');
        }

        return User::query()->findOrFail($approval->user_id);
    }
}
