<?php

declare(strict_types=1);

namespace RoundlyConsulting\Requests\Exceptions;

use RuntimeException;

/**
 * Base exception for every failure surfaced by the package, so consumers can
 * catch the whole hierarchy with a single `catch (RequestException $e)`.
 */
class RequestException extends RuntimeException {}
