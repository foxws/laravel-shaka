---
sidebar_position: 9
---

# Queue Integration Guide

This guide explains how to integrate Laravel Shaka Packager with Laravel's queue system for processing media in the background.

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

// Dispatch to default queue
PackageMediaJob::dispatch('videos/input.mp4', 'processed/');

// Dispatch to specific queue
PackageMediaJob::dispatch('videos/input.mp4', 'processed/')
    ->onQueue('media-processing');

// Dispatch with delay
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
                    // Notify user of completion
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

Process multiple files in a batch:

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
        // First batch job failure
    })
    ->finally(function (Batch $batch) {
        // The batch has finished executing
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

If using Laravel Horizon, add to `config/horizon.php`:

```php
'environments' => [
    'production' => [
        'media-processing' => [
            'connection' => 'redis',
            'queue' => ['media'],
            'balance' => 'auto',
            'maxProcesses' => 2, // Limit concurrent packaging
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 512,
            'tries' => 3,
            'timeout' => 7200,
        ],
    ],
],
```

See [Configuration](./configuration.md) for tuning `temporary_files_min_free`
and related storage guards when running concurrent queue workers.

## Best practices

1. **Set appropriate timeouts**: Media packaging can take time, set realistic timeouts
2. **Limit concurrent jobs**: Packaging is resource-intensive, limit concurrent processes
3. **Monitor memory**: Use memory limits to prevent server issues
4. **Implement retries**: Network issues with remote storage may require retries
5. **Use job chaining**: Chain cleanup jobs after packaging
6. **Track progress**: Use events or database updates to track progress
7. **Clean up temporary files**: Always clean up after success or failure

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
