<?php

declare(strict_types=1);

use Illuminate\Support\Arr;

dataset('translation files', ['messages']);

it('resolves exception messages from the package namespace', function (): void {
    $message = trans('requests::messages.invalid_status_transition', [
        'from' => 'Rejected',
        'to' => 'Approved',
    ]);

    expect($message)->toBe('Cannot transition a request from Rejected to Approved.');
});

/**
 * Every language ships the same keys and the same `:placeholders` as English.
 */
it('ships the same keys in every language', function (string $file): void {
    $en = Arr::dot(require __DIR__.'/../../resources/lang/en/'.$file.'.php');
    $sk = Arr::dot(require __DIR__.'/../../resources/lang/sk/'.$file.'.php');

    expect($en)->not->toBeEmpty()
        ->and(array_keys($sk))->toBe(array_keys($en));
})->with('translation files');

it('keeps every placeholder in every language', function (string $file): void {
    $en = Arr::dot(require __DIR__.'/../../resources/lang/en/'.$file.'.php');
    $sk = Arr::dot(require __DIR__.'/../../resources/lang/sk/'.$file.'.php');

    $placeholders = static function (mixed $line): array {
        preg_match_all('/:(\w+)/', (string) $line, $matches);
        $names = array_values(array_unique($matches[1]));
        sort($names);

        return $names;
    };

    foreach ($en as $key => $line) {
        expect($placeholders($sk[$key] ?? ''))->toBe($placeholders($line), "{$file}.{$key}");
    }
})->with('translation files');

it('loads slovak and english under the requests namespace', function (): void {
    $replace = ['from' => 'rejected', 'to' => 'approved'];

    expect(trans('requests::messages.invalid_status_transition', $replace))
        ->toBe('Cannot transition a request from rejected to approved.');

    app()->setLocale('sk');

    expect(trans('requests::messages.invalid_status_transition', $replace))
        ->toBe('Stav žiadosti nie je možné zmeniť z „rejected“ na „approved“.');
});
