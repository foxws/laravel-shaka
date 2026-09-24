<?php

declare(strict_types=1);

use Aws\CommandInterface;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Foxws\Shaka\Tests\TestCase;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

uses(TestCase::class)->in(__DIR__);

/**
 * Check if Shaka Packager binary is available for testing
 */
function hasPackager(): bool
{
    $binary = config('laravel-shaka.packager.binaries', 'packager');

    return file_exists($binary) && is_executable($binary);
}

/**
 * Skip test if Shaka Packager is not installed
 */
function skipIfNoPackager(): void
{
    if (! hasPackager()) {
        test()->markTestSkipped('Shaka Packager binary not available');
    }
}

/**
 * Build a real S3-driver disk whose requests are answered in-process and
 * recorded, so tests can assert on which S3 operations were issued.
 *
 * @param  array<int, array{name: string, args: array<string, mixed>}>  $commands  Filled with each issued command.
 * @param  string|null  $failOn  Command name to reject, e.g. 'UploadPart'.
 */
function makeRecordingS3Disk(array &$commands = [], ?string $failOn = null, string $disk = 'recording-s3'): Filesystem
{
    config(["filesystems.disks.{$disk}" => [
        'driver' => 's3',
        'key' => 'test-key',
        'secret' => 'test-secret',
        'region' => 'us-east-1',
        'bucket' => 'test-bucket',
        'root' => 'segments',
        'handler' => function (CommandInterface $command) use (&$commands, $failOn) {
            $commands[] = ['name' => $command->getName(), 'args' => $command->toArray()];

            if ($command->getName() === $failOn) {
                return Create::rejectionFor(
                    new S3Exception("{$failOn} failed", $command)
                );
            }

            return Create::promiseFor(new Result(match ($command->getName()) {
                'CreateMultipartUpload' => ['UploadId' => 'upload-1'],
                'UploadPart' => ['ETag' => '"etag"'],
                'GetObject' => ['Body' => Utils::streamFor('remote media contents')],
                'HeadObject' => ['ContentLength' => 21],
                default => [],
            }));
        },
    ]]);

    return Storage::disk($disk);
}

/**
 * @param  array<int, array{name: string, args: array<string, mixed>}>  $commands
 * @return array<int, string>
 */
function commandNames(array $commands): array
{
    return array_column($commands, 'name');
}
