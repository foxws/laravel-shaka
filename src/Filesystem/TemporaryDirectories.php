<?php

declare(strict_types=1);

namespace Foxws\Shaka\Filesystem;

use Foxws\Shaka\Exceptions\InsufficientStorageException;
use Illuminate\Filesystem\Filesystem;

class TemporaryDirectories
{
    /**
     * Root of the temporary directories.
     */
    protected string $root;

    /**
     * Root of the cache temporary directories (e.g., RAM disk like /dev/shm).
     */
    protected ?string $cacheRoot = null;

    /**
     * Minimum free space (in bytes) required in the main root before a new
     * directory is created there. A value of zero or less disables the check.
     */
    protected int $minFreeBytes = 0;

    /**
     * Minimum free space (in bytes) required in the cache root before a new
     * cache directory is created there. Kept separate from minFreeBytes
     * since the cache root is often a much smaller mount than the main
     * root (e.g. a small /dev/shm vs a multi-GB tmpfs). A value of zero
     * or less disables the check.
     */
    protected int $cacheMinFreeBytes = 0;

    /**
     * Multiplier applied to a job's expected input size (see create()) to
     * account for container/segmentation overhead when estimating how much
     * space the packaged output will need.
     */
    protected float $sizeSafetyMultiplier = 1.5;

    /**
     * Array of all directories
     */
    protected array $directories = [];

    /**
     * Sets the root and removes the trailing slash.
     */
    public function __construct(
        string $root,
        ?string $cacheRoot = null,
        int $minFreeBytes = 0,
        float $sizeSafetyMultiplier = 1.5,
        int $cacheMinFreeBytes = 0
    ) {
        $this->root = rtrim($root, '/');
        $this->cacheRoot = $cacheRoot ? rtrim($cacheRoot, '/') : null;
        $this->minFreeBytes = $minFreeBytes;
        $this->sizeSafetyMultiplier = $sizeSafetyMultiplier;
        $this->cacheMinFreeBytes = $cacheMinFreeBytes;
    }

    /**
     * Returns the full path a of new temporary directory.
     *
     * @param  int  $expectedBytes  Combined size of the job's source input files, if known.
     *                              Used to check the root has enough room for this specific
     *                              job, on top of the static minFreeBytes floor.
     */
    public function create(int $expectedBytes = 0): string
    {
        $requiredBytes = $expectedBytes > 0
            ? (int) ceil($expectedBytes * $this->sizeSafetyMultiplier)
            : 0;

        $this->ensureSufficientSpace($this->root, $requiredBytes, $this->minFreeBytes);

        $directory = $this->root.'/'.bin2hex(random_bytes(8));

        mkdir($directory, 0777, true);

        return $this->directories[] = $directory;
    }

    /**
     * Returns the full path of a new directory in cache storage.
     * Uses cache storage (e.g., RAM disk) if configured, otherwise falls back to regular temp.
     */
    public function createCache(): string
    {
        $root = $this->cacheRoot ?? $this->root;

        $this->ensureSufficientSpace($root, 0, $this->cacheMinFreeBytes);

        $directory = $root.'/'.bin2hex(random_bytes(8));

        mkdir($directory, 0777, true);

        return $this->directories[] = $directory;
    }

    /**
     * Guards against starting work in a root that doesn't have enough
     * free space left, e.g. a size-limited tmpfs mount. Fails fast instead
     * of letting the packager run and error out partway through.
     */
    protected function ensureSufficientSpace(string $path, int $requiredBytes, int $floorBytes): void
    {
        $requiredBytes = max($requiredBytes, $floorBytes);

        if ($requiredBytes <= 0) {
            return;
        }

        // The target directory itself may not exist yet (e.g. the first
        // job after a fresh tmpfs mount) - disk_free_space() returns false
        // for a path that doesn't exist, which would otherwise silently
        // skip the check. Walk up to the nearest existing ancestor, which
        // is on the same filesystem in all realistic setups.
        $free = @disk_free_space($this->nearestExistingPath($path));

        if ($free === false) {
            return;
        }

        if ($free < $requiredBytes) {
            throw new InsufficientStorageException(
                sprintf('Insufficient storage space in [%s]: %d bytes free, %d bytes required.', $path, $free, $requiredBytes)
            );
        }
    }

    /**
     * Walks up from $path until it finds a directory that actually exists.
     */
    protected function nearestExistingPath(string $path): string
    {
        while ($path !== '' && $path !== DIRECTORY_SEPARATOR && ! is_dir($path)) {
            $path = dirname($path);
        }

        return $path === '' ? DIRECTORY_SEPARATOR : $path;
    }

    /**
     * Check if cache temporary storage is available.
     */
    public function hasCacheStorage(): bool
    {
        return $this->cacheRoot !== null;
    }

    /**
     * Loop through all directories and delete them.
     */
    public function deleteAll(): void
    {
        $filesystem = new Filesystem;

        foreach ($this->directories as $directory) {
            $filesystem->deleteDirectory($directory);
        }

        $this->directories = [];
    }
}
