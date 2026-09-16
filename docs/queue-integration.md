---
section: Usage
order: 3
---

# Queue Integration Guide

Packaging a video can take a while, so it's usually best done in a background job rather than during a web request. This guide shows how to run Laravel Shaka Packager through Laravel's queue system.

## Basic queue job

Create a job to handle media packaging:

```php
<?php

namespace App\Jobs;

use Foxws\Shaka\Facades\Shaka;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PackageMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $inputPath,
        public string $outputPath,
        public string $disk = 's3'
    ) {}

    public function handle(): void
    {
        Shaka::fromDisk($this->disk)
            ->open($this->inputPath)
            ->addVideoStream($this->inputPath, 'video_1080p.mp4', ['bandwidth' => '5000000'])
            ->addVideoStream($this->inputPath, 'video_720p.mp4', ['bandwidth' => '3000000'])
            ->addAudioStream($this->inputPath, 'audio.mp4')
            ->withHlsMasterPlaylist('master.m3u8')
            ->export()
            ->toPath($this->outputPath)
            ->save();
    }
}
```

## Dispatching the job

```php
use App\Jobs\PackageMediaJob;

// Dispatch to the default queue
PackageMediaJob::dispatch('videos/input.mp4', 'processed/');

// Dispatch to a specific queue
PackageMediaJob::dispatch('videos/input.mp4', 'processed/')
    ->onQueue('media-processing');

// Dispatch with a delay
PackageMediaJob::dispatch('videos/input.mp4', 'processed/')
    ->delay(now()->addMinutes(5));
```

## Job with progress tracking

```php
<?php

namespace App\Jobs;

use Foxws\Shaka\Facades\Shaka;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PackageMediaWithProgressJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 7200; // 2 hours
    public int $tries = 3;

    public function __construct(
        public string $inputPath,
        public string $outputPath,
        public ?int $userId = null
    ) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        try {
            Shaka::fromDisk('s3')
                ->open($this->inputPath)
                ->addVideoStream($this->inputPath, 'video.mp4')
                ->addAudioStream($this->inputPath, 'audio.mp4')
                ->withHlsMasterPlaylist('master.m3u8')
                ->export()
                ->afterSaving(function ($exporter, $result) {
                    // Notify the user that packaging is done
                    if ($this->userId) {
                        // Send notification
                    }
                })
                ->toPath($this->outputPath)
                ->save();
        } catch (\Exception $e) {
            $this->fail($e);
        }
    }

    public function failed(\Throwable $exception): void
    {
        // Handle job failure
        \Log::error('Media packaging failed', [
            'input' => $this->inputPath,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

## Batch processing

Process several files together as one batch:

```php
use App\Jobs\PackageMediaJob;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

$jobs = [];

foreach ($mediaFiles as $file) {
    $jobs[] = new PackageMediaJob($file, 'processed/');
}

$batch = Bus::batch($jobs)
    ->name('Media Packaging Batch')
    ->then(function (Batch $batch) {
        // All jobs completed successfully
    })
    ->catch(function (Batch $batch, Throwable $e) {
        // The first job in the batch failed
    })
    ->finally(function (Batch $batch) {
        // The batch has finished running
    })
    ->dispatch();
```

## Configuration recommendations

### Queue configuration

Update `config/queue.php`:

```php
'connections' => [
    'media-processing' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'media',
        'retry_after' => 7200, // 2 hours
        'block_for' => null,
    ],
],
```

### Horizon configuration (optional)

If you use Laravel Horizon, add this to `config/horizon.php`:

```php
'environments' => [
    'production' => [
        'media-processing' => [
            'connection' => 'redis',
            'queue' => ['media'],
            'balance' => 'auto',
            'maxProcesses' => 2, // Limit how many packaging jobs run at once
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 512,
            'tries' => 3,
            'timeout' => 7200,
        ],
    ],
],
```

See [Configuration](./configuration.md) for tuning `temporary_files_min_free` and related storage guards when several queue workers run at the same time.

## Best practices

1. **Set a realistic timeout** - Packaging can take a while; size the timeout to your content.
2. **Limit how many jobs run at once** - Packaging is resource-intensive, so cap concurrent jobs.
3. **Watch memory usage** - Set memory limits so a runaway job doesn't take down the server.
4. **Add retries** - Network issues with remote storage may need a retry rather than an immediate failure.
5. **Chain follow-up jobs** - For example, run a cleanup job right after packaging.
6. **Track progress** - Use events or database updates so users can see where a job stands.
7. **Always clean up temporary files** - Whether the job succeeds or fails.

## Example with cleanup

```php
public function handle(): void
{
    try {
        Shaka::fromDisk('s3')
            ->open($this->inputPath)
            ->addVideoStream($this->inputPath, 'video.mp4')
            ->withHlsMasterPlaylist('master.m3u8')
            ->export()
            ->toPath($this->outputPath)
            ->save();

        // Clean up temporary files
        Shaka::cleanupTemporaryFiles();
    } catch (\Exception $e) {
        // Clean up on error too
        Shaka::cleanupTemporaryFiles();
        throw $e;
    }
}
```
