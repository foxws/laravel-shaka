---
section: Advanced
order: 2
---

# Troubleshooting

Start with `php artisan shaka:info`. It shows whether the binary runs and where temporary files go.

## Packager command failed

```text
Foxws\Shaka\Exceptions\RuntimeException: Packager command failed: ...
```

Shaka Packager exited with an error. The rest of the message is its own output, which usually names the problem.

- **`not found` or exit code 127:** the binary isn't at `PACKAGER_PATH` or on the `PATH`. Check with `which packager`, and make it executable with `chmod +x`.
- **A stream that doesn't exist:** you added an audio stream for a file without audio, or a video stream for an audio-only file. Only add the streams the file has.
- **`Unknown field in stream descriptor`:** a file name contains a comma or another character the descriptor can't hold. Keep `PACKAGER_FORCE_GENERIC_INPUT=true` (the default).

To see the exact command, call `->getCommand()` instead of `save()`, and run it in a terminal.

## The job times out

`Illuminate\Process\Exceptions\ProcessTimedOutException` means Shaka Packager ran longer than `PACKAGER_TIMEOUT` (default 4 hours). Raise it, and check the job and queue timeouts too. See [Queues](queue-integration.md).

If the worker is killed without that exception, the job's own `$timeout` ran out first.

## Not enough space

```text
InsufficientStorageException: Insufficient storage space in [/cache/temp/packager]: 314572800 bytes free, 1610612736 bytes required.
```

A [storage guard](configuration.md) stopped the job before it started, so nothing needs cleaning up. Free up space, give the mount more room, or run fewer jobs at once. If it happens under load, fewer concurrent jobs is usually the right fix.

## Packager produced no output files

The run finished but wrote nothing. Usually no streams were added, or the input has no usable video or audio. Check the input with `ffprobe`.

## Files failed to copy

```text
RuntimeException: 2 file(s) failed to copy to disk "s3": video.mp4: ...
```

The upload failed for the files listed. Common causes:

- Wrong S3 credentials, bucket or endpoint in `config/filesystems.php`.
- `withVisibility('public')` on a bucket that blocks public ACLs. Leave visibility unset, or allow ACLs.
- A self-hosted S3 store that needs `use_path_style_endpoint`.

## Temporary files pile up

Something isn't calling `cleanupTemporaryFiles()` after a failure. Call it in `finally` in every job. See [Usage](usage.md).

## The player won't play the stream

- **Nothing loads in the browser:** the bucket needs a CORS policy that allows your site.
- **It stops after a while:** signed URLs in the playlist expired. Give segment URLs a longer lifetime, or reload the playlist.
- **Subtitles don't show in DASH:** use an `.mp4` output for text streams, not `.vtt`.
- **Encrypted video doesn't play in Safari:** use the `cbcs` protection scheme. See [Encryption](aes-encryption.md).
- **Encrypted DASH doesn't play:** the player needs the key, for example through Shaka Player's `clearKeys` setting.

## Logs

Packager commands, with keys redacted, and their output are logged to `PACKAGER_LOG_CHANNEL`. Use a separate channel to keep them apart:

```env
PACKAGER_LOG_CHANNEL=packager
```

## Still stuck?

Open an [issue](https://github.com/foxws/laravel-shaka/issues) with the output of `php artisan shaka:info`, the command from `getCommand()`, and the error message.
