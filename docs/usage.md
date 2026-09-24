---
section: Usage
order: 1
---

# Usage

## The basic flow

Every job follows the same steps: open the input, add streams, choose the manifests, then export and save.

```php
use Foxws\Shaka\Facades\Shaka;

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
```

- `fromDisk()` picks the disk to read from. Without it, your default filesystem disk is used.
- The first argument of `addVideoStream()` and friends is a path you passed to `open()`. The second is the output file name.
- `export()` returns the exporter. Nothing runs until you call `save()`.
- `save()` runs Shaka Packager, copies the output to the target disk and deletes the local copy.
- `cleanupTemporaryFiles()` removes anything left behind, such as downloaded inputs or the output of a failed run. Always call it in `finally`, especially in queue workers.

## Where the output goes

Shaka Packager writes to a local temporary directory first. `save()` then copies everything to the target disk:

- `toDisk()` sets the target disk. Without it, the input disk is used.
- `toPath()` sets the folder on that disk. Without it, files land in the root of the disk.
- `withVisibility('private')` sets the visibility of the uploaded files.

S3 disks upload in parallel, and large files use multipart uploads. On a local disk, files are moved instead of copied. See [Configuration](configuration.md) to tune this.

## DASH and HLS together

Streams are written as fragmented MP4 (CMAF), so one set of segments works for both DASH and HLS. Set both outputs and you get both manifests from a single run, with no extra segments:

```php
->withMpdOutput('index.mpd')
->withHlsMasterPlaylist('master.m3u8')
```

Set only one of them if you only need one format.

## Several qualities

Shaka Packager doesn't encode, so each quality has to exist as its own file. Encode the qualities first, open them all, and add a stream for each:

```php
Shaka::fromDisk('media')
    ->open(['videos/clip-1080p.mp4', 'videos/clip-720p.mp4', 'videos/clip-480p.mp4'])
    ->addVideoStream('videos/clip-1080p.mp4', '1080p.mp4')
    ->addVideoStream('videos/clip-720p.mp4', '720p.mp4')
    ->addVideoStream('videos/clip-480p.mp4', '480p.mp4')
    ->addAudioStream('videos/clip-1080p.mp4', 'audio.mp4')
    ->withMpdOutput('index.mpd')
    ->withHlsMasterPlaylist('master.m3u8')
    ->export()
    ->save();
```

Adding the same input twice with a different `bandwidth` doesn't create a new quality. It writes the same stream twice with a different label.

## Stream options

The third argument sets [stream descriptor](https://shaka-project.github.io/shaka-packager/html/documentation.html#stream-descriptors) fields:

```php
->addAudioStream('videos/clip.mp4', 'audio-nl.mp4', ['language' => 'nl', 'hls_name' => 'Nederlands'])
```

Only add streams that exist. A file without an audio track fails when you add an audio stream for it. Check with `ffprobe` or [Laravel FFMpeg](https://github.com/protonemedia/laravel-ffmpeg) first if you're not sure.

## Subtitles

Open the subtitle file along with the video, then add it as a text stream:

```php
Shaka::fromDisk('media')
    ->open(['videos/clip.mp4', 'captions/clip.en.vtt'])
    ->addVideoStream('videos/clip.mp4', 'video.mp4')
    ->addAudioStream('videos/clip.mp4', 'audio.mp4')
    ->addTextStream('captions/clip.en.vtt', 'subtitles-en.mp4', ['language' => 'en', 'dash_roles' => 'subtitle'])
    ->withMpdOutput('index.mpd')
    ->withHlsMasterPlaylist('master.m3u8')
    ->export()
    ->save();
```

Use an `.mp4` output for subtitles rather than `.vtt`. With a plain `.vtt` output, the DASH manifest has no segment index, and Shaka Player skips the track.

A path that you didn't open is passed to Shaka Packager as-is, so it must be an absolute local path.

## Manifest options

The most common ones:

```php
use Foxws\Shaka\Support\HlsPlaylistType;

->withSegmentDuration(6)
->withHlsPlaylistType(HlsPlaylistType::Vod)
->withDefaultLanguage('en')
->withDefaultTextLanguage('en')
->withAllowCodecSwitching()
```

Any other Shaka Packager flag can be set with `withOption()`:

```php
->withOption('generate_static_live_mpd', true)
```

See the [Quick Reference](quick-reference.md) for the full list.

## After saving

`afterSaving()` runs after the files are on the target disk:

```php
->export()
->toDisk('s3')
->afterSaving(fn ($exporter, $result) => $video->markAsReady())
->save();
```

## Errors

- `Foxws\Shaka\Exceptions\RuntimeException` means Shaka Packager itself failed. The message includes its error output.
- `RuntimeException` from `save()` means some files couldn't be copied to the target disk. The message lists them.
- `Foxws\Shaka\Exceptions\InsufficientStorageException` means a storage guard stopped the job before it started. See [Configuration](configuration.md).

## Events

| Event | Properties |
| --- | --- |
| `Foxws\Shaka\Events\PackagingStarted` | `$mediaCollection`, `$options` |
| `Foxws\Shaka\Events\PackagingCompleted` | `$result`, `$executionTime` |
| `Foxws\Shaka\Events\PackagingFailed` | `$exception`, `$executionTime`, `$context` |

## Debugging

See the command without running it:

```php
$command = Shaka::open('videos/clip.mp4')
    ->addVideoStream('videos/clip.mp4', 'video.mp4')
    ->withMpdOutput('index.mpd')
    ->getCommand();
```

Or call `->export()->dd()` to dump it and stop.
