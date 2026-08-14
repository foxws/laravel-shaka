---
sidebar_position: 3
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

Add multiple video streams with different bandwidths to produce a
multi-quality adaptive stream:

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

## Working with different disks

`fromDisk()` sets the source disk, and `toDisk()`/`toPath()` control where
the packaged output is saved. Each can be a different Laravel filesystem
disk (local, S3, or any custom disk):

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

`withAESEncryption()` returns an `EncryptionKey` value object (not `$this`), so
it breaks the fluent chain — call it on its own line:

```php
// Basic encryption with auto-generated AES-128 key
$streamer = Shaka::open('input.mp4')
    ->addVideoStream('input.mp4', 'video.mp4')
    ->addAudioStream('input.mp4', 'audio.mp4')
    ->withHlsMasterPlaylist('master.m3u8');

$encryptionKey = $streamer->withAESEncryption(); // Auto-generates key with 'cbc1' scheme

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

See the [AES Encryption guide](./aes-encryption.md) for complete documentation,
including codec-specific examples and key storage details.

## Dynamic URL resolvers (HLS & DASH)

Serve encrypted streaming content with S3 signed URLs by resolving key,
media, and playlist/manifest URLs on demand:

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

See the [URL Resolvers guide](./url-resolvers.md) for the full API and more
use cases (CDN integration, multi-tenant applications, dynamic key rotation).

## Next steps

- [Quick Reference](./quick-reference.md) - every available method, at a glance
- [Queue Integration](./queue-integration.md) - run packaging jobs in the background
