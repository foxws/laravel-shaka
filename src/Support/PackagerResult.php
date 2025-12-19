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
     * Copy exported files to a different disk
     */
    public function toDisk(Disk|Filesystem|string $disk, ?string $visibility = null): self
    {
        $targetDisk = Disk::make($disk);

        if (! $this->sourceDisk) {
            throw new \RuntimeException('Cannot copy files: source disk not set');
        }

        // Get output paths from metadata
        $outputPaths = $this->collectOutputPaths();

        foreach ($outputPaths as $path) {
            if ($this->sourceDisk->exists($path)) {
                $targetDisk->writeStream($path, $this->sourceDisk->readStream($path));

                if ($visibility) {
                    $targetDisk->setVisibility($path, $visibility);
                }
            }
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

        // Collect manifest outputs
        if ($relativeMpd = $this->getMetadataValue('relative_mpd_output')) {
            $paths[] = $relativeMpd;
        }

        if ($relativeHls = $this->getMetadataValue('relative_hls_output')) {
            $paths[] = $relativeHls;
        }

        return array_unique($paths);
    }
}
