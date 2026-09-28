<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Actions;

use Illuminate\Support\Collection;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\Support\RequestModel;

/**
 * Expires every open request whose expiry deadline has passed, in id-ordered
 * chunks so each expired row safely leaves the scanned set. A dry run only counts
 * the due requests.
 */
final readonly class ExpireDueRequests
{
    public function __construct(private ExpireRequest $expire) {}

    /**
     * @return int the number of requests expired (or, on a dry run, due)
     */
    public function execute(bool $dryRun = false, int $chunk = 500): int
    {
        $count = 0;

        RequestModel::class()::query()
            ->expired()
            ->chunkById(max(1, $chunk), function (Collection $requests) use ($dryRun, &$count): void {
                foreach ($requests as $request) {
                    /** @var Request $request */
                    if (! $dryRun) {
                        $this->expire->execute($request);
                    }

                    $count++;
                }
            });

        return $count;
    }
}
