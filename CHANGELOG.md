# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added
- Test release 0.1.2 for updater verification in WordPress admin.

### Changed

### Fixed

### Security

## [0.1.1] - 2026-02-27

### Added
- Added a functional `Updates` admin tab backed by REST and Alpine for live version/release visibility.
- Added changelog automation script (`scripts/changelog.mjs`) to validate, promote, and extract release notes.
- Added release convenience scripts (`release:patch`, `release:minor`, `release:major`) and changelog validation command.

### Changed
- Switched release note source from generated GitHub notes to version sections in `CHANGELOG.md`.
- Updated release script to enforce clean working tree and `main` branch before publishing.
- Updated package script and `.distignore` to ensure installable ZIPs include runtime assets and vendor autoload.

### Fixed
- Fixed packaging exclusions that previously dropped required runtime files from release ZIPs.
- Fixed updater repository constant to point at the public `BuildUp-Bookings-Dev/barefoot-engine` repository.

### Security
- Added stricter release preflight gates to reduce accidental invalid tags/releases.

## [0.1.0] - 2026-02-27

### Added
- Plugin scaffold with Vite build pipeline, admin tab shell, and public/admin asset enqueueing.
- API Integration and General Settings admin tabs with REST-backed save flows.
- GitHub updater integration using `plugin-update-checker`.

### Changed
- Standardized admin UI class names under `be-*` to reduce WordPress style conflicts.

### Fixed
- Autoloader mismatch for `Public_Facing` class file naming.

### Security
- Added capability checks and REST nonce usage for protected admin settings endpoints.
