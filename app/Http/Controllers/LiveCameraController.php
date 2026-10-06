<?php

namespace App\Http\Controllers;

use App\Events\AiParkingRealtime;
use App\Models\ParkingArea;
use App\Services\AiCameraRegistry;
use App\Services\AiParkingHealthService;
use App\Services\AiParkingOccupancyService;
use App\Services\WebRtcStream;
use App\Support\PlateLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LiveCameraController extends Controller
{
    public function index(
        AiParkingOccupancyService $ai,
        AiParkingHealthService $health,
        AiCameraRegistry $registry
    ): View {
        $routeName = request()->route()?->getName() ?? '';
        $isGuard = str_contains($routeName, 'guard.');
        $layout = $isGuard ? 'layouts.guard' : 'layouts.portal';

        $cameras = [];
        $areaIds = collect($registry->cameras())->pluck('area_id')->filter()->unique()->values()->all();
        $areasById = $areaIds === []
            ? collect()
            : ParkingArea::query()->whereIn('id', $areaIds)->get()->keyBy('id');

        $webrtc = WebRtcStream::fromConfig();

        foreach ($registry->cameras() as $cam) {
            // Do not probe MJPEG here — that blocked Live Cameras for 60s+.
            // Clean MJPEG for Live Cameras (no YOLO overlay).
            $whepUrl = $webrtc->whepUrl($cam['id']);
            $streamUrl = $whepUrl
                ? null
                : ($health->streamBrowserUrl($cam['id'], false) ?? ($cam['stream_url'] ?? null));
            $snap = $ai->latestSnapshot($cam['id']);
            $area = $areasById->get($cam['area_id']);
            $parkingUrl = $isGuard
                ? route('guard.parking', ['zone_id' => $cam['area_id']])
                : route('admin.parking', ['zone_id' => $cam['area_id']]);

            $hasStream = filled($whepUrl) || filled($streamUrl);
            // Live only when the AI service reports RTSP online for this camera.
            // Blank placeholder MJPEG frames must not count as online.
            $online = $health->isCameraOnline($cam['id']);
            $ingestActive = $health->isIngestActive($cam['id']);

            $cameras[] = [
                'id' => strtolower(str_replace('_', '-', $cam['id'])),
                'camera_id' => $cam['id'],
                'name' => filled($cam['name'] ?? null) ? $cam['name'] : ($area?->area_name ?? 'Camera'),
                'location' => $cam['location'],
                'stream_url' => $streamUrl,
                'whep_url' => $whepUrl,
                'playback' => $whepUrl ? 'webrtc' : 'mjpeg',
                'has_stream' => $hasStream,
                'online' => $online,
                'ai_monitored' => false,
                'parking_url' => $parkingUrl,
                'vehicle_count' => $snap['vehicle_count'] ?? null,
                'occupied' => $snap['occupied'] ?? null,
                'available' => $snap['available'] ?? null,
                'updated_at_label' => $snap['updated_at_label'] ?? null,
                'ingest_active' => $ingestActive,
            ];
        }

        // Only show configured AI CCTV feeds on Live Cameras (no fake offline placeholders).

        $primary = $registry->primaryCameraId();
        $areaId = $registry->resolveAreaId($primary);
        $area = ParkingArea::query()->find($areaId);
        $streamUrl = $health->streamBrowserUrl($primary);
        $aiHealth = $health->statusFast($isGuard, $primary);

        $total = count($cameras);
        $online = collect($cameras)->where('online', true)->count();

        return view('cameras.live', [
            'layout' => $layout,
            'streamUrl' => $streamUrl,
            'ai' => $ai->latestSnapshot($primary),
            'aiCameras' => $ai->allSnapshots(),
            'aiHealth' => $aiHealth,
            'aiCamerasHealth' => $health->statusAll($isGuard, false),
            'aiAreaId' => $areaId,
            'aiAreaName' => $area?->area_name ?? 'Parking area',
            'cameras' => $cameras,
            'cameraStats' => [
                'total' => $total,
                'online' => $online,
                'offline' => $total - $online,
            ],
            'statusUrl' => $isGuard
                ? route('guard.parking.status')
                : route('admin.parking.status'),
            'parkingUrl' => $isGuard
                ? route('guard.parking', ['zone_id' => $areaId])
                : route('admin.parking', ['zone_id' => $areaId]),
        ]);
    }

    public function aiMonitor(
        AiParkingOccupancyService $ai,
        AiParkingHealthService $health,
        AiCameraRegistry $registry
    ): View {
        $primary = $registry->primaryCameraId();
        $areaId = $registry->resolveAreaId($primary);
        $area = ParkingArea::query()->find($areaId);
        $aiHealth = $health->statusFast(true, $primary);

        return view('guard.ai-parking-monitor', [
            'streamUrl' => $health->streamBrowserUrl($primary, true),
            'ai' => $ai->latestSnapshot($primary, false),
            'aiDayEvents' => $ai->dayViolationEvents(),
            'aiHealth' => $aiHealth,
            'aiCameras' => $ai->allSnapshots(),
            'aiCamerasHealth' => $health->statusAll(true, false),
            'registryCameras' => $registry->cameras(),
            'aiAreaId' => $areaId,
            'aiAreaName' => $area?->area_name ?? 'Parking area',
            'statusUrl' => route('guard.parking.status'),
            'correctPlateUrl' => route('guard.ai-parking.correct-plate'),
            'aiCropOrigin' => $health->viewerIsRemote() ? null : $health->pythonServiceBaseUrl(),
            'plateCropUrlTemplate' => url('/guard/ai-parking/plate-crop/__CAMERA__/__TRACK__'),
            'parkingUrl' => route('guard.parking', ['zone_id' => $areaId]),
        ]);
    }

    public function stream(AiParkingHealthService $health, ?string $camera = null): RedirectResponse|StreamedResponse|Response
    {
        $withAi = request()->boolean('ai', true);
        $upstream = $this->localAiUrl($health->upstreamStreamUrl($camera, $withAi), 'AI parking stream');

        // On this PC, send the browser straight to port 8090 so php artisan serve
        // is not stuck on one endless video. On iscvms.com the browser cannot
        // open that address, so this site relays the frames.
        if (! $health->viewerIsRemote()) {
            return redirect()->away($upstream);
        }

        return response()->stream(function () use ($upstream) {
            $handle = @fopen($upstream, 'rb', false, stream_context_create([
                'http' => ['timeout' => 8],
            ]));
            if ($handle === false) {
                return;
            }
            while (! feof($handle) && ! connection_aborted()) {
                $chunk = fread($handle, 65536);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                echo $chunk;
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
            fclose($handle);
        }, 200, [
            'Content-Type' => 'multipart/x-mixed-replace; boundary=frame',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function status(AiParkingOccupancyService $ai): JsonResponse
    {
        // Release the file session lock before the occupancy read. Otherwise this
        // poll (several times a second) blocks every other page for the same user.
        if (session()->isStarted()) {
            session()->save();
        }

        $zoneFilter = request()->query('zone_id');
        $zoneId = is_numeric($zoneFilter) ? (int) $zoneFilter : null;

        return response()->json($ai->statusPayload($zoneId, request()->boolean('lite')));
    }

    public function correctPlate(Request $request, AiParkingOccupancyService $ai, AiCameraRegistry $registry): JsonResponse
    {
        $validated = $request->validate([
            'camera_id' => ['required', 'string', 'max:64'],
            'track_id' => ['nullable', 'integer', 'min:0'],
            'recognition_session_id' => ['nullable', 'integer', 'min:1'],
            'plate' => ['required', 'string', 'min:4', 'max:32'],
        ]);

        $cameraId = strtoupper(trim($validated['camera_id']));
        $known = collect($registry->cameras())->pluck('id')->map(fn ($id) => strtoupper((string) $id));
        if ($known->isNotEmpty() && ! $known->contains($cameraId) && $ai->latestSnapshot($cameraId) === null) {
            return response()->json(['ok' => false, 'message' => 'Unknown camera.'], 422);
        }

        $trackId = array_key_exists('track_id', $validated) && $validated['track_id'] !== null
            ? (int) $validated['track_id']
            : null;
        $sessionId = array_key_exists('recognition_session_id', $validated) && $validated['recognition_session_id'] !== null
            ? (int) $validated['recognition_session_id']
            : null;

        if ($trackId === null && $sessionId === null) {
            return response()->json(['ok' => false, 'message' => 'Track id is required.'], 422);
        }

        try {
            $identity = $ai->correctPlate(
                $cameraId,
                $trackId,
                $validated['plate'],
                $request->user()?->id,
                $sessionId
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        AiParkingRealtime::emit(AiParkingRealtime::EVENT_PLATE_MANUAL_ENTRY, [
            'cameraId' => $cameraId,
            'trackingId' => $trackId,
            'recognitionSessionId' => $sessionId,
            'plateNumber' => $identity['plate'] ?? PlateLookup::normalize($validated['plate']),
            'source' => 'MANUAL',
            'ownerName' => $identity['owner_name'] ?? $identity['owner_label'] ?? null,
            'userRole' => $identity['owner_role'] ?? $identity['role'] ?? null,
            'vehicleType' => $identity['vehicle_details'] ?? null,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Plate updated.',
            'data' => $identity,
        ]);
    }

    /**
     * Zoom-region plate OCR for monitor testing. Looks up the owner in memory only — never writes Mongo.
     */
    public function testScan(Request $request, AiCameraRegistry $registry, AiParkingHealthService $health): JsonResponse
    {
        $validated = $request->validate([
            'camera_id' => ['required', 'string', 'max:64'],
            'view' => ['nullable', 'array'],
            'view.x' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'view.y' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'view.w' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'view.h' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ]);

        $cameraId = strtoupper(trim($validated['camera_id']));
        $known = collect($registry->cameras())->pluck('id')->map(fn ($id) => strtoupper((string) $id));
        if ($known->isNotEmpty() && ! $known->contains($cameraId)) {
            return response()->json([
                'ok' => false,
                'saved' => false,
                'message' => 'Unknown camera.',
            ], 422);
        }

        $base = $health->pythonServiceBaseUrl();
        if ($base === null) {
            return response()->json([
                'ok' => false,
                'saved' => false,
                'message' => 'AI parking service is not configured.',
            ], 503);
        }

        $token = trim((string) config('services.ai_parking.api_token', ''));

        try {
            $response = Http::connectTimeout(2)
                ->timeout(20)
                ->acceptJson()
                ->withHeaders($token !== '' ? ['X-AI-TOKEN' => $token] : [])
                ->post($base.'/test-scan', [
                    'camera_id' => $cameraId,
                    'view' => $validated['view'] ?? ['x' => 0, 'y' => 0, 'w' => 1, 'h' => 1],
                ]);
        } catch (\Throwable) {
            return response()->json([
                'ok' => false,
                'saved' => false,
                'message' => 'AI parking service is unreachable. Start the YOLO service and try again.',
            ], 503);
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            return response()->json([
                'ok' => false,
                'saved' => false,
                'message' => 'AI parking service returned an invalid response.',
            ], $response->successful() ? 502 : 503);
        }

        $ocrText = trim((string) ($payload['ocr_text'] ?? $payload['plate_text'] ?? $payload['plate'] ?? ''));
        $plateStatus = strtolower(trim((string) ($payload['plate_status'] ?? '')));
        $identity = [
            'registered' => false,
            'owner_name' => null,
            'owner_label' => 'Unknown',
            'registration_status' => 'Plate Not Registered',
            'plate' => $ocrText !== '' ? $ocrText : null,
            'role' => null,
            'vehicle_details' => null,
        ];

        if ($ocrText !== '' && $plateStatus !== 'unreadable') {
            $identity = PlateLookup::identity($ocrText);
        }

        $registered = (bool) ($identity['registered'] ?? false);

        return response()->json([
            'ok' => (bool) ($payload['ok'] ?? $response->successful()),
            'saved' => false,
            'camera_id' => $cameraId,
            'plate' => $registered ? ($identity['plate'] ?: $ocrText) : ($ocrText !== '' ? $ocrText : null),
            'ocr_text' => $ocrText !== '' ? $ocrText : null,
            'plate_status' => $ocrText === '' ? ($plateStatus !== '' ? $plateStatus : 'empty') : 'ok',
            'ocr_confidence' => $payload['ocr_confidence'] ?? null,
            'registered' => $registered,
            'owner_name' => $registered ? ($identity['owner_name'] ?? null) : null,
            'owner_label' => $registered ? ($identity['owner_name'] ?? 'Registered') : 'Unknown',
            'registration_status' => $registered
                ? (string) ($identity['registration_status'] ?? 'Registered')
                : ($ocrText !== '' ? 'Plate Not Registered' : 'Plate Unreadable'),
            'vehicle_details' => $identity['vehicle_details'] ?? null,
            'crop_jpeg_base64' => $payload['crop_jpeg_base64'] ?? null,
            'message' => $payload['message'] ?? ($ocrText === '' ? 'Plate Unreadable' : null),
        ]);
    }

    public function plateCrop(string $camera, int $track, AiCameraRegistry $registry, AiParkingHealthService $health): RedirectResponse|Response
    {
        return $this->redirectTrackCrop($camera, $track, 'plate-crop', $registry, $health);
    }

    public function vehicleCrop(string $camera, int $track, AiCameraRegistry $registry, AiParkingHealthService $health): RedirectResponse|Response
    {
        return $this->redirectTrackCrop($camera, $track, 'vehicle-crop', $registry, $health);
    }

    /**
     * Redirect to the local AI JPEG — never proxy through php artisan serve
     * (monitor polls crops every second and that freezes occupancy/status).
     */
    private function redirectTrackCrop(
        string $camera,
        int $track,
        string $kind,
        AiCameraRegistry $registry,
        AiParkingHealthService $health
    ): RedirectResponse|Response {
        $cameraId = strtoupper(trim($camera));
        $known = collect($registry->cameras())->pluck('id')->map(fn ($id) => strtoupper((string) $id));
        if ($known->isNotEmpty() && ! $known->contains($cameraId)) {
            abort(404);
        }

        $base = $health->pythonServiceBaseUrl();
        if ($base === null) {
            abort(503, 'AI parking service is not configured.');
        }

        $kind = $kind === 'vehicle-crop' ? 'vehicle-crop' : 'plate-crop';
        $upstream = $this->localAiUrl($base.'/'.rawurlencode($cameraId).'/'.$kind.'/'.$track.'.jpg', 'AI parking crop');

        if (! $health->viewerIsRemote()) {
            return redirect()->away($upstream);
        }

        try {
            $response = Http::connectTimeout(2)->timeout(4)->get($upstream);
        } catch (\Throwable) {
            abort(503, 'AI parking service is unreachable.');
        }

        if (! $response->successful()) {
            abort($response->status() === 404 ? 404 : 503);
        }

        return response($response->body(), 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function localAiUrl(?string $upstream, string $label): string
    {
        if ($upstream === null || trim($upstream) === '') {
            abort(503, $label.' is not configured.');
        }

        $host = strtolower((string) parse_url($upstream, PHP_URL_HOST));
        if (! in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            abort(503, $label.' host is not allowed.');
        }

        return $upstream;
    }
}
