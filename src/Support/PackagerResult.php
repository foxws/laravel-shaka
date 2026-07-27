<?php

declare(strict_types=1);

namespace Foxws\Shaka\Support;

use Foxws\Shaka\Filesystem\Disk;
use Generator;
use GuzzleHttp\Promise\EachPromise;
use Illuminate\Contracts\Filesystem\Filesystem;
use League\MimeTypeDetection\ExtensionMimeTypeDetector;
use RuntimeException;
use Throwable;

class PackagerResult
{
    /** @var array<int, CopyFailure> */
    protected array $failedFiles = [];

    protected ?Filesystem $tempFilesystem = null;

    protected ?Filesystem $cacheFilesystem = null;

    public function __construct(
        protected string $output,
        protected ?Disk $sourceDisk = null,
        protected ?string $temporaryDirectory = null,
        protected ?string $cacheDirectory = null,
        protected ?array $configuration = null,
    ) {}

    public function getOutput(): string
    {
        return $this->output;
    }

    /**
     * Copy exported files from temporary directory to target disk.
     */
    public function toDisk(Disk|Filesystem|string $disk, ?string $visibility = null, bool $cleanup = true, ?string $outputPath = null): self
    {
        $targetDisk = Disk::make($disk);

        if (! $this->temporaryDirectory) {
            throw new RuntimeException('Cannot copy files: temporary directory not set');
        }

        $targetDirectory = $outputPath ?: $this->getSourceDirectory();

        $tempDisk = $this->getTempFilesystem();
        $cacheDisk = $this->getCacheFilesystem();

        $fileOps = array_merge(
            $tempDisk ? $this->buildFileOperations($tempDisk->allFiles(), $targetDirectory, $this->temporaryDirectory) : [],
            $cacheDisk ? $this->buildFileOperations($cacheDisk->allFiles(), $targetDirectory, $this->cacheDirectory) : [],
        );

        throw_if(
            blank($fileOps),
            RuntimeException::class,
            'Packager produced no output files. Verify that the input media contains valid video or audio streams.'
        );

        $this->copyFilesConcurrently($fileOps, $targetDisk, $visibility);

        if ($cleanup) {
            if ($tempDisk && is_dir($this->temporaryDirectory)) {
                $tempDisk->deleteDirectory('/');
                @rmdir($this->temporaryDirectory);
            }

            if ($cacheDisk && $this->cacheDirectory && is_dir($this->cacheDirectory)) {
                $cacheDisk->deleteDirectory('/');
                @rmdir($this->cacheDirectory);
            }
        }

        if ($this->hasCopyFailures()) {
            $errors = array_map(strval(...), $this->failedFiles);

            throw new RuntimeException(
                sprintf(
                    '%d file(s) failed to copy to disk "%s": %s',
                    count($this->failedFiles),
                    $targetDisk->getName(),
                    implode('; ', $errors)
                )
            );
        }

        return $this;
    }

    /**
     * Build file operation descriptors from a list of relative paths.
     *
     * @param  array<string>  $files
     * @return array<int, FileOperation>
     */
    protected function buildFileOperations(array $files, ?string $targetDirectory, string $sourceBasePath): array
    {
        return array_map(fn (string $relativePath) => new FileOperation(
            absolutePath: $sourceBasePath.DIRECTORY_SEPARATOR.$relativePath,
            targetPath: $targetDirectory ? $targetDirectory.$relativePath : $relativePath,
        ), $files);
    }

    /**
     * Upload files to the target disk, using async S3 promises when the disk
     * is S3-backed, and a sequential loop for local/other disks.
     *
     * @param  array<int, FileOperation>  $fileOps
     */
    protected function copyFilesConcurrently(array $fileOps, Disk $disk, ?string $visibility): void
    {
        if ($disk->isS3Disk()) {
            $this->uploadFilesViaS3Async($fileOps, $disk, $visibility);

            return;
        }

        $this->uploadFilesSequentially($fileOps, $disk, $visibility);
    }

    /**
     * Upload files concurrently to S3 using the AWS SDK's putObjectAsync.
     *
     * Dispatches up to `laravel-shaka.concurrency_workers` uploads at a time
     * via Guzzle promises. This is I/O-overlap concurrency within a single
     * process — no forking, no process spawning, no shared-state corruption.
     *
     * Adapter-level options (e.g. CacheControl) and the Flysystem path prefix
     * are preserved so behaviour matches what writeStream would produce.
     *
     * @param  array<int, FileOperation>  $fileOps
     */
    protected function uploadFilesViaS3Async(array $fileOps, Disk $disk, ?string $visibility): void
    {
        $client = $disk->getS3Client();
        $bucket = $disk->getS3Bucket();
        $adapterOptions = $disk->getS3UploadOptions();
        $concurrency = (int) ($this->configuration['concurrency_workers'] ?? 10);
        $mimeDetector = new ExtensionMimeTypeDetector;

        $acl = match ($visibility) {
            'public' => 'public-read',
            'private' => 'private',
            default => null,
        };

        $failed = [];

        $generator = (function () use ($fileOps, $client, $bucket, $disk, $adapterOptions, $acl, $mimeDetector, &$failed): Generator {
            foreach ($fileOps as $op) {
                $stream = fopen($op->absolutePath, 'rb');

                if ($stream === false) {
                    $failed[] = new CopyFailure(
                        source: $op->absolutePath,
                        target: $op->targetPath,
                        error: "Failed to open file: {$op->absolutePath}",
                    );

                    continue;
                }

                $key = $disk->prefixS3Path($op->targetPath);

                $params = array_merge($adapterOptions, [
                    'Bucket' => $bucket,
                    'Key' => $key,
                    'Body' => $stream,
                    'ContentType' => $mimeDetector->detectMimeTypeFromPath($key) ?? 'application/octet-stream',
                ]);

                if ($acl !== null) {
                    $params['ACL'] = $acl;
                }

                yield $client->putObjectAsync($params)->then(
                    function () use ($stream): void {
                        if (is_resource($stream)) {
                            fclose($stream);
                        }
                    },
                    function ($reason) use ($stream, $op, &$failed): void {
                        if (is_resource($stream)) {
                            fclose($stream);
                        }

                        $failed[] = new CopyFailure(
                            source: $op->absolutePath,
                            target: $op->targetPath,
                            error: $reason instanceof Throwable ? $reason->getMessage() : (string) $reason,
                        );
                    }
                );
            }
        })();

        (new EachPromise($generator, ['concurrency' => $concurrency]))->promise()->wait();

        $this->failedFiles = array_merge($this->failedFiles, $failed);
    }

    /**
     * Upload files sequentially to the target disk.
     *
     * @param  array<int, FileOperation>  $fileOps
     */
    protected function uploadFilesSequentially(array $fileOps, Disk $disk, ?string $visibility): void
    {
        $options = $visibility ? ['visibility' => $visibility] : [];

        foreach ($fileOps as $op) {
            try {
                $stream = fopen($op->absolutePath, 'rb');

                if ($stream === false) {
                    throw new RuntimeException("Failed to open file: {$op->absolutePath}");
                }

                $disk->writeStream($op->targetPath, $stream, $options);

                if (is_resource($stream)) {
                    fclose($stream);
                }
            } catch (Throwable $e) {
                if (isset($stream) && is_resource($stream)) {
                    fclose($stream);
                }

                $this->failedFiles[] = new CopyFailure(
                    source: $op->absolutePath,
                    target: $op->targetPath,
                    error: $e->getMessage(),
                );
            }
        }
    }

    protected function getTempFilesystem(): ?Filesystem
    {
        if (! $this->temporaryDirectory || ! is_dir($this->temporaryDirectory)) {
            return null;
        }

        if (! $this->tempFilesystem) {
            $this->tempFilesystem = Disk::make('local')->buildFilesystem([
                'driver' => 'local',
                'root' => $this->temporaryDirectory,
            ]);
        }

        return $this->tempFilesystem;
    }

    protected function getCacheFilesystem(): ?Filesystem
    {
        if (! $this->cacheDirectory || ! is_dir($this->cacheDirectory)) {
            return null;
        }

        if (! $this->cacheFilesystem) {
            $this->cacheFilesystem = Disk::make('local')->buildFilesystem([
                'driver' => 'local',
                'root' => $this->cacheDirectory,
            ]);
        }

        return $this->cacheFilesystem;
    }

    /**
     * @return array<int, CopyFailure>
     */
    public function getFailedFiles(): array
    {
        return $this->failedFiles;
    }

    public function hasCopyFailures(): bool
    {
        return filled($this->failedFiles);
    }

    protected function getSourceDirectory(): ?string
    {
        if ($this->sourceDisk && method_exists($this->sourceDisk, 'getDirectory')) {
            $directory = $this->sourceDisk->getDirectory();

            if ($directory && $directory !== '.') {
                return rtrim($directory, '/').'/';
            }
        }

        return null;
    }

    /**
     * Get all encryption key files from the temporary and cache directories.
     *
     * Useful when using key rotation to collect all generated keys.
     *
     * @return array<int, EncryptionKeyFile>
     */
    public function getEncryptionKeys(): array
    {
        $disks = array_filter([
            $this->temporaryDirectory => $this->getTempFilesystem(),
            $this->cacheDirectory => $this->getCacheFilesystem(),
        ]);

        $keys = [];

        foreach ($disks as $basePath => $disk) {
            foreach ($disk->allFiles() as $relativePath) {
                $filename = basename($relativePath);
                $extension = pathinfo($filename, PATHINFO_EXTENSION);
                $isKeyFile = $extension === 'key'
                    || (bool) preg_match('/^[a-zA-Z_-]+_\d+$/', pathinfo($filename, PATHINFO_FILENAME));

                if ($isKeyFile) {
                    $keys[] = new EncryptionKeyFile(
                        path: $basePath.DIRECTORY_SEPARATOR.$relativePath,
                        filename: $filename,
                        content: bin2hex($disk->get($relativePath)),
                    );
                }
            }
        }

        return $keys;
    }
}
