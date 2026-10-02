<?php

declare(strict_types=1);

namespace Foxws\Shaka\Exceptions;

use LogicException;

class MissingPackagerException extends LogicException
{
    public static function noResolver(): self
    {
        return new self('MediaOpenerFactory needs a packager or a packager resolver.');
    }
}
