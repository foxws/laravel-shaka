---
section: Reference
order: 3
---

# How It Works

## One job, step by step

1. **Open.** `Shaka::fromDisk('media')->open($path)` creates a `MediaOpener` with a fresh `Packager`. Each opened path becomes a `Media` object.
2. **Resolve inputs.** Shaka Packager needs local files. By default, an input on a local disk is linked into a temporary folder as `input.<ext>`. An input on a remote disk, such as S3, is downloaded there once.
3. **Build the command.** Each `add*Stream()` and `with*()` call adds to a `CommandBuilder`. Output names are placed in a new temporary folder under `temporary_files_root`.
4. **Run.** `save()` runs the `packager` binary through Laravel's `Process`, with the configured timeout.
5. **Upload.** The output folder and the key folder are copied to the target disk. That's in parallel for S3, a `rename()` for local disks, and a stream copy for anything else.
6. **Clean up.** The temporary folders are deleted after the upload. `cleanupTemporaryFiles()` removes whatever is still left, for example after a failure.

## Classes

| Class | Role |
| --- | --- |
| `Shaka` facade / `MediaOpenerFactory` | Makes a new `MediaOpener` for every call, so jobs don't share state. |
| `MediaOpener` | Holds the opened media, and passes stream and option calls on to the `Packager`. |
| `Packager` | Turns disk paths into local paths, fills the `CommandBuilder` and runs the job. |
| `CommandBuilder` | Collects streams and options, and builds the argument list. |
| `ShakaPackager` | Runs the binary and throws when it fails. |
| `MediaExporter` | Returned by `export()`. Holds the target disk and path, and runs everything on `save()`. |
| `PackagerResult` | The finished run. Its `toDisk()` uploads the output. |
| `TemporaryDirectories` | Creates, and later deletes, the temporary folders. Runs the storage guards. |
| `DynamicHLSPlaylist`, `DynamicDASHManifest` | Rewrite playlists when they're served. They don't run the binary. |

## Container bindings

| Binding | Lifetime |
| --- | --- |
| `ShakaPackager` | Singleton, shared by every job in a process. |
| `TemporaryDirectories` | Singleton. Tracks every folder so `cleanupTemporaryFiles()` can remove them. |
| `Packager` | Scoped, and the facade calls `fresh()` for each opener. |

Because `ShakaPackager` is shared, don't change it inside a job. Settings you change there stay for every later job in a queue worker or Octane process.

## Testing your code

You don't need the binary in unit tests:

- Build the job and assert on `->getCommand()` instead of calling `save()`.
- Use `Storage::fake()` for the input and target disks.
- Test the code that runs after packaging, such as `afterSaving()` callbacks, separately.
