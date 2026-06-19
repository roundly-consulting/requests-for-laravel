<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Requests\DataTransferObjects\CreateRequestDto;
use RoundlyConsulting\Requests\Models\Request;
use RoundlyConsulting\Requests\RequestBuilder;
use RoundlyConsulting\Requests\RequestManager;
use RoundlyConsulting\Requests\Testing\RequestsFake;

/**
 * @method static RequestBuilder make()
 * @method static Request create(CreateRequestDto $dto)
 * @method static Request approve(Request $request, Model $actor)
 * @method static Request reject(Request $request, Model $actor)
 * @method static Request reopen(Request $request, Model $actor)
 * @method static Request cancel(Request $request)
 * @method static Request expire(Request $request)
 *
 * @see RequestManager
 */
final class Requests extends Facade
{
    public static function fake(): RequestsFake
    {
        $fake = new RequestsFake;

        self::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return RequestManager::class;
    }
}
