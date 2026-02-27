# Barefoot Engine Plugin Scaffold

WordPress plugin scaffold for Barefoot API vacation rental integration.

## Requirements

- PHP 8.1+
- WordPress 6.5+
- Composer
- Node.js + npm
- GitHub CLI (`gh`) for release automation

## Setup

```bash
composer install
npm install
npm run build
```

## Build Commands

```bash
npm run build   # production build to assets/dist
npm run watch   # watch mode for JS/SCSS builds
```

## Packaging

```bash
npm run package
```

Creates installable zip in `dist/`.

Packaging preflight requires:
- `assets/dist/.vite/manifest.json` (run `npm run build`)
- `libraries/vendor/autoload.php` (run `composer install`)

## Release Automation

Use one of:

```bash
npm run release:patch
npm run release:minor
npm run release:major
```

`npm run release` defaults to patch bump.

Release script flow:
1. Verifies clean git state + `main` branch + `gh` auth
2. Validates changelog structure
3. Bumps plugin version
4. Promotes `## [Unreleased]` to `## [X.Y.Z] - YYYY-MM-DD`
5. Runs `composer install --no-dev --optimize-autoloader`
6. Builds assets and packages installable zip
7. Commits, tags (`vX.Y.Z`), pushes branch/tags
8. Creates GitHub release with notes pulled from the new version block in `CHANGELOG.md`

## Changelog Rules

Release notes are sourced from `CHANGELOG.md` only.

Use this structure:
- `## [Unreleased]`
- `## [X.Y.Z] - YYYY-MM-DD`
- `### Added`, `### Changed`, `### Fixed`, `### Security` inside each section

Validate changelog format:

```bash
npm run changelog:check
```

## GitHub Updater

`plugin-update-checker` is wired through `src/Integrations/class-github-updater.php` and configured for GitHub Releases assets.

Updater constants in `barefoot-engine.php`:

- `BAREFOOT_ENGINE_GITHUB_REPOSITORY`
- `BAREFOOT_ENGINE_GITHUB_BRANCH`

## External Libraries

Composer vendor directory is isolated at:

- `libraries/vendor`
