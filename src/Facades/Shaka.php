<?php

namespace Foxws\Shaka\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Foxws\Shaka\Shaka
 */
class Shaka extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Foxws\Shaka\Shaka::class;
    }
}
