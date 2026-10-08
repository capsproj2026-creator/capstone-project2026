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
            'markers' => self::photoMarkers($areaId),
        ];
    }

    /**
     * Slot numbers painted on the public snapshot. Percentages are of the
     * 767×1024 photo. 1 is the stall nearest the camera.
     *
     * @return list<array{label: string, title: string, x: float, y: float}>
     */
    public static function photoMarkers(int $areaId): array
    {
        $spots = match ($areaId) {
            3 => [ // Duran Hall Front — one number in the center of each bay. DU-1 is nearest the camera; the crosswalk is between 6 and 7.
                ['DU-1', 44.0, 87.0],
                ['DU-2', 43.0, 76.0],
                ['DU-3', 43.0, 68.7],
                ['DU-4', 42.0, 63.3],
                ['DU-5', 42.0, 59.1],
                ['DU-6', 42.0, 56.5],
                ['DU-7', 43.0, 50.2],
                ['DU-8', 44.0, 47.6],
                ['DU-9', 45.0, 45.2],
                ['DU-10', 46.0, 43.2],
            ],
            4 => [ // ACAD 1 Building (Front) — one number in the center of each bay. AC-1 is nearest the entrance sign.
                ['AC-1', 50.0, 88.0],
                ['AC-2', 52.0, 83.3],
                ['AC-3', 54.0, 78.6],
                ['AC-4', 55.0, 75.6],
                ['AC-5', 58.0, 69.2],
                ['AC-6', 60.0, 64.8],
                ['AC-7', 62.0, 60.2],
                ['AC-8', 64.0, 55.6],
                ['AC-9', 66.5, 51.0],
                ['AC-10', 69.0, 46.8],
            ],
            default => [],
        };

        $markers = [];
        foreach ($spots as [$title, $x, $y]) {
            $number = preg_replace('/^.*-/', '', $title) ?: $title;
            $markers[] = [
                'label' => $number,
                'title' => $title,
                'x' => $x,
                'y' => $y,
            ];
        }

        return $markers;
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
