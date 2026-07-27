<?php

declare(strict_types=1);

namespace Foxws\Shaka\Commands;

use Composer\InstalledVersions;
use Foxws\Shaka\Exceptions\RuntimeException;
use Foxws\Shaka\Support\ShakaPackager;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class PackageInfoCommand extends Command
{
    protected $signature = 'shaka:info';

    protected $description = 'Display package information and verify Shaka Packager installation';

    public function handle(Repository $config, ShakaPackager $packager): int
    {
        info('Laravel Shaka Packager - Information & Verification');

        $tempDir = $config->get('laravel-shaka.temporary_files_root', storage_path('app/shaka/temp'));
        $logChannel = $config->get('laravel-shaka.log_channel');
        $logStatus = $logChannel === false ? 'Disabled' : ($logChannel ?: $config->get('logging.default', 'Default'));

        // Actually invoke the binary (--version) so this reflects whether Shaka
        // Packager can really run, not just whether the config resolved.
        $binaryVersion = null;

        try {
            $binaryVersion = $packager->getVersion();
        } catch (RuntimeException $e) {
            error("✗ Cannot execute Shaka Packager binary: {$e->getMessage()}");
        }

        table(
            ['Setting', 'Value', 'Status'],
            [
                ['Package Version', InstalledVersions::getPrettyVersion('foxws/laravel-shaka') ?? 'dev-main', '✓'],
                ['Packager Binary', $packager->getBinaryPath(), $binaryVersion ? '✓' : '✗'],
                ['Binary Version', $binaryVersion ?? 'Unknown', $binaryVersion ? '✓' : '✗'],
                ['Timeout', "{$packager->getTimeout()}s", '✓'],
                ['Temp Directory', $tempDir, $this->getTempDirStatus($tempDir)],
                ['Logging', $logStatus, '✓'],
                ['Force Generic Input', $config->get('laravel-shaka.force_generic_input') ? 'Enabled' : 'Disabled', '✓'],
            ]
        );

        if (! is_writable($tempDir) && is_dir($tempDir)) {
            error("✗ Temporary directory is not writable: {$tempDir}");

            return self::FAILURE;
        }

        if (! is_dir($tempDir)) {
            warning('⚠ Temporary directory does not exist (will be created automatically)');
        }

        if (! $binaryVersion) {
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
