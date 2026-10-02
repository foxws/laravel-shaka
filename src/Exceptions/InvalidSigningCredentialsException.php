<?php

declare(strict_types=1);

namespace Foxws\Shaka\Exceptions;

use LogicException;

class InvalidSigningCredentialsException extends LogicException
{
    public static function missingKey(): self
    {
        return new self('Signing credentials have neither an AES key and IV nor an RSA key path.');
    }
}
