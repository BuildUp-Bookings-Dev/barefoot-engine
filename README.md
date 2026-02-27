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

## Release Automation

```bash
npm run release
```

Release script flow:

1. Bumps patch version (`0.1.0 -> 0.1.1` by default)
2. Builds assets
3. Packages plugin zip
4. Commits + tags (`vX.Y.Z`)
5. Pushes `main` and tags
6. Creates GitHub release and uploads zip asset

You can pass bump type manually:

```bash
node ./scripts/release.mjs minor
node ./scripts/release.mjs major
node ./scripts/release.mjs 1.2.3
```

## GitHub Updater

`plugin-update-checker` is wired through `src/Integrations/class-github-updater.php` and configured for GitHub Releases assets.

Update these constants in `barefoot-engine.php`:

- `BAREFOOT_ENGINE_GITHUB_REPOSITORY`
- `BAREFOOT_ENGINE_GITHUB_BRANCH`

## External Libraries

Composer vendor directory is isolated at:

- `libraries/vendor`
