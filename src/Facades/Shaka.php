<?php

declare(strict_types=1);

namespace Foxws\Shaka\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Foxws\Shaka\MediaOpener fromDisk($disk)
 * @method static \Foxws\Shaka\MediaOpener open($path)
 * @method static \Foxws\Shaka\MediaOpener openFromDisk($disk, $path)
 * @method static \Foxws\Shaka\MediaOpener cleanupTemporaryFiles()
 * @method static \Foxws\Shaka\Exporters\MediaExporter export()
 * @method static \Foxws\Shaka\Http\DynamicHLSPlaylist dynamicHLSPlaylist(?string $disk = null)
 * @method static \Foxws\Shaka\Http\DynamicDASHManifest dynamicDASHManifest(?string $disk = null)
 *
 * @see \Foxws\Shaka\MediaOpener
 */
class Shaka extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'laravel-shaka';
    }
}
