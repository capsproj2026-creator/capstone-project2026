<?php

namespace App\Services;

class ParkingZoneSnapshot
{
    public function __construct(
        private readonly string $hardwareDir,
        private readonly string $publicParkingDir,
        private readonly string $publicUrlPrefix = 'images/parking',
    ) {
    }

    public static function fromApp(): self
    {
        return new self(
            base_path('hardware/ai_parking'),
            public_path('images/parking'),
        );
    }

    /**
     * @return array{area_id: int, path: string, filename: string, label: string, calibrated: bool}|null
     */
    public function forAreaId(?int $areaId): ?array
    {
        if ($areaId === null || $areaId < 1) {
            return null;
        }

        foreach ($this->lots() as $lot) {
            if ((int) ($lot['area_id'] ?? 0) !== $areaId) {
                continue;
            }

            return $this->toSnapshot($lot);
        }

        return null;
    }

    /**
     * @return list<array{area_id: int, path: string, filename: string, label: string, calibrated: bool}>
     */
    public function all(): array
    {
        $out = [];

        foreach ($this->lots() as $lot) {
            $snapshot = $this->toSnapshot($lot);
            if ($snapshot) {
                $out[] = $snapshot;
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lots(): array
    {
        $path = $this->hardwareDir.DIRECTORY_SEPARATOR.'lot_profiles.json';
        if (! is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data)) {
            return [];
        }

        $lots = $data['lots'] ?? [];
        $order = $data['order'] ?? array_keys($lots);
        $ordered = [];

        foreach ($order as $key) {
            if (isset($lots[$key]) && is_array($lots[$key])) {
                $ordered[] = $lots[$key];
            }
        }

        return $ordered;
    }

    /**
     * @param  array<string, mixed>  $lot
     * @return array{area_id: int, path: string, filename: string, label: string, calibrated: bool}|null
     */
    private function toSnapshot(array $lot): ?array
    {
        $file = trim((string) ($lot['snapshot'] ?? ''));
        if ($file === '' || str_contains($file, '/') || str_contains($file, '\\') || str_contains($file, '..')) {
            return null;
        }

        $publicFile = $this->publicParkingDir.DIRECTORY_SEPARATOR.$file;
        if (! is_file($publicFile)) {
            return null;
        }

        $areaId = (int) ($lot['area_id'] ?? 0);
        if ($areaId < 1) {
            return null;
        }

        return [
            'area_id' => $areaId,
            'path' => $this->publicUrlPrefix.'/'.$file,
            'filename' => $file,
            'label' => (string) ($lot['name'] ?? 'Parking zone'),
            'calibrated' => $this->isCalibrated($lot),
        ];
    }

    /**
     * @param  array<string, mixed>  $lot
     */
    private function isCalibrated(array $lot): bool
    {
        $zonesFile = trim((string) ($lot['zones_file'] ?? ''));
        if ($zonesFile === '' || str_contains($zonesFile, '/') || str_contains($zonesFile, '\\') || str_contains($zonesFile, '..')) {
            return false;
        }

        $path = $this->hardwareDir.DIRECTORY_SEPARATOR.$zonesFile;
        if (! is_file($path)) {
            return false;
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data) || empty($data['calibrated'])) {
            return false;
        }

        foreach ($data['zones'] ?? [] as $zone) {
            if (($zone['type'] ?? '') === 'slot' && count($zone['points'] ?? []) >= 3) {
                return true;
            }
        }

        return false;
    }
}
