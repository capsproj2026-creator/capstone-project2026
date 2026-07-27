<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

class MongoConnection
{
    public static function ping(): void
    {
        $database = (string) config('database.connections.mongodb.database', 'capstone');

        DB::connection('mongodb')
            ->getMongoClient()
            ->selectDatabase($database)
            ->command(['ping' => 1]);
    }

    public static function usesAtlas(): bool
    {
        $uri = (string) config('database.connections.mongodb.dsn', '');

        return str_starts_with($uri, 'mongodb+srv://');
    }

    /**
     * @return array{connected: bool, database: string, cloud: bool, message: string, error?: string}
     */
    public static function status(): array
    {
        $database = (string) config('database.connections.mongodb.database', 'capstone');
        $cloud = self::usesAtlas();

        try {
            self::ping();

            return [
                'connected' => true,
                'database' => $database,
                'cloud' => $cloud,
                'message' => $cloud
                    ? 'Connected to MongoDB Atlas. The same data is available from any device using this MONGODB_URI.'
                    : 'Connected to local MongoDB. Other devices cannot reach this database unless they use the same network/host.',
            ];
        } catch (Throwable $e) {
            return [
                'connected' => false,
                'database' => $database,
                'cloud' => $cloud,
                'message' => 'MongoDB connection failed.',
                'error' => $e->getMessage(),
            ];
        }
    }
}
