<?php

declare(strict_types=1);

namespace Foxws\Shaka\Support;

use Exception;
use Foxws\Shaka\Filesystem\Disk;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Concurrency;
use RuntimeException;

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
            throw new RuntimeException('Cannot copy files: temporary directory not set');
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

        if ($this->hasCopyFailures()) {
            $errors = array_map(
                fn (array $f) => "{$f['target']}: {$f['error']}",
                $this->failedFiles
            );

            throw new RuntimeException(
                sprintf(
                    '%d file(s) failed to copy to disk "%s" after retries: %s',
                    count($this->failedFiles),
                    $targetDisk->getName(),
                    implode('; ', $errors)
                )
            );
        }

        return $this;
    }

    /**
     * Build primitive file operation descriptors from a list of relative paths.
     *
     * @param  array<string>  $files
     * @return array<int, array{absolutePath: string, targetPath: string}>
     */
    protected function buildFileOperations(array $files, ?string $targetDirectory, string $sourceBasePath): array
    {
        $ops = [];

        foreach ($files as $relativePath) {
            $absolutePath = $sourceBasePath.DIRECTORY_SEPARATOR.$relativePath;

            $ops[] = [
                'absolutePath' => $absolutePath,
                'targetPath' => $targetDirectory ? $targetDirectory.$relativePath : $relativePath,
            ];
        }

        return $ops;
    }

    /**
     * Upload files concurrently using the fork driver via the Concurrency facade.
     *
     * Files are chunked into up to 5 forks (capped to avoid overwhelming remote
     * storage such as S3 or Garage with too many simultaneous connections).
     * Each file upload retries up to 3 times with exponential backoff (1s, 2s)
     * to recover from transient throttling or connection errors.
     *
     * @param  array<int, array{absolutePath: string, targetPath: string}>  $fileOps
     */
    protected function copyFilesConcurrently(array $fileOps, string $diskName, ?string $visibility): void
    {
        $workers = min($this->configuration['concurrency_workers'] ?? 5, 5);
        $chunkSize = (int) ceil(count($fileOps) / $workers);
        $chunks = array_chunk($fileOps, max(1, $chunkSize));

        $tasks = [];

        foreach ($chunks as $chunk) {
            $tasks[] = function () use ($chunk, $diskName, $visibility): array {
                $disk = Disk::make($diskName);
                $failures = [];
                $options = $visibility ? ['visibility' => $visibility] : [];

                foreach ($chunk as $op) {
                    $attempt = 0;
                    $maxAttempts = 3;
                    $stream = null;

                    while ($attempt < $maxAttempts) {
                        try {
                            $stream = fopen($op['absolutePath'], 'rb');

                            if ($stream === false) {
                                throw new RuntimeException("Failed to open file: {$op['absolutePath']}");
                            }

                            $disk->writeStream($op['targetPath'], $stream, $options);

                            if (is_resource($stream)) {
                                fclose($stream);
                            }

                            break;
                        } catch (Exception $e) {
                            if (is_resource($stream)) {
                                fclose($stream);
                            }

                            $attempt++;

                            if ($attempt >= $maxAttempts) {
                                $failures[] = [
                                    'source' => $op['absolutePath'],
                                    'target' => $op['targetPath'],
                                    'error' => $e->getMessage(),
                                ];
                            } else {
                                // Exponential backoff: 1s on first retry, 2s on second
                                usleep((int) (500000 * (2 ** $attempt)));
                            }
                        }
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
