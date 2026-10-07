<?php

declare(strict_types=1);

namespace Foxws\Shaka\Tests;

use Foxws\Media\MediaServiceProvider;
use Foxws\Shaka\ShakaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            MediaServiceProvider::class,
            ShakaServiceProvider::class,
        ];
    }
}
