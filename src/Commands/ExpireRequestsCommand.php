<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Commands;

use Illuminate\Console\Command;
use RoundlyConsulting\Approvals\Facades\Approvals;
use RoundlyConsulting\Requests\Actions\ExpireRequest;
use RoundlyConsulting\Requests\Models\Request;

final class ExpireRequestsCommand extends Command
{
    protected $signature = 'requests:expire {--dry-run : Report what would expire without changing anything} {--chunk=500 : Number of requests processed per batch}';

    protected $description = 'Expire open requests whose expiry deadline has passed';

    public function handle(ExpireRequest $expire): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(1, (int) $this->option('chunk'));

        $count = 0;

        $this->resolveModel()::query()
            ->expired()
            ->chunkById($chunk, function ($requests) use ($expire, $dryRun, &$count): void {
                foreach ($requests as $request) {
                    if (! $dryRun) {
                        $expire->execute($request);
                    }

                    $count++;
                }
            });

        $this->info($dryRun
            ? "Would expire {$count} request(s)."
            : "Expired {$count} request(s).");

        if (! $dryRun) {
            // Lapse any pending approval decisions whose own expiry has passed.
            $lapsed = Approvals::expire();

            if ($lapsed > 0) {
                $this->info("Lapsed {$lapsed} expired approval decision(s).");
            }
        }

        return self::SUCCESS;
    }

    /** @return class-string<Request> */
    private function resolveModel(): string
    {
        /** @var class-string<Request> $model */
        $model = config('requests.model', Request::class);

        return $model;
    }
}
