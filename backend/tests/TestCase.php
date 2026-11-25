<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Env;
use Tests\Support\TestDatabaseGuard;

abstract class TestCase extends BaseTestCase
{
    /**
     * Only the central connection is wrapped in a transaction by RefreshDatabase;
     * tenant databases are created/dropped per test by InteractsWithTenancy.
     *
     * @var list<string>
     */
    protected array $connectionsToTransact = [];

    /**
     * Guard runs right after the app is booted, i.e. before RefreshDatabase or any
     * migration can touch the database (parallel workers only append `_test_{token}`).
     */
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $connection = (string) $app['config']->get('database.default');
        TestDatabaseGuard::assertSafeName(
            $app['config']->get("database.connections.{$connection}.database"),
            "central test database (connection \"{$connection}\")",
        );

        return $app;
    }

    protected function setUp(): void
    {
        $this->connectionsToTransact = [(string) Env::get('DB_CONNECTION', 'mysql')];

        parent::setUp();

        $this->withoutVite();
    }
}
