<?php

declare(strict_types=1);

namespace Foxws\Shaka\Support;

use Foxws\Shaka\Events\PackagingCompleted;
use Foxws\Shaka\Events\PackagingFailed;
use Foxws\Shaka\Events\PackagingStarted;
use Foxws\Shaka\Filesystem\MediaCollection;
use Foxws\Shaka\Filesystem\TemporaryDirectories;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\ForwardsCalls;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * @method $this withBaseUrls(string|array $urls)
 * @method $this withHlsBaseUrl(string $url)
 * @method $this withHlsKeyUri(string $uri)
 * @method $this withHlsPlaylistType(\Foxws\Shaka\Support\HlsPlaylistType|string $type)
 * @method $this withHlsMediaSequenceNumber(int $number)
 * @method $this withHlsStartTimeOffset(float|int $seconds)
 * @method $this withCreateSessionKeys(bool $enabled = true)
 * @method $this withSegmentDuration(int $seconds)
 * @method $this withFragmentDuration(float|int $seconds)
 * @method $this withStartSegmentNumber(int $number)
 * @method $this withTransportStreamTimestampOffsetMs(int $ms)
 * @method $this withGenerateStaticLiveMpd(bool $enabled = true)
 * @method $this withMinBufferTime(float|int $seconds)
 * @method $this withMinimumUpdatePeriod(float|int $seconds)
 * @method $this withSuggestedPresentationDelay(float|int $seconds)
 * @method $this withTimeShiftBufferDepth(float|int $seconds)
 * @method $this withPreservedSegmentsOutsideLiveWindow(int $numSegments)
 * @method $this withUtcTimings(string $schemeIdUriValuePairs)
 * @method $this withDefaultLanguage(string $language)
 * @method $this withDefaultTextLanguage(string $language)
 * @method $this withAllowApproximateSegmentTimeline(bool $enabled = true)
 * @method $this withAllowCodecSwitching(bool $enabled = true)
 * @method $this withLowLatencyDashMode(bool $enabled = true)
 * @method $this withForceClIndex(bool $enabled = true)
 * @method $this withDashLabel(string $label)
 * @method $this withEncryption(array $encryptionConfig)
 * @method $this withProtectionScheme(\Foxws\Shaka\Support\ProtectionScheme|string $scheme)
 * @method $this withCryptByteBlock(int $count)
 * @method $this withSkipByteBlock(int $count)
 * @method $this withVp9SubsampleEncryption(bool $enabled = true)
 * @method $this withClearLead(float|int $seconds)
 * @method $this withProtectionSystems(string $systems)
 * @method $this withPlayreadyExtraHeaderData(string $xml)
 * @method $this withEnableRawKeyEncryption(bool $enabled = true)
 * @method $this withEnableRawKeyDecryption(bool $enabled = true)
 * @method $this withKeys(string $keyInfoString)
 * @method $this withIv(string $hex)
 * @method $this withPssh(string $hex)
 * @method $this withEnableWidevineEncryption(bool $enabled = true)
 * @method $this withEnableEntitlementLicense(bool $enabled = true)
 * @method $this withEnableWidevineDecryption(bool $enabled = true)
 * @method $this withKeyServerUrl(string $url)
 * @method $this withContentId(string $hex)
 * @method $this withPolicy(string $policy)
 * @method $this withMaxSdPixels(int $pixels)
 * @method $this withMaxHdPixels(int $pixels)
 * @method $this withMaxUhd1Pixels(int $pixels)
 * @method $this withSigner(string $signer)
 * @method $this withSigningCredentials(\Foxws\Shaka\Support\SigningCredentials $credentials)
 * @method $this withAesSigningKey(string $hex)
 * @method $this withAesSigningIv(string $hex)
 * @method $this withRsaSigningKeyPath(string $path)
 * @method $this withCryptoPeriodDuration(int $seconds)
 * @method $this withGroupId(string $hex)
 * @method $this withEnablePlayreadyEncryption(bool $enabled = true)
 * @method $this withPlayreadyServerUrl(string $url)
 * @method $this withProgramIdentifier(string $identifier)
 * @method $this withCaFile(string $path)
 * @method $this withClientCertFile(string $path)
 * @method $this withClientCertPrivateKeyFile(string $path)
 * @method $this withClientCertPrivateKeyPassword(string $password)
 * @method $this withOption(string $key, mixed $value)
 * @method $this removeOption(string $key)
 * @method string build()
 * @method array buildArray()
 * @method $this reset()
 * @method \Illuminate\Support\Collection getStreams()
 * @method array getOptions()
 */
class Packager
{
    use ForwardsCalls;

    protected ShakaPackager $packager;

    protected ?MediaCollection $mediaCollection = null;

    protected ?LoggerInterface $logger;

    protected ?CommandBuilder $builder = null;

    protected ?string $temporaryDirectory = null;

    protected ?string $cacheDirectory = null;

    protected ?array $configuration = null;

    public function __construct(
        ShakaPackager $packager,
        ?LoggerInterface $logger = null,
        ?array $configuration = null
    ) {
        $this->packager = $packager;
        $this->logger = $logger;
        $this->configuration = $configuration;
    }

    public static function create(
        ?LoggerInterface $logger = null,
        ?array $configuration = null
    ): self {
        $packager = ShakaPackager::create($logger, $configuration);

        return new self($packager, $logger, $configuration);
    }

    public function fresh(): self
    {
        return new self($this->packager, $this->logger, $this->configuration);
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
        $this->builder = $this->createBuilder();

        if ($this->logger) {
            $this->logger->debug('Opened media collection', [
                'count' => $mediaCollection->count(),
                'paths' => $mediaCollection->collection()->map->getPath()->all(),
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
            $this->builder = $this->createBuilder();
        }

        return $this->builder;
    }

    /**
     * Create a new CommandBuilder with configuration defaults
     */
    protected function createBuilder(): CommandBuilder
    {
        $builder = CommandBuilder::make();

        if (! $this->configuration) {
            return $builder;
        }

        // Apply segment_duration default
        if (filled($this->configuration['segment_duration'] ?? null)) {
            $builder->withSegmentDuration($this->configuration['segment_duration']);
        }

        // Apply packager_options defaults
        if (filled($this->configuration['packager_options'] ?? null) && is_array($this->configuration['packager_options'])) {
            foreach ($this->configuration['packager_options'] as $key => $value) {
                $builder->withOption($key, $value);
            }
        }

        return $builder;
    }

    /**
     * Create streams from the media collection
     *
     * @return Collection<int, Stream>
     */
    public function streams(): Collection
    {
        return $this->mediaCollection->collection()->flatMap(
            fn ($media) => [Stream::video($media), Stream::audio($media), Stream::text($media)]
        );
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
     * Add an text stream to the builder
     */
    public function addTextStream(string $input, string $output, array $options = []): self
    {
        // Resolve input to full local path for Shaka Packager
        $inputPath = $this->resolveInputPath($input);

        // Resolve output to full local path for Shaka Packager
        $outputPath = $this->resolveOutputPath($output);

        $this->builder()->addTextStream($inputPath, $outputPath, $options);

        return $this;
    }

    /**
     * Add a stream to the builder
     */
    public function addStream(Stream|array $stream): self
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
                return $media->getSafeInputPath();
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

        // Use the registered TemporaryDirectories service. Pass the combined
        // size of the configured input media so it can check the root has
        // enough room for this specific job, not just a static floor.
        $expectedBytes = $this->mediaCollection?->totalSize() ?? 0;

        $this->temporaryDirectory = app(TemporaryDirectories::class)->create($expectedBytes);

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
     * Enable AES-128 encryption with auto-generated keys.
     *
     * Generates encryption key, writes to cache storage, and configures Shaka Packager.
     * When used with withKeyRotationDuration(), the filename becomes a base name
     * (e.g., 'key' becomes 'key_0', 'key_1', 'key_2', etc. in cache storage).
     *
     * Protection schemes:
     * - 'cenc' (AES-CTR): Recommended for Widevine/PlayReady, supports key rotation
     * - 'cbcs' (AES-CBC): For FairPlay/Safari
     * - 'cbc1': Legacy HLS, limited browser support
     * - null: SAMPLE-AES, widest compatibility but NO key rotation support
     *
     * @param  string  $keyFilename  Base name for key file (default: 'key')
     * @param  string|null  $protectionScheme  Protection scheme ('cenc', 'cbcs', 'cbc1', or null)
     * @param  string|null  $label  Optional label for multi-key scenarios
     */
    public function withAESEncryption(string $keyFilename = 'key', ?string $protectionScheme = null, ?string $label = null): EncryptionKey
    {
        // Generate key and write to cache storage (fast)
        $encryptionKey = EncryptionKey::generateAndWrite($keyFilename);

        // Store cache directory for later use in PackagerResult
        $this->cacheDirectory = dirname($encryptionKey->filePath);

        // Set individual encryption options directly on the builder
        $this->builder()->withOption('enable_raw_key_encryption', true);
        $this->builder()->withOption('keys', $encryptionKey->toShakaFormat($label));
        $this->builder()->withOption('hls_key_uri', $keyFilename);
        $this->builder()->withOption('clear_lead', 0);

        if (filled($protectionScheme)) {
            $this->builder()->withOption('protection_scheme', $protectionScheme);
        }

        return $encryptionKey;
    }

    /**
     * Enable key rotation for encryption.
     *
     * Rotates encryption keys at specified intervals. Call after withAESEncryption().
     * Common values: 300 (5 min), 600 (10 min), 1800 (30 min), 3600 (1 hour).
     *
     * IMPORTANT: Key rotation requires protection scheme 'cenc' or 'cbcs'.
     * SAMPLE-AES (null) does not support key rotation.
     *
     * @param  int  $seconds  Duration in seconds before rotating to a new key
     */
    public function withKeyRotationDuration(int $seconds): self
    {
        $this->builder()->withCryptoPeriodDuration($seconds);

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
        // List of sensitive keys that should be redacted
        static $sensitiveKeys = [
            'keys' => true,
            'key' => true,
            'key_id' => true,
            'pssh' => true,
            'protection_systems' => true,
            'raw_key' => true,
            'iv' => true,
            'aes_signing_key' => true,
            'aes_signing_iv' => true,
            'content_id' => true,
            'group_id' => true,
            'client_cert_private_key_password' => true,
        ];

        $filtered = $options;

        foreach ($sensitiveKeys as $key => $_) {
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

        $command = $this->builder->buildArray();

        if ($this->logger) {
            $this->logger->info('Starting packaging operation', [
                'streams' => $this->builder->getStreams()->count(),
                'options' => $this->filterSensitiveOptions($this->builder->getOptions()),
            ]);
        }

        // Dispatch event before starting the packaging operation
        PackagingStarted::dispatch($this->mediaCollection, $command);

        $startTime = microtime(true);

        try {
            $result = $this->packager->command($command);

            // Get the first media's disk as the source disk
            $sourceDisk = $this->mediaCollection->collection()->first()?->getDisk();

            $packagerResult = new PackagerResult($result, $sourceDisk, $this->temporaryDirectory, $this->cacheDirectory, $this->configuration);

            if ($this->logger) {
                $this->logger->info('Packaging operation completed');
            }

            PackagingCompleted::dispatch($packagerResult, microtime(true) - $startTime);

            return $packagerResult;
        } catch (Throwable $e) {
            $executionTime = microtime(true) - $startTime;

            if ($this->logger) {
                $this->logger->error('Packaging operation failed', [
                    'exception' => $e->getMessage(),
                    'execution_time' => $executionTime,
                ]);
            }

            PackagingFailed::dispatch($e, $executionTime, [
                'command' => $command,
                'mediaCollection' => $this->mediaCollection,
            ]);

            throw $e;
        }
    }

    public function packageWithBuilder(CommandBuilder $builder): PackagerResult
    {
        $command = $builder->buildArray();

        if ($this->logger) {
            $this->logger->info('Starting packaging operation with builder', [
                'streams' => $builder->getStreams()->count(),
                'options' => $this->filterSensitiveOptions($builder->getOptions()),
            ]);
        }

        PackagingStarted::dispatch($this->mediaCollection, $command);

        $startTime = microtime(true);

        try {
            $result = $this->packager->command($command);

            $sourceDisk = $this->mediaCollection?->collection()->first()?->getDisk();

            $packagerResult = new PackagerResult($result, $sourceDisk, $this->temporaryDirectory, $this->cacheDirectory, $this->configuration);

            if ($this->logger) {
                $this->logger->info('Packaging operation completed');
            }

            PackagingCompleted::dispatch($packagerResult, microtime(true) - $startTime);

            return $packagerResult;
        } catch (Throwable $e) {
            $executionTime = microtime(true) - $startTime;

            if ($this->logger) {
                $this->logger->error('Packaging operation failed', [
                    'exception' => $e->getMessage(),
                    'execution_time' => $executionTime,
                ]);
            }

            PackagingFailed::dispatch($e, $executionTime, [
                'command' => $command,
                'mediaCollection' => $this->mediaCollection,
            ]);

            throw $e;
        }
    }

    /**
     * Forward all other method calls to the underlying CommandBuilder,
     * returning $this for fluent chaining when the builder returns itself.
     */
    public function __call(string $name, array $arguments): mixed
    {
        $result = $this->forwardCallTo($this->builder(), $name, $arguments);

        return $result instanceof CommandBuilder ? $this : $result;
    }
}
