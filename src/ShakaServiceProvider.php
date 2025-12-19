<?php

declare(strict_types=1);

namespace Foxws\Shaka;

use Foxws\Shaka\Filesystem\MediaOpenerFactory;
use Foxws\Shaka\Filesystem\TemporaryDirectories;
use Foxws\Shaka\Support\Packager;
use Foxws\Shaka\Support\ShakaPackager;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ShakaServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-shaka')
            ->hasConfigFile('laravel-shaka');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton('laravel-shaka-logger', function () {
            $logChannel = $this->app['config']->get('laravel-shaka.log_channel');

            if ($logChannel === false) {
                return null;
            }

            return $this->app['log']->channel($logChannel ?: $this->app['config']->get('logging.default'));
        });

        $this->app->singleton('laravel-shaka-configuration', function () {
            $config = $this->app['config'];

            $baseConfig = [
                'packager.binaries' => $config->get('laravel-shaka.packager.binaries'),
                'timeout' => $config->get('laravel-shaka.timeout'),
            ];

            if ($configuredTemporaryRoot = $config->get('laravel-shaka.temporary_files_root')) {
                $baseConfig['temporary_directory'] = $configuredTemporaryRoot;
            }

            return $baseConfig;
        });

        $this->app->singleton(TemporaryDirectories::class, function () {
            return new TemporaryDirectories(
                $this->app['config']->get('laravel-shaka.temporary_files_root', sys_get_temp_dir()),
            );
        });

        // Register the Shaka Packager Driver
        $this->app->singleton(ShakaPackager::class, function ($app) {
            $logger = $app->make('laravel-shaka-logger');
            $config = $app->make('laravel-shaka-configuration');

            return ShakaPackager::create($logger, $config);
        });

        // Register the Packager
        $this->app->singleton(Packager::class, function ($app) {
            $driver = $app->make(ShakaPackager::class);
            $logger = $app->make('laravel-shaka-logger');

            return new Packager($driver, $logger);
        });

        // Register the main class to use with the facade
        $this->app->singleton('laravel-shaka', function () {
            return new MediaOpenerFactory(
                $this->app['config']->get('filesystems.default'),
                null,
                fn () => $this->app->make(Packager::class)
            );
        });
    }
}
