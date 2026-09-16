---
section: Advanced
order: 1
---

# AES Encryption with Different Codecs

This guide shows how to use `withAESEncryption()` with different video codecs.

## Quick start

```php
use Foxws\Shaka\Filesystem\Media;
use Foxws\Shaka\Filesystem\MediaCollection;
use Foxws\Shaka\Support\Packager;

// Open your media
$media = Media::make('videos', 'input.mp4');
$packager = Packager::create();
$packager->open(MediaCollection::make([$media]));

// Enable encryption (uses cbc1 by default)
$encryptionKey = $packager->withAESEncryption();

// Add your streams
$packager->addStream([
    'in' => $media->getLocalPath(),
    'stream' => 'video',
    'output' => 'encrypted_video.mp4',
]);
```

`withAESEncryption()` returns an `EncryptionKey` value object with `key`, `keyId`, and `filePath` properties.

## Codec-specific examples

### H.264/AVC encryption

H.264 is the most widely supported codec. Use `cbc1` for the broadest compatibility:

```php
$media = Media::make('videos', 'h264_video.mp4');
$packager->open(MediaCollection::make([$media]));

// Generate encryption key
$encryptionKey = $packager->withAESEncryption('h264.key', 'cbc1');

// Add video stream
$packager->addStream([
    'in' => $media->getLocalPath(),
    'stream' => 'video',
    'output' => 'h264_encrypted.mp4',
]);

// The key is now at: $encryptionKey->filePath
// Key: $encryptionKey->key
// Key ID: $encryptionKey->keyId
```

## Key rotation

Key rotation periodically swaps in a new encryption key, so a leaked key only exposes a limited window of content instead of the whole stream:

```php
$media = Media::make('videos', 'input.mp4');
$packager->open(MediaCollection::make([$media]));

// Enable encryption with key rotation every 5 minutes
// Base name 'key' becomes: key_0.key, key_1.key, key_2.key, etc.
$encryptionKey = $packager->withAESEncryption(); // Uses the default 'key' base name
$packager->withKeyRotationDuration(60); // 60 seconds, a balanced choice

$packager->addVideoStream('input.mp4', 'video.mp4');
$packager->withHlsMasterPlaylist('master.m3u8');
$result = $packager->export();
```

### Common rotation intervals

| Interval | When to use it |
| --- | --- |
| `->withKeyRotationDuration(30)` | High security (Apple's recommendation for HLS) |
| `->withKeyRotationDuration(60)` | Balanced security |
| `->withKeyRotationDuration(300)` | Lower overhead (5 minutes), still secure |
| `->withKeyRotationDuration(900)` | Minimal rotation (15 minutes), for low-risk content |

### How it works

Shaka Packager does all of this automatically:

1. Generates a new key at each rotation interval
2. Embeds the key's URI in the manifest (`#EXT-X-KEY` tags for HLS)
3. Encrypts each segment with the key that matches its timing

Players fetch the correct key for each segment on their own — you don't need to coordinate this yourself.

### Collecting rotated keys

When you package with key rotation and then upload the result, every key that was generated is tracked for you:

```php
$packager->withAESEncryption(); // Default: key_0.key, key_1.key, key_2.key...
$packager->withKeyRotationDuration(300);
$packager->addVideoStream('input.mp4', 'video.mp4');
$packager->withHlsMasterPlaylist('master.m3u8');
$result = $packager->export();

// Upload everything (segments + keys) to a private S3 bucket
$result->toDisk('s3', 'videos');

// Get every key that was uploaded, so you can store metadata in your database
$uploadedKeys = $result->getEncryptionKeys();

foreach ($uploadedKeys as $key) {
    EncryptionKey::create([
        'filename' => $key->filename,    // e.g., "key_0.key", "key_1.key"
        'path' => $key->path,            // S3 path: "videos/key_0.key"
        'key' => $key->content,          // Hex-encoded key content
        'video_id' => $video->id,
    ]);
}
```

That's it — `toDisk()` uploads both segments and encryption keys to your **private S3 bucket** in one step.

### Serving keys with dynamic URLs

Use `setKeyUrlResolver()` to generate signed, temporary URLs on demand when you serve a playlist:

```php
use Foxws\Shaka\Http\DynamicHLSPlaylist;

// In your controller
public function playlist(Video $video)
{
    $playlist = (new DynamicHLSPlaylist('s3'))
        ->setKeyUrlResolver(function ($keyFilename) use ($video) {
            // Generate a signed URL on demand (expires in 1 hour)
            return Storage::disk('s3')->temporaryUrl(
                "videos/{$video->id}/{$keyFilename}",
                now()->addHour()
            );
        })
        ->open($video->hls_master_path);

    return $playlist->toResponse(request());
}
```

**Why this is worth doing:**

- URLs are generated fresh on every request.
- You don't need to store or track expiration times yourself.
- Keys stay in a private S3 bucket the whole time.
- Players fetch keys transparently, with no extra work on your end.

See [URL Resolvers](./url-resolvers.md) for the full `DynamicHLSPlaylist` and `DynamicDASHManifest` API.

## Codec-specific examples (continued)

### HEVC/H.265 encryption

HEVC compresses better than H.264. Use `cbcs` for newer devices:

```php
$media = Media::make('videos', 'hevc_video.mp4');
$packager->open(MediaCollection::make([$media]));

// Use cbcs for HEVC (better for newer devices)
$encryptionKey = $packager->withAESEncryption('hevc.key', 'cbcs');

$packager->addStream([
    'in' => $media->getLocalPath(),
    'stream' => 'video',
    'output' => 'hevc_encrypted.mp4',
]);
```

### AV1 encryption

AV1 is a modern, royalty-free codec with strong compression:

```php
$media = Media::make('videos', 'av1_video.mp4');
$packager->open(MediaCollection::make([$media]));

// AV1 works with all protection schemes
$encryptionKey = $packager->withAESEncryption('av1.key', 'cenc');

$packager->addStream([
    'in' => $media->getLocalPath(),
    'stream' => 'video',
    'output' => 'av1_encrypted.mp4',
]);
```

## Protection schemes

A protection scheme controls how encryption is applied to segments, and which players and devices can decrypt them.

| Scheme | Best for | Compatible with |
| --- | --- | --- |
| `cbc1` (default) | HLS, maximum browser compatibility | Safari, Chrome, Firefox, Edge, iOS, Android |
| `cbcs` | Newer platforms, better performance | iOS 10+, Android 7+, modern browsers |
| `cenc` | DASH (the standard scheme) | Most DASH players, EME-enabled browsers |
| `null` (SAMPLE-AES) | HLS without a protection scheme | HLS players, Apple devices |

Examples:

```php
// cbc1 - default, most compatible
$encryptionKey = $packager->withAESEncryption('encryption.key', 'cbc1');

// cbcs - modern devices
$encryptionKey = $packager->withAESEncryption('encryption.key', 'cbcs');

// cenc - common encryption, the DASH standard
$encryptionKey = $packager->withAESEncryption('encryption.key', 'cenc');

// null - SAMPLE-AES, HLS-specific
$encryptionKey = $packager->withAESEncryption('hls.key', null);
```

Every method above also accepts a `Foxws\Shaka\Support\ProtectionScheme` enum case (`ProtectionScheme::Cbc1`, `::Cbcs`, `::Cenc`, `::Cens`) instead of a raw string, if you'd rather avoid typos in the scheme name.

## Multi-codec packaging

Package several codecs with a single shared encryption key:

```php
$h264 = Media::make('videos', 'h264.mp4');
$hevc = Media::make('videos', 'hevc.mp4');
$av1 = Media::make('videos', 'av1.mp4');

$collection = MediaCollection::make([$h264, $hevc, $av1]);
$packager->open($collection);

// One key for all codecs (with an optional label for organization)
$encryptionKey = $packager->withAESEncryption('master.key', 'cbc1', 'multi');

// Add streams for each codec
$packager->addStream([
    'in' => $h264->getLocalPath(),
    'stream' => 'video',
    'output' => 'h264_1080p.mp4',
]);

$packager->addStream([
    'in' => $hevc->getLocalPath(),
    'stream' => 'video',
    'output' => 'hevc_1080p.mp4',
]);

$packager->addStream([
    'in' => $av1->getLocalPath(),
    'stream' => 'video',
    'output' => 'av1_1080p.mp4',
]);

// All streams are encrypted with the same key
$result = $packager->export();
```

## Separate keys per codec

For more advanced setups, give each codec its own key:

```php
// H.264 with its own key
$packagerH264 = Packager::create();
$packagerH264->open(MediaCollection::make([Media::make('videos', 'h264.mp4')]));
$keyH264 = $packagerH264->withAESEncryption('h264.key');

// HEVC with its own key
$packagerHevc = Packager::create();
$packagerHevc->open(MediaCollection::make([Media::make('videos', 'hevc.mp4')]));
$keyHevc = $packagerHevc->withAESEncryption('hevc.key');

// AV1 with its own key
$packagerAv1 = Packager::create();
$packagerAv1->open(MediaCollection::make([Media::make('videos', 'av1.mp4')]));
$keyAv1 = $packagerAv1->withAESEncryption('av1.key');

// Each codec now has its own, unique encryption key
```

## HLS with encryption

A complete HLS packaging example with encryption:

```php
$media = Media::make('videos', 'video.mp4');
$packager->open(MediaCollection::make([$media]));

// Generate encryption key
$encryptionKey = $packager->withAESEncryption('encryption.key', 'cbc1');

// Add video variants
$packager->builder()
    ->addVideoStream($media->getLocalPath(), 'video_1080p.m3u8', ['bandwidth' => '5000000'])
    ->addVideoStream($media->getLocalPath(), 'video_720p.m3u8', ['bandwidth' => '3000000'])
    ->addAudioStream($media->getLocalPath(), 'audio.m3u8', ['language' => 'en'])
    ->withHlsMasterPlaylist('master.m3u8');

$result = $packager->export();

// The encryption key is referenced in the HLS playlist.
// Players fetch 'encryption.key' to decrypt segments.
```

## DASH with encryption

A complete DASH packaging example with encryption:

```php
$media = Media::make('videos', 'video.mp4');
$packager->open(MediaCollection::make([$media]));

// Use cenc for DASH
$encryptionKey = $packager->withAESEncryption('encryption.key', 'cenc');

$packager->builder()
    ->addVideoStream($media->getLocalPath(), 'video_1080p.mp4', ['bandwidth' => '5000000'])
    ->addVideoStream($media->getLocalPath(), 'video_720p.mp4', ['bandwidth' => '3000000'])
    ->addAudioStream($media->getLocalPath(), 'audio.mp4', ['language' => 'en'])
    ->withMpdOutput('manifest.mpd');

$result = $packager->export();
```

## Key storage

The encryption key is stored in two places:

1. **Cache storage** (RAM disk when available) — fast, temporary storage used while the key is generated.
   - Default: `/dev/shm` on Linux, or your system's temp directory otherwise.
   - Configure via the `PACKAGER_CACHE_FILES_ROOT` environment variable.

2. **Export directory** — a copy that goes out with the rest of the packaging output, for upload to cloud storage.
   - Automatically included whenever you export to S3 or other storage.
   - The key file's name is customizable via the `$keyFilename` parameter.

```php
$encryptionKey = $packager->withAESEncryption('my-custom-key.bin');

// Key is in cache: /dev/shm/random-hash/my-custom-key.bin
// Key is in export: /tmp/packager-temp/random-hash/my-custom-key.bin
// Both copies contain identical key data

echo $encryptionKey->filePath; // Cache path
echo $encryptionKey->key;      // Hex-encoded 128-bit key
echo $encryptionKey->keyId;    // Hex-encoded key ID
```

### Secure storage with signed URLs

In production, store keys in a **private S3 bucket** and use `setKeyUrlResolver()` to generate signed URLs on demand, rather than exposing the bucket publicly:

```php
use Foxws\Shaka\Http\DynamicHLSPlaylist;
use Illuminate\Support\Facades\Storage;

// In your controller
public function streamVideo(Video $video)
{
    $this->authorize('view', $video);

    $playlist = (new DynamicHLSPlaylist('s3'))
        ->setKeyUrlResolver(function ($keyFilename) use ($video) {
            // Generate a fresh signed URL for each key request
            return Storage::disk('s3')->temporaryUrl(
                "videos/{$video->id}/{$keyFilename}",
                now()->addHour()
            );
        })
        ->setMediaUrlResolver(function ($segmentFilename) use ($video) {
            // Also sign segment URLs, so the whole chain stays private
            return Storage::disk('s3')->temporaryUrl(
                "videos/{$video->id}/{$segmentFilename}",
                now()->addHours(2)
            );
        })
        ->open($video->hls_master_path);

    return $playlist->toResponse(request());
}
```

**Why this is worth doing:**

- Keys are never publicly reachable.
- URLs are generated fresh on each request.
- You don't need to track expiration times yourself.
- Players fetch keys and segments transparently.
- You can revoke access instantly through your own authorization checks.

## Troubleshooting

### Codec not supported

Confirm your input video is actually encoded with the codec you expect:

```bash
ffmpeg -i video.mp4
# Look for "Video: h264" or "Video: hevc" or "Video: av1"
```

### Protection scheme issues

Different devices support different protection schemes:

| Device/player | Recommended scheme |
| --- | --- |
| Safari/iOS | `cbc1` or `null` (SAMPLE-AES) |
| Chrome/Android | `cbc1`, `cbcs`, or `cenc` |
| DASH players | `cenc` |
| HLS players | `cbc1` or `null` |

### Key file not found

Make sure the key file is copied into your export directory:

```php
// The package copies the key for you automatically
$encryptionKey = $packager->withAESEncryption('encryption.key');

// The key is now in both the cache and export temp directories.
// When you export/upload, the key file is included automatically.
```

See the [Troubleshooting](./troubleshooting.md) guide for more issues and solutions.

## API reference

```php
/**
 * Enable AES-128 encryption with auto-generated keys.
 *
 * When used with withKeyRotationDuration(), the filename becomes a base name
 * (e.g., 'key' becomes 'key_0.key', 'key_1.key', 'key_2.key', etc.).
 *
 * @param string $keyFilename Base name for key file (default: 'key')
 * @param ProtectionScheme|string|null $protectionScheme 'cbc1', 'cbcs', 'cenc', 'cens', or null for SAMPLE-AES
 * @param string|null $label Optional label for multi-key scenarios
 */
public function withAESEncryption(
    string $keyFilename = 'key',
    ProtectionScheme|string|null $protectionScheme = 'cbc1',
    ?string $label = null
): EncryptionKey

/**
 * Enable key rotation for encryption.
 *
 * @param int $seconds Duration in seconds before rotating to a new key
 * @return self
 */
public function withKeyRotationDuration(int $seconds): self
```

`EncryptionKey` is a readonly value object with three properties:

```php
final readonly class EncryptionKey
{
    public string $key;
    public string $keyId;
    public ?string $filePath;
}
```

`PackagerResult::getEncryptionKeys()` returns an array of `Foxws\Shaka\Support\EncryptionKeyFile` value objects, each with `path`, `filename`, and `content` (hex-encoded) properties.

## Related documentation

- [Configuration Guide](./configuration.md)
- [Shaka Packager Encryption Docs](https://shaka-project.github.io/shaka-packager/html/tutorials/raw_key.html)
