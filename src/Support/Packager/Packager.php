<?php

declare(strict_types=1);

namespace Foxws\Shaka\Support\Packager;

use Foxws\Shaka\Support\Filesystem\MediaCollection;
use Illuminate\Support\Traits\ForwardsCalls;

class Packager
{
    use ForwardsCalls;

    protected ?MediaCollection $mediaCollection = null;

    public function fresh(): self
    {
        return new static;
    }

    public function getMediaCollection(): MediaCollection
    {
        return $this->mediaCollection;
    }

    public function open(MediaCollection $mediaCollection): self
    {
        $this->mediaCollection = $mediaCollection;

        if ($mediaCollection->count() === 1) {
            // TODO: process single media mode
        } else {
            // TODO: process multiple media mode
        }

        return $this;
    }
}
