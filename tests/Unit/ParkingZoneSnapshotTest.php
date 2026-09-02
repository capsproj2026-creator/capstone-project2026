<?php

namespace Tests\Unit;

use App\Services\ParkingZoneSnapshot;
use PHPUnit\Framework\TestCase;

class ParkingZoneSnapshotTest extends TestCase
{
    private function service(): ParkingZoneSnapshot
    {
        $root = dirname(__DIR__, 2);

        return new ParkingZoneSnapshot(
            $root.DIRECTORY_SEPARATOR.'hardware'.DIRECTORY_SEPARATOR.'ai_parking',
            $root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'images'.DIRECTORY_SEPARATOR.'parking',
        );
    }

    private function lotProfile(string $key): array
    {
        $path = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'hardware'.DIRECTORY_SEPARATOR.'ai_parking'.DIRECTORY_SEPARATOR.'lot_profiles.json';
        $data = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($data['lots'][$key] ?? null, $key);

        return $data['lots'][$key];
    }

    public function test_acad1_and_duran_snapshots_are_calibrated(): void
    {
        $service = $this->service();

        $acad = $service->forAreaId(4);
        $this->assertNotNull($acad);
        $this->assertSame('images/parking/snapshot_acad1.jpg', $acad['path']);
        $this->assertSame('ACAD 1 Building (Front)', $acad['label']);
        $this->assertTrue($acad['calibrated']);

        $duran = $service->forAreaId(3);
        $this->assertNotNull($duran);
        $this->assertSame('images/parking/snapshot_duran.jpg', $duran['path']);
        $this->assertSame('Duran Hall (Front)', $duran['label']);
        $this->assertTrue($duran['calibrated']);
        $this->assertSame(2, $this->lotProfile('duran')['camera']);
        $this->assertSame(3, $this->lotProfile('duran')['area_id']);
        $this->assertSame(1, $this->lotProfile('acad1')['camera']);
        $this->assertSame(4, $this->lotProfile('acad1')['area_id']);
    }

    public function test_unknown_area_has_no_snapshot(): void
    {
        $this->assertNull($this->service()->forAreaId(99));
        $this->assertNull($this->service()->forAreaId(null));
    }

    public function test_all_returns_only_lots_with_public_photos(): void
    {
        $all = $this->service()->all();
        $paths = array_column($all, 'path');

        $this->assertContains('images/parking/snapshot_acad1.jpg', $paths);
        $this->assertContains('images/parking/snapshot_duran.jpg', $paths);
        $this->assertCount(2, $all);
    }

    public function test_calibrated_polygons_use_full_snapshot_frame(): void
    {
        $root = dirname(__DIR__, 2);
        $dir = $root.DIRECTORY_SEPARATOR.'hardware'.DIRECTORY_SEPARATOR.'ai_parking';

        foreach (['zones_acad1.json', 'zones_duran.json'] as $file) {
            $data = json_decode((string) file_get_contents($dir.DIRECTORY_SEPARATOR.$file), true);
            $this->assertIsArray($data, $file);
            $this->assertTrue($data['calibrated']);
            $this->assertSame(767, $data['image_width'], $file);
            $this->assertSame(1024, $data['image_height'], $file);

            $slots = array_values(array_filter(
                $data['zones'] ?? [],
                static fn ($zone) => ($zone['type'] ?? '') === 'slot'
            ));
            $this->assertCount(10, $slots, $file);

            $xs = [];
            $ys = [];
            foreach ($slots as $slot) {
                $this->assertCount(4, $slot['points'], $slot['id'] ?? $file);
                foreach ($slot['points'] as $point) {
                    $xs[] = $point[0];
                    $ys[] = $point[1];
                }
            }

            $this->assertGreaterThan(450, max($xs) - min($xs), $file.' should span the stall row width');
            $this->assertGreaterThan(500, max($ys) - min($ys), $file.' should span the full stall row, not a zoomed crop');
            $this->assertGreaterThan(700, max($ys), $file.' should reach the foreground stalls');
            $this->assertLessThan(450, min($ys), $file.' should reach the far stalls');
        }
    }
}
