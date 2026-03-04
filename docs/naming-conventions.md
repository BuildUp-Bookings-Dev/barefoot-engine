# Barefoot Engine Naming Conventions

## Top-Level Ownership

- `bootstrap/`: plugin bootstrap and lifecycle classes only
- `support/`: tiny shared infrastructure only
- `modules/`: non-admin, non-widget business capabilities only
- `admin/`: admin-owned PHP, settings, and templates only
- `public/`: shared site-facing runtime and widget-family PHP logic only
- `assets/src/`: source assets grouped by runtime surface

## Path Rules

- Plugin-owned directories must use lowercase kebab-case.
- Plugin-owned PHP, JS, CSS, and SCSS files must use lowercase kebab-case.
- Do not use underscores in plugin-owned path segments.
- Do not use uppercase letters in plugin-owned path segments.
- Class names and namespaces may remain PHP-style even when the filesystem path is kebab-case.

## Placement Rules

- Put plugin startup and lifecycle wiring in `bootstrap/`.
- Put shared infrastructure like the hook loader and asset manifest reader in `support/`.
- Put non-admin, non-widget capabilities in `modules/`.
- Put admin-owned PHP, settings, and templates in `admin/`.
- Put shared site-facing orchestration in `public/`.
- Put each widget family in its own `public/widgets/<widget-name>/` directory.

## Templates

- Templates render prepared data only.
- Templates must not instantiate services, register hooks, or persist data.
- Admin templates belong in `admin/views/`.
- Future public-facing templates should live under `public/views/` if they are introduced later.

## Assets

- Admin assets belong in `assets/src/admin/`.
- Shared public assets belong in `assets/src/public/`.
- Widget assets currently flow through `assets/src/public/` unless a dedicated widget asset surface is introduced later.

## Exemptions

These conventional root files are allowed to keep their standard names:

- `barefoot-engine.php`
- `uninstall.php`
- `README.md`
- `CHANGELOG.md`
- `readme.txt`
- `composer.json`
- `composer.lock`
- `package.json`
- `package-lock.json`
- `vite.config.js`
- `postcss.config.cjs`
- `phpcs.xml.dist`

These directories are excluded from naming enforcement:

- `libraries/vendor/`
- `node_modules/`
- `assets/dist/`
- `dist/`
- `.git/`
- `.build/`
- `.playwright-cli/`

## Examples

Good:

- `modules/api-integration/api-integration-controller.php`
- `public/widgets/example-widget/example-widget-shortcode.php`
- `assets/src/public/index.js`
- `admin/views/tabs/properties.php`

Bad:

- `src/Services/General_Settings.php`
- `src/Widgets/Example/Example_Widget_Shortcode.php`
- `templates/admin/components/header.php`
- `assets/src/js/widgets/legacy-widget.js`
