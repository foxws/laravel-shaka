<?php

declare(strict_types=1);

namespace Foxws\Shaka\Support;

use Foxws\Shaka\Filesystem\Disk;
use Illuminate\Contracts\Filesystem\Filesystem;

class PackagerResult
{
    protected array $uploadedEncryptionKeys = [];

    protected ?Disk $targetDisk = null;

    protected ?string $targetPath = null;

    protected ?string $visibility = null;

    protected bool $cleanup = true;

    public function __construct(
        protected string $output,
        protected ?Disk $sourceDisk = null,
        protected ?string $temporaryDirectory = null,
        protected ?string $cacheDirectory = null
    ) {}

    public function getOutput(): string
    {
        return $this->output;
    }

    /**
     * Set the target disk for file uploads
     */
    public function toDisk(Disk|Filesystem|string $disk, ?string $visibility = null, bool $cleanup = true): self
    {
        $this->targetDisk = Disk::make($disk);
        $this->visibility = $visibility;
        $this->cleanup = $cleanup;

        return $this;
    }

    /**
     * Set the target path/directory for file uploads
     */
    public function toPath(string $path): self
    {
        $this->targetPath = rtrim($path, '/') . '/';

        return $this;
    }

    /**
     * Execute the file upload to the configured disk and path
     */
    public function save(): self
    {
        if (! $this->targetDisk) {
            throw new \RuntimeException('Target disk not set. Call toDisk() first.');
        }

        if (! $this->temporaryDirectory) {
            throw new \RuntimeException('Cannot copy files: temporary directory not set');
        }

        \Log::info('PackagerResult starting file upload', [
            'target_disk' => get_class($this->targetDisk),
            'target_path' => $this->targetPath,
            'temp_dir' => $this->temporaryDirectory,
            'cache_dir' => $this->cacheDirectory,
        ]);

        // Get the target directory
        $targetDirectory = $this->targetPath ?: $this->getSourceDirectory();

        // Collect files from temp directory (segments/manifests) and cache directory (encryption keys)
        $files = $this->getAllFilesInTemporaryDirectory($this->temporaryDirectory);

        if ($this->cacheDirectory && is_dir($this->cacheDirectory)) {
            $cacheFiles = $this->getAllFilesInTemporaryDirectory($this->cacheDirectory);
            $files = array_merge($files, $cacheFiles);
        }

        // Copy all files to target disk
        foreach ($files as $file) {
            $filename = basename($file);
            $targetPath = $targetDirectory ? $targetDirectory.$filename : $filename;

            // Check if this is an encryption key file
            // Rotation keys: base_0, base_1, etc. (with or without extension)
            // Static keys: any filename without extension that looks like a key file
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $basename = pathinfo($filename, PATHINFO_FILENAME);

            // Rotation key pattern: common key base names with _digits suffix
            // Matches: key_0, encryption_1, drm_2, secret_3, etc.
            // Does NOT match: video_1080, audio_128, init_0, segment_0, etc.
            $isRotationKey = preg_match('/^(key|encryption|drm|secret|aes)_\d+$/i', $basename);

            // Static key pattern: common key filenames without numeric suffix and no extension
            $isStaticKey = !$extension && preg_match('/^(key|encryption|drm|secret|aes)$/i', $filename);

            $isKeyFile = $isRotationKey || $isStaticKey;

            // Small text files (.m3u8, .mpd manifests) and key files - use put() for reliability
            $isSmallFile = $isKeyFile || in_array($extension, ['m3u8', 'mpd']);

            if ($isSmallFile) {
                $content = file_get_contents($file);
                $this->targetDisk->put($targetPath, $content);

                // Track uploaded encryption key metadata
                if ($isKeyFile) {
                    $this->uploadedEncryptionKeys[] = [
                        'filename' => $filename,
                        'path' => $targetPath,
                        'content' => bin2hex($content),
                    ];
                }
            } else {
                // Stream large binary files (video/audio segments)
                $stream = fopen($file, 'rb');
                $this->targetDisk->writeStream($targetPath, $stream);
                fclose($stream);
            }

            if ($this->visibility) {
                $this->targetDisk->setVisibility($targetPath, $this->visibility);
            }

            // Clean up temporary file after copying
            if ($this->cleanup) {
                unlink($file);
            }
        }

        // Clean up temporary directory if empty
        if ($this->cleanup && is_dir($this->temporaryDirectory)) {
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
        $keys = [];

        // Check temp directory for keys
        if ($this->temporaryDirectory && is_dir($this->temporaryDirectory)) {
            $files = $this->getAllFilesInTemporaryDirectory($this->temporaryDirectory);
            $keys = array_merge($keys, $this->extractKeysFromFiles($files));
        }

        // Check cache directory for keys (where rotation keys are stored)
        if ($this->cacheDirectory && is_dir($this->cacheDirectory)) {
            $cacheFiles = $this->getAllFilesInTemporaryDirectory($this->cacheDirectory);
            $keys = array_merge($keys, $this->extractKeysFromFiles($cacheFiles));
        }

        return $keys;
    }

    /**
     * Extract encryption keys from a list of files
     */
    protected function extractKeysFromFiles(array $files): array
    {
        $keys = [];

        foreach ($files as $file) {
            $filename = basename($file);
            $extension = pathinfo($filename, PATHINFO_EXTENSION);

            // Check for encryption key files
            // Rotation keys: base_0, base_1, etc. (with or without extension)
            // Static keys: any filename without extension that looks like a key file
            $basename = pathinfo($filename, PATHINFO_FILENAME);

            // Rotation key pattern: common key base names with _digits suffix
            // Matches: key_0, encryption_1, drm_2, secret_3, etc.
            // Does NOT match: video_1080, audio_128, init_0, segment_0, etc.
            $isRotationKey = preg_match('/^(key|encryption|drm|secret|aes)_\d+$/i', $basename);

            // Static key pattern: common key filenames without numeric suffix and no extension
            $isStaticKey = !$extension && preg_match('/^(key|encryption|drm|secret|aes)$/i', $filename);

            $isKeyFile = $isRotationKey || $isStaticKey;

            if ($isKeyFile) {
                $keys[] = [
                    'path' => $file,
                    'filename' => $filename,
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
