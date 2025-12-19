<?php

declare(strict_types=1);

namespace Foxws\Shaka\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Foxws\Shaka\Shaka fromDisk($disk)
 * @method static \Foxws\Shaka\Shaka fromFilesystem(\Illuminate\Contracts\Filesystem\Filesystem $filesystem)
 * @method static \Foxws\Shaka\Shaka open($path)
 * @method static \Foxws\Shaka\Shaka cleanupTemporaryFiles()
 *
 * @see \Foxws\Shaka\Shaka
 */
class Shaka extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Foxws\Shaka\Shaka::class;
    }
}
