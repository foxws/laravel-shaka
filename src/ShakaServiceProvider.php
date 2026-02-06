<?php

declare(strict_types=1);

namespace Foxws\Shaka;

use Foxws\Shaka\Filesystem\MediaOpenerFactory;
use Foxws\Shaka\Filesystem\TemporaryDirectories;
use Foxws\Shaka\Support\Packager;
use Foxws\Shaka\Support\ShakaPackager;
use Illuminate\Support\Facades\Config;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ShakaServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-shaka')
            ->hasConfigFile('laravel-shaka')
            ->hasCommands([
                Commands\PackageInfoCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton('laravel-shaka-logger', function () {
            $logChannel = Config::get('laravel-shaka.log_channel');

            if ($logChannel === false) {
                return null;
            }

            return app('log')->channel($logChannel ?: Config::get('logging.default'));
        });

        $this->app->singleton('laravel-shaka-configuration', function () {
            $baseConfig = [
                'packager.binaries' => Config::string('laravel-shaka.packager.binaries'),
                'timeout' => Config::integer('laravel-shaka.timeout'),
            ];

            if ($configuredTemporaryRoot = Config::string('laravel-shaka.temporary_files_root')) {
                $baseConfig['temporary_directory'] = $configuredTemporaryRoot;
            }

            return $baseConfig;
        });

        $this->app->singleton(TemporaryDirectories::class, function () {
            return new TemporaryDirectories(
                Config::string('laravel-shaka.temporary_files_root', sys_get_temp_dir()),
                Config::string('laravel-shaka.cache_files_root') ?: null,
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
                Config::string('filesystems.default'),
                null,
                fn () => app(Packager::class)
            );
        });
    }
}
