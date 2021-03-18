<?php

namespace AnourValar\EloquentSerialize\Tests;

use AnourValar\EloquentSerialize\Tests\Models\UserPhoneNote;
use Illuminate\Database\Schema\Blueprint;

abstract class AbstractTest extends \Orchestra\Testbench\TestCase
{
    /**
     * @var \AnourValar\EloquentSerialize\Service
     */
    protected $service;

    /**
     * Init
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withFactories(__DIR__.'/factories');
        $this->setUpDatabase($this->app);
        $this->setUpSeeder();

        \DB::enableQueryLog();

        $this->service = \App::make(\AnourValar\EloquentSerialize\Service::class);
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function setUpDatabase(\Illuminate\Foundation\Application $app)
    {
        $app['db']->connection()->getSchemaBuilder()->create('users', function (Blueprint $table)
        {
            $table->increments('id');
            $table->string('title');
            $table->integer('sort');
            $table->jsonb('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $app['db']->connection()->getSchemaBuilder()->create('user_phones', function (Blueprint $table)
        {
            $table->increments('id');
            $table->integer('user_id');
            $table->string('phone');
            $table->boolean('is_primary');
            $table->timestamps();
        });

        $app['db']->connection()->getSchemaBuilder()->create('user_phone_notes', function (Blueprint $table)
        {
            $table->increments('id');
            $table->integer('user_phone_id');
            $table->string('note');
            $table->timestamps();
        });

        $app['db']->connection()->getSchemaBuilder()->create('posts', function (Blueprint $table)
        {
            $table->increments('id');
            $table->integer('user_id');
            $table->string('title');
            $table->text('body');
            $table->timestamps();
        });
    }

    /**
     * @return void
     */
    protected function setUpSeeder()
    {
        for ($i = 0; $i < 80; $i++) {
            factory(UserPhoneNote::class)->create();
        }
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder
     * @param boolean $execute
     * @return void
     */
    protected function compare(\Illuminate\Database\Eloquent\Builder $builder, bool $execute = true): void
    {
        $reference = $this->service->serialize($builder);
        $package = $builder;

        for ($i = 1; $i <= 3; $i++) {
            $package = $this->service->serialize($package);
            $package = json_encode($package);

            $package = json_decode($package, true);
            $package = $this->service->unserialize($package);

            $original = $this->getScheme($builder, $execute);
            $repacked = $this->getScheme($package, $execute);

            $this->assertTrue($original === $repacked, "#$i:\nOriginal:\n$original\n\nRepacked:\n$repacked\n\n");
            $this->assertTrue($reference === $this->service->serialize($package), "#$i");
        }
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @param boolean $execute
     * @return string
     */
    private function getScheme(\Illuminate\Database\Eloquent\Builder $builder, bool $execute): string
    {
        \DB::flushQueryLog();
        if ($execute) {
            $result = $builder->get();
        } else {
            $result = [];
        }
        $logs = \DB::getQueryLog();

        foreach ($logs as &$log) {
            unset($log['time']);
        }
        unset($log);

        return json_encode(['query' => $logs, 'result' => $result], JSON_PRETTY_PRINT);
    }
}
