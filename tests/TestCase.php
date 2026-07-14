<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Tests;

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

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
        });
    }

    /**
     * Neither package auto-loads its migrations (both publish them), so the suite
     * runs them itself: the approvals engine tables back the request approval flow.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom($this->approvalsMigrationsPath());
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * The approvals package's migrations directory, resolved from wherever composer
     * installed it.
     */
    private function approvalsMigrationsPath(): string
    {
        $base = dirname((string) (new ReflectionClass(ApprovalsServiceProvider::class))->getFileName(), 2);

        return $base.'/database/migrations';
    }
}
