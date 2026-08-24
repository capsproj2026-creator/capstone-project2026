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
        'or_cr_photo',
        'status',
        'strike_count',
        'Gate_access',
        'rfid_uid',
        'google_id',
        'job_title',
        'id_document',
        'declined_at',
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
            'declined_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'email_verification_expires_at' => 'datetime',
        ];
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
        return in_array((int) ($this->user_role_id ?? 0), [3, 4], true);
    }

    public function loginBlockedReason(): ?string
    {
        if ($this->isLocked()) {
            return 'Your account has been permanently locked after receiving '.self::MAX_STRIKES.' violations. Contact the administration office.';
        }

        if ($this->status === self::STATUS_DENIED) {
            return 'Your registration was declined.';
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
            $doc = $field === 'or_cr_photo' ? 'orcr' : 'license';

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
