<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class SystemSettingService
{
    public const CACHE_KEY = 'system_settings.singleton';

    /**
     * @return array{
     *     campus_name: string,
     *     timezone: string,
     *     contact_email: string,
     *     contact_phone: string,
     *     auto_lock_on_3rd_violation: bool,
     *     send_violation_notifications: bool,
     *     enable_visitor_time_limits: bool,
     *     require_photo_evidence: bool
     * }
     */
    public function defaults(): array
    {
        return [
            'campus_name' => (string) config('app.name', 'Smart Campus VMS'),
            'timezone' => 'Asia/Manila',
            'contact_email' => (string) config('mail.from.address', ''),
            'contact_phone' => '',
            'auto_lock_on_3rd_violation' => true,
            'send_violation_notifications' => true,
            'enable_visitor_time_limits' => true,
            'require_photo_evidence' => false,
        ];
    }

    /**
     * @return array{
     *     campus_name: string,
     *     timezone: string,
     *     contact_email: string,
     *     contact_phone: string,
     *     auto_lock_on_3rd_violation: bool,
     *     send_violation_notifications: bool,
     *     enable_visitor_time_limits: bool,
     *     require_photo_evidence: bool
     * }
     */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, function () {
            $row = SystemSetting::query()->where('id', SystemSetting::SINGLETON_ID)->first();
            $defaults = $this->defaults();

            if (! $row) {
                return $defaults;
            }

            return [
                'campus_name' => (string) ($row->campus_name ?: $defaults['campus_name']),
                'timezone' => (string) ($row->timezone ?: $defaults['timezone']),
                'contact_email' => (string) ($row->contact_email ?? $defaults['contact_email']),
                'contact_phone' => (string) ($row->contact_phone ?? $defaults['contact_phone']),
                'auto_lock_on_3rd_violation' => (bool) ($row->auto_lock_on_3rd_violation ?? true),
                'send_violation_notifications' => (bool) ($row->send_violation_notifications ?? true),
                'enable_visitor_time_limits' => (bool) ($row->enable_visitor_time_limits ?? true),
                'require_photo_evidence' => (bool) ($row->require_photo_evidence ?? false),
            ];
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return $all[$key] ?? $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        return (bool) $this->get($key, $default);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(array $attributes): array
    {
        $defaults = $this->defaults();
        $allowed = array_keys($defaults);
        $payload = ['id' => SystemSetting::SINGLETON_ID];

        foreach ($allowed as $key) {
            if (! array_key_exists($key, $attributes)) {
                continue;
            }

            $value = $attributes[$key];
            if (str_starts_with($key, 'auto_') || str_starts_with($key, 'send_') || str_starts_with($key, 'enable_') || str_starts_with($key, 'require_')) {
                $payload[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            } else {
                $payload[$key] = is_string($value) ? trim($value) : $value;
            }
        }

        $existing = SystemSetting::query()->where('id', SystemSetting::SINGLETON_ID)->first();
        if ($existing) {
            $existing->update($payload);
        } else {
            SystemSetting::query()->create(array_merge($defaults, $payload));
        }

        Cache::forget(self::CACHE_KEY);

        return $this->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function timezoneOptions(): array
    {
        $preferred = [
            'Asia/Manila' => 'Asia/Manila (Philippines)',
            'UTC' => 'UTC',
            'America/Los_Angeles' => 'Pacific Time (US)',
            'America/Denver' => 'Mountain Time (US)',
            'America/Chicago' => 'Central Time (US)',
            'America/New_York' => 'Eastern Time (US)',
            'Europe/London' => 'Europe/London',
            'Asia/Tokyo' => 'Asia/Tokyo',
            'Asia/Singapore' => 'Asia/Singapore',
        ];

        $options = [];
        foreach ($preferred as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }
}
