<?php

namespace SuperAdmin\Admin\Reporter;

use Illuminate\Routing\Router;
use SuperAdmin\Admin\Admin;

trait BootExtension
{
    public static function boot()
    {
        static::registerRoutes();

        static::importAssets();

        Admin::extend('reporter', __CLASS__);
    }

    /**
     * Register routes for super-admin.
     *
     * @return void
     */
    protected static function registerRoutes()
    {
        parent::routes(function ($router) {
            /* @var Router $router */
            $router->resource('exceptions', 'SuperAdmin\Admin\Reporter\ExceptionController');
        });
    }

    /**
     * {@inheritdoc}
     */
    public static function import()
    {
        parent::createMenu('Exception Reporter', 'exceptions', 'icon-bug');

        parent::createPermission('Exceptions reporter', 'ext.reporter', 'exceptions*');
    }

    public static function importAssets()
    {
        Admin::js('/vendor/super-admin-reporter/prism/prism.js');
        Admin::css('/vendor/super-admin-reporter/prism/prism.css');
    }
}
