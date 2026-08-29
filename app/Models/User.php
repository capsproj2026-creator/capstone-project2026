<?php

namespace App\Models;

use App\Models\Concerns\HasSequentialId;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use MongoDB\Laravel\Auth\User as Authenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasSequentialId;
    use MustVerifyEmailTrait;
    use Notifiable;

    public const STATUS_PENDING = 'Pending';

    public const STATUS_GRANTED = 'Granted';

    public const STATUS_DENIED = 'Denied';

    public const STATUS_LOCKED = 'Locked';

    public const MAX_STRIKES = 3;

    public const GATE_ACCESS_PENDING = 'Pending';

    public const GATE_ACCESS_GRANTED = 'Granted';

    public const GATE_ACCESS_DENIED = 'Denied';

    /** @deprecated Legacy value; treat the same as GRANTED in hasGateAccess() */
    public const GATE_ACCESS_LEGACY = 'Access';

    protected $connection = 'mongodb';

    protected $collection = 'users';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = [
        'fullname',
        'profile_pic',
        'phone_number',
        'email',
        'password',
        'user_role_id',
        'department_code',
        'vehicle_id',
        'id_number',
        'plate_number',
        'driver_license',
        'driver_license_number',
        'or_cr_photo',
        'lto_or_photo',
        'lto_cr_photo',
        'address',
        'application_date',
        'vehicle_model',
        'vehicle_color',
        'ownership_type',
        'ownership_other',
        'usage_type',
        'usage_other',
        'affiliation',
        'affiliation_other',
        'course_year_section',
        'status',
        'strike_count',
        'Gate_access',
        'rfid_uid',
        'temp_rfid_uid',
        'payment_status',
        'payment_reference',
        'paid_at',
        'google_id',
        'job_title',
        'id_document',
        'declined_at',
        'decline_remarks',
        'account_type',
        'temporary_expires_at',
        'temporary_sequence',
        'temp_identity_key',
        'temp_conversion_token',
        'email_verified_at',
        'email_verification_token',
        'email_verification_expires_at',
        'created_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'strike_count' => 'integer',
            'user_role_id' => 'integer',
            'vehicle_id' => 'integer',
            'created_at' => 'datetime',
            'application_date' => 'datetime',
            'declined_at' => 'datetime',
            'temporary_expires_at' => 'datetime',
            'temporary_sequence' => 'integer',
            'paid_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'email_verification_expires_at' => 'datetime',
        ];
    }

    public function isTemporaryAccount(): bool
    {
        return ($this->account_type ?? '') === \App\Services\TemporaryRfidService::ACCOUNT_TEMPORARY;
    }

    public function isUnregisteredStudentFaculty(): bool
    {
        if ($this->isTemporaryAccount()) {
            return true;
        }

        $name = mb_strtolower(trim((string) ($this->fullname ?? $this->name ?? '')));

        return in_array($name, [
            mb_strtolower(\App\Services\TemporaryRfidService::PLACEHOLDER_NAME),
            'temporary access',
            'unregistered student / faculty',
            'unregistered student/faculty',
        ], true);
    }

    public function displayRoleLabel(): string
    {
        if ($this->isUnregisteredStudentFaculty()) {
            return 'Student / Faculty';
        }

        return $this->roleName();
    }

    public function displayEmail(): string
    {
        $email = trim((string) ($this->email ?? ''));
        if ($this->isUnregisteredStudentFaculty() || $email === '' || str_ends_with(strtolower($email), '.invalid')) {
            return '—';
        }

        return $email;
    }

    public function temporaryAccessExpired(): bool
    {
        if (! $this->isTemporaryAccount()) {
            return false;
        }

        $expires = $this->temporary_expires_at;

        return $expires !== null && $expires->lte(now());
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(UserRole::class, 'user_role_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_code', 'departmentcode');
    }

    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(UserVehicle::class, 'user_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function gateLogs(): HasMany
    {
        return $this->hasMany(GateLog::class);
    }

    public function roleName(): string
    {
        return $this->role?->role_name ?? 'User';
    }

    /**
     * Legacy imports may store the person name as "fullname" or "Name".
     */
    public function getNameAttribute($value = null): ?string
    {
        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        foreach (['name', 'fullname', 'Name'] as $key) {
            $candidate = $this->attributes[$key] ?? null;
            if (is_string($candidate) && trim($candidate) !== '') {
                return $candidate;
            }
        }

        return null;
    }

    public function setNameAttribute(?string $value): void
    {
        $this->attributes['name'] = $value;

        foreach (['fullname', 'Name'] as $legacyKey) {
            if (array_key_exists($legacyKey, $this->attributes)) {
                $this->attributes[$legacyKey] = $value;
            }
        }
    }

    public function displayName(): string
    {
        $name = trim((string) ($this->name ?? ''));

        return $name !== '' ? $name : 'Unknown';
    }

    public function isGranted(): bool
    {
        return $this->status === self::STATUS_GRANTED;
    }

    public function isLocked(): bool
    {
        if ($this->status === self::STATUS_LOCKED || $this->status === 'Suspended') {
            return true;
        }

        $autoLock = true;
        try {
            $autoLock = app(\App\Services\SystemSettingService::class)->bool('auto_lock_on_3rd_violation', true);
        } catch (\Throwable) {
            // Fall back to default enforcement when settings are unavailable.
        }

        return $autoLock && $this->strike_count >= self::MAX_STRIKES;
    }

    public function canAccessPortal(): bool
    {
        return $this->isGranted() && ! $this->isLocked();
    }

    public function hasGateAccess(): bool
    {
        return in_array($this->Gate_access ?? '', [
            self::GATE_ACCESS_GRANTED,
            self::GATE_ACCESS_LEGACY,
        ], true);
    }

    public function isCampusVehicleOwner(): bool
    {
        return in_array((int) ($this->user_role_id ?? 0), [
            \App\Services\NavigationService::ROLE_STUDENT,
            \App\Services\NavigationService::ROLE_STAFF,
        ], true);
    }

    public function gateRoleLabel(): string
    {
        if ($this->isUnregisteredStudentFaculty()) {
            return 'Student / Faculty';
        }

        return $this->roleName() ?: 'Unknown';
    }

    public function loginBlockedReason(): ?string
    {
        if ($this->isLocked()) {
            return 'Your account has been permanently locked after receiving '.self::MAX_STRIKES.' violations. Contact the administration office.';
        }

        if ($this->isTemporaryAccount()) {
            if ($this->temporaryAccessExpired()) {
                return \App\Services\TemporaryRfidService::EXPIRED_MESSAGE;
            }

            return 'This is an unregistered student/faculty gate pass. Complete vehicle registration to keep campus access.';
        }

        if ($this->status === self::STATUS_DENIED) {
            $remarks = trim((string) ($this->decline_remarks ?? ''));

            return $remarks !== ''
                ? 'Your registration was declined: '.$remarks
                : 'Your registration was declined.';
        }

        if ($this->status === self::STATUS_PENDING) {
            return 'Account is awaiting Admin approval.';
        }

        if (! $this->isGranted()) {
            return 'You do not have access to this system.';
        }

        return null;
    }

    public function hasUploadedProfilePicture(): bool
    {
        $filename = $this->profile_pic;

        if (! $filename || in_array($filename, ['default_avatar.png', 'N/A', ''], true)) {
            return false;
        }

        return Storage::disk('public')->exists('uploads/profile/'.$filename);
    }

    public function profilePictureUrl(): string
    {
        if ($this->hasUploadedProfilePicture()) {
            return asset('storage/uploads/profile/'.$this->profile_pic);
        }

        return 'https://ui-avatars.com/api/?name='.urlencode($this->fullname).'&background=2563eb&color=fff&size=128';
    }

    public function uploadedDocumentPath(string $field, string $directory): ?string
    {
        $filename = basename(str_replace('\\', '/', (string) ($this->{$field} ?? '')));
        if ($filename === '' || in_array($filename, ['N/A', 'default_avatar.png', '.', '..'], true)) {
            return null;
        }
        if (preg_match('/[\/\\\\]/', (string) ($this->{$field} ?? ''))) {
            return null;
        }

        return $directory.'/'.$filename;
    }

    public function uploadedDocumentUrl(string $field, string $directory): ?string
    {
        $path = $this->uploadedDocumentPath($field, $directory);
        if (! $path) {
            return null;
        }

        // Prefer private local disk; fall back to legacy public copies.
        if (Storage::disk('local')->exists($path) || Storage::disk('public')->exists($path)) {
            $doc = match ($field) {
                'or_cr_photo', 'lto_or_photo' => $field === 'lto_or_photo' ? 'or' : 'orcr',
                'lto_cr_photo' => 'cr',
                'id_document' => 'id',
                default => 'license',
            };

            return route('admin.users.document', ['id' => $this->id, 'doc' => $doc]);
        }

        return null;
    }

    public function resolveDocumentAbsolutePath(string $field, string $directory): ?string
    {
        $path = $this->uploadedDocumentPath($field, $directory);
        if (! $path) {
            return null;
        }

        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->path($path);
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->path($path);
        }

        return null;
    }

    public function isImageDocument(string $field): bool
    {
        $filename = strtolower((string) ($this->{$field} ?? ''));
        if ($filename === '') {
            return false;
        }

        return (bool) preg_match('/\.(jpe?g|png|gif|webp)$/', $filename);
    }
}
