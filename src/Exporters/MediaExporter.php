<?php

declare(strict_types=1);

namespace Foxws\Shaka\Exporters;

use Foxws\Shaka\Filesystem\Disk;
use Foxws\Shaka\Filesystem\Media;
use Foxws\Shaka\MediaOpener;
use Foxws\Shaka\Support\Packager;
use Foxws\Shaka\Support\PackagerResult;
use Illuminate\Support\Traits\ForwardsCalls;

class MediaExporter
{
    use ForwardsCalls;

    protected ?Packager $packager = null;

    protected ?Disk $toDisk = null;

    protected ?string $visibility = null;

    protected ?string $toPath = null;

    protected ?array $afterSavingCallbacks = [];

    public function __construct(Packager $packager)
    {
        $this->packager = $packager;
    }

    protected function getDisk(): Disk
    {
        if ($this->toDisk) {
            return $this->toDisk;
        }

        $media = $this->packager->getMediaCollection();

        /** @var Disk $disk */
        $disk = $media->first()->getDisk();

        return $this->toDisk = $disk->clone();
    }

    public function toDisk($disk): self
    {
        $this->toDisk = Disk::make($disk);

        return $this;
    }

    public function toPath(string $path): self
    {
        $this->toPath = rtrim($path, '/').'/';

        return $this;
    }

    public function withVisibility(string $visibility): self
    {
        $this->visibility = $visibility;

        return $this;
    }

    /**
     * Returns the final command, useful for debugging purposes.
     */
    public function getCommand(): string
    {
        return $this->packager->getCommand();
    }

    /**
     * Dump the final command and end the script.
     */
    public function dd(): void
    {
        dd($this->getCommand());
    }

    /**
     * Adds a callable to the callbacks array.
     */
    public function afterSaving(callable $callback): self
    {
        $this->afterSavingCallbacks[] = $callback;

        return $this;
    }

    protected function prepareSaving(?string $path = null): ?Media
    {
        $outputMedia = $path ? $this->getDisk()->makeMedia($path) : null;

        return $outputMedia;
    }

    protected function runAfterSavingCallbacks(PackagerResult $result)
    {
        foreach ($this->afterSavingCallbacks as $key => $callback) {
            call_user_func($callback, $this, $result);

            unset($this->afterSavingCallbacks[$key]);
        }
    }

    public function save(?string $path = null): MediaOpener
    {
        // Execute the packaging operation (writes to temporary directory)
        $result = $this->packager->export();

        // Determine target disk
        $targetDisk = $this->toDisk ?: $this->getDisk();

        // Copy outputs from temporary directory to target disk and cleanup
        if ($this->toPath) {
            $result->toPath($this->toPath);
        }

        $result->toDisk($targetDisk, $this->visibility, true)
            ->save();

        $this->runAfterSavingCallbacks($result);

        return $this->getMediaOpener();
    }

    /**
     * Register a callback to handle uploaded encryption keys.
     * The callback receives an array of uploaded keys.
     */
    public function onKeysUploaded(callable $callback): self
    {
        return $this->afterSaving(function ($exporter, $result) use ($callback) {
            $uploadedKeys = $result->getUploadedEncryptionKeys();

            if (! empty($uploadedKeys)) {
                call_user_func($callback, $uploadedKeys);
            }
        });
    }

    /**
     * Get the MediaOpener instance.
     */
    protected function getMediaOpener(): MediaOpener
    {
        return new MediaOpener($this->getDisk(), $this->packager, $this->packager->getMediaCollection());
    }

    /**
     * Forward method calls to the packager.
     */
    public function __call($method, $arguments)
    {
        $result = $this->forwardCallTo($this->packager, $method, $arguments);

        return $result === $this->packager ? $this : $result;
    }
}
