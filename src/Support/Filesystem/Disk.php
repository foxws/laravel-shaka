<?php

declare(strict_types=1);

namespace Foxws\Shaka\Support\Filesystem;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Traits\ForwardsCalls;

class Disk
{
    use ForwardsCalls;

    protected ?Filesystem $disk = null;

    protected ?string $temporaryDirectory = null;

    protected ?FilesystemAdapter $filesystemAdapter = null;

    public static function make(Filesystem $disk): self
    {
        return new static($disk);
    }

     public static function makeTemporaryDisk(): self
    {
        $filesystemAdapter = app('filesystem')->createLocalDriver([
            'root' => app(TemporaryDirectories::class)->create(),
        ]);

        return new static($filesystemAdapter);
    }

    /**
     * Creates a fresh instance, mostly used to force a new TemporaryDirectory.
     */
    public function clone(): self
    {
        return new Disk($this->disk);
    }

    /**
     * Creates a new TemporaryDirectory instance if none is set, otherwise
     * it returns the current one.
     */
    public function getTemporaryDirectory(): string
    {
        if ($this->temporaryDirectory) {
            return $this->temporaryDirectory;
        }

        return $this->temporaryDirectory = app(TemporaryDirectories::class)->create();
    }

}
