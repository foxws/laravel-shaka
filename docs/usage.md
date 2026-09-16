---
section: Usage
order: 1
---

# Usage

## Basic usage

```php
use Foxws\Shaka\Facades\Shaka;

$result = Shaka::open('input.mp4')
    ->addVideoStream('input.mp4', 'video.mp4')
    ->addAudioStream('input.mp4', 'audio.mp4')
    ->withHlsMasterPlaylist('master.m3u8')
    ->export()
    ->save();
```

## Adaptive bitrate streaming

Add several video streams with different bandwidths to produce one adaptive stream with multiple quality levels:

```php
$result = Shaka::open('input.mp4')
    ->addVideoStream('input.mp4', 'video_1080p.mp4', ['bandwidth' => '5000000'])
    ->addVideoStream('input.mp4', 'video_720p.mp4', ['bandwidth' => '3000000'])
    ->addVideoStream('input.mp4', 'video_480p.mp4', ['bandwidth' => '1500000'])
    ->addAudioStream('input.mp4', 'audio.mp4')
    ->withHlsMasterPlaylist('master.m3u8')
    ->withSegmentDuration(6)
    ->export()
    ->save();
```

## Dual DASH + HLS output (CMAF)

By default, video and audio are packaged as CMAF (fragmented MP4). That means the same set of segments can be described by both a DASH manifest and an HLS master playlist. Chain `withMpdOutput()` and `withHlsMasterPlaylist()` on the same builder to package both from a single `export()` call — one packaging pass, no extra transcoding, just an extra manifest file:

```php
$result = Shaka::open('input.mp4')
    ->addVideoStream('input.mp4', 'video.mp4')
    ->addAudioStream('input.mp4', 'audio.mp4')
    ->withMpdOutput('manifest.mpd')
    ->withHlsMasterPlaylist('master.m3u8')
    ->export()
    ->save();
```

If you only need one format, only set that one — Shaka Packager only generates the manifest(s) you ask for.

## Working with different disks

`fromDisk()` sets where the source file is read from. `toDisk()` and `toPath()` control where the packaged output is written. Each can point at a different Laravel filesystem disk — local, S3, or any custom disk:

```php
$result = Shaka::fromDisk('s3')
    ->open('videos/input.mp4')
    ->addVideoStream('videos/input.mp4', 'video.mp4')
    ->addAudioStream('videos/input.mp4', 'audio.mp4')
    ->withHlsMasterPlaylist('master.m3u8')
    ->export()
    ->toDisk('export') // Save output to a different disk (e.g., local, s3, etc.)
    ->toPath('exports/') // (Optional) Save to a subdirectory on the target disk
    ->save();
```

## HLS with encryption

`withAESEncryption()` returns an `EncryptionKey` value object, not `$this` — so it breaks the fluent chain. Call it on its own line:

```php
// Basic encryption with an auto-generated AES-128 key
$streamer = Shaka::open('input.mp4')
    ->addVideoStream('input.mp4', 'video.mp4')
    ->addAudioStream('input.mp4', 'audio.mp4')
    ->withHlsMasterPlaylist('master.m3u8');

$encryptionKey = $streamer->withAESEncryption(); // Auto-generates a key with the 'cbc1' scheme

$streamer->export()->save();

// With key rotation (generates key_0.key, key_1.key, etc.)
$streamer = Shaka::open('input.mp4')
    ->addVideoStream('input.mp4', 'video.mp4')
    ->addAudioStream('input.mp4', 'audio.mp4')
    ->withHlsMasterPlaylist('master.m3u8');

$encryptionKey = $streamer->withAESEncryption();
$streamer->withKeyRotationDuration(60); // Rotate every 60 seconds

$streamer->export()->toDisk('s3')->save();
```

See the [AES Encryption guide](./aes-encryption.md) for the full picture, including codec-specific examples and where keys are stored.

## Dynamic URL resolvers (HLS & DASH)

Serve encrypted streaming content behind S3 signed URLs by resolving key, media, and playlist/manifest URLs on demand, at request time:

**HLS example:**

```php
use Foxws\Shaka\Http\DynamicHLSPlaylist;
use Illuminate\Support\Facades\Storage;

public function playlist(Video $video)
{
    return (new DynamicHLSPlaylist('s3'))
        ->open("videos/{$video->id}/master.m3u8")
        ->setKeyUrlResolver(fn ($key) => Storage::disk('s3')->temporaryUrl(
            "videos/{$video->id}/{$key}",
            now()->addHour()
        ))
        ->setMediaUrlResolver(fn ($file) => Storage::disk('s3')->temporaryUrl(
            "videos/{$video->id}/{$file}",
            now()->addHours(2)
        ))
        ->toResponse(request());
}
```

**DASH example:**

```php
use Foxws\Shaka\Http\DynamicDASHManifest;
use Illuminate\Support\Facades\Storage;

public function manifest(Video $video)
{
    return (new DynamicDASHManifest('s3'))
        ->open("videos/{$video->id}/manifest.mpd")
        ->setKeyUrlResolver(fn ($key) => Storage::disk('s3')->temporaryUrl(
            "videos/{$video->id}/{$key}",
            now()->addHour()
        ))
        ->setMediaUrlResolver(fn ($file) => Storage::disk('s3')->temporaryUrl(
            "videos/{$video->id}/{$file}",
            now()->addHours(2)
        ))
        ->setInitUrlResolver(fn ($file) => Storage::disk('s3')->temporaryUrl(
            "videos/{$video->id}/{$file}",
            now()->addHours(2)
        ))
        ->toResponse(request());
}
```

See the [URL Resolvers guide](./url-resolvers.md) for the full API and more use cases (CDN integration, multi-tenant apps, dynamic key rotation).

## Next steps

- [Quick Reference](./quick-reference.md) - every available method, at a glance
- [Queue Integration](./queue-integration.md) - run packaging jobs in the background
