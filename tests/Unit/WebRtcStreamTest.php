<?php

namespace Tests\Unit;

use App\Services\WebRtcStream;
use PHPUnit\Framework\TestCase;

class WebRtcStreamTest extends TestCase
{
    public function test_path_matches_the_mediamtx_config_writer(): void
    {
        $stream = new WebRtcStream('http://127.0.0.1:8889');

        $this->assertSame('cam-ai-1', $stream->pathFor('CAM-AI-1'));
        $this->assertSame('cam-ai-2', $stream->pathFor('CAM-AI-2'));
        $this->assertSame('cam-ai-3', $stream->pathFor('CAM-AI-3'));
    }

    public function test_whep_url_has_no_rtsp_credentials(): void
    {
        $stream = new WebRtcStream('http://192.168.1.50:8889/');

        $url = $stream->whepUrl('CAM-AI-1');

        $this->assertSame('http://192.168.1.50:8889/cam-ai-1/whep', $url);
        $this->assertStringNotContainsString('rtsp://', (string) $url);
        $this->assertStringNotContainsString('@', (string) $url);
    }

    public function test_empty_base_disables_webrtc(): void
    {
        $stream = new WebRtcStream('');

        $this->assertFalse($stream->enabled());
        $this->assertNull($stream->whepUrl('CAM-AI-1'));
    }
}
