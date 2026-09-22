<?php

namespace App\Support;

use Throwable;

/**
 * Detect MongoDB / primary-database outages so the app can show a friendly
 * 503 instead of a raw exception page.
 */
class DatabaseUnavailable
{
    public const MESSAGE = 'Database is temporarily unavailable. Please try again in a moment.';

    public const RFID_MESSAGE = 'Database temporarily unavailable. Gate access cannot be verified right now.';

    /**
     * @var list<string>
     */
    private const CLASS_HINTS = [
        'MongoDB\\Driver\\Exception\\ConnectionTimeoutException',
        'MongoDB\\Driver\\Exception\\ConnectionException',
        'MongoDB\\Driver\\Exception\\RuntimeException',
        'MongoDB\\Laravel\\Exceptions\\ConnectionException',
    ];

    /**
     * @var list<string>
     */
    private const MESSAGE_HINTS = [
        'no suitable servers',
        'server selection timeout',
        'serverselectiontimeout',
        'failed to connect',
        'connection refused',
        'connection timed out',
        'timed out after',
        'socket timeout',
        'network is unreachable',
        'mongodb connection',
        'could not connect to server',
        'topology is closed',
        'not master and slaveok=false',
    ];

    public static function matches(Throwable $e): bool
    {
        for ($current = $e; $current !== null; $current = $current->getPrevious()) {
            if (self::matchesOne($current)) {
                return true;
            }
        }

        return false;
    }

    private static function matchesOne(Throwable $e): bool
    {
        $class = $e::class;

        foreach (self::CLASS_HINTS as $hint) {
            if ($class === $hint || is_a($e, $hint)) {
                // RuntimeException from the driver is broad — require a connection-ish message.
                if ($hint === 'MongoDB\\Driver\\Exception\\RuntimeException') {
                    return self::messageLooksLikeOutage($e->getMessage());
                }

                return true;
            }
        }

        return self::messageLooksLikeOutage($e->getMessage());
    }

    private static function messageLooksLikeOutage(string $message): bool
    {
        $haystack = strtolower($message);

        foreach (self::MESSAGE_HINTS as $hint) {
            if (str_contains($haystack, $hint)) {
                return true;
            }
        }

        return false;
    }
}
