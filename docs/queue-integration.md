---
section: Usage
order: 3
---

# Queues

Packaging a long video takes minutes, so run it in a queued job, not in a request.

## A packaging job

```php
namespace App\Jobs;

use App\Models\Video;
use Foxws\Shaka\Facades\Shaka;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class PackageVideo implements ShouldQueue
{
    use Queueable;

    public $timeout = 3600;

    public $tries = 2;

    public function __construct(public Video $video) {}

    public function handle(): void
    {
        $packager = Shaka::fromDisk('media')->open($this->video->path);

        try {
            $packager
                ->addVideoStream($this->video->path, 'video.mp4')
                ->addAudioStream($this->video->path, 'audio.mp4')
                ->withMpdOutput('index.mpd')
                ->withHlsMasterPlaylist('master.m3u8')
                ->export()
                ->toDisk('s3')
                ->toPath("streams/{$this->video->id}/")
                ->afterSaving(fn () => $this->video->markAsReady())
                ->save();
        } finally {
            $packager->cleanupTemporaryFiles();
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->video->markAsFailed();
    }
}
```

```php
PackageVideo::dispatch($video)->onQueue('media');
```

## Timeouts

Three timeouts need to line up:

1. `PACKAGER_TIMEOUT` stops the Shaka Packager process. The default is 14400 seconds (4 hours).
2. The job's `$timeout` stops the worker. Keep it at or above `PACKAGER_TIMEOUT`, or the worker is killed while Shaka Packager still runs.
3. The queue connection's `retry_after` must be **longer** than the job's `$timeout`. Otherwise another worker picks up the same job while the first one is still packaging.

```php
// config/queue.php
'media' => [
    'driver' => 'redis',
    'connection' => 'default',
    'queue' => 'media',
    'retry_after' => 3660,
],
```

## How many at once

Packaging mostly uses disk and network, and every running job needs space in `temporary_files_root` for its whole output. Limit how many run at the same time. With Horizon:

```php
// config/horizon.php
'supervisor-media' => [
    'connection' => 'media',
    'queue' => ['media'],
    'maxProcesses' => 2,
    'timeout' => 3600,
    'tries' => 2,
],
```

If `temporary_files_root` is a size-limited mount, turn on the [storage guards](configuration.md). A job then fails right away instead of halfway through.

## Long-running workers

Queue workers live for many jobs, so:

- Always call `cleanupTemporaryFiles()` in `finally`. A failed job otherwise leaves its files behind.
- Don't change the shared driver with `app(ShakaPackager::class)->setTimeout()` inside a job. The driver is a singleton, so the change sticks for every later job in that worker.
- Use `WithoutOverlapping` or `ShouldBeUnique` if the same video can be queued twice.
