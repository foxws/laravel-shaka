---
section: Reference
order: 2
---

# Quick Reference

## Opening media

Called on the `Shaka` facade.

| Method | Purpose |
| --- | --- |
| `fromDisk($disk)` | Disk to read input from. A disk name or a `Filesystem`. |
| `open($paths)` | Open one path, an array of paths, or an `UploadedFile`. |
| `openFromDisk($disk, $paths)` | `fromDisk()` and `open()` in one call. |
| `get()` | The opened `MediaCollection`. |
| `each($items, $callback)` | Run the callback with a fresh opener for each item. |
| `cleanupTemporaryFiles()` | Delete all temporary files. |
| `dynamicHLSPlaylist($disk)` | See [URL Resolvers](url-resolvers.md). |
| `dynamicDASHManifest($disk)` | See [URL Resolvers](url-resolvers.md). |

## Streams

| Method | Purpose |
| --- | --- |
| `addVideoStream($input, $output, $options = [])` | Video from an opened input. |
| `addAudioStream($input, $output, $options = [])` | Audio from an opened input. |
| `addTextStream($input, $output, $options = [])` | Subtitles from an opened input. |
| `addStream(Stream\|array $stream)` | A raw stream descriptor. Paths aren't resolved, so use absolute paths. |

`$options` are [stream descriptor](https://shaka-project.github.io/shaka-packager/html/documentation.html#stream-descriptors) fields, such as `language`, `hls_name`, `dash_roles` or `bandwidth`.

## Output

| Method | Purpose |
| --- | --- |
| `withMpdOutput($path)` | Write a DASH manifest. |
| `withHlsMasterPlaylist($path)` | Write an HLS master playlist. |
| `withSegmentDuration($seconds)` | Segment length. |
| `withFragmentDuration($seconds)` | Fragment length. |
| `withHlsPlaylistType($type)` | `HlsPlaylistType::Vod`, `Event` or `Live`. |
| `withBaseUrls($urls)` | `<BaseURL>` elements in the DASH manifest. |
| `withHlsBaseUrl($url)` | Base URL for HLS segments. |
| `withDefaultLanguage($lang)` | Default audio language. |
| `withDefaultTextLanguage($lang)` | Default subtitle language. |
| `withAllowCodecSwitching()` | Let players switch between codecs. |
| `withAllowApproximateSegmentTimeline()` | Allow small timing differences in the DASH timeline. |
| `withMinBufferTime($seconds)` | DASH `minBufferTime`. |
| `withOption($key, $value)` | Any other Shaka Packager flag. |
| `removeOption($key)` | Remove a flag. |

## Encryption

| Method | Purpose |
| --- | --- |
| `withAESEncryption($keyFilename = 'key', $scheme = null, $label = null)` | Generate a key and encrypt. Returns an `EncryptionKey`. |
| `withKeyRotationDuration($seconds)` | Rotate keys. See [Encryption](aes-encryption.md). |
| `withProtectionScheme($scheme)` | `cenc`, `cbcs`, `cbc1` or `cens`. |
| `withClearLead($seconds)` | Leave the first seconds unencrypted. |

Widevine, PlayReady and key server flags each have a `with*` method too, such as `withEnableWidevineEncryption()`, `withKeyServerUrl()` and `withSigner()`. They map one-to-one to the [Shaka Packager flags](https://shaka-project.github.io/shaka-packager/html/documentation.html).

## Exporting

Called on the result of `export()`.

| Method | Purpose |
| --- | --- |
| `toDisk($disk)` | Disk to write to. Defaults to the input disk. |
| `toPath($path)` | Folder on that disk. Defaults to the root. |
| `withVisibility($visibility)` | `public` or `private`. |
| `afterSaving($callback)` | Runs after upload, with `($exporter, $result)`. |
| `save()` | Run Shaka Packager and upload the output. |
| `getCommand()` | The command as a string, without running it. |
| `dd()` | Dump the command and stop. |

## Artisan

| Command | Purpose |
| --- | --- |
| `shaka:info` | Check the binary, version and temporary directory. |

## Classes

| Class | Purpose |
| --- | --- |
| `Foxws\Shaka\Facades\Shaka` | Entry point. |
| `Foxws\Shaka\Support\Packager` | Holds the streams and runs a job. |
| `Foxws\Shaka\Support\CommandBuilder` | Builds the command line. |
| `Foxws\Shaka\Support\ShakaPackager` | Runs the binary. |
| `Foxws\Shaka\Support\PackagerResult` | Output of a run. Uploads it with `toDisk()`. |
| `Foxws\Shaka\Support\EncryptionKey` | A generated key: `key`, `keyId`, `filePath`. |
| `Foxws\Shaka\Http\DynamicHLSPlaylist` | Rewrites HLS playlists. |
| `Foxws\Shaka\Http\DynamicDASHManifest` | Rewrites DASH manifests. |
