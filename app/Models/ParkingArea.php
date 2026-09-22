<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class ParkingArea extends MongoModel
{
    protected $collection = 'parking_areas';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'area_name',
        'capacity',
        'designation_notes',
        'is_visible',
        'allowed_roles',
        'slot_prefix',
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
        if (is_array($this->allowed_roles) && $this->allowed_roles !== []) {
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

    /**
     * Vehicle classes allowed in this lot, or null when any vehicle is OK.
     * Inferred from designation notes (Car / Motorcycle / mixed).
     *
     * @return list<string>|null
     */
    public function getAllowedVehicleClasses(): ?array
    {
        $notes = strtolower((string) ($this->designation_notes ?? ''));
        if ($notes === '') {
            return null;
        }

        $hasMoto = str_contains($notes, 'motorcycle')
            || str_contains($notes, 'motor ')
            || str_contains($notes, 'mc/');
        $hasCar = str_contains($notes, 'car')
            || str_contains($notes, 'automobile')
            || str_contains($notes, 'suv')
            || str_contains($notes, 'van');

        if ($hasMoto && $hasCar) {
            return null;
        }
        if ($hasMoto) {
            return ['motorcycle'];
        }
        if ($hasCar || str_contains($notes, 'official') || str_contains($notes, 'reserved')) {
            return ['car', 'suv', 'van', 'bus', 'truck'];
        }

        return null;
    }

    /**
     * Normalize a YOLO / registry vehicle label into a class family.
     */
    public static function normalizeVehicleClass(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }
        $key = strtolower(trim($raw));
        $key = str_replace(['-', '_'], ' ', $key);
        if (str_contains($key, 'motor') || str_contains($key, 'tricycle') || str_contains($key, 'trike') || in_array($key, ['mc', 'bike'], true)) {
            return 'motorcycle';
        }
        if (str_contains($key, 'suv')) {
            return 'suv';
        }
        if (str_contains($key, 'van')) {
            return 'van';
        }
        if (str_contains($key, 'truck') || str_contains($key, 'lorry')) {
            return 'truck';
        }
        if (str_contains($key, 'bus')) {
            return 'bus';
        }
        if (str_contains($key, 'car') || str_contains($key, 'sedan') || str_contains($key, 'auto')) {
            return 'car';
        }

        return $key;
    }

    public function allowsVehicleClass(?string $vehicleClass): bool
    {
        $allowed = $this->getAllowedVehicleClasses();
        if ($allowed === null) {
            return true;
        }
        $normalized = self::normalizeVehicleClass($vehicleClass);
        if ($normalized === null) {
            return true;
        }
        if (in_array($normalized, $allowed, true)) {
            return true;
        }
        // Car-family slots accept suv/van/truck/bus interchangeably.
        $carFamily = ['car', 'suv', 'van', 'bus', 'truck'];
        if (in_array($normalized, $carFamily, true) && array_intersect($allowed, $carFamily) !== []) {
            return true;
        }

        return false;
    }

    /**
     * Map display roles (Faculty) onto stored allowed_roles (Staff).
     */
    public function allowsOccupantRole(?string $roleName): bool
    {
        if ($roleName === null || trim($roleName) === '') {
            return true;
        }
        $role = trim($roleName);
        $aliases = [$role];
        if (strcasecmp($role, 'Faculty') === 0 || strcasecmp($role, User::STAFF_DISPLAY_LABEL) === 0) {
            $aliases[] = 'Staff';
        }
        if (strcasecmp($role, 'Staff') === 0) {
            $aliases[] = 'Faculty';
            $aliases[] = User::STAFF_DISPLAY_LABEL;
        }
        if (strcasecmp($role, 'Student / Faculty') === 0) {
            $aliases[] = 'Student';
            $aliases[] = 'Staff';
            $aliases[] = 'Faculty';
        }
        foreach ($aliases as $candidate) {
            if ($this->isAccessibleByRole($candidate)) {
                return true;
            }
        }

        return false;
    }
}
