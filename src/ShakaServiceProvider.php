<?php

declare(strict_types=1);

namespace Foxws\Shaka;

use Foxws\Shaka\Commands\ShakaCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ShakaServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-shaka')
            ->hasConfigFile()
            ->hasMigration('create_laravel_shaka_table')
            ->hasCommand(ShakaCommand::class);
    }
}
