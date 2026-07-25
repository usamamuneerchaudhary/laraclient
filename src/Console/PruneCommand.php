<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\LaraClient\Console;

use Illuminate\Console\Command;
use Usamamuneerchaudhary\LaraClient\Models\LaraClientLog;

/**
 * The v1 logs table grew without bound; on a busy app it becomes the largest
 * table in the database. Schedule this daily.
 *
 *     Schedule::command('laraclient:prune')->daily();
 */
class PruneCommand extends Command
{
    protected $signature = 'laraclient:prune
                            {--days=          : Override the retention window for successful calls}
                            {--failure-days=  : Override the retention window for failures}
                            {--chunk=1000     : Rows deleted per query}';

    protected $description = 'Delete old LaraClient log entries';

    public function handle(): int
    {
        $keepDays = (int) ($this->option('days') ?? config('lara_client.prune.keep_days', 14));
        $keepFailureDays = (int) ($this->option('failure-days') ?? config('lara_client.prune.keep_failures_days', 30));
        $chunk = max(100, (int) $this->option('chunk'));

        // Failures are kept longer than successes: they are the rows anyone
        // ever goes back to read.
        $successes = $this->pruneWhere(
            fn ($query) => $query->where('created_at', '<', now()->subDays($keepDays))
                ->where('status', '<', 400)
                ->whereNotNull('status'),
            $chunk,
        );

        $failures = $this->pruneWhere(
            fn ($query) => $query->where('created_at', '<', now()->subDays($keepFailureDays))
                ->where(fn ($q) => $q->where('status', '>=', 400)->orWhereNull('status')),
            $chunk,
        );

        $this->components->info(sprintf(
            'Pruned %s successful and %s failed log entries.',
            number_format($successes),
            number_format($failures),
        ));

        return self::SUCCESS;
    }

    protected function pruneWhere(callable $constraint, int $chunk): int
    {
        $total = 0;

        do {
            $query = LaraClientLog::query();
            $constraint($query);

            $ids = $query->orderBy('id')->limit($chunk)->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted = LaraClientLog::query()->whereIn('id', $ids)->delete();
            $total += $deleted;
        } while ($deleted > 0);

        return $total;
    }
}
