<?php

declare(strict_types=1);

namespace Foxws\Shaka\Support\Filesystem;

use Closure;
use Foxws\Shaka\Support\Packager\Packager;
use Illuminate\Support\Traits\ForwardsCalls;

class MediaOpener
{
    use ForwardsCalls;

    protected ?string $defaultDisk = null;
}
