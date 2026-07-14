<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Requests\Facades\Requests;
use RoundlyConsulting\Requests\RequestManager;
use RoundlyConsulting\Requests\RequestsServiceProvider;

function invokeAliasRegistration(): void
{
    $provider = new RequestsServiceProvider(app());

    $method = new ReflectionMethod($provider, 'registerFacadeAlias');
    $method->invoke($provider);
}

it('skips alias registration when disabled', function (): void {
    config()->set('requests.register_facade_alias', false);

    invokeAliasRegistration();
})->throwsNoExceptions();

it('skips alias registration when the name is already taken', function (): void {
    config()->set('requests.register_facade_alias', true);

    AliasLoader::getInstance()->alias('Requests', Requests::class);

    invokeAliasRegistration();
})->throwsNoExceptions();

it('registers every publish tag', function (string $tag): void {
    expect(ServiceProvider::pathsToPublish(RequestsServiceProvider::class, $tag))->not->toBeEmpty();
})->with([
    'requests-config',
    'requests-migrations',
    'requests-translations',
]);

it('publishes its migration timestamp-injected into the host', function (): void {
    $paths = ServiceProvider::pathsToPublish(RequestsServiceProvider::class, 'requests-migrations');

    expect($paths)->toHaveCount(1);

    $source = (string) array_key_first($paths);
    $target = (string) reset($paths);

    expect(basename($source))->toBe('create_requests_table.php')
        ->and($target)->toStartWith(database_path('migrations'))
        ->and(basename($target))->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_create_requests_table\.php$/');
});

it('never auto-loads its migrations — the host must publish them', function (): void {
    $registered = array_map(
        static fn (string $path): string => realpath($path) ?: $path,
        app('migrator')->paths(),
    );

    expect($registered)->not->toContain(realpath(__DIR__.'/../../database/migrations'));
});

it('loads the package translations', function (): void {
    expect(__('requests::messages.request_already_resolved'))->not->toBe('requests::messages.request_already_resolved');
});

it('binds the manager as a singleton', function (): void {
    expect(app(RequestManager::class))->toBe(app(RequestManager::class));
});

it('registers the expire command', function (): void {
    expect(array_keys(app(Kernel::class)->all()))->toContain('requests:expire');
});

it('contributes a requests section to about', function (string $expected): void {
    $this->artisan('about --only=requests')
        ->expectsOutputToContain($expected)
        ->assertExitCode(0);
})->with([
    'Requests',
    'Model',
    'Transition guard',
    'Default TTL',
    'Facade alias',
]);

it('reports the configured ttl in about', function (): void {
    config()->set('requests.default_ttl', 90);

    $this->artisan('about --only=requests')
        ->expectsOutputToContain('90 min')
        ->assertExitCode(0);
});

it('reports the transition guard as enforced in about', function (): void {
    config()->set('requests.enforce_transitions', true);

    $this->artisan('about --only=requests')
        ->expectsOutputToContain('ENFORCED')
        ->assertExitCode(0);
});

it('reports a disabled facade alias in about', function (): void {
    config()->set('requests.register_facade_alias', false);

    $this->artisan('about --only=requests')
        ->expectsOutputToContain('DISABLED')
        ->assertExitCode(0);
});
