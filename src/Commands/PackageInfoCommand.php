<?php

declare(strict_types=1);

namespace Foxws\Shaka\Commands;

use Foxws\Shaka\Exceptions\ExecutableNotFoundException;
use Foxws\Shaka\Support\Packager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class PackageInfoCommand extends Command
{
    protected $signature = 'shaka:info';

    protected $description = 'Display package information and verify Shaka Packager installation';

    public function handle(): int
    {
        info('🔍 Laravel Shaka Packager - Information & Verification');

        // Package version
        $composerPath = base_path('vendor/foxws/laravel-shaka/composer.json');
        $packageVersion = 'dev-main';

        if (file_exists($composerPath)) {
            $composer = json_decode(file_get_contents($composerPath), true);
            $packageVersion = $composer['version'] ?? 'dev-main';
        }

        note("Package Version: {$packageVersion}");

        // Verify shaka installation
        $shakaBinary = Config::get('laravel-shaka.packager.binaries', 'packager');
        note("Packager Binary: {$shakaBinary}");

        $driverInitialized = false;
        try {
            $driver = Packager::create();
            $this->components->info('✓ Shaka Packager driver initialized successfully');
            $driverInitialized = true;
        } catch (ExecutableNotFoundException $e) {
            error('✗ Cannot initialize Packager driver');
            error($e->getMessage());
        } catch (\Exception $e) {
            error('✗ Error initializing Packager driver');
            error($e->getMessage());
        }

        // Configuration details
        $timeout = Config::get('laravel-shaka.timeout');
        $logChannel = Config::get('laravel-shaka.log_channel');
        $logStatus = $logChannel === false ? 'Disabled' : ($logChannel ?: Config::get('logging.default', 'Default'));
        $tempDir = Config::get('laravel-shaka.temporary_files_root', storage_path('app/shaka/temp'));
        $forceGeneric = Config::get('laravel-shaka.force_generic_input') ? 'Enabled' : 'Disabled';

        table(
            ['Configuration', 'Value', 'Status'],
            [
                ['Packager Binary', $shakaBinary, $driverInitialized ? '✓' : '✗'],
                ['Timeout', "{$timeout} seconds", '✓'],
                ['Temp Directory', $tempDir, $this->getTempDirStatus($tempDir)],
                ['Logging', $logStatus, '✓'],
                ['Force Generic Input', $forceGeneric, '✓'],
            ]
        );

        // Check temporary directory
        if (! is_dir($tempDir)) {
            warning('⚠ Temporary directory does not exist (will be created automatically)');
        } elseif (! is_writable($tempDir)) {
            error("✗ Temporary directory is not writable: {$tempDir}");

            return self::FAILURE;
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

        if (! is_writable($tempDir)) {
            return '✗';
        }

        return '✓';
    }
}
