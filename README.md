Exception reporter for Super Admin
==================================

Stores reported exceptions in the database and shows them in the admin panel: the request, headers, cookies and the stack trace with source code, plus how often the same problem happened.

**Documentation:** https://super-admin.org/laravel/extension-reporter/

Requirements: PHP 8.0+, Laravel 8 – 13, Super Admin 1.x.

## Installation

```bash
composer require super-admin-org/reporter
php artisan vendor:publish --tag=super-admin-reporter   # Prism highlighter
php artisan migrate
php artisan admin:import reporter
```

Send exceptions to it.

Laravel 11 and newer, `bootstrap/app.php`:

```php
use SuperAdmin\Admin\Reporter\Reporter;

->withExceptions(function (Exceptions $exceptions) {
    $exceptions->report(function (Throwable $e) {
        Reporter::report($e);
    });
})
```

Laravel 8 – 10, `app/Exceptions/Handler.php`:

```php
public function register()
{
    $this->reportable(function (Throwable $e) {
        \SuperAdmin\Admin\Reporter\Reporter::report($e);
    });
}
```

Open `/admin/exceptions`.

## Privacy

Passwords, tokens, card numbers, `Authorization`/`Cookie` headers and cookie values are never stored; they are replaced by `[redacted]`.

## Configuration

```php
// config/admin.php
'extensions' => [
    'reporter' => [
        'table'          => 'laravel_exceptions',
        'redact'         => ['national_id'],   // extra request fields to hide
        'store_cookies'  => false,             // true keeps cookie values (except redacted names)
        'retention_days' => 30,                // used by reporter:prune
    ],
],
```

Delete old rows with `php artisan reporter:prune` (or `--days=7`), for example daily from the scheduler.

## Tests

```bash
composer install
composer test
```

License
-------
Licensed under [The MIT License (MIT)](LICENSE).
