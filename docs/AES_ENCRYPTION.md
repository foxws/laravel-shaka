# AES Encryption with Different Codecs

This guide demonstrates how to use the `withAESEncryption()` method with various video codecs.

## Quick Start

```php
use Foxws\Shaka\Filesystem\Media;
use Foxws\Shaka\Filesystem\MediaCollection;
use Foxws\Shaka\Support\Packager;

// Open your media
$media = Media::make('videos', 'input.mp4');
$packager = Packager::create();
$packager->open(MediaCollection::make([$media]));

// Enable encryption (uses cbc1 by default)
$keyData = $packager->withAESEncryption();

// Add your streams
$packager->addStream([
    'in' => $media->getLocalPath(),
    'stream' => 'video',
    'output' => 'encrypted_video.mp4',
]);
```

## Codec-Specific Examples

### H.264/AVC Encryption

H.264 is the most widely supported codec. Use `cbc1` for maximum compatibility:

```php
$media = Media::make('videos', 'h264_video.mp4');
$packager->open(MediaCollection::make([$media]));

// Generate encryption key
$keyData = $packager->withAESEncryption('h264.key', 'cbc1');

// Add video stream
$packager->addStream([
    'in' => $media->getLocalPath(),
    'stream' => 'video',
    'output' => 'h264_encrypted.mp4',
]);

// The key is now at: $keyData['file_path']
// Key: $keyData['key']
// Key ID: $keyData['key_id']
```

## Key Rotation

Automatic key rotation enhances security by periodically generating new encryption keys:

```php
$media = Media::make('videos', 'input.mp4');
$packager->open(MediaCollection::make([$media]));

// Enable encryption with key rotation every 5 minutes
$keyData = $packager
    ->withAESEncryption('encryption.key', 'cbc1')
    ->withKeyRotationDuration(300); // 300 seconds = 5 minutes

$packager->addVideoStream('input.mp4', 'video.mp4');
$packager->withHlsMasterPlaylist('master.m3u8');
$result = $packager->export();
```

### Common Rotation Intervals

```php
// 5 minutes - high security, more keys
->withKeyRotationDuration(300)

// 10 minutes - balanced
->withKeyRotationDuration(600)

// 30 minutes - fewer keys, longer segments
->withKeyRotationDuration(1800)

// 1 hour - minimal rotation
->withKeyRotationDuration(3600)
```

### How It Works

Shaka Packager automatically:

1. Generates a new key at each rotation interval
2. Embeds key URIs in the manifest (#EXT-X-KEY tags for HLS)
3. Encrypts segments with the appropriate key based on timing

Players automatically fetch the correct key for each segment.

### Collecting Rotated Keys

After packaging with key rotation, the keys are automatically tracked when uploading:

```php
$result = $packager
    ->withAESEncryption('encryption.key', 'cbc1')
    ->withKeyRotationDuration(300)
    ->addVideoStream('input.mp4', 'video.mp4')
    ->withHlsMasterPlaylist('master.m3u8')
    ->export();

// Upload everything (segments + keys) to S3
$result->toDisk('s3', 'public');

// Get all keys that were uploaded - ready for database storage
$uploadedKeys = $result->getUploadedEncryptionKeys();

foreach ($uploadedKeys as $key) {
    EncryptionKey::create([
        'filename' => $key['filename'],    // e.g., "encryption_0.key"
        'path' => $key['path'],            // S3 path: "videos/encryption_0.key"
        'key' => $key['content'],          // Hex-encoded key content
        'video_id' => $video->id,
    ]);
}
```

That's it! `toDisk()` automatically uploads both segments and encryption keys, then `getUploadedEncryptionKeys()` gives you everything you need to store in your database.

## Codec-Specific Examples (continued)

### H.264/AVC Encryption (continued)

```php

```

### HEVC/H.265 Encryption

HEVC offers better compression. Use `cbcs` for modern devices:

```php
$media = Media::make('videos', 'hevc_video.mp4');
$packager->open(MediaCollection::make([$media]));

// Use cbcs for HEVC (better for newer devices)
$keyData = $packager->withAESEncryption('hevc.key', 'cbcs');

$packager->addStream([
    'in' => $media->getLocalPath(),
    'stream' => 'video',
    'output' => 'hevc_encrypted.mp4',
]);
```

### AV1 Encryption

AV1 is a modern, royalty-free codec with excellent compression:

```php
$media = Media::make('videos', 'av1_video.mp4');
$packager->open(MediaCollection::make([$media]));

// AV1 works with all protection schemes
$keyData = $packager->withAESEncryption('av1.key', 'cenc');

$packager->addStream([
    'in' => $media->getLocalPath(),
    'stream' => 'video',
    'output' => 'av1_encrypted.mp4',
]);
```

## Protection Schemes

### cbc1 (Default - Most Compatible)

Best for HLS and maximum browser compatibility:

```php
$keyData = $packager->withAESEncryption('encryption.key', 'cbc1');
// Compatible with: Safari, Chrome, Firefox, Edge, iOS, Android
```

### cbcs (Modern Devices)

For newer platforms with better performance:

```php
$keyData = $packager->withAESEncryption('encryption.key', 'cbcs');
// Compatible with: iOS 10+, Android 7+, modern browsers
```

### cenc (Common Encryption)

DASH standard, widely supported:

```php
$keyData = $packager->withAESEncryption('encryption.key', 'cenc');
// Compatible with: Most DASH players, EME-enabled browsers
```

### SAMPLE-AES (HLS-Specific)

For HLS without a protection scheme:

```php
$keyData = $packager->withAESEncryption('hls.key', null);
// Compatible with: HLS players, Apple devices
```

## Multi-Codec Packaging

Package multiple codecs with a single encryption key:

```php
$h264 = Media::make('videos', 'h264.mp4');
$hevc = Media::make('videos', 'hevc.mp4');
$av1 = Media::make('videos', 'av1.mp4');

$collection = MediaCollection::make([$h264, $hevc, $av1]);
$packager->open($collection);

// One key for all codecs (with optional label for organization)
$keyData = $packager->withAESEncryption('master.key', 'cbc1', 'multi');

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

// All streams will be encrypted with the same key
$result = $packager->package();
```

## Separate Keys Per Codec

For advanced scenarios, use different keys for each codec:

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

// Each codec has unique encryption keys
```

## HLS with Encryption

Complete HLS packaging with encryption:

```php
$media = Media::make('videos', 'video.mp4');
$packager->open(MediaCollection::make([$media]));

// Generate encryption key
$keyData = $packager->withAESEncryption('encryption.key', 'cbc1');

// Add video variants
$packager->builder()
    ->addVideoStream($media->getLocalPath(), 'video_1080p.m3u8', ['bandwidth' => '5000000'])
    ->addVideoStream($media->getLocalPath(), 'video_720p.m3u8', ['bandwidth' => '3000000'])
    ->addAudioStream($media->getLocalPath(), 'audio.m3u8', ['language' => 'en'])
    ->withHlsMasterPlaylist('master.m3u8');

$result = $packager->package();

// The encryption key will be referenced in the HLS playlist
// Player will fetch 'encryption.key' to decrypt segments
```

## DASH with Encryption

Complete DASH packaging with encryption:

```php
$media = Media::make('videos', 'video.mp4');
$packager->open(MediaCollection::make([$media]));

// Use cenc for DASH
$keyData = $packager->withAESEncryption('encryption.key', 'cenc');

$packager->builder()
    ->addVideoStream($media->getLocalPath(), 'video_1080p.mp4', ['bandwidth' => '5000000'])
    ->addVideoStream($media->getLocalPath(), 'video_720p.mp4', ['bandwidth' => '3000000'])
    ->addAudioStream($media->getLocalPath(), 'audio.mp4', ['language' => 'en'])
    ->withMpdOutput('manifest.mpd');

$result = $packager->package();
```

## Key Storage

The encryption key is stored in two locations:

1. **Cache storage** (RAM disk if available): Fast temporary storage for key generation
    - Default: `/dev/shm` (Linux) or system temp directory
    - Configure via: `PACKAGER_CACHE_FILES_ROOT` environment variable

2. **Export directory**: Copied to packaging output for cloud storage upload
    - Automatically included when exporting to S3 or other storage
    - Key file name is customizable via the `$keyFilename` parameter

```php
$keyData = $packager->withAESEncryption('my-custom-key.bin');

// Key is in cache: /dev/shm/random-hash/my-custom-key.bin
// Key is in export: /tmp/packager-temp/random-hash/my-custom-key.bin
// Both contain identical key data

echo $keyData['file_path']; // Cache path
echo $keyData['key'];       // Hex-encoded 128-bit key
echo $keyData['key_id'];    // Hex-encoded key ID
```

## Troubleshooting

### Codec Not Supported

Ensure your input video is actually encoded with the expected codec:

```bash
ffmpeg -i video.mp4
# Look for "Video: h264" or "Video: hevc" or "Video: av1"
```

### Protection Scheme Issues

Different devices support different protection schemes:

- **Safari/iOS**: Use `cbc1` or null (SAMPLE-AES)
- **Chrome/Android**: Use `cbc1`, `cbcs`, or `cenc`
- **DASH Players**: Use `cenc`
- **HLS Players**: Use `cbc1` or null

### Key File Not Found

Ensure the key file is copied to your export directory:

```php
// The package automatically copies the key for you
$keyData = $packager->withAESEncryption('encryption.key');

// Key is now in both cache and export temp directories
// When you export/upload, the key file will be included
```

## API Reference

```php
/**
 * Enable AES-128 encryption with auto-generated keys.
 *
 * @param string $keyFilename Name of the key file (default: 'encryption.key')
 * @param string|null $protectionScheme 'cbc1', 'cbcs', 'cenc', or null for SAMPLE-AES
 * @param string|null $label Optional label for multi-key scenarios
 * @return array{key: string, key_id: string, file_path: string}
 */
public function withAESEncryption(
    string $keyFilename = 'encryption.key',
    ?string $protectionScheme = 'cbc1',
    ?string $label = null
): array

/**
 * Enable key rotation for encryption.
 *
 * @param int $seconds Duration in seconds before rotating to a new key
 * @return self
 */
public function withKeyRotationDuration(int $seconds): self
```

## Related Documentation

- [Configuration Guide](../docs/CONFIGURATION.md)
- [Shaka Packager Encryption Docs](https://shaka-project.github.io/shaka-packager/html/tutorials/raw_key.html)
