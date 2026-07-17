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

it('loads the package translations', function (): void {
    expect(__('requests::messages.request_already_resolved'))->not->toBe('requests::messages.request_already_resolved');
});

it('binds the manager as a singleton', function (): void {
    expect(app(RequestManager::class))->toBe(app(RequestManager::class));
});

it('registers the expire command', function (): void {
    expect(array_keys(app(Kernel::class)->all()))->toContain('requests:expire');
});
