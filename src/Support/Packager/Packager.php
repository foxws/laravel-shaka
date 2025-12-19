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

    protected ?CommandBuilder $builder = null;

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

        // Validate the media collection
        if ($mediaCollection->count() === 0) {
            throw new \InvalidArgumentException('MediaCollection cannot be empty');
        }

        // Initialize a fresh CommandBuilder for this media collection
        $this->builder = CommandBuilder::make();

        if ($this->logger) {
            $this->logger->debug('Opened media collection', [
                'count' => $mediaCollection->count(),
                'paths' => $mediaCollection->getLocalPaths(),
            ]);
        }

        return $this;
    }

    public function getBuilder(): ?CommandBuilder
    {
        return $this->builder;
    }

    public function builder(): CommandBuilder
    {
        if (! $this->builder) {
            $this->builder = CommandBuilder::make();
        }

        return $this->builder;
    }

    /**
     * Create streams from the media collection
     *
     * @return \Illuminate\Support\Collection<Stream>
     */
    public function streams(): \Illuminate\Support\Collection
    {
        $streams = new \Illuminate\Support\Collection;

        foreach ($this->mediaCollection->collection() as $media) {
            // You can create multiple streams per media (video, audio, etc.)
            $streams->push(Stream::video($media));
            $streams->push(Stream::audio($media));
        }

        return $streams;
    }

    /**
     * Add a video stream to the builder
     */
    public function addVideoStream(string $input, string $output, ?array $options = []): self
    {
        $this->builder()->addVideoStream($input, $output, $options);

        return $this;
    }

    /**
     * Add an audio stream to the builder
     */
    public function addAudioStream(string $input, string $output, ?array $options = []): self
    {
        $this->builder()->addAudioStream($input, $output, $options);

        return $this;
    }

    /**
     * Add a stream to the builder
     */
    public function addStream(array $stream): self
    {
        $this->builder()->addStream($stream);

        return $this;
    }

    /**
     * Set MPD output
     */
    public function withMpdOutput(string $path): self
    {
        $this->builder()->withMpdOutput($path);

        return $this;
    }

    /**
     * Set HLS master playlist output
     */
    public function withHlsMasterPlaylist(string $path): self
    {
        $this->builder()->withHlsMasterPlaylist($path);

        return $this;
    }

    /**
     * Set segment duration
     */
    public function withSegmentDuration(int $seconds): self
    {
        $this->builder()->withSegmentDuration($seconds);

        return $this;
    }

    /**
     * Enable encryption
     */
    public function withEncryption(array $encryptionConfig): self
    {
        $this->builder()->withEncryption($encryptionConfig);

        return $this;
    }

    /**
     * Execute packaging with the configured builder
     */
    public function export(): PackagerResult
    {
        if (! $this->builder) {
            throw new \RuntimeException('No streams configured. Use addVideoStream() or addAudioStream() first.');
        }

        $command = $this->builder->build();

        if ($this->logger) {
            $this->logger->info('Starting packaging operation', [
                'streams' => $this->builder->getStreams()->count(),
                'options' => $this->builder->getOptions(),
            ]);
        }

        $result = $this->driver->command($command);

        if ($this->logger) {
            $this->logger->info('Packaging operation completed');
        }

        return new PackagerResult($result, [
            'streams' => $this->builder->getStreams()->toArray(),
            'options' => $this->builder->getOptions(),
        ]);
    }

    public function packageWithBuilder(CommandBuilder $builder): PackagerResult
    {
        $command = $builder->build();

        if ($this->logger) {
            $this->logger->info('Starting packaging operation with builder', [
                'streams' => $builder->getStreams()->count(),
                'options' => $builder->getOptions(),
            ]);
        }

        $result = $this->driver->command($command);

        if ($this->logger) {
            $this->logger->info('Packaging operation completed');
        }

        return new PackagerResult($result, [
            'streams' => $builder->getStreams()->toArray(),
            'options' => $builder->getOptions(),
        ]);
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
