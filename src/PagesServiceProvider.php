<?php

namespace ItmoSoft\Pages;

use Illuminate\Support\ServiceProvider;
use ItmoSoft\Pages\Services\PageManager;

class PagesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/pages.php', 'itmosoft-pages');
        $this->app->singleton(PageManager::class);
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/pages.php' => config_path('itmosoft-pages.php')], 'itmosoft-pages-config');
        $this->publishes([
            __DIR__.'/../database/migrations/create_pages_table.php.stub' => database_path('migrations/'.date('Y_m_d_His', time()).'_create_pages_table.php'),
            __DIR__.'/../database/migrations/create_page_revisions_table.php.stub' => database_path('migrations/'.date('Y_m_d_His', time() + 1).'_create_page_revisions_table.php'),
        ], 'itmosoft-pages-migrations');

        if (! $this->app->routesAreCached()) {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        }
    }
}
