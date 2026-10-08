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
            3 => [ // Duran Hall Front — DU-1 nearest the camera, crosswalk between 6 and 7.
                ['DU-1', 52.0, 90.0],
                ['DU-2', 48.0, 83.0],
                ['DU-3', 44.0, 76.0],
                ['DU-4', 41.0, 70.0],
                ['DU-5', 38.0, 64.0],
                ['DU-6', 40.0, 58.0],
                ['DU-7', 42.0, 48.0],
                ['DU-8', 50.0, 46.0],
                ['DU-9', 53.0, 43.0],
                ['DU-10', 55.0, 40.0],
            ],
            4 => [ // ACAD 1 Building (Front) — AC-1 nearest the entrance sign.
                ['AC-1', 38.0, 86.0],
                ['AC-2', 42.0, 80.0],
                ['AC-3', 45.0, 74.0],
                ['AC-4', 48.0, 68.0],
                ['AC-5', 50.0, 62.0],
                ['AC-6', 53.0, 57.0],
                ['AC-7', 58.0, 52.0],
                ['AC-8', 62.0, 48.0],
                ['AC-9', 64.0, 45.0],
                ['AC-10', 67.0, 42.0],
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
