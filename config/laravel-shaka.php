<?php

declare(strict_types=1);

return [

    'packager' => [
        'binaries' => env('PACKAGER_PATH', '/usr/local/bin/packager'),
    ],

    'timeout' => 60 * 60 * 4, // 4 hours

    'log_channel' => env('PACKAGER_LOG_CHANNEL', false),

    'temporary_files_root' => env('PACKAGER_TEMPORARY_FILES_ROOT', storage_path('app/packager/temp')),

    'temporary_files_encrypted' => env('PACKAGER_TEMPORARY_ENCRYPTED', '/dev/shm'),

    /**
     * Force generic input filenames to avoid special character issues with Shaka Packager.
     * When enabled, creates a temporary copy/symlink with a safe name (e.g., 'input.ext').
     * This prevents issues with filenames containing leading dashes, parentheses, commas, etc.
     */
    'force_generic_input' => env('PACKAGER_FORCE_GENERIC_INPUT', false),

];
