Exception reporter for Super-Admin
=================================

This tool stores the exception information into the database and provides a developer-friendly web interface to view the exception information.

[![StyleCI](https://styleci.io/repos/508366116/shield?branch=main)](https://styleci.io/repos/508366116)
[![Packagist](https://img.shields.io/github/license/super-admin-org/reporter.svg?maxAge=2592000&style=flat-square&color=brightgreen)](https://packagist.org/packages/super-admin-org/reporter)
[![Total Downloads](https://img.shields.io/packagist/dt/super-admin-org/reporter.svg?style=flat-square&color=brightgreen)](https://packagist.org/packages/super-admin-org/reporter)
[![Pull request welcome](https://img.shields.io/badge/pr-welcome-green.svg?style=flat-square&color=brightgreen)]()

## Screenshot

![super-admin-reporter](https://github.com/user-attachments/assets/da049dcc-74ee-4d03-b19e-1fc58eff8499)


## Installation

```
$ composer require super-admin-org/reporter

$ php artisan vendor:publish --tag=super-admin-reporter

$ php artisan migrate --path=vendor/super-admin-org/reporter/database/migrations

$ php artisan admin:import reporter
```

Open `app/Exceptions/Handler.php`,
1) Add: `use SuperAdmin\Admin\Reporter\Reporter;`
2) Call `Reporter::report()` inside `register` ... `reportable` method:
```php
<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use SuperAdmin\Admin\Reporter\Reporter;
use Throwable;


class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            // Add This line
            Reporter::report($e);
        });
    }
}

```

Open `http://localhost/admin/exceptions` to view exceptions.

License
------------
Licensed under [The MIT License (MIT)](LICENSE).
