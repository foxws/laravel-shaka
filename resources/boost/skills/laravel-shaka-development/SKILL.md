---
name: laravel-shaka-development
description: Package already-encoded video and audio into HLS and DASH with foxws/laravel-shaka (Shaka Packager) on top of foxws/laravel-media, including cbcs/cenc encryption with key rotation and clear lead, DRM systems, Widevine and PlayReady key servers, live and low-latency DASH, base URLs, subtitles, exporting to local or S3 disks and faking Shaka Packager in tests. Use when working with $opener->shaka(), the "shaka" packager driver, Foxws\Shaka classes such as ShakaOptions, or config/shaka.php.
---

# Packaging with laravel-shaka

`foxws/laravel-shaka` is an add-on for `foxws/laravel-media`. It registers a `shaka` driver for laravel-media's packaging builder that runs the [Shaka Packager](https://shaka-project.github.io/shaka-packager/html/) binary. Shaka Packager remuxes and segments media that is **already encoded**; it does not transcode. Opening media, disks, temporary files, the process runner, uploads, encryption keys, signed playlists, events and fakes all come from laravel-media; activate `laravel-media-development` for those.

## Packaging flow

```php
use Foxws\Media\Encryption\ProtectionScheme;
use Foxws\Media\Facades\Media;
use Foxws\Media\Filesystem\ExportResult;
use Foxws\Media\Packaging\PackagingBuilder;

$result = Media::fromDisk('media')
    ->open('videos/clip.mp4')
    ->shaka()                               // = ->package()->using('shaka')
    ->addVideoStream(output: 'video.mp4')
    ->addAudioStream(output: 'audio.mp4', language: 'en')
    ->withHlsPlaylist('master.m3u8')
    ->withDashManifest('manifest.mpd')
    ->forVod()
    ->withEncryption(scheme: ProtectionScheme::Cbcs)
    ->toDisk('streams')                     // defaults to the source disk
    ->withVisibility('private')
    ->withContext(['video_id' => $video->id])
    ->afterSaving(fn (PackagingBuilder $builder, ExportResult $result) => $video->markAsPackaged())
    ->save("{$video->id}");

$result->paths();          // manifests first
$result->encryptionKey();  // store it for the key or license route
```

- `save($directory)` packages into a laravel-media temporary directory, then moves or uploads everything to the target disk, and dispatches `ExportCompleted`/`ExportFailed` with the `withContext()` data.
- Don't call any cleanup: laravel-media deletes temporary files after every queue job and request.
- `add*Stream($path, $output, ..., $options)`: `$path` defaults to the first opened file; other paths are read from the same disk. `addStreamsFrom()` probes every opened file and adds its video and audio. `$options` are Shaka stream descriptor fields such as `hls_name` or `dash_roles`.
- Use `.mp4` outputs for subtitles, not `.vtt`: DASH needs the segment index.
- For several qualities, encode with `$opener->ladder(Ladder::standard(), 'renditions/{height}p.mp4')` first, then add each rendition's video and one audio stream.
- `MEDIA_PACKAGER=shaka` makes Shaka the driver for `package()`, `exportAsHLS()`, `exportAsDASH()` and `exportAsStreams()` too. Prefer laravel-media's `native` driver (FFmpeg only) unless Shaka-only features are needed: `cbcs`, key rotation, clear lead, DRM systems, live DASH, several audio streams.

## Shaka options

Typed, validated options go through `ShakaOptions`, passed with `withOptions()`:

```php
use Foxws\Shaka\ProtectionSystem;
use Foxws\Shaka\ShakaOptions;

->withKeyRotation(300)->withClearLead(2)
->withOptions(
    ShakaOptions::make()
        ->baseUrls('https://cdn.example.com/streams/1/')
        ->protectionSystems(ProtectionSystem::Widevine, ProtectionSystem::PlayReady)
        ->createSessionKeys()
)
```

- URLs and numbering: `baseUrls()`, `hlsBaseUrl()`, `hlsMediaSequenceNumber()`, `hlsStartTimeOffset()`, `startSegmentNumber()`, `transportStreamTimestampOffset()`.
- Live DASH: `minBufferTime()`, `minimumUpdatePeriod()`, `suggestedPresentationDelay()`, `timeShiftBufferDepth()`, `preservedSegmentsOutsideLiveWindow()`, `utcTimings([...])`, `lowLatencyDashMode()`, `generateStaticLiveMpd()`, `forceClIndex()`. Live playlists: `withHlsPlaylist('master.m3u8', HlsPlaylistType::Live)`.
- Key servers: `widevine($url, $contentIdHex, $policy)`, `playready($url, $programId)`, `aesSigning()` or `rsaSigning()`, `keyServerTls()`, `maxPixels()`, `groupId()`.
- Anything else: `withOption('name', $value)`; `true` passes a flag, `null`/`false` leaves it out. Option and field names are validated, and commas in descriptor values are replaced, so values can't inject arguments.

## Serving

Use laravel-media's signed playlists: `Media::fromDisk('streams')->open("{$id}/master.m3u8")->hlsPlaylist()->resolveMediaUrlsUsing(...)->resolveKeyUrlsUsing(...)->toResponse($request)`, and `dashManifest()` for DASH. DASH players need the key themselves (e.g. Shaka Player's `drm.clearKeys`).

## Queues and errors

- Package in a queued job, with `->timeout($seconds)` below the job's `$timeout` (default `shaka.timeout`).
- Failures throw `Foxws\Media\Exceptions\ProcessFailedException` (use `isRetryable()`); a missing binary throws `ExecutableNotFoundException`. Invalid options, field names or a fragment longer than the segment throw `InvalidArgumentException`.
- `command()` returns the command line with keys redacted. Shaka runs with `--quiet`, so only its warnings are logged.

## Configuration

`php artisan vendor:publish --tag=shaka-config` publishes `config/shaka.php` with `binary` (`SHAKA_PACKAGER_BINARY`, default `packager`) and `timeout` (`SHAKA_PACKAGER_TIMEOUT`). `php artisan media:info` lists the binary. Everything else lives in laravel-media's `config/media.php`.

## Testing

Never run the real binary in tests:

```php
use Foxws\Media\Facades\Media;
use Foxws\Shaka\ShakaExecutable;
use Foxws\Shaka\Testing\FakeShaka;

Storage::fake('media');
$fake = FakeShaka::respond(Media::fake());   // writes placeholder outputs and manifests

// ... run the code under test

$fake->assertRan(ShakaExecutable::Packager, fn (array $arguments) => in_array('--protection_scheme=cbcs', $arguments, true));
$fake->assertSaved('1/master.m3u8', 'media');
$fake->failNext(ShakaExecutable::Packager, 'Packaging Error'); // test failures
```
