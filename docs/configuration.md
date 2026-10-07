---
section: Reference
order: 1
---

# Configuration

Publish the config file to change the defaults:

```bash
php artisan vendor:publish --tag="shaka-config"
```

| Option | `.env` | Default | What it does |
| --- | --- | --- | --- |
| `binary` | `SHAKA_PACKAGER_BINARY` | `packager` | The Shaka Packager binary to run, from your `PATH` or a full path. |
| `timeout` | `SHAKA_PACKAGER_TIMEOUT` | `14400` | How long one packager run may take, in seconds, unless the builder's `timeout()` sets another. |

Temporary files, logging, S3 uploads and the default packager (`MEDIA_PACKAGER=shaka`) are set in laravel-media's `config/media.php`.
