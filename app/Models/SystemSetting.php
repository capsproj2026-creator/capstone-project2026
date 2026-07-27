<?php

namespace App\Models;

class SystemSetting extends MongoModel
{
    protected $collection = 'system_settings';

    public $timestamps = false;

    public const SINGLETON_ID = 1;

    protected $fillable = [
        'campus_name',
        'timezone',
        'contact_email',
        'contact_phone',
        'auto_lock_on_3rd_violation',
        'send_violation_notifications',
        'enable_visitor_time_limits',
        'require_photo_evidence',
    ];

    protected function casts(): array
    {
        return [
            'auto_lock_on_3rd_violation' => 'boolean',
            'send_violation_notifications' => 'boolean',
            'enable_visitor_time_limits' => 'boolean',
            'require_photo_evidence' => 'boolean',
        ];
    }
}
