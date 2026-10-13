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
        $this->assertSame('Duran Hall Front', $duran['label']);
        $this->assertTrue($duran['calibrated']);
        $this->assertSame(3, $this->lotProfile('duran')['area_id']);
        $this->assertSame(4, $this->lotProfile('acad1')['area_id']);

        // CAM-1 and CAM-2 now feed the prototype lots; ACAD 1 and Duran keep their photos only.
        $this->assertSame(1, $this->lotProfile('prototype')['camera']);
        $this->assertSame(2, $this->lotProfile('prototype2')['camera']);
        $this->assertNull($this->lotProfile('acad1')['camera'] ?? null);
        $this->assertNull($this->lotProfile('duran')['camera'] ?? null);
    }

    public function test_snapshot_photos_are_numbered_from_the_nearest_stall(): void
    {
        $duran = ParkingZoneSnapshot::photoMarkers(3);
        $acad = ParkingZoneSnapshot::photoMarkers(4);

        $this->assertCount(10, $duran);
        $this->assertCount(10, $acad);
        $this->assertSame('DU-1', $duran[0]['title']);
        $this->assertSame('1', $duran[0]['label']);
        $this->assertSame('AC-1', $acad[0]['title']);
        $this->assertSame('10', $acad[9]['label']);
        $this->assertSame([], ParkingZoneSnapshot::photoMarkers(99));
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

    public function test_drawn_bays_fit_inside_their_calibration_frame(): void
    {
        $root = dirname(__DIR__, 2);
        $dir = $root.DIRECTORY_SEPARATOR.'hardware'.DIRECTORY_SEPARATOR.'ai_parking';

        foreach (['zones_acad1.json', 'zones_duran.json'] as $file) {
            $data = json_decode((string) file_get_contents($dir.DIRECTORY_SEPARATOR.$file), true);
            $this->assertIsArray($data, $file);
            $this->assertTrue($data['calibrated']);

            $width = (int) $data['image_width'];
            $height = (int) $data['image_height'];
            $this->assertGreaterThan(0, $width, $file);
            $this->assertGreaterThan(0, $height, $file);

            $slots = array_values(array_filter(
                $data['zones'] ?? [],
                static fn ($zone) => ($zone['type'] ?? '') === 'slot'
            ));
            $this->assertCount(10, $slots, $file);

            $drawn = array_values(array_filter($slots, static fn ($slot) => ! empty($slot['points'])));
            $this->assertNotEmpty($drawn, $file.' should have at least one drawn bay');

            foreach ($drawn as $slot) {
                $label = $file.' '.($slot['id'] ?? '?');
                $this->assertGreaterThanOrEqual(3, count($slot['points']), $label);
                foreach ($slot['points'] as [$x, $y]) {
                    $this->assertTrue($x >= 0 && $x <= $width, $label.' x outside the calibration frame');
                    $this->assertTrue($y >= 0 && $y <= $height, $label.' y outside the calibration frame');
                }
            }
        }
    }
}
