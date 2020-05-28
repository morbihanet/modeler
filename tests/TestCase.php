<?php

namespace Morbihanet\Modeler\Test;

use Morbihanet\Modeler\Core;
use Morbihanet\Modeler\Redis;
use Morbihanet\Modeler\Store;
use Morbihanet\Modeler\FileStore;
use Morbihanet\Modeler\MemoryStore;
use Morbihanet\Modeler\MailManager;
use Illuminate\Database\Schema\Blueprint;
use Orchestra\Testbench\TestCase as Orchestra;
use M6Web\Component\RedisMock\RedisMock as Mock;

abstract class TestCase extends Orchestra
{
    public function setUp(): void
    {
        parent::setUp();

        Core::boot();
        Core::app($this->app);

        $this->setUpDatabase();

        $this->app['config']->set('database.redis.client', 'predis');
        Redis::engine(new Mock);

        $this->app['config']->set('database.connections.db_memory', [
            'driver'    => 'sqlite',
            'database'  => ':memory:',
            'prefix'    => '',
        ]);

        $this->app['db']->connection('db_memory')->getSchemaBuilder()->create('kv', function (Blueprint $table) {
            $table->string('k')->primary()->unique();
            $table->longText('v')->nullable();
            $table->unsignedBigInteger('e')->index()->default(0);
            $table->timestamp('called_at')->nullable()->useCurrent();
        });

        if (!is_dir(__DIR__ . '/data')) {
            mkdir(__DIR__ . '/data', 0777);
        }

        $this->app->singleton('mail.manager', function ($app) {
            return new MailManager($app);
        });

        $this->app->bind('mailer', function ($app) {
            return $app->make('mail.manager')->mailer();
        });

        $this->app['config']->set('queue.default', 'sync');
        $this->app['config']->set('mail.driver', 'remote');
        $this->app['config']->set('mail.remote.url', '');
        $this->app['config']->set('mail.remote.key', '');
    }

    public function tearDown(): void
    {
        Redis::flushdb();
        MemoryStore::empty();
        FileStore::empty();

        if (is_dir(__DIR__ . '/data')) {
            @rmdir(__DIR__ . '/data');
        }
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array
     */
    protected function getPackageProviders($app): array
    {
        return [];
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'sqlite');

        $app['config']->set('database.connections.sqlite', [
            'driver'    => 'sqlite',
            'database'  => ':memory:',
            'prefix'    => '',
        ]);

        $app['config']->set('modeler', [
            'data_class' => 'App\\Data',
            'datum_class' => 'App\\Datum\\Models',
            'model_class' => 'App\\Repositories',
            'item_class' => 'App\\Entities',
            'cache_ttl' => 24 * 3600,
            'file_dir' => storage_path("dbf"),
            'user_model' => User::class,
            'schedule_store' => Store::class,
            'notification_store' => Store::class,
            'bearer_store' => Store::class,
            'modeler_store' => Store::class,
            'scheduler_route' => '/modeler/scheduler/cron',
        ]);
    }

    protected function setUpDatabase(): void
    {
        $this->app['db']->connection()->getSchemaBuilder()->create('kv', function (Blueprint $table) {
            $table->string('k')->primary()->unique();
            $table->longText('v')->nullable();
            $table->unsignedBigInteger('e')->index()->default(0);
            $table->timestamp('called_at')->nullable()->useCurrent();
        });

        $this->app['db']->connection()->getSchemaBuilder()->create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }
}
