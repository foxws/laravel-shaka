<?php

declare(strict_types=1);

namespace Foxws\Shaka\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;

class PackagingFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Throwable $exception,
        public float $executionTime = 0,
        public array $context = [],
    ) {}
}
