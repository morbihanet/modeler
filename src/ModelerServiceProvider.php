<?php
namespace Morbihanet\Modeler;

use Illuminate\Support\ServiceProvider;

class ModelerServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->registerMigrations();
        $this->registerPublishing();
    }

    private function registerMigrations()
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'../database/migrations');
        }
    }

    private function registerPublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '../database/migrations' => database_path('migrations'),
            ], 'modeler-migrations');
        }

        $this->publishes([
            __DIR__.'/../config/modeler.php' => config_path('modeler.php'),
        ], 'modeler-config');
    }
}
