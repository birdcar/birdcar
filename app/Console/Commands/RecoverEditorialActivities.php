<?php

namespace App\Console\Commands;

use App\Actions\Publishing\RunEditorialActivity;
use App\Models\EditorialActivity;
use App\Models\Publishing\EditorialActivityStatus;
use App\Settings\PublishingAgentSettings;
use Illuminate\Console\Command;

class RecoverEditorialActivities extends Command
{
    protected $signature = 'publishing:recover-activities {--limit=50}';

    protected $description = 'Release corrected configuration pauses, re-enqueue durable pending editorial agent activities and pause ambiguous old runs.';

    public function handle(PublishingAgentSettings $settings): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $count = 0;
        $paused = 0;
        $released = 0;

        if (! $settings->paused) {
            EditorialActivity::query()
                ->where('status', EditorialActivityStatus::Paused->value)
                ->whereIn('pause_reason', EditorialActivity::CONFIGURATION_PAUSE_REASONS)
                ->whereHas('attempt', function ($query): void {
                    $query->whereNull('paused_at')->whereNull('parked_at')->whereNull('abandoned_at');
                })
                ->orderBy('id')
                ->limit($limit)
                ->each(function (EditorialActivity $activity) use (&$released): void {
                    if (RunEditorialActivity::configurationProblem($activity) !== null) {
                        return;
                    }

                    $released += EditorialActivity::query()
                        ->whereKey($activity->id)
                        ->where('status', EditorialActivityStatus::Paused->value)
                        ->where('pause_reason', $activity->pause_reason)
                        ->update([
                            'status' => EditorialActivityStatus::Pending->value,
                            'paused_at' => null,
                            'pause_reason' => null,
                            'available_at' => now(),
                        ]);
                });

            EditorialActivity::query()
                ->where('status', EditorialActivityStatus::Pending->value)
                ->where(function ($query): void {
                    $query->whereNull('available_at')->orWhere('available_at', '<=', now());
                })
                ->orderBy('id')
                ->limit($limit)
                ->each(function (EditorialActivity $activity) use (&$count): void {
                    RunEditorialActivity::dispatch((int) $activity->id)->afterCommit();
                    $count++;
                });
        }

        EditorialActivity::query()
            ->where('status', EditorialActivityStatus::Running->value)
            ->where('started_at', '<', now()->subMinutes(30))
            ->orderBy('id')
            ->limit($limit)
            ->each(function (EditorialActivity $activity) use (&$paused): void {
                $paused += EditorialActivity::query()
                    ->whereKey($activity->id)
                    ->where('status', EditorialActivityStatus::Running->value)
                    ->update([
                        'status' => EditorialActivityStatus::Paused->value,
                        'paused_at' => now(),
                        'pause_reason' => 'Recovery found an interrupted run whose provider outcome is uncertain; review it before rerunning.',
                    ]);
            });

        $this->info($settings->paused
            ? 'Publishing agents are paused; no pending work was dispatched. Paused '.$paused.' ambiguous runs.'
            : 'Released '.$released.' configuration pauses; recovered '.$count.' pending editorial activities; paused '.$paused.' ambiguous runs.');

        return self::SUCCESS;
    }
}
