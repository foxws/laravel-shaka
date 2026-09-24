---
section: Reference
order: 1
---

# Configuration

Publish the config file to change the defaults:

```bash
php artisan vendor:publish --tag="shaka-config"
```

This creates `config/laravel-shaka.php`. Most options can also be set in `.env`.

## Binary and process

| Key | Env | Default | Purpose |
| --- | --- | --- | --- |
| `packager.binaries` | `PACKAGER_PATH` | `packager` | Path to the Shaka Packager binary, or its name on `PATH`. |
| `timeout` | `PACKAGER_TIMEOUT` | `14400` | Seconds before the process is stopped. See [Queues](queue-integration.md). |
| `log_channel` | `PACKAGER_LOG_CHANNEL` | your `LOG_CHANNEL` | Channel for packager logs. `false` turns logging off, `null` uses the default channel. Keys are redacted from logs. |

## Packaging defaults

| Key | Env | Default | Purpose |
| --- | --- | --- | --- |
| `segment_duration` | `PACKAGER_SEGMENT_DURATION` | `6` | Segment length in seconds. Shorter segments seek faster but mean more requests. |
| `packager_options` | | `null` | An array of Shaka Packager options added to every run, such as `['allow_codec_switching' => true]`. Set it in the config file; an `.env` string is ignored. |
| `force_generic_input` | `PACKAGER_FORCE_GENERIC_INPUT` | `true` | Links each input as `input.<ext>` in a temporary folder, so names with commas or other special characters don't break the command. |

## Temporary files

Shaka Packager writes its whole output locally before it's uploaded. Inputs from remote disks are downloaded here too.

| Key | Env | Default | Purpose |
| --- | --- | --- | --- |
| `temporary_files_root` | `PACKAGER_TEMPORARY_FILES_ROOT` | `storage/app/packager/temp` | Where inputs and output are written. Needs room for the full output of every job running at the same time. |
| `cache_files_root` | `PACKAGER_CACHE_FILES_ROOT` | `/dev/shm` | Where encryption keys are written. A RAM disk keeps keys off the physical disk. Set it to an empty string to use `temporary_files_root`. |

### Storage guards

A job that runs out of space fails halfway, after doing most of the work. These checks stop it before it starts, with a `Foxws\Shaka\Exceptions\InsufficientStorageException`. All of them are off by default.

| Key | Env | Default | Purpose |
| --- | --- | --- | --- |
| `temporary_files_min_free` | `PACKAGER_TEMPORARY_MIN_FREE` | `0` | Minimum free bytes in `temporary_files_root`. |
| `temporary_files_size_multiplier` | `PACKAGER_TEMPORARY_SIZE_MULTIPLIER` | `1.5` | The job's input size is multiplied by this and must also fit. |
| `cache_files_min_free` | `PACKAGER_CACHE_MIN_FREE` | `0` | Minimum free bytes in `cache_files_root`. |

Shaka Packager doesn't re-encode, so the output is about as large as the input. The multiplier adds room for container overhead. To tune it, compare the size of a finished job's temporary folder (`du -sh`) with the size of its input.

The two roots have separate floors because they're often very different sizes. `/dev/shm` may only have a few dozen MB, while `temporary_files_root` may have many GB.

### Example: a RAM disk for temporary files

Segments are written once, uploaded and deleted, so they don't need to survive a restart. In a Podman Quadlet you can mount the temporary root as `tmpfs`:

```ini
[Container]
Tmpfs=/cache:rw,size=12g,mode=1777
```

```env
PACKAGER_TEMPORARY_FILES_ROOT=/cache/temp/packager
PACKAGER_TEMPORARY_MIN_FREE=1073741824
PACKAGER_CACHE_MIN_FREE=10485760
```

A `tmpfs` size is a limit, not a reservation. Several jobs together can still fill it, so also limit how many run at once.

## Uploads

These apply when the target is an S3 disk. On a local disk, files are moved with `rename()` instead.

| Key | Env | Default | Purpose |
| --- | --- | --- | --- |
| `concurrency_workers` | `PACKAGER_CONCURRENCY_WORKERS` | `30` | How many files upload at the same time. |
| `multipart_threshold` | `PACKAGER_MULTIPART_THRESHOLD` | `67108864` (64 MB) | Files this size or larger use a multipart upload. |
| `multipart_part_size` | `PACKAGER_MULTIPART_PART_SIZE` | `16777216` (16 MB) | Size of each part. At least 5 MB. |
| `multipart_concurrency` | `PACKAGER_MULTIPART_CONCURRENCY` | `5` | Parts uploaded at the same time, per file. |

A single upload is limited to 5 GB, so multipart is needed for larger files. It's also faster for big files, because parts go up in parallel. A failed multipart upload is cancelled, so its parts don't stay in the bucket.

For a local S3-compatible store (MinIO, RustFS, Garage), higher `concurrency_workers` values usually help. Against AWS over the internet, measure before going past 30 to 50.

The disk's own options are kept, such as `CacheControl` from `options` in `config/filesystems.php`.
