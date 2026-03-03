# Barefoot Engine Naming Conventions

## Top-Level Ownership

- `bootstrap/`: plugin bootstrap and lifecycle classes only
- `support/`: tiny shared infrastructure only
- `modules/`: non-widget business capabilities only
- `widgets/`: widget-family PHP logic only
- `public/`: shared site-facing runtime behavior only
- `settings/`: plugin-wide shared settings only
- `views/`: render-only PHP templates only
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
- Put non-widget capabilities in `modules/`.
- Put each widget family in its own `widgets/<widget-name>/` directory.
- Put shared site-facing orchestration in `public/`.
- Put shared plugin-wide settings in `settings/`.
- Put render-only PHP in `views/`.

## Views

- Views render prepared data only.
- Views must not instantiate services, register hooks, or persist data.
- Admin templates belong in `views/admin/`.
- Future widget or module templates should mirror their owner, for example `views/search-widget/`.

## Assets

- Admin assets belong in `assets/src/admin/`.
- Shared public assets belong in `assets/src/public/`.
- Widget assets belong in `assets/src/widgets/<widget-name>/`.

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
- `widgets/search-widget/search-widget-shortcode.php`
- `assets/src/widgets/search-widget/index.js`
- `views/admin/tabs/properties.php`

Bad:

- `src/Services/General_Settings.php`
- `src/Widgets/Search/Search_Widget_Shortcode.php`
- `templates/admin/components/header.php`
- `assets/src/js/widgets/search-widgets.js`
