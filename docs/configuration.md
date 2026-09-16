---
section: Reference
order: 1
---

# Configuration

Laravel Shaka is configured through the `config/laravel-shaka.php` file.

## Publishing configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag="shaka-config"
```

## Configuration options

### Packager binary

Set the path to the Shaka Packager binary:

```php
'packager' => [
    'binaries' => env('PACKAGER_PATH', '/usr/local/bin/packager'),
],
```

**Environment variable:**

```env
PACKAGER_PATH=/usr/local/bin/packager
```

**Multiple binary paths:**

You can also give it a list — the package uses the first one it finds:

```php
'packager' => [
    'binaries' => [
        '/usr/local/bin/packager',
        '/usr/bin/packager',
        '/opt/shaka-packager/packager',
    ],
],
```

### Timeout

Set the maximum time a packaging operation is allowed to run:

```php
'timeout' => 60 * 60 * 4, // 4 hours in seconds
```

**Environment variable:**

```env
PACKAGER_TIMEOUT=14400
```

**Things to keep in mind:**

| Factor | Effect on timeout |
| --- | --- |
| Longer videos | Need more time |
| 4K content | Takes noticeably longer than 1080p |
| Multiple quality variants | Multiply the total processing time |
| Server's PHP `max_execution_time` | Should also be able to cover the job |

### Logging

Turn on logging to track packaging operations:

```php
'log_channel' => env('PACKAGER_LOG_CHANNEL', false),
```

**Environment variables:**

```env
# Disable logging (default)
PACKAGER_LOG_CHANNEL=false

# Use the default log channel
PACKAGER_LOG_CHANNEL=stack

# Use a custom channel
PACKAGER_LOG_CHANNEL=packager
```

**Custom log channel:**

Define a custom channel in `config/logging.php`:

```php
'channels' => [
    'packager' => [
        'driver' => 'daily',
        'path' => storage_path('logs/packager.log'),
        'level' => 'debug',
        'days' => 14,
    ],
],
```

### Temporary files

Set where temporary files are stored during packaging:

```php
'temporary_files_root' => env('PACKAGER_TEMPORARY_FILES_ROOT', storage_path('app/packager/temp')),
```

**Environment variable:**

```env
PACKAGER_TEMPORARY_FILES_ROOT=/tmp/packager
```

**Things to keep in mind:**

- Remote files (S3, etc.) are copied here before processing starts.
- Make sure there's enough disk space.
- Clean up regularly with `cleanupTemporaryFiles()`.
- For faster processing, point this at `/dev/shm` (a RAM disk).

### Encrypted files

Set where encrypted temporary files are stored:

```php
'temporary_files_encrypted' => env('PACKAGER_TEMPORARY_ENCRYPTED', '/dev/shm'),
```

**Environment variable:**

```env
PACKAGER_TEMPORARY_ENCRYPTED=/dev/shm
```

### Storage space guards

These settings make packaging fail fast with a clear exception, instead of a job dying part-way through, when a size-limited storage location (for example a size-limited tmpfs) is running low on space. All three checks are off by default (`0`), so upgrading the package doesn't change behavior for existing installs until you turn them on.

```php
'temporary_files_min_free' => env('PACKAGER_TEMPORARY_MIN_FREE', 0),
'temporary_files_size_multiplier' => env('PACKAGER_TEMPORARY_SIZE_MULTIPLIER', 1.5),
'cache_files_min_free' => env('PACKAGER_CACHE_MIN_FREE', 0),
```

**Environment variables:**

```env
PACKAGER_TEMPORARY_MIN_FREE=1073741824       # 1 GiB floor on temporary_files_root
PACKAGER_TEMPORARY_SIZE_MULTIPLIER=1.5       # safety factor applied to the job's input size
PACKAGER_CACHE_MIN_FREE=10485760             # 10 MiB floor on cache_files_root
```

**How each check works:**

| Setting | What it checks |
| --- | --- |
| `temporary_files_min_free` | A fixed floor checked against `temporary_files_root` before a job starts. |
| `temporary_files_size_multiplier` | Before packaging starts, the combined size of the job's own input files is multiplied by this number and checked as well, on top of the fixed floor. Packager repackages/segments input that's already encoded, rather than re-encoding it, so output size tracks input size closely — this catches a job whose *own* footprint won't fit, not just a root that happens to be nearly full for other reasons. |
| `cache_files_min_free` | A separate floor for `cache_files_root` (manifests and encryption keys). It's kept independent of `temporary_files_min_free` because this root is often a much smaller mount than the main temporary root (see the tmpfs example below) — a multi-GB floor sized for the main root would permanently break a small cache mount. |

Both checks throw `Foxws\Shaka\Exceptions\InsufficientStorageException`, which you can catch separately from other packaging failures (for example, in a queued job's `failed()` method).

**Tuning the multiplier:** `1.5` is a starting point, not a measurement. After a real job runs, compare `du -sh` on its temporary directory against the combined size of its source input files, and adjust `PACKAGER_TEMPORARY_SIZE_MULTIPLIER` from there. If you generate separate HLS and DASH segment sets instead of sharing CMAF segments across both, expect real usage closer to 2x than 1.5x.

#### Example: temporary_files_root on a Podman tmpfs

If you run Horizon/queue workers in Podman and want packaging scratch space to live in RAM instead of hitting your NVMe drive (segments are written once, uploaded, then deleted — nothing here needs to survive a restart), mount the root as a `tmpfs` in your `.container` quadlet instead of a regular volume:

```ini
# horizon.container (podman quadlet)
[Container]
...
# Was: Volume=app-cache:/cache:rw,z
Tmpfs=/cache:rw,size=12g,mode=1777
```

Then point the package at it, and set a floor sized to fit comfortably inside that tmpfs, leaving headroom for concurrent jobs:

```env
PACKAGER_TEMPORARY_FILES_ROOT=/cache/temp/packager
PACKAGER_TEMPORARY_MIN_FREE=1073741824   # 1 GiB
PACKAGER_TEMPORARY_SIZE_MULTIPLIER=1.5
```

`cache_files_root` (manifests/keys) typically points at `/dev/shm`, a separate tmpfs the container runtime mounts automatically. Keep its floor small relative to that mount's actual size (often just tens of MB, via a container's `ShmSize`):

```env
PACKAGER_CACHE_FILES_ROOT=/dev/shm
PACKAGER_CACHE_MIN_FREE=10485760   # 10 MiB
```

> A tmpfs `size=` is a quota, not a reservation — it doesn't stop concurrent jobs from collectively going over it. Pair this with a concurrency limit on your queue (for example, Horizon's `maxProcesses`) sized so `workers x largest expected job footprint` stays comfortably under the tmpfs size. Treat `temporary_files_min_free` as a fail-fast safety net for jobs that slip past that limit, not as the main defense.

## Complete configuration example

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Shaka Packager Binary
    |--------------------------------------------------------------------------
    |
    | Path to the Shaka Packager binary. Can be a string or array of paths.
    | The system will use the first available binary.
    |
    */

    'packager' => [
        'binaries' => env('PACKAGER_PATH', '/usr/local/bin/packager'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum execution time in seconds for packaging operations.
    | Adjust based on your content size and quality requirements.
    |
    */

    'timeout' => env('PACKAGER_TIMEOUT', 60 * 60 * 4), // 4 hours

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Log channel for packaging operations. Set to false to disable logging.
    | Use your default log channel or define a custom one.
    |
    */

    'log_channel' => env('PACKAGER_LOG_CHANNEL', false),

    /*
    |--------------------------------------------------------------------------
    | Temporary Files
    |--------------------------------------------------------------------------
    |
    | Root directory for temporary files during packaging operations.
    | Remote files are downloaded here before processing.
    |
    */

    'temporary_files_root' => env('PACKAGER_TEMPORARY_FILES_ROOT', storage_path('app/packager/temp')),

    /*
    |--------------------------------------------------------------------------
    | Encrypted Temporary Files
    |--------------------------------------------------------------------------
    |
    | Directory for encrypted temporary files. Using /dev/shm (RAM disk)
    | provides better performance for encryption operations.
    |
    */

    'temporary_files_encrypted' => env('PACKAGER_TEMPORARY_ENCRYPTED', '/dev/shm'),

    /*
    |--------------------------------------------------------------------------
    | Storage Space Guards
    |--------------------------------------------------------------------------
    |
    | Fail fast with a clear exception instead of a job dying mid-packaging
    | when a storage-constrained root runs low on space. Set to 0 to
    | disable a given check.
    |
    */

    'temporary_files_min_free' => env('PACKAGER_TEMPORARY_MIN_FREE', 0),
    'temporary_files_size_multiplier' => env('PACKAGER_TEMPORARY_SIZE_MULTIPLIER', 1.5),
    'cache_files_min_free' => env('PACKAGER_CACHE_MIN_FREE', 0),

];
```

## Environment configuration

Example `.env` configuration:

```env
# Shaka Packager Configuration
PACKAGER_PATH=/usr/local/bin/packager
PACKAGER_TIMEOUT=14400
PACKAGER_LOG_CHANNEL=packager
PACKAGER_TEMPORARY_FILES_ROOT=/tmp/packager
PACKAGER_TEMPORARY_ENCRYPTED=/dev/shm
PACKAGER_TEMPORARY_MIN_FREE=1073741824
PACKAGER_TEMPORARY_SIZE_MULTIPLIER=1.5
PACKAGER_CACHE_MIN_FREE=10485760
```

## Verification

After configuring the package, verify your setup:

```bash
php artisan shaka:info
```

This command checks that:

- The binary path is valid and executable
- The binary's version can be read
- The timeout is configured
- The logger is set up correctly

## Runtime configuration

You can also configure the packager at runtime instead of (or on top of) the config file:

```php
use Foxws\Shaka\Support\Packager\Packager;
use Foxws\Shaka\Support\Packager\ShakaPackager;

// Create with custom configuration
$driver = new ShakaPackager(
    binaryPath: '/custom/path/packager',
    logger: Log::channel('custom'),
    timeout: 7200
);

$packager = new Packager($driver, Log::channel('custom'));
```

Or using the static `create()` method:

```php
$packager = Packager::create(
    logger: Log::channel('packager'),
    configuration: [
        'packager' => ['binaries' => '/custom/path/packager'],
        'timeout' => 7200,
    ]
);
```

## Driver configuration

You can also change driver settings after it's been created:

```php
$driver = app(ShakaPackager::class);

// Change timeout
$driver->setTimeout(7200);

// Change logger
$driver->setLogger(Log::channel('debug'));
```

## Troubleshooting

### Binary not found

If you see an "Executable not found" error:

1. Check that the binary exists: `which packager`
2. Check its permissions: `ls -l /usr/local/bin/packager`
3. Make sure it's executable: `chmod +x /usr/local/bin/packager`
4. Update the config with the correct path

### Timeout errors

If operations time out:

1. Increase the timeout in the config
2. Check your server's PHP `max_execution_time`
3. Move long operations onto a queue
4. Reduce video settings (resolution, bitrate)

### Permission errors

If you see permission errors:

1. Check the temporary directory's permissions
2. Make sure the web server user can write to it
3. Confirm the binary is executable
4. Check SELinux/AppArmor policies

### Logging issues

If logging isn't working:

1. Confirm the log channel exists in `config/logging.php`
2. Check the log directory's permissions
3. Make sure the channel is configured correctly
4. Test it with a simple log entry

See the [Troubleshooting](./troubleshooting.md) guide for more issues and solutions.
