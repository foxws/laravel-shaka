<?php

declare(strict_types=1);

return [

    'packager' => [
        'binaries' => env('PACKAGER_PATH', '/usr/local/bin/packager'),
    ],

    'timeout' => 60 * 60 * 4, // 4 hours

    'log_channel' => env('PACKAGER_PATH_LOG_CHANNEL', false),

    'temporary_files_root' => env('PACKAGER_TEMPORARY_FILES_ROOT', storage_path('app/packager/temp')),

    'temporary_files_encrypted_hls' => env('PACKAGER_PATH_TEMPORARY_ENCRYPTED_HLS', '/dev/shm'),

];
