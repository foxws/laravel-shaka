<?php

declare(strict_types=1);

namespace Foxws\Shaka\Support;

use Foxws\Shaka\Filesystem\Disk;
use Illuminate\Contracts\Filesystem\Filesystem;

class PackagerResult
{
    protected string $output;

    protected array $metadata = [];

    protected ?Disk $sourceDisk = null;

    public function __construct(string $output, array $metadata = [], ?Disk $sourceDisk = null)
    {
        $this->output = $output;
        $this->metadata = $metadata;
        $this->sourceDisk = $sourceDisk;
    }

    public function getOutput(): string
    {
        return $this->output;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function addMetadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;

        return $this;
    }

    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    public function toArray(): array
    {
        return [
            'output' => $this->output,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Copy exported files from temporary directory to target disk
     */
    public function toDisk(Disk|Filesystem|string $disk, ?string $visibility = null, bool $cleanupSource = true): self
    {
        $targetDisk = Disk::make($disk);

        $temporaryDirectory = $this->getMetadataValue('temporary_directory');

        if (! $temporaryDirectory) {
            throw new \RuntimeException('Cannot copy files: temporary directory not set');
        }

        // Get the target directory from the first media (if available)
        $targetDirectory = $this->getTargetDirectory();

        // Scan the temporary directory for all files (including generated segments)
        $files = $this->getAllFilesInTemporaryDirectory($temporaryDirectory);

        foreach ($files as $file) {
            $filename = basename($file);

            // Determine target path (preserve directory structure if needed)
            $targetPath = $targetDirectory ? $targetDirectory.$filename : $filename;

            $stream = fopen($file, 'r');
            $targetDisk->writeStream($targetPath, $stream);
            fclose($stream);

            if ($visibility) {
                $targetDisk->setVisibility($targetPath, $visibility);
            }

            // Clean up temporary file after copying
            if ($cleanupSource) {
                unlink($file);
            }
        }

        // Clean up temporary directory if empty
        if ($cleanupSource && is_dir($temporaryDirectory)) {
            @rmdir($temporaryDirectory);
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
     * Get the target directory from metadata
     */
    protected function getTargetDirectory(): ?string
    {
        // Check if we have explicit output paths that contain directories
        $relativePaths = $this->collectOutputPaths();

        if (! empty($relativePaths)) {
            $firstPath = $relativePaths[0];
            $directory = dirname($firstPath);

            if ($directory && $directory !== '.') {
                return rtrim($directory, '/').'/';
            }
        }

        return null;
    }

    /**
     * Collect all output file paths from metadata (using relative paths)
     */
    protected function collectOutputPaths(): array
    {
        $paths = [];

        // Collect relative stream outputs
        $relativeOutputs = $this->getMetadataValue('relative_outputs', []);
        $paths = array_merge($paths, $relativeOutputs);

        // Collect manifest outputs (these are stored as arrays with single values)
        if ($relativeMpd = $this->getMetadataValue('relative_mpd_output')) {
            $paths[] = is_array($relativeMpd) ? $relativeMpd[0] : $relativeMpd;
        }

        if ($relativeHls = $this->getMetadataValue('relative_hls_output')) {
            $paths[] = is_array($relativeHls) ? $relativeHls[0] : $relativeHls;
        }

        return array_unique($paths);
    }
}
