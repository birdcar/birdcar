<?php

namespace App\Console\Commands;

use App\Actions\Publishing\ImportWritingArchive;
use App\Models\User;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class PublishingImportArchive extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'publishing:import-archive
        {--dry-run : Parse and report without writing records}
        {--write : Create missing archive records after preflight}
        {--source=resources/writing : Source writing archive directory}
        {--baseline=72f7d8ad8521573cb224022c902447f9ca4c4351 : Baseline commit for provenance}
        {--actor= : Existing operator user id for writes}
        {--confirm-production-write : Required with --write in production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import the legacy writing archive into publishing records';

    public function handle(ImportWritingArchive $import): int
    {
        $write = (bool) $this->option('write');

        if ($write && (bool) $this->option('dry-run')) {
            $this->error('Choose either --dry-run or --write, not both.');

            return Command::FAILURE;
        }

        $source = (string) $this->option('source');
        $baseline = (string) $this->option('baseline');

        try {
            $report = $write
                ? $this->write($import, $source, $baseline)
                : $import->dryRun($source, $baseline);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return Command::FAILURE;
        }

        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $this->info($write ? 'Archive import completed.' : 'Archive import dry run completed without database writes.');

        return Command::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function write(ImportWritingArchive $import, string $source, string $baseline): array
    {
        if (app()->environment('production') && ! (bool) $this->option('confirm-production-write')) {
            throw new RuntimeException('Production writes require --confirm-production-write.');
        }

        $actorId = $this->option('actor');
        if (! (is_string($actorId) && ctype_digit($actorId))) {
            throw new RuntimeException('Writes require --actor with an existing user id.');
        }

        $actor = User::query()->find((int) $actorId);
        if (! $actor instanceof User) {
            throw new RuntimeException('The requested import actor does not exist.');
        }

        return $import->write($source, $baseline, $actor);
    }
}
