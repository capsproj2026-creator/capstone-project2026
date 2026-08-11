<?php

namespace App\Support;

/**
 * Resolve MongoDB DSN for Laravel from .env (Atlas or local).
 */
class MongoDsn
{
    public static function resolve(): string
    {
        $mode = strtolower(trim((string) env('MONGODB_MODE', 'local')));
        $direct = trim((string) env('MONGODB_URI', ''));

        if ($direct !== '' && ! self::isPlaceholderUri($direct)) {
            return $direct;
        }

        if ($mode === 'atlas' || self::hasAtlasCredentials()) {
            $built = self::buildAtlasUri();
            if ($built !== null) {
                return $built;
            }
        }

        return 'mongodb://127.0.0.1:27017';
    }

    public static function hasAtlasCredentials(): bool
    {
        return trim((string) env('MONGODB_ATLAS_USER', '')) !== ''
            && trim((string) env('MONGODB_ATLAS_PASSWORD', '')) !== ''
            && trim((string) env('MONGODB_ATLAS_HOST', '')) !== '';
    }

    public static function buildAtlasUri(): ?string
    {
        $user = trim((string) env('MONGODB_ATLAS_USER', ''));
        $pass = trim((string) env('MONGODB_ATLAS_PASSWORD', ''));
        $host = trim((string) env('MONGODB_ATLAS_HOST', ''));
        $appName = trim((string) env('MONGODB_ATLAS_APP_NAME', 'CapstoneDatabase'));

        if ($user === '' || $pass === '' || $host === '') {
            return null;
        }

        $host = preg_replace('#^mongodb(\+srv)?://#i', '', $host) ?? $host;
        $host = rtrim($host, '/');

        return sprintf(
            'mongodb+srv://%s:%s@%s/?retryWrites=true&w=majority&appName=%s',
            rawurlencode($user),
            rawurlencode($pass),
            $host,
            rawurlencode($appName)
        );
    }

    public static function isPlaceholderUri(string $uri): bool
    {
        $lower = strtolower($uri);

        return str_contains($lower, 'username:password')
            || str_contains($lower, 'user:pass@')
            || str_contains($lower, '@cluster.mongodb.net')
            || str_contains($lower, 'your_cluster');
    }

    public static function withTlsParams(string $dsn): string
    {
        if (! str_starts_with($dsn, 'mongodb+srv://')) {
            return $dsn;
        }

        if (! filter_var(env('MONGODB_TLS_ALLOW_INVALID', false), FILTER_VALIDATE_BOOLEAN)) {
            return $dsn;
        }

        $tlsParams = 'tlsAllowInvalidCertificates=true&tlsAllowInvalidHostnames=true';

        return str_contains($dsn, '?') ? "{$dsn}&{$tlsParams}" : "{$dsn}?{$tlsParams}";
    }
}
