<?php

namespace App\Services;

use App\Models\ParkingArea;
use App\Models\ParkingSlot;
use Database\Seeders\AiTestLotSeeder;
use Illuminate\Validation\ValidationException;

class ParkingLayoutService
{
    public function __construct(private readonly AiCameraRegistry $cameras)
    {
    }

    /**
     * @return list<int>
     */
    public function protectedAreaIds(): array
    {
        $ids = $this->cameras->monitoredAreaIds();

        foreach (AiTestLotSeeder::LOTS as $lot) {
            $ids[] = (int) ($lot['id'] ?? 0);
        }

        return array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0)));
    }

    public function isProtectedArea(int $areaId): bool
    {
        return in_array($areaId, $this->protectedAreaIds(), true);
    }

    /**
     * @param  array{area_name: string, slot_prefix: string, slot_count: int, designation_notes?: string|null, is_visible?: bool, allowed_roles?: list<string>}  $data
     */
    public function createArea(array $data): ParkingArea
    {
        $name = trim((string) $data['area_name']);
        $prefix = $this->normalizePrefix((string) $data['slot_prefix']);
        $count = max(1, min(200, (int) $data['slot_count']));
        $roles = $this->normalizeRoles($data['allowed_roles'] ?? ['Student', 'Staff']);

        $this->assertPrefixAvailable($prefix);

        $area = ParkingArea::query()->create([
            'area_name' => $name,
            'capacity' => $count,
            'designation_notes' => filled($data['designation_notes'] ?? null)
                ? trim((string) $data['designation_notes'])
                : null,
            'is_visible' => (bool) ($data['is_visible'] ?? true),
            'allowed_roles' => $roles,
            'slot_prefix' => $prefix,
        ]);

        $this->createSlotsForArea($area, $count, 1);

        return $area->fresh() ?? $area;
    }

    public function addSlots(ParkingArea $area, int $count): int
    {
        $count = max(1, min(50, $count));
        $start = $this->nextSlotIndex($area);

        $this->createSlotsForArea($area, $count, $start);
        $this->syncCapacity($area);

        return $count;
    }

    public function deleteArea(ParkingArea $area): void
    {
        if ($this->isProtectedArea((int) $area->id)) {
            throw ValidationException::withMessages([
                'area' => "\"{$area->area_name}\" is monitored by AI cameras and cannot be removed.",
            ]);
        }

        $occupied = ParkingSlot::query()
            ->where('area_id', $area->id)
            ->get()
            ->first(fn (ParkingSlot $slot) => $slot->isOccupied());

        if ($occupied) {
            throw ValidationException::withMessages([
                'area' => "Cannot remove \"{$area->area_name}\" while slot {$occupied->slot_number} is occupied.",
            ]);
        }

        ParkingSlot::query()->where('area_id', $area->id)->delete();
        $area->delete();
    }

    public function deleteSlot(ParkingSlot $slot): void
    {
        if ($slot->isOccupied()) {
            throw ValidationException::withMessages([
                'slot' => "Slot {$slot->slot_number} is occupied and cannot be removed.",
            ]);
        }

        $area = $slot->area;
        $slot->delete();

        if ($area) {
            $this->syncCapacity($area);
        }
    }

    public function syncCapacity(ParkingArea $area): void
    {
        $area->update([
            'capacity' => ParkingSlot::query()->where('area_id', $area->id)->count(),
        ]);
    }

    public function slotPrefix(ParkingArea $area): string
    {
        $stored = $this->normalizePrefix((string) ($area->slot_prefix ?? ''), allowEmpty: true);
        if ($stored !== '') {
            return $stored;
        }

        $sample = ParkingSlot::query()->where('area_id', $area->id)->orderBy('slot_number')->first();
        $fromSlot = $this->prefixFromSlotNumber((string) ($sample?->slot_number ?? ''));
        if ($fromSlot !== '') {
            return $fromSlot;
        }

        $fromName = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $area->area_name) ?: 'P', 0, 3));

        return $fromName !== '' ? $fromName : 'P';
    }

    /**
     * @param  list<string>  $roles
     * @return list<string>
     */
    public function normalizeRoles(array $roles): array
    {
        $allowed = ['Student', 'Staff', 'Visitor'];
        $clean = array_values(array_unique(array_filter(
            $roles,
            fn ($role) => in_array($role, $allowed, true)
        )));

        return $clean !== [] ? $clean : ['Student', 'Staff'];
    }

    private function createSlotsForArea(ParkingArea $area, int $count, int $startIndex): void
    {
        $prefix = $this->slotPrefix($area);

        if (! $area->slot_prefix) {
            $area->update(['slot_prefix' => $prefix]);
        }

        for ($i = 0; $i < $count; $i++) {
            $n = $startIndex + $i;
            $slotNumber = $prefix.'-'.$n;

            if (ParkingSlot::query()->where('slot_number', $slotNumber)->exists()) {
                throw ValidationException::withMessages([
                    'slot_prefix' => "Slot {$slotNumber} already exists. Choose a different prefix.",
                ]);
            }

            ParkingSlot::query()->create([
                'area_id' => (int) $area->id,
                'slot_number' => $slotNumber,
                'status' => 'Available',
                'parked_user_id' => null,
                'parked_visitor_id' => null,
            ]);
        }
    }

    private function nextSlotIndex(ParkingArea $area): int
    {
        $prefix = $this->slotPrefix($area);
        $max = 0;

        ParkingSlot::query()->where('area_id', $area->id)->get(['slot_number'])->each(function (ParkingSlot $slot) use ($prefix, &$max) {
            if (preg_match('/^'.preg_quote($prefix, '/').'-(\d+)$/i', (string) $slot->slot_number, $m)) {
                $max = max($max, (int) $m[1]);
            }
        });

        return $max + 1;
    }

    private function assertPrefixAvailable(string $prefix): void
    {
        $taken = ParkingArea::query()->get()->first(function (ParkingArea $area) use ($prefix) {
            return strcasecmp($this->slotPrefix($area), $prefix) === 0;
        });

        if ($taken) {
            throw ValidationException::withMessages([
                'slot_prefix' => "Prefix {$prefix} is already used by \"{$taken->area_name}\".",
            ]);
        }
    }

    private function normalizePrefix(string $prefix, bool $allowEmpty = false): string
    {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $prefix) ?: '');

        if ($clean === '') {
            if ($allowEmpty) {
                return '';
            }

            throw ValidationException::withMessages([
                'slot_prefix' => 'Enter a short slot prefix (letters or numbers).',
            ]);
        }

        if (strlen($clean) > 6) {
            throw ValidationException::withMessages([
                'slot_prefix' => 'Slot prefix must be 1–6 characters.',
            ]);
        }

        return $clean;
    }

    private function prefixFromSlotNumber(string $slotNumber): string
    {
        if (preg_match('/^([A-Za-z0-9]+)-/', $slotNumber, $m)) {
            return strtoupper($m[1]);
        }

        return '';
    }
}
