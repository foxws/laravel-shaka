# Changelog

All notable changes to `laravel-shaka` will be documented in this file.

## [0.1.0-alpha] - 2025-12-19

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

### Features
- `MediaOpener` class with disk switching capabilities
- `Packager` class for managing packaging operations
- `Stream` builder for creating video/audio stream configurations
- `MediaCollection` for handling multiple media files
- `Disk` abstraction for filesystem operations
- Facade support for easy access
- Service provider with Laravel integration

### Testing
- Unit tests for all core components
- Tests for disk operations and switching
- Tests for stream configuration
- Tests for packager operations
- Architecture tests ensuring code standards
