<?php

declare(strict_types=1);

use Illuminate\Foundation\AliasLoader;
use RoundlyConsulting\Requests\Facades\Requests;
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
