<?php

declare(strict_types=1);

use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\Carbon;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Requests\Enums\Status;
use RoundlyConsulting\Requests\Exceptions\InvalidStatusTransition;
use RoundlyConsulting\Requests\Facades\Requests;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\RequestsServiceProvider;
use RoundlyConsulting\Requests\Tests\User;

/**
 * A host wiring these keys to `env()` hands the package strings. `default_ttl` used to be
 * read with `is_int()`, so `'60'` silently meant "never expire"; the booleans were cast
 * with `(bool)`, so `'false'` switched the transition guard ON.
 */
it('stamps the expiry from a numeric-string default ttl', function (): void {
    config()->set('requests.default_ttl', '60');

    Carbon::setTestNow('2026-06-19 12:00:00');

    $request = Requests::make()->create();

    expect($request->expires_at?->equalTo(now()->addMinutes(60)))->toBeTrue();

    Carbon::setTestNow();
});

it('treats an unset default ttl as no expiry', function (?string $ttl): void {
    config()->set('requests.default_ttl', $ttl);

    expect(Requests::make()->create()->expires_at)->toBeNull();
})->with(['absent' => null, 'blank' => '', 'whitespace' => '  ']);

it('refuses a default ttl that is not a positive whole number of minutes', function (mixed $ttl): void {
    config()->set('requests.default_ttl', $ttl);

    Requests::make()->create();
})->with(['abc', '1.5', 0, '-5'])->throws(InvalidConfigurationException::class);

it('reads a string "false" transition guard as off', function (): void {
    config()->set('requests.enforce_transitions', 'false');

    $request = Request::factory()->rejected()->create();

    Requests::approve($request, User::create());

    expect($request->fresh()?->status)->toBe(Status::Approved);
});

it('reads a string "true" transition guard as on', function (): void {
    config()->set('requests.enforce_transitions', 'true');

    expect(fn () => Requests::expire(Request::factory()->approved()->create()))
        ->toThrow(InvalidStatusTransition::class);
});

it('reads a blank transition guard as not set, so off (strict config)', function (string $blank): void {
    config()->set('requests.enforce_transitions', $blank);

    $request = Request::factory()->rejected()->create();

    Requests::approve($request, User::create());

    expect($request->fresh()?->status)->toBe(Status::Approved);
})->with(['blank' => '', 'whitespace' => ' ']);

it('refuses a mistyped transition guard instead of reading it as off (strict config)', function (): void {
    config()->set('requests.enforce_transitions', 'enforced');

    expect(fn () => Requests::expire(Request::factory()->approved()->create()))
        ->toThrow(InvalidConfigurationException::class, 'Configuration value [requests.enforce_transitions] must be a boolean');
});

it('refuses to migrate on an unrecognized key type (strict config)', function (): void {
    config()->set('requests.key_type', 'nonsense');

    expect(function (): void {
        $migration = require __DIR__.'/../../database/migrations/create_requests_table.php';
        $migration->up();
    })->toThrow(InvalidConfigurationException::class, 'Configuration value [requests.key_type] must be one of [bigint, uuid, ulid] (case-insensitive), [nonsense] given.');
});

it('reports env-string config in the about section', function (): void {
    config()->set('requests.enforce_transitions', 'on');
    config()->set('requests.default_ttl', '90');
    config()->set('requests.register_facade_alias', 'false');

    expect('requests')->toLeakNoSecrets(
        secrets: [],
        mustRender: ['ENFORCED', '90 min', 'DISABLED'],
    );
});

it('skips the facade alias for a string "false"', function (): void {
    config()->set('requests.register_facade_alias', 'false');

    $loader = AliasLoader::getInstance();
    $loader->setAliases(array_diff_key($loader->getAliases(), ['Requests' => true]));

    $provider = new RequestsServiceProvider(app());
    (new ReflectionMethod($provider, 'registerFacadeAlias'))->invoke($provider);

    expect($loader->getAliases())->not->toHaveKey('Requests');
});

it('registers the facade alias when the switch is blank (strict config)', function (string $blank): void {
    config()->set('requests.register_facade_alias', $blank);

    $loader = AliasLoader::getInstance();
    $loader->setAliases(array_diff_key($loader->getAliases(), ['Requests' => true]));

    $provider = new RequestsServiceProvider(app());
    (new ReflectionMethod($provider, 'registerFacadeAlias'))->invoke($provider);

    expect($loader->getAliases())->toHaveKey('Requests');
})->with(['blank' => '', 'whitespace' => ' ']);
