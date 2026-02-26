<?php

declare(strict_types=1);

namespace Foxws\Shaka\Support;

use Foxws\Shaka\Events\PackagingCompleted;
use Foxws\Shaka\Events\PackagingFailed;
use Foxws\Shaka\Events\PackagingStarted;
use Foxws\Shaka\Filesystem\MediaCollection;
use Foxws\Shaka\Filesystem\TemporaryDirectories;
use Illuminate\Support\Traits\ForwardsCalls;
use Psr\Log\LoggerInterface;
use Throwable;

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
     * @return \Illuminate\Support\Collection<int, Stream>
     */
    public function streams(): \Illuminate\Support\Collection
    {
        $streams = new \Illuminate\Support\Collection;

        foreach ($this->mediaCollection->collection() as $media) {
            // You can create multiple streams per media (video, audio, etc.)
            $streams->push(Stream::video($media));
            $streams->push(Stream::audio($media));
            $streams->push(Stream::text($media));
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
     * Set the base URL prefix for HLS Media Playlists and media files.
     */
    public function withHlsBaseUrl(string $url): self
    {
        $this->builder()->withHlsBaseUrl($url);

        return $this;
    }

    /**
     * Set the key URI for 'identity' and FairPlay key formats.
     */
    public function withHlsKeyUri(string $uri): self
    {
        $this->builder()->withHlsKeyUri($uri);

        return $this;
    }

    /**
     * Set the HLS playlist type: 'VOD', 'EVENT', or 'LIVE'.
     */
    public function withHlsPlaylistType(string $type): self
    {
        $this->builder()->withHlsPlaylistType($type);

        return $this;
    }

    /**
     * Set the initial EXT-X-MEDIA-SEQUENCE value for live HLS playlists.
     */
    public function withHlsMediaSequenceNumber(int $number): self
    {
        $this->builder()->withHlsMediaSequenceNumber($number);

        return $this;
    }

    /**
     * Set EXT-X-START offset on HLS media playlists (positive = from start, negative = from end).
     */
    public function withHlsStartTimeOffset(float|int $seconds): self
    {
        $this->builder()->withHlsStartTimeOffset($seconds);

        return $this;
    }

    /**
     * Restrict output to HLS only.
     */
    public function withHlsOnly(bool $enabled = true): self
    {
        $this->builder()->withHlsOnly($enabled);

        return $this;
    }

    /**
     * Emit EXT-X-SESSION-KEY in the master playlist for offline HLS playback.
     */
    public function withCreateSessionKeys(bool $enabled = true): self
    {
        $this->builder()->withCreateSessionKeys($enabled);

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
     * Set fragment duration in seconds (independent of segment duration).
     */
    public function withFragmentDuration(float|int $seconds): self
    {
        $this->builder()->withFragmentDuration($seconds);

        return $this;
    }

    /**
     * Set the start segment number for DASH SegmentTemplate and HLS segment names.
     */
    public function withStartSegmentNumber(int $number): self
    {
        $this->builder()->withStartSegmentNumber($number);

        return $this;
    }

    /**
     * Set the transport stream timestamp offset in milliseconds (default: 100ms).
     */
    public function withTransportStreamTimestampOffsetMs(int $ms): self
    {
        $this->builder()->withTransportStreamTimestampOffsetMs($ms);

        return $this;
    }

    /**
     * Generate a static MPD even when using segment templates.
     */
    public function withGenerateStaticLiveMpd(bool $enabled = true): self
    {
        $this->builder()->withGenerateStaticLiveMpd($enabled);

        return $this;
    }

    /**
     * Set the minimum buffer time for the DASH MPD.
     */
    public function withMinBufferTime(float|int $seconds): self
    {
        $this->builder()->withMinBufferTime($seconds);

        return $this;
    }

    /**
     * Set how often (in seconds) players should refresh the dynamic MPD.
     */
    public function withMinimumUpdatePeriod(float|int $seconds): self
    {
        $this->builder()->withMinimumUpdatePeriod($seconds);

        return $this;
    }

    /**
     * Set the suggested presentation delay in seconds for a dynamic MPD.
     */
    public function withSuggestedPresentationDelay(float|int $seconds): self
    {
        $this->builder()->withSuggestedPresentationDelay($seconds);

        return $this;
    }

    /**
     * Set the time shift buffer depth in seconds for dynamic DASH/HLS live streams.
     */
    public function withTimeShiftBufferDepth(float|int $seconds): self
    {
        $this->builder()->withTimeShiftBufferDepth($seconds);

        return $this;
    }

    /**
     * Set the number of segments to preserve outside the live window.
     */
    public function withPreservedSegmentsOutsideLiveWindow(int $numSegments): self
    {
        $this->builder()->withPreservedSegmentsOutsideLiveWindow($numSegments);

        return $this;
    }

    /**
     * Set UTCTiming scheme/value pairs for the dynamic DASH MPD.
     */
    public function withUtcTimings(string $schemeIdUriValuePairs): self
    {
        $this->builder()->withUtcTimings($schemeIdUriValuePairs);

        return $this;
    }

    /**
     * Set the default language for audio/text tracks.
     */
    public function withDefaultLanguage(string $language): self
    {
        $this->builder()->withDefaultLanguage($language);

        return $this;
    }

    /**
     * Set the default language for text tracks only (overrides withDefaultLanguage()).
     */
    public function withDefaultTextLanguage(string $language): self
    {
        $this->builder()->withDefaultTextLanguage($language);

        return $this;
    }

    /**
     * Allow approximate segment timeline for live DASH profiles.
     */
    public function withAllowApproximateSegmentTimeline(bool $enabled = true): self
    {
        $this->builder()->withAllowApproximateSegmentTimeline($enabled);

        return $this;
    }

    /**
     * Restrict output to DASH only.
     */
    public function withDashOnly(bool $enabled = true): self
    {
        $this->builder()->withDashOnly($enabled);

        return $this;
    }

    /**
     * Allow adaptive codec switching within DASH adaptation sets.
     */
    public function withAllowCodecSwitching(bool $enabled = true): self
    {
        $this->builder()->withAllowCodecSwitching($enabled);

        return $this;
    }

    /**
     * Enable LL-DASH (Low Latency DASH) streaming mode.
     */
    public function withLowLatencyDashMode(bool $enabled = true): self
    {
        $this->builder()->withLowLatencyDashMode($enabled);

        return $this;
    }

    /**
     * Force streams to be ordered in the muxer as given on the command line.
     */
    public function withForceClIndex(bool $enabled = true): self
    {
        $this->builder()->withForceClIndex($enabled);

        return $this;
    }

    /**
     * Set a label for DASH adaptation set grouping.
     */
    public function withDashLabel(string $label): self
    {
        $this->builder()->withDashLabel($label);

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
     * @return array{key: string, key_id: string, file_path: string} Encryption key data
     */
    public function withAESEncryption(string $keyFilename = 'key', ?string $protectionScheme = null, ?string $label = null): array
    {
        // Generate key and write to cache storage (fast)
        $keyData = EncryptionKeyGenerator::generateAndWrite($keyFilename);

        // Store cache directory for later use in PackagerResult
        $this->cacheDirectory = dirname($keyData['file_path']);

        // Format keys for Shaka Packager
        $formattedKeys = EncryptionKeyGenerator::formatForShaka($keyData['key_id'], $keyData['key'], $label);

        // Set individual encryption options directly on the builder
        $this->builder()->withOption('enable_raw_key_encryption', true);
        $this->builder()->withOption('keys', $formattedKeys);
        $this->builder()->withOption('hls_key_uri', $keyFilename);
        $this->builder()->withOption('clear_lead', 0);

        if (filled($protectionScheme)) {
            $this->builder()->withOption('protection_scheme', $protectionScheme);
        }

        return $keyData;
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
     * Set the protection scheme: 'cenc', 'cbc1', 'cens', or 'cbcs'.
     */
    public function withProtectionScheme(string $scheme): self
    {
        $this->builder()->withProtectionScheme($scheme);

        return $this;
    }

    /**
     * Set the count of encrypted 16-byte blocks in the protection pattern.
     */
    public function withCryptByteBlock(int $count): self
    {
        $this->builder()->withCryptByteBlock($count);

        return $this;
    }

    /**
     * Set the count of unencrypted 16-byte blocks in the protection pattern.
     */
    public function withSkipByteBlock(int $count): self
    {
        $this->builder()->withSkipByteBlock($count);

        return $this;
    }

    /**
     * Enable or disable VP9 subsample encryption.
     */
    public function withVp9SubsampleEncryption(bool $enabled = true): self
    {
        $this->builder()->withVp9SubsampleEncryption($enabled);

        return $this;
    }

    /**
     * Set the clear lead duration in seconds (default: 5).
     */
    public function withClearLead(float|int $seconds): self
    {
        $this->builder()->withClearLead($seconds);

        return $this;
    }

    /**
     * Specify which protection systems to generate (e.g. Widevine, PlayReady).
     */
    public function withProtectionSystems(string $systems): self
    {
        $this->builder()->withProtectionSystems($systems);

        return $this;
    }

    /**
     * Set extra XML data to append to the PlayReady PSSH.
     */
    public function withPlayreadyExtraHeaderData(string $xml): self
    {
        $this->builder()->withPlayreadyExtraHeaderData($xml);

        return $this;
    }

    /**
     * Enable raw key encryption.
     */
    public function withEnableRawKeyEncryption(bool $enabled = true): self
    {
        $this->builder()->withEnableRawKeyEncryption($enabled);

        return $this;
    }

    /**
     * Enable raw key decryption.
     */
    public function withEnableRawKeyDecryption(bool $enabled = true): self
    {
        $this->builder()->withEnableRawKeyDecryption($enabled);

        return $this;
    }

    /**
     * Set raw key info string(s) for encryption or decryption.
     */
    public function withKeys(string $keyInfoString): self
    {
        $this->builder()->withKeys($keyInfoString);

        return $this;
    }

    /**
     * Set the IV in hex format (16 or 32 hex digits). Testing use only.
     */
    public function withIv(string $hex): self
    {
        $this->builder()->withIv($hex);

        return $this;
    }

    /**
     * Set one or more concatenated PSSH boxes in hex string format.
     */
    public function withPssh(string $hex): self
    {
        $this->builder()->withPssh($hex);

        return $this;
    }

    /**
     * Enable Widevine encryption.
     */
    public function withEnableWidevineEncryption(bool $enabled = true): self
    {
        $this->builder()->withEnableWidevineEncryption($enabled);

        return $this;
    }

    /**
     * Enable entitlement license in the Widevine encryption request.
     */
    public function withEnableEntitlementLicense(bool $enabled = true): self
    {
        $this->builder()->withEnableEntitlementLicense($enabled);

        return $this;
    }

    /**
     * Enable Widevine decryption.
     */
    public function withEnableWidevineDecryption(bool $enabled = true): self
    {
        $this->builder()->withEnableWidevineDecryption($enabled);

        return $this;
    }

    /**
     * Set the Widevine key server URL.
     */
    public function withKeyServerUrl(string $url): self
    {
        $this->builder()->withKeyServerUrl($url);

        return $this;
    }

    /**
     * Set the content identifier (hex string) for Widevine.
     */
    public function withContentId(string $hex): self
    {
        $this->builder()->withContentId($hex);

        return $this;
    }

    /**
     * Set the Widevine policy name.
     */
    public function withPolicy(string $policy): self
    {
        $this->builder()->withPolicy($policy);

        return $this;
    }

    /**
     * Set the maximum SD pixel threshold for Widevine DRM labelling.
     */
    public function withMaxSdPixels(int $pixels): self
    {
        $this->builder()->withMaxSdPixels($pixels);

        return $this;
    }

    /**
     * Set the maximum HD pixel threshold for Widevine DRM labelling.
     */
    public function withMaxHdPixels(int $pixels): self
    {
        $this->builder()->withMaxHdPixels($pixels);

        return $this;
    }

    /**
     * Set the maximum UHD1 pixel threshold for Widevine DRM labelling.
     */
    public function withMaxUhd1Pixels(int $pixels): self
    {
        $this->builder()->withMaxUhd1Pixels($pixels);

        return $this;
    }

    /**
     * Set the signer name.
     */
    public function withSigner(string $signer): self
    {
        $this->builder()->withSigner($signer);

        return $this;
    }

    /**
     * Set the AES signing key (hex). Requires withAesSigningIv(). Exclusive with withRsaSigningKeyPath().
     */
    public function withAesSigningKey(string $hex): self
    {
        $this->builder()->withAesSigningKey($hex);

        return $this;
    }

    /**
     * Set the AES signing IV (hex). Required when withAesSigningKey() is set.
     */
    public function withAesSigningIv(string $hex): self
    {
        $this->builder()->withAesSigningIv($hex);

        return $this;
    }

    /**
     * Set the path to the PKCS#1 RSA private key file. Exclusive with withAesSigningKey().
     */
    public function withRsaSigningKeyPath(string $path): self
    {
        $this->builder()->withRsaSigningKeyPath($path);

        return $this;
    }

    /**
     * Set the key rotation period in seconds.
     */
    public function withCryptoPeriodDuration(int $seconds): self
    {
        $this->builder()->withCryptoPeriodDuration($seconds);

        return $this;
    }

    /**
     * Set the Widevine group identifier (hex string).
     */
    public function withGroupId(string $hex): self
    {
        $this->builder()->withGroupId($hex);

        return $this;
    }

    /**
     * Enable PlayReady encryption.
     */
    public function withEnablePlayreadyEncryption(bool $enabled = true): self
    {
        $this->builder()->withEnablePlayreadyEncryption($enabled);

        return $this;
    }

    /**
     * Set the PlayReady packaging server URL.
     */
    public function withPlayreadyServerUrl(string $url): self
    {
        $this->builder()->withPlayreadyServerUrl($url);

        return $this;
    }

    /**
     * Set the PlayReady program identifier.
     */
    public function withProgramIdentifier(string $identifier): self
    {
        $this->builder()->withProgramIdentifier($identifier);

        return $this;
    }

    /**
     * Set the absolute path to the CA certificate file (PEM).
     */
    public function withCaFile(string $path): self
    {
        $this->builder()->withCaFile($path);

        return $this;
    }

    /**
     * Set the absolute path to the client certificate file.
     */
    public function withClientCertFile(string $path): self
    {
        $this->builder()->withClientCertFile($path);

        return $this;
    }

    /**
     * Set the absolute path to the client certificate private key file.
     */
    public function withClientCertPrivateKeyFile(string $path): self
    {
        $this->builder()->withClientCertPrivateKeyFile($path);

        return $this;
    }

    /**
     * Set the password for the client certificate private key file.
     */
    public function withClientCertPrivateKeyPassword(string $password): self
    {
        $this->builder()->withClientCertPrivateKeyPassword($password);

        return $this;
    }

    /**
     * Add a custom option to the builder
     */
    public function withOption(string $key, mixed $value): self
    {
        $this->builder()->withOption($key, $value);

        return $this;
    }

    /**
     * Remove a previously set option from the builder
     */
    public function removeOption(string $key): self
    {
        $this->builder()->removeOption($key);

        return $this;
    }

    /**
     * Add multiple custom options to the builder
     */
    public function withOptions(array $options): self
    {
        foreach ($options as $key => $value) {
            $this->builder()->withOption($key, $value);
        }

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
}
