---
section: Reference
order: 2
---

# Upgrading from 2.x

3.0 is built on [foxws/laravel-media](https://github.com/foxws/laravel-media). The package now only adds Shaka Packager, as a driver for laravel-media's packaging builder. Opening media, disks, temporary files, running processes, uploads, encryption keys, signed playlists, events and fakes come from laravel-media.

## Requirements

- Laravel 13 (12 is no longer supported), PHP 8.4.
- `foxws/laravel-media` ^0.3.4 is installed with the package.

## Packaging

The `Shaka` facade, `MediaOpener`, `Packager`, `MediaExporter` and `CommandBuilder` are gone. Open media with laravel-media and call `shaka()`, then pass the directory to `save()`:

```php
// 2.x
$packager = Shaka::fromDisk('media')->open('videos/clip.mp4');

try {
    $packager
        ->addVideoStream('videos/clip.mp4', 'video.mp4')
        ->addAudioStream('videos/clip.mp4', 'audio.mp4')
        ->withMpdOutput('index.mpd')
        ->withHlsMasterPlaylist('master.m3u8')
        ->export()
        ->toDisk('s3')
        ->toPath('streams/clip/')
        ->save();
} finally {
    $packager->cleanupTemporaryFiles();
}

// 3.0
Media::fromDisk('media')
    ->open('videos/clip.mp4')
    ->shaka()
    ->addVideoStream('videos/clip.mp4', 'video.mp4')
    ->addAudioStream('videos/clip.mp4', 'audio.mp4')
    ->withDashManifest('index.mpd')
    ->withHlsPlaylist('master.m3u8')
    ->toDisk('s3')
    ->save('streams/clip');
```

- `cleanupTemporaryFiles()` is no longer needed: laravel-media deletes temporary files after every queue job and request.
- `export()` is gone: `toDisk()`, `withVisibility()`, `afterSaving()` and `save()` are on the builder. `save()` returns an `ExportResult` (the disk and the saved paths, manifests first) instead of the opener.
- The first argument of `add*Stream()` is optional: it defaults to the first opened file. A path you didn't open is read from the same disk, instead of being passed to Shaka Packager as a local path.
- `addStream()` and the `Stream` value object are removed. Use `addVideoStream()`, `addAudioStream()` or `addTextStream()`, and pass descriptor fields as `options`.

## Methods

| 2.x | 3.0 |
| --- | --- |
| `withMpdOutput()` | `withDashManifest()` |
| `withHlsMasterPlaylist()`, `withHlsPlaylistType()` | `withHlsPlaylist($path, HlsPlaylistType::Vod)` |
| `withSegmentDuration()`, `withFragmentDuration()` | `segmentDuration()`, `fragmentDuration()` |
| `withDefaultLanguage()`, `withDefaultTextLanguage()` | `defaultLanguage()`, `defaultTextLanguage()` |
| `withAllowCodecSwitching()`, `withAllowApproximateSegmentTimeline()` | `allowCodecSwitching()`, `approximateSegmentTimeline()`, or both with `forVod()` |
| `withAESEncryption($keyFile, $scheme, $label)` | `withEncryption(keyFile: $keyFile, scheme: ProtectionScheme::Cbcs, label: $label)` |
| `withProtectionScheme()`, `withHlsKeyUri()` | `withEncryption(scheme: ..., keyUri: ...)` |
| `withKeyRotationDuration()`, `withCryptoPeriodDuration()` | `withKeyRotation()` |
| `withClearLead()` | unchanged |
| `withBaseUrls()`, `withHlsBaseUrl()`, the live DASH and encryption options | methods on `ShakaOptions`, passed with `withOptions()`, mostly the same names without `with` (see [Usage](usage.md#shaka-packager-options)) |
| `withProtectionSystems('Widevine,PlayReady')` | `protectionSystems(ProtectionSystem::Widevine, ProtectionSystem::PlayReady)` |
| `withUtcTimings('scheme=value')` | `utcTimings(['scheme' => 'value'])` |
| `withEnableWidevineEncryption()`, `withKeyServerUrl()`, `withContentId()`, `withPolicy()` | `widevine($keyServerUrl, $contentId, $policy)` |
| `withEnablePlayreadyEncryption()`, `withPlayreadyServerUrl()`, `withProgramIdentifier()` | `playready($serverUrl, $programIdentifier)` |
| `withSigner()` with `withAesSigningKey()` and `withAesSigningIv()`, or `withRsaSigningKeyPath()`, `withSigningCredentials()` | `aesSigning($signer, $key, $iv)` or `rsaSigning($signer, $keyPath)` |
| `withCaFile()`, `withClientCertFile()`, `withClientCertPrivateKeyFile()`, `withClientCertPrivateKeyPassword()` | `keyServerTls(...)` |
| `withMaxSdPixels()`, `withMaxHdPixels()`, `withMaxUhd1Pixels()` | `maxPixels(sd: ..., hd: ..., uhd1: ...)` |
| `withEnableRawKeyDecryption()`, `withEnableWidevineDecryption()` | `decrypt()`, `decrypt(widevine: true)` |
| `withEnableRawKeyEncryption()`, `withKeys()` | set by `withEncryption()` |
| `withOption()` | unchanged; `removeOption()` is `withOption($name, null)` |
| `getCommand()`, `dd()` | `command()` |

`withAESEncryption()` returned the key; `withEncryption()` generates one unless you pass an `EncryptionKey`, and `$result->encryptionKey()` returns it after `save()`. `getEncryptionKeys()` is gone: with key rotation, Shaka Packager derives the later keys from that one.

## Serving streams

`Shaka::dynamicHLSPlaylist()` and `Shaka::dynamicDASHManifest()` are replaced by laravel-media's:

```php
// 2.x
Shaka::dynamicHLSPlaylist('s3')->setMediaUrlResolver($resolver)->open('streams/clip/master.m3u8')->toResponse($request);

// 3.0
Media::fromDisk('s3')->open('streams/clip/master.m3u8')->hlsPlaylist()->resolveMediaUrlsUsing($resolver)->toResponse($request);
```

`setKeyUrlResolver()`, `setMediaUrlResolver()` and `setPlaylistUrlResolver()` are now `resolveKeyUrlsUsing()`, `resolveMediaUrlsUsing()` and `resolvePlaylistUrlsUsing()`. Resolvers receive the file's path on the disk.

## Events and exceptions

- `PackagingStarted`, `PackagingCompleted` and `PackagingFailed` are removed. Listen to laravel-media's `ProcessStarted`, `ProcessCompleted` and `ProcessFailed`, or `ExportCompleted` and `ExportFailed` (with `withContext()`).
- Shaka Packager failures throw laravel-media's `ProcessFailedException`, with `isRetryable()`, instead of `RuntimeException`. A missing binary throws laravel-media's `ExecutableNotFoundException`.
- Invalid options and field names throw `InvalidArgumentException`.

## Configuration

`config/laravel-shaka.php` is now `config/shaka.php`, published with the `shaka-config` tag:

| 2.x | 3.0 |
| --- | --- |
| `packager.binaries` (`PACKAGER_PATH`) | `binary` (`SHAKA_PACKAGER_BINARY`) |
| `timeout` (`PACKAGER_TIMEOUT`) | `timeout` (`SHAKA_PACKAGER_TIMEOUT`) |
| `segment_duration`, `packager_options` | `segmentDuration()` and `withOptions()` per run |
| `log_channel`, `temporary_files_*`, `cache_files_*`, the S3 upload options | laravel-media's `config/media.php` |
| `force_generic_input` | removed: inputs with unsafe names are always linked under a plain name |

`php artisan shaka:info` is replaced by `php artisan media:info`, which lists `packager`.

## Tests

Replace `Process::fake()` with `FakeShaka::respond(Media::fake())`. See [Testing](testing.md).
