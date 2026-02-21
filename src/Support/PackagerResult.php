<?php

declare(strict_types=1);

namespace Foxws\Shaka\Support;

use Foxws\Shaka\Filesystem\Disk;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\Storage;

class PackagerResult
{

    protected array $uploadedEncryptionKeys = [];

    protected array $copiedFiles = [];

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
     * @return array<int, array{absolutePath: string, targetPath: string, filename: string, extension: string, isKeyFile: bool, isSmallFile: bool, size: int}>
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
                'filename' => $filename,
                'extension' => $extension,
                'isKeyFile' => $isKeyFile,
                'isSmallFile' => $isKeyFile || $extension === 'm3u8',
                'size' => filesize($absolutePath),
            ];
        }

        return $ops;
    }

    /**
     * Upload files concurrently using Laravel's Concurrency facade.
     *
     * Files are chunked into up to CONCURRENCY_WORKERS child processes. Each child
     * bootstraps a fresh Laravel application so Storage::disk() is fully available
     * without needing to serialize any Filesystem objects.
     *
     * @param  array<int, array{absolutePath: string, targetPath: string, filename: string, extension: string, isKeyFile: bool, isSmallFile: bool}>  $fileOps
     */
    protected function copyFilesConcurrently(array $fileOps, string $diskName, ?string $visibility): void
    {
        $workers = $this->configuration['concurrency_workers'] ?? 10;
        $chunkSize = (int) ceil(count($fileOps) / $workers);
        $chunks = array_chunk($fileOps, max(1, $chunkSize));

        $tasks = array_map(
            fn (array $chunk) => function () use ($chunk, $diskName, $visibility): array {
                $disk = Storage::disk($diskName);
                $results = [];

                $options = $visibility ? ['visibility' => $visibility] : [];

                foreach ($chunk as $op) {
                    try {
                        $content = null;

                        if ($op['isSmallFile']) {
                            $content = file_get_contents($op['absolutePath']);
                            $disk->put($op['targetPath'], $content, $options);
                        } else {
                            $stream = fopen($op['absolutePath'], 'rb');
                            $disk->writeStream($op['targetPath'], $stream, $options);

                            if (is_resource($stream)) {
                                fclose($stream);
                            }
                        }

                        $results[] = [
                            'success' => true,
                            'targetPath' => $op['targetPath'],
                            'source' => $op['absolutePath'],
                            'size' => $op['size'],
                            'type' => $op['isKeyFile'] ? 'key' : ($op['extension'] === 'm3u8' ? 'manifest' : 'segment'),
                            'isKeyFile' => $op['isKeyFile'],
                            'filename' => $op['filename'],
                            'keyContent' => $op['isKeyFile'] ? bin2hex($content) : null,
                        ];
                    } catch (\Exception $e) {
                        $results[] = [
                            'success' => false,
                            'targetPath' => $op['targetPath'],
                            'source' => $op['absolutePath'],
                            'error' => $e->getMessage(),
                        ];
                    }
                }

                return $results;
            },
            $chunks
        );

        foreach (Concurrency::run($tasks) as $chunkResults) {
            foreach ($chunkResults as $result) {
                if ($result['success']) {
                    $this->copiedFiles[$result['targetPath']] = [
                        'source' => $result['source'],
                        'size' => $result['size'],
                        'type' => $result['type'],
                    ];

                    if ($result['isKeyFile']) {
                        $this->uploadedEncryptionKeys[] = [
                            'filename' => $result['filename'],
                            'path' => $result['targetPath'],
                            'content' => $result['keyContent'],
                        ];
                    }
                } else {
                    $this->failedFiles[] = [
                        'source' => $result['source'],
                        'target' => $result['targetPath'],
                        'error' => $result['error'],
                        'size' => 0,
                    ];
                }
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

    public function getCopiedFiles(): array
    {
        return $this->copiedFiles;
    }

    public function getFailedFiles(): array
    {
        return $this->failedFiles;
    }

    public function hasCopyFailures(): bool
    {
        return ! empty($this->failedFiles);
    }

    public function getCopySummary(): array
    {
        $totalSize = 0;
        foreach ($this->copiedFiles as $file) {
            $totalSize += $file['size'] ?? 0;
        }

        return [
            'total' => count($this->copiedFiles) + count($this->failedFiles),
            'copied' => count($this->copiedFiles),
            'failed' => count($this->failedFiles),
            'totalSize' => $totalSize,
        ];
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

    /**
     * Get encryption keys that were uploaded during the last toDisk() call.
     *
     * Returns keys with their uploaded paths and hex-encoded content, ready for database storage.
     *
     * @return array<int, array{filename: string, path: string, content: string}> Array of uploaded keys
     */
    public function getUploadedEncryptionKeys(): array
    {
        return $this->uploadedEncryptionKeys;
    }
}
