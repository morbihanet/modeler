<?php

namespace Morbihanet\Modeler\Test;

use Morbihanet\Modeler\Core;
use Morbihanet\Modeler\Redis;
use Morbihanet\Modeler\FileStore;
use Morbihanet\Modeler\MemoryStore;
use Illuminate\Database\Schema\Blueprint;
use Orchestra\Testbench\TestCase as Orchestra;
use M6Web\Component\RedisMock\RedisMock as Mock;

abstract class TestCase extends Orchestra
{
    public function setUp(): void
    {
        parent::setUp();

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
            'model_class' => 'App\\Repositories',
            'item_class' => 'App\\Entities',
            'cache_ttl' => 24 * 3600,
            'file_dir' => __DIR__ . '/data',
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
    }
}
