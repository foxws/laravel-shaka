<?php

declare(strict_types=1);

namespace Foxws\Shaka;

use Foxws\Shaka\Support\Filesystem\Disk;
use Foxws\Shaka\Support\Filesystem\Media;
use Foxws\Shaka\Support\Filesystem\MediaCollection;
use Foxws\Shaka\Support\Filesystem\TemporaryDirectories;
use Foxws\Shaka\Support\Packager\Packager;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\ForwardsCalls;

class Shaka
{
    use ForwardsCalls;

    protected ?Disk $disk = null;

    protected ?Packager $packager = null;

    protected ?MediaCollection $collection = null;

    public function __construct(
        ?string $disk = null,
        ?Packager $packager = null,
        ?MediaCollection $mediaCollection = null
    ) {
        $this->fromDisk($disk ?: config('filesystems.default'));

        $this->packager = $packager ?: app(Packager::class)->fresh();

        $this->collection = $mediaCollection ?: new MediaCollection;
    }

    public function clone(): self
    {
        return new Shaka(
            $this->disk,
            $this->packager,
            $this->collection
        );
    }

    public function fromDisk(Filesystem|string $disk): self
    {
        $this->disk = Disk::make($disk);

        return $this;
    }

    private static function makeLocalDiskFromPath(string $path): Disk
    {
        $adapter = (new FilesystemManager(app()))->createLocalDriver([
            'root' => $path,
        ]);

        return Disk::make($adapter);
    }

    /**
     * Instantiates a Media object for each given path.
     */
    public function open($paths): self
    {
        foreach (Arr::wrap($paths) as $path) {
            if ($path instanceof UploadedFile) {
                $disk = static::makeLocalDiskFromPath($path->getPath());

                $media = Media::make($disk, $path->getFilename());
            } else {
                $media = Media::make($this->disk, $path);
            }

            $this->collection->push($media);
        }

        return $this;
    }

    public function get(): MediaCollection
    {
        return $this->collection;
    }

    public function each($items, callable $callback): self
    {
        Collection::make($items)->each(function ($item, $key) use ($callback) {
            return $callback($this->clone(), $item, $key);
        });

        return $this;
    }

    public function getPackager(): Packager
    {
        return $this->packager->open($this->collection);
    }

    public function cleanupTemporaryFiles(): self
    {
        app(TemporaryDirectories::class)->deleteAll();

        return $this;
    }

    public function __call($method, $arguments)
    {
        $result = $this->forwardCallTo($packager = $this->getPackager(), $method, $arguments);

        return ($result === $packager) ? $this : $result;
    }
}
