<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function refreshApplication(): void
    {
        parent::refreshApplication();
        if (config('database.default') !== 'testing'
            || ! str_ends_with(config('database.connections.testing.database'), '_test')) {
            throw new \RuntimeException('Tests require the isolated testing connection and a database ending in _test.');
        }
        config(['queue.connections.database.connection' => 'testing']);
        \Illuminate\Support\Facades\Http::preventStrayRequests();
    }
}
