# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added

### Changed

- Improved property search filtering so bedroom, bathroom, view, guest, type, rating, and amenity selections return matching properties more accurately.
- Updated the listings filter panel layout so amenities stay full width and scroll cleanly inside the modal.
- Updated property result clicks so selected dates and guest counts carry into the single-property booking widget.

### Fixed

- Fixed map/listing search results that could include properties outside the selected bedroom count or view.
- Fixed the booking widget so selected dates from search links are prefilled on the property page.

### Security

## [1.0.2] - 2026-04-30

### Added

### Changed
- Updated the default search widget to offer a View dropdown with Golf Course and Poolview options in place of the Bathrooms dropdown.
- Added view-aware search matching so Golf Course and Poolview searches can match properties through their synced amenities.

### Fixed

### Security

## [1.0.1] - 2026-04-01

### Added
- Added a new `Property Grid` shortcode and Elementor widget for rendering all active properties with responsive columns, optional filters, and client-side pagination.

### Changed
- Updated the public asset boot flow so the new Property Grid widget initializes reliably in Elementor preview and on the frontend.

### Fixed
- Fixed Property Grid card spacing and metadata layout to better match the Featured Properties card language.
- Fixed long Property Grid pagination strips by switching to a compact page-number window and displaying the filtered property count.

### Security

## [1.0.0] - 2026-03-24

### Added
- Added full property sync, partial sync, rate sync, and delta-refresh tooling for Barefoot property data.
- Added AJAX listings search with map integration, hybrid live availability checks, and stay-total price formatting.
- Added property booking widget, pricing table, booking checkout flow, booking records, and plugin-owned booking confirmation pages.
- Added featured properties slider and Elementor widget support.
- Added changelog automation script (`scripts/changelog.mjs`) to validate, promote, and extract release notes.
- Added release convenience scripts (`release:patch`, `release:minor`, `release:major`) and changelog validation command.

### Changed
- Switched release note source from generated GitHub notes to version sections in `CHANGELOG.md`.
- Updated the admin experience around API integration, properties, updates, and help.
- Updated listings to use GitHub-sourced widget libraries and newer native `bp-listings` capabilities such as infinite scroll and sticky/full-height map behavior.
- Updated booking confirmation and checkout presentation to use a cleaner plugin-owned flow and template.
- Updated package script and `.distignore` to ensure installable ZIPs include runtime assets and vendor autoload.

### Fixed
- Fixed packaging exclusions that previously dropped required runtime files from release ZIPs.
- Fixed updater repository constant to point at the public `BuildUp-Bookings-Dev/barefoot-engine` repository.
- Fixed listings regressions around map layout, search filters, optional fields, dropdown behavior, and stay-total card pricing.
- Fixed booking summaries to resolve the correct property data, payment schedule deposit amount, and payable amount.

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
