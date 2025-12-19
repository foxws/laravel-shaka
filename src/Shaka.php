<?php

declare(strict_types=1);

namespace Foxws\Shaka;

use Illuminate\Support\Traits\ForwardsCalls;

class Shaka
{
    use ForwardsCalls;

    protected ?string $defaultDisk = null;
}
