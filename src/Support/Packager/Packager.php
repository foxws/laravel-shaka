<?php

declare(strict_types=1);

namespace Foxws\Shaka\Support\Packager;

use Foxws\Shaka\Support\Filesystem\MediaCollection;
use Illuminate\Support\Traits\ForwardsCalls;
use Psr\Log\LoggerInterface;

class Packager
{
    use ForwardsCalls;

    protected ShakaPackagerDriver $driver;

    protected ?MediaCollection $mediaCollection = null;

    protected ?LoggerInterface $logger;

    public function __construct(
        ShakaPackagerDriver $driver,
        ?LoggerInterface $logger = null
    ) {
        $this->driver = $driver;
        $this->logger = $logger;
    }

    public static function create(
        ?LoggerInterface $logger = null,
        ?array $configuration = null
    ): self {
        $driver = ShakaPackagerDriver::create($logger, $configuration);

        return new self($driver, $logger);
    }

    public function fresh(): self
    {
        return new self($this->driver, $this->logger);
    }

    public function getDriver(): ShakaPackagerDriver
    {
        return $this->driver;
    }

    public function setDriver(ShakaPackagerDriver $driver): self
    {
        $this->driver = $driver;

        return $this;
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

    public function package(array $streams, string $output): PackagerResult
    {
        // Build command arguments
        $arguments = $this->buildPackageCommand($streams, $output);

        if ($this->logger) {
            $this->logger->info('Starting packaging operation', [
                'streams' => count($streams),
                'output' => $output,
            ]);
        }

        // Execute via driver
        $result = $this->driver->command($arguments);

        if ($this->logger) {
            $this->logger->info('Packaging operation completed', [
                'output' => $output,
            ]);
        }

        return new PackagerResult($result, [
            'output_path' => $output,
            'streams' => $streams,
        ]);
    }

    protected function buildPackageCommand(array $streams, string $output): string
    {
        // Build your packager-specific command syntax
        // This is where you translate your high-level API to binary commands
        $parts = [];

        foreach ($streams as $stream) {
            // Example structure - adjust based on your actual Shaka Packager syntax
            // 'in=input.mp4,stream=video,output=output_video.mp4'
            if (is_array($stream)) {
                $streamParts = [];
                foreach ($stream as $key => $value) {
                    $streamParts[] = "{$key}={$value}";
                }
                $parts[] = implode(',', $streamParts);
            } else {
                $parts[] = $stream;
            }
        }

        return implode(' ', $parts);
    }
}
