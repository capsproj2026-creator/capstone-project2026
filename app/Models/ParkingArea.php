<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class ParkingArea extends MongoModel
{
    protected $collection = 'parking_areas';

    public $timestamps = false;

    protected $fillable = [
        'area_name',
        'capacity',
        'designation_notes',
        'is_visible',
        'allowed_roles',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'is_visible' => 'boolean',
            'allowed_roles' => 'array',
        ];
    }

    public function slots(): HasMany
    {
        return $this->hasMany(ParkingSlot::class, 'area_id');
    }

    public function isVisibleToUsers(): bool
    {
        return ($this->is_visible ?? true) === true;
    }

    /**
     * Resolved role list for this zone.
     * Uses the stored allowed_roles when present; otherwise infers from legacy designation notes.
     *
     * @return list<string>
     */
    public function getAllowedRoles(): array
    {
        if ($this->allowed_roles !== null) {
            return array_values(array_unique($this->allowed_roles));
        }

        return self::inferRolesFromDesignation($this->designation_notes ?? '');
    }

    /**
     * Portal rule: zone.isVisible && allowedRoles.includes(userRole)
     */
    public function isVisibleToUser(string $roleName): bool
    {
        return $this->isVisibleToUsers()
            && in_array($roleName, $this->getAllowedRoles(), true);
    }

    /**
     * @return \Illuminate\Support\Collection<int, self>
     */
    public static function visibleToRole(string $roleName): \Illuminate\Support\Collection
    {
        return static::query()
            ->orderBy('id')
            ->get()
            ->filter(fn (self $area) => $area->isVisibleToUser($roleName))
            ->values();
    }

    public static function inferRolesFromDesignation(string $notes): array
    {
        $normalized = strtolower($notes);
        $roles = [];

        if (str_contains($normalized, 'student')) {
            $roles[] = 'Student';
        }

        if (
            str_contains($normalized, 'employee')
            || str_contains($normalized, 'official')
        ) {
            $roles[] = 'Staff';
        }

        if (str_contains($normalized, 'visitor')) {
            $roles[] = 'Visitor';
        }

        if ($roles === [] && str_contains($normalized, 'car')) {
            $roles[] = 'Staff';
        }

        if ($roles === []) {
            $roles = ['Student', 'Staff'];
        }

        return array_values(array_unique($roles));
    }

    public function isAccessibleByRole(string $roleName): bool
    {
        if (! $this->isVisibleToUsers()) {
            return false;
        }

        return in_array($roleName, $this->getAllowedRoles(), true);
    }
}
