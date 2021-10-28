<?php

namespace Firevel\MysqlToSpanner;

use Firevel\MysqlToSpanner\Commands\SpannerDump;
use Firevel\MysqlToSpanner\Commands\SpannerMigrate;
use Illuminate\Support\ServiceProvider;

class MysqlToSpannerServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SpannerDump::class,
                SpannerMigrate::class,
            ]);
        }
    }
}
