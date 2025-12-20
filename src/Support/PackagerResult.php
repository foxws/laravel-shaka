<?php

declare(strict_types=1);

namespace Foxws\Shaka\Support;

use Foxws\Shaka\Filesystem\Disk;
use Illuminate\Contracts\Filesystem\Filesystem;

class PackagerResult
{
    public function __construct(
        protected string $output,
        protected ?Disk $sourceDisk = null,
        protected ?string $temporaryDirectory = null
    )
    {}

    public function getOutput(): string
    {
        return $this->output;
    }

    /**
     * Copy exported files from temporary directory to target disk
     */
    public function toDisk(Disk|Filesystem|string $disk, ?string $visibility = null, bool $cleanup = true, ?string $outputPath = null): self
    {
        $targetDisk = Disk::make($disk);

        if (! $this->temporaryDirectory) {
            throw new \RuntimeException('Cannot copy files: temporary directory not set');
        }

        // Get the target directory from outputPath parameter or preserve source structure
        $targetDirectory = $outputPath ?: $this->getSourceDirectory();

        // Scan the temporary directory for all files (including generated segments)
        $files = $this->getAllFilesInTemporaryDirectory($this->temporaryDirectory);

        foreach ($files as $file) {
            $filename = basename($file);

            // Determine target path
            $targetPath = $targetDirectory ? $targetDirectory.$filename : $filename;

            $stream = fopen($file, 'r');
            $targetDisk->writeStream($targetPath, $stream);
            fclose($stream);

            if ($visibility) {
                $targetDisk->setVisibility($targetPath, $visibility);
            }

            // Clean up temporary file after copying
            if ($cleanup) {
                unlink($file);
            }
        }

        // Clean up temporary directory if empty
        if ($cleanup && is_dir($this->temporaryDirectory)) {
            @rmdir($this->temporaryDirectory);
        }

        return $this;
    }

    /**
     * Get all files in the temporary directory
     */
    protected function getAllFilesInTemporaryDirectory(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $items = scandir($directory);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$item;

            if (is_file($path)) {
                $files[] = $path;
            }
        }

        return $files;
    }

    /**
     * Get the source directory to preserve directory structure if no output path specified
     */
    protected function getSourceDirectory(): ?string
    {
        // If we have a source disk with media, preserve its directory structure
        if ($this->sourceDisk && method_exists($this->sourceDisk, 'getDirectory')) {
            $directory = $this->sourceDisk->getDirectory();

            if ($directory && $directory !== '.') {
                return rtrim($directory, '/').'/';
            }
        }

        return null;
    }
}
