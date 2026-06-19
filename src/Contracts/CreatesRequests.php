<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Contracts;

use RoundlyConsulting\Requests\DataTransferObjects\CreateRequestDto;
use RoundlyConsulting\Requests\Models\Request;

/**
 * Implemented by the CreateRequest action and the testing fake so the fluent
 * builder can delegate to either without coupling to a concrete class.
 */
interface CreatesRequests
{
    public function execute(CreateRequestDto $dto): Request;
}
