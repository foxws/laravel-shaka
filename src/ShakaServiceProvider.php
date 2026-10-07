<?php

declare(strict_types=1);

namespace Foxws\Shaka;

use Foxws\Media\Executables\Executables;
use Foxws\Media\Opener;
use Foxws\Media\Packaging\PackagerManager;
use Foxws\Media\Packaging\PackagingBuilder;
use Illuminate\Support\ServiceProvider;

class ShakaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/shaka.php', 'shaka');

        $this->callAfterResolving(PackagerManager::class, function (PackagerManager $packagers): void {
            $packagers->extend('shaka', fn (): ShakaPackager => new ShakaPackager);
        });

        Opener::macro('shaka', function (): PackagingBuilder {
            /** @var Opener $this */
            return $this->package()->using('shaka');
        });
    }

    public function boot(): void
    {
        $this->app->make(Executables::class)->register(ShakaExecutable::Packager);

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/shaka.php' => config_path('shaka.php'),
        ], ['shaka', 'shaka-config']);
    }
}
