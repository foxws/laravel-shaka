<?php

declare(strict_types=1);

namespace Foxws\Shaka\Commands;

use Composer\InstalledVersions;
use Foxws\Shaka\Exceptions\ExecutableNotFoundException;
use Foxws\Shaka\Support\Packager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Throwable;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class PackageInfoCommand extends Command
{
    protected $signature = 'shaka:info';

    protected $description = 'Display package information and verify Shaka Packager installation';

    public function handle(): int
    {
        info('Laravel Shaka Packager - Information & Verification');

        $shakaBinary = Config::get('laravel-shaka.packager.binaries', 'packager');
        $tempDir = Config::get('laravel-shaka.temporary_files_root', storage_path('app/shaka/temp'));
        $timeout = Config::get('laravel-shaka.timeout');
        $logChannel = Config::get('laravel-shaka.log_channel');
        $logStatus = $logChannel === false ? 'Disabled' : ($logChannel ?: Config::get('logging.default', 'Default'));

        $driverInitialized = false;
        try {
            Packager::create();
            $driverInitialized = true;
        } catch (ExecutableNotFoundException $e) {
            error('✗ Cannot initialize Packager driver: '.$e->getMessage());
        } catch (Throwable $e) {
            error('✗ Error initializing Packager driver: '.$e->getMessage());
        }

        table(
            ['Setting', 'Value', 'Status'],
            [
                ['Package Version', InstalledVersions::getPrettyVersion('foxws/laravel-shaka') ?? 'dev-main', '✓'],
                ['Packager Binary', $shakaBinary, $driverInitialized ? '✓' : '✗'],
                ['Timeout', "{$timeout}s", '✓'],
                ['Temp Directory', $tempDir, $this->getTempDirStatus($tempDir)],
                ['Logging', $logStatus, '✓'],
                ['Force Generic Input', Config::get('laravel-shaka.force_generic_input') ? 'Enabled' : 'Disabled', '✓'],
            ]
        );

        if (! is_writable($tempDir) && is_dir($tempDir)) {
            error("✗ Temporary directory is not writable: {$tempDir}");

            return self::FAILURE;
        }

        if (! is_dir($tempDir)) {
            warning('⚠ Temporary directory does not exist (will be created automatically)');
        }

        if (! $driverInitialized) {
            error('✗ Shaka Packager is not properly configured. Please check the errors above.');

            return self::FAILURE;
        }

        info('✅ Shaka Packager is properly configured and ready to use!');

        return self::SUCCESS;
    }

    protected function getTempDirStatus(string $tempDir): string
    {
        if (! is_dir($tempDir)) {
            return '⚠';
        }

        return is_writable($tempDir) ? '✓' : '✗';
    }
}
