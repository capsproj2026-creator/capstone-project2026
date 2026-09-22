<?php

/**
 * One-shot checkup: CAM-1/CAM-2 → areas → admin/guard/user parking status.
 * Usage: php scripts/check-ai-parking-propagation.php
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ParkingArea;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Services\AiCameraRegistry;
use App\Services\AiParkingOccupancyService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;

$token = (string) config('services.ai_parking.api_token');
$registry = app(AiCameraRegistry::class);

echo "=== Camera registry (from .env / config) ===\n";
foreach ($registry->cameras() as $cam) {
    echo sprintf(
        "  %s → area_id=%d (%s) enabled stream=%s\n",
        $cam['id'],
        $cam['area_id'],
        $cam['name'],
        $cam['stream_url'] ?? ''
    );
}
echo 'Monitored area IDs: '.implode(', ', $registry->monitoredAreaIds())."\n\n";

$cam1 = $registry->find('CAM-1') ?? $registry->find('CAM-AI-1');
$cam2 = $registry->find('CAM-2') ?? $registry->find('CAM-AI-2');

if (! $cam1 || ! $cam2) {
    fwrite(STDERR, "FAIL: Need both CAM-1 and CAM-2 (or CAM-AI-1 / CAM-AI-2) enabled in .env\n");
    exit(1);
}

$area1 = (int) $cam1['area_id'];
$area2 = (int) $cam2['area_id'];

echo "=== Parking areas ===\n";
foreach ([$area1, $area2] as $id) {
    $area = ParkingArea::query()->find($id);
    if (! $area) {
        echo "  area {$id}: MISSING\n";
        continue;
    }
    $slotCount = ParkingSlot::query()->where('area_id', $id)->count();
    echo sprintf(
        "  #%d %s | slots=%d | visible=%s | roles=%s\n",
        $id,
        $area->area_name,
        $slotCount,
        $area->isVisibleToUsers() ? 'yes' : 'no',
        json_encode($area->getAllowedRoles())
    );
    $samples = ParkingSlot::query()->where('area_id', $id)->orderBy('slot_number')->limit(5)->get(['slot_number', 'status']);
    foreach ($samples as $s) {
        echo "    {$s->slot_number} = {$s->status}\n";
    }
}
echo "\n";

$pickSlots = function (int $areaId, int $take) {
    return ParkingSlot::query()
        ->where('area_id', $areaId)
        ->whereNotIn('status', ['Maintenance', 'Reserved'])
        ->orderBy('slot_number')
        ->limit($take)
        ->get();
};

$slotsA = $pickSlots($area1, 2);
$slotsB = $pickSlots($area2, 2);

if ($slotsA->count() < 1 || $slotsB->count() < 1) {
    fwrite(STDERR, "FAIL: Need at least one writable slot in each camera area.\n");
    exit(1);
}

// Snapshot previous statuses so we can restore.
$restore = [];
foreach ($slotsA->concat($slotsB) as $slot) {
    $restore[(int) $slot->id] = [
        'status' => $slot->status,
        'parked_user_id' => $slot->parked_user_id,
        'parked_visitor_id' => $slot->parked_visitor_id ?? null,
    ];
}

$payloadFor = function (array $cam, $slots) {
    return [
        'camera_id' => $cam['id'],
        'area_id' => 9999, // should be ignored
        'vehicle_count' => $slots->count(),
        'mode' => 'slots',
        'slots' => $slots->map(fn ($s) => [
            'slot_number' => $s->slot_number,
            'occupied' => true,
        ])->values()->all(),
        'detections' => $slots->map(fn () => [
            'class' => 'car',
            'confidence' => 0.91,
        ])->values()->all(),
    ];
};

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$postOccupancy = function (array $payload) use ($kernel, $token) {
    $request = Illuminate\Http\Request::create(
        '/api/ai-parking/occupancy',
        'POST',
        [],
        [],
        [],
        [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_AI_TOKEN' => $token,
        ],
        json_encode($payload)
    );
    $response = $kernel->handle($request);
    $body = json_decode($response->getContent(), true);
    $kernel->terminate($request, $response);

    return [$response->getStatusCode(), $body];
};

echo "=== POST occupancy (simulate detections) ===\n";
[$code1, $body1] = $postOccupancy($payloadFor($cam1, $slotsA));
[$code2, $body2] = $postOccupancy($payloadFor($cam2, $slotsB));

echo "  {$cam1['id']}: HTTP {$code1} area=".($body1['data']['area_id'] ?? '?')
    .' occupied='.($body1['data']['occupied'] ?? '?')."\n";
echo "  {$cam2['id']}: HTTP {$code2} area=".($body2['data']['area_id'] ?? '?')
    .' occupied='.($body2['data']['occupied'] ?? '?')."\n";

$ok = $code1 === 200 && $code2 === 200
    && (int) ($body1['data']['area_id'] ?? 0) === $area1
    && (int) ($body2['data']['area_id'] ?? 0) === $area2;

foreach ($slotsA as $s) {
    $fresh = ParkingSlot::query()->find($s->id);
    $mark = $fresh && $fresh->status === 'Occupied' ? 'OK' : 'FAIL';
    echo "  DB {$s->slot_number} (area {$area1}) → ".($fresh->status ?? 'missing')." [{$mark}]\n";
    $ok = $ok && $mark === 'OK';
}
foreach ($slotsB as $s) {
    $fresh = ParkingSlot::query()->find($s->id);
    $mark = $fresh && $fresh->status === 'Occupied' ? 'OK' : 'FAIL';
    echo "  DB {$s->slot_number} (area {$area2}) → ".($fresh->status ?? 'missing')." [{$mark}]\n";
    $ok = $ok && $mark === 'OK';
}

$loginAs = function (string $email) use ($kernel) {
    $user = User::query()->where('email', $email)->first();
    if (! $user) {
        return null;
    }
    auth()->login($user);

    return $user;
};

$getJson = function (string $uri, $user) use ($kernel) {
    $request = Illuminate\Http\Request::create($uri, 'GET', [], [], [], [
        'HTTP_ACCEPT' => 'application/json',
    ]);
    $request->setLaravelSession(app('session.store'));
    $request->setUserResolver(static fn () => $user);
    auth()->guard('web')->setUser($user);
    auth()->shouldUse('web');
    $response = $kernel->handle($request);
    $body = json_decode($response->getContent(), true);
    $kernel->terminate($request, $response);

    return [$response->getStatusCode(), $body];
};

$servicePayload = function () {
    return app(AiParkingOccupancyService::class)->statusPayload();
};

$zoneOccupied = function (?array $body, int $areaId): ?int {
    foreach (($body['zones'] ?? []) as $zone) {
        if ((int) ($zone['id'] ?? 0) === $areaId) {
            return (int) ($zone['occupied'] ?? 0);
        }
    }

    return null;
};

echo "\n=== Portal status endpoints ===\n";

$shared = $servicePayload();
$sharedOcc1 = $zoneOccupied($shared, $area1);
$sharedOcc2 = $zoneOccupied($shared, $area2);
echo sprintf(
    "  shared statusPayload | area%d occupied=%s | area%d occupied=%s\n",
    $area1,
    $sharedOcc1 === null ? 'not listed' : (string) $sharedOcc1,
    $area2,
    $sharedOcc2 === null ? 'not listed' : (string) $sharedOcc2
);
$ok = $ok && $sharedOcc1 !== null && $sharedOcc2 !== null && $sharedOcc1 >= 1 && $sharedOcc2 >= 1;

$checks = [
    ['admin@my.cspc.edu.ph', '/admin/parking/status', 'admin'],
    ['guard@my.cspc.edu.ph', '/guard/parking/status', 'guard'],
    ['student@my.cspc.edu.ph', '/user/parking/status', 'student'],
    ['staff@my.cspc.edu.ph', '/user/parking/status', 'staff'],
];

// Fall back to any seeded student/staff if those emails differ
$fallbackRole = function (string $role) {
    return User::query()->whereHas('role', fn ($q) => $q->where('role_name', $role))->first();
};

foreach ($checks as [$email, $uri, $label]) {
    auth()->logout();
    $user = User::query()->where('email', $email)->first();
    if (! $user) {
        $roleName = match ($label) {
            'admin' => 'Admin',
            'guard' => 'Guard',
            'student' => 'Student',
            'staff' => 'Staff',
            default => null,
        };
        $user = $roleName ? $fallbackRole($roleName) : null;
    }
    if (! $user) {
        echo "  {$label}: SKIP (no user)\n";
        continue;
    }
    [$code, $body] = $getJson($uri, $user);
    $occ1 = $zoneOccupied($body, $area1);
    $occ2 = $zoneOccupied($body, $area2);
    $has1 = $occ1 !== null;
    $has2 = $occ2 !== null;
    echo sprintf(
        "  %s (%s) HTTP %d | area%d occupied=%s | area%d occupied=%s\n",
        $label,
        $user->email,
        $code,
        $area1,
        $has1 ? (string) $occ1 : 'not listed',
        $area2,
        $has2 ? (string) $occ2 : 'not listed'
    );

    if (in_array($label, ['admin', 'guard'], true) && $code === 200) {
        $ok = $ok && $has1 && $has2 && $occ1 >= 1 && $occ2 >= 1;
    }
    if ($label === 'staff' && $code === 200) {
        $areaModel1 = ParkingArea::query()->find($area1);
        $areaModel2 = ParkingArea::query()->find($area2);
        $staffShouldSee1 = $areaModel1 && $areaModel1->isVisibleToUser('Staff');
        $staffShouldSee2 = $areaModel2 && $areaModel2->isVisibleToUser('Staff');
        if ($staffShouldSee1) {
            $ok = $ok && $has1 && $occ1 >= 1;
        }
        if ($staffShouldSee2) {
            $ok = $ok && $has2 && $occ2 >= 1;
        }
        if (! $staffShouldSee1 && ! $staffShouldSee2) {
            echo "  note: Staff user parking hides Acad1/Duran (is_visible/roles). Admin/Guard still see live slots.\n";
        }
    }
}

// Restore slots
echo "\n=== Restoring previous slot statuses ===\n";
foreach ($restore as $id => $prev) {
    ParkingSlot::query()->where('id', $id)->update($prev);
    echo "  restored slot {$id} → {$prev['status']}\n";
}

echo "\n".($ok ? "RESULT: PASS — occupancy propagates to DB and admin/guard status.\n" : "RESULT: FAIL — see details above.\n");
exit($ok ? 0 : 1);
