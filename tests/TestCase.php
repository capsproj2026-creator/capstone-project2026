<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests create and delete users, gate logs, and violations; never let them touch the live database.
        foreach (['mongodb', 'mongodb_atlas'] as $connection) {
            $database = (string) config("database.connections.{$connection}.database");
            if (! str_ends_with($database, '_test')) {
                fwrite(STDERR, "Refusing to run tests against MongoDB database [{$database}] on connection [{$connection}].\n");
                exit(1);
            }
        }
    }
}
