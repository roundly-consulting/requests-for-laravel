<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Tests;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use ReflectionClass;
use RoundlyConsulting\Approvals\ApprovalsServiceProvider;
use RoundlyConsulting\Requests\RequestsServiceProvider;

abstract class TestCase extends Orchestra
{
    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return [
            ApprovalsServiceProvider::class,
            RequestsServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');

        $this->loadApprovalsSchema();

        foreach (glob(__DIR__.'/../database/migrations/*.php') ?: [] as $file) {
            (require $file)->up();
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
        });
    }

    /**
     * Run the approvals engine migrations in dependency order (its tables back the
     * request approval flow now that the native engine is gone).
     */
    private function loadApprovalsSchema(): void
    {
        $base = dirname((string) (new ReflectionClass(ApprovalsServiceProvider::class))->getFileName(), 2);

        $migrations = [
            'create_approvals_table',
            'create_approval_requests_table',
            'add_v11_columns_to_approvals_table',
            'add_staging_to_approval_requests_table',
            'create_approval_request_stages_table',
            'create_approval_delegations_table',
        ];

        foreach ($migrations as $name) {
            $migration = require "{$base}/database/migrations/{$name}.php";

            if ($migration instanceof Migration) {
                $migration->up();
            }
        }
    }
}
