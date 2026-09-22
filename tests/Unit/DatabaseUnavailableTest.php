<?php

namespace Tests\Unit;

use App\Support\DatabaseUnavailable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DatabaseUnavailableTest extends TestCase
{
    public function test_matches_mongo_connection_timeout_messages(): void
    {
        $e = new RuntimeException(
            'No suitable servers found (`serverSelectionTryOnce` set): [connection refused calling hello on 127.0.0.1:27017]'
        );

        $this->assertTrue(DatabaseUnavailable::matches($e));
    }

    public function test_matches_nested_previous_exception(): void
    {
        $inner = new RuntimeException('Failed to connect: connection timed out');
        $outer = new RuntimeException('Query failed', 0, $inner);

        $this->assertTrue(DatabaseUnavailable::matches($outer));
    }

    public function test_ignores_unrelated_exceptions(): void
    {
        $this->assertFalse(DatabaseUnavailable::matches(new RuntimeException('Validation failed for email')));
    }
}
