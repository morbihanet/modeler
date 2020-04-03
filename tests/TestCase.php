<?php

namespace Morbihanet\Modeler\Test;

use Morbihanet\Modeler\Redis;
use Morbihanet\Modeler\MemoryStore;
use Illuminate\Database\Schema\Blueprint;
use Orchestra\Testbench\TestCase as Orchestra;
use M6Web\Component\RedisMock\RedisMock as Mock;

abstract class TestCase extends Orchestra
{
    public function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabase();
        Redis::engine(new Mock);
    }

    public function tearDown(): void
    {
        Redis::flushdb();
        MemoryStore::empty();
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [];
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app)
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
        ]);
    }

    protected function setUpDatabase()
    {
        $this->app['db']->connection()->getSchemaBuilder()->create('kv', function (Blueprint $table) {
            $table->string('k')->primary()->unique();
            $table->longText('v')->nullable();
            $table->unsignedBigInteger('e')->index()->default(0);
            $table->timestamp('called_at')->nullable()->useCurrent();
        });
    }
}
