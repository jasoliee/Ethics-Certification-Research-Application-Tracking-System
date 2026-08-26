<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Fail closed before RefreshDatabase can touch a persistent connection.
     */
    public function createApplication(): Application
    {
        $application = parent::createApplication();
        $connection = $application['config']->get('database.default');
        $database = $application['config']->get('database.connections.sqlite.database');

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(
                'Unsafe test database configuration blocked. Tests require SQLite :memory:.',
            );
        }

        return $application;
    }
}
