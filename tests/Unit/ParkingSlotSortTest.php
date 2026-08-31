<?php

namespace Tests\Unit;

use App\Models\ParkingSlot;
use PHPUnit\Framework\TestCase;

class ParkingSlotSortTest extends TestCase
{
    public function test_slot_labels_sort_numerically_not_lexicographically(): void
    {
        $labels = ['FO-1', 'FO-10', 'FO-11', 'FO-12', 'FO-2', 'FO-20', 'FO-3'];
        usort($labels, fn ($a, $b) => ParkingSlot::naturalSortKey($a) <=> ParkingSlot::naturalSortKey($b));

        $this->assertSame(
            ['FO-1', 'FO-2', 'FO-3', 'FO-10', 'FO-11', 'FO-12', 'FO-20'],
            $labels
        );
    }

    public function test_different_prefixes_sort_then_increment(): void
    {
        $labels = ['TA-10', 'AD-2', 'TA-2', 'AD-10'];
        usort($labels, fn ($a, $b) => ParkingSlot::naturalSortKey($a) <=> ParkingSlot::naturalSortKey($b));

        $this->assertSame(['AD-2', 'AD-10', 'TA-2', 'TA-10'], $labels);
    }
}
