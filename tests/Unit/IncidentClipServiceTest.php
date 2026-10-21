<?php

namespace Tests\Unit;

use App\Models\CameraClip;
use App\Services\AiParkingHealthService;
use App\Services\IncidentClipService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IncidentClipServiceTest extends TestCase
{
    public function test_capture_downloads_clip_when_file_is_not_on_local_disk(): void
    {
        Config::set('services.ai_parking.api_token', 'clip-token');

        $this->mock(AiParkingHealthService::class, function ($mock) {
            $mock->shouldReceive('pythonServiceBaseUrl')->andReturn('http://127.0.0.1:18090');
        });

        $mp4 = str_repeat('m', 64);

        Http::fake([
            'http://127.0.0.1:18090/save-clip' => Http::response([
                'ok' => true,
                'filename' => 'clip-remote-test.mp4',
                'frames' => 12,
                'camera_id' => 'CAM-2',
            ], 200),
            'http://127.0.0.1:18090/incident-clip/clip-remote-test.mp4' => Http::response($mp4, 200, [
                'Content-Type' => 'video/mp4',
            ]),
        ]);

        $local = storage_path('app/private/incident-clips/clip-remote-test.mp4');
        if (is_file($local)) {
            unlink($local);
        }

        $clip = app(IncidentClipService::class)->capture('CAM-2', null, 'manual', 1);

        $this->assertInstanceOf(CameraClip::class, $clip);
        $this->assertSame('CAM-2', $clip->camera_id);
        $this->assertSame('incident-clips/clip-remote-test.mp4', $clip->file_path);
        $this->assertFileExists($local);
        $this->assertGreaterThanOrEqual(32, filesize($local));

        Http::assertSent(function ($request) {
            return $request->url() === 'http://127.0.0.1:18090/save-clip'
                && $request['camera_id'] === 'CAM-2';
        });
        Http::assertSent(function ($request) {
            return $request->url() === 'http://127.0.0.1:18090/incident-clip/clip-remote-test.mp4';
        });

        @unlink($local);
        optional($clip)->delete();
    }

    public function test_capture_surfaces_ai_error_message(): void
    {
        Config::set('services.ai_parking.api_token', 'clip-token');

        $this->mock(AiParkingHealthService::class, function ($mock) {
            $mock->shouldReceive('pythonServiceBaseUrl')->andReturn('http://127.0.0.1:18090');
        });

        Http::fake([
            'http://127.0.0.1:18090/save-clip' => Http::response([
                'ok' => false,
                'message' => 'No recent frames to save.',
            ], 422),
        ]);

        $service = app(IncidentClipService::class);
        $clip = $service->capture('CAM-2');

        $this->assertNull($clip);
        $this->assertSame('No recent frames to save.', $service->failureMessage());
    }
}
