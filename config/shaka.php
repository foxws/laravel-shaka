<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Shaka Packager
|--------------------------------------------------------------------------
|
| Temporary files, logging, disks and the packaging options themselves come
| from laravel-media's config/media.php and its packaging builder. Set
| MEDIA_PACKAGER=shaka to package with Shaka Packager by default.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Binary
    |--------------------------------------------------------------------------
    |
    | The Shaka Packager binary: a name looked up in the PATH, or a full path
    | such as /usr/local/bin/packager. "php artisan media:info" shows what was
    | found.
    |
    */

    'binary' => env('SHAKA_PACKAGER_BINARY', 'packager'),

    /*
    |--------------------------------------------------------------------------
    | Timeout
    |--------------------------------------------------------------------------
    |
    | The seconds one packager run may take, unless the builder's timeout()
    | sets another. Keep this at or below your queue job's $timeout.
    |
    */

    'timeout' => (int) env('SHAKA_PACKAGER_TIMEOUT', 14400),

];
