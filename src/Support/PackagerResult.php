<?php

declare(strict_types=1);

namespace Foxws\Shaka\Support;

use Foxws\Shaka\Filesystem\Disk;
use Illuminate\Contracts\Filesystem\Filesystem;

class PackagerResult
{
    protected array $uploadedEncryptionKeys = [];

    public function __construct(
        protected string $output,
        protected ?Disk $sourceDisk = null,
        protected ?string $temporaryDirectory = null
    ) {}

    public function getOutput(): string
    {
        return $this->output;
    }

    /**
     * Copy exported files from temporary directory to target disk
     */
    public function toDisk(Disk|Filesystem|string $disk, ?string $visibility = null, bool $cleanup = true, ?string $outputPath = null): self
    {
        $targetDisk = Disk::make($disk);

        if (! $this->temporaryDirectory) {
            throw new \RuntimeException('Cannot copy files: temporary directory not set');
        }

        // Get the target directory from outputPath parameter or preserve source structure
        $targetDirectory = $outputPath ?: $this->getSourceDirectory();

        // Scan the temporary directory for all files (including generated segments)
        $files = $this->getAllFilesInTemporaryDirectory($this->temporaryDirectory);

        foreach ($files as $file) {
            $filename = basename($file);
            $isKeyFile = pathinfo($filename, PATHINFO_EXTENSION) === 'key';

            // Determine target path
            $targetPath = $targetDirectory ? $targetDirectory.$filename : $filename;

            // For key files (tiny, 16 bytes), read once and upload directly
            if ($isKeyFile) {
                $keyContent = file_get_contents($file);
                $targetDisk->put($targetPath, $keyContent);

                // Track uploaded encryption key
                $this->uploadedEncryptionKeys[] = [
                    'filename' => $filename,
                    'path' => $targetPath,
                    'content' => bin2hex($keyContent),
                ];
            } else {
                // For large files (segments), stream from disk to avoid memory usage
                $stream = fopen($file, 'r');
                $targetDisk->writeStream($targetPath, $stream);
                fclose($stream);
            }

            if ($visibility) {
                $targetDisk->setVisibility($targetPath, $visibility);
            }

            // Clean up temporary file after copying
            if ($cleanup) {
                unlink($file);
            }
        }

        // Clean up temporary directory if empty
        if ($cleanup && is_dir($this->temporaryDirectory)) {
            @rmdir($this->temporaryDirectory);
        }

        return $this;
    }

    /**
     * Get all files in the temporary directory
     */
    protected function getAllFilesInTemporaryDirectory(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $items = scandir($directory);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$item;

            if (is_file($path)) {
                $files[] = $path;
            }
        }

        return $files;
    }

    /**
     * Get the source directory to preserve directory structure if no output path specified
     */
    protected function getSourceDirectory(): ?string
    {
        // If we have a source disk with media, preserve its directory structure
        if ($this->sourceDisk && method_exists($this->sourceDisk, 'getDirectory')) {
            $directory = $this->sourceDisk->getDirectory();

            if ($directory && $directory !== '.') {
                return rtrim($directory, '/').'/';
            }
        }

        return null;
    }

    /**
     * Get all encryption key files from the temporary directory.
     *
     * Useful when using key rotation to collect all generated keys.
     *
     * @return array<int, array{path: string, filename: string, content: string}> Array of key files with path, filename, and hex-encoded content
     */
    public function getEncryptionKeys(): array
    {
        if (! $this->temporaryDirectory || ! is_dir($this->temporaryDirectory)) {
            return [];
        }

        $keys = [];
        $files = $this->getAllFilesInTemporaryDirectory($this->temporaryDirectory);

        foreach ($files as $file) {
            // Look for .key files (standard encryption key extension)
            if (pathinfo($file, PATHINFO_EXTENSION) === 'key') {
                $keys[] = [
                    'path' => $file,
                    'filename' => basename($file),
                    'content' => bin2hex(file_get_contents($file)),
                ];
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
