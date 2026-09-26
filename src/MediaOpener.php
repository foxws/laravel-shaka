<?php

declare(strict_types=1);

namespace Foxws\Shaka;

use Foxws\Shaka\Filesystem\Disk;
use Foxws\Shaka\Filesystem\Media;
use Foxws\Shaka\Filesystem\MediaCollection;
use Foxws\Shaka\Filesystem\TemporaryDirectories;
use Foxws\Shaka\Support\Packager;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Traits\ForwardsCalls;

/**
 * @method \Foxws\Shaka\Support\Packager fresh()
 * @method $this setPackager(\Foxws\Shaka\Support\ShakaPackager $packager)
 * @method \Foxws\Shaka\Filesystem\MediaCollection getMediaCollection()
 * @method ?\Foxws\Shaka\Support\CommandBuilder getBuilder()
 * @method \Foxws\Shaka\Support\CommandBuilder builder()
 * @method \Illuminate\Support\Collection streams()
 * @method $this addVideoStream(string $input, string $output, array $options = [])
 * @method $this addAudioStream(string $input, string $output, array $options = [])
 * @method $this addTextStream(string $input, string $output, array $options = [])
 * @method $this addStream(\Foxws\Shaka\Support\Stream|array $stream)
 * @method $this withMpdOutput(string $path)
 * @method $this withHlsMasterPlaylist(string $path)
 * @method \Foxws\Shaka\Support\EncryptionKey withAESEncryption(string $keyFilename = 'key', ?string $protectionScheme = null, ?string $label = null)
 * @method $this withKeyRotationDuration(int $seconds)
 * @method string getCommand()
 * @method \Foxws\Shaka\Support\PackagerResult packageWithBuilder(\Foxws\Shaka\Support\CommandBuilder $builder)
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
class MediaOpener
{
    use ForwardsCalls;

    protected ?Disk $disk = null;

    protected ?Packager $packager = null;

    protected ?MediaCollection $collection = null;

    public function __construct(
        Disk|string|null $disk = null,
        ?Packager $packager = null,
        ?MediaCollection $mediaCollection = null
    ) {
        $this->fromDisk($disk ?: Config::string('filesystems.default'));

        $this->packager = $packager ?: app(Packager::class)->fresh();

        $this->collection = $mediaCollection ?: new MediaCollection;
    }

    public function clone(): self
    {
        return new MediaOpener(
            $this->disk,
            $this->packager,
            $this->collection
        );
    }

    public function fromDisk(Disk|Filesystem|string $disk): self
    {
        $this->disk = Disk::make($disk);

        return $this;
    }

    public function getDisk(): ?Disk
    {
        return $this->disk;
    }

    protected static function makeLocalDiskFromPath(string $path): Disk
    {
        $adapter = (new FilesystemManager(app()))->createLocalDriver([
            'root' => $path,
        ]);

        return Disk::make($adapter);
    }

    /**
     * Instantiates a Media object for each given path.
     */
    public function open($paths): self
    {
        foreach (Arr::wrap($paths) as $path) {
            if ($path instanceof UploadedFile) {
                $disk = static::makeLocalDiskFromPath($path->getPath());

                $media = Media::make($disk, $path->getFilename());
            } else {
                $media = Media::make($this->disk, $path);
            }

            $this->collection->push($media);
        }

        // Initialize the packager with the collection
        $this->packager->open($this->collection);

        return $this;
    }

    /**
     * Open files from a specific disk
     */
    public function openFromDisk(Filesystem|string $disk, $paths): self
    {
        return $this->fromDisk($disk)->open($paths);
    }

    public function get(): MediaCollection
    {
        return $this->collection;
    }

    public function each($items, callable $callback): self
    {
        Collection::make($items)->each(function ($item, $key) use ($callback) {
            return $callback($this->clone(), $item, $key);
        });

        return $this;
    }

    public function getPackager(): Packager
    {
        return $this->packager;
    }

    /**
     * Returns an instance of MediaExporter with the packager.
     */
    public function export(): Exporters\MediaExporter
    {
        return new Exporters\MediaExporter($this->packager);
    }

    /**
     * Create a new DynamicHLSPlaylist instance for customizing HLS playlists.
     */
    public static function dynamicHLSPlaylist(?string $disk = null): Http\DynamicHLSPlaylist
    {
        return new Http\DynamicHLSPlaylist($disk);
    }

    /**
     * Create a new DynamicDASHManifest instance for customizing DASH manifests.
     */
    public static function dynamicDASHManifest(?string $disk = null): Http\DynamicDASHManifest
    {
        return new Http\DynamicDASHManifest($disk);
    }

    public function cleanupTemporaryFiles(): self
    {
        app(TemporaryDirectories::class)->deleteAll();

        return $this;
    }

    public function __call($method, $arguments)
    {
        $result = $this->forwardCallTo($packager = $this->getPackager(), $method, $arguments);

        return ($result === $packager) ? $this : $result;
    }
}
