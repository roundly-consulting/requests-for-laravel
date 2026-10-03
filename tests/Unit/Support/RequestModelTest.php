<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\Support\RequestModel;
use RoundlyConsulting\Requests\Tests\User;

it('resolves the packaged model by default', function (): void {
    expect(RequestModel::class())->toBe(Request::class);
});

it('resolves a configured request subclass', function (): void {
    $custom = new class extends Request
    {
        protected $table = 'requests';
    };

    config()->set('requests.model', $custom::class);

    expect(RequestModel::class())->toBe($custom::class);
});

it('refuses a foreign model instead of falling back to the packaged one', function (): void {
    // The toolkit refuses any class that is not the packaged model or a subclass of it.
    config()->set('requests.model', User::class);

    expect(fn (): string => RequestModel::class())->toThrow(
        InvalidConfigurationException::class,
        'Configuration value [requests.model] must be a class-string of ['.Request::class.'], ['.User::class.'] given.',
    );
});

it('throws when the configured model is not an eloquent model', function (): void {
    config()->set('requests.model', 'NotAModel');

    RequestModel::class();
})->throws(InvalidConfigurationException::class);
