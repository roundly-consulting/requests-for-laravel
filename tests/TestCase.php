<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use RoundlyConsulting\Requests\RequestsServiceProvider;

abstract class TestCase extends Orchestra
{
    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return [
            RequestsServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');

        foreach (glob(__DIR__.'/../database/migrations/*.php') ?: [] as $file) {
            (require $file)->up();
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
        });
    }
}
