<?php
namespace Morbihanet\Modeler;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Artisan;

class ModelerServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if (!defined('PS')) {
            define('PS', PATH_SEPARATOR);
        }

        if (!defined('DS')) {
            define('DS', DIRECTORY_SEPARATOR);
        }

        Core::boot();
        Core::app($this->app);

        $this->registerMigrations();
        $this->registerPublishing();

        Route::get(config('modeler.scheduler_route', '/modeler/scheduler/cron'), function () {
            set_time_limit(0);
            $done = Scheduler::run();

            return response()->json(['status' => 'OK', 'done' => $done]);
        });

        Route::post(config('modeler.api_route', '/xapi'), function (Request $request): JsonResponse {
            set_time_limit(0);

            return Api::call($request);
        });

        Artisan::command('modeler:scheduler', function () {
            $json = file_get_contents(url(config('modeler.scheduler_route', '/modeler/scheduler/cron')));

            $this->comment($json);
        })->describe('Run Modeler Scheduler tasks');

        Artisan::command('modeler:seed', function () {
            (new Seeder)->run();
        })->describe('Run Modeler seeds');
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

    public function register()
    {
        $this->app->singleton('mail.manager', function ($app) {
            return new MailManager($app);
        });

        $this->app->bind('mailer', function ($app) {
            return $app->make('mail.manager')->mailer();
        });

        Event::add('core.locale', function (string $locale) {
            $this->app->setLocale($locale);
        });
    }
}
