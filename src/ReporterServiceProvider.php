<?php

namespace SuperAdmin\Admin\Reporter;

use Illuminate\Support\ServiceProvider;
use SuperAdmin\Admin\Reporter\Console\PruneCommand;

class ReporterServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function boot()
    {
        // Template-aware: <views>/<template>/ and <template>/extensions/super-admin-reporter/ override <views>/.
        if (class_exists(\SuperAdmin\Admin\Template\TemplateManager::class)) {
            \SuperAdmin\Admin\Facades\Admin::template()->extensionViews('super-admin-reporter', __DIR__.'/../resources/views');
        } else {
            $this->loadViewsFrom(__DIR__.'/../resources/views', 'super-admin-reporter');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes(
                [__DIR__.'/../resources/assets/' => public_path('vendor/super-admin-reporter')],
                ['super-admin-reporter', 'laravel-assets']
            );

            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

            $this->commands([PruneCommand::class]);
        }

        Reporter::boot();
    }
}
