<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Approvals\Events\ApprovalRequestResolved;
use RoundlyConsulting\PackageToolkit\Concerns\RegistersBlueprintMacros;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;
use RoundlyConsulting\Requests\Commands\ExpireRequestsCommand;
use RoundlyConsulting\Requests\Facades\Requests;
use RoundlyConsulting\Requests\Listeners\SyncRequestStatusFromApproval;
use RoundlyConsulting\Requests\Support\RequestModel;

final class RequestsServiceProvider extends PackageServiceProvider
{
    use RegistersBlueprintMacros;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('requests')
            ->hasConfigFile()
            ->hasMigrations()
            ->hasTranslations()
            ->hasCommands([
                ExpireRequestsCommand::class,
            ])
            ->contributesToAbout(static fn (): array => [
                'Model' => class_basename(RequestModel::class()),
                'Transition guard' => config('requests.enforce_transitions', false) === true ? 'ENFORCED' : 'OFF',
                'Default TTL' => self::defaultTtl(),
                'Facade alias' => self::facadeAlias(),
            ]);
    }

    public function register(): void
    {
        parent::register();

        $this->app->singleton(RequestManager::class);
    }

    public function boot(): void
    {
        parent::boot();

        // The migrations' key-type-aware morph columns are macros, so they must
        // exist before a host runs `php artisan migrate`.
        $this->registerBlueprintMacros();

        $this->registerFacadeAlias();

        Event::listen(ApprovalRequestResolved::class, SyncRequestStatusFromApproval::class);
    }

    /**
     * Kept hand-wired rather than declared with the toolkit's `hasFacadeAlias()`:
     * the toolkit registers the alias unconditionally, while this package's config
     * documents that an alias the host already took is left alone.
     */
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

    private static function defaultTtl(): string
    {
        $ttl = config('requests.default_ttl');

        return is_int($ttl) ? $ttl.' min' : 'NONE';
    }

    private static function facadeAlias(): string
    {
        return config('requests.register_facade_alias', true) === false ? 'DISABLED' : 'Requests';
    }
}
