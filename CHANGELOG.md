# Changelog

All notable changes to `laravel-shaka` will be documented in this file.

## [0.1.0] - 2025-12-20

### Added
- Initial alpha release
- Core Shaka Packager integration
- Fluent API for creating adaptive streaming content
- Support for DASH and HLS packaging
- Multi-disk support (local, S3, custom filesystems)
- MediaOpener for handling media files
- Stream configuration with video and audio streams
- Packager driver with ShakaPackager implementation
- Comprehensive test suite
- Architecture tests for code quality
- Configuration file with environment variable support
- **Dynamic URL Resolvers** - Separate classes for HLS and DASH manifest URL customization

### Features
- `MediaOpener` class with disk switching capabilities
- `Packager` class for managing packaging operations
- `Stream` builder for creating video/audio stream configurations
- `MediaCollection` for handling multiple media files
- `Disk` abstraction for filesystem operations
- Facade support for easy access
- Service provider with Laravel integration
- **DynamicHLSPlaylist** class for processing and customizing HLS playlists
  - `setKeyUrlResolver()` - Generate URLs for encryption keys
  - `setMediaUrlResolver()` - Generate URLs for media segments
  - `setPlaylistUrlResolver()` - Generate URLs for sub-playlists
  - Process and rewrite playlist files with custom URLs
  - Return as HTTP response with correct content type
  - Automatic URL caching for performance
  - **No temporary directory creation** - Only reads existing files
- **DynamicDASHManifest** class for processing and customizing DASH manifests
  - `setMediaUrlResolver()` - Generate URLs for media segments
  - `setInitUrlResolver()` - Generate URLs for initialization segments
  - Process and rewrite MPD manifest files
  - Return as HTTP response with correct content type
  - Automatic URL caching for performance
  - **No temporary directory creation** - Only reads existing files
- **Media class improvements**
  - Optional `$createTemporary` parameter to control temporary directory creation
  - Prevents unnecessary temp directories when only reading files
  - Backward compatible - defaults to `true` for packaging operations
- Support for CDN integration, signed URLs, and multi-tenant applications

### Encryption
- Browser-compatible HLS encryption using AES-128-CBC
  - Use `protection_scheme: 'cbc1'` for browser-compatible encryption
  - Use `.ts` segments (not `.mp4`) for encrypted content
  - Set `clear_lead: 0` to encrypt all segments from the start
  - Default (no protection_scheme) produces SAMPLE-AES which only works on native iOS/tvOS
- Comprehensive encryption documentation in README

### Testing
- Unit tests for all core components
- Tests for disk operations and switching
- Tests for stream configuration
- Tests for packager operations
- Tests for URL resolver functionality
- Architecture tests ensuring code standards

### Examples
- [UrlResolverExamples.php](examples/UrlResolverExamples.php) - Comprehensive URL resolver examples
- [FluentBuilderExamples.php](examples/FluentBuilderExamples.php) - Fluent API examples
- [FromDiskExamples.php](examples/FromDiskExamples.php) - Multi-disk usage examples
- [PackagerExamples.php](examples/PackagerExamples.php) - Direct packager usage examples
