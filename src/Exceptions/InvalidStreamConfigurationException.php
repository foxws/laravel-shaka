<?php

declare(strict_types=1);

namespace Foxws\Shaka\Exceptions;

use Exception;

class InvalidStreamConfigurationException extends Exception
{
    public static function missingInput(): self
    {
        return new self('A stream needs a Media item or an input path.');
    }
}
