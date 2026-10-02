<?php

declare(strict_types=1);

namespace Foxws\Shaka\Events;

use Foxws\Shaka\Filesystem\MediaCollection;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PackagingStarted
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<int, string>  $options  The packager's command-line arguments.
     */
    public function __construct(
        public ?MediaCollection $mediaCollection = null,
        public array $options = [],
    ) {}
}
