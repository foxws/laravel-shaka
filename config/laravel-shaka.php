<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Packager Binaries
    |--------------------------------------------------------------------------
    |
    | Path to the Shaka Packager binary executable.
    |
    */

    'packager' => [
        'binaries' => env('PACKAGER_PATH', 'packager'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Segment Duration
    |--------------------------------------------------------------------------
    |
    | Default duration of each segment in seconds.
    | A typical value is between 4 and 10 seconds.
    |
    | Lower values: faster seeking, more HTTP requests
    | Higher values: fewer HTTP requests, slower seeking
    |
    */

    'segment_duration' => (int) env('PACKAGER_SEGMENT_DURATION', 10),

    /*
    |--------------------------------------------------------------------------
    | Packager Options
    |--------------------------------------------------------------------------
    |
    | Configuration options for Shaka Packager.
    | For more information, visit: https://shaka-project.github.io/shaka-packager/html/options.html
    |
    | Available options:
    |   - num_subsegments_per_sidx: Number of subsegments per SIDX box
    |     (0 = disable, reduces overhead)
    |   - fragment_sap_aligned: Align fragments to stream access points
    |     (improves seeking performance)
    |   - mp4_include_pssh_in_stream: Include PSSH in stream for better
    |     DRM compatibility
    |   - generate_static_live_mpd: Generate static MPD for DASH
    |     (improves caching)
    |   - default_language: Default language for audio/subtitle tracks
    |
    */

    'packager_options' => env('PACKAGER_OPTIONS', [
        'num_subsegments_per_sidx' => 0,
        'fragment_sap_aligned' => true,
        'mp4_include_pssh_in_stream' => true,
        'generate_static_live_mpd' => true,
        'default_language' => 'en',
    ]),

    /*
    |--------------------------------------------------------------------------
    | Force Generic Input Paths
    |--------------------------------------------------------------------------
    |
    | Whether to force using generic input paths for media files.
    | This can help normalize path handling across different systems.
    |
    */

    'force_generic_input' => env('PACKAGER_FORCE_GENERIC_INPUT', true),

    /*
    |--------------------------------------------------------------------------
    | Packaging Process Timeout
    |--------------------------------------------------------------------------
    |
    | Timeout for the packaging process in seconds.
    | Default: 14400 seconds (4 hours)
    |
    */

    'timeout' => env('PACKAGER_TIMEOUT', 14400),

    /*
    |--------------------------------------------------------------------------
    | Log Channel
    |--------------------------------------------------------------------------
    |
    | The log channel for packager output. Set to null to use the default channel,
    | or false to disable logging entirely.
    |
    */

    'log_channel' => env('PACKAGER_LOG_CHANNEL', null),

    /*
    |--------------------------------------------------------------------------
    | Temporary Files Root
    |--------------------------------------------------------------------------
    |
    | Root directory for temporary files used during the packaging process.
    | These are typically large video chunks and intermediate files.
    |
    */

    'temporary_files_root' => env('PACKAGER_TEMPORARY_FILES_ROOT', storage_path('app/packager/temp')),

    /*
    |--------------------------------------------------------------------------
    | Cache Files Root
    |--------------------------------------------------------------------------
    |
    | Cache storage directory for small files (e.g., RAM disk like /dev/shm).
    |
    | Used for:
    |   - Encryption keys
    |   - Manifests
    |   - Other small files that benefit from faster I/O
    |
    | NOT used for large video files, which use temporary_files_root
    | to avoid consuming excessive RAM.
    |
    | Set to null to disable and use temporary_files_root for all operations.
    |
    */

    'cache_files_root' => env('PACKAGER_CACHE_FILES_ROOT', '/dev/shm'),

];
