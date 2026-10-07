---
section: Usage
order: 1
---

# Usage

`shaka()` returns laravel-media's packaging builder with the Shaka Packager driver. Everything in laravel-media's [packaging docs](https://foxws.nl/laravel-media/packaging) works the same way; this page covers what Shaka Packager adds.

## The basic flow

Open the input, add streams, choose the manifests, then save to a directory on the target disk:

```php
use Foxws\Media\Facades\Media;

$result = Media::fromDisk('media')
    ->open('videos/clip.mp4')
    ->shaka()
    ->addVideoStream(output: 'video.mp4')
    ->addAudioStream(output: 'audio.mp4', language: 'en')
    ->withHlsPlaylist('master.m3u8')
    ->withDashManifest('manifest.mpd')
    ->forVod()
    ->toDisk('s3')
    ->save('streams/clip');

$result->paths(); // ['streams/clip/master.m3u8', 'streams/clip/manifest.mpd', ...]
```

- The output defaults to the disk the media was opened from. `withVisibility('private')` sets the visibility of the uploaded files.
- `addStreamsFrom()` probes every opened file and adds its video and audio streams.
- Streams are written as fragmented MP4 (CMAF), so one set of segments works for both DASH and HLS. Use a `.ts` output for MPEG-TS HLS, or `.webm` for WebM DASH.
- There's nothing to clean up: laravel-media deletes temporary files after every queue job and request.

## Several qualities

Shaka Packager doesn't encode, so each quality has to exist as its own file. Encode them with laravel-media's ladder first, then open them all and add each one:

```php
use Foxws\Media\Encoding\Ladder;

Media::fromDisk('media')->open('videos/clip.mp4')->ladder(Ladder::standard(), 'renditions/{height}p.mp4')->save();

Media::fromDisk('media')
    ->open(['renditions/1080p.mp4', 'renditions/720p.mp4', 'renditions/480p.mp4'])
    ->shaka()
    ->addVideoStream('renditions/1080p.mp4', '1080p.mp4')
    ->addVideoStream('renditions/720p.mp4', '720p.mp4')
    ->addVideoStream('renditions/480p.mp4', '480p.mp4')
    ->addAudioStream('renditions/1080p.mp4', 'audio.mp4')
    ->withHlsPlaylist()
    ->withDashManifest()
    ->save('streams/clip');
```

## Stream options

The `options` argument sets Shaka Packager's [stream descriptor](https://shaka-project.github.io/shaka-packager/html/documentation.html#stream-descriptors) fields:

```php
->addAudioStream(output: 'audio-nl.mp4', language: 'nl', options: ['hls_name' => 'Nederlands'])
```

Commas in values are replaced, and field names are checked, so a value can't add other fields or options.

## Subtitles

Add WebVTT files from the same disk as text streams. Use an `.mp4` output: with a plain `.vtt` output, the DASH manifest has no segment index, and Shaka Player skips the track.

```php
->addTextStream('captions/clip.en.vtt', 'subtitles-en.mp4', language: 'en', options: ['dash_roles' => 'subtitle'])
```

## Encryption

laravel-media's `withEncryption()` encrypts with a raw AES key (Common Encryption). Shaka Packager supports every part of it:

```php
use Foxws\Media\Encryption\ProtectionScheme;

$result = Media::fromDisk('media')->open('videos/clip.mp4')
    ->shaka()
    ->addStreamsFrom()
    ->withHlsPlaylist()
    ->withDashManifest()
    ->withEncryption(scheme: ProtectionScheme::Cbcs)
    ->withKeyRotation(300)
    ->withClearLead(2)
    ->save('streams/clip');

$result->encryptionKey(); // the key, to store for your key or license route
```

`cbcs` gives one set of segments that plays with both HLS and DASH, including Safari. With key rotation, Shaka Packager derives later keys from the first one.

## Shaka Packager options

`ShakaOptions` sets Shaka Packager's other options with typed and validated methods. Pass it to `withOptions()`:

```php
use Foxws\Shaka\ProtectionSystem;
use Foxws\Shaka\ShakaOptions;

->withOptions(
    ShakaOptions::make()
        ->baseUrls('https://cdn.example.com/streams/clip/')
        ->protectionSystems(ProtectionSystem::Widevine, ProtectionSystem::PlayReady)
        ->createSessionKeys()
)
```

| Group | Methods |
| --- | --- |
| URLs and numbering | `baseUrls()`, `hlsBaseUrl()`, `hlsMediaSequenceNumber()`, `hlsStartTimeOffset()`, `startSegmentNumber()`, `transportStreamTimestampOffset()` |
| Live DASH | `minBufferTime()`, `minimumUpdatePeriod()`, `suggestedPresentationDelay()`, `timeShiftBufferDepth()`, `preservedSegmentsOutsideLiveWindow()`, `utcTimings()`, `lowLatencyDashMode()`, `generateStaticLiveMpd()`, `forceClIndex()` |
| Encryption | `protectionSystems()`, `createSessionKeys()`, `cryptByteBlock()`, `skipByteBlock()`, `vp9SubsampleEncryption()`, `playreadyExtraHeaderData()`, `iv()`, `pssh()` |
| Key servers | `widevine()`, `enableEntitlementLicense()`, `maxPixels()`, `groupId()`, `aesSigning()`, `rsaSigning()`, `playready()`, `keyServerTls()` |
| Encrypted input | `decrypt()` |

Any other flag can be passed with `withOption('name', $value)`: `true` passes a flag, and `null` or `false` leaves it out.

## Serving the streams

Serve private streams with laravel-media's `DynamicHLSPlaylist` and `DynamicDASHManifest`, which sign every URL in the playlist when it's requested:

```php
return Media::fromDisk('s3')->open('streams/clip/master.m3u8')
    ->hlsPlaylist()
    ->resolveMediaUrlsUsing(fn (string $path) => Storage::disk('s3')->temporaryUrl($path, now()->addHour()))
    ->resolveKeyUrlsUsing(fn (string $key) => route('videos.key', $video))
    ->toResponse($request);
```

## Queues and errors

- Package in a queued job. Give long runs `->timeout($seconds)` below the job's `$timeout` (default: `shaka.timeout`).
- A failed run throws laravel-media's `ProcessFailedException`, with Shaka Packager's error output; use `isRetryable()` to choose between `release()` and `fail()`. A missing binary throws `ExecutableNotFoundException`.
- `save()` dispatches laravel-media's `ExportCompleted` and `ExportFailed`, with the data from `withContext()`. `beforeSaving()` and `afterSaving()` run around it.
- `command()` returns the command line without running it, with keys redacted.
