<?php

declare(strict_types=1);

namespace Foxws\Shaka\Support\Filesystem;

use Closure;
use Foxws\Shaka\Support\Packager\Packager;
use Illuminate\Support\Traits\ForwardsCalls;

class MediaOpenerFactory
{
    use ForwardsCalls;

    protected ?string $defaultDisk = null;

    protected ?Packager $packager = null;

    protected ?Closure $packagerResolver = null;

    protected function packager(): Packager
    {
        if ($this->packager) {
            return $this->packager;
        }

        $resolver = $this->packagerResolver;

        return $this->packager = $resolver();
    }

    public function new(): MediaOpener
    {
        return new MediaOpener($this->defaultDisk, $this->packager());
    }

    /**
     * Handle dynamic method calls into the MediaOpener.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->forwardCallTo($this->new(), $method, $parameters);
    }
}
