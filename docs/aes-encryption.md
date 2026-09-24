---
section: Advanced
order: 1
---

# Encryption

`withAESEncryption()` generates a random 128-bit key, tells Shaka Packager to encrypt every stream with it, and writes the key to a file that's uploaded with the segments.

```php
use Foxws\Shaka\Facades\Shaka;

$packager = Shaka::fromDisk('media')
    ->open('videos/clip.mp4')
    ->addVideoStream('videos/clip.mp4', 'video.mp4')
    ->addAudioStream('videos/clip.mp4', 'audio.mp4')
    ->withMpdOutput('index.mpd')
    ->withHlsMasterPlaylist('master.m3u8');

$key = $packager->withAESEncryption('key', 'cbcs');

$packager->export()->toDisk('s3')->toPath('streams/clip/')->save();

$video->update([
    'encryption_key' => $key->key,       // 32 hex characters
    'encryption_key_id' => $key->keyId,  // 32 hex characters
]);
```

`withAESEncryption()` returns the key, not the packager, so it can't sit in the middle of a chain. Call it on its own line.

## Arguments

```php
withAESEncryption(string $keyFilename = 'key', ?string $protectionScheme = null, ?string $label = null): EncryptionKey
```

- `$keyFilename` is the name of the key file, and the URI written into HLS playlists (`#EXT-X-KEY`).
- `$protectionScheme` is one of `cenc`, `cbcs`, `cbc1` or `cens`. Leave it `null` to use Shaka Packager's default, `cenc`.
- `$label` is the key label passed to Shaka Packager. You only need it when streams use different keys.

The returned `EncryptionKey` has `key` and `keyId` (both hex) and `filePath`, the local path of the key file.

## Choosing a protection scheme

| Scheme | Plays on |
| --- | --- |
| `cbcs` | Safari and Apple devices, and recent Chrome, Firefox and Edge. The best choice when you serve both HLS and DASH. |
| `cenc` | Chrome, Firefox, Edge and Android. Not Safari's native HLS player. |
| `cbc1`, `cens` | Few players. Avoid them unless you know you need them. |

If you use one set of segments for both HLS and DASH, pick `cbcs`.

## Where the key goes

The key file is written to `cache_files_root` (by default `/dev/shm`, a RAM disk), not next to the segments. `save()` uploads it to the same folder as the segments, then deletes the local copy.

The key file is as sensitive as the video. Keep the bucket private, and only hand out key URLs to users who may watch:

- **HLS:** sign the key URL with `setKeyUrlResolver()` on the [dynamic playlist](url-resolvers.md), or point it at your own route that checks access and returns the key.
- **DASH:** there's no key URL. Give the player the key yourself. In Shaka Player that's a ClearKey setting:

```js
player.configure({
    drm: {
        clearKeys: { [keyId]: key },
    },
});
```

Store `$key->key` and `$key->keyId` so you can serve the key later without reading the file.

## Key rotation

```php
$key = $packager->withAESEncryption('key', 'cbcs');
$packager->withKeyRotationDuration(600);
```

This passes `--crypto_period_duration` to Shaka Packager. Rotation needs `cenc` or `cbcs`.

Be careful with it. With raw keys, Shaka Packager works out the keys for later periods from the one you gave it, and its source marks that method as meant for testing. The package only writes and returns the first key. Play a full video to check it works before relying on rotation. For real key rotation, use a key server such as Widevine or PlayReady through `withOption()`.

## Several streams, one key

Every stream in one run uses the same key. To use a separate key per video, run a separate packaging job per video.

## Full control

`withAESEncryption()` sets these Shaka Packager options for you: `enable_raw_key_encryption`, `keys`, `hls_key_uri`, `clear_lead` (0) and `protection_scheme`. To set them yourself, for example to use your own key, skip `withAESEncryption()` and use `withOption()`:

```php
->withOption('enable_raw_key_encryption', true)
->withOption('keys', "label=:key_id={$keyId}:key={$key}")
->withOption('protection_scheme', 'cbcs')
->withOption('hls_key_uri', 'https://example.com/keys/clip')
```

In that case you write and store the key yourself.

See the [Shaka Packager raw key docs](https://shaka-project.github.io/shaka-packager/html/tutorials/raw_key.html) for all options.
