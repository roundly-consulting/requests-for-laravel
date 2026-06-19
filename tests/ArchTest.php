<?php

declare(strict_types=1);

use RoundlyConsulting\Requests\Exceptions\RequestException;

it('will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();

it('extends the base package exception')
    ->expect('RoundlyConsulting\Requests\Exceptions')
    ->classes()
    ->toExtend(RequestException::class)
    ->ignoring(RequestException::class);

it('keeps events final')
    ->expect('RoundlyConsulting\Requests\Events')
    ->toBeFinal();

it('keeps actions final with a single execute method')
    ->expect('RoundlyConsulting\Requests\Actions')
    ->toBeFinal()
    ->toHaveMethod('execute');
