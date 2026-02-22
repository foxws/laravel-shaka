<?php

declare(strict_types=1);

namespace Foxws\Shaka\Support;

use Foxws\Shaka\Filesystem\Disk;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Concurrency;

class PackagerResult
{
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
            throw new \RuntimeException('Cannot copy files: temporary directory not set');
        }

        $targetDirectory = $outputPath ?: $this->getSourceDirectory();

        $tempDisk = $this->getTempFilesystem();
        $cacheDisk = $this->getCacheFilesystem();

        $fileOps = array_merge(
            $tempDisk ? $this->buildFileOperations($tempDisk->allFiles(), $targetDirectory, $this->temporaryDirectory) : [],
            $cacheDisk ? $this->buildFileOperations($cacheDisk->allFiles(), $targetDirectory, $this->cacheDirectory) : [],
        );

        if (! empty($fileOps)) {
            $this->copyFilesConcurrently($fileOps, $targetDisk->getName(), $visibility);
        }

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

        return $this;
    }

    /**
     * Build primitive file operation descriptors from a list of relative paths.
     *
     * @param  array<string>  $files
     * @return array<int, array{absolutePath: string, targetPath: string, isSmallFile: bool}>
     */
    protected function buildFileOperations(array $files, ?string $targetDirectory, string $sourceBasePath): array
    {
        $ops = [];

        foreach ($files as $relativePath) {
            $filename = basename($relativePath);
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $isKeyFile = $extension === 'key' || (bool) preg_match('/^[a-zA-Z_-]+_\d+$/', pathinfo($filename, PATHINFO_FILENAME));
            $absolutePath = $sourceBasePath.DIRECTORY_SEPARATOR.$relativePath;

            $ops[] = [
                'absolutePath' => $absolutePath,
                'targetPath' => $targetDirectory ? $targetDirectory.$relativePath : $relativePath,
                'isSmallFile' => $isKeyFile || $extension === 'm3u8',
            ];
        }

        return $ops;
    }

    /**
     * Upload files concurrently using the fork driver via the Concurrency facade.
     *
     * Files are chunked into up to CONCURRENCY_WORKERS forks. The fork driver
     * (spatie/fork) has no hard-coded timeout, unlike the default process driver,
     * making it suitable for large file uploads to remote disks such as S3.
     *
     * @param  array<int, array{absolutePath: string, targetPath: string, isSmallFile: bool}>  $fileOps
     */
    protected function copyFilesConcurrently(array $fileOps, string $diskName, ?string $visibility): void
    {
        $workers = $this->configuration['concurrency_workers'] ?? 10;
        $chunkSize = (int) ceil(count($fileOps) / $workers);
        $chunks = array_chunk($fileOps, max(1, $chunkSize));

        $tasks = [];

        foreach ($chunks as $chunk) {
            $tasks[] = function () use ($chunk, $diskName, $visibility): array {
                $disk = Disk::make($diskName);
                $failures = [];
                $options = $visibility ? ['visibility' => $visibility] : [];

                foreach ($chunk as $op) {
                    try {
                        if ($op['isSmallFile']) {
                            $disk->put($op['targetPath'], file_get_contents($op['absolutePath']), $options);
                        } else {
                            $stream = fopen($op['absolutePath'], 'rb');
                            $disk->writeStream($op['targetPath'], $stream, $options);

                            if (is_resource($stream)) {
                                fclose($stream);
                            }
                        }
                    } catch (\Exception $e) {
                        $failures[] = [
                            'source' => $op['absolutePath'],
                            'target' => $op['targetPath'],
                            'error' => $e->getMessage(),
                        ];
                    }
                }

                return $failures;
            };
        }

        $this->failedFiles = array_merge(...Concurrency::driver('fork')->run($tasks));
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

    public function getFailedFiles(): array
    {
        return $this->failedFiles;
    }

    public function hasCopyFailures(): bool
    {
        return ! empty($this->failedFiles);
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
     * @return array<int, array{path: string, filename: string, content: string}>
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
                    $keys[] = [
                        'path' => $basePath.DIRECTORY_SEPARATOR.$relativePath,
                        'filename' => $filename,
                        'content' => bin2hex($disk->get($relativePath)),
                    ];
                }
            }
        }

        return $keys;
    }
}
