<?php

declare(strict_types=1);

namespace Foxws\Shaka\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Foxws\Shaka\MediaOpener fromDisk($disk)
 * @method static \Foxws\Shaka\MediaOpener open($path)
 * @method static \Foxws\Shaka\MediaOpener cleanupTemporaryFiles()
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
