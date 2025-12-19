<?php

declare(strict_types=1);

namespace Foxws\Shaka\Exporters;

use Foxws\Shaka\MediaOpener;
use Foxws\Shaka\Support\Filesystem\Disk;
use Foxws\Shaka\Support\Filesystem\Media;
use Foxws\Shaka\Support\Packager\Packager;
use Foxws\Shaka\Support\ProcessOutput;
use Illuminate\Support\Traits\ForwardsCalls;

class MediaExporter
{
    use ForwardsCalls;

    protected ?Packager $packager = null;

    protected ?Disk $toDisk = null;

    protected ?string $visibility = null;

    protected ?array $afterSavingCallbacks = null;

    public function __construct(Packager $packager, Disk $toDisk)
    {
        $this->packager = $packager;
        $this->toDisk = $toDisk;
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

    public function withVisibility(string $visibility): self
    {
        $this->visibility = $visibility;

        return $this;
    }

    /**
     * Returns the final command, useful for debugging purposes.
     *
     * @return mixed
     */
    public function getCommand(?string $path = null)
    {
        $media = $this->prepareSaving($path);

        return $this->packager->getFinalCommand(
            optional($media)->getLocalPath() ?: '/dev/null'
        );
    }

     /**
     * Dump the final command and end the script.
     *
     * @return void
     */
    public function dd(?string $path = null)
    {
        dd($this->getCommand($path));
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

    protected function runAfterSavingCallbacks(?Media $outputMedia = null)
    {
        foreach ($this->afterSavingCallbacks as $key => $callback) {
            call_user_func($callback, $this, $outputMedia);

            unset($this->afterSavingCallbacks[$key]);
        }
    }

    public function save(?string $path = null)
    {
        $outputMedia = $this->prepareSaving($path);

        // $this->packager->applyBeforeSavingCallbacks();

        if ($outputMedia) {
            $outputMedia->copyAllFromTemporaryDirectory($this->visibility);
            $outputMedia->setVisibility($path, $this->visibility);
        }

        // if ($this->onProgressCallback) {
        //     call_user_func($this->onProgressCallback, 100, 0, 0);
        // }

        $this->runAfterSavingCallbacks($outputMedia);

        return $this->getMediaOpener();
    }

    protected function getMediaOpener(): MediaOpener
    {
        return new MediaOpener(
            $this->packager->getMediaCollection()->last()->getDisk(),
            $this->packager,
            $this->packager->getMediaCollection()
        );
    }

    /**
     * Forwards the call to the driver object and returns the result
     * if it's something different than the driver object itself.
     */
    public function __call($method, $arguments)
    {
        $result = $this->forwardCallTo($packager = $this->packager, $method, $arguments);

        return ($result === $packager) ? $this : $result;
    }
}
