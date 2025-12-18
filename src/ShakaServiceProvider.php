<?php

namespace Foxws\Shaka;

use Foxws\Shaka\Commands\ShakaCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ShakaServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-shaka')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_shaka_table')
            ->hasCommand(ShakaCommand::class);
    }
}
