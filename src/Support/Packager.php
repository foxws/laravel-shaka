<?php

declare(strict_types=1);

namespace Foxws\Shaka\Support;

use Foxws\Shaka\Filesystem\MediaCollection;
use Foxws\Shaka\Filesystem\TemporaryDirectories;
use Illuminate\Support\Traits\ForwardsCalls;
use Psr\Log\LoggerInterface;

class Packager
{
    use ForwardsCalls;

    protected ShakaPackager $packager;

    protected ?MediaCollection $mediaCollection = null;

    protected ?LoggerInterface $logger;

    protected ?CommandBuilder $builder = null;

    protected ?string $temporaryDirectory = null;

    public function __construct(
        ShakaPackager $packager,
        ?LoggerInterface $logger = null
    ) {
        $this->packager = $packager;
        $this->logger = $logger;
    }

    public static function create(
        ?LoggerInterface $logger = null,
        ?array $configuration = null
    ): self {
        $packager = ShakaPackager::create($logger, $configuration);

        return new self($packager, $logger);
    }

    public function fresh(): self
    {
        return new self($this->packager, $this->logger);
    }

    public function getPackager(): ShakaPackager
    {
        return $this->packager;
    }

    public function setPackager(ShakaPackager $packager): self
    {
        $this->packager = $packager;

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
     * @return \Illuminate\Support\Collection<int, Stream>
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
    public function addVideoStream(string $input, string $output, array $options = []): self
    {
        // Resolve input to full local path for Shaka Packager
        $inputPath = $this->resolveInputPath($input);

        // Resolve output to full local path for Shaka Packager
        $outputPath = $this->resolveOutputPath($output);

        $this->builder()->addVideoStream($inputPath, $outputPath, $options);

        return $this;
    }

    /**
     * Add an audio stream to the builder
     */
    public function addAudioStream(string $input, string $output, array $options = []): self
    {
        // Resolve input to full local path for Shaka Packager
        $inputPath = $this->resolveInputPath($input);

        // Resolve output to full local path for Shaka Packager
        $outputPath = $this->resolveOutputPath($output);

        $this->builder()->addAudioStream($inputPath, $outputPath, $options);

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
     * Resolve input path to full local path from MediaCollection
     */
    protected function resolveInputPath(string $input): string
    {
        // Try to find media in collection
        if ($this->mediaCollection) {
            $media = $this->mediaCollection->findByPath($input);

            if ($media) {
                return $media->getLocalPath();
            }
        }

        // If not found, assume it's already a full path
        return $input;
    }

    /**
     * Resolve output path to temporary directory for Shaka Packager processing
     */
    protected function resolveOutputPath(string $output): string
    {
        // Get or create temporary directory
        $tempDir = $this->getTemporaryDirectory();

        // Combine with output filename (without source directory)
        return $tempDir.DIRECTORY_SEPARATOR.$output;
    }

    /**
     * Get or create temporary directory for this export
     */
    protected function getTemporaryDirectory(): string
    {
        if ($this->temporaryDirectory) {
            return $this->temporaryDirectory;
        }

        // Use the registered TemporaryDirectories service
        $this->temporaryDirectory = app(TemporaryDirectories::class)->create();

        return $this->temporaryDirectory;
    }

    /**
     * Set MPD output
     */
    public function withMpdOutput(string $path): self
    {
        $fullPath = $this->resolveOutputPath($path);

        $this->builder()->withMpdOutput($fullPath);

        return $this;
    }

    /**
     * Set HLS master playlist output
     */
    public function withHlsMasterPlaylist(string $path): self
    {
        $fullPath = $this->resolveOutputPath($path);

        $this->builder()->withHlsMasterPlaylist($fullPath);

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
     * Returns the final command that would be executed, useful for debugging purposes.
     */
    public function getCommand(): string
    {
        if (! $this->builder) {
            throw new \RuntimeException('No streams configured. Use addVideoStream() or addAudioStream() first.');
        }

        return $this->builder->build();
    }

    /**
     * Filter sensitive data from options before logging
     */
    protected function filterSensitiveOptions(array $options): array
    {
        $filtered = $options;

        // List of sensitive keys that should be redacted
        $sensitiveKeys = [
            'keys',
            'key',
            'key_id',
            'pssh',
            'protection_systems',
            'raw_key',
            'iv',
        ];

        foreach ($sensitiveKeys as $key) {
            if (isset($filtered[$key])) {
                $filtered[$key] = '[REDACTED]';
            }
        }

        return $filtered;
    }

    /**
     * Export packaging with the configured builder
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
                'options' => $this->filterSensitiveOptions($this->builder->getOptions()),
            ]);
        }

        $result = $this->packager->command($command);

        if ($this->logger) {
            $this->logger->info('Packaging operation completed');
        }

        // Get the first media's disk as the source disk
        $sourceDisk = $this->mediaCollection->collection()->first()?->getDisk();

        return new PackagerResult($result, $sourceDisk, $this->temporaryDirectory);
    }

    public function packageWithBuilder(CommandBuilder $builder): PackagerResult
    {
        $command = $builder->build();

        if ($this->logger) {
            $this->logger->info('Starting packaging operation with builder', [
                'streams' => $builder->getStreams()->count(),
                'options' => $this->filterSensitiveOptions($builder->getOptions()),
            ]);
        }

        $result = $this->packager->command($command);

        if ($this->logger) {
            $this->logger->info('Packaging operation completed');
        }

        return new PackagerResult($result);
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

        // Export via packager
        $result = $this->packager->command($arguments);

        if ($this->logger) {
            $this->logger->info('Packaging operation completed', [
                'output' => $output,
            ]);
        }

        return new PackagerResult($result);
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
