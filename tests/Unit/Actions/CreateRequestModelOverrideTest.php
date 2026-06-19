<?php

declare(strict_types=1);

use RoundlyConsulting\Requests\Actions\CreateRequest;
use RoundlyConsulting\Requests\DataTransferObjects\CreateRequestDto;
use RoundlyConsulting\Requests\Models\Request;

it('honours a custom request model from config', function (): void {
    $custom = new class extends Request
    {
        protected $table = 'requests';
    };

    config()->set('requests.model', $custom::class);

    $request = (new CreateRequest)->execute(new CreateRequestDto);

    expect($request)->toBeInstanceOf($custom::class);
});
