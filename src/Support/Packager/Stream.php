<?php

declare(strict_types=1);

namespace Foxws\Shaka\Support\Packager;

use Illuminate\Support\Collection;

class Stream
{
    protected ?string $disk = null;

    protected ?Collection $collection = null;

    public static function make(): self
    {
        return new self;
    }
}
