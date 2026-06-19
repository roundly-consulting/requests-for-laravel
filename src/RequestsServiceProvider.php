<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Requests\Commands\ExpireRequestsCommand;
use RoundlyConsulting\Requests\Facades\Requests;

final class RequestsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/requests.php', 'requests');

        $this->app->singleton(RequestManager::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'requests');

        $this->registerFacadeAlias();

        if ($this->app->runningInConsole()) {
            $this->commands([
                ExpireRequestsCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/requests.php' => config_path('requests.php'),
            ], 'requests-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'requests-migrations');

            $this->publishes([
                __DIR__.'/../resources/lang' => $this->app->langPath('vendor/requests'),
            ], 'requests-translations');
        }
    }

    private function registerFacadeAlias(): void
    {
        if (! config('requests.register_facade_alias', true)) {
            return;
        }

        $loader = AliasLoader::getInstance();

        // Collision-safe: only register if the host hasn't already aliased the name.
        if (array_key_exists('Requests', $loader->getAliases())) {
            return;
        }

        $loader->alias('Requests', Requests::class);
    }
}
