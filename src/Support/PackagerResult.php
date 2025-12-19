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

        // Get relative output paths from metadata
        $relativePaths = $this->collectOutputPaths();

        foreach ($relativePaths as $relativePath) {
            // Full path in temporary directory
            $tempFilePath = $temporaryDirectory.DIRECTORY_SEPARATOR.basename($relativePath);

            if (file_exists($tempFilePath)) {
                $stream = fopen($tempFilePath, 'r');
                $targetDisk->writeStream($relativePath, $stream);
                fclose($stream);

                if ($visibility) {
                    $targetDisk->setVisibility($relativePath, $visibility);
                }

                // Clean up temporary file after copying
                if ($cleanupSource) {
                    unlink($tempFilePath);
                }
            }
        }

        // Clean up temporary directory if empty
        if ($cleanupSource && is_dir($temporaryDirectory)) {
            @rmdir($temporaryDirectory);
        }

        return $this;
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
