<?php

namespace Tests\Unit;

use App\Support\AiDetectionPresenter;
use Tests\TestCase;

class AiDetectionPresenterTest extends TestCase
{
    public function test_scanning_label_shows_attempt_count(): void
    {
        $label = AiDetectionPresenter::unresolvedPlateLabel([
            'plate_status' => 'pending',
            'ocr_attempts' => 4,
            'ocr_max_attempts' => 10,
            'motion_state' => 'parked',
        ]);

        $this->assertSame('Scanning... 4/10', $label);
    }

    public function test_plate_not_read_line_stays_explicit(): void
    {
        $line = AiDetectionPresenter::plateLine([
            'plate_status' => 'not_read',
            'class' => 'car',
            'motion_state' => 'parked',
        ]);

        $this->assertStringContainsString('Plate Not Read', $line);
        $this->assertStringNotContainsString('UNKNOWN', $line);
    }
}
