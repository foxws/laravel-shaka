<?php

declare(strict_types=1);

namespace Foxws\Shaka\Filesystem;

use Illuminate\Support\Collection;
use Illuminate\Support\Traits\ForwardsCalls;

class MediaCollection
{
    use ForwardsCalls;

    protected ?Collection $items = null;

    public function __construct(array $items = [])
    {
        $this->items = Collection::make($items);
    }

    public static function make(array $items = []): self
    {
        return new static($items);
    }

    /**
     * Returns an array with all locals paths of the Media items.
     */
    public function getLocalPaths(): array
    {
        return $this->items->map->getLocalPath()->all();
    }

    /**
     * Find a Media object by its path.
     */
    public function findByPath(string $path): ?Media
    {
        return $this->items->first(function (Media $media) use ($path) {
            return $media->getPath() === $path;
        });
    }

    public function collection(): Collection
    {
        return $this->items;
    }

    /**
     * Count the number of items in the collection.
     */
    public function count(): int
    {
        return $this->items->count();
    }

    public function __call($method, $parameters)
    {
        return $this->forwardCallTo($this->collection(), $method, $parameters);
    }
}
