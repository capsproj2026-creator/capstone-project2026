<?php

namespace App\Support;

/**
 * Dual online/offline behavior for campus LAN demos.
 *
 * APP_OFFLINE:
 * - auto  — follow active Mongo endpoint (Atlas = online, local = offline)
 * - true  — force offline (no Google/SMTP; registration auto-verifies)
 * - false — force online features (email verify + Google when configured)
 *
 * Arduino RFID and CCTV always stay on LAN / localhost streams.
 */
class OfflineMode
{
    private static ?bool $enabled = null;

    public static function mode(): string
    {
        $raw = strtolower(trim((string) env('APP_OFFLINE', 'auto')));

        return match ($raw) {
            '1', 'true', 'yes', 'on', 'offline' => 'true',
            '0', 'false', 'no', 'off', 'online' => 'false',
            default => 'auto',
        };
    }

    public static function enabled(): bool
    {
        if (self::$enabled !== null) {
            return self::$enabled;
        }

        self::$enabled = match (self::mode()) {
            'true' => true,
            'false' => false,
            default => MongoDsn::resolvedSource() === 'local',
        };

        return self::$enabled;
    }

    /**
     * Email verification cannot reach Brevo when WAN is down.
     */
    public static function autoVerifyEmail(): bool
    {
        if (! filter_var(env('APP_OFFLINE_AUTO_VERIFY_EMAIL', true), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        return self::enabled();
    }

    public static function disableCloudAuth(): bool
    {
        return self::enabled();
    }

    /**
     * Apply runtime mailer override so SMTP does not hang while offline.
     */
    public static function applyRuntimeConfig(): void
    {
        if (! self::enabled()) {
            return;
        }

        $mailer = strtolower(trim((string) config('mail.default', 'smtp')));
        if (in_array($mailer, ['smtp', 'ses', 'mailgun', 'postmark', 'resend', 'sendmail'], true)) {
            config(['mail.default' => 'log']);
        }
    }

    public static function label(): string
    {
        return self::enabled() ? 'offline' : 'online';
    }
}
