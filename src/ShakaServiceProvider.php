<?php

declare(strict_types=1);

namespace Foxws\Shaka;

use Foxws\Shaka\Support\Filesystem\MediaOpenerFactory;
use Foxws\Shaka\Support\Filesystem\TemporaryDirectories;
use Foxws\Shaka\Support\Packager\Packager;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ShakaServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-shaka')
            ->hasConfigFile();
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
                // 'ffmpeg.binaries' => $config->get('laravel-shaka.ffmpeg.binaries'),
                // 'ffprobe.binaries' => $config->get('laravel-shaka.ffprobe.binaries'),
                // 'timeout' => $config->get('laravel-shaka.timeout'),
            ];

            if ($configuredTemporaryRoot = $config->get('laravel-shaka.temporary_files_root')) {
                $baseConfig['temporary_directory'] = $configuredTemporaryRoot;
            }

            return $baseConfig;
        });

        $this->app->singleton(TemporaryDirectories::class, function () {
            return new TemporaryDirectories(
                $this->app['config']->get('laravel-ffmpeg.temporary_files_root', sys_get_temp_dir()),
            );
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
