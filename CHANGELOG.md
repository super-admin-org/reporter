# Changelog

## v1.0.6 - 2026-11-20

### Changed

- Works with super-admin 2.x: the package now requires `super-admin-org/super-admin` `^1.0|^2.0`. Earlier versions required `^1.0`, so Composer could not install them next to super-admin v2.0.0.
- Package homepage and documentation: https://super-admin.org/laravel/extension-reporter/

## v1.0.5 - 2026-11-20

### Added

- Assets are also tagged `laravel-assets`, so Laravel apps republish them after every `composer update`. The migration folder is declared on the extension, so `php artisan admin:upgrade` (in super-admin versions that have it) lists and runs it.

## v1.0.4 - 2026-11-20

### Security

- Passwords, tokens, card numbers, `Authorization`/`Cookie`/CSRF headers and cookie values are no longer stored (`[redacted]`). Extra fields: `admin.extensions.reporter.redact`.
- The exception list printed messages and paths as HTML; they are escaped now.

### Fixed

- The list page failed with "Undefined array key" for exceptions from `HEAD` and other uncommon methods; `DELETE` used a colour that does not exist.
- Values longer than their column (messages over 255 characters, long paths) made the insert fail silently on MySQL; values are cut to fit and new installs store `message` as `TEXT`.
- The **Input** column was always empty; it is replaced by **Seen**.
- `created_at` showed in ISO format.
- Stack frames with Windows paths (`D:\...`) were not parsed.
- `Tracer\Parser` implemented `Iterator` without return types (PHP 8.1 deprecation).
- The detail page had a nested heading.

### Added

- **Seen** column and badge: how many times the same exception happened at the same file and line.
- `php artisan reporter:prune {--days=}` and the `retention_days` option.
- Filters by path and HTTP method.
- Tests on Orchestra Testbench.

### Changed

- The Prism highlighter loads on the detail page only, not on every admin page.
- Only the `index`, `show` and `destroy` routes are registered.
- Requires PHP 8.0+ and declares Laravel 8 – 13.
