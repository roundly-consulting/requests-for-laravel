<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Tests;

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Approvals\ApprovalsServiceProvider;
use RoundlyConsulting\Requests\RequestsServiceProvider;
use RoundlyConsulting\Testing\PackageTestCase;

abstract class TestCase extends PackageTestCase
{
    /**
     * Every provider the suite really needs, in registration order. Approvals is a hard
     * `require` a host would auto-discover, and the request lifecycle genuinely runs on
     * it (`SyncRequestStatusFromApproval` listens for `ApprovalRequestResolved`), so
     * naming it is what makes the test env an install rather than a fiction.
     *
     * `enums-for-laravel` and `package-toolkit-for-laravel` are hard `require`s too, but
     * the first ships no provider and the second is a base class rather than a registered
     * package — so the list is genuinely two entries.
     *
     * @return list<class-string<ServiceProvider>>
     */
    protected function packageProviders(): array
    {
        return [
            ApprovalsServiceProvider::class,
            RequestsServiceProvider::class,
        ];
    }

    /**
     * The migrations, named by **provider class** — never by filename.
     *
     * This replaces a hand-rolled `defineDatabaseMigrations()` that reflected on
     * ApprovalsServiceProvider to find its package root and then guessed
     * `/database/migrations` beneath it: exactly what the base case's
     * `LoadsProviderMigrations` concern does once, correctly, for the whole fleet.
     *
     * The `users` fixture table used to be built by `Schema::create()` inside
     * `getEnvironmentSetUp()` — i.e. during *environment* configuration, before the
     * migrator ever ran, and outside anything that resets it. On in-memory SQLite that
     * was invisible (the database dies with the connection); on a real engine the table
     * survives teardown and the next test dies creating it again. It is a fixture
     * migration now, so the base case's drop-and-remigrate reset owns it like any other.
     *
     * @return list<class-string<ServiceProvider>|string>
     */
    protected function migrationSources(): array
    {
        return [
            ApprovalsServiceProvider::class,
            RequestsServiceProvider::class,
            __DIR__.'/database/migrations',
        ];
    }
}
