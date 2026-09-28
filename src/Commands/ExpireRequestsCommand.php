<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Commands;

use Illuminate\Console\Command;
use RoundlyConsulting\Approvals\ApprovalsManager;
use RoundlyConsulting\Requests\RequestManager;

final class ExpireRequestsCommand extends Command
{
    protected $signature = 'requests:expire {--dry-run : Report what would expire without changing anything} {--chunk=500 : Number of requests processed per batch}';

    protected $description = 'Expire open requests whose expiry deadline has passed';

    public function handle(RequestManager $requests, ApprovalsManager $approvals): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $count = $requests->expireDue($dryRun, (int) $this->option('chunk'));

        $this->info($dryRun
            ? "Would expire {$count} request(s)."
            : "Expired {$count} request(s).");

        if (! $dryRun) {
            // Lapse any pending approval decisions whose own expiry has passed.
            $lapsed = $approvals->expire();

            if ($lapsed > 0) {
                $this->info("Lapsed {$lapsed} expired approval decision(s).");
            }
        }

        return self::SUCCESS;
    }
}
