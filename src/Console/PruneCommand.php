<?php

namespace SuperAdmin\Admin\Reporter\Console;

use Illuminate\Console\Command;
use SuperAdmin\Admin\Reporter\ExceptionModel;
use SuperAdmin\Admin\Reporter\Reporter;

class PruneCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'reporter:prune {--days= : Delete exceptions older than this many days (default: admin.extensions.reporter.retention_days, or 30)}';

    /**
     * @var string
     */
    protected $description = 'Delete old exceptions stored by the Super Admin reporter';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: Reporter::config('retention_days', 30));

        if ($days < 1) {
            $this->error('--days must be 1 or more.');

            return 1;
        }

        $deleted = ExceptionModel::pruneOlderThan($days);

        $this->info("Deleted {$deleted} exception(s) older than {$days} day(s).");

        return 0;
    }
}
